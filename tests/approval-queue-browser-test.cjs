// Generate the sample panel first: docker compose exec -T web php tests/approval-view-test.php --preview
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const docker = args => {
    const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if(r.status!==0) throw Error(r.stderr||r.stdout);
    return r.stdout;
};
const sessions = [];
const session = role => {const s=JSON.parse(docker([role]));sessions.push(s);return s;};
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-logs-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket;
(async()=>{
    const admin=session('Super Admin');
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page load timeout');};
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await visit('/admin/submissions.php');
    await until(()=>evaluate("Boolean(document.getElementById('approval-filters'))"),'Approvals unavailable');
    assert.equal(await evaluate("Boolean(document.querySelector('.approval-page-text'))"),false);
    assert.equal(await evaluate("Array.from(document.querySelectorAll('[role=alert]')).some(e=>e.textContent.includes('queue could not'))"),false);
    const redirect=await evaluate("fetch('/admin/content-edit.php?page=review').then(r=>r.url)");
    assert.ok(redirect.includes('status=pending')&&!redirect.includes('content-editor'),'Old review URL did not open combined queue');
    await evaluate(`(async()=>{window.__sectionPreview=await(await fetch('/cache/unified-section-preview.html')).text();const original=window.fetch;window.fetch=function(url,options){if(String(url).includes('__section_preview=1'))return Promise.resolve(new Response(__sectionPreview,{headers:{'Content-Type':'text/html'}}));return original.apply(this,arguments);};const link=document.createElement('a');link.href='/admin/submissions.php?id=1&source=section&__section_preview=1';link.setAttribute('data-review-open','');link.id='section-preview-link';link.textContent='Sample section';document.querySelector('.approval-page').append(link);link.click();})()`);
    await until(()=>evaluate("document.getElementById('approval-dialog').open"),'Section review modal unavailable');
    assert.ok(await evaluate("document.getElementById('approval-dialog').textContent.includes('Current published text')"));
    assert.ok(await evaluate("document.getElementById('approval-dialog').textContent.includes('Proposed text')"));
    await evaluate("document.querySelector('#approval-dialog .approval-review-actions form button').click()");
    await until(()=>evaluate("Boolean(document.getElementById('publish-password'))"),'Page-text approval did not require password');
    await evaluate("document.querySelector('.gnc-dialog-cancel').click()");
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);
        assert.ok(await evaluate("document.getElementById('approval-dialog').scrollWidth<=innerWidth"),'Review modal overflow');
        fs.writeFileSync(`cache/unified-review-${width}.png`,Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await evaluate("document.querySelector('#approval-dialog [data-review-close]').click()");
    assert.equal(await evaluate("document.getElementById('approval-dialog').open"),false);
    console.log('Combined Approvals loads; separate banner removed; old review links redirect; sample section opens in same review dialog; approval requires password; cancellation/close and desktop/mobile layouts verified. No content saved.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});