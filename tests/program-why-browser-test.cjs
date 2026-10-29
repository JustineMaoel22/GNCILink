const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=os.tmpdir();
const draftMarker='why-regression-'+Date.now();
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-program-why-'));
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
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=beed'});
    await until(()=>evaluate("Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.state.doc.querySelector('[data-ve-type=why]'))"),'Program why editor unavailable');
    const original=await evaluate('JSON.parse(VE.state.doc.getElementById("veProgData").textContent).items.why');
    const publicUrl=await evaluate('(()=>{const u=new URL(VE.state.url,location.origin);u.searchParams.delete("ce_preview");u.searchParams.delete("draft_test");return u.href;})()');
    const draftInfo=await evaluate(`(()=>{const u=new URL(VE.state.url,location.origin);['ce_preview','_','_ve','add_org'].forEach(k=>u.searchParams.delete(k));u.searchParams.sort();return {route:u.pathname+u.search,csrf:VE_CONFIG.csrf};})()`);
    cleanupDraft=async()=>{
        const fields={page:'programs',route:draftInfo.route,csrf_token:draftInfo.csrf};
        const post=async extra=>{const response=await fetch(base+'/admin/action/editor-draft-handler.php',{method:'POST',headers:{Cookie:adminSession.name+'='+adminSession.value},body:new URLSearchParams(Object.assign({},fields,extra))});const result=await response.json();if(!response.ok)throw Error(result.error);return result;};
        const loaded=await post({action:'load'});if(loaded.draft)await post({action:'delete',revision:loaded.draft.revision});
    };
    const before=await(await fetch(publicUrl)).text();
    await evaluate(`window.whyParentToken=true;VE.state.win.whyFrameToken=true;const field=VE.state.doc.querySelector('[data-ve-field="prog-text"]');window.otherReasonField=field.getAttribute('data-ve-key');field.textContent='Unrelated why regression edit';field.dispatchEvent(new Event('input',{bubbles:true}));VE.state.win.scrollTo({top:260,behavior:'instant'});`);
    const scroll=await evaluate('VE.state.win.scrollY');
    async function add(){await evaluate('VE.state.doc.querySelector(\'[data-ve-add="item"][data-ve-type="why"]\').click()');}
    await add();
    assert.equal(await evaluate(`VE.state.doc.querySelectorAll('[data-ve-item^="why:"]').length`),original.length+1);
    assert.equal(await evaluate('window.whyParentToken&&VE.state.win.whyFrameToken'),true,'Adding a why retains both documents');
    assert.equal(await evaluate('VE.state.win.scrollY'),scroll,'Adding keeps the current scroll position');
    assert.equal(await evaluate('VE.adapter().draftState().pChange[window.otherReasonField]'),'Unrelated why regression edit','Adding preserves other edits');
    async function edit(index,prop,value){await evaluate(`(()=>{const e=VE.state.doc.querySelector('[data-ve-key="why:${index}:${prop}"]');e.textContent=${JSON.stringify(value)};e.dispatchEvent(new Event('input',{bubbles:true}));})()`);}
    await edit(original.length,'title','Draft why title');await edit(original.length,'body','Draft why body');
    assert.equal(await evaluate(`VE.state.doc.querySelector('[data-ve-key="why:${original.length}:body"]').hasAttribute('data-ve-rich')`),true,'New why descriptions remain rich-text editable');
    await add();
    await evaluate(`window.AdminDialogs.confirm=async()=>true;VE.state.doc.querySelector('[data-ve-key="why:${original.length+1}:title"]').click()`);
    await evaluate('Array.from(document.querySelectorAll("#veTb button")).find(b=>b.title==="Delete this card").click()');
    await until(()=>evaluate(`VE.state.doc.querySelectorAll('[data-ve-item^="why:"]').length===${original.length+1}`),'Pending why deletion failed');
    await evaluate(`VE.state.doc.querySelector('[data-ve-key="why:${original.length}:title"]').click()`);
    await evaluate('Array.from(document.querySelectorAll("#veTb button")).find(b=>b.title==="Move earlier").click()');
    assert.equal(await evaluate(`VE.adapter().draftState().pRows.why[${original.length-1}].title`),'Draft why title','Reason movement updates the draft');
    await edit(original.length-1,'title','Moved draft why');
    assert.equal(await evaluate(`VE.adapter().draftState().pRows.why[${original.length-1}].title`),'Moved draft why','Moved why retains correct field bindings');
    assert.equal(writes.length,0,'Adding, moving and deleting whys do not publish');
    assert.equal(await(await fetch(publicUrl)).text(),before,'Public page stays unchanged');
    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('!VE.state.busy&&document.getElementById("ceToasts").textContent.includes("Draft saved")'),'Reason draft not saved');
    await evaluate('document.getElementById("veFrame").src=document.getElementById("veFrame").src');
    await until(()=>evaluate(`Boolean(window.VE&&VE.state.doc===document.getElementById('veFrame').contentDocument&&!VE.state.win.whyFrameToken&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.adapter().draftState().pRows.why?.length===${original.length+1})`),'Reason draft not restored');
    assert.equal(await evaluate(`VE.state.doc.querySelector('[data-ve-key="why:${original.length-1}:title"]').textContent`),'Moved draft why');
    assert.equal(await evaluate(`VE.state.doc.querySelector('[data-ve-key="why:${original.length-1}:body"]').textContent`),'Draft why body');
    assert.equal(await evaluate('VE.adapter().draftState().pChange[window.otherReasonField]'),'Unrelated why regression edit');
    const deletedReasonId=await evaluate(`VE.adapter().draftState().pRows.why[${original.length}].item_id`);
    assert(deletedReasonId>0,'Exercise deletion of an existing saved why');
    await evaluate(`window.whyDeleteToken=true;VE.state.win.whyDeleteToken=true;window.AdminDialogs.confirm=async()=>true;VE.state.doc.querySelector('[data-ve-key="why:${original.length}:title"]').click()`);
    await evaluate('Array.from(document.querySelectorAll("#veTb button")).find(b=>b.title==="Delete this card").click()');
    await until(()=>evaluate(`VE.state.doc.querySelectorAll('[data-ve-item^="why:"]').length===${original.length}`),'Saved why deletion failed');
    assert.equal(await evaluate('window.whyDeleteToken&&VE.state.win.whyDeleteToken'),true,'Deleting a saved why retains both documents');
    assert.equal(await(await fetch(publicUrl)).text(),before,'Deleting a saved why stays private until Publish');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await delay(350);
        assert.equal(await evaluate(`(()=>{const group=VE.state.doc.querySelector('.why-list'),r=group.getBoundingClientRect();return Array.from(group.querySelectorAll('.why-item')).every(card=>{const b=card.getBoundingClientRect();return b.left>=r.left-1&&b.right<=r.right+1;});})()`),true,'Reasons fit their container at '+width+'px');
    }
    let failReasons = true;
    socket.addEventListener('message',async event=>{
        const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;
        const request=packet.params.request,body=request.postData||'',match=/name="items"\r?\n\r?\n([^\r\n]+)/.exec(body);
        const response=match&&failReasons?{success:false,error:'Reason save failed; please retry.'}:match?{success:true,items:JSON.parse(match[1]).map((row,index)=>Object.assign({},row,{item_id:row.item_id||900000+index}))}:{success:true,program:{}};
        await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(response)).toString('base64')});
    });
    await send('Fetch.enable',{patterns:[{urlPattern:'*program-page-handler.php*'}]});
    await evaluate('window.reasonPublishToken=true;VE.state.win.reasonPublishToken=true');
    await evaluate('window.ContentCompleteness=null;document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Publish confirmation missing');
    await evaluate('document.querySelector(".ve-publish-accept").click()');
    await until(()=>evaluate('!VE.state.busy&&document.getElementById("ceToasts").textContent.includes("Reason save failed")'),'Failed save message missing');
    assert.equal(await evaluate('window.reasonPublishToken&&VE.state.win.reasonPublishToken'),true,'Failed save retains both documents');
    assert.equal(await evaluate('Boolean(VE.adapter().draftState().pRows.why)'),true,'Failed saves keep all reasons for retry');
    failReasons = false;
    await evaluate('document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Retry confirmation missing');
    await evaluate('document.querySelector(".ve-publish-accept").click()');
    await until(()=>evaluate('Boolean(window.VE&&!VE.state.busy&&document.getElementById("veLoader").hidden&&document.getElementById("veDiscardDraft").hidden)'),'Publish did not finish');
    assert.equal(await evaluate('window.reasonPublishToken&&VE.state.win.reasonPublishToken'),true,'Successful publication retains both documents');
    assert.equal(await evaluate('VE.adapter().dirtyCount()'),0,'Successful save accepts the server baseline');
    const posted=writes.find(body=>body.includes('save_program_items'));
    assert(posted&&posted.includes('Moved draft why')&&posted.includes('Draft why body'),'Publish includes edited why data');
    const publishedRows=JSON.parse(/name="items"\r?\n\r?\n([^\r\n]+)/.exec(posted)[1]);
    assert(!publishedRows.some(row=>row.item_id===deletedReasonId),'Publish omits the deleted saved why');
    assert(writes.some(body=>body.includes('save_program_details')),'Publish retains unrelated text edits');
    await add();
    assert.equal(await evaluate('window.reasonPublishToken&&VE.state.win.reasonPublishToken'),true,'Adding after publication does not reload');
    assert.equal(await evaluate(`VE.state.doc.querySelector('[data-ve-key="why:${original.length-1}:body"]').textContent`),'Draft why body','Published reasons survive another addition');
    console.log('PASS: reasons add/edit/move/delete in place, preserve other edits, restore drafts, fit desktop/mobile, retain failed saves for retry, and publish without navigation. Live program writes intercepted.');

})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(cleanupDraft)try{await cleanupDraft();}catch(error){console.error('Draft cleanup failed:',error.message);process.exitCode=1;}
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-program-why-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
