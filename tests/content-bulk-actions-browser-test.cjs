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
    for(const page of ['/admin/news.php','/admin/announcement.php','/admin/events.php']){
        await visit(page);await until(()=>evaluate('Boolean(document.querySelector("[data-bulk-actions]"))'),'Bulk actions missing: '+page);
        await evaluate(`window.__bar=document.querySelector('[data-bulk-actions]');window.__table=document.getElementById(__bar.dataset.table);window.__form=document.querySelector('[data-bulk-form]');window.__dialog=document.querySelector('[data-bulk-dialog]');window.__password=__form.querySelector('[name=current_password]');window.__visible=()=>Array.from(__table.querySelectorAll('[data-bulk-item]')).filter(input=>input.closest('tr').getClientRects().length);
          if(!__visible().length){const row=document.createElement('tr');row.innerHTML='<td><input type="checkbox" data-bulk-item value="2147483647"></td>';__table.querySelector('tbody').appendChild(row);}`);
        await delay(100);
        for(const width of [1440,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});await delay(100);
            assert.equal(await evaluate(`Array.from(__table.querySelectorAll('[data-bulk-item]')).every(input=>{const row=input.closest('tr'),thumb=row.querySelector('.ann-thumb');if(!thumb)return !input.closest('.evt-actions-cell');const a=input.getBoundingClientRect(),b=thumb.getBoundingClientRect();return input.closest('.ann-img-cell')&&a.right<=b.left&&Math.abs((a.top+a.height/2)-(b.top+b.height/2))<3;})`),true,'Checkboxes sit left of thumbnails at '+width);
            await evaluate(`__visible()[0]?.scrollIntoView({block:'center',behavior:'instant'})`);
            fs.writeFileSync('cache/selection-left-'+page.split('/').at(-1).replace('.php','')+'-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        }
        assert.equal(await evaluate('__bar.querySelector("[data-bulk-delete]").disabled'),true,'Empty selection disables deletion');
        const total=await evaluate('__visible().length');
        await evaluate(`__bar.querySelector('[data-bulk-all]').click()`);
        assert.equal(await evaluate('__visible().filter(i=>i.checked).length'),total,'Select all applies to current visible page');
        if(total>1){
            await evaluate('__visible()[0].click()');assert.equal(await evaluate('__bar.querySelector("[data-bulk-all]").indeterminate'),true,'Partial selection uses mixed state');
            await evaluate(`window.__hidden=__visible().find(i=>i.checked);__hidden.closest('tr').hidden=true;`);
            await until(()=>evaluate('!__hidden.checked'),'Filtered rows removed from selection');
            await evaluate('__hidden.closest("tr").hidden=false');await delay(100);
            await evaluate(`__bar.querySelector('[data-bulk-all]').click()`);
        }
        await evaluate(`window.__selectedIds=__visible().filter(i=>i.checked).map(i=>i.value);__bar.querySelector('[data-bulk-delete]').click()`);
        assert.equal(await evaluate('document.querySelectorAll(".modal.show").length'),0,'Selecting a checkbox must not open the item editor');
        assert.equal(await evaluate('__dialog.open'),true,'Bulk delete opens confirmation');
        assert.ok((await evaluate('__dialog.querySelector("[data-bulk-description]").textContent')).includes(String(await evaluate('__selectedIds.length'))),'Dialog confirms selected amount');
        assert.equal(await evaluate('__password.checkValidity()'),false,'Password required');
        for(const width of [1440,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});await delay(100);
            assert.equal(await evaluate('(()=>{const r=__dialog.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth;})()'),true,'Dialog fits '+width);
            fs.writeFileSync('cache/bulk-'+page.split('/').at(-1).replace('.php','')+'-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        }
        if(await evaluate('__bar.dataset.kind')==='event'){
            await evaluate(`window.__originalBulkFetch=window.fetch;window.fetch=function(input,init){if(init?.body?.get('ajax_action')==='bulk_delete'){window.__bulkIds=init.body.getAll('ids[]');window.__bulkPassword=init.body.get('current_password');return Promise.resolve(new Response(JSON.stringify({success:false,message:'Intercepted; no deletion performed.'}),{headers:{'Content-Type':'application/json'}}));}return __originalBulkFetch(input,init);};`);
        }else await evaluate(`__form.addEventListener('submit',event=>{event.preventDefault();const data=new FormData(__form);window.__bulkIds=data.getAll('ids[]');window.__bulkPassword=data.get('current_password');});`);
        await evaluate(`__password.value='test-only-bulk-password';__form.requestSubmit()`);
        await until(()=>evaluate('Boolean(window.__bulkIds)'),'Bulk request not captured');
        assert.deepEqual(await evaluate('__bulkIds'),await evaluate('__selectedIds'),'Only selected IDs submitted');
        assert.equal(await evaluate('__bulkPassword'),'test-only-bulk-password','Password included once');
        await evaluate(`__dialog.close();if(window.__originalBulkFetch)window.fetch=__originalBulkFetch;`);
        await until(()=>evaluate('__password.value===""'),'Cancel clears password');
        const missing=await evaluate(`(async()=>{const frame=document.createElement('iframe');document.body.appendChild(frame);try{const body=new FormData(__form);body.set('current_password','');body.append('ids[]','2147483647');if(__bar.dataset.kind==='event')body.set('action','get');const response=await frame.contentWindow.fetch(__form.getAttribute('action'),{method:'POST',body});return await response.text();}finally{frame.remove();}})()`);
        if(!missing.includes('Enter your current password to confirm deletion.'))console.log('Bulk refusal details:',page,await evaluate(`Array.from(new DOMParser().parseFromString(${JSON.stringify(missing)},'text/html').querySelectorAll('.alert')).map(el=>el.textContent.trim())`));
        assert.ok(missing.includes('Enter your current password to confirm deletion.'),'Server rejects bulk deletion without password, including conflicting event action fields');
        console.log('Select-all, partial/filter selection, exact IDs and password protection:',page);
    }
    console.log('Bulk selection checks passed. Every deletion request was intercepted or refused; no real content deleted.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);socket?.close();browser.kill();
});
