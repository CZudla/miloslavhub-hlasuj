const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const catalog=JSON.parse(fs.readFileSync('frontend/lang/en.json','utf8'));let checks=0;
const check=(condition,message)=>{assert(condition,message);checks++;};
check(fs.readFileSync('frontend/lang/en.json','utf8')===fs.readFileSync('wordpress/miloslavhub-live/lang/en.json','utf8'),'Frontend and plugin catalogs must match');
check(fs.readFileSync('frontend/assets/i18n.js','utf8')===fs.readFileSync('wordpress/miloslavhub-live/assets/i18n.js','utf8'),'UI translation runtimes must match');
check(fs.readFileSync('frontend/ui-messages.php','utf8')===fs.readFileSync('wordpress/miloslavhub-live/includes/class-mhl-ui-messages.php','utf8'),'PHP translation runtimes must match');
for(const language of ['cs','en']){
 const ctx={document:{getElementById:()=>null},MHLUIConfig:{language,messages:language==='en'?catalog:{}},Intl};
 vm.runInNewContext(fs.readFileSync('frontend/assets/i18n.js','utf8'),ctx);
 const ui=ctx.MHLUI;
 check(ui.text('Vysvětlení')===(language==='en'?'Explanation':'Vysvětlení'),'Explicit UI translation');
 check(ui.text('role_Vysvětlení_internal')==='role_Vysvětlení_internal','Identifiers are never changed by fragment matching');
 check(ui.text('Neplatný identifikátor relace.')===(language==='en'?'Invalid session identifier.':'Neplatný identifikátor relace.'),'Full message takes precedence over fragments');
 const participant='Přezdívka <img src=x onerror=alert(1)>',question='Vysvětlení správné odpovědi';
 check(ui.html(['<label>Přezdívka</label><p>','</p><h2>','</h2>'],participant,question).endsWith('<p>'+participant+'</p><h2>'+question+'</h2>'),'Dynamic participant and teacher content are preserved exactly');
 check(ui.number(4.27,2)===(language==='en'?'4.27':'4,27'),'Locale controls decimal separator');
 check(ui.language===language,'Explicit language retained');
 check(ui.text('unmapped English text')==='unmapped English text','Unknown literals remain intact');
}
check(Object.entries(catalog).every(([key,value])=>key.length>0&&typeof value==='string'&&value.length>0),'Catalog has no empty or nontext message');
console.log(JSON.stringify({status:'passed',checks,catalog_messages:Object.keys(catalog).length,scope:'UI literals, locale formatting and dynamic content boundary'}));
