const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
function fixture(args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/editor-presence-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;}
const sessions=[],sockets=[];
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-presence-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
async function connection(url){
    const socket=new WebSocket(url);sockets.push(socket);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();socket.addEventListener('message',event=>{const data=JSON.parse(event.data);if(pending.has(data.id)){pending.get(data.id)(data);pending.delete(data.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,data=>data.error?reject(Error(JSON.stringify(data.error))):resolve(data.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    return {send,evaluate};
}
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome not ready');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const info=await(await fetch(`http://127.0.0.1:${port}/json/version`)).json(),root=await connection(info.webSocketDebuggerUrl);
    async function client(role){
        const session=JSON.parse(fixture([role]));sessions.push(session);
        const context=await root.send('Target.createBrowserContext');
        const target=await root.send('Target.createTarget',{url:'about:blank',browserContextId:context.browserContextId});
        const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        const c=await connection(tabs.find(t=>t.id===target.targetId).webSocketDebuggerUrl);
        await c.send('Page.enable');await c.send('Network.enable');await c.send('Network.setCookie',{name:session.name,value:session.value,url:'http://localhost:8080',httpOnly:true});
        c.visit=async page=>{await c.send('Page.navigate',{url:'http://localhost:8080/admin/content-edit.php?page='+page});await until(()=>c.evaluate('Boolean(window.VE?.presence()?.token()) && !VE.state.busy && document.getElementById("veLoader").hidden'),'Editor ready: '+page);};
        c.api=(action,extra={})=>c.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/action/editor-presence-handler.php',{method:'POST',body:new URLSearchParams(Object.assign({csrf_token:VE_CONFIG.csrf,token:VE.presence().token(),action:${JSON.stringify(action)}},${JSON.stringify(extra)}))});return {status:response.status,data:await response.json()};}finally{frame.remove();}})()`);
        c.session=session;return c;
    }
    const a=await client('Super Admin'),b=await client('Content Editor');
    await a.visit('home');await b.visit('home');
    await until(async()=>{await a.api('ping');return (await a.evaluate('document.querySelectorAll("#vePresenceList li").length'))>=2;},'Both viewers appear');
    await a.evaluate(`window.__field=Array.from(VE.state.doc.querySelectorAll('[contenteditable]')).find(e=>e.isContentEditable&&e.__ve);__field.textContent='Presence pending text';__field.dispatchEvent(new Event('input',{bubbles:true}));`);
    await a.evaluate('VE.presence().sync()');
    await until(()=>b.evaluate('document.getElementById("vePresenceWarning").textContent.includes("editing this page")'),'Other editor warning');
    const active=(await b.api('ping')).data.users;
    assert.ok(active.some(u=>u.id===a.session.user_id&&u.activity==='editing'));
    await b.evaluate('VE.presence().sync()');
    const expectedPhotos=active.filter(u=>u.avatar).length;
    await until(()=>b.evaluate('document.querySelectorAll("#vePresenceList .ve-presence-avatar img").length==='+expectedPhotos),'Saved profile pictures appear in the presence list');
    for(const width of [1440,768,390]){
        await b.send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await b.evaluate(`document.getElementById('vePresenceButton').click()`);await delay(100);
        assert.equal(await b.evaluate(`(()=>{const r=document.getElementById('vePresencePanel').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height>0;})()`),true,'Presence panel fits '+width);
        fs.writeFileSync(path.join(__dirname,'../cache/editor-presence-'+width+'.png'),Buffer.from((await b.send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await b.evaluate(`document.getElementById('vePresenceButton').click()`);
    }
    await b.visit('history');
    await until(async()=>!(await a.api('ping')).data.users.some(u=>u.id===b.session.user_id),'Page switch removes user');
    // Returning gets a fresh preview/baseline; pending changes are not saved by presence.
    await b.visit('home');
    const deny=await a.api('ping',{token:await b.evaluate('VE.presence().token()')});assert.equal(deny.status,409,'Foreign session token rejected');
    const csrf=await a.api('ping',{csrf_token:'invalid'});assert.equal(csrf.status,403,'Missing CSRF rejected');
    const before=await a.evaluate(`__field.textContent`);
    // Invalid JSON is rejected by the existing handler before content writes. It reserves a
    // revision and publishing lease, allowing a real conflict test without changing live content.
    const reserved=await a.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/action/page-content-handler.php',{method:'POST',headers:{'X-GNCI-Editor-Peer':VE.presence().token(),'X-GNCI-Editor-Batch':'1','X-GNCI-Publish-Token':${JSON.stringify(a.session.publish_token)}},body:new URLSearchParams({csrf_token:VE_CONFIG.csrf,action:'save_home_content',texts:'{',reset_images:'[]'})});return {status:response.status,data:await response.json()};}finally{frame.remove();}})()`);
    assert.ok(reserved.status>=400||reserved.data.success===false||reserved.data.error,'Invalid payload did not publish');
    const conflict=await b.api('check');assert.equal(conflict.status,409,'Stale editor cannot save');
    const blockedSave=await b.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/action/editor-draft-handler.php',{method:'POST',headers:{'X-GNCI-Editor-Peer':VE.presence().token()},body:new URLSearchParams({csrf_token:VE_CONFIG.csrf,action:'save',page:'home',route:'/',revision:'0',snapshot:'{}'})});return response.status;}finally{frame.remove();}})()`);
    assert.equal(blockedSave,409,'Actual draft handler rejects stale saves before writing');
    const recoveryRoute='/?presence_test='+Date.now();
    const checkpoint=await b.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);window.__recoveryRoute=${JSON.stringify(recoveryRoute)};window.__draftAPI=async(action,extra={})=>{const response=await frame.contentWindow.fetch('/admin/action/editor-draft-handler.php',{method:'POST',headers:{'X-GNCI-Editor-Peer':VE.presence().token()},body:new URLSearchParams(Object.assign({csrf_token:VE_CONFIG.csrf,action,page:'home',route:__recoveryRoute,revision:'0'},extra))});return {status:response.status,data:await response.json()};};window.__recoveryFrame=frame;return __draftAPI('save_recovery',{snapshot:JSON.stringify({version:1,state:{},preview:{}})});})()`);
    assert.equal(checkpoint.status,200,'A stale editor can explicitly keep a private recovery checkpoint');
    const restore=await b.evaluate(`__draftAPI('load')`);
    assert.ok(restore.data.draft.snapshot.content_version,'Recovery checkpoints preserve their original concurrency baseline');
    assert.equal((await b.api('check')).status,409,'A recovery checkpoint cannot bypass stale-write protection');
    await b.evaluate(`__draftAPI('delete',{revision:${checkpoint.data.revision}})`);
    await b.evaluate(`__recoveryFrame.remove()`);
    await until(()=>b.evaluate('document.getElementById("vePresenceWarning").classList.contains("is-conflict")'),'Stale warning visible');
    assert.equal(await a.evaluate('__field.textContent'),before,'Pending changes preserved');
    // Even a freshly joined preview must wait for the publishing lease.
    await b.visit('home');assert.equal((await b.api('check')).status,409,'Publishing batch excludes competing writes');
    await a.api('release');assert.equal((await b.api('check')).status,200,'Publishing lease releases');
    const c=await client('Super Admin');await c.visit('home');
    const invalidWrite=c=>c.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/action/page-content-handler.php',{method:'POST',headers:{'X-GNCI-Editor-Peer':VE.presence().token(),'X-GNCI-Publish-Token':${JSON.stringify(c.session.publish_token)}},body:new URLSearchParams({csrf_token:VE_CONFIG.csrf,action:'save_home_content',texts:'{',reset_images:'[]'})});return response.status;}finally{frame.remove();}})()`);
    const concurrent=await Promise.all([invalidWrite(a),invalidWrite(c)]);
    assert.deepEqual(concurrent.sort(),[409,422],'Concurrent writes serialize and only one passes the revision gate');
    const anonymous=await a.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{return (await frame.contentWindow.fetch('/admin/action/editor-presence-handler.php',{method:'POST',credentials:'omit',body:new URLSearchParams({action:'join',page:'home',route:'/'})})).status;}finally{frame.remove();}})()`);
    assert.equal(anonymous,401,'Anonymous presence requests rejected');
    const leave=await b.api('leave');assert.equal(leave.status,200);
    assert.ok(!(await a.api('ping')).data.users.some(u=>u.id===b.session.user_id),'Leave removes user');
    console.log('Two-account presence, activity, page switch, responsive UI, session/CSRF checks, stale-write rejection and batch lease passed. No live content saved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{
    sockets.forEach(s=>s.close());browser.kill();for(const s of sessions)fixture(['destroy',s.value]);
});
