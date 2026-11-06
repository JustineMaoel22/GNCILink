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
    for(const page of ['/admin/facebook-posts.php','/admin/news.php','/admin/announcement.php','/admin/events.php']){
        await visit(page);await until(()=>evaluate('Boolean(window.GNCImageFraming)'),'Image framing API missing');
        const rect=await evaluate(`(()=>{const image=document.querySelector('.fb-thumbnail,.ann-thumb');if(!image)return null;image.scrollIntoView({block:'center',behavior:'instant'});const r=image.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()`);
        if(rect)await send('Input.dispatchMouseEvent',{type:'mouseMoved',...rect});
        await delay(100);
        assert.equal(await evaluate('Boolean(document.querySelector(".image-frame-trigger:not([hidden])"))'),false,'Reposition absent on '+page);
        await evaluate(`void GNCImageFraming.edit(document.querySelector('img'))`);
        assert.equal(await evaluate('Boolean(document.querySelector(".image-frame-dialog"))'),false,'Existing-image edit unavailable outside editor');
        console.log('No Reposition control:',page);
    }
    await visit('/admin/content-edit.php?page=home');
    await until(()=>evaluate('Boolean(window.VE?.state.doc)&&!VE.state.busy&&document.getElementById("veLoader").hidden'),'Content Editor not ready');
    await evaluate(`window.__image=Array.from(VE.state.doc.querySelectorAll('img')).find(img=>VE.state.win.GNCImageFraming.findTarget(img)&&!img.closest('header,footer,nav'));__image.scrollIntoView({block:'center',behavior:'instant'});`);
    const rect=await evaluate(`(()=>{const r=__image.getBoundingClientRect(),frame=document.getElementById('veFrame').getBoundingClientRect();return {x:frame.x+r.x+r.width/2,y:frame.y+r.y+r.height/2};})()`);
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',...rect});
    await until(()=>evaluate('Boolean(VE.state.doc.querySelector(".image-frame-trigger:not([hidden])"))'),'Editor Reposition control missing');
    await evaluate(`VE.state.doc.querySelector('.image-frame-trigger:not([hidden])').click()`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]")||VE.state.doc.querySelector(".image-frame-dialog[open]"))'),'Editor reposition dialog missing');
    await evaluate(`const dialog=document.querySelector('.image-frame-dialog[open]')||VE.state.doc.querySelector('.image-frame-dialog[open]');Array.from(dialog.querySelectorAll('button')).find(b=>b.textContent==='Cancel').click()`);
    console.log('Content Editor repositioning remains available. No image settings saved.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);socket?.close();browser.kill();
});
