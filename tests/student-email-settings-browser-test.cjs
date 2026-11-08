const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket,auth,mirrorAuth;
const session=args=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
async function until(fn, message) {
    for (let end=Date.now()+15000; Date.now()<end;) { if (await fn()) return; await delay(50); }
    throw Error(message);
}
(async () => {
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open',resolve,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message', event => { const data=JSON.parse(event.data); if (pending.has(data.id)) { pending.get(data.id)(data); pending.delete(data.id); } });
    const send = (method,params={},sessionId) => new Promise((resolve,reject) => {
        const id=++serial; pending.set(id,data => data.error ? reject(Error(JSON.stringify(data.error))) : resolve(data.result));
        socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));
    });
    const evaluate = async expression => {
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };





    await send('Page.enable');await send('Network.enable');
    const base='http://localhost:8080';
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/settings'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.getElementById('student_email_url')"),'Student email setting unavailable');
    const form="document.getElementById('student_email_url').form";
    assert.equal(await evaluate(form+".dataset.autoSave"),'0','URL uses explicit save');
    assert.equal(await evaluate(form+".querySelector('button[type=submit]').disabled"),true);
    let calls=0,rejectSave=false;
    await send('Fetch.enable',{patterns:[{urlPattern:'*settings.php*',requestStage:'Request'}]});
    socket.addEventListener('message',async event=>{
        const data=JSON.parse(event.data);if(data.method!=='Fetch.requestPaused')return;
        const {requestId,request}=data.params;
        if(request.method!=='POST'){await send('Fetch.continueRequest',{requestId});return;}
        const body=new URLSearchParams(request.postData||'');
        assert.equal(body.get('action'),'save_student_email_url');assert.ok(body.get('csrf_token'));
        calls++;
        const result=rejectSave?{success:false,error:'The student email URL could not be saved.'}:{success:true,value:body.get('student_email_url').trim(),message:'Student email link saved.'};
        await send('Fetch.fulfillRequest',{requestId,responseCode:rejectSave?422:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(result)).toString('base64')});
    });
    await evaluate("(()=>{const el=document.getElementById('student_email_url');el.value='https://mail.example.edu/login';el.dispatchEvent(new Event('input',{bubbles:true}));})()");
    assert.equal(calls,0,'Typing never publishes a URL automatically');
    assert.equal(await evaluate(form+".querySelector('button[type=submit]').disabled"),false);
    await evaluate(form+".requestSubmit()");
    await until(()=>evaluate(form+".dataset.savedValue==='https://mail.example.edu/login'"),'Save confirmation did not update the baseline');
    assert.equal(calls,1);
    rejectSave=true;
    await evaluate("(()=>{const el=document.getElementById('student_email_url');el.value='https://mail.example.edu/new';el.dispatchEvent(new Event('input',{bubbles:true}));el.form.requestSubmit();})()");
    await until(()=>evaluate(form+".querySelector('[data-settings-feedback]').textContent.includes('could not be saved')"),'Save error not shown');
    assert.equal(await evaluate("document.getElementById('student_email_url').value"),'https://mail.example.edu/new','Failed save keeps the entered URL');
    // Restore the in-browser saved baseline before leaving; no real settings were written.
    await evaluate("(()=>{const el=document.getElementById('student_email_url');el.value=el.form.dataset.savedValue;el.dispatchEvent(new Event('input',{bubbles:true}));})()");
    fs.mkdirSync(path.join(__dirname,'../cache/student-email'),{recursive:true});
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await evaluate("document.getElementById('student-email').scrollIntoView({block:'center',behavior:'instant'})");await delay(200);
        const fits=await evaluate("(()=>{const r=document.getElementById('student_email_url').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth})()");assert.equal(fits,true);
        const unobstructed=await evaluate(`(()=>{const a=document.getElementById('student_email_url').form.querySelector('button[type=submit]').getBoundingClientRect(),b=document.getElementById('gncPwaInstall')?.getBoundingClientRect();return !b||a.right<=b.left||a.left>=b.right||a.bottom<=b.top||a.top>=b.bottom})()`);assert.equal(unobstructed,true,'Install button does not cover Save');
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(__dirname,'../cache/student-email/settings-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }
    await send('Fetch.disable');
    mirrorAuth=JSON.parse(session(['Content Editor']));await send('Network.setCookie',{...mirrorAuth,url:base,httpOnly:true});
    await send('Page.navigate',{url:base+'/admin/settings'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.getElementById('student_email_url')"),'Content Editor settings unavailable');
    assert.equal(await evaluate(form+".dataset.directSave"),'0');
    assert.equal(await evaluate(form+".querySelector('button[type=submit]').textContent"),'Submit for approval');
    console.log('PASS: student email field, explicit AJAX save, CSRF inclusion, failure retention, responsive layout, and Content Editor approval UI. No real settings changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(mirrorAuth)session(['destroy',mirrorAuth.value]);if(socket)socket.close();browser.kill();});
