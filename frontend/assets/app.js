(() => {
  'use strict';
  const root=document.getElementById('mhl-app'),screen=document.getElementById('screen'); if(!root||!screen)return;
  const cfg={view:root.dataset.view||'home',mode:root.dataset.mode||'live',lecture:root.dataset.lecture||'',question:root.dataset.question||'',subject:root.dataset.subject||'',projectionToken:root.dataset.projectionToken||'',api:root.dataset.api||'',mainSite:root.dataset.mainSite||'https://miloslavhub.cz',frontend:root.dataset.frontend||'https://hlasuj.miloslavhub.cz'};
  let timer=null,countdownTimer=null,currentLecture=cfg.lecture,currentQuestion=cfg.question,currentSubject=cfg.subject||localStorage.getItem('mhl_joined_subject')||'',lastCurrentStatus='',lastCurrentSession=null,serverOffsetMs=0;
  let activeBrand={template:'neutral',name:'Hlasuj!',subtitle:'by MiloslavHub · Interaktivní hlasování pro výuku',url:'',logo:'',primary:'#1f2937',accent:'#475569',soft:'#f1f5f9',showMiloslavHub:false};
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  function uuid(){if(globalThis.crypto&&crypto.randomUUID)return crypto.randomUUID();const b=new Uint8Array(24);if(globalThis.crypto&&crypto.getRandomValues)crypto.getRandomValues(b);else for(let i=0;i<b.length;i++)b[i]=Math.floor(Math.random()*256);return Array.from(b,x=>x.toString(16).padStart(2,'0')).join('');}
  function participant(){let id=localStorage.getItem('mhl_participant_id');if(!id){id=uuid();localStorage.setItem('mhl_participant_id',id);}return id;}
  function nicknameKey(subject=currentSubject){return subject?`mhl_nickname_${subject}`:'mhl_nickname';}
  function nickname(){const scoped=(localStorage.getItem(nicknameKey())||'').trim();if(scoped)return scoped;const legacy=(localStorage.getItem('mhl_nickname')||'').trim();return legacy;}
  function saveNickname(nick,subject=currentSubject){if(subject)localStorage.setItem(`mhl_nickname_${subject}`,nick);else localStorage.setItem('mhl_nickname',nick);}
  function clearNickname(subject=currentSubject){if(subject)localStorage.removeItem(`mhl_nickname_${subject}`);else localStorage.removeItem('mhl_nickname');}
  function privacyLink(subject=currentSubject){return subject?`${cfg.frontend}/privacy/${encodeURIComponent(subject)}`:`${cfg.frontend}/privacy`;}
  function privacyMini(q={}){
    const subject=q.subject_slug||currentSubject;
    const href=q.privacy_url||privacyLink(subject);
    const anonymous=!(q.gamification&&q.gamification.nickname_required);
    const text=anonymous
      ? 'Odesláním odpovědi se uloží hlas a technický pseudonymní identifikátor. U nebodované ankety se k odpovědi nepřipojuje přezdívka.'
      : 'Pro průběh soutěže se používá přezdívka a pseudonymní technický identifikátor. Zveřejnění přezdívky v Síni slávy je samostatná dobrovolná volba.';
    return `<div class="privacy-mini"><span>${esc(text)}</span><a href="${esc(href)}">Soukromí a moje data</a></div>`;
  }

  async function api(path,options={}){
    const req={method:options.method||'GET',credentials:'omit',cache:'no-store'};
    if(options.body!==undefined){req.body=options.body;req.headers={'Content-Type':'text/plain;charset=UTF-8',...(options.headers||{})};}
    else if(options.headers){req.headers=options.headers;}
    const res=await fetch(cfg.api+path,req);let data=null;try{data=await res.json();}catch(_){}
    if(!res.ok){const e=new Error((data&&data.message)||'Požadavek se nepodařil.');e.status=res.status;e.code=(data&&data.code)||'';e.data=data;throw e;}return data;
  }
  const state=s=>s==='joining'?'Připojují se studenti':s==='open'?'Hlasování je otevřené':s==='closed'?'Hlasování je ukončené':s==='waiting'?'Otázka čeká na aktivaci':s==='skipped'?'Otázka byla přeskočena':'Otázka nyní není aktivní';
  function brandProfile(q){const t=q?.subject_template||{};return {template:t.brand_template||'neutral',name:t.brand_name||'Hlasuj',subtitle:t.brand_subtitle||'by MiloslavHub · Interaktivní hlasování pro výuku',url:t.brand_url||'',logo:t.brand_logo_url||'',primary:t.primary_color||'#1f2937',accent:t.accent_color||'#475569',soft:t.accent_soft||'#f1f5f9',showMiloslavHub:!!t.show_miloslavhub};}
  function applyBrand(q){activeBrand=brandProfile(q);const st=document.documentElement.style;st.setProperty('--text',activeBrand.primary);st.setProperty('--accent',activeBrand.accent);st.setProperty('--accent-soft',activeBrand.soft);st.setProperty('--bar',activeBrand.accent);const b=document.getElementById('site-brand'),sub=document.getElementById('site-brand-sub');if(b){b.textContent=activeBrand.name;if(activeBrand.url){b.href=activeBrand.url;b.removeAttribute('aria-disabled');}else{b.removeAttribute('href');b.setAttribute('aria-disabled','true');}}if(sub)sub.textContent=activeBrand.subtitle;document.body.dataset.brand=activeBrand.template;}
  function brandInline(){const logo=activeBrand.logo?`<img class="inline-brand-logo" src="${esc(activeBrand.logo)}" alt="">`:'';const name=activeBrand.url?`<a href="${esc(activeBrand.url)}">${esc(activeBrand.name)}</a>`:esc(activeBrand.name);return `${logo}<strong>${name}</strong><span>${esc(activeBrand.subtitle)}</span>`;}
  function brandFooter(){const name=activeBrand.url?`<a href="${esc(activeBrand.url)}">${esc(activeBrand.name)}</a>`:esc(activeBrand.name);return `<div class="footer-brand ${esc(activeBrand.template)}"><strong>${name}</strong><span>${esc(activeBrand.subtitle)}</span></div>`;}

  function hideEmptyControls(){screen.querySelectorAll('button,a.button').forEach(el=>{const hasVisual=el.querySelector('img,svg,[data-icon]');const hasLabel=(el.getAttribute('aria-label')||'').trim()!=='';if(!hasVisual&&!hasLabel&&(el.textContent||'').trim()===''){el.hidden=true;el.setAttribute('aria-hidden','true');el.tabIndex=-1;}});}
  const emptyControlObserver=new MutationObserver(()=>hideEmptyControls());emptyControlObserver.observe(screen,{childList:true,subtree:true});
  function stopCountdown(){if(countdownTimer){clearInterval(countdownTimer);countdownTimer=null;}}
  function countdownMarkup(x,compact=false){if(x.status!=='open'||!x.closes_at_ms)return'';return `<div class="countdown ${compact?'compact':''}" data-countdown><span class="countdown-label">Zbývá</span><strong data-countdown-value>--</strong><span class="countdown-unit">s</span></div>`;}
  function startCountdown(x,onExpire){stopCountdown();if(x.status!=='open'||!x.closes_at_ms)return;if(Number.isFinite(x.server_now_ms))serverOffsetMs=Number(x.server_now_ms)-Date.now();let expired=false;const tick=()=>{const el=screen.querySelector('[data-countdown]'),value=screen.querySelector('[data-countdown-value]');if(!el||!value){stopCountdown();return;}const now=Date.now()+serverOffsetMs;const left=Math.max(0,(Number(x.closes_at_ms)-now)/1000);value.textContent=left>10?String(Math.ceil(left)):left.toFixed(1).replace('.',',');el.classList.toggle('urgent',left<=10);el.classList.toggle('critical',left<=5);if(left<=0&&!expired){expired=true;stopCountdown();setTimeout(()=>onExpire&&onExpire(),250);}};tick();countdownTimer=setInterval(tick,200);}
  function ctas(c){if(!c)return'';let a='';if(c.materials_url)a+=`<a class="button" href="${esc(c.materials_url)}">Materiály k přednášce</a>`;if(c.assistant_url)a+=`<a class="button secondary" href="${esc(c.assistant_url)}">AI asistent</a>`;if(activeBrand.showMiloslavHub&&c.main_site_url)a+=`<a class="text-link" href="${esc(c.main_site_url)}">miloslavhub.cz</a>`;return a?`<div class="cta-row">${a}</div>`:'';}
  function teachers(q){if(!Array.isArray(q.teachers)||!q.teachers.length)return'';const list=q.teachers.map(t=>{const name=t.profile_url?`<a href="${esc(t.profile_url)}">${esc(t.name)}</a>`:esc(t.name);return `${name}${t.role?` <span>(${esc(t.role)})</span>`:''}`;}).join(' · ');return `<div class="teachers"><strong>Vyučující:</strong> ${list}</div>`;}
  function subjectHeader(q){const t=q.subject_template||{},tpl=['standard','competition','minimal'].includes(t.template)?t.template:'standard',name=t.short_title||q.subject_title||'',meta=[t.code,t.period].filter(Boolean).map(esc).join(' · '),competition=t.competition_title||'',extra=t.extra_info||'';if(tpl==='minimal'){return `<div class="subject-header template-minimal">${name?`<div class="subject">${esc(name)}</div>`:''}${meta?`<div class="subject-meta">${meta}</div>`:''}${teachers(q)}</div>`;}if(tpl==='competition'){return `<div class="subject-header template-competition">${competition?`<div class="competition-title">${esc(competition)}</div>`:''}${name?`<div class="subject">${esc(name)}</div>`:''}${meta?`<div class="subject-meta">${meta}</div>`:''}${q.lecture_title?`<div class="lecture">${esc(q.lecture_title)}</div>`:''}${teachers(q)}${extra?`<div class="subject-extra">${esc(extra)}</div>`:''}</div>`;}return `<div class="subject-header template-standard">${name?`<div class="subject">${esc(name)}</div>`:''}${meta?`<div class="subject-meta">${meta}</div>`:''}${q.lecture_title?`<div class="lecture">${esc(q.lecture_title)}</div>`:''}${teachers(q)}${extra?`<div class="subject-extra">${esc(extra)}</div>`:''}</div>`;}
  function header(q){applyBrand(q);return `${cfg.mode==='test'?'<div class="test-badge">TESTOVACÍ REŽIM</div>':''}${subjectHeader(q)}`;}
  function pathFor(view,question){const prefix=cfg.mode==='test'?(view==='results'?'test-results':'test'):(cfg.mode==='async'?(view==='results'?'poll-results':'poll'):(view==='results'?'r':'q'));return `/${prefix}/${encodeURIComponent(currentLecture)}/${encodeURIComponent(question)}`;}
  function setContext(lecture,question,view=cfg.view){let changed=false;if(lecture&&lecture!==currentLecture){currentLecture=lecture;changed=true;}if(question&&question!==currentQuestion){currentQuestion=question;changed=true;}if(changed&&currentLecture&&currentQuestion){try{history.replaceState(null,'',pathFor(view,currentQuestion));}catch(_){}}return changed;}
  function rememberContext(subject,lecture){if(subject){currentSubject=subject;localStorage.setItem('mhl_joined_subject',subject);const k=`mhl_nickname_${subject}`;if(!localStorage.getItem(k)&&localStorage.getItem('mhl_nickname'))localStorage.setItem(k,localStorage.getItem('mhl_nickname'));}if(lecture){currentLecture=lecture;localStorage.setItem('mhl_joined_lecture',lecture);}localStorage.setItem('mhl_joined_mode',cfg.mode);}
  function renderHome(){screen.innerHTML=`<h1>Živé hlasování</h1><p class="home-note">Naskenujte QR kód v prezentaci. Po prvním připojení se další otázky zobrazí automaticky.</p><div class="message brand-message"><strong>by MiloslavHub · Interaktivní hlasování pro výuku</strong><span>Bez instalace aplikace a bez účtu.</span></div>`;}
  function needsJoinNickname(q){return !!(q.gamification&&q.gamification.join_nickname_required);}
  function needsVoteNickname(q){return !!(q.gamification&&q.gamification.nickname_required);}
  function hasVoted(q){return !!(q.session_id&&localStorage.getItem(`mhl_voted_${q.session_id}`)==='1');}
  async function fetchQuestion(){return api(`/question/${encodeURIComponent(currentLecture)}/${encodeURIComponent(currentQuestion)}?mode=${encodeURIComponent(cfg.mode)}&participant_id=${encodeURIComponent(participant())}`);}
  async function activateQuestion(){const out=await api('/activate',{method:'POST',body:JSON.stringify({lecture_slug:currentLecture,question_slug:currentQuestion,mode:cfg.mode,participant_id:participant()})});rememberContext(out.subject_slug||'',out.lecture_slug||currentLecture);return out;}
  async function joinQuestion(q={}){if(!q.session_id)return q;try{const out=await api('/join',{method:'POST',body:JSON.stringify({lecture_slug:q.lecture_slug||currentLecture,question_slug:q.question_slug||currentQuestion,mode:cfg.mode,participant_id:participant()})});return out.question||q;}catch(_){return q;}}

  async function claimNickname(nick,q={}){const out=await api('/nickname/claim',{method:'POST',body:JSON.stringify({lecture_slug:q.lecture_slug||currentLecture,subject_slug:q.subject_slug||currentSubject,mode:cfg.mode,participant_id:participant(),nickname:nick})});rememberContext(out.subject_slug||q.subject_slug||'',q.lecture_slug||currentLecture);saveNickname(out.nickname||nick,out.subject_slug||q.subject_slug||currentSubject);return out;}
  async function setHallVisibility(subject,visibility){if(!subject)return;return api('/privacy/hall-opt-in',{method:'POST',body:JSON.stringify({subject_slug:subject,participant_id:participant(),visibility})});}
  async function setHallOptIn(subject,opt){return setHallVisibility(subject,opt?'nickname':'unset');}

  function sharePanel(q){const url=q.share_url||location.href;return `<details class="share-box"><summary>▦ Sdílet připojení</summary><div class="share-content"><strong>Pozvěte spolužáka</strong><span>Nechte ho naskenovat QR kód nebo mu pošlete odkaz.</span><div class="local-qr" data-qr-url="${esc(url)}" role="img" aria-label="QR kód pro připojení"></div><code>${esc(url)}</code><button class="button secondary share-action" type="button" data-share-url="${esc(url)}">Sdílet odkaz</button></div></details>`;}
  function renderQRCodes(){
    screen.querySelectorAll('[data-qr-url]').forEach(el=>{
      if(el.dataset.qrReady)return;
      try{
        const url=new URL(el.dataset.qrUrl,location.origin);
        if(!['http:','https:'].includes(url.protocol)||typeof QRCode==='undefined')throw new Error('QR unavailable');
        new QRCode(el,{text:url.href,width:220,height:220,correctLevel:QRCode.CorrectLevel.M});
        el.dataset.qrReady='1';
      }catch(_){el.textContent='QR kód není dostupný. Použijte odkaz pro připojení.';}
    });
  }
  function bindShare(){renderQRCodes();screen.querySelectorAll('[data-share-url]').forEach(b=>b.addEventListener('click',async()=>{const url=b.dataset.shareUrl||location.href;try{if(navigator.share)await navigator.share({title:'Hlasuj!',text:'Připoj se k hlasování',url});else{await navigator.clipboard.writeText(url);b.textContent='Odkaz zkopírován';setTimeout(()=>b.textContent='Sdílet odkaz',1600);}}catch(_){}}));}
  function joiningInfo(q){if(q.status!=='joining')return'';const count=Number(q.join_count||0);return `<div class="joining-card"><span class="pulse-dot"></span><div><strong>Připojují se studenti…</strong><span>Připojeno: ${esc(count)}. Hlasování spustí vyučující.</span></div></div>`;}

  function renderNicknameGate(q){
    const alreadyRunning=['joining','open'].includes(q.status);
    const days=q.nickname_policy?.reservation_days||365;
    screen.innerHTML=`${header(q)}<div class="question-topline"><div class="status ${esc(q.status)}">${q.status==='joining'?'Připojování studentů':alreadyRunning?'Hlasování už probíhá':'Připojení k soutěži'}</div>${countdownMarkup(q)}</div><div class="join-card"><div class="join-brand">${brandInline()}</div><h1>Zvolte si přezdívku</h1><p class="join-lead">Přezdívka je jedinečná pouze v tomto předmětu a zůstane vám i v dalších jeho přednáškách. V jiném předmětu můžete použít stejnou nebo jinou.</p>${alreadyRunning?'<div class="message warn">Hlasování už běží. Po potvrzení přezdívky se otázka zobrazí ihned.</div>':''}<form id="nickname-form" class="nickname-form"><label for="nickname">Přezdívka</label><input id="nickname" name="nickname" maxlength="40" autocomplete="off" autocapitalize="none" spellcheck="false" enterkeyhint="go" placeholder="Např. CyberFox" value="${esc(nickname())}" required><button class="button join-button" type="submit">Pokračovat k hlasování</button></form><div id="join-message"></div><p class="privacy-note">Rezervace přezdívky se obnovuje při používání a po ${esc(days)} dnech neaktivity se uvolní. Pokračováním potvrzujete, že jste se seznámil/a s informacemi o zpracování údajů. Zveřejnění přezdívky v Síni slávy je vždy samostatná dobrovolná volba. <a href="${esc(q.privacy_url||privacyLink(q.subject_slug||currentSubject))}">Soukromí a moje data</a></p></div>${brandFooter()}`;
    const form=document.getElementById('nickname-form'),inp=document.getElementById('nickname'),msg=document.getElementById('join-message'); inp?.focus();
    form?.addEventListener('submit',async e=>{
      e.preventDefault();const nick=(inp?.value||'').trim();if(!nick){msg.innerHTML='<div class="message bad">Zadejte přezdívku.</div>';inp?.focus();return;}
      const btn=form.querySelector('button');if(btn){btn.disabled=true;btn.textContent='Ověřuji přezdívku…';}
      try{await claimNickname(nick,q);if(btn)btn.textContent='Připojuji…';if(cfg.mode==='async'&&!alreadyRunning){const out=await activateQuestion();renderVote(await joinQuestion(out.question||q));}else{await loadVote();}}
      catch(err){if(err.code==='mhl_nickname_taken'){clearNickname(q.subject_slug||currentSubject);inp?.select();}msg.innerHTML=`<div class="message bad">${esc(err.message)}</div>`;if(btn){btn.disabled=false;btn.textContent='Pokračovat k hlasování';}}
    });
    startCountdown(q,loadVote);
  }

  function renderVote(q){
    rememberContext(q.subject_slug||'',q.lecture_slug||currentLecture);
    if(!nickname()&&q.participant_nickname)saveNickname(q.participant_nickname,q.subject_slug||currentSubject);
    const voted=hasVoted(q);let body='';
    if(!voted&&needsJoinNickname(q)&&!nickname()&&['idle','waiting','joining','open'].includes(q.status)){renderNicknameGate(q);return;}
    if(q.status==='joining'){body=joiningInfo(q)+sharePanel(q);}
    else if(q.status==='open'&&!voted){if(q.long_poll)body+='<div class="message long-poll-note"><strong>Dlouhodobá anketa</strong><span>Bez odpočtu. Odpověď můžete odeslat kdykoli, dokud je anketa otevřená.</span></div>';body+=`<div class="options">${q.options.map(o=>`<button class="option" type="button" data-option="${o.index}"><span class="option-code">${esc(o.code)}</span><span>${esc(o.label)}</span></button>`).join('')}</div>`;}
    else if(q.status==='open'&&voted){body=`<div class="message good vote-wait"><strong>Hlas byl uložen.</strong><span>Po ukončení hlasování se zde automaticky zobrazí výsledky.</span></div>${ctas(q.cta)}`;}
    else if(q.status==='waiting')body='<div class="message">Otázka je připravena. Počkejte, až vyučující spustí hlasování.</div>';
    else if(q.status==='closed')body=`<div class="message">Toto hlasování už bylo ukončeno.</div>${ctas(q.cta)}`;
    else if(q.status==='skipped')body='<div class="message">Tato otázka byla v této přednášce přeskočena.</div>';
    else body='<div class="message">Tato otázka právě není aktivní.</div>';
    screen.innerHTML=`${header(q)}<div class="question-topline"><div class="status ${esc(q.status)}">${esc(state(q.status))}</div>${countdownMarkup(q)}</div><h1>${esc(q.title)}</h1>${body}${q.status==='open'?sharePanel(q):''}<div id="vote-message"></div>${privacyMini(q)}${brandFooter()}`;bindShare();
    if(q.status==='open'&&!voted)screen.querySelectorAll('.option').forEach(b=>b.addEventListener('click',()=>submit(q,Number(b.dataset.option))));startCountdown(q,loadVote);
  }

  async function submit(q,opt){const msg=document.getElementById('vote-message'),buttons=[...screen.querySelectorAll('.option')],required=needsVoteNickname(q),nick=required?nickname():'';if(required&&!nick){renderNicknameGate(q);return;}buttons.forEach(b=>b.disabled=true);try{const out=await api('/vote',{method:'POST',body:JSON.stringify({lecture_slug:currentLecture,question_slug:currentQuestion,mode:cfg.mode,participant_id:participant(),nickname:nick,option_index:opt})});localStorage.setItem(`mhl_voted_${out.session_id}`,'1');const t=Number.isFinite(out.response_ms)&&!q.long_poll?` Váš čas: ${(out.response_ms/1000).toFixed(2).replace('.',',')} s.`:'';const tail=q.long_poll&&q.show_results_after_vote?' Zobrazuji průběžné výsledky.':(q.long_poll?' Děkujeme za odpověď.':' Výsledky se zobrazí po ukončení hlasování.');msg.innerHTML=`<div class="message good"><strong>Hlas byl uložen.</strong>${esc(t)}${esc(tail)}</div>`;setTimeout(loadVote,250);}catch(e){if(e.code==='mhl_already_voted'){if(q.session_id)localStorage.setItem(`mhl_voted_${q.session_id}`,'1');msg.innerHTML=`<div class="message bad">${esc(e.message)}</div>`;setTimeout(loadVote,350);}else if(e.code==='mhl_nickname_taken'){clearNickname(q.subject_slug||currentSubject);renderNicknameGate(q);}else{msg.innerHTML=`<div class="message bad">${esc(e.message)}</div>`;buttons.forEach(b=>b.disabled=false);}}}

  async function loadVote(){if(!currentQuestion)return;try{const q=await fetchQuestion();rememberContext(q.subject_slug||'',q.lecture_slug||currentLecture);if(!nickname()&&q.participant_nickname)saveNickname(q.participant_nickname,q.subject_slug||currentSubject);if((q.status==='closed'||(q.long_poll&&q.show_results_after_vote))&&hasVoted(q)){await loadStudentResults(q);return;}renderVote(q);}catch(e){screen.innerHTML=`<h1>Otázku se nepodařilo načíst</h1><div class="message bad">${esc(e.message)}</div>`;}}
  function leaderboard(rows,limit=10,detailHeader='Detail'){const visible=rows.slice(0,limit);return `<table class="leaderboard"><thead><tr><th>#</th><th>Přezdívka</th><th>${esc(detailHeader)}</th><th>Body</th></tr></thead><tbody>${visible.map((x,i)=>`<tr><td class="rank">${i+1}.</td><td><strong>${esc(x.nickname)}</strong></td><td>${esc(x.detail)}</td><td>${esc(x.points)}</td></tr>`).join('')}</tbody></table>`;}
  function bars(r){return r.options.map(o=>{const percent=Math.max(0,Math.min(100,Number(o.percent)||0)),count=Number(o.count)||0,word=count===1?'hlas':(count>=2&&count<=4?'hlasy':'hlasů');return `<div class="bar-row result-bar-card ${r.correct_index===o.index?'correct':''}"><div class="bar-head"><span class="bar-code">${esc(o.code)}</span><strong class="bar-label">${esc(o.label)}</strong><span class="bar-meta"><b>${esc(count)}</b> ${word}<em>${esc(o.percent)} %</em></span></div><div class="bar-track" role="img" aria-label="${esc(o.label)}: ${esc(o.percent)} procent"><div class="bar-fill" style="width:${percent}%"></div></div></div>`;}).join('');}
  function participantCard(r){
    const p=r.participant_result;if(!p)return'';
    const correct=r.mode==='quiz'?(p.is_correct?'<strong class="student-good">Správně</strong>':'<strong class="student-bad">Nesprávně</strong>'):'';
    const answer=`${esc(p.option_code)}${p.option_label?` – ${esc(p.option_label)}`:''}`;
    const time=r.long_poll?'—':(Number.isFinite(p.response_ms)?`${(p.response_ms/1000).toFixed(2).replace('.',',')} s`:'—');
    const gam=!!(r.gamification&&r.gamification.enabled);
    const rank=(p.lecture_rank&&p.lecture_participants)?`<div class="result-rank"><span>Pořadí ${esc(p.scope_label||'')}</span><strong>${esc(p.total_rank||p.lecture_rank)}. z ${esc(p.total_participants||p.lecture_participants)}</strong></div>`:'';
    const points=gam?`<div class="score-strip"><div><span>Za tuto otázku</span><strong>${esc(p.points)} b.</strong></div><div class="score-total"><span>Celkem ${esc(p.scope_label||'v předmětu')}</span><strong>${esc(Number.isFinite(p.total_points)?p.total_points:(Number.isFinite(p.lecture_points)?p.lecture_points:p.points))} b.</strong></div>${rank}</div>`:'';
    return `<section class="student-result"><div class="student-result-title"><span>Váš výsledek</span>${correct}</div>${points}<div class="student-answer-row"><div><span>Vaše odpověď</span><strong>${answer}</strong></div><div><span>Čas od STARTU</span><strong>${esc(time)}</strong></div></div></section>`;
  }
  function hallPrompt(r){
    const h=r?.participant_result?.hall_of_fame;if(!h||!h.enabled||!h.eligible||h.visibility!=='unset')return'';
    const fallback=h.nonopt_mode==='anonymous'?'Pokud nic neuděláte, v Síni slávy se zobrazí anonymní účastník.':'Pokud nic neuděláte, v Síni slávy se nezobrazíte.';
    return `<section class="hall-qualify"><div><span>Síň slávy</span><strong>Jste na ${esc(h.rank)}. místě a patříte do Top ${esc(h.limit)}.</strong><small>${esc(fallback)}</small></div><button class="button hall-add" type="button" data-hall-add>Přidat mou přezdívku</button><button class="button secondary hall-anon" type="button" data-hall-anon>Zůstat anonymní</button><div data-hall-msg></div></section>`;
  }
  function bindHallPrompt(r){const subject=r.subject_slug||currentSubject;screen.querySelector('[data-hall-add]')?.addEventListener('click',async e=>{e.currentTarget.disabled=true;try{await setHallVisibility(subject,'nickname');const m=screen.querySelector('[data-hall-msg]');if(m)m.innerHTML='<div class="message good">Přezdívka byla přidána do Síně slávy.</div>';}catch(x){e.currentTarget.disabled=false;}});screen.querySelector('[data-hall-anon]')?.addEventListener('click',async e=>{e.currentTarget.disabled=true;try{await setHallVisibility(subject,'anonymous');const m=screen.querySelector('[data-hall-msg]');if(m)m.innerHTML='<div class="message">V Síni slávy zůstanete anonymní.</div>';}catch(x){e.currentTarget.disabled=false;}});}

  function renderStudentResults(r){
    const correctNote=(r.mode==='quiz'&&Number.isInteger(r.correct_index)&&r.options[r.correct_index])?`<div class="correct-answer">Správná odpověď: <strong>${esc(r.options[r.correct_index].code)} – ${esc(r.options[r.correct_index].label)}</strong></div>`:'';
    let questionRank='';
    if(r.gamification&&r.gamification.enabled&&r.question_leaderboard?.length){questionRank=`<details class="mobile-leaderboard question-ranking" open><summary>Pořadí této otázky · čas od STARTU</summary>${leaderboard(r.question_leaderboard.map(x=>({nickname:x.nickname,detail:`${String.fromCharCode(65+x.option_index)} ${x.is_correct?'✓':'✗'} · ${Number.isFinite(x.response_ms)?(x.response_ms/1000).toFixed(1).replace('.',',')+' s':'—'}`,points:x.points})),10,'Odpověď · čas od STARTU')}</details>`;}
    let overall='';
    if(r.gamification&&r.gamification.enabled&&r.overall_leaderboard?.length){overall=`<details class="mobile-leaderboard"><summary>Pořadí ${esc(r.score_scope_label||'v předmětu')}</summary>${leaderboard(r.overall_leaderboard.map(x=>({nickname:x.nickname,detail:`${x.correct_count}/${x.answers} správně`,points:x.points})),10,'Výsledky')}</details>`;}
    screen.innerHTML=`${header(r)}<div class="question-topline mobile-result-status"><div class="status closed">Výsledky</div></div><h1>${esc(r.title)}</h1>${participantCard(r)}${hallPrompt(r)}${correctNote}<div class="mobile-results-title">Jak hlasovali ostatní</div>${bars(r)}<div class="total">Počet hlasujících: <strong>${esc(r.total)}</strong></div>${questionRank}${overall}${sharePanel(r)}${r.subject_slug?`<div class="privacy-actions"><a href="${esc(privacyLink(r.subject_slug))}">Soukromí a moje data</a>${r.hall_of_fame?.enabled?`<a href="${esc(r.hall_of_fame.url)}">Síň slávy</a>`:''}</div>`:''}${ctas(r.cta)}${r.long_poll?'<div class="next-wait">Děkujeme za odpověď. Tato dlouhodobá anketa zůstává otevřená pro další respondenty.</div>':'<div class="next-wait"><span class="pulse-dot"></span>Čekáme na další otázku…</div>'}${brandFooter()}`;bindShare();bindHallPrompt(r);
  }
  async function loadStudentResults(){if(!currentQuestion)return;try{const sep='&participant_id='+encodeURIComponent(participant());const r=await api(`/results/${encodeURIComponent(currentLecture)}/${encodeURIComponent(currentQuestion)}?mode=${encodeURIComponent(cfg.mode)}${sep}`);renderStudentResults(r);}catch(e){screen.innerHTML=`<h1>Výsledky se nepodařilo načíst</h1><div class="message bad">${esc(e.message)}</div>`;}}

  function renderResults(r){let main='';if(!r.reveal_results&&r.status==='open'){main=`<div class="waiting-results"><strong>Hlasování probíhá.</strong><span>Počet hlasujících: ${esc(r.total)}</span><p>Rozložení odpovědí se zobrazí po ukončení hlasování.</p></div>`;}else{main=bars(r);}
    let lbs='';if(r.gamification&&r.gamification.enabled&&r.question_leaderboard?.length){lbs+=`<h2>Pořadí této otázky</h2><p class="leaderboard-note">Čas je měřen od společného STARTU otázky do přijetí hlasu serverem.</p>${leaderboard(r.question_leaderboard.map(x=>({nickname:x.nickname,detail:`${String.fromCharCode(65+x.option_index)} ${x.is_correct?'✓':'✗'} · ${Number.isFinite(x.response_ms)?(x.response_ms/1000).toFixed(1).replace('.',',')+' s':'—'}`,points:x.points})),10,'Odpověď · čas od STARTU')}`;}if(r.gamification&&r.gamification.enabled&&r.overall_leaderboard?.length){lbs+=`<h2>Pořadí ${esc(r.score_scope_label||'v předmětu')}</h2>${leaderboard(r.overall_leaderboard.map(x=>({nickname:x.nickname,detail:`${x.correct_count}/${x.answers} správně`,points:x.points})),10,'Výsledky')}`;}
    const correctNote=(r.mode==='quiz'&&r.status==='closed'&&Number.isInteger(r.correct_index)&&r.options[r.correct_index])?`<div class="correct-answer projection-correct">Správná odpověď: <strong>${esc(r.options[r.correct_index].code)} – ${esc(r.options[r.correct_index].label)}</strong></div>`:'';
    screen.innerHTML=`${header(r)}<div class="results-head"><div><div class="question-topline"><div class="status ${esc(r.status)}">${esc(state(r.status))}</div>${countdownMarkup(r,true)}</div><h1>${esc(r.title)}</h1><div class="total">Počet hlasujících: <strong>${esc(r.total)}</strong></div></div><div class="qr-card"><div class="local-qr" data-qr-url="${esc(r.vote_url||'')}" role="img" aria-label="QR kód pro připojení"></div><div class="qr-brand">${brandInline()}</div></div></div>${correctNote}${main}${lbs}${ctas(r.cta)}${brandFooter()}`;renderQRCodes();startCountdown(r,loadResults);}
  async function loadResults(){if(!currentQuestion)return;try{const r=await api(`/results/${encodeURIComponent(currentLecture)}/${encodeURIComponent(currentQuestion)}?mode=${encodeURIComponent(cfg.mode)}`);renderResults(r);}catch(e){screen.innerHTML=`<h1>Výsledky se nepodařilo načíst</h1><div class="message bad">${esc(e.message)}</div>`;}}
  async function followTeaching(){if(!currentLecture&&!currentSubject)return;try{const endpoint=currentSubject?`/subject/${encodeURIComponent(currentSubject)}/current?mode=${encodeURIComponent(cfg.mode)}`:`/lecture/${encodeURIComponent(currentLecture)}/current?mode=${encodeURIComponent(cfg.mode)}`;const c=await api(endpoint);if(c.subject_slug){currentSubject=c.subject_slug;localStorage.setItem('mhl_joined_subject',c.subject_slug);}const changedSession=c.session_id!==lastCurrentSession;const changedStatus=c.status!==lastCurrentStatus;lastCurrentSession=c.session_id??null;lastCurrentStatus=c.status||'';if(['joining','open'].includes(c.status)&&c.question_slug){const changed=setContext(c.lecture_slug||currentLecture,c.question_slug,cfg.view);if(c.lecture_slug)localStorage.setItem('mhl_joined_lecture',c.lecture_slug);if(changed||changedSession||changedStatus){if(cfg.view==='results')await loadResults();else await loadVote();}}else if(changedStatus&&currentQuestion){if(c.lecture_slug&&c.lecture_slug!==currentLecture){currentLecture=c.lecture_slug;localStorage.setItem('mhl_joined_lecture',c.lecture_slug);}if(cfg.view==='results')await loadResults();else await loadVote();}}catch(_){} }
  function pageBrand(title,subtitle='by MiloslavHub · Interaktivní hlasování pro výuku'){applyBrand({subject_template:{brand_template:'miloslavhub',brand_name:'Hlasuj!',brand_subtitle:subtitle,brand_url:cfg.mainSite,primary_color:'#172033',accent_color:'#1f5fae',accent_soft:'#eaf2fb'}});return `<div class="standalone-head"><a href="${esc(cfg.frontend)}/" class="standalone-home">← Domů</a><span>${esc(title)}</span></div>`;}
  async function startHall(){try{const subject=cfg.subject||currentSubject;if(!subject)throw new Error('Chybí předmět.');const h=await api(`/hall-of-fame/${encodeURIComponent(subject)}?participant_id=${encodeURIComponent(participant())}`);currentSubject=h.subject_slug||subject;const rows=(h.entries||[]).map((x,i)=>`<div class="hof-row hof-${x.rank||i+1}"><span class="hof-rank">${esc(x.rank||i+1)}</span><strong>${esc(x.nickname)}</strong><span>${esc(x.correct_count)}/${esc(x.answers)} správně</span><b>${esc(x.points)} b.</b></div>`).join('');screen.innerHTML=`${pageBrand('Síň slávy')}<section class="hof-hero"><span>Gamifikace</span><h1>${esc(h.title||'Síň slávy')}</h1><p>${esc(h.subject_title||'')}</p></section><section class="hof-card">${rows||'<div class="message">Zatím zde není žádný zveřejněný nebo anonymní účastník.</div>'}<div class="hof-foot"><span>Zveřejnění přezdívky je dobrovolné; podle nastavení mohou být ostatní místa skryta nebo anonymní.</span><a href="${esc(h.privacy_url||privacyLink(subject))}">Soukromí a moje data</a></div></section>`;}catch(e){screen.innerHTML=`${pageBrand('Síň slávy')}<h1>Síň slávy není dostupná</h1><div class="message bad">${esc(e.message)}</div>`;}}
  async function startPrivacy(){
    try{
      const info=await api('/privacy');
      const subject=cfg.subject||currentSubject;
      let me=null;
      if(subject){
        try{me=await api(`/privacy/me?subject=${encodeURIComponent(subject)}&participant_id=${encodeURIComponent(participant())}`);}catch(_){}
      }
      const r=info.retention||{};
      const mine=me?`<section class="privacy-self-panel">
        <div class="privacy-section-head"><span>Vaše údaje</span><h2>Moje data v tomto předmětu</h2></div>
        <div class="privacy-self-grid">
          <div><span>Předmět</span><strong>${esc(me.subject_title)}</strong></div>
          <div><span>Přezdívka</span><strong>${esc(me.nickname||'—')}</strong></div>
        </div>
        ${me.nickname?`
          <label class="hall-opt">
            <span><strong>Síň slávy</strong><small>Zveřejnění je dobrovolné. Zvolte způsob zobrazení, pokud se kvalifikujete do Top N.</small></span>
            <select id="privacy-hall-vis">
              <option value="unset">Bez rozhodnutí</option>
              <option value="nickname">Zobrazit přezdívku</option>
              <option value="anonymous">Zobrazit anonymně</option>
              <option value="hidden">Nezobrazovat</option>
            </select>
          </label>
          <div class="privacy-self-actions">
            <button id="privacy-save" class="button" type="button">Uložit volbu</button>
            <button id="privacy-delete" class="button danger-button" type="button">Vymazat moje data z tohoto předmětu</button>
          </div>
        `:'<p>V tomto prohlížeči zatím není pro předmět rezervovaná přezdívka.</p>'}
      </section>`:'';

      screen.innerHTML=`${pageBrand('Soukromí a data')}
        <section class="privacy-hero privacy-hero-clean">
          <span>Soukromí a transparentnost</span>
          <h1>Jak Hlasuj pracuje s údaji</h1>
          <p>Stručně a srozumitelně: jaké údaje aplikace používá, proč je potřebuje, jak dlouho je uchovává a jaká máte práva.</p>
        </section>

        <section class="privacy-summary">
          <div><strong>Bez jména a e-mailu</strong><span>Základní hlasování samo nevyžaduje jméno, studentské číslo ani e-mail.</span></div>
          <div><strong>Pseudonymní identifikátor</strong><span>Technický identifikátor umožňuje zabránit duplicitním hlasům a navazovat otázky.</span></div>
          <div><strong>Dobrovolné zveřejnění</strong><span>Přezdívka v Síni slávy se zveřejní jen na základě vaší aktivní volby.</span></div>
        </section>

        <div class="privacy-layout">
          <section class="privacy-section">
            <div class="privacy-section-head"><span>01</span><h2>Jaké údaje se používají</h2></div>
            <p>Soutěžní kvízy mohou používat přezdívku, odpověď, body, čas odpovědi a pseudonymní technický identifikátor. Nebodované ankety ukládají odpověď bez přezdívky a používají technický klíč omezený na danou otázku.</p>
          </section>

          <section class="privacy-section">
            <div class="privacy-section-head"><span>02</span><h2>Účel zpracování</h2></div>
            <p>Údaje se používají pro zajištění hlasování, ochranu proti duplicitním hlasům, zobrazení výsledků, případné bodování a návaznost dalších otázek. Konkrétní právní titul zpracování určuje správce daného nasazení podle jeho účelu a postavení.</p>
          </section>

          <section class="privacy-section">
            <div class="privacy-section-head"><span>03</span><h2>Doby uchování</h2></div>
            <dl class="retention retention-clean">
              <div><dt>Ostré hlasy</dt><dd>${esc(r.live_votes_days)} dní</dd></div>
              <div><dt>Rezervace přezdívky</dt><dd>${esc(r.nickname_days)} dní</dd></div>
              <div><dt>Testovací data</dt><dd>${esc(r.test_data_days)} dní</dd></div>
              <div><dt>Technické příchody</dt><dd>${esc(r.technical_joins_days)} dní</dd></div>
            </dl>
          </section>

          <section class="privacy-section" id="hall-of-fame">
            <div class="privacy-section-head"><span>04</span><h2>Dobrovolná Síň slávy</h2></div>
            <p>Zveřejnění přezdívky není podmínkou účasti v hlasování. Pokud se kvalifikujete do Top N, můžete aktivně zvolit zveřejnění přezdívky, anonymní zobrazení nebo nezobrazení. Volbu lze změnit v této sekci, pokud ji konkrétní nasazení podporuje.</p>
          </section>

          <section class="privacy-section">
            <div class="privacy-section-head"><span>05</span><h2>Technické ukládání v prohlížeči</h2></div>
            <p>Základní frontend používá lokální úložiště pro pseudonymní identifikátor účastníka, kontext předmětu a případnou přezdívku. Jde o technické údaje potřebné pro fungování služby. Základní distribuce frontendu sama neobsahuje marketingovou ani analytickou cookie lištu; pokud provozovatel přidá analytické nebo marketingové nástroje, musí tomu přizpůsobit informace a případné souhlasy.</p>
          </section>

          <section class="privacy-section">
            <div class="privacy-section-head"><span>06</span><h2>Vaše práva</h2></div>
            <p>Podle okolností máte právo na informace a přístup k osobním údajům, jejich opravu nebo výmaz, omezení zpracování, námitku proti zpracování a právo podat stížnost u dozorového úřadu. Rozsah konkrétních práv závisí na právním základu a způsobu nasazení.</p>
          </section>

          <section class="privacy-section privacy-controller">
            <div class="privacy-section-head"><span>07</span><h2>Správce a kontakt</h2></div>
            <p><strong>${esc(info.controller_name||'Správce konkrétního nasazení')}</strong><br><a href="mailto:${esc(info.contact_email||'')}">${esc(info.contact_email||'')}</a></p>
            <p>Pro dotaz, žádost o přístup nebo výmaz kontaktujte správce uvedeného výše. Pokud je systém provozován jinou organizací, může být správcem tato organizace.</p>
          </section>
        </div>

        <aside class="privacy-legal-note">
          <strong>Důležité k provozu</strong>
          <p>Tato stránka popisuje chování aplikace. Úplnou informační povinnost podle konkrétního nasazení musí správce doplnit zejména o svůj právní základ, případné příjemce nebo zpracovatele, předávání do třetích zemí a další údaje, pokud se na dané nasazení vztahují.</p>
        </aside>

        ${mine}`;

      const hv=document.getElementById('privacy-hall-vis');
      if(hv&&me)hv.value=me.hall_visibility||'unset';

      document.getElementById('privacy-save')?.addEventListener('click',async()=>{
        const btn=document.getElementById('privacy-save');btn.disabled=true;
        try{await setHallVisibility(me.subject_slug,document.getElementById('privacy-hall-vis')?.value||'unset');btn.textContent='Uloženo';}
        catch(e){btn.textContent=e.message;}
        setTimeout(()=>{btn.disabled=false;btn.textContent='Uložit volbu';},1800);
      });

      document.getElementById('privacy-delete')?.addEventListener('click',async()=>{
        if(!confirm('Opravdu vymazat pseudonymní data tohoto účastníka z předmětu? Tuto akci nelze vrátit.'))return;
        const btn=document.getElementById('privacy-delete');btn.disabled=true;
        try{
          const out=await api('/privacy/delete',{method:'POST',body:JSON.stringify({subject_slug:me.subject_slug,participant_id:participant()})});
          clearNickname(me.subject_slug);
          btn.textContent='Data byla vymazána';
          screen.insertAdjacentHTML('beforeend',`<div class="message good">${esc(out.message||'Data byla vymazána.')}</div>`);
        }catch(e){btn.disabled=false;btn.textContent=e.message;}
      });

      if(location.hash==='#hall-of-fame'){
        setTimeout(()=>document.getElementById('hall-of-fame')?.scrollIntoView({behavior:'smooth',block:'start'}),100);
      }
    }catch(e){
      screen.innerHTML=`${pageBrand('Soukromí a data')}<h1>Informace se nepodařilo načíst</h1><div class="message bad">${esc(e.message)}</div>`;
    }
  }

  async function startProjection(){
    async function refresh(){try{const c=await api(`/projection/${encodeURIComponent(cfg.subject)}/${encodeURIComponent(cfg.projectionToken)}`);if(c.subject_template)applyBrand(c);if(c.question_slug&&c.lecture_slug){currentSubject=c.subject_slug||cfg.subject;currentLecture=c.lecture_slug;currentQuestion=c.question_slug;await loadResults();}else{screen.innerHTML=`${subjectHeader(c)}<section class="projection-wait"><span class="pulse-dot"></span><h1>${c.status==='idle'?'Čekáme na zahájení přednášky':'Čekáme na další otázku'}</h1><p>Projekce se přepne automaticky, jakmile vyučující spustí otázku.</p></section>${brandFooter()}`;}}catch(e){screen.innerHTML=`<h1>Projekční odkaz není platný</h1><div class="message bad">${esc(e.message)}</div>`;}}
    startTimer(refresh,1800);
  }

  function startTimer(fn,ms){fn();timer=setInterval(fn,ms);document.addEventListener('visibilitychange',()=>{if(document.hidden){clearInterval(timer);timer=null;}else if(!timer){fn();timer=setInterval(fn,ms);}});}
  async function startVote(){try{
      if(cfg.mode==='async'){
        const out=await activateQuestion();renderVote(out.question||await fetchQuestion());
      }else{
        const q=await fetchQuestion();renderVote(await joinQuestion(q));
      }
    }catch(e){screen.innerHTML=`<h1>Hlasování se nepodařilo načíst</h1><div class="message bad">${esc(e.message)}</div>`;}
    if(cfg.mode!=='async')startTimer(followTeaching,2500);
  }
  async function startResults(){await loadResults();if(cfg.mode==='async')startTimer(loadResults,3000);else startTimer(async()=>{await followTeaching();await loadResults();},2200);}
  if(cfg.view==='vote'&&cfg.lecture&&cfg.question)startVote();else if(cfg.view==='projection'&&cfg.subject&&cfg.projectionToken)startProjection();else if(cfg.view==='results'&&cfg.lecture&&cfg.question)startResults();else if(cfg.view==='hall'&&cfg.subject)startHall();else if(cfg.view==='privacy')startPrivacy();else renderHome();
})();
