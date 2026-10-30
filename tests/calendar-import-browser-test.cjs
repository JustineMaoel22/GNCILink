const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
function sessionCommand(...args) {
    const result = spawnSync('docker', ['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php', ...args], {encoding:'utf8',windowsHide:true});
    if(result.status!==0) throw Error(result.stderr || result.stdout); return result.stdout;
}
const session = JSON.parse(sessionCommand('create'));
const profile = fs.mkdtempSync(path.join(os.tmpdir(),'gnci-calendar-import-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(100);}throw Error(message);}
let socket;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome startup');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base='http://localhost:8080';await send('Page.enable');await send('Network.enable');
    await send('Network.setCookie',{name:session.name,value:session.value,url:base,httpOnly:true});
    await send('Page.navigate',{url:base+'/admin/events.php'});
    await until(()=>evaluate("Boolean(document.getElementById('calendarImportUpload') && window.bootstrap)"),'Import upload UI');await delay(500);
    await evaluate(`(()=>{
        const original=window.fetch;window.importRequests=[];
        window.fetch=async(url,options)=>{
            const action=options?.body?.get('ajax_action');
            if(!action?.startsWith('import_')) return original(url,options);
            window.importRequests.push({action,csrf:options.body.get('csrf_token'),rows:options.body.get('import_rows'),status:options.body.get('import_status')});
            if(action==='import_cancel')return {json:async()=>({success:true})};
            if(action==='import_confirm')return {json:async()=>({success:false,message:'Test: confirmation inspected; no events saved.'})};
            if(action==='import_manage_preview')return {json:async()=>({success:true,token:'b'.repeat(64),calendar_name:'Test imported calendar',calendar_action:options.body.get('calendar_action'),rows:[{title:'Imported Foundation Day',start_date:'2026-01-15 00:00:00',end_date:null}]})};
            return {json:async()=>({success:true,token:'a'.repeat(64),departments_ready:true,audience_groups_ready:true,departments:[{dept_id:1,dept_name:'Computing'},{dept_id:2,dept_name:'Business'}],programs:[{code:'IT',name:'Information Technology',dept_id:1},{code:'BA',name:'Business Administration',dept_id:2}],categories:[{category_id:1,category_name:'Holiday'},{category_id:2,category_name:'School Event'}],existing:[{event_id:50,title:'Existing imported event',start_date:'2026-01-15 00:00:00',category_id:1}],rows:[
                {title:'Foundation Day',start_date:'2026-01-15',end_date:'',start_time:'09:00',end_time:'',description:'School community',category_id:2,warnings:[],source:'Jan 15',event_id:0,action:'create'},
                {title:'<img src=x onerror=window.importXss=true>',start_date:'',end_date:'',description:'Needs correction',category_id:null,warnings:['Date unclear.','Category needs review.'],source:'TBA',event_id:0,action:'create'},
                ...Array.from({length:525},(_,i)=>({title:'School Activity '+String(i+1).padStart(3,'0'),start_date:i%2?'2026-02-10':'2027-01-15',end_date:'',start_time:'',end_time:'',description:'Extracted description',location:'School Hall',category_id:2,warnings:[],source:'School calendar',event_id:0,action:'create'}))
            ]})};
        };
    })()`);
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:width<500});
        await evaluate("document.querySelector('[data-bs-target=\"#calendarImportModal\"]').click()");
        await until(()=>evaluate("document.getElementById('calendarImportModal').classList.contains('show') && bootstrap.Modal.getInstance(document.getElementById('calendarImportModal'))?._isTransitioning === false"),'Import modal open');
        const uploadFits=await evaluate("document.querySelector('#calendarImportModal .modal-content').getBoundingClientRect().width <= innerWidth");assert(uploadFits,'Upload modal fits screen');
        await evaluate(`(()=>{const input=document.getElementById('calendarImportFile');const data=new DataTransfer();data.items.add(new File(['test'],'calendar.docx'));input.files=data.files;document.getElementById('calendarImportUpload').dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}));})()`);
        await until(()=>evaluate("document.querySelectorAll('#calendarImportRows tr[data-row-index]').length===20"),'Only 20 of 527 rows rendered');
        if(width<500){
            assert(await evaluate("getComputedStyle(document.getElementById('ciStatus').closest('label')).display==='none'"),'Mobile filters start collapsed');
            await evaluate("document.getElementById('ciToggleFilters').click();document.getElementById('ciToggleBulk').click()");
            assert(await evaluate("getComputedStyle(document.getElementById('ciStatus').closest('label')).display!=='none' && document.getElementById('ciToggleBulk').getAttribute('aria-expanded')==='true'"),'Mobile filters and bulk tools expand');
        }
        const value=async(id,value)=>evaluate(`(()=>{const input=document.getElementById(${JSON.stringify(id)});input.value=${JSON.stringify(value)};input.dispatchEvent(new Event(input.type==='search'?'input':'change'));})()`);
        assert(await evaluate("document.getElementById('ciPageInfo').textContent.includes('527 total') && document.getElementById('calendarImportSummary').textContent.includes('527')"),'Global counts cover all rows');
        await evaluate("document.getElementById('ciSelectVisible').click();document.getElementById('ciApproveSelected').click()");
        assert(await evaluate("document.querySelectorAll('#calendarImportRows .ci-badge.approved').length===20"),'Visible selection approves 20 valid rows');
        await evaluate("document.querySelector('#calendarImportRows tr[data-row-index] button').click()");
        await evaluate(`(()=>{const input=document.querySelector('.ci-editor [data-field=location]');input.value='Reviewed Gym';input.dispatchEvent(new Event('input'));Array.from(document.querySelectorAll('.ci-editor button')).find(b=>b.textContent==='Done').click();})()`);
        await evaluate("document.getElementById('ciNext').click();document.getElementById('ciPrevious').click()");
        assert(await evaluate("document.querySelector('#calendarImportRows tr[data-row-index] .ci-description').textContent.includes('Reviewed Gym') && document.getElementById('ciSelectedCount').textContent==='20 selected'"),'Edits and selections persist across pages');
        await evaluate("(()=>{document.querySelector('#calendarImportRows tr[data-row-index] button').click();const department=document.querySelector('.ci-editor [data-field=dept_id]');department.value='2';department.dispatchEvent(new Event('change'));})()");
        assert(await evaluate("Array.from(document.querySelector('.ci-editor [data-field=program]').options).map(o=>o.value).join(',')==='ALL,BA'"),'Row editor filters programs by department');
        await evaluate("(()=>{const program=document.querySelector('.ci-editor [data-field=program]');program.value='BA';program.dispatchEvent(new Event('change'));Array.from(document.querySelectorAll('.ci-editor button')).find(b=>b.textContent==='Done').click();})()");
        await value('ciSearch','School Activity 400');await value('ciMonth','02');await value('ciYear','2026');await value('ciCategory','2');await value('ciStatus','ready');
        assert(await evaluate("document.querySelectorAll('#calendarImportRows tr[data-row-index]').length===1 && document.getElementById('ciPageInfo').textContent.includes('1 matching')"),'Combined filters search all 527 rows');
        for(const id of ['ciSearch','ciMonth','ciYear','ciCategory','ciStatus'])await value(id,'');
        await value('ciPageSize','10');assert.equal(await evaluate("document.querySelectorAll('#calendarImportRows tr[data-row-index]').length"),10);
        await value('ciPageSize','100');assert.equal(await evaluate("document.querySelectorAll('#calendarImportRows tr[data-row-index]').length"),100);
        await value('ciPageSize','20');await value('ciSearch','School Activity');await value('ciSort','title');
        assert(await evaluate("document.querySelector('#calendarImportRows .ci-title').textContent==='School Activity 001'"),'Title sort across pages');
        await evaluate("document.getElementById('ciClearSelection').click();document.getElementById('ciSelectMatching').click()");
        assert.equal(await evaluate("document.getElementById('ciSelectedCount').textContent"),'525 selected');
        await value('ciBulkDepartment','1');
        assert(await evaluate("Array.from(document.getElementById('ciBulkProgram').options).map(o=>o.value).join(',')==='ALL,IT'"),'Bulk programs limited to selected department');
        await value('ciBulkProgram','IT');await evaluate("document.getElementById('ciApplyAudience').click()");
        assert(await evaluate("document.querySelector('#calendarImportRows tr[data-row-index]').textContent.includes('Computing') && document.getElementById('ciFeedback').textContent.includes('525 activities')"),'Bulk audience applies across all matching pages');
        await value('ciBulkDepartment','group:basic-education');
        assert(await evaluate("document.getElementById('ciBulkProgram').options.length===1 && document.getElementById('ciBulkProgram').value==='ALL'"),'Basic Education group offers only all programs');
        await value('ciBulkDepartment','group:college');await evaluate("document.getElementById('ciApplyAudience').click()");
        assert(await evaluate("document.querySelector('#calendarImportRows tr[data-row-index]').textContent.includes('All College')"),'College group shown in compact preview');
        await value('ciBulkCategory','1');await evaluate("document.getElementById('ciApplyCategory').click();document.getElementById('ciApproveSelected').click()");
        await value('ciSearch','');await value('ciSort','date');await evaluate("document.getElementById('calendarImportSelectValid').click()");
        await value('ciSearch','<img');
        assert(await evaluate("!window.importXss && !document.querySelector('#calendarImportRows img') && document.querySelector('#calendarImportRows .ci-warning')!==null"),'Unsafe text escaped; warning visible');
        await evaluate("document.getElementById('ciClearSelection').click();document.getElementById('ciSelectMatching').click();document.getElementById('ciApproveSelected').click()");
        assert(await evaluate("document.querySelector('#calendarImportRows .ci-badge.needs')!==null && document.getElementById('ciFeedback').textContent.includes('skipped')"),'Critical errors cannot be bulk approved');
        await evaluate("document.getElementById('ciExcludeSelected').click()");await value('ciSearch','');
        await evaluate("document.getElementById('ciContinue').click()");
        assert(await evaluate("document.querySelector('[data-ci-step=\"3\"]').getAttribute('aria-current')==='step'"),'Verification step');
        await evaluate("document.getElementById('ciContinue').click()");
        assert(await evaluate("document.getElementById('ciFinalSummary').textContent.includes('526 approved') && !document.getElementById('calendarImportConfirm').disabled && document.getElementById('ciFinal').textContent.includes('Nothing is automatically published')"),'Final summary and approval policy');
        await evaluate("document.getElementById('calendarImportConfirm').click()");
        await until(()=>evaluate("!document.getElementById('calendarImportError').classList.contains('d-none') && !document.getElementById('calendarImportConfirm').disabled"),'Confirmation response');
        const sent=await evaluate("JSON.parse(window.importRequests.filter(r=>r.action==='import_confirm').at(-1).rows)");
        assert.equal(sent.length,527);assert.equal(sent.filter(row=>row.include).length,526);assert(!sent[1].include);assert.equal(sent[0].location,'Reviewed Gym');assert(sent.slice(2).every(row=>row.category_id==='1'));
        assert(sent.slice(2).every(row=>row.dept_id==='group:college'&&row.program==='ALL'),'Department group survives pagination, filtering, review and confirmation');
        assert.equal(sent[0].dept_id,'2');assert.equal(sent[0].program,'BA','Individual program correction saved');
        assert.equal(await evaluate("window.importRequests.filter(r=>r.action==='import_confirm').at(-1).status"),'draft','Import cannot implicitly publish');
        await evaluate("document.getElementById('ciBack').click();document.getElementById('ciBack').click()");
        if(width<500)await evaluate("document.getElementById('ciToggleFilters').click();document.getElementById('ciToggleBulk').click()");
        assert(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector('#calendarImportModal .modal-footer').getBoundingClientRect().bottom<=innerHeight+1"),'Responsive workspace and visible action footer');
        fs.mkdirSync('cache/calendar-import-audit',{recursive:true});
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(`cache/calendar-import-audit/preview-${width}.png`,Buffer.from(shot.data,'base64'));
        await evaluate("document.getElementById('calendarImportCancel').click()");
        await until(()=>evaluate("Boolean(document.querySelector('dialog[open]'))"),'Unsaved review warning');
        await evaluate("document.querySelector('dialog[open] .gnc-dialog-cancel').click()");
        await until(()=>evaluate("!document.querySelector('dialog')"),'Keep-reviewing dialog settled');
        assert(await evaluate("document.getElementById('calendarImportModal').classList.contains('show') && document.getElementById('calendarImportSummary').textContent.includes('526')"),'Keep reviewing preserves edits');
        await evaluate("document.getElementById('calendarImportCancel').click()");await until(()=>evaluate("Boolean(document.querySelector('dialog[open]'))"),'Discard confirmation');
        await evaluate("document.querySelector('dialog[open] .gnc-dialog-accept').click()");await delay(500);
        await until(()=>evaluate("!document.getElementById('calendarImportModal').classList.contains('show')"),'Confirmed discard closes preview');
        await evaluate("document.querySelector('[data-bs-target=\"#calendarImportModal\"]').click()");
        await until(()=>evaluate("document.getElementById('calendarImportModal').classList.contains('show') && bootstrap.Modal.getInstance(document.getElementById('calendarImportModal'))?._isTransitioning === false"),'Management modal ready');
        await evaluate(`(()=>{const series=document.getElementById('calendarManageSeries');if(!series.querySelector('option[value="999999"]'))series.append(new Option('Test imported calendar','999999'));series.value='999999';document.getElementById('calendarManageDelete').click();})()`);
        await until(()=>evaluate("!document.getElementById('calendarManagePreview').classList.contains('d-none')"),'Management preview opens');
        assert(await evaluate("document.getElementById('calendarImportConfirm').disabled && document.getElementById('calendarManageExplanation').textContent.includes('permanently deleted')"),'Delete requires explicit review with permanent deletion warning');
        await evaluate("document.getElementById('calendarManageReviewed').click()");
        assert(await evaluate("!document.getElementById('calendarImportConfirm').disabled && document.getElementById('calendarManageRows').textContent.includes('Imported Foundation Day')"),'Review enables deletion and identifies affected events');
        await evaluate("document.getElementById('calendarImportConfirm').click()");
        await until(()=>evaluate("!document.getElementById('calendarImportError').classList.contains('d-none') && !document.getElementById('calendarImportConfirm').disabled && document.getElementById('calendarImportError').textContent.includes('confirmation inspected')"),'Delete confirmation response');
        await evaluate("document.getElementById('calendarImportCancel').click()");await delay(500);
        await until(()=>evaluate("Boolean(document.querySelector('dialog[open]'))"),'Management discard confirmation');await evaluate("document.querySelector('dialog[open] .gnc-dialog-accept').click()");
        await until(()=>evaluate("!document.getElementById('calendarImportModal').classList.contains('show') && document.getElementById('calendarManagePreview').classList.contains('d-none')"),'Management cancellation resets modal');
    }
    assert(await evaluate("window.importRequests.every(r=>r.csrf)&&window.importRequests.some(r=>r.action==='import_cancel')"),'All import requests carry CSRF and cancellation sent');
    console.log('PASS: 527-activity desktop/mobile workspace, four steps, global combined filters, pagination, bulk categories/approval/exclusion, edit persistence, error blocking, safe confirmation payload, draft-only import, discard protection and archive/delete review. No events saved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    sessionCommand('destroy',session.value);if(socket)socket.close();browser.kill();await delay(500);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-import-'))fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});
});
