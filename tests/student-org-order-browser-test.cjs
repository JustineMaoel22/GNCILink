const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-org-order-'));
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
    orgFixture=JSON.parse(fixture('seed_pages'));
    const auth=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true}));
    session={name:auth.name,id:auth.value};
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    const frame='(document.getElementById("veFrame")?.contentDocument)';
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=student-organizations'});
    await until(()=>evaluate(`Boolean(window.VE)&&Boolean(${frame}?.getElementById('veOrgData'))&&document.getElementById('veLoader').hidden`),'Organization editor missing');
    console.log('Editor loaded');
    await evaluate(`VE.state.url=${JSON.stringify(orgFixture.url+'?category='+orgFixture.categories[0]+'&ce_preview=1')};document.getElementById('veFrame').src=${JSON.stringify(base+orgFixture.url+'?category='+orgFixture.categories[0]+'&ce_preview=1')}`);
    await until(()=>evaluate(`${frame}?.querySelectorAll('[data-ve-org-card]:not([hidden])').length===6&&new URL(${frame}.URL).searchParams.get('category')===${JSON.stringify(String(orgFixture.categories[0]))}&&document.getElementById('veLoader').hidden`),'Fixture category missing');
    console.log('Fixture loaded');
    await evaluate(`window.orderParentToken='parent';${frame}.defaultView.orderFrameToken='frame';${frame}.defaultView.scrollTo(0,300)`);
    const first=orgFixture.records[0].org_id, second=orgFixture.records[1].org_id;
    console.log('Selecting card');
    await evaluate(`${frame}.querySelector('[data-ve-org-card="${second}"] h3').click()`);
    await until(()=>evaluate(`Array.from(document.querySelectorAll('#veTb button')).some(button=>button.title==='Move earlier'||button.getAttribute('aria-label')==='Move earlier')`),'Move earlier control missing');
    console.log('Selected card');
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(button=>button.title==='Move earlier'||button.getAttribute('aria-label')==='Move earlier').click()`);
    await until(()=>evaluate(`${frame}.querySelector('[data-ve-org-card]:not([hidden])')?.getAttribute('data-ve-org-card')===${JSON.stringify(String(second))}&&!VE.state.busy`),'Canvas order not updated');
    assert.equal(await evaluate('window.orderParentToken'),'parent');
    assert.equal(await evaluate(`${frame}.defaultView.orderFrameToken`),'frame','Canvas move does not reload iframe');
    assert.equal(await evaluate(`${frame}.defaultView.scrollY`),300,'Canvas scroll position retained');
    assert.equal(await evaluate('VE.adapter().dirtyCount()'),1,'Arrow order change is pending');
    const arrowLive=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0])).text();
    assert(arrowLive.indexOf(orgFixture.records[0].name)<arrowLive.indexOf(orgFixture.records[1].name),'Arrow move does not change public order');
    console.log('Canvas moved');
    await evaluate(`${frame}.querySelector('[data-ve-org-card="${second}"] h3').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(button=>button.title==='Move later'||button.getAttribute('aria-label')==='Move later').click()`);
    await until(()=>evaluate(`${frame}.querySelector('[data-ve-org-card]:not([hidden])')?.getAttribute('data-ve-org-card')===${JSON.stringify(String(first))}`),'Move later did not update canvas');
    assert.equal(await evaluate('window.orderParentToken'),'parent','Move later does not reload editor');
    assert.equal(await evaluate(`${frame}.defaultView.orderFrameToken`),'frame');
    assert.equal(await evaluate(`${frame}.defaultView.scrollY`),300);
    assert.equal(await evaluate('VE.adapter().dirtyCount()'),0,'Moving back clears the pending order');
    const live=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0])).text();
    assert(live.indexOf(orgFixture.records[0].name)<live.indexOf(orgFixture.records[1].name),'Order persists publicly');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await delay(350);
    await evaluate(`${frame}.querySelector('.student-org-grid').scrollIntoView({block:'start',behavior:'instant'});${frame}.defaultView.scrollBy({top:-180,behavior:'instant'})`);
    await delay(350);
    const third=orgFixture.records[2].org_id;
    async function point(id,handle){return evaluate(`(()=>{const d=${frame},e=d.querySelector('[data-ve-org-card="${id}"]${handle?' .ve-drag-handle':''}'),r=e.getBoundingClientRect(),f=document.getElementById('veFrame').getBoundingClientRect();return {x:f.left+r.left+r.width/2,y:f.top+r.top+r.height/2};})()`);}
    async function press(id){const p=await point(id,true);await send('Input.dispatchMouseEvent',{type:'mouseMoved',...p,button:'none'});await delay(80);await send('Input.dispatchMouseEvent',{type:'mousePressed',...p,button:'left',clickCount:1});await delay(450);}
    await press(first);
    assert.equal(await evaluate(`Boolean(${frame}.querySelector('.ve-drag-preview'))`),true,'Hold lifts a floating card');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',...await point(first,true),button:'left',clickCount:1});
    assert.equal(await evaluate(`Boolean(${frame}.querySelector('.ve-drag-preview'))`),false,'Escape cancels the drag');
    await delay(450);
    await press(first);
    const drop=await point(third,false);
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',...drop,button:'left',buttons:1});
    assert.equal(await evaluate(`${frame}.querySelector('.ve-drag-target')?.getAttribute('data-ve-org-card')`),String(third),'Destination card is highlighted');
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',...drop,button:'left',clickCount:1});
    await until(()=>evaluate(`${frame}.querySelector('[data-ve-org-card]:not([hidden])')?.getAttribute('data-ve-org-card')===${JSON.stringify(String(third))}&&!VE.state.busy`),'Drag swap was not saved');
    const swapped=await evaluate(`Array.from(${frame}.querySelectorAll('[data-ve-org-card]:not([hidden])'),e=>Number(e.getAttribute('data-ve-org-card')))`);
    assert.deepEqual(swapped.slice(0,3),[third,second,first],'Only source and destination switch positions');
    assert.equal(await evaluate(`${frame}.defaultView.orderFrameToken`),'frame','Drag does not reload iframe');
    assert.equal(await evaluate('VE.adapter().dirtyCount()'),1,'Drag order waits for Publish');
    const stillLive=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0])).text();
    assert(stillLive.indexOf(orgFixture.records[0].name)<stillLive.indexOf(orgFixture.records[2].name),'Drag does not change public order');
    await evaluate("document.getElementById('veSaveDraft').click()");
    await until(()=>evaluate("!VE.state.busy&&document.getElementById('ceToasts').textContent.includes('Draft saved')"),'Order draft not saved');
    await evaluate(`document.getElementById('veFrame').src=document.getElementById('veFrame').src`);
    await until(()=>evaluate(`!${frame}?.defaultView.orderFrameToken&&${frame}?.querySelector('[data-ve-org-card]:not([hidden])')?.getAttribute('data-ve-org-card')===${JSON.stringify(String(third))}&&VE.adapter().draftState().pendingOrder?.filter(id=>${JSON.stringify(orgFixture.records.map(row=>row.org_id))}.includes(id))?.[0]===${third}&&document.getElementById('veLoader').hidden`),'Saved order draft was not restored');
    const restoredLive=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0])).text();
    assert(restoredLive.indexOf(orgFixture.records[0].name)<restoredLive.indexOf(orgFixture.records[2].name),'Saved and restored draft stays private');
    // Order-only publishing does not require unrelated completeness prompts in this fixture.
    await evaluate('window.ContentCompleteness=null;document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Publish confirmation missing');
    await evaluate('document.querySelector(".ve-publish-dialog .ve-publish-accept").click()');
    await until(async()=>{const html=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0])).text();return html.indexOf(orgFixture.records[2].name)<html.indexOf(orgFixture.records[1].name)&&html.indexOf(orgFixture.records[1].name)<html.indexOf(orgFixture.records[0].name);},'Publish did not apply order');
    await until(()=>evaluate('Boolean(window.VE)&&!VE.state.busy&&VE.adapter().dirtyCount()===0'),'Published draft not cleared');
    // Page assignments are derived from the staged overall order, never stored separately.
    await until(()=>evaluate(`document.getElementById('veLoader').hidden&&Boolean(${frame}?.querySelector('[data-ve-org-card="${third}"] .ve-org-page-move'))`),'Published directory reloaded');
    await evaluate(`(()=>{const link=Array.from(${frame}.querySelectorAll('.student-org-filters a')).find(a=>new URL(a.href).searchParams.get('category')==='${orgFixture.categories[0]}');link.click();})()`);
    await evaluate(`${frame}.querySelector('[data-ve-org-card="${third}"] .ve-org-page-move').click()`);
    await until(()=>evaluate("Boolean(document.querySelector('[data-org-destination-page]'))"),'Move to Page option missing');
    assert.equal(await evaluate("document.querySelector('[data-org-destination-page]').options.length"),3,'All three category pages available');
    const baselineIds=await evaluate(`JSON.parse(${frame}.getElementById('veOrgData').textContent).organizations.map(row=>Number(row.org_id))`);
    await evaluate("document.querySelector('[data-org-destination-page]').value='3';document.querySelector('[data-org-destination-page]').dispatchEvent(new Event('change'));document.querySelector('[data-org-destination-position]').value='2';document.querySelector('[data-org-apply-page-move]').click()");
    await until(()=>evaluate(`new URL(VE.state.url,location.origin).searchParams.get('p')==='3'&&Array.from(${frame}.querySelectorAll('[data-ve-org-card]:not([hidden])'))[1]?.getAttribute('data-ve-org-card')==='${third}'`),'Card did not move to page 3 position 2');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-org-card]:not([hidden])').length`),2,'Last page contains correct remainder');
    const movedOrder=await evaluate('VE.adapter().draftState().pendingOrder');
    const categoryIds=new Set(orgFixture.records.filter((row,index)=>index<7||index>=9).map(row=>Number(row.org_id)));
    for(let i=0;i<baselineIds.length;i++)if(!categoryIds.has(baselineIds[i]))assert.equal(movedOrder[i],baselineIds[i],'Other categories retain their overall slots');
    let publicFirst=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0])).text();assert(publicFirst.includes(orgFixture.records[2].name),'Cross-page move stays private');
    await evaluate(`(()=>{const link=Array.from(${frame}.querySelectorAll('.student-org-pagination a')).find(a=>a.textContent==='1');link.click();})()`);
    await until(()=>evaluate(`new URL(VE.state.url,location.origin).searchParams.get('p')==='1'&&${frame}.querySelectorAll('[data-ve-org-card]:not([hidden])').length===6`),'Page 1 refills after move');
    assert(await evaluate(`!Array.from(${frame}.querySelectorAll('[data-ve-org-card]:not([hidden])')).some(card=>card.getAttribute('data-ve-org-card')==='${third}')`),'Moved card absent from page 1 preview');
    await evaluate("document.getElementById('veSaveDraft').click()");
    await until(()=>evaluate("!VE.state.busy&&document.getElementById('veSaveDraft').disabled"),'Cross-page draft saved');
    await evaluate(`document.getElementById('veFrame').src=${JSON.stringify(base+orgFixture.url+'?category='+orgFixture.categories[0]+'&p=3&ce_preview=1')}`);
    await until(()=>evaluate(`document.getElementById('veLoader').hidden&&!VE.state.busy&&Array.from(${frame}?.querySelectorAll('[data-ve-org-card]:not([hidden])')||[])[1]?.getAttribute('data-ve-org-card')==='${third}'`),'Shared draft restored when opening page 3');
    await evaluate('window.ContentCompleteness=null;document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Cross-page publish confirmation');await evaluate('document.querySelector(".ve-publish-accept").click()');
    await until(async()=>{const html=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0]+'&p=3')).text();return html.includes(orgFixture.records[2].name);},'Published card appears on page 3');
    await until(()=>evaluate('Boolean(window.VE)&&!VE.state.busy&&VE.adapter().dirtyCount()===0&&document.getElementById("veLoader").hidden'),'Cross-page publication completed');
    publicFirst=await(await fetch(base+orgFixture.url+'?category='+orgFixture.categories[0])).text();assert(!publicFirst.includes(orgFixture.records[2].name),'Published page 1 no longer contains moved card');
    // Mobile dropdown moves the same card back; no drag gesture is required.
    await until(()=>evaluate(`Boolean(${frame}?.querySelector('[data-ve-org-card="${third}"] .ve-org-page-move'))`),'Published directory ready for mobile move');
    await evaluate(`(()=>{const link=Array.from(${frame}.querySelectorAll('.student-org-filters a')).find(a=>new URL(a.href).searchParams.get('category')==='${orgFixture.categories[0]}');link.click();})()`);
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:900,deviceScaleFactor:1,mobile:true});
    await evaluate(`${frame}.querySelector('[data-ve-org-card="${third}"] .ve-org-page-move').click()`);
    await until(()=>evaluate("Boolean(document.querySelector('[data-org-destination-page]'))"),'Mobile move dropdown');
    const popBounds=await evaluate("(()=>{const r=document.querySelector('[data-org-destination-page]').closest('.ve-pop-form').getBoundingClientRect();return {left:r.left,right:r.right,width:innerWidth};})()");assert(popBounds.left>=0&&popBounds.right<=popBounds.width+1,'Mobile move controls fit screen');
    await evaluate("document.querySelector('[data-org-destination-page]').value='1';document.querySelector('[data-org-destination-page]').dispatchEvent(new Event('change'));document.querySelector('[data-org-destination-position]').value='3';document.querySelector('[data-org-apply-page-move]').click()");
    await until(()=>evaluate(`Array.from(${frame}.querySelectorAll('[data-ve-org-card]:not([hidden])'))[2]?.getAttribute('data-ve-org-card')==='${third}'`),'Mobile move to page 1 position 3');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-org-card]:not([hidden])').length`),6);
    assert(await evaluate(`(()=>{const ids=VE.adapter().draftState().pendingOrder;return new Set(ids).size===ids.length;})()`),'Moves never duplicate records');
    const rejected=await fetch(base+'/admin/action/student-organizations-handler.php',{method:'POST',body:new URLSearchParams({action:'reorder',order:JSON.stringify([first,third])})});
    assert.equal(rejected.status,403,'Anonymous reorders are rejected');
    console.log('PASS: drag/arrows, cross-page insertion and shifting, three destination pages, private pagination preview, shared draft reload, explicit publication, mobile destination selection, stable page sizes and unique records.');

})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(orgFixture)try{fixture('cleanup',orgFixture.prefix);}catch(error){console.error(error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-org-order-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
