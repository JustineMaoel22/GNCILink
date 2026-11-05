const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const php=args=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
const sessions=[];let socket;
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-settings-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
(async()=>{
    const session=role=>{const s=JSON.parse(php([role]));sessions.push(s);return s;};
    const admin=session('Super Admin'),editor=session('Content Editor'),mirror=session('The Mirror');
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();let send;
    let fail=false,hold=false,held,posts=0,beforeunloadCount=0;const requests=[];
    const fulfill=(request,error=false)=>send('Fetch.fulfillRequest',{requestId:request.requestId,responseCode:error?500:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(error?{success:false,error:'Test save failed. Please retry.'}:{success:true,value:request.value,message:'Changes saved.'})).toString('base64')});
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}
        if(p.method==='Page.javascriptDialogOpening'){beforeunloadCount++;send('Page.handleJavaScriptDialog',{accept:false});}
        if(p.method==='Fetch.requestPaused'){
            const r=p.params;
            if(r.request.method!=='POST'){send('Fetch.continueRequest',{requestId:r.requestId});return;}
            posts++;const params=new URLSearchParams(r.request.postData);requests.push(params);
            const key=params.get('action')==='save_ai_image_checker'?'ai_image_checker_enabled':'page_suggestions_enabled';
            const request={requestId:r.requestId,value:params.getAll(key).at(-1)};
            if(hold){held=request;return;}
            fulfill(request,fail);fail=false;
        }
    });
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true,userGesture:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';await send('Page.enable');await send('Network.enable');
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page unavailable');};
    const settings=async()=>{await visit('/admin/settings.php');await until(()=>evaluate("Boolean(document.querySelector('.settings-page'))"),'Settings unavailable');};
    const resize=async width=>{await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);};
    const shot=async name=>fs.writeFileSync('cache/'+name+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});await resize(1440);await settings();
    const root=await send('DOM.getDocument');
    const input=await send('DOM.querySelector',{nodeId:root.root.nodeId,selector:'#profile-picture-file'});
    await send('DOM.setFileInputFiles',{nodeId:input.nodeId,files:[path.resolve('assets/images/test-image.png')]});
    await until(()=>evaluate("Boolean(document.querySelector('.image-frame-dialog[open]'))"),'Crop dialog unavailable');
    const bounds=await evaluate("(()=>{const el=document.querySelector('.image-frame-preview');const r=el.getBoundingClientRect();return {width:r.width,height:r.height,radius:getComputedStyle(el).borderRadius}})()");
    assert.ok(Math.abs(bounds.width-bounds.height)<1);assert.equal(bounds.radius,'50%');
    await shot('profile-circle-crop');
    await resize(390);
    assert.ok(await evaluate("(()=>{const r=document.querySelector('.image-frame-preview').getBoundingClientRect();return Math.abs(r.width-r.height)<1&&r.right<=innerWidth})()"));
    await shot('profile-circle-crop-mobile');
    await evaluate("document.querySelector('.image-frame-save').click()");
    await until(()=>evaluate("!document.querySelector('.image-frame-dialog')"),'Crop did not close');
    const framed=await evaluate("(()=>{const f=document.querySelector('.settings-profile-form');const event=new Event('submit',{bubbles:true,cancelable:true});f.addEventListener('submit',e=>e.preventDefault(),{once:true});f.dispatchEvent(event);return JSON.parse(f.querySelector('[name=image_framing]').value)})()");
    assert.equal(Object.keys(framed).length,1);
    console.log('Circular crop checks passed: desktop/mobile circle, 1:1 ratio, accepted crop metadata. No image uploaded or profile changed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{for(const s of sessions)php(['destroy',s.value]);}finally{socket?.close();browser.kill();}});
