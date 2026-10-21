const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const vm = require('node:vm');
const { spawn } = require('node:child_process');

const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
const start = source.indexOf('    var FRAME_CSS = [');
const frameCSS = vm.runInNewContext(source.slice(start, source.indexOf('    function injectStyle', start)) + '\nFRAME_CSS');
const css = fs.readFileSync('assets/css/bootstrap.min.css', 'utf8') + '\n'
    + fs.readFileSync('assets/css/index-style.css', 'utf8').replace(/@import[^;]+;/g, '') + '\n' + frameCSS;
const fixture = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">
<style>:root{--gnc-gold:#eaba3b;--gnc-green:#094024}${css}</style></head><body>
<section class="hero-slideshow"><div class="hero-slide active"><div class="container h-100"><div class="hero-content">
<h1 class="hero-title-line" data-ve-field="slide-title1" contenteditable="plaintext-only">Empowering mind. Inspiring futures.</h1>
<h1 class="hero-title-line ve-empty" data-ve-field="slide-title2" data-ve-ph="Second line (optional)" contenteditable="plaintext-only"></h1>
<div class="hero-desc">A Legacy of academic excellence, character formation, and service to the community since 1918.</div>
<div class="hero-ctas"><a class="btn-enroll">Enroll now</a><a class="btn-explore">Explore GNC</a></div>
</div></div></div></section><div id="gncbot">Chat launcher</div></body></html>`;
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnc-editor-mobile-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0',
    '--user-data-dir=' + profile, 'about:blank'
], { windowsHide: true, stdio: 'ignore' });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) {
    for (const end = Date.now() + 10000; Date.now() < end;) {
        if (await work()) return;
        await delay(30);
    }
    throw Error(message);
}
let socket;
(async () => {
    await until(() => fs.existsSync(path.join(profile, 'DevToolsActivePort')), 'Chrome did not start');
    const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let serial = 0;
    const pending = new Map();
    socket.addEventListener('message', event => {
        const packet = JSON.parse(event.data);
        if (pending.has(packet.id)) { pending.get(packet.id)(packet); pending.delete(packet.id); }
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++serial;
        const timer = setTimeout(() => { pending.delete(id); reject(Error('Browser timeout: ' + method)); }, 10000);
        pending.set(id, packet => {
            clearTimeout(timer);
            packet.error ? reject(Error(JSON.stringify(packet.error))) : resolve(packet.result);
        });
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
    // An actual narrow iframe exercises the editor's device mode independently of the desktop viewport.
    await evaluate(`document.body.innerHTML = '<iframe style="width:390px;height:520px;border:0"></iframe>'; document.querySelector('iframe').srcdoc = ${JSON.stringify(fixture)};`);
    await until(() => evaluate(`!!document.querySelector('iframe').contentDocument.querySelector('[data-ve-field]')`), 'Preview did not load');
    for (const height of [520, 736]) {
        await evaluate(`document.querySelector('iframe').style.height='${height}px'`);
        const metrics = await evaluate(`(() => {
            const frame=document.querySelector('iframe'), doc=frame.contentDocument, win=frame.contentWindow;
            const hint=doc.querySelector('.ve-empty'), title=doc.querySelector('[data-ve-field="slide-title1"]');
            const content=doc.querySelector('.hero-content').getBoundingClientRect();
            return {hintHeight:hint.getBoundingClientRect().height,titleSize:parseFloat(win.getComputedStyle(title).fontSize),
                contentTop:content.top,contentBottom:content.bottom,viewportHeight:win.innerHeight,
                overflow:doc.documentElement.scrollWidth>win.innerWidth,
                chatbot:win.getComputedStyle(doc.getElementById('gncbot')).display};
        })()`);
        assert.ok(metrics.hintHeight < 30, 'Optional headline remains a compact editor hint');
        assert.ok(metrics.titleSize < 30, 'Hero uses mobile font size within desktop editor');
        assert.ok(metrics.contentTop >= 0 && metrics.contentBottom <= metrics.viewportHeight, 'Hero content and both buttons fit in the preview');
        assert.equal(metrics.overflow, false, 'No horizontal overflow');
        assert.equal(metrics.chatbot, 'none', 'Chat launcher does not cover editor controls');
    }
    assert.deepEqual(await evaluate(`(() => {
        const doc=document.querySelector('iframe').contentDocument;
        doc.documentElement.classList.add('ve-previewing');
        const hint=doc.querySelector('.ve-empty');
        return [hint.getBoundingClientRect().height, hint.textContent];
    })()`), [0, ''], 'Visitor preview hides the hint without saving placeholder text');
    assert.ok(await evaluate(`(() => {
        const doc=document.querySelector('iframe').contentDocument;
        const title=doc.querySelector('[data-ve-field="slide-title2"]');
        title.textContent='A real second headline'; title.classList.remove('ve-empty');
        return title.getBoundingClientRect().height>0;
    })()`), 'A real second headline remains visible in visitor preview');
    console.log('PASS: mobile iframe typography, compact empty headline, hero fit at short and tall heights, no overflow, chatbot exclusion and visitor preview.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close();
    browser.kill();
    await delay(500);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnc-editor-mobile-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
