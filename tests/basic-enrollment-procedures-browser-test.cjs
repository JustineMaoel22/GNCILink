const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=path.join('cache','admin-design-audit');fs.mkdirSync(auditDirectory,{recursive:true});
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-calendar-widget-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*facebook.com*','*youtube.com*']});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"try{sessionStorage.setItem('gncIntroSeen','1')}catch(_){}"});

    await send('Network.setCookie',{name:adminSession.name,value:adminSession.value,url:base,httpOnly:true});


    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/pages/admission/enrollment-procedures.php'});
    await until(()=>evaluate("Boolean(document.getElementById('basic-education-tab') && window.bootstrap?.Tab)"),'Enrollment tabs unavailable');
    assert.equal(await evaluate("document.querySelectorAll('#enrollmentTab [role=tab]').length"),4);
    for(const [tab,count] of [['freshmen',10],['transferees',9],['old-students',7],['basic-education',null]]) {
        await evaluate(`document.getElementById('${tab}-tab').click()`);
        await until(()=>evaluate(`document.getElementById('${tab}-pane').classList.contains('show')`),'Tab did not activate: '+tab);
        if (count !== null) assert.equal(await evaluate(`document.querySelectorAll('#${tab}-pane .enrollment-step-card').length`),count,'Existing steps preserved');
    }
    assert.equal(await evaluate("document.querySelector('#basic-education-pane a').getAttribute('href')"),'/pages/admission/basic-education-admission-req.php');
    assert.equal(await evaluate("document.querySelectorAll('[data-ve-section]').length"),0,'Public page has no editing markers');
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=enrollment-procedures'});
    const frame='document.getElementById("veFrame")?.contentDocument';
    await until(()=>evaluate(`Boolean(${frame}?.querySelector('[data-ve-section="basic_education_procedures"]')?.__ve)`),'Enrollment Content Editor unavailable');
    assert.equal(await evaluate('VE_CONFIG.page.slug'),'enrollment-procedures');
    await evaluate(`${frame}.getElementById('basic-education-tab').click()`);
    await until(()=>evaluate(`${frame}.getElementById('basic-education-pane').classList.contains('show')`),'Editor Basic Education tab did not activate');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-section]').length`),2);
    await evaluate(`(()=>{const el=${frame}.querySelector('[data-ve-section="basic_education_title"]');el.focus();el.textContent='Basic Education TEST';el.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await until(()=>evaluate('!document.getElementById("vePrimary").disabled'),'Inline edit did not enable Publish');
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-enrollment-add]').textContent.trim()`),'Add step');
    await send('Page.navigate',{url:base+'/admin/enrollment-steps.php'});
    await until(()=>evaluate("Boolean(document.getElementById('enrollment-add-step') && window.AdminDialogs)"),'Step manager unavailable');
    const initial=await evaluate('document.querySelectorAll(".enrollment-edit-step").length');
    await evaluate("document.getElementById('enrollment-add-step').click();document.getElementById('enrollment-add-step').click()");
    assert.equal(await evaluate('document.querySelectorAll(".enrollment-edit-step").length'),initial+2);
    await evaluate(`(()=>{const rows=Array.from(document.querySelectorAll('.enrollment-edit-step')).slice(-2);rows.forEach((row,i)=>{row.querySelector('[data-title]').value='TEST office '+i;row.querySelector('[data-instruction]').value='TEST approved instructions';row.querySelector('[data-title]').dispatchEvent(new Event('input',{bubbles:true}));});rows[0].querySelector('[data-illustration]').value='/assets/images/svg/enrollment-procedures/fresh-1.svg';rows[0].querySelector('[data-illustration]').dispatchEvent(new Event('change',{bubbles:true}));rows[1].querySelector('[data-up]').click();})()`);
    assert.equal(await evaluate("Array.from(document.querySelectorAll('[data-title]')).at(-2).value"),'TEST office 1');
    assert.equal(await evaluate("Array.from(document.querySelectorAll('[data-preview]')).at(-1).hidden"),false);
    await evaluate("Array.from(document.querySelectorAll('[data-remove]')).at(-2).click()");
    await until(()=>evaluate("Boolean(document.querySelector('dialog[open] .gnc-dialog-accept'))"),'Remove confirmation unavailable');
    await evaluate("document.querySelector('dialog[open] .gnc-dialog-accept').click()");
    await until(()=>evaluate(`document.querySelectorAll('.enrollment-edit-step').length===${initial+1}`),'Step removal failed');
    assert.equal(await evaluate("document.getElementById('enrollment-publish').disabled"),false);
    await evaluate("document.getElementById('enrollment-publish').click()");
    await until(()=>evaluate("Boolean(document.querySelector('dialog[open] .gnc-dialog-cancel'))"),'Publish confirmation unavailable');
    await evaluate("document.querySelector('dialog[open] .gnc-dialog-cancel').click()");
    await send('Page.captureScreenshot',{format:'png'}).then(result=>fs.writeFileSync(path.join(auditDirectory,'enrollment-step-manager-desktop.png'),Buffer.from(result.data,'base64')));
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await delay(600);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'),true,'Manager has no mobile overflow');
    await send('Page.captureScreenshot',{format:'png'}).then(result=>fs.writeFileSync(path.join(auditDirectory,'enrollment-step-manager-mobile.png'),Buffer.from(result.data,'base64')));
    const forbidden=await evaluate(`(async()=>{const body=new FormData();body.append('csrf_token','bad-token');const response=await fetch('/admin/enrollment-steps.php',{method:'POST',body});return response.status;})()`);
    assert.equal(forbidden,403,'Invalid CSRF refused');
    const publicHtml=await(await fetch(base+'/pages/admission/enrollment-procedures.php')).text();
    assert(publicHtml.includes('Illustrations by'));assert(publicHtml.includes('href="https://storyset.com/"'));assert(!publicHtml.includes('TEST office'));
    console.log('PASS: College tabs/26 steps preserved; Basic Education editor link, add/edit/reorder/remove, illustration selection, publish confirmation, mobile layout and CSRF rejection checked. No content published.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
