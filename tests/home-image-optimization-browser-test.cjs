const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-home-optimize-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session,send;
const {execFileSync}=require('node:child_process');
let orgFixture, restoreEyebrow;
const fixture=(...args)=>execFileSync('docker',['compose','exec','-T','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser command timed out: '+method));},15000);pending.set(id,result=>{clearTimeout(timer);result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')console.error('Browser exception:',JSON.stringify(p.params.exceptionDetails));});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*','*optimize-image.php*']});



    const auth=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true}));
    session={name:auth.name,id:auth.value};
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    const frame='(document.getElementById("veFrame")?.contentDocument)';
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=home'});
    await until(()=>evaluate(`Boolean(window.VE)&&Boolean(${frame}?.querySelector('[data-ve-img="home-background"]'))&&document.getElementById('veLoader').hidden`),'Homepage editor unavailable');
    await evaluate(`${frame}.querySelector('[data-ve-img="home-background"]').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Change background image').click()`);
    await evaluate(`(async()=>{const canvas=document.createElement('canvas');canvas.width=800;canvas.height=600;canvas.getContext('2d').fillRect(0,0,800,600);const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));const original=new File([blob,new Uint8Array(6*1024*1024)],'large-home.png',{type:'image/png'});const transfer=new DataTransfer();transfer.items.add(original);const input=document.getElementById('veFile');input.files=transfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await until(()=>evaluate('Object.keys(VE.adapter().draftState().hcFiles).length>0'),'Optimized image not staged');
    assert((await evaluate('document.getElementById("veFile").files[0].size'))<=5*1024*1024);
    assert.equal(await evaluate('document.getElementById("veFile").disabled'),false);
    console.log('PASS: real homepage large upload optimized below 5 MB, staged background, and input unlocked. Server optimization endpoint blocked to simulate shared hosting; no content published.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-home-optimize-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
