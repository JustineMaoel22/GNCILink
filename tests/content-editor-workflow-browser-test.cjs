const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=path.join('cache','admin-design-audit');fs.mkdirSync(auditDirectory,{recursive:true});
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-calendar-widget-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*facebook.com*','*youtube.com*']});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"try{sessionStorage.setItem('gncIntroSeen','1')}catch(_){}"});

    await send('Network.setCookie',{name:adminSession.name,value:adminSession.value,url:base,httpOnly:true});


 await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=bael'});
 const frame='document.getElementById("veFrame")?.contentDocument';
 await until(()=>evaluate('Boolean('+frame+'?.querySelector("[data-ve-key=mission_text]")?.__ve)'),'Program editor not ready');
 await evaluate(frame+'.querySelector("[data-ve-program-section=uniform]").click()');
 await until(()=>evaluate('Boolean(document.querySelector(".ve-dock-tab.is-on")?.textContent.includes("Uniform photos"))'),'Uniform visual strip did not open');
 assert.equal(await evaluate('Boolean(document.getElementById("veDrawer")||document.querySelector(".pgm-card"))'),false,'Programs no longer render the form drawer');
 await until(()=>evaluate('!VE.state.busy && document.getElementById("veLoader").hidden'),'Program editor still loading');
 // A synthetic settings panel exercises the real delegated flow without any network writes.
 await evaluate('(()=>{const panel=document.createElement("div");panel.id="workflowFixture";panel.setAttribute("data-warning-scope","");panel.innerHTML="<label>Heading<input name=heading value=Complete></label><button type=button data-content-publish>Save Changes</button>";document.body.append(panel);window.workflowSaves=0;panel.querySelector("button").addEventListener("click",()=>workflowSaves++);})()');
 await until(()=>evaluate('document.querySelector("#workflowFixture button").textContent.trim()==="Publish"'),'Dynamic action label not normalized');
 await evaluate('document.querySelector("#workflowFixture button").click();document.querySelector("#workflowFixture button").click()');
 await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Administration confirmation missing');
 assert.equal(await evaluate('document.querySelectorAll(".ve-publish-dialog").length'),1);
 await evaluate('document.querySelector(".ve-publish-cancel").click()');await delay(100);
 assert.equal(await evaluate('workflowSaves'),0,'Cancel must not save');
 await evaluate('document.querySelector("#workflowFixture button").click()');
 await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Confirmation did not reopen');
 await evaluate('document.querySelector(".ve-publish-accept").click()');
 await until(()=>evaluate('workflowSaves===1'),'Confirmed save must execute once');
 await evaluate('document.querySelector("#workflowFixture input").value="";document.querySelector("#workflowFixture button").click()');
 await until(()=>evaluate('Boolean(document.querySelector(".cms-completeness-dialog[open]"))'),'Missing-content warning missing');
 await evaluate('document.querySelector(".cms-completeness-publish").click()');
 await until(()=>evaluate('workflowSaves===2'),'Publish Anyway must save exactly once');
 await evaluate('document.querySelector("#workflowFixture").remove();ContentEditorNotice("Saved successfully",false)');
 assert.equal(await evaluate('[...document.querySelectorAll(".ce-toast span")].at(-1).textContent'),'Published. Your changes are live now.');
 await evaluate('ContentEditorNotice("Network error. Please try again.",true)');
 assert.equal(await evaluate('[...document.querySelectorAll(".ce-toast-error span")].at(-1).textContent'),'Network error. Please try again.');
 // Form submits retain draft/pending semantics.
 await evaluate('(()=>{const f=document.createElement("form");f.id="workflowForm";f.setAttribute("data-content-publish","");f.innerHTML="<select name=status><option value=draft>Draft</option><option value=pending>Pending</option><option value=published>Live</option></select><input name=title value=Complete><button type=submit>Save Changes</button>";document.body.append(f);window.formSaves=0;f.addEventListener("submit",e=>{e.preventDefault();formSaves++});})()');
 await until(()=>evaluate('document.querySelector("#workflowForm button").textContent.trim()==="Save draft"'),'Draft action lost');
 await evaluate('document.querySelector("#workflowForm button").click()');assert.equal(await evaluate('formSaves'),1);
 await evaluate('document.querySelector("#workflowForm select").value="pending";document.querySelector("#workflowForm select").dispatchEvent(new Event("change",{bubbles:true}))');
 assert.equal(await evaluate('document.querySelector("#workflowForm button").textContent.trim()'),'Submit for approval');
 await evaluate('document.querySelector("#workflowForm select").value="published";document.querySelector("#workflowForm select").dispatchEvent(new Event("change",{bubbles:true}));document.querySelector("#workflowForm button").click()');
 await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Published form confirmation missing');
 await evaluate('document.querySelector(".ve-publish-accept").click()');await until(()=>evaluate('formSaves===2'),'Published form did not replay once');
 await evaluate('document.querySelector("#workflowForm").remove()');

 // Every canvas must expose the same workflow, including pages with a custom adapter.
 for(const page of ['home','administration','college-departments','basic-education','graduate-school','vision-mission','history','contact-us','enrollment-procedures','basic-education-admission-requirements','college-admission-requirements','student-organizations','student-achievements','athletics-sports','online-payment-guide','tuition-fees','updates','community-activities','faqs']) {
  await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+page});
  await until(()=>evaluate('Boolean(window.ContentEditorWorkflow&&window.VE&&!VE.state.busy&&document.getElementById("veLoader").hidden&&document.getElementById("vePrimary"))'),'Shared workflow unavailable: '+page);
  await evaluate('(()=>{const root=document.createElement("section");root.id="sweepFixture";root.innerHTML="<p data-warning-field data-warning-label=Heading>Complete heading</p>";document.body.append(root);window.sweepResult=null;ContentEditorWorkflow.confirm({root}).then(value=>sweepResult=value);})()');
  await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Publish confirmation unavailable: '+page);
  assert.equal(await evaluate('document.getElementById("vePublishMessage").textContent'),'Publish your changes to the live website?');
  await evaluate('document.querySelector(".ve-publish-cancel").click()');
  await until(()=>evaluate('sweepResult===false'),'Cancel failed: '+page);
  await evaluate('document.querySelector("#sweepFixture p").textContent="";window.sweepResult=null;ContentEditorWorkflow.confirm({root:document.getElementById("sweepFixture")}).then(value=>sweepResult=value);void 0');
  await until(()=>evaluate('Boolean(document.querySelector(".cms-completeness-dialog[open]"))'),'Missing-content warning unavailable: '+page);
  await evaluate('document.querySelector(".cms-completeness-publish").click()');
  await until(()=>evaluate('sweepResult===true'),'Publish Anyway failed: '+page);
  assert.equal(await evaluate('VE.state.allowIncomplete'),true,'Override remembered on '+page);
  await evaluate('document.getElementById("sweepFixture").remove()');
 }
 // The embedded post editors delegate to the parent dialog and toast as well.
 await evaluate('(()=>{const f=document.createElement("iframe");f.id="workflowEmbed";f.src="/admin/news.php?embed=1";document.body.append(f);})()');
 await until(()=>evaluate('Boolean(document.getElementById("workflowEmbed")?.contentWindow.ContentEditorNotice)'),'Embedded editor bridge unavailable');
 await evaluate('(()=>{const w=document.getElementById("workflowEmbed").contentWindow,d=w.document,f=d.createElement("form");f.id="bridgeFixture";f.setAttribute("data-content-publish","");f.innerHTML="<input name=title value=Complete><button type=submit>Save changes</button>";d.body.append(f);w.bridgeSaves=0;f.addEventListener("submit",e=>{e.preventDefault();w.bridgeSaves++});})()');
 await until(()=>evaluate('document.getElementById("workflowEmbed").contentDocument.querySelector("#bridgeFixture button").textContent.trim()==="Publish"'),'Embedded label not normalized');
 await evaluate('document.getElementById("workflowEmbed").contentDocument.querySelector("#bridgeFixture button").click()');
 await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Embedded action did not use parent dialog');
 await evaluate('document.querySelector(".ve-publish-cancel").click()');await delay(100);
 assert.equal(await evaluate('document.getElementById("workflowEmbed").contentWindow.bridgeSaves'),0,'Embedded cancel must not save');
 await evaluate('document.getElementById("workflowEmbed").contentWindow.ContentEditorNotice("News updated successfully",false)');
 assert.equal(await evaluate('[...document.querySelectorAll(".ce-toast span")].at(-1).textContent'),'Published. Your changes are live now.');
 console.log('PASS: same confirmation and Publish Anyway across 19 Content Editor pages and embedded post editor; no content saved.');
 console.log('PASS: main/drawer shared dialog, dynamic Publish labels, cancel, duplicate protection, Publish Anyway, success/error notifications, draft/approval preservation and one-time form replay. No real content saved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
