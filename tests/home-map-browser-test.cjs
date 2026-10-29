// Runs against the local PHP site in an installed headless Chrome; no npm dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn } = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-home-map-'));
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
    await send('Network.setCacheDisabled', { cacheDisabled: true });
    await send('Network.setBlockedURLs', { urls: ['*fonts.gstatic.com*', '*cdn.jsdelivr.net*', '*facebook.com*', '*cloudflareinsights.com*', '*cdnjs.cloudflare.com*'] });
    // Leave the optional font stylesheet pending to simulate a slow external service.
    let fontPending = false;
    socket.addEventListener('message', event => {
        const packet = JSON.parse(event.data);
        if (packet.method === 'Fetch.requestPaused') fontPending = true;
    });
    await send('Fetch.enable', { patterns: [{ urlPattern: '*fonts.googleapis.com*' }] });
    await send('Page.navigate', { url: base + '/campus_navigator.html' });
    await until(() => fontPending, 'Font request was not intercepted');
    await delay(1500);
    assert.equal(await evaluate("!!document.getElementById('map') && document.querySelectorAll('.chip').length === 9"), true, 'Map must initialize while the optional font stylesheet is still pending');
    assert.equal(await evaluate("(()=>{const m=document.getElementById('map'),r=m.getBoundingClientRect(),v=m.viewBox.baseVal;return v.x<=0&&v.y<=0&&v.x+v.width>=1421.9&&v.y+v.height>=2303.9&&Math.abs(v.width/v.height-r.width/r.height)<.001;})()"),true,'Cold startup shows the entire campus before fonts finish');
    await evaluate("document.getElementById('map').style.width='85%'");
    await until(()=>evaluate("(()=>{const m=document.getElementById('map'),r=m.getBoundingClientRect(),v=m.viewBox.baseVal;return Math.abs(v.width/v.height-r.width/r.height)<.001;})()"),'Camera must follow SVG viewport changes even when its outer container stays unchanged');
    await evaluate("document.getElementById('map').style.width=''");
    await evaluate("go('beta')");
    assert.equal(await evaluate("current === 'beta' && document.getElementById('path').classList.contains('go')"), true, 'Building routes work without the external font');
    await send('Fetch.disable');
    await send('Network.setBlockedURLs', { urls: ['*fonts.googleapis.com*', '*fonts.gstatic.com*', '*cdn.jsdelivr.net*', '*facebook.com*', '*cloudflareinsights.com*', '*cdnjs.cloudflare.com*'] });
    async function buildingPoint(id) {
        await evaluate('fitView(false)');
        return evaluate(`(() => {
            const building=document.querySelector('#b-${id} .main'), b=building.getBBox();
            for(const fy of [.5,.25,.75,.1,.9]) for(const fx of [.5,.25,.75,.1,.9]) {
                const point=new DOMPoint(b.x+b.width*fx,b.y+b.height*fy).matrixTransform(building.getScreenCTM());
                if(document.elementFromPoint(point.x,point.y)?.closest('.bld')?.dataset.id === '${id}') return {x:point.x,y:point.y};
            }
            throw Error('No visible clickable point for ${id}');
        })()`);
    }
    // Real browser input exercises pointer capture; dispatching a synthetic click does not.
    for (const id of ['beta','banzali','tanjangco','goseco','puno','limlingan','narciso','parking1','parking2']) {
        const point=await buildingPoint(id);
        await send('Input.dispatchMouseEvent', {type:'mouseMoved',...point});
        await send('Input.dispatchMouseEvent', {type:'mousePressed',...point,button:'left',clickCount:1});
        await send('Input.dispatchMouseEvent', {type:'mouseReleased',...point,button:'left',clickCount:1});
        assert.equal(await evaluate('current'), id, 'Clicking the actual '+id+' building selects its route');
    }
    const dragStart=await buildingPoint('beta');
    await evaluate('clear(true)');
    await send('Input.dispatchMouseEvent', {type:'mousePressed',...dragStart,button:'left',clickCount:1});
    await send('Input.dispatchMouseEvent', {type:'mouseMoved',x:dragStart.x+40,y:dragStart.y+20,button:'left',buttons:1});
    await send('Input.dispatchMouseEvent', {type:'mouseReleased',x:dragStart.x+40,y:dragStart.y+20,button:'left',clickCount:1});
    assert.equal(await evaluate('current'), null, 'Dragging from a building must not select it');
    await send('Emulation.setDeviceMetricsOverride', {width:390,height:620,deviceScaleFactor:1,mobile:true});
    await send('Emulation.setTouchEmulationEnabled', {enabled:true});
    for (const id of ['tanjangco','banzali']) {
        const point=await buildingPoint(id);
        await send('Input.dispatchTouchEvent', {type:'touchStart',touchPoints:[point]});
        await send('Input.dispatchTouchEvent', {type:'touchEnd',touchPoints:[]});
        await until(()=>evaluate('current === '+JSON.stringify(id)), 'Touching the actual '+id+' building must select its route');
    }
    await send('Emulation.setTouchEmulationEnabled', {enabled:false});
    const interruptedDrag = await evaluate(`(() => {
        fitView(false);
        const map=document.getElementById('map'),r=map.getBoundingClientRect(),x=r.left+r.width/2,y=r.top+r.height/2;
        map.dispatchEvent(new WheelEvent('wheel',{clientX:x,clientY:y,deltaY:-70,ctrlKey:true,cancelable:true}));
        const before=map.viewBox.baseVal.width;
        const pointer=(type,id,dx=0)=>map.dispatchEvent(new PointerEvent(type,{pointerId:id,pointerType:'touch',clientX:x+dx,clientY:y,bubbles:true}));
        pointer('pointerdown',101);
        window.dispatchEvent(new Event('blur'));
        pointer('pointerdown',102);pointer('pointermove',102,40);pointer('pointerup',102,40);
        return {before,after:map.viewBox.baseVal.width};
    })()`);
    assert.ok(Math.abs(interruptedDrag.before-interruptedDrag.after)<1,'An interrupted drag must not turn the next touch into an unintended pinch');
    for (const [width, height] of [[1440, 900], [390, 844]]) {
        await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 600 });
        await send('Page.navigate', { url: base + '/' });
        await until(() => evaluate("!!document.querySelector('.gnc-map-frame') && document.readyState !== 'loading'"), 'Homepage unavailable');
        await evaluate("document.documentElement.classList.remove('gnc-intro-on'); document.querySelector('.gnc-map-frame').scrollIntoView({block:'start',behavior:'instant'})");
        await until(() => evaluate("(() => { const f=document.querySelector('.gnc-map-frame'); return getComputedStyle(f).opacity === '1' && !!f.contentDocument.getElementById('map') && f.contentDocument.querySelectorAll('.chip').length === 9; })()"), 'Map did not appear on first scroll at ' + width);
        assert.equal(await evaluate("(()=>{const frame=document.querySelector('.gnc-map-frame'),m=frame.contentDocument.getElementById('map'),r=m.getBoundingClientRect(),v=m.viewBox.baseVal;return v.x<=0&&v.y<=0&&v.x+v.width>=1421.9&&v.y+v.height>=2303.9&&Math.abs(v.width/v.height-r.width/r.height)<.001;})()"),true,'First visit fits the full campus at '+width+'px without resetting');
        await evaluate("document.querySelector('.gnc-map-frame').contentWindow.go('banzali')");
        assert.equal(await evaluate("document.querySelector('.gnc-map-frame').contentWindow.current"), 'banzali');
        await evaluate("scrollTo({top:0,behavior:'instant'})");
        await delay(200);
        await evaluate("document.querySelector('.gnc-map-frame').scrollIntoView({block:'start',behavior:'instant'})");
        assert.equal(await evaluate("document.querySelector('.gnc-map-frame').contentWindow.current"), 'banzali', 'Scrolling back preserves the map and route');
        await evaluate("(() => {const frame=document.querySelector('.gnc-map-frame');frame.contentWindow.fitView(false);frame.style.width='75%';})()");
        await until(() => evaluate("(() => {const frame=document.querySelector('.gnc-map-frame'),map=frame.contentDocument.getElementById('map'),r=map.getBoundingClientRect(),v=map.viewBox.baseVal;return r.width>0&&r.height>0&&Math.abs(v.width/v.height-r.width/r.height)<.001;})()"), 'Camera must fit resized frame without refreshing at '+width);
        assert.equal(await evaluate("document.querySelector('.gnc-map-frame').contentWindow.current"), 'banzali', 'Resizing preserves the selected building');
        await evaluate("document.querySelector('.gnc-map-frame').style.width=''");
    }
    console.log('PASS: real building clicks and touch taps, drag suppression, stalled-font startup, interrupted-touch recovery, first-scroll visibility, resize and scroll-away/return on desktop and mobile.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-home-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
