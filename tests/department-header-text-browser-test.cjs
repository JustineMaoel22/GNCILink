const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const php=(...args)=>spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php',...args],{encoding:'utf8',windowsHide:true});
const created=php('create');assert.equal(created.status,0,created.stderr);const session=JSON.parse(created.stdout);
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-header-text-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));let socket;
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await pause(80);}throw Error(message);}
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Browser unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,packet=>packet.error?reject(Error(JSON.stringify(packet.error))):resolve(packet.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    await send('Network.setCookie',{name:session.name,value:session.value,url:base,httpOnly:true});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`Object.defineProperty(window,'VE_CONFIG',{configurable:true,set(value){delete window.VE_CONFIG;window.VE_CONFIG=value;const url=new URL(value.page.url,location.origin);url.searchParams.set('draft_test','header-text-regression');value.page.url=url.pathname+url.search;}});`});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-departments&sub=cased'});
    const ready=()=>evaluate("Boolean(window.VE&&!VE.state.busy&&VE.state.doc?.querySelector('[data-ve-key=header_prefix]')?.__ve)");await until(ready,'Department header unavailable');
    if(!await evaluate("document.getElementById('veDiscardDraft').hidden")){
        await evaluate("window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");await pause(800);await until(ready,'Draft cleanup reload failed');
    }
    assert.equal(await evaluate("Boolean(document.getElementById('veSettingsBtn')||document.getElementById('veDrawer'))"),false,'College department form drawer is removed');
    await evaluate("(()=>{const el=VE.state.doc.querySelector('[data-ve-field=dept-name][data-ve-key=navigation-name]');el.click();el.textContent='Department navigation test';el.dispatchEvent(new Event('input',{bubbles:true}));})()");
    assert.equal(await evaluate("VE.adapter().draftState().dChange.dept_name"),'Department navigation test','Department identity remains editable inline');
    await evaluate("document.getElementById('vePageOpts').click()");
    await evaluate("(()=>{const input=document.querySelector('#vePop input[aria-label=\"Hide background image on mobile\"]');input.checked=true;input.dispatchEvent(new Event('change'));})()");
    assert.equal(await evaluate("VE.state.doc.querySelector('.dept-intro-program').classList.contains('dept-mobile-background-hidden')"),true,'Mobile background setting is enabled');
    await evaluate("document.querySelector('.ve-devices [data-device=mobile]').click()");
    await until(()=>evaluate("VE.state.win.innerWidth<768"),'Mobile preview unavailable');
    assert.equal(await evaluate("VE.state.win.getComputedStyle(VE.state.doc.querySelector('[data-dept-banner-image]')).display"),'none','Background is hidden on mobile');
    assert.equal(await evaluate("VE.state.win.getComputedStyle(VE.state.doc.querySelector('.dept-hero-title')).display==='none'"),false,'Header text remains visible');
    await evaluate("document.querySelector('.ve-devices [data-device=tablet]').click()");
    await until(()=>evaluate("VE.state.win.innerWidth>=768&&VE.state.win.innerWidth<=1024"),'Tablet preview unavailable');
    assert.notEqual(await evaluate("VE.state.win.getComputedStyle(VE.state.doc.querySelector('[data-dept-banner-image]')).display"),'none','Tablet retains the background');
    await evaluate("document.querySelector('.ve-devices [data-device=desktop]').click()");
    await until(()=>evaluate("VE.state.win.innerWidth>1024"),'Desktop preview unavailable');
    await evaluate("document.getElementById('vePageOpts').click()");
    await evaluate("(()=>{const color=document.querySelector('#vePop input[aria-label=\"Department colour\"]');color.value='#5c198b';color.dispatchEvent(new Event('input'));})()");
    assert.equal(await evaluate("VE.adapter().draftState().dChange.theme_color"),'#5C198B','Colour is staged through Page options');
    assert.equal(await evaluate("VE.state.doc.querySelector('.dept-intro-program').style.getPropertyValue('--gnc-template')"),'#5C198B','Colour preview updates the page');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').hidden"),true,'Extra text is optional initially');
    await evaluate("(()=>{const el=VE.state.doc.querySelector('[data-ve-key=header_prefix]');el.click();el.textContent='School of';el.dispatchEvent(new Event('input',{bubbles:true}));})()");
    await evaluate("(()=>{const el=VE.state.doc.querySelector('[data-ve-key=header_subject]');el.click();el.textContent='Header subject test';el.dispatchEvent(new Event('input',{bubbles:true}));})()");
    await evaluate("[...document.querySelectorAll('#veTb button')].find(b=>b.textContent==='Add header text box').click()");
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').hidden"),false);
    await evaluate("(()=>{const el=VE.state.doc.querySelector('[data-dept-header-extra]');el.textContent='Extra header test';el.dispatchEvent(new Event('input',{bubbles:true}));const font=document.querySelector('#veTb select[title^=\"Google Font\"]');font.value='poppins';font.dispatchEvent(new Event('change'));})()");
    assert.ok(await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').style.fontFamily.includes('Poppins')"),'Extra text supports its own font');
    await evaluate("[...document.querySelectorAll('#veTb button')].find(button=>button.textContent==='Snap to center').click()");
    const centered=()=>evaluate("(()=>{const el=VE.state.doc.querySelector('[data-dept-header-extra]'),r=el.getBoundingClientRect(),b=el.closest('section').getBoundingClientRect();return Math.abs(r.left+r.width/2-b.left-b.width/2)<1})()");
    assert.equal(await centered(),true,'Snap to center centers the header box in its section: '+JSON.stringify(await evaluate("(()=>{const el=VE.state.doc.querySelector('[data-dept-header-extra]'),r=el.getBoundingClientRect(),b=el.closest('section').getBoundingClientRect();return {x:r.left,w:r.width,bx:b.left,bw:b.width,style:el.getAttribute('style')}})()")));
    for(const part of ['prefix','subject','blurb']) {
        await evaluate(`VE.state.doc.querySelector('[data-dept-header-${part}]').click();[...document.querySelectorAll('#veTb button')].find(b=>b.textContent==='Remove this text box').click()`);
        assert.equal(await evaluate(`VE.state.doc.querySelector('[data-dept-header-${part}]').classList.contains('dept-header-hidden')`),true,'Only selected box is removed');
        assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-text]').classList.contains('dept-header-hidden')"),false,'Other header text stays visible');
        if(part !== 'blurb') {
            await evaluate(`document.getElementById('vePageOpts').click();[...document.querySelectorAll('#vePop button')].find(b=>b.textContent===${JSON.stringify('Restore header '+(part==='subject'?'title':'prefix'))}).click()`);
            assert.equal(await evaluate(`VE.state.doc.querySelector('[data-dept-header-${part}]').classList.contains('dept-header-hidden')`),false,'Removed box can be restored');
        }
    }
    await evaluate("document.getElementById('veSaveDraft').click()");await until(()=>evaluate("!VE.state.busy&&document.getElementById('veStatus').textContent.includes('Draft saved')"),'Draft save failed');
    await send('Page.reload');await pause(800);await until(ready,'Draft restore failed');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-ve-key=header_prefix]').textContent"),'School of');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-ve-key=header_subject]').textContent"),'Header subject test');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').textContent"),'Extra header test');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').hidden"),false,'Extra box restores visibly');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-blurb]').classList.contains('dept-header-hidden')"),true,'Individual removal survives draft reload');
    await evaluate("document.getElementById('vePageOpts').click();[...document.querySelectorAll('#vePop button')].find(b=>b.textContent==='Restore line under the title').click()");
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-blurb]').classList.contains('dept-header-hidden')"),false,'Subtitle can be restored after reload');
    assert.equal(await centered(),true,'Centered placement survives draft reload');
    assert.equal(await evaluate("VE.adapter().draftState().dChange.theme_color"),'#5C198B','Colour survives draft reload');
    assert.equal(await evaluate("VE.state.doc.querySelector('.dept-intro-program').classList.contains('dept-mobile-background-hidden')"),true,'Mobile background choice survives draft reload');
    assert.notEqual(await evaluate("VE.state.win.getComputedStyle(VE.state.doc.querySelector('[data-dept-banner-image]')).display"),'none','Desktop retains the background after draft restore');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-ve-key=navigation-name]').textContent"),'Department navigation test','Department name survives draft reload');
    await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').click()");
    fs.writeFileSync(path.join(os.tmpdir(),'gnci-header-text-editor.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    const live=await(await fetch(base+'/pages/academics/college/college-department.php?slug=cased')).text();assert.ok(!live.includes('Extra header test')&&!live.includes('Header subject test'),'Draft stays private');
    await evaluate("document.getElementById('vePageOpts').click();(()=>{const input=document.querySelector('#vePop input[aria-label=\"Hide background image on mobile\"]');input.checked=false;input.dispatchEvent(new Event('change'));})()");
    await evaluate("document.querySelector('.ve-devices [data-device=mobile]').click()");
    await until(()=>evaluate("VE.state.win.innerWidth<768"),'Mobile preview unavailable');
    assert.notEqual(await evaluate("VE.state.win.getComputedStyle(VE.state.doc.querySelector('[data-dept-banner-image]')).display"),'none','Background can be restored on mobile');
    await evaluate("document.querySelector('.ve-devices [data-device=desktop]').click()");
    await until(()=>evaluate("VE.state.win.innerWidth>1024"),'Desktop preview unavailable');
    await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').click();[...document.querySelectorAll('#veTb button')].find(b=>b.textContent==='Remove text box').click()");
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-extra]').hidden"),true,'Remove only hides the extra box');
    assert.equal(await evaluate("VE.state.doc.querySelector('[data-dept-header-text]').classList.contains('dept-header-hidden')"),false,'Main heading remains visible');
    await evaluate("window.AdminDialogs.confirm=async()=>true;document.getElementById('veDiscardDraft').click()");await pause(800);await until(ready,'Discard failed');
    assert.ok(!await evaluate("VE.state.doc.querySelector('[data-ve-key=header_subject]').textContent.includes('Header subject test')"),'Discard restores published title');
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=basic-education'});
    await until(()=>evaluate("location.search.includes('page=basic-education')&&document.readyState==='complete'&&Boolean(document.getElementById('veSettingsBtn'))"),'Basic Education retains its existing More settings panel');
    console.log('PASS: mobile-only background toggle and restore, tablet/desktop preservation, draft persistence; inline name/colour/header controls and Basic Education form preserved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    php('destroy',session.value);if(socket)socket.close();browser.kill();await pause(700);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-header-text-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
