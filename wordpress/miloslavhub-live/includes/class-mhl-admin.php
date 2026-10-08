<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/class-mhl-i18n.php';

class MHL_Admin {
    public static function init(): void {
        add_action('admin_menu', array(__CLASS__,'menu'));
        add_action('add_meta_boxes', array(__CLASS__,'meta_boxes'));
        add_action('save_post_mhl_question', array(__CLASS__,'save_question'));
        add_action('save_post_mhl_lecture', array(__CLASS__,'save_lecture'));
        add_action('save_post_mhl_subject', array(__CLASS__,'save_subject'));
        add_action('admin_enqueue_scripts', array(__CLASS__,'assets'));
        add_action('admin_init', array(__CLASS__,'ensure_demo_available'));
        foreach (array('start_run','open_session','close_session','reset_session','finish_run','save_settings','export_run','simulate_votes','clear_test_data','create_demo','reset_demo') as $action) {
            add_action('admin_post_mhl_'.$action, array(__CLASS__,$action));
        }
        add_filter('manage_mhl_question_posts_columns', array(__CLASS__,'question_columns'));
        add_action('manage_mhl_question_posts_custom_column', array(__CLASS__,'question_column_content'),10,2);
        add_filter('manage_mhl_lecture_posts_columns', array(__CLASS__,'lecture_columns'));
        add_action('manage_mhl_lecture_posts_custom_column', array(__CLASS__,'lecture_column_content'),10,2);
        add_filter('manage_mhl_subject_posts_columns', array(__CLASS__,'subject_columns'));
        add_action('manage_mhl_subject_posts_custom_column', array(__CLASS__,'subject_column_content'),10,2);
        add_filter('post_row_actions', array(__CLASS__,'row_actions'),10,2);
    }

    public static function menu(): void {
        add_menu_page('MiloslavHub Live',MHL_I18n::text('Živé hlasování'),'mhl_access','mhl-live',array(__CLASS__,'dashboard'),'dashicons-chart-bar',58);
        add_submenu_page('mhl-live',MHL_I18n::text('Přehled'),MHL_I18n::text('Přehled'),'mhl_access','mhl-live',array(__CLASS__,'dashboard'));
        add_submenu_page('mhl-live',MHL_I18n::text('Živé ovládání'),MHL_I18n::text('Živé ovládání'),'mhl_access','mhl-live-control',array(__CLASS__,'live_control'));
        add_submenu_page('mhl-live',MHL_I18n::text('Testovací laboratoř'),MHL_I18n::text('Testovací laboratoř'),'mhl_access','mhl-live-test',array(__CLASS__,'test_lab'));
        add_submenu_page('mhl-live',MHL_I18n::text('Ukázkové demo'),MHL_I18n::text('Ukázkové demo'),'manage_options','mhl-live-demo',array(__CLASS__,'demo_page'));
        add_submenu_page('mhl-live',MHL_I18n::text('Dlouhodobé ankety'),MHL_I18n::text('Dlouhodobé ankety'),'mhl_access','mhl-live-async',array(__CLASS__,'long_polls'));
        add_submenu_page('mhl-live',MHL_I18n::text('Archiv výsledků'),MHL_I18n::text('Archiv výsledků'),'mhl_access','mhl-live-archive',array(__CLASS__,'archive'));
        add_submenu_page('mhl-live',MHL_I18n::text('Nastavení'),MHL_I18n::text('Nastavení'),'manage_options','mhl-live-settings',array(__CLASS__,'settings_page'));
    }
    public static function assets(string $hook): void {
        $screen=get_current_screen(); $pt=$screen->post_type??'';
        if(strpos($hook,'mhl')===false && !in_array($pt,array('mhl_question','mhl_lecture','mhl_subject'),true)){return;}
        wp_enqueue_style('mhl-admin',MHL_URL.'assets/admin.css',array(),MHL_VERSION);
        wp_enqueue_script('mhl-qrcode',MHL_URL.'assets/qrcode.min.js',array(),'1.0.0',true);
        MHL_I18n::assets();
        wp_enqueue_script('mhl-admin',MHL_URL.'assets/admin.js',array('mhl-qrcode','mhl-i18n'),MHL_VERSION,true);
    }
    public static function meta_boxes(): void {
        add_meta_box('mhl-subject-template',MHL_I18n::text('Brand a rozvržení předmětu'),array(__CLASS__,'subject_template_box'),'mhl_subject','normal','high');
        add_meta_box('mhl-subject-teachers',MHL_I18n::text('Vyučující'),array(__CLASS__,'subject_teachers_box'),'mhl_subject','normal','high');
        add_meta_box('mhl-subject-hall',MHL_I18n::text('Síň slávy a soukromí'),array(__CLASS__,'subject_hall_box'),'mhl_subject','normal','default');
        add_meta_box('mhl-subject-projection',MHL_I18n::text('Projekce bez WordPress účtu'),array(__CLASS__,'subject_projection_box'),'mhl_subject','side','default');
        add_meta_box('mhl-question-config',MHL_I18n::text('Nastavení otázky'),array(__CLASS__,'question_meta_box'),'mhl_question','normal','high');
        add_meta_box('mhl-question-rag','AI / RAG',array(__CLASS__,'question_rag_box'),'mhl_question','side','default');
        add_meta_box('mhl-lecture-config',MHL_I18n::text('Předmět, otázky a interakce'),array(__CLASS__,'lecture_meta_box'),'mhl_lecture','normal','high');
        add_meta_box('mhl-lecture-qr',MHL_I18n::text('QR kódy do prezentace'),array(__CLASS__,'lecture_qr_box'),'mhl_lecture','side','default');
    }
    private static function help(string $text): string { return MHL_I18n::html('<span class="mhl-help" tabindex="0" role="button" aria-label="Nápověda" data-help="').esc_attr($text).'">?</span>'; }

    public static function subject_template_box(WP_Post $post): void {
        wp_nonce_field('mhl_save_subject','mhl_subject_nonce');
        $t=MHL_Core::subject_template((int)$post->ID);
        ?>
        <p class="description"><strong>Hlasuj! by MiloslavHub <?php echo esc_html(MHL_VERSION); ?>.</strong><?php echo esc_html_mhl_literal(' Každý předmět má dvě nezávislé vrstvy: '); ?><strong>brand</strong><?php echo esc_html_mhl_literal(' (kdo se prezentuje) a '); ?><strong><?php echo esc_html_mhl_literal('rozvržení'); ?></strong><?php echo esc_html_mhl_literal(' (standardní / soutěžní / minimalistické). Brand se automaticky použije na mobilu i projekci.'); ?></p>
        <div class="mhl-brand-presets">
          <label class="mhl-brand-card"><input type="radio" name="mhl_brand_template" value="miloslavhub" <?php checked($t['brand_template'],'miloslavhub'); ?>><span><strong>Miloslav Hub</strong><small><?php echo esc_html_mhl_literal('Osobní přednášky; výrazně propaguje Miloslav Hub a miloslavhub.cz.'); ?></small></span></label>
          <label class="mhl-brand-card mhl-brand-fes"><input type="radio" name="mhl_brand_template" value="fes_upce" <?php checked($t['brand_template'],'fes_upce'); ?>><span><strong>FES UPa</strong><small><?php echo esc_html_mhl_literal('Fakultní zelená, Univerzita Pardubice; bez osobní propagace Miloslav Hub.'); ?></small></span></label>
          <label class="mhl-brand-card mhl-brand-neutral"><input type="radio" name="mhl_brand_template" value="neutral" <?php checked($t['brand_template'],'neutral'); ?>><span><strong><?php echo esc_html_mhl_literal('Neutrální'); ?></strong><small><?php echo esc_html_mhl_literal('Pro použití mimo FES UPa i mimo osobní brand.'); ?></small></span></label>
          <label class="mhl-brand-card"><input type="radio" name="mhl_brand_template" value="custom" <?php checked($t['brand_template'],'custom'); ?>><span><strong><?php echo esc_html_mhl_literal('Vlastní'); ?></strong><small><?php echo esc_html_mhl_literal('Vlastní název, web, logo a barvy.'); ?></small></span></label>
        </div>
        <div class="mhl-field-grid mhl-subject-template-grid">
          <label><strong><?php echo esc_html_mhl_literal('Rozvržení '); ?><?php echo self::help(MHL_I18n::text('Standardní je univerzální. Soutěžní zvýrazní název soutěže a bodování. Minimalistické ponechá jen nejdůležitější informace.')); ?></strong>
            <select name="mhl_subject_template">
              <option value="standard" <?php selected($t['template'],'standard'); ?>><?php echo esc_html_mhl_literal('Standardní'); ?></option>
              <option value="competition" <?php selected($t['template'],'competition'); ?>><?php echo esc_html_mhl_literal('Soutěžní'); ?></option>
              <option value="minimal" <?php selected($t['template'],'minimal'); ?>><?php echo esc_html_mhl_literal('Minimalistické'); ?></option>
            </select>
          </label>
          <label><strong><?php echo esc_html_mhl_literal('Krátký název '); ?><?php echo self::help(MHL_I18n::text('Zkrácený název předmětu pro malé obrazovky a kompaktní záhlaví, např. OOP.')); ?></strong><input type="text" name="mhl_subject_short_title" value="<?php echo esc_attr($t['short_title']); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Např. OOP')); ?>"></label>
          <label><strong><?php echo esc_html_mhl_literal('Kód předmětu '); ?><?php echo self::help(MHL_I18n::text('Volitelný oficiální nebo interní kód předmětu, např. FBIK.')); ?></strong><input type="text" name="mhl_subject_code" value="<?php echo esc_attr($t['code']); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Např. FBIK')); ?>"></label>
          <label><strong><?php echo esc_html_mhl_literal('Semestr / skupina '); ?><?php echo self::help(MHL_I18n::text('Volitelná informace pro rozlišení běhů předmětu, např. ZS 2026/27 nebo skupina A.')); ?></strong><input type="text" name="mhl_subject_period" value="<?php echo esc_attr($t['period']); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Např. ZS 2026/27')); ?>"></label>
          <label class="mhl-template-wide"><strong><?php echo esc_html_mhl_literal('Název soutěže '); ?><?php echo self::help(MHL_I18n::text('Používá se hlavně u soutěžního rozvržení jako výrazný název kvízu nebo soutěže.')); ?></strong><input type="text" name="mhl_competition_title" value="<?php echo esc_attr($t['competition_title']); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Např. OOP Challenge')); ?>"></label>
          <label class="mhl-template-wide"><strong><?php echo esc_html_mhl_literal('Doplňující informace '); ?><?php echo self::help(MHL_I18n::text('Volitelný delší text zobrazovaný podle zvolené šablony, např. pravidla soutěže, způsob bodování nebo organizační poznámka.')); ?></strong><textarea name="mhl_subject_extra_info" rows="4" placeholder="<?php echo esc_html_mhl_literal('Např. Body se počítají jen v rámci aktuální přednášky.'); ?>"><?php echo esc_textarea($t['extra_info']); ?></textarea></label>
        </div>
        <details class="mhl-custom-brand" <?php echo $t['brand_template']==='custom'?'open':''; ?>>
          <summary><strong><?php echo esc_html_mhl_literal('Nastavení vlastní značky'); ?></strong><?php echo esc_html_mhl_literal(' – používá se pouze u šablony Vlastní'); ?></summary>
          <div class="mhl-field-grid mhl-custom-brand-grid">
            <label><strong><?php echo esc_html_mhl_literal('Název značky '); ?><?php echo self::help(MHL_I18n::text('Název organizace, projektu nebo akce zobrazovaný místo přednastavené značky.')); ?></strong><input type="text" name="mhl_custom_brand_name" value="<?php echo esc_attr($t['custom_brand_name']); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Název organizace / akce')); ?>"></label>
            <label><strong><?php echo esc_html_mhl_literal('Podtitulek '); ?><?php echo self::help(MHL_I18n::text('Krátký doprovodný text pod názvem značky, např. Živé hlasování.')); ?></strong><input type="text" name="mhl_custom_brand_subtitle" value="<?php echo esc_attr($t['custom_brand_subtitle']); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Živé hlasování')); ?>"></label>
            <label><strong><?php echo esc_html_mhl_literal('Web '); ?><?php echo self::help(MHL_I18n::text('Cílová veřejná adresa značky; používá se jako odkaz ve veřejném rozhraní.')); ?></strong><input type="url" name="mhl_custom_brand_url" value="<?php echo esc_attr($t['custom_brand_url']); ?>" placeholder="https://example.cz"></label>
            <label><strong><?php echo esc_html_mhl_literal('Logo (URL) '); ?><?php echo self::help(MHL_I18n::text('Volitelná veřejná HTTPS adresa loga. Pokud ji nevyplníte, použije se pouze textová identita.')); ?></strong><input type="url" name="mhl_custom_logo_url" value="<?php echo esc_attr($t['custom_logo_url']); ?>" placeholder="https://…/logo.svg"></label>
            <label><strong><?php echo esc_html_mhl_literal('Hlavní barva '); ?><?php echo self::help(MHL_I18n::text('Primární barva vlastní značky pro prvky rozhraní.')); ?></strong><input type="color" name="mhl_custom_primary_color" value="<?php echo esc_attr($t['custom_primary_color']); ?>"></label>
            <label><strong><?php echo esc_html_mhl_literal('Akcentní barva '); ?><?php echo self::help(MHL_I18n::text('Doplňková barva pro zvýraznění výsledků a interaktivních prvků.')); ?></strong><input type="color" name="mhl_custom_accent_color" value="<?php echo esc_attr($t['custom_accent_color']); ?>"></label>
          </div>
        </details>
        <p class="description"><?php echo esc_html_mhl_literal('Šablona '); ?><strong>FES UPa</strong><?php echo esc_html_mhl_literal(' používá fakultní zelenou a textovou identitu FES/UPa; plugin neobsahuje licencované univerzitní písmo ani oficiální logo. Lze je případně doplnit později z autorizovaných podkladů.'); ?></p>
        <?php
    }

