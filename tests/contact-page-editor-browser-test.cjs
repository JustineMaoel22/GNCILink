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
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=contact-us'});
    const frame='document.getElementById("veFrame")?.contentDocument';
    await until(()=>evaluate(`Boolean(${frame}?.querySelector('[data-ve-section="contact_email"]')?.__ve) && document.getElementById('veLoader').hidden`),'Contact Us editor unavailable');
    await evaluate(`${frame}.fonts.ready.then(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))))`);
    assert.equal(await evaluate('VE_CONFIG.page.slug'),'contact-us');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-plain-section]').length`),74);
    assert.equal(await evaluate(`${frame}.querySelectorAll('.gnc-dir-item').length`),23);
    assert.equal(await evaluate(`getComputedStyle(${frame}.querySelector('.gnc-contact-title-main')).color`),'rgb(255, 255, 255)','White heading preserved');
    for(const [key,value] of [['hero_title','Contact TEST heading'],['contact_address','TEST address'],['contact_phone','(045) 987-6543'],['contact_email','test@example.com'],['name_label','TEST full name'],['direct_0_office','TEST office'],['local_19_number','555'],['contact_facebook','https://www.facebook.com/example']]) {
        const point=await evaluate(`(()=>{const el=${frame}.querySelector('[data-ve-section="${key}"]');el.scrollIntoView({block:'center',behavior:'instant'});const r=el.getBoundingClientRect(),f=document.getElementById('veFrame').getBoundingClientRect();return {x:f.left+r.left+r.width/2,y:f.top+r.top+r.height/2};})()`);
        await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...point});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...point});
        await until(()=>evaluate(`${frame}.activeElement===${frame}.querySelector('[data-ve-section="${key}"]')`),key+' focuses inline');
        await evaluate(`${frame}.getSelection().selectAllChildren(${frame}.querySelector('[data-ve-section="${key}"]'))`);
        await send('Input.insertText',{text:value});
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-section="${key}"]').textContent`),value);
        assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false);
        assert.equal(await evaluate('document.getElementById("vePop").hidden'),true);
        const links={contact_phone:['phone','tel:0459876543'],contact_email:['email','mailto:test@example.com'],contact_facebook:['facebook','https://www.facebook.com/example']};
        if(links[key]) assert.equal(await evaluate(`${frame}.querySelector('[data-contact-channel="${links[key][0]}"]').getAttribute('href')`),links[key][1],'Contact link follows inline text');
    }
    const html=await(await fetch(base+'/pages/contact-us.php')).text();
    assert(!html.includes('Contact TEST heading'));assert(!html.includes('TEST office'));assert(!html.includes('test@example.com'));assert(!html.includes('data-ve-section'));assert(html.includes('name="gnc_hp_7f3a"'));assert(html.includes('name="csrf"'));
    console.log('PASS: all 74 Contact Us fields registered; headings, details, form labels, Facebook URL and directory entries edit inline; Publish is enabled; layout, form protection and live content remain intact. No content was published or messages sent.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
