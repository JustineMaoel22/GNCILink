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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','faq-regression');value.page.url=u.pathname+u.search;}}});})();`});


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=faqs'});
    const ready="Boolean(window.VE&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.state.doc.querySelector('[data-ve-faq-add]'))";
    await until(()=>evaluate(ready),'FAQ editor unavailable');
    if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){
        await evaluate("window.pendingReload=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate('!window.pendingReload&&'+ready),'Old test draft not discarded');
    }
    const baseCount=await evaluate('VE.adapter().draftState().content.items.length');
    const titles=['Private FAQ fixture A','Private FAQ fixture B'];const ids=[];
    for(const title of titles){
        await evaluate("VE.state.doc.querySelector('[data-ve-faq-add]').click()");
        const id=await evaluate('VE.adapter().draftState().content.items.at(-1).id');ids.push(id);
        for(const [key,value] of Object.entries({question:title,answer:'Private answer '+title+'\nSecond line.'})){
            await evaluate(`(()=>{const el=VE.state.doc.querySelector('[data-ve-faq-item="${id}"] [data-ve-field="faq-${key}"]');el.textContent=${JSON.stringify(value)};el.dispatchEvent(new VE.state.win.Event('input',{bubbles:true}));})()`);
        }
    }
    await evaluate(`VE.state.doc.querySelector('[data-ve-faq-item="${ids[1]}"] [data-ve-field=faq-question]').click();document.querySelector('#veTb [title="Move FAQ earlier"]').click()`);
    assert.deepEqual(await evaluate('VE.adapter().draftState().content.items.slice(-2).map(row=>row.question)'),titles.toReversed(),'Reorder staged');
    const publicBefore=await fetch(base+'/pages/faq.php').then(r=>r.text());assert.ok(!publicBefore.includes('Private FAQ fixture'),'Unpublished questions are private');
    assert.ok(publicBefore.includes('href="/pages/faq.php"'),'Navbar opens actual FAQ page');
    await evaluate("document.getElementById('veSaveDraft').click()");
    await until(()=>evaluate("!VE.state.busy&&document.getElementById('veStatus').textContent.includes('Draft saved')"),'FAQ draft not saved');
    await evaluate('window.pendingReload=true');await send('Page.reload');
    await until(()=>evaluate('!window.pendingReload&&'+ready+"&&document.getElementById('veStatus').textContent.includes('Draft saved')"),'FAQ draft not restored');
    assert.deepEqual(await evaluate('VE.adapter().draftState().content.items.slice(-2).map(row=>row.question)'),titles.toReversed());
    await evaluate(`window.AdminDialogs.confirm=async()=>true;VE.state.doc.querySelector('[data-ve-faq-item="${ids[1]}"] [data-ve-field=faq-question]').click();document.querySelector('#veTb [title="Delete FAQ"]').click()`);
    await until(()=>evaluate('VE.adapter().draftState().content.items.length==='+String(baseCount+1)),'Deletion not staged');
    const requests=[];let failure=true;
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused'||!packet.params.request.url.includes('/admin/action/faqs-handler.php'))return;requests.push(packet.params.request.postData||'');await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:failure?422:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(failure?{error:'Isolated test rejection'}:{success:true,revision:'fixture-published'})).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*faqs-handler.php*'}]});
    async function confirmSave(cancel=false){
        await evaluate('VE.adapter().runPrimary()');
        await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-dialog[open],.cms-completeness-dialog[open]'))"),'Shared publish confirmation missing');
        await evaluate(cancel?"(document.querySelector('.ve-publish-cancel')||document.querySelector('.cms-completeness-keep')).click()":"(document.querySelector('.ve-publish-accept')||document.querySelector('.cms-completeness-publish')).click()");
    }
    await confirmSave(true);await delay(100);assert.equal(requests.length,0,'Cancel never publishes');
    await confirmSave();await until(async()=>requests.length===1&&await evaluate('!VE.state.busy'),'Failed request still busy');
    assert.equal(await evaluate('VE.adapter().draftState().content.items.at(-1).question'),titles[0],'Failure retains edits');
    failure=false;await evaluate('window.pendingReload=true');await confirmSave();
    await until(()=>evaluate('!window.pendingReload&&'+ready+"&&document.getElementById('veDiscardDraft').hidden"),'Successful publish did not clear private draft');
    assert.equal(requests.length,2);assert.ok(requests[1].includes('Private FAQ fixture A'));assert.ok(!requests[1].includes('Private FAQ fixture B'),'Deleted FAQ excluded from submission');
    await send('Fetch.disable');
    assert.equal(await evaluate("fetch('/admin/action/faqs-handler.php',{method:'POST',credentials:'omit'}).then(r=>r.status)"),403,'Unauthorized request rejected');
    assert.equal(await evaluate("fetch('/admin/action/faqs-handler.php',{method:'POST'}).then(r=>r.status)"),403,'Missing CSRF rejected');
    const fixtures={};
    for(const mode of ['filled','empty']){
        const result=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/faqs-fixture.php',mode],{encoding:'utf8',windowsHide:true});assert.equal(result.status,0,result.stderr||result.stdout);fixtures[mode]=result.stdout;
    }
    socket.addEventListener('message',async event=>{const p=JSON.parse(event.data);if(p.method!=='Fetch.requestPaused'||!p.params.request.url.includes('faq_fixture'))return;const mode=new URL(p.params.request.url).searchParams.get('faq_fixture');await send('Fetch.fulfillRequest',{requestId:p.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(fixtures[mode]).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*faq_fixture=*'}]});
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await send('Page.navigate',{url:base+'/pages/faq.php?faq_fixture=filled'});
    await until(()=>evaluate("document.querySelectorAll('.faq-item').length===6&&document.readyState==='complete'"),'Public FAQ fixture missing');
    await until(()=>evaluate("!document.getElementById('gncPageEntrance')"),'Opening transition did not finish');
    assert.equal(await evaluate("Boolean(document.querySelector('[contenteditable=true],[data-ve-faq-add],#faqPageData'))"),false,'Public FAQ has no private editing data');
    assert.equal(await evaluate("document.querySelectorAll('.faq-answer script').length"),0,'Answer markup is escaped');
    assert.ok(await evaluate("document.querySelector('.faq-answer').textContent.includes('<script>literal text</script>')"),'Answer text is preserved');
    await evaluate("document.querySelector('.faq-item summary').click()");assert.equal(await evaluate("document.querySelector('.faq-item').open"),true,'Accordion expands');
    await evaluate("document.querySelector('.faq-item summary').click()");assert.equal(await evaluate("document.querySelector('.faq-item').open"),false,'Accordion collapses');
    await send('Page.bringToFront');
    await evaluate("document.querySelector('.faq-item summary').focus()");
    assert.equal(await evaluate("document.activeElement.tagName"),'SUMMARY','Accordion summary receives keyboard focus');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,text:'\r'});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await until(()=>evaluate("document.querySelector('.faq-item').open"),'Keyboard opens accordion');
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await evaluate("window.scrollTo({top:0,behavior:'instant'})");
        await until(()=>evaluate("[...document.querySelectorAll('.faq-list,.faq-item summary,.faq-answer')].every(el=>el.getBoundingClientRect().right<=innerWidth+1)"),'FAQ fits '+width);
        fs.writeFileSync(path.join(auditDirectory,'faqs-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await send('Page.navigate',{url:base+'/pages/faq.php?faq_fixture=empty'});
    await until(()=>evaluate("document.querySelector('[data-faq-empty]')&&!document.querySelector('[data-faq-empty]').hidden"),'FAQ empty state unavailable');
    assert.equal(await evaluate("document.querySelectorAll('.faq-item').length"),0,'Empty page has no seeded questions');
    await send('Fetch.disable');
    console.log('PASS: FAQ inline editing, reorder/delete, private draft restore, cancel/publish/retry, auth/CSRF, escaped public accordion with keyboard support, responsive layouts and empty state. Publications intercepted; existing content unchanged.');})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});