    public static function subject_hall_box(WP_Post $post): void {
        $h=MHL_Core::subject_hall_of_fame((int)$post->ID);
        echo MHL_I18n::html('<p class="description">Síň slávy používá výsledné pořadí v předmětu. Student se rozhoduje až ve chvíli, kdy se skutečně dostane mezi nastavený počet nejlepších. Výchozí stav nezveřejňuje přezdívku.</p>');
        echo '<div class="mhl-field-grid">';
        echo MHL_I18n::html('<label><strong>Síň slávy ').self::help(MHL_I18n::text('Povolí stránku s agregovaným pořadím tohoto předmětu. Studentovi v Top N se po výsledku nabídne tlačítko pro zveřejnění přezdívky.')).'</strong><span><input type="checkbox" name="mhl_hof_enabled" value="1" '.checked($h['enabled'],true,false).MHL_I18n::html('> Povolit</span></label>');
        echo MHL_I18n::html('<label><strong>Viditelnost ').self::help(MHL_I18n::text('Veřejná = stránku může otevřít kdokoli s odkazem. Pouze účastníci = žebříček uvidí jen prohlížeč s aktivní rezervací přezdívky.')).'</strong><select name="mhl_hof_visibility"><option value="public" '.selected($h['visibility']??'public','public',false).MHL_I18n::html('>Veřejná</option><option value="participants" ').selected($h['visibility']??'public','participants',false).MHL_I18n::html('>Pouze účastníci předmětu</option></select></label>');
        echo MHL_I18n::html('<label><strong>Počet míst ').self::help(MHL_I18n::text('Např. 10 znamená, že nabídku k přidání do Síně slávy dostanou studenti na 1.–10. místě.')).'</strong><input type="number" min="3" max="100" name="mhl_hof_limit" value="'.esc_attr($h['limit']).'"></label>';
        echo MHL_I18n::html('<label><strong>Kdo nepřidá přezdívku ').self::help(MHL_I18n::text('Určuje, co se stane s kvalifikovaným studentem, který tlačítko Přidat do Síně slávy nepoužije. Nezobrazovat je soukromější; anonymně zachová kompletní pořadí bez přezdívky.')).'</strong><select name="mhl_hof_nonopt_mode"><option value="hidden" '.selected($h['nonopt_mode']??'hidden','hidden',false).MHL_I18n::html('>Nezobrazovat</option><option value="anonymous" ').selected($h['nonopt_mode']??'hidden','anonymous',false).MHL_I18n::html('>Zobrazit anonymně</option></select></label>');
        echo MHL_I18n::html('<label><strong>Období ').self::help(MHL_I18n::text('Pořadí se počítá pouze z hlasů za poslední zadaný počet dní.')).'</strong><input type="number" min="1" max="3650" name="mhl_hof_period_days" value="'.esc_attr($h['period_days']).MHL_I18n::html('"> dní</label>');
        echo MHL_I18n::html('<label><strong>Nadpis ').self::help(MHL_I18n::text('Volitelný veřejný název soutěže nebo žebříčku.')).'</strong><input type="text" name="mhl_hof_title" value="'.esc_attr($h['title']).MHL_I18n::html('" placeholder="Např. OOP Challenge"></label>');
        echo '</div>';
        if(!empty($h['url'])){ echo MHL_I18n::html('<p class="mhl-url-note"><strong>Adresa:</strong> <code>').esc_html($h['url']).'</code> &nbsp; <a class="button" href="'.esc_url($h['url']).MHL_I18n::html('" target="_blank" rel="noopener">Otevřít</a></p>'); }
        echo MHL_I18n::html('<p class="description"><strong>Soukromí:</strong> veřejně se neposílá jméno, e-mail ani technický identifikátor. Přezdívka se zveřejní až po aktivním kliknutí studenta.</p>');
    }

    public static function subject_projection_box(WP_Post $post): void {
        $p=MHL_Core::subject_projection((int)$post->ID);
        echo MHL_I18n::html('<p>Trvalý <strong>read-only</strong> projekční odkaz lze předat vyučujícímu, který nemá účet ve WordPressu. Stránka automaticky sleduje právě aktivní otázku v předmětu.</p>');
        if(!empty($p['url'])){echo '<p><code style="word-break:break-all">'.esc_html($p['url']).'</code></p><p><button type="button" class="button mhl-copy" data-copy="'.esc_attr($p['url']).MHL_I18n::html('">Kopírovat</button> <a class="button" href="').esc_url($p['url']).MHL_I18n::html('" target="_blank" rel="noopener">Otevřít ↗</a></p>');}
        echo MHL_I18n::html('<label><input type="checkbox" name="mhl_projection_regenerate" value="1"> Vygenerovat při uložení nový projekční odkaz</label>');
        echo MHL_I18n::html('<p class="description">Nový token zneplatní dříve předané projekční odkazy. Studentské QR kódy tím nejsou dotčeny.</p>');
    }

    public static function subject_teachers_box(WP_Post $post): void {
        wp_nonce_field('mhl_save_subject','mhl_subject_nonce');
        $teachers=MHL_Core::subject_teachers((int)$post->ID,false);
        if(!$teachers){$teachers=array(array('name'=>'','role'=>'','profile_url'=>'','email'=>'','show_public'=>true));}
        ?>
        <p class="description"><?php echo esc_html_mhl_literal('Uveďte jednoho nebo více vyučujících předmětu. Přednášky je automaticky zdědí. Ve veřejném hlasování se zobrazí jen vyučující označení „Zobrazovat studentům“. E-mail zůstává pouze v administraci.'); ?></p>
        <div class="mhl-teachers" data-mhl-teachers>
          <div class="mhl-teachers-head" aria-hidden="true"><span><?php echo esc_html_mhl_literal('Jméno'); ?></span><span><?php echo esc_html_mhl_literal('Role'); ?></span><span><?php echo esc_html_mhl_literal('Profil / web'); ?></span><span>E-mail</span><span><?php echo esc_html_mhl_literal('Veřejně'); ?></span><span></span></div>
          <div data-mhl-teachers-list>
          <?php foreach($teachers as $i=>$t): ?>
            <div class="mhl-teacher-row" data-mhl-teacher-row>
              <input type="text" name="mhl_teachers[<?php echo esc_attr($i); ?>][name]" value="<?php echo esc_attr($t['name']??''); ?>" placeholder="Miloslav Hub" aria-label="<?php echo esc_attr(MHL_I18n::text('Jméno vyučujícího')); ?>">
              <input type="text" name="mhl_teachers[<?php echo esc_attr($i); ?>][role]" value="<?php echo esc_attr($t['role']??''); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Přednášející')); ?>" aria-label="<?php echo esc_attr(MHL_I18n::text('Role vyučujícího')); ?>" list="mhl-teacher-roles">
              <input type="url" name="mhl_teachers[<?php echo esc_attr($i); ?>][profile_url]" value="<?php echo esc_attr($t['profile_url']??''); ?>" placeholder="https://…" aria-label="<?php echo esc_attr(MHL_I18n::text('Profil nebo web vyučujícího')); ?>">
              <input type="email" name="mhl_teachers[<?php echo esc_attr($i); ?>][email]" value="<?php echo esc_attr($t['email']??''); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('e-mail (neveřejný)')); ?>" aria-label="<?php echo esc_attr(MHL_I18n::text('E-mail vyučujícího')); ?>">
              <label class="mhl-teacher-public"><input type="checkbox" name="mhl_teachers[<?php echo esc_attr($i); ?>][show_public]" value="1" <?php checked(!empty($t['show_public'])); ?>> <span><?php echo esc_html_mhl_literal('Zobrazovat'); ?></span></label>
              <button type="button" class="button-link-delete mhl-remove-teacher"><?php echo esc_html_mhl_literal('Odebrat'); ?></button>
            </div>
          <?php endforeach; ?>
          </div>
          <p><button type="button" class="button" data-mhl-add-teacher><?php echo esc_html_mhl_literal('+ Přidat vyučujícího'); ?></button> <?php echo self::help(MHL_I18n::text('Vyučující jsou metadata předmětu; nezískávají tím žádná administrační oprávnění. Přednášky seznam automaticky přebírají z předmětu.')); ?></p>
          <datalist id="mhl-teacher-roles"><option value="<?php echo esc_attr(MHL_I18n::text('Garant')); ?>"><option value="<?php echo esc_attr(MHL_I18n::text('Přednášející')); ?>"><option value="<?php echo esc_attr(MHL_I18n::text('Cvičící')); ?>"><option value="<?php echo esc_attr(MHL_I18n::text('Hostující přednášející')); ?>"></datalist>
          <template data-mhl-teacher-template>
            <div class="mhl-teacher-row" data-mhl-teacher-row>
              <input type="text" data-field="name" placeholder="<?php echo esc_html_mhl_literal('Jméno vyučujícího'); ?>" aria-label="<?php echo esc_html_mhl_literal('Jméno vyučujícího'); ?>">
              <input type="text" data-field="role" placeholder="<?php echo esc_html_mhl_literal('Přednášející'); ?>" aria-label="<?php echo esc_html_mhl_literal('Role vyučujícího'); ?>" list="mhl-teacher-roles">
              <input type="url" data-field="profile_url" placeholder="https://…" aria-label="<?php echo esc_html_mhl_literal('Profil nebo web vyučujícího'); ?>">
              <input type="email" data-field="email" placeholder="<?php echo esc_html_mhl_literal('e-mail (neveřejný)'); ?>" aria-label="<?php echo esc_html_mhl_literal('E-mail vyučujícího'); ?>">
              <label class="mhl-teacher-public"><input type="checkbox" data-field="show_public" value="1" checked> <span><?php echo esc_html_mhl_literal('Zobrazovat'); ?></span></label>
              <button type="button" class="button-link-delete mhl-remove-teacher"><?php echo esc_html_mhl_literal('Odebrat'); ?></button>
            </div>
          </template>
        </div>
        <?php
    }

