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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','community-activities-regression');value.page.url=u.pathname+u.search;}}});})();`});


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=community-activities'});
    await until(()=>evaluate("Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.state.doc.querySelector('[data-ve-community-add]'))"),'Activity editor unavailable');
    if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){
        await evaluate("window.pendingReload=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate("Boolean(!window.pendingReload&&window.VE&&!VE.state.busy&&document.getElementById('veLoader').hidden)"),'Old test draft not discarded');
    }
    assert.equal(await evaluate('VE.adapter().draftState().content.activities.length'),0,'No seeded activity examples');
    assert.ok(await evaluate("VE.state.win.getComputedStyle(VE.state.doc.querySelector('.community-activity-hero')).backgroundImage.includes('admission-header.png')"),'Original banner retained');
    async function fill(id,key,value){await evaluate(`(()=>{const el=VE.state.doc.querySelector('[data-ve-community-card="${id}"] [data-ve-field="community-activity-${key}"]');el.textContent=${JSON.stringify(value)};el.dispatchEvent(new Event('input',{bubbles:true}));})()`);}
    const ids=[];
    for(const suffix of ['A','B']){
        await evaluate("VE.state.doc.querySelector('[data-ve-community-add]').click()");const id=await evaluate('VE.adapter().draftState().content.activities.at(-1).id');ids.push(id);
        for(const [key,value] of Object.entries({title:'Authored fixture '+suffix,description:'Private description '+suffix,category:'Custom category '+suffix}))await fill(id,key,value);
    }
    async function stageImage(name){
        await evaluate(`VE.state.win.GNCCommunityActivities.select(${JSON.stringify(ids[0])});VE.state.doc.querySelector('[data-community-feature]').click();document.getElementById('veFile').click=()=>{};[...document.querySelectorAll('#veTb button')].find(b=>b.textContent==='Replace picture').click()`);
        await evaluate(`(()=>{const el=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII='),c=>c.charCodeAt(0))],${JSON.stringify(name)},{type:'image/png'}));el.files=dt.files;el.onchange();})()`);
        await until(()=>evaluate('Object.values(VE.adapter().draftState().files)[0]?.name==='+JSON.stringify(name)),'Image not staged');
    }
    await stageImage('first.png');await stageImage('replacement.png');
    assert.equal(await evaluate('Object.keys(VE.adapter().draftState().files).length'),1,'Replacing a staged image uses one upload slot');
    await evaluate(`VE.state.doc.querySelector('[data-ve-community-card="${ids[1]}"] h4').click();document.querySelector('#veTb [title="Move activity earlier"]').click()`);
    assert.equal(await evaluate('VE.adapter().draftState().content.activities[0].id'),ids[1]);
    const publicHtml=await fetch(base+'/pages/student%20life/community-activity.php').then(r=>r.text());assert.ok(!publicHtml.includes('Authored fixture'),'Draft activity stays private');assert.ok(publicHtml.includes('No activities available yet'));
    await evaluate("document.getElementById('veSaveDraft').click()");await until(()=>evaluate("!VE.state.busy&&document.getElementById('veStatus').textContent.includes('Draft saved')"),'Draft not saved');
    await evaluate('window.pendingReload=true');await send('Page.reload');await until(()=>evaluate("Boolean(!window.pendingReload&&window.VE&&!VE.state.busy&&document.getElementById('veStatus').textContent.includes('Draft saved'))"),'Draft not restored');
    assert.deepEqual(await evaluate('VE.adapter().draftState().content.activities.map(r=>r.title)'),['Authored fixture B','Authored fixture A']);assert.equal(await evaluate('Object.values(VE.adapter().draftState().files)[0].name'),'replacement.png');
    await evaluate(`window.AdminDialogs.confirm=async()=>true;VE.state.doc.querySelector('[data-ve-community-card="${ids[1]}"] h4').click();document.querySelector('#veTb [title="Delete activity"]').click()`);
    await until(()=>evaluate('VE.adapter().draftState().content.activities.length===1'),'Delete not staged');
    const requests=[];let fail=true;
    socket.addEventListener('message',async event=>{const p=JSON.parse(event.data);if(p.method!=='Fetch.requestPaused'||!p.params.request.url.includes('community-activities-handler.php'))return;requests.push(p.params.request.postData||'');await send('Fetch.fulfillRequest',{requestId:p.params.requestId,responseCode:fail?422:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(fail?{error:'Fixture failure'}:{success:true,revision:'fixture-published'})).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*community-activities-handler.php*'}]});
    async function publish(){await evaluate('window.ContentCompleteness=null;VE.adapter().runPrimary()');await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-dialog[open]'))"),'Shared confirmation missing');await evaluate("document.querySelector('.ve-publish-accept').click()");}
    await publish();await until(async()=>requests.length===1&&await evaluate('!VE.state.busy&&!document.querySelector(".ve-publish-dialog")'),'Failed publish still busy');assert.equal(await evaluate('Object.keys(VE.adapter().draftState().files).length'),1);
    fail=false;await evaluate('window.pendingReload=true');await publish();await until(()=>evaluate("Boolean(!window.pendingReload&&window.VE&&!VE.state.busy&&document.getElementById('veLoader').hidden&&document.getElementById('veDiscardDraft').hidden)"),'Publish did not finish');
    assert.equal(requests.length,2);assert.ok(requests[1].includes('filename="replacement.png"'));const submitted=JSON.parse(/name="content"\r?\n\r?\n([^\r\n]+)/.exec(requests[1])[1]);assert.equal(submitted.activities.length,1);assert.equal(submitted.activities[0].category,'Custom category A');
    await send('Fetch.disable');
    assert.equal(await evaluate("fetch('/admin/action/community-activities-handler.php',{method:'POST',credentials:'omit'}).then(r=>r.status)"),403);assert.equal(await evaluate("fetch('/admin/action/community-activities-handler.php',{method:'POST'}).then(r=>r.status)"),403);
    const fixture=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/community-activities-fixture.php'],{encoding:'utf8',windowsHide:true});assert.equal(fixture.status,0,fixture.stderr||fixture.stdout);
    socket.addEventListener('message',async event=>{const p=JSON.parse(event.data);if(p.method==='Fetch.requestPaused'&&p.params.request.url.includes('activity_fixture=1'))await send('Fetch.fulfillRequest',{requestId:p.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(fixture.stdout).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*activity_fixture=1*',resourceType:'Document'}]});
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await send('Page.navigate',{url:base+'/pages/student%20life/community-activity.php?activity_fixture=1'});
        await until(()=>evaluate("Boolean(window.GNCCommunityActivities&&document.readyState==='complete')"),'Public fixture unavailable');
        assert.equal(await evaluate("document.querySelectorAll('[data-community-id]').length"),6,'All database entries render, no fixed example list');
        assert.equal(await evaluate("Boolean(document.querySelector('[data-ve-community-add],[contenteditable=true]'))"),false);
        assert.ok(await evaluate("[...document.querySelectorAll('.community-activity-showcase,.community-activity-feature,.community-activity-list-panel')].every(el=>el.getBoundingClientRect().right<=innerWidth+1)"),'Responsive activity content fits');
        fs.writeFileSync(path.join(auditDirectory,'community-activities-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate("document.querySelector('[data-community-id=activity-fixture-6]').click()");assert.equal(await evaluate("Boolean(document.querySelector('[data-community-open],[data-community-dialog]'))"),false);
    }
    await send('Fetch.disable');
    console.log('PASS: existing banner, inline activity/category editing, image replacement, reorder/delete, private draft restore, shared publish/retry, auth/CSRF and responsive public details. Database-rendered fixtures and intercepted publication leave saved content unchanged.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
