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
    socket.addEventListener('message',event=>{const m=JSON.parse(event.data);if(m.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:true});});
    async function setup(fail,route='/admin/content-edit.php?page=vision-mission'){
        await visit(route);
        await until(()=>evaluate("Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[contenteditable=true]'))"),'Editable page unavailable');
        await evaluate(`window.__publicationRequests=[];sessionStorage.removeItem('recent-publication-last');window.__publicationFail=${fail};window.AdminPublish.begin=async()=>true;window.AdminPublish.end=()=>{};window.ContentCompleteness.inspect=()=>({missing:[]});const original=window.fetch;window.fetch=async function(url,options){if(options?.method==='POST'){const body=options.body;const action=body.get('action');window.__publicationRequests.push({url:String(url),action,page:body.get('page'),sub:body.get('sub'),title:body.get('title')});if(action==='record_publication')sessionStorage.setItem('recent-publication-last',JSON.stringify(window.__publicationRequests.at(-1)));if(String(url).includes('page-content-handler'))return new Response(JSON.stringify({success:true}),{headers:{'Content-Type':'application/json'}});if(String(url).includes('page-review-handler'))return new Response(JSON.stringify(window.__publicationFail?{success:false,error:'Simulated publish failure'}:{success:true,has_draft:false,review_status:'none',editor_html:body.get('content'),live_html:body.get('content')}),{headers:{'Content-Type':'application/json'}});if(String(url).includes('editor-draft-handler'))return new Response(JSON.stringify({success:true,revision:1}),{headers:{'Content-Type':'application/json'}});throw Error('Unexpected write blocked: '+url);}return original.apply(this,arguments);};const d=document.getElementById('veFrame').contentDocument;const field=d.querySelector('[contenteditable=true]');field.focus();field.innerHTML+='<p>Publication lifecycle test only.</p>';field.dispatchEvent(new Event('input',{bubbles:true}));`);
        await until(()=>evaluate("!document.getElementById('vePrimary').disabled"),'Change not detected');
    }
    async function publish(){
        await evaluate("document.getElementById('vePrimary').click()");
        await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-accept'))"),'Publish confirmation missing');
        await evaluate("document.querySelector('.ve-publish-accept').click()");
    }
    await setup(false);await publish();
    await until(()=>evaluate("__publicationRequests.some(r=>r.action==='record_publication')"),'Successful publication not recorded');
    const success=await evaluate('__publicationRequests');
    const recorded=success.find(r=>r.action==='record_publication');
    assert.equal(recorded.page,'vision-mission');assert.ok(recorded.title);
    assert.ok(success.findIndex(r=>r.action==='record_publication')>success.findIndex(r=>r.action==='publish'));
    await setup(false,'/admin/content-edit.php?page=basic-education&sub=jhs-regular');await publish();
    await until(()=>evaluate("Boolean(sessionStorage.getItem('recent-publication-last'))"),'Entity publication summary was lost during navigation');
    const entityRecord=await evaluate("JSON.parse(sessionStorage.getItem('recent-publication-last'))");
    assert.equal(entityRecord.page,'basic-education');assert.equal(entityRecord.sub,'jhs-regular');
    await setup(true);await publish();
    await until(()=>evaluate("__publicationRequests.some(r=>r.action==='publish')&&!document.getElementById('vePrimary').disabled"),'Failed publication did not finish');
    assert.equal(await evaluate("__publicationRequests.some(r=>r.action==='record_publication')"),false);
    await evaluate("window.__veLeaving=true");
    await send('Page.handleJavaScriptDialog',{accept:true}).catch(()=>{});
    console.log('Editor lifecycle verified: successful Publish records page metadata after the content save; failed Publish does not create a Recent Content entry. All writes mocked; no content published.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});