<?php
declare(strict_types=1);
define('ABSPATH',__DIR__);
$GLOBALS['test_admin']=false;$GLOBALS['test_locale']='cs_CZ';
function is_admin(): bool { return $GLOBALS['test_admin']; }
function get_user_locale(): string { return $GLOBALS['test_locale']; }
require __DIR__.'/../wordpress/miloslavhub-live/includes/class-mhl-i18n.php';
require __DIR__.'/../frontend/i18n.php';
$_GET['lang']='en';$_GET['ui_lang']='en';$checks=0;
function i18n_check(bool $ok,string $message): void { global $checks;$checks++;if(!$ok){throw new RuntimeException($message);} }
i18n_check(mhl_ui_language()==='en','Explicit public language');
i18n_check(mhl_ui_text('Vysvětlení')==='Explanation','Public PHP translation');
i18n_check(MHL_I18n::text('Vysvětlení')==='Explanation','Public API translation');
i18n_check(MHL_I18n::text('identifier_Vysvětlení_end')==='identifier_Vysvětlení_end','Identifier boundary');
i18n_check(MHL_I18n::html('<p>Vysvětlení</p>')==='<p>Explanation</p>','Static HTML markup preserved');
i18n_check(MHL_I18n::html('\">Zobrazit náhled</a>')==='\">Show preview</a>','HTML fragments preserve attribute delimiters');
i18n_check(MHL_I18n::html('<p>Osobní produktová identita autora pro výuku, školení a prezentace.</p>')==='<p>The author&#039;s product identity for teaching, training and presentations.</p>','Translated apostrophes are escaped in HTML');
$GLOBALS['test_admin']=true;
i18n_check(MHL_I18n::text('Vysvětlení')==='Vysvětlení','Public language cannot override Czech administrator locale');
$GLOBALS['test_locale']='en_GB';
i18n_check(MHL_I18n::text('Vysvětlení')==='Explanation','English WordPress locale');
ob_start();mhl_ui_bootstrap();$html=ob_get_clean();
i18n_check(!str_contains($html,"The author's"),'Embedded catalog escapes apostrophes');
i18n_check(substr_count($html,'</script>')===2,'Catalog cannot terminate its JSON script');
// Audit explicitly marked PHP UI literals; authored content never enters these helpers.
$missing=array();$audited=0;
$paths=array_merge(glob(__DIR__.'/../wordpress/miloslavhub-live/includes/*.php'),array(__DIR__.'/../frontend/index.php',__DIR__.'/../frontend/demo/index.php',__DIR__.'/../frontend/demo/mobile.php',__DIR__.'/../frontend/demo/lib.php',__DIR__.'/../frontend/demo/api.php'));
foreach($paths as $path){
    $source=file_get_contents($path);$offset=0;
    foreach(token_get_all($source) as $token){
        $raw=is_array($token)?$token[1]:$token;
        if(is_array($token)&&$token[0]===T_CONSTANT_ENCAPSED_STRING&&preg_match('/(?:esc_html_mhl_literal|mhl_ui_(?:text|html)|MHL_I18n::(?:text|html)|__|esc_html__|esc_attr__)\(\s*$/',substr($source,max(0,$offset-85),min(85,$offset)))){
            $value=substr($raw,1,-1);
            $value=$raw[0]==="'"?str_replace(array("\\'","\\\\"),array("'","\\"),$value):stripcslashes($value);
            $audited++;
            if(preg_match('/[áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]/u',MHL_UI_Messages::replace($value,mhl_ui_catalog()))){$missing[]=basename($path).': '.$value;}
        }
        $offset+=strlen($raw);
    }
}
i18n_check($audited>400&&!$missing,'Marked PHP UI literals have English coverage: '.implode('; ',array_slice($missing,0,3)));
echo json_encode(array('status'=>'passed','checks'=>$checks,'marked_ui_literals_audited'=>$audited)),"\n";
