// First-visit regression check in installed headless Chrome; no npm dependencies.
// Serves the local map into the public page through browser interception only.
// Does not upload or change hosted files. --baseline uses the hosted map instead.
// Run with --phone and/or --2d to check mobile layout and the SVG fallback.
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

    const phone=process.argv.includes('--phone'),fallback=process.argv.includes('--2d'),baseline=process.argv.includes('--baseline');
    await send('Network.enable');await send('Page.enable');await send('Network.setCacheDisabled',{cacheDisabled:true});
    await send('Emulation.setDeviceMetricsOverride',{width:phone?390:1366,height:phone?844:900,deviceScaleFactor:1,mobile:phone});
    if(phone)await send('Emulation.setTouchEmulationEnabled',{enabled:true});
    if(fallback)await send('Network.setBlockedURLs',{urls:['*cdnjs.cloudflare.com*']});
    if(!baseline){
      socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.method==='Fetch.requestPaused')send('Fetch.fulfillRequest',{requestId:p.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:fs.readFileSync('campus_navigator.html').toString('base64')}).catch(error=>console.error(error));});
      await send('Fetch.enable',{patterns:[{urlPattern:'*campus_navigator.html*',requestStage:'Request'}]});
    }
    await send('Page.navigate',{url:'https://gncipampanga.com/'});
    await until(()=>evaluate("!!document.querySelector('.gnc-map-frame')"),'No homepage map');
    await delay(7000);
    await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close());const f=document.querySelector('.gnc-map-frame');window.scrollTo({top:scrollY+f.getBoundingClientRect().top-120,behavior:'instant'})");
    await until(()=>evaluate("(()=>{const w=document.querySelector('.gnc-map-frame').contentWindow;return "+(fallback?"!!w.document.querySelector('.chip')":"!!w.__campus3d?.cam")+"})()"),'Map did not initialize');
    const point=await evaluate("(()=>{const f=document.querySelector('.gnc-map-frame'),r=f.getBoundingClientRect(),b=f.contentDocument.querySelector('#zin').getBoundingClientRect();return {x:r.x+b.x+b.width/2,y:r.y+b.y+b.height/2}})()");
    const initial=await evaluate("document.querySelector('.gnc-map-frame').contentDocument.querySelector('#map').getAttribute('viewBox')");
    await send('Input.dispatchMouseEvent',{type:'mousePressed',...point,button:'left',clickCount:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',...point,button:'left',clickCount:1});
    const zoom=await evaluate("new Promise(resolve=>setTimeout(()=>{const w=document.querySelector('.gnc-map-frame').contentWindow;resolve({cam:w.__campus3d?.cam,view:w.document.querySelector('#map').getAttribute('viewBox'),clockGap:w.performance.now()-w.document.timeline.currentTime})},1400))");
    console.log({phone,fallback,baseline,zoom});
    if(fallback)assert.notEqual(zoom.view,initial,'2D zoom must respond on first visit');
    else assert.ok(Math.abs(zoom.cam.zoom-1.6)<.02,'3D zoom must respond on first visit');
    await evaluate("document.querySelector('.gnc-map-frame').contentDocument.querySelector('#c-beta').click()");
    await delay(3400);
    const route=await evaluate("(()=>{const w=document.querySelector('.gnc-map-frame').contentWindow;return {cam:w.__campus3d?.cam,status:w.document.querySelector('#status').textContent,view:w.document.querySelector('#map').getAttribute('viewBox')}})()");
    assert.equal(route.status,'Beta Building');
    if(fallback)assert.notEqual(route.view,zoom.view,'2D route camera must follow selection');
    else assert.ok(route.cam.zoom>2,'3D route camera must follow selection');
    console.log('PASS first-visit zoom and building route',route);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});


