// Exercises real pointer gestures in the editor; no administration records are changed.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-admin-drag-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(100);}throw Error(message);}
let socket,session,fixtureId,requestApi;
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
    session=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php','session'],{encoding:'utf8',windowsHide:true}));
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Network.enable');await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*']});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=administration'});
    await until(()=>evaluate("document.getElementById('veFrame')?.contentDocument?.querySelectorAll('[data-ve-person] .ve-drag-handle').length>=3"),'Administration drag handles unavailable');
    assert.equal(await evaluate("(()=>{const d=document.getElementById('veFrame').contentDocument;return d.documentElement.classList.contains('gnc-editor-preview')&&d.defaultView.getComputedStyle(d.querySelector('.admin-open')).display==='none'&&d.defaultView.getComputedStyle(d.querySelector('.reveal-up')).transitionDuration==='0s';})()"),true,'Editor preview disables opening overlays and reveal transitions');
    const ids=()=>evaluate("Array.from(document.getElementById('veFrame').contentDocument.querySelector('[data-ve-grid=people]').querySelectorAll('[data-ve-person]')).map(card=>Number(card.dataset.vePerson))");
    const original=await ids();
    async function positions(){
        await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-person]').scrollIntoView({block:'center',behavior:'instant'})");
        return evaluate("(()=>{const f=document.getElementById('veFrame'),offset=f.getBoundingClientRect(),cards=Array.from(f.contentDocument.querySelector('[data-ve-grid=people]').querySelectorAll('[data-ve-person]'));return cards.slice(0,2).map(card=>{const r=card.querySelector('.ve-drag-handle').getBoundingClientRect();return {x:offset.left+r.left+r.width/2,y:offset.top+r.top+r.height/2};});})()");
    }
    async function gesture(hold){
        const [from,to]=await positions();await send('Input.dispatchMouseEvent',{type:'mouseMoved',...from});await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',buttons:1,clickCount:1,...from});
        let before;
        if(hold){await delay(450);
            assert.equal(await evaluate("document.getElementById('veFrame').contentDocument.querySelectorAll('.ve-drag-preview.is-lifted').length"),1,'Holding shows a lifted preview of the card');
            assert.equal(await evaluate("document.getElementById('veFrame').contentDocument.querySelectorAll('.ve-drag-preview [data-ve-person]').length"),0,'Preview cannot become a duplicate editable card');
            before=await evaluate("document.getElementById('veFrame').contentDocument.querySelector('.ve-drag-preview').getBoundingClientRect().left");
        }
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',buttons:1,...to});
        if(hold){const after=await evaluate("document.getElementById('veFrame').contentDocument.querySelector('.ve-drag-preview').getBoundingClientRect().left");assert.ok(Math.abs(after-before-(to.x-from.x))<2,'Held card follows the pointer');}
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',buttons:0,clickCount:1,...to});
        if(hold){await delay(250);assert.equal(await evaluate("document.getElementById('veFrame').contentDocument.querySelectorAll('.ve-drag-preview').length"),0,'Drop animation removes the floating preview');}
    }
    await gesture(false);assert.deepEqual(await ids(),original,'Moving before hold threshold must not reorder');
    assert.equal(await evaluate('window.VE.adapter().dirtyCount()'),0);
    await gesture(true);
    const expected=[original[1],original[0],...original.slice(2)];assert.deepEqual(await ids(),expected,'Holding and dropping swaps only the two chosen cards');
    assert.equal(await evaluate('window.VE.adapter().dirtyCount()'),1,'Order waits for Publish');
    assert.equal(await evaluate("document.getElementById('vePrimary').disabled"),false);
    await delay(450);await gesture(true);assert.deepEqual(await ids(),original,'Swapping back restores original order');
    assert.equal(await evaluate('window.VE.adapter().dirtyCount()'),0,'Restoring original order clears pending reorder');
    await evaluate("document.getElementById('vePreviewBtn').click()");
    assert.equal(await evaluate("getComputedStyle(document.getElementById('veFrame').contentDocument.querySelector('.ve-drag-handle')).display"),'none','Drag controls are hidden in Preview');
    requestApi=async fields=>{const response=await fetch(base+'/admin/action/page-content-handler.php',{method:'POST',headers:{Cookie:session.name+'='+session.id},body:new URLSearchParams({csrf_token:session.csrf,...fields})});const result=await response.json();assert.equal(result.success,true,result.error);return result;};
    const section=await evaluate('window.VE_CONFIG.admin.sections[0].key');
    const fixtureName='Archive sync test '+Date.now();
    const created=await requestApi({action:'admin_save_person',person_id:0,section_key:section,name:fixtureName,role:'Temporary test person'});
    fixtureId=created.sections.find(item=>item.key===section).people.find(person=>person.name===fixtureName).id;
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=administration'});
    await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-person="${fixtureId}"] .ve-drag-handle'))`),'Temporary archive test card unavailable');
    await evaluate(`document.getElementById('adm-meta-title').value='Unsaved heading test';window.AdminDialogs.confirm=async()=>true;document.getElementById('veFrame').contentDocument.querySelector('[data-ve-person="${fixtureId}"]').click();document.querySelector('#veTb [aria-label="Hide from the website (keeps it)"]').click()`);
    await until(()=>evaluate(`Boolean(document.querySelector('#adm-archived-box .adm-archived[data-id="${fixtureId}"]'))`),'Canvas archive did not update More settings');
    assert.equal(await evaluate("document.getElementById('adm-meta-title').value"),'Unsaved heading test','Archive synchronization preserves unsaved settings headings');
    assert.equal(await evaluate('document.getElementById("adm-archived-box").open'),true,'Newly archived card is immediately visible in Archived');
    await until(()=>evaluate(`!document.getElementById('veFrame').contentDocument.querySelector('[data-ve-person="${fixtureId}"]')`),'Archived card remained on canvas');
    await evaluate(`document.getElementById('veSettingsBtn').click();document.querySelector('#adm-archived-box [data-id="${fixtureId}"] [data-act=restore]').click()`);
    await until(()=>evaluate(`Boolean(document.querySelector('#adm-list .adm-item[data-id="${fixtureId}"]'))`),'Restoring did not update settings list');
    await evaluate("document.querySelector('#veDrawer [data-ve-drawer-close]').click()");
    await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-person="${fixtureId}"]'))`),'Restored card did not return to canvas');
    console.log('PASS: animated drag swaps and pending order; archive synchronizes into More settings without losing heading edits; restoring returns to canvas. Existing administration records unchanged.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(fixtureId&&requestApi)try{await requestApi({action:'admin_delete_person',person_id:fixtureId});}catch(error){console.error('Temporary person cleanup failed:',error.message);process.exitCode=1;}
    if(session)try{execFileSync('docker',['compose','exec','-T','web','php','tests/student-organizations-test.php','destroy_session',session.id],{windowsHide:true,stdio:'pipe'});}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-admin-drag-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
