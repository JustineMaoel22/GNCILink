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
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test','program-visual-media-regression');value.page.url=u.pathname+u.search;}}});})();`});

    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=bael'});
    await until(()=>evaluate("Boolean(window.VE && VE.state.doc && !VE.state.busy && document.getElementById('veLoader').hidden && document.querySelector('#veDock .ve-thumb'))"),'Program slide editor unavailable');
    if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){
        await evaluate("window.pendingReload=true;window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");
        await until(()=>evaluate("Boolean(!window.pendingReload && window.VE && !VE.state.busy && document.getElementById('veLoader').hidden)"),'Old test draft not discarded');
    }

    assert.equal(await evaluate("Boolean(document.getElementById('veDrawer')||document.getElementById('veSettingsBtn'))"),false);
    async function typeInline(selector,text){
        await evaluate(`(()=>{const el=VE.state.doc.querySelector(${JSON.stringify(selector)});el.click();el.focus();const r=VE.state.doc.createRange();r.selectNodeContents(el);const s=VE.state.doc.defaultView.getSelection();s.removeAllRanges();s.addRange(r);})()`);
        await send('Input.insertText',{text});
    }
    await typeInline('[data-ve-key="full-name"]','Bachelor of Arts in Draft Program');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-ve-key=short-name]').textContent"),'Draft Program','Full name updates heading');
    await evaluate("(()=>{const el=VE.state.doc.querySelector('[data-ve-key=short-name]');el.textContent='';el.dispatchEvent(new Event('input',{bubbles:true}));})()");
    await typeInline('[data-ve-key="short-name"]','Inline Program');
    assert.equal(await evaluate("VE.adapter().draftState().pMeta.name"),'Bachelor of Arts in Inline Program','Heading preserves degree prefix');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-ve-key=full-name]').textContent"),'Bachelor of Arts in Inline Program');
    assert.equal(await evaluate("document.getElementById('vePop').hidden"),true,'Typing name does not open a form');
    const cardDescription=await evaluate('JSON.parse(VE.state.doc.getElementById("veProgData").textContent).description');
    assert.equal(await evaluate("Boolean(VE.state.doc.querySelector('[data-ve-field=prog-description]'))"),false,'Duplicate card description editor is absent');
    await evaluate("VE.state.doc.querySelector('[data-ve-program-section=curriculum]').click();document.getElementById('veFile').click=()=>{};[...document.querySelectorAll('#vePop button')].find(b=>b.textContent==='Upload or replace PDF').click()");
    await evaluate(`(()=>{const input=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File(['%PDF-1.4 draft fixture'],'curriculum-replacement.pdf',{type:'application/pdf'}));input.files=dt.files;input.onchange();})()`);
    await evaluate("VE.state.doc.querySelector('.about-feature-seal-wrap img').click();document.getElementById('veFile').click=()=>{};[...document.querySelectorAll('#veTb button')].find(b=>b.textContent.trim()==='Replace picture').click()");
    await evaluate(`(()=>{const input=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg=='),c=>c.charCodeAt(0))],'seal-replacement.png',{type:'image/png'}));input.files=dt.files;input.onchange();})()`);
    await evaluate("document.getElementById('vePageOpts').click();const el=document.querySelector('#vePop select');el.value=[...el.options].find(o=>o.value!==el.value).value;el.dispatchEvent(new Event('change'))");
    const dept=await evaluate('VE.adapter().draftState().pMeta.dept_id');
    await evaluate("document.getElementById('veSaveDraft').click()");
    await until(()=>evaluate("!VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved')"),'Media draft not saved');
    await evaluate('window.pendingReload=true');await send('Page.reload');
    await until(()=>evaluate("Boolean(!window.pendingReload && window.VE && !VE.state.busy && document.getElementById('veStatus').textContent.includes('Draft saved'))"),'Media draft not restored');
    assert.equal(await evaluate('VE.adapter().draftState().pPdf.file.name'),'curriculum-replacement.pdf');
    assert.equal(await evaluate('VE.adapter().draftState().pMedia.seal.file.name'),'seal-replacement.png');
    assert.equal(await evaluate("VE.state.doc.querySelector('.about-feature-seal-wrap img').src.startsWith('blob:')"),true,'Seal preview restored from draft');
    assert.equal(await evaluate('VE.adapter().draftState().pMeta.dept_id'),dept);
    assert.equal(await evaluate('VE.adapter().draftState().pMeta.name'),'Bachelor of Arts in Inline Program');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-ve-key=short-name]').textContent"),'Inline Program','Inline heading restored');
    assert.equal(await evaluate("Boolean(VE.state.doc.querySelector('[data-ve-field=prog-description]'))"),false,'Draft restoration does not restore the duplicate field');
    const requests=[];
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;requests.push(packet.params.request.postData||'');await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify({success:true,program:{}})).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*program-page-handler.php*'}]});
    await evaluate('window.ContentCompleteness=null;VE.adapter().runPrimary()');
    await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-dialog[open]'))"),'Publish confirmation missing');
    await evaluate("document.querySelector('.ve-publish-accept').click()");
    await until(()=>evaluate("Boolean(window.VE && !VE.state.busy && document.getElementById('veLoader').hidden && document.getElementById('veDiscardDraft').hidden)"),'Media publication did not finish');
    assert.equal(requests.length,2);
    const details=requests.find(p=>p.includes('save_program_details')),pdf=requests.find(p=>p.includes('save_program_pdf'));
    assert.ok(details.includes('name="seal"; filename="seal-replacement.png"'));
    assert.equal(/name="dept_id"\r?\n\r?\n([^\r\n]+)/.exec(details)[1],String(dept));
    assert.equal(/name="program_name"\r?\n\r?\n([^\r\n]+)/.exec(details)[1],'Bachelor of Arts in Inline Program');
    assert.equal(/name="description"\r?\n\r?\n([^\r\n]*)/.exec(details)[1],cardDescription||'','Program publication preserves the stored card description');
    assert.ok(pdf.includes('filename="curriculum-replacement.pdf"'));
    await send('Fetch.disable');
    console.log('PASS: sidebar absent; curriculum/seal/department edits survive private drafts and publish through existing handlers (responses intercepted; live content unchanged).');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
