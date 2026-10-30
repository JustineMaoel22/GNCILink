const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php',...args],{encoding:'utf8',windowsHide:true});
const auth=JSON.parse(php('tests/admin-design-session.php','create'));
const folder=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-inline-style-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+folder,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+35000;Date.now()<end;){if(await fn())return;await delay(70);}throw Error(message);}
let socket,evaluate,fixtureId,fixtureRoute;const base='http://localhost:8080',marker=String(Date.now());
(async()=>{
    await until(()=>fs.existsSync(path.join(folder,'DevToolsActivePort')),'Chrome startup');
    const port=fs.readFileSync(path.join(folder,'DevToolsActivePort'),'utf8').split('\n')[0],tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial,timer=setTimeout(()=>reject(Error(method+' timeout')),40000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
    evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    await send('Page.enable');await send('Network.enable');
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:true});});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*facebook.com*','*youtube.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*']});
    await send('Network.setCookie',{name:auth.name,value:auth.value,url:base});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`Object.defineProperty(window,'VE_CONFIG',{configurable:true,set(value){delete window.VE_CONFIG;window.VE_CONFIG=value;if(value?.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('format_test','${marker}');value.page.url=u.pathname+u.search;}}});`});
    const pages=['home','history','vision-mission','administration','college-departments&sub=cased','programs&sub=bael','graduate-school','basic-education','student-organizations','student-achievements','athletics-sports','community-activities','faqs','tuition-fees','online-payment-guide','scholarships','contact-us','enrollment-procedures','basic-education-admission-requirements','college-admission-requirements'];
    for(const page of pages){
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
        await until(()=>evaluate(`window.VE_CONFIG?.page.slug===${JSON.stringify(page.split('&')[0])}&&Boolean(window.VE)&&!VE.state.busy&&document.getElementById('veLoader').hidden`),'Editor '+page);
        if(page==='enrollment-procedures')await evaluate(`(()=>{const pane=VE.state.doc.getElementById('basic-education-pane');VE.state.doc.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('active','show'));pane.classList.add('active','show');if(!pane.querySelector('[data-ve-field="enrollment-step-text"]')){pane.querySelector('[data-ve-enrollment-add]').click();const field=pane.querySelector('[data-ve-field="enrollment-step-text"][data-ve-key="title"]');field.textContent='Temporary enrollment step';field.dispatchEvent(new Event('input',{bubbles:true}));}})()`);
        const before=await evaluate(`(()=>{const nodes=VE.state.doc.querySelectorAll(${JSON.stringify(page==='enrollment-procedures'?'[data-ve-field="enrollment-step-text"]':'[data-ve-field],[data-ve-section]')});const el=Array.from(nodes).find(el=>el.isContentEditable&&el.textContent.trim().length>10&&el.getClientRects().length&&VE.state.win.GNCTextLayout.targets().values().some(t=>t===el));if(!el)throw Error('No editable target');window.styleField=el;el.click();const walker=el.ownerDocument.createTreeWalker(el,NodeFilter.SHOW_TEXT);let node;while(walker.nextNode()){if(walker.currentNode.length>=5){node=walker.currentNode;break;}}if(!node)throw Error('No text node');const r=el.ownerDocument.createRange();r.setStart(node,0);r.setEnd(node,5);const selection=VE.state.win.getSelection();selection.removeAllRanges();selection.addRange(r);return el.textContent;})()`);
        await until(()=>evaluate(`Array.from(document.querySelectorAll('#veToolbar button')).some(b=>b.textContent==='Font size')||Array.from(document.querySelectorAll('.ve-b')).some(b=>b.textContent==='Font size')`),'Size control '+page);
        await evaluate(`Array.from(document.querySelectorAll('.ve-b')).find(b=>b.textContent==='Font size').click();const size=document.querySelector('select[aria-label="Font size"]');size.value='32';size.dispatchEvent(new Event('change'));`);
        assert.equal(await evaluate(`Array.from(styleField.querySelectorAll('[data-gnc-inline-size]')).reduce((n,s)=>n+s.textContent.length,0)`),5,'Only selected characters sized on '+page+' '+await evaluate(`JSON.stringify({html:styleField.outerHTML,settings:VE.state.win.GNCTextLayout.snapshot(),toasts:document.getElementById('ceToasts').textContent})`));
        assert.equal(await evaluate('styleField.textContent'),before,'Content preserved on '+page);
        await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));Array.from(document.querySelectorAll('.ve-b')).find(b=>b.textContent==='Font color').click();const hex=document.querySelector('[aria-label="HEX text color"]');hex.value='#135E95';hex.dispatchEvent(new Event('input'));`);
        assert.equal(await evaluate(`Array.from(styleField.querySelectorAll('[data-gnc-inline-color]')).reduce((n,s)=>n+s.textContent.length,0)`),5,'Only selected characters colored on '+page);
        assert(await evaluate(`styleField.querySelector('[data-gnc-inline-size]')?.dataset.gncInlineSize==='32'`),'Color preserves size '+page);
        if(page==='home'){
            await evaluate(`Array.from(document.querySelectorAll('#vePop button')).find(b=>b.textContent==='Default text color').click()`);
            assert.equal(await evaluate('styleField.querySelectorAll("[data-gnc-inline-color]").length'),0,'Reset removes only selected color');
            assert(await evaluate('!!styleField.querySelector("[data-gnc-inline-size]")'),'Reset color preserves size');
            await evaluate(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));Array.from(document.querySelectorAll('.ve-b')).find(b=>b.textContent==='Font size').click();const custom=document.querySelector('[aria-label="Custom font size"]');custom.value='27.5';custom.dispatchEvent(new Event('input'));`);
            assert.equal(await evaluate('styleField.querySelector("[data-gnc-inline-size]").dataset.gncInlineSize'),'27.5','Custom fractional size previews immediately');
        }
        if(page==='history'){
            await evaluate('document.getElementById("veSaveDraft").click()');await until(()=>evaluate('!VE.state.busy&&document.getElementById("veSaveDraft").disabled'),'Private draft save');
            await evaluate('window.beforeStyleReload=true');await send('Page.reload');await until(()=>evaluate('!window.beforeStyleReload&&window.VE&&!VE.state.busy&&document.getElementById("veLoader").hidden&&!!VE.state.doc.querySelector("[data-gnc-inline-size]")'),'Style draft restored');
            assert(await evaluate('!!VE.state.doc.querySelector("[data-gnc-inline-color]")'),'Color restored from draft');
            fixtureId=await evaluate('VE.state.doc.querySelector("[data-gnc-inline-size]").dataset.gncInlineOwner');fixtureRoute=await evaluate('VE.state.url');
            const publicBefore=await(await fetch(new URL(fixtureRoute,base))).text();assert(!publicBefore.includes('"runs":'),'Draft formatting stays private');
            await evaluate('window.ContentCompleteness=null;window.beforeStylePublish=true;document.getElementById("vePrimary").click()');await until(()=>evaluate('!!document.querySelector(".ve-publish-dialog[open]")'),'Explicit publish confirmation');await evaluate('document.querySelector(".ve-publish-accept").click()');
            await until(()=>evaluate('window.VE&&!VE.state.busy&&VE.adapter().dirtyCount()===0'),'Style publication');
        }
        console.log('PASS editor:',page);
    }
    await send('Page.navigate',{url:new URL(fixtureRoute,base).href});
    await until(()=>evaluate('Boolean(window.GNCInlineTextStyle)&&!!document.querySelector("[data-gnc-inline-size]")'),'Public styled text');
    for(const width of [1440,900,390]){await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:false});await evaluate('GNCTextLayout.apply()');assert(await evaluate(`(()=>{const s=document.querySelector('[data-gnc-inline-size]');return !!s&&getComputedStyle(s).fontSize==='32px'&&getComputedStyle(s).color==='rgb(19, 94, 149)';})()`),'Public styles at '+width);}
    assert(await evaluate(`(()=>{const el=document.createElement('div');el.dataset.gncText='eeeeeeeeeeeeeeeeeeeeeeee';el.style.width='300px';el.style.maxWidth='100%';el.innerHTML='<p style="text-align:center">Before <strong>Bold <em>Italic</em></strong> <u>Underlined</u> after</p>';document.body.append(el);const text=el.textContent,a=GNCInlineTextStyle;const runs=a.edit([],text,{start:8,end:25},{size:200,color:'#B91C1C'});GNCTextLayout.setChanges({'eeeeeeeeeeeeeeeeeeeeeeee':{mobile:{value:{runs}}}});const markup=el.querySelector('strong em')&&el.querySelector('u')&&el.querySelector('p').style.textAlign==='center';const chars=Array.from(el.querySelectorAll('[data-gnc-inline-owner]')).reduce((n,s)=>n+s.textContent.length,0);return !!markup&&chars===17&&el.textContent===text&&el.scrollWidth<=el.clientWidth+1;})()`),'Nested bold/italic/underline and alignment survive across selection boundaries; large mobile text wraps');
    console.log('PASS: selected-only size/color on 20 editor pages; private draft restore, explicit publication, public styles at desktop/tablet/mobile widths.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(async()=>{
    if(fixtureId&&fixtureRoute&&evaluate)try{await evaluate(`(async()=>{const fd=new FormData();fd.set('csrf_token',window.VE_CONFIG?.csrf||window.GNC_IMAGE_FRAMING_CONFIG?.csrf||'');fd.set('route',${JSON.stringify(fixtureRoute)});fd.set('changes',JSON.stringify({[${JSON.stringify(fixtureId)}]:{desktop:null,tablet:null,mobile:null}}));await fetch('/admin/action/text-layout-handler.php',{method:'POST',body:fd});})()`);}catch{}
    try{php('tests/admin-design-session.php','destroy',auth.value);}catch{}
    if(socket)socket.close();browser.kill();await delay(800);if(path.dirname(folder)===os.tmpdir()&&path.basename(folder).startsWith('gnci-inline-style-'))try{fs.rmSync(folder,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
