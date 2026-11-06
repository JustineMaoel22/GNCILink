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
    for(const page of ['/admin/facebook-posts.php']){
        await visit(page);
        await until(()=>evaluate('Boolean(document.querySelector(".content-icon-action"))'),'Icon actions missing: '+page);
        assert.equal(await evaluate(`Array.from(document.querySelectorAll('.content-icon-action')).every(b=>Boolean(b.getAttribute('aria-label')&&b.querySelector('i')))`),true,'Actions have accessible names');
        for(const width of [1440,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
            const rect=await evaluate(`(()=>{window.__action=document.querySelector('.content-icon-action:not(:disabled)');__action.scrollIntoView({block:'center',behavior:'instant'});const r=__action.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2,width:r.width,height:r.height};})()`);
            assert.ok(rect.width<=44&&rect.height<=44&&rect.width>=36,'Compact icon button');
            await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:rect.x,y:rect.y});
            await until(()=>evaluate(`Boolean(document.querySelector('.tooltip.show .tooltip-inner'))`),'Hover tooltip missing');
            assert.ok((await evaluate(`document.querySelector('.tooltip.show .tooltip-inner').textContent`)).includes(await evaluate('__action.getAttribute("aria-label")')),'Hover names the action');
            await delay(200);
            fs.writeFileSync('cache/icon-actions-'+page.split('/').at(-1).replace('.php','')+'-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
            await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:1,y:1});
            await evaluate('bootstrap.Tooltip.getInstance(__action).hide()');
            await delay(150);
        }
        console.log('Icon buttons and hover tooltips:',page);
    }
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);socket?.close();browser.kill();
});
