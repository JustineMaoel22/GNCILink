const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-rec-modal-'));
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
    // Advance only the recommendation clock; leave normal navigation and UI timers running.
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{
        const nativeNow=Date.now.bind(Date),nativePerformanceNow=performance.now.bind(performance),nativeTimeout=window.setTimeout.bind(window),nativeClear=window.clearTimeout.bind(window);
        let offset=0,serial=0;const jobs=new Map();
        Date.now=()=>nativeNow()+offset;
        Object.defineProperty(performance,'now',{value:()=>nativePerformanceNow()+offset});
        window.setTimeout=(callback,delay,...args)=>{if(typeof callback==='function'&&callback.name==='openWhenReady'){const id=--serial;jobs.set(id,{callback,due:Date.now()+delay,args});return id;}return nativeTimeout(callback,delay,...args);};
        window.clearTimeout=id=>{if(jobs.has(id))jobs.delete(id);else nativeClear(id);};
        window.__advanceRecommendationsClock=ms=>{offset+=ms;for(let i=0;i<20;i++){const due=Array.from(jobs.entries()).find(([,job])=>job.due<=Date.now());if(!due)break;jobs.delete(due[0]);due[1].callback(...due[1].args);}};
    })()`});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    async function navigate(url){url=(await fetch(url)).url;await send('Page.navigate',{url});await until(()=>evaluate(`(()=>{const expected=new URL(${JSON.stringify(url)});return location.pathname.replace(/\.php$/,'')===expected.pathname.replace(/\.php$/,'')&&location.search===expected.search&&document.readyState==='complete';})()`),'Page did not load: '+url);await until(()=>evaluate('document.querySelectorAll("#gncRecCards a").length>0'),'Recommendation catalog unavailable');}
    async function advance(ms){await evaluate(`window.__advanceRecommendationsClock(${ms})`);await delay(250);}
    await navigate(base+'/');
    assert.equal(await evaluate('document.getElementById("gncRecommendations").tagName'),'DIALOG');
    assert.equal(await evaluate('document.querySelector("section#gncRecommendations")'),null,'Footer suggestion section is removed');
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),false,'Suggestions stay closed initially');
    await advance(Math.max(1,59000-await evaluate('performance.now()')));
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),false,'Suggestions must not open before one minute');
    await advance(1500);
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),true,'Suggestions open after one minute');
    assert.equal(await evaluate('document.activeElement.id'),'gncRecClose','Focus moves into the dialog');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await advance(250);
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),false,'Escape dismisses suggestions');
    await advance(65000);
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),false,'Dismissed suggestions do not repeatedly interrupt the same page');
    await navigate(base+'/pages/about/vision-mission.php');
    await evaluate('const blocker=document.createElement("dialog");blocker.id="testBlocker";blocker.textContent="Another dialog";document.body.appendChild(blocker);blocker.showModal()');
    await advance(Math.max(1,61000-await evaluate('performance.now()')));
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),false,'Suggestions wait for another dialog');
    await evaluate('document.getElementById("testBlocker").close()');await advance(1200);
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),true,'New public page has its own delayed suggestions');
    await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',buttons:1,clickCount:1,x:1,y:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',buttons:0,clickCount:1,x:1,y:1});
    await advance(250);
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),false,'Backdrop click dismisses suggestions');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:false});
    await navigate(base+'/pages/student%20life/student%20organization%20and%20leadership%20program/student-org.php');
    await advance(Math.max(1,61000-await evaluate('performance.now()')));
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),true);
    assert.equal(await evaluate('(()=>{const r=document.getElementById("gncRecommendations").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height<=innerHeight;})()'),true,'Modal fits a mobile viewport');
    const screenshot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-recommendations-modal.png'),Buffer.from(screenshot.data,'base64'));
    await evaluate('document.getElementById("gncRecSettings").click()');
    assert.equal(await evaluate('document.getElementById("gncRecPrivacy").hidden'),false,'Privacy settings can be expanded');
    await evaluate('document.getElementById("gncRecEnable").click()');
    assert.equal(await evaluate('JSON.parse(localStorage.getItem("gnc_recommendations_v1")).consent'),true,'Personalization settings still work');
    await evaluate('document.getElementById("gncRecDismiss").click()');await advance(250);
    assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),false,'Keep exploring closes the modal');
    await send('Page.navigate',{url:base+'/pages/about/vision-mission.php?ce_preview=1'});
    await until(()=>evaluate('location.search.includes("ce_preview")&&document.readyState==="complete"'),'Preview page unavailable');
    assert.equal(await evaluate('Boolean(document.getElementById("gncRecommendations"))'),false,'Visual Editor previews omit recommendation modals');
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await navigate(base+'/pages/academics/college/college-department.php?slug=cased');
        await evaluate('document.querySelector(".dept-dean-content").scrollIntoView({block:"center",behavior:"instant"})');
        await delay(1300);
        const originalScroll=await evaluate('scrollY');
        await advance(Math.max(1,61000-await evaluate('performance.now()')));
        assert.equal(await evaluate('document.getElementById("gncRecommendations").open'),true);
        assert.equal(await evaluate('document.getElementById("mainNavbar").getBoundingClientRect().top'),0,'Sticky navbar stays visible behind suggestions');
        assert(Math.abs(await evaluate('scrollY')-originalScroll)<=1,'Opening suggestions preserves page position');
        await evaluate('document.getElementById("gncRecClose").click()');await advance(250);
        assert(Math.abs(await evaluate('scrollY')-originalScroll)<=1,'Closing suggestions preserves page position');
        assert.equal(await evaluate('document.getElementById("mainNavbar").getBoundingClientRect().top'),0);
    }
    console.log('PASS: 60-second timing, once-per-page display, Escape/backdrop/buttons, other-modal deferral, mobile fit, personalization, preview exclusions, sticky navbar and scroll preservation. No database changes.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-rec-modal-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
