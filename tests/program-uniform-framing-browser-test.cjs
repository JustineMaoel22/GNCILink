const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-uniform-framing-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'],
    {windowsHide:true, stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) {
    for (const end = Date.now() + 15000; Date.now() < end;) { if (await work()) return; await delay(50); }
    throw Error(message);
}
let socket;
(async () => {
    await until(() => fs.existsSync(path.join(profile, 'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, {once:true}));
    let serial = 0; const pending = new Map();
    socket.addEventListener('message', event => { const p = JSON.parse(event.data); if (pending.has(p.id)) { pending.get(p.id)(p); pending.delete(p.id); } });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++serial;
        const timer = setTimeout(() => { pending.delete(id); reject(Error('Timed out: ' + method)); }, 15000);
        pending.set(id, p => { clearTimeout(timer); p.error ? reject(Error(JSON.stringify(p.error))) : resolve(p.result); });
        socket.send(JSON.stringify({id, method, params}));
    });
    const evaluate = async expression => {
        const r = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true});
        if (r.exceptionDetails) throw Error(JSON.stringify(r.exceptionDetails));
        return r.result.value;
    };
    await send('Page.enable');
    await send('Page.navigate', {url:(process.env.BASE_URL || 'http://localhost:8080') + '/assets/css/image-framing.css'});
    await until(() => evaluate('document.readyState === "complete"'), 'Local server unavailable');
    await send('Page.setDocumentContent', {frameId:(await send('Page.getFrameTree')).frameTree.frame.id,
        html:'<!doctype html><html><head><style>' + fs.readFileSync('assets/css/image-framing.css', 'utf8') + '</style></head><body><div class="fcar-viewport" style="width:640px;height:360px"></div><input type="file" id="upload"></body></html>'});
    await evaluate(`window.GNC_IMAGE_FRAMING_CONFIG={admin:true};window.optimizationRequests=0;const originalFetch=window.fetch.bind(window);window.fetch=(url,options)=>{if(String(url).includes('optimize-image.php')){optimizationRequests++;throw Error('Hosting optimizer unavailable');}return originalFetch(url,options);};`);
    await evaluate(fs.readFileSync('assets/js/image-upload.js', 'utf8'));
    await evaluate(fs.readFileSync('assets/js/image-framing.js', 'utf8'));
    const editor = fs.readFileSync('assets/js/content-edit.js', 'utf8');
    await evaluate(`var tab='uniform', S={doc:document}, fileInput=document.getElementById('upload'); fileInput.click=()=>{};function backgroundTarget(){return null;}
        ${editor.slice(editor.indexOf('        function pickFiles(accept, cb)'), editor.indexOf('        /** Why a file cannot'))}
        ${editor.slice(editor.indexOf('    function pickFile(accept,'), editor.indexOf('    function pickPersonPhoto('))}
        window.makeFile=async(w,h,mime='image/png')=>{const c=document.createElement('canvas');c.width=w;c.height=h;const ctx=c.getContext('2d');ctx.fillStyle='#176b42';ctx.fillRect(0,0,w,h);ctx.fillStyle='white';ctx.fillRect(0,0,w/2,h/2);return new File([await new Promise(r=>c.toBlob(r,mime)),new Uint8Array(6*1024*1024)],'photo.'+mime.split('/')[1],{type:mime});};`);
    for (const width of [1440, 390]) {
        await send('Emulation.setDeviceMetricsOverride', {width, height:900, deviceScaleFactor:1, mobile:width < 500});
        if (width < 500) await evaluate('window.createImageBitmap=undefined');
        // Homepage header pickers preserve the incoming image's orientation.
        for(const [w,h]of [[240,400],[640,360],[300,300]]){
            await evaluate(`(async()=>{window.headerAccepted=false;pickFile('image/*,video/*',()=>{headerAccepted=true;},false,null,false,false,'natural');const dt=new DataTransfer();dt.items.add(await makeFile(${w},${h}));fileInput.files=dt.files;fileInput.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            await until(()=>evaluate('!!document.querySelector(".image-frame-dialog[open]")'),'Homepage crop unavailable');
            const ratio=await evaluate('document.querySelector(".image-frame-preview").clientWidth/document.querySelector(".image-frame-preview").clientHeight');
            assert(Math.abs(ratio-w/h)<.02,'Homepage photo keeps natural orientation at '+width+'px: '+w+'x'+h);
            assert.equal(await evaluate('headerAccepted'),false,'Choosing a file waits for crop confirmation');
            await evaluate('document.querySelector(".image-frame-actions button").click()');
            assert.equal(await evaluate('headerAccepted'),false,'Cancelling does not stage a replacement');
        }
        // Exercise the actual Uniform picker, including an empty uniform list.
        await evaluate(`(async()=>{tab='uniform';pickFiles('image/png',()=>{});window.uniformFile=await makeFile(240,400);
            const dt=new DataTransfer();dt.items.add(uniformFile);fileInput.files=dt.files;fileInput.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until(() => evaluate('!!document.querySelector(".image-frame-dialog[open]")'), 'Uniform upload dialog unavailable');
        assert(await evaluate('fileInput.files[0].size<=5*1024*1024'), 'Oversized PNG is optimized before repositioning');
        await evaluate('window.uniformFile=fileInput.files[0]');
        const preview = await evaluate(`(()=>{const p=document.querySelector('.image-frame-preview'),i=p.querySelector('img');return {ratio:p.clientWidth/p.clientHeight,top:parseFloat(i.style.top),height:parseFloat(i.style.height),frameHeight:p.clientHeight,fits:p.getBoundingClientRect().right<=innerWidth};})()`);
        assert(Math.abs(preview.ratio - .6) < .01, 'Portrait uses its own 3:5 ratio');
        assert(Math.abs(preview.top) < 1 && Math.abs(preview.height-preview.frameHeight) < 2, 'Full photo visible at 100%');
        assert(preview.fits, 'Preview fits viewport');
        await evaluate(`(()=>{const z=document.querySelector('.image-frame-controls input');z.value=2;z.dispatchEvent(new Event('input'));document.querySelector('.image-frame-preview').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true}));document.querySelector('.image-frame-save').click();})()`);
        await until(() => evaluate('!document.querySelector(".image-frame-dialog")'), 'Upload position not accepted');
        // Natural IMG sizing (object-fit: fill) must still apply uploaded framing.
        await evaluate(`(()=>{document.querySelector('.template-uniform-card')?.remove();const card=document.createElement('div');card.className='template-uniform-card';const img=new Image();img.id='uniform';img.setAttribute('data-image-frame-ratio','natural');img.style.cssText='display:block;width:180px;height:auto';img.src=URL.createObjectURL(uniformFile);card.append(img);document.body.append(card);})()`);
        await until(() => evaluate('document.getElementById("uniform").style.transform === "scale(2)"'), 'Uniform preview did not apply uploaded zoom');
        assert.equal(await evaluate('document.getElementById("uniform").style.transformOrigin'), '52% 50%');
        await evaluate('void GNCImageFraming.edit(document.getElementById("uniform"))');
        await until(() => evaluate('!!document.querySelector(".image-frame-dialog[open]")'), 'Existing uniform reposition unavailable');
        assert(Math.abs(await evaluate('document.querySelector(".image-frame-preview").clientWidth/document.querySelector(".image-frame-preview").clientHeight') - .6) < .01, 'Existing uniform uses natural ratio after zoom');
        await evaluate(`document.querySelector('.image-frame-controls input').value=3;document.querySelector('.image-frame-controls input').dispatchEvent(new Event('input'));document.querySelector('.image-frame-actions button').click();`);
        assert.equal(await evaluate('document.getElementById("uniform").style.transform'), 'scale(2)', 'Cancel restores prior zoom');
        // A landscape replacement must use the new file, not the old portrait box.
        await evaluate(`(async()=>{pickFile('image/png',()=>{},false,document.getElementById('uniform'));const dt=new DataTransfer();dt.items.add(await makeFile(500,250));fileInput.files=dt.files;fileInput.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until(() => evaluate('!!document.querySelector(".image-frame-dialog[open]")'), 'Replacement dialog unavailable');
        assert(Math.abs(await evaluate('document.querySelector(".image-frame-preview").clientWidth/document.querySelector(".image-frame-preview").clientHeight') - 2) < .02, 'Replacement uses its own landscape ratio');
        await evaluate('document.querySelector(".image-frame-actions button").click()');
        // Facilities must receive compressed files only after positioning is
        // accepted, even when the host cannot accept oversized originals.
        for (const mime of ['image/png','image/jpeg','image/webp']) {
            await evaluate(`(async()=>{window.acceptedFacilityFiles=null;tab='facility';pickFiles('image/*',files=>{window.acceptedFacilityFiles=files;});const dt=new DataTransfer();dt.items.add(await makeFile(800,600,${JSON.stringify(mime)}));fileInput.files=dt.files;fileInput.dispatchEvent(new Event('change',{bubbles:true}));})()`);
            await until(() => evaluate('!!document.querySelector(".image-frame-dialog[open]")'), 'Large facility upload dialog unavailable');
            assert.equal(await evaluate('acceptedFacilityFiles'),null,'Facility handler waits for the position choice');
            await evaluate('document.querySelector(".image-frame-save").click()');
            await until(() => evaluate('!!acceptedFacilityFiles'), 'Optimized facility file did not reach the editor');
            const facility=await evaluate(`(()=>{const file=acceptedFacilityFiles[0];return {size:file.size,type:file.type,enabled:!fileInput.disabled};})()`);
            assert(facility.size<=5*1024*1024,'Facility handler receives a file within 5 MB');
            assert.equal(facility.type,mime,'Facility optimization preserves format');
            assert(facility.enabled,'Facility picker unlocks after optimization');
        }
        // Switching back to Facilities must restore its destination frame.
        await evaluate(`(async()=>{tab='facility';pickFiles('image/png',()=>{});const dt=new DataTransfer();dt.items.add(await makeFile(240,400));fileInput.files=dt.files;fileInput.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until(() => evaluate('!!document.querySelector(".image-frame-dialog[open]")'), 'Facility dialog unavailable');
        assert(Math.abs(await evaluate('document.querySelector(".image-frame-preview").clientWidth/document.querySelector(".image-frame-preview").clientHeight') - 16/9) < .02, 'Uniform setting does not leak into Facilities');
        await evaluate('document.querySelector(".image-frame-actions button").click()');
    }
    assert.equal(await evaluate('optimizationRequests'),0,'Large static PNGs never reach the unavailable hosting optimizer');
    console.log('PASS: Homepage portrait/landscape/square uploads retain natural framing on desktop/mobile; cancel stays private. Facilities/Uniform ratios, optimized files and replacements remain intact. No database writes.');
})().catch(error => {console.error(error); process.exitCode=1;}).finally(async () => {
    if (socket) socket.close(); browser.kill(); await delay(500);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-uniform-framing-')) {
        try {fs.rmSync(profile, {recursive:true, force:true, maxRetries:3, retryDelay:200});} catch {}
    }
});
