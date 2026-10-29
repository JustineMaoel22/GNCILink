const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const markup=execFileSync('php',['tests/visitor-feedback-fixture.php'],{encoding:'utf8',windowsHide:true});
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-feedback-browser-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,label){for(let end=Date.now()+20000;Date.now()<end;){if(await work())return;await pause(50);}throw Error(label);}
let socket,send,apiCalls=0,formLoads=0,liveFormStatus=null;
(async()=>{
 await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
 const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
 const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
 await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
 let serial=0;const pending=new Map();
 socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
 send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial,timer=setTimeout(()=>{pending.delete(id);reject(Error('Timed out: '+method));},20000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 const base=process.env.BASE_URL||'http://localhost:8080';
 await send('Page.enable');await send('Network.enable');
 socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.method==='Network.responseReceived'&&p.params.type==='Document'&&p.params.response.url.startsWith('https://docs.google.com/forms/'))liveFormStatus=p.params.response.status;});
 socket.addEventListener('message',async event=>{
  const p=JSON.parse(event.data);if(p.method!=='Fetch.requestPaused')return;
  const {request,requestId}=p.params;
  if(new URL(request.url).pathname.startsWith('/api/')){
   apiCalls++;await send('Fetch.fulfillRequest',{requestId,responseCode:410,body:''});
  }else if(request.url.startsWith('https://docs.google.com/forms/')){
   formLoads++;
   if(process.env.LIVE_FEEDBACK_FORM==='1'){await send('Fetch.continueRequest',{requestId});return;}
   await send('Fetch.fulfillRequest',{requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from('<!doctype html><meta charset="utf-8"><h1>GNCI Website Feedback</h1><p>Google Form placeholder for automated tests.</p>').toString('base64')});
  }else await send('Fetch.fulfillRequest',{requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from('<!doctype html><html><head><meta charset="utf-8"><title>GNCILink feedback test</title></head><body><button id="before">Before modal</button><main style="height:1200px"><h1>GNCILink</h1></main>'+markup+'</body></html>').toString('base64')});
 });
 await send('Fetch.enable',{patterns:[{urlPattern:'*/tests/feedback-fixture-page*'},{urlPattern:'*/admin/feedback-fixture-page*'},{urlPattern:'*/api/*'},{urlPattern:'https://docs.google.com/forms/*'}]});
 await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{
  window.feedbackConsent=true;window.feedbackPrivacyRequests=0;
  window.GNCPrivacy={allows:()=>window.feedbackConsent,manage:()=>window.feedbackPrivacyRequests++};
  let offset=0,hidden=false,focused=true,tick;const clock=performance.now.bind(performance),wall=Date.now.bind(Date),interval=window.setInterval.bind(window);
  Object.defineProperty(performance,'now',{value:()=>clock()+offset});Date.now=()=>wall()+offset;
  Object.defineProperty(document,'hidden',{get:()=>hidden});document.hasFocus=()=>focused;
  window.setInterval=(callback,ms,...args)=>{if(callback.name==='tick'&&ms===1000){tick=callback;return 12345;}return interval(callback,ms,...args);};
  window.feedbackAdvance=ms=>{for(let left=ms;left>0;left-=1000){offset+=Math.min(left,1000);if(tick)tick();}};
  window.feedbackVisible=value=>{hidden=!value;focused=value;document.dispatchEvent(new Event('visibilitychange'));};
 })();`});
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 async function navigate(suffix=''){await send('Page.navigate',{url:base+'/tests/feedback-fixture-page'+suffix});await until(()=>evaluate('document.readyState==="complete"&&Boolean(window.GNCVisitorFeedback)'),'Feedback fixture unavailable');}
 const open=()=>evaluate('document.getElementById("gncVisitorFeedback").open');
 async function advance(ms){await evaluate(`feedbackAdvance(${ms})`);await pause(80);}
 await navigate();await evaluate('localStorage.clear();document.getElementById("before").focus()');
 for(const width of [1440,820,390,320]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});
  assert.equal(await evaluate('(()=>{const b=document.getElementById("gncFeedbackTrigger"),r=b.getBoundingClientRect();return !b.hidden&&getComputedStyle(b).position==="fixed"&&getComputedStyle(b).writingMode==="vertical-rl"&&r.left===0&&r.right<=innerWidth&&r.width>=44&&r.top>=0&&r.bottom<=innerHeight;})()'),true,'Fixed vertical feedback tab fits '+width);
 }
 await evaluate('scrollTo(0,200)');
 assert.equal(await evaluate('Math.round(document.getElementById("gncFeedbackTrigger").getBoundingClientRect().top+document.getElementById("gncFeedbackTrigger").getBoundingClientRect().height/2)===Math.round(innerHeight/2)'),true,'Tab stays centered after scrolling');
 await evaluate('feedbackConsent=false;document.getElementById("gncFeedbackTrigger").click()');
 assert.equal(await evaluate('feedbackPrivacyRequests'),1,'Tab offers existing privacy settings before consent');
 assert.equal(await open(),false,'Consent still required');
 await evaluate('feedbackConsent=true;document.getElementById("gncFeedbackTrigger").focus();document.getElementById("gncFeedbackTrigger").click()');
 await until(open,'Manual feedback did not open immediately');
 await advance(30000);
 await evaluate('document.querySelector(".gnc-feedback-close").click()');await pause(250);
 assert.equal(await evaluate('document.activeElement.id'),'gncFeedbackTrigger','Focus returns to floating tab');
 assert.equal(await evaluate('JSON.parse(localStorage.getItem("gnci_feedback_v1")).until'),0,'Manual dismissal does not suppress automatic popup');
 await advance(28000);assert.equal(await open(),false,'Automatic popup still waits for one minute');
 await advance(3000);await until(open,'Automatic popup still runs after manual dismissal');
 await evaluate('document.querySelector(".gnc-feedback-close").click()');await pause(250);
 await evaluate('document.getElementById("gncFeedbackTrigger").click()');await until(open,'Manual opening bypasses automatic dismissal cooldown');
 await advance(2000);assert.equal(await open(),true,'Automatic cooldown does not close manual feedback');
 await evaluate('document.getElementById("gncFeedbackSubmitted").click()');await pause(250);
 await evaluate('document.getElementById("gncFeedbackTrigger").click()');await until(open,'Manual reopening works after acknowledgment');
 assert.equal(await evaluate('document.getElementById("gncFeedbackTitle").textContent'),'You already submitted','Acknowledged visitor sees submission message');
 assert.equal(await evaluate('document.getElementById("gncFeedbackGoogleForm").hidden&&!document.getElementById("gncFeedbackGoogleForm").hasAttribute("src")&&document.getElementById("gncFeedbackSubmitted").hidden'),true,'Acknowledged visitor cannot fill the form again');
 assert.equal(await evaluate('document.querySelectorAll("#gncVisitorFeedback").length'),1,'Manual and automatic triggers reuse a single modal');
 assert.equal(formLoads,1,'Reopening reuses the loaded Google Form');
 await evaluate('document.querySelector(".gnc-feedback-close").click()');await pause(250);
 await navigate('?page=acknowledged-reload');
 await evaluate('document.getElementById("gncFeedbackTrigger").click()');await until(open,'Acknowledgment survives navigation');
 assert.equal(await evaluate('document.getElementById("gncFeedbackTitle").textContent'),'You already submitted');
 await evaluate('document.querySelector(".gnc-feedback-secondary").click()');await pause(250);
 await evaluate('localStorage.clear()');await navigate('?page=automatic-regression');formLoads=0;
 await evaluate('document.getElementById("before").focus()');
 assert.equal(await evaluate('document.getElementById("gncFeedbackGoogleForm").hasAttribute("src")'),false,'Google iframe is not loaded before popup eligibility');
 await advance(30000);assert.equal(await open(),false);
 await evaluate('feedbackVisible(false)');await advance(90000);assert.equal(await open(),false,'Background time excluded');
 assert((await evaluate('JSON.parse(localStorage.getItem("gnci_feedback_v1")).ms'))<35000);
 await evaluate('feedbackVisible(true)');await navigate('?page=2');
 await advance(28000);assert.equal(await open(),false,'Cross-page timer does not open early');
 await evaluate('const blocker=document.createElement("dialog");blocker.id="blocker";blocker.textContent="Existing dialog";document.body.append(blocker);blocker.showModal()');
 await advance(3000);assert.equal(await open(),false,'Existing dialog blocks feedback');
 await evaluate('document.getElementById("blocker").close();document.getElementById("before").focus();scrollTo({top:240,behavior:"instant"})');
 await advance(1000);await until(open,'Feedback did not open');
 assert.equal(await evaluate('document.activeElement.classList.contains("gnc-feedback-close")'),true,'Focus enters dialog close control');
 assert.equal(await evaluate('document.querySelectorAll("#gncVisitorFeedback textarea,#gncVisitorFeedback input").length'),0,'Website embeds the supplied Google Form directly');
 assert.equal(await evaluate('document.getElementById("gncFeedbackGoogleForm").getAttribute("src")'),'https://docs.google.com/forms/d/e/1FAIpQLSextXO7NUBv8LnF-PArXycrebe1ViNF9YsdQGP23yRRTaYsrQ/viewform?embedded=true');
 await until(()=>formLoads===1,'Google form did not load');
 if(process.env.LIVE_FEEDBACK_FORM==='1')await until(()=>liveFormStatus===200,'Live Google Form document did not load successfully');
 await evaluate('document.getElementById("gncFeedbackGoogleForm").dispatchEvent(new Event("load"))');
 assert.equal(await evaluate('JSON.parse(localStorage.getItem("gnci_feedback_v1")).until'),0,'Iframe load is not treated as proof of submission');
 for(const width of [1440,820,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
  assert.equal(await evaluate('(()=>{const r=document.getElementById("gncVisitorFeedback").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height<=innerHeight;})()'),true,'Modal fits '+width);
 }
 await pause(250);
 if(process.env.LIVE_FEEDBACK_FORM==='1')await pause(1500);
 const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-visitor-feedback-mobile.png'),Buffer.from(shot.data,'base64'));
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
 await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await pause(250);
 assert.equal(await open(),false);assert.equal(await evaluate('document.activeElement.id'),'before','Focus restored');
 let remaining=await evaluate('JSON.parse(localStorage.getItem("gnci_feedback_v1")).until-Date.now()');assert(remaining>6.99*86400000&&remaining<=7*86400000,'Seven-day dismissal');
 await navigate('?page=3');await advance(120000);assert.equal(await open(),false,'Dismissal persists across pages');
 await evaluate('localStorage.setItem("gnci_feedback_v1",JSON.stringify({ms:0,until:1,offeredUntil:0}));localStorage.removeItem("gnci_feedback_prompt_v1")');
 await navigate('?page=expired-dismissal');await advance(61000);await until(open,'Expired dismissal should allow another offer');
 await evaluate('document.querySelector(".gnc-feedback-secondary").click()');await pause(220);
 assert.equal(await open(),false,'Not Now dismisses');
 assert((await evaluate('JSON.parse(localStorage.getItem("gnci_feedback_v1")).until-Date.now()'))>6.99*86400000);
 await evaluate('localStorage.clear()');await navigate('?page=cross-tab-lease');
 await evaluate('localStorage.setItem("gnci_feedback_prompt_v1",JSON.stringify({owner:"other-tab",until:Date.now()+120000}))');
 await advance(61000);assert.equal(await open(),false,'Another tab owns the prompt lease');
 await evaluate('localStorage.removeItem("gnci_feedback_prompt_v1")');await advance(1000);await until(open,'Released lease should allow offer');
 await evaluate('document.querySelector(".gnc-feedback-close").click()');await pause(220);
 assert.equal(await open(),false,'X dismisses');
 await evaluate('localStorage.clear()');await navigate('?page=4');await advance(61000);await until(open,'Acknowledgment fixture not open');
 await evaluate('document.getElementById("gncFeedbackSubmitted").click()');await pause(220);
 assert.equal(await open(),false,'Visitor acknowledgment closes the dialog');
 remaining=await evaluate('JSON.parse(localStorage.getItem("gnci_feedback_v1")).until-Date.now()');assert(remaining>29.99*86400000&&remaining<=30*86400000,'Acknowledgment pauses reminders for thirty days');
 assert.equal(apiCalls,0,'Embedded Google Form does not use the PHP/Apps Script endpoint');
 await navigate('?page=5');await advance(61000);assert.equal(await open(),false,'Successful submission stays suppressed');
 await evaluate('localStorage.setItem("gnci_feedback_v1",JSON.stringify({ms:0,until:1,offeredUntil:0}));localStorage.removeItem("gnci_feedback_prompt_v1")');
 await navigate('?page=expired-submission');await advance(61000);await until(open,'Expired success cooldown should allow another offer');
 await evaluate('localStorage.clear()');
 await send('Page.navigate',{url:base+'/tests/feedback-fixture-page?ce_preview=1'});
 await until(()=>evaluate('document.readyState==="complete"&&location.search.includes("ce_preview")'),'Preview unavailable');
 assert.equal(await evaluate('Boolean(window.GNCVisitorFeedback)'),false,'Visual Editor preview excludes feedback');
 await send('Page.navigate',{url:base+'/admin/feedback-fixture-page'});
 await until(()=>evaluate('document.readyState==="complete"&&location.pathname.startsWith("/admin/")'),'Admin fixture unavailable');
 assert.equal(await evaluate('Boolean(window.GNCVisitorFeedback)'),false,'Admin pages exclude feedback');
 console.log('PASS: direct Google Form embed and lazy loading, active time across pages, background exclusion, dialog deferral, desktop/tablet/mobile fit, focus restoration, 7-day dismissal/30-day acknowledgment, cooldown expiry, cross-tab leasing and admin/editor exclusions. No form responses submitted.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
 if(socket)socket.close();browser.kill();await pause(500);
 if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-feedback-browser-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:150});}catch{}
});
