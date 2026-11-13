// Local browser checks; fixtures stay in the DOM and no content is saved.
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const sessionTool = args => {
    const result = spawnSync('docker', ['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args], {encoding:'utf8',windowsHide:true});
    if (result.status !== 0) throw Error(result.stderr || result.stdout);
    return result.stdout;
};
const sessions = [], profile = fs.mkdtempSync(path.join(os.tmpdir(),'gnci-priority-content-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve,ms));
async function until(fn, message) { for (const end=Date.now()+30000; Date.now()<end;) { if (await fn()) return; await delay(100); } throw Error(message); }
let socket;
(async () => {
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message',event=>{const msg=JSON.parse(event.data); if(pending.has(msg.id)){pending.get(msg.id)(msg);pending.delete(msg.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,msg=>msg.error?reject(Error(JSON.stringify(msg.error))):resolve(msg.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base='http://localhost:8080';
    await send('Page.enable'); await send('Network.enable');
    async function login(role) { const session=JSON.parse(sessionTool([role]));sessions.push(session);await send('Network.setCookie',{...session,url:base,httpOnly:true}); }
    async function visit(route,selector) {
        await evaluate('window.__priorPriorityPage=true');
        await send('Page.navigate',{url:base+route});
        await until(()=>evaluate(`!window.__priorPriorityPage && document.readyState==='complete' && Boolean(document.querySelector(${JSON.stringify(selector)}))`).catch(()=>false),'Page unavailable: '+route);
    }
    await login('Super Admin');
    await visit('/admin/enrollment-steps.php','#enrollment-steps-form');
    // An iframe uses native fetch, bypassing the UI's automatic password prompt.
    const blocked=await evaluate(`(async()=>{
        const frame=document.createElement('iframe');document.body.appendChild(frame);const results=[];
        for(const route of ['/admin/action/alumni-voices-handler.php','/admin/enrollment-steps.php'])for(const action of ['', 'list', 'save_draft', 'delete']){
            const body=new URLSearchParams({csrf_token:document.querySelector('meta[name="cms-csrf"]').content,action});
            const response=await frame.contentWindow.fetch(route,{method:'POST',body});results.push([response.status,(await response.json()).reauth_required]);
        }frame.remove();return results;
    })()`);
    assert.equal(blocked.length,8);blocked.forEach(result=>assert.deepEqual(result,[428,true]));
    await visit('/admin/events.php','#evtDeptFilter');
    const filtered=await evaluate(`(()=>{
        const original=document.querySelector('tr.evt-row');if(!original)throw Error('An Events row is required as a DOM template');
        const parent=original.parentElement,template=original.cloneNode(true);parent.replaceChildren();
        const fixtures=[['wide','',[],[]],['basic','group:basic-education',[],[]],['college','group:college',[],[]],['basic-dept','1',[1],['basic-education']],['college-dept','2',[2],['college']],['mixed','1',[1,2],['basic-education','college']]];
        for(const [id,dept,depts,groups]of fixtures){const row=template.cloneNode(true);Object.assign(row.dataset,{eventId:id,status:'published',title:id,category:'',dept,depts:JSON.stringify(depts),groups:JSON.stringify(groups)});parent.appendChild(row);}
        document.getElementById('evtSearch').value='';document.getElementById('evtCatFilter').value='';document.querySelector('.evt-chip[data-filter="all"]').click();
        const result={};for(const group of ['basic-education','college']){const select=document.getElementById('evtDeptFilter');select.value='group:'+group;select.dispatchEvent(new Event('change',{bubbles:true}));result[group]=Array.from(parent.querySelectorAll('tr.evt-row')).filter(row=>row.style.display!=='none').map(row=>row.dataset.eventId);}
        return result;
    })()`);
    assert.deepEqual(filtered['basic-education'],['wide','basic','basic-dept','mixed']);
    assert.deepEqual(filtered.college,['wide','college','college-dept','mixed']);
    await login('Content Editor');
    await visit('/admin/enrollment-steps.php','#enrollment-steps-form');
    await evaluate(`(()=>{
        window.AdminDialogs.confirm=async()=>true;window.AdminDialogs.alert=async()=>{};
        window.__stepsResponse=428;
        window.fetch=async()=>window.__stepsResponse===428?Response.json({reauth_required:true},{status:428}):Response.json({success:true,submitted:true,message:'Queued test steps'},{status:202});
        document.getElementById('enrollment-add-step').click();
        const row=document.getElementById('enrollment-step-list').lastElementChild;
        row.querySelector('[data-title]').value='Test step';row.querySelector('[data-instruction]').value='Test instructions';
        document.getElementById('enrollment-steps-form').requestSubmit();
    })()`);
    await until(()=>evaluate(`document.getElementById('enrollment-save-status').textContent.includes('Publication was not confirmed')`),'Cancelled publication lost feedback');
    assert.equal(await evaluate('GNCEnrollmentStepsEditor.hasChanges()'),true);
    await evaluate(`window.__stepsResponse=202;document.getElementById('enrollment-steps-form').requestSubmit()`);
    await until(()=>evaluate(`document.getElementById('enrollment-save-status').textContent==='Queued test steps'`),'Queued enrollment response failed');
    assert.equal(await evaluate('GNCEnrollmentStepsEditor.hasChanges()'),false);
    assert.equal(await evaluate(`document.getElementById('enrollment-publish').disabled`),true);
    const alumniSource=fs.readFileSync('assets/js/alumni-voices-editor.js','utf8');
    const alumni=await evaluate(`(async()=>{
        const script=document.createElement('script');script.textContent=${JSON.stringify(alumniSource)};document.body.appendChild(script);
        let response={submitted:true,message:'Queued test alumni'},notice='';
        const editor=GNCAlumniVoicesEditor({doc:()=>document,send:async()=>response,notify:message=>{notice=message;}});
        editor.state({data:{items:[{id:'test',name:'Private edit'}],revision:'original'},baseline:'[]',files:{}});
        await editor.publish();const queued=notice==='Queued test alumni'&&editor.state().data.revision==='original'&&editor.dirty()===1;
        response={reauth_required:true,ok:false};let cancelled=false;try{await editor.publish();}catch(error){cancelled=error.message.includes('Your edits are kept');}
        return {queued,cancelled,dirty:editor.dirty()};
    })()`);
    assert.deepEqual(alumni,{queued:true,cancelled:true,dirty:1});
    await visit('/admin/announcement.php','#modal-status');
    assert.deepEqual(await evaluate(`Array.from(document.querySelector('#modal-status').options).map(option=>option.value)`),['draft','pending']);
    // Exercise edit hydration on a DOM-only button with an existing record's ID.
    for(const [saved,expected,label]of [['draft','draft','Save draft'],['published','pending','Submit for approval']]){
        const result=await evaluate(`(async()=>{
            const button=document.querySelector('.btn-edit-announcement');if(!button)throw Error('An announcement edit button is required');
            button.dataset.status=${JSON.stringify(saved)};button.click();await new Promise(resolve=>setTimeout(resolve,500));
            const result=[document.getElementById('modal-status').value,document.getElementById('modal-save-label').textContent];
            bootstrap.Modal.getInstance(document.getElementById('announcementModal')).hide();await new Promise(resolve=>setTimeout(resolve,400));return result;
        })()`);
        assert.deepEqual(result,[expected,label]);
    }
    // Exercise the actual public community script with published-shaped fixtures.
    const communitySource=fs.readFileSync('assets/js/community-activities.js','utf8');
    const community=await evaluate(`(async()=>{
        const frame=document.createElement('iframe');document.body.appendChild(frame);const doc=frame.contentDocument;
        doc.body.innerHTML='<ol data-community-list></ol><div data-community-feature><img data-community-image><span data-community-no-image></span><span data-community-caption></span></div><p data-community-empty></p><div data-community-showcase></div>';
        const content={texts:{hero_title:'Community'},activities:[{id:'a',title:'One',category:'Service',description:'Published description <safe>'},{id:'b',title:'Two',category:'Service',description:'Second description'}]};
        const data=doc.createElement('script');data.id='communityPageData';data.type='application/json';data.textContent=JSON.stringify({preview:false,content});doc.body.appendChild(data);
        const script=doc.createElement('script');script.textContent=${JSON.stringify(communitySource)};doc.body.appendChild(script);
        frame.contentWindow.GNCCommunityActivities.render(content);
        doc.querySelector('[data-community-id="b"] h4').click();
        const result={descriptions:Array.from(doc.querySelectorAll('.community-activity-description')).map(el=>el.textContent),active:doc.querySelector('.is-active').dataset.communityId,markers:doc.querySelectorAll('[data-ve-field]').length,unsafe:doc.querySelectorAll('safe').length};
        frame.remove();return result;
    })()`);
    assert.deepEqual(community,{descriptions:['Published description <safe>','Second description'],active:'b',markers:0,unsafe:0});
    console.log('PASS: both endpoints reject unconfirmed writes/action bypasses; department groups stay isolated; non-publisher draft edit status/labels; public activity descriptions and click selection. DOM fixtures only; no content saved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{
    for(const session of sessions)sessionTool(['destroy',session.value]);socket?.close();browser.kill();
});
