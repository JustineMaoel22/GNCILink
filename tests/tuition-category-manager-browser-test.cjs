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

    await visit('/admin/content-edit.php?page=tuition-fees');
    await until(()=>evaluate('Boolean(window.VE?.state.doc)&&!VE.state.busy&&document.getElementById("veLoader").hidden'),'Editor ready');
    await evaluate(`window.__writes=[];const original=window.fetch;window.fetch=function(url,opts){if(opts?.method==='POST'&&!String(url).includes('editor-presence-handler'))__writes.push(String(url));return original.apply(this,arguments);};window.__baseline=Object.keys(VE.state.adapter.draftState().categories);VE.state.doc.querySelector('[data-ve-fee-categories]').click();`);
    assert.equal(await evaluate('document.querySelector(".sm-dialog h2").textContent'),'Manage fee categories');
    await evaluate(`document.querySelector('[aria-label="Actions for category College"]').click();Array.from(document.querySelectorAll('.sm-menu:popover-open button')).find(b=>b.textContent==='Edit category').click();document.getElementById('sm-category-name').value='College Fees';document.getElementById('sm-category-icon').value='bank';document.querySelector('.sm-form button[type=submit]').click();`);
    assert.equal(await evaluate('VE.state.adapter.draftState().categories.college.label'),'College Fees');
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-fee-category=college] i").className'),'bi bi-bank');
    await evaluate(`document.querySelector('[aria-label="Actions for category College Fees"]').click();document.querySelector('[aria-label="Move category College Fees down"]').click();`);
    assert.equal(await evaluate('Object.keys(VE.state.adapter.draftState().categories)[1]'),'college');
    await evaluate(`Array.from(document.querySelectorAll('.sm-controls button')).find(b=>b.textContent.trim()==='Add category').click();document.getElementById('sm-category-name').value='Test Fee Category';document.getElementById('sm-category-icon').value='folder';document.querySelector('.sm-form button[type=submit]').click();`);
    assert.equal(await evaluate('Object.values(VE.state.adapter.draftState().categories).at(-1).label'),'Test Fee Category');
    await evaluate(`document.querySelector('[aria-label="Search categories or PDF documents"]').value='Test Fee';document.querySelector('[aria-label="Search categories or PDF documents"]').dispatchEvent(new Event('input'));`);
    assert.equal(await evaluate('document.querySelectorAll(".sm-category").length'),1);
    await evaluate(`document.querySelector('[aria-label="Actions for category Test Fee Category"]').click();Array.from(document.querySelectorAll('.sm-menu:popover-open button')).find(b=>b.textContent==='Delete category').click();Array.from(document.querySelectorAll('.sm-form-actions button')).find(b=>b.textContent==='Delete category').click();`);
    assert.equal(await evaluate('Object.values(VE.state.adapter.draftState().categories).some(c=>c.label==="Test Fee Category")'),false);
    await evaluate(`document.querySelector('.sm-controls input').value='';document.querySelector('.sm-controls input').dispatchEvent(new Event('input'));`);
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});await delay(100);
        assert.equal(await evaluate('document.querySelector(".sm-dialog").scrollWidth<=document.querySelector(".sm-dialog").clientWidth+1'),true,'Dialog fits '+width);
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(__dirname,'../cache/fee-categories-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }
    await evaluate(`document.querySelector('[aria-label="Add PDF document to College Fees"]').click();`);
    await until(()=>evaluate('document.querySelector(".sm-dialog")===null'),'PDF control returns to existing manager');
    assert.equal(await evaluate('Array.from(document.querySelectorAll("#vePop button")).some(b=>b.textContent==="Add PDF")'),true);
    assert.deepEqual(await evaluate('__writes'),[],'No content writes');
    console.log('Fee manager add/edit/icon/reorder/search/delete, responsive layout and PDF handoff passed. No content published.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});
