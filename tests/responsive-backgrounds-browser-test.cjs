const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-responsive-bg-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(fn,message){for(let end=Date.now()+40000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php',...args],{encoding:'utf8',windowsHide:true});
let socket,auth,send,evaluate,fixtureRoute,fixtureSelector,fixtureCsrf;
const base=process.env.BASE_URL||'http://localhost:8080';
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome startup');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>reject(Error(method+' timed out')),45000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
    evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    await send('Page.enable');await send('Network.enable');
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:true});});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    auth=JSON.parse(php('create'));await send('Network.setCookie',{name:auth.name,value:auth.value,url:base});
    await send('Page.navigate',{url:base+'/admin/content-edit.php'});
    await until(()=>evaluate(`Boolean(document.querySelector('a[href*="page=home"]'))`),'Content hub');
    const pages=await evaluate(`Array.from(new Set(Array.from(document.querySelectorAll('a[href*="page="]')).map(a=>new URL(a.href).searchParams.get('page')).filter(p=>p&&!['hub','review','updates'].includes(p))))`);
    const routes=new Set();let targets=0;
    for(const page of pages){
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+encodeURIComponent(page)});
        await until(()=>evaluate(`window.VE_CONFIG?.page.slug===${JSON.stringify(page)}&&Boolean(window.VE)&&document.getElementById('veLoader')?.hidden`),'Editor '+page);
        const info=await evaluate(`(()=>{const w=document.getElementById('veFrame').contentWindow;return {url:VE.state.url,api:!!w.GNCResponsiveBackgrounds,kind:VE_CONFIG.page.kind};})()`);
        assert(info.api,'Shared background module on '+page);routes.add(info.url);
        const result=await evaluate(`(()=>{const w=VE.state.win,a=w.GNCResponsiveBackgrounds;a.scan();let count=0;for(const t of a.targets.values()){if(!t.node.isConnected||!t.source)continue;const initial=structuredClone(a.changes());for(const [i,d] of ['desktop','tablet','mobile'].entries()){a.setDevice(d);a.stage(t,{...a.frame(t,d),path:t.source,x:13+i*17,y:21+i*19,zoom:1},null,d);}for(const [i,d] of ['desktop','tablet','mobile'].entries()){a.setDevice(d);if(a.value(t.selector,d).x!==13+i*17)throw Error('Shared state '+t.selector);const css=w.getComputedStyle(t.node,t.pseudo||null);const position=t.kind==='image'?css.objectPosition:css.backgroundPosition;if(!position.startsWith((13+i*17)+'%'))throw Error('Wrong preview '+d+' '+t.selector+' '+position);}a.setChanges(initial);if(!Object.keys(initial).length&&!Object.keys(w.GNC_RESPONSIVE_BACKGROUNDS).length&&t.node.hasAttribute('data-responsive-background-active'))throw Error('Reset left an override');count++;}a.setDevice(VE.state.device);return count;})()`);
        targets+=result;console.log('Editor:',page,result,'backgrounds');
    }
    // Hosted headers can have no legacy banner while device photos are present.
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-departments&sub=cased'});
    await until(()=>evaluate('Boolean(window.VE)&&document.getElementById("veLoader")?.hidden&&!!VE.state.doc?.querySelector("[data-dept-banner-image] img")'),'Empty department header fixture');
    const emptyHeader=await evaluate(`(async()=>{
        const w=VE.state.win,a=w.GNCResponsiveBackgrounds,img=w.document.querySelector('[data-dept-banner-image] img');
        a.accept({});img.hidden=true;img.removeAttribute('src');a.targets.clear();a.scan();
        const t=a.target(img.parentElement);if(!t)throw Error('Empty banner must be selectable for device uploads');
        const canvas=document.createElement('canvas');canvas.width=80;canvas.height=40;canvas.getContext('2d').fillRect(0,0,80,40);
        const file=new File([await new Promise(r=>canvas.toBlob(r,'image/png'))],'header.png',{type:'image/png'}),url=URL.createObjectURL(file);
        const frame={...a.frame(t,'desktop'),path:url};a.setDevice('desktop');a.stage(t,frame,file,'desktop');
        const decoded=new w.Image();decoded.src=url;await decoded.decode();await new Promise(r=>w.requestAnimationFrame(()=>w.requestAnimationFrame(r)));
        const uploaded=w.getComputedStyle(img).display!=='none'&&img.getBoundingClientRect().width>0&&w.getComputedStyle(img).content.includes(url);
        a.setDevice('mobile');const isolated=w.getComputedStyle(img).display==='none';
        a.reset();a.setDevice('desktop');const reset=w.getComputedStyle(img).display==='none'&&img.hidden;
        a.accept({[t.selector]:{desktop:{...frame,path:'/assets/images/test-image.png'}}});
        const published=w.getComputedStyle(img).display!=='none'&&w.getComputedStyle(img).content.includes('/assets/images/test-image.png');
        a.accept({});URL.revokeObjectURL(url);return {uploaded,isolated,reset,published};
    })()`);
    assert.deepEqual(emptyHeader,{uploaded:true,isolated:true,reset:true,published:true},'New uploads and saved device photos must reveal hidden empty headers, preserve other devices and reset cleanly');
    // Public viewport CSS must agree with the editor for every discovered route.
    for(const route of routes){
        await send('Page.navigate',{url:new URL(route,base).href});
        await until(()=>evaluate('Boolean(window.GNCResponsiveBackgrounds)&&document.readyState!=="loading"'),'Public background module '+route);
        await evaluate(`(()=>{const a=GNCResponsiveBackgrounds;a.scan();window.bgTestTarget=Array.from(a.targets.values()).find(t=>t.source&&t.node.isConnected);if(!bgTestTarget)return;for(const [i,d]of ['desktop','tablet','mobile'].entries())a.stage(bgTestTarget,{...a.frame(bgTestTarget,d),path:bgTestTarget.source,x:12+i*21,y:24,zoom:1},null,d);})()`);
        for(const [i,width]of [1366,900,390].entries()){
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            assert(await evaluate(`(()=>{GNCResponsiveBackgrounds.apply();if(!window.bgTestTarget)return true;const t=bgTestTarget,c=getComputedStyle(t.node,t.pseudo||null);return (t.kind==='image'?c.objectPosition:c.backgroundPosition).startsWith('${12+i*21}%');})()`),'Public device CSS '+route+' '+width);
        }
    }
    await send('Emulation.clearDeviceMetricsOverride');
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=history'});
    await until(()=>evaluate('Boolean(window.VE)&&document.getElementById("veLoader")?.hidden&&!!VE.state.win.GNCResponsiveBackgrounds'),'History editor');
    fixtureRoute='/pages/about/gnc-history.php?rb_test='+Date.now();
    await evaluate(`VE.state.url=${JSON.stringify(fixtureRoute)};document.getElementById('veFrame').src=${JSON.stringify(base+fixtureRoute+'&ce_preview=1')}`);
    await until(()=>evaluate(`VE.state.doc?.URL.includes('rb_test=')&&document.getElementById('veLoader').hidden&&!!VE.state.win.GNCResponsiveBackgrounds`),'Isolated editor fixture');
    fixtureSelector=await evaluate(`(()=>{const a=VE.state.win.GNCResponsiveBackgrounds;a.scan();const t=Array.from(a.targets.values()).find(t=>t.kind==='background'&&t.source);window.bgFixture={selector:t.selector,path:t.source};VE.state.device='mobile';a.setDevice('mobile');a.stage(t,{...a.frame(t),path:t.source,x:17,y:29,zoom:1.2},null,'mobile');return t.selector;})()`);
    assert(await evaluate('VE.adapter().dirtyCount()>0'),'Background enables Save/Publish');
    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('!VE.state.busy&&document.getElementById("veSaveDraft").disabled'),'Background draft saved');
    await evaluate('document.getElementById("veFrame").src=document.getElementById("veFrame").src');
    await until(()=>evaluate(`document.getElementById('veLoader').hidden&&VE.state.win.GNCResponsiveBackgrounds?.value(${JSON.stringify(fixtureSelector)},'mobile')?.x===17`),'Background draft restored');
    assert.equal(await evaluate(`Object.keys(VE.state.win.GNC_RESPONSIVE_BACKGROUNDS).length`),0,'Draft does not publish');
    await evaluate('window.ContentCompleteness=null;document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Publish requires confirmation');
    await evaluate('document.querySelector(".ve-publish-accept").click()');
    await until(()=>evaluate('!VE.state.busy&&VE.adapter().dirtyCount()===0'),'Publish completed');
    fixtureCsrf=await evaluate('VE_CONFIG.csrf');
    await send('Page.navigate',{url:base+fixtureRoute});
    await until(()=>evaluate('Boolean(window.GNCResponsiveBackgrounds)&&document.readyState!=="loading"'),'Published fixture');
    const settings=await evaluate('GNC_RESPONSIVE_BACKGROUNDS');
    // Public pages need not expose an editor token; retain it from the editor.
    await evaluate('window.backgroundTestCsrf='+JSON.stringify(fixtureCsrf));
    assert.equal(settings[fixtureSelector].mobile.x,17);assert.equal(settings[fixtureSelector].desktop,undefined);assert.equal(settings[fixtureSelector].tablet,undefined);
    // Real upload, persistence, validation, and per-device deletion through the shared backend.
    const response=await evaluate(`(async()=>{const c=document.createElement('canvas');c.width=90;c.height=160;c.getContext('2d').fillRect(0,0,90,160);const blob=await new Promise(r=>c.toBlob(r,'image/png'));const token=window.backgroundTestCsrf;const post=async(changes,file,csrf=token)=>{const fd=new FormData();fd.set('csrf_token',csrf);fd.set('route',${JSON.stringify(fixtureRoute)});fd.set('changes',JSON.stringify(changes));if(file)fd.set('background_0',file,'portrait.png');const r=await fetch('/admin/action/responsive-background-handler.php',{method:'POST',body:fd});return {status:r.status,body:await r.json()};};const selector=${JSON.stringify(fixtureSelector)};const current=GNC_RESPONSIVE_BACKGROUNDS[selector].mobile;const uploaded=await post({[selector]:{tablet:{...current,path:'',upload:'background_0'}}},blob);const badCsrf=await post({[selector]:{mobile:null}},null,'wrong');const badDevice=await post({[selector]:{phone:current}});const badPath=await post({[selector]:{desktop:{...current,path:'/config/config.php'}}});const removed=await post({[selector]:{mobile:null}});const cleared=await post({[selector]:{desktop:null,tablet:null,mobile:null}});return {uploaded,badCsrf,badDevice,badPath,removed,cleared};})()`);
    assert.equal(response.uploaded.status,200,JSON.stringify(response.uploaded));assert(response.uploaded.body.settings[fixtureSelector].tablet.path.startsWith('/uploads/'));
    assert.equal(response.badCsrf.status,403);assert.equal(response.badDevice.status,422);assert.equal(response.badPath.status,422);
    assert.equal(response.removed.status,200);assert(!response.removed.body.settings[fixtureSelector].mobile);assert(response.removed.body.settings[fixtureSelector].tablet);
    assert.equal(response.cleared.status,200);assert.equal(Object.keys(response.cleared.body.settings).length,0);
    const anonymous=await fetch(base+'/admin/action/responsive-background-handler.php',{method:'POST'});assert(anonymous.status===403||anonymous.redirected,'Anonymous publication rejected');
    console.log(`PASS: ${pages.length} editor pages, ${routes.size} public routes at three widths, ${targets} background targets, independent device edits, draft restore, explicit publish, upload, reset, CSRF and invalid inputs.`);
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{
    if(fixtureRoute&&fixtureSelector&&evaluate)try{await evaluate(`(async()=>{const fd=new FormData();fd.set('csrf_token',window.VE_CONFIG?.csrf||window.backgroundTestCsrf||window.GNC_IMAGE_FRAMING_CONFIG?.csrf||'');fd.set('route',${JSON.stringify(fixtureRoute)});fd.set('changes',JSON.stringify({[${JSON.stringify(fixtureSelector)}]:{desktop:null,tablet:null,mobile:null}}));await fetch('/admin/action/responsive-background-handler.php',{method:'POST',body:fd});})()`);}catch{}
    if(auth)try{php('destroy',auth.value);}catch{}
    if(socket)socket.close();browser.kill();await delay(700);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-responsive-bg-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
