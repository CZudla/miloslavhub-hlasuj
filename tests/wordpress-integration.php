<?php
declare(strict_types=1);
// Run only against a disposable WordPress and two disposable local databases.
$testRoot = getenv('MHL_TEST_WP_PATH') ?: '';
$config = $testRoot . '/wp-config.php';
if (!is_file($config) || !str_contains(file_get_contents($config), "'integration_wp'") || !str_contains(file_get_contents($config), "'127.0.0.1:")) {
    throw new RuntimeException('Refusing to run outside the isolated local integration site.');
}
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = '127.0.0.1:8097';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = '8097';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
require $testRoot . '/wp-load.php';
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static fn() => new WP_Error('test_network_blocked', 'External HTTP disabled in tests.'));
if (!is_blog_installed()) {
    require ABSPATH . 'wp-admin/includes/upgrade.php';
    wp_install('Hlasuj integration', 'integration_admin', 'test@example.invalid', false, '', bin2hex(random_bytes(20)));
}
require_once $testRoot . '/wp-content/plugins/miloslavhub-live/miloslavhub-live.php';
MHL_Access::init(); MHL_Access::register(); MHL_Core::init();
MHL_Core::register_content_types();
MHL_REST::init();
update_option('mhl_settings', ['frontend_url'=>'http://127.0.0.1:8097','allowed_origin'=>'http://127.0.0.1:8097']);

$checks = 0;
function integration_check(bool $ok, string $message): void {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($message);
}
function request(string $method, string $path, array $body=[]): WP_REST_Response {
    $r = new WP_REST_Request($method, '/mhl/v1'.$path);
    if ($method === 'POST') { $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($body)); }
    else { $r->set_query_params($body); }
    return rest_do_request($r);
}
integration_check(MHL_DB::schema_ready(), 'External schema must be ready.');
$admin = get_user_by('login','integration_admin');
wp_set_current_user($admin->ID);
$subject = wp_insert_post(['post_type'=>'mhl_subject','post_status'=>'publish','post_title'=>'Synthetic subject','post_name'=>'synthetic-subject']);
$lecture = wp_insert_post(['post_type'=>'mhl_lecture','post_status'=>'publish','post_title'=>'Synthetic lesson','post_name'=>'synthetic-lesson']);
$quiz = wp_insert_post(['post_type'=>'mhl_question','post_status'=>'publish','post_title'=>'Synthetic quiz','post_name'=>'synthetic-quiz']);
$poll = wp_insert_post(['post_type'=>'mhl_question','post_status'=>'publish','post_title'=>'Synthetic poll','post_name'=>'synthetic-poll']);
update_post_meta($lecture,'_mhl_subject_id',$subject);
update_post_meta($lecture,'_mhl_question_ids',[$quiz,$poll]);
update_post_meta($lecture,'_mhl_auto_qr',1); // Legacy metadata must not grant public control.
update_post_meta($quiz,'_mhl_options',['First','Second']);
update_post_meta($quiz,'_mhl_correct_index',0);
update_post_meta($quiz,'_mhl_time_limit',10);
update_post_meta($poll,'_mhl_options',['Yes','No']);
update_post_meta($poll,'_mhl_async_enabled',1);
$ls=MHL_Core::permanent_slug($lecture);$qs=MHL_Core::permanent_slug($quiz);$ps=MHL_Core::permanent_slug($poll);
$body=['lecture_slug'=>$ls,'question_slug'=>$qs,'mode'=>'live'];
$db=MHL_DB::db();
wp_set_current_user(0);
$before=(int)$db->get_var('SELECT COUNT(*) FROM mhl_runs');
integration_check(request('POST','/activate',$body)->get_status()===403,'Anonymous live activation must fail.');
integration_check(request('POST','/activate',array_replace($body,['mode'=>'test']))->get_status()===403,'Anonymous test activation must fail.');
integration_check((int)$db->get_var('SELECT COUNT(*) FROM mhl_runs')===$before,'Denied activation must not create run.');
integration_check(request('GET',"/question/$ls/$qs")->get_data()['status']==='idle','Loading idle question must not start it.');

wp_set_current_user($admin->ID);
$run=MHL_Core::create_run($lecture,'live',(int)$admin->ID);
integration_check((bool)$run,'Teacher can prepare a run.');
wp_set_current_user(0);
$participant=str_repeat('a',32);
$join=request('POST','/join',$body+['participant_id'=>$participant]);
integration_check($join->get_data()['status']==='waiting','Student join must leave prepared question waiting.');
$session=MHL_Core::get_current_session($lecture,$quiz,(int)$run->id);
$db->update('mhl_sessions',['status'=>'joining','joining_started_at'=>gmdate('Y-m-d H:i:s',time()-90)],['id'=>$session->id]);
integration_check(request('GET',"/question/$ls/$qs")->get_data()['status']==='joining','Legacy joining must not auto-open.');
wp_set_current_user($admin->ID);
$opened=request('POST','/activate',$body);
integration_check($opened->get_status()===200 && $opened->get_data()['status']==='open','Authenticated teacher opens voting.');
$sid=(int)$opened->get_data()['session_id'];
wp_set_current_user(0);
$beforeOpen=$db->get_var($db->prepare('SELECT opened_at FROM mhl_sessions WHERE id=%d',$sid));
request('POST','/join',$body+['participant_id'=>str_repeat('b',32)]);
integration_check($db->get_var($db->prepare('SELECT opened_at FROM mhl_sessions WHERE id=%d',$sid))===$beforeOpen,'Late join does not restart timer.');
$v=request('POST','/vote',$body+['participant_id'=>$participant,'option_index'=>0]);
integration_check($v->get_status()===201,'Open vote is accepted.');
integration_check(request('POST','/vote',$body+['participant_id'=>$participant,'option_index'=>1])->get_status()===409,'Duplicate vote is rejected.');
integration_check(request('GET',"/results/$ls/$qs")->get_data()['correct_index']===null,'Correct answer must not leak while open.');
$db->update('mhl_sessions',['reset_at'=>gmdate('Y-m-d H:i:s',time()-1)],['id'=>$sid]);
integration_check(request('POST','/vote',$body+['participant_id'=>str_repeat('b',32),'option_index'=>0])->get_status()===409,'Vote after deadline is rejected.');
integration_check((int)$db->get_var($db->prepare('SELECT COUNT(*) FROM mhl_votes WHERE session_id=%d',$sid))===1,'Rejected votes must not be stored.');
integration_check(request('GET',"/results/$ls/$qs")->get_data()['correct_index']===0,'Closed quiz reveals configured correct answer.');
integration_check(request('POST','/activate',['lecture_slug'=>$ls,'question_slug'=>$ps,'mode'=>'async'])->get_status()===200,'Enabled standalone poll retains public activation.');
integration_check(request('POST','/activate',array_replace($body,['mode'=>'async']))->get_status()>=400,'Async mode cannot activate a quiz.');
$beforeSlug=MHL_Core::get_vote_url($lecture,$quiz);
wp_update_post(['ID'=>$quiz,'post_title'=>'Renamed synthetic quiz','post_name'=>'changed-name']);
integration_check(MHL_Core::get_vote_url($lecture,$quiz)===$beforeSlug,'Editing title/slug must preserve old QR URL.');
echo wp_json_encode(['status'=>'passed','checks'=>$checks,'wordpress'=>$GLOBALS['wp_version'],'database'=>$db->get_var('SELECT VERSION()'),'production_data_used'=>false,'concurrency_tested'=>false], JSON_PRETTY_PRINT),"\n";
