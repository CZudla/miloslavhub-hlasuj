<?php
declare(strict_types=1);
$testRoot=getenv('MHL_TEST_WP_PATH')?:'';$config=$testRoot.'/wp-config.php';
if(!is_file($config)||!str_contains(file_get_contents($config),"'integration_wp'")||!str_contains(file_get_contents($config),"'127.0.0.1:")){
    throw new RuntimeException('Refusing to run outside the isolated local integration site.');
}
$_SERVER['HTTP_HOST']='127.0.0.1:8097';$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';
require $testRoot.'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
$activated=activate_plugin('miloslavhub-live/miloslavhub-live.php');
if(is_wp_error($activated)){throw new RuntimeException($activated->get_error_message());}
$admin=get_user_by('login','integration_admin');
if(!$admin){throw new RuntimeException('Missing synthetic administrator');}
if(($argv[1]??'')==='inspect'){
    $id=(int)get_option('mhl_browser_fixture_question');
    echo wp_json_encode(['title'=>get_post($id)->post_title,'options'=>get_post_meta($id,'_mhl_options',true),
        'disabled'=>(bool)get_user_meta($admin->ID,'mhl_ai_disabled',true),'fixture_calls'=>(int)get_option('mhl_browser_fixture_calls')]),"\n";
    exit;
}
$password=getenv('MHL_TEST_ADMIN_PASSWORD');
if(!$password){throw new RuntimeException('Synthetic password must be supplied in the environment');}
wp_set_password($password,$admin->ID);wp_set_current_user($admin->ID);
delete_user_meta($admin->ID,'mhl_ai_disabled');delete_option('mhl_ai_daily_usage');
update_option('mhl_browser_fixture_calls',0);
MHL_Core::register_content_types();
$qid=wp_insert_post(['post_type'=>'mhl_question','post_status'=>'draft','post_title'=>'Původní syntetická otázka?']);
update_post_meta($qid,'_mhl_options',['První odpověď','Druhá odpověď']);
update_option('mhl_browser_fixture_question',$qid);
echo wp_json_encode(['question_id'=>$qid,'login'=>'integration_admin']),"\n";
