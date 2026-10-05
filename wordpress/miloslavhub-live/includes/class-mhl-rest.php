<?php
if (!defined('ABSPATH')) { exit; }

class MHL_REST {
    public static function init(): void {
        add_action('rest_api_init', array(__CLASS__,'routes'));
        add_action('rest_api_init', array(__CLASS__,'cors_headers'),15);
        add_filter('rest_post_dispatch', array(__CLASS__,'noindex_headers'),10,3);
    }
    public static function cors_headers(): void {
        $s=MHL_Core::settings(); $origin=get_http_origin();
        if($origin && $s['allowed_origin'] && untrailingslashit($origin)===untrailingslashit($s['allowed_origin'])){
            header('Access-Control-Allow-Origin: '.esc_url_raw($origin)); header('Vary: Origin'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type');
        }
    }
    public static function noindex_headers($response,$server,$request){
        if(str_starts_with((string)$request->get_route(),'/mhl/v1/')){
            $response->header('X-Robots-Tag','noindex, noarchive, nosnippet');
            $response->header('X-MHL-RAG-Policy','exclude');
            $response->header('Cache-Control','no-store, private');
        }
        return $response;
    }
    public static function routes(): void {
        register_rest_route('mhl/v1','/question/(?P<lecture>[a-zA-Z0-9\-_]+)/(?P<question>[a-zA-Z0-9\-_]+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'question'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/results/(?P<lecture>[a-zA-Z0-9\-_]+)/(?P<question>[a-zA-Z0-9\-_]+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'results'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/lecture/(?P<lecture>[a-zA-Z0-9\-_]+)/current',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'current'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/subject/(?P<subject>[a-zA-Z0-9\-_]+)/current',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'subject_current'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/activate',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'activate'),'permission_callback'=>array(__CLASS__,'can_activate')));
        register_rest_route('mhl/v1','/join',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'join'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/nickname/claim',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'nickname_claim'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/vote',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'vote'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/privacy',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'privacy_info'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/privacy/me',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'privacy_me'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/privacy/hall-opt-in',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'privacy_hall_opt_in'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/privacy/delete',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'privacy_delete'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/hall-of-fame/(?P<subject>[a-zA-Z0-9\-_]+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'hall_of_fame'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/projection/(?P<subject>[a-zA-Z0-9\-_]+)/(?P<token>[a-zA-Z0-9]+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'projection_current'),'permission_callback'=>'__return_true'));
        register_rest_route('mhl/v1','/participant/(?P<subject>[a-zA-Z0-9\-_]+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'participant_subject'),'permission_callback'=>'__return_true'));
    }
    private static function post_by_slug(string $type,string $slug): ?WP_Post {
        $slug=sanitize_title($slug);
        $p=get_posts(array('name'=>$slug,'post_type'=>$type,'post_status'=>'publish','numberposts'=>1));
        if(!$p){
            // Trvalý QR identifikátor je uložen mimo běžný WordPress slug.
            // I kdyby byl post_name změněn přímým zásahem do DB, původní QR zůstane funkční.
            $p=get_posts(array('post_type'=>$type,'post_status'=>'publish','numberposts'=>1,'meta_key'=>'_mhl_permanent_slug','meta_value'=>$slug));
        }
        if(!$p && class_exists('MHL_Admin') && ($slug==='mhl-live-demo-lecture' || str_starts_with($slug,'demo-'))){
            // Veřejné demo musí fungovat i v případě, že administrátor po aktualizaci ještě neotevřel stránku Ukázkové demo.
            MHL_Admin::ensure_demo_content();
            $p=get_posts(array('name'=>$slug,'post_type'=>$type,'post_status'=>'publish','numberposts'=>1));
            if(!$p){$p=get_posts(array('post_type'=>$type,'post_status'=>'publish','numberposts'=>1,'meta_key'=>'_mhl_permanent_slug','meta_value'=>$slug));}
        }
        return $p?$p[0]:null;
    }
    private static function context(string $lecture_slug,string $question_slug,string $mode): array {
        $lecture=self::post_by_slug('mhl_lecture',$lecture_slug); $question=self::post_by_slug('mhl_question',$question_slug);
        if(!$lecture || !$question || !MHL_Core::question_in_lecture((int)$lecture->ID,(int)$question->ID)){return array(null,null,null,null);}
        $run=MHL_Core::get_active_run_for_question((int)$lecture->ID,(int)$question->ID,$mode);
        $session=$run?MHL_Core::get_current_session((int)$lecture->ID,(int)$question->ID,(int)$run->id,$mode,true):null;
        return array($lecture,$question,$run,$session);
    }
    private static function mode(WP_REST_Request $r): string { $m=sanitize_key((string)$r->get_param('mode')); return in_array($m,array('test','async'),true)?$m:'live'; }

    private static function request_data(WP_REST_Request $r): array {
        $j=$r->get_json_params();
        if(is_array($j)){return $j;}
        $raw=trim((string)$r->get_body());
        if($raw===''){return array();}
        $decoded=json_decode($raw,true);
        return is_array($decoded)?$decoded:array();
    }

    private static function question_payload(WP_Post $lecture, WP_Post $question, ?object $run, ?object $session, string $mode): array {
        $qmode=MHL_Core::question_type((int)$question->ID);
        $g=$run?MHL_Core::run_gamification($run):($mode==='async'?array('enabled'=>false,'scope'=>'none'):MHL_Core::lecture_gamification((int)$lecture->ID));
        $poll_points=(int)get_post_meta($question->ID,'_mhl_poll_points',true);
        $g['join_nickname_required']=$g['enabled'];
        $g['nickname_required']=$g['enabled'] && ($qmode==='quiz' || $poll_points>0);
        $opts=MHL_Core::get_question_options((int)$question->ID);
        return array(
            'question_id'=>(int)$question->ID,'lecture_id'=>(int)$lecture->ID,'lecture_slug'=>MHL_Core::permanent_slug((int)$lecture->ID),'question_slug'=>MHL_Core::permanent_slug((int)$question->ID),
            'title'=>$question->post_title,'mode'=>$qmode,'run_mode'=>$mode,'long_poll'=>$mode==='async','show_results_after_vote'=>$mode==='async'?MHL_Core::question_async_show_results((int)$question->ID):false,
            'options'=>array_map(static fn($label,$i)=>array('index'=>$i,'code'=>chr(65+$i),'label'=>$label),$opts,array_keys($opts)),
            'status'=>$session?$session->status:'idle','session_id'=>$session?(int)$session->id:null,
            'share_url'=>MHL_Core::get_vote_url((int)$lecture->ID,(int)$question->ID,$mode),
            'opened_at'=>$session&&$session->opened_at?mysql_to_rfc3339($session->opened_at):null,'reset_at'=>$session&&$session->reset_at?mysql_to_rfc3339($session->reset_at):null,
            'joining_started_at_ms'=>$session&&!empty($session->joining_started_at)?(int)(strtotime($session->joining_started_at.' UTC')*1000):null,
            'last_join_at_ms'=>$session&&!empty($session->last_join_at)?(int)(strtotime($session->last_join_at.' UTC')*1000):null,
            'join_count'=>$session?(int)MHL_DB::db()->get_var(MHL_DB::db()->prepare("SELECT COUNT(*) FROM ".MHL_DB::table('session_joins')." WHERE session_id=%d",(int)$session->id)):0,
            'join_timing'=>array('min_wait_seconds'=>(int)(MHL_Core::settings()['join_min_wait_seconds']??5),'quiet_seconds'=>(int)(MHL_Core::settings()['join_quiet_seconds']??3),'max_wait_seconds'=>(int)(MHL_Core::settings()['join_max_wait_seconds']??15)),
            'opened_at_ms'=>$session&&$session->opened_at?(int)(strtotime($session->opened_at.' UTC')*1000):null,'closes_at_ms'=>$session&&$session->reset_at?(int)(strtotime($session->reset_at.' UTC')*1000):null,'server_now_ms'=>(int)round(microtime(true)*1000),'time_limit_seconds'=>$mode==='async'?0:MHL_Core::question_time_limit((int)$question->ID),
            'gamification'=>$g,'nickname_policy'=>array('scope'=>'subject','reservation_days'=>(int)(MHL_Core::settings()['nickname_reservation_days']??365)),'subject_title'=>MHL_Core::get_subject_title((int)$lecture->ID),'subject_slug'=>MHL_Core::get_subject_slug((int)$lecture->ID),'lecture_title'=>$lecture->post_title,'teachers'=>MHL_Core::lecture_teachers((int)$lecture->ID,true),'subject_template'=>MHL_Core::lecture_subject_template((int)$lecture->ID),'hall_of_fame'=>MHL_Core::lecture_hall_of_fame((int)$lecture->ID),'privacy_url'=>trailingslashit((string)MHL_Core::settings()['frontend_url']).'privacy','cta'=>MHL_Core::lecture_ctas((int)$lecture->ID)
        );
    }

