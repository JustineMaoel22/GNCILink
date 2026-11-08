const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
async function until(fn, message) {
    for (let end=Date.now()+15000; Date.now()<end;) { if (await fn()) return; await delay(50); }
    throw Error(message);
}
(async () => {
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open',resolve,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message', event => { const data=JSON.parse(event.data); if (pending.has(data.id)) { pending.get(data.id)(data); pending.delete(data.id); } });
    const send = (method,params={},sessionId) => new Promise((resolve,reject) => {
        const id=++serial; pending.set(id,data => data.error ? reject(Error(JSON.stringify(data.error))) : resolve(data.result));
        socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));
    });
    const evaluate = async expression => {
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };



    await send('Page.enable');await send('Network.enable');await send('ServiceWorker.enable');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const base='http://localhost:8080';
    const visit=async route=>{
        await send('Page.navigate',{url:base+route});
        await until(()=>evaluate("document.readyState==='complete'"),'Page unavailable: '+route);
    };
    await visit('/');
    assert.equal(await evaluate("document.querySelector('link[rel=manifest]')?.getAttribute('href')"),'/manifest.json');
    await until(()=>evaluate("!!navigator.serviceWorker.controller"),'Worker did not take control');
    await until(()=>evaluate("caches.keys().then(async names=>{for(const name of names.filter(n=>n.endsWith('-pages')))if(await(await caches.open(name)).match('/'))return true;return false;})"),'First public page not saved');
    const manifest=await send('Page.getAppManifest');
    assert.equal(manifest.errors.length,0,'Manifest has no parser errors');
    const install=await send('Page.getInstallabilityErrors');
    assert.deepEqual(install.installabilityErrors,[],'Chromium reports no installability errors');
    console.log('PASS: manifest and Chromium installability checks');
    const probe=await evaluate(`Promise.all(['/service-worker.js','/manifest.json','/vision-mission','/contact-us'].map(async url=>{const r=await fetch(url);return {url,status:r.status,type:r.headers.get('content-type'),public:r.headers.get('X-GNCI-PWA-Cache')};}))`);
    assert.ok(probe.every(r=>r.status===200));assert.match(probe[0].type,/javascript/);assert.match(probe[1].type,/manifest\+json/);
    assert.equal(probe[2].public,'public');assert.equal(probe[3].public,null,'Contact form is never opted into offline storage');
    assert.equal(await evaluate("fetch('/vision-mission').then(r=>r.headers.get('X-GNCI-PWA-Cache'))"),null,'A session cookie suppresses public cache opt-in');
    // Remove the anonymous contact session before visiting informational pages.
    await send('Network.clearBrowserCookies');
    await visit('/vision-mission');
    await until(()=>evaluate("caches.keys().then(async names=>{for(const name of names.filter(n=>n.endsWith('-pages')))if(await(await caches.open(name)).match('/vision-mission'))return true;return false;})"),'Informational page not cached');
    // Precached assets and visited public pages must work with HTTP cache disabled.
    const targets=await send('Target.getTargets');
    const worker=targets.targetInfos.find(t=>t.type==='service_worker'&&t.url===base+'/service-worker.js');
    assert.ok(worker,'Real service worker target is running');
    const workerSession=(await send('Target.attachToTarget',{targetId:worker.targetId,flatten:true})).sessionId;
    await send('Network.enable',{},workerSession);
    await send('Network.setCacheDisabled',{cacheDisabled:true});
    await send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0},workerSession);
    await send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
    await visit('/vision-mission');
    assert.equal(await evaluate("!!document.querySelector('meta[name=gnci-offline]')"),true,'Saved page rendered offline');
    assert.ok(await evaluate("document.body.innerText.includes('Vision')"));
    assert.ok(await evaluate("!!document.querySelector('.gnc-pwa-offline-strip')"));
    await visit('/core-values');
    assert.equal(await evaluate("!!document.querySelector('.gnc-offline-card')"),true,'Unvisited page receives branded fallback');
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.gnc-offline-card')).backgroundColor"),'rgb(249, 246, 241)','Fallback stylesheet loaded offline');
    fs.mkdirSync(path.join(__dirname,'../cache/pwa'),{recursive:true});
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});await delay(150);
        assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false);
        const shot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,'../cache/pwa/offline-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }
    await send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1},workerSession);
    await send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await visit('/vision-mission');
    assert.equal(await evaluate("!!document.querySelector('meta[name=gnci-offline]')"),false,'Network-first navigation restores fresh HTML');
    const placement=await evaluate(`(()=>{const button=document.getElementById('gncPwaInstall'),socials=document.querySelector('.gnc-footer-socials');return {contact:button.parentElement.matches('.gnc-footer-contact'),below:button.getBoundingClientRect().top>=socials.getBoundingClientRect().bottom,text:button.textContent};})()`);
    assert.equal(placement.contact,true);assert.equal(placement.below,true);assert.equal(placement.text,'Install web app');
    await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close());document.querySelector('[data-privacy-action=reject]')?.click();document.getElementById('gncPwaInstall').click()");
    assert.equal(await evaluate("document.getElementById('gncPwaInstallHelp').open"),true,'Manual installation help is available');
    await evaluate("document.getElementById('gncPwaInstallHelp').close()");
    const forbidden=['/login','/content-editor','/api/search.php','/vision-mission?ce_preview=1','/vision-mission?token=private'];
    // Fetches to APIs/auth/preview paths remain network-only, even with a controlling worker.
    await evaluate(`Promise.all(${JSON.stringify(forbidden)}.map(url=>fetch(url).catch(()=>{})))`);
    const keys=await evaluate("caches.keys().then(async names=>(await Promise.all(names.filter(n=>n.startsWith('gncilink-pwa-')).map(async n=>(await(await caches.open(n)).keys()).map(r=>r.url)))).flat())");
    assert.ok(keys.every(url=>!forbidden.some(path=>url===base+path)),'No auth/API/preview/token response in caches');
    await visit('/login');
    assert.equal(await evaluate("document.querySelector('link[rel=manifest]')?.getAttribute('href')"),'/admin-manifest.json','Login uses the separate admin app identity');
    // A public page in the editor iframe must not receive installation UI or register a worker.
    await visit('/vision-mission?ce_preview=1');
    assert.equal(await evaluate("!!document.querySelector('link[rel=manifest]')"),false,'Preview does not get PWA metadata');
    console.log('PASS: offline saved pages, branded fallback, recovery, install help, safe cache exclusions, and unchanged login/preview boundaries');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});
