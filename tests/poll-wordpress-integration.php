<?php
declare(strict_types=1);
$root=getenv('MHL_TEST_WP_PATH')?:'';
$config=$root.'/wp-config.php';
if(!is_file($config)||!str_contains(file_get_contents($config),"'integration_wp'")||!str_contains(file_get_contents($config),"'127.0.0.1:")){
    throw new RuntimeException('Refusing non-isolated WordPress.');
}
$_SERVER['HTTP_HOST']='127.0.0.1'; $_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/';
require $root.'/wp-load.php';
require_once $root.'/wp-content/plugins/miloslavhub-live/miloslavhub-live.php';
MHL_Access::init(); MHL_Access::register(); MHL_Core::init(); MHL_Core::register_content_types(); MHL_REST::init();
$checks=0;
function poll_check(bool $ok,string $message):void{$GLOBALS['checks']++;if(!$ok){throw new RuntimeException($message);}}
function poll_request(string $method,string $path,array $data=array()):WP_REST_Response{
    $r=new WP_REST_Request($method,'/mhl/v1'.$path);
    if($method==='POST'){$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($data));}
    else{$r->set_query_params($data);}
    return rest_do_request($r);
}
$admin=get_user_by('login','integration_admin');wp_set_current_user($admin->ID);
$subject=wp_insert_post(array('post_type'=>'mhl_subject','post_status'=>'publish','post_title'=>'Neutral synthetic subject'));
$lecture=wp_insert_post(array('post_type'=>'mhl_lecture','post_status'=>'publish','post_title'=>'Neutral synthetic lesson'));
$quiz=wp_insert_post(array('post_type'=>'mhl_question','post_status'=>'publish','post_title'=>'Scored synthetic quiz'));
$poll=wp_insert_post(array('post_type'=>'mhl_question','post_status'=>'publish','post_title'=>'Neutral synthetic poll'));
update_post_meta($lecture,'_mhl_subject_id',$subject);update_post_meta($lecture,'_mhl_question_ids',array($quiz,$poll));
update_post_meta($lecture,'_mhl_gamification',1);update_post_meta($lecture,'_mhl_score_scope','lecture');
foreach(array($quiz,$poll) as $id){update_post_meta($id,'_mhl_options',array('First','Second'));update_post_meta($id,'_mhl_time_limit',0);}
update_post_meta($quiz,'_mhl_correct_index',0);
update_post_meta($poll,'_mhl_poll_points',1000); // Existing exports/meta must never reactivate scoring.
update_post_meta($poll,'_mhl_async_enabled',1);
$ls=MHL_Core::permanent_slug($lecture);$qs=MHL_Core::permanent_slug($quiz);$ps=MHL_Core::permanent_slug($poll);
$db=MHL_DB::db();$votes=MHL_DB::table('votes');$keys=array();
foreach(array('live','test','async') as $mode){
    $quiz_before=null;
    wp_set_current_user($admin->ID);
    if($mode!=='async'){
        [$run,$session]=MHL_Core::activate_question($lecture,$quiz,$mode,true);
        wp_set_current_user(0);
        $response=poll_request('POST','/vote',array('lecture_slug'=>$ls,'question_slug'=>$qs,'mode'=>$mode,'participant_id'=>str_repeat('q',32),'nickname'=>'Synthetic quiz participant','option_index'=>0));
        poll_check($response->get_status()===201,'Quiz control vote is accepted');
        $quiz_before=$db->get_row($db->prepare("SELECT * FROM {$votes} WHERE session_id=%d",(int)$session->id),ARRAY_A);
        poll_check((int)$quiz_before['points']>0&&(int)$quiz_before['is_correct']===1,'Quiz still receives correctness points');
        wp_set_current_user($admin->ID);MHL_Core::change_session((int)$session->id,'close');
    }
    [$run,$session]=MHL_Core::activate_question($lecture,$poll,$mode,true);
    poll_check($run&&$session&&$session->status==='open','Poll opens in '.$mode);
    wp_set_current_user(0);
    $data=array('lecture_slug'=>$ls,'question_slug'=>$ps,'mode'=>$mode,'participant_id'=>str_repeat('p',32),'option_index'=>1);
    $payload=poll_request('GET',"/question/$ls/$ps",array('mode'=>$mode))->get_data();
    poll_check($payload['mode']==='poll'&&!$payload['gamification']['nickname_required']&&!$payload['gamification']['join_nickname_required'],'Legacy points cannot require a poll nickname');
    $response=poll_request('POST','/vote',$data);
    poll_check($response->get_status()===201,'Anonymous poll vote is accepted in '.$mode);
    $stored=$db->get_row($db->prepare("SELECT * FROM {$votes} WHERE session_id=%d",(int)$session->id),ARRAY_A);
    poll_check((int)$stored['points']===0&&$stored['is_correct']===null,'Poll has no points or correctness');
    poll_check($stored['nickname']==='','Poll stores no nickname');
    poll_check($stored['participant_key']!==MHL_Core::participant_key($data['participant_id']),'Poll uses an unlinkable session key');
    $keys[]=$stored['participant_key'];
    poll_check(poll_request('POST','/vote',$data)->get_status()===409,'Duplicate anonymous poll vote is rejected');
    $second=array_replace($data,array('participant_id'=>str_repeat('r',32),'nickname'=>'MUST-NOT-BE-STORED'));
    poll_check(poll_request('POST','/vote',$second)->get_status()===201,'Stored browser nickname does not prevent a poll vote');
    $second_vote=$db->get_row($db->prepare("SELECT * FROM {$votes} WHERE session_id=%d AND participant_key<>%s",(int)$session->id,$stored['participant_key']));
    poll_check($second_vote->nickname===''&&(int)$second_vote->points===0&&$second_vote->is_correct===null,'Poll discards an explicitly supplied nickname');
    wp_set_current_user($admin->ID);MHL_Core::change_session((int)$session->id,'close');wp_set_current_user(0);
    $results=poll_request('GET',"/results/$ls/$ps",array('mode'=>$mode,'participant_id'=>$data['participant_id']))->get_data();
    poll_check($results['total']===2&&$results['correct_index']===null&&$results['question_leaderboard']===array(),'Poll results are neutral aggregates');
    $own=$results['participant_result'];
    poll_check($own&&$own['option_index']===1&&$own['points']===0&&$own['is_correct']===null&&$own['nickname']===''&&$own['total_rank']===null,'Only own neutral answer is returned');
    poll_check(poll_request('GET',"/results/$ls/$ps",array('mode'=>$mode,'participant_id'=>str_repeat('z',32)))->get_data()['participant_result']===null,'Another device cannot read this answer');
    if($quiz_before){
        poll_check(count($results['overall_leaderboard'])===1&&(int)$results['overall_leaderboard'][0]['points']===(int)$quiz_before['points'],'Overall quiz score is unchanged after a poll');
        poll_check($db->get_row($db->prepare("SELECT * FROM {$votes} WHERE id=%d",(int)$quiz_before['id']),ARRAY_A)===$quiz_before,'Historical quiz row remains byte-for-byte unchanged');
    }
}
poll_check(count(array_unique($keys))===3,'Same device has a different poll key in every session');
wp_set_current_user($admin->ID);
ob_start();MHL_Admin::question_meta_box(get_post($poll));$html=ob_get_clean();
poll_check(!str_contains($html,'name="mhl_poll_points"')&&str_contains($html,'Anketa nemá správnou odpověď'),'Teacher editor offers no poll scoring control');
$_POST=array('mhl_question_nonce'=>wp_create_nonce('mhl_save_question'),'mhl_options'=>array('First','Second'),'mhl_correct_index'=>'-1','mhl_poll_points'=>999);
MHL_Admin::save_question($poll);$_POST=array();
poll_check((int)get_post_meta($poll,'_mhl_poll_points',true)===1000,'Forged legacy scoring control is ignored without deleting existing metadata');
$bundle=MHL_Content_Transfer::export_subject($subject);
$preview=MHL_Content_Transfer::preview(wp_json_encode($bundle));$copy=MHL_Content_Transfer::confirm($preview['token']);
poll_check(MHL_Content_Transfer::export_subject($copy['subject_id'])===$bundle,'Legacy scoring metadata remains portable but inert');
// Exercise the exact internal operation used by the nonce-protected Test Lab action.
$run=MHL_Core::create_run($lecture,'test',(int)$admin->ID,true);
[$run,$session]=MHL_Core::activate_question($lecture,$poll,'test',true);
poll_check($run&&$session&&$session->status==='open','Fresh test poll is ready for simulation');
wp_set_current_user(0);
poll_check(is_wp_error(MHL_Admin::simulate_session_votes((int)$run->id,(int)$session->id)),'Anonymous simulation is denied');
poll_check((int)$db->get_var($db->prepare("SELECT COUNT(*) FROM {$votes} WHERE session_id=%d",(int)$session->id))===0,'Denied simulation stores nothing');
wp_set_current_user($admin->ID);
poll_check(MHL_Admin::simulate_session_votes((int)$run->id,(int)$session->id)===true,'Authorized test simulation succeeds');
$rows=$db->get_results($db->prepare("SELECT * FROM {$votes} WHERE session_id=%d",(int)$session->id));
poll_check(count($rows)===5,'Test Lab creates five synthetic votes');
poll_check(count(array_filter($rows,static fn($v)=>(int)$v->points!==0||$v->is_correct!==null||$v->nickname!==''))===0,'Simulated polls are neutral despite legacy point metadata');
MHL_Core::change_session((int)$session->id,'close');
poll_check(is_wp_error(MHL_Admin::simulate_session_votes((int)$run->id,(int)$session->id)),'Closed-session simulation is denied');
poll_check((int)$db->get_var($db->prepare("SELECT COUNT(*) FROM {$votes} WHERE session_id=%d",(int)$session->id))===5,'Rejected simulation adds no extra votes');
[$run,$session]=MHL_Core::activate_question($lecture,$quiz,'test',true);
poll_check(MHL_Admin::simulate_session_votes((int)$run->id,(int)$session->id)===true,'Quiz simulation still succeeds');
$rows=$db->get_results($db->prepare("SELECT * FROM {$votes} WHERE session_id=%d",(int)$session->id));
poll_check(count($rows)===5&&count(array_filter($rows,static fn($v)=>$v->nickname!==''))===5,'Quiz simulation retains test identities');
poll_check(count(array_filter($rows,static fn($v)=>(int)$v->is_correct===1&&(int)$v->points>0))===3,'Quiz simulation retains correctness scoring');
$live=MHL_Core::get_active_run($lecture,'live');
poll_check(is_wp_error(MHL_Admin::simulate_session_votes((int)$live->id,(int)$session->id)),'Simulation refuses a live run and foreign session');
echo wp_json_encode(array('status'=>'passed','checks'=>$checks,'actual_wordpress_database'=>true,'production_data_used'=>false));
