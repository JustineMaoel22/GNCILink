const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const php=(args)=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
const sessions=[];
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-help-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket;
(async()=>{
    const session=role=>{const s=JSON.parse(php([role]));sessions.push(s);return s;};
    const admin=session('Super Admin'),editor=session('Content Editor');
    let mirror;try{mirror=session('The Mirror');}catch(_){console.log('No local Mirror account; Mirror and legacy roles are covered by PHP permission checks.');}
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();let send;
    let saveFailure=false;const statuses=[];
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);
        if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}
        if(p.method==='Fetch.requestPaused'){
            const request=p.params;
            const status=new URLSearchParams(request.request.postData).get('status');statuses.push(status);
            const error=saveFailure;saveFailure=false;
            send('Fetch.fulfillRequest',{requestId:request.requestId,responseCode:error?503:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(error?{success:false,error:'Test save failure. Please try again.'}:{success:true})).toString('base64')});
        }
    });
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';await send('Page.enable');await send('Network.enable');
    await send('Fetch.enable',{patterns:[{urlPattern:'*/admin/action/onboarding-handler.php*',requestStage:'Request'}]});
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'&&Boolean(document.getElementById('help-search'))"),'Help unavailable');};
    const resize=async width=>{await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);};
    const shot=async name=>fs.writeFileSync('cache/'+name+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    const type=async value=>{await evaluate(`document.getElementById('help-search').value=${JSON.stringify(value)};document.getElementById('help-search').dispatchEvent(new Event('input',{bubbles:true}))`);await delay(200);};
    const choose=async id=>evaluate(`document.querySelector('[data-help-topic="${id}"]').click()`);
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});await resize(1440);
    await visit('/admin/help.php?topic=getting-started');
    assert.equal(await evaluate("document.querySelectorAll('.help-article:not([hidden])').length"),1);
    assert.equal(await evaluate("document.querySelectorAll('[data-help-topic]').length"),11);
    await shot('help-desktop');
    await type('How do I reset my password?');
    assert.equal(await evaluate("document.querySelector('[data-help-result]').dataset.helpResult"),'account-security');
    await shot('help-search');
    await evaluate("document.querySelector('[data-help-result]').click()");
    assert.equal(await evaluate("document.querySelector('.help-topic-links [aria-current]').dataset.helpTopic"),'account-security');
    assert.equal(await evaluate("document.activeElement.id"),'help-title-account-security');
    await evaluate("document.querySelector('#help-account-security details summary').click()");
    assert.ok(await evaluate("document.querySelector('#help-account-security details').open"));
    const href=await evaluate("document.querySelector('#help-account-security .help-module-link').getAttribute('href')");
    await evaluate("document.querySelector('#help-account-security .help-module-link').click()");
    await until(()=>evaluate("document.readyState==='complete'&&!document.getElementById('help-search')"),'Module link failed');
    await evaluate('history.back()');await until(()=>evaluate("Boolean(document.getElementById('help-search'))"),'Back failed');
    assert.equal(await evaluate("document.querySelector('.help-article:not([hidden])').dataset.helpArticle"),'account-security');
    await choose('announcements');
    await visit('/admin/help.php');
    assert.equal(await evaluate("document.querySelector('.help-article:not([hidden])').dataset.helpArticle"),'announcements');
    await type('no such topic abcxyz');assert.equal(await evaluate("document.getElementById('help-no-results').hidden"),false);
    await type('<script>alert(12345)</script>');assert.equal(await evaluate("document.querySelectorAll('#help-result-list script').length"),0);
    await type('calendar import');assert.equal(await evaluate("document.querySelector('[data-help-result]').dataset.helpResult"),'events');
    await choose('getting-started');
    for(const width of [820,390]){await resize(width);await evaluate('scrollTo(0,0)');assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'));await shot('help-'+width);}
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.help-topic-picker')).display"),'block');
    await evaluate("document.getElementById('help-topic-select').value='events';document.getElementById('help-topic-select').dispatchEvent(new Event('change',{bubbles:true}))");
    assert.equal(await evaluate("document.querySelector('.help-article:not([hidden])').dataset.helpArticle"),'events');
    await shot('help-mobile-topic');
    await choose('getting-started');await evaluate('scrollTo(0,0)');
    await evaluate("document.querySelector('[data-start-onboarding]').click()");
    await until(()=>evaluate("Boolean(document.querySelector('.gnc-tour[open]'))"),'Tour did not start');
    assert.match(await evaluate("document.querySelector('.gnc-tour-progress').textContent"),/Step 1 of/);
    assert.equal(await evaluate("document.querySelector('.gnc-tour-meter').value"),1);
    await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Next').click()");await delay(400);
    assert.equal(await evaluate("document.querySelector('.gnc-tour-meter').value"),2);
    const rect=await evaluate("(()=>{const r=document.querySelector('.gnc-tour-card').getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom}})()");
    assert.ok(rect.left>=0&&rect.right<=390&&rect.top>=0&&rect.bottom<=1000);
    await shot('help-tour-mobile');
    await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Back').click()");
    assert.equal(await evaluate("document.querySelector('.gnc-tour-meter').value"),1);
    saveFailure=true;await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Skip').click()");
    await until(()=>evaluate("document.querySelector('.gnc-tour-error')?.textContent.includes('Test save failure')"),'Tour save error not shown');
    await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Skip').click()");
    await until(()=>evaluate("!document.querySelector('.gnc-tour')"),'Tour skip did not close');assert.equal(statuses.at(-1),'skipped');
    await evaluate("document.querySelector('[data-start-onboarding]').click()");
    const steps=await evaluate("document.querySelector('.gnc-tour-meter').max");
    for(let i=1;i<steps;i++)await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Next').click()");
    assert.equal(await evaluate("document.querySelector('.gnc-tour-meter').value"),steps);
    await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Finish').click()");
    await until(()=>evaluate("!document.querySelector('.gnc-tour')"),'Tour finish failed');assert.equal(statuses.at(-1),'completed');
    for(const [role,account] of [['Content Editor',editor],...(mirror?[['The Mirror',mirror]]:[])]){
        await send('Network.setCookie',{...account,url:base,httpOnly:true});await visit('/admin/help.php?topic=users');
        assert.equal(await evaluate("document.querySelector('[data-help-topic=users]')"),null);
        assert.equal(await evaluate("document.querySelector('[data-help-topic=activity-logs]')"),null);
        assert.equal(await evaluate("document.querySelector('[data-help-topic=submissions]').textContent.includes('My Submissions')"),true);
        const config=await evaluate("JSON.parse(document.getElementById('adminOnboardingData').textContent).steps.map(s=>s.title)");
        assert.ok(!config.includes('Users')&&!config.includes('Activity Logs'));
        if(role==='The Mirror')assert.ok(!config.includes('Events')&&!config.includes('Content Editor'));
        await type('user management');assert.equal(await evaluate("document.querySelectorAll('[data-help-result]').length"),0);
    }
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});
    await send('Emulation.setScriptExecutionDisabled',{value:true});
    await visit('/admin/help.php?q=reset+password');
    assert.equal(await evaluate("document.querySelector('[data-help-result]').dataset.helpResult"),'account-security');
    await visit('/admin/help.php?topic=events');assert.equal(await evaluate("document.querySelector('.help-article:not([hidden])').dataset.helpArticle"),'events');
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    await visit('/admin/help.php?q%5B%5D=x&topic%5B%5D=x');
    assert.equal(await evaluate("document.querySelector('.help-article:not([hidden])').dataset.helpArticle"),'getting-started');
    await send('Fetch.disable');
    const invalid=await evaluate("fetch('/admin/action/onboarding-handler.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'status=completed&csrf_token=invalid'}).then(r=>r.status)");assert.equal(invalid,403);
    await send('Network.deleteCookies',{name:admin.name,url:base});
    const guest=await evaluate("fetch('/admin/help.php').then(r=>r.url)");assert.ok(/\/(?:admin\/login\.php|login)(?:[?#]|$)/.test(guest));
    console.log('Browser checks passed: role-specific help, live task search, no results, safe input, topic state, module links, FAQs, 1440/820/390px, mobile topic picker, tour Next/Back/Skip/Finish/restart, save errors, no-JS help, malformed input, CSRF and guest redirect. Tour saves were intercepted; no onboarding records changed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    try{for(const s of sessions)php(['destroy',s.value]);}finally{socket?.close();browser.kill();}
});
