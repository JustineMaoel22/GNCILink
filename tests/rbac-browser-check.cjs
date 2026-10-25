const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
module.exports=async function({base,superCookie,forcedCookie,resetToken}){
 const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-password-test-'));
 const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
 const delay=ms=>new Promise(r=>setTimeout(r,ms));let socket;
 async function until(fn,label){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(70);}throw Error(label);}
 try{
  await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];const tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();socket=new WebSocket(tabs.find(x=>x.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();
  socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
  const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,x=>x.error?reject(Error(JSON.stringify(x.error))):resolve(x.result));socket.send(JSON.stringify({id,method,params}));});
  const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
  const cookie=async text=>{const pair=text.split(';')[0],where=pair.indexOf('=');await send('Network.setCookie',{name:pair.slice(0,where),value:pair.slice(where+1),url:base,httpOnly:true});};
  await send('Page.enable');await send('Network.enable');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  const recoveryFolder=path.join('cache','admin-design-audit');fs.mkdirSync(recoveryFolder,{recursive:true});
  for(const [name,url,selector] of [['forgot-password','/admin/forgot-password.php','#email'],['reset-password','/admin/reset-password.php?token='+resetToken,'#new_password']]){
   await send('Page.navigate',{url:base+url});await until(()=>evaluate("document.readyState==='complete' && !!document.querySelector("+JSON.stringify(selector)+")"),'Recovery form unavailable');
   assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Recovery form fits mobile');
   if(name==='reset-password'){
    assert.equal(await evaluate('location.search'),'','Reset URL is clean');
    await evaluate("document.getElementById('show-passwords').click()");assert.equal(await evaluate("document.getElementById('new_password').type"),'text');await evaluate("document.getElementById('show-passwords').click()");
    await evaluate("document.getElementById('new_password').value='orchard lantern river phrase';document.getElementById('new_password').dispatchEvent(new Event('input'))");assert.equal(await evaluate("document.getElementById('password-strength').value"),4);await evaluate("document.getElementById('new_password').value='';document.getElementById('new_password').dispatchEvent(new Event('input'))");
   }
   const picture=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(recoveryFolder,name+'-mobile.png'),Buffer.from(picture.data,'base64'));
  }
  await cookie(superCookie);
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  await send('Page.navigate',{url:base+'/admin/submissions.php'});await until(()=>evaluate("document.readyState==='complete' && !!document.getElementById('approval-search')"),'Approvals queue unavailable');
  assert(await evaluate("document.querySelectorAll('.approval-card').length>0"),'Submission cards visible');
  assert.equal(await evaluate("getComputedStyle(document.querySelector('.approval-overview')).display"),'grid','Approvals stylesheet loaded');
  await evaluate("{const s=document.getElementById('approval-search');s.value='no-match-fixture-92843';s.dispatchEvent(new Event('input'));}");
  assert(await evaluate("!document.getElementById('approval-no-results').hidden && [...document.querySelectorAll('.approval-card')].every(c=>c.hidden)"),'Search filters cards and shows empty state');
  await evaluate("{const s=document.getElementById('approval-search');s.value='';s.dispatchEvent(new Event('input'));const f=document.getElementById('approval-status');f.value='approved';f.dispatchEvent(new Event('change'));}");
  assert(await evaluate("[...document.querySelectorAll('.approval-card')].every(c=>c.hidden || c.dataset.approvalStatus==='approved')"),'Status filter respects selected status');
  await evaluate("{const f=document.getElementById('approval-status');f.value='';f.dispatchEvent(new Event('change'));}");
  const approvalFolder=path.join('cache','admin-design-audit');fs.mkdirSync(approvalFolder,{recursive:true});
  for(const [name,width,height] of [['desktop',1440,1000],['mobile',390,844]]){
   await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:name==='mobile'});
   if(name==='mobile')await until(()=>evaluate("document.getElementById('gnc-sidebar').getBoundingClientRect().right<=1"),'Mobile sidebar finishes closing');
   assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Approvals fits '+name+' viewport');
   const picture=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(approvalFolder,'approvals-'+name+'.png'),Buffer.from(picture.data,'base64'));
  }
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  await send('Page.navigate',{url:base+'/admin/users.php'});await until(()=>evaluate("!!document.getElementById('new-role') && !!window.AdminDialogs"),'Users form unavailable');
  assert(!await evaluate("!!document.querySelector('input[name=password]')"),'No manual initial password field');
  await evaluate("document.getElementById('user-first_name').value='Browser';document.getElementById('user-last_name').value='Fixture';document.getElementById('user-email').value='browser-fixture@example.invalid';document.getElementById('new-role').value='Content Editor';document.querySelector('form[data-user-confirm]').requestSubmit()");
  await until(()=>evaluate("!!document.querySelector('.gnc-dialog-accept')"),'Create confirmation unavailable');await evaluate("document.querySelector('.gnc-dialog-accept').click()");
  await until(()=>evaluate("!!document.querySelector('#temporary-password-dialog[open]')"),'One-time password modal unavailable');assert.equal(await evaluate("document.getElementById('temporary-password').value.length"),12);
  await evaluate("Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async value=>{window.copyMatches=value===document.getElementById('temporary-password').value}}});document.getElementById('copy-temporary-password').click()");await until(()=>evaluate('window.copyMatches===true'),'Copy Password did not copy generated value');
  await evaluate("document.getElementById('close-temporary-password').click()");await until(()=>evaluate("!document.getElementById('temporary-password')"),'Temporary password was not scrubbed after closing');
  await send('Page.navigate',{url:base+'/admin/users.php'});await until(()=>evaluate("document.readyState==='complete' && !!document.getElementById('new-role')"),'Users reload unavailable');assert(!await evaluate("!!document.getElementById('temporary-password-dialog')"),'Later GET cannot display temporary password');
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  const userFolder=path.join('cache','admin-design-audit');fs.mkdirSync(userFolder,{recursive:true});const userShot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(userFolder,'rbac-users-mobile.png'),Buffer.from(userShot.data,'base64'));
  await cookie(forcedCookie);await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await send('Page.navigate',{url:base+'/admin/change-password.php'});await until(()=>evaluate("document.readyState==='complete' && !!document.getElementById('show-passwords')"),'Mandatory password page unavailable');assert.equal(await evaluate("document.querySelectorAll('input[type=password]').length"),3);
  await evaluate("document.getElementById('show-passwords').click()");assert.equal(await evaluate("document.querySelectorAll('input[type=password]').length"),0);await evaluate("document.getElementById('show-passwords').click()");assert.equal(await evaluate("document.querySelectorAll('input[type=password]').length"),3);
  assert(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Password page fits mobile viewport');
  const folder=path.join('cache','admin-design-audit');fs.mkdirSync(folder,{recursive:true});const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(folder,'first-login-password-mobile.png'),Buffer.from(shot.data,'base64'));
 }finally{if(socket)socket.close();browser.kill();await delay(500);if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-password-test-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}}
};
