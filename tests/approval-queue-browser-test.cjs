// Generate the sample panel first: docker compose exec -T web php tests/approval-view-test.php --preview
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const docker = args => {
    const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if(r.status!==0) throw Error(r.stderr||r.stdout);
    return r.stdout;
};
const authorRole=process.argv.includes('--content-editor')?'Content Editor':'The Mirror';
const sessions = [];
const session = role => {const s=JSON.parse(docker([role]));sessions.push(s);return s;};
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-logs-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket;
let cleanupDrafts=async()=>{};
(async()=>{
    const admin=session('Super Admin');
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page load timeout');};
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await visit('/admin/submissions.php');
    await until(()=>evaluate("Boolean(document.getElementById('approval-filters'))"),'Approvals unavailable');
    assert.equal(await evaluate("Boolean(document.querySelector('.approval-page-text'))"),false);
    assert.equal(await evaluate("Array.from(document.querySelectorAll('[role=alert]')).some(e=>e.textContent.includes('queue could not'))"),false);
    const redirect=await evaluate("fetch('/admin/content-edit.php?page=review').then(r=>r.url)");
    assert.ok(redirect.includes('status=pending')&&!redirect.includes('content-editor'),'Old review URL did not open combined queue');
    await evaluate(`(async()=>{window.__sectionPreview=${JSON.stringify(fs.readFileSync('cache/unified-section-preview.html','utf8'))};const original=window.fetch;window.fetch=function(url,options){if(String(url).includes('__section_preview=1'))return Promise.resolve(new Response(__sectionPreview,{headers:{'Content-Type':'text/html'}}));return original.apply(this,arguments);};const link=document.createElement('a');link.href='/admin/submissions.php?id=1&source=section&__section_preview=1';link.setAttribute('data-review-open','');link.id='section-preview-link';link.textContent='Sample section';document.querySelector('.approval-page').append(link);link.click();})()`);
    await until(()=>evaluate("document.getElementById('approval-dialog').open"),'Section review modal unavailable');
    assert.ok(await evaluate("document.getElementById('approval-dialog').textContent.includes('Current published text')"));
    assert.ok(await evaluate("document.getElementById('approval-dialog').textContent.includes('Proposed text')"));
    await evaluate("document.querySelector('#approval-dialog .approval-review-actions form button').click()");
    await until(()=>evaluate("Boolean(document.getElementById('publish-password'))"),'Page-text approval did not require password');
    await evaluate("document.querySelector('.gnc-dialog-cancel').click()");
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);
        assert.ok(await evaluate("document.getElementById('approval-dialog').scrollWidth<=innerWidth"),'Review modal overflow');
        fs.writeFileSync(`cache/unified-review-${width}.png`,Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await evaluate("document.querySelector('#approval-dialog [data-review-close]').click()");
    assert.equal(await evaluate("document.getElementById('approval-dialog').open"),false);
    const mirror=session(authorRole);
    await send('Network.setCookie',{...mirror,url:base,httpOnly:true});
    await visit('/admin/submissions.php?view=drafts');
    await until(()=>evaluate("document.querySelector('h1')?.textContent==='Drafts'"),'Private Drafts view unavailable');
    assert.ok(await evaluate("Boolean(document.querySelector('.gnc-sidebar a[href*=\"view=drafts\"]'))"),'Drafts navigation missing');
    assert.equal(await evaluate("Boolean(document.querySelector('.approval-overview'))"),false,'Drafts should not show approval counts');
    assert.equal(await evaluate("document.querySelector('input[name=view]').value"),'drafts');
    for(const route of ['/admin/news.php','/admin/announcement.php']){
        const defaults=await evaluate(`fetch('${route}').then(r=>r.text()).then(html=>{const d=new DOMParser().parseFromString(html,'text/html');return {status:d.querySelector('#modal-status')?.value,version:!!d.querySelector('[name=_review_revision]')}})`);
        assert.equal(defaults.status,'draft','New content must default to saving privately');
        assert.equal(defaults.version,true,'Draft edit revision protection missing');
    }
    const fixtureIds=[];
    cleanupDrafts=async()=>{
        await send('Network.setCookie',{...mirror,url:base,httpOnly:true});
        for(const id of fixtureIds){
            const removed=await evaluate(`(async()=>{for(const view of ['drafts','submissions']){const r=await fetch('/admin/submissions.php?view='+view+'&id=${id}');if(!r.ok)continue;const d=new DOMParser().parseFromString(await r.text(),'text/html');const button=d.querySelector('#submission-review form input[name=action][value=delete]');if(!button)continue;const form=button.closest('form');const reply=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form)});return reply.ok;}return false;})()`);
            assert.ok(removed,'Could not clean up private browser fixture '+id);
        }
    };
    for(const [route,formId] of [['/admin/news.php','news-form'],['/admin/announcement.php','announcement-form']]){
        await visit(route+'?modal=create');
        await until(()=>evaluate(`Boolean(document.querySelector('#${formId}'))`),'Article form unavailable');
        const title=authorRole+' draft browser fixture '+Date.now();
        const saved=await evaluate(`(async()=>{const form=document.getElementById('${formId}');const data=new FormData(form);data.set('title',${JSON.stringify(title)});data.set('content','<p>First private version</p>');data.set('status','draft');const r=await fetch(form.getAttribute('action'),{method:'POST',body:data});return {url:r.url,ok:r.ok,html:await r.text()};})()`);
        const id=new URL(saved.url).searchParams.get('id');
        assert.ok(id&&saved.ok,'Draft was not saved at '+saved.url+': '+saved.html.slice(0,300));fixtureIds.push(id);
        assert.equal(new URL(saved.url).searchParams.get('view'),'drafts','Draft save redirected into submissions');
        await visit('/admin/submissions.php?view=drafts&id='+id);
        await until(()=>evaluate("Boolean(document.querySelector('#submission-review a[href*=revise]'))"),'Draft edit action missing');
        const edit=await evaluate("document.querySelector('#submission-review a[href*=revise]').getAttribute('href')");
        await visit(edit);
        await until(()=>evaluate(`document.querySelector('#${formId} [name=_review_id]')?.value==='${id}'`),'Draft identity was not restored in editor');
        assert.equal(await evaluate("document.getElementById('modal-status').value"),'draft','Editing draft selected submission');
        for(let revision=1;revision<=2;revision++){
            const updated=await evaluate(`(async()=>{const form=document.getElementById('${formId}');const data=new FormData(form);data.set('content','<p>Private version ${revision+1}</p>');data.set('status','draft');const r=await fetch(form.getAttribute('action'),{method:'POST',body:data});return {url:r.url,ok:r.ok,html:await r.text()};})()`);
            assert.equal(new URL(updated.url).searchParams.get('id'),id,'Repeated save created another draft');
            assert.equal(new URL(updated.url).searchParams.get('view'),'drafts');
            await visit(edit);
            await until(()=>evaluate(`document.querySelector('#${formId} [name=_review_revision]')?.value==='${revision+1}'`),'Latest draft revision not restored');
        }
        assert.ok(await evaluate(`fetch('/admin/submissions.php').then(r=>{if(!r.ok)throw Error('Submission list failed: '+r.status);return r.text();}).then(html=>!Array.from(new DOMParser().parseFromString(html,'text/html').querySelectorAll('.approval-row-title')).some(el=>el.textContent===${JSON.stringify(title)}))`),'Draft leaked to My Submissions');
        await send('Network.setCookie',{...admin,url:base,httpOnly:true});
        const forbidden=await evaluate(`fetch('/admin/submissions.php?id=${id}').then(async r=>({status:r.status,url:r.url,body:(await r.text()).slice(0,600)}))`);
        assert.equal(forbidden.status,404,'Super Admin draft response: '+JSON.stringify(forbidden));
        assert.equal(await evaluate(`fetch('/admin/submissions.php?view=drafts&id=${id}').then(r=>r.status)`),404,'Super Admin accessed private Drafts');
        await send('Network.setCookie',{...mirror,url:base,httpOnly:true});
        await visit('/admin/submissions.php?view=drafts&id='+id);
        await until(()=>evaluate("Boolean(document.querySelector('#submission-review input[name=action][value=submit]'))"),'Explicit draft submit action unavailable');
        const submitted=await evaluate(`(async()=>{const form=document.querySelector('#submission-review input[name=action][value=submit]').closest('form');const r=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form)});return {url:r.url,ok:r.ok,status:r.status,body:(await r.text()).slice(0,500)};})()`);
        assert.ok(submitted.ok,JSON.stringify(submitted));assert.equal(new URL(submitted.url).searchParams.get('view'),'submissions');
        await send('Network.setCookie',{...admin,url:base,httpOnly:true});
        const reviewable=await evaluate(`fetch('/admin/submissions.php?id=${id}').then(r=>r.text()).then(html=>{const d=new DOMParser().parseFromString(html,'text/html');return !!d.querySelector('#submission-review input[name=action][value=approve]')&&d.querySelector('#submission-review').textContent.includes('Private version 3')})`);
        assert.equal(reviewable,true,'Explicitly submitted latest draft did not reach Approvals');
        await send('Network.setCookie',{...mirror,url:base,httpOnly:true});
        if(authorRole==='Content Editor'){
            const editSelector=formId==='news-form'?'.btn-edit-news':'.btn-edit-announcement';
            await visit(route+'?status=published');
            await until(()=>evaluate(`Boolean(document.querySelector('${editSelector}[data-status=published]'))`),'Published content fixture unavailable');
            const original=await evaluate(`(()=>{const b=document.querySelector('${editSelector}[data-status=published]');return {id:b.dataset.id,title:b.dataset.title,status:b.dataset.status,content:document.getElementById(b.dataset.contentId).innerHTML};})()`);
            const readPublished=()=>evaluate(`fetch('${route}?status=published').then(r=>{if(!r.ok)throw Error('Published list unavailable');return r.text();}).then(html=>{const d=new DOMParser().parseFromString(html,'text/html'),b=d.querySelector('${editSelector}[data-id="${original.id}"]');return b?{id:b.dataset.id,title:b.dataset.title,status:b.dataset.status,content:d.getElementById(b.dataset.contentId).innerHTML}:null;})`);
            await evaluate(`document.querySelector('${editSelector}[data-id="${original.id}"]').click()`);
            await until(()=>evaluate(`document.querySelector('#${formId} [name=${formId==='news-form'?'news_id':'announcement_id'}]')?.value==='${original.id}'`),'Published edit form unavailable');
            assert.equal(await evaluate("document.getElementById('modal-status').value"),'draft','Published edit must default to private draft');
            const edited=await evaluate(`(async()=>{const form=document.getElementById('${formId}'),data=new FormData(form);data.set('content','<p>Private proposed published edit</p>');const r=await fetch(form.getAttribute('action'),{method:'POST',body:data});return {url:r.url,ok:r.ok};})()`);
            const editId=new URL(edited.url).searchParams.get('id');
            assert.ok(edited.ok&&editId,'Published edit draft save failed');fixtureIds.push(editId);
            assert.equal(new URL(edited.url).searchParams.get('view'),'drafts');
            assert.deepEqual(await readPublished(),original,'Saving draft changed published content');
            await visit('/admin/submissions.php?view=drafts&id='+editId);
            await until(()=>evaluate("Boolean(document.querySelector('#submission-review input[name=action][value=submit]'))"),'Published-edit draft unavailable');
            const result=await evaluate(`(async()=>{const form=document.querySelector('#submission-review input[name=action][value=submit]').closest('form');const r=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form)});return {url:r.url,ok:r.ok};})()`);
            assert.ok(result.ok);assert.equal(new URL(result.url).searchParams.get('view'),'submissions');
            assert.deepEqual(await readPublished(),original,'Submitting edit changed published content before approval');
            await send('Network.setCookie',{...admin,url:base,httpOnly:true});
            assert.equal(await evaluate(`fetch('/admin/submissions.php?id=${editId}').then(r=>r.text()).then(html=>{const d=new DOMParser().parseFromString(html,'text/html');return !!d.querySelector('#submission-review input[name=action][value=approve]')&&d.querySelector('#submission-review').textContent.includes('Private proposed published edit')})`),true,'Edited version did not require Super Admin approval');
            await send('Network.setCookie',{...mirror,url:base,httpOnly:true});
        }
    }
    await visit('/admin/submissions.php');
    await until(()=>evaluate("document.querySelector('h1')?.textContent==='My Submissions'"),'My Submissions unavailable');
    assert.equal(await evaluate("Boolean(document.querySelector('#approval-status option[value=draft]'))"),false,'Submission filters expose drafts');
    console.log(authorRole+': Combined Approvals loads; separate banner removed; old review links redirect; sample section opens in same review dialog; approval requires password; cancellation/close and desktop/mobile layouts verified. Private News and Announcement drafts were saved, edited twice, explicitly submitted and cleaned up; no public content changed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{
    try{await cleanupDrafts();}catch(error){console.error(error);process.exitCode=1;}
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});