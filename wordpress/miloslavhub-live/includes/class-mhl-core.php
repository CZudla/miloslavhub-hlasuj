<?php
if (!defined('ABSPATH')) { exit; }

class MHL_Core {
    public static function init(): void {
        add_action('init', array(__CLASS__, 'register_content_types'));
        add_action('init', array(__CLASS__, 'maybe_close_expired_runs'));
        add_action('init', array(__CLASS__, 'maybe_purge_privacy_data'), 30);
        add_action('admin_init', array(__CLASS__, 'ensure_permanent_slugs'));
        add_filter('wp_insert_post_data', array(__CLASS__, 'lock_permanent_slug_on_update'), 20, 2);
        foreach (self::permanent_slug_post_types() as $post_type) {
            add_action('save_post_' . $post_type, array(__CLASS__, 'remember_permanent_slug'), 1, 3);
        }
    }

    private static function permanent_slug_post_types(): array {
        return array('mhl_subject','mhl_lecture','mhl_question');
    }

    /**
     * Jednorázově převezme současné slugy jako trvalé identifikátory.
     * Díky tomu zůstanou všechny již vytvořené QR kódy funkční i po aktualizaci pluginu.
     */
    public static function ensure_permanent_slugs(): void {
        if (!current_user_can('manage_options')) { return; }
        if (get_option('mhl_permanent_slug_schema') === '1') { return; }
        $posts = get_posts(array(
            'post_type'=>self::permanent_slug_post_types(),
            'post_status'=>'any',
            'numberposts'=>-1,
            'fields'=>'ids',
            'orderby'=>'ID',
            'order'=>'ASC',
        ));
        foreach ($posts as $post_id) {
            $post = get_post((int)$post_id);
            if (!$post || !$post->post_name || $post->post_status === 'auto-draft') { continue; }
            if (!get_post_meta((int)$post_id, '_mhl_permanent_slug', true)) {
                update_post_meta((int)$post_id, '_mhl_permanent_slug', sanitize_title((string)$post->post_name));
            }
        }
        update_option('mhl_permanent_slug_schema', '1', false);
    }

    private static function permanent_slug_belongs_elsewhere(string $post_type,string $slug,int $exclude_id=0): bool {
        if($slug===''){return false;}
        $ids=get_posts(array(
            'post_type'=>$post_type,'post_status'=>'any','numberposts'=>2,'fields'=>'ids',
            'meta_key'=>'_mhl_permanent_slug','meta_value'=>$slug,
            'post__not_in'=>$exclude_id?array($exclude_id):array()
        ));
        return !empty($ids);
    }

