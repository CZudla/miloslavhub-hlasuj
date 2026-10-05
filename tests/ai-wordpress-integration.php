<?php
declare(strict_types=1);
// Only disposable local WordPress with synthetic data and a blocked HTTP transport.
$testRoot = getenv('MHL_TEST_WP_PATH') ?: '';
$config = $testRoot.'/wp-config.php';
if (!is_file($config) || !str_contains(file_get_contents($config), "'integration_wp'") || !str_contains(file_get_contents($config), "'127.0.0.1:")) {
    throw new RuntimeException('Refusing to run outside the isolated local integration site.');
}
$_SERVER['HTTP_HOST']='127.0.0.1:8097';
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['REQUEST_URI']='/';
require $testRoot.'/wp-load.php';
require_once $testRoot.'/wp-content/plugins/miloslavhub-live/miloslavhub-live.php';
MHL_Core::register_content_types();
MHL_AI::init();
$checks=0; $providerCalls=0;
function ai_check(bool $ok,string $message): void { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException($message); }
function ai_request(string $path,array $body,bool $nonce=true): WP_REST_Response {
    $request=new WP_REST_Request('POST','/mhl/v1/ai/'.$path);
    $request->set_header('Content-Type','application/json');
    if ($nonce) $request->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));
    $request->set_body(wp_json_encode($body));
    return rest_do_request($request);
}
// The mu-plugin blocks all external HTTP. This later filter returns a fixture only.
add_filter('pre_http_request', static function($pre,$args,$url) {
    if ($url!=='https://api.openai.com/v1/responses') return new WP_Error('blocked','External HTTP is disabled.');
    $GLOBALS['providerCalls']++;
    $payload=json_decode($args['body'],true);
    $input=json_decode($payload['input'],true);
    ai_check(!array_key_exists('question_id',$input) && count($input)===4,'Minimal provider payload');
    $output=array('title'=>'Upravená syntetická otázka?','options'=>$input['options']);
    return array('headers'=>array(),'response'=>array('code'=>200),'body'=>wp_json_encode(array('status'=>'completed','output'=>array(array('type'=>'message','content'=>array(array('type'=>'output_text','text'=>wp_json_encode($output))))))));
},20,3);
$admin=get_user_by('login','integration_admin');
ai_check($admin instanceof WP_User,'Synthetic administrator exists');
wp_set_current_user($admin->ID);
$question=wp_insert_post(array('post_type'=>'mhl_question','post_status'=>'draft','post_title'=>'Původní syntetická otázka?'));
update_post_meta($question,'_mhl_options',array('A','B'));
$body=array('question_id'=>$question,'operation'=>'rephrase','language'=>'cs','title'=>'Původní syntetická otázka?','options'=>array('A','B'));
try {
    delete_option('mhl_ai_daily_usage');
    delete_user_meta($admin->ID,'mhl_ai_disabled');
    wp_set_current_user(0);
    ai_check(ai_request('suggest',$body)->get_status()===403,'Anonymous request denied');
    wp_set_current_user($admin->ID);
    ai_check(ai_request('suggest',$body,false)->get_status()===403,'Missing nonce denied');
    $nonadmin=wp_create_user('ai_synthetic_subscriber',bin2hex(random_bytes(12)),'ai-synthetic@example.invalid');
    ai_check(!is_wp_error($nonadmin),'Synthetic subscriber created');
    wp_set_current_user($nonadmin);
    ai_check(ai_request('suggest',$body)->get_status()===403,'Subscriber cannot invoke paid operation');
    wp_set_current_user($admin->ID);
    $other=wp_insert_post(array('post_type'=>'post','post_status'=>'draft','post_title'=>'Other type'));
    ai_check(ai_request('suggest',array_replace($body,array('question_id'=>$other)))->get_status()===403,'Other post type denied');
    wp_delete_post($other,true);
    ai_check($providerCalls===0,'Authorization failures made zero provider calls');
    $result=ai_request('suggest',$body);
    ai_check($result->get_status()===200,'Real WordPress accepted valid request');
    ai_check($result->get_data()['suggestion']['title']==='Upravená syntetická otázka?','Fixture reaches response');
    ai_check(get_post($question)->post_title===$body['title'],'AI endpoint did not modify stored question');
    ai_check(get_post($question)->post_status==='draft','AI endpoint did not publish question');
    ai_check(ai_request('suggest',$body)->get_status()===429,'Real persistent quota blocks rapid repeat');
    $result=ai_request('preference',array('enabled'=>false));
    ai_check($result->get_status()===200 && (bool)get_user_meta($admin->ID,'mhl_ai_disabled',true),'Personal disable persisted');
    ai_check(ai_request('suggest',$body)->get_status()===403,'Personal disable enforced');
    ai_request('preference',array('enabled'=>true));
    delete_option('mhl_ai_daily_usage');
    global $wpdb;
    $second=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
    $lock='mhl_ai_'.substr(hash('sha256',$wpdb->prefix),0,32);
    ai_check((string)$second->get_var($second->prepare('SELECT GET_LOCK(%s,0)',$lock))==='1','Second DB connection acquired admission lock');
    try {
        ai_check(ai_request('suggest',$body)->get_status()===429,'Concurrent request rejected by real DB lock');
    } finally {
        $second->get_var($second->prepare('SELECT RELEASE_LOCK(%s)',$lock));
        $second->close();
    }
    ai_check(ai_request('suggest',$body)->get_status()===200,'Operation resumes after lock release');
    ai_check($providerCalls===2,'Only two admitted operations reached synthetic provider');
    // Confirm ordinary WordPress saving remains separate from requesting a suggestion.
    wp_update_post(array('ID'=>$question,'post_title'=>'Výslovně uložený návrh'));
    ai_check(get_post($question)->post_title==='Výslovně uložený návrh','Normal explicit save works');
    echo wp_json_encode(array('status'=>'passed','checks'=>$checks,'wordpress'=>get_bloginfo('version'),'database'=>$wpdb->db_version(),'provider'=>'pre_http_request fixture','paid_calls'=>0));
} finally {
    wp_set_current_user($admin->ID);
    wp_delete_post($question,true);
    if (isset($nonadmin) && is_int($nonadmin)) { require_once ABSPATH.'wp-admin/includes/user.php'; wp_delete_user($nonadmin); }
    delete_user_meta($admin->ID,'mhl_ai_disabled');
    delete_option('mhl_ai_daily_usage');
}
