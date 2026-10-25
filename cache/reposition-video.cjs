const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const adminSession={};
const auditDirectory=path.join('cache','admin-design-audit');fs.mkdirSync(auditDirectory,{recursive:true});
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-calendar-widget-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*facebook.com*','*youtube.com*']});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"try{sessionStorage.setItem('gncIntroSeen','1')}catch(_){}"});




 await send('Page.navigate',{url:'file:///C:/Users/justi/Videos/Screen%20Recordings/Screen%20Recording%202026-10-23%20201209.mp4'});
 await until(()=>evaluate('Boolean(document.querySelector("video")?.readyState>=2)'),'Video not ready');
 console.log(await evaluate('JSON.stringify({duration:document.querySelector("video").duration})'));

 await send('Emulation.setDeviceMetricsOverride',{width:1280,height:720,deviceScaleFactor:1,mobile:false});
 await evaluate("document.querySelector('video').style.cssText='position:fixed;inset:0;width:100vw;height:100vh;object-fit:contain'");
 for(const time of [1,3,5,7]){
 await evaluate(`(async()=>{const v=document.querySelector('video');v.pause();v.currentTime=`+time+`;await new Promise(r=>v.addEventListener('seeked',r,{once:true}));})()`);
 const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync('cache/reposition-video-'+time+'.png',Buffer.from(shot.data,'base64'));
 }
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>{if(socket)socket.close();browser.kill()});