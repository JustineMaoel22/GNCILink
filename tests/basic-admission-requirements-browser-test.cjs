const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const { spawn, execFileSync } = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-basic-admission-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) { for (const end=Date.now()+30000;Date.now()<end;) { if (await work()) return; await delay(100); } throw Error(message); }
const php = (...args) => execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
let socket, session;
const newCategories = [];
(async () => {
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser command timed out: '+method));},20000);pending.set(id,packet=>{clearTimeout(timer);packet.error?reject(Error(JSON.stringify(packet.error))):resolve(packet.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    const url=base+'/pages/admission/basic-education-admission-req.php';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    for (const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url});
        await until(()=>evaluate(`location.href===${JSON.stringify(url)}&&document.readyState==='complete'&&document.querySelectorAll('[data-basic-school]').length===4`),'Basic Education page did not load');
        assert.equal(await evaluate(`document.querySelectorAll('[data-basic-applicant-panel] li').length`),55,'All 55 requirements from the four source images are included');
        for (const [school,count] of [['casa',3],['montessori',5],['jhs-regular',5],['shs-regular',1]]) {
            await evaluate(`document.querySelector('[data-basic-school="${school}"]').click()`);
            assert.equal(await evaluate(`document.querySelectorAll('[data-basic-school-panel]:not([hidden])').length`),1);
            assert.equal(await evaluate(`document.querySelectorAll('[data-basic-school-panel="${school}"] [data-basic-applicant]').length`),count);
            const applicantKeys=await evaluate(`Array.from(document.querySelectorAll('[data-basic-school-panel="${school}"] [data-basic-applicant]')).map(tab=>tab.dataset.basicApplicant)`);
            for (const key of applicantKeys) {
                await evaluate(`document.querySelector('[data-basic-applicant="${key}"]').click()`);
                assert.equal(await evaluate(`document.querySelector('[data-basic-applicant-panel="${key}"]').hidden`),false);
                assert.equal(await evaluate(`document.querySelectorAll('[data-basic-school-panel="${school}"] [data-basic-applicant-panel]:not([hidden])').length`),1);
                assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'No horizontal overflow at '+width);
            }
        }
        await evaluate(`document.querySelector('[data-basic-school="casa"]').click();document.querySelector('[data-basic-applicant="casa_new"]').click();scrollTo(0,0)`);
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'End',code:'End'});
        await evaluate(`document.querySelector('[data-basic-school="casa"]').focus()`);
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight'});
        assert.equal(await evaluate(`document.querySelector('[data-basic-school="montessori"]').getAttribute('aria-selected')`),'true','School tabs support keyboard navigation');
        await evaluate(`document.querySelector('[data-basic-school="casa"]').click();scrollTo(0,0)`);
        await delay(800);
        const screenshot=await send('Page.captureScreenshot');
        fs.writeFileSync(path.join(os.tmpdir(),`gnci-basic-admission-${width}.png`),Buffer.from(screenshot.data,'base64'));
    }
    const college=await (await fetch(base+'/pages/admission/admission-req.php')).text();
    assert.match(college,/Incoming Freshmen/);
    assert.match(college,/Honorable Dismissal/);
    assert(!college.includes('data-basic-school='),'College keeps its own applicant requirements');
    assert.match(college,/href="\/pages\/admission\/basic-education-admission-req.php"/,'Navbar opens the separate Basic Education page');
    session=JSON.parse(php('session'));
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=basic-education-admission-requirements'});
    const frame='document.getElementById("veFrame").contentDocument';
    await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-section="casa_new"]')?.__ve)`),'New page was not registered in the editor');
    assert.equal(await evaluate(`${frame}.querySelectorAll('.admission-req-content[data-ve-section][contenteditable]').length`),14,'Every requirements group is editable');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-admission-label]').length`),6,'Heading, description and four school tab labels are editable');
    await evaluate(`${frame}.querySelector('[data-basic-school="montessori"]').click();${frame}.querySelector('[data-basic-applicant="montessori_esc"]').click()`);
    assert.equal(await evaluate(`${frame}.querySelector('[data-basic-applicant-panel="montessori_esc"]').hidden`),false,'Editor tabs work');
    await evaluate(`(()=>{const list=${frame}.querySelector('[data-ve-section="montessori_esc"] li');list.textContent='Unpublished Basic Education test';list.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'Editing a requirement enables Publish');
    assert(!(await(await fetch(url)).text()).includes('Unpublished Basic Education test'),'Unsaved requirements stay private');
    await evaluate('window.__veLeaving=true');
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-admission-requirements'});
    await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-section="freshmen"]')?.__ve)`),'College Admission Requirements editor did not open');
    assert.equal(await evaluate('VE_CONFIG.page.slug'),'college-admission-requirements');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-section][contenteditable]').length`),3,'College introduction and both applicant groups are editable');
    assert.equal(await evaluate(`${frame}.querySelectorAll('#panel-freshmen li').length`),8);
    assert.equal(await evaluate(`${frame}.querySelectorAll('#panel-transferees li').length`),7);
    await evaluate(`${frame}.getElementById('tab-transferees').click()`);
    assert.equal(await evaluate(`${frame}.getElementById('panel-transferees').hidden`),false);
    await evaluate(`(()=>{const item=${frame}.querySelector('[data-ve-section="transferees"] li');item.textContent='Unpublished College test';item.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false);
    assert(!(await(await fetch(base+'/pages/admission/admission-req.php')).text()).includes('Unpublished College test'));
    await evaluate('window.__veLeaving=true');
    for (const [page,scope,publicPath] of [
        ['college-admission-requirements','college','/pages/admission/admission-req.php'],
        ['basic-education-admission-requirements','casa','/pages/admission/basic-education-admission-req.php'],
    ]) {
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
        await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-add-category]'))`),'Category controls unavailable');
        const firstKey=page.startsWith('college')?'freshmen':'casa_new';
        await evaluate(`${frame}.querySelector('[data-ve-admission-add-item="${firstKey}"]').click()`);
        await evaluate(`document.querySelector('#vePop textarea').value='Unpublished extra checklist item';document.querySelector('#vePop button').click()`);
        assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false);
        assert(!(await(await fetch(base+publicPath)).text()).includes('Unpublished extra checklist item'),'Added checklist item stays private');
        await evaluate('window.__veLeaving=true');
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
        await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-add-category]'))`),'Reload unavailable');
        assert.equal(await evaluate(`${frame}.body.textContent.includes('Unpublished extra checklist item')`),false,'Reload discards staged checklist items');
        const title='ADMISSIONTEST-'+scope+'-'+Date.now();
        await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-add-category]').click()`);
        await evaluate(`document.querySelector('#vePop input').value=${JSON.stringify(title)};document.querySelector('#vePop button').click()`);
        const key=await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] .req-panel.is-active [data-ve-section]').getAttribute('data-ve-section')`);
        assert.match(key,/custom_[a-f0-9]{16}$/);
        newCategories.push({page,key});
        assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'New category enables Publish');
        assert(!(await(await fetch(base+publicPath)).text()).includes(title),'New category remains private');
        await evaluate(`${frame}.querySelector('[data-ve-admission-add-item="${key}"]').click()`);
        await evaluate(`document.querySelector('#vePop textarea').value='New category checklist item';document.querySelector('#vePop button').click()`);
        const categoryPost=fields=>evaluate(`(async()=>{const fd=new FormData();Object.entries(${JSON.stringify(fields)}).forEach(([key,value])=>fd.append(key,value));if(!fd.has('csrf_token'))fd.append('csrf_token',VE_CONFIG.csrf);const response=await fetch('/admin/action/admission-category-handler.php',{method:'POST',body:fd});return {status:response.status,body:await response.json()};})()`);
        assert.equal((await categoryPost({page,section_key:firstKey,title,content:'<p>invalid default category overwrite</p>'})).status,422,'New-category endpoint cannot overwrite default sections');
        assert.equal((await categoryPost({page:'history',section_key:key,title,content:'<p>wrong page</p>'})).status,422,'Categories are restricted to the admission pages');
        assert.equal((await categoryPost({page,section_key:key,title,content:'<p>missing authorization token</p>',csrf_token:'invalid'})).status,403,'Publishing requires a valid CSRF token');
        await evaluate(`(()=>{const native=window.fetch;window.fetch=function(input,options){if(String(input).includes('/admin/action/admission-category-handler.php')){window.fetch=native;return Promise.resolve(new Response(JSON.stringify({error:'Simulated category save failure'}),{status:422,headers:{'Content-Type':'application/json'}}));}return native.apply(this,arguments);};})()`);
        await evaluate('document.getElementById("vePrimary").click()');
        await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept,.cms-completeness-publish'))`),'Failure test publish dialog missing');
        await evaluate(`document.querySelector('.ve-publish-accept,.cms-completeness-publish').click()`);
        await until(()=>evaluate('!VE.state.busy&&document.getElementById("vePrimary").disabled===false'),'Failed publication did not retain pending changes');
        assert(!(await(await fetch(base+publicPath)).text()).includes(title),'Failed publication keeps category private');
        await evaluate('document.getElementById("vePrimary").click()');
        await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept,.cms-completeness-publish'))`),'Publish dialog missing');
        await evaluate(`document.querySelector('.ve-publish-accept,.cms-completeness-publish').click()`);
        await until(async()=>{const html=await(await fetch(base+publicPath)).text();return html.includes(title)&&html.includes('New category checklist item');},'Published category missing');
        await until(()=>evaluate('!VE.state.busy&&document.getElementById("vePrimary").disabled'),'Publish did not finish cleanly');
        const savedContent=await evaluate(`VE.cleanHtml(${frame}.querySelector('[data-ve-section="${key}"]').innerHTML)`);
        assert.equal((await categoryPost({page,section_key:key,title,content:savedContent})).body.success,true,'Retry with the same key succeeds without duplicating the category');
        // A second edit exercises the existing section-review route for a newly published category.
        await evaluate(`${frame}.querySelector('[data-ve-admission-add-item="${key}"]').click()`);
        await evaluate(`document.querySelector('#vePop textarea').value='Second checklist item';document.querySelector('#vePop button').click()`);
        await evaluate('document.getElementById("vePrimary").click()');
        await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept,.cms-completeness-publish'))`),'Second publish dialog missing');
        await evaluate(`document.querySelector('.ve-publish-accept,.cms-completeness-publish').click()`);
        await until(async()=>{const html=await(await fetch(base+publicPath)).text();return html.includes('Second checklist item');},'Second checklist item did not persist');
        await until(()=>evaluate('!VE.state.busy&&document.getElementById("vePrimary").disabled'),'Second publication did not finish');
        assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-admission-edit-item]').length`),0,'Checklist has no Edit buttons');
        assert.equal(await evaluate(`${frame}.defaultView.getComputedStyle(${frame}.querySelector('[data-ve-section="${key}"] [data-ve-admission-item-text]')).outlineStyle`),'dashed','Editable text has a visible outline');
        const textPosition=await evaluate(`(()=>{const f=document.getElementById('veFrame'),text=f.contentDocument.querySelector('[data-ve-section="${key}"] [data-ve-admission-item-text]');text.scrollIntoView({block:'center',behavior:'instant'});const r=text.getBoundingClientRect(),p=f.getBoundingClientRect();return {x:p.left+r.left+r.width/2,y:p.top+r.top+r.height/2};})()`);
        await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...textPosition});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...textPosition});
        assert.equal(await evaluate('document.getElementById("vePop").hidden'),true,'Clicking editable text opens no popover');
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-section="${key}"] [data-ve-admission-item-text]').contains(${frame}.defaultView.getSelection().anchorNode)`),true,'Click places the caret in checklist text');
        assert.equal(await evaluate(`${frame}.defaultView.getComputedStyle(${frame}.querySelector('[data-ve-section="${key}"] [data-ve-admission-item-text]')).outlineStyle`),'solid','Selected text has a stronger outline');
        await evaluate(`(()=>{const d=${frame},text=d.querySelector('[data-ve-section="${key}"] [data-ve-admission-item-text]'),range=d.createRange();range.selectNodeContents(text);d.defaultView.getSelection().removeAllRanges();d.defaultView.getSelection().addRange(range);})()`);
        assert.equal(await evaluate(`${frame}.defaultView.getSelection().toString()`),'New category checklist item','Selection contains only the checklist text');
        await send('Input.insertText',{text:'Edited checklist requirement'});
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-section="${key}"] li [data-ve-admission-item-text]').textContent`),'Edited checklist requirement','Typing changes the checklist directly in the page');
        assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'Editing a checklist item enables Publish');
        assert(!(await(await fetch(base+publicPath)).text()).includes('Edited checklist requirement'),'Checklist edits stay private');
        await evaluate('document.getElementById("vePrimary").click()');
        await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept,.cms-completeness-publish'))`),'Edit publish dialog missing');
        await evaluate(`document.querySelector('.ve-publish-accept,.cms-completeness-publish').click()`);
        await until(async()=>{const html=await(await fetch(base+publicPath)).text();return html.includes('Edited checklist requirement')&&!html.includes('New category checklist item');},'Edited checklist item did not persist');
        await until(()=>evaluate('!VE.state.busy&&document.getElementById("vePrimary").disabled'),'Edit publication did not finish');
        await evaluate(`${frame}.querySelectorAll('[data-ve-section="${key}"] li [data-ve-admission-delete-item]')[1].click()`);
        assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-section="${key}"] li').length`),1,'Delete removes only the selected item');
        assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),false,'Deleting a checklist item enables Publish');
        assert((await(await fetch(base+publicPath)).text()).includes('Second checklist item'),'Deletion stays private until publishing');
        await evaluate('document.getElementById("vePrimary").click()');
        await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept,.cms-completeness-publish'))`),'Delete publish dialog missing');
        await evaluate(`document.querySelector('.ve-publish-accept,.cms-completeness-publish').click()`);
        await until(async()=>{const html=await(await fetch(base+publicPath)).text();return html.includes('Edited checklist requirement')&&!html.includes('Second checklist item');},'Checklist deletion did not persist');
        await evaluate('window.__veLeaving=true');
        await send('Page.navigate',{url:base+publicPath});
        await until(()=>evaluate(`document.readyState==='complete'&&document.body.textContent.includes(${JSON.stringify(title)})`),'Public category did not reload');
        await evaluate(`document.querySelector('[aria-controls="${scope==='college'?'panel-':'applicant-panel-'}${key}"]').click()`);
        assert.equal(await evaluate(`document.getElementById('${scope==='college'?'panel-':'applicant-panel-'}${key}').hidden`),false,'Published category tab works after reload');
        assert.equal(await evaluate(`document.getElementById('${scope==='college'?'panel-':'applicant-panel-'}${key}').querySelectorAll('li').length`),1,'Edited list and deletion survive reload');
        assert.equal(await evaluate(`document.querySelectorAll('[data-ve-admission-add-category],[data-ve-admission-add-item],[data-ve-admission-item-tools]').length`),0,'Public visitors receive no editor controls');
        async function openEditor() {
            await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
            await until(()=>evaluate(`Boolean(document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-admission-delete-category="${key}"]'))`),'Category management controls unavailable');
        }
        async function publishCanvas() {
            await evaluate('document.getElementById("vePrimary").click()');
            await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept,.cms-completeness-publish'))`),'Layout publish dialog missing');
            await evaluate(`document.querySelector('.ve-publish-accept,.cms-completeness-publish').click()`);
            await until(()=>evaluate('!VE.state.busy&&document.getElementById("vePrimary").disabled'),'Layout changes did not publish');
        }
        async function drag(sourceSelector,targetSelector) {
            const points=await evaluate(`(()=>{const f=document.getElementById('veFrame'),d=f.contentDocument,s=d.querySelector(${JSON.stringify(sourceSelector)}),t=d.querySelector(${JSON.stringify(targetSelector)});s.scrollIntoView({block:'center',behavior:'instant'});const offset=f.getBoundingClientRect(),a=s.getBoundingClientRect(),b=t.getBoundingClientRect();return {from:{x:offset.left+a.left+a.width/2,y:offset.top+a.top+a.height/2},to:{x:offset.left+b.left+b.width/2,y:offset.top+b.top+b.height/2}};})()`);
            await send('Input.dispatchMouseEvent',{type:'mouseMoved',...points.from});
            await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',buttons:1,clickCount:1,...points.from});
            await send('Input.dispatchMouseEvent',{type:'mouseMoved',buttons:1,...points.to});
            const state=await evaluate(`(()=>{const f=document.getElementById('veFrame'),d=f.contentDocument,r=f.getBoundingClientRect();return {points:${JSON.stringify(points)},hit:d.elementFromPoint(${points.to.x}-r.left,${points.to.y}-r.top)?.outerHTML.slice(0,400),source:d.querySelector('.ve-admission-dragging')?.outerHTML.slice(0,200),target:d.querySelector('.ve-admission-drop-target')?.outerHTML.slice(0,200)};})()`);
            await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',buttons:0,clickCount:1,...points.to});
            return state;
        }
        await openEditor();
        await evaluate(`${frame}.querySelector('[aria-controls="${scope==='college'?'panel-':'applicant-panel-'}${key}"]').click()`);
        await evaluate(`${frame}.querySelector('[data-ve-admission-add-item="${key}"]').click()`);
        await evaluate(`document.querySelector('#vePop textarea').value='Reordered checklist item';document.querySelector('#vePop button').click()`);
        assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-section="${key}"] li.ve-admission-item-unsaved').length`),1,'Added item is highlighted as unsaved');
        await drag(`[data-ve-section="${key}"] li:last-child [data-ve-admission-drag-item]`,`[data-ve-section="${key}"] li:first-child`);
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-section="${key}"] li [data-ve-admission-item-text]').textContent`),'Reordered checklist item','Pointer drag reorders checklist items');
        await evaluate(`${frame}.querySelector('[data-ve-section="${key}"] li [data-ve-admission-delete-item]').click()`);
        assert.equal(await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-undo-banner]').hidden`),false,'Deletion exposes Undo');
        await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-undo]').click()`);
        assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-section="${key}"] li').length`),2,'Undo restores deleted checklist item');
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-section="${key}"] li [data-ve-admission-item-text]').textContent`),'Reordered checklist item','Undo preserves item order');
        const renamed=title+' renamed';
        await evaluate(`${frame}.querySelector('[data-ve-admission-rename-category="${key}"]').click()`);
        await evaluate(`document.querySelector('#vePop input').value=${JSON.stringify(renamed)};document.querySelector('#vePop button').click()`);
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-admission-category="${key}"] [role="tab"]').textContent`),renamed,'Rename updates category tab inline in preview');
        const secondTitle='ADMISSIONTEST-second-'+scope+'-'+Date.now();
        await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-add-category]').click()`);
        await evaluate(`document.querySelector('#vePop input').value=${JSON.stringify(secondTitle)};document.querySelector('#vePop button').click()`);
        const secondKey=await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] .req-panel.is-active [data-ve-section]').getAttribute('data-ve-section')`);
        newCategories.push({page,key:secondKey});
        await evaluate(`${frame}.querySelector('[data-ve-admission-add-item="${secondKey}"]').click()`);
        await evaluate(`document.querySelector('#vePop textarea').value='Temporary second category requirement';document.querySelector('#vePop button').click()`);
        const categoryDrag=await drag(`[data-ve-admission-drag-category="${secondKey}"]`,`[data-ve-admission-category="${key}"]`);
        const order=()=>evaluate(`Array.from(${frame}.querySelectorAll('[data-admission-scope="${scope}"] [data-admission-category-tabs] [role="tab"]')).map(tab=>tab.getAttribute('data-basic-applicant')||tab.getAttribute('data-target'))`);
        assert.deepEqual((await order()).slice(-2),[secondKey,key],'Pointer drag reorders categories: '+JSON.stringify(categoryDrag));
        await evaluate(`${frame}.querySelector('[data-ve-admission-delete-category="${secondKey}"]').click()`);
        await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-undo]').click()`);
        assert.deepEqual((await order()).slice(-2),[secondKey,key],'Undo restores a deleted category and its position');
        assert.equal(await evaluate(`${frame}.querySelector('[data-ve-admission-category="${key}"]').classList.contains('is-unsaved')`),true,'Changed category has an unsaved highlight');
        assert(!(await(await fetch(base+publicPath)).text()).includes(renamed),'Renaming stays private until Publish');
        const badBatch=await categoryPost({action:'publish_page',page,sections:JSON.stringify([{key,title:'ADMISSIONTEST-invalid',content:'<h2>Invalid replacement</h2>',removed:false}]),orders:JSON.stringify({[scope]:[key]})});
        assert.equal(badBatch.status,422,'Incomplete or cross-scope category order is rejected');
        assert(!(await(await fetch(base+publicPath)).text()).includes('Invalid replacement'),'Rejected batch leaves published content untouched');
        await publishCanvas();
        await send('Page.navigate',{url:base+publicPath});
        await until(()=>evaluate(`document.readyState==='complete'&&document.body.textContent.includes(${JSON.stringify(renamed)})`),'Renamed category did not persist');
        assert.deepEqual((await evaluate(`Array.from(document.querySelectorAll('[data-admission-scope="${scope}"] [data-admission-category-tabs] [role="tab"]')).map(tab=>tab.getAttribute('data-basic-applicant')||tab.getAttribute('data-target'))`)).slice(-2),[secondKey,key],'Published category order survives reload');
        const panelId=(scope==='college'?'panel-':'applicant-panel-')+key;
        assert.equal(await evaluate(`document.getElementById('${panelId}').querySelector('li').textContent`),'Reordered checklist item','Published checklist order survives reload');
        await openEditor();
        await evaluate(`${frame}.querySelector('[data-ve-admission-delete-category="${secondKey}"]').click()`);
        await evaluate(`${frame}.querySelector('[data-admission-scope="${scope}"] [data-ve-admission-undo]').click()`);
        assert.equal(await evaluate('document.getElementById("vePrimary").disabled'),true,'Undoing the only category deletion restores clean state');
        await evaluate(`${frame}.querySelector('[data-ve-admission-delete-category="${secondKey}"]').click()`);
        assert((await(await fetch(base+publicPath)).text()).includes(secondTitle),'Category deletion stays private until Publish');
        await publishCanvas();
        assert(!(await(await fetch(base+publicPath)).text()).includes(secondTitle),'Published category deletion removes the public tab');
        await openEditor();
        assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-admission-category="${secondKey}"]').length`),0,'Deleted category stays deleted after reload');
        assert.equal(await evaluate(`${frame}.querySelectorAll('.ve-extra').length`),0,'Deleted categories do not reappear as extra editable sections');
        await evaluate('window.__veLeaving=true');
    }
    console.log('PASS: both admission editors support undo deletion, pointer reordering of items and categories, category rename/delete, unsaved highlights, inline editing, atomic publishing and reload persistence; privacy, authorization and desktop/mobile checks pass.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    try {execFileSync('docker',['compose','exec','-T','web','php','tests/admission-category-fixture.php',JSON.stringify(newCategories)],{encoding:'utf8',windowsHide:true});}catch(error){console.error('Test category cleanup failed:',error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    socket?.close();browser.kill();await delay(500);
    if(path.dirname(path.resolve(profile))===path.resolve(os.tmpdir())&&path.basename(profile).startsWith('gnci-basic-admission-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
