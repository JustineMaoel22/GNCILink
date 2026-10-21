const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-page-skeleton-'));
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
    const held=new Map();
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`
        const nativeTimeout=window.setTimeout.bind(window);window.__skeletonTimeouts=[];
        window.setTimeout=(fn,ms,...args)=>{if(ms===15000)window.__skeletonTimeouts.push(()=>fn(...args));return nativeTimeout(fn,ms,...args);};
    `});
    await send('Fetch.enable',{patterns:[{urlPattern:'*__skeleton-*'}]});
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Fetch.requestPaused')held.set(new URL(packet.params.request.url).pathname,packet.params.requestId);});
    async function finish(name,fail=false){await until(()=>held.has('/'+name),'Resource request missing');const requestId=held.get('/'+name);held.delete('/'+name);if(fail)await send('Fetch.failRequest',{requestId,errorReason:'Failed'});else await send('Fetch.fulfillRequest',{requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:name.endsWith('.svg')?'image/svg+xml':'text/html'}],body:Buffer.from(name.endsWith('.svg')?'<svg xmlns="http://www.w3.org/2000/svg" width="240" height="160"><rect width="240" height="160" fill="#1f5e2c"/></svg>':'<!doctype html><title>Loaded document</title><p>Loaded</p>').toString('base64')});await delay(100);}
    async function navigate(url){await send('Page.navigate',{url:base+url});await until(()=>evaluate(`location.href===${JSON.stringify(base+url)}&&document.readyState==='complete'`),'Page did not load');}
    async function addImage(name){await evaluate(`(()=>{const img=document.createElement('img');img.id='testSkeletonImage';img.width=240;img.height=160;img.src='/'+${JSON.stringify(name)};document.querySelector('main,section').appendChild(img);})()`);await until(()=>held.has('/'+name),'Delayed image did not start');await delay(100);}
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await navigate('/pages/admission/tuition-fees.php');
        await addImage('__skeleton-success-'+width+'.svg');
        assert.equal(await evaluate('document.getElementById("testSkeletonImage").classList.contains("gnc-auto-skeleton-image")'),true,'Pending image has a skeleton');
        assert.deepEqual(await evaluate('(()=>{const r=document.getElementById("testSkeletonImage").getBoundingClientRect();return [r.width,r.height]})()'),[240,160],'Skeleton preserves image size');
        await finish('__skeleton-success-'+width+'.svg');
        assert.equal(await evaluate('document.getElementById("testSkeletonImage").classList.contains("gnc-auto-skeleton-image")'),false,'Loaded image clears skeleton');
        await evaluate('document.getElementById("testSkeletonImage").remove()');
        await addImage('__skeleton-error-'+width+'.svg');await finish('__skeleton-error-'+width+'.svg',true);
        assert.equal(await evaluate('document.getElementById("testSkeletonImage").classList.contains("gnc-auto-skeleton-image")'),false,'Failed image clears skeleton');
        await evaluate('document.getElementById("testSkeletonImage").remove()');
        await addImage('__skeleton-timeout-'+width+'.svg');await evaluate('window.__skeletonTimeouts.forEach(fn=>fn())');
        assert.equal(await evaluate('document.getElementById("testSkeletonImage").classList.contains("gnc-auto-skeleton-image")'),false,'Timeout clears skeleton');await finish('__skeleton-timeout-'+width+'.svg');
        await evaluate('(()=>{const frame=document.createElement("iframe");frame.id="testSkeletonFrame";frame.src="/__skeleton-document-'+width+'.html";document.querySelector(".fee-document-stage").appendChild(frame)})()');
        await until(()=>evaluate('Boolean(document.querySelector(".gnc-auto-skeleton-frame"))'),'Document skeleton missing');
        await finish('__skeleton-document-'+width+'.html');
        assert.equal(await evaluate('Boolean(document.querySelector(".gnc-auto-skeleton-frame"))'),false,'Document load clears overlay');
        await evaluate('(()=>{const banner=document.createElement("section");banner.id="testSkeletonBanner";banner.className="fixture-hero";banner.style.cssText="height:120px;background-image:url(/__skeleton-banner-'+width+'.svg)";document.querySelector("main").appendChild(banner)})()');
        await until(()=>evaluate('document.getElementById("testSkeletonBanner").classList.contains("gnc-auto-skeleton-background")'),'Banner skeleton missing');
        await finish('__skeleton-banner-'+width+'.svg');
        assert.equal(await evaluate('document.getElementById("testSkeletonBanner").classList.contains("gnc-auto-skeleton-background")'),false,'Banner background restores after loading');
        console.log('PASS '+width+'px: pending images, dimensions, load/error/timeout cleanup and embedded document loading');
    }
    await navigate('/pages/academics/college/college-dept.php');await addImage('__skeleton-existing.svg');
    assert.equal(await evaluate('document.getElementById("testSkeletonImage").classList.contains("gnc-auto-skeleton-image")'),false,'Existing page loader is not duplicated');
    assert.equal(await evaluate(`Boolean(document.querySelector('link[href*="page-skeletons.css"]'))`),false);await finish('__skeleton-existing.svg');
    await navigate('/pages/admission/tuition-fees.php?ce_preview=1');await addImage('__skeleton-preview.svg');
    assert.equal(await evaluate('document.getElementById("testSkeletonImage").classList.contains("gnc-auto-skeleton-image")'),false,'Editor preview stays unchanged');await finish('__skeleton-preview.svg');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await navigate('/pages/admission/tuition-fees.php');await addImage('__skeleton-reduced.svg');
    assert.equal(await evaluate('getComputedStyle(document.getElementById("testSkeletonImage")).animationName'),'none','Reduced motion disables shimmer');await finish('__skeleton-reduced.svg');
    console.log('PASS: existing loaders skipped, editor preview excluded, reduced motion supported. No database changes.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-page-skeleton-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
