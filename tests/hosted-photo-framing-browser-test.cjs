const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
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

    await send('Network.setCookie',{name:adminSession.name,value:adminSession.value,url:base,httpOnly:true});


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=administration'});
    await until(()=>evaluate('Boolean(window.GNCImageFraming) && document.getElementById("veLoader").hidden'),'Administration editor unavailable');
    await evaluate(`window.framingErrors=[];window.addEventListener('error',event=>framingErrors.push(event.message))`);
    const result=await evaluate(`(()=>{const api=GNCImageFraming,detached=document.implementation.createHTMLDocument('detached'),img=detached.createElement('img');img.src='/assets/images/admission-header.png';detached.body.appendChild(img);api.apply(img,{x:50,y:50,zoom:2});api.watch(detached);return {view:detached.defaultView,source:api.sourceOf(img),target:api.findTarget(img),framed:img.hasAttribute('data-image-framed')};})()`);
    assert.equal(result.view,null);assert.equal(result.source,'');assert.equal(result.target,null);assert.equal(result.framed,false,'Detached document ignored before any styling');
    await evaluate(`(()=>{const api=GNCImageFraming,frame=document.createElement('iframe');document.body.appendChild(frame);const d=frame.contentDocument,img=d.createElement('img');img.src='/assets/images/admission-header.png';img.style.objectFit='cover';d.body.appendChild(img);api.watch(d);api.set('/assets/images/admission-header.png',{x:50,y:50,zoom:2});frame.remove();api.apply(img,{x:50,y:50,zoom:2});})()`);
    await delay(300);
    assert.deepEqual(await evaluate('framingErrors'),[],'Pending scans after iframe removal do not crash');
    const report=await evaluate(`fetch('/admin/action/page-content-handler.php?action=upload_environment').then(r=>r.json())`);
    assert.equal(report.success,true);assert.equal(typeof report.environment.extensions.mbstring,'boolean');assert.equal(typeof report.environment.administration_upload_writable,'boolean');
    const anonymous=await fetch(base+'/admin/action/page-content-handler.php?action=upload_environment',{redirect:'manual'});
    assert.equal(anonymous.status,302,'Runtime report requires an admin session');
    console.log('PASS: detached images and removed iframe scans are safe; upload environment report works and is authentication protected. No photos or records changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
