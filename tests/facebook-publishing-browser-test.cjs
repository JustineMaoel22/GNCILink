const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const sessionCommand = (...args) => spawnSync('docker', ['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php',...args], {encoding:'utf8',windowsHide:true});
const created = sessionCommand('create');
if (created.status !== 0) throw Error(created.stderr || created.stdout);
const session = JSON.parse(created.stdout);
const base = process.env.BASE_URL || 'http://localhost:8080';
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-facebook-test-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) { for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(60);}throw Error(message); }
let socket;
(async () => {
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*facebook.com*','*youtube.com*']});
    await send('Network.setCookie',{name:session.name,value:session.value,url:base,httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/announcement.php'});
    await until(()=>evaluate("typeof openCreateModal === 'function'"),'Announcement editor unavailable');
    await evaluate('openCreateModal()');
    assert.equal(await evaluate("document.getElementById('publish-destination').value"),'website');
    assert.deepEqual(await evaluate("Array.from(document.getElementById('publish-destination').options, x=>x.value)"),['website','facebook','both']);
    // Replace only the browser's submission method: no saved content or external calls.
    await evaluate("window.submitted=0; HTMLFormElement.prototype.submit=function(){window.submitted++}; modalTitleInput.value='Browser test'; modalQuill.setText('Message'); modalStatus.value='published'; document.getElementById('announcement-form').requestSubmit()");
    await until(()=>evaluate("!!document.querySelector('.gnc-dialog')"),'Publishing confirmation missing');
    assert.equal(await evaluate('window.submitted'),0);
    assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/website/);
    await evaluate("document.querySelector('.gnc-dialog-cancel').click()");
    await until(()=>evaluate("!document.querySelector('.gnc-dialog')"),'Cancelled dialog did not close');
    assert.equal(await evaluate('window.submitted'),0,'Cancel prevents save');
    await evaluate("document.getElementById('publish-destination').options[1].disabled=false;document.getElementById('publish-destination').value='facebook';document.getElementById('announcement-form').requestSubmit()");
    await until(()=>evaluate("!!document.querySelector('.gnc-dialog')"),'Facebook confirmation missing');
    assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/hidden from the website/);
    await evaluate("document.querySelector('.gnc-dialog-accept').click()");
    await until(()=>evaluate('window.submitted===1'),'Confirmed form did not submit');
    assert.equal(await evaluate("document.getElementById('facebook-confirmed').value"),'1');
    await evaluate('openCreateModal()');
    assert.equal(await evaluate("document.getElementById('publish-destination').value"),'website','Every new form defaults to website');
    assert.equal(await evaluate("document.getElementById('facebook-confirmed').value"),'0');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await delay(350);
    assert(await evaluate("document.querySelector('#announcementModal .modal-content').getBoundingClientRect().width <= innerWidth"),'Mobile modal fits');
    const audit=path.join('cache','admin-design-audit');fs.mkdirSync(audit,{recursive:true});
    const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(audit,'facebook-announcement-mobile.png'),Buffer.from(shot.data,'base64'));
    await send('Page.navigate',{url:base+'/admin/facebook-integration.php'});
    await until(()=>evaluate("!!document.getElementById('facebook-connect-form')"),'Configuration page unavailable');
    assert(await evaluate("document.body.textContent.includes('Connect Facebook')"),'Facebook sign-in button visible');
    assert(await evaluate("!document.body.textContent.includes('FB_PUBLISH_PAGE_TOKEN') && !document.querySelector('input[name=confirmed_page_id],input[name=access_token]')"),'No token or manual Page ID setup in admin UI');
    const settingsShot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(audit,'facebook-integration-mobile.png'),Buffer.from(settingsShot.data,'base64'));
    const cookie = session.name+'='+session.value;
    for(const endpoint of ['/admin/facebook-integration.php','/admin/action/create-announcement.php','/admin/action/facebook-oauth.php']) {
        const anonymous=await fetch(base+endpoint,{redirect:'manual'});
        assert.equal(anonymous.status,302,'Anonymous access redirects to login');
    }
    const invalid=await fetch(base+'/admin/facebook-integration.php',{method:'POST',headers:{Cookie:cookie,'Content-Type':'application/x-www-form-urlencoded'},body:'action=connect&csrf_token=invalid'});
    assert.match(await invalid.text(),/Invalid request token/,'CSRF rejection');
    const env=await fetch(base+'/.env');assert.equal(env.status,403,'Backend .env denied by Apache');
    console.log('PASS: website default/reset, all destinations, confirmation/cancel, mobile editor, token-free Facebook sign-in UI, anonymous access including OAuth callback, CSRF rejection and .env protection. No announcements saved or Facebook posts sent.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    sessionCommand('destroy',session.value);if(socket)socket.close();browser.kill();await delay(700);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-facebook-test-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
