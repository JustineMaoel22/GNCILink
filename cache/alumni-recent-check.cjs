const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'alumni-voices-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,label){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(150);}throw Error(label);}
let socket,session,cleanup;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();
    const listen=event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}if(p.method==='Page.javascriptDialogOpening')socket.send(JSON.stringify({id:++serial,method:'Page.handleJavaScriptDialog',params:{accept:true}}));};
    socket.addEventListener('message',listen);
    const reconnect=async()=>{socket.close();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));socket.addEventListener('message',listen);};
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Timed out: '+method+' '+(params.expression||'').slice(0,60)));},15000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
    const ev=expression=>new Promise((resolve,reject)=>{
        const client=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
        const timer=setTimeout(()=>{client.close();reject(Error('Evaluation timed out: '+expression.slice(0,60)));},12000);
        client.addEventListener('open',()=>client.send(JSON.stringify({id:1,method:'Runtime.evaluate',params:{expression,returnByValue:true,awaitPromise:true,timeout:10000}})),{once:true});
        client.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id!==1)return;clearTimeout(timer);client.close();if(p.error||p.result.exceptionDetails)reject(Error(JSON.stringify(p.error||p.result.exceptionDetails)));else resolve(p.result.result.value);});
    });
    const visit=async route=>{await send('Page.navigate',{url:'http://localhost:8080'+route});await until(()=>ev("document.readyState==='complete'"),'Page unavailable');await delay(2200);await ev("document.querySelector('#gncSiteDisclaimer form button')?.click();document.querySelector('[data-privacy-action=\"reject\"]')?.click()");};
    await send('Page.enable');await send('Network.enable');await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await visit('/alumni');
    const denied=await ev("fetch('/admin/action/alumni-voices-handler.php',{method:'POST'}).then(r=>r.status)");assert.equal(denied,403);
    const php=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','Super Admin'],{encoding:'utf8',windowsHide:true});if(php.status!==0)throw Error(php.stderr||php.stdout);session=JSON.parse(php.stdout);
    await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});await visit('/admin/content-edit.php?page=alumni');
    await until(()=>ev("Boolean(document.getElementById('veFrame').contentDocument?.querySelector('[data-alumni-voice-add]'))"),'Voice manager unavailable');
    {
        assert.ok(await ev("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-section=recent_events_title]')?.isContentEditable"),'Recent Events heading editable');
        await visit('/alumni');
        assert.ok(await ev("Boolean(document.querySelector('#alumni-recent-events'))"));
        for(const width of [1440,820,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:false});
            await ev("fetch('/cache/alumni-recent-fixture-1.html').then(r=>r.text()).then(html=>{document.querySelector('#alumni-recent-events').outerHTML=html})");await delay(300);
            const metrics=await ev("(()=>{const s=document.querySelector('#alumni-recent-events');window.scrollTo(0,s.getBoundingClientRect().top+scrollY-120);const cards=[...s.querySelectorAll('.campus-recent-card')].map(c=>c.getBoundingClientRect());return {overflow:document.documentElement.scrollWidth>innerWidth,count:cards.length,overlap:cards.some((a,i)=>cards.some((b,j)=>i!==j&&a.right>b.left+1&&a.left<b.right-1&&a.bottom>b.top+1&&a.top<b.bottom-1)),next:s.querySelector('[rel=next]')?.getAttribute('href')}})()");
            assert.equal(metrics.count,6);assert.equal(metrics.overflow,false);assert.equal(metrics.overlap,false);assert.ok(metrics.next.includes('event_page=2'));
            await delay(700);await ev("document.querySelector('#alumni-recent-events').scrollIntoView({block:'start'})");await delay(200);fs.writeFileSync('cache/alumni-recent-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
            await ev("fetch('/cache/alumni-recent-fixture-2.html').then(r=>r.text()).then(html=>{document.querySelector('#alumni-recent-events').outerHTML=html})");
            assert.equal(await ev("document.querySelector('#alumni-recent-events [aria-current=page]').textContent"),'2');
        }
        await visit('/pages/student%20life/campus-event.php');
        assert.ok(await ev("getComputedStyle(document.querySelector('.campus-recent-grid')).display==='grid'"),'Campus gallery still styled');
        await ev("document.querySelector('#recent-events').scrollIntoView({block:'start'})");await delay(700);await ev("document.querySelector('#recent-events').scrollIntoView({block:'start'})");
        fs.writeFileSync('cache/campus-recent-shared-css.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        console.log('PASS: editable gallery heading, six-card layout/pagination at 1440/820/390, no overlaps/overflow, shared Campus Events styles. Fixture markup only; no content saved.');return;
    }
    const route='/pages/alumni.php?voices_test='+Date.now(),token=await ev('VE_CONFIG.csrf');
    await ev(`VE.state.url=${JSON.stringify(route+'&ce_preview=1')};document.getElementById('veFrame').src=VE.state.url`);
    await until(()=>ev("Boolean(VE.adapter().draftState().alumniVoices?.data?.ready)&&!VE.state.busy"),'Manager not ready');await delay(1200);
    const original=await ev('VE.adapter().draftState().alumniVoices.data');assert.equal(original.items.length,0,'Run this fixture check on the empty development collection');
    let publishedRevision=null;
    const post=async(endpoint,fields)=>ev(`fetch(${JSON.stringify(endpoint)},{method:'POST',body:new URLSearchParams(${JSON.stringify({csrf_token:token,...fields})})}).then(r=>r.json())`);
    cleanup=async()=>{
        if(publishedRevision){const restored=await post('/admin/action/alumni-voices-handler.php',{items:JSON.stringify(original.items),revision:publishedRevision});assert.equal(restored.success,true,'Restore original voices');publishedRevision=null;}
        const draft=await post('/admin/action/editor-draft-handler.php',{action:'load',page:'alumni',route,revision:'0'});
        if(draft.draft)await post('/admin/action/editor-draft-handler.php',{action:'delete',page:'alumni',route,revision:String(draft.draft.revision)});
    };
    await send('Page.setInterceptFileChooserDialog',{enabled:true});
    for(let i=0;i<3;i++){
        await ev("document.getElementById('veFrame').contentDocument.querySelector('[data-alumni-voice-add]').click()");
        assert.equal(await ev("Boolean(document.querySelector('#vePop form'))"),false,'Adding must not open a form');
        await ev(`(()=>{const doc=document.getElementById('veFrame').contentDocument,card=doc.querySelectorAll('[data-alumni-voice]')[${i}];for(const [key,value]of Object.entries({'Name':'Test alumnus ${i+1}','Quote':'A test quote for responsive verification.','Batch / class year':'Batch test','Description / role':'Test role','Photo description':'Campus bell tower'})){let field=card.querySelector('[aria-label="'+key+'"]');if(!field.isContentEditable)throw Error('Field not inline editable');field.click();if(key==='Photo description')field.closest('details').open=true;field.focus();field.textContent=value;field.dispatchEvent(new Event('input',{bubbles:true}));}card.querySelector('[data-alumni-voice-photo]').click()})()`);
        const root=await send('DOM.getDocument'),input=await send('DOM.querySelector',{nodeId:root.root.nodeId,selector:'#veFile'});
        await send('DOM.setFileInputFiles',{nodeId:input.nodeId,files:[path.resolve('assets/images/gnc-church.jpg')]});
        assert.equal(await ev("document.getElementById('veFrame').contentDocument.querySelectorAll('[data-alumni-voice]').length"),i+1);
    }
    await ev("document.getElementById('veFrame').contentDocument.querySelectorAll('[data-alumni-voice-feature]')[2].click()");
    assert.equal(await ev("document.getElementById('veFrame').contentDocument.querySelector('.alumni-voice h3').textContent"),'Test alumnus 3');
    assert.equal(await ev('VE.adapter().dirtyCount()>0'),true);
    await ev("document.getElementById('veSaveDraft').click()");await until(()=>ev("!VE.state.busy&&document.getElementById('veDiscardDraft').hidden===false"),'Draft not saved');
    console.log('Draft saved');
    await ev("document.getElementById('veFrame').src=VE.state.url+'&reload=1'");await delay(2000);
    await until(()=>ev("VE.adapter().draftState().alumniVoices.data.items.length===3&&!VE.state.busy"),'Private card draft did not restore');
    assert.equal(await ev('Object.keys(VE.adapter().draftState().alumniVoices.files).length'),3,'Draft must retain photos');
    assert.equal(await ev("document.getElementById('veFrame').contentDocument.querySelector('.alumni-voice h3').isContentEditable"),true,'Restored card must remain inline editable');
    console.log('Draft restored');
    const csrf=await post('/admin/action/alumni-voices-handler.php',{items:'[]',revision:original.revision,csrf_token:'bad'});assert.ok(csrf.error);
    const validation=await post('/admin/action/alumni-voices-handler.php',{items:JSON.stringify([{id:'bad',name:'',quote:'test',image:'/assets/images/gnc-church.jpg'}]),revision:original.revision});assert.ok(validation.error);
    const state=await ev('VE.adapter().draftState().alumniVoices');
    const uploadRollback=await ev(`(async()=>{let state=VE.adapter().draftState().alumniVoices,item=state.data.items[0],fd=new FormData();fd.set('csrf_token',VE_CONFIG.csrf);fd.set('revision',${JSON.stringify(original.revision)});fd.set('items',JSON.stringify([item]));fd.set('voice_'+item.id,state.files[item.id]);fd.set('unexpected',new File(['test'],'unexpected.txt',{type:'text/plain'}));return (await fetch('/admin/action/alumni-voices-handler.php',{method:'POST',body:fd})).json()})()`);
    assert.match(uploadRollback.error,/Unexpected alumni photo upload/,'Valid photo upload must succeed before transaction rollback rejects the unexpected file');
    const published=await post('/admin/action/alumni-voices-handler.php',{items:JSON.stringify(state.data.items.map(item=>({...item,image:'/assets/images/gnc-church.jpg'}))),revision:original.revision});
    assert.equal(published.success,true);publishedRevision=published.revision;
    console.log('Collection published');
    const conflict=await post('/admin/action/alumni-voices-handler.php',{items:'[]',revision:original.revision});assert.match(conflict.error,/another editor/);
    await reconnect();
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:false});await visit('/alumni');
        assert.equal(await ev("document.querySelectorAll('.alumni-voice').length"),3);
        assert.equal(await ev("document.documentElement.scrollWidth<=innerWidth"),true);
        await ev("document.getElementById('alumni-voices').scrollIntoView({behavior:'instant'})");await delay(500);
        fs.writeFileSync('cache/alumni-voices-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await cleanup();cleanup=null;
    console.log('PASS: empty public state, admin add/photo/feature, private draft and photo restoration, publishing, stale revision rejection, invalid fields, permissions, responsive cards. Original collection restored; isolated draft removed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{try{if(cleanup)await cleanup();}finally{socket?.close();browser.kill();if(session)spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','destroy',session.value],{windowsHide:true});}});

