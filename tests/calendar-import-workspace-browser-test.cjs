// Local browser fixtures test saving/resuming across reloads; no events are imported.
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const sessionTool=args=>{
    const result=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if(result.status!==0)throw Error(result.stderr||result.stdout);
    return result.stdout;
};
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-import-modal-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(fn,message){for(let end=Date.now()+20000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket,session;
(async()=>{
    session=JSON.parse(sessionTool(['Super Admin']));
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const msg=JSON.parse(event.data);if(pending.has(msg.id)){pending.get(msg.id)(msg);pending.delete(msg.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,msg=>msg.error?reject(Error(JSON.stringify(msg.error))):resolve(msg.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');
    socket.addEventListener('message',event=>{const msg=JSON.parse(event.data);if(msg.method==='Runtime.exceptionThrown')console.error(JSON.stringify(msg.params.exceptionDetails));});
    const anonymous=await fetch('http://localhost:8080/admin/events.php',{method:'POST',body:new URLSearchParams({ajax_action:'import_review_list'}),redirect:'manual'});
    assert.ok([302,303].includes(anonymous.status),'Anonymous review requests must require login');
    await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});

    const fixture={success:true,token:'a'.repeat(64),categories:[{category_id:1,category_name:'School Activities'},{category_id:2,category_name:'Holiday'}],existing:[],departments_ready:true,audience_groups_ready:true,departments:[],programs:[],rows:[
        {title:'Missing category',start_date:'2026-11-10',category_id:null,warnings:['Category needs review.']},
        {title:'Unclear date',start_date:'',category_id:1,warnings:['Date is missing.']},
        {title:'TBA details',start_date:'2026-11-12',category_id:1,location:'TBA',warnings:['Time is TBA or unclear. Please verify the schedule time.','Location is marked as TBA or unclear. Verify the venue.']},
        {title:'Possible duplicate',start_date:'2026-11-13',category_id:1,warnings:['Possible duplicate of existing event.']},
        {title:'Clean event',start_date:'2026-11-14',category_id:1,warnings:[]},
        {title:'Audience review',start_date:'2026-11-15',category_id:1,warnings:['Several departments may match.']}
    ].map(row=>({description:'',location:'',program:'ALL',action:'create',...row}))};
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`
        window.__fixture=${JSON.stringify(fixture)};
        const originalFetch=window.fetch;window.__realFetch=originalFetch;
        window.fetch=(url,options)=>{
            const form=options?.body,action=form instanceof FormData?form.get('ajax_action'):null;
            const saved=JSON.parse(sessionStorage.getItem('testCalendarReview')||'null');
            const reply=value=>Promise.resolve(Response.json({success:true,...value}));
            if(action==='import_confirm')throw Error('Test must never confirm an import');
            if(action==='import_review_list')return reply({reviews:saved?[{token:window.__fixture.token,filename:'Saved fixture.docx',rows:saved.rows.length,saved_at:1700000000,review_version:saved.version}]:[]});
            if(action==='import_review_save'){
                const review=JSON.parse(form.get('review_workspace'));review.version++;
                sessionStorage.setItem('testCalendarReview',JSON.stringify(review));
                return reply({review_version:review.version,saved_at:1700000000});
            }
            if(action==='import_review_resume')return reply({...window.__fixture,rows:saved.rows,view:saved.view,review_version:saved.version,filename:'Saved fixture.docx'});
            if(action==='import_cancel'){
                if(form.get('discard_saved')==='1')sessionStorage.removeItem('testCalendarReview');
                sessionStorage.setItem('testCalendarCancels',String(Number(sessionStorage.getItem('testCalendarCancels')||0)+1));
                return reply({});
            }
            if(action==='import_preview')return reply(window.__fixture);
            return originalFetch(url,options);
        };
    `});
    await send('Page.navigate',{url:'http://localhost:8080/admin/events.php'});
    await until(()=>evaluate("Boolean(document.getElementById('calendarImportEditModal'))"),'Import interface unavailable');
    for(const action of ['import_review_save','import_review_resume','import_review_list']){
        const rejected=await evaluate(`(async()=>{const data=new FormData();data.set('ajax_action',${JSON.stringify(action)});data.set('csrf_token','invalid');return (await window.__realFetch(location.pathname,{method:'POST',body:data})).json();})()`);
        assert.equal(rejected.success,false,'Review endpoints require CSRF protection');
        assert.match(rejected.message,/security token/i);
    }
    const liveList=await evaluate("(async()=>{const data=new FormData();data.set('ajax_action','import_review_list');data.set('csrf_token',document.getElementById('csrfToken').value);return (await window.__realFetch(location.pathname,{method:'POST',body:data})).json();})()");
    assert.equal(liveList.success,true);assert.ok(Array.isArray(liveList.reviews),'Authenticated saved-review endpoint must return review metadata');
    await evaluate(`bootstrap.Modal.getOrCreateInstance(document.getElementById('calendarImportModal')).show();
        const transfer=new DataTransfer();transfer.items.add(new File(['fixture'],'calendar.docx'));document.getElementById('calendarImportFile').files=transfer.files;
        document.getElementById('calendarImportName').value='Workspace test';document.getElementById('calendarImportUpload').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));`);
    await until(()=>evaluate("document.querySelectorAll('#calendarImportRows tr').length===6"),'Preview not rendered').catch(async error=>{throw Error(error.message+': '+JSON.stringify(await evaluate("({error:document.getElementById('calendarImportError').textContent,status:document.getElementById('calendarImportFileStatus').textContent,mock:!!window.__fixture})")));});
    const visibleTitles=()=>evaluate("Array.from(document.querySelectorAll('#calendarImportRows .ci-title')).map(el=>el.textContent.trim())");
    const issue=async value=>evaluate(`(()=>{const input=document.getElementById('ciIssue');input.value=${JSON.stringify(value)};input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
    for(const [type,title] of [['category','Missing category'],['date','Unclear date'],['tba','TBA details'],['duplicate','Possible duplicate'],['audience','Audience review']]){
        await issue(type);assert.deepEqual(await visibleTitles(),[title],`Issue filter: ${type}`);
    }
    await issue('category');
    await evaluate("document.querySelector('#calendarImportRows select').value='1';document.querySelector('#calendarImportRows select').dispatchEvent(new Event('change',{bubbles:true}))");
    assert.equal((await visibleTitles()).length,0,'Corrected category must leave the category issue filter');
    await evaluate("document.getElementById('ciResetFilters').click()");
    await evaluate("document.querySelector('#calendarImportRows [data-edit-event]').click()");
    await until(()=>evaluate("document.getElementById('calendarImportEditModal').open"),'Editor missing');
    await evaluate(`(()=>{const input=document.querySelector('#calendarImportEditModal [data-field="title"]');input.value='Corrected event';input.dispatchEvent(new Event('input',{bubbles:true}));const category=document.querySelector('#calendarImportEditModal select');category.value='1';category.dispatchEvent(new Event('change',{bubbles:true}));Array.from(document.querySelectorAll('#calendarImportEditModal button')).find(button=>button.textContent==='Save & Approve').click();})()`);
    await until(()=>evaluate("!document.getElementById('calendarImportEditModal').open"),'Approval did not close editor');
    await evaluate("document.querySelector('#calendarImportRows input[type=checkbox]').click()");
    await issue('date');
    await evaluate("document.getElementById('ciSaveReview').click()");
    await until(()=>evaluate("document.getElementById('ciReviewSaveStatus').textContent.startsWith('Review saved')"),'Workspace was not saved');
    const saved=await evaluate("JSON.parse(sessionStorage.getItem('testCalendarReview'))");
    assert.equal(saved.rows[0].title,'Corrected event');assert.equal(saved.rows[0].include,true);assert.equal(saved.rows[0].reviewed,true);
    assert.equal(saved.view.ciIssue,'date');assert.deepEqual(saved.view.selected,[0]);
    // A refresh must not require another document upload or auto-approve unresolved rows.
    await evaluate('window.__beforeReviewReload=true');
    await send('Page.reload');
    await until(()=>evaluate("!window.__beforeReviewReload&&document.readyState==='complete'&&Boolean(window.bootstrap)&&Boolean(document.getElementById('calendarImportEditModal'))").catch(()=>false),'Reload failed');
    await evaluate("bootstrap.Modal.getOrCreateInstance(document.getElementById('calendarImportModal')).show()");
    await until(()=>evaluate("!document.getElementById('ciSavedReviews').classList.contains('d-none')"),'Saved review list not shown');
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:800,deviceScaleFactor:1,mobile:false});
        assert.ok(await evaluate("document.querySelector('#calendarImportModal .modal-body').scrollWidth<=innerWidth"),'Saved review controls must fit viewport');
    }
    await evaluate("document.getElementById('ciResumeReview').click()");
    await until(()=>evaluate("document.getElementById('ciReviewSaveStatus').textContent.startsWith('Saved review restored')"),'Review not restored');
    assert.equal(await evaluate("document.getElementById('ciIssue').value"),'date');
    assert.deepEqual(await visibleTitles(),['Unclear date']);
    await evaluate("document.getElementById('ciResetFilters').click()");
    assert.equal(await evaluate("document.querySelector('#calendarImportRows .ci-title').textContent"),'Corrected event');
    assert.equal(await evaluate("document.querySelector('#calendarImportRows .ci-badge').textContent"),'Approved');
    assert.equal(await evaluate("document.querySelector('#calendarImportRows input[type=checkbox]').checked"),true);
    assert.equal(await evaluate("document.querySelectorAll('#calendarImportRows .ci-badge.approved').length"),2,'Resume must not auto-approve pending entries');
    // Save changed view, then close normally; the saved batch must not be cancelled.
    await evaluate("document.getElementById('ciSaveReview').click()");
    await until(()=>evaluate("document.getElementById('ciSaveReview').disabled"),'Second save not started');
    await until(()=>evaluate("document.getElementById('ciReviewSaveStatus').textContent.startsWith('Review saved')"),'Second save failed');
    await evaluate("bootstrap.Modal.getInstance(document.getElementById('calendarImportModal')).hide()");
    await until(()=>evaluate("document.getElementById('calendarImportModal').style.display==='none'"),'Saved workspace must close without discard prompt');
    assert.equal(await evaluate("Number(sessionStorage.getItem('testCalendarCancels')||0)"),0,'Closing a saved review must preserve it');
    await evaluate("bootstrap.Modal.getOrCreateInstance(document.getElementById('calendarImportModal')).show()");
    await until(()=>evaluate("!document.getElementById('ciSavedReviews').classList.contains('d-none')"),'Saved review unavailable after close');
    await until(()=>evaluate("getComputedStyle(document.getElementById('calendarImportModal')).opacity==='1'&&getComputedStyle(document.querySelector('#calendarImportModal .modal-dialog')).transform==='none'"),'Modal animation did not finish');
    fs.writeFileSync(path.join(profile,'saved-review-390.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    await evaluate("document.getElementById('ciDiscardReview').click()");
    await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Discard saved review requires confirmation');
    await evaluate("document.querySelector('.gnc-dialog[open] .gnc-dialog-accept').click()");
    await until(()=>evaluate("document.getElementById('ciSavedReviews').classList.contains('d-none')"),'Discarded saved review must leave the resume list');
    assert.equal(await evaluate("sessionStorage.getItem('testCalendarReview')"),null);
    console.log('Screenshot: '+path.join(profile,'saved-review-390.png'));
    console.log('PASS: issue filters and correction, save/resume across reload, edited titles/categories, approvals, selections, pending rows, saved-close retention and responsive saved-review controls. No import confirmed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(session)sessionTool(['destroy',session.value]);socket?.close();browser.kill();});
