<?php
if (!defined('ABSPATH')) { exit; }

/** Hlasuj object permissions. Workspace roles are local application grants, not AUTH claims. */
final class MHL_Access {
    const TYPES = array('mhl_subject','mhl_lecture','mhl_question');
    const ROLES = array('owner','administrator','teacher','collaborating_teacher','viewer');

    public static function init(): void {
        add_action('init', array(__CLASS__, 'register'), 5);
        add_filter('map_meta_cap', array(__CLASS__, 'map_cap'), 20, 4);
        add_filter('posts_results', array(__CLASS__, 'filter_posts'), 20, 2);
        add_filter('wp_count_posts', array(__CLASS__, 'counts'), 20, 3);
        add_filter('pre_get_posts', array(__CLASS__, 'scope_query'));
        add_filter('wp_insert_post_data', array(__CLASS__, 'protect_author'), 30, 2);
        add_filter('add_post_metadata', array(__CLASS__, 'protect_meta'), 5, 5);
        add_filter('update_post_metadata', array(__CLASS__, 'protect_meta'), 5, 5);
        add_filter('delete_post_metadata', array(__CLASS__, 'protect_meta'), 5, 5);
        add_action('save_post', array(__CLASS__, 'assign_workspace'), 5, 3);
        add_action('transition_post_status',array(__CLASS__,'invalidate_relations'));
        add_action('deleted_post',array(__CLASS__,'invalidate_relations'));
        // Register after the parent menu so WordPress derives the same page hook on dispatch.
        add_action('admin_menu', array(__CLASS__, 'menu'), 20);
        add_action('admin_post_mhl_workspace', array(__CLASS__, 'handle'));
    }

    public static function register(): void {
        register_post_type('mhl_workspace', array('public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>array('title'),
            'map_meta_cap'=>false,'capabilities'=>array_fill_keys(array('edit_post','read_post','delete_post','edit_posts','edit_others_posts','publish_posts','read_private_posts','delete_posts','delete_private_posts','delete_published_posts','delete_others_posts','edit_private_posts','edit_published_posts','create_posts'),'manage_options')));
        // Versioned role setup preserves unrelated roles and never grants WordPress administration.
        if (get_option('mhl_access_roles_version') !== '1') {
            add_role('mhl_teacher', __('Učitel Hlasuj!', 'miloslavhub-live'), array('read'=>true,'mhl_access'=>true));
            $role=get_role('mhl_teacher'); if ($role) { $role->add_cap('mhl_access'); }
            $admin=get_role('administrator'); if ($admin) { $admin->add_cap('mhl_access'); }
            update_option('mhl_access_roles_version','1',false);
        }
    }

    public static function operator(int $uid=0): bool {
        $uid=$uid?:get_current_user_id();
        return $uid>0 && user_can($uid,'manage_options');
    }

    public static function member_role(int $workspace,int $uid=0): string {
        $uid=$uid?:get_current_user_id();
        $post=get_post($workspace);
        if (!$uid || !$post || $post->post_type!=='mhl_workspace' || $post->post_status!=='private') { return ''; }
        $members=get_post_meta($workspace,'_mhl_members',true);
        $role=is_array($members)?(string)($members[$uid]??''):'';
        return in_array($role,self::ROLES,true)?$role:'';
    }

    public static function workspace(int $post_id): int {
        $post=get_post($post_id);
        if (!$post || !in_array($post->post_type,self::TYPES,true)) { return -1; }
        return (int)get_post_meta($post_id,'_mhl_workspace_id',true);
    }

    public static function active_workspace(int $uid=0): int {
        $uid=$uid?:get_current_user_id();
        $id=(int)get_user_meta($uid,'mhl_active_workspace',true);
        return $id && self::member_role($id,$uid)!==''?$id:0;
    }

