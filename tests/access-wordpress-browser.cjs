const {chromium}=require('playwright'),assert=require('node:assert/strict');
const base=process.argv[2],fixture=JSON.parse(process.argv[3]||'{}'),password=process.env.MHL_TEST_ADMIN_PASSWORD;
if(!/^http:\/\/127\.0\.0\.1:\d+$/.test(base)||!password||!fixture.a)throw new Error('Isolated fixture required');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});let checks=0;const errors=[],external=[];
 const check=(ok,message)=>{assert(ok,message);checks++;};
 async function login(name){
  const context=await browser.newContext();await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():(external.push(new URL(route.request().url()).hostname),route.abort()));
  const page=await context.newPage();page.on('pageerror',error=>errors.push(page.url()+': '+error.stack));
  await page.goto(base+'/wp-login.php');await page.locator('#user_login').fill('acl-'+name);await page.locator('#user_pass').fill(password);
  await Promise.all([page.waitForURL('**/wp-admin/**'),page.locator('#wp-submit').click()]);
  // WordPress sends these restricted accounts to profile.php. Its beforeunload
  // handler assumes the ready callback initialized $form; finish ready before navigating.
  await page.evaluate(()=>new Promise(resolve=>jQuery(()=>resolve())));
  return {context,page};
 }
 try {
  let {context,page}=await login('a');
  await page.goto(base+'/wp-admin/edit.php?post_type=mhl_question');
  check(await page.locator('#post-'+fixture.a.question).count()===1,'Teacher list contains own/shared question');
  check(await page.locator('#post-'+fixture.c.question).count()===0,'Teacher list excludes foreign organization');
  let response=await page.goto(base+'/wp-admin/post.php?post='+fixture.c.question+'&action=edit');
  check(response.status()===403,'Direct foreign editor URL denied');
  response=await page.goto(base+'/wp-admin/options-general.php');
  check(response.status()===403,'Teacher cannot administer WordPress settings');
  await page.goto(base+'/wp-admin/post.php?post='+fixture.a.question+'&action=edit');
  check(await page.locator('#title').inputValue()==='Synthetic a question','Authorized teacher editor loads');
  const nonce=await page.locator('#mhl-ai-panel').getAttribute('data-nonce');
  const activation=await page.evaluate(async({nonce})=>{
   const response=await fetch('/index.php?rest_route=%2Fmhl%2Fv1%2Factivate',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':nonce},body:JSON.stringify({lecture_slug:'acl-c-lecture',question_slug:'acl-c-question',mode:'live'})});
   return {status:response.status,payload:await response.json()};
  },{nonce});
  check(activation.status===403,'Authenticated REST request cannot activate foreign lesson');
  await context.close();
  ({context,page}=await login('viewer'));
  await page.goto(base+'/wp-admin/admin.php?page=mhl-live-control&run_id='+fixture.run_id);
  check(await page.locator('.mhl-live-item').count()>0,'Viewer can see explicitly shared live results');
  check(await page.locator('a[href*="action=mhl_open_session"],a[href*="action=mhl_close_session"],a[href*="action=mhl_reset_session"],a[href*="action=mhl_finish_run"]').count()===0,'Viewer sees no teaching mutation buttons');
  await page.goto(base+'/wp-admin/admin.php?page=mhl-live-control');
  check(await page.locator('a[href*="action=mhl_start_run"]').count()===0,'Viewer sees no start buttons');
  response=await page.goto(base+'/wp-admin/post.php?post='+fixture.a.question+'&action=edit');
  check(response.status()===403,'Viewer cannot enter shared question editor');
  await context.close();
  ({context,page}=await login('b'));
  await page.goto(base+'/wp-admin/post.php?post='+fixture.a.question+'&action=edit');
  await page.getByText('No correct answer',{exact:true}).waitFor();checks++;
  check(await page.getByText('Correct answer explanation',{exact:true}).count()===1,'English quiz explanation label exists while poll controls keep it hidden');
  check(await page.locator('#title').inputValue()==='Synthetic a question','English editor preserves teacher text');
  check((await page.locator('#adminmenu').innerText()).includes('Live controls'),'English application navigation');
  check(await page.locator('input[name="mhl_options[]"]').first().getAttribute('placeholder')==='Answer A','English option placeholder is separate from content');
  let aiLanguage=null;
  const invalidSuggestion=url=>url.searchParams.get('rest_route')==='/mhl/v1/ai/suggest'||url.pathname.endsWith('/ai/suggest');
  await page.route(invalidSuggestion,route=>{
   aiLanguage=new URL(route.request().url()).searchParams.get('ui_lang');
   // Real authenticated REST validation, deliberately rejected before any provider request.
   return route.continue({postData:JSON.stringify({...route.request().postDataJSON(),operation:'invalid-fixture-operation'})});
  });
  await page.locator('[data-ai-preview]').click();await page.locator('[data-ai-send]').click();
  await page.getByText('Check the instructions, language and text length. Plain text is supported.',{exact:true}).waitFor();checks++;
  check(aiLanguage==='en','English teacher editor sends UI language to private REST');
  check(await page.locator('#title').inputValue()==='Synthetic a question','Rejected English AI request preserves teacher content');
  await page.unroute(invalidSuggestion);
  await page.goto(base+'/wp-admin/post.php?post='+fixture.b.subject+'&action=edit');
  check((await page.locator('body').innerText()).includes('Semester / group'),'English subject settings');
  check(await page.locator('input[name="mhl_subject_short_title"]').getAttribute('placeholder')==='e.g. OOP','English subject placeholder');
  response=await page.goto(base+'/wp-admin/admin.php?page=mhl-workspaces');
  check(response.status()===200,'Organization page has a registered WordPress menu hook for teachers');
  await page.getByRole('heading',{name:'Organisations and sharing',exact:true}).waitFor();checks++;
  check(await page.locator('input[name="user"][type="number"]').count()===0,'Sharing uses a teacher username rather than technical numeric IDs');
  check(!(await page.locator('body').innerText()).includes('Synthetic organization B'),'Organization management does not disclose foreign organization names');
  check(errors.length===0,'No management JavaScript errors: '+errors.join('; '));
  check(external.every(host=>host==='secure.gravatar.com'||host==='www.gravatar.com'),'Only blocked WordPress avatar requests; no application external requests');
  console.log(JSON.stringify({status:'passed',checks,accounts:3,organizations:2,external_requests_blocked:external.length,backend:'real isolated WordPress and MariaDB'}));
  await context.close();
 } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exit(1);});
