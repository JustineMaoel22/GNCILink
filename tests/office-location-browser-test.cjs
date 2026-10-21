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
    await evaluate("document.querySelector('[data-office-location]').click()");
    await until(() => evaluate("Boolean(document.querySelector('.office-map-frame').contentWindow.B && document.querySelector('.office-map-frame').contentWindow.current)"), 'Map did not select stored building');
    const state = await evaluate(`(() => {
        const button = document.querySelector('[data-office-location]'), data = JSON.parse(button.dataset.officeLocation), map = document.querySelector('.office-map-frame').contentWindow;
        return { selected: map.current, expected: data.building_id, highlighted: map.document.querySelector('.bld.sel').dataset.id, marker: map.document.getElementById('dest').classList.contains('go'), title: document.getElementById('officeMapTitle').textContent, office: data.name, dialogs: document.querySelectorAll('#officeMapModal').length };
    })()`);
    assert.equal(state.selected, state.expected); assert.equal(state.highlighted, state.expected); assert.equal(state.marker, true); assert.equal(state.title, state.office); assert.equal(state.dialogs, 1);
    await evaluate("document.querySelector('.office-map-frame').contentDocument.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}))");
    await until(() => evaluate("!document.getElementById('officeMapModal').open"), 'Escape inside map did not close modal');
    assert.equal(await evaluate("document.activeElement.matches('[data-office-location]')"), true, 'Focus restored to location card');
    // Reuse the same iframe for another office to prove selection is dynamic.
    await evaluate(`(() => { const button = document.querySelector('[data-office-location]'); button.dataset.officeLocation = JSON.stringify({name:'Other office',building_id:'banzali',building:'Banzali Hall',floor:'3',room:'301',address:'Banzali Hall, Floor 3, Room 301'}); button.click(); })()`);
    assert.equal(await evaluate("document.querySelector('.office-map-frame').contentWindow.current"), 'banzali');
    await send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape' });
    await send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape' });
    await until(() => evaluate("!document.getElementById('officeMapModal').open"), 'Escape on dialog did not close');
    for (const [width, height] of [[390, 844], [768, 1024], [1440, 900], [844, 390]]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 600 });
        await evaluate("document.querySelector('[data-office-location]').click()"); await delay(250);
        const fits = await evaluate("(() => { const r=document.getElementById('officeMapModal').getBoundingClientRect(); return r.left>=0 && r.top>=0 && r.right<=innerWidth && r.bottom<=innerHeight && document.querySelector('.office-map-frame').getBoundingClientRect().height>=120; })()");
        assert.equal(fits, true, `Modal fits ${width}x${height}`);
        await evaluate("document.querySelector('.office-map-close').click()");
        await until(() => evaluate("!document.getElementById('officeMapModal').open"), 'Close button failed');
    }
    await evaluate("document.querySelector('[data-office-location]').click()"); await delay(250);
    await send('Input.dispatchMouseEvent', { type: 'mousePressed', x: 1, y: 1, button: 'left', clickCount: 1 });
    await send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: 1, y: 1, button: 'left', clickCount: 1 });
    await until(() => evaluate("!document.getElementById('officeMapModal').open"), 'Backdrop click did not close modal');
    await send('Page.navigate', { url: (process.env.BASE_URL || 'http://localhost:8080') + '/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php' });
    await until(() => evaluate("Boolean(document.querySelector('[data-office-location]') && document.getElementById('officeMapModal') && document.readyState === 'complete')"), 'Montessori office card unavailable');
    await evaluate("document.querySelector('[data-office-location]').click()");
    await until(() => evaluate("document.querySelector('.office-map-frame').contentWindow.current === 'banzali'"), 'Montessori office did not select Banzali Hall');
    await until(() => evaluate(`(() => {
        const map = document.querySelector('.office-map-frame').contentWindow;
        const route = map.document.getElementById('path'), style = map.getComputedStyle(route);
        return route.classList.contains('go') && route.getAttribute('d') === map.B.banzali.d && route.getTotalLength() > 0 && style.visibility === 'visible' && parseFloat(style.strokeDashoffset) === 0;
    })()`), 'Montessori walking route did not finish drawing');
    console.log('PASS: stored building highlighting and marker, reusable modal, Escape from map and dialog, focus restore, close button, backdrop, four viewport sizes and visible Montessori route to Banzali Hall');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
