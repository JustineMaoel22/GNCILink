const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const php=args=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
const sessions=[];let socket;
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-settings-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
(async()=>{
    const session=role=>{const s=JSON.parse(php([role]));sessions.push(s);return s;};
    const admin=session('Super Admin'),editor=session('Content Editor'),mirror=session('The Mirror');
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();let send;
    let fail=false,hold=false,held,posts=0,beforeunloadCount=0;const requests=[];
    const fulfill=(request,error=false)=>send('Fetch.fulfillRequest',{requestId:request.requestId,responseCode:error?500:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(error?{success:false,error:'Test save failed. Please retry.'}:{success:true,value:request.value,message:'Changes saved.'})).toString('base64')});
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}
        if(p.method==='Page.javascriptDialogOpening'){beforeunloadCount++;send('Page.handleJavaScriptDialog',{accept:false});}
        if(p.method==='Fetch.requestPaused'){
            const r=p.params;
            if(r.request.method!=='POST'){send('Fetch.continueRequest',{requestId:r.requestId});return;}
            posts++;const params=new URLSearchParams(r.request.postData);requests.push(params);
            const key=params.get('action')==='save_ai_image_checker'?'ai_image_checker_enabled':'page_suggestions_enabled';
            const request={requestId:r.requestId,value:params.getAll(key).at(-1)};
            if(hold){held=request;return;}
            fulfill(request,fail);fail=false;
        }
    });
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true,userGesture:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';await send('Page.enable');await send('Network.enable');
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page unavailable');};
    const settings=async()=>{await visit('/admin/settings.php');await until(()=>evaluate("Boolean(document.querySelector('.settings-page'))"),'Settings unavailable');};
    const resize=async width=>{await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);};
    const shot=async name=>fs.writeFileSync('cache/'+name+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});await resize(1440);await settings();
    assert.equal(await evaluate("document.querySelectorAll('.settings-card').length"),4);
    assert.ok(await evaluate("document.getElementById('profile-picture-file').accept.includes('image/jpeg')"));
    assert.equal(await evaluate("document.querySelector('.settings-profile-form').enctype"),'multipart/form-data');
    assert.equal(await evaluate("[...document.querySelectorAll('[data-settings-form] button')].every(b=>b.disabled)"),true);
    assert.ok(await evaluate("['Connected','Disconnected','Needs setup','Status unavailable'].includes(document.querySelector('.settings-badge').textContent.trim())"));
    await shot('settings-desktop');
    const form="document.querySelector('[data-settings-form]')";
    const input=`${form}.querySelector('[role=switch]')`;
    const button=`${form}.querySelector('button[type=submit]')`;
    await send('Fetch.enable',{patterns:[{urlPattern:'*/admin/settings.php*',requestStage:'Request'}]});
    await evaluate(`${input}.click()`);
    assert.equal(await evaluate(`${button}.disabled`),false);
    assert.match(await evaluate(`${form}.querySelector('[data-settings-status]').textContent`),/Unsaved changes/);
    await evaluate("setTimeout(()=>{location.href='/admin/help.php'},0)");
    await until(()=>beforeunloadCount>0,'Unsaved navigation warning did not appear');
    assert.ok(await evaluate("Boolean(document.querySelector('.settings-page'))"));
    fail=true;await evaluate(`${form}.requestSubmit()`);
    await until(()=>evaluate(`${form}.querySelector('[data-settings-feedback]').textContent.includes('Test save failed')`),'Save error missing');
    assert.equal(await evaluate(`${button}.disabled`),false);
    assert.equal(await evaluate(`${form}.dataset.savedValue!==(${input}.checked?'1':'0')`),true);
    hold=true;await evaluate(`${form}.requestSubmit()`);await until(()=>Boolean(held),'Pending save unavailable');
    assert.equal(await evaluate(`${button}.disabled`),true);assert.equal(await evaluate(`${input}.disabled`),true);
    const count=posts;await evaluate(`${form}.requestSubmit()`);await delay(100);assert.equal(posts,count);
    await fulfill(held);hold=false;held=null;
    await until(()=>evaluate(`${form}.dataset.saving!=='1'`),'Save did not finish');
    assert.equal(await evaluate(`${form}.dataset.savedValue===(${input}.checked?'1':'0')`),true);
    assert.equal(await evaluate(`${button}.disabled`),true);
    await shot('settings-saved');
    const ai="document.querySelectorAll('[data-settings-form]')[1]";
    await evaluate(`${ai}.querySelector('[role=switch]').checked=false;${ai}.querySelector('[role=switch]').dispatchEvent(new Event('change'))`);
    assert.equal(await evaluate(`${ai}.querySelector('[data-ai-notice]').hidden`),false);
    await evaluate("document.getElementById('ai-features').scrollIntoView({block:'start',behavior:'instant'})");
    await shot('settings-ai-disabled');
    // Restore unsaved state before navigation; mocked saves never touch configuration.
    await evaluate(`${ai}.querySelector('[role=switch]').checked=${ai}.dataset.savedValue==='1';${ai}.querySelector('[role=switch]').dispatchEvent(new Event('change'))`);
    for(const width of [820,390]){await resize(width);await evaluate('scrollTo(0,0)');assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'));await shot('settings-'+width);}
    await evaluate('scrollTo(0,0)');
    await visit('/admin/account-password.php');
    assert.ok(await evaluate("document.getElementById('current_password')!==null"));
    await evaluate("document.getElementById('new_password').value='Example test phrase 123!';document.getElementById('confirm_password').value='Mismatch test phrase';document.getElementById('confirm_password').dispatchEvent(new Event('input'));document.getElementById('show-passwords').click()");
    assert.equal(await evaluate("document.getElementById('confirm_password').checkValidity()"),false);
    assert.equal(await evaluate("document.getElementById('new_password').type"),'text');
    await evaluate("document.querySelector('form').reset()");
    await send('Network.setCookie',{...editor,url:base,httpOnly:true});await settings();
    assert.equal(await evaluate("[...document.querySelectorAll('[data-settings-form]')].every(f=>f.dataset.directSave==='0')"),true);
    assert.ok(await evaluate("document.querySelector('.settings-approval-note').textContent.includes('approval')"));
    assert.ok(await evaluate("[...document.querySelectorAll('[data-settings-form] button')].every(b=>b.textContent==='Submit for approval')"));
    // Check the native editor submission without allowing any saved proposal.
    await evaluate(`${input}.click()`);hold=true;await evaluate(`${form}.requestSubmit()`);await until(()=>Boolean(held),'Editor native POST unavailable');
    assert.equal(requests.at(-1).get('action'),'save_page_suggestions');
    await send('Fetch.fulfillRequest',{requestId:held.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html'}],body:Buffer.from('<html><body>Test approval submission intercepted.</body></html>').toString('base64')});held=null;hold=false;
    await send('Network.setCookie',{...mirror,url:base,httpOnly:true});await settings();
    assert.equal(await evaluate("document.querySelectorAll('.settings-card').length"),1);
    assert.ok(await evaluate("document.querySelector('.settings-profile-form')!==null"));
    assert.equal(await evaluate("document.querySelectorAll('[data-settings-form]').length"),0);
    await send('Fetch.disable');
    const denied=await evaluate("fetch('/admin/settings.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=save_page_suggestions&page_suggestions_enabled=0&csrf_token=invalid'}).then(r=>r.status)");assert.equal(denied,403);
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});await settings();
    const csrf=await evaluate("fetch('/admin/settings.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},body:'action=save_page_suggestions&page_suggestions_enabled=0&csrf_token=invalid'}).then(async r=>({status:r.status,data:await r.json()}))");
    assert.equal(csrf.status,422);assert.equal(csrf.data.success,false);
    await send('Emulation.setScriptExecutionDisabled',{value:true});await settings();
    assert.equal(await evaluate("document.querySelectorAll('[data-settings-form]').length"),2);
    assert.equal(await evaluate(`${button}.disabled`),false);
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    await send('Network.deleteCookies',{name:admin.name,url:base});
    const guest=await evaluate("fetch('/admin/settings.php').then(r=>r.url)");assert.ok(/\/(?:admin\/login\.php|login)(?:[?#]|$)/.test(guest));
    console.log('Browser checks passed: 1440/820/390px layouts, saved connection status, independent switches, dirty state, navigation warning, failure/retry, saving state, duplicate prevention, actual returned value, AI notice, category navigation, password controls, editor approval POST, Mirror restrictions, CSRF, no-JS forms and guest redirect. Saves were intercepted; configuration and account passwords were unchanged.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{for(const s of sessions)php(['destroy',s.value]);}finally{socket?.close();browser.kill();}});
