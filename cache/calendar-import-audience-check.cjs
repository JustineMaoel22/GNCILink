const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'alumni-voices-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,label){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(150);}throw Error(label);}
let socket,session,cleanup;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();
    const listen=event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}if(p.method==='Page.javascriptDialogOpening')socket.send(JSON.stringify({id:++serial,method:'Page.handleJavaScriptDialog',params:{accept:true}}));};
    socket.addEventListener('message',listen);
    const reconnect=async()=>{socket.close();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));socket.addEventListener('message',listen);};
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Timed out: '+method+' '+(params.expression||'').slice(0,60)));},15000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
    const ev=expression=>new Promise((resolve,reject)=>{
        const client=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
        const timer=setTimeout(()=>{client.close();reject(Error('Evaluation timed out: '+expression.slice(0,60)));},12000);
        client.addEventListener('open',()=>client.send(JSON.stringify({id:1,method:'Runtime.evaluate',params:{expression,returnByValue:true,awaitPromise:true,timeout:10000}})),{once:true});
        client.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id!==1)return;clearTimeout(timer);client.close();if(p.error||p.result.exceptionDetails)reject(Error(JSON.stringify(p.error||p.result.exceptionDetails)));else resolve(p.result.result.value);});
    });
    const visit=async route=>{await send('Page.navigate',{url:'http://localhost:8080'+route});await until(()=>ev("document.readyState==='complete'"),'Page unavailable');await delay(2200);await ev("document.querySelector('#gncSiteDisclaimer form button')?.click();document.querySelector('[data-privacy-action=\"reject\"]')?.click()");};
    await send('Page.enable');await send('Network.enable');await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});

    const php=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','Super Admin'],{encoding:'utf8',windowsHide:true});if(php.status!==0)throw Error(php.stderr||php.stdout);session=JSON.parse(php.stdout);
    await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});await visit('/admin/events.php');
    await ev(`(()=>{const original=window.fetch;window.fetch=(input,options)=>{
        const action=options?.body instanceof FormData&&options.body.get('ajax_action');
        if(action==='import_confirm'){window.audiencePayload=JSON.parse(options.body.get('import_rows'));return Promise.resolve(new Response(JSON.stringify({success:false,error:'Verification only; no import applied.'}),{headers:{'Content-Type':'application/json'}}));}
        if(action==='import_preview')return Promise.resolve(new Response(JSON.stringify({success:true,token:'audience-only',categories:[{category_id:2,category_name:'School Event'}],existing:[],departments:[{dept_id:1,dept_name:'College One',slug:'one',department_type:'college'},{dept_id:2,dept_name:'College Two',slug:'two',department_type:'college'},{dept_id:3,dept_name:'Junior High School',slug:'jhs',department_type:'basic-education'}],programs:[{code:'TSTA',name:'Test A',dept_id:1},{code:'TSTB',name:'Test B',dept_id:2}],departments_ready:true,audience_groups_ready:true,rows:[{title:'Audience fixture',start_date:'2026-11-10',category_id:2,action:'create',warnings:[],program:'ALL'}]}),{headers:{'Content-Type':'application/json'}}));return original(input,options);};
        document.querySelector('[data-bs-target="#calendarImportModal"]').click();const transfer=new DataTransfer();transfer.items.add(new File(['audience fixture'],'audience-check.docx',{type:'application/vnd.openxmlformats-officedocument.wordprocessingml.document'}));document.getElementById('calendarImportFile').files=transfer.files;document.getElementById('calendarImportName').value='Audience check';document.getElementById('calendarImportUpload').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));})()`);
    await until(()=>ev("document.querySelectorAll('#calendarImportRows .ci-actions-cell').length===1"),'Preview unavailable');
    const edit=()=>ev("document.querySelector('#calendarImportRows .ci-row-actions button:nth-child(2)').click()");
    await edit();
    await ev("document.querySelector('#ci-departments-0 .evt-audience-toggle').click();document.querySelector('#ci-departments-0 input[value=\"1\"].gnc-ms-checkbox').click()");
    assert.equal(await ev("document.getElementById('ci-departments-0')._gncMsInstance.isOpen"),true,'Picker stays open for multiple selections');
    await ev("document.querySelector('#ci-departments-0 input[value=\"2\"].gnc-ms-checkbox').click()");
    assert.deepEqual(await ev("document.getElementById('ci-departments-0')._gncMsInstance.getSelected()"),['1','2']);
    assert.deepEqual(await ev("document.getElementById('ci-programs-0')._gncMsInstance.config.items.map(i=>i.value)"),['TSTA','TSTB']);
    await ev("document.querySelector('#ci-programs-0 .evt-audience-toggle').click();document.querySelector('#ci-programs-0 input[value=TSTA]').click();document.querySelector('#ci-programs-0 input[value=TSTB]').click()");
    assert.deepEqual(await ev("document.getElementById('ci-programs-0')._gncMsInstance.getSelected()"),['TSTA','TSTB']);
    await edit();await edit();
    assert.deepEqual(await ev("document.getElementById('ci-departments-0')._gncMsInstance.getSelected()"),['1','2']);
    assert.deepEqual(await ev("document.getElementById('ci-programs-0')._gncMsInstance.getSelected()"),['TSTA','TSTB']);
    await ev("document.querySelector('#ci-departments-0 .evt-audience-toggle').click();document.querySelector('#ci-departments-0 input[value=\"2\"].gnc-ms-checkbox').click()");
    assert.deepEqual(await ev("document.getElementById('ci-programs-0')._gncMsInstance.getSelected()"),['TSTA'],'Removing department prunes its program');
    await ev("document.querySelector('#ci-departments-0 input[value=\"3\"].gnc-ms-checkbox').click()");
    assert.deepEqual(await ev("document.getElementById('ci-programs-0')._gncMsInstance.config.items.map(i=>i.value)"),['TSTA'],'Basic Education department has no program choices');
    await ev("document.querySelector('#ci-departments-0 input[value=\"3\"].gnc-ms-checkbox').click();document.querySelector('#ci-departments-0 input[value=\"2\"].gnc-ms-checkbox').click();document.querySelector('#ci-programs-0 .evt-audience-toggle').click();document.querySelector('#ci-programs-0 input[value=TSTB]').click()");
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:false});await delay(250);
        assert.equal(await ev("document.querySelector('#calendarImportModal .modal-body').scrollWidth<=document.querySelector('#calendarImportModal .modal-body').clientWidth"),true,'No horizontal overflow at '+width);
        await ev("document.getElementById('ci-departments-0').scrollIntoView({block:'center'})");await delay(150);
        fs.writeFileSync('cache/calendar-import-audience-'+width+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    await ev("[...document.querySelectorAll('.ci-editor button')].find(b=>b.textContent==='Save & Approve').click();document.getElementById('ciContinue').click();document.getElementById('ciContinue').click();document.getElementById('calendarImportConfirm').click()");
    await until(()=>ev('Boolean(window.audiencePayload)'),'Confirm payload missing');
    const payload=await ev('window.audiencePayload');assert.deepEqual(payload[0].dept_ids,['1','2']);assert.deepEqual(payload[0].programs,['TSTA','TSTB']);
    console.log('PASS: multiple department/program checkboxes, picker stays open, editor reopening retains selection, program filtering/pruning, Basic Education exclusion, responsive widths, approval/confirmation arrays. Mocked preview and confirmation; no records changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{try{if(cleanup)await cleanup();}finally{socket?.close();browser.kill();if(session)spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','destroy',session.value],{windowsHide:true});}});

