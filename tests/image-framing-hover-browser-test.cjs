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


 await send('Page.navigate',{url:base+'/admin/content-edit.php?page=enrollment-procedures'});
 const frame='document.getElementById("hoverFrame")?.contentDocument';
 await until(()=>evaluate('Boolean(document.getElementById("veFrame")?.contentDocument?.querySelector("[data-ve-section]")?.__ve)'),'Editor unavailable');
 await evaluate('(()=>{const f=document.createElement("iframe");f.id="hoverFrame";f.style.cssText="position:fixed;inset:0;width:100vw;height:100vh;z-index:17000;border:0";f.src='+JSON.stringify(base+'/pages/news-and-announcement.php?ce_preview=1')+';document.body.append(f);})()');
 await until(()=>evaluate('Boolean('+frame+'?.querySelector(".newsannouncement-hero h1")&&document.getElementById("veLoader").hidden)'),'News editor unavailable');
 await delay(2200);
 const center=await evaluate('(()=>{const f=document.getElementById("hoverFrame"),el=f.contentDocument.querySelector(".newsannouncement-hero h1"),r=el.getBoundingClientRect(),fr=f.getBoundingClientRect();return {x:fr.left+r.left+r.width/2,y:fr.top+r.top+r.height/2};})()');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:10,y:10});
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',...center});
 await until(()=>evaluate('Boolean('+frame+'?.querySelector(".image-frame-trigger:not([hidden])"))'),'Reposition did not appear over heading');
 const hit=await evaluate('(()=>{const doc='+frame+',h=doc.querySelector(".newsannouncement-hero h1"),r=h.getBoundingClientRect();return doc.defaultView.GNCImageFraming.findTargetAt(h,r.left+r.width/2,r.top+r.height/2)===doc.querySelector(".newsannouncement-hero");})()');
 assert.equal(hit,true,'Heading resolves to its background image');
 // Sibling overlays over cover images must resolve too, without selecting nearby images.
 await evaluate('(()=>{const doc='+frame+',wrap=doc.createElement("div"),img=doc.createElement("img"),link=doc.createElement("a"),text=doc.createElement("span");wrap.id="hoverCropFixture";wrap.style.cssText="position:fixed;left:20px;top:120px;width:300px;height:180px;z-index:10000";img.src="/assets/images/gnc-front-II.jpg";img.style.cssText="width:100%;height:100%;object-fit:cover";link.href="#";link.style.cssText="position:absolute;inset:0;display:flex;align-items:center;justify-content:center";text.textContent="Image overlay";link.append(text);wrap.append(img,link);doc.body.append(wrap);})()');
 const overlay=await evaluate('(()=>{const doc='+frame+',el=doc.querySelector("#hoverCropFixture span"),r=el.getBoundingClientRect(),f=document.getElementById("hoverFrame").getBoundingClientRect();return {x:f.left+r.left+r.width/2,y:f.top+r.top+r.height/2};})()');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',...overlay});
 await until(()=>evaluate('Boolean('+frame+'?.querySelector(".image-frame-trigger:not([hidden])"))'),'Sibling overlay hid Reposition');
 assert.equal(await evaluate('(()=>{const d='+frame+',e=d.querySelector("#hoverCropFixture span"),r=e.getBoundingClientRect();return d.defaultView.GNCImageFraming.findTargetAt(e,r.left+r.width/2,r.top+r.height/2)===d.querySelector("#hoverCropFixture img");})()'),true);
 const button=await evaluate('(()=>{const d='+frame+',r=d.querySelector(".image-frame-trigger").getBoundingClientRect(),f=document.getElementById("hoverFrame").getBoundingClientRect();return {x:f.left+r.left+r.width/2,y:f.top+r.top+r.height/2};})()');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',...button});
 await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...button});await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...button});
 await until(()=>evaluate('Boolean('+frame+'?.querySelector(".image-frame-dialog[open]"))'),'Reposition button did not open crop dialog');
 // Reproduce the History recording in the actual editor iframe, with its sticky navbar.
 await evaluate('document.getElementById("hoverFrame").remove()');
 await send('Page.navigate',{url:base+'/admin/content-edit.php?page=history'});
 await until(()=>evaluate('Boolean(window.VE && VE_CONFIG.page.slug==="history" && VE.state.doc?.querySelector(".history-hero") && !VE.state.busy && document.getElementById("veLoader").hidden)'),'History editor unavailable');
 const historyFrame='document.getElementById("veFrame").contentDocument';
 await delay(1200);
 const heroPoint=await evaluate('(()=>{const f=document.getElementById("veFrame"),d=f.contentDocument,hero=d.querySelector(".history-hero"),r=hero.getBoundingClientRect(),fr=f.getBoundingClientRect();return {x:fr.left+r.left+r.width/2,y:fr.top+r.top+r.height/2};})()');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',...heroPoint});
 await until(()=>evaluate('Boolean('+historyFrame+'.querySelector(".image-frame-trigger:not([hidden])"))'),'History Reposition unavailable');
 const historyButton=await evaluate('(()=>{const f=document.getElementById("veFrame"),d=f.contentDocument,b=d.querySelector(".image-frame-trigger"),r=b.getBoundingClientRect(),nav=d.getElementById("mainNavbar").getBoundingClientRect(),hero=d.querySelector(".history-hero").getBoundingClientRect(),fr=f.getBoundingClientRect();return {x:fr.left+r.left+r.width/2,y:fr.top+r.top+r.height/2,belowNav:r.top>=nav.bottom,insideHero:r.top>=hero.top&&r.bottom<=hero.bottom};})()');
 assert.equal(historyButton.belowNav,true,'Reposition must not overlap the sticky navigation');
 assert.equal(historyButton.insideHero,true,'Reposition stays inside the visible hero');
 assert.equal(await evaluate('(()=>{const d='+historyFrame+',r=d.querySelector(".image-frame-trigger").getBoundingClientRect();return r.right<=d.documentElement.clientWidth && r.left>=0 && r.height<45;})()'),true,'Reposition fits inside the iframe without wrapping');
 for(let step=1;step<=12;step++){
     await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:heroPoint.x+(historyButton.x-heroPoint.x)*step/12,y:heroPoint.y+(historyButton.y-heroPoint.y)*step/12});
     await delay(35);
     assert.equal(await evaluate('!'+historyFrame+'.querySelector(".image-frame-trigger").hidden'),true,'Reposition remains visible while approaching it');
 }
 // Scrolling used to hide the control until a new pointerover event happened.
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',...heroPoint});
 await evaluate(historyFrame+'.defaultView.scrollBy(0,25)');await delay(150);
 assert.equal(await evaluate('!'+historyFrame+'.querySelector(".image-frame-trigger").hidden'),true,'Scroll recomputes the visible crop under a stationary pointer');
 await evaluate(historyFrame+'.dispatchEvent(new PointerEvent("pointerout",{bubbles:true,relatedTarget:null}))');
 assert.equal(await evaluate(historyFrame+'.querySelector(".image-frame-trigger").hidden'),true,'Leaving the iframe hides the control');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:heroPoint.x+2,y:heroPoint.y+2});await delay(100);
 assert.equal(await evaluate('!'+historyFrame+'.querySelector(".image-frame-trigger").hidden'),true,'Moving within the same hero restores the control');
 const actualButton=await evaluate('(()=>{const f=document.getElementById("veFrame"),r=f.contentDocument.querySelector(".image-frame-trigger").getBoundingClientRect(),fr=f.getBoundingClientRect();return {x:fr.left+r.left+r.width/2,y:fr.top+r.top+r.height/2};})()');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',...actualButton});
 await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...actualButton});
 await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...actualButton});
 await until(()=>evaluate('Boolean('+historyFrame+'.querySelector(".image-frame-dialog[open]"))'),'History Reposition did not open');
 await evaluate(historyFrame+'.querySelector(".image-frame-dialog").dispatchEvent(new Event("cancel",{cancelable:true}))');
 await until(()=>evaluate('!'+historyFrame+'.querySelector(".image-frame-dialog[open]")'),'History crop cancel did not close');
 assert.equal(await evaluate('VE.adapter().dirtyCount()'),0,'Cancel preserves existing framing and leaves no pending changes');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',...heroPoint});await delay(100);
 const shot=await send('Page.captureScreenshot',{format:'png'});
 fs.writeFileSync(path.join(auditDirectory,'history-reposition-hover.png'),Buffer.from(shot.data,'base64'));
 await evaluate(historyFrame+'.documentElement.classList.add("ve-previewing")');
 await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:heroPoint.x+3,y:heroPoint.y+3});await delay(100);
 assert.equal(await evaluate(historyFrame+'.querySelector(".image-frame-trigger").hidden'),true,'Preview mode hides editing controls');
 console.log('PASS: Reposition appears over center text and sibling link overlays; clicking the visible button opens the image editor. No framing saved.');
 console.log('PASS: History control stays below navigation, remains reachable, survives scrolling and same-image pointer movement, and cancel preserves framing.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
