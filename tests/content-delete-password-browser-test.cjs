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
    for(const config of [
        {page:'/admin/news.php',modal:'deleteNewsModal',password:'delete-news-password',form:'delete-news-form',endpoint:'/admin/action/create-news.php',id:'news_id'},
        {page:'/admin/announcement.php',modal:'deleteAnnouncementModal',password:'delete-announcement-password',form:'delete-announcement-form',endpoint:'/admin/action/create-announcement.php',id:'announcement_id'},
        {page:'/admin/events.php',modal:'deleteEventModal',password:'delete-event-password',endpoint:'/admin/events.php',id:'event_id'}
    ]){
        await visit(config.page);
        await until(()=>evaluate('Boolean(window.bootstrap && document.getElementById('+JSON.stringify(config.modal)+'))'),'Delete dialog missing: '+config.page);
        await evaluate(`bootstrap.Modal.getOrCreateInstance(document.getElementById(${JSON.stringify(config.modal)})).show()`);
        await until(()=>evaluate('document.activeElement.id==='+JSON.stringify(config.password)),'Password focus: '+config.page);
        assert.equal(await evaluate(`document.getElementById(${JSON.stringify(config.password)}).checkValidity()`),false,'Password is required');
        if(config.form){
            await evaluate(`window.__submits=0;document.getElementById(${JSON.stringify(config.form)}).addEventListener('submit',event=>{event.preventDefault();__submits++;window.__fields=Object.fromEntries(new FormData(event.target));});document.getElementById(${JSON.stringify(config.form)}).requestSubmit();`);
            assert.equal(await evaluate('__submits'),0,'Empty password cannot submit');
        }
        for(const width of [1440,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});await delay(100);
            assert.equal(await evaluate(`(()=>{const r=document.querySelector('#${config.modal} .modal-dialog').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;})()`),true,'Delete dialog fits '+width);
            fs.writeFileSync('cache/'+config.modal+'-password-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        }
        await evaluate(`document.getElementById(${JSON.stringify(config.password)}).value='test-only-confirmation';`);
        if(config.form){
            await evaluate(`document.getElementById(${JSON.stringify(config.form)}).requestSubmit()`);
            assert.equal(await evaluate('__fields.current_password'),'test-only-confirmation','Confirmation password included in form');
        }
        await evaluate(`bootstrap.Modal.getInstance(document.getElementById(${JSON.stringify(config.modal)})).hide()`);
        await until(()=>evaluate(`document.getElementById(${JSON.stringify(config.password)}).value===''`),'Cancel did not clear password');
        if(!config.form&&await evaluate('Boolean(document.querySelector(".evt-delete-btn"))')){
            await evaluate(`window.__originalDeleteFetch=window.fetch;window.fetch=function(input,init){if(init?.body?.get('ajax_action')==='delete'){window.__eventDeleteFields=Object.fromEntries(init.body);return Promise.resolve(new Response(JSON.stringify({success:false,message:'Test intercepted; no deletion performed.'}),{headers:{'Content-Type':'application/json'}}));}return __originalDeleteFetch(input,init);};document.querySelector('.evt-delete-btn').click();`);
            await until(()=>evaluate('document.activeElement.id==="delete-event-password"'),'Event password focus');
            await evaluate(`document.getElementById('delete-event-password').value='test-only-confirmation';document.getElementById('deleteEventConfirmBtn').click();`);
            await until(()=>evaluate('Boolean(window.__eventDeleteFields)'),'Event deletion payload not sent');
            assert.equal(await evaluate('__eventDeleteFields.current_password'),'test-only-confirmation','Event AJAX includes the password');
            assert.equal(await evaluate('document.getElementById("delete-event-password").value'),'','Event password cleared after sending');
            await evaluate(`window.fetch=__originalDeleteFetch;bootstrap.Modal.getInstance(document.getElementById('deleteEventModal')).hide()`);
            await until(()=>evaluate('!document.getElementById("deleteEventModal").classList.contains("show")'),'Event dialog close');
        }
        const result=await evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const token=${config.form?`document.querySelector('#${config.form} [name=csrf_token]').value`:`document.getElementById('csrfToken').value`};const response=await frame.contentWindow.fetch(${JSON.stringify(config.endpoint)},{method:'POST',body:new URLSearchParams({csrf_token:token,${config.form?'action':'ajax_action'}:'delete',${config.id}:'2147483647'})});return {status:response.status,text:await response.text()};}finally{frame.remove();}})()`);
        assert.ok(result.text.includes('Enter your current password to confirm deletion.'),'Server rejects missing password: '+config.page);
        console.log('Required deletion password and cancel/submit checks:',config.page);
    }
    console.log('All deletion dialogs and direct-POST checks passed. No real content deleted.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});
