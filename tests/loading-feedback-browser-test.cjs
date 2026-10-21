const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');

const root = path.resolve(__dirname, '..');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnc-loading-'));
const held = new Map();
const fixture = `<!doctype html><html><head>
<link rel="stylesheet" href="/assets/css/loading-feedback.css">
<script src="/assets/js/loading-feedback.js"></script></head><body>
<a id="navigate" href="/next">Next page</a>
<a id="anchor" href="#section">Section</a><div id="section">Section</div>
<a id="cancelled" href="/next" onclick="event.preventDefault()">Cancelled</a>
<a id="download" href="/next" download>Download</a>
<form id="form" action="/save" method="post"><input name="title" value="Keep this value">
<button name="action" value="publish">Publish</button></form>
</body></html>`;
const server = http.createServer((req, res) => {
    if (req.url.startsWith('/assets/')) {
        res.setHeader('Content-Type', req.url.endsWith('.js') ? 'application/javascript' : 'text/css');
        res.end(fs.readFileSync(path.join(root, req.url)));
    } else if (req.url === '/next' || req.url === '/save' || req.url.startsWith('/slow/') || req.url === '/api/track-visit.php') {
        let body = '';
        req.on('data', chunk => { body += chunk; });
        req.on('end', () => held.set(req.url, { res, body }));
    } else {
        res.setHeader('Content-Type', 'text/html');
        res.end(fixture);
    }
});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) {
    for (const end = Date.now() + 10000; Date.now() < end;) {
        if (await work()) return;
        await delay(30);
    }
    throw Error(message);
}
let browser, socket;
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const base = 'http://127.0.0.1:' + server.address().port;
    browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
        '--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0',
        '--user-data-dir=' + profile, 'about:blank'
    ], { windowsHide: true, stdio: 'ignore' });
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
        const timer = setTimeout(() => {
            pending.delete(id);
            reject(Error('Browser command timed out: ' + method + ' ' + JSON.stringify(params)));
        }, 12000);
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
    const visible = () => evaluate('!!document.querySelector("#gncLoadingFeedback:not([hidden])")');
    const finish = (url, status = 200) => {
        const request = held.get(url);
        held.delete(url);
        request.res.writeHead(status, { 'Content-Type': 'text/html' });
        request.res.end(fixture);
    };
    await send('Page.enable');
    await send('Runtime.enable');
    await send('Runtime.addBinding', { name: '__reportLoading' });
    let navigationFeedback = 0;
    socket.addEventListener('message', event => {
        const packet = JSON.parse(event.data);
        if (packet.method === 'Runtime.bindingCalled' && packet.params.name === '__reportLoading') navigationFeedback++;
    });
    await send('Page.addScriptToEvaluateOnNewDocument', { source: `
        document.addEventListener('DOMContentLoaded', () => {
            new MutationObserver(() => {
                if (document.querySelector('#gncLoadingFeedback:not([hidden])')) window.__reportLoading('visible');
            }).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['hidden'], childList: true });
        });
    ` });
    await send('Page.navigate', { url: base });
    await until(() => evaluate('document.readyState === "complete" && !!window.GNCLoading'), 'Fixture did not load');
    assert.equal(await visible(), false);
    await evaluate(`void fetch('/slow/a'); void fetch('/slow/b', { method: 'POST' });`);
    await until(visible, 'Overlapping requests did not show feedback');
    await until(() => held.has('/slow/a') && held.has('/slow/b'), 'Requests missing');
    finish('/slow/a');
    await delay(100);
    assert.equal(await visible(), true, 'Second request keeps indicator visible');
    finish('/slow/b', 500);
    await until(async () => !await visible(), 'HTTP error left indicator visible');
    await evaluate(`window.controller = new AbortController(); void fetch('/slow/abort', { signal: controller.signal }).catch(() => {});`);
    await until(visible, 'Abort request did not show feedback');
    await evaluate('controller.abort()');
    await until(async () => !await visible(), 'Aborted request left feedback visible');
    await evaluate(`void fetch('/api/track-visit.php');`);
    await delay(250);
    assert.equal(await visible(), false, 'Background analytics remain quiet');
    await until(() => held.has('/api/track-visit.php'), 'Analytics missing');
    finish('/api/track-visit.php');
    await evaluate(`document.getElementById('cancelled').click(); document.getElementById('anchor').click();`);
    await delay(250);
    assert.equal(await visible(), false, 'Cancelled links and anchors remain quiet');
    await evaluate(`document.getElementById('form').addEventListener('submit', e => e.preventDefault(), { once: true }); document.getElementById('form').requestSubmit();`);
    await delay(250);
    assert.equal(await visible(), false, 'Cancelled form remains quiet');
    navigationFeedback = 0;
    await evaluate(`document.querySelector('#form button').click()`);
    await until(() => held.has('/save'), 'Native form did not submit');
    await until(() => navigationFeedback > 0, 'Native submission did not show feedback');
    assert.equal(held.get('/save').body, 'title=Keep+this+value&action=publish', 'Submitting preserves button and field values');
    finish('/save');
    await until(() => evaluate('location.pathname === "/save" && document.readyState === "complete"'), 'Saved page missing');
    assert.equal(await visible(), false, 'New page starts idle');
    navigationFeedback = 0;
    await evaluate(`document.getElementById('navigate').click()`);
    await until(() => held.has('/next'), 'Navigation did not start');
    await until(() => navigationFeedback > 0, 'Page navigation did not show feedback');
    finish('/next');
    await until(() => evaluate('location.pathname === "/next" && document.readyState === "complete"'), 'Next page missing');
    await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await evaluate('window.endLoading = GNCLoading.begin("Loading...")');
    await until(visible, 'Manual loading did not show');
    assert.equal(await evaluate('getComputedStyle(document.getElementById("gncLoadingFeedback"), "::before").animationName'), 'none');
    assert.equal(await evaluate('document.getElementById("gncLoadingFeedback").getBoundingClientRect().right <= innerWidth'), true);
    await evaluate('window.dispatchEvent(new PageTransitionEvent("pageshow", { persisted: true }))');
    assert.equal(await visible(), false, 'Restoring from browser history clears stale feedback');
    console.log('PASS: concurrent requests, HTTP errors, aborts, analytics exclusion, cancelled actions, navigation, native form values, mobile layout, reduced motion and history restoration.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close();
    if (browser) browser.kill();
    server.closeAllConnections();
    server.close();
    await delay(500);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnc-loading-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
