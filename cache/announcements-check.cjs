const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const php=(file,args=[])=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php',file,...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
const sessions=[];let socket;
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-dashboard-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
(async()=>{
    const roles=['Super Admin','Content Editor','The Mirror'];
    const accounts=roles.map(role=>{const account=JSON.parse(php('cache/activity-log-session.php',[role]));sessions.push(account);return account;});
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();let send;
    const errors=[];
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}
        if(p.method==='Fetch.requestPaused')send('Fetch.fulfillRequest',{requestId:p.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from('{"success":true}').toString('base64')});
        if(p.method==='Runtime.exceptionThrown')errors.push(p.params.exceptionDetails.text);
    });
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true,userGesture:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';await send('Page.enable');await send('Network.enable');await send('Runtime.enable');
    await send('Fetch.enable',{patterns:[{urlPattern:'*/admin/action/onboarding-handler.php*',requestStage:'Request'}]});
    const closeTour=async()=>{
        await delay(150);
        if(await evaluate("Boolean(document.querySelector('.gnc-tour[open]'))")){
            await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Skip').click()");
            await until(()=>evaluate("!document.querySelector('.gnc-tour')"),'Tour did not close');
        }
    };
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page unavailable');await closeTour();};
    const resize=async width=>{await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);};
    const shot=async name=>fs.writeFileSync('cache/'+name+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    for(let i=0;i<roles.length;i++){
        await send('Network.setCookie',{...accounts[i],url:base,httpOnly:true});await resize(1440);await visit('/admin/announcement.php');
        assert.ok(await evaluate("document.querySelector('.announcement-management')!==null"));
        assert.equal(await evaluate("document.querySelector('.alert-danger')?.textContent.includes('could not be loaded')||false"),false);
        assert.ok(await evaluate("document.querySelectorAll('.ann-row').length>0"));
        assert.equal(await evaluate("document.body.textContent.includes('Undefined array key')"),false);
        if(i===0){
            await evaluate("document.getElementById('pinned-content').open=true");
            assert.ok(await evaluate("document.querySelectorAll('.ann-pinned-actions button').length>0"));
            await shot('pinned-content-management');
            await evaluate("document.querySelector('.ann-pinned-actions form').requestSubmit()");
            await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Unpin confirmation unavailable');
            assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/announcement itself is kept/);
            await evaluate("document.querySelector('.gnc-dialog-cancel').click()");
            await evaluate("document.getElementById('pinned-content').open=false");
            const denied=await evaluate("fetch('/admin/action/unpin-content.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'source=facebook&item_id=1&csrf_token=invalid'}).then(r=>r.status)");
            assert.equal(denied,403);
        }

        assert.equal(await evaluate("document.querySelector('.btn-pin-toggle')!==null"),i===0);
        assert.equal(await evaluate("document.querySelector('.ann-review-note')!==null"),i!==0);
        if(i===2)assert.equal(await evaluate("document.querySelectorAll('.btn-edit-announcement').length"),0);
        await shot('announcements-'+i);
        for(const width of [820,390]){await resize(width);assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Overflow at '+width+' role '+i);if(i===0)await shot('announcements-'+width);}
        await evaluate("document.querySelector('.ann-preview-open').click()");
        assert.equal(await evaluate("document.getElementById('ann-preview-dialog').open"),true);
        assert.ok(await evaluate("document.getElementById('ann-preview-title').textContent.length>0"));
        await shot('announcements-preview-mobile');
        await evaluate("document.querySelector('[data-ann-preview-close]').click()");
        await until(()=>evaluate("document.activeElement.classList.contains('ann-preview-open')"),'Preview focus not restored');
        await evaluate("document.getElementById('btn-new-announcement').click()");
        await until(()=>evaluate("document.querySelector('#announcementModal.show')!==null"),'Create modal unavailable');await delay(400);
        assert.equal(await evaluate("document.querySelector('#modal-title').required"),true);
        const states=await evaluate("[...document.querySelectorAll('#modal-status option')].map(o=>o.value)");
        assert.equal(states.includes('published'),i===0);
        await evaluate("document.getElementById('ann-draft-preview').click()");
        assert.equal(await evaluate("document.getElementById('ann-preview-dialog').open"),true);
        await evaluate("document.querySelector('[data-ann-preview-close]').click()");
        assert.equal(await evaluate("document.querySelectorAll('#ann-preview-message script').length"),0);
        await evaluate("(()=>{const f=document.getElementById('modal-gallery-input');const d=new DataTransfer();for(let i=0;i<11;i++)d.items.add(new File(['invalid-fixture'],'qa-'+i+'.png',{type:'image/png'}));f.files=d.files;f.dispatchEvent(new Event('change',{bubbles:true}))})()");
        await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Image limit notice not shown');
        assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/10/);
        assert.equal(await evaluate("document.querySelector('.image-frame-dialog')"),null);
        await evaluate("document.querySelector('.gnc-dialog-accept').click()");await delay(100);
        await evaluate("document.querySelector('#announcementModal [data-bs-dismiss=modal]').click()");
        await delay(200);
        if(await evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"))await evaluate("document.querySelector('.gnc-dialog-accept').click()");
        await until(()=>evaluate("!document.querySelector('#announcementModal.show')"),'Modal close failed');
        await visit('/admin/announcement.php?search=qa-no-match-xyz');
        assert.match(await evaluate("document.querySelector('.ann-empty').textContent"),/No announcements match/);
        await visit('/admin/announcement.php?search%5B%5D=x&status%5B%5D=x&modal%5B%5D=x');
        assert.ok(await evaluate("document.querySelector('.ann-query')!==null"));
        await visit('/admin/facebook-posts.php');
        assert.equal(await evaluate("document.querySelector('.announcement-management')"),null);
        assert.equal(await evaluate("document.querySelector('.facebook-management')!==null"),true);
    }
    await send('Network.setCookie',{...accounts[0],url:base,httpOnly:true});await visit('/admin/announcement.php');
    const edit=await evaluate("document.querySelector('.btn-edit-announcement')!==null");
    if(edit){await evaluate("document.querySelector('.btn-edit-announcement').click()");await until(()=>evaluate("document.querySelector('#announcementModal.show')!==null"),'Edit did not open');assert.ok(await evaluate("document.getElementById('modal-title').value.length>0"));await evaluate("document.querySelector('#announcementModal [data-bs-dismiss=modal]').click()");}
    const archive=await evaluate("document.querySelector('.ann-inline-form [name=action][value=archive]')!==null");
    if(archive){await evaluate("document.querySelector('.ann-inline-form [name=action][value=archive]').form.requestSubmit()");await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Archive confirmation missing');assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/hidden from the website/);await evaluate("document.querySelector('.gnc-dialog-cancel').click()");}
    await send('Emulation.setScriptExecutionDisabled',{value:true});await visit('/admin/announcement.php?status=draft');
    assert.ok(await evaluate("document.querySelector('.ann-query')!==null"));
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    await send('Network.deleteCookies',{name:accounts[0].name,url:base});
    const guest=await evaluate("fetch('/admin/announcement.php').then(r=>r.url)");assert.ok(/\/(?:admin\/login\.php|login)(?:[?#]|$)/.test(guest));
    assert.equal(errors.length,0,'Browser script exceptions');
    console.log('Announcement browser checks passed: all roles, scoped actions, desktop/tablet/mobile overflow, preview focus, create/edit forms, draft preview, immediate 11-image rejection before framing, status options, archive confirmation cancel, empty filters, malformed input, Facebook-page regression, no-JS filters and guest redirect. No announcement saved or deleted.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{for(const s of sessions)php('cache/activity-log-session.php',['destroy',s.value]);}finally{socket?.close();browser.kill();}});
