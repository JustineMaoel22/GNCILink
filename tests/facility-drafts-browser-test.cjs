const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=os.tmpdir();
const draftMarker='facility-drafts-regression-'+Date.now();
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-facility-drafts-'));
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
    let allowPublish=false,failPublish=false;const live=[];
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;const body=packet.params.request.postData||'';live.push({url:packet.params.request.url,body,allowed:allowPublish});const match=/name="items"\r?\n\r?\n([^\r\n]+)/.exec(body);const response=!allowPublish||failPublish?{success:false,error:'Fixture rejected publication'}:match?{success:true,items:JSON.parse(match[1]).map((row,i)=>({...row,item_id:row.item_id||900000+i,image_url:'/assets/images/gnc-front-II.jpg'}))}:{success:true};await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(response)).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*program-page-handler.php*'},{urlPattern:'*image-framing-handler.php*'}]});
    async function facilityTab(){await evaluate('Array.from(document.querySelectorAll("#veDock button")).find(b=>b.textContent.includes("Facility slides")).click()');}
    async function open(i=0){await evaluate(`document.querySelectorAll('#veDock .ve-thumb')[${i}].click()`);}
    async function saved(){await until(()=>evaluate('Boolean(!VE.state.busy&&document.getElementById("veLoader").hidden&&document.getElementById("veStatus").textContent.includes("Draft saved"))'),'Facility draft not saved');}
    async function choose(name){await evaluate(`(()=>{const input=document.getElementById('veFile');const canvas=document.createElement('canvas');canvas.width=160;canvas.height=100;canvas.getContext('2d').fillRect(0,0,160,100);const bytes=Uint8Array.from(atob(canvas.toDataURL('image/png').split(',')[1]),c=>c.charCodeAt(0));const dt=new DataTransfer();dt.items.add(new File([bytes],${JSON.stringify(name)},{type:'image/png'}));input.files=dt.files;input.onchange();})()`);await saved();}
    await facilityTab();
    const original=await evaluate('JSON.parse(VE.state.doc.getElementById("veFacData").textContent).slides');
    await evaluate('document.getElementById("veFile").click=()=>{};document.querySelector("#veDock .ve-add-slide").click()');
    await choose('Private facility one.png');
    assert.equal(await evaluate('VE.state.doc.querySelectorAll(".fcar-slide").length'),original.length+1,'Upload appears immediately');
    await evaluate('document.querySelector("#veDock .ve-add-slide").click()');await choose('Private facility two.png');
    assert.equal(await evaluate('VE.adapter().draftState().pRows.facility.length'),original.length+2);
    await open(0);await evaluate('window.AdminDialogs.confirm=async()=>true;[...document.querySelectorAll("#vePop button")].find(b=>b.textContent.trim()==="Delete").click()');await saved();
    assert.equal(await evaluate('VE.state.doc.querySelectorAll(".fcar-slide").length'),original.length+1,'Deletion removes only editor slide');
    await open(0);await evaluate('document.querySelector("#vePop input").value="Private renamed facility";document.querySelector("#vePop input").dispatchEvent(new KeyboardEvent("keydown",{key:"Enter",bubbles:true,cancelable:true}))');await saved();
    await open(0);await evaluate('[...document.querySelectorAll("#vePop button")].find(b=>b.textContent==="Reposition image").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Reposition dialog missing');
    await evaluate('document.querySelector(".image-frame-controls input").value=1.5;document.querySelector(".image-frame-controls input").dispatchEvent(new Event("input"));document.querySelector(".image-frame-save").click()');await saved();
    assert.equal(live.length,0,'Add, delete, rename and reposition never reach live handlers');
    assert.equal(await evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),false,'Editing does not request Publish');
    assert.equal(await(await fetch(publicUrl)).text(),before,'Public page stays unchanged');
    await send('Page.reload');await until(()=>evaluate('Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById("veLoader").hidden&&VE.adapter().draftState().pRows.facility?.length)'), 'Facility draft not restored');
    await until(()=>evaluate('Array.from(document.querySelectorAll("#veDock button")).some(b=>b.textContent.includes("Facility slides"))'),'Strip not restored');await facilityTab();
    assert.equal(await evaluate('VE.adapter().draftState().pRows.facility[0].title'),'Private renamed facility');
    assert.equal(await evaluate('VE.state.doc.querySelectorAll(".fcar-slide").length'),original.length+1);
    assert.equal(await evaluate('VE.adapter().draftState().pRows.facility.slice(-2).every(row=>row.file instanceof File&&row.image_url.startsWith("blob:"))'),true,'New files and previews survive refresh');
    await evaluate('VE.adapter().draftState().pRows.facility.slice(-2).forEach(row=>{if(!row.file.size)throw Error("Draft file bytes missing")})');
    // Replacing an unpublished upload must remain private and preserve its identity.
    await open(original.length-1);await evaluate('document.getElementById("veFile").click=()=>{};[...document.querySelectorAll("#vePop button")].find(b=>b.textContent==="Replace slide").click()');await choose('Replacement private.png');
    assert.equal(await evaluate(`VE.adapter().draftState().pRows.facility[${original.length-1}].file.name`),'Replacement private.png');
    await evaluate(`void VE.state.doc.defaultView.GNCImageFraming.edit(VE.state.doc.querySelectorAll('.fcar-slide img')[${original.length-1}])`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Uploaded reposition missing');
    await evaluate('document.querySelector(".image-frame-controls input").value=2;document.querySelector(".image-frame-controls input").dispatchEvent(new Event("input"));document.querySelector(".image-frame-save").click()');await saved();
    assert.equal(await evaluate(`VE.adapter().draftState().pRows.facility[${original.length-1}].framing.zoom`),2,'Uploaded image position is retained privately');
    await send('Page.reload');await until(()=>evaluate('Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById("veLoader").hidden&&VE.adapter().draftState().pRows.facility?.length)'),'Positioned upload draft not restored');
    assert.equal(await evaluate(`VE.adapter().draftState().pRows.facility[${original.length-1}].framing.zoom`),2,'Uploaded framing survives refresh');
    assert.equal(live.length,0);
    allowPublish=true;failPublish=true;
    async function publish(){await evaluate('window.ContentCompleteness=null;document.getElementById("vePrimary").click()');await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Explicit confirmation missing');await evaluate('document.querySelector(".ve-publish-accept").click()');}
    await publish();await until(async()=>live.length>0&&await evaluate('!VE.state.busy&&document.getElementById("ceToasts").textContent.includes("Fixture rejected publication")'),'Failed publish still busy');
    assert.equal(await evaluate('Boolean(VE.adapter().draftState().pRows.facility)'),true,'Failure retains all draft files and removals');
    failPublish=false;await publish();await until(()=>evaluate('Boolean(window.VE&&!VE.state.busy&&document.getElementById("veLoader").hidden&&document.getElementById("veDiscardDraft").hidden)'),'Publication did not finish');
    const request=live.filter(r=>r.body.includes('save_program_items')).at(-1),posted=JSON.parse(/name="items"\r?\n\r?\n([^\r\n]+)/.exec(request.body)[1]);
    assert.equal(posted.length,original.length+1);assert(!posted.some(row=>row.item_id===original[0].item_id),'Deleted ID omitted only at explicit publication');
    assert.equal(posted[0].title,'Private renamed facility');assert(request.body.includes('Replacement private.png'),'New replacement bytes reach publication');
    assert(request.body.includes('name="image_framing"'),'Uploaded repositioning reaches publication with the file');
    assert(live.every(request=>request.allowed),'All live requests follow explicit Publish');
    const publishedRequestCount=live.length;
    await until(()=>evaluate('Array.from(document.querySelectorAll("#veDock button")).some(b=>b.textContent.includes("Facility slides"))'),'Published strip missing');await facilityTab();
    const count=await evaluate('VE.state.doc.querySelectorAll(".fcar-slide").length');
    for(let remaining=count;remaining>0;remaining--){
        await open(0);await evaluate('window.AdminDialogs.confirm=async()=>true;[...document.querySelectorAll("#vePop button")].find(b=>b.textContent.trim()==="Delete").click()');
        await until(()=>evaluate(`!VE.state.busy&&VE.state.doc.querySelectorAll('.fcar-slide').length===${remaining-1}`),'Remove-all draft failed');
    }
    assert.equal(await evaluate('Boolean(VE.state.doc.querySelector(".ve-fac-empty"))'),true,'An empty draft remains editable');
    await evaluate('document.getElementById("veFile").click=()=>{};document.querySelector("#veDock .ve-add-slide").click()');await choose('First photo after empty.png');
    assert.equal(await evaluate('VE.state.doc.querySelectorAll(".fcar-slide").length'),1,'Adding to an empty draft rebuilds the carousel');
    assert.equal(live.length,publishedRequestCount,'Removing all and adding again never publishes');
    console.log('PASS: facility upload/delete/rename/reposition/replacement remain private, autosave and restore files on refresh; explicit Publish confirms, failure retains draft, and retry sends correct IDs/order/files. Live writes intercepted.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{if(cleanupDraft)try{await cleanupDraft();}catch(error){console.error(error);process.exitCode=1;}spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-facility-drafts-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}});
