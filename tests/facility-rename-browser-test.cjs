const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=os.tmpdir();
const draftMarker='facility-rename-regression-'+Date.now();
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-facility-rename-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,cleanupDraft;
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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test',${JSON.stringify(draftMarker)});value.page.url=u.pathname+u.search;}}});})();`});


    const writes=[];
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Network.requestWillBeSent'&&packet.params.request.method==='POST'&&packet.params.request.url.includes('program-page-handler.php'))writes.push(packet.params.request.postData||'');});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=bael'});
    await until(()=>evaluate("Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.state.doc.getElementById('veFacData'))"),'Facility editor unavailable');
    const publicUrl=await evaluate('(()=>{const u=new URL(VE.state.url,location.origin);u.searchParams.delete("ce_preview");u.searchParams.delete("draft_test");return u.href;})()');
    const draftInfo=await evaluate(`(()=>{const u=new URL(VE.state.url,location.origin);['ce_preview','_','_ve','add_org'].forEach(k=>u.searchParams.delete(k));u.searchParams.sort();return {route:u.pathname+u.search,csrf:VE_CONFIG.csrf};})()`);
    cleanupDraft=async()=>{
        const fields={page:'programs',route:draftInfo.route,csrf_token:draftInfo.csrf};
        const post=async extra=>{const response=await fetch(base+'/admin/action/editor-draft-handler.php',{method:'POST',headers:{Cookie:adminSession.name+'='+adminSession.value},body:new URLSearchParams(Object.assign({},fields,extra))});const result=await response.json();if(!response.ok)throw Error(result.error);return result;};
        const loaded=await post({action:'load'});if(loaded.draft)await post({action:'delete',revision:loaded.draft.revision});
    };
    const before=await(await fetch(publicUrl)).text();
    await evaluate('Array.from(document.querySelectorAll("#veDock button")).find(b=>b.textContent.includes("Facility slides")).click();window.renameToken=true;VE.state.win.renameToken=true');
    const originalRows=await evaluate('JSON.parse(VE.state.doc.getElementById("veFacData").textContent).slides');
    async function open(index=0){await evaluate(`document.querySelectorAll('#veDock .ve-thumb')[${index}].click()`);await until(()=>evaluate('!document.getElementById("vePop").hidden'),'Popup missing');}
    async function type(value){await evaluate(`(()=>{const input=document.querySelector('#vePop input');input.value=${JSON.stringify(value)};input.focus();})()`);}
    async function key(name,code){await send('Input.dispatchKeyEvent',{type:'keyDown',key:name,code:name,windowsVirtualKeyCode:code});await send('Input.dispatchKeyEvent',{type:'keyUp',key:name,code:name,windowsVirtualKeyCode:code});}
    await open();await type('Cancelled rename');await key('Escape',27);
    assert.equal(await evaluate('document.getElementById("vePop").hidden'),true);
    assert.equal(await evaluate('Object.keys(VE.adapter().draftState().slideTextEdits).length'),0,'Escape does not stage changes');
    await open();assert.equal(await evaluate('document.querySelector("#vePop input").value'),originalRows[0].title);
    await type('');await key('Enter',13);
    assert.equal(await evaluate('document.getElementById("vePop").hidden'),false,'Blank names stay open for correction');
    await type('Private facility rename');await key('Enter',13);
    await until(()=>evaluate('!VE.state.busy&&document.getElementById("vePop").hidden&&document.getElementById("veStatus").textContent.includes("Draft saved")'),'Enter did not save a private draft');
    assert.equal(await evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),false,'Enter never opens Publish');
    assert.equal(writes.length,0,'Enter never calls live program handler');
    assert.equal(await evaluate('window.renameToken&&VE.state.win.renameToken'),true,'Enter does not reload either document');
    assert.equal(await(await fetch(publicUrl)).text(),before,'Draft rename stays private');
    await send('Page.reload');
    await until(()=>evaluate('Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById("veLoader").hidden&&VE.adapter().draftState().pRows.facility)'),'Rename draft did not restore');
    await until(()=>evaluate('Array.from(document.querySelectorAll("#veDock button")).some(b=>b.textContent.includes("Facility slides"))'),'Restored facility strip missing');
    await evaluate('Array.from(document.querySelectorAll("#veDock button")).find(b=>b.textContent.includes("Facility slides")).click()');
    await open();assert.equal(await evaluate('document.querySelector("#vePop input").value'),'Private facility rename','Name survives full page refresh');
    await type('Cancelled second edit');await key('Escape',27);await open();
    assert.equal(await evaluate('document.querySelector("#vePop input").value'),'Private facility rename','Escape keeps the previously saved draft name');
    await type('Final facility name');
    let reject=true;
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;const body=packet.params.request.postData||'';const response=reject?{success:false,error:'Rename test failure'}:{success:true,items:originalRows.map((row,index)=>Object.assign({},row,index===0?{title:'Final facility name'}:{}))};await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(response)).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*program-page-handler.php*'}]});
    await evaluate('window.ContentCompleteness=null;Array.from(document.querySelectorAll("#vePop button")).find(b=>b.textContent==="Publish").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Explicit Publish did not request confirmation');
    assert.equal(writes.length,0,'Confirmation must precede live save');
    await evaluate('document.querySelector(".ve-publish-accept").click()');
    await until(()=>evaluate('!VE.state.busy&&document.getElementById("ceToasts").textContent.includes("Rename test failure")'),'Failure not reported');
    assert.equal(await evaluate('VE.adapter().draftState().pRows.facility[0].title'),'Final facility name','Failed publish retains rename for retry');
    reject=false;
    await evaluate('document.getElementById("vePrimary").click()');await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Retry confirmation missing');
    await evaluate('document.querySelector(".ve-publish-accept").click()');
    await until(()=>evaluate('Boolean(window.VE&&!VE.state.busy&&document.getElementById("veLoader").hidden&&document.getElementById("veDiscardDraft").hidden)'),'Publish did not finish');
    const posted=JSON.parse(/name="items"\r?\n\r?\n([^\r\n]+)/.exec(writes.at(-1))[1]);
    assert.equal(posted[0].title,'Final facility name');
    assert.deepEqual(posted.map(row=>row.item_id),originalRows.map(row=>row.item_id),'IDs and order stay intact');
    assert.deepEqual(posted.slice(1).map(row=>row.title),originalRows.slice(1).map(row=>row.title),'Other names stay intact');
    assert.equal(await(await fetch(publicUrl)).text(),before,'Live program writes intercepted');
    console.log('PASS: Enter saves a private rename draft without Publish or reload; Escape and blank-name validation work; refresh restores the draft; explicit Publish confirms and preserves IDs/order with failure retry. Live writes intercepted.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(cleanupDraft)try{await cleanupDraft();}catch(error){console.error(error);process.exitCode=1;}
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-facility-rename-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