    /** Organisation-wide distinct teaching accounts. Students/viewers never consume a seat. */
    public static function teaching_accounts(int $workspace): array {
        if ($workspace<=0 || !get_post($workspace) || get_post_type($workspace)!=='mhl_workspace') { return array(); }
        $ids=array();
        foreach ((array)get_post_meta($workspace,'_mhl_members',true) as $uid=>$role) {
            if (in_array($role,array('owner','administrator','teacher','collaborating_teacher'),true)
                && get_user_by('id',(int)$uid) && user_can((int)$uid,'mhl_access')) { $ids[]=(int)$uid; }
        }
        $ids=array_values(array_unique($ids));sort($ids,SORT_NUMERIC);return $ids;
    }

    public static function role_label(string $role): string {
        $labels=array('owner'=>__('Vlastník','miloslavhub-live'),'administrator'=>__('Správce organizace','miloslavhub-live'),
            'teacher'=>__('Učitel','miloslavhub-live'),'collaborating_teacher'=>__('Spolupracující učitel','miloslavhub-live'),
            'viewer'=>__('Pouze čtení','miloslavhub-live'));
        return $labels[$role]??__('Odebrat členství','miloslavhub-live');
    }

    private static function direct(int $post_id,int $uid): string {
        $post=get_post($post_id);
        if (!$post || !in_array($post->post_type,self::TYPES,true) || !$uid) { return ''; }
        if (self::operator($uid)) { return 'owner'; }
        if (!user_can($uid,'mhl_access')) { return ''; }
        $workspace=self::workspace($post_id);
        if ($workspace) {
            $member=self::member_role($workspace,$uid);
            if ($member==='') { return ''; }
            if (in_array($member,array('owner','administrator'),true)) { return 'owner'; }
        } else { $member='teacher'; }
        if ((int)$post->post_author===$uid && $member==='teacher') { return 'owner'; }
        $grants=get_post_meta($post_id,'_mhl_access_grants',true);
        $grant=is_array($grants)?(string)($grants[$uid]??''):'';
        if (!in_array($grant,array('collaborating_teacher','viewer'),true)) { return ''; }
        // A read-only workspace member cannot acquire editing through an old object grant.
        return $member==='viewer'?'viewer':$grant;
    }

