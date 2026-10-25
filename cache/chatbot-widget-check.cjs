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

    await until(()=>evaluate("document.querySelector('#gncbot.gncbot-ready .gncbot-mascot')?.naturalWidth > 0"),'Robot widget image unavailable');
    await delay(2500);
    fs.mkdirSync('cache/chatbot-widget',{recursive:true});
    for(const [width,height] of [[1440,900],[390,844]]){
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await delay(150);
        const state=await evaluate(`(()=>{const b=document.querySelector('.gncbot-launcher'),r=b.getBoundingClientRect(),image=b.querySelector('img');return {width:r.width,height:r.height,inside:r.right<=innerWidth&&r.bottom<=innerHeight,loaded:image.complete&&image.naturalWidth>0,background:getComputedStyle(b).backgroundColor,accessible:!!b.getAttribute('aria-label')};})()`);
        assert.equal(state.width,width===1440?112:84);assert.equal(state.height,state.width);assert.equal(state.inside,true);assert.equal(state.loaded,true);assert.equal(state.accessible,true);assert.equal(state.background,'rgba(0, 0, 0, 0)');
        await evaluate("document.querySelector('.gncbot-launcher').click()");
        assert.equal(await evaluate("document.querySelector('.gncbot-launcher').getAttribute('aria-expanded')"),'true');
        assert.equal(await evaluate("document.querySelector('.gncbot-panel').hidden"),false);
        if(width===390)assert.equal(await evaluate("document.body.classList.contains('gncbot-noscroll')"),true);
        await evaluate("document.querySelector('.gncbot-close').click()");
        assert.equal(await evaluate("document.querySelector('.gncbot-panel').hidden"),true);
        assert.equal(await evaluate("document.querySelector('.gncbot-launcher').getBoundingClientRect().width"),state.width);
        await evaluate("document.querySelector('.gncbot-launcher').click();document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))");
        assert.equal(await evaluate("document.querySelector('.gncbot-panel').hidden"),true);
        const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync('cache/chatbot-widget/'+width+'.png',Buffer.from(shot.data,'base64'));
    }
    console.log('PASS: robot image loads, desktop/mobile sizing fits, chat opens and closes, Escape works, and the robot returns after closing. No chatbot API calls made.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});

