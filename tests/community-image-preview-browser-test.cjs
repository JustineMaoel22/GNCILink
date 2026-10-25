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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','community-image-preview-regression');value.page.url=u.pathname+u.search;}}});})();`});


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=community-activities'});
    await until(()=>evaluate("Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.state.doc.querySelector('[data-ve-community-add]'))"),'Activity editor unavailable');
    if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){
        await evaluate("window.pendingReload=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate("Boolean(!window.pendingReload&&window.VE&&!VE.state.busy&&document.getElementById('veLoader').hidden)"),'Old test draft not discarded');
    }
    await evaluate("VE.state.doc.querySelector('[data-ve-community-add]').click()");
    const id=await evaluate('VE.adapter().draftState().content.activities.at(-1).id');
    await evaluate(`VE.state.win.GNCCommunityActivities.select(${JSON.stringify(id)})`);
    await evaluate(`VE.state.doc.querySelector('[data-community-feature]').click();document.getElementById('veFile').click=()=>{};[...document.querySelectorAll('#veTb button')].find(b=>b.textContent==='Replace picture').click()`);
    await evaluate(`(()=>{const el=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII='),c=>c.charCodeAt(0))],'test.png',{type:'image/png'}));el.files=dt.files;el.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await until(()=>evaluate("Boolean(document.querySelector('.image-frame-save'))"),'Framing not opened');
    await evaluate("VE.state.doc.querySelector('[data-ve-community-add]').click()");
    await evaluate("document.querySelector('.image-frame-save').click()");
    await until(()=>evaluate('Object.keys(VE.adapter().draftState().files).length===1'),'Not staged');
    assert.equal(await evaluate("VE.adapter().draftState().files.showcase_image.name"),'test.png','Upload belongs to the section');
    await until(()=>evaluate("(()=>{const image=VE.state.doc.querySelector('[data-community-image]');return !image.hidden&&image.complete&&image.naturalWidth>0&&VE.state.doc.querySelector('[data-community-no-image]').hidden})()"),'Uploaded preview is not visible');
    const second=await evaluate('VE.adapter().draftState().content.activities.at(-1).id');
    await evaluate(`VE.state.doc.querySelector('[data-community-feature]').click();VE.state.win.GNCCommunityActivities.select(${JSON.stringify(second)});[...document.querySelectorAll('#veTb button')].find(b=>b.textContent==='Replace picture').click()`);
    await evaluate(`(()=>{const el=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII='),c=>c.charCodeAt(0))],'second.png',{type:'image/png'}));el.files=dt.files;el.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await until(()=>evaluate("Boolean(document.querySelector('.image-frame-save'))"),'Second framing not opened');
    await evaluate("document.querySelector('.image-frame-save').click()");
    await until(()=>evaluate("VE.adapter().draftState().files.showcase_image.name==='second.png'"),'Shared image not replaced');
    assert.equal(await evaluate('Object.keys(VE.adapter().draftState().files).length'),1,'One shared photo upload slot');
    await until(()=>evaluate("(()=>{const image=VE.state.doc.querySelector('[data-community-image]');return !image.hidden&&image.complete&&image.naturalWidth>0&&VE.state.doc.querySelector('[data-community-no-image]').hidden})()"),'Second image preview is not visible');
    const displayedSource=await evaluate("VE.state.doc.querySelector('[data-community-image]').src");
    await evaluate("VE.state.doc.querySelector('[data-ve-community-add]').click()");
    const third=await evaluate('VE.adapter().draftState().content.activities.at(-1).id');
    await evaluate(`(()=>{const field=VE.state.doc.querySelector('[data-ve-community-card="${third}"] h4');field.dispatchEvent(new VE.state.win.MouseEvent('mouseover',{bubbles:true}));field.focus();field.textContent='New activity';field.dispatchEvent(new VE.state.win.Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-community-image]').src"),displayedSource,'Adding an activity retains the visible image');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-community-image]').hidden"),false);
    assert.equal(await evaluate(`VE.adapter().draftState().content.activities.find(row=>row.id===${JSON.stringify(third)}).image`),'','The new activity does not inherit another activity image');
    const publications=[];
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;publications.push(packet.params.request.postData||'');await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:422,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify({error:'Isolated publish fixture'})).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*community-activities-handler.php*'}]});
    await evaluate('VE.adapter().runPrimary()');
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Missing-content warning did not open');
    await evaluate("document.querySelector('.cms-completeness-keep').click()");
    await delay(100);assert.equal(publications.length,0,'Keep Editing does not publish');
    await evaluate('VE.adapter().runPrimary()');
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Missing-content warning did not reopen');
    await evaluate("document.querySelector('.cms-completeness-publish').click()");
    await until(async()=>publications.length===1&&await evaluate('!VE.state.busy'),'Publish Anyway blocked incomplete activity');
    assert.ok(/name="allow_incomplete"\r?\n\r?\n1/.test(publications[0]),'Explicit override reaches server');
    assert.equal(await evaluate('VE.state.allowIncomplete'),false,'Override cleared after request');
    assert.equal(await evaluate('Object.keys(VE.adapter().draftState().files).length'),1,'Failed request preserves the shared photo');
    assert.ok(publications[0].includes('name="showcase_image"'),'Shared photo reaches publish handler');
    await send('Fetch.disable');
    console.log('PASS: image preview, adding/editing activities, Keep Editing cancellation, and Publish Anyway sends incomplete content with a scoped override; failed requests preserve edits. No publication or authored draft changes.');})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});



