<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/class-mhl-ui-messages.php';

/** Translate compile-time UI literals; content and public teacher metadata are never arguments. */
final class MHL_I18n {
    public static function init(): void { add_filter('gettext',array(__CLASS__,'gettext'),20,3); }
    public static function assets(): void {
        wp_enqueue_script('mhl-i18n',MHL_URL.'assets/i18n.js',array(),MHL_VERSION,true);
        $catalog=self::english()?json_decode(file_get_contents(dirname(__DIR__).'/lang/en.json'),true,512,JSON_THROW_ON_ERROR):new stdClass();
        wp_add_inline_script('mhl-i18n','globalThis.MHLUIConfig='.wp_json_encode(array('language'=>self::english()?'en':'cs','messages'=>$catalog),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
    }
    public static function english(): bool {
        if (function_exists('is_admin') && is_admin()) { return function_exists('get_user_locale') && str_starts_with(get_user_locale(),'en'); }
        $requested=$_GET['ui_lang']??'';
        return is_string($requested) && in_array($requested,array('en','en-US'),true);
    }
    public static function text(string $static,bool $html=false): string {
        if (!self::english()) { return $static; }
        static $catalog=null;
        if ($catalog===null) {
            $catalog=json_decode(file_get_contents(dirname(__DIR__).'/lang/en.json'),true,512,JSON_THROW_ON_ERROR);
        }
        return MHL_UI_Messages::replace($static,$catalog,$html);
    }
    public static function html(string $static): string {
        // Only compile-time HTML literals enter here. Preserve markup; escape translated text.
        return self::text($static,true);
    }
    public static function gettext(string $translation,string $text,string $domain): string {
        return $domain==='miloslavhub-live'?self::text($text):$translation;
    }
}

function esc_html_mhl_literal(string $static): string {
    return htmlspecialchars(MHL_I18n::text($static),ENT_QUOTES,'UTF-8',false);
}
