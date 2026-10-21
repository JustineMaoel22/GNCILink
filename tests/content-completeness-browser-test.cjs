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
    const service = fs.readFileSync('assets/js/content-completeness.js','utf8');
    const css = fs.readFileSync('assets/css/content-completeness.css','utf8');
    await evaluate(`(() => {
        const style=document.createElement('style');style.textContent=${JSON.stringify(css)};document.head.append(style);
        document.body.innerHTML='<section id="preview"><h2>Head of Office</h2><h3 data-ve-field="dean-name" data-ve-label="Name">Current name</h3><p id="bio" contenteditable="true" data-ve-field="dean-bio" data-ve-label="Description"></p><span id="phone" contenteditable="true" data-ve-field="dean-phone" data-ve-label="Phone number"></span><span id="photo" tabindex="0" data-ve-img="dean-photo" aria-label="Portrait"></span><span id="address" data-office-address>Set office location</span><p data-ve-field="optional" data-warning-optional="true"></p><div data-warning-active="false"><p data-ve-field="draft"></p></div><article data-ve-program-card="draft-1" data-warning-placeholder="true"><h3 data-ve-field="program-name"></h3></article></section>';
        ${service}
        window.saves=0;window.preview=document.getElementById('preview');
        window.openWarning=()=>{window.result=undefined;window.pendingWarning=ContentCompleteness.review({root:preview});pendingWarning.then(result=>{window.result=result;if(result)saves++});};
    })()`);
    assert.equal(await evaluate('ContentCompleteness.inspect(preview).missing.length'),4,'Only applicable empty fields are warned about');
    await evaluate('openWarning();void 0');
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Warning did not open');
    assert.equal(await evaluate('pendingWarning===ContentCompleteness.review({root:preview})'),true,'Duplicate reviews share one decision');
    assert.equal(await evaluate("document.querySelectorAll('.cms-completeness-list li').length"),4);
    await evaluate("document.querySelector('.cms-completeness-keep').click()");
    await until(()=>evaluate('window.result===false'),'Keep editing did not cancel');
    assert.equal(await evaluate('saves'),0);
    assert.equal(await evaluate("document.querySelectorAll('.cms-missing-field').length"),4,'Missing fields are highlighted');
    assert.equal(await evaluate("preview.querySelector('h3').textContent"),'Current name','Existing edits remain intact');
    await evaluate('openWarning();void 0');
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Warning did not reopen');
    await evaluate("document.querySelector('.cms-completeness-list button').click()");
    await until(()=>evaluate("document.activeElement.id==='bio'"),'Missing-field link did not focus the editor');
    await until(()=>evaluate("window.result===false&&!document.querySelector('.cms-completeness-dialog')"),'Field navigation did not complete cancellation');
    await evaluate('openWarning();void 0');
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Warning did not reopen');
    await evaluate("(()=>{const button=document.querySelector('.cms-completeness-publish');button.click();button.click()})()");
    await until(()=>evaluate('saves===1'),'Publish Anyway did not proceed');
    assert.equal(await evaluate('saves'),1,'Repeated clicks publish once');
    for(const width of [1440,768,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:844,deviceScaleFactor:1,mobile:false});
        await evaluate('openWarning();void 0');
        await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Responsive warning did not open');
        assert.equal(await evaluate("(()=>{const r=document.querySelector('.cms-completeness-dialog').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth+1&&r.height<=innerHeight})()"),true,'Warning fits '+width+'px');
        await send('Page.bringToFront');
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await until(()=>evaluate("!document.querySelector('.cms-completeness-dialog')"),'Escape did not dismiss');
    }
    await evaluate(`(() => {
        document.getElementById('bio').textContent='Description';document.getElementById('phone').textContent='123';document.getElementById('address').textContent='Defined office address';
        document.getElementById('photo').innerHTML='<img src="data:image/png;base64,AA==">';
    })()`);
    assert.equal(await evaluate('ContentCompleteness.inspect(preview).missing.length'),0,'Completed fields pass');
    assert.equal(await evaluate('ContentCompleteness.review({root:preview})'),true);
    assert.equal(await evaluate("Boolean(document.querySelector('.cms-completeness-dialog'))"),false,'Complete content shows no warning');
    await evaluate(`(() => {
        const form=document.createElement('form');form.id='postForm';form.setAttribute('data-content-publish','');
        form.innerHTML='<h2>Announcement</h2><label>Description<input name="description"></label><button type="submit">Publish</button>';
        document.body.append(form);window.formSaves=0;form.addEventListener('submit',event=>{event.preventDefault();formSaves++});form.requestSubmit();form.requestSubmit();
    })()`);
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Content form was not intercepted');
    assert.equal(await evaluate('formSaves'),0);
    await evaluate("document.querySelector('.cms-completeness-keep').click()");
    await until(()=>evaluate("!document.querySelector('.cms-completeness-dialog')"),'Form warning did not cancel');
    await evaluate("document.getElementById('postForm').requestSubmit()");
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Content form warning did not reopen');
    await evaluate("document.querySelector('.cms-completeness-publish').click()");
    await until(()=>evaluate('formSaves===1'),'Content form did not replay through its existing handler');
    assert.equal(await evaluate("document.querySelector('#postForm [name=allow_incomplete]').value"),'1');
    await evaluate(`(() => {
        const panel=document.createElement('div');panel.setAttribute('data-warning-scope','');panel.id='panel';
        panel.innerHTML='<label>Office description<input name="description"></label><button type="button" data-content-publish>Save changes</button>';
        document.body.append(panel);window.panelSaves=0;panel.querySelector('button').addEventListener('click',()=>panelSaves++);panel.querySelector('button').click();panel.querySelector('button').click();
    })()`);
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'AJAX panel warning did not open');
    await evaluate("document.querySelector('.cms-completeness-publish').click()");
    await until(()=>evaluate('panelSaves===1'),'AJAX save did not run exactly once');
    const editor=fs.readFileSync('assets/js/content-edit.js','utf8');
    const confirmHelper=editor.slice(editor.indexOf('    function confirmPublish('),editor.indexOf("    document.addEventListener('mousedown'",editor.indexOf('    function confirmPublish(')));
    const elements=editor.slice(editor.indexOf('    function h('),editor.indexOf('    function clip('));
    await evaluate(`(() => {
        const D=document, S={doc:preview,busy:false}, C={csrf:''};
        ${elements}
        function toast(message){throw Error(message)}
        ${confirmHelper}
        document.getElementById('bio').textContent='';window.visualSaves=0;
        window.publishVisual=()=>confirmPublish('Publish changes?',()=>visualSaves++);
        publishVisual();publishVisual();
    })()`);
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Visual Editor publish did not check completeness');
    assert.equal(await evaluate("document.querySelectorAll('.cms-completeness-dialog').length"),1);
    await evaluate("document.querySelector('.cms-completeness-keep').click()");
    await until(()=>evaluate("!document.querySelector('.cms-completeness-dialog')"),'Visual warning did not cancel');
    assert.equal(await evaluate('visualSaves'),0);
    await evaluate('publishVisual();void 0');
    await until(()=>evaluate("Boolean(document.querySelector('.cms-completeness-dialog[open]'))"),'Visual warning did not reopen');
    await evaluate("document.querySelector('.cms-completeness-publish').click()");
    await until(()=>evaluate('visualSaves===1'),'Visual publish callback did not run');
    assert.equal(await evaluate("Boolean(document.querySelector('.ve-publish-dialog'))"),false,'Publish Anyway goes directly through the existing callback');
    await evaluate("document.getElementById('bio').textContent='Complete description';publishVisual();void 0");
    await until(()=>evaluate("Boolean(document.querySelector('.ve-publish-dialog[open]'))"),'Normal complete-page publishing changed');
    await evaluate("document.querySelector('.ve-publish-accept').click()");
    await until(()=>evaluate('visualSaves===2'),'Complete-page publishing callback did not run');
    console.log('PASS: applicable missing fields, optional exclusions, cancel/highlights, field navigation, Publish Anyway, double-click protection, Escape, responsive layout and delegated CMS form/AJAX saves');
    console.log('PASS: Visual Editor publishing uses the warning, preserves cancellation and publishes once; complete pages retain the existing flow');

})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
