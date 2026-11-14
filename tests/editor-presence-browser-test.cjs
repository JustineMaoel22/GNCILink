const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
function fixture(args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/editor-presence-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;}
const sessions=[],sockets=[];
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-presence-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+60000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
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
    async function client(role,existing){
        const session=existing||JSON.parse(fixture([role]));if(!existing)sessions.push(session);
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
    await until(async()=>!(await a.api('ping')).data.reservation,'Previous test reservation did not expire');
    assert.equal((await a.api('ping')).data.reservation,null,'Viewing never reserves content');
    assert.equal(await a.evaluate('VE.state.preview'),true,'Initial controls are read-only');
    await a.evaluate('VE.presence().acquire(false)');
    assert.equal((await b.api('acquire')).status,409,'Another account cannot acquire the page');
    const bypass=await b.evaluate(`(async()=>{const f=document.createElement('iframe');document.body.appendChild(f);try{return (await f.contentWindow.fetch('/admin/action/page-content-handler.php',{method:'POST',body:new URLSearchParams({csrf_token:VE_CONFIG.csrf,action:'save_home_content',texts:'{'})})).status;}finally{f.remove();}})()`);
    assert.equal(bypass,409,'Omitting the peer token cannot bypass reservations through proposal staging');
    await b.evaluate('VE.presence().sync()');
    await until(()=>b.evaluate('document.getElementById("vePresenceMessage").textContent.includes("Currently being edited by")'),'Reservation owner shown');
    const c=await client('Super Admin',a.session);await c.visit('home');
    assert.equal(c.session.user_id,a.session.user_id,'Same account in a second browser session');
    assert.equal((await c.api('acquire')).status,409,'Same account cannot bypass the reservation');
    const d=await client('Content Editor');await d.visit('history');
    assert.equal((await d.api('acquire')).status,200,'Independent content can be edited simultaneously');
    assert.equal((await d.api('takeover',{confirmed:'1'})).status,403,'Content Editor cannot take over');
    const route='/?reservation_test='+Date.now();
    const draft=(client,action,extra={})=>client.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/action/editor-draft-handler.php',{method:'POST',headers:{'X-GNCI-Editor-Peer':VE.presence().token()},body:new URLSearchParams(Object.assign({csrf_token:VE_CONFIG.csrf,action:${JSON.stringify(action)},page:'home',route:${JSON.stringify(route)},revision:'0'},${JSON.stringify(extra)}))});return {status:response.status,data:await response.json()};}finally{frame.remove();}})()`);
    const snapshot=JSON.stringify({version:1,state:{heading:'Private pending takeover work'},preview:{fields:[]}});
    const saved=await draft(a,'save',{snapshot});assert.equal(saved.status,200,'Active owner saves a private draft');
    assert.equal((await c.api('takeover')).status,400,'Takeover requires explicit confirmation');
    assert.equal((await c.api('takeover',{confirmed:'1'})).status,200,'Super Admin takeover succeeds');
    assert.equal((await a.api('check')).status,409,'Previous reservation cannot write');
    assert.equal((await draft(a,'save',{revision:saved.data.revision,snapshot})).status,409,'Backend rejects revoked draft writes');
    const kept=await draft(a,'load');assert.equal(kept.data.draft.snapshot.state.heading,'Private pending takeover work','Takeover preserves previous draft');
    await a.evaluate('VE.presence().sync()');await until(()=>a.evaluate('VE.state.preview'),'Previous editor becomes read-only');
    const deny=await a.api('ping',{token:await b.evaluate('VE.presence().token()')});assert.equal(deny.status,409,'Foreign-session token rejected');
    assert.equal((await a.api('ping',{csrf_token:'invalid'})).status,403,'CSRF required');
    fixture(['expire',c.session.value]);
    assert.equal((await c.api('ping')).data.reservation,null,'Heartbeat cannot renew an expired reservation');
    assert.equal((await c.api('check')).status,409,'Expired tab cannot write on reconnection');
    await c.evaluate('VE.presence().acquire(false)');
    await c.send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await until(()=>c.evaluate('VE.state.preview'),'Disconnected editor becomes read-only');
    await c.send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await until(()=>c.evaluate('!VE.state.preview'),'Reconnection verifies still-current reservation');
    // Invalid write advances only conflict metadata; no published content is changed.
    const invalid=await c.evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const response=await frame.contentWindow.fetch('/admin/action/page-content-handler.php',{method:'POST',headers:{'X-GNCI-Editor-Peer':VE.presence().token(),'X-GNCI-Publish-Token':${JSON.stringify(c.session.publish_token)}},body:new URLSearchParams({csrf_token:VE_CONFIG.csrf,action:'save_home_content',texts:'{',reset_images:'[]'})});return response.status;}finally{frame.remove();}})()`);
    assert.equal(invalid,422,'Invalid write does not publish');
    const old=await draft(a,'load');assert.equal(old.data.draft.outdated,true,'Old draft is identified');
    await a.evaluate(`(async()=>{var state={};window.__offers=GNCEditorDrafts.create({page:'home',adapter:()=>({draftState:function(value){if(value)state=value;return state;}}),document:()=>VE.state.doc,positions:()=>({}),setPositions:()=>{},clean:VE.cleanHtml,pending:()=>0,notify:()=>{},refresh:()=>{},presence:()=>VE.presence(),send:async fields=>{var f=document.createElement('iframe');document.body.appendChild(f);try{var r=await f.contentWindow.fetch('/admin/action/editor-draft-handler.php',{method:'POST',headers:{'X-GNCI-Editor-Peer':VE.presence().token()},body:new URLSearchParams(Object.assign({csrf_token:VE_CONFIG.csrf},fields))});var d=await r.json();return Object.assign({ok:r.ok},d);}finally{f.remove();}}});await __offers.load(${JSON.stringify(route)});})()`);
    assert.equal(await a.evaluate('document.querySelector("dialog h3").textContent'),'Saved draft uses an older page version','Outdated draft offered explicitly');
    await a.evaluate(`Array.from(document.querySelectorAll('dialog button')).find(b=>b.textContent==='Keep draft and view current').click()`);
    assert.equal(await a.evaluate(`__offers.save().then(()=>false,()=>true)`),true,'Preserved draft cannot be silently replaced from current view');
    assert.equal((await draft(a,'load')).data.draft.revision,saved.data.revision,'Viewing current leaves saved work untouched');
    await c.api('release');
    await a.visit('home');await a.api('acquire');
    assert.equal((await a.api('check')).status,200,'Fresh preview has current baseline');
    await draft(a,'load');assert.equal((await a.api('check')).status,200,'Viewing a saved draft never changes baseline');
    assert.equal((await a.api('resume_draft',{page:'home',route,revision:saved.data.revision})).status,200,'Explicit resume permitted');
    assert.equal((await a.api('check')).status,409,'Resumed old baseline cannot overwrite published changes');
    assert.equal((await a.api('reconcile_draft',{page:'home',route,revision:saved.data.revision})).status,400,'Reconciliation requires confirmation');
    const current=(await a.api('ping')).data;
    const reconciled=await a.api('reconcile_draft',{page:'home',route,revision:saved.data.revision,confirmed:'1',current_revision:current.revision,current_external:current.external});
    assert.equal(reconciled.status,200,'Reviewed reconciliation uses the current published version');
    assert.equal((await a.api('check')).status,200,'Reconciled draft may save');
    await draft(a,'delete',{revision:reconciled.data.draft_revision});
    await a.api('release');assert.equal((await b.api('acquire')).status,200,'Released reservation can be acquired');
    await b.visit('history');assert.equal((await a.api('ping')).data.reservation,null,'Page switching releases reservation');
    await b.visit('home');
    const race=await Promise.all([a.api('acquire'),b.api('acquire')]);
    assert.deepEqual(race.map(result=>result.status).sort(),[200,409],'Concurrent acquisition grants exactly one reservation');
    await (race[0].status===200?a:b).api('release');
    for(const width of [1440,768,390]){
        await b.send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await b.evaluate(`document.getElementById('vePresenceButton').click()`);await delay(100);
        assert.equal(await b.evaluate(`(()=>{const r=document.getElementById('vePresencePanel').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height>0;})()`),true,'Presence panel fits '+width);
        fs.writeFileSync(path.join(__dirname,'../cache/editor-presence-'+width+'.png'),Buffer.from((await b.send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        await b.evaluate(`document.getElementById('vePresenceButton').click()`);
    }
    console.log('Reservation browser checks passed: view-only entry, accounts/tabs, independent pages, takeover fencing, draft preservation, explicit resume/reconciliation, CSRF, switching, responsive panels. No published content saved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{
    sockets.forEach(s=>s.close());browser.kill();for(const s of sessions)fixture(['destroy',s.value]);
});
