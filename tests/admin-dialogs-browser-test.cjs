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
    await send('Page.navigate', { url: (process.env.BASE_URL || 'http://localhost:8080') + '/pages/academics/college/college-department.php?slug=cased' });
    try {
        await until(() => evaluate("Boolean(document.querySelector('[data-office-location]') && document.getElementById('officeMapModal') && document.readyState !== 'loading')"), 'CASED office card unavailable');
    } catch (error) {
        console.error(await evaluate("JSON.stringify({url:location.href,ready:document.readyState,title:document.title,body:document.body?.innerText.slice(0,1000)})"));
        throw error;
    }


    const script=fs.readFileSync('assets/js/admin-dialogs.js','utf8');
    const css=fs.readFileSync('assets/css/admin-dialogs.css','utf8');
    await evaluate(`(() => { ${script} const style=document.createElement('style');style.textContent=${JSON.stringify(css)};document.head.appendChild(style); const button=document.createElement('button');button.id='dialogTrigger';button.textContent='Delete badge';document.body.appendChild(button);button.focus();window.result=null;AdminDialogs.confirm('Delete "New badge" for good?\\n\\nThis cannot be undone.').then(value=>window.result=value); })()`);
    await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Confirmation unavailable');
    assert.equal(await evaluate("document.querySelector('.gnc-dialog-accept').textContent"),'Delete');
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.gnc-dialog-accept')).backgroundColor"),'rgb(164, 40, 40)');
    assert.equal(await evaluate('result'),null,'No action before decision');
    await evaluate("document.querySelector('.gnc-dialog-cancel').click()");
    await until(()=>evaluate('result===false'),'Cancel failed');
    assert.equal(await evaluate('document.activeElement.id'),'dialogTrigger');
    for(const method of ['escape','backdrop']) {
        await evaluate("result=null;AdminDialogs.confirm('Discard your changes?').then(value=>result=value);void 0");
        await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Popup unavailable');await delay(200);
        if(method==='escape') {
            await send('Page.bringToFront');
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
            await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        } else {
            await send('Input.dispatchMouseEvent',{type:'mousePressed',x:1,y:1,button:'left',clickCount:1});
            await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:1,y:1,button:'left',clickCount:1});
        }
        await until(()=>evaluate('result===false'),'Dismiss failed: '+method);
    }
    for(const [width,height] of [[1440,900],[390,844]]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await evaluate("result=null;AdminDialogs.confirm('Continue with this change?').then(value=>result=value);void 0");
        await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Popup unavailable');await delay(200);
        assert.equal(await evaluate("(()=>{const r=document.querySelector('.gnc-dialog').getBoundingClientRect();return r.left>=0&&r.top>=0&&r.right<=innerWidth&&r.bottom<=innerHeight})()"),true,'Fits viewport');
        assert.equal(await evaluate("getComputedStyle(document.querySelector('.gnc-dialog-accept')).backgroundColor"),'rgb(11, 61, 34)');
        await evaluate("document.querySelector('.gnc-dialog-accept').click()");
        await until(()=>evaluate('result===true'),'Confirm failed');
    }
    await evaluate("result=null;AdminDialogs.alert('<img src=x onerror=bad()>').then(()=>result=true);AdminDialogs.alert('Second notice');void 0");
    await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Notice unavailable');
    assert.equal(await evaluate("document.querySelector('.gnc-dialog-message img')!==null"),false);
    assert.equal(await evaluate("document.getElementById('gncDialogMessage').textContent"),'<img src=x onerror=bad()>');
    assert.equal(await evaluate("document.querySelectorAll('.gnc-dialog-actions button').length"),1,'Notice has one OK button');
    await evaluate("document.querySelector('.gnc-dialog-accept').click()");
    await until(()=>evaluate("document.getElementById('gncDialogMessage')?.textContent==='Second notice'"),'Notices must queue');
    await evaluate("document.querySelector('.gnc-dialog-accept').click()");
    await until(()=>evaluate("!document.querySelector('.gnc-dialog')"),'Final notice did not close');
    console.log('PASS: destructive and ordinary popup styling, explicit decisions, Cancel, Escape, backdrop, focus restoration, mobile layout, safe messages and queued notices');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
