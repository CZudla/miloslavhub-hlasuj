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
function feedback_check(bool $ok,string $message):void{
    $GLOBALS['checks']++; if(!$ok){throw new RuntimeException($message);}
}
$admin=get_user_by('login','integration_admin'); wp_set_current_user($admin->ID);
$subject=wp_insert_post(array('post_type'=>'mhl_subject','post_status'=>'publish','post_title'=>'Feedback synthetic subject'));
$lecture=wp_insert_post(array('post_type'=>'mhl_lecture','post_status'=>'publish','post_title'=>'Feedback synthetic lesson'));
$question=wp_insert_post(array('post_type'=>'mhl_question','post_status'=>'publish','post_title'=>'Feedback synthetic question'));
update_post_meta($lecture,'_mhl_subject_id',$subject); update_post_meta($lecture,'_mhl_question_ids',array($question));
update_post_meta($question,'_mhl_options',array('First','Second')); update_post_meta($question,'_mhl_correct_index',0);
$text="Řešení \\ a učitelův příklad.\nDalší řádek.";
update_post_meta($question,'_mhl_correct_answer_explanation',wp_slash($text));
update_post_meta($question,'_mhl_teacher_note','PRIVATE-TEACHER-NOTE');
feedback_check(MHL_Core::question_explanation_mode($question)==='teacher_only','Missing policy is private');
foreach(array('teacher_only','show_after_close','hidden','invalid') as $policy){
    update_post_meta($question,'_mhl_explanation_mode',$policy);
    foreach(array(null,'idle','joining','open','closed','skipped') as $status){
        $session=$status===null?null:(object)array('status'=>$status);
        feedback_check(MHL_Core::public_explanation($question,$session)===($policy==='show_after_close'&&$status==='closed'?$text:null),'Policy and session gate');
    }
}
delete_post_meta($question,'_mhl_correct_index'); update_post_meta($question,'_mhl_explanation_mode','show_after_close');
feedback_check(MHL_Core::public_explanation($question,(object)array('status'=>'closed'))===null,'Poll never exposes quiz explanation');
update_post_meta($question,'_mhl_correct_index',0);
$ls=MHL_Core::permanent_slug($lecture); $qs=MHL_Core::permanent_slug($question);
[$run,$session]=MHL_Core::activate_question($lecture,$question,'live',true);
feedback_check($run&&$session&&$session->status==='open','Synthetic question opened by authorized teacher');
$db=MHL_DB::db(); $db->update(MHL_DB::table('sessions'),array('status'=>'closed','closed_at'=>MHL_Core::now_mysql()),array('id'=>$session->id));
wp_set_current_user(0);
foreach(array('teacher_only','show_after_close','hidden','invalid') as $policy){
    update_post_meta($question,'_mhl_explanation_mode',$policy);
    foreach(array("/question/$ls/$qs","/results/$ls/$qs","/lecture/$ls/current") as $path){
        $request=new WP_REST_Request('GET','/mhl/v1'.$path); $response=rest_do_request($request);
        feedback_check($response->get_status()===200,'Public route returns success');
        $data=$response->get_data(); $json=wp_json_encode($data);
        feedback_check(!str_contains($json,'PRIVATE-TEACHER-NOTE')&&!str_contains($json,'teacher_note'),'Private teacher note never enters payload');
        if(str_starts_with($path,'/results/')){
            feedback_check($data['correct_answer_explanation']===($policy==='show_after_close'?$text:null),'Anonymous results enforce policy on server');
        }else{feedback_check(!str_contains($json,'correct_answer_explanation'),'Question/current payload does not send feedback text');}
    }
}
wp_set_current_user($admin->ID);
$db->update(MHL_DB::table('sessions'),array('status'=>'open','closed_at'=>null,'reset_at'=>null),array('id'=>$session->id));
update_post_meta($question,'_mhl_explanation_mode','show_after_close');
wp_set_current_user(0);
$response=rest_do_request(new WP_REST_Request('GET',"/mhl/v1/results/$ls/$qs"));
feedback_check($response->get_status()===200&&$response->get_data()['correct_answer_explanation']===null,'Anonymous open results never reveal the explanation');
wp_set_current_user($admin->ID);
$post=array('mhl_question_nonce'=>wp_create_nonce('mhl_save_question'),'mhl_options'=>array('First','Second'),'mhl_correct_index'=>'0',
    'mhl_correct_answer_explanation'=>wp_slash('<b>'.$text.'</b>'),'mhl_teacher_note'=>wp_slash("Poznámka \\ soukromá"),'mhl_explanation_mode'=>'show_after_close');
