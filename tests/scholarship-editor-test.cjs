const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-scholarship-editor-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session;
const {execFileSync}=require('node:child_process');
let snapshot, uploaded, pdfUploads=[];
const fixture=(...args)=>execFileSync('docker',['compose','exec','-T','web','php','tests/scholarship-content-test.php',...args],{encoding:'utf8',windowsHide:true});
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser command timed out: '+method));},15000);pending.set(id,result=>{clearTimeout(timer);result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')console.error('Browser exception:',JSON.stringify(p.params.exceptionDetails));});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});

    fixture('prepare_draft_test');
    snapshot=fixture('snapshot').trim();
    const auth=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true}));
    session={name:auth.name,id:auth.value};
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const frame='(document.getElementById("veFrame")?.contentDocument)';
    const editor=base+'/admin/content-edit.php?page=scholarships';
    const publicPath='/pages/admission/scholarship.php';
    const live=async()=>await(await fetch(base+publicPath)).text();
    const photo=html=>html.match(/href="#basicEdModal"[\s\S]*?<img src="([^"]+)"/)[1];
    const before=await live();
    await send('Page.navigate',{url:editor});
    try {
    await until(()=>evaluate(`Boolean(${frame}?.getElementById('veScholarshipData'))&&document.getElementById('veLoader').hidden`),'Scholarship editor unavailable');
    } catch(error) { console.error(await evaluate(`({url:location.href,kind:window.VE_CONFIG?.page?.kind,body:document.body.innerText.slice(0,400),src:document.getElementById('veFrame')?.src,frameBody:${frame}?.body?.innerText?.slice(0,500),marker:Boolean(${frame}?.getElementById('veScholarshipData'))})`));throw error; }
    assert.equal(await evaluate('VE_CONFIG.page.kind'),'scholarships');
    assert.equal(await evaluate(`${frame}.querySelectorAll('[data-ve-field="scholarship-text"]').length`),73); // Empty old-student grade labels are also editable in preview.
    await evaluate(`${frame}.querySelector('h1').textContent='Scholarship browser fixture';${frame}.querySelector('h1').dispatchEvent(new Event('input',{bubbles:true}))`);
    await until(()=>evaluate('VE.adapter().dirtyCount()>0'),'Text change not staged');
    assert.equal(await live(),before,'Unpublished text remains private');

    let pdf='%PDF-1.4\n';const offsets=[0];
    for(const object of ['<</Type /Catalog /Pages 2 0 R>>','<</Type /Pages /Count 1 /Kids [3 0 R]>>','<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R>>','<</Length 0>>\nstream\n\nendstream']){offsets.push(pdf.length);pdf+=`${offsets.length-1} 0 obj\n${object}\nendobj\n`;}
    const xref=pdf.length;pdf+='xref\n0 5\n0000000000 65535 f \n'+offsets.slice(1).map(n=>String(n).padStart(10,'0')+' 00000 n \n').join('')+`trailer\n<</Size 5 /Root 1 0 R>>\nstartxref\n${xref}\n%%EOF`;

    const initialEntryCount=await evaluate('VE.adapter().draftState().entries.length');
    async function addScholarship(category,title){
        await evaluate(`${frame}.defaultView.bootstrap.Tab.getOrCreateInstance(${frame}.getElementById('${category==='gnc'?'gnc-tab':'gov-tab'}')).show()`);
        await evaluate(`${frame}.querySelector('[data-ve-scholarship-add="${category}"]').click()`);
        await evaluate(`(()=>{const form=document.getElementById('vePop');form.querySelector('[aria-label="Scholarship title"]').value=${JSON.stringify(title)};form.querySelector('[aria-label="Scholarship details"]').value='Eligibility and application details for this scholarship.';form.querySelector('[aria-label="Scholarship link"]').value='https://example.com/scholarship';const input=form.querySelector('[aria-label="Scholarship PDF"]');const dt=new DataTransfer();dt.items.add(new File([${JSON.stringify(pdf)}],'scholarship.pdf',{type:'application/pdf'}));input.files=dt.files;input.dispatchEvent(new Event('change'));Array.from(form.querySelectorAll('button')).find(b=>b.textContent==='Add scholarship').click();})()`);
        await until(()=>evaluate(`VE.adapter().draftState().entries.some(e=>e.title===${JSON.stringify(title)})`),'Scholarship was not added to preview');
    }
    await addScholarship('government','Government scholarship fixture');
    await addScholarship('gnc','School scholarship fixture');
    assert.equal(await evaluate('VE.adapter().draftState().entries.length'),initialEntryCount+2);
    assert(!(await live()).includes('Government scholarship fixture'),'Added scholarships remain private');

    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('document.getElementById("veStatus").textContent.includes("Draft saved")'),'Draft not saved');
    assert.equal(await live(),before,'Saved drafts remain private');

    const savedEntries=await evaluate(`(async()=>{const fd=new FormData();fd.append('csrf_token',VE_CONFIG.csrf);fd.append('page','scholarships');fd.append('route','/pages/admission/scholarship.php');fd.append('action','load');const result=await(await fetch('/admin/action/editor-draft-handler.php',{method:'POST',body:fd})).json();return result.draft.snapshot.state.entries;})()`);
    assert(savedEntries.some(entry=>entry.title==='Government scholarship fixture')&&savedEntries.some(entry=>entry.title==='School scholarship fixture'),'Added scholarships persist in the private draft');

    await evaluate(`${frame}.querySelector('[data-ve-key="photo_1"]').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Change photo').click()`);
    const png=await evaluate(`(()=>{const canvas=document.createElement('canvas');canvas.width=600;canvas.height=400;const ctx=canvas.getContext('2d');ctx.fillStyle='#176b42';ctx.fillRect(0,0,600,400);return canvas.toDataURL('image/png').split(',')[1];})()`);
    await evaluate(`(()=>{const bytes=Uint8Array.from(atob('${png}'),c=>c.charCodeAt(0));const input=document.getElementById('veFile');const dt=new DataTransfer();dt.items.add(new File([bytes],'scholarship-fixture.png',{type:'image/png'}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-save"))'),'Photo framing dialog missing');
    await evaluate('document.querySelector(".image-frame-save").click()');
    await until(()=>evaluate(`Boolean(VE.adapter().draftState().changes.photo_1)`),'Photo not staged');
    assert.equal(photo(await live()),photo(before),'Unpublished photo remains private');
    await evaluate('document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-accept,.cms-completeness-publish"))'),'Publish dialog missing');
    await evaluate('document.querySelector(".ve-publish-accept,.cms-completeness-publish").click()');
    await until(async()=>{const html=await live();uploaded=photo(html);return html.includes('Scholarship browser fixture')&&uploaded.startsWith('/uploads/scholarships/');},'Published content not visible');
    assert.equal((await fetch(base+uploaded)).status,200);
    await until(()=>evaluate(`Boolean(${frame}?.getElementById('veScholarshipData'))&&document.getElementById('veLoader').hidden&&document.getElementById('vePrimary').disabled`),'Editor reload missing');

    assert((await live()).includes('Government scholarship fixture')&&(await live()).includes('School scholarship fixture'),'Both categories publish their added entries');
    pdfUploads=await evaluate('VE.adapter().draftState().entries.filter(e=>e.title.includes("scholarship fixture")).map(e=>e.pdf)');
    assert.equal(pdfUploads.length,2);
    for(const url of pdfUploads){const response=await fetch(base+url);assert.equal(response.status,200);assert((await response.text()).startsWith('%PDF-'));}
    assert.equal(await evaluate(`${frame}.querySelectorAll('.sch-pdf:not([hidden]) iframe').length`),2);
    console.log('PASS: uploaded scholarship PDFs, embedded viewers, and downloadable documents.');
    const governmentId=await evaluate('VE.adapter().draftState().entries.find(e=>e.title==="Government scholarship fixture").id');
    const schoolId=await evaluate('VE.adapter().draftState().entries.find(e=>e.title==="School scholarship fixture").id');
    await evaluate(`(()=>{const title=${frame}.querySelector('[data-scholarship-entry="${governmentId}"] [data-sch-entry-prop="title"]');title.textContent='Updated government scholarship fixture';title.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    await evaluate(`${frame}.querySelector('[data-scholarship-entry="${schoolId}"] h2').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Remove scholarship').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#vePop button')).find(b=>b.textContent==='Remove').click()`);
    assert((await live()).includes('School scholarship fixture'),'Removed entry stays public until Publish');
    await evaluate('document.getElementById("vePrimary").click()');
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-accept,.cms-completeness-publish"))'),'Entry publish dialog missing');
    await evaluate('document.querySelector(".ve-publish-accept,.cms-completeness-publish").click()');
    await until(async()=>{const html=await live();return html.includes('Updated government scholarship fixture')&&!html.includes('School scholarship fixture');},'Entry edits/removal did not publish');
    await until(()=>evaluate(`Boolean(${frame}?.getElementById('veScholarshipData'))&&document.getElementById('veLoader').hidden&&document.getElementById('vePrimary').disabled`),'Entry editor reload missing');
    console.log('PASS: adding to both categories, private staging/drafts, entry updates, and staged removal through publishing.');

    const request=(texts,images={},csrf=true)=>evaluate(`(async()=>{const fd=new FormData();${csrf?'fd.append("csrf_token",VE_CONFIG.csrf);':''}fd.append('texts',JSON.stringify(${JSON.stringify(texts)}));fd.append('images',JSON.stringify(${JSON.stringify(images)}));const r=await fetch('/admin/action/scholarship-handler.php',{method:'POST',body:fd});return {status:r.status,body:await r.json()};})()`);

    const pdfRequest=async(content,forged=false)=>evaluate(`(async()=>{const entries=JSON.parse(JSON.stringify(VE.adapter().draftState().entries));const entry=entries.find(e=>e.title==='Updated government scholarship fixture');if(${forged})entry.pdf='/uploads/scholarships/'+'a'.repeat(32)+'.pdf';const fd=new FormData();fd.append('csrf_token',VE_CONFIG.csrf);fd.append('entries',JSON.stringify(entries));fd.append('texts',JSON.stringify({copy_1:'Scholarship browser fixture'}));if(${JSON.stringify(content)}!==null)fd.append('pdf_'+entry.id,new File([${JSON.stringify(content)}],'replacement.pdf',{type:'application/pdf'}));const response=await fetch('/admin/action/scholarship-handler.php',{method:'POST',body:fd});return {status:response.status,result:await response.json()};})()`);
    assert.equal((await pdfRequest('not a pdf')).status,422,'Invalid PDF rejected');
    assert.equal((await pdfRequest(null,true)).status,422,'Forged PDF paths rejected');
    assert.equal((await pdfRequest(pdf)).status,200,'Existing scholarship PDF can be replaced');
    const replacement=await live();
    const replacementPath=replacement.match(/data-sch-pdf-open href="([^"]+)"/)[1];
    assert(!pdfUploads.includes(replacementPath));pdfUploads.push(replacementPath);
    assert.equal((await fetch(base+replacementPath)).status,200);
    console.log('PASS: PDF replacement, malformed PDF rejection, and forged-path rejection.');

    assert.equal((await request({incomingGrade7_0_pct:'101'})).status,422);
    assert.equal((await request({unknown:'value'})).status,422);
    assert.equal((await request({copy_1:'CSRF failure'}, {},false)).status,403);
    assert.equal((await request({copy_1:'Must roll back'}, {photo_2:{reset:false}})).status,422);
    assert((await live()).includes('Scholarship browser fixture'),'Invalid batch rolls back text');
    assert.equal((await request({incomingGrade7_0_pct:'75',requirement_0:'Browser fixture requirement'})).status,200);
    const published=await live();assert(published.includes('75 percent off')&&published.includes('Browser fixture requirement'));
    assert.equal((await fetch(base+'/admin/action/scholarship-handler.php',{method:'POST'})).status,403);
    await evaluate(`${frame}.querySelector('[data-ve-key="photo_2"]').click()`);
    await evaluate(`Array.from(document.querySelectorAll('#veTb button')).find(b=>b.getAttribute('aria-label')==='Edit scholarship details').click()`);
    await until(()=>evaluate(`${frame}.getElementById('collegeModal').classList.contains('show')`),'College details unavailable');
    assert(await evaluate(`${frame}.querySelector('#collegeModal [data-ve-key="collegeOld_0_pct"]').isContentEditable`));
    console.log('PASS: editor discovery, text/photo staging, draft privacy, publishing, percent/requirement updates, modals, validation, atomic rollback, authorization and CSRF.');
    assert.equal((await request({}, {photo_1:{reset:true}})).status,200);
    assert.equal(photo(await live()),'/assets/images/acad_scholar_basic_edu.png','Photo reset restores the original asset');

    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+publicPath});
        await until(()=>evaluate('document.readyState==="complete"&&Boolean(document.querySelector(".sch-entry"))'),'Published scholarship entry unavailable');
        await evaluate('bootstrap.Tab.getOrCreateInstance(document.getElementById("gov-tab")).show()');
        await delay(1800);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Added scholarship responsive width');
        assert.equal(await evaluate('Boolean(document.querySelector("[data-ve-scholarship-add]"))'),false,'Add controls stay private');
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-added-scholarship-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }

    fixture('restore',snapshot);snapshot=null;
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=online-payment-guide'});
    await until(()=>evaluate(`Boolean(${frame}?.getElementById('vePaymentGuideData'))&&document.getElementById('veLoader').hidden`),'Payment guide editor regression');
    assert.equal(await evaluate('VE_CONFIG.page.kind'),'payment-guide');
    assert(await evaluate(`Array.from(document.querySelectorAll('#veSecondary')).some(b=>b.textContent.includes('Browse Storyset'))`),'Payment guide retains its illustration controls');
    console.log('PASS: scholarship photo reset and existing payment-guide editor controls.');
    for(const width of [1440,768,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+publicPath});
        await until(()=>evaluate('document.readyState==="complete"&&Boolean(document.querySelector(".sch-photo-grid"))'),'Public page unavailable');
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Responsive page width');
        assert.equal(await evaluate('Boolean(document.getElementById("veScholarshipData"))'),false);
        await delay(1800);
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-scholarships-'+width+'.png'),Buffer.from(shot.data,'base64'));
        console.log('PASS '+width+'px: public page width and no editor controls.');
    }
    for (const [name,url] of [['tuition','/pages/admission/tuition-fees.php'],['payment','/pages/admission/online-payment-guide.php']]) {
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+url});
        await until(()=>evaluate('document.readyState==="complete"&&Boolean(document.querySelector("footer"))'),'Comparison page unavailable');
        await delay(1800);const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-scholarships-compare-'+name+'.png'),Buffer.from(shot.data,'base64'));
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(snapshot)try{fixture('restore',snapshot);}catch(error){console.error('Snapshot restore failed',error.message);process.exitCode=1;}
    if(uploaded?.startsWith('/uploads/scholarships/'))try{fixture('remove_test_upload',uploaded);}catch(error){console.error('Upload cleanup failed',error.message);process.exitCode=1;}
    try{fixture('cleanup_test_draft');}catch(error){console.error('Draft cleanup failed',error.message);process.exitCode=1;}
    for(const url of pdfUploads)try{fixture('remove_test_upload',url);}catch(error){console.error('PDF cleanup failed',error.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-scholarship-editor-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
