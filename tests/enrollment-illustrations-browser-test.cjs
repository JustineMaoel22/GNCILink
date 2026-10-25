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


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const frame='document.getElementById("veFrame")?.contentDocument';
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=enrollment-procedures'});
    await until(()=>evaluate(`Boolean(${frame}?.getElementById('veEnrollmentIllustrations')) && document.getElementById('veLoader').hidden`),'Enrollment illustration editor unavailable');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-img="enrollment-illustration"]').length >= 26`),true);
    const before=await(await fetch(base+'/pages/admission/enrollment-procedures.php')).text();
    assert(!before.includes('veEnrollmentIllustrations'));assert(!before.includes('data-ve-img="enrollment-illustration"'));
    const tools=['Change illustration','Restore Storyset illustration','Browse Storyset'];
    for(const [tab,key] of [['freshmen','fresh-1'],['transferees','trans-1'],['old-students','old-1']]) {
        await evaluate(`${frame}.getElementById('${tab}-tab').click();${frame}.querySelector('[data-ve-key="${key}"] img').click()`);
        const labels=await evaluate("Array.from(document.querySelectorAll('#veTb button'),b=>b.getAttribute('aria-label'))");
        tools.forEach(tool=>assert(labels.includes(tool),tab+' has '+tool));
    }
    await evaluate(`${frame}.getElementById('freshmen-tab').click();${frame}.querySelector('[data-ve-key="fresh-1"] img').click()`);
    await evaluate("Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Change illustration').click()");
    await evaluate(`(()=>{const input=document.getElementById('veFile'),data=new DataTransfer();data.items.add(new File(['<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="green"/></svg>'],'test-enrollment.svg',{type:'image/svg+xml'}));input.files=data.files;input.dispatchEvent(new Event('change'));})()`);
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-key="fresh-1"] img').getAttribute('src').startsWith('blob:')`),true,'Uploaded illustration previews inline');
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'Illustration enables global Publish');
    assert.equal(await evaluate('document.getElementById("veSecondary").disabled'),true,'Illustrations stay staged until Publish');
    assert.equal(await(await fetch(base+'/pages/admission/enrollment-procedures.php')).text(),before,'Preview does not change live artwork');
    // Exercise the global Publish flow while intercepting only its illustration write.
    await evaluate(`(()=>{const native=window.fetch;window.testNativeFetch=native;window.fetch=function(url,options){if(url==='/admin/action/enrollment-canvas-handler.php'){window.testIllustrationPublish={images:JSON.parse(options.body.get('images')),hasFile:options.body.get('image_fresh-1') instanceof File};return Promise.resolve(new Response(JSON.stringify({error:'TEST intercepted; nothing published'}),{status:422,headers:{'Content-Type':'application/json'}}));}return native.call(this,url,options);};})()`);
    await evaluate('document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-accept,.cms-completeness-publish"))'),'Publish confirmation missing');
    await evaluate('document.querySelector(".ve-publish-accept,.cms-completeness-publish").click()');
    await until(()=>evaluate('Boolean(window.testIllustrationPublish)'),'Global Publish did not send illustration changes');
    assert.equal(await evaluate('testIllustrationPublish.hasFile'),true);
    assert.equal(await evaluate('testIllustrationPublish.images["fresh-1"].reset'),false);
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'Failed publication keeps the staged illustration for retry');
    await evaluate("Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Restore Storyset illustration').click()");
    const expected=await evaluate(`JSON.parse(${frame}.getElementById('veEnrollmentIllustrations').textContent)['fresh-1'].default`);
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-key="fresh-1"] img').getAttribute('src')`),expected);
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),true,'Restoring an unpublished replacement clears its dirty state');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await delay(600);
    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    await evaluate('window.fetch=window.testNativeFetch');
    const bad=await evaluate(`(async()=>{const body=new FormData();body.append('csrf_token','bad');body.append('images','{}');return (await fetch('/admin/action/enrollment-canvas-handler.php',{method:'POST',body})).status;})()`);
    assert.equal(bad,403,'CSRF required');
    const anon=await fetch(base+'/admin/action/enrollment-canvas-handler.php',{method:'POST',body:new URLSearchParams({images:'{}'})});
    assert.equal(anon.status,403,'Anonymous image publishing refused');
    console.log('PASS: all College tabs offer payment-guide illustration tools; upload previews inline, Publish tracks image changes, restore clears staged changes, mobile and security checks passed. Nothing published.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
