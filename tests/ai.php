<?php
declare(strict_types=1);
// All WordPress/HTTP/DB operations are doubles. This suite cannot call a provider.
define('ABSPATH', __DIR__);
define('MHL_AI_ENABLED', ($argv[1] ?? '') !== 'disabled');
define('MHL_OPENAI_API_KEY', 'synthetic-key-never-sent');
$checks=0; $calls=array(); $options=array(); $meta=array(); $admin=true; $edit=true; $nonce=true;
function check(bool $ok, string $message): void { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException($message); }
class WP_Error { public function __construct(public string $code, public string $message, public array $data=array()) {} }
class WP_REST_Request {
    public function __construct(private mixed $body) {}
    public function get_json_params(): mixed { return $this->body; }
    public function get_header($name): string { return 'synthetic-nonce'; }
}
class WP_REST_Response {
    public array $headers=array();
    public function __construct(public array $data, public int $status) {}
    public function header($name,$value): void { $this->headers[$name]=$value; }
}
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function is_user_logged_in(): bool { return $GLOBALS['admin']; }
function current_user_can($cap, ...$args): bool { return $cap==='edit_post' ? $GLOBALS['edit'] : $GLOBALS['admin']; }
function wp_verify_nonce(...$args): bool { return $GLOBALS['nonce']; }
function get_current_user_id(): int { return 7; }
function wp_cache_delete(...$args): void {}
function get_post($id): ?object { return $id===42 ? (object)array('post_type'=>'mhl_question') : null; }
function get_user_meta($id,$name,$single): mixed { return $GLOBALS['meta'][$name] ?? ''; }
function update_user_meta($id,$name,$value): void { $GLOBALS['meta'][$name]=$value; }
function get_option($name,$default=false): mixed { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name,$value,$autoload=false): bool { $GLOBALS['options'][$name]=$value; return true; }
function wp_json_encode($value): string { return json_encode($value, JSON_UNESCAPED_UNICODE); }
function wp_remote_post($url,$args): mixed { $GLOBALS['calls'][]=array($url,$args); return $GLOBALS['response']; }
function wp_remote_retrieve_response_code($response): int { return $response['code']; }
function wp_remote_retrieve_body($response): string { return $response['body']; }
function register_rest_route($namespace,$route,$args): void { $GLOBALS['routes'][$route]=$args; }
class FakeWPDB {
    public string $prefix='synthetic_'; public bool $busy=false; public int $released=0;
    public function prepare($sql,...$args): string { return $sql; }
    public function get_var($sql): int { if (str_contains($sql,'RELEASE_LOCK')) { $this->released++; return 1; } return $this->busy ? 0 : 1; }
}
$wpdb=new FakeWPDB();
require __DIR__.'/../wordpress/miloslavhub-live/includes/class-mhl-ai.php';
$input=array('question_id'=>42,'operation'=>'rephrase','language'=>'cs','title'=>'Které číslo není sudé?','options'=>array('2','3',''));
$request=new WP_REST_Request($input);
if (!MHL_AI_ENABLED) {
    check(MHL_AI::suggest($request) instanceof WP_Error, 'Disabled installation refuses suggestions');
    check(MHL_AI::preference(new WP_REST_Request(array('enabled'=>true))) instanceof WP_Error, 'User cannot override disabled installation');
    check(count($calls)===0, 'Disabled installation makes zero HTTP calls');
    echo json_encode(array('status'=>'passed','checks'=>$checks)); exit;
}
function answer(array $output): array { return array('status'=>'completed','output'=>array(array('type'=>'message','content'=>array(array('type'=>'output_text','text'=>wp_json_encode($output)))))); }
function reset_quota(): void { $GLOBALS['options']=array(); }
$good=array('title'=>'Které z uvedených čísel není sudé?','options'=>$input['options']);
$response=array('code'=>200,'body'=>wp_json_encode(answer($good)));
MHL_AI::routes();
check($GLOBALS['routes']['/ai/suggest']['permission_callback']===array('MHL_AI','can_suggest'), 'Route is protected');
foreach (array('admin','edit','nonce') as $guard) {
    $GLOBALS[$guard]=false;
    check(MHL_AI::suggest($request) instanceof WP_Error, 'Authorization rejects '.$guard);
    $GLOBALS[$guard]=true;
}
foreach (array(0,99,'42',-1,null) as $id) {
    check(MHL_AI::suggest(new WP_REST_Request(array_replace($input,array('question_id'=>$id)))) instanceof WP_Error, 'Invalid question rejected');
}
$meta['mhl_ai_disabled']=1;
check(MHL_AI::suggest($request) instanceof WP_Error, 'Personal opt-out enforced');
check(MHL_AI::preference(new WP_REST_Request(array('enabled'=>true)))->data['enabled'], 'Personal preference can be restored');
check(MHL_AI::preference(new WP_REST_Request(array('enabled'=>'true'))) instanceof WP_Error, 'Preference requires boolean');
check(count($calls)===0, 'All denied requests made zero provider calls');
foreach (array(
    array('operation'=>'grade'), array('language'=>'xx'), array('title'=>''), array('title'=>str_repeat('x',2001)),
    array('title'=>'<script>alert(1)</script>'), array('title'=>array()), array('options'=>'bad'),
    array('options'=>array_fill(0,9,'x')), array('options'=>array('<img src=x>')), array('options'=>array(str_repeat('x',1001))),
    array('student_results'=>array(100)), array('title'=>"bad\0text")
) as $change) {
    check(MHL_AI::suggest(new WP_REST_Request(array_replace($input,$change))) instanceof WP_Error, 'Invalid input rejected');
}
check(count($calls)===0, 'Invalid requests made zero provider calls');
foreach (file(__DIR__.'/fixtures/ai-evaluation.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $row) {
    $case=json_decode($row,true,512,JSON_THROW_ON_ERROR);
    $accepted=!is_wp_error(MHL_AI::validate_input($case['input']));
    check($accepted===($case['expected']==='accept'), 'Evaluation fixture input contract: '.$case['id']);
}
$result=MHL_AI::suggest($request);
check($result instanceof WP_REST_Response && $result->data['suggestion']===$good, 'Valid output is returned as suggestion');
check($result->headers['Cache-Control']==='no-store', 'Suggestion cannot be cached');
$sent=json_decode($calls[0][1]['body'],true);
$content=json_decode($sent['input'],true);
check(array_keys($content)===array('operation','language','title','options'), 'Only selected content sent');
check($sent['model']==='gpt-6.1-sol' && $sent['reasoning']['effort']==='low', 'Model and effort explicit');
check($sent['store']===false && $sent['text']['format']['strict']===true, 'Storage flag and strict schema');
check(!isset($sent['temperature']) && !isset($sent['tools']), 'No unsupported sampling or tools');
check($calls[0][0]==='https://api.openai.com/v1/responses' && $calls[0][1]['redirection']===0, 'Fixed provider without redirects');
check($wpdb->released===1, 'Global admission lock released');
check(MHL_AI::suggest($request) instanceof WP_Error && count($calls)===1, 'Immediate repeat rate limited');
$options['mhl_ai_daily_usage']=array('day'=>gmdate('Y-m-d'),'count'=>20,'last'=>0);
check(MHL_AI::suggest($request) instanceof WP_Error && count($calls)===1, 'Daily budget enforced');
reset_quota(); $wpdb->busy=true;
check(MHL_AI::suggest($request) instanceof WP_Error && count($calls)===1, 'Concurrent call refused');
$wpdb->busy=false;
foreach (array(401,429,500) as $code) {
    reset_quota(); $response=array('code'=>$code,'body'=>'private provider diagnostics');
    $result=MHL_AI::suggest($request);
    check($result instanceof WP_Error && !str_contains($result->message,'private'), 'Provider error is redacted');
}
reset_quota(); $response=new WP_Error('timeout','synthetic secret');
check(MHL_AI::suggest($request) instanceof WP_Error, 'Transport failure handled');
foreach (array(
    array('status'=>'incomplete','output'=>array()),
    array('status'=>'completed','output'=>array(array('type'=>'message','content'=>array(array('type'=>'refusal','refusal'=>'no'))))),
    answer(array('title'=>'ok','options'=>array('2'))), answer(array('title'=>'ok','options'=>array('2','4',''))),
    answer(array('title'=>'<script>x</script>','options'=>$input['options'])),
    answer(array('title'=>'ok','options'=>$input['options'],'publish'=>true)),
    array('status'=>'completed','output'=>array(array('type'=>'message','content'=>'invalid'))),
    array('status'=>'completed','output'=>array(array('type'=>'message','content'=>array('invalid')))),
    array('status'=>'completed','output'=>array(array('type'=>'message','content'=>array(array('type'=>'output_text','text'=>'not json')))))
) as $bad) { check(MHL_AI::parse_response($bad,$input) instanceof WP_Error, 'Malformed/refused/altered output rejected'); }
$translated=array('title'=>'Which number is not even?','options'=>array('2','3',''));
check(MHL_AI::parse_response(answer($translated),array_replace($input,array('operation'=>'translate','language'=>'en')))===$translated, 'Translation preserves options and empty slots');
check(MHL_AI::parse_response(answer(array('title'=>'ok','options'=>array('2','3','extra'))),array_replace($input,array('operation'=>'translate'))) instanceof WP_Error, 'Translation cannot fill empty slots');
check($wpdb->released===7, 'Locks released after rate/budget/provider failures');
echo json_encode(array('status'=>'passed','checks'=>$checks,'provider'=>'in-memory double','paid_calls'=>0));
