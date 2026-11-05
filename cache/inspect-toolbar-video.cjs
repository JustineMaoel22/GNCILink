const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'alumni-voices-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,label){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(150);}throw Error(label);}
let socket,session,cleanup;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();
    const listen=event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}if(p.method==='Page.javascriptDialogOpening')socket.send(JSON.stringify({id:++serial,method:'Page.handleJavaScriptDialog',params:{accept:true}}));};
    socket.addEventListener('message',listen);
    const reconnect=async()=>{socket.close();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));socket.addEventListener('message',listen);};
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Timed out: '+method+' '+(params.expression||'').slice(0,60)));},15000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
    const ev=expression=>new Promise((resolve,reject)=>{
        const client=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
        const timer=setTimeout(()=>{client.close();reject(Error('Evaluation timed out: '+expression.slice(0,60)));},12000);
        client.addEventListener('open',()=>client.send(JSON.stringify({id:1,method:'Runtime.evaluate',params:{expression,returnByValue:true,awaitPromise:true,timeout:10000}})),{once:true});
        client.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id!==1)return;clearTimeout(timer);client.close();if(p.error||p.result.exceptionDetails)reject(Error(JSON.stringify(p.error||p.result.exceptionDetails)));else resolve(p.result.result.value);});
    });
    const visit=async route=>{await send('Page.navigate',{url:'http://localhost:8080'+route});await until(()=>ev("document.readyState==='complete'"),'Page unavailable');await delay(2200);await ev("document.querySelector('#gncSiteDisclaimer form button')?.click();document.querySelector('[data-privacy-action=\"reject\"]')?.click()");};
    await send('Page.enable');await send('Network.enable');await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:'file:///C:/Users/justi/Videos/Screen%20Recordings/Screen%20Recording%202026-11-05%20180137.mp4'});
    await until(()=>ev("Boolean(document.querySelector('video')?.readyState>=2)"),'Video did not load');
    const duration=await ev("document.querySelector('video').duration");console.log({duration});
    for(const [i,t]of [0.1,duration*.25,duration*.5,duration*.75,duration-.3].entries()){
        await ev(`(()=>{const v=document.querySelector('video');v.pause();v.currentTime=${t};return new Promise(r=>v.addEventListener('seeked',r,{once:true}))})()`);
        const data=(await send('Page.captureScreenshot',{format:'png'})).data;
        fs.writeFileSync('cache/toolbar-recording-'+i+'.png',Buffer.from(data,'base64'));
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{try{if(cleanup)await cleanup();}finally{socket?.close();browser.kill();if(session)spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','destroy',session.value],{windowsHide:true});}});

