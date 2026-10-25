// Runs against the local PHP site in an installed headless Chrome; no npm dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn } = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-facebook-links-'));
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


    const base = process.env.BASE_URL || 'http://localhost:8080';
    await send('Network.enable');
    await send('Network.setBlockedURLs', { urls: ['*fonts.googleapis.com*', '*fonts.gstatic.com*', '*cdn.jsdelivr.net*', '*facebook.com*', '*cloudflareinsights.com*', '*cdnjs.cloudflare.com*'] });
    await send('Page.addScriptToEvaluateOnNewDocument', {source:"try {sessionStorage.setItem('gncIntroSeen','1')} catch (_) {}"});
    let checked = 0;
    for (const [width, height] of [[1440,900], [390,844]]) {
        await send('Emulation.setDeviceMetricsOverride', {width,height,deviceScaleFactor:1,mobile:width<600});
        for (const path of ['/', '/pages/news-and-announcement.php', '/pages/student%20life/campus-event.php']) {
            await send('Page.navigate', {url:base + path});
            await until(() => evaluate("document.readyState !== 'loading' && !!document.querySelector('footer')"), 'Page unavailable: ' + path);
            const links = await evaluate(`[...document.querySelectorAll('a[href*="facebook.com"]')].map(a=>({href:a.href,target:a.target,rel:a.rel})).filter(a=>new URL(a.href).pathname==='/permalink.php')`);
            if (path !== '/pages/student%20life/campus-event.php') assert.ok(links.length > 0, 'Expected Facebook posts on ' + path);
            for (const link of links) {
                const url = new URL(link.href);
                assert.match(url.searchParams.get('story_fbid'), /^\d+$/);
                assert.match(url.searchParams.get('id'), /^\d+$/);
                assert.equal(link.target, '_blank');
                assert.ok(link.rel.includes('noopener'));
                checked++;
            }
            assert.equal(await evaluate(`document.querySelectorAll('a[href*="facebook.com/"]').length > 0 && [...document.querySelectorAll('a[href*="facebook.com/"]')].some(a=>/^\\/\\d+\\/posts\\/\\d+\\/?$/.test(new URL(a.href).pathname))`), false, 'No legacy numeric links remain on ' + path);
            if (path === '/pages/student%20life/campus-event.php') continue;
            const click = await evaluate(`(() => {
                const card = [...document.querySelectorAll('.gnc-news-row, .na-card')].find(c=>c.querySelector('a[href*="permalink.php"]'));
                if (!card) return null;
                const link=card.querySelector('a[href*="permalink.php"]');
                const opened=[];window.open=(...args)=>{opened.push(args);return null;};
                // Preserve ordinary anchor behavior and ensure card background uses the same URL.
                let direct=false;link.addEventListener('click',e=>{direct=!e.defaultPrevented;e.preventDefault();},{once:true});
                link.click();card.click();
                return {href:link.href,opened,direct};
            })()`);
            assert.ok(click, 'Facebook announcement card exists');
            assert.equal(click.direct, true);
            assert.deepEqual(click.opened, [[click.href, '_blank', 'noopener']]);
        }
    }
    console.log('PASS: ' + checked + ' rendered post links; desktop/mobile homepage, news and campus events; anchor/card targets and safe external-link attributes. Chromium checks do not emulate the Facebook iOS app.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-facebook-links-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
