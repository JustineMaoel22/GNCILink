const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
function docker(args) {
    const r = spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if (r.status !== 0) throw Error(r.stderr || r.stdout);
    return r.stdout;
}
const profile = fs.mkdtempSync(path.join(os.tmpdir(),'gnci-admission-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(r=>setTimeout(r,ms));
async function until(fn,message) { for(let end=Date.now()+30000;Date.now()<end;) { if(await fn())return;await delay(100); }throw Error(message); }
let socket, admin;
// Content writes and private draft saves are intercepted; the published site is never modified.
function intercept(snapshot = null) {
    return `window.__writes=[];window.__snapshot=${JSON.stringify(snapshot)};
        const original=window.fetch;window.__originalFetch=original;window.fetch=async function(url,opts){
            const body=opts?.body, action=body instanceof FormData?body.get('action'):null;
            if(String(url).includes('editor-draft-handler')){
                if(action==='save')window.__snapshot=body.get('snapshot');
                return new Response(JSON.stringify({success:true,revision:action==='load'?(__snapshot?1:0):1,draft:action==='load'&&__snapshot?{revision:1,snapshot:JSON.parse(__snapshot)}:null}),{headers:{'Content-Type':'application/json'}});
            }
            if(opts?.method==='POST'&&!String(url).includes('editor-presence')){
                window.__writes.push({url:String(url),action,sections:body.get('sections'),orders:body.get('orders')});
                const sections={};for(const s of JSON.parse(body.get('sections')||'[]'))sections[s.key]={success:true,has_draft:false,review_status:'none',editor_html:s.content};
                return new Response(JSON.stringify({success:true,sections}),{headers:{'Content-Type':'application/json'}});
            }
            return original.apply(this,arguments);
        };`;
}
(async()=>{
    admin=JSON.parse(docker(['Super Admin']));
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    await send('Page.enable');await send('Network.enable');
    await send('Network.setCookie',{...admin,url:'http://localhost:8080',httpOnly:true});
    const script=await send('Page.addScriptToEvaluateOnNewDocument',{source:intercept()});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const visit=async()=>{
        await send('Page.navigate',{url:'http://localhost:8080/admin/content-edit.php?page=basic-education-admission-requirements'});
        await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-admission-add-school]'))`),'Department category control unavailable');
    };
    await visit();
    const unverifiedPublish=await evaluate(`(async()=>{const body=new FormData();body.set('action','publish_page');body.set('page','basic-education-admission-requirements');body.set('csrf_token','invalid');return (await __originalFetch('/admin/action/admission-category-handler.php',{method:'POST',body})).status;})()`);
    assert.ok([403,428].includes(unverifiedPublish),'Unverified publishing request was accepted');
    await evaluate(`window.doc=()=>document.getElementById('veFrame').contentDocument;doc().querySelector('[data-ve-admission-add-school]').click();`);
    await until(()=>evaluate(`Boolean(document.querySelector('.ve-pop input'))`),'Name input unavailable');
    await evaluate(`document.querySelector('.ve-pop input').value='QA Department';document.querySelector('.ve-pop input').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));`);
    await until(()=>evaluate(`doc().querySelectorAll('[data-basic-school-panel]').length===5`),'Department was not added');
    const school=await evaluate(`doc().querySelector('[data-basic-school^="dept-"]').getAttribute('data-basic-school')`);
    await evaluate(`window.school=${JSON.stringify(school)};window.scope=()=>doc().querySelector('[data-admission-scope="'+school+'"]');scope().querySelector('[data-ve-admission-add-item]').click();`);
    await evaluate(`document.querySelector('.ve-pop textarea').value='Original report card';document.querySelector('.ve-pop button.btn-gnc').click();`);
    assert.equal(await evaluate(`scope().querySelectorAll('ol li').length`),1);
    await evaluate(`scope().querySelector('[data-ve-admission-add-category]').click();`);
    await evaluate(`document.querySelector('.ve-pop input').value='Transferees';document.querySelector('.ve-pop input').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));`);
    assert.equal(await evaluate(`scope().querySelectorAll('[data-basic-applicant]').length`),2);
    await evaluate(`const label=doc().querySelector('[data-ve-section="school_label_'+school+'"]');label.textContent='QA Department renamed';label.dispatchEvent(new Event('input',{bubbles:true}));`);
    assert.equal(await evaluate(`scope().querySelector('.basic-school-title').textContent`),'QA Department renamed');
    assert.deepEqual(await evaluate('__writes'),[],'Adding departments wrote live content');
    await evaluate(`document.getElementById('veSaveDraft').click()`);
    await until(()=>evaluate(`Boolean(__snapshot)`),'Private draft was not saved');
    const snapshot=await evaluate('__snapshot');
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:script.identifier});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:intercept(snapshot)});
    await visit();
    await until(()=>evaluate(`Boolean(document.getElementById('veFrame').contentDocument.querySelector('[data-basic-school^="dept-"]'))`),'Department did not restore from private draft');
    await evaluate(`window.doc=()=>document.getElementById('veFrame').contentDocument;window.school=${JSON.stringify(school)};window.scope=()=>doc().querySelector('[data-admission-scope="'+school+'"]');doc().querySelector('[data-basic-school="'+school+'"]').click();`);
    assert.equal(await evaluate(`scope().querySelectorAll('[data-basic-applicant]').length`),2);
    assert.equal(await evaluate(`scope().querySelector('ol li [data-ve-admission-item-text]').textContent`),'Original report card');
    assert.deepEqual(await evaluate(`Array.from(scope().querySelectorAll('[data-basic-applicant]')).map(tab=>tab.textContent)`),['New Students','Transferees'],'Draft restore changed applicant order');
    for(const width of [1440,768,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await evaluate(`(()=>{const tab=scope().querySelector('[data-basic-applicant]');tab.click();tab.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));})()`);
        assert.equal(await evaluate(`scope().querySelectorAll('[data-basic-applicant-panel]:not([hidden])').length`),1);
        assert.equal(await evaluate(`scope().querySelectorAll('[data-basic-applicant]')[1].getAttribute('aria-selected')`),'true');
        assert.equal(await evaluate(`doc().documentElement.scrollWidth<=doc().documentElement.clientWidth`),true,'Preview overflow at '+width);
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const screenshot=path.join(profile,'department-editor.png');
    fs.writeFileSync(screenshot,Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    console.log('Editor screenshot:',screenshot);
    await evaluate(`window.ContentCompleteness=undefined;window.AdminPublish={begin:async()=>true,end(){}};document.getElementById('vePrimary').click();`);
    await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept'))`),'Publish confirmation unavailable');
    await evaluate(`document.querySelector('.ve-publish-accept').click()`);
    await until(()=>evaluate(`__writes.some(w=>w.action==='publish_page')`),'Department publish payload unavailable');
    const write=await evaluate(`__writes.find(w=>w.action==='publish_page')`);
    const changes=JSON.parse(write.sections),orders=JSON.parse(write.orders);
    assert.equal(changes.find(s=>s.key==='school_label_'+school).removed,false);
    assert.equal(orders[school].length,2);
    assert.ok(changes.some(s=>s.content.includes('Original report card')));
    console.log('PASS: Department creation, requirement editing, rename, private draft restore, scoped keyboard tabs, desktop/tablet/mobile layout and publish payload. Content writes mocked.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    socket?.close();browser.kill();if(admin)docker(['destroy',admin.value]);
});
