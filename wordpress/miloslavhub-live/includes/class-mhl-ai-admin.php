<?php
if (!defined('ABSPATH')) { exit; }

class MHL_AI_Admin {
    public static function init(): void {
        add_action('add_meta_boxes', array(__CLASS__, 'boxes'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }

    public static function boxes(): void {
        if (MHL_AI::enabled() && current_user_can('manage_options')) {
            add_meta_box('mhl-ai', 'Volitelný AI asistent — pilot', array(__CLASS__, 'render'), 'mhl_question', 'normal', 'default');
        }
    }

    public static function assets(): void {
        if (!MHL_AI::enabled() || !current_user_can('manage_options') || (get_current_screen()->post_type ?? '') !== 'mhl_question') { return; }
        wp_enqueue_script('mhl-ai-admin', MHL_URL.'assets/ai-admin.js', array(), MHL_VERSION.'-ai-pilot-1', true);
    }

    public static function render(WP_Post $post): void {
        $enabled = !get_user_meta(get_current_user_id(), 'mhl_ai_disabled', true);
        ?>
        <div id="mhl-ai-panel" data-question-id="<?php echo (int)$post->ID; ?>" data-endpoint="<?php echo esc_url(rest_url('mhl/v1/ai/')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>" data-enabled="<?php echo $enabled ? '1' : '0'; ?>">
            <p>Asistent odešle do OpenAI pouze níže zobrazený text otázky a možnosti odpovědi. Před odesláním je zkontrolujte a vynechte osobní údaje. Návrh vždy zkontrolujte také věcně.</p>
            <p><button type="button" class="button" data-ai-toggle><?php echo $enabled ? 'Vypnout AI pro můj účet' : 'Zapnout AI pro můj účet'; ?></button></p>
            <fieldset data-ai-controls <?php disabled(!$enabled); ?>>
                <label>Úprava <select data-ai-operation><option value="rephrase">Přeformulovat otázku</option><option value="translate">Přeložit otázku a odpovědi</option></select></label>
                <label data-ai-language-label hidden>Cílový jazyk <select data-ai-language><option value="cs">Čeština</option><option value="en">Angličtina</option></select></label>
                <p><button type="button" class="button" data-ai-preview>Zkontrolovat obsah k odeslání</button></p>
                <div data-ai-source-box hidden><strong>Obsah k odeslání</strong><pre data-ai-source style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><button type="button" class="button" data-ai-send>Odeslat do OpenAI a navrhnout úpravu</button></div>
            </fieldset>
            <p role="status" aria-live="polite" data-ai-status></p>
            <div data-ai-result-box hidden><strong>Návrh — dosud neuložený</strong><pre data-ai-result style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
                <button type="button" class="button button-primary" data-ai-apply>Použít návrh v editoru</button>
                <button type="button" class="button" data-ai-discard>Zahodit návrh</button>
                <p>Po použití lze text dále upravit. Uložení nebo publikování proveďte běžným tlačítkem WordPressu.</p>
            </div>
        </div>
        <?php
    }
}
