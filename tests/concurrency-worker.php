<?php
declare(strict_types=1);
// Two CLI workers use separate connections to a disposable, loopback-only site.
$testRoot=getenv('MHL_TEST_WP_PATH')?:'';
$config=$testRoot.'/wp-config.php';
if(!is_file($config)||!str_contains(file_get_contents($config),"'integration_wp'")||!str_contains(file_get_contents($config),"'127.0.0.1:")){
    throw new RuntimeException('Refusing to run outside the isolated local integration site.');
}
$_SERVER['HTTP_HOST']='127.0.0.1:8097';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';
require $testRoot.'/wp-load.php';
require_once $testRoot.'/wp-content/plugins/miloslavhub-live/miloslavhub-live.php';
MHL_Core::register_content_types();MHL_REST::init();
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if (in_array($input['operation']??'',array('setup','finish','delete','close','reset','open_second'),true)) {
    $actor=get_user_by('login','integration_admin');
    if (!$actor) { throw new RuntimeException('Missing isolated teacher actor.'); }
    wp_set_current_user($actor->ID);
}
$db=MHL_DB::db();
$barrier_wait_ms=0.0;
if(!empty($input['barrier'])){
    $barrier=$input['barrier'];
    $directory=realpath($barrier['directory']);
    $allowed=realpath(__DIR__.'/../runtime');
    if(!$directory||!$allowed||!str_starts_with(strtolower($directory),strtolower($allowed).DIRECTORY_SEPARATOR)){
        throw new RuntimeException('Barrier files must stay inside runtime.');
    }
    $hit=false;
    add_filter('query',static function($query) use ($barrier,$directory,&$hit,&$barrier_wait_ms){
        $matches=match($barrier['point']){
            'before_lock','observe_lock'=>str_starts_with($query,'SELECT * FROM mhl_runs WHERE')&&str_ends_with($query,'FOR UPDATE'),
            'after_lock'=>str_starts_with($query,'SELECT * FROM mhl_sessions WHERE')&&str_ends_with($query,'FOR UPDATE'),
            'insert'=>str_starts_with($query,'INSERT INTO `mhl_votes`'),
            default=>false
        };
        if($matches&&!$hit){
            $hit=true;file_put_contents($directory.'/ready','1');
            if($barrier['point']!=='observe_lock'){
                $wait_began=microtime(true);
                $deadline=microtime(true)+min(120,max(15,(int)($barrier['timeout']??15)));
                while(!is_file($directory.'/release')){
                    if(microtime(true)>$deadline){throw new RuntimeException('Barrier timed out');}
                    usleep(10000);
                }
                $barrier_wait_ms+=1000*(microtime(true)-$wait_began);
            }
        }
        return $query;
    });
}
$operation=$input['operation'];
$result=null;
if($operation==='setup'){
    $subject=wp_insert_post(['post_type'=>'mhl_subject','post_status'=>'publish','post_title'=>'Synthetic concurrency subject']);
    $lecture=wp_insert_post(['post_type'=>'mhl_lecture','post_status'=>'publish','post_title'=>'Synthetic concurrency lesson']);
    $questions=[];
    for($i=0;$i<2;$i++){
        $qid=wp_insert_post(['post_type'=>'mhl_question','post_status'=>'publish','post_title'=>'Synthetic concurrent poll '.$i]);
        update_post_meta($qid,'_mhl_options',['A','B']);update_post_meta($qid,'_mhl_question_type','poll');
        update_post_meta($qid,'_mhl_time_limit',120);$questions[]=$qid;
    }
    update_post_meta($lecture,'_mhl_subject_id',$subject);update_post_meta($lecture,'_mhl_question_ids',$questions);
    $mode=$input['mode']??'live';$run=MHL_Core::create_run($lecture,$mode);
    if(!$run){throw new RuntimeException('Fixture run failed');}
    $session=MHL_Core::get_current_session($lecture,$questions[0],(int)$run->id,$mode,false);
    $opened=MHL_Core::change_session((int)$session->id,'open');
    if(is_wp_error($opened)){throw new RuntimeException($opened->get_error_message());}
    $second=MHL_Core::get_current_session($lecture,$questions[1],(int)$run->id,$mode,false);
    $result=['run_id'=>(int)$run->id,'session_id'=>(int)$session->id,'second_id'=>(int)$second->id,'body'=>[
        'lecture_slug'=>MHL_Core::permanent_slug($lecture),'question_slug'=>MHL_Core::permanent_slug($questions[0]),
        'mode'=>$mode,'participant_id'=>str_repeat('c',32),'option_index'=>0
    ]];
}elseif($operation==='vote'){
    $body=$input['fixture']['body'];
    if(isset($input['participant_id'])){$body['participant_id']=$input['participant_id'];}
    $r=new WP_REST_Request('POST','/mhl/v1/vote');$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($body));
    $began=microtime(true);$response=rest_do_request($r);$result=['status'=>$response->get_status(),'data'=>$response->get_data(),'duration_ms'=>max(0,1000*(microtime(true)-$began)-$barrier_wait_ms)];
}elseif($operation==='inspect'){
    $f=$input['fixture'];
    $result=['votes'=>(int)$db->get_var($db->prepare('SELECT COUNT(*) FROM mhl_votes WHERE run_id=%d',$f['run_id'])),
        'run'=>$db->get_var($db->prepare('SELECT status FROM mhl_runs WHERE id=%d',$f['run_id'])),
        'session'=>$db->get_var($db->prepare('SELECT status FROM mhl_sessions WHERE id=%d',$f['session_id'])),
        'latest'=>$db->get_row($db->prepare('SELECT id,status FROM mhl_sessions WHERE run_id=%d AND question_id=(SELECT question_id FROM mhl_sessions WHERE id=%d) ORDER BY id DESC LIMIT 1',$f['run_id'],$f['session_id'])),
        'deadline'=>$db->get_var($db->prepare('SELECT reset_at FROM mhl_sessions WHERE id=%d',$f['session_id']))];
}elseif($operation==='deadline'){
    $result=$db->update('mhl_sessions',['reset_at'=>gmdate('Y-m-d H:i:s',time()+($input['seconds']??2))],['id'=>$input['fixture']['session_id']]);
}elseif($operation==='expire_run'){
    $result=$db->update('mhl_runs',['expires_at'=>gmdate('Y-m-d H:i:s',time()-1)],['id'=>$input['fixture']['run_id']]);
}elseif($operation==='finish'){
    $result=MHL_Core::close_run($input['fixture']['run_id']);
}elseif($operation==='delete'){
    $result=MHL_Core::delete_test_run($input['fixture']['run_id']);
}elseif($operation==='auto_close'){
    $result=MHL_Core::current_open_session($input['fixture']['run_id'],$input['fixture']['body']['mode']);
}elseif(in_array($operation,['close','reset','open_second'],true)){
    $result=MHL_Core::change_session($operation==='open_second'?$input['fixture']['second_id']:$input['fixture']['session_id'],$operation==='open_second'?'open':$operation);
}else{throw new RuntimeException('Unknown operation');}
if(is_wp_error($result)){$result=['error'=>$result->get_error_code()];}
echo wp_json_encode($result,JSON_THROW_ON_ERROR),"\n";
