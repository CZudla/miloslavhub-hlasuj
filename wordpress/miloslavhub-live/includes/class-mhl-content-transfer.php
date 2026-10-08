<?php
if (!defined('ABSPATH')) { exit; }

/** Portable content only. No production identifiers, people, results or credentials. */
class MHL_Content_Transfer {
    public const FORMAT = 'hlasuj-content';
    public const FORMAT_VERSION = 2;
    public const MAX_BYTES = 2097152;
    private const MAX_QUESTIONS = 500;
    private const MAX_LECTURES = 100;

    public static function init(): void {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_mhl_export_content', array(__CLASS__, 'download'));
    }

    public static function menu(): void {
        add_submenu_page('mhl-live', __('Přenést obsah', 'miloslavhub-live'), __('Přenést obsah', 'miloslavhub-live'), 'mhl_access', 'mhl-content-transfer', array(__CLASS__, 'page'));
    }

    /** Deliberate allowlist. External links and instance settings are not portable. */
    private static function rules(string $type, int $version = self::FORMAT_VERSION): array {
        if ($type === 'subject') {
            return array(
                'brand_template'=>array('enum', array('miloslavhub','fes_upce','neutral','custom'), 'miloslavhub'),
                'subject_template'=>array('enum', array('standard','competition','minimal'), 'standard'),
                'subject_short_title'=>array('text', 200, ''), 'subject_code'=>array('text', 100, ''),
                'subject_period'=>array('text', 200, ''), 'competition_title'=>array('text', 300, ''),
                'subject_extra_info'=>array('text', 4000, ''), 'custom_brand_name'=>array('text', 200, ''),
                'custom_brand_subtitle'=>array('text', 300, ''),
                'custom_primary_color'=>array('color', null, '#172033'), 'custom_accent_color'=>array('color', null, '#1f5fae'),
                'hof_enabled'=>array('bool', null, false), 'hof_visibility'=>array('enum', array('public','participants'), 'public'),
                'hof_limit'=>array('int', array(3,100), 10), 'hof_period_days'=>array('int', array(1,3650), 365),
                'hof_title'=>array('text', 300, ''), 'hof_nonopt_mode'=>array('enum', array('hidden','anonymous'), 'hidden'),
            );
        }
        if ($type === 'lecture') {
            return array('gamification'=>array('bool', null, false), 'score_scope'=>array('enum', array('subject','lecture','none'), 'subject'), 'show_live_results'=>array('bool', null, false));
        }
        $rules = array('multiplier'=>array('enum', array(1,1.5,2), 1), 'speed_window'=>array('int', array(5,120), 20),
            'poll_points'=>array('int', array(0,1000), 0), 'time_limit'=>array('time', null, null),
            'rag_policy'=>array('enum', array('exclude','private','public_after_lecture'), 'exclude'),
            'async_show_results'=>array('bool', null, true));
        if ($version >= 2) {
            $rules['correct_answer_explanation']=array('text',4000,'');
            $rules['explanation_mode']=array('enum',array('teacher_only','show_after_close','hidden'),'teacher_only');
            $rules['teacher_note']=array('text',4000,'');
        }
        return $rules;
    }

    private static function fail(string $message): void { throw new RuntimeException($message); }

    private static function authorize(): void {
        if (!current_user_can('mhl_access')) { self::fail(__('Nemáte oprávnění přenášet obsah.', 'miloslavhub-live')); }
    }

    private static function post(int $id, string $type): WP_Post {
        $post = get_post($id);
        if (!$post || $post->post_type !== 'mhl_'.$type || !in_array($post->post_status, array('publish','draft','pending','private','future'), true) || !current_user_can('edit_post', $id)) {
            self::fail(__('Některý záznam není dostupný nebo k němu nemáte oprávnění.', 'miloslavhub-live'));
        }
        return $post;
    }

    private static function settings(int $id, string $type): array {
        $result = array();
        foreach (self::rules($type) as $name=>$rule) {
            $value = get_post_meta($id, '_mhl_'.$name, true);
            // WordPress stores false metadata as ''. Preserve an explicitly
            // disabled boolean instead of replacing it with a true default.
            if ($value === '' && ($rule[0] !== 'bool' || !metadata_exists('post', $id, '_mhl_'.$name))) { $value = $rule[2]; }
            elseif ($rule[0] === 'bool') { $value = (bool)$value; }
            elseif ($rule[0] === 'int' || $rule[0] === 'time') { $value = (int)$value; }
            elseif ($name === 'multiplier') { $value = (float)$value; if ($value == (int)$value) { $value = (int)$value; } }
            $result[$name] = $value;
        }
        return $result;
    }

