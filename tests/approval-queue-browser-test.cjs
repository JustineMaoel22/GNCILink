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
    const visit=async route=>{await evaluate("document.documentElement.dataset.testNavigation='old'");await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'&&!document.documentElement.dataset.testNavigation"),'Page load timeout');};
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
    const author=session(authorRole),fixtures=[];
    await send('Network.setCookie',{...author,url:base,httpOnly:true});
    cleanupDrafts=async()=>{
        await send('Network.setCookie',{...author,url:base,httpOnly:true});
        for(const item of fixtures){
            if(item.deleted)continue;
            const removed=await evaluate(`(async()=>{for(const url of ['${item.route}?status=draft&size=100','/admin/submissions.php?id=${item.id}']){const r=await fetch(url);if(!r.ok)continue;const d=new DOMParser().parseFromString(await r.text(),'text/html');const form=d.querySelector('[data-draft-id="${item.id}"] form[data-draft-action=delete]')||d.querySelector('#submission-review input[name=action][value=delete]')?.closest('form');if(!form)continue;const reply=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form)});return reply.ok;}return false;})()`);
            assert.ok(removed,'Could not clean up private browser fixture '+item.id);
        }
    };
    for(const [route,formId,filterId] of [['/admin/news.php','news-form','news-filter-status'],['/admin/announcement.php','announcement-form','ann-filter-status']]){
        const queryId=formId==='news-form'?'news-query':'ann-query';
        await visit(route+'?modal=create');
        assert.equal(await evaluate("Boolean(document.querySelector('.gnc-sidebar a[href*=\"view=drafts\"]'))"),false,'Separate Drafts navigation returned');
        assert.equal(await evaluate("document.getElementById('modal-status').value"),'draft','New content must default to saving privately');
        const title=authorRole+' integrated draft fixture '+Date.now();
        const saved=await evaluate(`(async()=>{const form=document.getElementById('${formId}'),data=new FormData(form);data.set('title',${JSON.stringify(title)});data.set('content','<p>First private version</p>');data.set('status','draft');const r=await fetch(form.getAttribute('action'),{method:'POST',body:data});return {url:r.url,ok:r.ok,html:await r.text()};})()`);
        const id=await evaluate(`(()=>{const d=new DOMParser().parseFromString(${JSON.stringify(saved.html)},'text/html');return Array.from(d.querySelectorAll('[data-draft-id]')).find(row=>row.querySelector('[data-draft-edit]')?.textContent===${JSON.stringify(title)})?.dataset.draftId;})()`);
        assert.ok(id&&saved.ok,'Draft save did not return its content list');fixtures.push({id,route});
        assert.equal(new URL(saved.url).searchParams.get('status'),'draft');
        assert.ok(!new URL(saved.url).pathname.includes('submissions'),'Saving draft opened separate Drafts page');
        await visit(route+'?status=draft');
        assert.ok(await evaluate(`Boolean(document.querySelector('[data-draft-id="${id}"]'))`),'Draft missing from module list');
        assert.equal(await evaluate("Boolean(document.querySelector('.approval-page'))"),false,'Separate Drafts layout still rendered');
        await evaluate(`(()=>{const select=document.getElementById('${filterId}');select.value='';select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until(()=>evaluate(`(new URL(location.href).searchParams.get('status')||'')===''&&!document.getElementById('${queryId}').hasAttribute('aria-busy')`),'All statuses did not refresh');
        assert.ok(await evaluate(`Boolean(document.querySelector('[data-draft-id="${id}"]'))`),'All statuses omitted private draft');
        if(formId==='announcement-form'){
            const deleteTitle=title+' delete only this fixture';
            const extra=await evaluate(`(async()=>{const form=document.getElementById('${formId}'),data=new FormData(form);data.set('action','create');data.set('_review_id','0');data.set('title',${JSON.stringify(deleteTitle)});data.set('content','<p>Disposable private draft</p>');data.set('status','draft');const r=await fetch(form.getAttribute('action'),{method:'POST',body:data});return {ok:r.ok,html:await r.text()};})()`);
            const deleteId=await evaluate(`(()=>{const d=new DOMParser().parseFromString(${JSON.stringify(extra.html)},'text/html');return Array.from(d.querySelectorAll('[data-draft-id]')).find(row=>row.querySelector('[data-draft-edit]')?.textContent===${JSON.stringify(deleteTitle)})?.dataset.draftId;})()`);
            assert.ok(extra.ok&&deleteId,'Could not prepare deletion fixture');const deletionFixture={id:deleteId,route};fixtures.push(deletionFixture);
            await visit(route+'?sort=oldest&size=100');
            const rowKeys=()=>evaluate(`Array.from(document.querySelectorAll('#announcements-table tbody tr.ann-row')).map(row=>row.dataset.draftId?'draft:'+row.dataset.draftId:'announcement:'+row.querySelector('[data-preview-id]')?.dataset.previewId)`);
            const before=await rowKeys();
            await evaluate(`document.querySelector('[data-draft-id="${deleteId}"] form[data-draft-action=delete] button').click()`);
            await until(()=>evaluate("Boolean(document.querySelector('dialog[open] .gnc-dialog-accept'))"),'Draft deletion confirmation unavailable');
            await evaluate("document.documentElement.dataset.deletingDraft='yes';document.querySelector('dialog[open] .gnc-dialog-accept').click()");
            await until(()=>evaluate("document.readyState==='complete'&&!document.documentElement.dataset.deletingDraft&&!!document.getElementById('ann-query')"),'Draft deletion did not return to announcement list');
            const after=await rowKeys();
            assert.ok(!after.includes('draft:'+deleteId),'Selected test draft was not deleted');deletionFixture.deleted=true;
            assert.equal(await evaluate("new URL(location.href).searchParams.get('status')||''"),'','Deletion forced a different status filter');
            assert.equal(await evaluate("document.getElementById('ann-filter-status').value"),'','Deletion changed All statuses');
            assert.equal(await evaluate("new URL(location.href).searchParams.get('sort')"),'oldest','Deletion lost the selected sort order');
            assert.equal(await evaluate("new URL(location.href).searchParams.get('size')"),'100','Deletion lost the page size');
            assert.ok(before.filter(key=>key!=='draft:'+deleteId).every(key=>after.includes(key)),'Unrelated announcement rows disappeared after deletion');
        }

        await evaluate(`(()=>{const select=document.getElementById('${filterId}');select.value='draft';select.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until(()=>evaluate(`new URL(location.href).searchParams.get('status')==='draft'&&!document.getElementById('${queryId}').hasAttribute('aria-busy')`),'Draft filter did not refresh in place');
        assert.equal(await evaluate(`document.querySelector('h1').textContent==='Drafts'`),false,'Draft filter navigated to separate page');
        if(formId==='announcement-form'){
            for(const width of [1440,390]){
                await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(200);
                assert.ok(await evaluate("document.documentElement.scrollWidth<=innerWidth"),'Integrated draft list overflows viewport');
                fs.writeFileSync(`cache/integrated-drafts-${width}.png`,Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
            }
            await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
        }
        const edit=await evaluate(`document.querySelector('[data-draft-id="${id}"] [data-draft-edit]').getAttribute('href')`);
        for(let revision=1;revision<=2;revision++){
            await visit(edit);
            assert.equal(await evaluate(`document.querySelector('#${formId} [name=_review_id]').value`),id,'Draft identity missing from editor');
            assert.equal(await evaluate("document.getElementById('modal-status').value"),'draft');
            const updated=await evaluate(`(async()=>{const form=document.getElementById('${formId}'),data=new FormData(form);data.set('content','<p>Private version ${revision+1}</p>');const r=await fetch(form.getAttribute('action'),{method:'POST',body:data});return {url:r.url,ok:r.ok,html:await r.text()};})()`);
            assert.ok(updated.ok&&!new URL(updated.url).pathname.includes('submissions'),'Draft update opened separate page');
            assert.equal(await evaluate(`new DOMParser().parseFromString(${JSON.stringify(updated.html)},'text/html').querySelector('[data-draft-id="${id}"]').dataset.draftRevision`),String(revision+1));
        }
        assert.ok(await evaluate(`fetch('/admin/submissions.php').then(r=>r.text()).then(html=>!Array.from(new DOMParser().parseFromString(html,'text/html').querySelectorAll('.approval-row-title')).some(el=>el.textContent===${JSON.stringify(title)}))`),'Draft leaked to My Submissions');
        await send('Network.setCookie',{...admin,url:base,httpOnly:true});
        assert.equal(await evaluate(`fetch('/admin/submissions.php?media=${id}&view=drafts&field=image').then(r=>r.status)`),404,'Other account accessed private draft media');
        await send('Network.setCookie',{...author,url:base,httpOnly:true});
        await visit('/admin/submissions.php?view=drafts&id='+id);
        assert.ok(await evaluate(`Boolean(document.getElementById('${queryId}'))&&!document.querySelector('.approval-page')`),'Old Drafts bookmark did not return to module list');
        const submitted=await evaluate(`(async()=>{const form=document.querySelector('[data-draft-id="${id}"] form[data-draft-action=submit]');const r=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form)});return {url:r.url,ok:r.ok};})()`);
        assert.ok(submitted.ok);assert.equal(new URL(submitted.url).searchParams.get('view'),'submissions');
        await send('Network.setCookie',{...admin,url:base,httpOnly:true});
        assert.equal(await evaluate(`fetch('/admin/submissions.php?id=${id}').then(r=>r.text()).then(html=>{const d=new DOMParser().parseFromString(html,'text/html');return !!d.querySelector('#submission-review input[name=action][value=approve]')&&d.querySelector('#submission-review').textContent.includes('Private version 3')})`),true,'Submitted draft did not reach Approvals');
        await send('Network.setCookie',{...author,url:base,httpOnly:true});
        if(authorRole==='Content Editor'){
            const editSelector=formId==='news-form'?'.btn-edit-news':'.btn-edit-announcement';
            await visit(route+'?status=published');
            const original=await evaluate(`(()=>{const b=document.querySelector('${editSelector}[data-status=published]');return {id:b.dataset.id,title:b.dataset.title,status:b.dataset.status,content:document.getElementById(b.dataset.contentId).innerHTML};})()`);
            const readPublished=()=>evaluate(`fetch('${route}?status=published').then(r=>r.text()).then(html=>{const d=new DOMParser().parseFromString(html,'text/html'),b=d.querySelector('${editSelector}[data-id="${original.id}"]');return b?{id:b.dataset.id,title:b.dataset.title,status:b.dataset.status,content:d.getElementById(b.dataset.contentId).innerHTML}:null;})`);
            await evaluate(`document.querySelector('${editSelector}[data-id="${original.id}"]').click()`);
            assert.equal(await evaluate("document.getElementById('modal-status').value"),'draft','Published edit must default to private draft');
            const edited=await evaluate(`(async()=>{const form=document.getElementById('${formId}'),data=new FormData(form);data.set('content','<p>Private proposed published edit</p>');const r=await fetch(form.getAttribute('action'),{method:'POST',body:data});return {url:r.url,ok:r.ok,html:await r.text()};})()`);
            const editId=await evaluate(`(()=>{const d=new DOMParser().parseFromString(${JSON.stringify(edited.html)},'text/html');return Array.from(d.querySelectorAll('[data-draft-id]')).find(row=>row.querySelector('[data-draft-edit]')?.textContent===${JSON.stringify(original.title)})?.dataset.draftId;})()`);
            assert.ok(edited.ok&&editId,'Published edit draft save failed');fixtures.push({id:editId,route});
            assert.deepEqual(await readPublished(),original,'Draft changed published original');
            await visit(route+'?status=draft');
            const result=await evaluate(`(async()=>{const form=document.querySelector('[data-draft-id="${editId}"] form[data-draft-action=submit]');const r=await fetch(form.getAttribute('action'),{method:'POST',body:new FormData(form)});return {url:r.url,ok:r.ok};})()`);
            assert.ok(result.ok);assert.deepEqual(await readPublished(),original,'Submitting draft changed published original');
            await send('Network.setCookie',{...admin,url:base,httpOnly:true});
            assert.equal(await evaluate(`fetch('/admin/submissions.php?id=${editId}').then(r=>r.text()).then(html=>{const d=new DOMParser().parseFromString(html,'text/html');return !!d.querySelector('#submission-review input[name=action][value=approve]')&&d.querySelector('#submission-review').textContent.includes('Private proposed published edit')})`),true,'Edited version did not require approval');
            await send('Network.setCookie',{...author,url:base,httpOnly:true});
        }
    }
    console.log(authorRole+': integrated News and Announcement drafts, All statuses, in-place draft filtering, old-bookmark redirects, repeated saves, explicit submission, deletion return state and published-content isolation passed. No public content changed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{
    try{await cleanupDrafts();}catch(error){console.error(error);process.exitCode=1;}
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});