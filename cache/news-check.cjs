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
        if(p.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:false});
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
    if(process.argv[2]==='visual'){
        await send('Network.setCookie',{...accounts[0],url:base,httpOnly:true});await resize(390);await visit('/admin/news.php');
        await evaluate("document.querySelector('.ann-list-heading').scrollIntoView({behavior:'instant',block:'start'})");await delay(500);await shot('news-mobile-cards');
        await resize(820);await visit('/admin/news.php?category=Others&from=2026-10-01&sort=updated');await evaluate("document.querySelector('#news-query').scrollIntoView({behavior:'instant',block:'start'})");await delay(500);await shot('news-filters-tablet');return;
    }
    for(let i=0;i<roles.length;i++){
        await send('Network.setCookie',{...accounts[i],url:base,httpOnly:true});await resize(1440);await visit('/admin/news.php');
        assert.ok(await evaluate("Boolean(document.querySelector('.news-management'))"));
        assert.equal(await evaluate("Boolean(document.querySelector('#ann-pin-heading'))"),i===0);
        assert.equal(await evaluate("Boolean(document.querySelector('.ann-review-note'))"),i!==0);
        assert.equal(await evaluate("Boolean(document.querySelector('.news-management > .alert-danger'))"),false);
        assert.equal(await evaluate("/Warning:|Fatal error:|Undefined array/.test(document.body.textContent)"),false);
        for(const width of [1440,820,390]){
            await resize(width);assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Page overflow '+width+' '+roles[i]);if(i===0)await shot('news-'+width);
        }
        const hasRows=await evaluate("Boolean(document.querySelector('[data-news-preview]'))");
        if(hasRows){
            await evaluate("document.querySelector('[data-news-preview]').click()");
            assert.ok(await evaluate("document.getElementById('ann-preview-dialog').open"));
            assert.ok(await evaluate("document.getElementById('ann-preview-title').textContent.length>0"));
            if(i===0)await shot('news-preview-mobile');
            await evaluate("document.querySelector('[data-news-preview-close]').click()");
            await until(()=>evaluate("Boolean(document.activeElement.dataset.newsPreview)"),'Preview focus not restored');
            const hasEdit=await evaluate("Boolean(document.querySelector('.btn-edit-news'))");
            if(hasEdit){
                await evaluate("document.querySelector('.btn-edit-news').click()");await until(()=>evaluate("Boolean(document.querySelector('#newsModal.show'))"),'Edit did not open');await delay(350);
                assert.ok(await evaluate("document.getElementById('modal-title').value.length>0"));
                if(i>0)assert.equal(await evaluate("document.getElementById('modal-status').value"),'pending');
                await evaluate("document.querySelector('#newsModal [data-bs-dismiss=modal]').click()");
                await until(()=>evaluate("!document.querySelector('#newsModal.show')"),'Edit did not close');
                await until(()=>evaluate("!document.querySelector('.modal-backdrop')"),'Edit close transition did not finish');
            }
        }
        await evaluate("document.getElementById('btn-new-news').click()");
        await until(()=>evaluate("Boolean(document.querySelector('#newsModal.show'))"),'Create did not open');await delay(350);
        const states=await evaluate("[...document.querySelectorAll('#modal-status option')].map(o=>o.value)");
        assert.equal(states.includes('published'),i===0);assert.ok(states.includes('draft'));assert.equal(states.includes('pending'),i!==0);
        assert.ok(await evaluate("document.querySelector('#news-form [name=return_query]')!==null"));
        if(i===0)await shot('news-create-mobile');
        await evaluate("document.getElementById('modal-title').value='Unsaved headline';document.querySelector('#modal-content-editor .ql-editor').innerHTML='<p>Draft preview <strong>formatting</strong><span onclick=alert(1)>Text</span><script>alert(1)</script></p>';document.getElementById('news-draft-preview').click()");
        assert.ok(await evaluate("document.getElementById('ann-preview-dialog').open"));
        assert.equal(await evaluate("document.getElementById('ann-preview-title').textContent"),'Unsaved headline');
        assert.equal(await evaluate("document.querySelectorAll('#news-preview-message script,#news-preview-message [onclick]').length"),0);
        assert.ok(await evaluate("Boolean(document.querySelector('#news-preview-message strong'))"));
        await evaluate("document.querySelector('[data-news-preview-close]').click()");await delay(100);
        // Invalid gallery batches must be rejected before the framing interface.
        await evaluate("(()=>{const d=new DataTransfer();for(let i=0;i<11;i++)d.items.add(new File(['fixture'],'qa-'+i+'.png',{type:'image/png'}));const f=document.getElementById('modal-gallery-input');f.files=d.files;f.dispatchEvent(new Event('change',{bubbles:true}))})()");
        await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Gallery limit feedback missing');
        assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/10/);
        assert.equal(await evaluate("document.querySelector('.image-frame-dialog')"),null);
        await evaluate("document.querySelector('.gnc-dialog-accept').click()");await delay(100);
        await evaluate("document.querySelector('#newsModal [data-bs-dismiss=modal]').click()");
        await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Unsaved changes notice missing');
        await evaluate("document.querySelector('.gnc-dialog-accept').click()");await until(()=>evaluate("!document.querySelector('#newsModal.show')"),'Discard did not close');
        await until(()=>evaluate("!document.querySelector('.modal-backdrop')"),'Create close transition did not finish');
        await visit('/admin/news.php?search=qa-no-match-xyz&sort=updated&size=25');
        assert.match(await evaluate("document.querySelector('.ann-empty').textContent"),/No news articles match/);
        assert.equal(await evaluate("document.getElementById('news-sort').value"),'updated');
        await visit('/admin/news.php?search%5B%5D=x&status%5B%5D=x&category%5B%5D=x&modal%5B%5D=x&from=2026-02-30');
        assert.ok(await evaluate("Boolean(document.querySelector('#news-query'))"));
        if(i>0){
            const rejected=await evaluate("fetch('/admin/action/create-news.php',{method:'POST',body:new URLSearchParams({action:'create',title:'',content:'',status:'published',csrf_token:document.querySelector('meta[name=cms-csrf]').content})}).then(r=>r.status)");
            assert.equal(rejected,422);
        }
    }
    await send('Network.setCookie',{...accounts[0],url:base,httpOnly:true});await resize(1440);await visit('/admin/news.php');
    const archive=await evaluate("Boolean(document.querySelector('.ann-inline-form [name=action][value=archive]'))");
    if(archive){await evaluate("document.querySelector('.ann-inline-form [name=action][value=archive]').form.requestSubmit()");await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Archive confirmation missing');assert.match(await evaluate("document.querySelector('.gnc-dialog').textContent"),/article and pictures remain saved/);await evaluate("document.querySelector('.gnc-dialog-cancel').click()");}
    if(await evaluate("Boolean(document.querySelector('.btn-delete-news'))")){await evaluate("document.querySelector('.btn-delete-news').click()");await until(()=>evaluate("Boolean(document.querySelector('#deleteNewsModal.show'))"),'Delete confirmation missing');assert.match(await evaluate("document.getElementById('deleteNewsModal').textContent"),/cannot be undone/);await evaluate("document.querySelector('#deleteNewsModal [data-bs-dismiss=modal]').click()");await delay(300);}
    await send('Emulation.setScriptExecutionDisabled',{value:true});await visit('/admin/news.php?status=draft&sort=updated');assert.ok(await evaluate("Boolean(document.querySelector('#news-query'))"));await send('Emulation.setScriptExecutionDisabled',{value:false});
    await send('Network.deleteCookies',{name:accounts[0].name,url:base});assert.match(await evaluate("fetch('/admin/news.php').then(r=>r.url)"),/\/(?:login|admin\/login\.php)/);
    assert.equal(errors.length,0,'Browser script exceptions: '+errors.join(','));
    console.log('News browser checks passed: three roles; desktop/tablet/mobile layout; saved/draft previews; formatting sanitization; editor status choices; gallery cap before framing; unsaved changes; archive/delete confirmations; query persistence; malformed inputs; no-JS filters; guest redirect. No articles saved or deleted.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{for(const s of sessions)php('cache/activity-log-session.php',['destroy',s.value]);}finally{socket?.close();browser.kill();}});