    public static function export_subject(int $subject_id): array {
        self::authorize();
        $subject = self::post($subject_id, 'subject');
        $lectures = get_posts(array('post_type'=>'mhl_lecture', 'post_status'=>array('publish','draft','pending','private','future'), 'numberposts'=>self::MAX_LECTURES+1, 'orderby'=>'ID', 'order'=>'ASC', 'meta_key'=>'_mhl_subject_id', 'meta_value'=>$subject_id));
        if (count($lectures)>self::MAX_LECTURES) { self::fail(__('Předmět má příliš mnoho přednášek pro jeden přenos.', 'miloslavhub-live')); }
        $bundle = array('format'=>self::FORMAT, 'format_version'=>self::FORMAT_VERSION,
            'subject'=>array('id'=>'s1', 'title'=>$subject->post_title, 'settings'=>self::settings($subject_id, 'subject')),
            'lectures'=>array(), 'questions'=>array());
        $question_map = array();
        foreach ($lectures as $i=>$lecture) {
            self::post((int)$lecture->ID, 'lecture');
            $refs = array();
            foreach (MHL_Core::get_lecture_question_ids((int)$lecture->ID) as $qid) {
                $question = self::post($qid, 'question');
                if (!isset($question_map[$qid])) {
                    $ref = 'q'.(count($question_map)+1); $question_map[$qid] = $ref;
                    if (count($question_map)>self::MAX_QUESTIONS) { self::fail(__('Předmět má příliš mnoho otázek pro jeden přenos.', 'miloslavhub-live')); }
                    $bundle['questions'][] = array('id'=>$ref, 'title'=>$question->post_title, 'options'=>MHL_Core::get_question_options($qid), 'correct_index'=>MHL_Core::question_correct_index($qid), 'settings'=>self::settings($qid, 'question'));
                }
                $refs[] = $question_map[$qid];
            }
            $bundle['lectures'][] = array('id'=>'l'.($i+1), 'title'=>$lecture->post_title, 'question_ids'=>$refs, 'settings'=>self::settings((int)$lecture->ID, 'lecture'));
        }
        return self::validate_json((string)wp_json_encode($bundle, JSON_UNESCAPED_UNICODE));
    }

    private static function keys($value, array $keys): void {
        if (!is_array($value) || array_is_list($value) || count($value)!==count($keys) || array_diff(array_keys($value), $keys)) { self::fail(__('Soubor obsahuje neplatná nebo nepodporovaná pole.', 'miloslavhub-live')); }
    }

