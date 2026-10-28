const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-org-filters-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session,send;
const {execFileSync}=require('node:child_process');
let orgFixture, restoreEyebrow;
const fixture=(...args)=>execFileSync('docker',['compose','exec','-T','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser command timed out: '+method));},15000);pending.set(id,result=>{clearTimeout(timer);result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')console.error('Browser exception:',JSON.stringify(p.params.exceptionDetails));});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});



    orgFixture=JSON.parse(fixture('seed'));
    const listing=base+orgFixture.url;
    await send('Page.navigate',{url:listing});
    await until(()=>evaluate('document.readyState==="complete"&&Boolean(document.querySelector(".student-org-filters"))'),'Directory missing');
    await evaluate('window.orgFilterDocumentToken="same-document"');
    async function clickCategory(id){
        await evaluate(`Array.from(document.querySelectorAll('.student-org-filters a')).find(a=>new URL(a.href).searchParams.get('category')===${JSON.stringify(String(id))}).click()`);
        await until(()=>evaluate(`new URL(location.href).searchParams.get('category')===${JSON.stringify(String(id))}&&!document.querySelector('.student-org-directory').hasAttribute('aria-busy')`),'Category did not update');
    }
    await clickCategory(orgFixture.categories[0]);
    assert.equal(await evaluate('window.orgFilterDocumentToken'),'same-document','Filter does not reload the page');
    assert.equal(await evaluate('document.querySelectorAll(".student-org-card").length'),6);
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".student-org-filters a")).animationName'),'none');
    await evaluate(`Array.from(document.querySelectorAll('.student-org-pagination a')).find(a=>a.textContent==='2').click()`);
    await until(()=>evaluate('new URL(location.href).searchParams.get("p")==="2"&&!document.querySelector(".student-org-directory").hasAttribute("aria-busy")'),'Pagination did not update');
    assert.equal(await evaluate('document.querySelectorAll(".student-org-card").length'),1);
    assert.equal(await evaluate('window.orgFilterDocumentToken'),'same-document');
    await clickCategory(orgFixture.categories[2]);
    assert.equal(await evaluate('document.querySelectorAll(".student-org-card").length'),0);
    assert.equal(await evaluate('document.querySelector(".student-org-empty h3").textContent'),'No organizations in this category');
    await evaluate('history.back()');
    await until(()=>evaluate('new URL(location.href).searchParams.get("p")==="2"&&document.querySelectorAll(".student-org-card").length===1&&!document.querySelector(".student-org-directory").hasAttribute("aria-busy")'),'Back did not restore results');
    await evaluate('history.forward()');
    await until(()=>evaluate('Boolean(document.querySelector(".student-org-empty"))&&!document.querySelector(".student-org-directory").hasAttribute("aria-busy")'),'Forward did not restore results');
    assert.equal(await evaluate('window.orgFilterDocumentToken'),'same-document');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await clickCategory(orgFixture.categories[0]);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true);
    }
    await send('Page.navigate',{url:listing+'?category='+orgFixture.categories[0]});
    await until(()=>evaluate('document.readyState==="complete"&&document.querySelectorAll(".student-org-card").length===6'),'Direct filtered URL missing');
    assert.equal(await evaluate('document.documentElement.classList.contains("gnc-shared-entrance")'),false,'Direct filtered URLs skip opening wipe');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".student-org-hero")).animationName'),'none','Filtered page skips hero entrance');
    await send('Page.navigate',{url:listing+'?category='+orgFixture.categories[0]+'&p=2'});
    await until(()=>evaluate('document.readyState==="complete"&&document.querySelectorAll(".student-org-card").length===1'),'Direct pagination missing');
    assert.equal(await evaluate('document.querySelectorAll(".student-org-directory .js-reveal").length'),0,'Direct pagination does not restart card reveals');
    assert.equal(await evaluate('document.documentElement.classList.contains("gnc-shared-entrance")'),false,'Direct pagination skips page transition');
    console.log('PASS: categories and pagination update without reloading/entrance animations, empty results, Back/Forward, direct URLs, and mobile width.');
    const auth=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true}));
    session={name:auth.name,id:auth.value};
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=student-organizations'});
    const frame='document.getElementById("veFrame").contentDocument';
    await until(()=>evaluate('Boolean(window.VE)&&document.getElementById("veLoader").hidden'),'Admin editor unavailable');
    const route=orgFixture.url+'?category='+orgFixture.categories[0]+'&ce_preview=1';
    await evaluate(`VE.state.url=${JSON.stringify(route)};document.getElementById('veFrame').src=${JSON.stringify(base+route)}`);
    await until(()=>evaluate(`${frame}?.querySelectorAll('[data-ve-org-card]').length===6&&new URL(${frame}.URL).searchParams.get('category')===${JSON.stringify(String(orgFixture.categories[0]))}&&VE.state.doc===${frame}&&document.getElementById('veLoader').hidden&&!VE.state.busy`),'Admin category unavailable');
    await evaluate(`window.paginationLoaderShown=false;new MutationObserver(()=>{if(!document.getElementById('veLoader').hidden)window.paginationLoaderShown=true;}).observe(document.getElementById('veLoader'),{attributes:true});${frame}.defaultView.paginationToken='same-preview'`);
    async function adminPage(number){
        await evaluate(`Array.from(${frame}.querySelectorAll('.student-org-pagination a')).find(a=>a.textContent===${JSON.stringify(String(number))}).click()`);
        await until(()=>evaluate(`new URL(VE.state.url,location.origin).searchParams.get('p')===${JSON.stringify(String(number))}&&!VE.state.busy`),'Admin pagination did not update');
    }
    await adminPage(2);
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-org-card]').length`),1);
    assert.equal(await evaluate(`${frame}.defaultView.paginationToken`),'same-preview','Admin pagination keeps the same document');
    assert.equal(await evaluate(`${frame}.querySelectorAll('.ve-drag-handle').length`),1,'New cards retain editor controls');
    await adminPage(1);
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-org-card]').length`),6);
    assert.equal(await evaluate('window.paginationLoaderShown'),false,'Admin pagination does not activate the preview transition');
    assert.equal(await evaluate(`${frame}.defaultView.paginationToken`),'same-preview');
    await evaluate(`window.AdminDialogs.confirm=async()=>false;const field=${frame}.querySelector('[data-ve-key="name"]');field.textContent='Unsaved pagination guard';field.dispatchEvent(new Event('input',{bubbles:true}));Array.from(${frame}.querySelectorAll('.student-org-pagination a')).find(a=>a.textContent==='2').click()`);
    await delay(250);
    assert.equal(await evaluate('new URL(VE.state.url,location.origin).searchParams.get("p")'),'1','Canceling navigation preserves unsaved edits');
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-key="name"]').textContent`),'Unsaved pagination guard');
    console.log('PASS: admin pagination keeps its document, skips the loading transition, rebinds editing/drag controls, and protects unsaved changes.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(orgFixture)try{fixture('cleanup',orgFixture.prefix);}catch(error){console.error(error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-org-filters-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
