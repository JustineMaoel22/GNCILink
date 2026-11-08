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
    const send = (method,params={}) => new Promise((resolve,reject) => {
        const id=++serial; pending.set(id,data => data.error ? reject(Error(JSON.stringify(data.error))) : resolve(data.result));
        socket.send(JSON.stringify({id,method,params}));
    });
    const evaluate = async expression => {
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };



    await send('Page.enable');await send('Network.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    const base='http://localhost:8080';
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page unavailable');};
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    // A waiting-worker stub tests notification behavior without changing deployment files.
    const stub=await send('Page.addScriptToEvaluateOnNewDocument',{source:`window.pwaMessages=[];window.pwaDocumentToken=Math.random();navigator.serviceWorker.register=async()=>({waiting:{postMessage:value=>pwaMessages.push(value)},installing:null,addEventListener(){},update(){return Promise.resolve();}});`});
    await visit('/vision-mission');
    await delay(250);
    const token=await evaluate('pwaDocumentToken');
    assert.equal(await evaluate("!!document.getElementById('gncPwaNotice')"),false,'Waiting updates do not create a popup');
    await evaluate("document.dispatchEvent(new Event('visibilitychange'));window.dispatchEvent(new Event('online'));navigator.serviceWorker.dispatchEvent(new Event('controllerchange'))");
    assert.equal(await evaluate('pwaDocumentToken'),token,'Background update events never reload the current page');
    assert.equal(await evaluate('pwaMessages.length'),0,'Waiting updates are not forced to activate');
    fs.mkdirSync(path.join(__dirname,'../cache/pwa'),{recursive:true});
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:stub.identifier});
    await send('Network.setUserAgentOverride',{userAgent:'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1'});
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await visit('/vision-mission');
    await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close());document.querySelector('[data-privacy-action=reject]')?.click();document.getElementById('gncPwaInstall').scrollIntoView({block:'center',behavior:'instant'})");
    await delay(150);
    const footerShot=await send('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(path.join(__dirname,'../cache/pwa/footer-install-390.png'),Buffer.from(footerShot.data,'base64'));
    await evaluate("document.getElementById('gncPwaInstall').click()");
    const steps=await evaluate("document.getElementById('gncPwaInstallHelp').textContent");
    assert.match(steps,/Safari/);assert.match(steps,/Share/);assert.match(steps,/Add to Home Screen/);
    assert.equal(await evaluate('document.documentElement.scrollWidth>innerWidth'),false);
    const iosShot=await send('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(path.join(__dirname,'../cache/pwa/ios-install-help.png'),Buffer.from(iosShot.data,'base64'));
    const standalone=await send('Page.addScriptToEvaluateOnNewDocument',{source:"Object.defineProperty(navigator,'standalone',{value:true,configurable:true});"});
    await visit('/vision-mission');
    assert.equal(await evaluate("!!document.getElementById('gncPwaInstall')"),false,'Installed-mode UI hides installation control');
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:standalone.identifier});
    const icons=await evaluate(`Promise.all(['/assets/images/pwa/icon-192.png','/assets/images/pwa/icon-512.png','/assets/images/pwa/apple-touch-icon.png','/assets/images/pwa/maskable-512.png'].map(url=>new Promise(resolve=>{const image=new Image();image.onload=()=>resolve([image.naturalWidth,image.naturalHeight]);image.onerror=()=>resolve(null);image.src=url;})))`);
    assert.deepEqual(icons,[[192,192],[512,512],[180,180],[512,512]],'Every app icon loads at its declared dimensions');
    const adminStub=await send('Page.addScriptToEvaluateOnNewDocument',{source:`window.pwaMessages=[];window.confirm=()=>false;navigator.serviceWorker.register=async()=>({waiting:{postMessage:value=>pwaMessages.push(value)},installing:null,addEventListener(){},update(){return Promise.resolve();}});`});
    await visit('/login');
    await delay(250);
    await evaluate("document.getElementById('email').dispatchEvent(new Event('input',{bubbles:true}));document.dispatchEvent(new Event('visibilitychange'))");
    assert.equal(await evaluate("!!document.getElementById('gncPwaNotice')"),false,'Admin waiting updates never show a popup while editing');
    assert.equal(await evaluate('pwaMessages.length'),0,'Administrative work is not interrupted by forced activation');
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:adminStub.identifier});
    console.log('PASS: quiet updates without popups or forced refresh; iOS instructions; emulated installed-mode UI; valid icon dimensions. Physical device installation is not simulated by these UI checks.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});

