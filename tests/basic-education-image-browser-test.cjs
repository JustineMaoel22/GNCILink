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
let socket;
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
    await send('Network.enable');
    await send('Network.setBlockedURLs', { urls: ['*fonts.googleapis.com*', '*fonts.gstatic.com*', '*cloudflareinsights.com*', '*cdn.jsdelivr.net*', '*facebook.com*', '*cdnjs.cloudflare.com*', '*unpkg.com*', '*youtube.com*', '*google.com*'] });
    for (const width of [1440, 768, 390]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
        let baseline;
        for (const route of ['/pages/academics/college/college-department.php?slug=cased', '/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php', '/pages/academics/graduate-school/graduate-school.php']) {
            await send('Page.navigate', { url: (process.env.BASE_URL || 'http://localhost:8080') + route });
            await until(() => evaluate(`location.pathname === ${JSON.stringify(route.split('?')[0])} && document.readyState !== 'loading' && Boolean(document.querySelector('.dept-dean-grid'))`), 'Shared department layout unavailable');
            const layout = await evaluate(`(() => {
                const style = selector => {const s=getComputedStyle(document.querySelector(selector));return [s.fontFamily,s.fontSize,s.lineHeight,s.color]};
                return {heading:style('.dept-dean-intro h2'), body:style('.dept-dean-content'),
                    sharedCss:Boolean(document.querySelector('link[href*="college-dept-style.css"]')),
                    overflow:Array.from(document.querySelectorAll('.dept-about-inner,.dept-programs-inner,.dept-dean-inner,.dept-dean-content')).some(el=>{const r=el.getBoundingClientRect();return r.left < -1 || r.right > innerWidth+1}),
                    sections:Array.from(document.querySelectorAll('main > section')).map(el=>el.className)};
            })()`);
            assert.ok(layout.sharedCss, 'Every department uses the shared stylesheet');
            assert.equal(layout.overflow, false, 'Department content fits viewport at ' + width + ': ' + route);
            if (baseline) {
                assert.deepEqual(layout.heading, baseline.heading, 'Leadership headings have consistent typography');
                assert.deepEqual(layout.body, baseline.body, 'Leadership content has consistent typography');
            }
            baseline = layout;
        }
    }
    console.log('PASS: College, Basic Education and Graduate School share typography and fit desktop, tablet and mobile viewports');
    await send('Page.navigate', { url: (process.env.BASE_URL || 'http://localhost:8080') + '/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php' });
    try {
        await until(() => evaluate("Boolean(document.querySelector('[data-office-location]') && document.getElementById('officeMapModal') && document.readyState !== 'loading')"), 'CASED office card unavailable');
    } catch (error) {
        console.error(await evaluate("JSON.stringify({url:location.href,ready:document.readyState,title:document.title,body:document.body?.innerText.slice(0,1000)})"));
        throw error;
    }

    const source=fs.readFileSync('assets/js/content-edit.js','utf8');
    const show=source.slice(source.indexOf('        function showPic('),source.indexOf('        function editFacebook('));
    const picture='data:image/svg+xml;base64,'+Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="120" height="120" fill="green"/></svg>').toString('base64');
    for(const [width,height] of [[1440,900],[390,844]]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await evaluate(`(() => {
            ${show}
            document.getElementById('imagePreviewFixture')?.remove();
            const fixture=document.createElement('div');fixture.id='imagePreviewFixture';
            fixture.innerHTML='<div class="dept-logo-wrap"><div class="skeleton-wrap is-loaded" style="display:inline-block"><span class="be-empty-media dept-logo" data-ve-img="dept-logo"><span>Add image</span></span></div></div><div class="dept-dean-photo"><div class="skeleton-wrap is-loaded"><span class="be-empty-media dept-dean-upload"><span>Add image</span></span></div></div><div class="dept-program-img skeleton-wrap is-loaded"><span class="be-empty-media"><span>Add image</span></span></div>';
            document.querySelector('.dept-about-inner').appendChild(fixture);
            window.beforeImageWidth=fixture.querySelector('.dept-logo').getBoundingClientRect().width;
            fixture.querySelectorAll('.be-empty-media').forEach(el=>showPic(el,${JSON.stringify(picture)}));
        })()`);
        await until(()=>evaluate("Array.from(document.querySelectorAll('#imagePreviewFixture img')).every(image=>image.complete&&image.naturalWidth>0)"),'Preview image did not load');
        const result=await evaluate("(()=>{const f=document.getElementById('imagePreviewFixture');return {before:beforeImageWidth,after:f.querySelector('.dept-logo').getBoundingClientRect().width,images:Array.from(f.querySelectorAll('img')).map(image=>({width:image.getBoundingClientRect().width,height:image.getBoundingClientRect().height,opacity:getComputedStyle(image).opacity})),markers:f.querySelectorAll('[data-ve-img]').length}})()");
        assert.equal(result.after,result.before,'Uploading preserves logo slot width');
        assert.ok(result.after>=160,'Logo slot does not collapse');
        result.images.forEach(image=>{assert.ok(image.width>100&&image.height>100,'Uploaded image has visible dimensions');assert.equal(image.opacity,'1');});
        assert.equal(result.markers,1,'Editing marker survives preview replacement');
    }
    console.log('PASS: uploads render as visible images in empty logo, portrait and program slots at desktop and mobile widths; dimensions and editing markers are preserved');
    const adapterSource=source.slice(source.indexOf('    function EntityAdapter()'),source.indexOf('    function FeedAdapter()'));
    const fieldHelpers=source.slice(source.indexOf('    function blocksText('),source.indexOf('    /** Keep pasted/new paragraphs'));
    const frameStyles=source.slice(source.indexOf('    var FRAME_CSS ='),source.indexOf('    function injectStyle('));
    const cards=await evaluate(`(async () => {
        ${frameStyles}
        const style=document.createElement('style');style.textContent=FRAME_CSS;document.head.appendChild(style);
        const fixture=document.createElement('section');fixture.className='dept-programs';document.querySelector('main').appendChild(fixture);
        fixture.innerHTML='<script type="application/json" id="veDeptData">{"dept_id":1,"kind":"college"}</script><script type="application/json" id="veDeptProgramsData">[]</script><div class="dept-programs-inner"><div class="dept-programs-grid" data-ve-program-grid><button class="ve-add-tile dept-program-add" data-ve-add="program">Add a program</button></div></div><template id="veDepartmentProgramTemplate"><article class="dept-program-card" data-ve-program-card="__KEY__"><div class="dept-program-img"><span class="be-empty-media" data-ve-program-image="__KEY__">Add image</span></div><div class="dept-program-body"><h3 data-ve-field="dept-program-name" data-ve-key="__KEY__" data-ve-ph="Add heading"></h3><p data-ve-field="dept-program-description" data-ve-key="__KEY__" data-ve-ph="Add description" data-ve-multiline="1"></p></div></article></template>';
        const D=document, S={doc:document,url:location.href}, C={}, MB=1024*1024, PLAIN='plaintext-only';
        const $=selector=>document.querySelector(selector), $$=(selector,root)=>Array.from((root||document).querySelectorAll(selector));
        const fieldOf=el=>el.closest('[data-ve-field]');
        function renderToolbar() {} function chrome() {} function select() {}
        function entityRoute() {return {page:'college-departments',sub:'cased'}}
        function plural(n,word) {return n+' '+word} function clip(text) {return text}
        let lastSave, picker, requests=[], notices=[], reloads=0, serial=500, failSecond=true, confirm=true;
        function run(label,work) {lastSave=work()}
        function confirmPublish(message,done) {done()}
        function toast(message) {notices.push(message)} function reloadEditor() {reloads++}
        function pickFile(accept,callback) {picker=callback} function previewUrl(file) {return URL.createObjectURL(file)}
        async function send(url,fields,files) {
            requests.push({fields:{...fields},file:files&&files.image});
            if (failSecond && fields.program_name==='Second program') return {ok:false,error:'Please retry'};
            return {ok:true,program:{program_id:fields.program_id||++serial}};
        }
        window.AdminDialogs={confirm:async()=>confirm};
        ${fieldHelpers}
        ${adapterSource}
        const adapter=EntityAdapter();adapter.mount(document);
        const check=(condition,message)=>{if(!condition)throw Error(message)};
        const grid=fixture.querySelector('[data-ve-program-grid]');
        check(grid.querySelectorAll('article').length===1&&adapter.dirtyCount()===0,'Empty department starts with an unsaved placeholder');
        adapter.pick(grid.querySelector('[data-ve-add]'));
        check(grid.querySelectorAll('article').length===2&&adapter.dirtyCount()===1,'Add inserts a card directly into the grid');
        adapter.runPrimary();await lastSave;
        check(requests.length===0,'Blank headings are rejected before publishing');
        const edit=(el,value)=>{el.textContent=value;el.__ve.onChange(el,value);markEmpty(el)};
        const articles=grid.querySelectorAll('article');
        edit(articles[0].querySelector('h3'),'First program');edit(articles[0].querySelector('p'),'Inline description');
        edit(articles[1].querySelector('h3'),'Second program');
        const image=articles[0].querySelector('[data-ve-program-image]');
        const imageTools=adapter.toolbar(adapter.pick(image));imageTools.find(item=>item.label==='Add image').run();
        const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII='),c=>c.charCodeAt(0));
        picker(new File([bytes],'test.png',{type:'image/png'}));
        check(Boolean(image.querySelector('img')),'Image upload renders immediately in the card');
        adapter.runPrimary();await lastSave;
        check(requests.length===2&&adapter.dirtyCount()===1&&reloads===0,'Failed cards stay pending while successful cards are remembered');
        check(requests[0].file instanceof File&&requests[0].fields.description==='Inline description','Publish includes image and description');
        edit(articles[0].querySelector('h3'),'Updated first program');
        failSecond=false;adapter.runPrimary();await lastSave;
        check(requests.length===4&&requests[2].fields.program_id===501&&requests[3].fields.program_id===0,'Retry updates the saved card and creates only the failed card');
        check(adapter.dirtyCount()===0&&reloads===1,'Successful publish clears pending changes');
        adapter.pick(grid.querySelector('[data-ve-add]'));
        const draft=grid.querySelector('article:last-of-type');
        const remove=adapter.toolbar(adapter.pick(draft)).find(item=>item.danger);
        confirm=false;await remove.run();check(draft.isConnected,'Cancel keeps the draft');
        confirm=true;await remove.run();check(!draft.isConnected&&requests.length===4,'Deleting an unpublished card sends no server request');
        fixture.querySelector('#veDeptData').textContent=JSON.stringify({dept_id:2,kind:'basic'});
        Array.from(grid.querySelectorAll('article')).forEach(card=>card.remove());
        adapter.mount(document);
        check(grid.querySelectorAll('article').length===1&&adapter.dirtyCount()===0,'Basic Education starts with the same placeholder');
        adapter.pick(grid.querySelector('[data-ve-add]'));
        check(grid.querySelectorAll('article').length===2&&adapter.dirtyCount()===1,'Basic Education supports the same Add program action');
        const basicCards=grid.querySelectorAll('article');
        edit(basicCards[0].querySelector('h3'),'Basic program one');edit(basicCards[1].querySelector('h3'),'Basic program two');
        adapter.runPrimary();await lastSave;
        check(requests.length===6&&requests[4].fields.dept_id===2&&adapter.dirtyCount()===0,'Basic Education publishes to the shared program endpoint');
        return {requests:requests.length,pending:adapter.dirtyCount()};
    })()`);
    assert.equal(cards.pending,0);
    console.log('PASS: College and Basic Education use the same inline add/edit/Publish workflow; image previews, retries and draft deletion work');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
