const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const base = 'http://localhost:8080';
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-editor-removal-'));
const browser = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir='+profile, 'about:blank'], {windowsHide:true, stdio:'ignore'});
const delay = ms => new Promise(r => setTimeout(r, ms));
const sessions = [];
let socket, serial = 0;
const pending = new Map(), errors = [];
function fixture(args) {
    const r = spawnSync('docker', ['compose','exec','-T','--user','www-data','web','php','tests/editor-presence-session.php',...args], {encoding:'utf8',windowsHide:true});
    if (r.status !== 0) throw Error(r.stderr || r.stdout);
    return r.stdout;
}
async function until(fn, message) {
    for (let end = Date.now()+25000; Date.now()<end;) { if (await fn()) return; await delay(100); }
    throw Error(message);
}
function send(method, params={}) {
    return new Promise((resolve,reject) => {
        const id = ++serial;
        pending.set(id, d => d.error ? reject(Error(JSON.stringify(d.error))) : resolve(d.result));
        socket.send(JSON.stringify({id,method,params}));
    });
}
async function evaluate(expression) {
    const r = await send('Runtime.evaluate', {expression,returnByValue:true,awaitPromise:true});
    if (r.exceptionDetails) throw Error(JSON.stringify(r.exceptionDetails));
    return r.result.value;
}
async function visit(page) {
    await evaluate('window.__previousEditor=true');
    await send('Page.navigate',{url:base+'/admin/content-edit.php'+page});
    await until(() => evaluate('!window.__previousEditor && !!window.VE && !!VE.state.doc && document.getElementById("veLoader").hidden'), 'Editor did not load '+page);
    assert.equal(await evaluate(`!!document.querySelector('#ceTable, #page-panel-hub, a[href*="page=hub"]')`),false,'Retired list absent');
}
async function request(auth, url, redirect='follow') {
    return fetch(new URL(url,base), {headers:{Cookie:auth.name+'='+auth.value},redirect});
}
(async () => {
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch('http://127.0.0.1:'+port+'/json/list')).json();
    socket = new WebSocket(tabs.find(t => t.type==='page').webSocketDebuggerUrl);
    await new Promise(r => socket.addEventListener('open',r,{once:true}));
    socket.addEventListener('message',e => {
        const d = JSON.parse(e.data);
        if (pending.has(d.id)) { pending.get(d.id)(d); pending.delete(d.id); }
        if (d.method==='Runtime.exceptionThrown') errors.push(d.params.exceptionDetails.text+' '+(d.params.exceptionDetails.exception?.description||''));
    });
    await send('Page.enable'); await send('Runtime.enable');
    let pageCount=0;
    for (const role of ['Super Admin','Content Editor']) {
        const auth = JSON.parse(fixture([role])); sessions.push(auth);
        await send('Network.clearBrowserCookies');
        await send('Network.setCookie',{name:auth.name,value:auth.value,url:base,httpOnly:true});
        const old = await request(auth,'/admin/content-editor?page=hub','manual');
        assert.equal(old.status,302); assert.equal(old.headers.get('location'),'?page=home');
        await visit('?page=hub');
        assert.equal(await evaluate('VE_CONFIG.page.slug'),'home');
        await visit('?page=does-not-exist');
        assert.equal(await evaluate('VE_CONFIG.page.slug'),'home');
        assert.equal(await evaluate('VE_CONFIG.requiresApproval'),role!=='Super Admin');
        assert.equal(await evaluate('!!document.getElementById("veSaveDraft")'),true);
        assert.equal(await evaluate('!!document.getElementById("vePresenceButton")'),true);
        const links = await evaluate('[...document.querySelectorAll("#vePageMenu a")].map(a=>a.getAttribute("href"))');
        assert.equal(links.some(l=>l.includes('page=review')),role==='Super Admin');
        for (const href of links) {
            const response = await request(auth,'/admin/content-edit.php'+href);
            const html = await response.text();
            assert.equal(response.status,200,role+' '+href);
            assert.ok(!/Fatal error|Uncaught (?:Error|Exception)|Warning:|Undefined variable/.test(html),role+' '+href+' PHP errors');
            if (!href.includes('page=review')) {
                assert.ok(html.includes('window.VE_CONFIG'),role+' '+href+' Visual Editor preserved');
                assert.ok(!html.includes('id="page-panel-hub"')&&!html.includes('href="?page=hub"'),href+' list removed');
            }
            pageCount++;
        }
        for (const slug of ['history','college-departments','programs','updates']) await visit('?page='+slug);
        for (const module of ['announcement','news','event']) {
            await visit('?page=updates&open='+module);
            assert.ok(await evaluate('VE_CONFIG.embed.open.includes('+JSON.stringify(module==='event'?'events.php':module+'.php')+')'));
        }
        const logs = await request(auth,role==='Super Admin'?'/admin/logs.php':'/admin/submissions.php');
        assert.equal(logs.status,200,'Activity logs / own submissions still load');
        await visit('?page=home');
        for (const width of [1440,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
            await evaluate('document.getElementById("vePageMenu").open=true');
            await delay(150);
            assert.equal(await evaluate('(()=>{const r=document.querySelector(".ve-menu-list").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth+1})()'),true,'Page menu fits '+width);
            fs.writeFileSync(path.join(__dirname,'content-editor-removal-'+role.replaceAll(' ','-')+'-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
            await evaluate('document.getElementById("vePageMenu").open=false');
        }
        console.log('PASS: '+role+' redirects, permissions, page picker, embedded editors, and desktop/mobile navigation.');
    }
    const anon = await fetch(base+'/admin/content-edit.php?page=home',{redirect:'manual'});
    assert.ok([302,303,401,403].includes(anon.status),'Anonymous access remains blocked');
    assert.deepEqual(errors,[],'No browser runtime exceptions');
    console.log('PASS: '+pageCount+' permitted page links; no browser exceptions. No content saves or publication performed.');
})().catch(e=>{console.error(e);process.exitCode=1}).finally(()=>{
    if(socket)socket.close();browser.kill();
    for(const auth of sessions)fixture(['destroy',auth.value]);
});
