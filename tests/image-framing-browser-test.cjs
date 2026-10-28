const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-framing-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session;
const {execFileSync}=require('node:child_process');
let fixtureData;
const fixture=(...args)=>execFileSync('docker',['compose','exec','-T','web','php','tests/image-framing-fixture.php',...args],{encoding:'utf8',windowsHide:true});
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Browser command timed out: '+method+' '+String(params.expression||'').slice(0,140)));},15000);pending.set(id,result=>{clearTimeout(timer);result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')console.error('Browser exception:',JSON.stringify(p.params.exceptionDetails));});
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    const auth=JSON.parse(execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true}));
    session={name:auth.name,id:auth.value};
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});


    await send('Page.navigate',{url:base+'/pages/academics/college/program.php?slug=beed'});
    await until(()=>evaluate('document.readyState==="complete"&&Boolean(window.GNCImageFraming)&&Boolean(document.querySelector(".hero-slide"))'),'Program banner unavailable');
    await delay(500);
    assert.equal(await evaluate(`(()=>{const e=document.querySelector('.hero-slide'),c=getComputedStyle(e),saved=GNCImageFraming.settings[new URL(GNCImageFraming.sourceOf(e)).pathname];return !!saved&&c.backgroundPosition===saved.x+'% '+saved.y+'%'&&c.backgroundSize.includes('px');})()`),true,'Live program banner applies its persisted framing');
    fixtureData=JSON.parse(fixture('seed'));
    const id=fixtureData.announcement_id;
    const info=()=>JSON.parse(fixture('inspect',String(id)));
    const original=info();
    const imagePath=original.path;
    // Rich-text dependencies are unrelated to framing and unavailable in offline test runs.
    await send('Page.addScriptToEvaluateOnNewDocument',{source:'window.bootstrap={Modal:class {constructor(el){this.el=el;el.__testModal=this;}show(){this.el.style.display="block";this.el.classList.add("show");}hide(){this.el.style.display="none";this.el.classList.remove("show");}static getInstance(el){return el.__testModal;}static getOrCreateInstance(el){return el.__testModal||new this(el);}}};window.Quill=class {constructor(selector){this.root=document.querySelector(selector);}on(){}setContents(){this.root.innerHTML="";}getText(){return this.root.textContent;}};'});
    async function navigate(url){await send('Page.navigate',{url});await delay(250);await until(()=>evaluate('document.readyState==="complete"&&Boolean(window.GNCImageFraming)'),'Page not ready');}
    async function openPreview(){await evaluate('void GNCImageFraming.edit(document.getElementById("modal-image-preview"))');await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Framing dialog did not open');}
    async function zoomTo(n){await evaluate(`(()=>{const z=document.querySelector('.image-frame-controls input');z.value=${n};z.dispatchEvent(new Event('input',{bubbles:true}));})()`);}
    async function drag(){
        const r=await evaluate('(()=>{const r=document.querySelector(".image-frame-preview").getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()');
        await send('Input.dispatchMouseEvent',{type:'mousePressed',x:r.x,y:r.y,button:'left',clickCount:1});
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:r.x+55,y:r.y+25,button:'left',buttons:1});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:r.x+55,y:r.y+25,button:'left',clickCount:1});
    }
    await navigate(base+'/admin/announcement.php?modal=edit&id='+id);
    // The Visual Editor's shared file input has no photo name. Its empty
    // portrait slot must determine the crop before an image exists there.
    await evaluate(`(async()=>{
        const slot=document.createElement('div');slot.id='emptyPortraitSlot';slot.style.cssText='width:240px;height:320px';document.body.append(slot);
        const input=document.createElement('input');input.type='file';input.id='unnamedPortraitUpload';input.setAttribute('data-photo-check','');document.body.append(input);
        GNCImageFraming.setUploadTarget(input,slot);
        const canvas=document.createElement('canvas');canvas.width=240;canvas.height=320;
        const ctx=canvas.getContext('2d');ctx.fillStyle='#eee';ctx.fillRect(0,0,240,320);ctx.fillStyle='#123';ctx.fillRect(80,20,80,80);
        const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
        const transfer=new DataTransfer();transfer.items.add(new File([blob],'portrait.png',{type:'image/png'}));input.files=transfer.files;
        input.dispatchEvent(new Event('change',{bubbles:true}));
    })()`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Empty portrait upload dialog did not open');
    const portrait=await evaluate(`(()=>{const p=document.querySelector('.image-frame-preview'),i=p.querySelector('img');return {ratio:p.clientWidth/p.clientHeight,imageHeight:parseFloat(i.style.height),frameHeight:p.clientHeight,top:parseFloat(i.style.top)};})()`);
    assert(Math.abs(portrait.ratio-.75)<.02,'An empty portrait slot uses its actual 3:4 ratio');
    assert(Math.abs(portrait.imageHeight-portrait.frameHeight)<2&&Math.abs(portrait.top)<2,'Matching portrait stays fully visible at 100% zoom');
    await evaluate('document.querySelector(".image-frame-actions button").click()');
    await until(()=>evaluate('!document.querySelector(".image-frame-dialog")'),'Portrait cancel failed');
    await evaluate('document.getElementById("emptyPortraitSlot").remove();document.getElementById("unnamedPortraitUpload").remove()');
    // Organization logos opt into framing while keeping contain sizing at 100%.
    await evaluate(`(()=>{
        const slot=document.createElement('span');slot.id='orgLogoSlot';slot.setAttribute('data-image-frame-fit','contain');slot.style.cssText='display:block;width:86px;height:86px;border-radius:50%';
        const img=document.createElement('img');img.id='orgLogoImage';img.src=${JSON.stringify(imagePath)};img.style.cssText='width:100%;height:100%;object-fit:contain;border-radius:50%';slot.append(img);document.body.append(slot);
        const plain=img.cloneNode();plain.id='plainContainImage';document.body.append(plain);
    })()`);
    await until(()=>evaluate('document.getElementById("orgLogoImage").naturalWidth>0'),'Logo fixture did not load');
    assert.equal(await evaluate('GNCImageFraming.findTarget(document.getElementById("orgLogoSlot"))?.id'),'orgLogoImage','Organization contain logos can be repositioned');
    assert.equal(await evaluate('GNCImageFraming.findTarget(document.getElementById("plainContainImage"))'),null,'Other contain illustrations remain unaffected');
    await evaluate('void GNCImageFraming.edit(document.getElementById("orgLogoSlot"))');
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Organization logo dialog unavailable');
    const logoPreview=await evaluate(`(()=>{const p=document.querySelector('.image-frame-preview'),i=p.querySelector('img');return {width:p.clientWidth,height:p.clientHeight,iw:parseFloat(i.style.width),ih:parseFloat(i.style.height)};})()`);
    assert(Math.abs(logoPreview.width-logoPreview.height)<2,'Organization logo preview keeps its square frame');
    assert(logoPreview.iw<=logoPreview.width+1&&logoPreview.ih<=logoPreview.height+1,'Contain logo stays fully visible at 100%');
    await zoomTo(2);
    assert.equal(await evaluate('document.getElementById("orgLogoImage").style.transform'),'scale(2)','Zoom applies to the organization logo');
    await evaluate('document.querySelector(".image-frame-actions button").click()');
    assert.equal(await evaluate('document.getElementById("orgLogoImage").style.transform'),'','Cancel restores the logo');
    await evaluate(`(()=>{
        const input=document.createElement('input');input.type='file';input.id='orgLogoUpload';document.body.append(input);GNCImageFraming.setUploadTarget(input,document.getElementById('orgLogoSlot'));
        const canvas=document.createElement('canvas');canvas.width=240;canvas.height=120;canvas.getContext('2d').fillRect(0,0,240,120);
        canvas.toBlob(blob=>{const transfer=new DataTransfer();transfer.items.add(new File([blob],'logo.png',{type:'image/png'}));input.files=transfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));},'image/png');
    })()`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Organization logo upload dialog unavailable');
    assert.equal(await evaluate(`(()=>{const p=document.querySelector('.image-frame-preview'),i=p.querySelector('img');return Math.abs(parseFloat(i.style.height)-p.clientHeight/2)<2;})()`),true,'Non-square logo upload uses contain sizing in the square frame');
    await zoomTo(2);
    await evaluate('document.querySelector(".image-frame-save").click()');
    await until(()=>evaluate('!document.querySelector(".image-frame-dialog")'),'Organization logo upload not accepted');
    await evaluate(`(()=>{const input=document.getElementById('orgLogoUpload'),img=document.getElementById('orgLogoImage');img.src=URL.createObjectURL(input.files[0]);})()`);
    await until(()=>evaluate('document.getElementById("orgLogoImage").style.transform==="scale(2)"'),'Accepted logo zoom did not reflect in the card');
    await evaluate('document.getElementById("orgLogoSlot").remove();document.getElementById("orgLogoUpload").remove();document.getElementById("plainContainImage").remove()');
    console.log('PASS: organization logo contain sizing, zoom, cancel, and accepted upload preview; unrelated contain images unchanged');
    await until(()=>evaluate('Boolean(document.getElementById("modal-image-preview")?.getAttribute("src"))'),'Announcement preview missing');
    const beforeStyle=await evaluate('document.getElementById("modal-image-preview").getAttribute("style")');
    await openPreview();
    const ratio=await evaluate('(()=>{const r=document.querySelector(".image-frame-preview").getBoundingClientRect();return r.width/r.height;})()');
    assert(Math.abs(ratio-1.4)<.02,'Preview retains the actual 140x100 container ratio');
    await zoomTo(2);await drag();
    const screenshot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-image-reposition-preview.png'),Buffer.from(screenshot.data,'base64'));
    assert.equal(await evaluate('document.getElementById("modal-image-preview").style.transform'),'scale(2)');
    assert.notEqual(await evaluate('document.getElementById("modal-image-preview").style.objectPosition'),'50% 50%','Pointer drag changes the displayed position');
    await evaluate('document.querySelector(".image-frame-actions button").click()');
    assert.equal((await evaluate('document.getElementById("modal-image-preview").getAttribute("style")'))||'',beforeStyle||'','Cancel restores the previous preview styles');
    assert.equal(info().frame,null,'Cancel does not write display settings');
    await openPreview();await zoomTo(1.7);await drag();
    await evaluate('document.querySelector(".image-frame-save").click()');
    await until(()=>evaluate('!document.querySelector(".image-frame-dialog")'),'Position did not save');
    const saved=info();assert.equal(saved.frame.zoom,1.7);assert.equal(saved.hash,original.hash,'Repositioning leaves original bytes unchanged');
    console.log('PASS: pointer drag, zoom, aspect ratio, live preview, cancel and metadata persistence');
    const csrf=await evaluate('GNC_IMAGE_FRAMING_CONFIG.csrf');
    const invalid=await fetch(base+'/admin/action/image-framing-handler.php',{method:'POST',headers:{Cookie:session.name+'='+session.id},body:new URLSearchParams({csrf_token:csrf,path:imagePath,framing:JSON.stringify({x:50,y:50,zoom:0})})});
    assert.equal(invalid.status,422);assert.deepEqual(info().frame,saved.frame);
    const denied=await fetch(base+'/admin/action/image-framing-handler.php',{method:'POST',body:new URLSearchParams({path:imagePath,framing:'{}'})});assert.equal(denied.status,403);
    const noToken=await fetch(base+'/admin/action/image-framing-handler.php',{method:'POST',headers:{Cookie:session.name+'='+session.id},body:new URLSearchParams({path:imagePath,framing:'{}'})});assert.equal(noToken.status,403);
    const article=base+'/announcement-article.php?id='+id;
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await navigate(article);
        await until(()=>evaluate(`Array.from(document.querySelectorAll('img')).some(img=>img.src.endsWith(${JSON.stringify(imagePath)})&&img.style.transform==='scale(1.7)')`),'Public image framing not applied');
        assert.equal(await evaluate(`GNCImageFraming.settings[${JSON.stringify(imagePath)}].zoom`),1.7);
        await evaluate(`(()=>{const box=document.createElement('div');box.id='framing-bg-test';box.style.cssText='width:320px;height:180px;background-image:linear-gradient(transparent,#0006),url('+${JSON.stringify(imagePath)}+');background-size:cover;background-position:center';document.body.append(box);})()`);
        await until(()=>evaluate('document.getElementById("framing-bg-test").style.backgroundSize.includes("px")'),'Background framing not applied');
        assert.equal(await evaluate('document.getElementById("framing-bg-test").style.backgroundSize.split(",").length'),2,'Gradient and image retain separate background layers');
        const backgroundSize=await evaluate('document.getElementById("framing-bg-test").style.backgroundSize');
        await evaluate('document.getElementById("framing-bg-test").style.width="400px";document.getElementById("framing-bg-test").style.height="225px"');
        await until(()=>evaluate('document.getElementById("framing-bg-test").style.backgroundSize') .then(value=>value!==backgroundSize),'Resized background frame did not update');
        assert.equal(await evaluate('document.getElementById("framing-bg-test").offsetWidth'),400);
        assert.equal(await evaluate('document.getElementById("framing-bg-test").offsetHeight'),225);
        console.log('PASS '+width+'px: public image and gradient background rendering, unchanged container dimensions');
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    console.log('Checking Visual Editor');
    await navigate(base+'/admin/content-edit.php?page=home');
    console.log('Visual Editor shell loaded');
    await until(()=>evaluate('Boolean(document.getElementById("veFrame")?.contentDocument?.querySelector("[data-ve-img]"))&&document.getElementById("veLoader").hidden'),'Visual Editor not ready');
    const frame='document.getElementById("veFrame").contentDocument';
    await evaluate(`(()=>{const el=${frame}.createElement('div');el.id='framing-ve-fixture';el.setAttribute('data-ve-img','home-image');el.setAttribute('data-ve-key','programs_1_image');el.style.cssText='width:320px;height:180px;background-size:cover;background-image:url('+${JSON.stringify(imagePath)}+')';${frame}.body.append(el);el.click();})()`);
    assert.equal(await evaluate('Boolean(document.querySelector(\'#veTb [aria-label="Reposition image"]\'))'),true,'Visual Editor exposes readjustment');
    await evaluate('document.querySelector(\'#veTb [aria-label="Reposition image"]\').click()');
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Visual Editor framing not available');
    await zoomTo(2);await evaluate('document.querySelector(".image-frame-actions button").click()');
    assert.equal(info().hash,original.hash);
    console.log('PASS: Visual Editor readjustment control');
    await navigate(base+'/admin/content-edit.php?page=programs&sub=beed');
    await until(()=>evaluate('Boolean(document.getElementById("veFrame")?.contentDocument?.querySelector(".hero-slide"))&&Boolean(document.querySelector("#veDock .ve-add-slide"))&&document.getElementById("veLoader").hidden'),'Program editor banner unavailable');
    await evaluate('document.querySelector("#veDock .ve-thumb").click()');
    assert.equal(await evaluate('Array.from(document.querySelectorAll("#vePop button")).some(b=>b.textContent==="Reposition image")'),true,'Banner options expose repositioning of the actual landing-page image');
    await evaluate(`(async()=>{
        Array.from(document.querySelectorAll('#vePop button')).find(b=>b.textContent==='Reposition image').click();
    })()`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Banner reposition dialog unavailable');
    const bannerRatio=await evaluate('(()=>{const r=document.getElementById("veFrame").contentDocument.querySelector(".hero-slide").getBoundingClientRect();return r.width/r.height;})()');
    assert(Math.abs(await evaluate('(()=>{const r=document.querySelector(".image-frame-preview").getBoundingClientRect();return r.width/r.height;})()')-bannerRatio)<.02,'Existing banner reposition preview matches the landing-page frame');
    await evaluate('document.querySelector(".image-frame-actions button").click()');
    await evaluate(`(async()=>{
        const input=document.getElementById('veFile');input.click=()=>{};
        document.querySelector('#veDock .ve-add-slide').click();
        await new Promise(resolve=>setTimeout(resolve,0));
        const canvas=document.createElement('canvas');canvas.width=1440;canvas.height=560;canvas.getContext('2d').fillRect(0,0,1440,560);
        const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png')),transfer=new DataTransfer();
        transfer.items.add(new File([blob],'banner-ratio-test.png',{type:'image/png'}));input.files=transfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));
    })()`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Banner upload preview unavailable');
    assert(Math.abs(await evaluate('(()=>{const r=document.querySelector(".image-frame-preview").getBoundingClientRect();return r.width/r.height;})()')-bannerRatio)<.02,'New banner upload uses the landing-page ratio instead of a generic landscape crop');
    await evaluate('document.querySelector(".image-frame-actions button").click()');
    await delay(100);
    console.log('PASS: live program framing, banner reposition control and matching upload/landing-page crop proportions');
    await navigate(base+'/admin/announcement.php?modal=create');
    const title='FRAME-UPLOAD-'+Date.now();
    await evaluate(`(async()=>{const b=await(await fetch(${JSON.stringify(imagePath)})).blob();const input=document.getElementById('modal-image-input'),dt=new DataTransfer();dt.items.add(new File([b],'frame-upload.png',{type:'image/png',lastModified:1}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'New upload did not open the position editor');
    await zoomTo(1.4);await drag();await evaluate('document.querySelector(".image-frame-save").click()');
    await until(()=>evaluate('!document.querySelector(".image-frame-dialog")&&document.getElementById("modal-image-preview").src.startsWith("data:")'),'Upload preview not released');
    assert.equal(await evaluate('GNCImageFraming.fileSettings(document.getElementById("modal-image-input").files[0]).zoom'),1.4);
    const result=await evaluate(`(async()=>{document.getElementById('modal-title').value=${JSON.stringify(title)};modalQuill.root.innerHTML='<p>New framed upload test.</p>';document.getElementById('modal-content-input').value=modalQuill.root.innerHTML;document.getElementById('modal-status').value='draft';const form=document.getElementById('announcement-form');form.addEventListener('submit',e=>e.preventDefault(),{once:true});form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));if(!form.querySelector('input[name=image_framing]'))throw Error('Native form framing missing');const r=await fetch('/admin/action/create-announcement.php',{method:'POST',body:new FormData(form)});return {status:r.status};})()`);
    assert.equal(result.status,200);
    const newId=JSON.parse(fixture('find',title));assert(newId,'Announcement with framed upload was not saved');
    const uploaded=JSON.parse(fixture('inspect',String(newId)));assert.equal(uploaded.frame.zoom,1.4);assert.equal(uploaded.hash,original.hash,'Upload retains exactly the selected original file');
    console.log('PASS: automatic upload editor, native form metadata, actual upload, saved framing and unchanged original bytes');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    try{fixture('cleanup');}catch(e){console.error('Fixture cleanup failed',e.message);process.exitCode=1;}
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-framing-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
