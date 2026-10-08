<?php
declare(strict_types=1);
$root=getenv('MHL_TEST_WP_PATH')?:'';$config=$root.'/wp-config.php';
if (!is_file($config)||!str_contains(file_get_contents($config),"'integration_wp'")||!str_contains(file_get_contents($config),"'127.0.0.1:")) { throw new RuntimeException('Refusing non-isolated WordPress.'); }
define('WP_ADMIN',true);
$_SERVER['HTTP_HOST']='127.0.0.1';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';
require $root.'/wp-load.php';
require_once $root.'/wp-content/plugins/miloslavhub-live/miloslavhub-live.php';
MHL_Access::init();MHL_Access::register();MHL_Core::init();MHL_Core::register_content_types();MHL_REST::init();
$checks=0;
function acl_check(bool $ok,string $why): void { $GLOBALS['checks']++;if (!$ok) { throw new RuntimeException($why); } }
function acl_request(string $method,string $path,array $data=array()): WP_REST_Response {
    $r=new WP_REST_Request($method,'/mhl/v1'.$path);
    if ($method==='POST') { $r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($data)); } else { $r->set_query_params($data); }
    return rest_do_request($r);
}
function acl_content(int $uid,int $workspace,string $suffix): array {
    wp_set_current_user($uid);update_user_meta($uid,'mhl_active_workspace',$workspace);
    $ids=array();
    foreach (array('subject','lecture','question') as $type) {
        $id=wp_insert_post(array('post_type'=>'mhl_'.$type,'post_status'=>'publish','post_title'=>'Synthetic '.$suffix.' '.$type,'post_name'=>'acl-'.$suffix.'-'.$type));
        acl_check($id>0,'Create synthetic content');$ids[$type]=(int)$id;
        acl_check(MHL_Access::workspace((int)$id)===$workspace,'Server assigns selected workspace');
    }
    update_post_meta($ids['lecture'],'_mhl_subject_id',$ids['subject']);update_post_meta($ids['lecture'],'_mhl_question_ids',array($ids['question']));
    update_post_meta($ids['question'],'_mhl_options',array('Synthetic first','Synthetic second'));
    update_post_meta($ids['question'],'_mhl_time_limit',0);
    return $ids;
}
$admin=get_user_by('login','integration_admin');wp_set_current_user($admin->ID);
$users=array();
foreach (array('a','b','c','viewer','manager') as $name) {
    $id=wp_insert_user(array('user_login'=>'acl-'.$name,'user_pass'=>bin2hex(random_bytes(24)),'user_email'=>'acl-'.$name.'@example.invalid','role'=>'mhl_teacher'));
    acl_check(!is_wp_error($id),'Create distinct local identity');$users[$name]=(int)$id;
    if (getenv('MHL_TEST_ADMIN_PASSWORD')) { wp_set_password(getenv('MHL_TEST_ADMIN_PASSWORD'),$id); }
    update_user_meta($id,'locale',$name==='b'?'en_US':'cs_CZ');
}
$org1=MHL_Access::create_workspace('Synthetic organization A',$users['manager'],'synthetic-org-A');
$org2=MHL_Access::create_workspace('Synthetic organization B',$users['c'],'synthetic-org-B');
acl_check(is_int($org1)&&is_int($org2),'Create separate local application workspaces');
foreach (array('a'=>'teacher','b'=>'teacher','viewer'=>'viewer') as $name=>$role) { acl_check(MHL_Access::set_member($org1,$users[$name],$role)===true,'Assign explicit organization role'); }
$seat_ids=MHL_Access::teaching_accounts($org1);
acl_check(count($seat_ids)===3&&!in_array($users['viewer'],$seat_ids,true),'Organization counts owner and all teaching accounts, excluding viewers');
acl_check(MHL_Access::teaching_accounts($org2)===array($users['c']),'Seats are independent across organizations');
acl_check(MHL_Access::teaching_accounts(0)===array(),'Unknown organization cannot produce a seat allowance');
$a=acl_content($users['a'],$org1,'a');$b=acl_content($users['b'],$org1,'b');$c=acl_content($users['c'],$org2,'c');
wp_set_current_user($users['a']);
acl_check(!current_user_can('manage_options'),'Teacher has no WordPress admin capability');
acl_check(current_user_can('mhl_access')&&current_user_can('edit_post',$a['question']),'Teacher can edit own question');
foreach (array($b,$c) as $foreign) {
    foreach ($foreign as $id) {
        foreach (array('read','edit','control','delete','share','transfer') as $op) { acl_check(!MHL_Access::can($id,$op),'Deny foreign object operation '.$op); }
        acl_check(!current_user_can('edit_post',$id),'Native editor rejects foreign object');
        acl_check(!current_user_can('delete_post',$id),'Native delete rejects foreign object');
    }
}
$found=get_posts(array('post_type'=>'mhl_question','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids'));
acl_check(in_array($a['question'],array_map('intval',$found),true)&&!in_array($b['question'],array_map('intval',$found),true)&&!in_array($c['question'],array_map('intval',$found),true),'SQL lists scoped before pagination');
$query=new WP_Query(array('post_type'=>'mhl_question','post_status'=>'publish','posts_per_page'=>1,'post__in'=>array($c['question'])));
acl_check($query->found_posts===0,'Forged post__in cannot bypass tenant scope');
acl_check(update_post_meta($a['question'],'_mhl_workspace_id',$org2)===false,'Cannot forge workspace metadata');
acl_check(update_post_meta($a['question'],'_mhl_access_grants',array($users['c']=>'owner'))===false,'Cannot forge object ACL metadata');
acl_check(update_post_meta($a['lecture'],'_mhl_subject_id',$c['subject'])===false,'Cannot attach another organization subject');
acl_check(update_post_meta($a['lecture'],'_mhl_question_ids',array($c['question']))===false,'Cannot attach another organization question');
wp_update_post(array('ID'=>$a['question'],'post_author'=>$users['c']));
acl_check((int)get_post($a['question'])->post_author===$users['a'],'Native author forgery cannot transfer ownership');
acl_check(is_wp_error(MHL_Access::set_member($org1,$users['a'],'owner')),'Teacher cannot promote self');
acl_check(is_wp_error(MHL_Access::share($c['subject'],$users['a'],'viewer')),'Teacher cannot share foreign content');
acl_check(is_wp_error(MHL_Access::share($a['subject'],$users['c'],'viewer')),'Sharing cannot cross organizations');
acl_check(MHL_Access::share($a['subject'],$users['b'],'collaborating_teacher')===true,'Owner can explicitly share a subject');
wp_set_current_user($users['b']);
foreach ($a as $id) { acl_check(MHL_Access::can($id,'read')&&MHL_Access::can($id,'edit'),'Subject collaboration reaches declared descendants'); }
acl_check(!MHL_Access::can($a['subject'],'share')&&!MHL_Access::can($a['subject'],'transfer'),'Collaborator cannot re-share or transfer');
acl_check(!MHL_Access::can($c['question']),'Collaboration does not disclose another tenant');
wp_set_current_user($users['a']);
acl_check(MHL_Access::share($a['subject'],$users['viewer'],'viewer')===true,'Share read-only subject');
wp_set_current_user($users['viewer']);update_user_meta($users['viewer'],'mhl_active_workspace',$org1);
foreach ($a as $id) { acl_check(MHL_Access::can($id,'read')&&!MHL_Access::can($id,'edit')&&!MHL_Access::can($id,'control'),'Read-only grant cannot edit or control'); }
acl_check(!current_user_can('mhl_create_content'),'Read-only organization member cannot create there');
acl_check(!str_contains(MHL_Access::run_sql('','control'),(string)$a['lecture']),'Read-only account excluded from mutation SQL scope');
wp_trash_post($a['lecture']);
acl_check(!MHL_Access::can($a['question']),'Trashing a shared lecture invalidates inherited question access');
wp_untrash_post($a['lecture']);
wp_set_current_user($admin->ID);wp_update_post(array('ID'=>$a['lecture'],'post_status'=>'publish'));wp_set_current_user($users['viewer']);
acl_check(MHL_Access::can($a['question']),'Restoring the shared lecture rebuilds relationship cache');
$activation=acl_request('POST','/activate',array('lecture_slug'=>MHL_Core::permanent_slug($a['lecture']),'question_slug'=>MHL_Core::permanent_slug($a['question']),'mode'=>'live'));
acl_check($activation->get_status()===403,'REST direct callback denies viewer control');
wp_set_current_user($users['a']);
[$run,$session]=MHL_Core::activate_question($a['lecture'],$a['question'],'live',true);
acl_check($run&&$session&&$session->status==='open','Owner can open live teaching');
$live_run_id=(int)$run->id;
wp_set_current_user($users['viewer']);$_GET['run_id']=$live_run_id;ob_start();MHL_Admin::live_control();$viewer_ui=ob_get_clean();unset($_GET['run_id']);
acl_check(!str_contains($viewer_ui,'action=mhl_close_session')&&!str_contains($viewer_ui,'action=mhl_reset_session')&&!str_contains($viewer_ui,'action=mhl_finish_run'),'Reader live screen omits all mutation controls');
wp_set_current_user($users['a']);
[$test_run]=MHL_Core::activate_question($a['lecture'],$a['question'],'test',true);
wp_set_current_user($users['viewer']);
acl_check($test_run&&!MHL_Core::delete_test_run((int)$test_run->id),'Reader cannot delete a shared test even through the core entry');
wp_set_current_user($users['a']);
acl_check(MHL_Core::delete_test_run((int)$test_run->id),'Authorized owner can delete own test');
acl_check(MHL_Access::can_run((int)$run->id,'export'),'Owner can export own results');
wp_set_current_user($users['c']);
acl_check(!MHL_Access::can_run((int)$run->id,'export')&&!MHL_Access::can_run((int)$run->id),'Foreign run and result export denied');
acl_check(is_wp_error(MHL_Admin::simulate_session_votes((int)$run->id,(int)$session->id)),'Simulation rejects foreign run');
acl_check(MHL_Core::activate_question($a['lecture'],$a['question'],'live',true)[3]==='forbidden','Core activation has its own object guard');
$activation=acl_request('POST','/activate',array('lecture_slug'=>MHL_Core::permanent_slug($a['lecture']),'question_slug'=>MHL_Core::permanent_slug($a['question']),'mode'=>'live'));
acl_check($activation->get_status()===403,'REST object ID guessing cannot start foreign lesson');
wp_set_current_user(0);
$public=acl_request('GET','/question/'.MHL_Core::permanent_slug($a['lecture']).'/'.MHL_Core::permanent_slug($a['question']));
acl_check($public->get_status()===200,'Existing published student QR keeps working without teacher login');
acl_check(!MHL_Access::can($a['question']),'Anonymous visitor has no management permission');
wp_set_current_user($users['manager']);
acl_check(MHL_Access::can($a['question'],'edit')&&MHL_Access::can($b['question'],'edit')&&!MHL_Access::can($c['question']),'Organization owner manages only own organization');
acl_check(is_wp_error(MHL_Access::set_member($org1,$users['manager'],'')),'Cannot remove last organization owner');
acl_check(MHL_Access::set_member($org1,$users['b'],'administrator')===true,'Owner can appoint organization administrator');
wp_set_current_user($users['b']);
acl_check(!current_user_can('manage_options')&&MHL_Access::can($a['question'],'edit'),'Organization administrator is not a site administrator');
acl_check(is_wp_error(MHL_Access::set_member($org1,$users['manager'],'')),'Organization administrator cannot remove owner');
wp_set_current_user($users['manager']);
acl_check(MHL_Access::set_member($org1,$users['b'],'teacher')===true,'Restore collaboration member role');
acl_check(MHL_Access::set_member($org1,$users['b'],'')===true,'Revoke membership');
acl_check(count(MHL_Access::teaching_accounts($org1))===2,'Removing membership releases exactly one organization seat');
wp_set_current_user($users['b']);
acl_check(!MHL_Access::can($a['question'])&&!MHL_Access::can($b['question']),'Membership removal immediately denies shared and previously owned organization content');
wp_set_current_user($users['manager']);MHL_Access::set_member($org1,$users['b'],'teacher');
acl_check(count(MHL_Access::teaching_accounts($org1))===3,'Membership restoration consumes one seat regardless of shared objects');
wp_set_current_user($users['a']);
$slug=MHL_Core::permanent_slug($a['question']);
acl_check(MHL_Access::transfer($a['question'],$users['b'])===true,'Transfer ownership within organization');
acl_check((int)get_post($a['question'])->post_author===$users['b']&&MHL_Core::permanent_slug($a['question'])===$slug,'Ownership transfer preserves permanent QR');
wp_set_current_user($users['b']);
acl_check(MHL_Access::can($a['question'],'transfer'),'New owner can manage ownership');
acl_check(is_wp_error(MHL_Access::transfer($a['question'],$users['c'])),'Transfer cannot cross organizations');
wp_set_current_user($admin->ID);
echo wp_json_encode(array('status'=>'passed','checks'=>$checks,'synthetic_data'=>true,'organizations'=>2,'accounts'=>5,'production_access'=>false,'browser_fixture'=>array('users'=>$users,'a'=>$a,'b'=>$b,'c'=>$c,'run_id'=>$live_run_id))),"\n";
