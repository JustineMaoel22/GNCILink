const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-org-contact-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session,send;
const {execFileSync}=require('node:child_process');
let orgFixture, restoreEyebrow;
const fixture=(...args)=>execFileSync('docker',['compose','exec','-T','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser command timed out: '+method));},15000);pending.set(id,result=>{clearTimeout(timer);result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')console.error('Browser exception:',JSON.stringify(p.params.exceptionDetails));});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});


    orgFixture=JSON.parse(fixture('seed'));
    const auth=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true}));
    session={name:auth.name,id:auth.value};
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    const frame='(document.getElementById("veFrame")?.contentDocument)';
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=student-organizations&sub='+orgFixture.records[0].slug});
    await until(()=>evaluate(`Boolean(${frame}?.getElementById('veOrgData'))&&document.getElementById('veLoader').hidden`),'Organization detail editor unavailable');
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-key="facebook_eyebrow"]').isContentEditable`),true);
    await evaluate(`${frame}.querySelector('[data-ve-key="facebook_eyebrow"]').textContent='Contact eyebrow fixture';${frame}.querySelector('[data-ve-key="facebook_eyebrow"]').dispatchEvent(new Event('input',{bubbles:true}))`);
    await evaluate(`${frame}.querySelector('[data-ve-org-facebook]').click()`);
    await until(()=>evaluate('Boolean(document.querySelector("#vePop input"))'),'URL dialog unavailable');
    // Use the editor's real URL dialog and confirm its staged state.
    await evaluate(`(()=>{const input=document.querySelector('#vePop input');input.value='https://www.facebook.com/GNCIContactFixture';input.dispatchEvent(new Event('input',{bubbles:true}));Array.from(document.querySelectorAll('#vePop button')).find(b=>b.textContent==='Apply').click();})()`);
    await until(()=>evaluate(`${frame}.querySelector('.org-facebook iframe')?.getAttribute('src').includes(encodeURIComponent('https://www.facebook.com/GNCIContactFixture'))`),'Facebook preview not updated');
    assert.equal(await evaluate(`Boolean(${frame}.querySelector('.org-facebook-link'))`),false,'No extra link beside the Facebook box');
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-org-facebook]').getAttribute('aria-label')`),'Edit Facebook page URL');

    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await evaluate(`${frame}.querySelector('.org-facebook').scrollIntoView({block:'center'})`);
        await delay(300);
        const layout=await evaluate(`(()=>{const section=${frame}.querySelector('.org-facebook').getBoundingClientRect();const box=${frame}.querySelector('.org-fb-box').getBoundingClientRect();const overlay=${frame}.querySelector('.org-fb-edit').getBoundingClientRect();return {center:Math.abs((box.left+box.width/2)-(section.left+section.width/2)),width:box.width,overlay:Math.abs(overlay.width-box.width),overflow:${frame}.documentElement.scrollWidth>${frame}.defaultView.innerWidth};})()`);
        assert(layout.center<2&&layout.width<=500&&layout.overlay<2&&!layout.overflow,'Centered compact embed with full editing overlay');
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-org-contact-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }

    const live=await(await fetch(base+orgFixture.detail)).text();
    assert(!live.includes('GNCIContactFixture')&&!live.includes('Contact eyebrow fixture'),'Changes stay private before Publish');
    const state=await evaluate('VE.adapter().draftState()');
    assert.equal(state.settings.facebook_eyebrow.toLowerCase(),'contact eyebrow fixture');
    assert.equal(Object.values(state.records)[0].changes.facebook_url,'https://www.facebook.com/GNCIContactFixture');
    console.log('PASS: editable contact eyebrow, URL dialog, staged iframe/link preview, and private pending changes.');

    const api=async(fields)=>evaluate(`(async()=>{const fd=new FormData();fd.append('csrf_token',VE_CONFIG.csrf);Object.entries(${JSON.stringify(fields)}).forEach(([key,value])=>fd.append(key,value));const response=await fetch('/admin/action/student-organizations-handler.php',{method:'POST',body:fd});return {status:response.status,data:await response.json()};})()`);
    restoreEyebrow=await evaluate(`JSON.parse(${frame}.getElementById('veOrgData').textContent).fields.facebook_eyebrow.value`);
    assert.equal((await api({action:'save_page',texts:JSON.stringify({facebook_eyebrow:'Published contact eyebrow fixture'})})).status,200);
    assert.equal((await api({action:'save',org_id:orgFixture.records[0].org_id,facebook_url:'https://www.facebook.com/GNCIContactFixture'})).status,200);
    const published=await(await fetch(base+orgFixture.detail)).text();
    assert(published.includes('Published contact eyebrow fixture')&&published.includes(encodeURIComponent('https://www.facebook.com/GNCIContactFixture')),'Published eyebrow and destination render publicly');
    assert.equal((await api({action:'save',org_id:orgFixture.records[0].org_id,facebook_url:'https://evil.example/'})).status,422);
    await api({action:'save_page',texts:JSON.stringify({facebook_eyebrow:restoreEyebrow})});restoreEyebrow=undefined;
    // Clearing the address must remove the embed/link from the staged preview.
    await evaluate(`${frame}.querySelector('[data-ve-org-facebook]').click()`);
    await evaluate(`document.querySelector('#vePop input').value='';Array.from(document.querySelectorAll('#vePop button')).find(b=>b.textContent==='Apply').click()`);
    assert.equal(await evaluate(`Boolean(${frame}.querySelector('.org-facebook iframe'))`),false);
    assert.equal(await evaluate(`${frame}.querySelector('[data-ve-org-facebook]').textContent`),'+Add the Facebook page link');
    console.log('PASS: persisted public eyebrow/link, URL validation, and clearing the staged embed.');

})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(restoreEyebrow!==undefined&&socket)try{await send('Runtime.evaluate',{expression:`(async()=>{const fd=new FormData();fd.append('csrf_token',VE_CONFIG.csrf);fd.append('action','save_page');fd.append('texts',JSON.stringify({facebook_eyebrow:${JSON.stringify(restoreEyebrow)}}));await fetch('/admin/action/student-organizations-handler.php',{method:'POST',body:fd});})()`,awaitPromise:true});}catch(error){console.error('Eyebrow cleanup failed',error.message);process.exitCode=1;}
    if(orgFixture)try{fixture('cleanup',orgFixture.prefix);}catch(error){console.error(error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-org-contact-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
