const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const base=process.argv[2],id=Number(process.argv[3]);
if(!/^http:\/\/127\.0\.0\.1:\d+$/.test(base)||!Number.isSafeInteger(id)||id<1)throw new Error('Synthetic loopback fixture required');
(async()=>{
  const browser=await chromium.launch({channel:'msedge',headless:true});
  const context=await browser.newContext({viewport:{width:1280,height:900}});
  const external=[],errors=[];
  await context.route('**/*',route=>{
    if(new URL(route.request().url()).origin===base)return route.continue();
    external.push(route.request().url()); return route.abort();
  });
  const page=await context.newPage(); page.on('pageerror',e=>errors.push(e.message));
  let checks=0;const check=(ok,message)=>{assert(ok,message);checks++;};
  try{
    await page.goto(base+'/wp-login.php');
    await page.locator('#user_login').fill(process.env.MHL_TEST_ADMIN_LOGIN);
    await page.locator('#user_pass').fill(process.env.MHL_TEST_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
    await page.goto(base+`/wp-admin/post.php?post=${id}&action=edit`);
    await page.getByText('Vysvětlení správné odpovědi',{exact:true}).click();
    await page.getByLabel('Vysvětlení',{exact:true}).fill('Řešení z prohlížeče.\nDruhý řádek \\ a apostrof \'');
    await page.getByLabel('Kdo uvidí vysvětlení?').selectOption('teacher_only');
    await page.getByText('Moje poznámka k výuce',{exact:true}).click();
    await page.getByLabel('Soukromá poznámka',{exact:true}).fill('PRIVATE-BROWSER-NOTE');
    await Promise.all([page.waitForURL('**message=1**'),page.locator('#publish').click()]);
    check((await page.locator('#mhl-correct-explanation').inputValue()).includes('Druhý řádek \\ a apostrof \''),'Actual editor preserves lines and slashes');
    check(await page.locator('#mhl-explanation-mode').inputValue()==='teacher_only','Private policy persists');
    check(await page.locator('#mhl-teacher-note').inputValue()==='PRIVATE-BROWSER-NOTE','Private teacher note persists');
    await page.getByText('Vysvětlení správné odpovědi',{exact:true}).click();
    await page.getByText('Moje poznámka k výuce',{exact:true}).click();
    await page.screenshot({path:'runtime/screenshots/teacher-feedback-editor.png',fullPage:true});
    await page.locator('input[name="mhl_correct_index"][value="-1"]').check();
    check(!await page.locator('#mhl-correct-explanation').isVisible(),'Poll hides quiz explanation settings');
    check(await page.locator('#mhl-teacher-note').isVisible(),'Poll retains private teaching note');
    check(await page.locator('input[name="mhl_poll_points"]').count()===0,'Teacher cannot enable poll participation points');
    check(await page.getByText('Anketa nemá správnou odpověď a nepřidává soutěžní body.',{exact:true}).isVisible(),'Teacher sees a neutral poll explanation');
    await page.goto(base+'/wp-admin/admin.php?page=mhl-live-demo');
    const qr=page.locator('.mhl-qr[data-qr-ready="1"] img').first();
    await qr.waitFor({state:'visible'});
    check(await qr.evaluate(img=>img.src.startsWith('data:image/png;')&&img.complete&&img.naturalWidth>=100),'WordPress administration displays a locally generated QR image');
    check(!external.some(url=>url.includes('qrserver.com')),'No QR link is sent to an external generator');
    check(errors.length===0,'No JavaScript errors');
    console.log(JSON.stringify({status:'passed',checks,actual_wordpress_admin:true,external_qr_requests:0}));
  }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
