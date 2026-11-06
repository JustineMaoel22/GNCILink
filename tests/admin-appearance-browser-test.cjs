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
    await visit('/admin/settings.php');
    await until(()=>evaluate("Boolean(document.getElementById('admin-font-size'))"),'Font setting unavailable');
    const standard=await evaluate("parseFloat(getComputedStyle(document.documentElement).fontSize)");
    await evaluate("const select=document.getElementById('admin-font-size');select.value='extra-large';select.dispatchEvent(new Event('change'))");
    assert.equal(await evaluate("parseFloat(getComputedStyle(document.documentElement).fontSize)"),standard*1.25);
    await visit('/admin/admin-dash.php');
    await until(()=>evaluate("document.documentElement.dataset.adminFontSize==='extra-large'"),'Size did not persist across pages');
    assert.equal(await evaluate("parseFloat(getComputedStyle(document.documentElement).fontSize)"),standard*1.25);
    await visit('/admin/settings.php');
    await until(()=>evaluate("document.getElementById('admin-font-size')?.value==='extra-large'"),'Selection did not restore');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await delay(350);
        const overflow=await evaluate("({width:innerWidth,scroll:document.documentElement.scrollWidth})");
        assert.ok(overflow.scroll<=overflow.width,'Settings overflow: '+JSON.stringify(overflow));
        fs.writeFileSync(`cache/settings-font-${width}.png`,Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await visit('/');
    assert.equal(await evaluate("document.documentElement.hasAttribute('data-admin-font-size')"),false);
    console.log('Font size checks passed: 125% sizing, automatic persistence, dashboard navigation, restored selection, desktop/mobile overflow, public page unchanged.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});