    public static function row_actions(array $actions, WP_Post $post): array {
        if (in_array($post->post_type, array('mhl_subject','mhl_lecture','mhl_question'), true)) {
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }

    public static function question_meta_box(WP_Post $post): void {
        wp_nonce_field('mhl_save_question','mhl_question_nonce');
        $options=MHL_Core::get_question_options($post->ID); while(count($options)<6){$options[]='';}
        $correct=MHL_Core::question_correct_index($post->ID); $mult=(float)(get_post_meta($post->ID,'_mhl_multiplier',true)?:1); $speed=(int)(get_post_meta($post->ID,'_mhl_speed_window',true)?:MHL_Core::settings()['speed_window']); $time_raw=get_post_meta($post->ID,'_mhl_time_limit',true); $time_value=($time_raw===''||$time_raw===null)?'auto':(string)(int)$time_raw;
        $type=$correct===null?MHL_I18n::text('Anketa – bez správné odpovědi'):MHL_I18n::text('Kvíz – se správnou odpovědí a body');
        ?>
        <p><strong><?php echo esc_html_mhl_literal('Typ otázky:'); ?></strong> <span id="mhl-inferred-type"><?php echo esc_html($type); ?></span> <?php echo self::help(MHL_I18n::text('Typ se určuje automaticky. Pokud není označena žádná správná odpověď, jde o anketu. Jakmile označíte správnou odpověď, otázka se chová jako kvíz.')); ?></p>
        <div class="mhl-field-grid">
          <label><strong><?php echo esc_html_mhl_literal('Časový limit hlasování '); ?><?php echo self::help(MHL_I18n::text('Určuje dobu od aktivace otázky do automatického uzavření. Při volbě Automaticky je výchozí kvíz 30 s a anketa bez limitu. Pokud je limit nastaven, studentům i na projekci se zobrazuje odpočet.')); ?></strong>
            <select name="mhl_time_limit">
              <option value="auto" <?php selected($time_value,'auto'); ?>><?php echo esc_html_mhl_literal('Automaticky podle typu'); ?></option>
              <option value="0" <?php selected($time_value,'0'); ?>><?php echo esc_html_mhl_literal('Bez časového limitu'); ?></option>
              <?php foreach(array(15,30,45,60,90,120,180,300) as $sec): ?><option value="<?php echo esc_attr($sec); ?>" <?php selected($time_value,(string)$sec); ?>><?php echo esc_html($sec); ?> s</option><?php endforeach; ?>
            </select>
          </label>
          <label class="mhl-quiz-only"><strong><?php echo esc_html_mhl_literal('Násobitel bodů '); ?><?php echo self::help(MHL_I18n::text('Určuje váhu otázky. ×1 je běžná otázka; vyšší násobitel použijte jen u výrazně důležitější otázky.')); ?></strong>
            <select name="mhl_multiplier"><?php foreach(array(1,1.5,2) as $m): ?><option value="<?php echo esc_attr($m); ?>" <?php selected($mult,$m); ?>>×<?php echo esc_html($m); ?></option><?php endforeach; ?></select>
          </label>
          <label class="mhl-quiz-only"><strong><?php echo esc_html_mhl_literal('Rychlostní okno '); ?><?php echo self::help(MHL_I18n::text('Doba, během níž se u správné odpovědi postupně snižuje rychlostní bonus. Po jejím uplynutí zůstávají základní body za správnost.')); ?></strong>
            <input type="number" min="5" max="120" name="mhl_speed_window" value="<?php echo esc_attr($speed); ?>"> s
          </label>
          <p class="description mhl-poll-only"><?php echo esc_html__('Anketa nemá správnou odpověď a nepřidává soutěžní body.', 'miloslavhub-live'); ?></p>
          <?php $async=MHL_Core::question_async_enabled($post->ID); $async_end=(string)get_post_meta($post->ID,'_mhl_async_end',true); $async_show=MHL_Core::question_async_show_results($post->ID); ?>
          <label class="mhl-poll-only mhl-template-wide"><strong><?php echo esc_html_mhl_literal('Dlouhodobá otevřená anketa '); ?><?php echo self::help(MHL_I18n::text('Vytvoří samostatný trvalý odkaz, který neblokuje živou přednášku. Ankета může běžet dny či týdny; nemá společný odpočet a nepočítá body.')); ?></strong><span><input type="checkbox" name="mhl_async_enabled" value="1" <?php checked($async); ?>><?php echo esc_html_mhl_literal(' Povolit samostatný dlouhodobý odkaz'); ?></span></label>
          <label class="mhl-poll-only"><strong><?php echo esc_html_mhl_literal('Automaticky uzavřít '); ?><?php echo self::help(MHL_I18n::text('Volitelné. Nechte prázdné pro anketu bez pevného konce; uzavřít ji pak lze v administraci.')); ?></strong><input type="datetime-local" name="mhl_async_end" value="<?php echo esc_attr($async_end); ?>"></label>
          <label class="mhl-poll-only"><strong><?php echo esc_html_mhl_literal('Průběžné výsledky '); ?><?php echo self::help(MHL_I18n::text('Po odevzdání odpovědi může respondent vidět aktuální rozložení hlasů, i když anketa stále běží.')); ?></strong><span><input type="checkbox" name="mhl_async_show_results" value="1" <?php checked($async_show); ?>><?php echo esc_html_mhl_literal(' Po hlasování zobrazit průběžné výsledky'); ?></span></label>
        </div>
        <p class="description mhl-quiz-only"><?php echo esc_html_mhl_literal('Výchozí princip: 800 bodů za správnost + až 200 bodů za rychlost. Nesprávná odpověď má 0 bodů.'); ?></p>
        <p><label><input type="radio" name="mhl_correct_index" value="-1" <?php checked($correct===null); ?>> <strong><?php echo esc_html_mhl_literal('Žádná správná odpověď'); ?></strong><?php echo esc_html_mhl_literal(' – otázka bude anketa.'); ?></label></p>
        <table class="widefat striped mhl-options-table"><thead><tr><th class="mhl-correct-col"><?php echo esc_html_mhl_literal('Správná'); ?></th><th><?php echo esc_html_mhl_literal('Možnost'); ?></th><th><?php echo esc_html_mhl_literal('Text odpovědi'); ?></th></tr></thead><tbody>
        <?php foreach($options as $i=>$label): ?><tr><td class="mhl-correct-col"><input type="radio" name="mhl_correct_index" value="<?php echo esc_attr($i); ?>" <?php checked($correct!==null && $correct===$i); ?>></td><td><strong><?php echo esc_html(chr(65+$i)); ?></strong></td><td><input type="text" class="widefat" name="mhl_options[]" value="<?php echo esc_attr($label); ?>" placeholder="<?php echo esc_attr(MHL_I18n::text('Odpověď ')); ?><?php echo esc_attr(chr(65+$i)); ?>"></td></tr><?php endforeach; ?>
        </tbody></table>
        <details class="mhl-quiz-only">
          <summary><?php echo esc_html__('Vysvětlení správné odpovědi','miloslavhub-live'); ?></summary>
          <p><label for="mhl-correct-explanation"><?php echo esc_html__('Vysvětlení','miloslavhub-live'); ?></label>
          <textarea id="mhl-correct-explanation" name="mhl_correct_answer_explanation" class="widefat" rows="4" maxlength="4000"><?php echo esc_textarea((string)get_post_meta($post->ID,'_mhl_correct_answer_explanation',true)); ?></textarea></p>
          <p><label for="mhl-explanation-mode"><?php echo esc_html__('Kdo uvidí vysvětlení?','miloslavhub-live'); ?></label>
          <select id="mhl-explanation-mode" name="mhl_explanation_mode">
          <?php foreach(array('teacher_only'=>__('Pouze já','miloslavhub-live'),'show_after_close'=>__('Studenti po ukončení hlasování','miloslavhub-live'),'hidden'=>__('Nezobrazovat studentům','miloslavhub-live')) as $value=>$label): ?>
            <option value="<?php echo esc_attr($value); ?>" <?php selected(MHL_Core::question_explanation_mode($post->ID),$value); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
          </select></p>
          <p class="description"><?php echo esc_html__('Výchozí volba je Pouze já. Před ukončením hlasování se vysvětlení studentům neposílá.','miloslavhub-live'); ?></p>
        </details>
        <details>
          <summary><?php echo esc_html__('Moje poznámka k výuce','miloslavhub-live'); ?></summary>
          <p><label for="mhl-teacher-note"><?php echo esc_html__('Soukromá poznámka','miloslavhub-live'); ?></label>
          <textarea id="mhl-teacher-note" name="mhl_teacher_note" class="widefat" rows="4" maxlength="4000"><?php echo esc_textarea((string)get_post_meta($post->ID,'_mhl_teacher_note',true)); ?></textarea></p>
          <p class="description"><?php echo esc_html__('Studenti ani projekce tuto poznámku neuvidí. Přenos obsahu pro kolegu ji obsahuje; před sdílením ji zkontrolujte.','miloslavhub-live'); ?></p>
        </details>
        <?php
    }
    public static function question_rag_box(WP_Post $post): void {
        $p=MHL_Core::rag_policy($post->ID); ?>
        <p><strong>Dostupnost pro AI/RAG <?php echo self::help(MHL_I18n::text('Výchozí je neindexovat. Samotný plugin otázky do RAG neposílá; toto pole je politika pro váš indexer.')); ?></strong></p>
        <select name="mhl_rag_policy" class="widefat"><option value="exclude" <?php selected($p,'exclude'); ?>><?php echo esc_html_mhl_literal('Neindexovat (doporučeno)'); ?></option><option value="private" <?php selected($p,'private'); ?>><?php echo esc_html_mhl_literal('Pouze soukromá KB'); ?></option><option value="public_after_lecture" <?php selected($p,'public_after_lecture'); ?>><?php echo esc_html_mhl_literal('Veřejná KB až po přednášce'); ?></option></select>
        <p class="description"><?php echo esc_html_mhl_literal('Frontend hlasování a REST API mají navíc '); ?><code>noindex</code><?php echo esc_html_mhl_literal('. Vlastní RAG indexer musí tuto politiku explicitně respektovat.'); ?></p><?php
    }
    public static function lecture_meta_box(WP_Post $post): void {
        wp_nonce_field('mhl_save_lecture','mhl_lecture_nonce');
        $subject_id=MHL_Core::get_lecture_subject_id($post->ID); $selected=MHL_Core::get_lecture_question_ids($post->ID); $gam=(bool)get_post_meta($post->ID,'_mhl_gamification',true); $scope=get_post_meta($post->ID,'_mhl_score_scope',true)?:'subject';
        $materials=(string)get_post_meta($post->ID,'_mhl_materials_url',true); $assistant=(string)get_post_meta($post->ID,'_mhl_assistant_url',true); $show_live=(bool)get_post_meta($post->ID,'_mhl_show_live_results',true); $auto_qr=MHL_Core::lecture_auto_qr($post->ID);
        $subjects=get_posts(array('post_type'=>'mhl_subject','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC')); $questions=get_posts(array('post_type'=>'mhl_question','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
        // Keep already assigned drafts visible so reviewing an imported lecture cannot
        // silently discard its unpublished subject or questions on ordinary save.
        $known_subjects=array_column($subjects,'ID'); $known_questions=array_column($questions,'ID');
        if($subject_id && !in_array($subject_id,$known_subjects,true)){
            $assigned=get_post($subject_id);
            if($assigned && $assigned->post_type==='mhl_subject' && in_array($assigned->post_status,array('draft','pending','private','future'),true) && current_user_can('edit_post',$subject_id)){$subjects[]=$assigned;}
        }
        $has_drafts=false;
        foreach($selected as $qid){
            if(in_array($qid,$known_questions,true)){continue;}
            $assigned=get_post($qid);
            if($assigned && $assigned->post_type==='mhl_question' && in_array($assigned->post_status,array('draft','pending','private','future'),true) && current_user_can('edit_post',$qid)){$questions[]=$assigned;$has_drafts=true;}
        }
        if($has_drafts){echo '<p class="description">'.esc_html__('Některé přiřazené otázky ještě nejsou zveřejněné. Před výukou zveřejněte otázky a předmět, potom přednášku. Uložení konceptu zachová jejich přiřazení.','miloslavhub-live').'</p>';}
        ?>
        <p><label><strong><?php echo esc_html_mhl_literal('Předmět * '); ?><?php echo self::help(MHL_I18n::text('Předmět představuje celý semestr nebo kurz. Každá přednáška patří právě jednomu předmětu a body se mezi různými předměty nepřenášejí.')); ?></strong> <select name="mhl_subject_id" required><option value=""><?php echo esc_html_mhl_literal('— vyberte —'); ?></option><?php foreach($subjects as $s): ?><option value="<?php echo esc_attr($s->ID); ?>" <?php selected($subject_id,$s->ID); ?>><?php echo esc_html($s->post_title); ?></option><?php endforeach; ?></select></label></p>
        <p><label><input type="checkbox" name="mhl_gamification" value="1" <?php checked($gam); ?>> <strong><?php echo esc_html_mhl_literal('Soutěžní režim '); ?><?php echo self::help(MHL_I18n::text('Zapne přezdívky, body, měření rychlosti a výsledkové pořadí. Přezdívka se pamatuje pro celý předmět a používá se i v dalších přednáškách.')); ?></strong><?php echo esc_html_mhl_literal(' – přezdívky, body a pořadí; výchozí součet je v rámci celého předmětu.'); ?></label></p>
        <p><label><strong><?php echo esc_html_mhl_literal('Celkové pořadí '); ?><?php echo self::help(MHL_I18n::text('Určuje, zda se bude zobrazovat dlouhodobý součet bodů. Doporučené nastavení je v rámci celého předmětu; jednotlivé přednášky i nadále začínají čistě, ale student vidí i celkový stav předmětu.')); ?></strong> <select name="mhl_score_scope"><option value="subject" <?php selected($scope,'subject'); ?>><?php echo esc_html_mhl_literal('V rámci celého předmětu'); ?></option><option value="lecture" <?php selected($scope,'lecture'); ?>><?php echo esc_html_mhl_literal('Pouze v rámci této přednášky'); ?></option><option value="none" <?php selected($scope,'none'); ?>><?php echo esc_html_mhl_literal('Bez celkového pořadí'); ?></option></select></label></p>
        <p><label><input type="checkbox" name="mhl_show_live_results" value="1" <?php checked($show_live); ?>> <strong><?php echo esc_html_mhl_literal('Průběžné výsledky '); ?><?php echo self::help(MHL_I18n::text('Když je zapnuto, graf se mění už během hlasování. Doporučené výchozí nastavení je vypnuto, aby průběžné výsledky neovlivňovaly další studenty.')); ?></strong><?php echo esc_html_mhl_literal(' – zobrazovat rozložení odpovědí už během hlasování.'); ?></label></p>
        <p><label><strong><?php echo esc_html_mhl_literal('Materiály / stránka přednášky '); ?><?php echo self::help(MHL_I18n::text('Volitelná adresa na miloslavhub.cz, kterou student uvidí po hlasování. Vhodné pro prezentaci, podklady, odkazy nebo shrnutí přednášky.')); ?></strong><br><input type="url" class="widefat" name="mhl_materials_url" value="<?php echo esc_attr($materials); ?>" placeholder="https://miloslavhub.cz/..."></label></p>
        <p><label><strong><?php echo esc_html_mhl_literal('AI asistent '); ?><?php echo self::help(MHL_I18n::text('Volitelná adresa vašeho AI asistenta. Po hlasování může student pokračovat na tuto stránku a pracovat s obsahem vašeho webu.')); ?></strong><br><input type="url" class="widefat" name="mhl_assistant_url" value="<?php echo esc_attr($assistant); ?>" placeholder="https://miloslavhub.cz/..."></label></p>
        <h4><?php echo esc_html_mhl_literal('Otázky v této přednášce '); ?><?php echo self::help(MHL_I18n::text('Otázky můžete přetahovat mezi seznamy Dostupné a Vybrané. Přetažením uvnitř Vybraných změníte pořadí. Stejné operace lze provést tlačítky Přidat, Odebrat a šipkami. Změna pořadí ani vložení nové otázky nemění trvalé QR adresy ostatních otázek.')); ?></h4>
        <?php if(!$questions): ?><p><?php echo esc_html_mhl_literal('Nejprve vytvořte otázky.'); ?></p><?php else:
            $by_id=array(); foreach($questions as $q){$by_id[(int)$q->ID]=$q;}
            $selected_questions=array(); foreach($selected as $qid){if(isset($by_id[(int)$qid])){$selected_questions[]=$by_id[(int)$qid]; unset($by_id[(int)$qid]);}}
            $available_questions=array_values($by_id);
        ?>
        <div class="mhl-question-picker" data-mhl-question-picker>
          <div class="mhl-question-section">
            <div class="mhl-question-section-head"><strong><?php echo esc_html_mhl_literal('Vybrané otázky – pořadí v přednášce'); ?></strong><span class="description"><?php echo esc_html_mhl_literal('Přetáhněte za ☰, použijte šipky nebo tlačítko Odebrat.'); ?></span></div>
            <div class="mhl-selected-questions" data-mhl-selected-list>
              <?php foreach($selected_questions as $q): $terms=wp_get_post_terms($q->ID,'mhl_question_category',array('fields'=>'names')); ?>
                <div class="mhl-question-item is-selected" data-question-id="<?php echo esc_attr($q->ID); ?>" data-title="<?php echo esc_attr(mb_strtolower($q->post_title)); ?>">
                  <span class="mhl-drag-handle" draggable="true" role="button" tabindex="0" aria-label="<?php echo esc_html_mhl_literal('Přetáhnout otázku'); ?>" title="<?php echo esc_html_mhl_literal('Přetáhnout mezi seznamy nebo změnit pořadí'); ?>">☰</span>
                  <span class="mhl-order-badge" aria-label="<?php echo esc_html_mhl_literal('Pořadí'); ?>"></span>
                  <label class="mhl-question-label"><input class="mhl-question-toggle" type="checkbox" name="mhl_question_ids[]" value="<?php echo esc_attr($q->ID); ?>" checked> <strong><?php echo esc_html($q->post_title); ?></strong><?php if($terms): ?><span class="description"> — <?php echo esc_html(implode(', ',$terms)); ?></span><?php endif; ?></label>
                  <input class="mhl-order" type="hidden" name="mhl_question_order[<?php echo esc_attr($q->ID); ?>]" value="">
                  <div class="mhl-order-actions"><button type="button" class="button-link mhl-move-up" aria-label="<?php echo esc_html_mhl_literal('Posunout otázku nahoru'); ?>" title="<?php echo esc_attr(MHL_I18n::text('Posunout nahoru')); ?>">↑</button><button type="button" class="button-link mhl-move-down" aria-label="<?php echo esc_html_mhl_literal('Posunout otázku dolů'); ?>" title="<?php echo esc_html_mhl_literal('Posunout dolů'); ?>">↓</button><button type="button" class="button-link-delete mhl-remove-question"><?php echo esc_html_mhl_literal('Odebrat'); ?></button><button type="button" class="button mhl-add-question"><?php echo esc_html_mhl_literal('Přidat'); ?></button></div>
                </div>
              <?php endforeach; ?>
              <p class="mhl-selected-empty"><?php echo esc_html_mhl_literal('Zatím není vybrána žádná otázka.'); ?></p>
            </div>
          </div>
          <div class="mhl-question-section">
            <div class="mhl-question-section-head"><strong><?php echo esc_html_mhl_literal('Dostupné otázky z banky'); ?></strong><span class="description"><?php echo esc_html_mhl_literal('Přetáhněte do Vybraných nebo použijte tlačítko Přidat.'); ?></span></div>
            <div class="mhl-available-questions" data-mhl-available-list>
              <?php foreach($available_questions as $q): $terms=wp_get_post_terms($q->ID,'mhl_question_category',array('fields'=>'names')); ?>
                <div class="mhl-question-item" data-question-id="<?php echo esc_attr($q->ID); ?>" data-title="<?php echo esc_attr(mb_strtolower($q->post_title)); ?>">
                  <span class="mhl-drag-handle" draggable="true" role="button" tabindex="0" aria-label="<?php echo esc_html_mhl_literal('Přidat otázku přetažením'); ?>" title="<?php echo esc_html_mhl_literal('Přetáhnout do vybraných otázek'); ?>">☰</span>
                  <span class="mhl-order-badge" aria-hidden="true"></span>
                  <label class="mhl-question-label"><input class="mhl-question-toggle" type="checkbox" name="mhl_question_ids[]" value="<?php echo esc_attr($q->ID); ?>"> <strong><?php echo esc_html($q->post_title); ?></strong><?php if($terms): ?><span class="description"> — <?php echo esc_html(implode(', ',$terms)); ?></span><?php endif; ?></label>
                  <input class="mhl-order" type="hidden" name="mhl_question_order[<?php echo esc_attr($q->ID); ?>]" value="">
                  <div class="mhl-order-actions"><button type="button" class="button-link mhl-move-up" aria-label="<?php echo esc_html_mhl_literal('Posunout otázku nahoru'); ?>" title="<?php echo esc_attr(MHL_I18n::text('Posunout nahoru')); ?>">↑</button><button type="button" class="button-link mhl-move-down" aria-label="<?php echo esc_html_mhl_literal('Posunout otázku dolů'); ?>" title="<?php echo esc_html_mhl_literal('Posunout dolů'); ?>">↓</button><button type="button" class="button-link-delete mhl-remove-question"><?php echo esc_html_mhl_literal('Odebrat'); ?></button><button type="button" class="button mhl-add-question"><?php echo esc_html_mhl_literal('Přidat'); ?></button></div>
                </div>
              <?php endforeach; ?>
              <p class="mhl-available-empty"><?php echo esc_html_mhl_literal('Všechny otázky z banky jsou už v této přednášce.'); ?></p>
            </div>
          </div>
        </div><?php endif;
    }
    public static function lecture_qr_box(WP_Post $post): void {
        if($post->post_status==='auto-draft'||!$post->post_name){echo MHL_I18n::html('<p>Po prvním uložení přednášky se zde zobrazí trvalé adresy a QR kódy.</p>');return;}
        echo MHL_I18n::html('<div class="notice-inline mhl-permanent-qr-note"><strong>Trvalé QR kódy</strong> ').self::help(MHL_I18n::text('Adresa QR se po prvním uložení uzamkne. Můžete později přejmenovat předmět, přednášku i otázku a QR vložený v prezentaci bude dál fungovat. Přestane fungovat pouze po smazání příslušné přednášky nebo otázky, případně po destruktivním ručním zásahu do databáze.')).MHL_I18n::html('<br><span class="description">QR můžete bezpečně ponechat v prezentaci i pro další semestry a roky.</span></div>');
        $ids=MHL_Core::get_lecture_question_ids($post->ID); if(!$ids){echo MHL_I18n::html('<p>Nejprve přiřaďte otázky a přednášku uložte.</p>');return;}
        foreach($ids as $qid){$url=MHL_Core::get_vote_url($post->ID,$qid,'live');echo '<div class="mhl-mini-qr"><strong>'.esc_html(get_the_title($qid)).'</strong><div class="mhl-qr" data-qr="'.esc_attr($url).'" data-size="160"></div><code>'.esc_html($url).'</code><p><button type="button" class="button mhl-copy" data-copy="'.esc_attr($url).MHL_I18n::html('">Kopírovat URL</button></p>');if(MHL_Core::question_async_enabled($qid)){$a=MHL_Core::get_vote_url($post->ID,$qid,'async');echo MHL_I18n::html('<hr><strong>Dlouhodobá anketa</strong><br><code>').esc_html($a).'</code><p><button type="button" class="button mhl-copy" data-copy="'.esc_attr($a).MHL_I18n::html('">Kopírovat dlouhodobý odkaz</button></p>');}echo '</div>';}
    }



    public static function save_subject(int $id): void {
        if(!isset($_POST['mhl_subject_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhl_subject_nonce'])),'mhl_save_subject')||!current_user_can('edit_post',$id)||wp_is_post_revision($id)){return;}
        $raw=isset($_POST['mhl_teachers'])?(array)$_POST['mhl_teachers']:array();
        $teachers=array();
        foreach($raw as $row){
            if(!is_array($row)){continue;}
            $name=sanitize_text_field(wp_unslash((string)($row['name']??'')));
            if($name===''){continue;}
            $teachers[]=array(
                'name'=>$name,
                'role'=>sanitize_text_field(wp_unslash((string)($row['role']??''))),
                'profile_url'=>esc_url_raw(wp_unslash((string)($row['profile_url']??''))),
                'email'=>sanitize_email(wp_unslash((string)($row['email']??''))),
                'show_public'=>!empty($row['show_public'])
            );
        }
        update_post_meta($id,'_mhl_teachers',$teachers);
        $brand=sanitize_key((string)($_POST['mhl_brand_template']??'miloslavhub'));
        if(!in_array($brand,array('miloslavhub','fes_upce','neutral','custom'),true)){$brand='miloslavhub';}
        update_post_meta($id,'_mhl_brand_template',$brand);
        $template=sanitize_key((string)($_POST['mhl_subject_template']??'standard'));
        if(!in_array($template,array('standard','competition','minimal'),true)){$template='standard';}
        update_post_meta($id,'_mhl_subject_template',$template);
        update_post_meta($id,'_mhl_custom_brand_name',sanitize_text_field(wp_unslash((string)($_POST['mhl_custom_brand_name']??''))));
        update_post_meta($id,'_mhl_custom_brand_subtitle',sanitize_text_field(wp_unslash((string)($_POST['mhl_custom_brand_subtitle']??''))));
        update_post_meta($id,'_mhl_custom_brand_url',esc_url_raw(wp_unslash((string)($_POST['mhl_custom_brand_url']??''))));
        update_post_meta($id,'_mhl_custom_logo_url',esc_url_raw(wp_unslash((string)($_POST['mhl_custom_logo_url']??''))));
        $pc=sanitize_hex_color((string)($_POST['mhl_custom_primary_color']??''));
        $ac=sanitize_hex_color((string)($_POST['mhl_custom_accent_color']??''));
        update_post_meta($id,'_mhl_custom_primary_color',$pc?:'#172033');
        update_post_meta($id,'_mhl_custom_accent_color',$ac?:'#1f5fae');
        update_post_meta($id,'_mhl_subject_short_title',sanitize_text_field(wp_unslash((string)($_POST['mhl_subject_short_title']??''))));
        update_post_meta($id,'_mhl_subject_code',sanitize_text_field(wp_unslash((string)($_POST['mhl_subject_code']??''))));
        update_post_meta($id,'_mhl_subject_period',sanitize_text_field(wp_unslash((string)($_POST['mhl_subject_period']??''))));
        update_post_meta($id,'_mhl_competition_title',sanitize_text_field(wp_unslash((string)($_POST['mhl_competition_title']??''))));
        update_post_meta($id,'_mhl_subject_extra_info',sanitize_textarea_field(wp_unslash((string)($_POST['mhl_subject_extra_info']??''))));
        update_post_meta($id,'_mhl_hof_enabled',!empty($_POST['mhl_hof_enabled'])?1:0);
        $hof_visibility=sanitize_key((string)($_POST['mhl_hof_visibility']??'public'));if(!in_array($hof_visibility,array('public','participants'),true)){$hof_visibility='public';}update_post_meta($id,'_mhl_hof_visibility',$hof_visibility);
        update_post_meta($id,'_mhl_hof_limit',min(100,max(3,absint($_POST['mhl_hof_limit']??10))));
        update_post_meta($id,'_mhl_hof_period_days',min(3650,max(1,absint($_POST['mhl_hof_period_days']??365))));
        update_post_meta($id,'_mhl_hof_title',sanitize_text_field(wp_unslash((string)($_POST['mhl_hof_title']??''))));
        $nonopt=sanitize_key((string)($_POST['mhl_hof_nonopt_mode']??'hidden'));if(!in_array($nonopt,array('hidden','anonymous'),true)){$nonopt='hidden';}update_post_meta($id,'_mhl_hof_nonopt_mode',$nonopt);
        if(!empty($_POST['mhl_projection_regenerate'])){update_post_meta($id,'_mhl_projection_token',wp_generate_password(32,false,false));}elseif(!(string)get_post_meta($id,'_mhl_projection_token',true)){update_post_meta($id,'_mhl_projection_token',wp_generate_password(32,false,false));}
    }

    public static function save_question(int $id): void {
        if(!isset($_POST['mhl_question_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhl_question_nonce'])),'mhl_save_question')||!current_user_can('edit_post',$id)||wp_is_post_revision($id)){return;}
        // Partial/older editors must not erase private notes or publish feedback.
        foreach(array('correct_answer_explanation','teacher_note') as $field){
            $key='mhl_'.$field;
            if(isset($_POST[$key]) && is_string($_POST[$key])){
                $text=sanitize_textarea_field(wp_unslash($_POST[$key]));
                $text=substr($text,0,4000);
                while($text!=='' && !preg_match('//u',$text)){$text=substr($text,0,-1);}
                update_post_meta($id,'_mhl_'.$field,wp_slash($text));
            }
        }
        if(isset($_POST['mhl_explanation_mode'])){
            $explanation_mode=is_string($_POST['mhl_explanation_mode'])?sanitize_key($_POST['mhl_explanation_mode']):'';
            update_post_meta($id,'_mhl_explanation_mode',in_array($explanation_mode,array('teacher_only','show_after_close','hidden'),true)?$explanation_mode:'teacher_only');
        }
        $posted=isset($_POST['mhl_options'])?(array)$_POST['mhl_options']:array();
        $correct_original=isset($_POST['mhl_correct_index'])?(int)$_POST['mhl_correct_index']:-1;
        $opts=array(); $correct_new=null;
        foreach($posted as $i=>$v){$clean=sanitize_text_field(wp_unslash($v));if($clean===''){continue;} $new_index=count($opts);$opts[]=$clean;if($correct_original===(int)$i){$correct_new=$new_index;}}
        update_post_meta($id,'_mhl_options',$opts);
        if($correct_new!==null){update_post_meta($id,'_mhl_correct_index',$correct_new);update_post_meta($id,'_mhl_mode','quiz');}else{delete_post_meta($id,'_mhl_correct_index');update_post_meta($id,'_mhl_mode','poll');}
        $mult=isset($_POST['mhl_multiplier'])?(float)$_POST['mhl_multiplier']:1;if(!in_array($mult,array(1.0,1.5,2.0),true)){$mult=1;}update_post_meta($id,'_mhl_multiplier',$mult);
        update_post_meta($id,'_mhl_speed_window',min(120,max(5,absint($_POST['mhl_speed_window']??20))));
        // Legacy participation-points metadata is preserved for portable round trips, never used for new votes.
        $time=sanitize_text_field(wp_unslash($_POST['mhl_time_limit']??'auto')); if($time==='auto'){delete_post_meta($id,'_mhl_time_limit');}else{$tv=(int)$time;update_post_meta($id,'_mhl_time_limit',$tv<=0?0:min(600,max(5,$tv)));}
        $rag=sanitize_key($_POST['mhl_rag_policy']??'exclude');if(!in_array($rag,array('exclude','private','public_after_lecture'),true)){$rag='exclude';}update_post_meta($id,'_mhl_rag_policy',$rag);
        $is_poll=$correct_new===null;update_post_meta($id,'_mhl_async_enabled',($is_poll&&!empty($_POST['mhl_async_enabled']))?1:0);update_post_meta($id,'_mhl_async_show_results',($is_poll&&!empty($_POST['mhl_async_show_results']))?1:0);$aend=sanitize_text_field(wp_unslash((string)($_POST['mhl_async_end']??'')));if($is_poll&&$aend!==''){update_post_meta($id,'_mhl_async_end',$aend);}else{delete_post_meta($id,'_mhl_async_end');}
    }
    public static function save_lecture(int $id): void {
        if(!isset($_POST['mhl_lecture_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mhl_lecture_nonce'])),'mhl_save_lecture')||!current_user_can('edit_post',$id)||wp_is_post_revision($id)){return;}
        update_post_meta($id,'_mhl_subject_id',absint($_POST['mhl_subject_id']??0)); $ids=isset($_POST['mhl_question_ids'])?array_values(array_unique(array_filter(array_map('absint',(array)$_POST['mhl_question_ids'])))):array(); $orders=isset($_POST['mhl_question_order'])?(array)$_POST['mhl_question_order']:array(); usort($ids,static fn($a,$b)=>(absint($orders[$a]??99)<=>absint($orders[$b]??99))); update_post_meta($id,'_mhl_question_ids',$ids);
        update_post_meta($id,'_mhl_gamification',isset($_POST['mhl_gamification'])?1:0); $scope=sanitize_key($_POST['mhl_score_scope']??'subject');update_post_meta($id,'_mhl_score_scope',in_array($scope,array('subject','lecture','none'),true)?$scope:'subject'); update_post_meta($id,'_mhl_show_live_results',isset($_POST['mhl_show_live_results'])?1:0);
        update_post_meta($id,'_mhl_materials_url',esc_url_raw(wp_unslash($_POST['mhl_materials_url']??''))); update_post_meta($id,'_mhl_assistant_url',esc_url_raw(wp_unslash($_POST['mhl_assistant_url']??'')));
    }

    public static function dashboard(): void {
        if (!current_user_can('mhl_access')) { return; }
        echo '<div class="wrap mhl-wrap"><h1>Hlasuj! by MiloslavHub</h1><div class="mhl-cards">';
        foreach (array('mhl_subject'=>'předmětů','mhl_lecture'=>'přednášek','mhl_question'=>'otázek') as $type=>$label) {
            echo '<div class="mhl-card"><strong>'.esc_html(count(MHL_Access::visible_ids($type))).'</strong><span>'.esc_html(MHL_I18n::text($label)).'</span></div>';
        }
        echo '</div><p>';
        if (current_user_can('mhl_create_content')) {
            foreach (array('mhl_subject'=>'Přidat předmět','mhl_question'=>'Přidat otázku','mhl_lecture'=>'Přidat přednášku') as $type=>$label) {
                echo '<a class="button" href="'.esc_url(admin_url('post-new.php?post_type='.$type)).'">'.esc_html(MHL_I18n::text($label)).'</a> ';
            }
        }
        echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=mhl-live-test')).'">'.esc_html(MHL_I18n::text('Testovací laboratoř')).'</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=mhl-workspaces')).'">'.esc_html__('Organizace a sdílení','miloslavhub-live').'</a>';
        if (MHL_Access::operator()) { echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=mhl-live-demo')).'">'.esc_html(MHL_I18n::text('Ukázkové demo')).'</a>'; }
        echo '</p></div>';
    }

    private static function list_runs(string $mode): void {
        if (!MHL_DB::schema_ready()) { echo MHL_I18n::html('<div class="notice notice-error inline"><p>Nejprve dokončete databázovou migraci.</p></div>');return; }
        $db=MHL_DB::db();$runs=MHL_DB::table('runs');$sessions=MHL_DB::table('sessions');$run_id=absint($_GET['run_id']??0);
        $run=$run_id?$db->get_row($db->prepare("SELECT * FROM {$runs} WHERE id=%d AND mode=%s",$run_id,$mode)):null;
        if ($run) { MHL_Access::require_run((int)$run->id,'results'); }
        if (!$run) {
            $lectures=get_posts(array('post_type'=>'mhl_lecture','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));
            if (!$lectures) { echo MHL_I18n::html('<p>Nejprve vytvořte předmět, otázku a přednášku.</p>');return; }
            echo MHL_I18n::html('<table class="widefat striped"><thead><tr><th>Předmět</th><th>Přednáška</th><th>Otázek</th><th></th></tr></thead><tbody>');
            foreach ($lectures as $lecture) {
                echo '<tr><td>'.esc_html(MHL_Core::get_subject_title($lecture->ID)).'</td><td><strong>'.esc_html($lecture->post_title).'</strong></td><td>'.esc_html(count(MHL_Core::get_lecture_question_ids($lecture->ID))).'</td><td>';
                if (MHL_Access::can((int)$lecture->ID,'control')) {
                    $url=wp_nonce_url(admin_url('admin-post.php?action=mhl_start_run&mode='.$mode.'&lecture_id='.$lecture->ID),'mhl_start_run_'.$mode.'_'.$lecture->ID);
                    echo '<a class="button button-primary" href="'.esc_url($url).'">'.esc_html(MHL_I18n::text($mode==='test'?MHL_I18n::text('Spustit TEST'):MHL_I18n::text('Spustit přednášku'))).'</a>';
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';return;
        }
        $control=MHL_Access::can_run((int)$run->id,'control');
        if ($mode==='test') { echo MHL_I18n::html('<div class="notice notice-warning inline"><p><strong>TESTOVACÍ REŽIM</strong> – oddělené relace a výsledky.</p></div>'); }
        echo '<h2>'.esc_html($run->title).'</h2><p>'.esc_html(MHL_I18n::text('Stav')).': '.esc_html($run->status).'</p>';
        $rows=$db->get_results($db->prepare("SELECT s.* FROM {$sessions} s INNER JOIN (SELECT question_id,MAX(id) max_id FROM {$sessions} WHERE run_id=%d GROUP BY question_id) x ON x.max_id=s.id ORDER BY s.id ASC",(int)$run->id));
        if ($control) { echo MHL_I18n::html('<p class="description"><strong>Nápověda k tlačítkům:</strong> Spustit hlasování otevře otázku všem studentům, Student ukazuje mobilní pohled, Projekce promítací pohled a Zopakovat otázku slouží k opakovanému testu jedné otázky.</p>'); }
        echo '<div class="mhl-live-list">';
        foreach ($rows as $session) {
            $qid=(int)$session->question_id;$vote=MHL_Core::get_vote_url((int)$run->lecture_id,$qid,$mode);$results=MHL_Core::get_results_url((int)$run->lecture_id,$qid,$mode);
            $labels=array('waiting'=>MHL_I18n::text('Připravená'),'joining'=>MHL_I18n::text('Připojování'),'open'=>MHL_I18n::text('Probíhá'),'closed'=>MHL_I18n::text('Ukončená'),'skipped'=>MHL_I18n::text('Přeskočená'));
            echo '<section class="mhl-live-item"><div><h2>'.esc_html(get_the_title($qid)).'</h2><p>'.esc_html(MHL_I18n::text('Stav')).': <strong>'.esc_html(MHL_I18n::text($labels[$session->status]??$session->status)).'</strong><br><code>'.esc_html($vote).'</code></p></div><div class="mhl-live-actions">';
            if ($control) {
                if (in_array($session->status,array('waiting','joining'),true)) {
                    $url=wp_nonce_url(admin_url('admin-post.php?action=mhl_open_session&session_id='.$session->id.'&run_id='.$run->id),'mhl_session_'.$session->id);
                    echo '<a class="button button-primary" href="'.esc_url($url).'">'.esc_html(MHL_I18n::text('Spustit hlasování')).'</a>';
                } elseif ($session->status==='open') {
                    $url=wp_nonce_url(admin_url('admin-post.php?action=mhl_close_session&session_id='.$session->id.'&run_id='.$run->id),'mhl_session_'.$session->id);
                    echo '<a class="button" href="'.esc_url($url).'">'.esc_html(MHL_I18n::text('Ukončit')).'</a>';
                    if ($mode==='test') {
                        $url=wp_nonce_url(admin_url('admin-post.php?action=mhl_simulate_votes&session_id='.$session->id.'&run_id='.$run->id),'mhl_simulate_'.$session->id);
                        echo '<a class="button" href="'.esc_url($url).'">'.esc_html(MHL_I18n::text('Simulovat 5 studentů')).'</a>';
                    }
                }
            }
            echo '<a class="button" href="'.esc_url($vote).'" target="_blank" rel="noopener">'.esc_html(MHL_I18n::text('Student ↗')).'</a><a class="button" href="'.esc_url($results).'" target="_blank" rel="noopener">'.esc_html(MHL_I18n::text('Projekce ↗')).'</a>';
            if ($control) {
                $url=wp_nonce_url(admin_url('admin-post.php?action=mhl_reset_session&session_id='.$session->id.'&run_id='.$run->id),'mhl_session_'.$session->id);
                echo '<a class="button" href="'.esc_url($url).'">'.esc_html(MHL_I18n::text('Zopakovat otázku')).'</a>';
            }
            echo '</div></section>';
        }
        echo '</div>';
        if ($control) {
            $url=wp_nonce_url(admin_url('admin-post.php?action=mhl_finish_run&run_id='.$run->id),'mhl_finish_run_'.$run->id);
            echo '<p><a class="button" href="'.esc_url($url).'">'.esc_html(MHL_I18n::text($mode==='test'?MHL_I18n::text('Ukončit test'):MHL_I18n::text('Ukončit přednášku'))).'</a></p>';
        }
    }

    public static function live_control(): void { if(!current_user_can('mhl_access')){return;} echo MHL_I18n::html('<div class="wrap mhl-wrap"><h1>Živé ovládání</h1>');self::list_runs('live');echo '</div>'; }
    public static function test_lab(): void { if(!current_user_can('mhl_access')){return;}echo MHL_I18n::html('<div class="wrap mhl-wrap"><h1>Testovací laboratoř</h1><p>Vyzkoušíte zde stejný studentský frontend i projekci, ale data jsou označena jako <strong>TEST</strong> a nejsou v ostrém archivu.</p>');self::list_runs('test');$clear=wp_nonce_url(admin_url('admin-post.php?action=mhl_clear_test_data'),'mhl_clear_test_data');echo '<hr><p><a class="button" href="'.esc_url($clear).MHL_I18n::html('" onclick="return confirm(\'Opravdu smazat všechna testovací hlasování?\')">Vymazat všechna testovací data <span class="mhl-button-note" title="Odstraní testovací relace a hlasy. Ostrá data tím nejsou dotčena.">?</span></a></p></div>'); }

    public static function long_polls(): void {
        if(!current_user_can('mhl_access')){return;}
        echo MHL_I18n::html('<div class="wrap mhl-wrap"><h1>Dlouhodobé ankety</h1><p>Samostatné ankety bez odpočtu. Běží v režimu <code>async</code>, takže neblokují živou přednášku ani soutěžní pořadí.</p>');
        if(!MHL_DB::schema_ready()){echo MHL_I18n::html('<div class="notice notice-error inline"><p>Nejprve dokončete databázovou migraci.</p></div></div>');return;}
        $lectures=get_posts(array('post_type'=>'mhl_lecture','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));$found=false;
        echo MHL_I18n::html('<table class="widefat striped"><thead><tr><th>Předmět</th><th>Přednáška</th><th>Anketa</th><th>Stav</th><th>Odkazy / akce</th></tr></thead><tbody>');
        foreach($lectures as $l){foreach(MHL_Core::get_lecture_question_ids((int)$l->ID) as $qid){if(!MHL_Core::question_async_enabled($qid)){continue;}$found=true;$run=MHL_Core::get_active_run_for_question((int)$l->ID,$qid,'async');$session=$run?MHL_Core::get_current_session((int)$l->ID,$qid,(int)$run->id,'async',true):null;$state=$session?(string)$session->status:MHL_I18n::text('neaktivní');$vote=MHL_Core::get_vote_url((int)$l->ID,$qid,'async');$res=MHL_Core::get_results_url((int)$l->ID,$qid,'async');echo '<tr><td>'.esc_html(MHL_Core::get_subject_title((int)$l->ID)).'</td><td>'.esc_html($l->post_title).'</td><td><strong>'.esc_html(get_the_title($qid)).'</strong></td><td>'.esc_html($state).'</td><td><a class="button" target="_blank" rel="noopener" href="'.esc_url($vote).'">Respondent ↗</a> <a class="button" target="_blank" rel="noopener" href="'.esc_url($res).MHL_I18n::html('">Výsledky ↗</a> ');if(MHL_Access::can((int)$l->ID,'control')){if($session&&$session->status==='open'){$u=wp_nonce_url(admin_url('admin-post.php?action=mhl_close_session&session_id='.(int)$session->id.'&run_id='.(int)$run->id),'mhl_session_'.(int)$session->id);echo '<a class="button" href="'.esc_url($u).MHL_I18n::html('">Uzavřít</a>');}elseif($session&&$session->status==='closed'){$u=wp_nonce_url(admin_url('admin-post.php?action=mhl_reset_session&session_id='.(int)$session->id.'&run_id='.(int)$run->id),'mhl_session_'.(int)$session->id);echo '<a class="button" href="'.esc_url($u).MHL_I18n::html('">Nová relace</a>');}}echo '</td></tr>';}}
        if(!$found){echo MHL_I18n::html('<tr><td colspan="5">Zatím není žádná otázka označena jako dlouhodobá otevřená anketa.</td></tr>');}
        echo MHL_I18n::html('</tbody></table><p class="description">První otevření respondentského odkazu anketu automaticky aktivuje. Pokud není nastaven datum ukončení, zůstane otevřená, dokud ji zde ručně neuzavřete.</p></div>');
    }

    private static function demo_ids(): array {
        $subject=get_page_by_path('mhl-live-demo-subject',OBJECT,'mhl_subject');
        $lecture=get_page_by_path('mhl-live-demo-lecture',OBJECT,'mhl_lecture');
        return array('subject_id'=>$subject?(int)$subject->ID:0,'lecture_id'=>$lecture?(int)$lecture->ID:0);
    }
    private static function upsert_demo_post(string $post_type,string $slug,string $title): int {
        $existing=get_page_by_path($slug,OBJECT,$post_type);
        if($existing){
            if($existing->post_status!=='publish'){
                wp_update_post(array('ID'=>(int)$existing->ID,'post_status'=>'publish'));
            }
            return (int)$existing->ID;
        }
        return (int)wp_insert_post(array('post_type'=>$post_type,'post_status'=>'publish','post_title'=>$title,'post_name'=>$slug));
    }
    /**
     * Idempotentně vytvoří/obnoví pevný demo obsah. Nemění ostrá hlasovací data.
     * Vrací ID předmětu, přednášky a tří demo otázek.
     */
    public static function ensure_demo_content(): array {
        $sid=self::upsert_demo_post('mhl_subject','mhl-live-demo-subject','Ukázkové hlasování MiloslavHub Live');
        $u=wp_get_current_user();
        update_post_meta($sid,'_mhl_brand_template','miloslavhub');
        update_post_meta($sid,'_mhl_subject_template','competition');
        update_post_meta($sid,'_mhl_subject_short_title','MiloslavHub Live');
        update_post_meta($sid,'_mhl_subject_code','DEMO');
        update_post_meta($sid,'_mhl_subject_period','Ukázka pro vyučující');
        update_post_meta($sid,'_mhl_competition_title','Vyzkoušejte si interaktivní hlasování');
        update_post_meta($sid,'_mhl_subject_extra_info','2 kvízy se správnou odpovědí + 1 anonymní anketa. Demo se po 15 minutách automaticky ukončí.');
        if(!get_post_meta($sid,'_mhl_teachers',true)){
            update_post_meta($sid,'_mhl_teachers',array(array('name'=>$u->display_name?:'Vyučující','role'=>'Vyučující','profile_url'=>'','email'=>'','show_public'=>true)));
        }

        $q1=self::upsert_demo_post('mhl_question','demo-bezpecne-heslo','Které heslo je nejbezpečnější?');
        update_post_meta($q1,'_mhl_options',array('12345678','Pardubice2026','xP7!qM2#vL','heslo123'));
        update_post_meta($q1,'_mhl_correct_index',2);
        update_post_meta($q1,'_mhl_multiplier',1);
        update_post_meta($q1,'_mhl_speed_window',20);
        update_post_meta($q1,'_mhl_time_limit',30);
        update_post_meta($q1,'_mhl_rag_policy','exclude');

        $q2=self::upsert_demo_post('mhl_question','demo-vpn','Co znamená zkratka VPN?');
        update_post_meta($q2,'_mhl_options',array('Virtual Private Network','Verified Public Network','Virtual Protected Node','Variable Private Network'));
        update_post_meta($q2,'_mhl_correct_index',0);
        update_post_meta($q2,'_mhl_multiplier',1);
        update_post_meta($q2,'_mhl_speed_window',20);
        update_post_meta($q2,'_mhl_time_limit',30);
        update_post_meta($q2,'_mhl_rag_policy','exclude');

        $q3=self::upsert_demo_post('mhl_question','demo-verejna-wifi','Používáte někdy veřejnou Wi-Fi?');
        update_post_meta($q3,'_mhl_options',array('Ano, často','Občas','Výjimečně','Nikdy'));
        delete_post_meta($q3,'_mhl_correct_index');
        update_post_meta($q3,'_mhl_poll_points',0);
        update_post_meta($q3,'_mhl_time_limit',45);
        update_post_meta($q3,'_mhl_rag_policy','exclude');

        $lid=self::upsert_demo_post('mhl_lecture','mhl-live-demo-lecture','Ukázková interaktivní přednáška');
        update_post_meta($lid,'_mhl_subject_id',$sid);
        update_post_meta($lid,'_mhl_question_ids',array($q1,$q2,$q3));
        update_post_meta($lid,'_mhl_gamification',1);
        update_post_meta($lid,'_mhl_auto_qr',1);
        update_post_meta($lid,'_mhl_score_scope','subject');
        update_post_meta($lid,'_mhl_show_live_results',0);
        update_post_meta($lid,'_mhl_demo',1);
        update_post_meta($lid,'_mhl_demo_minutes',15);

        return array('subject_id'=>$sid,'lecture_id'=>$lid,'question_ids'=>array($q1,$q2,$q3));
    }
    /**
     * Po aktualizaci na 0.7.2 vytvoří ukázkové demo automaticky při prvním vstupu
     * administrátora do WordPressu. Není nutné mačkat tlačítko Vytvořit demo.
     */
    public static function ensure_demo_available(): void {
        if(!current_user_can('manage_options') || !post_type_exists('mhl_subject')){return;}
        $existing=self::demo_ids();
        // I po starší aktualizaci mohlo být uloženo, že demo bylo vytvořeno, ale obsah chyběl.
        // Proto při každém admin vstupu ověříme, že demo přednáška existuje; pokud ne, opravíme ji.
        if(get_option('mhl_demo_autocreated_072')==='yes' && !empty($existing['lecture_id'])){return;}
        $ids=self::ensure_demo_content();
        if(!empty($ids['lecture_id'])){
            update_option('mhl_demo_autocreated_072','yes',false);
            set_transient('mhl_demo_autocreated_notice',1,120);
        }
    }
    public static function create_demo(): void {
        if(!current_user_can('manage_options')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}
        check_admin_referer('mhl_create_demo');
        self::ensure_demo_content();
        update_option('mhl_demo_autocreated_072','yes',false);
        wp_safe_redirect(admin_url('admin.php?page=mhl-live-demo&created=1')); exit;
    }
    public static function reset_demo(): void {
        if(!current_user_can('manage_options')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}
        check_admin_referer('mhl_reset_demo');
        $ids=self::ensure_demo_content();
        $lid=(int)($ids['lecture_id']??0);
        if($lid && MHL_DB::schema_ready()){
            $db=MHL_DB::db();$runs=MHL_DB::table('runs');$sessions=MHL_DB::table('sessions');$votes=MHL_DB::table('votes');
            $run_ids=$db->get_col($db->prepare("SELECT id FROM {$runs} WHERE lecture_id=%d AND mode='test'",$lid));
            foreach($run_ids?:array() as $rid){if(!MHL_Core::delete_test_run((int)$rid)){wp_die(MHL_I18n::text('Test se nepodařilo odstranit.'));}}
        }
        wp_safe_redirect(admin_url('admin.php?page=mhl-live-demo&reset=1')); exit;
    }
    public static function demo_page(): void {
        if(!current_user_can('manage_options')){return;}
        // Opraví i částečně smazané demo, takže QR v ukázkové prezentaci zůstávají vždy platné.
        $ids=self::ensure_demo_content();
        $lid=(int)($ids['lecture_id']??0);
        echo MHL_I18n::html('<div class="wrap mhl-wrap"><h1>Ukázkové demo</h1><p>Tři stálé testovací QR kódy a připravená PowerPointová prezentace pro vyučující, kteří si chtějí systém vyzkoušet sami nebo s kolegou/studentem. Demo je vytvořeno automaticky, používá pouze režim TEST a po 15 minutách aktivní relace automaticky skončí.</p>');
        if(get_transient('mhl_demo_autocreated_notice')){delete_transient('mhl_demo_autocreated_notice');echo MHL_I18n::html('<div class="notice notice-success inline"><p><strong>Ukázkové demo bylo automaticky vytvořeno.</strong> QR kódy v prezentaci jsou nyní připravené k použití.</p></div>');}
        $qids=MHL_Core::get_lecture_question_ids($lid); echo '<div class="mhl-demo-grid">';
        foreach($qids as $qid){$url=MHL_Core::get_vote_url($lid,$qid,'test');echo '<div class="mhl-demo-card"><strong>'.esc_html(get_the_title($qid)).'</strong><div class="mhl-qr" data-qr="'.esc_attr($url).'" data-size="190"></div><p><a href="'.esc_url($url).MHL_I18n::html('" target="_blank">Otevřít test ↗</a></p></div>');}
        echo '</div>';
        $ppt=MHL_URL.'assets/miloslavhub-live-demo.pptx'; $reset=wp_nonce_url(admin_url('admin-post.php?action=mhl_reset_demo'),'mhl_reset_demo');
        echo '<p><a class="button button-primary" href="'.esc_url($ppt).MHL_I18n::html('" download>Stáhnout ukázkovou prezentaci (.pptx)</a> <a class="button" href="').esc_url($reset).MHL_I18n::html('">Resetovat demo do výchozího stavu</a></p><p class="description">QR kódy v prezentaci jsou trvalé. Pokud demo obsah chybí, plugin jej automaticky znovu vytvoří. Po skončení 15minutového testu se při dalším načtení založí nová čistá testovací relace.</p></div>');
    }

    public static function archive(): void { if(!current_user_can('mhl_access')){return;}echo MHL_I18n::html('<div class="wrap mhl-wrap"><h1>Archiv výsledků</h1>');if(!MHL_DB::schema_ready()){echo MHL_I18n::html('<p>Nejprve dokončete migraci databáze.</p></div>');return;}$db=MHL_DB::db();$runs=MHL_DB::table('runs');$votes=MHL_DB::table('votes');$rows=$db->get_results("SELECT r.*,COUNT(v.id) vote_count FROM {$runs} r LEFT JOIN {$votes} v ON v.run_id=r.id WHERE r.mode='live' AND ".MHL_Access::run_sql('r')." GROUP BY r.id ORDER BY r.id DESC LIMIT 100");echo MHL_I18n::html('<table class="widefat striped"><thead><tr><th>Relace</th><th>Předmět</th><th>Přednáška</th><th>Začátek</th><th>Stav</th><th>Hlasů</th></tr></thead><tbody>');foreach($rows?:array() as $r){$ex=wp_nonce_url(admin_url('admin-post.php?action=mhl_export_run&run_id='.(int)$r->id),'mhl_export_run_'.(int)$r->id);echo '<tr><td>#'.esc_html($r->id).'</td><td>'.esc_html($r->subject_id?get_the_title((int)$r->subject_id):'').'</td><td>'.esc_html($r->title).'</td><td>'.esc_html(get_date_from_gmt($r->started_at,'j. n. Y H:i')).'</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->vote_count).' &nbsp; <a href="'.esc_url($ex).'">CSV</a></td></tr>';}echo '</tbody></table></div>'; }

    public static function settings_page(): void {
        if(!current_user_can('manage_options')){return;}
        $s=MHL_Core::settings(); $st=MHL_DB::status();
        echo MHL_I18n::html('<div class="wrap mhl-wrap"><h1>Nastavení MiloslavHub Live</h1>');
        echo '<div class="notice '.($st['ok']?'notice-success':'notice-error').MHL_I18n::html(' inline"><p><strong>Samostatná databáze:</strong> ').esc_html($st['message']).'</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mhl_save_settings">';
        wp_nonce_field('mhl_save_settings');
        echo '<table class="form-table">';
        echo MHL_I18n::html('<tr><th>Adresa hlasování ').self::help(MHL_I18n::text('Veřejná URL frontendu, kterou studenti otevírají přes QR kódy. Typicky samostatná HTTPS subdoména, například https://hlasuj.example.cz.')).'</th><td><input class="regular-text" type="url" name="frontend_url" value="'.esc_attr($s['frontend_url']).'"></td></tr>';
        echo MHL_I18n::html('<tr><th>Povolený frontend (CORS) ').self::help(MHL_I18n::text('Bezpečnostní nastavení: určuje, z jaké veřejné adresy smí frontend volat REST API WordPressu. Obvykle je shodné s adresou hlasování.')).'</th><td><input class="regular-text" type="url" name="allowed_origin" value="'.esc_attr($s['allowed_origin']).'"></td></tr>';
        echo MHL_I18n::html('<tr><th>Hlavní web ').self::help(MHL_I18n::text('Volitelný veřejný web značky nebo organizace. Zobrazí se jen tam, kde to zvolená brand šablona dovoluje.')).'</th><td><input class="regular-text" type="url" name="main_site_url" value="'.esc_attr($s['main_site_url']).'"></td></tr>';
        echo MHL_I18n::html('<tr><th>Výchozí AI asistent ').self::help(MHL_I18n::text('Volitelné. Pokud žádného AI asistenta nemáte, ponechte pole prázdné. Tlačítko AI asistent se ve veřejném rozhraní zobrazí jen při skutečně vyplněné URL.')).'</th><td><input class="regular-text" type="url" name="assistant_url" value="'.esc_attr($s['assistant_url']).MHL_I18n::html('" placeholder="Ponechte prázdné, pokud AI asistenta nepoužíváte"><p class="description">Prázdné pole = žádný odkaz na AI asistenta.</p></td></tr>');
        echo MHL_I18n::html('<tr><th>Výchozí čas kvízu ').self::help(MHL_I18n::text('Časový limit pro kvízové otázky nastavené na Automaticky podle typu. Po vypršení se hlasování automaticky uzavře.')).'</th><td><input type="number" min="5" max="600" name="quiz_seconds" value="'.esc_attr($s['quiz_seconds']).MHL_I18n::html('"> sekund <p class="description">Doporučeno 30 s.</p></td></tr>');
        echo MHL_I18n::html('<tr><th>Výchozí čas ankety ').self::help(MHL_I18n::text('Časový limit pro anketní otázky nastavené na Automaticky podle typu. Hodnota 0 znamená bez časového limitu.')).'</th><td><input type="number" min="0" max="600" name="poll_seconds" value="'.esc_attr($s['poll_seconds']).'"> sekund <p class="description">0 = bez limitu.</p></td></tr>';
        echo MHL_I18n::html('<tr><th>Max. délka aktivní přednášky ').self::help(MHL_I18n::text('Bezpečnostní limit celé relace přednášky. Pokud přednášku ručně neukončíte, po této době se relace automaticky uzavře.')).'</th><td><input type="number" min="30" max="1440" name="run_minutes" value="'.esc_attr($s['run_minutes']).'"> minut</td></tr>';
        echo MHL_I18n::html('<tr><th>Základní body ').self::help(MHL_I18n::text('Počet bodů za správnou kvízovou odpověď před započtením rychlostního bonusu a násobitele otázky.')).'</th><td><input type="number" min="0" max="5000" name="base_points" value="'.esc_attr($s['base_points']).'"></td></tr>';
        echo MHL_I18n::html('<tr><th>Max. rychlostní bonus ').self::help(MHL_I18n::text('Maximální počet bodů navíc za rychlou správnou odpověď. Bonus postupně klesá během rychlostního okna otázky.')).'</th><td><input type="number" min="0" max="5000" name="speed_points" value="'.esc_attr($s['speed_points']).'"></td></tr>';
        echo MHL_I18n::html('<tr><th>Rezervace přezdívky ').self::help(MHL_I18n::text('Přezdívka je jedinečná pouze v rámci jednoho předmětu. Stejný student může mít stejnou nebo jinou přezdívku v jiném předmětu. Po této době bez aktivity se přezdívka uvolní pro jiného studenta.')).'</th><td><input type="number" min="1" max="3650" name="nickname_reservation_days" value="'.esc_attr($s['nickname_reservation_days']??365).MHL_I18n::html('"> dní <p class="description">Doporučeno 365 dní. Lhůta se obnoví při každém použití přezdívky v předmětu.</p></td></tr>');
        echo MHL_I18n::html('<tr><th colspan="2"><h2>Soukromí a retence</h2><p class="description">Nastavení minimalizace dat. Nejde o náhradu právního posouzení konkrétního nasazení.</p></th></tr>');
        echo MHL_I18n::html('<tr><th>Správce / provozovatel ').self::help(MHL_I18n::text('Název osoby nebo organizace uváděný na veřejné stránce Ochrana soukromí.')).'</th><td><input class="regular-text" type="text" name="privacy_controller_name" value="'.esc_attr($s['privacy_controller_name']??'Miloslav Hub').'"></td></tr>';
        echo MHL_I18n::html('<tr><th>Kontakt pro soukromí ').self::help(MHL_I18n::text('Kontaktní e-mail pro dotazy k osobním údajům a soukromí.')).'</th><td><input class="regular-text" type="email" name="privacy_contact_email" value="'.esc_attr($s['privacy_contact_email']??'').'"></td></tr>';
        echo MHL_I18n::html('<tr><th>Retence ostrých hlasů ').self::help(MHL_I18n::text('Po této době se staré ostré hlasy automaticky smažou. Úklid probíhá nejvýše jednou denně při běžném provozu webu.')).'</th><td><input type="number" min="1" max="3650" name="privacy_live_retention_days" value="'.esc_attr($s['privacy_live_retention_days']??365).MHL_I18n::html('"> dní</td></tr>');
        echo MHL_I18n::html('<tr><th>Retence TEST dat ').self::help(MHL_I18n::text('Testovací hlasy a související identifikátory mají mít kratší životnost než ostrá data.')).'</th><td><input type="number" min="1" max="365" name="privacy_test_retention_days" value="'.esc_attr($s['privacy_test_retention_days']??30).MHL_I18n::html('"> dní</td></tr>');
        echo MHL_I18n::html('<tr><th>Retence technických příchodů ').self::help(MHL_I18n::text('Krátkodobý záznam příchodu zařízení k otázce slouží pro přehled připojení. Hlasování spouští vyučující.')).'</th><td><input type="number" min="1" max="90" name="privacy_join_retention_days" value="'.esc_attr($s['privacy_join_retention_days']??7).MHL_I18n::html('"> dní</td></tr>');
        echo '</table>';
        submit_button(MHL_I18n::text('Uložit nastavení'));
        echo '</form>';
        echo MHL_I18n::html('<div class="notice notice-info inline"><p><strong>RAG policy:</strong> Výchozí stav všech nových otázek je „Neindexovat“. Kromě pluginu nastavte v indexeru explicitní blokaci <code>hlasuj.miloslavhub.cz/*</code>, <code>/wp-json/mhl/*</code> a post typů <code>mhl_question</code>, <code>mhl_lecture</code>, <code>mhl_subject</code>.</p></div></div>');
    }

    public static function start_run(): void {
        if(!current_user_can('mhl_access')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}
        $lecture_id=absint($_GET['lecture_id']??0); $mode=sanitize_key((string)($_GET['mode']??'live'))==='test'?'test':'live';
        check_admin_referer('mhl_start_run_'.$mode.'_'.$lecture_id);
        if(!$lecture_id || get_post_type($lecture_id)!=='mhl_lecture'){wp_die(MHL_I18n::text('Přednáška nebyla nalezena.'));}
        if(!MHL_Access::can($lecture_id,'control')){wp_die(esc_html__('Nemáte oprávnění.', 'miloslavhub-live'),'',array('response'=>403));}
        $subject_id=MHL_Core::get_lecture_subject_id($lecture_id);
        $subject_run=$subject_id?MHL_Core::get_active_run_for_subject($subject_id,$mode):null;
        if($subject_run){MHL_Access::require_run((int)$subject_run->id);}if($subject_run && !MHL_Core::close_run((int)$subject_run->id)){wp_die(MHL_I18n::text('Předchozí běh se nepodařilo uzavřít.'));}
        $run=MHL_Core::create_run($lecture_id,$mode,get_current_user_id(),true);
        if(!$run){wp_die(MHL_I18n::text('Přednášku se nepodařilo spustit. Zkontrolujte, že má přiřazený předmět a alespoň jednu otázku.'));}
        wp_safe_redirect(admin_url('admin.php?page='.($mode==='test'?'mhl-live-test':'mhl-live-control').'&run_id='.(int)$run->id));exit;
    }

    private static function session_guard(): object { if(!current_user_can('mhl_access')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}$sid=absint($_GET['session_id']??0);check_admin_referer('mhl_session_'.$sid);$db=MHL_DB::db();$t=MHL_DB::table('sessions');$s=$db->get_row($db->prepare("SELECT * FROM {$t} WHERE id=%d",$sid));if(!$s){wp_die(MHL_I18n::text('Relace nebyla nalezena.'));}MHL_Access::require_run((int)$s->run_id);return $s; }
    private static function redirect_run(int $run_id): void {$db=MHL_DB::db();$runs=MHL_DB::table('runs');$mode=(string)$db->get_var($db->prepare("SELECT mode FROM {$runs} WHERE id=%d",$run_id));$page=$mode==='test'?'mhl-live-test':($mode==='async'?'mhl-live-async':'mhl-live-control');wp_safe_redirect(admin_url('admin.php?page='.$page.'&run_id='.$run_id));exit;}
    private static function session_action(string $action): void {
        $s=self::session_guard();
        $result=MHL_Core::change_session((int)$s->id,$action);
        if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
        self::redirect_run((int)$s->run_id);
    }
    public static function open_session(): void {self::session_action('open');}
    public static function close_session(): void {self::session_action('close');}
    public static function reset_session(): void {self::session_action('reset');}
    public static function finish_run(): void {if(!current_user_can('mhl_access')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}$rid=absint($_GET['run_id']??0);check_admin_referer('mhl_finish_run_'.$rid);MHL_Access::require_run($rid);$db=MHL_DB::db();$runs=MHL_DB::table('runs');$mode=(string)$db->get_var($db->prepare("SELECT mode FROM {$runs} WHERE id=%d",$rid));if(!MHL_Core::close_run($rid)){wp_die(MHL_I18n::text('Běh se nepodařilo uzavřít.'));}$page=$mode==='test'?'mhl-live-test':($mode==='async'?'mhl-live-async':'mhl-live-control');wp_safe_redirect(admin_url('admin.php?page='.$page));exit;}
    public static function simulate_votes(): void {
        if(!current_user_can('mhl_access')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}
        $sid=absint($_GET['session_id']??0);$rid=absint($_GET['run_id']??0);
        check_admin_referer('mhl_simulate_'.$sid);
        $result=self::simulate_session_votes($rid,$sid);
        if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
        self::redirect_run($rid);
    }

    /** Internal administration operation; HTTP callers still require the action nonce above. */
    public static function simulate_session_votes(int $run_id,int $session_id): bool|WP_Error {
        if(!current_user_can('mhl_access') || !MHL_Access::can_run($run_id)){
            return new WP_Error('mhl_forbidden',MHL_I18n::text('Nemáte oprávnění.'),array('status'=>403));
        }
        return MHL_DB::with_run_lock($run_id,static function($db,$run) use($session_id,$run_id){
            $sessions=MHL_DB::table('sessions');$votes=MHL_DB::table('votes');
            $session=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d AND run_id=%d AND mode='test'",$session_id,$run_id));
            if($run->mode!=='test'||$run->status!=='active'||!$session||$session->status!=='open'
                ||($session->reset_at&&strtotime($session->reset_at.' UTC')<=time())
                ||($run->expires_at&&strtotime($run->expires_at.' UTC')<=time())){
                return new WP_Error('mhl_test_closed',MHL_I18n::text('Testovací otázka není otevřená.'));
            }
            $question_id=(int)$session->question_id;$options=MHL_Core::get_question_options($question_id);
            $quiz=MHL_Core::question_type($question_id)==='quiz';$correct_index=MHL_Core::question_correct_index($question_id);
            $settings=MHL_Core::settings();
            for($i=1;$i<=5;$i++){
                $option=$options?($i-1)%count($options):0;$ms=900+$i*850;
                $correct=$quiz?($option===$correct_index?1:0):null;$points=0;
                if($correct){
                    $mult=(float)(get_post_meta($question_id,'_mhl_multiplier',true)?:1);
                    $window=max(5,(int)(get_post_meta($question_id,'_mhl_speed_window',true)?:$settings['speed_window']));
                    $factor=max(0.0,1.0-(($ms/1000)/$window));
                    $points=(int)round(((int)$settings['base_points']+((int)$settings['speed_points']*$factor))*$mult);
                }
                $ok=$db->insert($votes,array('session_id'=>$session_id,'run_id'=>$run_id,'question_id'=>$question_id,'mode'=>'test',
                    'participant_key'=>hash('sha256','test-'.$run_id.'-'.$session_id.'-'.$i.'-'.wp_generate_uuid4()),
                    'nickname'=>$quiz?MHL_I18n::text('Test').$i:'','option_index'=>$option,'is_correct'=>$correct,'response_ms'=>$ms,'points'=>$points,
                    'created_at'=>MHL_Core::now_mysql()),array('%d','%d','%d','%s','%s','%s','%d','%d','%d','%d','%s'));
                if($ok===false){return new WP_Error('mhl_test_failed',MHL_I18n::text('Testovací hlasy se nepodařilo uložit.'));}
            }
            return true;
        });
    }
    public static function clear_test_data(): void {
        if(!current_user_can('mhl_access')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}
        check_admin_referer('mhl_clear_test_data');
        $db=MHL_DB::db();
        $ids=$db->get_col("SELECT id FROM ".MHL_DB::table('runs')." WHERE mode='test' AND ".MHL_Access::run_sql('','control'));
        foreach($ids?:array() as $rid){if(!MHL_Core::delete_test_run((int)$rid)){wp_die(MHL_I18n::text('Test se nepodařilo odstranit.'));}}
        if(MHL_Access::operator()){$db->query("DELETE FROM ".MHL_DB::table('participants')." WHERE mode='test'");}
        wp_safe_redirect(admin_url('admin.php?page=mhl-live-test'));exit;
    }
    public static function csv_text(string $value): string {
        // Spreadsheet applications may ignore leading whitespace/control characters.
        return preg_match('/^[\x00-\x20\x{00A0}]*[=+@\-]/u', $value) || preg_match('/^[\t\r\n]/', $value)
            ? "'" . $value : $value;
    }
    public static function export_run(): void {if(!current_user_can('mhl_access')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}$rid=absint($_GET['run_id']??0);check_admin_referer('mhl_export_run_'.$rid);MHL_Access::require_run($rid,'export');$db=MHL_DB::db();$votes=MHL_DB::table('votes');$rows=$db->get_results($db->prepare("SELECT * FROM {$votes} WHERE run_id=%d ORDER BY id ASC",$rid));nocache_headers();header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="miloslavhub-live-run-'.$rid.'.csv"');$o=fopen('php://output','w');fwrite($o,"\xEF\xBB\xBF");fputcsv($o,array('run_id','session_id','question','nickname','option','is_correct','response_ms','points','created_at'),';','"','');foreach($rows?:array() as $r){fputcsv($o,array((int)$r->run_id,(int)$r->session_id,self::csv_text(get_the_title((int)$r->question_id)),self::csv_text((string)$r->nickname),chr(65+(int)$r->option_index),is_null($r->is_correct)?'':(int)$r->is_correct,(int)$r->response_ms,(int)$r->points,$r->created_at),';','"','');}fclose($o);exit;}
    public static function save_settings(): void {
        if(!current_user_can('manage_options')){wp_die(MHL_I18n::text('Nemáte oprávnění.'));}
        check_admin_referer('mhl_save_settings');
        $f=esc_url_raw(untrailingslashit(wp_unslash($_POST['frontend_url']??'')));
        $o=esc_url_raw(untrailingslashit(wp_unslash($_POST['allowed_origin']??'')));
        $prev=MHL_Core::settings();
        update_option('mhl_settings',array(
            'frontend_url'=>$f?:'https://hlasuj.miloslavhub.cz','allowed_origin'=>$o?:($f?:'https://hlasuj.miloslavhub.cz'),
            'main_site_url'=>esc_url_raw(wp_unslash($_POST['main_site_url']??'https://miloslavhub.cz')),'assistant_url'=>esc_url_raw(wp_unslash($_POST['assistant_url']??'')),
            'question_seconds'=>(int)$prev['question_seconds'],'quiz_seconds'=>min(600,max(5,absint($_POST['quiz_seconds']??30))),'poll_seconds'=>min(600,max(0,absint($_POST['poll_seconds']??0))),
            'run_minutes'=>min(1440,max(30,absint($_POST['run_minutes']??120))),'base_points'=>min(5000,max(0,absint($_POST['base_points']??800))),'speed_points'=>min(5000,max(0,absint($_POST['speed_points']??200))),'speed_window'=>(int)$prev['speed_window'],
            'nickname_reservation_days'=>min(3650,max(1,absint($_POST['nickname_reservation_days']??365))),
            'join_min_wait_seconds'=>(int)$prev['join_min_wait_seconds'],'join_quiet_seconds'=>(int)$prev['join_quiet_seconds'],'join_max_wait_seconds'=>(int)$prev['join_max_wait_seconds'],
            'privacy_controller_name'=>sanitize_text_field(wp_unslash($_POST['privacy_controller_name']??'Miloslav Hub')),
            'privacy_contact_email'=>sanitize_email(wp_unslash($_POST['privacy_contact_email']??'')),
            'privacy_live_retention_days'=>min(3650,max(1,absint($_POST['privacy_live_retention_days']??365))),
            'privacy_test_retention_days'=>min(365,max(1,absint($_POST['privacy_test_retention_days']??30))),
            'privacy_join_retention_days'=>min(90,max(1,absint($_POST['privacy_join_retention_days']??7))),
            'privacy_policy_version'=>(string)($prev['privacy_policy_version']??'2026-09-24')
        ),'',false);
        wp_safe_redirect(admin_url('admin.php?page=mhl-live-settings&updated=1'));exit;
    }


    public static function subject_columns(array $c): array {$c['mhl_template']=MHL_I18n::text('Brand / rozvržení');$c['mhl_teachers']=MHL_I18n::text('Vyučující');return $c;}
    public static function subject_column_content(string $col,int $id): void {
        if($col==='mhl_template'){ $t=MHL_Core::subject_template($id); $brands=array('miloslavhub'=>'Miloslav Hub','fes_upce'=>'FES UPa','neutral'=>MHL_I18n::text('Neutrální'),'custom'=>MHL_I18n::text('Vlastní')); $layouts=array('standard'=>MHL_I18n::text('Standardní'),'competition'=>MHL_I18n::text('Soutěžní'),'minimal'=>MHL_I18n::text('Minimalistické')); echo esc_html(($brands[$t['brand_template']]??$t['brand_template']).' / '.($layouts[$t['template']]??$t['template'])); return; }
        if($col!=='mhl_teachers'){return;}
        $teachers=MHL_Core::subject_teachers($id,false);
        if(!$teachers){echo '<span class="description">—</span>';return;}
        $parts=array();
        foreach($teachers as $t){
            $label=esc_html((string)$t['name']);
            if(!empty($t['role'])){$label.=' <span class="description">('.esc_html((string)$t['role']).')</span>';}
            if(empty($t['show_public'])){$label.=MHL_I18n::html(' <span title="Nezobrazuje se studentům">🔒</span>');}
            $parts[]=$label;
        }
        echo implode('<br>',$parts);
    }

    public static function question_columns(array $c): array {$c['mhl_type']=MHL_I18n::text('Typ');$c['mhl_rag']='RAG';return $c;}
    public static function question_column_content(string $col,int $id): void {if($col==='mhl_type'){echo esc_html(MHL_Core::question_type($id)==='quiz'?MHL_I18n::text('Kvíz'):MHL_I18n::text('Anketa'));}elseif($col==='mhl_rag'){echo esc_html(MHL_Core::rag_policy($id));}}
    public static function lecture_columns(array $c): array {$c['mhl_subject']=MHL_I18n::text('Předmět');$c['mhl_questions']=MHL_I18n::text('Otázek');$c['mhl_gamification']=MHL_I18n::text('Soutěž');return $c;}
    public static function lecture_column_content(string $col,int $id): void {if($col==='mhl_subject'){echo esc_html(MHL_Core::get_subject_title($id));}elseif($col==='mhl_questions'){echo esc_html(count(MHL_Core::get_lecture_question_ids($id)));}elseif($col==='mhl_gamification'){echo get_post_meta($id,'_mhl_gamification',true)?MHL_I18n::text('Ano'):MHL_I18n::text('Ne');}}
}
