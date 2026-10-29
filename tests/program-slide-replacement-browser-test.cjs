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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','slide-replacement-regression');value.page.url=u.pathname+u.search;}}});})();`});

    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=bael'});
    await until(()=>evaluate("Boolean(window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veLoader').hidden && document.querySelector('#veDock .ve-thumb'))"),'Program slide editor unavailable');
    if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){
        await evaluate("window.pendingReload=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate("Boolean(!window.pendingReload && window.VE && !VE.state.busy && document.getElementById('veLoader').hidden)"),'Old test draft not discarded');
    }
    const lists=await evaluate("({hero_slide:JSON.parse(VE.state.doc.getElementById('veBannerData').textContent).slides,facility:JSON.parse(VE.state.doc.getElementById('veFacData').textContent).slides})");
    assert.ok(lists.hero_slide.length&&lists.facility.length);
    async function open(index){await evaluate("document.querySelectorAll('#veDock .ve-thumb')["+index+"].click()");await until(()=>evaluate("[...document.querySelectorAll('#vePop button')].some(b=>b.textContent==='Replace slide')"),'Replace option missing');}
    async function choose(name,type,data){
        await evaluate(`(()=>{const b=[...document.querySelectorAll('#veTb button,#vePop button')].find(b=>/^(Replace slide|Replace picture)$/.test(b.textContent.trim()));const input=document.getElementById('veFile');input.click=()=>{};b.click();})()`);
        await delay(50);
        await evaluate(`(()=>{const input=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob(`+JSON.stringify(data)+`),c=>c.charCodeAt(0))],`+JSON.stringify(name)+`,{type:`+JSON.stringify(type)+`}));input.files=dt.files;input.onchange();})()`);
    }
    const png='iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==';
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const point=await evaluate("(()=>{const hero=VE.state.doc.querySelector('.template-hero');hero.scrollIntoView();const r=hero.getBoundingClientRect(),f=document.getElementById('veFrame').getBoundingClientRect();return {x:f.left+r.left+r.width*.85,y:f.top+r.top+r.height*.5};})()");
    await send('Input.dispatchMouseEvent',{type:'mousePressed',...point,button:'left',clickCount:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',...point,button:'left',clickCount:1});
    await until(()=>evaluate("Boolean(VE.state.sel?.kind==='program-slide' && [...document.querySelectorAll('#veTb button')].some(b=>b.textContent.trim()==='Replace picture'))"),'Clicking banner did not offer replacement');
    const screenshot=await send('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(path.join(auditDirectory,'program-replace-slide.png'),Buffer.from(screenshot.data,'base64'));
    await choose('invalid.txt','text/plain',btoa('invalid'));
    assert.equal(await evaluate('Object.keys(VE.adapter().draftState().slideReplacements).length'),0,'Invalid selection does not replace anything');
    await choose('replacement-banner.png','image/png',png);
    await until(()=>evaluate("Object.keys(VE.adapter().draftState().slideReplacements).length===1"),'Banner not staged');
    assert.equal(await evaluate("VE.state.doc.querySelector('.hero-slide').style.backgroundImage.includes('blob:')"),true);
    await evaluate("VE.state.doc.querySelector('.fcar-slide img').click()");
    assert.equal(await evaluate('VE.state.sel.kind'),'program-slide','Facility picture offers replacement on click');
    await choose('replacement-facility.png','image/png',png);
    await until(()=>evaluate("!VE.state.busy&&VE.adapter().draftState().pRows.facility?.[0].file"),'Facility not staged');
    assert.equal(await evaluate("VE.state.doc.querySelector('.fcar-slide img').src.startsWith('blob:')"),true);
    await evaluate("document.getElementById('veSaveDraft').click()");
    await until(()=>evaluate("!VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved')"),'Replacements draft not saved');
    await evaluate('window.pendingReload=true');await send('Page.reload');
    await until(()=>evaluate("Boolean(!window.pendingReload && window.VE && !VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved'))"),'Replacements not restored');
    assert.deepEqual(await evaluate("Object.values(VE.adapter().draftState().slideReplacements).map(c=>c.file.name).concat(VE.adapter().draftState().pRows.facility.filter(r=>r.file).map(r=>r.file.name)).sort()"),['replacement-banner.png','replacement-facility.png']);
    assert.equal(await evaluate("VE.state.doc.querySelector('.hero-slide').style.backgroundImage.includes('blob:') && VE.state.doc.querySelector('.fcar-slide img').src.startsWith('blob:')"),true,'Both replacement previews restore');
    let reject=true;const uploads=[];
    socket.addEventListener('message',async event=>{
        const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;
        const request=packet.params.request;
        const payload=request.postData||'';
        const type=payload.includes('hero_slide')?'hero_slide':'facility';
        uploads.push({type,payload});
        await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:reject?400:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(reject?{success:false,error:'Fixture upload failed'}:{success:true,items:lists[type]})).toString('base64')});
    });
    await send('Fetch.enable',{patterns:[{urlPattern:'*program-page-handler.php*'}]});
    async function publish(){
        const before=uploads.length;
        await evaluate('window.ContentCompleteness=null;VE.adapter().runPrimary()');
        await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-dialog[open]'))"),'Publish confirmation missing');
        await evaluate("document.querySelector('.ve-publish-accept').click()");
        await until(async()=>uploads.length>=before+2 && await evaluate('Boolean(window.VE && !VE.state.busy && !document.querySelector(".ve-publish-dialog"))'),'Publish still running');
    }
    await publish();
    assert.equal(await evaluate('Object.keys(VE.adapter().draftState().slideReplacements).length+VE.adapter().draftState().pRows.facility.filter(r=>r.file).length'),2,'Failed upload preserves both replacement files');
    reject=false;await publish();
    await until(()=>evaluate("Boolean(window.VE && !VE.state.busy && document.getElementById('veLoader').hidden && document.getElementById('veDiscardDraft').hidden)"),'Successful upload did not finish');
    assert.equal(uploads.length,4);
    for(const upload of uploads){
        const items=JSON.parse(/name="items"\r?\n\r?\n([^\r\n]+)/.exec(upload.payload)[1]);
        assert.deepEqual(items.map(r=>r.item_id),lists[upload.type].map(r=>r.item_id),'Slide IDs and order preserved');
        assert.deepEqual(items.map(r=>r.title),lists[upload.type].map(r=>r.title),'Titles preserved');
        assert.deepEqual(items.map(r=>r.body),lists[upload.type].map(r=>r.body),'Descriptions preserved');
        assert.ok(upload.payload.includes('name="file_0"'),'Replacement sent for selected slot');
        assert.equal(upload.payload.includes('name="file_1"'),false,'Other slide media not reuploaded');
    }
    console.log('PASS: banner/facility replacement, invalid file rejection, draft/preview restoration, upload failure/retry, exact IDs/order/text, and selected file slot. Publish uploads intercepted; live media unchanged.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
