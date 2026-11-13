// Uses the local editor; content, password, photo-check and draft replies are mocked.
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const sessions=[];
function docker(args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;}
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-administration-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+20000;Date.now()<end;){if(await fn())return;await delay(80);}throw Error(message);}
let socket;
function intercept(snapshot=null,review=false){return `window.__snap=${JSON.stringify(snapshot)};window.__writes=[];window.__pw=0;window.__submitted=0;window.__approved=0;window.__checks=0;window.__saves=0;
let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config;},set(v){config=v;if(${review})v.reviewSubmission=123;window.__sections=structuredClone(v.admin.sections);}});
const original=window.fetch;window.fetch=async function(url,opts){
const target=String(url),body=opts?.body,action=body?.get?.('action');const reply=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
if(target.includes('publish-confirmation-handler')){if(action==='end')return reply({success:true});__pw++;return body.get('password')==='qa-correct'?reply({success:true,token:'a'.repeat(64)}):reply({success:false,error:'Incorrect password.'},422);}
if(target.includes('photo-check-handler')){__checks++;return reply({success:true,checked:true,ok:true,reason:'clear'});}
if(target.includes('editor-draft-handler')){if(action==='save'){__saves++;if(window.__failDraft)return reply({success:false,error:'Draft storage test failure.'},503);__snap=body.get('snapshot');}if(action==='delete')__snap=null;if(action==='submit_review')__submitted++;if(action==='complete_review')__approved++;return reply({success:true,revision:__snap?1:0,draft:action==='load'&&__snap?{revision:1,snapshot:JSON.parse(__snap)}:null,submission_id:123});}
if(target.includes('page-content-handler')&&opts?.method==='POST'){
__writes.push({action,id:Number(body.get('person_id')||body.get('office_id')),name:body.get('name')||body.get('head_name'),section:body.get('section_key'),photo:body.get('photo')?.size||0,confirmed:new Headers(opts.headers).has('X-GNCI-Publish-Token')});
if(window.__failName===body.get('name')){window.__failName=null;return reply({success:false,error:'Simulated personnel save failure.'},422);}
const id=9000+__writes.length,kind=action==='admin_save_office'?'office':'person',section=__sections.find(s=>s.key===body.get('section_key'));
if(section&&action.startsWith('admin_save_')&&kind){section[kind==='office'?'offices':'people'].push({id,name:body.get('name')||body.get('head_name'),role:body.get('role')||body.get('head_role'),office:body.get('office_name'),contact:{},services:[],image:''});}
return reply({success:true,person_id:id,office_id:id,sections:__sections});}
return original.apply(this,arguments);};`;}
(async()=>{
await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0],tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();
socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
let serial=0;const pending=new Map();socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
await send('Page.enable');await send('Network.enable');
let injected;
async function visit(section,snapshot=null,review=false){
if(injected)await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:injected.identifier});
injected=await send('Page.addScriptToEvaluateOnNewDocument',{source:intercept(snapshot,review)});
await send('Page.navigate',{url:'http://localhost:8080/admin/content-edit.php?page=administration&section='+section});
await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-add]'))&&document.getElementById('veLoader').hidden`),'Administration editor unavailable');
await evaluate(`window.AdminDialogs.confirm=async()=>true;window.ContentCompleteness=undefined;window.doc=()=>document.getElementById('veFrame').contentDocument;`);
await delay(500);
}
async function open(kind){await evaluate(`doc().querySelector('[data-ve-add="${kind}"]').click()`);await until(()=>evaluate(kind==='office'?`document.getElementById('admOfficeModal').classList.contains('show')&&!bootstrap.Modal.getInstance(document.getElementById('admOfficeModal'))._isTransitioning`:`!document.getElementById('vePop').hidden`),'Add form unavailable');}
async function fill(name,office=false){await evaluate(office?`document.getElementById('adm-office-name').value='QA office';document.getElementById('adm-office-head').value=${JSON.stringify(name)};document.getElementById('adm-office-role').value='QA position';`:`(()=>{const inputs=document.querySelectorAll('#vePop input');inputs[0].value=${JSON.stringify(name)};inputs[1].value='QA position';})()`);}
async function upload(){const office=await evaluate(`document.getElementById('admOfficeModal').classList.contains('show')`);if(!office)await evaluate(`Array.from(document.querySelectorAll('#vePop button')).find(b=>b.textContent==='Choose photo').click()`);
await evaluate(`(()=>{const input=document.getElementById('${office?'adm-office-photo':'veFile'}'),dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='),c=>c.charCodeAt(0))],'qa.png',{type:'image/png'}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
await until(()=>evaluate(`Boolean(document.querySelector('.image-frame-save'))`),'Image framing unavailable');await evaluate(`document.querySelector('.image-frame-save').dispatchEvent(new MouseEvent('mousedown',{bubbles:true}));document.querySelector('.image-frame-save').click()`);
await until(()=>evaluate(office?`document.getElementById('adm-office-preview').src.startsWith('data:image')`:`document.querySelector('#vePop small')?.textContent.includes('qa.png')`),'Photo did not return to add form');}
async function add(office=false){await until(()=>evaluate(`!document.getElementById('veStatus').classList.contains('ve-status-busy')`),'Previous save did not finish');await evaluate(office?`document.getElementById('adm-office-form').requestSubmit();document.getElementById('adm-office-form').requestSubmit();`:`(()=>{const b=Array.from(document.querySelectorAll('#vePop button')).find(b=>/Add to page|Add office/.test(b.textContent));b.click();b.click();})()`);
await until(()=>evaluate(`${office?"!document.getElementById('admOfficeModal').classList.contains('show')":"document.getElementById('vePop').hidden"}&&Boolean(__snap)&&!document.getElementById('vePrimary').disabled`),'Addition did not save');assert.equal(await evaluate('__pw'),0);assert.deepEqual(await evaluate('__writes'),[]);
await until(()=>evaluate(`(()=>{const img=doc().querySelector('[data-ve-person^="-"] [data-ve-img="person-photo"],[data-ve-office^="-"] [data-ve-img="office-photo"]');return img&&img.complete&&img.naturalWidth>0&&Number(doc().defaultView.getComputedStyle(img).opacity)>.99;})()`),'Draft portrait loaded but remained hidden behind the skeleton');}
async function publish(){await evaluate(`document.getElementById('vePrimary').click()`);await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept'))`),'Publish confirmation unavailable');await evaluate(`document.querySelector('.ve-publish-accept').click()`);}
async function password(){await until(()=>evaluate(`Boolean(document.getElementById('publish-password'))`),'Password prompt missing');await evaluate(`document.getElementById('publish-password').value='qa-correct';document.getElementById('publish-password').form.requestSubmit()`);}
const keys=['board-of-trustees','corporate-officers','board-of-administration','heads-of-student-services'];
let reviewSnapshot;
for(const role of (process.env.ADMIN_DRAFT_FOCUS?[]:['Super Admin','Content Editor'])){
const session=JSON.parse(docker([role]));sessions.push(session);await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});
for(const [index,key] of keys.entries()){
if(process.env.ADMIN_DRAFT_OFFICE_ONLY&&index!==3)continue;
const office=index===3;await visit(key);await open(office?'office':'person');
await evaluate(office?`document.getElementById('adm-office-form').requestSubmit()`:`Array.from(document.querySelectorAll('#vePop button')).find(b=>/Add to page|Add office/.test(b.textContent)).click()`);
assert.equal(await evaluate('__saves'),0,'Blank form saved');assert.equal(await evaluate('__pw'),0);
if(index%2===0)await upload();await fill('QA '+key,office);if(index%2===1)await upload();
if(office&&role==='Super Admin'){
await evaluate(`window.__failDraft=true;document.getElementById('adm-office-form').requestSubmit()`);
await until(()=>evaluate(`document.getElementById('adm-office-error').textContent.includes('Draft storage test failure.')&&!document.getElementById('veStatus').classList.contains('ve-status-busy')`),'Office draft failure was not shown inline');
assert.equal(await evaluate(`document.getElementById('adm-office-name').value`),'QA office');assert.equal(await evaluate(`document.getElementById('adm-office-head').value`),'QA '+key);assert.equal(await evaluate(`document.getElementById('adm-office-photo').files.length`),1,'Failed save lost the selected photo');
await evaluate(`window.__failDraft=false`);
}
await add(office);assert.equal(await evaluate('__saves'),office&&role==='Super Admin'?2:1,'Double click created two drafts');
const snapshot=await evaluate('__snap');assert.equal(Object.keys(JSON.parse(snapshot).state.additions).length,1);assert.ok(JSON.parse(snapshot).files.length>0);
assert.equal(await evaluate(`doc().querySelectorAll('[data-ve-${office?'office':'person'}^="-"]').length`),1,'Draft missing from canvas');
await visit(key,snapshot);assert.equal(await evaluate(`doc().querySelectorAll('[data-ve-${office?'office':'person'}^="-"]').length`),1,'Refresh lost draft card');
assert.equal(await evaluate(`doc().querySelector('[data-ve-${office?'office':'person'}^="-"] ${office?'.hss-badge-title':'[data-ve-field="person-name"]'}').textContent`),'QA '+key,'Refresh overwrote the personnel name');
await until(()=>evaluate(`(()=>{const img=doc().querySelector('[data-ve-${office?'office':'person'}^="-"] [data-ve-img="${office?'office':'person'}-photo"]'),wrap=img?.closest('.skeleton-wrap');return img?.complete&&img.naturalWidth>0&&Number(doc().defaultView.getComputedStyle(img).opacity)>.99&&(!wrap||wrap.classList.contains('is-loaded'));})()`),'Restored portrait stayed invisible');
if(role==='Content Editor'){await evaluate(`document.getElementById('vePrimary').click()`);await until(()=>evaluate('__submitted===1'),'Approval snapshot not submitted');assert.equal(await evaluate('__pw'),0);assert.deepEqual(await evaluate('__writes'),[]);if(index===0)reviewSnapshot=await evaluate('__snap');}
else {await publish();await password();await until(()=>evaluate('__writes.length===1'),'Personnel publication failed');assert.ok((await evaluate('__writes'))[0].confirmed);assert.ok((await evaluate('__writes'))[0].photo>0);}
console.log(role+': '+key+' validation, photo, draft restore and publish/approval passed.');
}
}
if(process.env.ADMIN_DRAFT_OFFICE_ONLY){console.log('PASS: shared office modal, photo preservation on failed saves, duplicate-submission guard, refresh and both publishing roles.');return;}
if(!sessions.length)sessions.push(JSON.parse(docker(['Super Admin'])));
await send('Network.setCookie',{...sessions[0],url:'http://localhost:8080',httpOnly:true});
if(reviewSnapshot){await visit(keys[0],reviewSnapshot,true);await publish();await password();await until(()=>evaluate('__approved===1'),'Approval did not complete');}
// Failed draft saves keep the form and permit one clean retry.
await visit(keys[0]);await open('person');await fill('QA retry');await evaluate('window.__failDraft=true');
await evaluate(`Array.from(document.querySelectorAll('#vePop button')).find(b=>b.textContent==='Add to page').click()`);
await until(()=>evaluate(`document.body.textContent.includes('Draft storage test failure.')`),'Draft error feedback missing');assert.equal(await evaluate(`doc().querySelectorAll('[data-ve-person^="-"]').length`),0);
await evaluate('window.__failDraft=false');await add();assert.equal(Object.keys(JSON.parse(await evaluate('__snap')).state.additions).length,1);
// The settings form uses the same draft flow; name-only additions retain optional role/photo.
await visit(keys[0]);await evaluate(`document.getElementById('adm-add').click();document.getElementById('adm-person-name').value='QA settings';document.getElementById('adm-person-form').requestSubmit()`);
await until(()=>evaluate(`Boolean(__snap)&&!document.getElementById('admPersonModal').classList.contains('show')`),'Settings addition did not save');
assert.equal(await evaluate('__pw'),0);assert.deepEqual(await evaluate('__writes'),[]);assert.equal(await evaluate(`doc().querySelectorAll('[data-ve-person^="-"]').length`),1);
// Switching sections preserves the same collection draft and its original card.
// Older snapshots included the section wrapper order. A different tab's grid
// cannot match, so replaying that order used to append the intro below the grid.
const legacyLayout=JSON.parse(await evaluate('__snap'));
legacyLayout.preview.orders.push({selector:'.admin-section-inner',children:['.admin-section-intro','[data-ve-grid][data-ve-key="board-of-trustees"]']});
await visit(keys[1],JSON.stringify(legacyLayout));
assert.equal(await evaluate(`doc().querySelector('.admin-section-inner').firstElementChild.classList.contains('admin-section-intro')`),true,'Cross-tab draft moved the heading below the cards');
assert.ok(await evaluate(`(()=>{const intro=doc().querySelector('.admin-section-intro'),grid=doc().querySelector('[data-ve-grid]');return intro.getBoundingClientRect().bottom<=grid.getBoundingClientRect().top;})()`),'Section heading must render above the card grid');
await visit(keys[0],JSON.stringify(legacyLayout));
await evaluate(`doc().querySelector('a[href*="section=corporate-officers"]').click()`);
await until(()=>evaluate(`doc().querySelector('[data-ve-grid]')?.getAttribute('data-ve-key')==='corporate-officers'&&document.getElementById('veLoader').hidden`),'Tab switch failed');
await open('person');await fill('QA second section');await add();
assert.equal(Object.keys(JSON.parse(await evaluate('__snap')).state.additions).length,2,'Switching sections lost a draft');
await evaluate(`doc().querySelector('a[href*="section=board-of-trustees"]').click()`);
await until(()=>evaluate(`doc().querySelector('[data-ve-person^="-"]')?.textContent.includes('QA settings')&&document.getElementById('veLoader').hidden`),'Returning to first section lost its draft');
// A successful insert is removed from pending edits before retrying failed publication.
await visit(keys[0]);await open('person');await fill('QA first');await add();await open('person');await fill('QA fails once');await add();
await evaluate(`window.__failName='QA fails once'`);await publish();await password();
await until(()=>evaluate(`__writes.length===2&&!document.getElementById('vePrimary').disabled`),'Partial publish did not settle');
assert.equal(await evaluate(`doc().querySelectorAll('[data-ve-person^="-"]').length`),1,'Successful insert retained a temporary ID');
await publish();await password();await until(()=>evaluate('__writes.length===3'),'Failed insert could not be retried');
assert.deepEqual(await evaluate('__writes.map(w=>w.name)'),['QA first','QA fails once','QA fails once']);
// Empty sections use the same preview-only template, with escaped names and optional photos.
await visit(keys[0]);await evaluate(`(()=>{const heading=doc().querySelector('[data-ve-field="admin-section-title"]');heading.textContent='Unpublished heading';heading.dispatchEvent(new Event('input',{bubbles:true}));doc().querySelectorAll('[data-ve-person]').forEach(el=>el.remove());})()`);await open('person');await fill('QA <person>');await add();
assert.equal(Object.keys(JSON.parse(await evaluate('__snap')).state.edits.sec).length,1,'Adding lost unrelated pending changes');
await evaluate(`(()=>{const el=doc().querySelector('[data-ve-person^="-"] [data-ve-field="person-name"]');el.textContent='Changed';el.dispatchEvent(new Event('input',{bubbles:true}));el.textContent='QA <person>';el.dispatchEvent(new Event('input',{bubbles:true}));})()`);
assert.equal(await evaluate(`document.getElementById('vePrimary').disabled`),false,'Restoring the initial name lost a photo-less addition');
assert.equal(await evaluate(`doc().querySelector('[data-ve-person^="-"] person')`),null,'Name was inserted as markup');
await visit(keys[0],await evaluate('__snap'));assert.equal(await evaluate(`doc().querySelector('[data-ve-person^="-"] [data-ve-field="person-name"]').textContent`),'QA <person>');
// Replacing the portrait on an already-created draft must also reveal its image.
await evaluate(`doc().querySelector('[data-ve-person^="-"]').click();document.querySelector('#veTb [title="Replace the photo"]').click();(()=>{const dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='),c=>c.charCodeAt(0))],'replacement.png',{type:'image/png'}));const input=document.getElementById('veFile');input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
await until(()=>evaluate(`Boolean(document.querySelector('.image-frame-save'))`),'Replacement framing unavailable');await evaluate(`document.querySelector('.image-frame-save').click()`);
await until(()=>evaluate(`(()=>{const img=doc().querySelector('[data-ve-person^="-"] [data-ve-img="person-photo"]');return img.src.startsWith('blob:')&&img.complete&&img.naturalWidth>0&&Number(doc().defaultView.getComputedStyle(img).opacity)>.99;})()`),'Replacement portrait remained invisible');
await evaluate(`document.getElementById('veSaveDraft').click()`);await until(()=>evaluate('__saves===1'),'Replacement photo was not saved');
await visit(keys[0],await evaluate('__snap'));
await until(()=>evaluate(`(()=>{const img=doc().querySelector('[data-ve-person^="-"] [data-ve-img="person-photo"]');return img.src.startsWith('blob:')&&img.complete&&img.naturalWidth>0&&Number(doc().defaultView.getComputedStyle(img).opacity)>.99;})()`),'Replacement portrait was invisible after restoration');
console.log('PASS: Administration drafts, restored photos, all sections, both roles, approval review and failed-save retry. No real content changed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();browser.kill();for(const s of sessions)docker(['destroy',s.value]);});

