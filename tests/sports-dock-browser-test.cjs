const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=os.tmpdir();
const draftMarker='sports-dock-regression-'+Date.now();
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-sports-dock-'));
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
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.method==='Network.requestWillBeSent'&&p.params.request.method==='POST'&&p.params.request.url.includes('athletics-sports-handler.php'))writes.push(p.params.request.postData||'');});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=athletics-sports'});
    await until(()=>evaluate('Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById("veLoader").hidden&&document.querySelectorAll("#veDock [role=tab]").length===3)'),'Sports tray unavailable');
    const publicUrl=await evaluate('(()=>{const u=new URL(VE.state.url,location.origin);["ce_preview","draft_test"].forEach(k=>u.searchParams.delete(k));return u.href;})()');
    const before=await(await fetch(publicUrl)).text();
    const draftInfo=await evaluate(`(()=>{const u=new URL(VE.state.url,location.origin);['ce_preview','_','_ve'].forEach(k=>u.searchParams.delete(k));u.searchParams.sort();return {route:u.pathname+u.search,csrf:VE_CONFIG.csrf};})()`);
    cleanupDraft=async()=>{const fields={page:'athletics-sports',route:draftInfo.route,csrf_token:draftInfo.csrf};const post=async extra=>{const response=await fetch(base+'/admin/action/editor-draft-handler.php',{method:'POST',headers:{Cookie:adminSession.name+'='+adminSession.value},body:new URLSearchParams(Object.assign({},fields,extra))});if(!response.ok)throw Error('Draft cleanup failed');return response.json();};const result=await post({action:'load'});if(result.draft)await post({action:'delete',revision:result.draft.revision});};
    const initial=await evaluate('VE.adapter().draftState().rows.length');
    await evaluate('window.dockParentToken=true;VE.state.win.dockFrameToken=true;document.querySelector("#veDock .ve-add-slide").click()');
    await until(()=>evaluate(`document.querySelectorAll('#veDock .ve-thumb').length===${initial+1}`),'Sport tray count did not update');
    const id=await evaluate('VE.adapter().draftState().rows.at(-1).id');
    await evaluate(`document.querySelector('[data-sports-dock="${id}"]').click()`);
    await evaluate('document.querySelector("#vePop input").value="Tray draft sport";Array.from(document.querySelectorAll("#vePop button")).find(b=>b.textContent==="Use name").click()');
    assert.equal(await evaluate('VE.adapter().draftState().rows.at(-1).name'),'Tray draft sport');
    assert.equal(await evaluate('window.dockParentToken&&VE.state.win.dockFrameToken'),true,'Tray edits retain both documents');
    assert.equal(await evaluate(`document.querySelector('[data-sports-dock="${id}"] .ve-thumb-t').textContent`),'Tray draft sport');
    const selectTab=key=>evaluate(`document.querySelector('[data-sports-dock="${key}"]').click()`);
    for(const [key,stateKey] of [['teams','teams'],['facilities','facilities'],['sports','rows']]){
        await selectTab(key);
        assert.equal(await evaluate('document.querySelectorAll("#veDock .ve-thumb").length'),await evaluate(`VE.adapter().draftState().${stateKey}.length`),'Tray reflects '+key+' collection');
    }
    await evaluate(`document.querySelector('[data-sports-dock="${id}"]').click();Array.from(document.querySelectorAll('#vePop button')).find(b=>b.title==='Move earlier').click()`);
    assert.equal(await evaluate(`VE.adapter().draftState().rows[${initial-1}].id`),id,'Move control changes the existing draft order');
    assert.equal(writes.length,0,'Tray edits do not publish');
    assert.equal(await(await fetch(publicUrl)).text(),before,'Public sports page stays unchanged');
    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('!VE.state.busy&&document.getElementById("ceToasts").textContent.includes("Draft saved")'),'Sports draft not saved');
    await evaluate('document.getElementById("veFrame").src=document.getElementById("veFrame").src');
    await until(()=>evaluate(`Boolean(VE.state.doc===document.getElementById('veFrame').contentDocument&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.adapter().draftState().rows.some(row=>row.id==='${id}'))`),'Sports draft not restored');
    assert.equal(await evaluate(`document.querySelector('[data-sports-dock="${id}"] .ve-thumb-t').textContent`),'Tray draft sport','Tray restores saved draft names');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(200);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true,'Editor fits at '+width+'px');
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.captureScreenshot',{format:'png'}).then(result=>fs.writeFileSync(path.join(auditDirectory,'gnci-sports-dock.png'),Buffer.from(result.data,'base64')));
    await evaluate('VE.state.preview=true;VE.adapter().renderDock()');
    assert.equal(await evaluate('document.getElementById("veDock").hidden'),true,'Tray hides in Preview');
    console.log('PASS: sports tray tabs/counts, add, rename, move, no page reload, private drafts/restoration, desktop/mobile layout and Preview. No published content writes. Screenshot: '+path.join(auditDirectory,'gnci-sports-dock.png'));
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(cleanupDraft)try{await cleanupDraft();}catch(error){console.error('Draft cleanup failed:',error.message);process.exitCode=1;}
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-sports-dock-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
