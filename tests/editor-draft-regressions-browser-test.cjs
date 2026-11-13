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

    await visit('/admin/content-edit.php?page=administration');
    await until(()=>evaluate('Boolean(window.VE?.state.doc)&&!VE.state.busy&&document.getElementById("veLoader").hidden'),'Administration ready');
    const result=await evaluate(`(async()=>{
        const adapter=VE.state.adapter;
        // Reproduce the empty-map shape returned by PHP, without altering any draft.
        adapter.draftState({edits:{sec:[],person:[],office:[],order:[]},additions:[],nextId:-1});
        const field=VE.state.doc.querySelector('[data-ve-field="admin-section-title"]');
        field.textContent='Draft regression heading';field.dispatchEvent(new Event('input',{bubbles:true}));
        const state=adapter.draftState();
        state.additions[-99]={kind:'person',section:'board-of-trustees',item:{id:-99,name:'Test'}};
        const packed=JSON.parse(await GNCEditorDrafts.encode({version:1,state,preview:{}}));
        return {heading:packed.state.edits.sec['board-of-trustees'].title,newPerson:packed.state.additions[-99].item.name,sectionMap:!Array.isArray(state.edits.sec)};
    })()`);
    assert.deepEqual(result,{heading:'Draft regression heading',newPerson:'Test',sectionMap:true});
    console.log('Restored Administration heading and negative draft IDs survive encoding');
    await visit('/admin/content-edit.php?page=history');
    await until(()=>evaluate('Boolean(window.VE?.state.doc)&&!VE.state.busy&&document.getElementById("veLoader").hidden'),'History ready');
    await evaluate(`window.__originalFetch=window.fetch;window.__saves=[];
        window.fetch=async function(url,opts){if(String(url).includes('editor-draft-handler')&&opts?.body?.get('action')==='save'){__saves.push(JSON.parse(opts.body.get('snapshot')));return new Response(JSON.stringify({success:true,revision:__saves.length}),{headers:{'Content-Type':'application/json'}});}return __originalFetch.apply(this,arguments);};
        window.__field=Array.from(VE.state.doc.querySelectorAll('[contenteditable]')).find(e=>e.isContentEditable&&e.__ve&&!e.closest('header,footer'));
        window.__original=__field.innerHTML;__field.textContent='Draft regression text';__field.dispatchEvent(new Event('input',{bubbles:true}));`);
    await until(()=>evaluate('!document.getElementById("veSaveDraft").disabled'),'Save enabled');
    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('!VE.state.busy&&__saves.length===1'),'First mocked save');
    await evaluate('document.getElementById("veUndo").click()');await delay(100);
    assert.equal(await evaluate('__field.innerHTML===__original'),true);
    assert.equal(await evaluate('VE.state.adapter.dirtyCount()'),0);
    assert.equal(await evaluate('document.getElementById("veSaveDraft").disabled'),false);
    assert.equal(await evaluate('document.querySelector(".ve-status").textContent.includes("Unsaved draft changes")'),true);
    assert.equal(await evaluate(`(()=>{const e=new Event('beforeunload',{cancelable:true});window.dispatchEvent(e);return e.defaultPrevented;})()`),true);
    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('!VE.state.busy&&__saves.length===2'),'Save undone draft');
    assert.equal(await evaluate('document.getElementById("veSaveDraft").disabled'),true);
    assert.equal(await evaluate(`__saves[1].preview.fields.some(f=>f.html===__original)`),true);
    console.log('Undo to published content remains saveable, warns before leaving, and updates checkpoint');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});
