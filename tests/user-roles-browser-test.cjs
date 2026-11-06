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
    await visit('/admin/users.php');
    await until(()=>evaluate('Boolean(document.getElementById("role-user-dialog"))'),'Users page not ready');
    await evaluate(`window.__roleRow=Array.from(document.querySelectorAll('[data-saved-role]')).find(form=>form.dataset.savedRole!=='Super Admin');window.__oldRole=__roleRow.dataset.savedRole;window.__newRole=__oldRole==='The Mirror'?'Content Editor':'The Mirror';`);
    assert.equal(await evaluate('__roleRow.querySelector("button").disabled'),true,'Unchanged role cannot be saved');
    await evaluate(`const select=__roleRow.querySelector('[name=role]');select.value=__newRole;select.dispatchEvent(new Event('change'));__roleRow.querySelector('button').click();`);
    assert.equal(await evaluate('document.getElementById("role-user-dialog").open'),true,'Saving opens password confirmation');
    assert.equal(await evaluate('document.activeElement.id'),'role-user-password','Password receives focus');
    assert.equal(await evaluate('document.getElementById("role-user-selected").value'),await evaluate('__newRole'),'Correct selected role in dialog');
    assert.equal(await evaluate('document.getElementById("role-user-id").value'),await evaluate('__roleRow.querySelector("[name=user_id]").value'),'Correct account in dialog');
    await evaluate(`window.__roleSubmits=0;document.getElementById('role-user-form').addEventListener('submit',event=>{event.preventDefault();window.__roleSubmits++;window.__roleFields=Object.fromEntries(new FormData(event.target));});document.getElementById('role-user-form').requestSubmit();`);
    assert.equal(await evaluate('__roleSubmits'),0,'Empty password cannot submit');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        assert.equal(await evaluate('(()=>{const r=document.getElementById("role-user-dialog").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;})()'),true,'Dialog fits '+width);
        fs.writeFileSync('cache/user-role-password-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await evaluate(`document.getElementById('role-user-password').value='test-only-role-password';document.getElementById('role-user-form').requestSubmit();`);
    assert.equal(await evaluate('__roleSubmits'),1,'Password allows form submission');
    assert.equal(await evaluate('__roleFields.current_password'),'test-only-role-password','Submission includes the confirmation password');
    await evaluate(`document.getElementById('cancel-role-user').click()`);
    await until(()=>evaluate('document.getElementById("role-user-password").value===""'),'Dialog close did not clear password');
    assert.equal(await evaluate('document.getElementById("role-user-password").value'),'','Cancel clears password');
    assert.equal(await evaluate('document.getElementById("role-user-id").value'),'','Cancel clears selected account');
    assert.equal(await evaluate('__roleRow.querySelector("[name=role]").value'),await evaluate('__newRole'),'Cancel retains pending selection');
    await evaluate(`__roleRow.querySelector('button').click();document.getElementById('role-user-dialog').close();`);
    assert.equal(await evaluate('document.getElementById("role-user-password").value'),'','Reopened confirmation starts empty');
    // Direct POST without JavaScript must still be refused. No real role is changed.
    const missing=await evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/users.php',{method:'POST',body:new URLSearchParams({csrf_token:__roleRow.querySelector('[name=csrf_token]').value,action:'role',user_id:__roleRow.querySelector('[name=user_id]').value,role:__newRole})});return await response.text();}finally{frame.remove();}})()`);
    assert.ok(missing.includes('Enter your current Super Admin password to save this role change.'),'Server rejects a missing password');
    const invalid=await evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/users.php',{method:'POST',body:new URLSearchParams({csrf_token:__roleRow.querySelector('[name=csrf_token]').value,action:'role',user_id:__roleRow.querySelector('[name=user_id]').value,role:__newRole,current_password:'test-only-deliberately-invalid'})});return await response.text();}finally{frame.remove();}})()`);
    assert.ok(invalid.includes('Your current password is incorrect. The role was not changed.'),'Server rejects an incorrect password');
    assert.ok(!invalid.includes('test-only-deliberately-invalid'),'Password is never echoed in the response');
    console.log('Password dialog, required field, cancel/reset, desktop/mobile layout and direct-POST rejection passed. No real roles changed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});
