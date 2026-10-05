<?php
if (!defined('ABSPATH')) { exit; }

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
        add_menu_page('MiloslavHub Live','Živé hlasování','manage_options','mhl-live',array(__CLASS__,'dashboard'),'dashicons-chart-bar',58);
        add_submenu_page('mhl-live','Přehled','Přehled','manage_options','mhl-live',array(__CLASS__,'dashboard'));
        add_submenu_page('mhl-live','Živé ovládání','Živé ovládání','manage_options','mhl-live-control',array(__CLASS__,'live_control'));
        add_submenu_page('mhl-live','Testovací laboratoř','Testovací laboratoř','manage_options','mhl-live-test',array(__CLASS__,'test_lab'));
        add_submenu_page('mhl-live','Ukázkové demo','Ukázkové demo','manage_options','mhl-live-demo',array(__CLASS__,'demo_page'));
        add_submenu_page('mhl-live','Dlouhodobé ankety','Dlouhodobé ankety','manage_options','mhl-live-async',array(__CLASS__,'long_polls'));
        add_submenu_page('mhl-live','Archiv výsledků','Archiv výsledků','manage_options','mhl-live-archive',array(__CLASS__,'archive'));
        add_submenu_page('mhl-live','Nastavení','Nastavení','manage_options','mhl-live-settings',array(__CLASS__,'settings_page'));
    }
    public static function assets(string $hook): void {
        $screen=get_current_screen(); $pt=$screen->post_type??'';
        if(strpos($hook,'mhl')===false && !in_array($pt,array('mhl_question','mhl_lecture','mhl_subject'),true)){return;}
        wp_enqueue_style('mhl-admin',MHL_URL.'assets/admin.css',array(),MHL_VERSION);
        wp_enqueue_script('mhl-admin',MHL_URL.'assets/admin.js',array(),MHL_VERSION,true);
    }
    public static function meta_boxes(): void {
        add_meta_box('mhl-subject-template','Brand a rozvržení předmětu',array(__CLASS__,'subject_template_box'),'mhl_subject','normal','high');
        add_meta_box('mhl-subject-teachers','Vyučující',array(__CLASS__,'subject_teachers_box'),'mhl_subject','normal','high');
        add_meta_box('mhl-subject-hall','Síň slávy a soukromí',array(__CLASS__,'subject_hall_box'),'mhl_subject','normal','default');
        add_meta_box('mhl-subject-projection','Projekce bez WordPress účtu',array(__CLASS__,'subject_projection_box'),'mhl_subject','side','default');
        add_meta_box('mhl-question-config','Nastavení otázky',array(__CLASS__,'question_meta_box'),'mhl_question','normal','high');
        add_meta_box('mhl-question-rag','AI / RAG',array(__CLASS__,'question_rag_box'),'mhl_question','side','default');
        add_meta_box('mhl-lecture-config','Předmět, otázky a interakce',array(__CLASS__,'lecture_meta_box'),'mhl_lecture','normal','high');
        add_meta_box('mhl-lecture-qr','QR kódy do prezentace',array(__CLASS__,'lecture_qr_box'),'mhl_lecture','side','default');
    }
    private static function help(string $text): string { return '<span class="mhl-help" tabindex="0" role="button" aria-label="Nápověda" data-help="'.esc_attr($text).'">?</span>'; }

    public static function subject_template_box(WP_Post $post): void {
        wp_nonce_field('mhl_save_subject','mhl_subject_nonce');
        $t=MHL_Core::subject_template((int)$post->ID);
        ?>
        <p class="description"><strong>Hlasuj! by MiloslavHub <?php echo esc_html(MHL_VERSION); ?>.</strong> Každý předmět má dvě nezávislé vrstvy: <strong>brand</strong> (kdo se prezentuje) a <strong>rozvržení</strong> (standardní / soutěžní / minimalistické). Brand se automaticky použije na mobilu i projekci.</p>
        <div class="mhl-brand-presets">
          <label class="mhl-brand-card"><input type="radio" name="mhl_brand_template" value="miloslavhub" <?php checked($t['brand_template'],'miloslavhub'); ?>><span><strong>Miloslav Hub</strong><small>Osobní přednášky; výrazně propaguje Miloslav Hub a miloslavhub.cz.</small></span></label>
          <label class="mhl-brand-card mhl-brand-fes"><input type="radio" name="mhl_brand_template" value="fes_upce" <?php checked($t['brand_template'],'fes_upce'); ?>><span><strong>FES UPa</strong><small>Fakultní zelená, Univerzita Pardubice; bez osobní propagace Miloslav Hub.</small></span></label>
          <label class="mhl-brand-card mhl-brand-neutral"><input type="radio" name="mhl_brand_template" value="neutral" <?php checked($t['brand_template'],'neutral'); ?>><span><strong>Neutrální</strong><small>Pro použití mimo FES UPa i mimo osobní brand.</small></span></label>
          <label class="mhl-brand-card"><input type="radio" name="mhl_brand_template" value="custom" <?php checked($t['brand_template'],'custom'); ?>><span><strong>Vlastní</strong><small>Vlastní název, web, logo a barvy.</small></span></label>
        </div>
        <div class="mhl-field-grid mhl-subject-template-grid">
          <label><strong>Rozvržení <?php echo self::help('Standardní je univerzální. Soutěžní zvýrazní název soutěže a bodování. Minimalistické ponechá jen nejdůležitější informace.'); ?></strong>
            <select name="mhl_subject_template">
              <option value="standard" <?php selected($t['template'],'standard'); ?>>Standardní</option>
              <option value="competition" <?php selected($t['template'],'competition'); ?>>Soutěžní</option>
              <option value="minimal" <?php selected($t['template'],'minimal'); ?>>Minimalistické</option>
            </select>
          </label>
          <label><strong>Krátký název <?php echo self::help('Zkrácený název předmětu pro malé obrazovky a kompaktní záhlaví, např. OOP.'); ?></strong><input type="text" name="mhl_subject_short_title" value="<?php echo esc_attr($t['short_title']); ?>" placeholder="Např. OOP"></label>
          <label><strong>Kód předmětu <?php echo self::help('Volitelný oficiální nebo interní kód předmětu, např. FBIK.'); ?></strong><input type="text" name="mhl_subject_code" value="<?php echo esc_attr($t['code']); ?>" placeholder="Např. FBIK"></label>
          <label><strong>Semestr / skupina <?php echo self::help('Volitelná informace pro rozlišení běhů předmětu, např. ZS 2026/27 nebo skupina A.'); ?></strong><input type="text" name="mhl_subject_period" value="<?php echo esc_attr($t['period']); ?>" placeholder="Např. ZS 2026/27"></label>
          <label class="mhl-template-wide"><strong>Název soutěže <?php echo self::help('Používá se hlavně u soutěžního rozvržení jako výrazný název kvízu nebo soutěže.'); ?></strong><input type="text" name="mhl_competition_title" value="<?php echo esc_attr($t['competition_title']); ?>" placeholder="Např. OOP Challenge"></label>
          <label class="mhl-template-wide"><strong>Doplňující informace <?php echo self::help('Volitelný delší text zobrazovaný podle zvolené šablony, např. pravidla soutěže, způsob bodování nebo organizační poznámka.'); ?></strong><textarea name="mhl_subject_extra_info" rows="4" placeholder="Např. Body se počítají jen v rámci aktuální přednášky."><?php echo esc_textarea($t['extra_info']); ?></textarea></label>
        </div>
        <details class="mhl-custom-brand" <?php echo $t['brand_template']==='custom'?'open':''; ?>>
          <summary><strong>Nastavení vlastní značky</strong> – používá se pouze u šablony Vlastní</summary>
          <div class="mhl-field-grid mhl-custom-brand-grid">
            <label><strong>Název značky <?php echo self::help('Název organizace, projektu nebo akce zobrazovaný místo přednastavené značky.'); ?></strong><input type="text" name="mhl_custom_brand_name" value="<?php echo esc_attr($t['custom_brand_name']); ?>" placeholder="Název organizace / akce"></label>
            <label><strong>Podtitulek <?php echo self::help('Krátký doprovodný text pod názvem značky, např. Živé hlasování.'); ?></strong><input type="text" name="mhl_custom_brand_subtitle" value="<?php echo esc_attr($t['custom_brand_subtitle']); ?>" placeholder="Živé hlasování"></label>
            <label><strong>Web <?php echo self::help('Cílová veřejná adresa značky; používá se jako odkaz ve veřejném rozhraní.'); ?></strong><input type="url" name="mhl_custom_brand_url" value="<?php echo esc_attr($t['custom_brand_url']); ?>" placeholder="https://example.cz"></label>
            <label><strong>Logo (URL) <?php echo self::help('Volitelná veřejná HTTPS adresa loga. Pokud ji nevyplníte, použije se pouze textová identita.'); ?></strong><input type="url" name="mhl_custom_logo_url" value="<?php echo esc_attr($t['custom_logo_url']); ?>" placeholder="https://…/logo.svg"></label>
            <label><strong>Hlavní barva <?php echo self::help('Primární barva vlastní značky pro prvky rozhraní.'); ?></strong><input type="color" name="mhl_custom_primary_color" value="<?php echo esc_attr($t['custom_primary_color']); ?>"></label>
            <label><strong>Akcentní barva <?php echo self::help('Doplňková barva pro zvýraznění výsledků a interaktivních prvků.'); ?></strong><input type="color" name="mhl_custom_accent_color" value="<?php echo esc_attr($t['custom_accent_color']); ?>"></label>
          </div>
        </details>
        <p class="description">Šablona <strong>FES UPa</strong> používá fakultní zelenou a textovou identitu FES/UPa; plugin neobsahuje licencované univerzitní písmo ani oficiální logo. Lze je případně doplnit později z autorizovaných podkladů.</p>
        <?php
    }

    public static function subject_hall_box(WP_Post $post): void {
        $h=MHL_Core::subject_hall_of_fame((int)$post->ID);
        echo '<p class="description">Síň slávy používá výsledné pořadí v předmětu. Student se rozhoduje až ve chvíli, kdy se skutečně dostane mezi nastavený počet nejlepších. Výchozí stav nezveřejňuje přezdívku.</p>';
        echo '<div class="mhl-field-grid">';
        echo '<label><strong>Síň slávy '.self::help('Povolí stránku s agregovaným pořadím tohoto předmětu. Studentovi v Top N se po výsledku nabídne tlačítko pro zveřejnění přezdívky.').'</strong><span><input type="checkbox" name="mhl_hof_enabled" value="1" '.checked($h['enabled'],true,false).'> Povolit</span></label>';
        echo '<label><strong>Viditelnost '.self::help('Veřejná = stránku může otevřít kdokoli s odkazem. Pouze účastníci = žebříček uvidí jen prohlížeč s aktivní rezervací přezdívky.').'</strong><select name="mhl_hof_visibility"><option value="public" '.selected($h['visibility']??'public','public',false).'>Veřejná</option><option value="participants" '.selected($h['visibility']??'public','participants',false).'>Pouze účastníci předmětu</option></select></label>';
        echo '<label><strong>Počet míst '.self::help('Např. 10 znamená, že nabídku k přidání do Síně slávy dostanou studenti na 1.–10. místě.').'</strong><input type="number" min="3" max="100" name="mhl_hof_limit" value="'.esc_attr($h['limit']).'"></label>';
        echo '<label><strong>Kdo nepřidá přezdívku '.self::help('Určuje, co se stane s kvalifikovaným studentem, který tlačítko Přidat do Síně slávy nepoužije. Nezobrazovat je soukromější; anonymně zachová kompletní pořadí bez přezdívky.').'</strong><select name="mhl_hof_nonopt_mode"><option value="hidden" '.selected($h['nonopt_mode']??'hidden','hidden',false).'>Nezobrazovat</option><option value="anonymous" '.selected($h['nonopt_mode']??'hidden','anonymous',false).'>Zobrazit anonymně</option></select></label>';
        echo '<label><strong>Období '.self::help('Pořadí se počítá pouze z hlasů za poslední zadaný počet dní.').'</strong><input type="number" min="1" max="3650" name="mhl_hof_period_days" value="'.esc_attr($h['period_days']).'"> dní</label>';
        echo '<label><strong>Nadpis '.self::help('Volitelný veřejný název soutěže nebo žebříčku.').'</strong><input type="text" name="mhl_hof_title" value="'.esc_attr($h['title']).'" placeholder="Např. OOP Challenge"></label>';
        echo '</div>';
        if(!empty($h['url'])){ echo '<p class="mhl-url-note"><strong>Adresa:</strong> <code>'.esc_html($h['url']).'</code> &nbsp; <a class="button" href="'.esc_url($h['url']).'" target="_blank" rel="noopener">Otevřít</a></p>'; }
        echo '<p class="description"><strong>Soukromí:</strong> veřejně se neposílá jméno, e-mail ani technický identifikátor. Přezdívka se zveřejní až po aktivním kliknutí studenta.</p>';
    }

    public static function subject_projection_box(WP_Post $post): void {
        $p=MHL_Core::subject_projection((int)$post->ID);
        echo '<p>Trvalý <strong>read-only</strong> projekční odkaz lze předat vyučujícímu, který nemá účet ve WordPressu. Stránka automaticky sleduje právě aktivní otázku v předmětu.</p>';
        if(!empty($p['url'])){echo '<p><code style="word-break:break-all">'.esc_html($p['url']).'</code></p><p><button type="button" class="button mhl-copy" data-copy="'.esc_attr($p['url']).'">Kopírovat</button> <a class="button" href="'.esc_url($p['url']).'" target="_blank" rel="noopener">Otevřít ↗</a></p>';}
        echo '<label><input type="checkbox" name="mhl_projection_regenerate" value="1"> Vygenerovat při uložení nový projekční odkaz</label>';
        echo '<p class="description">Nový token zneplatní dříve předané projekční odkazy. Studentské QR kódy tím nejsou dotčeny.</p>';
    }

    public static function subject_teachers_box(WP_Post $post): void {
        wp_nonce_field('mhl_save_subject','mhl_subject_nonce');
        $teachers=MHL_Core::subject_teachers((int)$post->ID,false);
        if(!$teachers){$teachers=array(array('name'=>'','role'=>'','profile_url'=>'','email'=>'','show_public'=>true));}
        ?>
        <p class="description">Uveďte jednoho nebo více vyučujících předmětu. Přednášky je automaticky zdědí. Ve veřejném hlasování se zobrazí jen vyučující označení „Zobrazovat studentům“. E-mail zůstává pouze v administraci.</p>
        <div class="mhl-teachers" data-mhl-teachers>
          <div class="mhl-teachers-head" aria-hidden="true"><span>Jméno</span><span>Role</span><span>Profil / web</span><span>E-mail</span><span>Veřejně</span><span></span></div>
          <div data-mhl-teachers-list>
          <?php foreach($teachers as $i=>$t): ?>
            <div class="mhl-teacher-row" data-mhl-teacher-row>
              <input type="text" name="mhl_teachers[<?php echo esc_attr($i); ?>][name]" value="<?php echo esc_attr($t['name']??''); ?>" placeholder="Miloslav Hub" aria-label="Jméno vyučujícího">
              <input type="text" name="mhl_teachers[<?php echo esc_attr($i); ?>][role]" value="<?php echo esc_attr($t['role']??''); ?>" placeholder="Přednášející" aria-label="Role vyučujícího" list="mhl-teacher-roles">
              <input type="url" name="mhl_teachers[<?php echo esc_attr($i); ?>][profile_url]" value="<?php echo esc_attr($t['profile_url']??''); ?>" placeholder="https://…" aria-label="Profil nebo web vyučujícího">
              <input type="email" name="mhl_teachers[<?php echo esc_attr($i); ?>][email]" value="<?php echo esc_attr($t['email']??''); ?>" placeholder="e-mail (neveřejný)" aria-label="E-mail vyučujícího">
              <label class="mhl-teacher-public"><input type="checkbox" name="mhl_teachers[<?php echo esc_attr($i); ?>][show_public]" value="1" <?php checked(!empty($t['show_public'])); ?>> <span>Zobrazovat</span></label>
              <button type="button" class="button-link-delete mhl-remove-teacher">Odebrat</button>
            </div>
          <?php endforeach; ?>
          </div>
          <p><button type="button" class="button" data-mhl-add-teacher>+ Přidat vyučujícího</button> <?php echo self::help('Vyučující jsou metadata předmětu; nezískávají tím žádná administrační oprávnění. Přednášky seznam automaticky přebírají z předmětu.'); ?></p>
          <datalist id="mhl-teacher-roles"><option value="Garant"><option value="Přednášející"><option value="Cvičící"><option value="Hostující přednášející"></datalist>
          <template data-mhl-teacher-template>
            <div class="mhl-teacher-row" data-mhl-teacher-row>
              <input type="text" data-field="name" placeholder="Jméno vyučujícího" aria-label="Jméno vyučujícího">
              <input type="text" data-field="role" placeholder="Přednášející" aria-label="Role vyučujícího" list="mhl-teacher-roles">
              <input type="url" data-field="profile_url" placeholder="https://…" aria-label="Profil nebo web vyučujícího">
              <input type="email" data-field="email" placeholder="e-mail (neveřejný)" aria-label="E-mail vyučujícího">
              <label class="mhl-teacher-public"><input type="checkbox" data-field="show_public" value="1" checked> <span>Zobrazovat</span></label>
              <button type="button" class="button-link-delete mhl-remove-teacher">Odebrat</button>
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
        $correct=MHL_Core::question_correct_index($post->ID); $mult=(float)(get_post_meta($post->ID,'_mhl_multiplier',true)?:1); $speed=(int)(get_post_meta($post->ID,'_mhl_speed_window',true)?:MHL_Core::settings()['speed_window']); $poll_points=(int)get_post_meta($post->ID,'_mhl_poll_points',true); $time_raw=get_post_meta($post->ID,'_mhl_time_limit',true); $time_value=($time_raw===''||$time_raw===null)?'auto':(string)(int)$time_raw;
        $type=$correct===null?'Anketa – bez správné odpovědi':'Kvíz – se správnou odpovědí a body';
        ?>
        <p><strong>Typ otázky:</strong> <span id="mhl-inferred-type"><?php echo esc_html($type); ?></span> <?php echo self::help('Typ se určuje automaticky. Pokud není označena žádná správná odpověď, jde o anketu. Jakmile označíte správnou odpověď, otázka se chová jako kvíz.'); ?></p>
        <div class="mhl-field-grid">
          <label><strong>Časový limit hlasování <?php echo self::help('Určuje dobu od aktivace otázky do automatického uzavření. Při volbě Automaticky je výchozí kvíz 30 s a anketa bez limitu. Pokud je limit nastaven, studentům i na projekci se zobrazuje odpočet.'); ?></strong>
            <select name="mhl_time_limit">
              <option value="auto" <?php selected($time_value,'auto'); ?>>Automaticky podle typu</option>
              <option value="0" <?php selected($time_value,'0'); ?>>Bez časového limitu</option>
              <?php foreach(array(15,30,45,60,90,120,180,300) as $sec): ?><option value="<?php echo esc_attr($sec); ?>" <?php selected($time_value,(string)$sec); ?>><?php echo esc_html($sec); ?> s</option><?php endforeach; ?>
            </select>
          </label>
          <label class="mhl-quiz-only"><strong>Násobitel bodů <?php echo self::help('Určuje váhu otázky. ×1 je běžná otázka; vyšší násobitel použijte jen u výrazně důležitější otázky.'); ?></strong>
            <select name="mhl_multiplier"><?php foreach(array(1,1.5,2) as $m): ?><option value="<?php echo esc_attr($m); ?>" <?php selected($mult,$m); ?>>×<?php echo esc_html($m); ?></option><?php endforeach; ?></select>
          </label>
          <label class="mhl-quiz-only"><strong>Rychlostní okno <?php echo self::help('Doba, během níž se u správné odpovědi postupně snižuje rychlostní bonus. Po jejím uplynutí zůstávají základní body za správnost.'); ?></strong>
            <input type="number" min="5" max="120" name="mhl_speed_window" value="<?php echo esc_attr($speed); ?>"> s
          </label>
          <label class="mhl-poll-only"><strong>Body za účast <?php echo self::help('U ankety se standardně body nepřidělují. Pokud chcete odměnit pouhou účast, nastavte malý počet bodů; nezávisí na zvolené odpovědi ani rychlosti.'); ?></strong>
            <input type="number" min="0" max="1000" name="mhl_poll_points" value="<?php echo esc_attr($poll_points); ?>">
          </label>
          <?php $async=MHL_Core::question_async_enabled($post->ID); $async_end=(string)get_post_meta($post->ID,'_mhl_async_end',true); $async_show=MHL_Core::question_async_show_results($post->ID); ?>
          <label class="mhl-poll-only mhl-template-wide"><strong>Dlouhodobá otevřená anketa <?php echo self::help('Vytvoří samostatný trvalý odkaz, který neblokuje živou přednášku. Ankета může běžet dny či týdny; nemá společný odpočet a nepočítá body.'); ?></strong><span><input type="checkbox" name="mhl_async_enabled" value="1" <?php checked($async); ?>> Povolit samostatný dlouhodobý odkaz</span></label>
          <label class="mhl-poll-only"><strong>Automaticky uzavřít <?php echo self::help('Volitelné. Nechte prázdné pro anketu bez pevného konce; uzavřít ji pak lze v administraci.'); ?></strong><input type="datetime-local" name="mhl_async_end" value="<?php echo esc_attr($async_end); ?>"></label>
          <label class="mhl-poll-only"><strong>Průběžné výsledky <?php echo self::help('Po odevzdání odpovědi může respondent vidět aktuální rozložení hlasů, i když anketa stále běží.'); ?></strong><span><input type="checkbox" name="mhl_async_show_results" value="1" <?php checked($async_show); ?>> Po hlasování zobrazit průběžné výsledky</span></label>
        </div>
        <p class="description mhl-quiz-only">Výchozí princip: 800 bodů za správnost + až 200 bodů za rychlost. Nesprávná odpověď má 0 bodů.</p>
        <p><label><input type="radio" name="mhl_correct_index" value="-1" <?php checked($correct===null); ?>> <strong>Žádná správná odpověď</strong> – otázka bude anketa.</label></p>
        <table class="widefat striped mhl-options-table"><thead><tr><th class="mhl-correct-col">Správná</th><th>Možnost</th><th>Text odpovědi</th></tr></thead><tbody>
        <?php foreach($options as $i=>$label): ?><tr><td class="mhl-correct-col"><input type="radio" name="mhl_correct_index" value="<?php echo esc_attr($i); ?>" <?php checked($correct!==null && $correct===$i); ?>></td><td><strong><?php echo esc_html(chr(65+$i)); ?></strong></td><td><input type="text" class="widefat" name="mhl_options[]" value="<?php echo esc_attr($label); ?>" placeholder="Odpověď <?php echo esc_attr(chr(65+$i)); ?>"></td></tr><?php endforeach; ?>
        </tbody></table>
        <?php
    }
    public static function question_rag_box(WP_Post $post): void {
        $p=MHL_Core::rag_policy($post->ID); ?>
        <p><strong>Dostupnost pro AI/RAG <?php echo self::help('Výchozí je neindexovat. Samotný plugin otázky do RAG neposílá; toto pole je politika pro váš indexer.'); ?></strong></p>
        <select name="mhl_rag_policy" class="widefat"><option value="exclude" <?php selected($p,'exclude'); ?>>Neindexovat (doporučeno)</option><option value="private" <?php selected($p,'private'); ?>>Pouze soukromá KB</option><option value="public_after_lecture" <?php selected($p,'public_after_lecture'); ?>>Veřejná KB až po přednášce</option></select>
        <p class="description">Frontend hlasování a REST API mají navíc <code>noindex</code>. Vlastní RAG indexer musí tuto politiku explicitně respektovat.</p><?php
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
        <p><label><strong>Předmět * <?php echo self::help('Předmět představuje celý semestr nebo kurz. Každá přednáška patří právě jednomu předmětu a body se mezi různými předměty nepřenášejí.'); ?></strong> <select name="mhl_subject_id" required><option value="">— vyberte —</option><?php foreach($subjects as $s): ?><option value="<?php echo esc_attr($s->ID); ?>" <?php selected($subject_id,$s->ID); ?>><?php echo esc_html($s->post_title); ?></option><?php endforeach; ?></select></label></p>
        <p><label><input type="checkbox" name="mhl_gamification" value="1" <?php checked($gam); ?>> <strong>Soutěžní režim <?php echo self::help('Zapne přezdívky, body, měření rychlosti a výsledkové pořadí. Přezdívka se pamatuje pro celý předmět a používá se i v dalších přednáškách.'); ?></strong> – přezdívky, body a pořadí; výchozí součet je v rámci celého předmětu.</label></p>
        <p><label><strong>Celkové pořadí <?php echo self::help('Určuje, zda se bude zobrazovat dlouhodobý součet bodů. Doporučené nastavení je v rámci celého předmětu; jednotlivé přednášky i nadále začínají čistě, ale student vidí i celkový stav předmětu.'); ?></strong> <select name="mhl_score_scope"><option value="subject" <?php selected($scope,'subject'); ?>>V rámci celého předmětu</option><option value="lecture" <?php selected($scope,'lecture'); ?>>Pouze v rámci této přednášky</option><option value="none" <?php selected($scope,'none'); ?>>Bez celkového pořadí</option></select></label></p>
        <p><label><input type="checkbox" name="mhl_show_live_results" value="1" <?php checked($show_live); ?>> <strong>Průběžné výsledky <?php echo self::help('Když je zapnuto, graf se mění už během hlasování. Doporučené výchozí nastavení je vypnuto, aby průběžné výsledky neovlivňovaly další studenty.'); ?></strong> – zobrazovat rozložení odpovědí už během hlasování.</label></p>
        <p><label><strong>Materiály / stránka přednášky <?php echo self::help('Volitelná adresa na miloslavhub.cz, kterou student uvidí po hlasování. Vhodné pro prezentaci, podklady, odkazy nebo shrnutí přednášky.'); ?></strong><br><input type="url" class="widefat" name="mhl_materials_url" value="<?php echo esc_attr($materials); ?>" placeholder="https://miloslavhub.cz/..."></label></p>
        <p><label><strong>AI asistent <?php echo self::help('Volitelná adresa vašeho AI asistenta. Po hlasování může student pokračovat na tuto stránku a pracovat s obsahem vašeho webu.'); ?></strong><br><input type="url" class="widefat" name="mhl_assistant_url" value="<?php echo esc_attr($assistant); ?>" placeholder="https://miloslavhub.cz/..."></label></p>
        <h4>Otázky v této přednášce <?php echo self::help('Otázky můžete přetahovat mezi seznamy Dostupné a Vybrané. Přetažením uvnitř Vybraných změníte pořadí. Stejné operace lze provést tlačítky Přidat, Odebrat a šipkami. Změna pořadí ani vložení nové otázky nemění trvalé QR adresy ostatních otázek.'); ?></h4>
        <?php if(!$questions): ?><p>Nejprve vytvořte otázky.</p><?php else:
            $by_id=array(); foreach($questions as $q){$by_id[(int)$q->ID]=$q;}
            $selected_questions=array(); foreach($selected as $qid){if(isset($by_id[(int)$qid])){$selected_questions[]=$by_id[(int)$qid]; unset($by_id[(int)$qid]);}}
            $available_questions=array_values($by_id);
        ?>
        <div class="mhl-question-picker" data-mhl-question-picker>
          <div class="mhl-question-section">
            <div class="mhl-question-section-head"><strong>Vybrané otázky – pořadí v přednášce</strong><span class="description">Přetáhněte za ☰, použijte šipky nebo tlačítko Odebrat.</span></div>
            <div class="mhl-selected-questions" data-mhl-selected-list>
              <?php foreach($selected_questions as $q): $terms=wp_get_post_terms($q->ID,'mhl_question_category',array('fields'=>'names')); ?>
                <div class="mhl-question-item is-selected" data-question-id="<?php echo esc_attr($q->ID); ?>" data-title="<?php echo esc_attr(mb_strtolower($q->post_title)); ?>">
                  <span class="mhl-drag-handle" draggable="true" role="button" tabindex="0" aria-label="Přetáhnout otázku" title="Přetáhnout mezi seznamy nebo změnit pořadí">☰</span>
                  <span class="mhl-order-badge" aria-label="Pořadí"></span>
                  <label class="mhl-question-label"><input class="mhl-question-toggle" type="checkbox" name="mhl_question_ids[]" value="<?php echo esc_attr($q->ID); ?>" checked> <strong><?php echo esc_html($q->post_title); ?></strong><?php if($terms): ?><span class="description"> — <?php echo esc_html(implode(', ',$terms)); ?></span><?php endif; ?></label>
                  <input class="mhl-order" type="hidden" name="mhl_question_order[<?php echo esc_attr($q->ID); ?>]" value="">
                  <div class="mhl-order-actions"><button type="button" class="button-link mhl-move-up" aria-label="Posunout otázku nahoru" title="Posunout nahoru">↑</button><button type="button" class="button-link mhl-move-down" aria-label="Posunout otázku dolů" title="Posunout dolů">↓</button><button type="button" class="button-link-delete mhl-remove-question">Odebrat</button><button type="button" class="button mhl-add-question">Přidat</button></div>
                </div>
              <?php endforeach; ?>
              <p class="mhl-selected-empty">Zatím není vybrána žádná otázka.</p>
            </div>
          </div>
          <div class="mhl-question-section">
            <div class="mhl-question-section-head"><strong>Dostupné otázky z banky</strong><span class="description">Přetáhněte do Vybraných nebo použijte tlačítko Přidat.</span></div>
            <div class="mhl-available-questions" data-mhl-available-list>
              <?php foreach($available_questions as $q): $terms=wp_get_post_terms($q->ID,'mhl_question_category',array('fields'=>'names')); ?>
                <div class="mhl-question-item" data-question-id="<?php echo esc_attr($q->ID); ?>" data-title="<?php echo esc_attr(mb_strtolower($q->post_title)); ?>">
                  <span class="mhl-drag-handle" draggable="true" role="button" tabindex="0" aria-label="Přidat otázku přetažením" title="Přetáhnout do vybraných otázek">☰</span>
                  <span class="mhl-order-badge" aria-hidden="true"></span>
                  <label class="mhl-question-label"><input class="mhl-question-toggle" type="checkbox" name="mhl_question_ids[]" value="<?php echo esc_attr($q->ID); ?>"> <strong><?php echo esc_html($q->post_title); ?></strong><?php if($terms): ?><span class="description"> — <?php echo esc_html(implode(', ',$terms)); ?></span><?php endif; ?></label>
                  <input class="mhl-order" type="hidden" name="mhl_question_order[<?php echo esc_attr($q->ID); ?>]" value="">
                  <div class="mhl-order-actions"><button type="button" class="button-link mhl-move-up" aria-label="Posunout otázku nahoru" title="Posunout nahoru">↑</button><button type="button" class="button-link mhl-move-down" aria-label="Posunout otázku dolů" title="Posunout dolů">↓</button><button type="button" class="button-link-delete mhl-remove-question">Odebrat</button><button type="button" class="button mhl-add-question">Přidat</button></div>
                </div>
              <?php endforeach; ?>
              <p class="mhl-available-empty">Všechny otázky z banky jsou už v této přednášce.</p>
            </div>
          </div>
        </div><?php endif;
    }
    public static function lecture_qr_box(WP_Post $post): void {
        if($post->post_status==='auto-draft'||!$post->post_name){echo '<p>Po prvním uložení přednášky se zde zobrazí trvalé adresy a QR kódy.</p>';return;}
        echo '<div class="notice-inline mhl-permanent-qr-note"><strong>Trvalé QR kódy</strong> '.self::help('Adresa QR se po prvním uložení uzamkne. Můžete později přejmenovat předmět, přednášku i otázku a QR vložený v prezentaci bude dál fungovat. Přestane fungovat pouze po smazání příslušné přednášky nebo otázky, případně po destruktivním ručním zásahu do databáze.').'<br><span class="description">QR můžete bezpečně ponechat v prezentaci i pro další semestry a roky.</span></div>';
        $ids=MHL_Core::get_lecture_question_ids($post->ID); if(!$ids){echo '<p>Nejprve přiřaďte otázky a přednášku uložte.</p>';return;}
        foreach($ids as $qid){$url=MHL_Core::get_vote_url($post->ID,$qid,'live');echo '<div class="mhl-mini-qr"><strong>'.esc_html(get_the_title($qid)).'</strong><div class="mhl-qr" data-qr="'.esc_attr($url).'" data-size="160"></div><code>'.esc_html($url).'</code><p><button type="button" class="button mhl-copy" data-copy="'.esc_attr($url).'">Kopírovat URL</button></p>';if(MHL_Core::question_async_enabled($qid)){$a=MHL_Core::get_vote_url($post->ID,$qid,'async');echo '<hr><strong>Dlouhodobá anketa</strong><br><code>'.esc_html($a).'</code><p><button type="button" class="button mhl-copy" data-copy="'.esc_attr($a).'">Kopírovat dlouhodobý odkaz</button></p>';}echo '</div>';}
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
        $posted=isset($_POST['mhl_options'])?(array)$_POST['mhl_options']:array();
        $correct_original=isset($_POST['mhl_correct_index'])?(int)$_POST['mhl_correct_index']:-1;
        $opts=array(); $correct_new=null;
        foreach($posted as $i=>$v){$clean=sanitize_text_field(wp_unslash($v));if($clean===''){continue;} $new_index=count($opts);$opts[]=$clean;if($correct_original===(int)$i){$correct_new=$new_index;}}
        update_post_meta($id,'_mhl_options',$opts);
        if($correct_new!==null){update_post_meta($id,'_mhl_correct_index',$correct_new);update_post_meta($id,'_mhl_mode','quiz');}else{delete_post_meta($id,'_mhl_correct_index');update_post_meta($id,'_mhl_mode','poll');}
        $mult=isset($_POST['mhl_multiplier'])?(float)$_POST['mhl_multiplier']:1;if(!in_array($mult,array(1.0,1.5,2.0),true)){$mult=1;}update_post_meta($id,'_mhl_multiplier',$mult);
        update_post_meta($id,'_mhl_speed_window',min(120,max(5,absint($_POST['mhl_speed_window']??20)))); update_post_meta($id,'_mhl_poll_points',min(1000,max(0,absint($_POST['mhl_poll_points']??0))));
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
        if(!current_user_can('manage_options')){return;} $qc=wp_count_posts('mhl_question')->publish??0;$lc=wp_count_posts('mhl_lecture')->publish??0;$sc=wp_count_posts('mhl_subject')->publish??0;$active=0;if(MHL_DB::schema_ready()){$db=MHL_DB::db();$runs=MHL_DB::table('runs');$active=(int)$db->get_var("SELECT COUNT(*) FROM {$runs} WHERE status='active' AND mode='live'");}
        echo '<div class="wrap mhl-wrap"><h1>MiloslavHub Live</h1><p>Interaktivní přednášky pod značkou <strong>miloslavhub.cz</strong>. Otázky jsou v bance, přednášky patří právě jednomu předmětu a ostrá/testovací data jsou oddělena.</p><div class="mhl-cards"><div class="mhl-card"><strong>'.esc_html($sc).'</strong><span>předmětů</span></div><div class="mhl-card"><strong>'.esc_html($lc).'</strong><span>přednášek</span></div><div class="mhl-card"><strong>'.esc_html($qc).'</strong><span>otázek</span></div><div class="mhl-card"><strong>'.esc_html($active).'</strong><span>aktivních přednášek</span></div></div><p><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type=mhl_subject')).'">Přidat předmět</a> <a class="button" href="'.esc_url(admin_url('post-new.php?post_type=mhl_question')).'">Přidat otázku</a> <a class="button" href="'.esc_url(admin_url('post-new.php?post_type=mhl_lecture')).'">Přidat přednášku</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=mhl-live-test')).'">Testovací laboratoř</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=mhl-live-demo')).'">Ukázkové demo</a></p><div class="notice notice-info inline"><p><strong>RAG:</strong> otázky a hlasovací data jsou výchozím nastavením mimo indexaci. Veřejný frontend i API mají noindex; váš vlastní RAG indexer musí navíc explicitně vyloučit <code>hlasuj.miloslavhub.cz/*</code>, <code>/wp-json/mhl/*</code> a post typy <code>mhl_*</code>.</p></div></div>';
    }

    private static function list_runs(string $mode): void {
        if(!MHL_DB::schema_ready()){echo '<div class="notice notice-error inline"><p>Nejprve dokončete databázovou migraci 0.3.0.</p></div>';return;}
        $db=MHL_DB::db();$runs=MHL_DB::table('runs');$sessions=MHL_DB::table('sessions');$run_id=absint($_GET['run_id']??0);$run=$run_id?$db->get_row($db->prepare("SELECT * FROM {$runs} WHERE id=%d AND mode=%s",$run_id,$mode)):null;
        if(!$run){$lectures=get_posts(array('post_type'=>'mhl_lecture','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));if(!$lectures){echo '<p>Nejprve vytvořte předmět, otázku a přednášku.</p>';return;}echo '<table class="widefat striped"><thead><tr><th>Předmět</th><th>Přednáška</th><th>Otázek</th><th></th></tr></thead><tbody>';foreach($lectures as $l){$url=wp_nonce_url(admin_url('admin-post.php?action=mhl_start_run&mode='.$mode.'&lecture_id='.$l->ID),'mhl_start_run_'.$mode.'_'.$l->ID);echo '<tr><td>'.esc_html(MHL_Core::get_subject_title($l->ID)).'</td><td><strong>'.esc_html($l->post_title).'</strong></td><td>'.esc_html(count(MHL_Core::get_lecture_question_ids($l->ID))).'</td><td><a class="button button-primary" href="'.esc_url($url).'">'.($mode==='test'?'Spustit TEST':'Spustit přednášku').'</a></td></tr>';}echo '</tbody></table>';return;}
        echo $mode==='test'?'<div class="notice notice-warning inline"><p><strong>TESTOVACÍ REŽIM.</strong> Tato data se nezapočítávají do ostrého archivu.</p></div>':''; echo '<p><strong>'.esc_html($run->title).'</strong> — relace #'.esc_html($run->id).' — '.esc_html($run->status).'</p>';
        $rows=$db->get_results($db->prepare("SELECT s.* FROM {$sessions} s INNER JOIN (SELECT question_id,MAX(id) max_id FROM {$sessions} WHERE run_id=%d GROUP BY question_id) x ON x.max_id=s.id ORDER BY s.id ASC",(int)$run->id));echo '<p class="description"><strong>Nápověda k tlačítkům:</strong> Spustit hlasování otevře otázku všem studentům, Student ukazuje mobilní pohled, Projekce promítací pohled a Zopakovat otázku slouží k opakovanému testu jedné otázky.</p><div class="mhl-live-list">';foreach($rows as $s){$qid=(int)$s->question_id;$vote=MHL_Core::get_vote_url((int)$run->lecture_id,$qid,$mode);$res=MHL_Core::get_results_url((int)$run->lecture_id,$qid,$mode);$status_labels=array('waiting'=>'Připravená','joining'=>'Připojování','open'=>'Probíhá','closed'=>'Ukončená','skipped'=>'Přeskočená');$status_label=$status_labels[$s->status]??$s->status;echo '<section class="mhl-live-item"><div><h2>'.esc_html(get_the_title($qid)).'</h2><p>Stav: <strong>'.esc_html($status_label).'</strong><br><code>'.esc_html($vote).'</code></p></div><div class="mhl-live-actions">';if(in_array($s->status,array('waiting','joining'),true)){$u=wp_nonce_url(admin_url('admin-post.php?action=mhl_open_session&session_id='.$s->id.'&run_id='.$run->id),'mhl_session_'.$s->id);echo '<a class="button button-primary" href="'.esc_url($u).'">Spustit hlasování <span class="mhl-button-note" title="Spustí hlasování pro všechny připojené studenty. QR kód slouží pouze k připojení.">?</span></a>';}elseif($s->status==='open'){$u=wp_nonce_url(admin_url('admin-post.php?action=mhl_close_session&session_id='.$s->id.'&run_id='.$run->id),'mhl_session_'.$s->id);echo '<a class="button" href="'.esc_url($u).'">Ukončit <span class="mhl-button-note" title="Ruční ukončení aktivní otázky. Po ukončení se zobrazí výsledky a už nelze hlasovat.">?</span></a>';if($mode==='test'){$sim=wp_nonce_url(admin_url('admin-post.php?action=mhl_simulate_votes&session_id='.$s->id.'&run_id='.$run->id),'mhl_simulate_'.$s->id);echo '<a class="button" href="'.esc_url($sim).'">Simulovat 5 studentů <span class="mhl-button-note" title="Pouze v testu: přidá ukázkové odpovědi, abyste viděl/a graf a pořadí bez dalších lidí.">?</span></a>';}}$reset=wp_nonce_url(admin_url('admin-post.php?action=mhl_reset_session&session_id='.$s->id.'&run_id='.$run->id),'mhl_session_'.$s->id);echo '<a class="button" href="'.esc_url($vote).'" target="_blank">Student ↗ <span class="mhl-button-note" title="Otevře stejný pohled, který vidí student na mobilu.">?</span></a><a class="button" href="'.esc_url($res).'" target="_blank">Projekce ↗ <span class="mhl-button-note" title="Otevře projekční obrazovku s QR, odpočtem, grafem a výsledky.">?</span></a><a class="button" href="'.esc_url($reset).'">Zopakovat otázku <span class="mhl-button-note" title="Resetuje pouze tuto otázku v aktuální testovací nebo živé relaci. Používá se pro opakovaný test.">?</span></a></div></section>';}echo '</div>';$finish=wp_nonce_url(admin_url('admin-post.php?action=mhl_finish_run&run_id='.$run->id),'mhl_finish_run_'.$run->id);echo '<p><a class="button" href="'.esc_url($finish).'">Ukončit '.($mode==='test'?'test':'přednášku').'</a></p>';
    }
    public static function live_control(): void { if(!current_user_can('manage_options')){return;} echo '<div class="wrap mhl-wrap"><h1>Živé ovládání</h1>';self::list_runs('live');echo '</div>'; }
    public static function test_lab(): void { if(!current_user_can('manage_options')){return;}echo '<div class="wrap mhl-wrap"><h1>Testovací laboratoř</h1><p>Vyzkoušíte zde stejný studentský frontend i projekci, ale data jsou označena jako <strong>TEST</strong> a nejsou v ostrém archivu.</p>';self::list_runs('test');$clear=wp_nonce_url(admin_url('admin-post.php?action=mhl_clear_test_data'),'mhl_clear_test_data');echo '<hr><p><a class="button" href="'.esc_url($clear).'" onclick="return confirm(\'Opravdu smazat všechna testovací hlasování?\')">Vymazat všechna testovací data <span class="mhl-button-note" title="Odstraní testovací relace a hlasy. Ostrá data tím nejsou dotčena.">?</span></a></p></div>'; }

    public static function long_polls(): void {
        if(!current_user_can('manage_options')){return;}
        echo '<div class="wrap mhl-wrap"><h1>Dlouhodobé ankety</h1><p>Samostatné ankety bez odpočtu. Běží v režimu <code>async</code>, takže neblokují živou přednášku ani soutěžní pořadí.</p>';
        if(!MHL_DB::schema_ready()){echo '<div class="notice notice-error inline"><p>Nejprve dokončete databázovou migraci.</p></div></div>';return;}
        $lectures=get_posts(array('post_type'=>'mhl_lecture','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));$found=false;
        echo '<table class="widefat striped"><thead><tr><th>Předmět</th><th>Přednáška</th><th>Anketa</th><th>Stav</th><th>Odkazy / akce</th></tr></thead><tbody>';
        foreach($lectures as $l){foreach(MHL_Core::get_lecture_question_ids((int)$l->ID) as $qid){if(!MHL_Core::question_async_enabled($qid)){continue;}$found=true;$run=MHL_Core::get_active_run_for_question((int)$l->ID,$qid,'async');$session=$run?MHL_Core::get_current_session((int)$l->ID,$qid,(int)$run->id,'async',true):null;$state=$session?(string)$session->status:'neaktivní';$vote=MHL_Core::get_vote_url((int)$l->ID,$qid,'async');$res=MHL_Core::get_results_url((int)$l->ID,$qid,'async');echo '<tr><td>'.esc_html(MHL_Core::get_subject_title((int)$l->ID)).'</td><td>'.esc_html($l->post_title).'</td><td><strong>'.esc_html(get_the_title($qid)).'</strong></td><td>'.esc_html($state).'</td><td><a class="button" target="_blank" rel="noopener" href="'.esc_url($vote).'">Respondent ↗</a> <a class="button" target="_blank" rel="noopener" href="'.esc_url($res).'">Výsledky ↗</a> ';if($session&&$session->status==='open'){$u=wp_nonce_url(admin_url('admin-post.php?action=mhl_close_session&session_id='.(int)$session->id.'&run_id='.(int)$run->id),'mhl_session_'.(int)$session->id);echo '<a class="button" href="'.esc_url($u).'">Uzavřít</a>';}elseif($session&&$session->status==='closed'){$u=wp_nonce_url(admin_url('admin-post.php?action=mhl_reset_session&session_id='.(int)$session->id.'&run_id='.(int)$run->id),'mhl_session_'.(int)$session->id);echo '<a class="button" href="'.esc_url($u).'">Nová relace</a>';}echo '</td></tr>';}}
        if(!$found){echo '<tr><td colspan="5">Zatím není žádná otázka označena jako dlouhodobá otevřená anketa.</td></tr>';}
        echo '</tbody></table><p class="description">První otevření respondentského odkazu anketu automaticky aktivuje. Pokud není nastaven datum ukončení, zůstane otevřená, dokud ji zde ručně neuzavřete.</p></div>';
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
        if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}
        check_admin_referer('mhl_create_demo');
        self::ensure_demo_content();
        update_option('mhl_demo_autocreated_072','yes',false);
        wp_safe_redirect(admin_url('admin.php?page=mhl-live-demo&created=1')); exit;
    }
    public static function reset_demo(): void {
        if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}
        check_admin_referer('mhl_reset_demo');
        $ids=self::ensure_demo_content();
        $lid=(int)($ids['lecture_id']??0);
        if($lid && MHL_DB::schema_ready()){
            $db=MHL_DB::db();$runs=MHL_DB::table('runs');$sessions=MHL_DB::table('sessions');$votes=MHL_DB::table('votes');
            $run_ids=$db->get_col($db->prepare("SELECT id FROM {$runs} WHERE lecture_id=%d AND mode='test'",$lid));
            foreach($run_ids?:array() as $rid){if(!MHL_Core::delete_test_run((int)$rid)){wp_die('Test se nepodařilo odstranit.');}}
        }
        wp_safe_redirect(admin_url('admin.php?page=mhl-live-demo&reset=1')); exit;
    }
    public static function demo_page(): void {
        if(!current_user_can('manage_options')){return;}
        // Opraví i částečně smazané demo, takže QR v ukázkové prezentaci zůstávají vždy platné.
        $ids=self::ensure_demo_content();
        $lid=(int)($ids['lecture_id']??0);
        echo '<div class="wrap mhl-wrap"><h1>Ukázkové demo</h1><p>Tři stálé testovací QR kódy a připravená PowerPointová prezentace pro vyučující, kteří si chtějí systém vyzkoušet sami nebo s kolegou/studentem. Demo je vytvořeno automaticky, používá pouze režim TEST a po 15 minutách aktivní relace automaticky skončí.</p>';
        if(get_transient('mhl_demo_autocreated_notice')){delete_transient('mhl_demo_autocreated_notice');echo '<div class="notice notice-success inline"><p><strong>Ukázkové demo bylo automaticky vytvořeno.</strong> QR kódy v prezentaci jsou nyní připravené k použití.</p></div>';}
        $qids=MHL_Core::get_lecture_question_ids($lid); echo '<div class="mhl-demo-grid">';
        foreach($qids as $qid){$url=MHL_Core::get_vote_url($lid,$qid,'test');echo '<div class="mhl-demo-card"><strong>'.esc_html(get_the_title($qid)).'</strong><div class="mhl-qr" data-qr="'.esc_attr($url).'" data-size="190"></div><p><a href="'.esc_url($url).'" target="_blank">Otevřít test ↗</a></p></div>';}
        echo '</div>';
        $ppt=MHL_URL.'assets/miloslavhub-live-demo.pptx'; $reset=wp_nonce_url(admin_url('admin-post.php?action=mhl_reset_demo'),'mhl_reset_demo');
        echo '<p><a class="button button-primary" href="'.esc_url($ppt).'" download>Stáhnout ukázkovou prezentaci (.pptx)</a> <a class="button" href="'.esc_url($reset).'">Resetovat demo do výchozího stavu</a></p><p class="description">QR kódy v prezentaci jsou trvalé. Pokud demo obsah chybí, plugin jej automaticky znovu vytvoří. Po skončení 15minutového testu se při dalším načtení založí nová čistá testovací relace.</p></div>';
    }

    public static function archive(): void { if(!current_user_can('manage_options')){return;}echo '<div class="wrap mhl-wrap"><h1>Archiv výsledků</h1>';if(!MHL_DB::schema_ready()){echo '<p>Nejprve dokončete migraci databáze.</p></div>';return;}$db=MHL_DB::db();$runs=MHL_DB::table('runs');$votes=MHL_DB::table('votes');$rows=$db->get_results("SELECT r.*,COUNT(v.id) vote_count FROM {$runs} r LEFT JOIN {$votes} v ON v.run_id=r.id WHERE r.mode='live' GROUP BY r.id ORDER BY r.id DESC LIMIT 100");echo '<table class="widefat striped"><thead><tr><th>Relace</th><th>Předmět</th><th>Přednáška</th><th>Začátek</th><th>Stav</th><th>Hlasů</th></tr></thead><tbody>';foreach($rows?:array() as $r){$ex=wp_nonce_url(admin_url('admin-post.php?action=mhl_export_run&run_id='.(int)$r->id),'mhl_export_run_'.(int)$r->id);echo '<tr><td>#'.esc_html($r->id).'</td><td>'.esc_html($r->subject_id?get_the_title((int)$r->subject_id):'').'</td><td>'.esc_html($r->title).'</td><td>'.esc_html(get_date_from_gmt($r->started_at,'j. n. Y H:i')).'</td><td>'.esc_html($r->status).'</td><td>'.esc_html($r->vote_count).' &nbsp; <a href="'.esc_url($ex).'">CSV</a></td></tr>';}echo '</tbody></table></div>'; }

    public static function settings_page(): void {
        if(!current_user_can('manage_options')){return;}
        $s=MHL_Core::settings(); $st=MHL_DB::status();
        echo '<div class="wrap mhl-wrap"><h1>Nastavení MiloslavHub Live</h1>';
        echo '<div class="notice '.($st['ok']?'notice-success':'notice-error').' inline"><p><strong>Samostatná databáze:</strong> '.esc_html($st['message']).'</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="mhl_save_settings">';
        wp_nonce_field('mhl_save_settings');
        echo '<table class="form-table">';
        echo '<tr><th>Adresa hlasování '.self::help('Veřejná URL frontendu, kterou studenti otevírají přes QR kódy. Typicky samostatná HTTPS subdoména, například https://hlasuj.example.cz.').'</th><td><input class="regular-text" type="url" name="frontend_url" value="'.esc_attr($s['frontend_url']).'"></td></tr>';
        echo '<tr><th>Povolený frontend (CORS) '.self::help('Bezpečnostní nastavení: určuje, z jaké veřejné adresy smí frontend volat REST API WordPressu. Obvykle je shodné s adresou hlasování.').'</th><td><input class="regular-text" type="url" name="allowed_origin" value="'.esc_attr($s['allowed_origin']).'"></td></tr>';
        echo '<tr><th>Hlavní web '.self::help('Volitelný veřejný web značky nebo organizace. Zobrazí se jen tam, kde to zvolená brand šablona dovoluje.').'</th><td><input class="regular-text" type="url" name="main_site_url" value="'.esc_attr($s['main_site_url']).'"></td></tr>';
        echo '<tr><th>Výchozí AI asistent '.self::help('Volitelné. Pokud žádného AI asistenta nemáte, ponechte pole prázdné. Tlačítko AI asistent se ve veřejném rozhraní zobrazí jen při skutečně vyplněné URL.').'</th><td><input class="regular-text" type="url" name="assistant_url" value="'.esc_attr($s['assistant_url']).'" placeholder="Ponechte prázdné, pokud AI asistenta nepoužíváte"><p class="description">Prázdné pole = žádný odkaz na AI asistenta.</p></td></tr>';
        echo '<tr><th>Výchozí čas kvízu '.self::help('Časový limit pro kvízové otázky nastavené na Automaticky podle typu. Po vypršení se hlasování automaticky uzavře.').'</th><td><input type="number" min="5" max="600" name="quiz_seconds" value="'.esc_attr($s['quiz_seconds']).'"> sekund <p class="description">Doporučeno 30 s.</p></td></tr>';
        echo '<tr><th>Výchozí čas ankety '.self::help('Časový limit pro anketní otázky nastavené na Automaticky podle typu. Hodnota 0 znamená bez časového limitu.').'</th><td><input type="number" min="0" max="600" name="poll_seconds" value="'.esc_attr($s['poll_seconds']).'"> sekund <p class="description">0 = bez limitu.</p></td></tr>';
        echo '<tr><th>Max. délka aktivní přednášky '.self::help('Bezpečnostní limit celé relace přednášky. Pokud přednášku ručně neukončíte, po této době se relace automaticky uzavře.').'</th><td><input type="number" min="30" max="1440" name="run_minutes" value="'.esc_attr($s['run_minutes']).'"> minut</td></tr>';
        echo '<tr><th>Základní body '.self::help('Počet bodů za správnou kvízovou odpověď před započtením rychlostního bonusu a násobitele otázky.').'</th><td><input type="number" min="0" max="5000" name="base_points" value="'.esc_attr($s['base_points']).'"></td></tr>';
        echo '<tr><th>Max. rychlostní bonus '.self::help('Maximální počet bodů navíc za rychlou správnou odpověď. Bonus postupně klesá během rychlostního okna otázky.').'</th><td><input type="number" min="0" max="5000" name="speed_points" value="'.esc_attr($s['speed_points']).'"></td></tr>';
        echo '<tr><th>Rezervace přezdívky '.self::help('Přezdívka je jedinečná pouze v rámci jednoho předmětu. Stejný student může mít stejnou nebo jinou přezdívku v jiném předmětu. Po této době bez aktivity se přezdívka uvolní pro jiného studenta.').'</th><td><input type="number" min="1" max="3650" name="nickname_reservation_days" value="'.esc_attr($s['nickname_reservation_days']??365).'"> dní <p class="description">Doporučeno 365 dní. Lhůta se obnoví při každém použití přezdívky v předmětu.</p></td></tr>';
        echo '<tr><th colspan="2"><h2>Soukromí a retence</h2><p class="description">Nastavení minimalizace dat. Nejde o náhradu právního posouzení konkrétního nasazení.</p></th></tr>';
        echo '<tr><th>Správce / provozovatel '.self::help('Název osoby nebo organizace uváděný na veřejné stránce Ochrana soukromí.').'</th><td><input class="regular-text" type="text" name="privacy_controller_name" value="'.esc_attr($s['privacy_controller_name']??'Miloslav Hub').'"></td></tr>';
        echo '<tr><th>Kontakt pro soukromí '.self::help('Kontaktní e-mail pro dotazy k osobním údajům a soukromí.').'</th><td><input class="regular-text" type="email" name="privacy_contact_email" value="'.esc_attr($s['privacy_contact_email']??'').'"></td></tr>';
        echo '<tr><th>Retence ostrých hlasů '.self::help('Po této době se staré ostré hlasy automaticky smažou. Úklid probíhá nejvýše jednou denně při běžném provozu webu.').'</th><td><input type="number" min="1" max="3650" name="privacy_live_retention_days" value="'.esc_attr($s['privacy_live_retention_days']??365).'"> dní</td></tr>';
        echo '<tr><th>Retence TEST dat '.self::help('Testovací hlasy a související identifikátory mají mít kratší životnost než ostrá data.').'</th><td><input type="number" min="1" max="365" name="privacy_test_retention_days" value="'.esc_attr($s['privacy_test_retention_days']??30).'"> dní</td></tr>';
        echo '<tr><th>Retence technických příchodů '.self::help('Krátkodobý záznam příchodu zařízení k otázce slouží pro přehled připojení. Hlasování spouští vyučující.').'</th><td><input type="number" min="1" max="90" name="privacy_join_retention_days" value="'.esc_attr($s['privacy_join_retention_days']??7).'"> dní</td></tr>';
        echo '</table>';
        submit_button('Uložit nastavení');
        echo '</form>';
        echo '<div class="notice notice-info inline"><p><strong>RAG policy:</strong> Výchozí stav všech nových otázek je „Neindexovat“. Kromě pluginu nastavte v indexeru explicitní blokaci <code>hlasuj.miloslavhub.cz/*</code>, <code>/wp-json/mhl/*</code> a post typů <code>mhl_question</code>, <code>mhl_lecture</code>, <code>mhl_subject</code>.</p></div></div>';
    }

    public static function start_run(): void {
        if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}
        $lecture_id=absint($_GET['lecture_id']??0); $mode=sanitize_key((string)($_GET['mode']??'live'))==='test'?'test':'live';
        check_admin_referer('mhl_start_run_'.$mode.'_'.$lecture_id);
        if(!$lecture_id || get_post_type($lecture_id)!=='mhl_lecture'){wp_die('Přednáška nebyla nalezena.');}
        $subject_id=MHL_Core::get_lecture_subject_id($lecture_id);
        $subject_run=$subject_id?MHL_Core::get_active_run_for_subject($subject_id,$mode):null;
        if($subject_run && !MHL_Core::close_run((int)$subject_run->id)){wp_die('Předchozí běh se nepodařilo uzavřít.');}
        $run=MHL_Core::create_run($lecture_id,$mode,get_current_user_id(),true);
        if(!$run){wp_die('Přednášku se nepodařilo spustit. Zkontrolujte, že má přiřazený předmět a alespoň jednu otázku.');}
        wp_safe_redirect(admin_url('admin.php?page='.($mode==='test'?'mhl-live-test':'mhl-live-control').'&run_id='.(int)$run->id));exit;
    }

    private static function session_guard(): object { if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}$sid=absint($_GET['session_id']??0);check_admin_referer('mhl_session_'.$sid);$db=MHL_DB::db();$t=MHL_DB::table('sessions');$s=$db->get_row($db->prepare("SELECT * FROM {$t} WHERE id=%d",$sid));if(!$s){wp_die('Relace nebyla nalezena.');}return $s; }
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
    public static function finish_run(): void {if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}$rid=absint($_GET['run_id']??0);check_admin_referer('mhl_finish_run_'.$rid);$db=MHL_DB::db();$runs=MHL_DB::table('runs');$mode=(string)$db->get_var($db->prepare("SELECT mode FROM {$runs} WHERE id=%d",$rid));if(!MHL_Core::close_run($rid)){wp_die('Běh se nepodařilo uzavřít.');}$page=$mode==='test'?'mhl-live-test':($mode==='async'?'mhl-live-async':'mhl-live-control');wp_safe_redirect(admin_url('admin.php?page='.$page));exit;}
    public static function simulate_votes(): void {
        if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}
        $sid=absint($_GET['session_id']??0);$rid=absint($_GET['run_id']??0);
        check_admin_referer('mhl_simulate_'.$sid);
        $result=MHL_DB::with_run_lock($rid,static function($db,$run) use ($sid,$rid) {
            $sessions=MHL_DB::table('sessions');$votes=MHL_DB::table('votes');$s=$db->get_row($db->prepare("SELECT * FROM {$sessions} WHERE id=%d AND run_id=%d AND mode='test'",$sid,$rid));if($run->mode!=='test'||$run->status!=='active'||!$s||$s->status!=='open'||($s->reset_at && strtotime($s->reset_at.' UTC')<=time())||($run->expires_at && strtotime($run->expires_at.' UTC')<=time())){return new WP_Error('mhl_test_closed','Testovací otázka není otevřená.');}$qid=(int)$s->question_id;$opts=MHL_Core::get_question_options($qid);$qmode=MHL_Core::question_type($qid);$ci=MHL_Core::question_correct_index($qid);if($ci===null){$ci=-1;}$settings=MHL_Core::settings();for($i=1;$i<=5;$i++){$option=$opts?($i-1)%count($opts):0;$ms=900+$i*850;$correct=$qmode==='quiz'?($option===$ci?1:0):null;$points=0;if($correct){$mult=(float)(get_post_meta($qid,'_mhl_multiplier',true)?:1);$win=max(5,(int)(get_post_meta($qid,'_mhl_speed_window',true)?:$settings['speed_window']));$factor=max(0.0,1.0-(($ms/1000)/$win));$points=(int)round(((int)$settings['base_points']+((int)$settings['speed_points']*$factor))*$mult);}elseif($qmode==='poll'){$points=(int)get_post_meta($qid,'_mhl_poll_points',true);} $ok=$db->insert($votes,array('session_id'=>$sid,'run_id'=>$rid,'question_id'=>$qid,'mode'=>'test','participant_key'=>hash('sha256','test-'.$rid.'-'.$sid.'-'.$i.'-'.wp_generate_uuid4()),'nickname'=>'Test'.$i,'option_index'=>$option,'is_correct'=>$correct,'response_ms'=>$ms,'points'=>$points,'created_at'=>MHL_Core::now_mysql()),array('%d','%d','%d','%s','%s','%s','%d','%d','%d','%d','%s'));if($ok===false){return new WP_Error('mhl_test_failed','Testovací hlasy se nepodařilo uložit.');}} return true;
        });
        if(is_wp_error($result)){wp_die(esc_html($result->get_error_message()));}
        self::redirect_run($rid);
    }
    public static function clear_test_data(): void {
        if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}
        check_admin_referer('mhl_clear_test_data');
        $db=MHL_DB::db();
        $ids=$db->get_col("SELECT id FROM ".MHL_DB::table('runs')." WHERE mode='test'");
        foreach($ids?:array() as $rid){if(!MHL_Core::delete_test_run((int)$rid)){wp_die('Test se nepodařilo odstranit.');}}
        $db->query("DELETE FROM ".MHL_DB::table('participants')." WHERE mode='test'");
        wp_safe_redirect(admin_url('admin.php?page=mhl-live-test'));exit;
    }
    public static function csv_text(string $value): string {
        // Spreadsheet applications may ignore leading whitespace/control characters.
        return preg_match('/^[\x00-\x20\x{00A0}]*[=+@\-]/u', $value) || preg_match('/^[\t\r\n]/', $value)
            ? "'" . $value : $value;
    }
    public static function export_run(): void {if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}$rid=absint($_GET['run_id']??0);check_admin_referer('mhl_export_run_'.$rid);$db=MHL_DB::db();$votes=MHL_DB::table('votes');$rows=$db->get_results($db->prepare("SELECT * FROM {$votes} WHERE run_id=%d ORDER BY id ASC",$rid));nocache_headers();header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="miloslavhub-live-run-'.$rid.'.csv"');$o=fopen('php://output','w');fwrite($o,"\xEF\xBB\xBF");fputcsv($o,array('run_id','session_id','question','nickname','option','is_correct','response_ms','points','created_at'),';','"','');foreach($rows?:array() as $r){fputcsv($o,array((int)$r->run_id,(int)$r->session_id,self::csv_text(get_the_title((int)$r->question_id)),self::csv_text((string)$r->nickname),chr(65+(int)$r->option_index),is_null($r->is_correct)?'':(int)$r->is_correct,(int)$r->response_ms,(int)$r->points,$r->created_at),';','"','');}fclose($o);exit;}
    public static function save_settings(): void {
        if(!current_user_can('manage_options')){wp_die('Nemáte oprávnění.');}
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


    public static function subject_columns(array $c): array {$c['mhl_template']='Brand / rozvržení';$c['mhl_teachers']='Vyučující';return $c;}
    public static function subject_column_content(string $col,int $id): void {
        if($col==='mhl_template'){ $t=MHL_Core::subject_template($id); $brands=array('miloslavhub'=>'Miloslav Hub','fes_upce'=>'FES UPa','neutral'=>'Neutrální','custom'=>'Vlastní'); $layouts=array('standard'=>'Standardní','competition'=>'Soutěžní','minimal'=>'Minimalistické'); echo esc_html(($brands[$t['brand_template']]??$t['brand_template']).' / '.($layouts[$t['template']]??$t['template'])); return; }
        if($col!=='mhl_teachers'){return;}
        $teachers=MHL_Core::subject_teachers($id,false);
        if(!$teachers){echo '<span class="description">—</span>';return;}
        $parts=array();
        foreach($teachers as $t){
            $label=esc_html((string)$t['name']);
            if(!empty($t['role'])){$label.=' <span class="description">('.esc_html((string)$t['role']).')</span>';}
            if(empty($t['show_public'])){$label.=' <span title="Nezobrazuje se studentům">🔒</span>';}
            $parts[]=$label;
        }
        echo implode('<br>',$parts);
    }

    public static function question_columns(array $c): array {$c['mhl_type']='Typ';$c['mhl_rag']='RAG';return $c;}
    public static function question_column_content(string $col,int $id): void {if($col==='mhl_type'){echo esc_html(MHL_Core::question_type($id)==='quiz'?'Kvíz':'Anketa');}elseif($col==='mhl_rag'){echo esc_html(MHL_Core::rag_policy($id));}}
    public static function lecture_columns(array $c): array {$c['mhl_subject']='Předmět';$c['mhl_questions']='Otázek';$c['mhl_gamification']='Soutěž';return $c;}
    public static function lecture_column_content(string $col,int $id): void {if($col==='mhl_subject'){echo esc_html(MHL_Core::get_subject_title($id));}elseif($col==='mhl_questions'){echo esc_html(count(MHL_Core::get_lecture_question_ids($id)));}elseif($col==='mhl_gamification'){echo get_post_meta($id,'_mhl_gamification',true)?'Ano':'Ne';}}
}
