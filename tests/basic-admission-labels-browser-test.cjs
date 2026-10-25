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

    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=basic-education-admission-requirements'});
    const frame='document.getElementById("veFrame")?.contentDocument';
    await until(()=>evaluate(`Boolean(${frame}?.querySelector('[data-ve-section="school_label_casa"]')?.__ve)`),'School label editor unavailable');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-admission-label]').length`),6);
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-section="school_label_casa"]').textContent`),'CASA & Grade School Montessori','Built-in ampersands display normally');
    for(const [key,value] of [['school_label_montessori','Montessori TEST & label'],['hero_title','Basic Education TEST heading'],['hero_intro','TEST admission description']]) {
        const point=await evaluate(`(()=>{const el=${frame}.querySelector('[data-ve-section="${key}"]');el.scrollIntoView({block:'center'});const r=el.getBoundingClientRect(),f=document.getElementById('veFrame').getBoundingClientRect();return {x:f.left+r.left+r.width/2,y:f.top+r.top+r.height/2};})()`);
        await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...point});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...point});
        assert.equal(await evaluate(`${frame}.activeElement===${frame}.querySelector('[data-ve-section="${key}"]')`),true,'Click focuses the inline text');
        await evaluate(`${frame}.getSelection().selectAllChildren(${frame}.querySelector('[data-ve-section="${key}"]'))`);
        await send('Input.insertText',{text:value});
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-section="${key}"]').textContent`),value);
        assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false);
        assert.equal(await evaluate('document.getElementById("vePop").hidden'),true,'Inline typing does not open a form');
        if(key.startsWith('school_label_')) {
            await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowLeft',code:'ArrowLeft',windowsVirtualKeyCode:37});
            await send('Input.dispatchKeyEvent',{type:'keyUp',key:'ArrowLeft',code:'ArrowLeft',windowsVirtualKeyCode:37});
            assert.equal(await evaluate(`${frame}.activeElement===${frame}.querySelector('[data-ve-section="${key}"]')`),true,'Arrow keys keep editing the word');
        }
    }
    await evaluate(`${frame}.querySelector('[data-basic-school="jhs-regular"]').click()`);
    assert.equal(await evaluate(`${frame}.querySelector('[data-basic-school-panel="jhs-regular"]').hidden`),false,'Tab switching still works');
    const html=await(await fetch(base+'/pages/admission/basic-education-admission-req.php')).text();
    assert(!html.includes('Montessori TEST &amp; label'));assert(!html.includes('Basic Education TEST heading'));assert(!html.includes('TEST admission description'));
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:true});});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-admission-requirements'});
    await until(()=>evaluate(`Boolean(${frame}?.querySelector('h1[data-ve-section="hero_title"]')?.__ve)`),'College heading editor unavailable');
    await evaluate(`(()=>{const el=${frame}.querySelector('h1[data-ve-section="hero_title"]');el.focus();${frame}.getSelection().selectAllChildren(el);})()`);
    await send('Input.insertText',{text:'College TEST & heading'});
    assert.equal(await evaluate(`${frame}.querySelector('h1[data-ve-section="hero_title"]').textContent`),'College TEST & heading');
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'College heading enables Publish');
    const collegeHtml=await(await fetch(base+'/pages/admission/admission-req.php')).text();
    assert(!collegeHtml.includes('College TEST &amp; heading'),'Unpublished College heading stays private');
    console.log('PASS: Basic Education fields and College heading edit inline; Publish enables; tabs work; unpublished edits stay private. No test content published.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
