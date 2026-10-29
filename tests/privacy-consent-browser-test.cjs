const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-privacy-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'],
    {windowsHide:true, stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) {
    for (const end = Date.now() + 15000; Date.now() < end;) { if (await work()) return; await delay(50); }
    throw Error(message);
}
let socket;
(async () => {
    await until(() => fs.existsSync(path.join(profile, 'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, {once:true}));
    let serial = 0; const pending = new Map();
    socket.addEventListener('message', event => { const p = JSON.parse(event.data); if (pending.has(p.id)) { pending.get(p.id)(p); pending.delete(p.id); } });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++serial;
        const timer = setTimeout(() => { pending.delete(id); reject(Error('Timed out: ' + method)); }, 15000);
        pending.set(id, p => { clearTimeout(timer); p.error ? reject(Error(JSON.stringify(p.error))) : resolve(p.result); });
        socket.send(JSON.stringify({id, method, params}));
    });
    const evaluate = async expression => {
        const r = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
        if (r.exceptionDetails) throw Error(JSON.stringify(r.exceptionDetails));
        return r.result.value;
    };

    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    const requests=[];socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.method==='Network.requestWillBeSent')requests.push(p.params.request.url);});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*maps.google.com*','*docs.google.com*']});
    async function visit(){await send('Page.navigate',{url:base+'/pages/faq.php'});await until(()=>evaluate('document.readyState==="complete"&&!!window.GNCPrivacy&&!!document.getElementById("gncPrivacyBanner")'),'Privacy UI unavailable');}
    await visit();
    assert.equal(await evaluate('document.getElementById("gncPrivacyBanner").hidden'),false);
    assert.equal(requests.some(url=>url.includes('/api/track-visit.php')),false,'No tracking before opt-in');
    assert.equal(await evaluate('document.querySelector("iframe[data-privacy-src]").hasAttribute("src")'),false,'Map blocked before choice');
    assert.equal(await evaluate('localStorage.getItem("gnci_feedback_v1")'),null,'No reminder tracking before choice');
    await evaluate('document.querySelector("[data-privacy-action=reject]").click()');
    await visit();
    assert.equal(await evaluate('GNCPrivacy.allows("analytics")'),false,'Reject persists');
    const denied=await evaluate('(async()=>{const r=await fetch("/api/track-visit.php",{method:"POST"});return r.json();})()');
    assert.equal(denied.tracking,false,'Server tracking endpoint rejects absent opt-in');
    await send('Network.setCookie',{name:'gnc_vid',value:'abcdef1234567890abcdef1234567890',url:base,httpOnly:true});
    await evaluate('GNCPrivacy.setCategory("personalization",false)');
    await until(async()=>!(await send('Network.getCookies',{urls:[base]})).cookies.some(c=>c.name==='gnc_vid'),'HttpOnly visitor cookie not cleared');
    await evaluate('GNCPrivacy.setCategory("external",true)');
    await until(()=>evaluate('document.querySelector("iframe[data-privacy-src]").hasAttribute("src")'),'Embed not enabled');
    assert.equal(await evaluate('GNCPrivacy.allows("analytics")'),false,'Choices are independent');
    await evaluate('GNCPrivacy.setCategory("personalization",true);localStorage.setItem("gnc_recommendations_v1","{}");GNCPrivacy.setCategory("personalization",false)');
    assert.equal(await evaluate('localStorage.getItem("gnc_recommendations_v1")'),null,'Revocation clears profile');
    await evaluate('GNCPrivacy.setCategory("external",false)');
    assert.equal(await evaluate('document.querySelector("iframe[data-privacy-src]").hasAttribute("src")'),false,'Revocation unloads embed');
    for(const width of [1440,390]){await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});await evaluate('GNCPrivacy.manage()');assert.equal(await evaluate('document.getElementById("gncPrivacyDialog").getBoundingClientRect().right<=innerWidth'),true,'Settings fit viewport');await evaluate('document.querySelector("[data-privacy-action=close]").click()');}
    const anonymous=await fetch(base+'/pages/legal/privacy-policy.php');assert.equal(anonymous.status,404,'Unapproved policy not public');
    const noChoice=await evaluate('(async()=>{const r=await fetch("/chatbot/chatbot.php",{method:"POST",headers:{"Content-Type":"application/json","X-GNCI-Chat":"1"},body:JSON.stringify({message:"hello"})});return {status:r.status,body:await r.json()};})()');assert.equal(noChoice.status,422,'AI requires a specific choice');
    await send('Page.addScriptToEvaluateOnNewDocument',{source:'Object.defineProperty(navigator,"globalPrivacyControl",{value:true});'});
    await visit();assert.equal(await evaluate('GNCPrivacy.allows("external")'),false,'GPC keeps optional embeds off');
    console.log('PASS: first visit, reject/reload, independent categories, blocked tracking endpoint, HttpOnly cookie deletion, embed activation/revocation, local-profile clearing, responsive dialog, GPC, private policies and AI acknowledgment. No analytics opt-in or external-provider connections.');
})().catch(error => {console.error(error); process.exitCode=1;}).finally(async () => {
    if (socket) socket.close(); browser.kill(); await delay(500);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-privacy-')) {
        try {fs.rmSync(profile, {recursive:true, force:true, maxRetries:3, retryDelay:200});} catch {}
    }
});

