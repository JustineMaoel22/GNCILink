const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const php=(file,args=[])=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php',file,...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
const sessions=[];let fixture,socket;
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-approvals-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
(async()=>{
    fixture=JSON.parse(php('cache/approval-fixtures.php'));
    const session=role=>{const s=JSON.parse(php('cache/activity-log-session.php',[role]));sessions.push(s);return s;};
    const admin=session('Super Admin'),editor=session('Content Editor');
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';await send('Page.enable');await send('Network.enable');
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'&&Boolean(document.getElementById('approval-filters'))"),'Approvals unavailable');};
    const size=async width=>{await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);};
    const shot=async name=>fs.writeFileSync('cache/'+name+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});await size(1440);
    await visit('/admin/submissions.php?q=GNCI+approval+QA');
    assert.equal(await evaluate("document.querySelectorAll('[data-review-open]').length"),7);
    await shot('approvals-desktop');
    const location=await evaluate('location.href');
    const link=`document.querySelector('[data-review-open][href*="id=${fixture.ids[0]}"]')`;
    await evaluate(`${link}.click()`);await until(()=>evaluate("document.getElementById('approval-dialog').open"),'Review did not open');
    assert.equal(await evaluate("document.activeElement.hasAttribute('data-review-close')"),true);
    assert.equal(await evaluate("document.querySelectorAll('.approval-comparison').length>0"),true);
    assert.equal(await evaluate("document.querySelector('.approval-review').textContent.includes('QA-should-not-appear')"),false);
    assert.equal(await evaluate("document.querySelector('.approval-review script')===null"),true);
    assert.ok(await evaluate("document.querySelector('form[data-review-label=\"Approve and apply\"]')!==null"));
    assert.ok(await evaluate("document.querySelector('form[data-review-label=\"Request revision\"]')!==null"));
    assert.ok(await evaluate("document.querySelector('form[data-review-label=\"Reject submission\"]')!==null"));
    await shot('approvals-review-desktop');
    await evaluate("document.querySelector('.approval-history').open=true");
    assert.ok(await evaluate("document.querySelector('.approval-history').textContent.includes('Earlier feedback')"));
    await evaluate("document.querySelector('form[data-review-label=\"Approve and apply\"]').requestSubmit()");
    await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Confirmation unavailable');
    assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/apply its changes to the website/);
    await evaluate("document.querySelector('.gnc-dialog-cancel').click()");
    await until(()=>evaluate("!document.querySelector('.gnc-dialog')"),'Confirmation did not close');
    assert.equal(await evaluate("document.querySelector('form[data-review-label=\"Approve and apply\"] button').disabled"),false);
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await until(()=>evaluate("!document.getElementById('approval-dialog').open"),'Escape did not close');
    await until(()=>evaluate("document.activeElement.hasAttribute('data-review-open')"),'Focus did not restore');
    assert.equal(await evaluate('location.href'),location);
    for(const width of [820,390]){await size(width);assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'));await shot('approvals-'+width);}
    await evaluate(`${link}.click()`);await until(()=>evaluate("document.getElementById('approval-dialog').open"),'Mobile review unavailable');
    assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'));await shot('approvals-review-mobile');
    await evaluate("document.querySelector('.approval-decision').open=true");
    const blank=await evaluate("document.querySelector('[name=reason]').checkValidity()");assert.equal(blank,false);
    await evaluate("document.querySelector('[data-review-close]').click()");
    await visit('/admin/submissions.php?q=GNCI+approval+QA&status=needs_revision');assert.equal(await evaluate("document.querySelectorAll('[data-review-open]').length"),1);
    await visit('/admin/submissions.php?q=GNCI+approval+QA&type=pages');assert.equal(await evaluate("document.querySelectorAll('[data-review-open]').length"),1);
    await evaluate("document.querySelector('[data-review-open]').click()");await until(()=>evaluate("document.getElementById('approval-dialog').open"),'Snapshot review unavailable');
    assert.ok(await evaluate("document.querySelector('.approval-preview-links a').href.includes('review_submission=')"));
    assert.equal(await evaluate("document.querySelector('form[data-review-label=\"Approve and apply\"]')"),null);
    await visit('/admin/submissions.php?q=GNCI+approval+QA+no+such+proposal');assert.match(await evaluate("document.querySelector('.approval-empty').textContent"),/No matching submissions/);
    await visit('/admin/submissions.php?q%5B%5D=x&id%5B%5D=x&from%5B%5D=x');assert.ok(await evaluate("document.querySelector('.approval-overview')!==null"));
    // No published content is changed: browser POST checks only send invalid CSRF.
    const invalid=await evaluate(`fetch('/admin/submissions.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'submission_id=${fixture.ids[0]}&revision=2&action=approve&csrf_token=invalid'}).then(r=>r.text())`);
    assert.ok(invalid.includes('Invalid request token'));
    await send('Network.setCookie',{...editor,url:base,httpOnly:true});
    await visit('/admin/submissions.php?q=GNCI+approval+QA');
    await evaluate(`${link}.click()`);await until(()=>evaluate("document.getElementById('approval-dialog').open"),'Editor review unavailable');
    assert.equal(await evaluate("document.querySelector('form[data-review-label=\"Approve and apply\"]')"),null);
    assert.equal(await evaluate("document.querySelector('form[data-review-label=\"Request revision\"]')"),null);
    // Direct link remains a usable full-page fallback when scripts are disabled.
    await send('Emulation.setScriptExecutionDisabled',{value:true});await visit(`/admin/submissions.php?q=GNCI+approval+QA&id=${fixture.ids[0]}`);
    assert.ok(await evaluate("document.querySelector('#submission-review')!==null"));
    console.log('Browser checks passed: 1440/820/390px, modal focus/Escape, comparisons, safe preview, confirmation cancel, feedback/history, filters, snapshot routing, empty state, malformed input, CSRF, editor controls and no-JS fallback.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    try{if(fixture)php('cache/approval-fixtures.php',['destroy',JSON.stringify(fixture.ids)]);for(const s of sessions)php('cache/activity-log-session.php',['destroy',s.value]);}
    finally{socket?.close();browser.kill();}
});
