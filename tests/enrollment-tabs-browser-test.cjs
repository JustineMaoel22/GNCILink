const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
function docker(args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;}
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-enrollment-tabs-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket,admin;
// All content and private-draft writes are mocked. Presence uses the real local endpoint.
function intercept(snapshot=null){return `window.__snapshot=${JSON.stringify(snapshot)};window.__writes=[];const original=window.fetch;window.fetch=async function(url,opts){
    const body=opts?.body,action=body instanceof FormData?body.get('action'):null;
    if(String(url).includes('editor-draft-handler')){if(action==='save')__snapshot=body.get('snapshot');return new Response(JSON.stringify({success:true,revision:action==='load'?(__snapshot?1:0):1,draft:action==='load'&&__snapshot?{revision:1,snapshot:JSON.parse(__snapshot)}:null}),{headers:{'Content-Type':'application/json'}});}
    if(opts?.method==='POST'&&!String(url).includes('editor-presence')){
        __writes.push({url:String(url),tabs:body.get('tabs'),tabs_revision:body.get('tabs_revision')});
        const doc=document.getElementById('veFrame').contentDocument,canvas=JSON.parse(doc.getElementById('veEnrollmentSteps').textContent);
        return new Response(JSON.stringify({success:true,steps:canvas.steps,revision:canvas.revision,images:JSON.parse(doc.getElementById('veEnrollmentIllustrations').textContent),tabs:JSON.parse(body.get('tabs')||'[]'),tabs_revision:'mock-revision'}),{headers:{'Content-Type':'application/json'}});
    }return original.apply(this,arguments);
};`;}
(async()=>{
    admin=JSON.parse(docker(['Super Admin']));
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    await send('Page.enable');await send('Network.enable');await send('Network.setCookie',{...admin,url:'http://localhost:8080',httpOnly:true});
    let script=await send('Page.addScriptToEvaluateOnNewDocument',{source:intercept()});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const visit=async()=>{await send('Page.navigate',{url:'http://localhost:8080/admin/content-edit.php?page=enrollment-procedures'});await until(()=>evaluate(`document.getElementById('veFrame')?.contentDocument?.querySelectorAll('[data-ve-enrollment-add-tab]').length>=3`),'Enrollment add-tab controls unavailable');await evaluate(`window.doc=()=>document.getElementById('veFrame').contentDocument;`);};
    await visit();
    assert.equal(await evaluate(`doc().querySelectorAll('#freshmen-pane .enrollment-step-card').length`),10,'Existing College steps changed');
    const add=async(parent,title)=>{
        await evaluate(`doc().querySelector('[data-ve-enrollment-add-tab="${parent}"]').click()`);
        await until(()=>evaluate(`Boolean(document.querySelector('.ve-pop input'))`),'Tab name input missing');
        await evaluate(`document.querySelector('.ve-pop input').value=${JSON.stringify(title)};document.querySelector('.ve-pop input').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));`);
        return evaluate(`Array.from(doc().querySelectorAll('[data-ve-enrollment-tab-name]')).find(b=>b.textContent===${JSON.stringify(title)}).getAttribute('data-ve-enrollment-tab-name')`);
    };
    const college=await add('college','Additional college applicants');
    await evaluate(`(()=>{const el=doc().querySelector('[data-ve-section="${college}"]');el.innerHTML='<p>Bring an original report card.</p>';el.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    const level=await add('level','Graduate School');
    const levelId='extra-'+level.slice(-16);
    const child=await add(levelId,'New graduate students');
    const basic=await add('basic','New Basic Education applicants');
    const addStep=async key=>evaluate(`doc().querySelector('[data-ve-enrollment-tab-add-step="${key}"]').click()`);
    const fillStep=async(key,index,title,instruction)=>evaluate(`(()=>{const card=doc().querySelectorAll('[data-ve-enrollment-tab-key="${key}"]')[${index}];for(const [field,value] of Object.entries({title:${JSON.stringify(title)},location:'Window 9',instruction:${JSON.stringify(instruction)}})){const el=card.querySelector('[data-ve-key="'+field+'"]');el.textContent=value;el.dispatchEvent(new Event('input',{bubbles:true}));}})()`);
    await addStep(child);
    await evaluate(`window.ContentCompleteness=undefined;document.getElementById('vePrimary').click()`);
    await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept'))`),'Step validation confirmation missing');
    await evaluate(`document.querySelector('.ve-publish-accept').click()`);
    await until(()=>evaluate(`document.body.textContent.includes('Add an office/title and instructions to each step')`),'Incomplete step was not rejected');
    assert.deepEqual(await evaluate('__writes'),[],'Incomplete steps reached the publish endpoint');
    await fillStep(child,0,"Registrar's Office",'Submit your report card.');
    await addStep(child);await fillStep(child,1,'Business Office','Pay the enrollment fee.');
    await evaluate(`doc().querySelectorAll('[data-ve-enrollment-tab-key="${child}"]')[1].click();Array.from(document.querySelectorAll('#veTb button')).find(b=>b.textContent.includes('Move step up')).click()`);
    assert.equal(await evaluate(`doc().querySelector('[data-ve-enrollment-tab-key="${child}"] [data-ve-key="title"]').textContent`),'Business Office');
    await evaluate(`doc().querySelectorAll('[data-ve-enrollment-tab-key="${child}"]')[1].click();Array.from(document.querySelectorAll('#veTb button')).find(b=>b.textContent.includes('Remove step')).click();Array.from(document.querySelectorAll('.ve-pop button')).find(b=>b.textContent==='Remove step').click()`);
    assert.equal(await evaluate(`doc().querySelectorAll('[data-ve-enrollment-tab-key="${child}"]').length`),1);
    await evaluate(`doc().querySelector('[data-ve-enrollment-tab-key="${child}"]').click();Array.from(document.querySelectorAll('#veTb button')).find(b=>b.textContent.includes('Choose illustration')).click();document.querySelector('.ve-pop select').value='/assets/images/svg/enrollment-procedures/fresh-2.svg';Array.from(document.querySelectorAll('.ve-pop button')).find(b=>b.textContent==='Apply').click()`);
    await addStep(level);await fillStep(level,0,'Graduate Office','Submit the graduate application.');
    await addStep(basic);await fillStep(basic,0,'Basic Education Office','Submit the Basic Education application.');
    assert.deepEqual(await evaluate('__writes'),[],'Adding tabs wrote live content');
    await evaluate(`doc().querySelector('[data-ve-enrollment-tab-name="${level}"]').click()`);await delay(250);
    await until(()=>evaluate(`Array.from(document.querySelectorAll('#veTb button')).some(b=>b.textContent.includes('Rename tab'))`),'Tab rename tool missing');
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.textContent.includes('Rename tab')).click()`);
    await evaluate(`document.querySelector('.ve-pop input').value='Graduate Studies';document.querySelector('.ve-pop input').dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));`);
    assert.equal(await evaluate(`doc().querySelector('[data-enrollment-tab-heading="${level}"]').textContent`),'Graduate Studies');
    await evaluate(`document.getElementById('veSaveDraft').click()`);await until(()=>evaluate('Boolean(__snapshot)'),'Tab draft not saved');
    const snapshot=await evaluate('__snapshot');await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:script.identifier});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:intercept(snapshot)});await visit();
    await until(()=>evaluate(`doc().querySelectorAll('[data-ve-enrollment-tab-name]').length===4`),'Custom tabs did not restore');
    assert.equal(await evaluate(`doc().querySelector('[data-ve-section="${college}"]').textContent`),'Bring an original report card.');
    assert.equal(await evaluate(`doc().querySelector('[data-ve-enrollment-tab-name="${level}"]').textContent`),'Graduate Studies');
    assert.equal(await evaluate(`doc().querySelector('[data-ve-enrollment-tab-key="${child}"] [data-ve-key="instruction"]').textContent`),'Pay the enrollment fee.');
    assert.equal(await evaluate(`doc().querySelector('[data-ve-enrollment-tab-key="${level}"] [data-ve-key="title"]').textContent`),'Graduate Office');
    assert.equal(await evaluate(`doc().querySelector('[data-ve-enrollment-tab-key="${child}"] img').getAttribute('src')`),'/assets/images/svg/enrollment-procedures/fresh-2.svg');
    assert.equal(await evaluate(`doc().querySelector('[data-ve-enrollment-tab-add-step="${child}"]').parentElement.id`),'extra-'+child.slice(-16)+'-pane','Add step moved out of its tab during draft restoration');
    assert.equal(await evaluate(`Boolean(doc().querySelector('[data-enrollment-tab-parent="${levelId}"]').compareDocumentPosition(doc().querySelector('[data-enrollment-tab-content="${levelId}"]'))&Node.DOCUMENT_POSITION_FOLLOWING)`),true,'Applicant tabs moved below their content');
    assert.equal(await evaluate(`doc().querySelector('[data-ve-section="${level}"]').parentElement.id`),levelId+'-overview-pane','Existing parent content was not preserved in the overview tab');
    assert.equal(await evaluate(`doc().querySelector('[data-enrollment-tab-heading="${level}"]').nextElementSibling.getAttribute('data-enrollment-tab-parent')`),levelId,'Applicant tabs must appear directly below the education-level heading');
    for(const width of [1440,768,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await evaluate(`doc().querySelector('[data-ve-enrollment-tab-name="${level}"]').click()`);await delay(250);
        await evaluate(`doc().querySelector('[data-ve-enrollment-tab-name="${child}"]').click()`);await delay(250);
        assert.equal(await evaluate(`doc().getElementById('${levelId}-pane').classList.contains('active')`),true);
        assert.equal(await evaluate(`doc().querySelector('[data-enrollment-tab-parent="${levelId}"] [data-ve-enrollment-tab-name]').getAttribute('aria-selected')`),'true');
        assert.equal(await evaluate(`doc().getElementById('${levelId}-overview-pane').classList.contains('active')`),false,'Parent steps remain visible while an applicant tab is selected');
        assert.equal(await evaluate(`doc().documentElement.scrollWidth<=doc().documentElement.clientWidth`),true,'Overflow at '+width);
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await evaluate(`doc().getElementById('college-tab').click()`);await delay(250);
    await evaluate(`doc().querySelector('[data-ve-enrollment-tab-name="${college}"]').click()`);await delay(250);
    assert.equal(await evaluate(`doc().querySelector('[data-ve-section="${college}"]').getBoundingClientRect().height>0`),true,'Selected tab content is hidden');
    assert.equal(await evaluate(`doc().querySelectorAll('#enrollmentLevelContent > .active').length`),1,'More than one education level is active');
    await evaluate(`doc().querySelector('[data-ve-enrollment-tab-name="${level}"]').click()`);await delay(250);
    await evaluate(`doc().querySelector('[data-ve-enrollment-tab-name="${child}"]').click();doc().querySelector('[data-enrollment-tab-heading="${level}"]').scrollIntoView({block:'start'})`);await delay(250);
    const screenshot=path.join(profile,'enrollment-tabs.png');fs.writeFileSync(screenshot,Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));console.log('Screenshot:',screenshot);
    await evaluate(`window.ContentCompleteness=undefined;window.AdminPublish={begin:async()=>true,end(){}};document.getElementById('vePrimary').click()`);
    await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept'))`),'Publish confirmation missing');await evaluate(`document.querySelector('.ve-publish-accept').click()`);
    await until(()=>evaluate(`__writes.some(w=>w.url.includes('enrollment-canvas-handler'))`),'Enrollment tabs were not included in publishing');
    const write=await evaluate(`__writes.find(w=>w.url.includes('enrollment-canvas-handler'))`),published=JSON.parse(write.tabs);
    assert.equal(published.length,4);assert.ok(write.tabs_revision);assert.equal(published.find(t=>t.key===level).title,'Graduate Studies');
    assert.ok(published.find(t=>t.key===college).content.includes('original report card'));
    assert.equal(published.find(t=>t.key===child).steps[0].title,'Business Office');
    assert.equal(published.find(t=>t.key===child).steps.length,1);
    assert.equal(published.find(t=>t.key===level).steps[0].instruction,'Submit the graduate application.');
    assert.equal(published.find(t=>t.key===basic).steps[0].title,'Basic Education Office');
    await until(()=>evaluate(`document.querySelector('.ve-status')?.textContent.includes('Everything here is live')`),'Publishing did not clear tab changes');
    console.log('PASS: Tabs and isolated step cards, required fields, reorder/remove, illustration selection, draft restore, desktop/tablet/mobile and publish payload. Content writes mocked.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();browser.kill();if(admin)docker(['destroy',admin.value]);});
