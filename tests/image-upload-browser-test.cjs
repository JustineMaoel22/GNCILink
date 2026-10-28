const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
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
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"try{sessionStorage.setItem('gncIntroSeen','1')}catch(_){}"});

    const source=fs.readFileSync('assets/js/image-upload.js','utf8');
    await evaluate(`window.GNC_IMAGE_FRAMING_CONFIG={admin:true,csrf:'test-token'};window.calls=0;window.errors=[];window.optimizationFailure=false;window.hangOptimization=false;window.AdminDialogs={alert:message=>errors.push(message)};
    const originalTimeout=window.setTimeout.bind(window);window.setTimeout=(callback,ms,...args)=>originalTimeout(callback,hangOptimization&&ms===60000?20:ms,...args);
    window.fetch=async function(url,options){calls++;if(hangOptimization)return new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new DOMException('Aborted','AbortError')),{once:true}));await new Promise(resolve=>setTimeout(resolve,30));return optimizationFailure?new Response(JSON.stringify({error:'Invalid photo'}),{status:422,headers:{'Content-Type':'application/json'}}):new Response(new Blob(['optimized'],{type:'image/png'}));};
    ${source}
    window.upload=document.createElement('input');upload.type='file';upload.accept='image/*';document.body.append(upload);window.changes=0;upload.addEventListener('change',()=>{changes++;window.seen=Array.from(upload.files,f=>({name:f.name,size:f.size,type:f.type}));});
    window.large=new File([new Uint8Array(6*1024*1024)],'large.png',{type:'image/png'});
    window.small=new File(['unchanged'],'small.png',{type:'image/png'});
    window.video=new File([new Uint8Array(6*1024*1024)],'video.mp4',{type:'video/mp4'});
    window.select=function(files){const transfer=new DataTransfer();files.forEach(file=>transfer.items.add(file));upload.files=transfer.files;upload.dispatchEvent(new Event('change',{bubbles:true}));};
    select([large,small,video]);`);
    assert.equal(await evaluate('changes'),0,'Original oversized file does not reach form validation');
    await until(()=>evaluate('changes===1'),'Optimized input did not reach change handler');
    const files=await evaluate('seen');
    assert(files[0].size<=5*1024*1024);assert.equal(files[1].size,9);assert.equal(files[2].size,6*1024*1024);assert.equal(await evaluate('calls'),1);
    await evaluate('select([large])');await until(()=>evaluate('changes===2'),'Cached optimized upload unavailable');assert.equal(await evaluate('calls'),1);
    await evaluate('optimizationFailure=true;select([new File([new Uint8Array(6*1024*1024)],"bad.png",{type:"image/png"})])');
    await until(()=>evaluate('errors.length===1'),'Optimization error not shown');assert.equal(await evaluate('upload.files.length'),0);assert.equal(await evaluate('changes'),2);
    await evaluate('optimizationFailure=false;window.dropTarget=document.createElement("div");document.body.append(dropTarget);dropTarget.addEventListener("drop",event=>window.dropped=event.dataTransfer.files[0].size);const transfer=new DataTransfer();transfer.items.add(new File([new Uint8Array(6*1024*1024)],"drop.png",{type:"image/png"}));dropTarget.dispatchEvent(new DragEvent("drop",{bubbles:true,cancelable:true,dataTransfer:transfer}));');
    await until(()=>evaluate('Boolean(window.dropped)'),'Drop upload was not optimized');assert((await evaluate('dropped'))<=5*1024*1024);
    await evaluate('hangOptimization=true;window.timedFile=new File([new Uint8Array(6*1024*1024)],"timeout.png",{type:"image/png"});select([timedFile]);');
    await until(()=>evaluate('errors.length===2'),'Timeout did not show a retry message');
    assert.ok((await evaluate('errors[1]')).includes('timed out'));
    assert.equal(await evaluate('upload.disabled'),false,'Timeout unlocks the file input');
    assert.equal(await evaluate('document.querySelector("[role=status]").hidden'),true,'Timeout clears optimizing notice');
    await evaluate('hangOptimization=false;select([timedFile])');await until(()=>evaluate('changes===3'),'A timed-out file could not be retried');

    const local=await evaluate(`(async()=>{const result=[];const canvas=document.createElement('canvas');canvas.width=80;canvas.height=60;canvas.getContext('2d').fillStyle='rgba(255,0,0,0.5)';canvas.getContext('2d').fillRect(0,0,40,60);const before=calls;for(const mime of ['image/png','image/jpeg','image/webp']){const blob=await new Promise(resolve=>canvas.toBlob(resolve,mime));const file=new File([blob,new Uint8Array(6*1024*1024)],'real.'+mime.split('/')[1],{type:mime});const optimized=await GNCImageUpload.optimize(file);const bitmap=await createImageBitmap(optimized);const probe=document.createElement('canvas');probe.width=bitmap.width;probe.height=bitmap.height;probe.getContext('2d').drawImage(bitmap,0,0);const alpha=probe.getContext('2d').getImageData(70,20,1,1).data[3];result.push({mime:optimized.type,size:optimized.size,width:bitmap.width,height:bitmap.height,alpha});bitmap.close();}return {result,requests:calls-before};})()`);
    assert.equal(local.requests,0,'Static photos optimize without server requests');
    for(const item of local.result){assert(item.size<=5*1024*1024);assert.equal(item.width,80);assert.equal(item.height,60);if(item.mime==='image/png'||item.mime==='image/webp')assert.equal(item.alpha,0,'Transparency preserved');}
    assert.deepEqual(local.result.map(item=>item.mime),['image/png','image/jpeg','image/webp']);
    console.log('PASS: real PNG/JPG/WebP browser optimization, original formats and dimensions, alpha preservation, and no server requests.');

    console.log('PASS: file inputs, mixed batches, cached repeat selections, error rejection, drag/drop, timeout cleanup and retry.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
