const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const docker = args => {
    const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if(r.status!==0) throw Error(r.stderr||r.stdout);
    return r.stdout;
};
const sessions = [];
const session = role => {const s=JSON.parse(docker([role]));sessions.push(s);return s;};
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-logs-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket;
(async()=>{
    const admin=session('Super Admin');
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page load timeout');};
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const routes=process.argv.slice(2).length?process.argv.slice(2):['home','history','contact-us','programs','administration','student-organizations','student-achievements','athletics-sports','faqs','community-activities','scholarships','online-payment-guide','tuition-fees','college-departments','basic-education','graduate-school','enrollment-procedures','college-admission-requirements'];
    for(const route of routes){
        await visit('/admin/content-edit.php?page='+route);
        await until(()=>evaluate('Boolean(window.VE?.state.doc) && !VE.state.busy && document.getElementById("veLoader").hidden'),'Editor not ready: '+route);
        await evaluate(`window.__writes=[];const original=window.fetch;window.fetch=function(url,opts){if(opts?.method==='POST')__writes.push(String(url));return original.apply(this,arguments);};
          window.__field=Array.from(VE.state.doc.querySelectorAll('[contenteditable]')).find(e=>e.isContentEditable && e.__ve && !e.closest('header,footer'));
          window.__before=__field?.innerHTML;`);
        if(!await evaluate('Boolean(__field)')){console.log('No editable text on route:',route);continue;}
        if(route==='home'){
            await evaluate(`VE.state.doc.querySelector('[data-ve-key="programs_2_image"]').scrollIntoView({block:'center'});`);
            await until(()=>evaluate(`!VE.state.doc.querySelector('[data-ve-key="programs_2_image"]').classList.contains('lazy-bg')`),'Homepage background did not load');
            await evaluate(`window.__programImage=VE.state.doc.querySelector('[data-ve-key="programs_2_image"]');window.__programBackground=__programImage.style.backgroundImage;`);
        }
        await evaluate(`(async()=>{const images=Array.from(VE.state.doc.images);await Promise.all(images.map(async img=>{img.loading='eager';try{await img.decode();}catch(_){}}));window.__loadedImages=images.filter(img=>img.naturalWidth>0&&!img.closest('header,footer'));})()`);
        await evaluate(`__field.textContent='History regression first';__field.dispatchEvent(new Event('input',{bubbles:true}));`);
        await until(()=>evaluate('!document.getElementById("veUndo").disabled'),'Undo not enabled: '+route);
        await evaluate(`__field.textContent='History regression second';__field.dispatchEvent(new Event('input',{bubbles:true}));`);
        await delay(50);
        await evaluate('document.getElementById("veUndo").click()');
        assert.equal(await evaluate('__field.textContent'),'History regression first',route+' first undo');
        await evaluate('document.getElementById("veUndo").click()');
        assert.equal(await evaluate('__field.innerHTML'),await evaluate('__before'),route+' baseline undo');
        if(route==='home'){
            assert.equal(await evaluate('__programImage.style.backgroundImage'),await evaluate('__programBackground'),'Undo preserves asynchronously loaded homepage background');
            for(let n=0;n<3;n++){
                await evaluate('document.getElementById("veRedo").click();document.getElementById("veUndo").click()');
                assert.equal(await evaluate('__programImage.style.backgroundImage'),await evaluate('__programBackground'),'Repeated undo preserves homepage background');
            }
            const shot=await send('Page.captureScreenshot',{format:'png'});
            fs.writeFileSync(path.join(__dirname,'../cache/editor-history-home-background.png'),Buffer.from(shot.data,'base64'));
        }
        assert.deepEqual(await evaluate(`(async()=>{const images=__loadedImages.map(old=>old.isConnected?old:(Array.from(VE.state.doc.images).find(img=>img.getAttribute('src')===old.getAttribute('src'))||old));await Promise.all(images.map(async img=>{try{await img.decode();}catch(_){}}));return images.filter(img=>!img.isConnected||!img.naturalWidth||getComputedStyle(img).opacity==='0'||img.closest('.skeleton-hero')||img.closest('.skeleton-wrap:not(.is-loaded)')).map(img=>({src:img.getAttribute('src'),class:img.className,parent:img.parentElement?.className}));})()`),[],route+' undo preserves loaded images');
        assert.equal(await evaluate('document.getElementById("veUndo").disabled'),true,route+' empty undo');
        await evaluate(`VE.state.doc.dispatchEvent(new KeyboardEvent('keydown',{key:'y',ctrlKey:true,bubbles:true,cancelable:true}));`);
        assert.equal(await evaluate('__field.textContent'),'History regression first',route+' Ctrl Y');
        await evaluate(`VE.state.doc.dispatchEvent(new KeyboardEvent('keydown',{key:'Z',ctrlKey:true,shiftKey:true,bubbles:true,cancelable:true}));`);
        assert.equal(await evaluate('__field.textContent'),'History regression second',route+' Shift Z');
        assert.equal(await evaluate('document.getElementById("veRedo").disabled'),true,route+' empty redo');
        await evaluate(`VE.state.doc.dispatchEvent(new KeyboardEvent('keydown',{key:'z',ctrlKey:true,bubbles:true,cancelable:true}));
          __field.textContent='History regression branch';__field.dispatchEvent(new Event('input',{bubbles:true}));`);
        await delay(50);
        assert.equal(await evaluate('document.getElementById("veRedo").disabled'),true,route+' redo branch cleared');
        assert.deepEqual(await evaluate('__writes'),[],route+' no automatic save');
        // Leave without saving the test content.
        await evaluate(`VE.state.win.onbeforeunload=null;window.onbeforeunload=null;window.AdminDialogs.confirm=async()=>true;`);
        console.log('Text history and shortcuts:',route);
    }
    await visit('/admin/content-edit.php?page=programs');
    await until(()=>evaluate('Boolean(window.VE?.state.doc) && !VE.state.busy && document.getElementById("veLoader").hidden'),'Program editor not ready');
    await evaluate(`window.__rich=Array.from(VE.state.doc.querySelectorAll('[data-ve-rich]')).find(e=>e.isContentEditable);__rich.innerHTML='<p>Formatting history check</p>';__rich.dispatchEvent(new Event('input',{bubbles:true}));`);
    await delay(50);
    await evaluate(`window.__richBefore=__rich.innerHTML;
      __rich.focus();const range=VE.state.doc.createRange();range.selectNodeContents(__rich);const selection=VE.state.win.getSelection();selection.removeAllRanges();selection.addRange(range);VE.state.doc.execCommand('bold');`);
    await delay(50);
    const richAfter=await evaluate('__rich.innerHTML');
    assert.notEqual(richAfter,await evaluate('__richBefore'),'Formatting changed the field');
    await evaluate('document.getElementById("veUndo").click()');
    assert.equal(await evaluate('__rich.innerHTML'),await evaluate('__richBefore'),'Undo formatting');
    await evaluate('document.getElementById("veRedo").click()');
    assert.equal(await evaluate('__rich.innerHTML'),richAfter,'Redo formatting');
    await evaluate(`(()=>{const input=document.createElement('input');document.getElementById('vePop').appendChild(input);window.__native=new KeyboardEvent('keydown',{key:'z',ctrlKey:true,bubbles:true,cancelable:true});input.dispatchEvent(__native);input.remove();})()`);
    assert.equal(await evaluate('__native.defaultPrevented'),false,'Popup input preserves native undo');
    await evaluate(`window.AdminDialogs.confirm=async()=>true;window.__writes=[];window.fetch=async function(url,opts){if(opts?.method==='POST')__writes.push(String(url));return new Response(JSON.stringify({ok:true,success:true,revision:1}),{headers:{'Content-Type':'application/json'}});};
      Array.from(document.querySelectorAll('.ve-dock-tab')).find(b=>b.textContent.includes('Uniform')).click();`);
    const count=await evaluate('document.querySelectorAll("#veDock .ve-thumb").length');
    await evaluate(`document.querySelector('#veDock .ve-add-slide').click();`);
    await evaluate(`const input=document.getElementById('veFile'), dt=new DataTransfer();dt.items.add(new File([Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='),c=>c.charCodeAt(0))],'history.png',{type:'image/png'}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));`);
    await until(()=>evaluate('Boolean(document.querySelector(".image-frame-save"))'),'Image framing unavailable');
    await evaluate('document.querySelector(".image-frame-save").click()');
    await until(()=>evaluate('document.querySelectorAll("#veDock .ve-thumb").length==='+ (count+1)),'Upload not staged');
    await delay(100);
    await evaluate('document.getElementById("veUndo").click()');
    assert.equal(await evaluate('document.querySelectorAll("#veDock .ve-thumb").length'),count,'Undo image insertion');
    await evaluate('document.getElementById("veRedo").click()');
    assert.equal(await evaluate('document.querySelectorAll("#veDock .ve-thumb").length'),count+1,'Redo image insertion');
    await evaluate(`Array.from(document.querySelectorAll('#veDock .ve-thumb')).at(-1).click();Array.from(document.querySelectorAll('.ve-pop button')).find(b=>b.textContent.includes('Delete')).click();`);
    await delay(100);
    assert.equal(await evaluate('document.querySelectorAll("#veDock .ve-thumb").length'),count,'Image deletion staged');
    await evaluate('document.getElementById("veUndo").click()');
    assert.equal(await evaluate('document.querySelectorAll("#veDock .ve-thumb").length'),count+1,'Undo image deletion');
    await evaluate('document.getElementById("veRedo").click()');
    assert.equal(await evaluate('document.querySelectorAll("#veDock .ve-thumb").length'),count,'Redo image deletion');
    assert.deepEqual(await evaluate('__writes'),[],'Media history never saves');
    await evaluate('document.getElementById("veSaveDraft").click()');
    await until(()=>evaluate('!VE.state.busy && __writes.length===1'),'Explicit draft save failed');
    await evaluate('document.getElementById("veUndo").click()');
    assert.equal(await evaluate('__writes.length'),1,'Undo after Save draft never saves automatically');
    assert.equal(await evaluate('document.getElementById("veRedo").disabled'),false,'Save draft retains history');
    for(const width of [1440,390]){
        await evaluate(`document.querySelectorAll('.ce-toast-x').forEach(b=>b.click());`);
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await delay(100);
        assert.equal(await evaluate(`['veUndo','veRedo'].every(id=>{const r=document.getElementById(id).getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height>0;})`),true,'Visible history controls at '+width);
        const shot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,'../cache/editor-history-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }
    console.log('Image history and responsive controls passed');
    const isolated=await evaluate(`(async()=>{
      const frame=document.createElement('iframe');frame.hidden=true;document.body.appendChild(frame);const doc=frame.contentDocument;
      doc.body.innerHTML='<div contenteditable="true">Initial</div><img><section class="skeleton-hero" data-bg="/assets/images/test-image.png"></section><aside class="skeleton-group"><span class="skeleton-wrap"><img></span></aside>';
      const field=doc.querySelector('div'),img=doc.querySelector('img');
      const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='),c=>c.charCodeAt(0));
      const a=new File([bytes],'a.png',{type:'image/png'}),b=new File([bytes],'b.png',{type:'image/png'});
      const ua=URL.createObjectURL(a),ub=URL.createObjectURL(b);GNCEditorDrafts.trackUrl(ua,a);GNCEditorDrafts.trackUrl(ub,b);
      img.src=ua;let state={file:a,text:'Initial',number:0},buttons;
      const history=GNCEditorHistory.create({document:()=>doc,blocked:()=>false,read:()=>state,write:v=>{state=v;},beforeRestore:()=>{},refresh:()=>{},buttons:(undo,redo)=>{buttons={undo,redo};}});
      history.bind(doc);history.start();
      const hero=doc.querySelector('section'),group=doc.querySelector('aside'),wrap=group.querySelector('span'),loadedImage=wrap.querySelector('img');
      hero.classList.remove('skeleton-hero');hero.style.backgroundImage='url("/assets/images/test-image.png")';
      group.classList.add('is-loaded');wrap.classList.add('is-loaded');loadedImage.classList.add('is-loaded');
      state.file=b;img.src=ub;history.refresh();await Promise.resolve();URL.revokeObjectURL(ua);
      history.undo();await img.decode();const media=state.file===a&&img.naturalWidth===1;
      const loaderState=!hero.classList.contains('skeleton-hero')&&hero.style.backgroundImage.includes('test-image.png')&&[group,wrap,loadedImage].every(el=>el.classList.contains('is-loaded'));
      history.redo();const redoMedia=state.file===b;history.reset();history.start();
      for(const text of ['A','AB','ABC']){field.dispatchEvent(new InputEvent('beforeinput',{inputType:'insertText',bubbles:true}));field.textContent=text;state.text=text;history.refresh();await Promise.resolve();}
      history.undo();const grouped=state.text==='Initial';history.redo();const groupedRedo=state.text==='ABC';
      history.reset();history.start();for(let number=1;number<=55;number++){state.number=number;history.refresh();await Promise.resolve();}
      for(let n=0;n<55;n++)history.undo();const bounded=state.number===5&&!buttons.undo;
      history.reset();frame.remove();URL.revokeObjectURL(ub);return {media,redoMedia,grouped,groupedRedo,bounded,loaderState};
    })()`);
    assert.deepEqual(isolated,{media:true,redoMedia:true,grouped:true,groupedRedo:true,bounded:true,loaderState:true});
    console.log('Revoked media recovery, typing groups and 50-change history passed');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});