    private static ?array $question_parents=null;
    public static function invalidate_relations(): void { self::$question_parents=null; }
    private static function question_parents(int $question): array {
        if (self::$question_parents===null) {
            global $wpdb;self::$question_parents=array();
            $ids=array_map('intval',$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='mhl_lecture' AND post_status NOT IN ('trash','auto-draft')")?:array());
            update_meta_cache('post',$ids);
            foreach ($ids as $id) {
                foreach ((array)get_post_meta($id,'_mhl_question_ids',true) as $qid) { self::$question_parents[(int)$qid][]=$id; }
            }
        }
        return self::$question_parents[$question]??array();
    }

    public static function role(int $post_id,int $uid=0): string {
        $uid=$uid?:get_current_user_id();
        $own=self::direct($post_id,$uid);
        if ($own==='owner' || $own==='collaborating_teacher') { return $own; }
        $post=get_post($post_id); if (!$post) { return ''; }
        $roles=array($own);
        if ($post->post_type==='mhl_lecture') {
            $parent=(int)get_post_meta($post_id,'_mhl_subject_id',true);
            if ($parent && self::workspace($parent)===self::workspace($post_id)) { $roles[]=self::direct($parent,$uid); }
        } elseif ($post->post_type==='mhl_question') {
            // A shared lecture grants access only to its explicitly attached questions in the same workspace.
            foreach (self::question_parents($post_id) as $lid) {
                if (self::workspace((int)$lid)!==self::workspace($post_id)) { continue; }
                $roles[]=self::role((int)$lid,$uid);
            }
        }
        foreach (array('owner','collaborating_teacher','viewer') as $role) {
            if (in_array($role,$roles,true)) { return $role==='owner'?'collaborating_teacher':$role; }
        }
        return '';
    }

    public static function can(int $post_id,string $operation='read',int $uid=0): bool {
        $role=self::role($post_id,$uid);
        if ($operation==='read' || $operation==='results' || $operation==='export') { return $role!==''; }
        if (in_array($operation,array('edit','control'),true)) { return in_array($role,array('owner','collaborating_teacher'),true); }
        if (in_array($operation,array('delete','share','transfer'),true)) { return self::direct($post_id,$uid?:get_current_user_id())==='owner'; }
        return false;
    }

    public static function can_create(int $uid=0): bool {
        $uid=$uid?:get_current_user_id();
        if (self::operator($uid)) { return true; }
        if (!$uid || !user_can($uid,'mhl_access')) { return false; }
        $workspace=self::active_workspace($uid);
        return !$workspace || in_array(self::member_role($workspace,$uid),array('owner','administrator','teacher'),true);
    }

    public static function map_cap(array $caps,string $cap,int $uid,array $args): array {
        if ($cap==='mhl_access' && self::operator($uid)) { return array('manage_options'); }
        if ($cap==='mhl_create_content') { return self::can_create($uid)?array('read'):array('do_not_allow'); }
        $ops=array('edit_post'=>'edit','read_post'=>'read','delete_post'=>'delete','edit_mhl_content'=>'edit','read_mhl_content'=>'read','delete_mhl_content'=>'delete');
        if (!isset($ops[$cap]) || empty($args[0])) { return $caps; }
        $post=get_post((int)$args[0]);
        if (!$post || !in_array($post->post_type,self::TYPES,true)) { return $caps; }
        return self::can((int)$post->ID,$ops[$cap],$uid)?array('read'):array('do_not_allow');
    }

    /** Only management queries are scoped; public voting keeps its existing QR disclosure contract. */
    private static function management(): bool {
        return is_admin();
    }

    public static function visible_ids(string $type='',int $uid=0): array {
        global $wpdb; $uid=$uid?:get_current_user_id();
        $types=$type?array($type):self::TYPES;
        if (array_diff($types,self::TYPES)) { return array(); }
        $quoted=implode(',',array_map(static fn($v)=>"'".esc_sql($v)."'",$types));
        $ids=$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$quoted})");
        $workspace=self::active_workspace($uid);
        return array_values(array_filter(array_map('intval',$ids?:array()),static fn($id)=>(self::operator($uid)||self::workspace($id)===$workspace)&&self::can($id,'read',$uid)));
    }

    public static function scope_query(WP_Query $query): void {
        if (!self::management() || self::operator() || $query->get('_mhl_public_lookup')===true) { return; }
        $types=(array)$query->get('post_type');
        if (!array_intersect($types,self::TYPES)) { return; }
        $allowed=self::visible_ids(); $requested=$query->get('post__in');
        if ($requested) { $allowed=array_values(array_intersect($allowed,array_map('intval',$requested))); }
        $query->set('post__in',$allowed?:array(0));
        // get_posts normally suppresses filters; pre_get_posts still scopes its SQL before LIMIT/count.
    }

    public static function filter_posts(array $posts,WP_Query $query): array {
        if (!self::management() || self::operator() || $query->get('_mhl_public_lookup')===true) { return $posts; }
        return array_values(array_filter($posts,static fn($p)=>!in_array($p->post_type,self::TYPES,true)||self::can((int)$p->ID)));
    }

    public static function counts(object $counts,string $type,string $perm): object {
        if (!self::management() || self::operator() || !in_array($type,self::TYPES,true)) { return $counts; }
        $scoped=clone $counts;
        foreach (get_object_vars($scoped) as $status=>$count) { $scoped->$status=0; }
        foreach (self::visible_ids($type) as $id) { $status=get_post_status($id);$scoped->$status=1+(int)($scoped->$status??0); }
        return $scoped;
    }

    public static function protect_author(array $data,array $postarr): array {
        if (self::$writing || !in_array($data['post_type']??'',self::TYPES,true) || self::operator()) { return $data; }
        $post=!empty($postarr['ID'])?get_post((int)$postarr['ID']):null;
        $data['post_author']=$post?(int)$post->post_author:get_current_user_id();
        return $data;
    }

    private static bool $writing=false;
    public static function protect_meta($check,int $post_id,string $key,$value,$extra) {
        if ($key==='_mhl_question_ids') { self::$question_parents=null; }
        if (self::$writing || !in_array(get_post_type($post_id),self::TYPES,true)) { return $check; }
        if (in_array($key,array('_mhl_workspace_id','_mhl_access_grants'),true)) { return false; }
        // A forged relation must never attach another tenant's private content to public QR output.
        if ($key==='_mhl_subject_id' || $key==='_mhl_question_ids') {
            $ids=$key==='_mhl_subject_id'?array((int)$value):array_map('intval',(array)$value);
            foreach ($ids as $id) {
                if (!$id) { continue; }
                if (self::workspace($id)!==self::workspace($post_id) || !self::can($id,'read')) { return false; }
            }
        }
        return $check;
    }

    public static function assign_workspace(int $id,WP_Post $post,bool $update): void {
        if ($post->post_type==='mhl_lecture') { self::$question_parents=null; }
        if ($update || !in_array($post->post_type,self::TYPES,true) || wp_is_post_revision($id)) { return; }
        self::$writing=true;
        try { update_post_meta($id,'_mhl_workspace_id',self::active_workspace((int)$post->post_author)); }
        finally { self::$writing=false; }
    }

    public static function capabilities(): array {
        return array('edit_post'=>'edit_mhl_content','read_post'=>'read_mhl_content','delete_post'=>'delete_mhl_content',
            'edit_posts'=>'mhl_access','edit_others_posts'=>'mhl_access','publish_posts'=>'mhl_create_content',
            'read_private_posts'=>'mhl_access','delete_posts'=>'mhl_access','delete_private_posts'=>'mhl_access',
            'delete_published_posts'=>'mhl_access','delete_others_posts'=>'mhl_access','edit_private_posts'=>'mhl_access',
            'edit_published_posts'=>'mhl_access','create_posts'=>'mhl_create_content','read'=>'read');
    }

    public static function can_run(int $run_id,string $operation='control'): bool {
        if (self::operator()) { return true; }
        if (!MHL_DB::schema_ready()) { return false; }
        $db=MHL_DB::db();$runs=MHL_DB::table('runs');
        $lecture=(int)$db->get_var($db->prepare("SELECT lecture_id FROM {$runs} WHERE id=%d",$run_id));
        return $lecture>0 && self::can($lecture,$operation);
    }

    public static function require_run(int $run_id,string $operation='control'): void {
        if (!self::can_run($run_id,$operation)) { wp_die(esc_html__('Nemáte oprávnění.', 'miloslavhub-live'),'',array('response'=>403)); }
    }

    /** Safe SQL fragment for external voting DB queries; IDs come only from authorized WordPress objects. */
    public static function run_sql(string $alias='',string $operation='results'): string {
        if (self::operator()) { return '1=1'; }
        if (!in_array($alias,array('','r'),true)) { return '1=0'; }
        if (!in_array($operation,array('results','control','export'),true)) { return '1=0'; }
        $ids=array_values(array_filter(self::visible_ids('mhl_lecture'),static fn($id)=>self::can($id,$operation)));
        return ($alias?$alias.'.':'').'lecture_id IN ('.implode(',',$ids?:array(0)).')';
    }

    private static function locked(int $id,callable $action) {
        global $wpdb;
        foreach (array($wpdb->posts,$wpdb->postmeta) as $table) {
            $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
            if (strtolower((string)$engine)!=='innodb') { return new WP_Error('mhl_access_storage',__('Úložiště oprávnění vyžaduje transakční tabulky.', 'miloslavhub-live')); }
        }
        if ($wpdb->query('START TRANSACTION')===false) { return new WP_Error('mhl_access_storage',__('Úložiště oprávnění není dostupné.', 'miloslavhub-live')); }
        try {
            $found=$wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$id));
            if (!$found) { throw new RuntimeException('missing'); }
            wp_cache_delete($id,'post_meta');clean_post_cache($id);
            $result=$action();
            if (is_wp_error($result)) { $wpdb->query('ROLLBACK');wp_cache_delete($id,'post_meta');return $result; }
            if ($wpdb->query('COMMIT')===false) { throw new RuntimeException('commit'); }
            return $result;
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');wp_cache_delete($id,'post_meta');clean_post_cache($id);
            return new WP_Error('mhl_access_storage',__('Změnu oprávnění se nepodařilo uložit.', 'miloslavhub-live'));
        }
    }

    public static function create_workspace(string $title,int $owner,string $external_ref=''): int|WP_Error {
        if (!self::operator() || !get_user_by('id',$owner) || trim($title)==='') { return new WP_Error('mhl_forbidden',__('Nemáte oprávnění.', 'miloslavhub-live')); }
        $id=wp_insert_post(array('post_type'=>'mhl_workspace','post_status'=>'private','post_title'=>sanitize_text_field($title),'post_author'=>$owner),true);
        if (is_wp_error($id)) { return $id; }
        if (!update_post_meta($id,'_mhl_members',array($owner=>'owner'))) { wp_delete_post($id,true);return new WP_Error('mhl_access_storage',__('Organizaci se nepodařilo uložit.', 'miloslavhub-live')); }
        update_post_meta($id,'_mhl_external_organization_ref',sanitize_text_field($external_ref));
        $user=new WP_User($owner);$user->add_cap('mhl_access');
        return (int)$id;
    }

    public static function set_member(int $workspace,int $uid,string $role): bool|WP_Error {
        if (!in_array($role,array_merge(self::ROLES,array('')),true) || !get_user_by('id',$uid)) { return new WP_Error('mhl_invalid_member',__('Neplatný účet nebo role.', 'miloslavhub-live')); }
        return self::locked($workspace,static function() use($workspace,$uid,$role) {
            $actor=self::member_role($workspace);
            if (!self::operator() && !in_array($actor,array('owner','administrator'),true)) { return new WP_Error('mhl_forbidden',__('Nemáte oprávnění.', 'miloslavhub-live')); }
            $members=(array)get_post_meta($workspace,'_mhl_members',true);
            if (!self::operator() && $actor!=='owner' && ($role==='owner'||($members[$uid]??'')==='owner')) { return new WP_Error('mhl_owner_required',__('Tuto změnu může provést vlastník.', 'miloslavhub-live')); }
            $next=$members;
            if ($role==='') { unset($next[$uid]); } else { $next[$uid]=$role; }
            if (!in_array('owner',$next,true)) { return new WP_Error('mhl_last_owner',__('Organizace musí mít vlastníka.', 'miloslavhub-live')); }
            if ($next!==$members && update_post_meta($workspace,'_mhl_members',$next)===false) { throw new RuntimeException('write'); }
            if ($role!=='') { $user=new WP_User($uid);$user->add_cap('mhl_access'); }
            return true;
        });
    }

    public static function share(int $post_id,int $uid,string $role): bool|WP_Error {
        if (!in_array($role,array('collaborating_teacher','viewer',''),true)) { return new WP_Error('mhl_invalid_role',__('Neplatná role.', 'miloslavhub-live')); }
        return self::locked($post_id,static function() use($post_id,$uid,$role) {
            $workspace=self::workspace($post_id);
            if (!self::can($post_id,'share') || !$uid || !get_user_by('id',$uid) || !user_can($uid,'mhl_access')
                || ($workspace && self::member_role($workspace,$uid)==='')) { return new WP_Error('mhl_forbidden',__('Nemáte oprávnění sdílet tento obsah.', 'miloslavhub-live')); }
            $grants=(array)get_post_meta($post_id,'_mhl_access_grants',true);$next=$grants;
            if ($role==='') { unset($next[$uid]); } else { $next[$uid]=$role; }
            self::$writing=true;
            try { if ($next!==$grants && update_post_meta($post_id,'_mhl_access_grants',$next)===false) { throw new RuntimeException('write'); } }
            finally { self::$writing=false; }
            return true;
        });
    }

    public static function transfer(int $post_id,int $uid): bool|WP_Error {
        return self::locked($post_id,static function() use($post_id,$uid) {
            $workspace=self::workspace($post_id);
            if (!self::can($post_id,'transfer') || !get_user_by('id',$uid) || !user_can($uid,'mhl_access') || ($workspace && !in_array(self::member_role($workspace,$uid),array('owner','administrator','teacher'),true))) { return new WP_Error('mhl_forbidden',__('Nemáte oprávnění převést obsah.', 'miloslavhub-live')); }
            self::$writing=true;
            try {
                // Direct SQL avoids changing the permanent slug or triggering content-save callbacks.
                global $wpdb;
                if ($wpdb->update($wpdb->posts,array('post_author'=>$uid),array('ID'=>$post_id),array('%d'),array('%d'))===false) { throw new RuntimeException('write'); }
                clean_post_cache($post_id);
            } finally { self::$writing=false; }
            return true;
        });
    }

    public static function menu(): void {
        add_submenu_page('mhl-live',__('Organizace a sdílení', 'miloslavhub-live'),__('Organizace a sdílení', 'miloslavhub-live'),'mhl_access','mhl-workspaces',array(__CLASS__,'page'));
    }

    public static function handle(): void {
        if (!current_user_can('mhl_access')) { wp_die(esc_html__('Nemáte oprávnění.', 'miloslavhub-live'),'',array('response'=>403)); }
        check_admin_referer('mhl_workspace');
        $action=sanitize_key((string)($_POST['operation']??''));$workspace=absint($_POST['workspace']??0);$uid=absint($_POST['user']??0);
        if (isset($_POST['user_login']) && is_string($_POST['user_login'])) {
            $user=get_user_by('login',sanitize_user(wp_unslash($_POST['user_login']),true));$uid=$user?(int)$user->ID:0;
        }
        $result=false;
        if ($action==='create') { $result=self::create_workspace(sanitize_text_field(wp_unslash($_POST['title']??'')),$uid,sanitize_text_field(wp_unslash($_POST['external_ref']??''))); }
        elseif ($action==='member') { $result=self::set_member($workspace,$uid,sanitize_key((string)($_POST['role']??''))); }
        elseif ($action==='switch' && (!$workspace || self::member_role($workspace)!=='')) { $result=update_user_meta(get_current_user_id(),'mhl_active_workspace',$workspace);$result=true; }
        elseif ($action==='share') { $result=self::share(absint($_POST['post']??0),$uid,sanitize_key((string)($_POST['role']??''))); }
        elseif ($action==='transfer') { $result=self::transfer(absint($_POST['post']??0),$uid); }
        if (!$result || is_wp_error($result)) { wp_die(esc_html(is_wp_error($result)?$result->get_error_message():__('Změna není povolena.', 'miloslavhub-live')),'',array('response'=>403)); }
        wp_safe_redirect(admin_url('admin.php?page=mhl-workspaces&saved=1'));exit;
    }

    private static function form(string $operation): void {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('mhl_workspace');
        echo '<input type="hidden" name="action" value="mhl_workspace"><input type="hidden" name="operation" value="'.esc_attr($operation).'">';
    }

    public static function page(): void {
        if (!current_user_can('mhl_access')) { return; }
        echo '<div class="wrap"><h1>'.esc_html__('Organizace a sdílení', 'miloslavhub-live').'</h1><p>'.esc_html__('Každý učitel používá vlastní účet. Sdílení obsahu a role organizace se nastavují samostatně.', 'miloslavhub-live').'</p>';
        $workspaces=get_posts(array('post_type'=>'mhl_workspace','post_status'=>'private','numberposts'=>-1));
        self::form('switch');echo '<label>'.esc_html__('Pracovat v', 'miloslavhub-live').' <select name="workspace"><option value="0">'.esc_html__('Moje osobní výuka', 'miloslavhub-live').'</option>';
        foreach ($workspaces as $w) { if (self::member_role((int)$w->ID)!=='') { echo '<option value="'.esc_attr($w->ID).'" '.selected(self::active_workspace(),(int)$w->ID,false).'>'.esc_html($w->post_title).'</option>'; } }
        echo '</select></label> ';submit_button(__('Přepnout', 'miloslavhub-live'),'secondary','submit',false);echo '</form>';
        foreach ($workspaces as $w) {
            $role=self::member_role((int)$w->ID);if (!self::operator() && !in_array($role,array('owner','administrator'),true)) { continue; }
            echo '<h2>'.esc_html($w->post_title).'</h2><ul>';
            foreach ((array)get_post_meta($w->ID,'_mhl_members',true) as $member=>$r) { $u=get_user_by('id',(int)$member);echo '<li>'.esc_html(($u?$u->display_name:'#'.$member).' — '.self::role_label($r)).'</li>'; }
            echo '</ul>';self::form('member');
            echo '<input type="hidden" name="workspace" value="'.esc_attr($w->ID).'"><label>'.esc_html__('Přihlašovací jméno učitele', 'miloslavhub-live').' <input name="user_login" autocomplete="off" required></label> <label>'.esc_html__('Role', 'miloslavhub-live').' <select name="role">';
            foreach (array_merge(self::ROLES,array('')) as $r) { echo '<option value="'.esc_attr($r).'">'.esc_html(self::role_label($r)).'</option>'; }
            echo '</select></label> ';submit_button(__('Uložit členství', 'miloslavhub-live'),'secondary','submit',false);echo '</form>';
        }
        if (self::operator()) {
            echo '<h2>'.esc_html__('Přidat organizaci', 'miloslavhub-live').'</h2>';self::form('create');
            foreach (array('title'=>__('Název', 'miloslavhub-live'),'user_login'=>__('Přihlašovací jméno vlastníka', 'miloslavhub-live'),'external_ref'=>__('Reference organizace v Hubu', 'miloslavhub-live')) as $name=>$label) { echo '<p><label>'.esc_html($label).' <input name="'.esc_attr($name).'" '.($name==='external_ref'?'':'required').'></label></p>'; }
            submit_button(__('Vytvořit', 'miloslavhub-live'));echo '</form>';
        }
        echo '<h2>'.esc_html__('Sdílet nebo převést obsah', 'miloslavhub-live').'</h2>';
        foreach (array('share','transfer') as $operation) {
            self::form($operation);echo '<label>'.esc_html__('Obsah', 'miloslavhub-live').' <select name="post">';
            foreach (self::visible_ids() as $id) { if (self::can($id,$operation)) { echo '<option value="'.esc_attr($id).'">'.esc_html(get_the_title($id)).'</option>'; } }
            echo '</select></label> <label>'.esc_html__('Přihlašovací jméno učitele', 'miloslavhub-live').' <input name="user_login" autocomplete="off" required></label> ';
            if ($operation==='share') { echo '<select name="role"><option value="viewer">'.esc_html__('Pouze čtení', 'miloslavhub-live').'</option><option value="collaborating_teacher">'.esc_html__('Spolupracující učitel', 'miloslavhub-live').'</option><option value="">'.esc_html__('Zrušit sdílení', 'miloslavhub-live').'</option></select> '; }
            submit_button($operation==='share'?__('Uložit sdílení', 'miloslavhub-live'):__('Převést vlastnictví', 'miloslavhub-live'),'secondary','submit',false);echo '</form>';
        }
        echo '</div>';
    }
}
