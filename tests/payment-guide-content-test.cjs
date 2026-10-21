const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-payment-content-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session;
const {execFileSync}=require('node:child_process');
let snapshot;
const fixture=(...args)=>execFileSync('docker',['compose','exec','-T','web','php','tests/payment-guide-content-fixture.php',...args],{encoding:'utf8',windowsHide:true});
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
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
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')console.error('Browser exception:',JSON.stringify(p.params.exceptionDetails));});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    session=JSON.parse(php('session'));
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});


    snapshot=fixture('snapshot').trim();
    const editor=base+'/admin/content-edit.php?page=online-payment-guide';
    const frame='document.getElementById("veFrame")?.contentDocument';
    const live=async()=>await(await fetch(base+'/pages/admission/online-payment-guide.php')).text();
    const token='PAYMENT-CONTENT-'+Date.now();
    const before=await live();
    async function ready(){await until(()=>evaluate(`Boolean(${frame}?.querySelector('[data-ve-key="hero_title"]')?.__ve)&&Boolean(document.getElementById('veLoader')?.hidden)`),'Editable fields unavailable');}
    async function edit(key,value,html=false){await evaluate(`(()=>{const el=${frame}.querySelector('[data-ve-key="${key}"]');el.${html?'innerHTML':'textContent'}=${JSON.stringify(value)};el.dispatchEvent(new Event('input',{bubbles:true}));el.click();})()`);}
    await send('Page.navigate',{url:editor});await ready();
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-field="payment-guide-text"]').length`)>40,true);
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),true);
    await edit('hero_title',token);
    await edit('account_number','TEST-ACCOUNT-ONLY');
    assert.equal(await evaluate(`Array.from(${frame}.querySelectorAll('[data-ve-key="account_number"]')).every(e=>e.textContent==='TEST-ACCOUNT-ONLY')`),true,'Shared account details stay synchronized');
    await edit('gcash_step_1','<p><strong>'+token+'</strong> payment instructions</p>',true);
    assert.equal(await evaluate('Boolean(document.querySelector(\'#veTb [aria-label="Bold (Ctrl+B)"]\'))'),true,'Rich text toolbar available');
    await edit('contact_link_label','Test contact label');
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Edit destination').click()`);
    const url='/pages/contact-us.php?payment-test='+token;
    await evaluate(`(()=>{const input=document.querySelector('#vePop input');input.value=${JSON.stringify(url)};document.querySelector('#vePop button').click();})()`);
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-payment-link="contact_link_url"]').getAttribute('href')`),url);
    assert.equal((await live()).includes(token),false,'Unpublished text and URLs stay private');
    await evaluate('document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-accept,.cms-completeness-publish"))'),'Publish confirmation missing');
    await evaluate('document.querySelector(".ve-publish-accept,.cms-completeness-publish").click()');
    await until(async()=> (await live()).includes(token),'Published text not visible');
    await ready();
    await until(()=>evaluate('Boolean(document.getElementById("vePrimary")?.disabled)'),'Publish state did not reset');
    const published=await live();
    assert(published.includes('<strong>'+token+'</strong>'),'Rich formatting survives publishing');
    assert.equal((published.match(/TEST-ACCOUNT-ONLY/g)||[]).length,2,'Both account numbers persist');
    assert(published.includes(url));
    assert(!published.includes('data-ve-field="payment-guide-text"'),'Public visitors get no editor controls');
    assert(!published.includes('Downloading from Storyset?'),'Admin note stays private');
    async function post(texts,extra={}){return evaluate(`(async()=>{const fd=new FormData();fd.append('csrf_token',VE_CONFIG.csrf);fd.append('texts',JSON.stringify(${JSON.stringify(texts)}));fd.append('images',JSON.stringify(${JSON.stringify(extra)}));const r=await fetch('/admin/action/payment-guide-handler.php',{method:'POST',body:fd});return {status:r.status,body:await r.json()};})()`);}
    for(const invalid of ['javascript:alert(1)','//example.com','https://example.com\\bad','data:text/html,test']){
        const result=await post({hero_title:'MUST-NOT-PUBLISH',contact_link_url:invalid});assert.equal(result.status,422,'Unsafe destination rejected');
    }
    assert.equal((await post({unknown_field:'bad'})).status,422);
    assert.equal((await post({hero_title:'x'.repeat(256)})).status,422,'Length checked on server');
    assert.equal((await post({hero_title:'MUST-NOT-PUBLISH'},{unknown:{reset:true}})).status,422,'Image validation rolls back accompanying text');
    assert(!(await live()).includes('MUST-NOT-PUBLISH'));
    const sanitized=await post({gcash_step_1:'<p>Safe <strong>instructions</strong><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">link</a></p>'});
    assert.equal(sanitized.status,200);
    const clean=await live();assert(clean.includes('<strong>instructions</strong>'));assert(!clean.includes('alert(1)'));
    fixture('restore',snapshot);snapshot=null;
    assert.equal(await live(),before,'All test text and URL overrides restored exactly');
    for(const width of [1440,768,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+'/pages/admission/online-payment-guide.php'});
        await until(()=>evaluate('document.readyState==="complete"&&Boolean(document.querySelector(".payment-options"))'),'Guide unavailable');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
        console.log('PASS '+width+'px: public guide layout');
    }
    console.log('PASS: inline headings, rich instructions, synchronized accounts, button labels/URLs, staging privacy, publication, validation, atomic rollback, sanitization and fixture restoration.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(snapshot)try{fixture('restore',snapshot);}catch(error){console.error('Content fixture cleanup failed',error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-payment-content-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
