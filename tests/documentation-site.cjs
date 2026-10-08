const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const base=process.argv[2];
if(!/^http:\/\/127\.0\.0\.1:\d+$/.test(base)&&base!=='https://hlasuj.miloslavhub.cz')throw Error('Explicit local or Hlasuj documentation host required');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 const context=await browser.newContext();const external=[],errors=[];let checks=0;
 await context.route('**/*',r=>{if(new URL(r.request().url()).origin!==base){external.push(r.request().url());return r.abort();}return r.continue();});
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 const check=(v,m)=>{assert(v,m);checks++;};
 fs.mkdirSync('runtime/screenshots',{recursive:true});
 try{
  for(const [lang,path,title] of [['cs','/docs/','Příručky pro výuku. Podklady pro správu.'],['en','/docs/en/','Guides for teaching. References for running Hlasuj!.']]){
   const response=await page.goto(base+path);check(response.status()===200,'Documentation HTTP 200');
   check(await page.locator('html').getAttribute('lang')===lang,'Correct document language');
   check(await page.locator('h1').innerText()===title,'Role-oriented documentation headline');
   check(await page.locator('.role-card').count()===3,'Teacher, student and administrator entry points');
   check((await page.locator('.version').innerText()).includes('0.8.9'),'Release baseline is explicit');
   check((await page.locator('.version').innerText()).includes('AUTH'),'Unfinished integration remains explicit');
   check(await page.locator('a[href*="Hlasuj-0.8.9-prirucky.pdf"]').count()===1,'Qualified PDF has a version-specific link');
   check(await page.locator('a[href="mailto:hlasuj@miloslavhub.cz"]').count()===1,'Product support contact');
   check(await page.locator('script,iframe,form').count()===0,'Static documentation makes no analytics, embed or form requests');
   await page.setViewportSize({width:1440,height:1000});await page.screenshot({path:`runtime/screenshots/documentation-${lang}-desktop.png`,fullPage:true});
   await page.setViewportSize({width:390,height:844});
   check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow on a phone');
   await page.screenshot({path:`runtime/screenshots/documentation-${lang}-mobile.png`,fullPage:true});
   const other=lang==='cs'?'en':'cs';await page.locator(`nav a[lang="${other}"]`).click();
   check(await page.locator('html').getAttribute('lang')===other,'Language navigation reaches the other actual page');
  }
  check(external.length===0,'No third-party resource requests');check(errors.length===0,'No JavaScript errors');
  console.log(JSON.stringify({status:'passed',checks,scope:'Static documentation pages only; not full application qualification',external_requests:external.length,production_data_used:false}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
