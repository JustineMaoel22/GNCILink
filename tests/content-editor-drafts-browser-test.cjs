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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','browser-regression');value.page.url=u.pathname+u.search;}}});})();`});
    const pages=process.env.DRAFT_TEST_PUBLISH_ONLY?[]:['home','history','administration','programs&sub=bael','college-departments&sub=cased','basic-education','student-organizations','student-achievements','athletics-sports','tuition-fees','online-payment-guide','contact-us','vision-mission','enrollment-procedures','basic-education-admission-requirements','college-admission-requirements','graduate-school','basic-education&sub=jhs-regular'];
    for(const page of pages){
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
        await until(()=>evaluate("Boolean(window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veLoader').hidden && VE_CONFIG.page.slug==="+JSON.stringify(page.split('&')[0])+" && !document.getElementById('veSaveDraft').hidden)"),'Editor not ready: '+page);
        if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){await evaluate("window.draftReloadFlag=true;window.draftReloadFlag=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");await until(()=>evaluate("Boolean(!window.draftReloadFlag&&window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById('veDiscardDraft').hidden)"),'Old test draft not cleaned');}
        const marker='Draft test '+page.split('&')[0];
        const field=await evaluate(`(()=>{const el=[...VE.state.doc.querySelectorAll('[data-ve-field],[data-ve-section]')].find(el=>el.__ve && el.getAttribute('contenteditable')!=='false');if(!el)return null;window.draftTestField=el;return {text:el.textContent,field:el.getAttribute('data-ve-field'),key:el.getAttribute('data-ve-key'),section:el.getAttribute('data-ve-section')};})()`);
        assert.ok(field,'Editable field exists: '+page);
        await evaluate(`(()=>{const el=window.draftTestField;el.textContent=`+JSON.stringify(marker)+`;el.dispatchEvent(new Event('input',{bubbles:true}));el.__ve.onChange(el,`+JSON.stringify(marker)+`);})()`);
        if(page==='home')await evaluate(`(()=>{const el=VE.state.doc.querySelector('[data-ve-img="home-background"][data-ve-key="news_background"]');const action=VE.adapter().toolbar(VE.adapter().pick(el)).find(a=>a.label==='Change background image');const input=document.getElementById('veFile');input.click=()=>{};action.run();const dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='),c=>c.charCodeAt(0))],'draft.png',{type:'image/png'}));input.files=dt.files;input.onchange();})()`);
        if(page==='athletics-sports')await evaluate("VE.state.doc.querySelector('[data-ve-sport-add]').click()");
        if(page==='student-achievements')await evaluate("VE.state.doc.querySelector('[data-ve-achievement-add]').click()");
        if(page==='student-organizations')await evaluate("VE.adapter().runSecondary()");
        if(page==='tuition-fees')await evaluate("(()=>{const a=VE.adapter(),s=a.draftState(),key=Object.keys(s.categories)[0],file=new File(['%PDF-1.4 draft fixture'],'draft.pdf',{type:'application/pdf'});s.changes.draftFile={id:0,category:key,title:'Draft PDF',file};s.categories[key].documents.push({id:0,_key:'draftFile',title:'Draft PDF',url:URL.createObjectURL(file),size:'1 KB'});a.draftState(s);})()");
        await until(()=>evaluate("!document.getElementById('veSaveDraft').disabled"),'Save draft unavailable: '+page);
        await evaluate("document.getElementById('veSaveDraft').click()");
        await until(()=>evaluate("!VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved')"),'Draft not saved: '+page);
        assert.equal(await evaluate("document.getElementById('veSaveDraft').disabled"),true,'Saved state is clean');
        const publicPath=await evaluate("(()=>{const u=new URL(VE.state.url,location.origin);['ce_preview','draft_test','_ve'].forEach(k=>u.searchParams.delete(k));return u.pathname+u.search})()");
        const publicHtml=await(await fetch(base+publicPath)).text();
        assert.equal(publicHtml.includes(marker),false,'Draft must not change the public page: '+page);
        await evaluate('window.draftReloadFlag=true');await send('Page.reload');
        await until(()=>evaluate("Boolean(!window.draftReloadFlag && window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved'))"),'Draft not restored: '+page);
        assert.equal(await evaluate(`VE.state.doc.body.textContent.includes(`+JSON.stringify(marker)+`)`),true,'Restored text: '+page);
        if(page==='home')assert.equal(await evaluate("VE.adapter().draftState().hcFiles.news_background.name==='draft.png' && VE.state.doc.querySelector('[data-ve-img=\"home-background\"][data-ve-key=\"news_background\"]').closest('section').style.getPropertyValue('--home-section-background').includes('blob:')"),true,'Selected image and preview survived reload');
        if(page==='athletics-sports')assert.equal(await evaluate("VE.adapter().draftState().rows.some(row=>row.name==='New sport') && [...VE.state.doc.querySelectorAll('[data-ve-sport]')].some(el=>el.textContent.includes('New sport'))"),true,'New sport survived reload');
        if(page==='student-achievements')assert.equal(await evaluate("Object.keys(VE.adapter().draftState().records).some(key=>key.startsWith('new-') && VE.state.doc.querySelector('[data-ve-achievement-id=\"'+key+'\"]'))"),true,'New achievement survived reload');
        if(page==='student-organizations')assert.equal(await evaluate("Object.keys(VE.adapter().draftState().records).some(key=>key.startsWith('new-') && VE.state.doc.querySelector('[data-ve-org-card=\"'+key+'\"]'))"),true,'New organization survived reload');
        if(page==='tuition-fees')assert.equal(await evaluate("VE.adapter().draftState().changes.draftFile.file.name==='draft.pdf' && VE.adapter().draftState().categories[Object.keys(VE.adapter().draftState().categories)[0]].documents.some(doc=>doc.title==='Draft PDF')"),true,'PDF survived reload');
        await evaluate("window.draftReloadFlag=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate("Boolean(!window.draftReloadFlag && window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veDiscardDraft').hidden && !VE.state.doc.body.textContent.includes("+JSON.stringify(marker)+"))"),'Draft not discarded: '+page);
        console.log('PASS draft save/restore/discard: '+page);
    }
    for(const page of ['college-departments','college-admission-requirements']){
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
        await until(()=>evaluate("Boolean(window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veLoader').hidden && VE_CONFIG.page.slug==="+JSON.stringify(page)+")"),'Order fixture unavailable');
        const expected=await evaluate(page==='college-departments'?`(()=>{const doc=VE.state.doc,handle=doc.querySelector('[data-ve-department-drag]');handle.dispatchEvent(new doc.defaultView.KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true}));return VE.adapter().draftState().departmentOrder;})()`:`(()=>{const doc=VE.state.doc,tabs=doc.querySelector('[data-admission-category-tabs]'),cards=[...tabs.querySelectorAll('.ve-admission-category')];tabs.insertBefore(cards[1],cards[0]);const el=doc.querySelector('[data-ve-section]');el.dispatchEvent(new doc.defaultView.Event('input',{bubbles:true}));return VE.adapter().draftState().orders;})()`);
        await until(()=>evaluate("!document.getElementById('veSaveDraft').disabled"),'Order draft not ready');
        await evaluate("document.getElementById('veSaveDraft').click()");
        await until(()=>evaluate("!VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved')"),'Order not saved');
        await evaluate('window.draftReloadFlag=true');await send('Page.reload');
        await until(()=>evaluate("Boolean(!window.draftReloadFlag && window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved'))"),'Order not restored');
        assert.deepEqual(await evaluate(page==='college-departments'?'VE.adapter().draftState().departmentOrder':'VE.adapter().draftState().orders'),expected,'Draft layout restored for '+page);
        await evaluate("window.draftReloadFlag=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate("Boolean(!window.draftReloadFlag && window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veDiscardDraft').hidden)"),'Order draft not discarded');
        console.log('PASS draft layout: '+page);
    }
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=history'});
    await until(()=>evaluate("Boolean(window.VE && VE.state.doc && !VE.state.busy && VE_CONFIG.page.slug==='history' && document.getElementById('veLoader').hidden)"),'Publish fixture not ready');
    assert.equal(await evaluate(`(async()=>{const file=new File(['binary draft fixture'],'draft.txt',{type:'text/plain'}),url=URL.createObjectURL(file);GNCEditorDrafts.trackUrl(url,file);const packed=JSON.parse(await GNCEditorDrafts.encode({version:1,state:{file},preview:{src:url}}));const restored=GNCEditorDrafts.decode(packed);return packed.files.length===1 && await restored.state.file.text()==='binary draft fixture' && (await(await fetch(restored.preview.src)).text())==='binary draft fixture';})()`),true,'Binary files are deduplicated and restored with fresh preview URLs');
    await evaluate("(()=>{const el=VE.state.doc.querySelector('[data-ve-section]');el.innerHTML='<p>Private draft publishing fixture</p>';el.__ve.onChange();el.dispatchEvent(new Event('input',{bubbles:true}));window.ContentCompleteness=null;})()");
    await until(()=>evaluate("!document.getElementById('veSaveDraft').disabled"),'Publish fixture draft action disabled');
    await evaluate("document.getElementById('veSaveDraft').click()");
    await until(()=>evaluate("!VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved')"),'Publish fixture not saved');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
        assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector('.ve-top').getBoundingClientRect().right<=innerWidth"),true,'Draft controls fit viewport '+width);
        const screenshot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(auditDirectory,'draft-controls-'+width+'.png'),Buffer.from(screenshot.data,'base64'));
    }
    let denyPublish=true,publishRequests=0;
    socket.addEventListener('message',event=>{
        const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;
        publishRequests++;
        const response=denyPublish?{success:false,error:'Fixture publish denied'}:{success:true,has_draft:false,review_status:'none'};
        send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:denyPublish?403:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(response)).toString('base64')}).catch(console.error);
    });
    await send('Fetch.enable',{patterns:[{urlPattern:'*page-review-handler.php*'}]});
    async function publishFixture(){
        await evaluate('VE.adapter().runPrimary()');
        await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-dialog[open]'))"),'Publish confirmation missing');
        await evaluate("document.querySelector('.ve-publish-accept').click()");
        await until(()=>evaluate("!VE.state.busy && !document.querySelector('.ve-publish-dialog')"),'Publish did not finish');
    }
    await publishFixture();
    assert.equal(await evaluate("!document.getElementById('veDiscardDraft').hidden && VE.adapter().dirtyCount()>0"),true,'Failed publication retains draft');
    denyPublish=false;
    await publishFixture();
    assert.equal(await evaluate("document.getElementById('veDiscardDraft').hidden && VE.adapter().dirtyCount()===0"),true,'Successful publication clears private checkpoint');
    assert.equal(publishRequests,2,'Both publication attempts intercepted; live content was not written');
    await send('Fetch.disable');
    assert.equal((await(await fetch(base+'/pages/about/gnc-history.php')).text()).includes('Private draft publishing fixture'),false);
    console.log('PASS: failed publish preserves draft; successful publish clears checkpoint (publish responses stubbed), mobile/desktop controls fit.');
    const unauth=await fetch(base+'/admin/action/editor-draft-handler.php',{method:'POST'});
    assert.equal(unauth.status,401);
    const badCsrf=await evaluate("fetch('/admin/action/editor-draft-handler.php',{method:'POST',body:new URLSearchParams({action:'load',page:'home',route:'/'})}).then(r=>r.status)");
    assert.equal(badCsrf,403);
    console.log('PASS: anonymous and missing-CSRF requests rejected.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
