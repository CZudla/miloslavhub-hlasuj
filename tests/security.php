<?php
declare(strict_types=1);
// Isolated contract tests: WordPress authentication and DB are test doubles.
// These tests do not replace integration tests against WordPress/MySQL.
define('ABSPATH', __DIR__);
$admin = false;
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function current_user_can(string $cap): bool { return $GLOBALS['admin'] && $cap === 'manage_options'; }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
class WP_Error {
    public function __construct(public string $code, public string $message, public array $data) {}
}
class WP_REST_Request {
    public function __construct(private array $body = [], private bool $textPlain = false) {}
    public function get_json_params(): ?array { return $this->textPlain ? null : $this->body; }
    public function get_body(): string { return json_encode($this->body); }
}
class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; }
function register_rest_route($namespace, $route, $args): void { $GLOBALS['routes'][$route] = $args; }
class TestDB {
    public object $session;
    public array $writes = [];
    public function prepare($query, ...$args) { return $query; }
    public function get_row($query) { return $this->session; }
    public function update(...$args) { $this->writes[] = $args; }
}
class MHL_DB {
    public static TestDB $test;
    public static function schema_ready(): bool { return true; }
    public static function table($table): string { return 'mhl_' . $table; }
    public static function db(): TestDB { return self::$test; }
}
require __DIR__ . '/../wordpress/miloslavhub-live/includes/class-mhl-rest.php';
require __DIR__ . '/../wordpress/miloslavhub-live/includes/class-mhl-core.php';
require __DIR__ . '/../wordpress/miloslavhub-live/includes/class-mhl-admin.php';
require __DIR__ . '/../frontend/demo/lib.php';

MHL_REST::routes();
check($GLOBALS['routes']['/activate']['permission_callback'] === ['MHL_REST', 'can_activate'], 'Activation route must enforce permission.');
foreach ([[], ['mode'=>'live'], ['mode'=>'test'], ['mode'=>'invalid']] as $body) {
    foreach ([false, true] as $textPlain) {
        $request = new WP_REST_Request($body, $textPlain);
        $result = MHL_REST::can_activate($request);
        check($result instanceof WP_Error && $result->data['status'] === 403, 'Anonymous control must fail.');
        check(MHL_REST::activate($request) instanceof WP_Error, 'Direct callback must fail before any content/DB lookup.');
    }
}
check(MHL_REST::can_activate(new WP_REST_Request(['mode'=>'async'])) === true, 'Explicit async polls retain their public entry.');
$admin = true;
check(MHL_REST::can_activate(new WP_REST_Request(['mode'=>'live'])) === true, 'Authorized administrator can control live flow.');
$admin = false;
foreach (['live','test'] as $mode) {
    check(MHL_Core::activate_question(1, 2, $mode)[3] === 'forbidden', 'Core activation must not bypass permission.');
    MHL_DB::$test = new TestDB();
    MHL_DB::$test->session = (object)['id'=>1, 'mode'=>$mode, 'status'=>'joining', 'joining_started_at'=>'2000-01-01 00:00:00'];
    check(MHL_Core::maybe_start_voting(1)->status === 'joining', 'Elapsed waiting time does not start live/test voting.');
    check(MHL_DB::$test->writes === [], 'Reading a legacy joining session must not open it.');
}
check(!MHL_Core::lecture_auto_qr(1), 'Old auto QR metadata cannot enable participant control.');

foreach (['=1+1','+SUM(A1)','-1+1','@SUM(A1)',"\t=1+1",'   =1+1',"\rfoo", "\u{00A0}=1+1"] as $value) {
    check(MHL_Admin::csv_text($value) === "'".$value, 'CSV formula/control prefix must be neutralized.');
}
foreach (['Žluťoučký kůň','Student 1','123','hello;world','"quoted"',''] as $value) {
    check(MHL_Admin::csv_text($value) === $value, 'Ordinary CSV text must remain unchanged.');
}

$ids = ['named'=>str_repeat('a',32), 'anonymous'=>str_repeat('b',32), 'skip'=>str_repeat('c',32), 'unset'=>str_repeat('d',32)];
$session = ['id'=>str_repeat('e',32), 'stage'=>7, 'expires_at'=>time()+900, 'participants'=>[], 'scores'=>['1'=>[], '3'=>[]]];
foreach ($ids as $choice=>$id) {
    $session['participants'][$id] = ['nickname'=>'PRIVATE-'.$choice, 'hall_choice'=>$choice==='named'?'nickname':($choice==='unset'?null:$choice)];
    $session['scores']['1'][$id]=1000;
    $session['scores']['3'][$id]=1000;
}
$public = mhl_demo_public_state($session);
$wire = json_encode($public);
check(str_contains($wire, 'PRIVATE-named'), 'Explicitly opted-in nickname should be visible.');
foreach (['anonymous','skip','unset'] as $choice) {
    check(!str_contains($wire, 'PRIVATE-'.$choice), 'Public response leaked '.$choice.' nickname.');
}
check(count(array_filter($public['leaderboard'], fn($r)=>empty($r['synthetic']))) === 2, 'Only named/anonymous choices may appear.');
check(in_array('Anonymní účastník', array_column($public['leaderboard'], 'nickname'), true), 'Anonymous entry needs a neutral label.');
foreach (['named','anonymous','skip'] as $choice) {
    $own = mhl_demo_public_state($session, $ids[$choice]);
    check($own['stage'] === 8 && $own['my_score'] === 2000, 'Finished participant keeps private score.');
}
check(mhl_demo_public_state($session, $ids['unset'])['stage'] === 7, 'Another participant still needs their own choice.');
$session['stage'] = 8;
check(mhl_demo_public_state($session, $ids['unset'])['stage'] === 7, 'Late participant is not forced past consent.');
echo json_encode(['suite'=>'security-contracts','checks'=>$checks,'status'=>'passed'], JSON_PRETTY_PRINT), "\n";
