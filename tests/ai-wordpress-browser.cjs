const {chromium}=require('playwright');
const assert=require('node:assert/strict');
const base=process.argv[2], qid=Number(process.argv[3]);
if(!/^http:\/\/127\.0\.0\.1:\d+$/.test(base)||!qid)throw new Error('Synthetic loopback site required');
(async()=>{
  const browser=await chromium.launch({channel:'msedge',headless:true});
  const context=await browser.newContext();
  const external=[],errors=[];
  await context.route('**/*',route=>{
    const url=new URL(route.request().url());
    if(url.origin!==base){external.push(url.origin);return route.abort();}
    return route.continue();
  });
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  page.on('dialog',dialog=>dialog.accept());
  let checks=0,suggestions=0;
  page.on('request',r=>{if(r.method()==='POST'&&(r.url().includes('/ai/suggest')||r.url().includes('ai%2Fsuggest')))suggestions++;});
  const check=(value,message)=>{assert(value,message);checks++;};
  const edit=base+`/wp-admin/post.php?post=${qid}&action=edit`;
  const panel=page.locator('#mhl-ai-panel');
  const ready=async()=>page.locator('[data-ai-result-box]').waitFor({state:'visible'});
  const suggest=async()=>{await page.locator('[data-ai-preview]').click();await page.locator('[data-ai-send]').click();await ready();};
  try{
    await page.goto(base+'/wp-login.php');
    await page.locator('#user_login').fill(process.env.MHL_TEST_ADMIN_LOGIN);
    await page.locator('#user_pass').fill(process.env.MHL_TEST_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
    await page.goto(edit);await panel.waitFor();check(await panel.isVisible(),'Actual WordPress metabox is visible');
    await page.locator('[data-ai-preview]').click();
    check(suggestions===0,'Preview performs no suggestion request');
    check((await page.locator('[data-ai-source]').textContent()).includes('První odpověď'),'Preview uses real option inputs');
    await page.locator('[data-ai-send]').click();await ready();
    check(await page.locator('#title').inputValue()==='Původní syntetická otázka?','Response leaves editor unchanged');
    await page.locator('[data-ai-apply]').click();
    check(await page.locator('#title').inputValue()==='Upravená syntetická otázka?','Explicit apply changes editor');
    await page.goto(edit);await panel.waitFor();
    check(await page.locator('#title').inputValue()==='Původní syntetická otázka?','Apply without WordPress save leaves stored question unchanged');
    await page.waitForTimeout(3100);
    await page.locator('[data-ai-operation]').selectOption('translate');
    await page.locator('[data-ai-language]').selectOption('en');
    await suggest();
    check((await page.locator('[data-ai-result]').textContent()).includes('Translated synthetic question?'),'Translation returned through actual authenticated REST route');
    await page.locator('[data-ai-apply]').click();
    check(await page.locator('input[name="mhl_options[]"]').first().inputValue()==='Translated První odpověď','Explicit apply changes translated options');
    await Promise.all([page.waitForURL(url=>['1','6'].includes(url.searchParams.get('message'))),page.locator('#publish').click()]);
    await page.goto(edit);await panel.waitFor();
    check(await page.locator('#title').inputValue()==='Translated synthetic question?','Ordinary WordPress save persists approved title');
    check(await page.locator('input[name="mhl_options[]"]').first().inputValue()==='Translated První odpověď','Ordinary WordPress save persists approved options');
    await page.locator('[data-ai-toggle]').click();
    await page.waitForFunction(()=>document.querySelector('[data-ai-status]').textContent==='AI je pro váš účet vypnutá.');
    await page.goto(edit);await panel.waitFor();
    check(await panel.getAttribute('data-enabled')==='0' && await page.locator('[data-ai-preview]').isDisabled(),'Personal disable survives a new page request');
    check(suggestions===2,'Exactly two explicit suggestion requests');
    check(errors.length===0,'No browser JavaScript errors');
    // WordPress can reference Gravatar; those requests are blocked and are unrelated to the provider.
    console.log(JSON.stringify({status:'passed',checks,actual_wordpress_admin:true,external_requests_blocked:external.length,paid_api_calls:0}));
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
