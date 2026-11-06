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
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
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
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:950,deviceScaleFactor:1.25,mobile:true});
    await visit('/gnc-history');await delay(2500);await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close())");
    fs.writeFileSync('cache/history-mobile-seam-public.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await visit('/admin/content-edit.php?page=history');
    await until(()=>evaluate('Boolean(window.VE?.state.doc)&&!VE.state.busy&&document.getElementById("veLoader").hidden'),'Editor not ready');
    await evaluate(`document.querySelector('.ve-devices button:last-child').click()`);await delay(500);
    fs.writeFileSync('cache/history-mobile-seam-editor.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await delay(2500);
    assert.equal(await evaluate(`VE.state.win.getComputedStyle(VE.state.doc.querySelector('.history-hero')).overflow`),'visible','Curve must extend past the hero edge');
    assert.equal(await evaluate(`VE.state.win.getComputedStyle(VE.state.doc.querySelector('.history-hero-art')).overflow`),'hidden','Moving photo stays clipped');
    assert.equal(await evaluate(`VE.state.doc.querySelector('#ghHeroBg').parentElement.className`),'history-hero-art');
    for(const zoom of [0.9,0.93,1,1.1]){
        await evaluate(`document.documentElement.style.zoom=${zoom}`);await delay(150);
        fs.writeFileSync('cache/history-mobile-seam-'+zoom+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    console.log('History mobile hero checked publicly and in the editor at 90%, 93%, 100% and 110% zoom. Photo clipping is separate from the overlapping curve; no content saved.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{for(const s of sessions)docker(['destroy',s.value]);socket?.close();browser.kill();});