    private static function text($value, int $limit): string {
        if (!is_string($value) || strlen($value)>$limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) || trim($value)==='') { self::fail(__('Text v souboru je neplatný nebo příliš dlouhý.', 'miloslavhub-live')); }
        $clean = sanitize_textarea_field($value);
        if (trim($clean)==='') { self::fail(__('Text v souboru je prázdný po odstranění značek.', 'miloslavhub-live')); }
        return $clean;
    }

    private static function validate_settings($settings, string $type, int $version = self::FORMAT_VERSION): array {
        $rules = self::rules($type,$version); self::keys($settings, array_keys($rules));
        foreach ($rules as $name=>$rule) {
            $v = $settings[$name]; $ok = false;
            switch ($rule[0]) {
                case 'bool': $ok = is_bool($v); break;
                case 'int': $ok = is_int($v) && $v >= $rule[1][0] && $v <= $rule[1][1]; break;
                case 'enum': $ok = in_array($v, $rule[1], true); break;
                case 'color': $ok = is_string($v) && preg_match('/^#[a-fA-F0-9]{6}$/D', $v); break;
                case 'time': $ok = $v === null || (is_int($v) && ($v === 0 || ($v >= 5 && $v <= 600))); break;
                case 'text': $ok = is_string($v) && strlen($v) <= $rule[1] && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $v); if ($ok) { $settings[$name] = sanitize_textarea_field($v); } break;
            }
            if (!$ok) { self::fail(__('Nastavení v souboru má neplatnou hodnotu.', 'miloslavhub-live')); }
        }
        return $settings;
    }

    public static function validate_json(string $json): array {
        if ($json === '' || strlen($json)>self::MAX_BYTES) { self::fail(__('Soubor musí být JSON do 2 MiB.', 'miloslavhub-live')); }
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException $e) { self::fail(__('Soubor není platný JSON.', 'miloslavhub-live')); }
        self::keys($data, array('format','format_version','subject','lectures','questions'));
        if ($data['format']!==self::FORMAT || !in_array($data['format_version'],array(1,self::FORMAT_VERSION),true)) { self::fail(__('Tato verze formátu není podporována.', 'miloslavhub-live')); }
        self::keys($data['subject'], array('id','title','settings'));
        if ($data['subject']['id']!=='s1') { self::fail(__('Neplatný identifikátor předmětu.', 'miloslavhub-live')); }
        $data['subject']['title'] = self::text($data['subject']['title'], 1000);
        $data['subject']['settings'] = self::validate_settings($data['subject']['settings'], 'subject');
        foreach (array('questions'=>self::MAX_QUESTIONS,'lectures'=>self::MAX_LECTURES) as $key=>$limit) {
            if (!is_array($data[$key]) || !array_is_list($data[$key]) || count($data[$key])>$limit) { self::fail(__('Soubor obsahuje příliš mnoho záznamů nebo neplatný seznam.', 'miloslavhub-live')); }
        }
        $ids = array();
        foreach ($data['questions'] as &$q) {
            self::keys($q, array('id','title','options','correct_index','settings'));
            if (!is_string($q['id']) || !preg_match('/^q[1-9][0-9]{0,3}$/D', $q['id']) || isset($ids[$q['id']])) { self::fail(__('Identifikátory otázek nejsou jedinečné.', 'miloslavhub-live')); }
            $ids[$q['id']] = true; $q['title'] = self::text($q['title'], 1000);
            if (!is_array($q['options']) || !array_is_list($q['options']) || count($q['options'])<2 || count($q['options'])>26) { self::fail(__('Otázka musí mít 2 až 26 odpovědí.', 'miloslavhub-live')); }
            foreach ($q['options'] as &$option) { $option = self::text($option, 1000); } unset($option);
            if ($q['correct_index']!==null && (!is_int($q['correct_index']) || $q['correct_index']<0 || $q['correct_index']>=count($q['options']))) { self::fail(__('Správná odpověď odkazuje mimo nabídku.', 'miloslavhub-live')); }
            $q['settings'] = self::validate_settings($q['settings'], 'question',$data['format_version']);
        } unset($q);
        $lecture_ids = array(); $used = array();
        foreach ($data['lectures'] as &$l) {
            self::keys($l, array('id','title','question_ids','settings'));
            if (!is_string($l['id']) || !preg_match('/^l[1-9][0-9]{0,3}$/D', $l['id']) || isset($lecture_ids[$l['id']])) { self::fail(__('Identifikátory přednášek nejsou jedinečné.', 'miloslavhub-live')); }
            $lecture_ids[$l['id']] = true; $l['title'] = self::text($l['title'], 1000);
            if (!is_array($l['question_ids']) || !array_is_list($l['question_ids']) || count($l['question_ids'])>self::MAX_QUESTIONS) { self::fail(__('Neplatné pořadí otázek.', 'miloslavhub-live')); }
            $seen = array();
            foreach ($l['question_ids'] as $ref) {
                if (!is_string($ref) || !isset($ids[$ref]) || isset($seen[$ref])) { self::fail(__('Vazba na otázku je neplatná nebo duplicitní.', 'miloslavhub-live')); }
                $seen[$ref] = true; $used[$ref] = true;
            }
            $l['settings'] = self::validate_settings($l['settings'], 'lecture');
        } unset($l);
        if (count($used)!==count($ids)) { self::fail(__('Soubor obsahuje otázky bez vazby na přednášku.', 'miloslavhub-live')); }
        return $data;
    }

    private static function locked(callable $callback) {
        self::authorize();
        $key = 'mhl_content_lock_'.get_current_user_id();
        if (!add_option($key, time(), '', false)) { self::fail(__('Přenos již probíhá. Vyčkejte na jeho dokončení.', 'miloslavhub-live')); }
        try { return $callback(); } finally { delete_option($key); }
    }

    public static function preview(string $json): array {
        self::authorize(); $bundle = self::validate_json($json);
        return self::locked(static function () use ($bundle) {
            $pending = array('token'=>bin2hex(random_bytes(24)), 'bundle'=>$bundle);
            if (!set_transient('mhl_content_pending_'.get_current_user_id(), $pending, 15*MINUTE_IN_SECONDS)) { self::fail(__('Náhled se nepodařilo uložit.', 'miloslavhub-live')); }
            return $pending;
        });
    }

    public static function confirm(string $token): array {
        return self::locked(static function () use ($token) {
            $key = 'mhl_content_pending_'.get_current_user_id(); $pending = get_transient($key);
            if (!is_array($pending) || !hash_equals($pending['token'], $token)) { self::fail(__('Náhled vypršel nebo byl již použit. Nahrajte soubor znovu.', 'miloslavhub-live')); }
            // Consume before writing: two submissions can never create two copies.
            if (!delete_transient($key)) { self::fail(__('Náhled se nepodařilo uzavřít.', 'miloslavhub-live')); }
            $bundle = self::validate_json((string)wp_json_encode($pending['bundle']));
            return self::import_bundle($bundle);
        });
    }

    private static function import_bundle(array $bundle): array {
        $created = array(); $mapping = array(); $prefix = 'import-'.bin2hex(random_bytes(10)).'-';
        try {
            foreach (array('subject'=>array($bundle['subject']), 'question'=>$bundle['questions'], 'lecture'=>$bundle['lectures']) as $type=>$records) {
                if (!current_user_can(get_post_type_object('mhl_'.$type)->cap->create_posts)) { self::fail(__('Nemáte oprávnění vytvářet tento obsah.', 'miloslavhub-live')); }
                foreach ($records as $record) {
                    $id = wp_insert_post(wp_slash(array('post_type'=>'mhl_'.$type, 'post_status'=>'draft', 'post_title'=>$record['title'], 'post_name'=>$prefix.$record['id'], 'post_author'=>get_current_user_id())), true);
                    if (is_wp_error($id) || !$id) { self::fail(__('Import se nepodařil. Nové záznamy budou odstraněny.', 'miloslavhub-live')); }
                    $created[] = $id; $mapping[$record['id']] = $id;
                    $meta = array();
                    foreach ($record['settings'] as $name=>$value) { if ($value!==null) { $meta['_mhl_'.$name] = $value; } }
                    if ($type === 'question') {
                        $meta['_mhl_options'] = $record['options']; $meta['_mhl_mode'] = $record['correct_index']===null ? 'poll' : 'quiz';
                        if ($record['correct_index']!==null) { $meta['_mhl_correct_index'] = $record['correct_index']; }
                        $meta['_mhl_async_enabled'] = 0;
                    }
                    if ($type === 'lecture') {
                        $meta['_mhl_subject_id'] = $mapping['s1']; $meta['_mhl_question_ids'] = array_map(static fn($ref)=>$mapping[$ref], $record['question_ids']);
                        $meta['_mhl_auto_qr'] = 0;
                    }
                    foreach ($meta as $key=>$value) {
                        if (add_post_meta($id, $key, wp_slash($value), true) === false) { self::fail(__('Nastavení se nepodařilo uložit. Import bude vrácen.', 'miloslavhub-live')); }
                    }
                }
            }
            return array('subject_id'=>$mapping['s1'], 'created_ids'=>$created);
        } catch (Throwable $e) {
            $removed = true;
            foreach (array_reverse($created) as $id) { if (!wp_delete_post($id, true)) { $removed = false; } }
            if (!$removed) { self::fail(__('Import selhal a některé nové koncepty se nepodařilo odstranit. Správce musí zkontrolovat koncepty s prefixem import-.', 'miloslavhub-live')); }
            throw $e;
        }
    }

    public static function download(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { wp_die(esc_html__('Použijte tlačítko exportu.', 'miloslavhub-live'), '', array('response'=>405)); }
        if (!current_user_can('mhl_access')) { wp_die(esc_html__('Nemáte oprávnění.', 'miloslavhub-live'), '', array('response'=>403)); }
        check_admin_referer('mhl_export_content');
        try { $data = self::export_subject(absint($_POST['subject_id'] ?? 0)); }
        catch (Throwable $e) { wp_die(esc_html($e->getMessage())); }
        nocache_headers(); header('Content-Type: application/json; charset=UTF-8'); header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="predmet.hlasuj.json"');
        echo wp_json_encode($data, JSON_UNESCAPED_UNICODE); exit;
    }

    public static function page(): void {
        if (!current_user_can('mhl_access')) { wp_die(esc_html__('Nemáte oprávnění.', 'miloslavhub-live'), '', array('response'=>403)); }
        $message = ''; $error = ''; $result = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('mhl_import_content');
            try {
                if (($_POST['step'] ?? '') === 'preview') {
                    $file = $_FILES['content_file'] ?? array();
                    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') || ($file['size'] ?? 0)>self::MAX_BYTES) { self::fail(__('Vyberte JSON do 2 MiB.', 'miloslavhub-live')); }
                    $json = file_get_contents($file['tmp_name'], false, null, 0, self::MAX_BYTES+1);
                    if ($json === false) { self::fail(__('Soubor nelze přečíst.', 'miloslavhub-live')); }
                    self::preview($json);
                } elseif (($_POST['step'] ?? '') === 'confirm') {
                    $token = $_POST['preview_token'] ?? '';
                    if (!is_string($token)) { self::fail(__('Neplatný náhled.', 'miloslavhub-live')); }
                    $result = self::confirm(wp_unslash($token)); $message = __('Obsah byl přenesen. Nové koncepty můžete zkontrolovat a postupně zveřejnit.', 'miloslavhub-live');
                } else { self::fail(__('Neplatný krok přenosu.', 'miloslavhub-live')); }
            } catch (Throwable $e) { $error = $e->getMessage(); }
        }
        echo '<div class="wrap mhl-wrap"><h1>'.esc_html__('Přenést obsah', 'miloslavhub-live').'</h1>';
        if ($error) { echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>'; }
        if ($message) { echo '<div class="notice notice-success"><p>'.esc_html($message).'</p></div><p><a class="button" href="'.esc_url(get_edit_post_link($result['subject_id'], 'raw')).'">'.esc_html__('Otevřít nový předmět', 'miloslavhub-live').'</a></p>'; }
        echo '<p>'.esc_html__('Přeneste předmět s přednáškami a otázkami. Soubor obsahuje správné odpovědi, vysvětlení i soukromé poznámky učitele. Před sdílením je zkontrolujte a soubor předejte pouze určeným vyučujícím.', 'miloslavhub-live').'</p>';
        echo '<p>'.esc_html__('Přenáší se texty, pořadí, sdílené otázky a podporovaná nastavení. Výsledky, lidé, kategorie, externí odkazy, soubory, termíny a trvalé QR adresy se nepřenášejí. Zkontrolujte také osobní údaje v textech.', 'miloslavhub-live').'</p>';
        echo '<h2>'.esc_html__('Sdílet předmět s kolegou', 'miloslavhub-live').'</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('mhl_export_content'); echo '<input type="hidden" name="action" value="mhl_export_content"><label for="mhl-transfer-subject">'.esc_html__('Předmět', 'miloslavhub-live').'</label> <select id="mhl-transfer-subject" name="subject_id" required><option value="">'.esc_html__('Vyberte předmět', 'miloslavhub-live').'</option>';
        foreach (get_posts(array('post_type'=>'mhl_subject','post_status'=>array('publish','draft','pending','private','future'),'numberposts'=>-1,'orderby'=>'title','order'=>'ASC')) as $post) {
            if (current_user_can('edit_post', $post->ID)) { echo '<option value="'.esc_attr($post->ID).'">'.esc_html($post->post_title).'</option>'; }
        }
        echo '</select> '; submit_button(__('Stáhnout obsah', 'miloslavhub-live'), 'secondary', 'submit', false); echo '</form>';
        echo '<h2>'.esc_html__('Převzít předmět ze souboru', 'miloslavhub-live').'</h2><form method="post" enctype="multipart/form-data">'; wp_nonce_field('mhl_import_content');
        echo '<input type="hidden" name="step" value="preview"><label for="mhl-transfer-file">'.esc_html__('Soubor předmětu', 'miloslavhub-live').'</label> <input id="mhl-transfer-file" type="file" name="content_file" accept=".json,application/json" required> '; submit_button(__('Zobrazit náhled', 'miloslavhub-live'), 'secondary', 'submit', false); echo '</form>';
        $pending = get_transient('mhl_content_pending_'.get_current_user_id());
        if (is_array($pending)) {
            $bundle = $pending['bundle'];
            echo '<section id="mhl-transfer-preview"><h2>'.esc_html__('Náhled nového předmětu', 'miloslavhub-live').'</h2><p><strong>'.esc_html($bundle['subject']['title']).'</strong></p><p>'.esc_html(sprintf(__('Přednášky: %1$d · Otázky: %2$d', 'miloslavhub-live'), count($bundle['lectures']), count($bundle['questions']))).'</p><ul>';
            foreach ($bundle['lectures'] as $lecture) { echo '<li>'.esc_html($lecture['title']).' ('.esc_html(count($lecture['question_ids'])).')</li>'; }
            echo '</ul><p>'.esc_html__('Vzniknou nové koncepty s novými adresami. Dlouhodobé ankety zůstanou vypnuté. Zkontrolujte obsah, doplňte vyučující a odkazy a zveřejněte nejprve otázky, potom předmět a přednášky. Náhled platí 15 minut.', 'miloslavhub-live').'</p><form method="post">'; wp_nonce_field('mhl_import_content');
            echo '<input type="hidden" name="step" value="confirm"><input type="hidden" name="preview_token" value="'.esc_attr($pending['token']).'">'; submit_button(__('Vytvořit nové koncepty', 'miloslavhub-live'), 'primary', 'submit', false); echo '</form></section>';
        }
        echo '</div>';
    }
}
