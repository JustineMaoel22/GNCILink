// Runs against the local PHP site in an installed headless Chrome; no npm dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn } = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-office-map-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) {
    const deadline = Date.now() + 30000;
    while (Date.now() < deadline) { const value = await work(); if (value) return value; await delay(100); }
    throw new Error(message);
}
let socket,fixture,session;
(async () => {
    await until(() => fs.existsSync(path.join(profile, 'DevToolsActivePort')), 'Chrome did not start');
    const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
    const target = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(target.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let serial = 0; const requests = new Map();
    socket.addEventListener('message', event => {
        const packet = JSON.parse(event.data);
        if (packet.id && requests.has(packet.id)) { requests.get(packet.id)(packet); requests.delete(packet.id); }
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++serial;
        requests.set(id, result => result.error ? reject(Error(JSON.stringify(result.error))) : resolve(result.result));
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    const execFileSync=require('node:child_process').execFileSync;
    const php=(...args)=>execFileSync('docker',['compose','exec','-T',...(args[0]==='session'?['--user','www-data']:[]),'web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
    await send('Runtime.enable');
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Runtime.exceptionThrown')console.error('Browser error:',packet.params.exceptionDetails.exception?.description?.slice(0,600)||packet.params.exceptionDetails.text);});
    fixture=JSON.parse(php('seed'));
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    async function navigate(url){await send('Page.navigate',{url});await until(()=>evaluate(`location.href===${JSON.stringify(url)}&&document.readyState==='complete'`),'Page did not load: '+url);}
    const listing=base+fixture.url+'?category='+fixture.categories[0];
    await navigate(listing);
    assert.equal(await evaluate("document.querySelectorAll('.student-org-card').length"),6,'Listing shows six organizations per page');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1050,deviceScaleFactor:1,mobile:false});
    const screenshot=await send('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(path.join(os.tmpdir(),'gnci-student-organizations-preview.png'),Buffer.from(screenshot.data,'base64'));
    assert.equal(await evaluate("document.querySelectorAll('.org-lead-topics li').length"),5,'Leadership training topics appear below the directory');
    assert.equal(await evaluate("document.querySelectorAll('.org-lead-callout').length"),2,'Retreat section includes both callouts');
    await evaluate("document.querySelector('.org-leadership').scrollIntoView({block:'start',behavior:'instant'})");
    const leadershipScreenshot=await send('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(path.join(os.tmpdir(),'gnci-student-leadership-preview.png'),Buffer.from(leadershipScreenshot.data,'base64'));
    assert.equal(await evaluate("document.querySelector('.student-org-pagination [aria-current=page]').textContent"),'1');
    assert.equal(await evaluate(`document.body.textContent.includes(${JSON.stringify(fixture.records[8].name)})`),false,'Drafts are private');
    assert.equal(await evaluate(`document.querySelector('.student-org-filters').textContent.includes(${JSON.stringify(fixture.prefix+' Unused test')})`),true,'Saved categories appear publicly even without published organizations');
    await navigate(base+fixture.url+'?category='+fixture.categories[2]);
    assert.equal(await evaluate("document.querySelectorAll('.student-org-card').length"),0,'Empty categories do not expose drafts or unrelated organizations');
    assert.equal(await evaluate("document.querySelector('.student-org-empty h3').textContent"),'No organizations in this category');
    await navigate(listing);
    await navigate(listing+'&p=2');
    assert.equal(await evaluate("document.querySelectorAll('.student-org-card').length"),1);
    await navigate(listing+'&p=999');
    assert.equal(await evaluate("document.querySelector('.student-org-pagination [aria-current=page]').textContent"),'2','Out-of-range pagination is clamped');
    await navigate(base+fixture.url+'?category='+fixture.categories[1]);
    assert.equal(await evaluate("document.querySelectorAll('.student-org-card').length"),1,'Filtering only shows published records in the selected category');
    await navigate(listing+'&ce_preview=1');
    assert.equal(await evaluate("Boolean(document.getElementById('veOrgData'))"),false,'Visitors cannot obtain preview metadata');
    for(const [width,columns]of [[1440,3],[768,2],[390,1]]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        assert.equal(await evaluate("getComputedStyle(document.querySelector('.student-org-grid')).gridTemplateColumns.split(' ').length"),columns);
        assert.equal(await evaluate("Array.from(document.querySelectorAll('.student-org-card')).every(card=>card.getBoundingClientRect().right<=innerWidth+1)"),true,'Cards fit '+width+'px');
        assert.equal(await evaluate("document.querySelector('.org-leadership').getBoundingClientRect().right<=innerWidth+1"),true,'Leadership sections fit '+width+'px');
        assert.equal(await evaluate("getComputedStyle(document.querySelector('.org-lead-retreat-grid')).gridTemplateColumns.split(' ').length"),width<768?1:2,'Retreat content responds at '+width+'px');
    }
    await navigate(base+fixture.detail);
    assert.equal(await evaluate("document.querySelectorAll('.org-values article').length"),2);
    assert.equal(await evaluate("document.querySelectorAll('.org-gallery img').length"),1);
    const draftResponse=await fetch(base+fixture.detail.replace(fixture.records[0].slug,fixture.records[8].slug)+'&ce_preview=1');
    assert.equal(draftResponse.status,404,'Unpublished detail pages remain private');
    const unauthorized=await fetch(base+'/admin/action/student-organizations-handler.php',{method:'POST',body:new URLSearchParams({action:'delete',org_id:fixture.records[0].org_id})});
    assert.equal(unauthorized.status,403,'Anonymous mutations are rejected');
    session=JSON.parse(php('session'));
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await navigate(base+'/admin/content-edit.php?page=student-organizations');
    try { await until(()=>evaluate("Boolean(document.getElementById('veFrame')?.contentDocument?.getElementById('veOrgData'))"),'Organization visual preview unavailable'); }
    catch(error){console.error(await evaluate("JSON.stringify({url:location.pathname+location.search,body:document.body.innerText.slice(0,1000),frame:document.getElementById('veFrame')?.src,frameText:document.getElementById('veFrame')?.contentDocument?.body?.innerText.slice(0,500),kind:window.VE_CONFIG?.page.kind})"));throw error;}
    await until(()=>evaluate("Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-org-add]'))&&window.VE?.adapter()?.actions()?.secondary?.label==='Add Organization'"),'Canvas Add Organization action unavailable');
    assert.equal(await evaluate("Boolean(document.getElementById('orgEditForm'))"),false,'Legacy organization form is not mounted');
    await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-add]').click()");
    await until(()=>evaluate("Boolean(document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-card^=new-] [contenteditable]'))"),'Inline organization card was not added');
    const newName=fixture.prefix+' Created through editor';
    await evaluate(`(() => {const field=document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-card^=new-] [data-ve-key=name]');field.textContent=${JSON.stringify(newName)};field.dispatchEvent(new Event('input',{bubbles:true}));field.click();})()`);
    await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-card^=new-] [data-ve-org-assign-category]').click()");
    await until(()=>evaluate("Boolean(document.querySelector('#vePop select'))"),'Category selector did not open');
    await evaluate(`document.querySelector('#vePop select').value=${JSON.stringify(String(fixture.categories[0]))};Array.from(document.querySelectorAll('#vePop button')).find(button=>button.textContent==='Apply').click()`);
    // Category popovers must work without losing the unsaved inline card.
    await evaluate("Array.from(document.querySelectorAll('#veTb button')).find(button=>button.getAttribute('aria-label')==='Category').click();Array.from(document.querySelectorAll('#vePop button')).find(button=>button.textContent==='Add category').click()");
    const categoryName=fixture.prefix+' Canvas category';
    await evaluate(`document.querySelector('#vePop input').value=${JSON.stringify(categoryName)};document.querySelector('#vePop button').click()`);
    await until(()=>evaluate(`Array.from(document.querySelectorAll('#vePop option')).some(option=>option.textContent===${JSON.stringify(categoryName)})`),'Canvas category creation failed');
    await evaluate(`document.querySelector('#vePop select').value=Array.from(document.querySelectorAll('#vePop option')).find(option=>option.textContent===${JSON.stringify(categoryName)}).value;Array.from(document.querySelectorAll('#vePop button')).find(button=>button.textContent==='Rename').click();document.querySelector('#vePop input').value=${JSON.stringify(categoryName+' renamed')};document.querySelector('#vePop button').click()`);
    await until(()=>evaluate(`Array.from(document.querySelectorAll('#vePop option')).some(option=>option.textContent===${JSON.stringify(categoryName+' renamed')})`),'Canvas category rename failed');
    await evaluate(`document.querySelector('#vePop select').value=Array.from(document.querySelectorAll('#vePop option')).find(option=>option.textContent===${JSON.stringify(categoryName+' renamed')}).value;Array.from(document.querySelectorAll('#vePop button')).find(button=>button.textContent==='Delete').click();Array.from(document.querySelectorAll('#vePop button')).find(button=>button.textContent==='Delete category').click()`);
    await until(()=>evaluate(`Boolean(document.querySelector('#vePop select'))&&!Array.from(document.querySelectorAll('#vePop option')).some(option=>option.textContent===${JSON.stringify(categoryName+' renamed')})`),'Canvas category deletion failed');
    assert.equal(await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-card^=new-] [data-ve-key=name]').textContent"),newName,'Category changes retain unsaved text');
    await evaluate(`document.querySelector('#vePop select').value=${JSON.stringify(String(fixture.categories[0]))};Array.from(document.querySelectorAll('#vePop button')).find(button=>button.textContent==='Apply').click()`);
    const imagePath=path.join(profile,'organization-test.png');
    fs.writeFileSync(imagePath,Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII=','base64'));
    async function uploadCanvasFile(){const documentNode=await send('DOM.getDocument');const node=await send('DOM.querySelector',{nodeId:documentNode.root.nodeId,selector:'#veFile'});
        await send('DOM.setFileInputFiles',{nodeId:node.nodeId,files:[imagePath]});
    }
    for(const key of ['logo','cover']){
        await evaluate(`document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-card^=new-] [data-ve-org-media=${key}]').click();Array.from(document.querySelectorAll('#veTb button')).find(button=>button.getAttribute('aria-label')==='Replace picture').click()`);
        await uploadCanvasFile();
    }
    await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-card^=new-] [data-ve-org-details]').click()");
    await until(()=>evaluate(`document.getElementById('veFrame')?.contentDocument?.querySelector('.dynamic-org-hero h1')?.textContent===${JSON.stringify(newName)}`),'Saving the inline draft did not open its visual page');
    const createdUrl=await evaluate("document.getElementById('veFrame').contentWindow.location.href.replace('&ce_preview=1','')");
    assert.equal((await fetch(createdUrl)).status,404,'New organization stays unpublished before Publish');
    await until(()=>evaluate("window.VE?.adapter()?.actions()?.primary?.disabled===false&&!document.getElementById('vePrimary').disabled"),'Draft Publish action was not ready');
    await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-assign-category]').click()");
    await until(()=>evaluate("Boolean(document.querySelector('#vePop select'))"),'Detail page category assignment did not open');
    await evaluate(`document.querySelector('#vePop select').value=${JSON.stringify(String(fixture.categories[1]))};Array.from(document.querySelectorAll('#vePop button')).find(button=>button.textContent==='Apply').click()`);
    assert.ok((await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-assign-category]').textContent")).includes(fixture.prefix+' Community test'),'Assigned category appears in the detail editor');
    await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-gallery-add]').click()");
    await uploadCanvasFile();
    assert.equal(await evaluate("document.getElementById('veFrame').contentDocument.querySelectorAll('[data-ve-org-gallery]').length"),1,'Gallery uploads preview on canvas');
    await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-gallery-add]').click()");
    await uploadCanvasFile();
    await evaluate("Array.from(document.getElementById('veFrame').contentDocument.querySelectorAll('[data-ve-org-gallery]')).at(-1).click();document.querySelector('#veTb button[aria-label=\"Remove gallery image\"]').click()");
    assert.equal(await evaluate("document.getElementById('veFrame').contentDocument.querySelectorAll('[data-ve-org-gallery]').length"),1,'Gallery images can be removed on canvas before saving');
    await evaluate("document.getElementById('vePrimary').click()");
    try { await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Missing organization details did not produce a warning'); }
    catch(error){console.error(await evaluate("JSON.stringify({actions:window.VE?.adapter()?.actions(),missing:window.ContentCompleteness?.inspect(document.getElementById('veFrame').contentDocument).missing,dialogs:Array.from(document.querySelectorAll('dialog')).map(el=>el.innerText)})"));throw error;}
    await evaluate("document.querySelector('.cms-completeness-keep').click()");
    await until(()=>evaluate("!document.querySelector('.cms-completeness-dialog')"),'Keep Editing did not close warning');
    await evaluate(`(() => {const doc=document.getElementById('veFrame').contentDocument;const field=doc.querySelector('[data-ve-key="description"]');field.textContent='Edited directly on the visual canvas';field.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await evaluate("document.getElementById('vePrimary').click()");
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Incomplete overview was not warned about');
    await evaluate("document.querySelector('.cms-completeness-publish').click()");
    await until(async()=>{const response=await fetch(createdUrl);return response.status===200&&(await response.text()).includes('Edited directly on the visual canvas');},'Publishing did not update the public organization');
    const publicHtml=await(await fetch(createdUrl)).text();
    assert.ok(publicHtml.includes('/uploads/homepage/'),'Uploaded media is used by the public page');
    assert.ok(publicHtml.includes('org-gallery'),'Gallery upload is persisted');
    await navigate(base+'/admin/content-edit.php?page=student-organizations');
    await until(()=>evaluate("Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-key=leadership_title]')?.__ve)"),'Leadership inline fields unavailable');
    await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-page-media=retreat_photo]').click();document.querySelector('#veTb [aria-label=\"Replace picture\"]').click()");
    await uploadCanvasFile();
    assert.equal(await evaluate("document.getElementById('veFrame').contentDocument.querySelector('[data-ve-org-page-media=retreat_photo] img').src.startsWith('blob:')"),true,'Leadership photo replacements preview immediately');
    await evaluate("(()=>{const field=document.getElementById('veFrame').contentDocument.querySelector('[data-ve-key=leadership_title]');field.textContent='Unsaved leadership heading';field.dispatchEvent(new Event('input',{bubbles:true}));})()");
    assert.equal(await evaluate('window.VE.adapter().dirtyCount()'),2,'Leadership text and photos use the existing unsaved-change tracking');
    await evaluate("window.AdminDialogs.confirm=async()=>true;document.getElementById('veCancel').click()");
    await until(()=>evaluate('window.VE?.adapter()?.dirtyCount()===0'),'Cancel did not discard the staged leadership changes');
    // Shared rich-text tools must work in every editor adapter without publishing test edits.
    const richPages=[
        ['home','[data-ve-field="pres-message"]'],
        ['administration','[data-ve-field="admin-section-desc"]'],
        ['basic-education','[data-ve-field="basic-text"][data-ve-rich]'],
        ['basic-education&sub=casa-gradeschool','[data-ve-field="dean-bio"]'],
        ['college-departments','[data-ve-field="basic-text"][data-ve-rich]'],
        ['college-departments&sub=cased','[data-ve-field="dean-bio"]'],
        ['graduate-school','[data-ve-field="dean-bio"]'],
        ['programs','[data-ve-field="prog-text"][data-ve-rich]'],
        ['student-organizations','[data-ve-key="retreat_benefit"]'],
        ['student-organizations&sub='+fixture.records[0].slug,'[data-ve-key="vision"]']
    ];
    for(const [page,selector] of richPages){
        await evaluate('window.__veLeaving=true');
        await navigate(base+'/admin/content-edit.php?page='+page);
        await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector(${JSON.stringify(selector)})?.__ve?.rich)`),'Rich text unavailable on '+page);
        await evaluate(`(()=>{const doc=document.getElementById('veFrame').contentDocument,field=doc.querySelector(${JSON.stringify(selector)});field.textContent='Rich formatting test';field.dispatchEvent(new Event('input',{bubbles:true}));field.click();field.focus();const range=doc.createRange();range.selectNodeContents(field);const selection=doc.defaultView.getSelection();selection.removeAllRanges();selection.addRange(range);})()`);
        assert.equal(await evaluate('Boolean(document.querySelector(\'#veTb button[aria-label="Bold (Ctrl+B)"]\'))'),true,'Rich toolbar on '+page);
        await evaluate('document.querySelector(\'#veTb button[aria-label="Bold (Ctrl+B)"]\').click()');
        assert.equal(await evaluate(`Boolean(document.getElementById('veFrame').contentDocument.querySelector(${JSON.stringify(selector)}).querySelector('b,strong'))`),true,'Formatting applies on '+page);
        assert.ok(await evaluate('window.VE.adapter().dirtyCount()>0'),'Formatting remains pending on '+page);
    }
    await evaluate('window.__veLeaving=true');
    await navigate(base+'/admin/content-edit.php?page=student-organizations');
    await until(()=>evaluate('Boolean(document.getElementById("veFrame")?.contentDocument?.querySelector("[data-ve-org-icon]")&&window.VE?.adapter())'),'Organization icon controls unavailable');
    await evaluate('document.getElementById("veFrame").contentDocument.querySelector("[data-ve-org-icon=retreat_benefit_icon]").click();document.querySelector(\'#veTb button[aria-label="Change icon"]\').click()');
    await evaluate('document.querySelector(\'#vePop input[type=search]\').value="book";document.querySelector(\'#vePop input[type=search]\').dispatchEvent(new Event("input"))');
    await until(()=>evaluate('Boolean(document.querySelector(\'#vePop button[title="Book (Bootstrap Icons)"]\'))'),'Bootstrap icon search unavailable');
    await evaluate('document.querySelector(\'#vePop button[title="Book (Bootstrap Icons)"]\').click()');
    assert.equal(await evaluate('Boolean(document.getElementById("veFrame").contentDocument.querySelector("[data-ve-org-icon=retreat_benefit_icon] .bi-book"))'),true,'Callout icon changes immediately');
    await evaluate('window.__veLeaving=true');
    const cookie=session.name+'='+session.id;
    const api=async fields=>{const response=await fetch(base+'/admin/action/student-organizations-handler.php',{method:'POST',headers:{Cookie:cookie},body:new URLSearchParams({csrf_token:session.csrf,...fields})});return {status:response.status,data:await response.json()};};
    const noCsrf=await fetch(base+'/admin/action/student-organizations-handler.php',{method:'POST',headers:{Cookie:cookie},body:new URLSearchParams({action:'delete',org_id:fixture.records[0].org_id})});
    assert.equal(noCsrf.status,403,'Missing CSRF token is rejected');
    assert.equal((await api({action:'save_category',category_id:fixture.categories[1],name:fixture.prefix+' Renamed category'})).data.success,true);
    assert.equal((await api({action:'save',org_id:fixture.records[0].org_id,status:'draft'})).data.success,true);
    assert.equal((await fetch(base+fixture.detail)).status,404,'Unpublishing immediately hides a detail page');
    assert.equal((await api({action:'delete_category',category_id:fixture.categories[1]})).data.success,true);
    const retained=await fetch(base+fixture.detail.replace(fixture.records[0].slug,fixture.records[7].slug));
    assert.equal(retained.status,200);assert.ok((await retained.text()).includes(fixture.records[7].name),'Deleting a category retains its published organizations');
    console.log('PASS: dynamic cards, six-per-page pagination, category filtering, draft privacy, responsive layouts, authenticated editor creation, real image/gallery uploads, missing warnings, visual editing, publishing, category management and CSRF/permission checks');

})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    const execFileSync=require('node:child_process').execFileSync;
    if(fixture) {try{execFileSync('docker',['compose','exec','-T','web','php','tests/student-organizations-test.php','cleanup',fixture.prefix],{windowsHide:true,stdio:'pipe'});}catch(error){console.error('Fixture cleanup failed:',error.message);process.exitCode=1;}}
    if(session) {try{execFileSync('docker',['compose','exec','-T','web','php','tests/student-organizations-test.php','destroy_session',session.id],{windowsHide:true,stdio:'pipe'});}catch{}}
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