    public static function can_activate(WP_REST_Request $r): bool|WP_Error {
        $data = self::request_data($r);
        // Async polls are explicitly enabled per question. Live/test control requires
        // WordPress authentication (including its REST nonce for cookie authentication).
        if (($data['mode'] ?? 'live') === 'async' || current_user_can('manage_options')) {
            return true;
        }
        return new WP_Error('mhl_teacher_required', 'Hlasování spouští vyučující.', array('status'=>403));
    }

    public static function activate(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $permission = self::can_activate($r);
        if (is_wp_error($permission)) { return $permission; }
        $j=self::request_data($r);
        $lecture_slug=sanitize_title((string)($j['lecture_slug']??''));$question_slug=sanitize_title((string)($j['question_slug']??''));$m=sanitize_key((string)($j['mode']??'live'));$mode=in_array($m,array('test','async'),true)?$m:'live';
        $lecture=self::post_by_slug('mhl_lecture',$lecture_slug);$question=self::post_by_slug('mhl_question',$question_slug);
        if(!$lecture||!$question||!MHL_Core::question_in_lecture((int)$lecture->ID,(int)$question->ID)){return new WP_Error('mhl_not_found','Otázka nebo přednáška nebyla nalezena.',array('status'=>404));}
        [$run,$session,$changed,$reason]=MHL_Core::activate_question((int)$lecture->ID,(int)$question->ID,$mode,true);
        if(!$run||!$session){return new WP_Error('mhl_activate_failed','Hlasování se nepodařilo aktivovat.',array('status'=>500));}
        return new WP_REST_Response(array(
            'ok'=>true,'run_id'=>(int)$run->id,'session_id'=>(int)$session->id,'status'=>$session->status,'changed'=>$changed,'reason'=>$reason,
            'lecture_slug'=>MHL_Core::permanent_slug((int)$lecture->ID),'question_slug'=>MHL_Core::permanent_slug((int)$question->ID),
            'subject_slug'=>MHL_Core::get_subject_slug((int)$lecture->ID),
            'vote_url'=>MHL_Core::get_vote_url((int)$lecture->ID,(int)$question->ID,$mode),'results_url'=>MHL_Core::get_results_url((int)$lecture->ID,(int)$question->ID,$mode),
            'question'=>self::question_payload($lecture,$question,$run,$session,$mode)
        ),200);
    }

    public static function current(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $mode=self::mode($r);$lecture=self::post_by_slug('mhl_lecture',(string)$r['lecture']);
        if(!$lecture){return new WP_Error('mhl_not_found','Přednáška nebyla nalezena.',array('status'=>404));}
        $run=MHL_Core::get_active_run((int)$lecture->ID,$mode);
        if(!$run){return new WP_REST_Response(array('status'=>'idle','run_id'=>null,'question_slug'=>null,'lecture_title'=>$lecture->post_title,'subject_title'=>MHL_Core::get_subject_title((int)$lecture->ID),'teachers'=>MHL_Core::lecture_teachers((int)$lecture->ID,true),'subject_template'=>MHL_Core::lecture_subject_template((int)$lecture->ID)),200);}
        $session=MHL_Core::current_open_session((int)$run->id,$mode);
        if(!$session){return new WP_REST_Response(array('status'=>'waiting','run_id'=>(int)$run->id,'question_slug'=>null,'lecture_title'=>$lecture->post_title,'subject_title'=>MHL_Core::get_subject_title((int)$lecture->ID),'teachers'=>MHL_Core::lecture_teachers((int)$lecture->ID,true),'subject_template'=>MHL_Core::lecture_subject_template((int)$lecture->ID)),200);}
        $q=get_post((int)$session->question_id);
        return new WP_REST_Response(array(
            'status'=>$session->status,'run_id'=>(int)$run->id,'session_id'=>(int)$session->id,'question_id'=>(int)$session->question_id,'question_slug'=>$q?MHL_Core::permanent_slug((int)$q->ID):null,
            'vote_url'=>$q?MHL_Core::get_vote_url((int)$lecture->ID,(int)$q->ID,$mode):null,'results_url'=>$q?MHL_Core::get_results_url((int)$lecture->ID,(int)$q->ID,$mode):null,
            'lecture_title'=>$lecture->post_title,'subject_title'=>MHL_Core::get_subject_title((int)$lecture->ID),'teachers'=>MHL_Core::lecture_teachers((int)$lecture->ID,true),'subject_template'=>MHL_Core::lecture_subject_template((int)$lecture->ID)
        ),200);
    }

