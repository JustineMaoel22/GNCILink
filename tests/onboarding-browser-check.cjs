const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{spawn}=require('node:child_process');
module.exports=async({base,sessions,inspect})=>{
 const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-onboarding-'));
 const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
 const delay=ms=>new Promise(r=>setTimeout(r,ms));let socket;
 async function until(fn,label){for(let end=Date.now()+20000;Date.now()<end;){if(await fn())return;await delay(60);}throw Error(label);}
 try{
  await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
  const tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
  let serial=0;const pending=new Map();socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
  const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
  await send('Network.enable');await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdnjs.cloudflare.com*','*cdn.jsdelivr.net*','*quilljs.com*','*facebook.com*']});
  for(const [i,width] of [1440,768,390].entries()){
   const pair=sessions[i].cookie.split(';')[0],pos=pair.indexOf('=');await send('Network.setCookie',{name:pair.slice(0,pos),value:pair.slice(pos+1),url:base,httpOnly:true});
   await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
   const home='/admin/admin-dash.php';
   await send('Page.navigate',{url:base+home});await until(()=>evaluate("!!document.querySelector('.gnc-tour[open]')"),'Automatic welcome unavailable');
   assert.equal(await evaluate("document.getElementById('gncTourTitle').textContent"),'Welcome to GNC Admin Panel');
   assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Role dashboard fits '+width+'px');
   await evaluate("document.querySelector('.gnc-tour-actions button:last-child').click()");
   await until(()=>evaluate("document.querySelector('.gnc-tour-progress').textContent.startsWith('Step 2 ' )"),'Next failed');
   await evaluate("document.querySelector('.gnc-tour-actions button:nth-child(2)').click()");
   assert.equal(await evaluate("document.getElementById('gncTourTitle').textContent"),'Welcome to GNC Admin Panel','Back works');
   const count=await evaluate("JSON.parse(document.getElementById('adminOnboardingData').textContent).steps.filter(s=>!s.optional||document.querySelector(s.selector)).length");
   for(let step=0;step<count-1;step++){
    await evaluate("document.querySelector('.gnc-tour-actions button:last-child').click()");await delay(350);
    assert.equal(await evaluate("(()=>{const r=document.querySelector('.gnc-tour-card').getBoundingClientRect();return r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1;})()"),true,'Tutorial fits '+width+'px');
    const highlight=await evaluate("(()=>{const r=document.querySelector('.gnc-tour-ring').getBoundingClientRect();return {ok:r.width>10&&r.left>=0&&r.right<=innerWidth+8,title:document.getElementById('gncTourTitle').textContent,rect:r.toJSON()};})()");
    assert.equal(highlight.ok,true,'Feature is highlighted on '+width+'px: '+JSON.stringify(highlight));
   }
   await evaluate("document.querySelector('.gnc-tour-actions button:last-child').click()");await until(()=>evaluate("!document.querySelector('.gnc-tour')"),'Finish failed');
   assert(inspect().some(row=>Number(row.user_id)===i+1&&row.status==='completed'));
   await send('Page.reload');await until(()=>evaluate("document.readyState==='complete' && !!document.getElementById('adminOnboardingData')"),'Reload failed');
   assert.equal(await evaluate("!!document.querySelector('.gnc-tour')"),false,'Completed guide does not auto-open');
   await send('Page.navigate',{url:base+'/admin/help.php'});await until(()=>evaluate("!!document.querySelector('[data-start-onboarding]')&&document.readyState==='complete'"),'Help unavailable');
   await evaluate("document.querySelector('[data-start-onboarding]').click()");await until(()=>evaluate("!!document.querySelector('.gnc-tour[open]')"),'Manual restart unavailable');
   await evaluate("window.onboardingFetch=window.fetch;window.fetch=()=>Promise.resolve({ok:false,json:async()=>({success:false,error:'Test: progress could not be saved'})});document.querySelector('.gnc-tour-actions button:first-child').click();void 0");
   await until(()=>evaluate("document.querySelector('.gnc-tour-error')?.textContent.includes('could not be saved')"),'Save failure must be visible');
   assert.equal(await evaluate("!!document.querySelector('.gnc-tour[open]')"),true,'Failed save keeps guide open for retry');
   assert(inspect().some(row=>Number(row.user_id)===i+1&&row.status==='completed'),'Failed save does not change completion');
   await evaluate("window.fetch=window.onboardingFetch");
   await evaluate("document.querySelector('.gnc-tour-actions button:first-child').click()");await until(()=>evaluate("!document.querySelector('.gnc-tour')"),'Skip failed');
   assert(inspect().some(row=>Number(row.user_id)===i+1&&row.status==='skipped'));
  }
  console.log('PASS: automatic welcome, Next/Back/Finish/Skip, persisted completion/restart, highlighted role menus and card bounds at desktop, tablet and mobile widths.');
 }finally{if(socket)socket.close();browser.kill();await delay(700);if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-onboarding-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}}
};
