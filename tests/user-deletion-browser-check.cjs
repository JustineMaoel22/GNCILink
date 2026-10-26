const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{spawn}=require('node:child_process');
module.exports=async({base,cookie})=>{
 const folder=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-delete-ui-')),browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+folder,'about:blank'],{windowsHide:true,stdio:'ignore'});let socket;
 const delay=ms=>new Promise(r=>setTimeout(r,ms));async function until(fn,msg){for(let end=Date.now()+20000;Date.now()<end;){if(await fn())return;await delay(70);}throw Error(msg);}
 try{
  await until(()=>fs.existsSync(path.join(folder,'DevToolsActivePort')),'Chrome unavailable');const port=fs.readFileSync(path.join(folder,'DevToolsActivePort'),'utf8').split('\n')[0],tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
  const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
  const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
  await send('Network.enable');await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdnjs.cloudflare.com*','*cdn.jsdelivr.net*','*quilljs.com*']});const pos=cookie.indexOf('=');await send('Network.setCookie',{name:cookie.slice(0,pos),value:cookie.slice(pos+1),url:base});
  await send('Page.navigate',{url:base+'/admin/users.php'});await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('[data-delete-user]')"),'Users page unavailable');
  for(const width of [1440,390]){
   await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
   await evaluate("document.querySelector('[data-delete-user=\"4\"]').click()");await until(()=>evaluate("document.getElementById('delete-user-dialog').open"),'Delete dialog missing');
   assert.equal(await evaluate("document.activeElement.id"),'delete-user-password');assert.equal(await evaluate("document.getElementById('delete-user-password').required"),true);assert.equal(await evaluate("document.getElementById('delete-user-form').checkValidity()"),false,'Password required');
   assert.equal(await evaluate("(()=>{const r=document.getElementById('delete-user-dialog').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight;})()"),true,'Dialog fits viewport');
   await evaluate("document.getElementById('delete-user-password').value='synthetic-input';document.getElementById('cancel-delete-user').click()");await until(()=>evaluate("!document.getElementById('delete-user-dialog').open"),'Cancel failed');await delay(100);assert.equal(await evaluate("document.getElementById('delete-user-password').value"),'','Cancelled password cleared');
  }
  console.log('PASS: delete dialog, required password, cancellation/scrubbing and mobile/desktop bounds. No browser deletion submitted.');
 }finally{if(socket)socket.close();browser.kill();await delay(500);if(path.dirname(folder)===os.tmpdir()&&path.basename(folder).startsWith('gnci-delete-ui-'))try{fs.rmSync(folder,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}}
};
