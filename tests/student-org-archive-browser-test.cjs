const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-org-archive-'));
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



    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Page.javascriptDialogOpening'){console.log('Browser dialog:',packet.params.type,packet.params.message);send('Page.handleJavaScriptDialog',{accept:true});}});

    orgFixture=JSON.parse(fixture('seed'));
    const auth=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true}));
    session={name:auth.name,id:auth.value};
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const frame='document.getElementById("veFrame").contentDocument',id=orgFixture.records[0].org_id;
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=student-organizations'});
    await until(()=>evaluate('Boolean(window.VE)&&document.getElementById("veLoader").hidden&&!VE.state.busy'),'Editor unavailable');
    const route=orgFixture.url+'?category='+orgFixture.categories[0]+'&ce_preview=1';
    await evaluate(`VE.state.url=${JSON.stringify(route)};document.getElementById('veFrame').src=${JSON.stringify(base+route)}`);
    await until(()=>evaluate(`VE.state.doc===${frame}&&new URL(${frame}.URL).searchParams.get('category')===${JSON.stringify(String(orgFixture.categories[0]))}&&document.getElementById('veLoader').hidden&&!VE.state.busy`),'Fixture directory unavailable');
    async function selectCard(){await evaluate(`${frame}.querySelector('[data-ve-org-card="${id}"] h3').click()`);}
    async function action(label){
        await selectCard();
        await until(()=>evaluate(`Array.from(document.querySelectorAll('#veTb button')).some(b=>b.getAttribute('aria-label')===${JSON.stringify(label)})`),'Toolbar action missing: '+label);
        await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')===${JSON.stringify(label)}).click()`);
    }
    const publicDetail=()=>fetch(base+orgFixture.detail);
    assert.equal((await publicDetail()).status,200);
    await action('Archive organization');
    assert.equal(await evaluate(`VE.adapter().draftState().records[${id}].changes.archived`),'1');
    assert.equal((await publicDetail()).status,200,'Archive is private before Publish');
    await action('Restore organization');
    assert.equal(await evaluate('VE.adapter().dirtyCount()'),0,'Restore cancels a pending archive');
    await action('Archive organization');
    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('!VE.state.busy&&document.getElementById("ceToasts").textContent.includes("Draft saved")'),'Archive draft not saved');
    await evaluate(`${frame}.defaultView.archiveReloadToken=true;document.getElementById('veFrame').src=document.getElementById('veFrame').src`);
    await until(()=>evaluate(`!${frame}?.defaultView.archiveReloadToken&&VE.state.doc===${frame}&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.adapter().draftState().records[${id}]?.changes.archived==='1'`),'Archive draft not restored');
    assert.equal((await publicDetail()).status,200,'Saved archive draft remains private');
    async function publish(){
        await evaluate('window.ContentCompleteness=null;document.getElementById("vePrimary").click()');
        await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Publish dialog missing');
        await evaluate('document.querySelector(".ve-publish-accept").click()');
        await until(()=>evaluate('Boolean(window.VE)&&!VE.state.busy&&document.getElementById("veLoader").hidden&&VE.adapter().dirtyCount()===0'),'Publish not completed');
    }
    await publish();
    await until(async()=>(await publicDetail()).status===404,'Archived detail still public');
    const listing=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0]+'&archived=1&ce_preview=1')).text();
    assert(!listing.includes(orgFixture.records[0].name),'Public query parameters cannot expose archived organizations');
    const noAuth=await fetch(base+'/admin/action/student-organizations-handler.php',{method:'POST',body:new URLSearchParams({action:'save',org_id:id,archived:'0'})});
    assert.equal(noAuth.status,403,'Anonymous restoration is rejected');
    const noCsrf=await evaluate(`(async()=>{const response=await fetch('/admin/action/student-organizations-handler.php',{method:'POST',body:new URLSearchParams({action:'save',org_id:${id},archived:'0'})});return response.status;})()`);
    assert.equal(noCsrf,403,'Authenticated restoration requires CSRF');
    await until(()=>evaluate(`Boolean(${frame}.querySelector('[data-ve-org-archive-view]'))&&!VE.state.busy`),'Archive view link unavailable');
    await evaluate(`${frame}.querySelector('[data-ve-org-archive-view]').click()`);
    await until(()=>evaluate(`new URL(VE.state.url,location.origin).searchParams.get('archived')==='1'&&!VE.state.busy&&Boolean(${frame}.querySelector('[data-ve-org-card="${id}"]'))`),'Archived organization not listed');
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-org-card="${id}"] .student-org-draft').textContent`),'Archived');
    await action('Restore organization');
    assert.equal((await publicDetail()).status,404,'Restoration stays private before Publish');
    await publish();
    await until(async()=>(await publicDetail()).status===200,'Restored detail not public');
    console.log('PASS: Archive/Restore toolbar actions, canceling a pending archive, private draft persistence, explicit publishing, Archived view, preserved public URL, and authentication/CSRF checks.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(orgFixture)try{fixture('cleanup',orgFixture.prefix);}catch(error){console.error(error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-org-archive-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