$_POST=$post; MHL_Admin::save_question($question);
feedback_check(get_post_meta($question,'_mhl_correct_answer_explanation',true)===$text,'Editor sanitizes markup and preserves slashes, UTF-8 and lines');
feedback_check(get_post_meta($question,'_mhl_teacher_note',true)==="Poznámka \\ soukromá",'Editor stores private note');
$_POST['mhl_question_nonce']='bad'; $_POST['mhl_teacher_note']='REPLACEMENT'; MHL_Admin::save_question($question);
feedback_check(get_post_meta($question,'_mhl_teacher_note',true)!=='REPLACEMENT','Bad nonce cannot modify private notes');
$_POST=$post; $_POST['mhl_teacher_note']='REPLACEMENT'; wp_set_current_user(0); MHL_Admin::save_question($question);
feedback_check(get_post_meta($question,'_mhl_teacher_note',true)!=='REPLACEMENT','Anonymous editor cannot modify notes');
wp_set_current_user($admin->ID);
$_POST=$post; $_POST['mhl_explanation_mode']='invalid'; MHL_Admin::save_question($question);
feedback_check(MHL_Core::question_explanation_mode($question)==='teacher_only','Invalid policy falls back to private');
$_POST=$post; $_POST['mhl_correct_answer_explanation']=str_repeat('č',3000); MHL_Admin::save_question($question);
$stored=get_post_meta($question,'_mhl_correct_answer_explanation',true);
feedback_check(strlen($stored)<=4000&&preg_match('//u',$stored)===1,'Byte limit preserves valid UTF-8');
$_POST=$post; MHL_Admin::save_question($question);
unset($_POST['mhl_teacher_note'],$_POST['mhl_correct_answer_explanation'],$_POST['mhl_explanation_mode']); MHL_Admin::save_question($question);
feedback_check(get_post_meta($question,'_mhl_correct_answer_explanation',true)===$text&&MHL_Core::question_explanation_mode($question)==='show_after_close','Partial editor preserves text and policy');
ob_start(); MHL_Admin::question_meta_box(get_post($question)); $html=ob_get_clean();
feedback_check(str_contains($html,'Moje poznámka k výuce')&&str_contains($html,'Studenti po ukončení hlasování'),'Pedagogical editor exposes feedback controls');
$bundle=MHL_Content_Transfer::export_subject($subject);
feedback_check($bundle['format_version']===2&&$bundle['questions'][0]['settings']['teacher_note']==="Poznámka \\ soukromá",'Authorized content export includes teacher material');
$preview=MHL_Content_Transfer::preview(wp_json_encode($bundle)); $copy=MHL_Content_Transfer::confirm($preview['token']);
feedback_check(MHL_Content_Transfer::export_subject($copy['subject_id'])===$bundle,'V2 feedback and notes round trip');
foreach(array('policy','note_type','explanation_type','note_limit','legacy_fields') as $case){
    $invalid=$bundle;
    switch($case){
        case 'policy':$invalid['questions'][0]['settings']['explanation_mode']='always_public';break;
        case 'note_type':$invalid['questions'][0]['settings']['teacher_note']=array('bad');break;
        case 'explanation_type':$invalid['questions'][0]['settings']['correct_answer_explanation']=false;break;
        case 'note_limit':$invalid['questions'][0]['settings']['teacher_note']=str_repeat('x',4001);break;
        case 'legacy_fields':$invalid['format_version']=1;break;
    }
    $rejected=false;
    try{MHL_Content_Transfer::validate_json(wp_json_encode($invalid));}catch(RuntimeException $e){$rejected=true;}
    feedback_check($rejected,'Invalid feedback import rejected: '.$case);
}
$legacy=$bundle; $legacy['format_version']=1;
foreach($legacy['questions'] as &$q){foreach(array('teacher_note','correct_answer_explanation','explanation_mode') as $field){unset($q['settings'][$field]);}}unset($q);
$preview=MHL_Content_Transfer::preview(wp_json_encode($legacy)); $copy=MHL_Content_Transfer::confirm($preview['token']);
$copyq=array_values(array_filter($copy['created_ids'],static fn($id)=>get_post_type($id)==='mhl_question'))[0];
feedback_check(MHL_Core::question_explanation_mode($copyq)==='teacher_only'&&get_post_meta($copyq,'_mhl_teacher_note',true)==='','Legacy V1 imports with private empty defaults');
$_POST=array();
echo wp_json_encode(array('status'=>'passed','checks'=>$checks,'question_id'=>$question,'actual_wordpress_database'=>true,'production_data_used'=>false));
