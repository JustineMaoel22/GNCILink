const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-payment-editor-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session;
const {execFileSync}=require('node:child_process');
let ownsSlot=false;
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser command timed out: '+method));},15000);pending.set(id,result=>{clearTimeout(timer);result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')console.error('Browser exception:',JSON.stringify(p.params.exceptionDetails));});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    session=JSON.parse(php('session'));
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});

    const editor=base+'/admin/content-edit.php?page=online-payment-guide';
    const frame='document.getElementById("veFrame")?.contentDocument';
    const live=async()=>await(await fetch(base+'/pages/admission/online-payment-guide.php')).text();
    const imagePath=html=>html.match(/class="payment-option-art"[^>]*>\s*<img src="([^"]+)"/)[1];
    const original=imagePath(await live());
    assert.equal(original,'/assets/images/payment-guide/gcash.svg','Run on the default GCash slot to avoid changing existing custom artwork');
    async function load(){
        await send('Page.navigate',{url:editor});
        try { await until(()=>evaluate(`Boolean(${frame}?.getElementById('vePaymentGuideData'))&&document.getElementById('veLoader').hidden`),'Payment editor unavailable'); }
        catch(error){console.error(await evaluate(`({url:location.href,kind:window.VE_CONFIG?.page?.kind,frame:document.getElementById('veFrame')?.src,body:${frame}?.body?.innerText?.slice(0,300),loader:document.getElementById('veLoader')?.innerText,marker:Boolean(${frame}?.getElementById('vePaymentGuideData'))})`));throw error;}
    }
    async function publish(){
        await evaluate('document.getElementById("vePrimary").click()');
        await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-accept,.cms-completeness-publish"))'),'Publish dialog missing');
        await evaluate('document.querySelector(".ve-publish-accept,.cms-completeness-publish").click()');
    }
    await load();
    assert.equal(await evaluate('VE_CONFIG.page.kind'),'payment-guide');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-img="payment-guide-image"]').length`),8);
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),true);
    assert(!(await live()).includes('data-ve-img="payment-guide-image"'),'Public page hides editing controls');
    assert(!(await live()).includes('vePaymentGuideData'),'Public page hides editor configuration');
    const svg='<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" onload="alert(1)"><script>alert(1)</script><rect width="100" height="100" fill="#215e30"/><image href="https://example.com/remote.png"/></svg>';
    await evaluate(`${frame}.querySelector('[data-ve-key="gcash"] img').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Change illustration').click()`);
    await evaluate(`(()=>{const input=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File([${JSON.stringify(svg)}],'payment-test.svg',{type:'image/svg+xml'}));input.files=dt.files;input.dispatchEvent(new Event('change'));})()`);
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'Staged replacement enables Publish');
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-key="gcash"] img').getAttribute('src').startsWith('blob:')`),true);
    assert.equal(imagePath(await live()),original,'Staged artwork stays private');
    ownsSlot=true;
    await publish();
    console.log('PASS: replacement staged and publication confirmed');
    let uploaded;
    await until(async()=>{uploaded=imagePath(await live());return uploaded.startsWith('/uploads/payment-guide/');},'Published SVG not visible');
    const stored=await(await fetch(base+uploaded)).text();
    assert(!/onload|<script|https:\/\/example.com/.test(stored),'SVG scripts, event handlers and external references removed');
    await until(()=>evaluate(`Boolean(window.VE_CONFIG&&${frame}?.getElementById('vePaymentGuideData'))&&Boolean(document.getElementById('veLoader')?.hidden)&&Boolean(document.getElementById('vePrimary')?.disabled)`),'Editor reload after publication');
    console.log('PASS: uploaded SVG sanitized and editor reloaded');
    const request=async(images,files={},csrf=true)=>evaluate(`(async()=>{const fd=new FormData();${csrf?'fd.append("csrf_token",VE_CONFIG.csrf);':''}fd.append('images',JSON.stringify(${JSON.stringify(images)}));const files=${JSON.stringify(files)};Object.keys(files).forEach(key=>fd.append('image_'+key,new File([files[key]],'invalid.png',{type:'image/png'})));const r=await fetch('/admin/action/payment-guide-handler.php',{method:'POST',body:fd});return {status:r.status,body:await r.json()};})()`);
    assert.equal((await request({unknown:{reset:true}})).status,422);
    assert.equal((await request({gcash:{reset:true}}, {}, false)).status,403,'CSRF is required');
    assert.equal((await request({gcash:{reset:true},banking:{reset:false}},{banking:'not an image'})).status,422,'Invalid upload rolls back the entire batch');
    assert.equal(imagePath(await live()),uploaded,'Failed batch preserves live illustration');
    const anon=await fetch(base+'/admin/action/payment-guide-handler.php',{method:'POST',body:new URLSearchParams({images:'{}'})});
    assert.equal(anon.status,403,'Anonymous publishing rejected');
    await until(()=>evaluate(`Boolean(${frame}?.getElementById('vePaymentGuideData'))&&document.getElementById('vePrimary').disabled`),'Editor reload');
    await evaluate(`${frame}.querySelector('[data-ve-key="gcash"] img').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Restore Storyset illustration').click()`);
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-key="gcash"] img').getAttribute('src')`),original);
    assert.equal(imagePath(await live()),uploaded,'Restore stays private until publishing');
    await publish();
    console.log('PASS: restore publication confirmed');
    await until(async()=>imagePath(await live())===original,'Default not restored');
    ownsSlot=false;
    await until(()=>evaluate(`Boolean(window.VE_CONFIG&&${frame}?.getElementById('vePaymentGuideData'))&&Boolean(document.getElementById('veLoader')?.hidden)&&Boolean(document.getElementById('vePrimary')?.disabled)`),'Editor reload after restore');
    assert.equal((await fetch(base+uploaded)).status,404,'Replaced upload cleaned up');
    for(const width of [1440,768,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+'/pages/admission/online-payment-guide.php'});
        await until(()=>evaluate('document.readyState==="complete"&&Boolean(document.querySelector(".payment-options"))'),'Public page unavailable');
        await evaluate('document.querySelectorAll(".payment-guide img").forEach(e=>e.loading="eager")');
        await until(()=>evaluate('Array.from(document.querySelectorAll(".payment-guide img")).every(e=>e.complete&&e.naturalWidth>0)'),'Storyset artwork did not load');
        assert.equal(await evaluate('document.querySelectorAll(".payment-guide img").length'),8);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
        assert.equal(await evaluate('Boolean(document.querySelector(".payment-art-credit a"))'),true);
        console.log('PASS '+width+'px: all eight local illustrations load, attribution and responsive width');
        if(width===1440){await delay(1800);await evaluate('window.scrollTo({top:document.querySelector(".payment-verification").getBoundingClientRect().top+scrollY-85,behavior:"instant"})');const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-storyset-guide.png'),Buffer.from(shot.data,'base64'));}
    }
    console.log('PASS: editor discovery, upload staging/privacy, publishing, SVG sanitization, invalid batch rollback, authorization, CSRF, restore and file cleanup.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(ownsSlot&&session)try{
        const response=await fetch((process.env.BASE_URL||'http://localhost:8080')+'/admin/action/payment-guide-handler.php',{method:'POST',headers:{Cookie:session.name+'='+session.id},body:new URLSearchParams({csrf_token:session.csrf,images:JSON.stringify({gcash:{reset:true}})})});
        assert.equal(response.status,200,'Fixture cleanup');
    }catch(error){console.error('Fixture cleanup failed',error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-payment-editor-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
