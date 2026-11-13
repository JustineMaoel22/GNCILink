const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const docker = args => {
    const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if(r.status!==0) throw Error(r.stderr||r.stdout);
    return r.stdout;
};
const sessions = [];
// A valid, self-contained PDF, so the browser can render the selected fixture.
let pdfFixture = '%PDF-1.4\n';
const offsets = [0];
const stream = 'BT /F1 18 Tf 40 150 Td (Curriculum preview regression) Tj ET\n';
const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 400 200] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    '<< /Length '+stream.length+' >>\nstream\n'+stream+'endstream'
];
objects.forEach((object, i) => { offsets.push(pdfFixture.length); pdfFixture += `${i+1} 0 obj\n${object}\nendobj\n`; });
const xref = pdfFixture.length;
pdfFixture += 'xref\n0 6\n0000000000 65535 f \n'+offsets.slice(1).map(offset=>String(offset).padStart(10,'0')+' 00000 n \n').join('')+'trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n'+xref+'\n%%EOF\n';
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
    await visit('/admin/content-edit.php?page=programs');
    await until(()=>evaluate('Boolean(window.VE?.state.doc?.getElementById("veProgData")) && !VE.state.busy && document.getElementById("veLoader").hidden'),'Program editor unavailable');
    await evaluate(`window.__writes=[]; const original=window.fetch; window.fetch=function(url,opts){if(opts?.method==='POST')__writes.push(String(url));return original.apply(this,arguments);};
      window.__baseline=VE.adapter().draftState(); window.__published=JSON.parse(VE.state.doc.getElementById('veProgData').textContent).pdf_url;
      window.__pdfBytes=${JSON.stringify(pdfFixture)};
      window.__choose=function(name){const input=document.getElementById('veFile');const dt=new DataTransfer();dt.items.add(new File([__pdfBytes],name,{type:'application/pdf'}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));};
      window.__manage=function(){VE.state.doc.querySelector('[data-ve-program-section="curriculum"]').click();};
      window.__popButton=function(text){Array.from(document.querySelectorAll('.ve-pop button')).find(b=>b.textContent===text).click();};
      __manage(); __popButton('Upload or replace PDF'); __choose('replacement-one.pdf');`);
    await until(()=>evaluate('Boolean(VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer"))'),'Selected PDF did not appear');
    await until(()=>evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] canvas")?.width > 0'),'The shared viewer did not render the selected PDF');
    assert.equal(await evaluate('Boolean(VE.state.doc.querySelector("[data-ve-curriculum-preview] iframe"))'),false,'Preview must use the shared viewer');
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] [data-role=page-count]").textContent'),'1');
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-title").textContent'),await evaluate('VE.state.doc.querySelector("#curriculum h2").textContent'));
    await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview]").scrollIntoView({block:"center"})');
    const screenshot = await send('Page.captureScreenshot',{format:'png'});
    fs.writeFileSync(path.join(profile,'curriculum-preview.png'),Buffer.from(screenshot.data,'base64'));
    assert.equal(await evaluate(`(async()=>{const f=VE.state.doc.querySelector('[data-ve-curriculum-preview] .gnc-pdf-viewer');return await(await fetch(f.getAttribute('data-pdf-src'))).text();})()`),await evaluate('__pdfBytes'));
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer").querySelector("[download]").download'),'replacement-one.pdf');
    assert.equal(await evaluate('VE.state.doc.querySelector("#curriculum").getAttribute("data-warning-value")'),'replacement-one.pdf');
    if(await evaluate('Boolean(__published)'))assert.equal(await evaluate('VE.state.doc.querySelector("#curriculum .gnc-pdf-viewer").style.display'),'none');
    await evaluate(`__manage();__popButton('Upload or replace PDF');__choose('replacement-two.pdf');`);
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer").querySelector("[download]").download'),'replacement-two.pdf');
    await evaluate(`document.getElementById('veUndo').click();`);
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer").querySelector("[download]").download'),'replacement-one.pdf');
    await evaluate(`document.getElementById('veRedo').click();`);
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer").querySelector("[download]").download'),'replacement-two.pdf');
    await evaluate(`(async()=>{window.__draft=await GNCEditorDrafts.encode({state:VE.adapter().draftState()});__manage();__popButton('Undo PDF change');})()`);
    assert.equal(await evaluate('Boolean(VE.state.doc.querySelector("[data-ve-curriculum-preview]"))'),false);
    if(await evaluate('Boolean(__published)'))assert.equal(await evaluate('VE.state.doc.querySelector("#curriculum .gnc-pdf-viewer").style.display'),'');
    await evaluate(`const decoded=GNCEditorDrafts.decode(JSON.parse(__draft));VE.adapter().draftState(decoded.state);VE.adapter().draftRebind();`);
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer").querySelector("[download]").download'),'replacement-two.pdf');
    assert.equal(await evaluate(`(async()=>await(await fetch(VE.state.doc.querySelector('[data-ve-curriculum-preview] .gnc-pdf-viewer').getAttribute('data-pdf-src'))).text())()`),await evaluate('__pdfBytes'));
    await evaluate(`__manage();__popButton('Remove PDF');`);
    assert.equal(await evaluate('Boolean(VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer"))'),false);
    assert.equal(await evaluate('VE.state.doc.querySelector("#curriculum").getAttribute("data-warning-value")'),'');
    // Simulate a program with no previously published document.
    await evaluate(`VE.adapter().draftState(__baseline);VE.adapter().draftRebind();VE.state.doc.querySelector('#curriculum .gnc-pdf-viewer')?.remove();
      __manage();__popButton('Upload or replace PDF');__choose('first-curriculum.pdf');`);
    assert.equal(await evaluate('VE.state.doc.querySelector("[data-ve-curriculum-preview] .gnc-pdf-viewer").querySelector("[download]").download'),'first-curriculum.pdf');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    assert.equal(await evaluate('Boolean(VE.state.doc.querySelector("[data-ve-curriculum-preview] a[target=_blank]"))'),true);
    assert.deepEqual(await evaluate('__writes.filter(url=>!url.includes("editor-presence-handler"))'),[],'Selecting a PDF must not write live content');
    console.log('PASS: replacement bytes, repeated replacement, undo/redo, removal, draft restore, first upload, mobile open link, no content writes.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});



