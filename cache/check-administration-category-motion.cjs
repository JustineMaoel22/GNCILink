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
    await visit('/administration');
    assert.equal(await evaluate('document.documentElement.classList.contains("gnc-shared-entrance")'),true,'Initial Administration visit keeps the opening effect');
    const categories=await evaluate(`Array.from(document.querySelectorAll('.admin-tab')).map(a=>({href:new URL(a.href).pathname+new URL(a.href).search,title:a.textContent.trim()}))`);
    assert.ok(categories.length>1,'Administration has category links');
    for(const tab of categories){
        await visit(tab.href);
        assert.equal(await evaluate('Boolean(document.getElementById("gncPageEntrance"))'),false,'No page wipe when switching '+tab.title);
        assert.equal(await evaluate('document.querySelectorAll(".admin-scroll-pending,.admin-scroll-revealing").length'),0,'No card reveal when switching '+tab.title);
        assert.equal(await evaluate('document.querySelector(".admin-tab.active").textContent.trim()'),tab.title,'Selected category still renders');
        if(await evaluate('Boolean(document.querySelector(".hss-flip-card"))')){
            await evaluate('document.querySelector(".hss-flip-card").click()');
            assert.equal(await evaluate('document.querySelector(".hss-flip-card").getAttribute("aria-expanded")'),'true','Office card interaction remains available');
        }
    }
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:1000,deviceScaleFactor:1,mobile:true});
    await visit(categories[1].href);
    assert.equal(await evaluate('Boolean(document.getElementById("gncPageEntrance"))||Boolean(document.querySelector(".admin-scroll-revealing"))'),false,'Mobile category switches without animation');
    console.log('All '+categories.length+' Administration categories switch without page/card entrance animations. Navigation and office-card interaction passed on desktop; mobile category check passed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);socket?.close();browser.kill();
});
