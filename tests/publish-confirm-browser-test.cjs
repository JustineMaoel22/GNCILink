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

    const source=fs.readFileSync('assets/js/content-edit.js','utf8');
    const helper=source.slice(source.indexOf('    function confirmPublish('),source.indexOf("    document.addEventListener('mousedown'",source.indexOf('    function confirmPublish(')));
    // Extract only the function, excluding neighboring popover event handlers.
    const confirmFunction=helper.slice(0,helper.lastIndexOf('\n    }')+6);
    const elements=source.slice(source.indexOf('    function h('),source.indexOf('    function clip('));
    const css=fs.readFileSync('assets/css/content-editor-style.css','utf8');
    await evaluate(`(() => {
        const D=document; ${elements} ${confirmFunction}
        const style=D.createElement('style');style.textContent=${JSON.stringify(css)};D.head.appendChild(style);
        window.publishCount=0;
        window.openPublish=()=>confirmPublish('Your changes will be visible to website visitors.',()=>window.publishCount++);
        const button=D.createElement('button');button.id='testPublishTrigger';button.textContent='Publish';D.body.appendChild(button);button.focus();
        openPublish();openPublish();
    })()`);
    assert.equal(await evaluate("document.querySelectorAll('.ve-publish-dialog').length"),1,'Only one popup can open');
    assert.equal(await evaluate("document.activeElement.className"),'ve-publish-cancel','Keep editing has initial focus');
    const colors = await evaluate("(() => { const button=document.querySelector('.ve-publish-accept'), style=getComputedStyle(button); return {background:style.backgroundColor,text:style.color,disabled:button.disabled}; })()");
    assert.equal(colors.background, await evaluate('getComputedStyle(document.documentElement).getPropertyValue("--gnc-green").trim().startsWith("#") ? (()=>{const el=document.createElement("span");el.style.color="var(--gnc-green)";document.body.append(el);const color=getComputedStyle(el).color;el.remove();return color;})() : "rgb(9, 64, 36)"'), 'Publish uses the existing brand green outside the editor container');
    assert.equal(colors.text, 'rgb(255, 255, 255)', 'Publish has readable white text');
    assert.equal(colors.disabled, false, 'Publish is enabled');
    assert.equal(await evaluate('publishCount'),0,'Opening popup does not publish');
    await evaluate("document.querySelector('.ve-publish-cancel').click()");
    await until(()=>evaluate("!document.querySelector('.ve-publish-dialog')"),'Cancel did not dismiss');
    assert.equal(await evaluate('publishCount'),0,'Cancel does not publish');
    assert.equal(await evaluate("document.activeElement.id"),'testPublishTrigger','Focus returns to trigger');
    await evaluate('openPublish()');
    await send('Page.bringToFront');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await until(()=>evaluate("!document.querySelector('.ve-publish-dialog')"),'Escape did not dismiss popup');
    assert.equal(await evaluate('publishCount'),0);
    for(const [width,height] of [[1440,900],[390,844]]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await evaluate('openPublish()');await delay(220);
        assert.equal(await evaluate("(()=>{const r=document.querySelector('.ve-publish-dialog').getBoundingClientRect();return r.left>=0&&r.top>=0&&r.right<=innerWidth&&r.bottom<=innerHeight})()"),true,'Popup fits viewport');
        await evaluate("document.querySelector('.ve-publish-cancel').click()");
        await until(()=>evaluate("!document.querySelector('.ve-publish-dialog')"),'Cancel did not dismiss');
    }
    await evaluate('openPublish()');await delay(220);
    await send('Input.dispatchMouseEvent',{type:'mousePressed',x:1,y:1,button:'left',clickCount:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:1,y:1,button:'left',clickCount:1});
    await until(()=>evaluate("!document.querySelector('.ve-publish-dialog')"),'Backdrop did not dismiss popup');
    assert.equal(await evaluate('publishCount'),0);
    await evaluate("openPublish();document.querySelector('.ve-publish-accept').click()");
    await until(()=>evaluate('publishCount===1'),'Publish callback did not run');
    assert.equal(await evaluate('publishCount'),1,'Publish button saves exactly once');
    console.log('PASS: styled publish popup, explicit confirmation, cancellation, Escape, backdrop, duplicate prevention, focus restoration and responsive layout');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
