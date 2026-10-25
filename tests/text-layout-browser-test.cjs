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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','text-layout-regression');value.page.url=u.pathname+u.search;}}});})();`});

    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=bael'});
    await until(()=>evaluate("Boolean(window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veLoader').hidden && document.querySelector('#veDock .ve-thumb'))"),'Program slide editor unavailable');
    if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){
        await evaluate("window.pendingReload=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate("Boolean(!window.pendingReload && window.VE && !VE.state.busy && document.getElementById('veLoader').hidden)"),'Old test draft not discarded');
    }


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await delay(1200);
    await until(()=>evaluate("Boolean(VE.state.win.GNCTextLayout && VE.state.doc.querySelector('[data-ve-key=short-name]')?.__ve)"),'Text layout unavailable');
    const title="VE.state.doc.querySelector('[data-ve-key=short-name]')";
    const rect=()=>evaluate(`(()=>{const r=${title}.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height}})()`);
    const before=await rect();
    const neighbor=await evaluate("VE.state.doc.querySelector('.template-subtitle')?.getBoundingClientRect().top || VE.state.doc.querySelector('[data-ve-key=hero_tagline]')?.getBoundingClientRect().top");
    await evaluate(title+'.click()');
    assert.equal(await evaluate(title+'.isContentEditable'),true,'Inline typing remains enabled');
    await evaluate("[...document.querySelectorAll('#veTb button')].find(b=>b.textContent.trim()==='Move and resize text').click()");
    assert.equal(await evaluate("VE.state.doc.querySelectorAll('.text-layout-handle').length"),8);
    assert.equal(await evaluate("VE.state.doc.querySelector('.text-layout-grid').hidden"),false,'Grid visible in layout mode');
    async function gesture(selector,dx,dy){
        const p=await evaluate(`(()=>{const r=VE.state.doc.querySelector(${JSON.stringify(selector)}).getBoundingClientRect(),f=document.getElementById('veFrame').getBoundingClientRect();return {x:r.left+r.width/2+f.left,y:r.top+r.height/2+f.top};})()`);
        await send('Input.dispatchMouseEvent',{type:'mousePressed',...p,button:'left',clickCount:1});
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:p.x+dx,y:p.y+dy,button:'left',buttons:1});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:p.x+dx,y:p.y+dy,button:'left',clickCount:1});await delay(100);
    }
    await gesture('.text-layout-move',61,27);
    let moved=await rect();assert.ok(Math.abs(moved.x-before.x)>5||Math.abs(moved.y-before.y)>5,'Drag moves text');
    const snapped=await evaluate(`(()=>{const r=${title}.getBoundingClientRect(),b=VE.state.doc.querySelector('.template-hero').getBoundingClientRect();return {x:r.left-b.left,y:r.top-b.top}})()`);
    assert.ok(Math.abs(snapped.x/8-Math.round(snapped.x/8))<.02&&Math.abs(snapped.y/8-Math.round(snapped.y/8))<.02,'Dragging snaps to section grid');
    await gesture('[data-direction=se]',-80,35);
    let resized=await rect();assert.ok(resized.w<moved.w-20,'Resize handle changes width');
    assert.ok(Math.abs((resized.x+resized.w)/8-Math.round((resized.x+resized.w)/8))<.02,'Resize edge snaps to grid');
    await evaluate("[...document.querySelectorAll('#veTb button')].find(b=>b.textContent.trim()==='Snap to grid').click()");
    assert.equal(await evaluate("VE.state.doc.querySelector('.text-layout-grid').hidden"),true,'Snap toggle hides grid');
    const unsnappedBefore=await rect();await gesture('.text-layout-move',3,1);const unsnapped=await rect();
    assert.ok(Math.abs((unsnapped.x-unsnappedBefore.x)-3)<.1,'Snapping off permits fine adjustment');
    resized=unsnapped;
    assert.ok(await evaluate('VE.adapter().dirtyCount()')>0,'Placement enables draft/publish');
    if(neighbor)assert.equal(await evaluate("VE.state.doc.querySelector('.template-subtitle')?.getBoundingClientRect().top || VE.state.doc.querySelector('[data-ve-key=hero_tagline]')?.getBoundingClientRect().top"),neighbor,'Neighbors keep their positions');
    fs.writeFileSync(path.join(auditDirectory,'text-layout-program-desktop.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await evaluate("document.getElementById('veSaveDraft').click()");
    await until(()=>evaluate("!VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved')"),'Layout draft not saved');
    await evaluate('window.pendingReload=true');await send('Page.reload');
    await until(()=>evaluate("Boolean(!window.pendingReload && window.VE && !VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved'))"),'Layout draft not restored');
    assert.ok(Math.abs((await rect()).w-resized.w)<2,'Resized width restored from draft');
    await evaluate("document.querySelector('.ve-devices [data-device=mobile]').click()");await until(()=>evaluate("VE.state.win.innerWidth<=390"),'Mobile width did not settle');await delay(100);
    assert.equal(await evaluate(title+'.style.position'),'','Desktop placement does not affect mobile');
    await evaluate(title+'.click()');await evaluate("[...document.querySelectorAll('#veTb button')].find(b=>b.textContent.trim()==='Move and resize text').click()");
    await gesture('[data-direction=se]',-20,10);
    assert.equal(await evaluate(title+'.style.position'),'absolute','Mobile can be customized separately');
    await evaluate("[...document.querySelectorAll('#veTb button')].find(b=>b.textContent.trim()==='Reset text placement').click()");
    assert.equal(await evaluate(title+'.style.position'),'','Reset restores mobile normal layout');
    await evaluate("document.querySelector('.ve-devices [data-device=desktop]').click()");await until(()=>evaluate("VE.state.win.innerWidth>1024"),'Desktop width did not settle');await delay(350);
    let fail=true;const requests=[];
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;requests.push(packet.params.request.postData||'');await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:fail?422:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(fail?{error:'Fixture failure'}:{success:true})).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*text-layout-handler.php*'}]});
    async function publish(){const count=requests.length;await evaluate('window.ContentCompleteness=null;VE.adapter().runPrimary()');await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-dialog[open]'))"),'Confirmation missing');await evaluate("document.querySelector('.ve-publish-accept').click()");await until(async()=>requests.length>count&&await evaluate('!VE.state.busy && !document.querySelector(".ve-publish-dialog")'),'Publishing did not finish');}
    await publish();assert.ok(await evaluate('VE.adapter().dirtyCount()')>0,'Failed publish retains placement');
    fail=false;await publish();assert.equal(await evaluate('VE.adapter().dirtyCount()'),0);
    assert.equal(await evaluate(title+'.style.position'),'absolute','Published placement remains visible without reload: '+JSON.stringify(await evaluate("({mode:VE.state.win.GNCTextLayout.device(),config:VE.state.win.GNCTextLayout.snapshot(),title:VE.state.doc.querySelector('[data-ve-key=short-name]').outerHTML})")));
    await send('Fetch.disable');
    const payload=requests.at(-1), match=/name="changes"\r?\n\r?\n([^\r\n]+)/.exec(payload);
    const changes=JSON.parse(match[1]);assert.equal(Object.values(changes)[0].mobile,undefined,'Reset removes unpublished mobile override');
    const publicSettings=Object.fromEntries(Object.entries(changes).map(([id,devices])=>[id,Object.fromEntries(Object.entries(devices).filter(([,v])=>v))]));
    assert.ok(payload.includes('name="csrf_token"'));
    assert.equal(await evaluate("fetch('/admin/action/text-layout-handler.php',{method:'POST',credentials:'omit'}).then(r=>r.status)"),403,'Anonymous publication rejected');
    assert.equal(await evaluate("fetch('/admin/action/text-layout-handler.php',{method:'POST',credentials:'same-origin'}).then(r=>r.status)"),403,'Missing CSRF rejected');
    for(const page of ['home','history','contact-us']){
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
        await until(()=>evaluate("Boolean(window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veLoader').hidden)"),'Shared editor unavailable: '+page);
        await evaluate("(()=>{const el=[...VE.state.doc.querySelectorAll('[data-gnc-text]')].find(el=>!el.querySelector('[data-gnc-text]')&&el.getBoundingClientRect().width&&el.matches('[data-ve-field],[data-ve-section]'));if(!el)throw Error('No inline text');el.scrollIntoView();el.click()})()");
        assert.ok(await evaluate("[...document.querySelectorAll('#veTb button')].some(b=>b.textContent.trim()==='Move and resize text')"),'Shared text layout control: '+page);
        fs.writeFileSync(path.join(auditDirectory,'text-layout-editor-'+page+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'GNC_TEXT_LAYOUT',{configurable:true,get(){return config},set(value){config=${JSON.stringify(publicSettings)}}});})();`});
    const publicUrl=base+'/pages/academics/college/program.php?slug=bael';
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:publicUrl});await until(()=>evaluate("Boolean(window.GNCTextLayout && document.readyState==='complete' && document.querySelector('.template-title'))"),'Public page unavailable');await delay(250);
        assert.equal(await evaluate("Boolean(document.querySelector('.text-layout-box,[contenteditable=true]'))"),false,'Public page has no layout controls');
        assert.equal(await evaluate("document.querySelector('.template-title').style.position"),width===1440?'absolute':'','Public breakpoint-specific placement');
        fs.writeFileSync(path.join(auditDirectory,'text-layout-public-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        const overflow=await evaluate("({width:innerWidth,scroll:document.documentElement.scrollWidth,bad:[...document.querySelectorAll('body *')].filter(el=>{const r=el.getBoundingClientRect();return r.width&&r.right>innerWidth+2}).slice(0,6).map(el=>({tag:el.tagName,cls:el.className,width:el.getBoundingClientRect().width,right:el.getBoundingClientRect().right}))})");
        const baseline=await evaluate(`(()=>{const changes=${JSON.stringify(publicSettings)},reset={};for(const id of Object.keys(changes))reset[id]={desktop:{value:null},tablet:{value:null},mobile:{value:null}};GNCTextLayout.setChanges(reset);const w=document.documentElement.scrollWidth;GNCTextLayout.setChanges({});return w})()`);
        assert.ok(overflow.scroll<=baseline+2,'Placement adds no horizontal overflow: '+JSON.stringify(overflow));
        assert.ok(await evaluate("(()=>{const r=document.querySelector('.template-title').getBoundingClientRect();return r.left>=-1&&r.right<=innerWidth+1})()"),'Title stays within viewport');
    }
    console.log('PASS: real pointer drag/resize, 8px snapping and fine adjustment, inline editing, neighbor footprint, draft restore, responsive reset, failed/successful intercepted publication, public breakpoint application, no added overflow or public controls. Live page metadata unchanged.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