    public static function subject_current(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $mode=self::mode($r);$subject=self::post_by_slug('mhl_subject',(string)$r['subject']);
        if(!$subject){return new WP_Error('mhl_not_found','Předmět nebyl nalezen.',array('status'=>404));}
        $run=MHL_Core::get_active_run_for_subject((int)$subject->ID,$mode);
        if(!$run){return new WP_REST_Response(array('status'=>'idle','run_id'=>null,'lecture_slug'=>null,'question_slug'=>null,'subject_slug'=>MHL_Core::permanent_slug((int)$subject->ID),'subject_title'=>$subject->post_title,'teachers'=>MHL_Core::subject_teachers((int)$subject->ID,true),'subject_template'=>MHL_Core::subject_template((int)$subject->ID)),200);}
        $lecture=get_post((int)$run->lecture_id);$session=MHL_Core::current_open_session((int)$run->id,$mode);
        if(!$session){return new WP_REST_Response(array('status'=>'waiting','run_id'=>(int)$run->id,'lecture_slug'=>$lecture?MHL_Core::permanent_slug((int)$lecture->ID):null,'question_slug'=>null,'lecture_title'=>$lecture?$lecture->post_title:'','subject_slug'=>MHL_Core::permanent_slug((int)$subject->ID),'subject_title'=>$subject->post_title,'teachers'=>MHL_Core::subject_teachers((int)$subject->ID,true),'subject_template'=>MHL_Core::subject_template((int)$subject->ID)),200);}
        $q=get_post((int)$session->question_id);
        return new WP_REST_Response(array(
            'status'=>$session->status,'run_id'=>(int)$run->id,'session_id'=>(int)$session->id,'lecture_slug'=>$lecture?MHL_Core::permanent_slug((int)$lecture->ID):null,'question_id'=>(int)$session->question_id,'question_slug'=>$q?MHL_Core::permanent_slug((int)$q->ID):null,
            'vote_url'=>($lecture&&$q)?MHL_Core::get_vote_url((int)$lecture->ID,(int)$q->ID,$mode):null,'results_url'=>($lecture&&$q)?MHL_Core::get_results_url((int)$lecture->ID,(int)$q->ID,$mode):null,
            'lecture_title'=>$lecture?$lecture->post_title:'','subject_slug'=>MHL_Core::permanent_slug((int)$subject->ID),'subject_title'=>$subject->post_title,'teachers'=>MHL_Core::subject_teachers((int)$subject->ID,true),'subject_template'=>MHL_Core::subject_template((int)$subject->ID)
        ),200);
    }

