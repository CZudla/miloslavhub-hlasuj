<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/class-mhl-i18n.php';

class MHL_AI_Admin {
    public static function init(): void {
        add_action('add_meta_boxes', array(__CLASS__, 'boxes'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function boxes(): void {
        if (MHL_AI::enabled() && current_user_can('mhl_access')) {
            add_meta_box('mhl-ai', MHL_I18n::text('Volitelný AI asistent — pilot'), array(__CLASS__, 'render'), 'mhl_question', 'normal', 'default');
        }
    }

    public static function assets(): void {
        if (!MHL_AI::enabled() || !current_user_can('mhl_access') || (get_current_screen()->post_type ?? '') !== 'mhl_question') { return; }
        MHL_I18n::assets();
        wp_enqueue_script('mhl-ai-admin', MHL_URL.'assets/ai-admin.js', array('mhl-i18n'), MHL_VERSION.'-ai-pilot-1', true);
    }

    public static function render(WP_Post $post): void {
        $enabled = !get_user_meta(get_current_user_id(), 'mhl_ai_disabled', true);
        ?>
        <div id="mhl-ai-panel" data-question-id="<?php echo (int)$post->ID; ?>" data-endpoint="<?php echo esc_url(rest_url('mhl/v1/ai/')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>" data-enabled="<?php echo $enabled ? '1' : '0'; ?>">
            <p><?php echo esc_html_mhl_literal('Asistent odešle do OpenAI pouze níže zobrazený text otázky a možnosti odpovědi. Před odesláním je zkontrolujte a vynechte osobní údaje. Návrh vždy zkontrolujte také věcně.'); ?></p>
            <p><button type="button" class="button" data-ai-toggle><?php echo $enabled ? MHL_I18n::text('Vypnout AI pro můj účet') : MHL_I18n::text('Zapnout AI pro můj účet'); ?></button></p>
            <fieldset data-ai-controls <?php disabled(!$enabled); ?>>
                <label><?php echo esc_html_mhl_literal('Úprava '); ?><select data-ai-operation><option value="rephrase"><?php echo esc_html_mhl_literal('Přeformulovat otázku'); ?></option><option value="translate"><?php echo esc_html_mhl_literal('Přeložit otázku a odpovědi'); ?></option></select></label>
                <label data-ai-language-label hidden><?php echo esc_html_mhl_literal('Cílový jazyk '); ?><select data-ai-language><option value="cs"><?php echo esc_html_mhl_literal('Čeština'); ?></option><option value="en"><?php echo esc_html_mhl_literal('Angličtina'); ?></option></select></label>
                <p><button type="button" class="button" data-ai-preview><?php echo esc_html_mhl_literal('Zkontrolovat obsah k odeslání'); ?></button></p>
                <div data-ai-source-box hidden><strong><?php echo esc_html_mhl_literal('Obsah k odeslání'); ?></strong><pre data-ai-source style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><button type="button" class="button" data-ai-send><?php echo esc_html_mhl_literal('Odeslat do OpenAI a navrhnout úpravu'); ?></button></div>
            </fieldset>
            <p role="status" aria-live="polite" data-ai-status></p>
            <div data-ai-result-box hidden><strong><?php echo esc_html_mhl_literal('Návrh — dosud neuložený'); ?></strong><pre data-ai-result style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
                <button type="button" class="button button-primary" data-ai-apply><?php echo esc_html_mhl_literal('Použít návrh v editoru'); ?></button>
                <button type="button" class="button" data-ai-discard><?php echo esc_html_mhl_literal('Zahodit návrh'); ?></button>
                <p><?php echo esc_html_mhl_literal('Po použití lze text dále upravit. Uložení nebo publikování proveďte běžným tlačítkem WordPressu.'); ?></p>
            </div>
        </div>
        <?php
    }
}
