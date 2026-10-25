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


 socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:true});});
 const frame='document.getElementById("veFrame")?.contentDocument';
 for(const [page,sub,kind,field] of [['college-departments','cased','department-statement','mission_text'],['programs','bael','program-statement','mission_text']]) {
  await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page+'&sub='+sub});
  await until(()=>evaluate('Boolean('+frame+'?.querySelector("[data-ve-field][data-ve-key='+field+']")?.__ve)'),'Editor not ready: '+page);
  for(const [key,search,title,glyph] of [['vision_icon','bullseye','Bullseye (Bootstrap Icons)','bi-bullseye'],['mission_icon','school','School (Material Symbols)','school']]) {
   const selector='[data-ve-icon="'+kind+'"][data-ve-key="'+key+'"]';
   const before=await evaluate('(()=>{const el='+frame+'.querySelector('+JSON.stringify(selector)+');el.scrollIntoView({block:"center"});const r=el.getBoundingClientRect();return {width:r.width,height:r.height,background:getComputedStyle(el).backgroundColor};})()');
   await evaluate(frame+'.querySelector('+JSON.stringify(selector)+').click()');
   await until(()=>evaluate('[...document.querySelectorAll("button")].some(el=>el.textContent.trim()==="Change icon")'),'Change icon action missing: '+page);
   await evaluate('[...document.querySelectorAll("button")].find(el=>el.textContent.trim()==="Change icon").click()');
   const searchSelector = JSON.stringify('input[aria-label="Search icons"]');
   await until(()=>evaluate('Boolean(document.querySelector('+searchSelector+'))'),'Library unavailable');
   await evaluate('(()=>{const el=document.querySelector('+searchSelector+');el.value='+JSON.stringify(search)+';el.dispatchEvent(new Event("input",{bubbles:true}));})()');
   await evaluate('(()=>{const el=[...document.querySelectorAll(".ve-icon-opt")].find(el=>el.title==='+JSON.stringify(title)+');if(!el)throw Error("Icon choice missing");el.click();})()');
   const after=await evaluate('(()=>{const el='+frame+'.querySelector('+JSON.stringify(selector)+');const r=el.getBoundingClientRect();return {width:r.width,height:r.height,background:getComputedStyle(el).backgroundColor,html:el.innerHTML};})()');
   assert(after.html.includes(glyph));assert.equal(after.width,before.width);assert.equal(after.height,before.height);assert.equal(after.background,before.background);
   assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false);
   console.log(page+' '+key+': changed icon, badge design preserved, Publish enabled');
  }
 }
 console.log('PASS: all four icons editable; no test content published.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
