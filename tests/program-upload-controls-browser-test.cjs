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


 await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=bael'});
 const frame='document.getElementById("veFrame")?.contentDocument';
 await until(()=>evaluate('Boolean('+frame+'?.querySelector("[data-ve-field][data-ve-key=mission_text]")?.__ve)'),'Program editor unavailable');
 assert.equal(await evaluate('Boolean(document.getElementById("veDrawer")||document.getElementById("veSettingsBtn")||document.querySelector(".pgm-card"))'),false,'No program form sidebar is rendered');
 await evaluate(frame+'.querySelector("[data-ve-program-section=uniform]").click()');
 assert.equal(await evaluate('document.querySelector(".ve-dock-tab.is-on").textContent.includes("Uniform photos")'),true,'Uniforms use visual slide strip');
 assert.equal(await evaluate('Boolean(document.querySelector("#veDock .ve-add-slide"))'),true,'Uniform upload control available');
 await evaluate(frame+'.querySelector("[data-ve-program-section=curriculum]").click()');
 assert.equal(await evaluate('[...document.querySelectorAll("#vePop button")].some(b=>b.textContent==="Upload or replace PDF")'),true,'Curriculum upload opens next to the page');
 await evaluate('document.getElementById("vePageOpts").click()');
 assert.equal(await evaluate('Boolean(document.querySelector("#vePop select"))'),true,'Department assignment remains available');
 assert.equal(await evaluate('[...document.querySelectorAll("#vePop button")].some(b=>b.textContent==="Replace program seal")'),true,'Seal replacement remains available');
 console.log('PASS: program form sidebar removed; uniforms, PDF, department and seal controls remain available in visual tools.');
 await evaluate('document.getElementById("vePop").hidden=true');
 const screenshot=await send('Page.captureScreenshot',{format:'png'});
 fs.writeFileSync(path.join(auditDirectory,'program-visual-editor-no-sidebar.png'),Buffer.from(screenshot.data,'base64'));
 const html=await(await fetch(base+'/pages/academics/college/program.php?slug=bael')).text();
 assert(!html.includes('data-ve-program-section='),'No editor upload controls on public page');
 console.log('PASS: public page contains no editor controls; no published content changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
