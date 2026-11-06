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

    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page load timeout');};
    for (const width of [390,768,1024]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:1024,deviceScaleFactor:1,mobile:true});
        await visit('/core-values');await delay(2500);
        await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close())");
        const layout=await evaluate(`(()=>{const n=document.querySelector('.core-values-breadcrumb'), a=n.firstElementChild.getBoundingClientRect(), b=n.lastElementChild.getBoundingClientRect();return {center:(a.left+b.right)/2,width:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth,justify:getComputedStyle(n).justifyContent};})()`);
        assert.equal(layout.overflow,false,'No page overflow at '+width);
        if(width<=768) assert.ok(Math.abs(layout.center-width/2)<2,'Centered breadcrumb at '+width+': '+JSON.stringify(layout));
        else assert.equal(layout.justify,'normal','Desktop alignment stays unchanged');
        if(width===768)fs.writeFileSync('cache/core-values-breadcrumb-768.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        console.log(width+'px: '+JSON.stringify(layout));
    }
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();browser.kill();});
