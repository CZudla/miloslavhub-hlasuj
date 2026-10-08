<?php
require_once __DIR__.'/ui-messages.php';
/** UI language only. Never pass teacher content or participant input to mhl_ui_text(). */
function mhl_ui_language(): string {
    static $language=null;
    if ($language!==null) { return $language; }
    $requested=$_GET['lang']??$_COOKIE['mhl_ui_lang']??'cs';
    $language=is_string($requested)&&in_array($requested,array('en','en-US','en_US'),true)?'en':'cs';
    if (isset($_GET['lang']) && is_string($_GET['lang']) && in_array($_GET['lang'],array('en','cs','en-US','cs-CZ'),true) && !headers_sent()) {
        setcookie('mhl_ui_lang',$language,array('expires'=>time()+31536000,'path'=>'/','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'httponly'=>true,'samesite'=>'Lax'));
    }
    return $language;
}
function mhl_ui_catalog(): array {
    static $catalog=null;
    if ($catalog===null) { $catalog=json_decode(file_get_contents(__DIR__.'/lang/en.json'),true,512,JSON_THROW_ON_ERROR); }
    return $catalog;
}
function mhl_ui_text(string $static): string {
    if (mhl_ui_language()!=='en') { return $static; }
    return MHL_UI_Messages::replace($static,mhl_ui_catalog());
}
function mhl_ui_bootstrap(): void {
    echo '<script id="mhl-ui-catalog" type="application/json">'.json_encode(array('language'=>mhl_ui_language(),'messages'=>mhl_ui_language()==='en'?mhl_ui_catalog():new stdClass()),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script><script src="/assets/i18n.js"></script>';
}
function mhl_ui_html(string $literal): string {
    return htmlspecialchars(mhl_ui_text($literal),ENT_QUOTES,'UTF-8',false);
}
