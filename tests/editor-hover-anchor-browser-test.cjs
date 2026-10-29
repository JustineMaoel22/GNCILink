const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=os.tmpdir();
const draftMarker='slide-controls-regression-'+Date.now();
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-hover-anchor-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,cleanupDraft;
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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test',${JSON.stringify(draftMarker)});value.page.url=u.pathname+u.search;}}});})();`});


    for(const width of [1440,768,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-departments&sub=cba'});
        await until(()=>evaluate('Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById("veLoader").hidden)'),'Department editor missing');
        await evaluate(`(()=>{const doc=VE.state.doc,img=doc.querySelector('.dept-program-img img'),card=img.closest('.dept-program-card');window.hoverImage=img;window.hoverCard=card;img.style.setProperty('transform','scale(2)','important');img.style.setProperty('transform-origin','50% 50%','important');img.style.setProperty('clip-path','inset(25% 25% 25% 25%)','important');img.setAttribute('data-image-framed','');card.scrollIntoView({block:'center',behavior:'instant'});})()`);
        await delay(1500);
        const pt=await evaluate('(()=>{const r=hoverImage.parentElement.getBoundingClientRect(),f=VE.state.frame.getBoundingClientRect();return {x:f.left+r.left+r.width/2,y:f.top+r.top+r.height/2};})()');
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',...pt});
        await until(()=>evaluate('Boolean(document.querySelector(".ve-edit-feedback-hint:not([hidden])")&&VE.state.doc.querySelector(".image-frame-trigger:not([hidden])"))'),'Hover controls missing');
        async function check(){return evaluate(`(()=>{const f=VE.state.frame.getBoundingClientRect(),hint=document.querySelector('.ve-edit-feedback-hint').getBoundingClientRect(),button=VE.state.doc.querySelector('.image-frame-trigger').getBoundingClientRect(),crop=VE.state.doc.defaultView.GNCImageFraming.visibleRect(hoverImage);return Math.abs(hint.top-(f.top+button.top))<2&&hint.left>=f.left+crop.left&&hint.right<f.left+button.left&&button.top>=crop.top&&button.bottom<=crop.bottom&&button.right<=crop.right+1;})()`);}
        assert(await check(),'Both controls line up inside the visible photo at '+width+'px');
        await evaluate('hoverCard.style.transform="translateY(24px)"');await delay(150);
        assert(await check(),'Controls follow the moving card without pointer movement');
        fs.writeFileSync(path.join(auditDirectory,'editor-hover-anchor-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        const buttonPoint=await evaluate('(()=>{const b=VE.state.doc.querySelector(".image-frame-trigger"),r=b.getBoundingClientRect(),f=VE.state.frame.getBoundingClientRect();return {x:f.left+r.left+r.width/2,y:f.top+r.top+r.height/2};})()');
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',...buttonPoint});
        await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...buttonPoint});await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...buttonPoint});
        await until(()=>evaluate('Boolean(VE.state.doc.querySelector(".image-frame-dialog[open]")||document.querySelector(".image-frame-dialog[open]"))'),'Reposition not clickable');
        await evaluate('(()=>{const box=VE.state.doc.querySelector(".image-frame-dialog[open]")||document.querySelector(".image-frame-dialog[open]");[...box.querySelectorAll("button")].find(b=>b.textContent==="Cancel").click();})()');
    }
    console.log('PASS: edit hints and Reposition align inside zoomed photos at desktop/tablet/mobile widths, follow moving cards without pointer movement, and Reposition remains clickable. No framing saved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-hover-anchor-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}});