    public static function question(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $mode=self::mode($r); [$lecture,$question,$run,$session]=self::context((string)$r['lecture'],(string)$r['question'],$mode);
        if(!$lecture || !$question){return new WP_Error('mhl_not_found','Otázka nebo přednáška nebyla nalezena.',array('status'=>404));}
        $payload=self::question_payload($lecture,$question,$run,$session,$mode);
        $pid=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)$r->get_param('participant_id'));
        $payload['participant_nickname']='';
        if($mode!=='async' && strlen($pid)>=16 && strlen($pid)<=128){
            $sid=MHL_Core::get_lecture_subject_id((int)$lecture->ID);$db=MHL_DB::db();$pt=MHL_DB::table('participants');$pk=MHL_Core::participant_key($pid);$now=MHL_Core::now_mysql();
            $payload['participant_nickname']=(string)($db->get_var($db->prepare("SELECT nickname FROM {$pt} WHERE subject_id=%d AND mode=%s AND participant_key=%s AND expires_at>%s LIMIT 1",$sid,$mode,$pk,$now))?:'');
        }
        return new WP_REST_Response($payload,200);
    }


    public static function join(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $j=self::request_data($r);$lecture_slug=sanitize_title((string)($j['lecture_slug']??''));$question_slug=sanitize_title((string)($j['question_slug']??''));$m=sanitize_key((string)($j['mode']??'live'));$mode=in_array($m,array('test','async'),true)?$m:'live';$participant_id=(string)($j['participant_id']??'');
        [$lecture,$question,$run,$session]=self::context($lecture_slug,$question_slug,$mode);
        if(!$lecture||!$question||!$run||!$session){return new WP_Error('mhl_not_active','Otázka není aktivní.',array('status'=>409));}
        $session=MHL_Core::register_session_join((int)$session->id,$participant_id)?:$session;
        return new WP_REST_Response(array('ok'=>true,'status'=>$session->status,'question'=>self::question_payload($lecture,$question,$run,$session,$mode)),200);
    }

    private static function claim_nickname_for_subject(int $subject_id, string $mode, string $participant_id, string $nickname) {
        if (!$subject_id) { return new WP_Error('mhl_subject_missing','Předmět nebyl nalezen.',array('status'=>400)); }
        $nickname = sanitize_text_field($nickname);
        $nickname = function_exists('mb_substr') ? mb_substr($nickname,0,40) : substr($nickname,0,40);
        $nickname = trim(preg_replace('/\s+/u',' ',$nickname));
        if ($nickname === '') { return new WP_Error('mhl_nickname_required','Zadejte přezdívku.',array('status'=>400)); }
        if (strlen($participant_id)<16 || strlen($participant_id)>128) { return new WP_Error('mhl_bad_participant','Neplatný identifikátor zařízení.',array('status'=>400)); }
        $key=MHL_Core::nickname_key($nickname); if ($key==='') { return new WP_Error('mhl_nickname_required','Zadejte přezdívku.',array('status'=>400)); }
        $db=MHL_DB::db(); $table=MHL_DB::table('participants'); $votes=MHL_DB::table('votes'); $runs=MHL_DB::table('runs');
        $pkey=MHL_Core::participant_key($participant_id); $now=MHL_Core::now_mysql(); $days=max(1,min(3650,(int)(MHL_Core::settings()['nickname_reservation_days']??365))); $expires=gmdate('Y-m-d H:i:s',time()+$days*DAY_IN_SECONDS);
        // Uvolníme pouze propadlé rezervace. Aktivní přezdívky v jiných předmětech se nijak neovlivňují.
        $db->query($db->prepare("DELETE FROM {$table} WHERE subject_id=%d AND mode=%s AND expires_at<=%s",$subject_id,$mode,$now));
        $mine=$db->get_row($db->prepare("SELECT * FROM {$table} WHERE subject_id=%d AND mode=%s AND participant_key=%s LIMIT 1",$subject_id,$mode,$pkey));
        if ($mine && (string)$mine->nickname_key===$key) {
            $db->update($table,array('nickname'=>$nickname,'last_seen_at'=>$now,'expires_at'=>$expires),array('id'=>(int)$mine->id),array('%s','%s','%s'),array('%d'));
            return array('nickname'=>$nickname,'expires_at'=>$expires,'reservation_days'=>$days);
        }
        $occupied=$db->get_row($db->prepare("SELECT * FROM {$table} WHERE subject_id=%d AND mode=%s AND nickname_key=%s AND expires_at>%s LIMIT 1",$subject_id,$mode,$key,$now));
        if ($occupied && !hash_equals((string)$occupied->participant_key,$pkey)) {
            return new WP_Error('mhl_nickname_taken','Tato přezdívka je v tomto předmětu již používána. Zvolte jinou.',array('status'=>409));
        }
        // Kompatibilita s hlasy vytvořenými před verzí 0.8.2: poslední použití přezdívky v témže předmětu ji rezervuje původnímu zařízení po stejnou dobu.
        if (!$occupied) {
            $cutoff=gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS);
            $legacy=$db->get_row($db->prepare("SELECT v.participant_key,v.nickname,MAX(v.created_at) last_seen FROM {$votes} v INNER JOIN {$runs} r ON r.id=v.run_id WHERE r.subject_id=%d AND v.mode=%s AND LOWER(TRIM(v.nickname))=%s AND v.nickname<>'' AND v.created_at>%s GROUP BY v.participant_key,v.nickname ORDER BY last_seen DESC LIMIT 1",$subject_id,$mode,$key,$cutoff));
            if ($legacy && !hash_equals((string)$legacy->participant_key,$pkey)) {
                return new WP_Error('mhl_nickname_taken','Tato přezdívka je v tomto předmětu již používána. Zvolte jinou.',array('status'=>409));
            }
        }
        if ($mine) {
            $ok=$db->update($table,array('nickname'=>$nickname,'nickname_key'=>$key,'last_seen_at'=>$now,'expires_at'=>$expires),array('id'=>(int)$mine->id),array('%s','%s','%s','%s'),array('%d'));
        } else {
            $ok=$db->insert($table,array('subject_id'=>$subject_id,'mode'=>$mode,'participant_key'=>$pkey,'nickname'=>$nickname,'nickname_key'=>$key,'claimed_at'=>$now,'last_seen_at'=>$now,'expires_at'=>$expires),array('%d','%s','%s','%s','%s','%s','%s','%s'));
        }
        if ($ok===false) {
            $again=$db->get_row($db->prepare("SELECT participant_key FROM {$table} WHERE subject_id=%d AND mode=%s AND nickname_key=%s AND expires_at>%s LIMIT 1",$subject_id,$mode,$key,$now));
            if ($again && !hash_equals((string)$again->participant_key,$pkey)) { return new WP_Error('mhl_nickname_taken','Tato přezdívka je v tomto předmětu již používána. Zvolte jinou.',array('status'=>409)); }
            return new WP_Error('mhl_nickname_failed','Přezdívku se nepodařilo rezervovat.',array('status'=>500));
        }
        return array('nickname'=>$nickname,'expires_at'=>$expires,'reservation_days'=>$days);
    }

    public static function nickname_claim(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $j=self::request_data($r); $m=sanitize_key((string)($j['mode']??'live'));$mode=in_array($m,array('test','async'),true)?$m:'live';
        $participant_id=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)($j['participant_id']??'')); $nickname=(string)($j['nickname']??'');
        $lecture_slug=sanitize_title((string)($j['lecture_slug']??'')); $subject_slug=sanitize_title((string)($j['subject_slug']??''));
        $subject_id=0; $subject=null;
        if ($subject_slug!=='') { $subject=self::post_by_slug('mhl_subject',$subject_slug); if($subject){$subject_id=(int)$subject->ID;} }
        if (!$subject_id && $lecture_slug!=='') { $lecture=self::post_by_slug('mhl_lecture',$lecture_slug); if($lecture){$subject_id=MHL_Core::get_lecture_subject_id((int)$lecture->ID); $subject=$subject_id?get_post($subject_id):null;} }
        if (!$subject_id) { return new WP_Error('mhl_subject_missing','Předmět nebyl nalezen.',array('status'=>404)); }
        $claimed=self::claim_nickname_for_subject($subject_id,$mode,$participant_id,$nickname); if(is_wp_error($claimed)){return $claimed;}
        return new WP_REST_Response(array('ok'=>true,'nickname'=>$claimed['nickname'],'subject_slug'=>$subject?MHL_Core::permanent_slug((int)$subject->ID):$subject_slug,'expires_at'=>$claimed['expires_at'],'reservation_days'=>$claimed['reservation_days']),200);
    }

    public static function vote(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $j=self::request_data($r);
        $lecture_slug=sanitize_title((string)($j['lecture_slug']??'')); $question_slug=sanitize_title((string)($j['question_slug']??'')); $m=sanitize_key((string)($j['mode']??'live'));$mode=in_array($m,array('test','async'),true)?$m:'live';
        $participant_id=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)($j['participant_id']??'')); $nickname=sanitize_text_field((string)($j['nickname']??''));
        $nickname=function_exists('mb_substr')?mb_substr($nickname,0,40):substr($nickname,0,40); $option=(isset($j['option_index'])&&is_numeric($j['option_index']))?(int)$j['option_index']:-1;
        if(strlen($participant_id)<16||strlen($participant_id)>128){return new WP_Error('mhl_bad_participant','Neplatný identifikátor zařízení.',array('status'=>400));}
        [$lecture,$question,$run,$session]=self::context($lecture_slug,$question_slug,$mode);
        if(!$lecture||!$question){return new WP_Error('mhl_not_found','Otázka nebo přednáška nebyla nalezena.',array('status'=>404));}
        if(!$run||!$session||$session->status!=='open'){return new WP_Error('mhl_not_open','Hlasování právě není otevřené.',array('status'=>409));}
        $opts=MHL_Core::get_question_options((int)$question->ID); if($option<0||$option>=count($opts)){return new WP_Error('mhl_bad_option','Neplatná odpověď.',array('status'=>400));}
        $qmode=MHL_Core::question_type((int)$question->ID); $g=MHL_Core::run_gamification($run); $poll_points=(int)get_post_meta($question->ID,'_mhl_poll_points',true);
        $nickname_required=$g['enabled']&&($qmode==='quiz'||$poll_points>0); if($nickname_required&&$nickname===''){return new WP_Error('mhl_nickname_required','Zadejte přezdívku.',array('status'=>400));}
        if($nickname_required){$subject_id=MHL_Core::get_lecture_subject_id((int)$lecture->ID);$claim=self::claim_nickname_for_subject($subject_id,$mode,$participant_id,$nickname);if(is_wp_error($claim)){return $claim;}$nickname=$claim['nickname'];}
        // Názorová anketa bez bodů zůstává anonymní i tehdy, když má student přezdívku uloženou v prohlížeči.
        if(!$nickname_required){$nickname='';}
        $db=MHL_DB::db(); $votes=MHL_DB::table('votes');
        // Nebodovaná anketa používá pouze session-scoped klíč, takže nelze spojovat odpovědi napříč otázkami.
        $pkey=$nickname_required?MHL_Core::participant_key($participant_id):hash_hmac('sha256',$participant_id.'|session|'.(int)$session->id,wp_salt('nonce'));
        $existing=$db->get_row($db->prepare("SELECT * FROM {$votes} WHERE session_id=%d AND participant_key=%s LIMIT 1",(int)$session->id,$pkey));
        if($existing){return new WP_Error('mhl_already_voted','Na tuto otázku jste již hlasoval/a.',array('status'=>409));}
        $correct=null; $points=0; $opened=$session->opened_at?strtotime($session->opened_at.' UTC'):time(); $ms=$mode==='async'?0:max(0,(int)round((microtime(true)-(float)$opened)*1000));
        if($qmode==='quiz'){
            $ci=MHL_Core::question_correct_index((int)$question->ID); $correct=($ci!==null && $option===$ci)?1:0;
            if($correct){$s=MHL_Core::settings();$mult=(float)(get_post_meta($question->ID,'_mhl_multiplier',true)?:1);$win=max(5,(int)(get_post_meta($question->ID,'_mhl_speed_window',true)?:$s['speed_window']));$factor=max(0.0,1.0-(($ms/1000)/$win));$points=(int)round(((int)$s['base_points']+((int)$s['speed_points']*$factor))*$mult);}
        } elseif($g['enabled']&&$poll_points>0){$points=$poll_points;}
        $ok=$db->insert($votes,array('session_id'=>(int)$session->id,'run_id'=>(int)$run->id,'question_id'=>(int)$question->ID,'mode'=>$mode,'participant_key'=>$pkey,'nickname'=>$nickname,'option_index'=>$option,'is_correct'=>$correct,'response_ms'=>$ms,'points'=>$points,'created_at'=>MHL_Core::now_mysql()),array('%d','%d','%d','%s','%s','%s','%d','%d','%d','%d','%s'));
        if(!$ok){return new WP_Error('mhl_vote_failed','Hlas se nepodařilo uložit.',array('status'=>500));}
        return new WP_REST_Response(array('ok'=>true,'session_id'=>(int)$session->id,'response_ms'=>$ms,'results_pending'=>true),201);
    }


    public static function privacy_info(WP_REST_Request $r): WP_REST_Response {
        $s=MHL_Core::settings();
        return new WP_REST_Response(array(
            'controller_name'=>(string)($s['privacy_controller_name']??''),
            'contact_email'=>(string)($s['privacy_contact_email']??''),
            'policy_version'=>(string)($s['privacy_policy_version']??''),
            'retention'=>array('live_votes_days'=>(int)$s['privacy_live_retention_days'],'test_data_days'=>(int)$s['privacy_test_retention_days'],'technical_joins_days'=>(int)$s['privacy_join_retention_days'],'nickname_days'=>(int)$s['nickname_reservation_days']),
            'principles'=>array(
                'Soutěžní kvízy používají přezdívku a pseudonymní technický klíč.',
                'Plugin sám nevyžaduje jméno, studentské číslo ani e-mail účastníka.',
                'Nebodované ankety neukládají přezdívku a používají klíč platný pouze pro konkrétní otázku.',
                'Síň slávy je dobrovolná; přezdívka se zveřejní až po výslovném rozhodnutí účastníka. Ostatní mohou být podle nastavení skryti nebo anonymní.'
            )
        ),200);
    }

    private static function subject_from_request_slug(string $slug): ?WP_Post {
        return self::post_by_slug('mhl_subject',sanitize_title($slug));
    }

    public static function privacy_me(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $subject=self::subject_from_request_slug((string)$r->get_param('subject'));
        $pid=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)$r->get_param('participant_id'));
        if(!$subject){return new WP_Error('mhl_subject_missing','Předmět nebyl nalezen.',array('status'=>404));}
        if(strlen($pid)<16||strlen($pid)>128){return new WP_Error('mhl_bad_participant','Neplatný identifikátor zařízení.',array('status'=>400));}
        $db=MHL_DB::db();$participants=MHL_DB::table('participants');$votes=MHL_DB::table('votes');$runs=MHL_DB::table('runs');$pkey=MHL_Core::participant_key($pid);
        $row=$db->get_row($db->prepare("SELECT nickname,hall_of_fame_opt_in,hall_visibility,expires_at,last_seen_at FROM {$participants} WHERE subject_id=%d AND mode='live' AND participant_key=%s LIMIT 1",(int)$subject->ID,$pkey));
        $agg=$db->get_row($db->prepare("SELECT COUNT(*) answers,COALESCE(SUM(points),0) points,COALESCE(SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END),0) correct_count FROM {$votes} v INNER JOIN {$runs} r ON r.id=v.run_id WHERE r.subject_id=%d AND v.mode='live' AND v.participant_key=%s",(int)$subject->ID,$pkey));
        return new WP_REST_Response(array('subject_title'=>$subject->post_title,'subject_slug'=>MHL_Core::permanent_slug((int)$subject->ID),'nickname'=>$row?(string)$row->nickname:'','hall_of_fame_opt_in'=>$row?(bool)$row->hall_of_fame_opt_in:false,'hall_visibility'=>$row?(string)($row->hall_visibility??'unset'):'unset','expires_at'=>$row?(string)$row->expires_at:null,'answers'=>(int)($agg->answers??0),'points'=>(int)($agg->points??0),'correct_count'=>(int)($agg->correct_count??0),'hall_of_fame'=>MHL_Core::subject_hall_of_fame((int)$subject->ID)),200);
    }

    public static function privacy_hall_opt_in(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $j=self::request_data($r);$subject=self::subject_from_request_slug((string)($j['subject_slug']??''));$pid=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)($j['participant_id']??''));
        if(!$subject){return new WP_Error('mhl_subject_missing','Předmět nebyl nalezen.',array('status'=>404));}
        if(strlen($pid)<16||strlen($pid)>128){return new WP_Error('mhl_bad_participant','Neplatný identifikátor zařízení.',array('status'=>400));}
        $db=MHL_DB::db();$table=MHL_DB::table('participants');$pkey=MHL_Core::participant_key($pid);
        $visibility=sanitize_key((string)($j['visibility']??''));
        if($visibility===''){$visibility=!empty($j['opt_in'])?'nickname':'unset';}
        if(!in_array($visibility,array('unset','nickname','anonymous','hidden'),true)){$visibility='unset';}
        $row=$db->get_row($db->prepare("SELECT id FROM {$table} WHERE subject_id=%d AND mode='live' AND participant_key=%s LIMIT 1",(int)$subject->ID,$pkey));
        if(!$row){return new WP_Error('mhl_participant_missing','Nejprve si v tomto předmětu rezervujte přezdívku.',array('status'=>409));}
        $opt=$visibility==='nickname'?1:0;$opted=in_array($visibility,array('nickname','anonymous','hidden'),true)?MHL_Core::now_mysql():null;
        $db->update($table,array('hall_of_fame_opt_in'=>$opt,'hall_visibility'=>$visibility,'hall_opted_at'=>$opted),array('id'=>(int)$row->id),array('%d','%s','%s'),array('%d'));
        return new WP_REST_Response(array('ok'=>true,'hall_of_fame_opt_in'=>(bool)$opt,'hall_visibility'=>$visibility),200);
    }

    public static function privacy_delete(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $j=self::request_data($r);$subject=self::subject_from_request_slug((string)($j['subject_slug']??''));$pid=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)($j['participant_id']??''));
        if(!$subject){return new WP_Error('mhl_subject_missing','Předmět nebyl nalezen.',array('status'=>404));}
        if(strlen($pid)<16||strlen($pid)>128){return new WP_Error('mhl_bad_participant','Neplatný identifikátor zařízení.',array('status'=>400));}
        $db=MHL_DB::db();$pkey=MHL_Core::participant_key($pid);$votes=MHL_DB::table('votes');$runs=MHL_DB::table('runs');$sessions=MHL_DB::table('sessions');$joins=MHL_DB::table('session_joins');$participants=MHL_DB::table('participants');
        $run_ids=array_values(array_filter(array_map('absint',$db->get_col($db->prepare("SELECT id FROM {$runs} WHERE subject_id=%d",(int)$subject->ID))?:array())));
        if($run_ids){$ids=implode(',',$run_ids);$session_ids=array_values(array_filter(array_map('absint',$db->get_col("SELECT id FROM {$sessions} WHERE run_id IN ({$ids})")?:array())));if($session_ids){$sids=implode(',',$session_ids);$db->query($db->prepare("DELETE FROM {$joins} WHERE participant_key=%s AND session_id IN ({$sids})",$pkey));}$db->query($db->prepare("DELETE FROM {$votes} WHERE participant_key=%s AND run_id IN ({$ids})",$pkey));}
        $db->query($db->prepare("DELETE FROM {$participants} WHERE subject_id=%d AND participant_key=%s",(int)$subject->ID,$pkey));
        return new WP_REST_Response(array('ok'=>true,'message'=>'Data spojená s tímto účastnickým identifikátorem byla z předmětu odstraněna. Anonymní ankety bez bodů nejsou s tímto identifikátorem propojené.'),200);
    }

    public static function hall_of_fame(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $subject=self::subject_from_request_slug((string)$r['subject']);
        if(!$subject){return new WP_Error('mhl_subject_missing','Předmět nebyl nalezen.',array('status'=>404));}
        $h=MHL_Core::subject_hall_of_fame((int)$subject->ID);
        if(empty($h['enabled'])){return new WP_Error('mhl_hall_disabled','Síň slávy není pro tento předmět zveřejněna.',array('status'=>404));}
        $db=MHL_DB::db();$participants=MHL_DB::table('participants');$votes=MHL_DB::table('votes');$runs=MHL_DB::table('runs');
        if(($h['visibility']??'public')==='participants'){
            $pid=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)$r->get_param('participant_id'));
            if(strlen($pid)<16||strlen($pid)>128){return new WP_Error('mhl_hall_participants_only','Tato Síň slávy je dostupná pouze účastníkům předmětu.',array('status'=>403));}
            $pkey=MHL_Core::participant_key($pid);$now=MHL_Core::now_mysql();
            $allowed=$db->get_var($db->prepare("SELECT id FROM {$participants} WHERE subject_id=%d AND mode='live' AND participant_key=%s AND expires_at>%s LIMIT 1",(int)$subject->ID,$pkey,$now));
            if(!$allowed){return new WP_Error('mhl_hall_participants_only','Tato Síň slávy je dostupná pouze účastníkům předmětu.',array('status'=>403));}
        }
        $cutoff=gmdate('Y-m-d H:i:s',time()-(int)$h['period_days']*DAY_IN_SECONDS);$limit=max(3,min(100,(int)$h['limit']));
        $sql="SELECT v.participant_key,MAX(v.nickname) nickname,SUM(v.points) points,SUM(CASE WHEN v.is_correct=1 THEN 1 ELSE 0 END) correct_count,COUNT(v.id) answers,MIN(v.created_at) first_vote,MAX(COALESCE(p.hall_visibility,'unset')) hall_visibility FROM {$runs} r INNER JOIN {$votes} v ON v.run_id=r.id AND v.mode='live' LEFT JOIN {$participants} p ON p.subject_id=r.subject_id AND p.mode='live' AND p.participant_key=v.participant_key WHERE r.subject_id=%d AND v.nickname<>'' AND v.created_at>=%s GROUP BY v.participant_key ORDER BY points DESC,correct_count DESC,first_vote ASC LIMIT %d";
        $rows=$db->get_results($db->prepare($sql,(int)$subject->ID,$cutoff,$limit));$items=array();
        foreach($rows?:array() as $i=>$x){$vis=(string)($x->hall_visibility??'unset');$name='';if($vis==='nickname'){$name=(string)$x->nickname;}elseif($vis==='anonymous'||($vis==='unset'&&($h['nonopt_mode']??'hidden')==='anonymous')){$name='Anonymní účastník';}elseif($vis==='hidden'||$vis==='unset'){continue;}else{continue;}$items[]=array('rank'=>$i+1,'nickname'=>$name,'anonymous'=>$name==='Anonymní účastník','points'=>(int)$x->points,'correct_count'=>(int)$x->correct_count,'answers'=>(int)$x->answers);}
        return new WP_REST_Response(array('subject_title'=>$subject->post_title,'subject_slug'=>MHL_Core::permanent_slug((int)$subject->ID),'title'=>$h['title'],'period_days'=>$h['period_days'],'limit'=>$limit,'visibility'=>$h['visibility']??'public','nonopt_mode'=>$h['nonopt_mode']??'hidden','entries'=>$items,'privacy_url'=>$h['privacy_url']),200);
    }

    public static function participant_subject(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $subject=self::subject_from_request_slug((string)$r['subject']);$pid=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)$r->get_param('participant_id'));$mode=self::mode($r);
        if(!$subject){return new WP_Error('mhl_subject_missing','Předmět nebyl nalezen.',array('status'=>404));}if(strlen($pid)<16||strlen($pid)>128){return new WP_Error('mhl_bad_participant','Neplatný identifikátor zařízení.',array('status'=>400));}
        $db=MHL_DB::db();$pt=MHL_DB::table('participants');$pk=MHL_Core::participant_key($pid);$now=MHL_Core::now_mysql();$row=$db->get_row($db->prepare("SELECT nickname,hall_visibility,expires_at FROM {$pt} WHERE subject_id=%d AND mode=%s AND participant_key=%s AND expires_at>%s LIMIT 1",(int)$subject->ID,$mode,$pk,$now));
        return new WP_REST_Response(array('subject_slug'=>MHL_Core::permanent_slug((int)$subject->ID),'nickname'=>$row?(string)$row->nickname:'','hall_visibility'=>$row?(string)($row->hall_visibility??'unset'):'unset','expires_at'=>$row?(string)$row->expires_at:null),200);
    }

    public static function projection_current(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $subject=self::subject_from_request_slug((string)$r['subject']);if(!$subject){return new WP_Error('mhl_not_found','Předmět nebyl nalezen.',array('status'=>404));}
        $projection=MHL_Core::subject_projection((int)$subject->ID);$token=(string)$r['token'];if(empty($projection['token'])||!hash_equals((string)$projection['token'],$token)){return new WP_Error('mhl_projection_forbidden','Neplatný projekční odkaz.',array('status'=>403));}
        $run=MHL_Core::get_active_run_for_subject((int)$subject->ID,'live');$base=array('subject_slug'=>MHL_Core::permanent_slug((int)$subject->ID),'subject_title'=>$subject->post_title,'subject_template'=>MHL_Core::subject_template((int)$subject->ID),'teachers'=>MHL_Core::subject_teachers((int)$subject->ID,true));
        if(!$run){return new WP_REST_Response(array_merge($base,array('status'=>'idle','run_id'=>null,'lecture_slug'=>null,'question_slug'=>null)),200);}
        $lecture=get_post((int)$run->lecture_id);$session=MHL_Core::current_open_session((int)$run->id,'live');
        if(!$session){$db=MHL_DB::db();$st=MHL_DB::table('sessions');$session=$db->get_row($db->prepare("SELECT * FROM {$st} WHERE run_id=%d AND mode='live' AND status IN ('closed','skipped') ORDER BY id DESC LIMIT 1",(int)$run->id));}
        if(!$session){return new WP_REST_Response(array_merge($base,array('status'=>'waiting','run_id'=>(int)$run->id,'lecture_slug'=>$lecture?MHL_Core::permanent_slug((int)$lecture->ID):null,'question_slug'=>null,'lecture_title'=>$lecture?$lecture->post_title:'')),200);}
        $q=get_post((int)$session->question_id);return new WP_REST_Response(array_merge($base,array('status'=>$session->status,'run_id'=>(int)$run->id,'session_id'=>(int)$session->id,'lecture_slug'=>$lecture?MHL_Core::permanent_slug((int)$lecture->ID):null,'question_slug'=>$q?MHL_Core::permanent_slug((int)$q->ID):null,'lecture_title'=>$lecture?$lecture->post_title:'')),200);
    }

    private static function subject_run_ids(int $lecture_id, string $mode): array {
        $subject_id = MHL_Core::get_lecture_subject_id($lecture_id);
        if (!$subject_id) { return array(); }
        $lectures = get_posts(array(
            'post_type'=>'mhl_lecture','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids',
            'meta_key'=>'_mhl_subject_id','meta_value'=>$subject_id
        ));
        $lecture_ids = array_values(array_filter(array_map('absint', $lectures ?: array())));
        if (!$lecture_ids) { return array(); }
        $db = MHL_DB::db(); $runs = MHL_DB::table('runs');
        $placeholders = implode(',', array_fill(0, count($lecture_ids), '%d'));
        $sql = "SELECT id FROM {$runs} WHERE lecture_id IN ({$placeholders}) AND mode=%s";
        $args = array_merge($lecture_ids, array($mode));
        $ids = $db->get_col($db->prepare($sql, $args));
        return array_values(array_filter(array_map('absint', $ids ?: array())));
    }

    private static function leaderboard_for_runs(array $run_ids, string $mode): array {
        $run_ids = array_values(array_filter(array_map('absint', $run_ids)));
        if (!$run_ids) { return array(); }
        $db=MHL_DB::db(); $votes=MHL_DB::table('votes');
        $placeholders = implode(',', array_fill(0, count($run_ids), '%d'));
        $sql = "SELECT participant_key,MAX(nickname) nickname,SUM(points) points,SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) correct_count,COUNT(*) answers,MIN(created_at) first_vote FROM {$votes} WHERE run_id IN ({$placeholders}) AND mode=%s AND nickname<>'' GROUP BY participant_key ORDER BY points DESC,correct_count DESC,first_vote ASC LIMIT 20";
        $rows=$db->get_results($db->prepare($sql, array_merge($run_ids, array($mode))));
        return array_map(static fn($x)=>array('nickname'=>$x->nickname,'points'=>(int)$x->points,'correct_count'=>(int)$x->correct_count,'answers'=>(int)$x->answers),$rows?:array());
    }

    private static function participant_result(?object $run, ?object $session, WP_Post $question, string $mode, string $participant_id): ?array {
        if(!$run || !$session || $participant_id==='' || strlen($participant_id)<16 || strlen($participant_id)>128){return null;}
        $db=MHL_DB::db(); $votes=MHL_DB::table('votes');
        $g=MHL_Core::run_gamification($run); $qmode=MHL_Core::question_type((int)$question->ID); $poll_points=(int)get_post_meta($question->ID,'_mhl_poll_points',true);
        $linked_identity=$g['enabled'] && ($qmode==='quiz' || $poll_points>0);
        // Anonymní/nebodované ankety používají session-scoped klíč: výsledek lze vrátit jen tomuto prohlížeči, ale hlas nelze propojovat napříč otázkami.
        $pkey=$linked_identity?MHL_Core::participant_key($participant_id):hash_hmac('sha256',$participant_id.'|session|'.(int)$session->id,wp_salt('nonce'));
        $vote=$db->get_row($db->prepare("SELECT nickname,option_index,is_correct,response_ms,points FROM {$votes} WHERE session_id=%d AND mode=%s AND participant_key=%s LIMIT 1",(int)$session->id,$mode,$pkey));
        if(!$vote){return null;}
        $opts=MHL_Core::get_question_options((int)$question->ID);
        $idx=(int)$vote->option_index;
        $scope = $g['scope'] ?? 'lecture';
        $run_ids = ($scope === 'subject') ? self::subject_run_ids((int)$run->lecture_id, $mode) : array((int)$run->id);
        if (!$run_ids) { $run_ids = array((int)$run->id); }
        $placeholders = implode(',', array_fill(0, count($run_ids), '%d'));
        $summary_sql = "SELECT COALESCE(SUM(points),0) total_points, COUNT(*) total_answers, COALESCE(SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END),0) total_correct FROM {$votes} WHERE run_id IN ({$placeholders}) AND mode=%s AND participant_key=%s";
        $summary=$db->get_row($db->prepare($summary_sql, array_merge($run_ids, array($mode, $pkey))));
        $out=array(
            'has_voted'=>true,
            'option_index'=>$idx,
            'option_code'=>isset($opts[$idx])?chr(65+$idx):'',
            'option_label'=>$opts[$idx]??'',
            'response_ms'=>(int)$vote->response_ms,
            'points'=>(int)$vote->points,
            'is_correct'=>is_null($vote->is_correct)?null:(bool)$vote->is_correct,
            'nickname'=>(string)$vote->nickname,
            'scope'=>$scope,
            'scope_label'=>$scope==='subject'?'v předmětu':'v přednášce',
            'lecture_points'=>(int)($summary->total_points ?? $vote->points),
            'lecture_answers'=>(int)($summary->total_answers ?? 1),
            'lecture_correct'=>(int)($summary->total_correct ?? (((int)$vote->is_correct===1)?1:0)),
            'total_points'=>(int)($summary->total_points ?? $vote->points),
            'total_answers'=>(int)($summary->total_answers ?? 1),
            'total_correct'=>(int)($summary->total_correct ?? (((int)$vote->is_correct===1)?1:0)),
            'lecture_rank'=>null,
            'lecture_participants'=>null,
            'total_rank'=>null,
            'total_participants'=>null,
        );
        if($g['enabled'] && (string)$vote->nickname!==''){
            $rows = self::leaderboard_for_runs($run_ids, $mode);
            $out['lecture_participants']=count($rows?:array());
            $out['total_participants']=count($rows?:array());
            foreach(($rows?:array()) as $i=>$row){
                // leaderboard_for_runs does not expose participant_key; repeat a compact rank query with participant_key.
            }
            $rank_sql = "SELECT participant_key,SUM(points) points,SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) correct_count,MIN(created_at) first_vote FROM {$votes} WHERE run_id IN ({$placeholders}) AND mode=%s AND nickname<>'' GROUP BY participant_key ORDER BY points DESC,correct_count DESC,first_vote ASC";
            $rank_rows=$db->get_results($db->prepare($rank_sql, array_merge($run_ids, array($mode))));
            $out['lecture_participants']=count($rank_rows?:array());
            $out['total_participants']=count($rank_rows?:array());
            foreach(($rank_rows?:array()) as $i=>$row){
                if(hash_equals((string)$row->participant_key,$pkey)){$out['lecture_rank']=$i+1;$out['total_rank']=$i+1;break;}
            }
        }
        if($mode==='live' && !empty($run->subject_id)){
            $h=MHL_Core::subject_hall_of_fame((int)$run->subject_id);
            if(!empty($h['enabled'])){
                $pt=MHL_DB::table('participants');$prow=$db->get_row($db->prepare("SELECT hall_visibility FROM {$pt} WHERE subject_id=%d AND mode='live' AND participant_key=%s LIMIT 1",(int)$run->subject_id,$pkey));
                $rank=(int)($out['total_rank']??0);$out['hall_of_fame']=array('enabled'=>true,'eligible'=>$rank>0&&$rank<=(int)$h['limit'],'limit'=>(int)$h['limit'],'rank'=>$rank,'visibility'=>$prow?(string)($prow->hall_visibility??'unset'):'unset','url'=>$h['url'],'nonopt_mode'=>$h['nonopt_mode']??'hidden');
            }
        }
        return $out;
    }

    public static function results(WP_REST_Request $r): WP_REST_Response|WP_Error {
        $mode=self::mode($r); $participant_id=preg_replace('/[^a-zA-Z0-9\-_]/','',(string)$r->get_param('participant_id')); [$lecture,$question,$run,$session]=self::context((string)$r['lecture'],(string)$r['question'],$mode);
        if(!$lecture||!$question){return new WP_Error('mhl_not_found','Otázka nebo přednáška nebyla nalezena.',array('status'=>404));}
        $opts=MHL_Core::get_question_options((int)$question->ID);$counts=array_fill(0,count($opts),0);$total=0;$qlb=array();$olb=array();$g=MHL_Core::run_gamification($run);
        if($session&&$run){$db=MHL_DB::db();$votes=MHL_DB::table('votes');$rows=$db->get_results($db->prepare("SELECT option_index,COUNT(*) c FROM {$votes} WHERE session_id=%d AND mode=%s GROUP BY option_index",(int)$session->id,$mode));foreach($rows?:array() as $row){$i=(int)$row->option_index;if(isset($counts[$i])){$counts[$i]=(int)$row->c;$total+=(int)$row->c;}}
            if($g['enabled']&&$session->status==='closed'){$qmode=MHL_Core::question_type((int)$question->ID);if($qmode==='quiz'){$qlb=self::question_leaderboard((int)$session->id,$mode);}if($g['scope']==='subject'){$olb=self::leaderboard_for_runs(self::subject_run_ids((int)$lecture->ID,$mode),$mode);}elseif($g['scope']==='lecture'){$olb=self::run_leaderboard((int)$run->id,$mode);}}
        }
        $qmode=MHL_Core::question_type((int)$question->ID);$correct_index=null;if($qmode==='quiz'&&$session&&$session->status==='closed'){$correct_index=MHL_Core::question_correct_index((int)$question->ID);}
        $show_live=(bool)get_post_meta($lecture->ID,'_mhl_show_live_results',true);$reveal=$mode==='async'?MHL_Core::question_async_show_results((int)$question->ID):(!$session||$session->status!=='open'||$show_live||$mode==='test');
        $data=array();foreach($opts as $i=>$label){$data[]=array('index'=>$i,'code'=>chr(65+$i),'label'=>$label,'count'=>$reveal?($counts[$i]??0):null,'percent'=>$reveal?($total>0?round((($counts[$i]??0)/$total)*100,1):0):null);}
        $participant_result=($session && ($session->status!=='open'||$mode==='async'))?self::participant_result($run,$session,$question,$mode,$participant_id):null;
        return new WP_REST_Response(array('question_id'=>(int)$question->ID,'lecture_id'=>(int)$lecture->ID,'question_slug'=>MHL_Core::permanent_slug((int)$question->ID),'title'=>$question->post_title,'mode'=>$qmode,'run_mode'=>$mode,'long_poll'=>$mode==='async','show_results_after_vote'=>$mode==='async'?MHL_Core::question_async_show_results((int)$question->ID):false,'status'=>$session?$session->status:'idle','session_id'=>$session?(int)$session->id:null,'opened_at_ms'=>$session&&$session->opened_at?(int)(strtotime($session->opened_at.' UTC')*1000):null,'closes_at_ms'=>$session&&$session->reset_at?(int)(strtotime($session->reset_at.' UTC')*1000):null,'server_now_ms'=>(int)round(microtime(true)*1000),'time_limit_seconds'=>$mode==='async'?0:MHL_Core::question_time_limit((int)$question->ID),'total'=>$total,'reveal_results'=>$reveal,'options'=>$data,'correct_index'=>$correct_index,'gamification'=>$g,'score_scope_label'=>(($g['scope']??'lecture')==='subject'?'v předmětu':'v této přednášce'),'question_leaderboard'=>$qlb,'overall_leaderboard'=>$olb,'vote_url'=>MHL_Core::get_vote_url((int)$lecture->ID,(int)$question->ID,$mode),'subject_title'=>MHL_Core::get_subject_title((int)$lecture->ID),'subject_slug'=>MHL_Core::get_subject_slug((int)$lecture->ID),'lecture_title'=>$lecture->post_title,'teachers'=>MHL_Core::lecture_teachers((int)$lecture->ID,true),'subject_template'=>MHL_Core::lecture_subject_template((int)$lecture->ID),'hall_of_fame'=>MHL_Core::lecture_hall_of_fame((int)$lecture->ID),'privacy_url'=>trailingslashit((string)MHL_Core::settings()['frontend_url']).'privacy','cta'=>MHL_Core::lecture_ctas((int)$lecture->ID),'participant_result'=>$participant_result),200);
    }
    private static function question_leaderboard(int $session_id,string $mode): array {
        $db=MHL_DB::db();$votes=MHL_DB::table('votes');$rows=$db->get_results($db->prepare("SELECT nickname,option_index,is_correct,response_ms,points FROM {$votes} WHERE session_id=%d AND mode=%s AND nickname<>'' ORDER BY points DESC,response_ms ASC,id ASC LIMIT 20",$session_id,$mode));
        return array_map(static fn($x)=>array('nickname'=>$x->nickname,'option_index'=>(int)$x->option_index,'is_correct'=>is_null($x->is_correct)?null:(bool)$x->is_correct,'response_ms'=>(int)$x->response_ms,'points'=>(int)$x->points),$rows?:array());
    }
    private static function run_leaderboard(int $run_id,string $mode): array {
        $db=MHL_DB::db();$votes=MHL_DB::table('votes');$rows=$db->get_results($db->prepare("SELECT participant_key,MAX(nickname) nickname,SUM(points) points,SUM(CASE WHEN is_correct=1 THEN 1 ELSE 0 END) correct_count,COUNT(*) answers FROM {$votes} WHERE run_id=%d AND mode=%s AND nickname<>'' GROUP BY participant_key ORDER BY points DESC,correct_count DESC LIMIT 20",$run_id,$mode));
        return array_map(static fn($x)=>array('nickname'=>$x->nickname,'points'=>(int)$x->points,'correct_count'=>(int)$x->correct_count,'answers'=>(int)$x->answers),$rows?:array());
    }
}
