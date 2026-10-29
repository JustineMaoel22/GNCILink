const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const markup=execFileSync('php',['tests/visitor-feedback-fixture.php'],{encoding:'utf8',windowsHide:true});
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-feedback-browser-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,label){for(let end=Date.now()+20000;Date.now()<end;){if(await work())return;await pause(50);}throw Error(label);}
let socket,send;
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
 const questions=Array.from({length:6},(_,i)=>({id:String(101+i),title:'Existing rating question '+(i+1),section:i<2?'Effectiveness':i<4?'Efficiency':'Satisfaction'}));
 let saved=false,statusCalls=0,submitCalls=0,saveCount=0,failAfterSave=false,unavailable=false;
 await send('Page.enable');await send('Network.enable');
 socket.addEventListener('message',async event=>{
  const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;
  const {request,requestId}=packet.params;
  let value,status=200;
  if(new URL(request.url).pathname==='/api/visitor-feedback.php'){
   const input=JSON.parse(request.postData);
   if(unavailable){status=503;value={ok:false,error:'Feedback verification is unavailable. Please try again later.'};}
   else if(input.action==='status'){statusCalls++;value={ok:true,submitted:saved,questions,csrf:'test-csrf'};}
   else{
    submitCalls++;
    if(input.csrf!=='test-csrf'||Object.keys(input.answers||{}).length!==6||questions.some(q=>!Number.isInteger(input.answers[q.id])||input.answers[q.id]<1||input.answers[q.id]>5)){
     status=422;value={ok:false,error:'Please answer all six questions.'};
    }else{
     if(!saved){saved=true;saveCount++;}
     if(failAfterSave){status=503;value={ok:false,error:'Connection interrupted. Check your response.'};}
     else value={ok:true,submitted:true,csrf:'test-csrf'};
    }
   }
   await send('Fetch.fulfillRequest',{requestId,responseCode:status,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(value)).toString('base64')});
  }else{
   await send('Fetch.fulfillRequest',{requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'text/html; charset=utf-8'}],body:Buffer.from('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><button id="before">Before popup</button><main style="height:1200px">Feedback test</main>'+markup).toString('base64')});
  }
 });
 await send('Fetch.enable',{patterns:[{urlPattern:'*/tests/feedback-fixture-page*'},{urlPattern:'*/admin/feedback-fixture-page*'},{urlPattern:'*/api/visitor-feedback.php'}]});
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
 async function navigate(suffix=''){await send('Page.navigate',{url:base+'/tests/feedback-fixture-page'+suffix});await until(()=>evaluate('document.readyState==="complete"&&!!window.GNCVisitorFeedback'),'Feedback fixture unavailable');}
 const open=()=>evaluate('document.getElementById("gncVisitorFeedback").open');
 const ready=()=>evaluate('!document.getElementById("gncFeedbackSubmitted").disabled');
 async function manual(){await evaluate('document.getElementById("gncFeedbackTrigger").focus();document.getElementById("gncFeedbackTrigger").click()');await until(open,'Popup did not open');await until(ready,'Verification did not finish');}
 async function close(){await evaluate('document.querySelector(".gnc-feedback-close").click()');await until(async()=>!await open(),'Popup did not close');}
 async function fill(count=6){await evaluate(`document.querySelectorAll('#gncFeedbackQuestions fieldset').forEach((f,i)=>{if(i<${count})f.querySelector('input[value="4"]').checked=true;})`);}
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await navigate();await evaluate('localStorage.clear()');
 assert.equal(statusCalls,0,'No identity/API request before opening');
 await evaluate('feedbackConsent=false;document.getElementById("gncFeedbackTrigger").click()');
 assert.equal(await open(),false);assert.equal(statusCalls,0);assert.equal(await evaluate('feedbackPrivacyRequests'),1);
 await evaluate('feedbackConsent=true');await manual();
 assert.equal(await evaluate('document.querySelectorAll("#gncFeedbackQuestions fieldset").length'),6);
 assert.equal(await evaluate('document.querySelectorAll("#gncFeedbackQuestions input[required]").length'),30);
 await evaluate('document.getElementById("gncFeedbackSubmitted").click()');await until(ready,'Check did not finish');
 assert.equal(await evaluate('document.getElementById("gncFeedbackTitle").textContent'),'Rate Your Experience');
 assert.equal(await evaluate('document.getElementById("gncFeedbackStatus").textContent.includes("No completed response")'),true);
 assert.equal(saved,false);assert.equal(saveCount,0);
 for(const count of [0,1,5]){
  await fill(count);await evaluate('document.getElementById("gncFeedbackRatings").requestSubmit()');await pause(70);
  assert.equal(submitCalls,0,'Incomplete ratings never reach submission');
 }
 await evaluate('window.dispatchEvent(new MessageEvent("message",{data:{submitted:true}}));localStorage.setItem("gnci_feedback_v1",JSON.stringify({submitted:true,verified:true,until:Date.now()+86400000}));window.dispatchEvent(new StorageEvent("storage",{key:"gnci_feedback_v1"}))');
 await until(ready,'Storage verification did not finish');
 assert.equal(await evaluate('document.getElementById("gncFeedbackRatings").hidden'),false,'Forged browser state/message does not lock or confirm');
 await fill();failAfterSave=true;
 await evaluate('document.getElementById("gncFeedbackRatings").requestSubmit()');await until(ready,'Submit timeout test did not finish');
 assert.equal(saveCount,1);assert.equal(await evaluate('document.getElementById("gncFeedbackRatings").hidden'),false,'Unconfirmed network failure does not claim success');
 failAfterSave=false;
 await evaluate('document.getElementById("gncFeedbackSubmitted").click()');await until(ready,'Saved receipt was not checked');
 assert.equal(await evaluate('document.getElementById("gncFeedbackTitle").textContent'),'You already submitted');
 assert.equal(await evaluate('document.getElementById("gncFeedbackRatings").hidden'),true);
 await evaluate('document.getElementById("gncFeedbackRatings").dispatchEvent(new Event("submit",{cancelable:true,bubbles:true}))');
 assert.equal(saveCount,1,'Verified browser cannot submit again');
 await close();await evaluate('localStorage.clear()');await navigate('?page=return');await manual();
 assert.equal(await evaluate('document.getElementById("gncFeedbackTitle").textContent'),'You already submitted','Server receipt survives clearing reminder storage/navigation');
 await close();saved=false;await evaluate('localStorage.clear()');await navigate('?page=double-click');await manual();await fill();
 await evaluate('document.getElementById("gncFeedbackRatings").requestSubmit();document.getElementById("gncFeedbackRatings").dispatchEvent(new Event("submit",{cancelable:true}))');
 await until(ready,'Double-click submission did not finish');assert.equal(saveCount,2,'Double click produces one new saved response');
 await close();saved=false;unavailable=true;await evaluate('localStorage.clear()');await navigate('?page=unavailable');await manual();
 assert.equal(await evaluate('document.getElementById("gncFeedbackRatings").hidden'),true,'Unavailable verification never falls back to unverified submission');
 assert.equal(await evaluate('document.getElementById("gncFeedbackStatus").textContent.includes("unavailable")'),true);
 await close();unavailable=false;await evaluate('localStorage.clear()');await navigate('?page=timing');
 await evaluate('feedbackAdvance(30000);feedbackVisible(false);feedbackAdvance(90000)');assert.equal(await open(),false);
 await evaluate('feedbackVisible(true)');await navigate('?page=timing-next');await evaluate('feedbackAdvance(28000)');assert.equal(await open(),false);
 await evaluate('const blocker=document.createElement("dialog");blocker.id="blocker";document.body.append(blocker);blocker.showModal();feedbackAdvance(3000)');assert.equal(await open(),false);
 await evaluate('document.getElementById("blocker").close();document.getElementById("before").focus();feedbackAdvance(1000)');await until(open,'Due feedback did not open');await until(ready,'Due feedback did not load');
 for(const width of [1440,820,390,320]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});
  const fit=await evaluate('(()=>{const d=document.getElementById("gncVisitorFeedback"),r=d.getBoundingClientRect();return {left:r.left,right:r.right,height:r.height,innerWidth,innerHeight,scroll:d.scrollWidth,client:d.clientWidth};})()');
  assert.equal(fit.left>=0&&fit.right<=fit.innerWidth&&fit.height<=fit.innerHeight&&fit.scroll<=fit.client,true,'Popup fits '+width+': '+JSON.stringify(fit));
 }
 await pause(250);
 const shot=await send('Page.captureScreenshot',{format:'jpeg',quality:65});fs.writeFileSync(path.join(os.tmpdir(),'gnci-feedback-verified-mobile.jpg'),Buffer.from(shot.data,'base64'));
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
 await until(async()=>!await open(),'Escape failed');assert.equal(await evaluate('document.activeElement.id'),'before');
 const cooldown=await evaluate('JSON.parse(localStorage.getItem("gnci_feedback_v1")).until-Date.now()');assert(cooldown>6.99*86400000&&cooldown<=7*86400000);
 await navigate('?page=cooldown');await evaluate('feedbackAdvance(120000)');assert.equal(await open(),false);
 await manual();await evaluate('feedbackConsent=false;window.dispatchEvent(new Event("gnc:privacychange"))');await until(async()=>!await open(),'Privacy revocation did not close feedback');
 assert.equal(await evaluate('document.querySelectorAll("#gncFeedbackQuestions input").length'),0,'Privacy revocation clears answers');
 await send('Page.navigate',{url:base+'/tests/feedback-fixture-page?ce_preview=1'});await until(()=>evaluate('document.readyState==="complete"&&location.search.includes("ce_preview")'),'Preview unavailable');assert.equal(await evaluate('!!window.GNCVisitorFeedback'),false);
 await send('Page.navigate',{url:base+'/admin/feedback-fixture-page'});await until(()=>evaluate('document.readyState==="complete"&&location.pathname.startsWith("/admin/")'),'Admin unavailable');assert.equal(await evaluate('!!window.GNCVisitorFeedback'),false);
 console.log('PASS: required ratings, verified button, no false completion, uncertain-save recovery, duplicate prevention, reload/server lock, unavailable service, consent, timing, focus, responsive layout and admin exclusions (verification API mocked; no Google responses sent).');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
 if(socket)socket.close();browser.kill();await pause(500);
 if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-feedback-browser-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:150});}catch{}
});
