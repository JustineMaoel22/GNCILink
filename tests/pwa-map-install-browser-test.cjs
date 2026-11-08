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
    // Isolate this promotion from independent timed suggestions/feedback during the test.
    await send('Network.setBlockedURLs',{urls:['*assets/js/recommendations.js*','*assets/js/visitor-feedback.js*']});
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const base='http://localhost:8080';
    const visit=async()=>{await send('Page.navigate',{url:base+'/'});await until(()=>evaluate("document.readyState==='complete'&&!!document.getElementById('campus-map')&&!!document.getElementById('gncPwaInstall')"),'Homepage unavailable');};
    const mockInstall=()=>evaluate("(()=>{const e=new Event('beforeinstallprompt',{cancelable:true});window.installPrompts=0;e.prompt=async()=>{window.installPrompts++};e.userChoice=Promise.resolve({outcome:'dismissed'});window.dispatchEvent(e)})()");
    const scrollMap=()=>evaluate("document.getElementById('campus-map').scrollIntoView({block:'center',behavior:'instant'})");
    const shown=()=>evaluate("!!document.querySelector('.gnc-pwa-install-notice')");
    await visit();
    await evaluate("document.querySelector('#gncSiteDisclaimer button[type=submit]')?.click();document.querySelector('[data-privacy-action=reject]')?.click();localStorage.removeItem('gncilink-install-dismissed');sessionStorage.removeItem('gncilink-install-shown')");
    await mockInstall();await delay(2300);
    assert.equal(await shown(),false,'Never promote at top of homepage');
    await evaluate("(()=>{const d=document.createElement('dialog');d.id='testModal';d.innerHTML='<button>Close</button>';document.body.append(d);d.showModal()})()");
    await scrollMap();await delay(2500);
    assert.equal(await shown(),false,'Open dialog takes priority');
    await evaluate("document.getElementById('testModal').close();document.getElementById('testModal').remove()");
    await scrollMap();
    await until(shown,'Prompt did not appear near campus map');
    assert.equal(await evaluate("!!document.querySelector('.gnc-pwa-install-notice dialog')"),false,'Prompt is nonmodal');
    fs.mkdirSync(path.join(__dirname,'../cache/pwa'),{recursive:true});
    for(const width of [1440,390]){
        await evaluate("sessionStorage.removeItem('gncilink-install-shown')");
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width===390});
        await visit();await mockInstall();await scrollMap();await until(shown,'Prompt unavailable at viewport '+width);
        assert.equal(await evaluate("(()=>{const r=document.querySelector('.gnc-pwa-install-notice').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight})()"),true,'Prompt fits viewport');
        assert.equal(await evaluate("(()=>{const r=document.querySelector('.gnc-pwa-install-notice').getBoundingClientRect(),b=document.getElementById('gncBackTop').getBoundingClientRect();return r.bottom<=b.top||r.right<=b.left||r.left>=b.right})()"),true,'Back-to-top control remains unobstructed');
        assert.equal(await evaluate("(()=>{const r=document.querySelector('.gnc-pwa-install-chat').getBoundingClientRect(),b=document.querySelector('.gncbot-launcher').getBoundingClientRect();return r.bottom<b.top&&Math.abs(r.right-b.right)<2})()"),true,'Speech bubble sits above Cian without covering the launcher');
        assert.equal(await evaluate("document.querySelector('.gnc-pwa-tip-from').textContent"),'A tip from Cian');
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(__dirname,'../cache/pwa/map-install-'+width+'.png'),Buffer.from(shot.data,'base64'));
        await evaluate("window.scrollTo({top:0,behavior:'instant'})");await delay(250);
        assert.equal(await evaluate("document.getElementById('campus-map').getBoundingClientRect().top>innerHeight"),true,'Map has left view');
        assert.equal(await shown(),true,'Scrolling away must not dismiss the install bubble');
        await scrollMap();
    }
    await mockInstall();
    await evaluate("document.querySelector('.gnc-pwa-install-notice .gnc-pwa-actions button').click()");
    assert.equal(await evaluate('installPrompts'),1,'Install click invokes native prompt');
    await until(()=>evaluate("sessionStorage.getItem('gncilink-install-shown')==='1'"),'Native dismissal should remain suppressed for this session');
    assert.equal(await evaluate("localStorage.getItem('gncilink-install-dismissed')"),null,'No persistent dismissal cooldown');
    await visit();await mockInstall();await scrollMap();await delay(2300);assert.equal(await shown(),false,'Does not repeat after reload');
    // Simulate a fresh tab session and a leftover seven-day dismissal from the old code.
    await evaluate("sessionStorage.removeItem('gncilink-install-shown');localStorage.setItem('gncilink-install-dismissed',String(Date.now()))");
    await visit();await mockInstall();await scrollMap();await until(shown,'A new session must show the reminder despite an old cooldown');
    await evaluate("document.querySelector('.gnc-pwa-install-notice .gnc-pwa-dismiss').click()");
    assert.equal(await shown(),false);
    assert.equal(await evaluate("sessionStorage.getItem('gncilink-install-shown')"),'1');
    await send('Page.navigate',{url:base+'/vision-mission'});
    await until(()=>evaluate("location.pathname==='/vision-mission'&&document.readyState==='complete'"),'Cross-page navigation failed');
    await visit();await mockInstall();await scrollMap();await delay(2300);assert.equal(await shown(),false,'Dismissal survives navigation between pages');
    // A browser can omit beforeinstallprompt; the card must still offer installation help.
    await evaluate("sessionStorage.removeItem('gncilink-install-shown');localStorage.removeItem('gncilink-install-dismissed')");
    const noNative=await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();event.stopImmediatePropagation();},true)"});
    await visit();
    await evaluate("document.querySelector('#campus-map iframe').focus({preventScroll:true})");
    await scrollMap();await until(shown,'Reminder must appear without native install event and with map focused');
    assert.equal(await evaluate("document.activeElement===document.querySelector('#campus-map iframe')"),true,'Reminder preserves map focus');
    await evaluate("document.querySelector('.gnc-pwa-install-notice .gnc-pwa-actions button').click()");
    await until(()=>evaluate("!!document.querySelector('#gncPwaInstallHelp[open]')"),'Fallback installation instructions unavailable');
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:noNative.identifier});
    await evaluate("document.getElementById('gncPwaInstallHelp').close();sessionStorage.removeItem('gncilink-install-shown')");
    await visit();await scrollMap();await until(shown,'Chatbot bubble unavailable');
    await evaluate("document.querySelector('.gncbot-launcher').click()");
    await until(()=>evaluate("document.getElementById('gncbot').classList.contains('is-open')"),'Chatbot should still open');
    assert.equal(await shown(),true,'Opening chat must not automatically dismiss the install bubble');
    // Installation persists independently of session dismissal and hides later browser visits.
    await evaluate("document.querySelector('.gncbot-close').click();window.dispatchEvent(new Event('appinstalled'));sessionStorage.removeItem('gncilink-install-shown')");
    assert.equal(await evaluate("localStorage.getItem('gncilink-visitor-installed')"),'1');
    const installedStub=await send('Page.addScriptToEvaluateOnNewDocument',{source:"window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();event.stopImmediatePropagation();},true)"});
    await send('Page.navigate',{url:base+'/'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.getElementById('campus-map')"),'Installed browser visit unavailable');
    await scrollMap();await delay(2300);
    assert.equal(await shown(),false,'Observed installation hides reminder in a new session');
    assert.equal(await evaluate("!!document.getElementById('gncPwaInstall')"),false,'Observed installation also hides footer installation control');
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:installedStub.identifier});
    // Emulate a standalone launch independently of the recorded installation.
    await evaluate("localStorage.removeItem('gncilink-visitor-installed')");
    const standaloneStub=await send('Page.addScriptToEvaluateOnNewDocument',{source:"Object.defineProperty(navigator,'standalone',{value:true,configurable:true})"});
    await send('Page.navigate',{url:base+'/'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.getElementById('campus-map')"),'Standalone visit unavailable');
    await scrollMap();await delay(2300);assert.equal(await shown(),false,'Standalone app never shows install reminder');
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:standaloneStub.identifier});
    console.log('PASS: responsive Cian bubble, native/fallback installation, session-only dismissal across navigation, new-session reminder, retired cooldown, observed installation and standalone suppression. Native OS installation is not simulated.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});
