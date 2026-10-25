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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','community-impact-regression');value.page.url=u.pathname+u.search;}}});})();`});


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=community-activities'});
    await until(()=>evaluate("Boolean(window.VE&&!VE.state.busy&&document.getElementById('veLoader').hidden&&VE.state.doc.querySelector('[data-community-impact-preview]'))"),'Impact editor unavailable');
    assert.ok(await evaluate("Boolean(VE.state.doc.querySelector('[data-ve-key=impact_heading]').__ve)"),'Impact heading is editable');
    assert.ok(await evaluate("Boolean(VE.state.doc.querySelector('[data-ve-key=closing_title]').__ve&&VE.state.doc.querySelector('[data-ve-key=closing_description]').__ve)"),'Mission banner text is editable');
    await evaluate("VE.state.doc.querySelector('[data-community-closing-image]').click()");
    assert.equal(await evaluate('VE.state.sel.id'),'closing','Closing background has its own replacement target');
    assert.ok(await evaluate("[...document.querySelectorAll('#veTb button')].some(button=>button.textContent==='Replace picture')"),'Closing background can be replaced');
    assert.equal(await evaluate("Boolean(VE.state.doc.querySelector('[data-ve-community-news]'))"),false,'Manage impact news button is removed');
    await send('Page.navigate',{url:base+'/admin/news.php?embed=1&category=Community%20Extension'});
    await until(()=>evaluate("Boolean(typeof openCreateModal==='function'&&document.querySelector('#modal-category option[value=\"Community Extension\"]'))"),'Community news category unavailable');
    await evaluate('openCreateModal()');
    assert.equal(await evaluate("document.getElementById('modal-category').value"),'Community Extension','Create News keeps the current category');
    const fixtures={};
    for(const page of [1,2,3]){
        const rendered=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/community-activities-fixture.php','impact',String(page)],{encoding:'utf8',windowsHide:true});
        assert.equal(rendered.status,0,rendered.stderr||rendered.stdout);fixtures[page]=rendered.stdout;
    }
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;const page=Number(new URL(packet.params.request.url).searchParams.get('impact_page')||1);await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from(fixtures[page]||fixtures[1]).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*community-activity.php*'}]});
    await send('Page.navigate',{url:base+'/pages/student%20life/community-activity.php?impact_page=1'});
    await until(()=>evaluate("Boolean(document.querySelector('#community-impact .campus-recent-card')&&window.GNCCommunityActivities)"),'Public impact feed unavailable');
    assert.equal(await evaluate("document.querySelectorAll('#community-impact .campus-recent-card').length"),6);
    const activeActivity=await evaluate('GNCCommunityActivities.active()');
    const activityImage=await evaluate("document.querySelector('[data-community-image]').src");
    await evaluate("document.querySelector('[data-community-id=activity-fixture-6]').dispatchEvent(new MouseEvent('mouseover',{bubbles:true}))");
    assert.equal(await evaluate('GNCCommunityActivities.active()'),activeActivity,'Hover does not select another activity');
    assert.equal(await evaluate("document.querySelector('[data-community-image]').src"),activityImage,'Hover retains the displayed picture');
    await evaluate("(()=>{const row=document.querySelector('[data-community-id=activity-fixture-6]');row.dispatchEvent(new FocusEvent('focusin',{bubbles:true}));row.click();GNCCommunityActivities.select('activity-fixture-6')})()");
    assert.equal(await evaluate("Boolean(document.querySelector('[data-community-open],[data-community-dialog]'))"),false,'Activity links and dialog are removed');
    assert.equal(await evaluate("document.querySelector('[data-community-image]').src"),activityImage,'Click, focus and activity selection retain the shared photo');
    assert.equal(await evaluate("document.getElementById('communityMissionTitle').textContent"),'Join the Mission');
    assert.ok(await evaluate("Boolean(document.getElementById('community-impact').compareDocumentPosition(document.querySelector('.community-mission'))&Node.DOCUMENT_POSITION_FOLLOWING)"),'Mission banner follows Community Impact');
    assert.equal(await evaluate("document.querySelector('#community-impact h3').textContent"),'Community impact fixture 14');
    assert.equal(await evaluate("Boolean(document.querySelector('[data-ve-community-news],[data-ve-post],[contenteditable=true]'))"),false,'Public feed has no editing controls');
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
        await evaluate("document.getElementById('community-impact').scrollIntoView({block:'start',behavior:'instant'})");await delay(100);
        const columns=await evaluate("getComputedStyle(document.querySelector('#community-impact .campus-recent-grid')).gridTemplateColumns.split(' ').length");
        assert.equal(columns,width===1440?3:width===820?2:1,'Responsive grid at '+width);
        assert.ok(await evaluate("[...document.querySelectorAll('#community-impact .campus-recent-card')].every(el=>el.getBoundingClientRect().right<=innerWidth+1)"),'Cards fit viewport');
        assert.equal(await evaluate("getComputedStyle(document.querySelector('#community-impact .campus-recent-card')).animationName"),'none','Reduced motion honored');
        fs.writeFileSync(path.join(auditDirectory,'community-impact-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await evaluate("document.querySelector('.community-mission').scrollIntoView({block:'center',behavior:'instant'})");
        assert.ok(await evaluate("(()=>{const banner=document.querySelector('.community-mission'),rect=banner.getBoundingClientRect();return rect.right<=innerWidth+1&&rect.width>0&&getComputedStyle(banner.querySelector('h2')).color==='rgb(255, 255, 255)'&&banner.querySelectorAll('.community-mission-orb').length===3})()"),'Mission banner fits and matches reference at '+width);
        assert.equal(await evaluate("getComputedStyle(document.querySelector('.community-mission-orb-top')).animationName"),'none','Banner respects reduced motion');
        fs.writeFileSync(path.join(auditDirectory,'community-mission-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'no-preference'}]});
    const motion=await evaluate("(()=>{const card=document.querySelector('#community-impact .campus-recent-card'),img=card.querySelector('img');return {animation:getComputedStyle(card).animationName,transition:getComputedStyle(img).transitionDuration,border:getComputedStyle(card).borderRadius}})()");
    assert.ok(['cev-reveal','cev-fade-up'].includes(motion.animation));assert.equal(motion.transition,'0.6s');assert.equal(motion.border,'0px','Current Campus Events edge treatment');
    await send('DOM.enable');await send('CSS.enable');
    const root=await send('DOM.getDocument');
    const cardNode=await send('DOM.querySelector',{nodeId:root.root.nodeId,selector:'#community-impact .campus-recent-card'});
    await send('CSS.forcePseudoState',{nodeId:cardNode.nodeId,forcedPseudoClasses:['hover']});await delay(750);
    const hover=await evaluate("(()=>{const card=document.querySelector('#community-impact .campus-recent-card');return {zoom:new DOMMatrix(getComputedStyle(card.querySelector('img')).transform).a,lift:new DOMMatrix(getComputedStyle(card.querySelector('.campus-recent-content')).transform).m42}})()");
    assert.ok(Math.abs(hover.zoom-1.08)<0.001,'Campus Events photo zoom is applied');assert.equal(hover.lift,-5,'Campus Events text hover lift is applied');
    await send('CSS.forcePseudoState',{nodeId:cardNode.nodeId,forcedPseudoClasses:[]});
    await evaluate("document.querySelector('#community-impact [rel=next]').click()");
    await until(()=>evaluate("document.querySelector('#community-impact [aria-current=page]')?.textContent==='2'"),'AJAX pagination failed');
    assert.equal(await evaluate("document.querySelector('#community-impact h3').textContent"),'Community impact fixture 8');
    await evaluate("document.querySelector('#community-impact [rel=next]').click()");
    await until(()=>evaluate("document.querySelectorAll('#community-impact .campus-recent-card').length===2"),'Last page missing');
    await evaluate('history.back()');
    await until(()=>evaluate("document.querySelector('#community-impact [aria-current=page]')?.textContent==='2'"),'Back navigation failed');
    await send('Fetch.disable');
    console.log('PASS: embedded Create News category, editable section heading, published feed rendering, six-card pagination/Back, 3/2/1 columns, Campus Events animation settings and reduced-motion support. Temporary tables and intercepted public requests leave saved content unchanged.');})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});