    /** Uloží slug při prvním skutečném uložení záznamu. Později se již nemění. */
    public static function remember_permanent_slug(int $post_id, WP_Post $post, bool $update): void {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) { return; }
        if (!in_array($post->post_type, self::permanent_slug_post_types(), true)) { return; }
        if ($post->post_status === 'auto-draft' || !$post->post_name) { return; }
        $stable=sanitize_title((string)get_post_meta($post_id, '_mhl_permanent_slug', true));
        // Klonovací pluginy někdy kopírují i metadata. Pokud by kopie zdědila cizí trvalý slug,
        // vezmeme její vlastní WordPress slug a zabráníme kolizi dvou QR adres.
        if ($stable==='' || self::permanent_slug_belongs_elsewhere($post->post_type,$stable,$post_id)) {
            update_post_meta($post_id, '_mhl_permanent_slug', sanitize_title((string)$post->post_name));
        }
    }

    /** Zabrání WordPressu nebo ruční editaci změnit slug po jeho uzamčení. */
    public static function lock_permanent_slug_on_update(array $data, array $postarr): array {
        $post_type = (string)($data['post_type'] ?? '');
        if (!in_array($post_type, self::permanent_slug_post_types(), true)) { return $data; }
        $post_id = absint($postarr['ID'] ?? 0);
        if (!$post_id) { return $data; }
        $stable = sanitize_title((string)get_post_meta($post_id, '_mhl_permanent_slug', true));
        if ($stable !== '' && !self::permanent_slug_belongs_elsewhere($post_type,$stable,$post_id)) { $data['post_name'] = $stable; }
        return $data;
    }

    /** Vrací trvalý slug; při starších datech bezpečně použije aktuální post_name. */
    public static function permanent_slug(int $post_id): string {
        $stable = sanitize_title((string)get_post_meta($post_id, '_mhl_permanent_slug', true));
        if ($stable !== '') { return $stable; }
        $post = get_post($post_id);
        return $post ? sanitize_title((string)$post->post_name) : '';
    }

    public static function register_content_types(): void {
        register_post_type('mhl_subject', array(
            'labels'=>array('name'=>'Předměty','singular_name'=>'Předmět','add_new_item'=>'Přidat předmět','edit_item'=>'Upravit předmět','new_item'=>'Nový předmět','search_items'=>'Hledat předměty'),
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'mhl-live','supports'=>array('title'),'show_in_rest'=>false,'menu_icon'=>'dashicons-book-alt'
        ));
        register_post_type('mhl_lecture', array(
            'labels'=>array('name'=>'Přednášky','singular_name'=>'Přednáška','add_new_item'=>'Přidat přednášku','edit_item'=>'Upravit přednášku','new_item'=>'Nová přednáška','search_items'=>'Hledat přednášky'),
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'mhl-live','supports'=>array('title'),'show_in_rest'=>false,'menu_icon'=>'dashicons-welcome-learn-more'
        ));
        register_post_type('mhl_question', array(
            'labels'=>array('name'=>'Banka otázek','singular_name'=>'Otázka','add_new_item'=>'Přidat otázku','edit_item'=>'Upravit otázku','new_item'=>'Nová otázka','search_items'=>'Hledat otázky'),
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'mhl-live','supports'=>array('title'),'show_in_rest'=>false,'menu_icon'=>'dashicons-editor-help'
        ));
        register_taxonomy('mhl_question_category', array('mhl_question'), array(
            'labels'=>array('name'=>'Kategorie otázek','singular_name'=>'Kategorie otázky'),
            'public'=>false,'show_ui'=>true,'show_admin_column'=>true,'hierarchical'=>true,'show_in_rest'=>false
        ));
    }

    public static function settings(): array {
        $settings=wp_parse_args(get_option('mhl_settings', array()), array(
            'frontend_url'=>'https://hlasuj.miloslavhub.cz',
            'question_seconds'=>60, // legacy fallback
            'quiz_seconds'=>30,
            'poll_seconds'=>0,
            'run_minutes'=>120,
            'base_points'=>800,
            'speed_points'=>200,
            'speed_window'=>20,
            'allowed_origin'=>'https://hlasuj.miloslavhub.cz',
            'main_site_url'=>'https://miloslavhub.cz',
            'assistant_url'=>'',
            'nickname_reservation_days'=>365,
            'join_min_wait_seconds'=>5,
            'join_quiet_seconds'=>3,
            'join_max_wait_seconds'=>15,
            'privacy_controller_name'=>'Miloslav Hub',
            'privacy_contact_email'=>'miloslav@miloslavhub.cz',
            'privacy_live_retention_days'=>365,
            'privacy_test_retention_days'=>30,
            'privacy_join_retention_days'=>7,
            'privacy_policy_version'=>'2026-09-24'
        ));
        // Normalizace staršího výchozího nastavení: pokud AI asistent omylem ukazuje jen na hlavní web,
        // považujeme jej za nenastavený.
        if(!empty($settings['assistant_url']) && untrailingslashit((string)$settings['assistant_url'])===untrailingslashit((string)$settings['main_site_url'])){
            $settings['assistant_url']='';
        }
        return $settings;
    }

    public static function now_mysql(): string { return current_time('mysql', true); }
    public static function mysql_after_minutes(int $minutes): string { return gmdate('Y-m-d H:i:s', time() + max(1,$minutes)*MINUTE_IN_SECONDS); }
    public static function mysql_after_seconds(int $seconds): string { return gmdate('Y-m-d H:i:s', time() + max(1,$seconds)); }
    public static function participant_key(string $participant_id): string { return hash_hmac('sha256', $participant_id, wp_salt('auth')); }
    public static function nickname_key(string $nickname): string {
        $nickname = trim(preg_replace('/\s+/u', ' ', sanitize_text_field($nickname)));
        if (function_exists('mb_strtolower')) { $nickname = mb_strtolower($nickname, 'UTF-8'); } else { $nickname = strtolower($nickname); }
        return $nickname;
    }

    public static function get_question_options(int $question_id): array {
        $options = get_post_meta($question_id, '_mhl_options', true);
        return is_array($options) ? array_values(array_filter(array_map('strval',$options), static fn($v)=>$v!=='')) : array();
    }
    public static function question_correct_index(int $question_id): ?int {
        $raw=get_post_meta($question_id,'_mhl_correct_index',true);
        if($raw==='' || $raw===null){return null;}
        $idx=(int)$raw; $opts=self::get_question_options($question_id);
        return ($idx>=0 && $idx<count($opts))?$idx:null;
    }
    public static function question_type(int $question_id): string { return self::question_correct_index($question_id)!==null?'quiz':'poll'; }


    /** Dlouhodobá anketa běžící mimo živou přednášku. */
    public static function question_async_enabled(int $question_id): bool {
        return self::question_type($question_id)==='poll' && (bool)get_post_meta($question_id,'_mhl_async_enabled',true);
    }
    public static function question_async_show_results(int $question_id): bool {
        $raw=get_post_meta($question_id,'_mhl_async_show_results',true);
        return $raw==='' ? true : (bool)$raw;
    }
    public static function question_async_end_mysql(int $question_id): ?string {
        $raw=trim((string)get_post_meta($question_id,'_mhl_async_end',true));
        if($raw===''){return null;}
        try {
            $local=new DateTimeImmutable($raw,wp_timezone());
            return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable $e) { return null; }
    }

    public static function question_time_limit(int $question_id): int {
        $raw=get_post_meta($question_id,'_mhl_time_limit',true);
        if($raw!=='' && $raw!==null){
            $v=(int)$raw;
            if($v<=0){return 0;}
            return max(5,min(600,$v));
        }
        $s=self::settings();
        $v=self::question_type($question_id)==='quiz'?(int)$s['quiz_seconds']:(int)$s['poll_seconds'];
        if($v<=0){return 0;}
        return max(5,min(600,$v));
    }

    public static function get_lecture_question_ids(int $lecture_id): array {
        $ids = get_post_meta($lecture_id, '_mhl_question_ids', true);
        return is_array($ids) ? array_values(array_unique(array_map('absint',$ids))) : array();
    }
    public static function get_lecture_subject_id(int $lecture_id): int { return absint(get_post_meta($lecture_id, '_mhl_subject_id', true)); }
    public static function get_subject_title(int $lecture_id): string {
        $id = self::get_lecture_subject_id($lecture_id); return $id ? (string) get_the_title($id) : '';
    }
    public static function get_subject_slug(int $lecture_id): string {
        $id=self::get_lecture_subject_id($lecture_id); if(!$id){return '';}
        $subject=get_post($id); return $subject ? self::permanent_slug((int)$subject->ID) : '';
    }

    public static function subject_teachers(int $subject_id,bool $public_only=true): array {
        if(!$subject_id){return array();}
        $raw=get_post_meta($subject_id,'_mhl_teachers',true);
        if(!is_array($raw)){return array();}
        $out=array();
        foreach($raw as $row){
            if(!is_array($row)){continue;}
            $name=sanitize_text_field((string)($row['name']??''));
            if($name===''){continue;}
            $show=!empty($row['show_public']);
            if($public_only && !$show){continue;}
            $item=array(
                'name'=>$name,
                'role'=>sanitize_text_field((string)($row['role']??'')),
                'profile_url'=>esc_url_raw((string)($row['profile_url']??'')),
                'show_public'=>$show
            );
            if(!$public_only){$item['email']=sanitize_email((string)($row['email']??''));}
            $out[]=$item;
        }
        return $out;
    }
    public static function lecture_teachers(int $lecture_id,bool $public_only=true): array {
        return self::subject_teachers(self::get_lecture_subject_id($lecture_id),$public_only);
    }

    public static function subject_template(int $subject_id): array {
        $defaults=array('template'=>'standard','brand_template'=>'miloslavhub','short_title'=>'','code'=>'','period'=>'','competition_title'=>'','extra_info'=>'','custom_brand_name'=>'','custom_brand_subtitle'=>'','custom_brand_url'=>'','custom_logo_url'=>'','custom_primary_color'=>'#172033','custom_accent_color'=>'#1f5fae');
        if(!$subject_id){return self::resolve_brand_template($defaults);}
        $template=sanitize_key((string)get_post_meta($subject_id,'_mhl_subject_template',true));
        if(!in_array($template,array('standard','competition','minimal'),true)){$template='standard';}
        $brand=sanitize_key((string)get_post_meta($subject_id,'_mhl_brand_template',true));
        if(!in_array($brand,array('miloslavhub','fes_upce','neutral','custom'),true)){$brand='miloslavhub';}
        $data=array(
            'template'=>$template,
            'brand_template'=>$brand,
            'short_title'=>sanitize_text_field((string)get_post_meta($subject_id,'_mhl_subject_short_title',true)),
            'code'=>sanitize_text_field((string)get_post_meta($subject_id,'_mhl_subject_code',true)),
            'period'=>sanitize_text_field((string)get_post_meta($subject_id,'_mhl_subject_period',true)),
            'competition_title'=>sanitize_text_field((string)get_post_meta($subject_id,'_mhl_competition_title',true)),
            'extra_info'=>sanitize_text_field((string)get_post_meta($subject_id,'_mhl_subject_extra_info',true)),
            'custom_brand_name'=>sanitize_text_field((string)get_post_meta($subject_id,'_mhl_custom_brand_name',true)),
            'custom_brand_subtitle'=>sanitize_text_field((string)get_post_meta($subject_id,'_mhl_custom_brand_subtitle',true)),
            'custom_brand_url'=>esc_url_raw((string)get_post_meta($subject_id,'_mhl_custom_brand_url',true)),
            'custom_logo_url'=>esc_url_raw((string)get_post_meta($subject_id,'_mhl_custom_logo_url',true)),
            'custom_primary_color'=>sanitize_hex_color((string)get_post_meta($subject_id,'_mhl_custom_primary_color',true))?:'#172033',
            'custom_accent_color'=>sanitize_hex_color((string)get_post_meta($subject_id,'_mhl_custom_accent_color',true))?:'#1f5fae',
        );
        return self::resolve_brand_template($data);
    }
    private static function resolve_brand_template(array $data): array {
        $brand=$data['brand_template']??'miloslavhub';
        if($brand==='fes_upce'){
            $data['brand_name']='Fakulta ekonomicko-správní';
            $data['brand_subtitle']='Univerzita Pardubice · živé hlasování';
            $data['brand_url']='https://fes.upce.cz';
            $data['brand_logo_url']='';
            $data['primary_color']='#172033';
            $data['accent_color']='#5b9f2f';
            $data['accent_soft']='#edf6e8';
            $data['show_miloslavhub']=false;
        } elseif($brand==='neutral'){
            $data['brand_name']='Živé hlasování';
            $data['brand_subtitle']='Interaktivní výuka';
            $data['brand_url']='';
            $data['brand_logo_url']='';
            $data['primary_color']='#1f2937';
            $data['accent_color']='#475569';
            $data['accent_soft']='#f1f5f9';
            $data['show_miloslavhub']=false;
        } elseif($brand==='custom'){
            $data['brand_name']=$data['custom_brand_name']?:'Živé hlasování';
            $data['brand_subtitle']=$data['custom_brand_subtitle']?:'Interaktivní výuka';
            $data['brand_url']=$data['custom_brand_url']?:'';
            $data['brand_logo_url']=$data['custom_logo_url']?:'';
            $data['primary_color']=$data['custom_primary_color']?:'#172033';
            $data['accent_color']=$data['custom_accent_color']?:'#1f5fae';
            $data['accent_soft']='#eef2f7';
            $data['show_miloslavhub']=false;
        } else {
            $data['brand_name']='Miloslav Hub';
            $data['brand_subtitle']='miloslavhub.cz · živé hlasování';
            $data['brand_url']='https://miloslavhub.cz';
            $data['brand_logo_url']='';
            $data['primary_color']='#172033';
            $data['accent_color']='#1f5fae';
            $data['accent_soft']='#eaf2fb';
            $data['show_miloslavhub']=true;
        }
        return $data;
    }
    public static function lecture_subject_template(int $lecture_id): array {
        return self::subject_template(self::get_lecture_subject_id($lecture_id));
    }



    /** Nastavení veřejné Síně slávy konkrétního předmětu. */
    public static function subject_hall_of_fame(int $subject_id): array {
        $enabled=(bool)get_post_meta($subject_id,'_mhl_hof_enabled',true);
        $limit=max(3,min(100,(int)(get_post_meta($subject_id,'_mhl_hof_limit',true)?:10)));
        $visibility=sanitize_key((string)get_post_meta($subject_id,'_mhl_hof_visibility',true));
        if(!in_array($visibility,array('public','participants'),true)){$visibility='public';}
        $days=max(1,min(3650,(int)(get_post_meta($subject_id,'_mhl_hof_period_days',true)?:365)));
        $title=sanitize_text_field((string)get_post_meta($subject_id,'_mhl_hof_title',true));
        $nonopt=sanitize_key((string)get_post_meta($subject_id,'_mhl_hof_nonopt_mode',true));
        if(!in_array($nonopt,array('hidden','anonymous'),true)){$nonopt='hidden';}
        $subject=get_post($subject_id);
        $settings=self::settings();
        $slug=$subject?self::permanent_slug($subject_id):'';
        return array(
            'enabled'=>$enabled,
            'limit'=>$limit,
            'visibility'=>$visibility,
            'period_days'=>$days,
            'title'=>$title?:($subject?($subject->post_title.' – Síň slávy'):'Síň slávy'),
            'nonopt_mode'=>$nonopt,
            'url'=>$slug?trailingslashit((string)$settings['frontend_url']).'hall-of-fame/'.$slug:'',
            'privacy_url'=>trailingslashit((string)$settings['frontend_url']).'privacy',
        );
    }

    public static function lecture_hall_of_fame(int $lecture_id): array {
        $subject_id=self::get_lecture_subject_id($lecture_id);
        return $subject_id?self::subject_hall_of_fame($subject_id):array('enabled'=>false,'limit'=>10,'period_days'=>365,'title'=>'Síň slávy','nonopt_mode'=>'hidden','url'=>'','privacy_url'=>trailingslashit((string)self::settings()['frontend_url']).'privacy');
    }

    /** Veřejný read-only projekční odkaz pro vyučujícího bez WordPress účtu. */
    public static function subject_projection(int $subject_id): array {
        $token=trim((string)get_post_meta($subject_id,'_mhl_projection_token',true));
        if($token==='' && $subject_id){
            $token=wp_generate_password(32,false,false);
            update_post_meta($subject_id,'_mhl_projection_token',$token);
        }
        $slug=$subject_id?self::permanent_slug($subject_id):'';
        $base=untrailingslashit((string)self::settings()['frontend_url']);
        return array('token'=>$token,'url'=>($slug&&$token)?$base.'/project/'.rawurlencode($slug).'/'.rawurlencode($token):'');
    }

    /**
     * Lehký úklid osobních/technických dat bez závislosti na cron službě.
     * Spustí se nejvýše jednou za 24 hodin při běžném požadavku WordPressu.
     */
    public static function maybe_purge_privacy_data(): void {
        if (!MHL_DB::configured() || !MHL_DB::schema_ready()) { return; }
        $last=(int)get_option('mhl_privacy_last_purge',0);
        if ($last && (time()-$last)<DAY_IN_SECONDS) { return; }
        // Zámek nastavujeme předem, aby souběžné požadavky nespustily více úklidů.
        update_option('mhl_privacy_last_purge',time(),false);
        try {
            $s=self::settings(); $db=MHL_DB::db();
            $votes=MHL_DB::table('votes'); $participants=MHL_DB::table('participants'); $joins=MHL_DB::table('session_joins');
            $live_cut=gmdate('Y-m-d H:i:s',time()-max(1,(int)$s['privacy_live_retention_days'])*DAY_IN_SECONDS);
            $test_cut=gmdate('Y-m-d H:i:s',time()-max(1,(int)$s['privacy_test_retention_days'])*DAY_IN_SECONDS);
            $join_cut=gmdate('Y-m-d H:i:s',time()-max(1,(int)$s['privacy_join_retention_days'])*DAY_IN_SECONDS);
            $now=self::now_mysql();
            $db->query($db->prepare("DELETE FROM {$votes} WHERE mode='live' AND created_at<%s",$live_cut));
            $db->query($db->prepare("DELETE FROM {$votes} WHERE mode='test' AND created_at<%s",$test_cut));
            $db->query($db->prepare("DELETE FROM {$joins} WHERE first_seen_at<%s",$join_cut));
            $db->query($db->prepare("DELETE FROM {$participants} WHERE expires_at<=%s",$now));
            $db->query($db->prepare("DELETE FROM {$participants} WHERE mode='test' AND last_seen_at<%s",$test_cut));
        } catch (Throwable $e) {
            // Úklid nesmí shodit veřejné hlasování. Stav DB zůstane viditelný v administraci.
        }
    }

    public static function question_in_lecture(int $lecture_id, int $question_id): bool { return in_array($question_id, self::get_lecture_question_ids($lecture_id), true); }
    public static function lecture_auto_qr(int $lecture_id): bool {
        // Retained for compatibility with stored metadata and old integrations.
        // Scanning a student QR never grants control over a live lesson.
        return false;
    }

    public static function get_vote_url(int $lecture_id, int $question_id, string $mode='live'): string {
        $s=self::settings(); $lecture=get_post($lecture_id); $question=get_post($question_id);
        if (!$lecture || !$question) { return ''; }
        $prefix = $mode==='test' ? 'test' : ($mode==='async' ? 'poll' : 'q');
        return untrailingslashit($s['frontend_url']).'/'.$prefix.'/'.rawurlencode(self::permanent_slug((int)$lecture->ID)).'/'.rawurlencode(self::permanent_slug((int)$question->ID));
    }
    public static function get_results_url(int $lecture_id, int $question_id, string $mode='live'): string {
        $s=self::settings(); $lecture=get_post($lecture_id); $question=get_post($question_id);
        if (!$lecture || !$question) { return ''; }
        $prefix = $mode==='test' ? 'test-results' : ($mode==='async' ? 'poll-results' : 'r');
        return untrailingslashit($s['frontend_url']).'/'.$prefix.'/'.rawurlencode(self::permanent_slug((int)$lecture->ID)).'/'.rawurlencode(self::permanent_slug((int)$question->ID));
    }

    public static function get_active_run(int $lecture_id, string $mode='live'): ?object {
        if (!MHL_DB::schema_ready()) { return null; }
        self::maybe_close_expired_runs(); $db=MHL_DB::db(); $runs=MHL_DB::table('runs'); $now=self::now_mysql();
        $row=$db->get_row($db->prepare("SELECT * FROM {$runs} WHERE lecture_id=%d AND mode=%s AND status='active' AND (expires_at IS NULL OR expires_at>%s) ORDER BY id DESC LIMIT 1",$lecture_id,$mode,$now));
        return $row?:null;
    }

    public static function get_active_run_for_subject(int $subject_id, string $mode='live'): ?object {
        if (!MHL_DB::schema_ready() || !$subject_id) { return null; }
        self::maybe_close_expired_runs(); $db=MHL_DB::db(); $runs=MHL_DB::table('runs'); $now=self::now_mysql();
        $row=$db->get_row($db->prepare("SELECT * FROM {$runs} WHERE subject_id=%d AND mode=%s AND status='active' AND (expires_at IS NULL OR expires_at>%s) ORDER BY id DESC LIMIT 1",$subject_id,$mode,$now));
        return $row?:null;
    }

    public static function close_run(int $run_id): void {
        if (!MHL_DB::schema_ready() || !$run_id) { return; }
        $db=MHL_DB::db(); $runs=MHL_DB::table('runs'); $sessions=MHL_DB::table('sessions'); $now=self::now_mysql();
        // Aktivní otázka se řádně uzavře. Otázky, které během přednášky nikdy nebyly aktivovány,
        // se označí jako přeskočené, aby neovlivňovaly body ani statistiky účasti.
        $db->query($db->prepare("UPDATE {$sessions} SET status='closed',closed_at=%s WHERE run_id=%d AND status IN ('joining','open')",$now,$run_id));
        $db->query($db->prepare("UPDATE {$sessions} SET status='skipped',closed_at=%s WHERE run_id=%d AND status='waiting'",$now,$run_id));
        $db->update($runs,array('status'=>'closed','closed_at'=>$now),array('id'=>$run_id),array('%s','%s'),array('%d'));
    }

    public static function create_run(int $lecture_id,string $mode='live',int $created_by=0,bool $close_existing=false): ?object {
        if(!MHL_DB::schema_ready()){return null;}
        $questions=self::get_lecture_question_ids($lecture_id); $subject_id=self::get_lecture_subject_id($lecture_id);
        if(!$lecture_id||!$subject_id||!$questions){return null;}
        $db=MHL_DB::db();$runs=MHL_DB::table('runs');$sessions=MHL_DB::table('sessions');$now=self::now_mysql();$settings=self::settings();
        if($close_existing){
            $existing_ids=$db->get_col($db->prepare("SELECT id FROM {$runs} WHERE lecture_id=%d AND mode=%s AND status='active'",$lecture_id,$mode));
            foreach($existing_ids?:array() as $existing_id){ self::close_run((int)$existing_id); }
        }
        $ok=$db->insert($runs,array(
            'lecture_id'=>$lecture_id,'subject_id'=>$subject_id,'title'=>get_the_title($lecture_id),'mode'=>$mode,'status'=>'active',
            'started_at'=>$now,'expires_at'=>$mode==='async'?null:self::mysql_after_minutes((int)(get_post_meta($lecture_id,'_mhl_demo',true)?(get_post_meta($lecture_id,'_mhl_demo_minutes',true)?:15):$settings['run_minutes'])),'created_by'=>$created_by?:null
        ),array('%d','%d','%s','%s','%s','%s','%s','%d'));
        if(!$ok){return null;}
        $run_id=(int)$db->insert_id;
        foreach($questions as $qid){
            $db->insert($sessions,array('run_id'=>$run_id,'question_id'=>$qid,'mode'=>$mode,'status'=>'waiting','created_at'=>$now),array('%d','%d','%s','%s','%s'));
        }
        return $db->get_row($db->prepare("SELECT * FROM {$runs} WHERE id=%d",$run_id))?:null;
    }

    public static function get_active_run_for_question(int $lecture_id, int $question_id, string $mode='live'): ?object {
        if (!self::question_in_lecture($lecture_id,$question_id)) { return null; }
        $run=self::get_active_run($lecture_id,$mode); if(!$run){return null;}
        $db=MHL_DB::db(); $sessions=MHL_DB::table('sessions');
        $exists=$db->get_var($db->prepare("SELECT id FROM {$sessions} WHERE run_id=%d AND question_id=%d AND mode=%s ORDER BY id DESC LIMIT 1",(int)$run->id,$question_id,$mode));
        return $exists?$run:null;
    }

    public static function get_current_session(int $lecture_id,int $question_id,?int $run_id=null,string $mode='live',bool $auto_close=true): ?object {
        if(!MHL_DB::schema_ready()){return null;} $db=MHL_DB::db(); $sessions=MHL_DB::table('sessions');
        if(!$run_id){$run=self::get_active_run_for_question($lecture_id,$question_id,$mode); if(!$run){return null;} $run_id=(int)$run->id;}
        $session=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE run_id=%d AND question_id=%d AND mode=%s ORDER BY id DESC LIMIT 1",$run_id,$question_id,$mode));
        if(!$session){return null;}
        if($auto_close){$session=self::maybe_start_voting((int)$session->id)?:$session;}
        if($auto_close && $session->status==='open' && !empty($session->reset_at) && strtotime($session->reset_at.' UTC')<=time()){
            $db->update($sessions,array('status'=>'closed','closed_at'=>self::now_mysql()),array('id'=>(int)$session->id),array('%s','%s'),array('%d'));
            $session=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d",(int)$session->id));
        }
        return $session?:null;
    }

    public static function current_open_session(int $run_id,string $mode='live'): ?object {
        if(!MHL_DB::schema_ready()){return null;}
        $db=MHL_DB::db();$sessions=MHL_DB::table('sessions');$now=self::now_mysql();
        $expired=$db->get_results($db->prepare("SELECT id FROM {$sessions} WHERE run_id=%d AND mode=%s AND status='open' AND reset_at IS NOT NULL AND reset_at<=%s",$run_id,$mode,$now));
        foreach($expired?:array() as $row){$db->update($sessions,array('status'=>'closed','closed_at'=>$now),array('id'=>(int)$row->id),array('%s','%s'),array('%d'));}
        $row=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE run_id=%d AND mode=%s AND status IN ('joining','open') ORDER BY COALESCE(joining_started_at,opened_at) DESC,id DESC LIMIT 1",$run_id,$mode));
        if($row && $row->status==='joining'){$row=self::maybe_start_voting((int)$row->id)?:$row;}
        return $row?:null;
    }

    public static function activate_question(int $lecture_id,int $question_id,string $mode='live',bool $allow_start=true): array {
        if ($mode !== 'async' && !current_user_can('manage_options')) {
            return array(null, null, false, 'forbidden');
        }
        if(!MHL_DB::schema_ready() || !self::question_in_lecture($lecture_id,$question_id)){return array(null,null,false,'invalid');}
        if($mode==='async' && !self::question_async_enabled($question_id)){return array(null,null,false,'async_disabled');}
        $run=self::get_active_run($lecture_id,$mode);
        if(!$run){
            if(!$allow_start){return array(null,null,false,'inactive');}
            $subject_id=self::get_lecture_subject_id($lecture_id);
            $subject_run=$mode==='async'?null:self::get_active_run_for_subject($subject_id,$mode);
            if($subject_run && (int)$subject_run->lecture_id!==$lecture_id){
                self::close_run((int)$subject_run->id);
            }
            $run=self::create_run($lecture_id,$mode,0,false);
            if(!$run){return array(null,null,false,'run_failed');}
        }
        $session=self::get_current_session($lecture_id,$question_id,(int)$run->id,$mode,true);
        if(!$session){return array($run,null,false,'session_missing');}
        if(in_array($session->status,array('closed','skipped'),true)){return array($run,$session,false,$session->status==='skipped'?'skipped':'completed');}
        if($session->status==='open'){return array($run,$session,false,'already_open');}

        $db=MHL_DB::db();$sessions=MHL_DB::table('sessions');$now=self::now_mysql();
        if($mode==='async'){
            $end=self::question_async_end_mysql($question_id);
            $db->update($sessions,array('status'=>'open','joining_started_at'=>null,'last_join_at'=>null,'opened_at'=>$now,'closed_at'=>null,'reset_at'=>$end),array('id'=>(int)$session->id),array('%s','%s','%s','%s','%s','%s'),array('%d'));
            $session=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d",(int)$session->id));
            return array($run,$session,true,'async_open');
        }
        $db->query($db->prepare("UPDATE {$sessions} SET status='closed',closed_at=%s WHERE run_id=%d AND mode=%s AND status IN ('joining','open') AND id<>%d",$now,(int)$run->id,$mode,(int)$session->id));
        $timeout=self::question_time_limit($question_id);
        $db->update($sessions,array('status'=>'open','joining_started_at'=>null,'last_join_at'=>null,'opened_at'=>$now,'closed_at'=>null,'reset_at'=>$timeout>0?self::mysql_after_seconds($timeout):null),array('id'=>(int)$session->id),array('%s','%s','%s','%s','%s','%s'),array('%d'));
        $session=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d",(int)$session->id));
        return array($run,$session,true,'teacher_open');
    }


    public static function register_session_join(int $session_id,string $participant_id): ?object {
        if(!MHL_DB::schema_ready() || !$session_id || strlen($participant_id)<16 || strlen($participant_id)>128){return null;}
        $db=MHL_DB::db();$sessions=MHL_DB::table('sessions');$joins=MHL_DB::table('session_joins');$now=self::now_mysql();$pkey=self::participant_key($participant_id);
        $session=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d",$session_id));
        if(!$session || !in_array($session->status,array('joining','open'),true)){return $session?:null;}
        if($session->status==='joining'){
            $exists=$db->get_var($db->prepare("SELECT id FROM {$joins} WHERE session_id=%d AND participant_key=%s LIMIT 1",$session_id,$pkey));
            if(!$exists){
                $db->insert($joins,array('session_id'=>$session_id,'participant_key'=>$pkey,'first_seen_at'=>$now),array('%d','%s','%s'));
                if($db->insert_id){$db->update($sessions,array('last_join_at'=>$now),array('id'=>$session_id),array('%s'),array('%d'));}
            }
        }
        return self::maybe_start_voting($session_id);
    }

    public static function maybe_start_voting(int $session_id): ?object {
        if(!MHL_DB::schema_ready() || !$session_id){return null;}
        $db=MHL_DB::db();$sessions=MHL_DB::table('sessions');$s=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d",$session_id));
        if(!$s || $s->status!=='joining'){return $s?:null;}
        // Legacy joining sessions remain waiting for an explicit teacher action.
        if ($s->mode !== 'async') { return $s; }
        $cfg=self::settings();$min=max(0,(int)$cfg['join_min_wait_seconds']);$quiet=max(1,(int)$cfg['join_quiet_seconds']);$max=max($min+1,(int)$cfg['join_max_wait_seconds']);
        $start=$s->joining_started_at?strtotime($s->joining_started_at.' UTC'):time();$last=$s->last_join_at?strtotime($s->last_join_at.' UTC'):$start;$now=time();
        $ready=(($now-$start)>=$min && ($now-$last)>=$quiet) || (($now-$start)>=$max);
        if(!$ready){return $s;}
        $now_mysql=self::now_mysql();$timeout=self::question_time_limit((int)$s->question_id);$reset=$timeout>0?self::mysql_after_seconds($timeout):null;
        $db->update($sessions,array('status'=>'open','opened_at'=>$now_mysql,'reset_at'=>$reset),array('id'=>$session_id),array('%s','%s','%s'),array('%d'));
        return $db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d",$session_id))?:null;
    }

    public static function maybe_close_expired_runs(): void {
        if(!MHL_DB::schema_ready()){return;} $db=MHL_DB::db(); $runs=MHL_DB::table('runs'); $sessions=MHL_DB::table('sessions'); $now=self::now_mysql();
        $expired=$db->get_col($db->prepare("SELECT id FROM {$runs} WHERE status='active' AND expires_at IS NOT NULL AND expires_at<=%s",$now));
        if($expired){
            foreach($expired as $rid){ self::close_run((int)$rid); }
        }
    }

    public static function lecture_gamification(int $lecture_id): array {
        if(!$lecture_id){return array('enabled'=>false,'scope'=>'none','nickname_required'=>false,'join_nickname_required'=>false);}
        $enabled=(bool)get_post_meta($lecture_id,'_mhl_gamification',true);
        $scope=get_post_meta($lecture_id,'_mhl_score_scope',true)?:'subject';
        if(!in_array($scope,array('subject','lecture','none'),true)){$scope='subject';}
        return array(
            'enabled'=>$enabled,
            'scope'=>$enabled?$scope:'none',
            'nickname_required'=>$enabled,
            'join_nickname_required'=>$enabled,
        );
    }

    public static function run_gamification(?object $run): array {
        if(!$run || empty($run->lecture_id)){return array('enabled'=>false,'scope'=>'none','nickname_required'=>false,'join_nickname_required'=>false);}
        if(($run->mode??'')==='async'){return array('enabled'=>false,'scope'=>'none','nickname_required'=>false,'join_nickname_required'=>false);}
        return self::lecture_gamification((int)$run->lecture_id);
    }

    public static function lecture_ctas(int $lecture_id): array {
        $s=self::settings();
        $materials=esc_url_raw((string)get_post_meta($lecture_id,'_mhl_materials_url',true));
        $assistant=esc_url_raw((string)get_post_meta($lecture_id,'_mhl_assistant_url',true));
        $global_assistant=esc_url_raw((string)$s['assistant_url']);
        $main_site=esc_url_raw((string)$s['main_site_url']);
        // Starší verze ukládaly hlavní web jako výchozí „AI asistent“. Pokud jsou URL shodné,
        // nepovažujeme to za skutečně nakonfigurovaného asistenta a tlačítko veřejně nezobrazíme.
        if($global_assistant && untrailingslashit($global_assistant)===untrailingslashit($main_site)){$global_assistant='';}
        return array(
            'materials_url'=>$materials,
            'assistant_url'=>$assistant ?: $global_assistant,
            'main_site_url'=>$main_site,
        );
    }

    public static function rag_policy(int $question_id): string {
        $p=(string)get_post_meta($question_id,'_mhl_rag_policy',true);
        return in_array($p,array('exclude','private','public_after_lecture'),true)?$p:'exclude';
    }
}
