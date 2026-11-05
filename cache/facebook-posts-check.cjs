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
    if(process.argv[2]==='pin-preview'){
        await send('Network.setCookie',{...accounts[0],url:base,httpOnly:true});
        for(const width of [1440,390]){
            await resize(width);await visit('/admin/announcement.php');
            assert.equal(await evaluate("document.getElementById('ann-pin-heading').textContent.trim()"),'Pinned news and announcement');
            assert.ok(await evaluate("document.querySelector('.ann-pin-banner #pinned-content')!==null"));
            assert.equal(await evaluate("document.querySelectorAll('.ann-pin-meter span').length"),5);
            assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'));
            await shot('announcement-pins-'+width);
            await evaluate("document.getElementById('pinned-content').open=true");
            assert.ok(await evaluate("document.querySelector('.ann-pinned-actions form [name=csrf_token]').value.length>0"));
            assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'));
        }
        await visit('/admin/facebook-posts.php');assert.equal(await evaluate("document.getElementById('fb-slideshow-title').textContent.trim()"),'Pinned news and announcement');
        console.log('Pin banner checks passed: heading, five segments, nested manager, CSRF form, desktop/mobile overflow, Facebook heading. No content changed.');return;
    }
    if(process.argv[2]==='visual'){
        await send('Network.setCookie',{...accounts[0],url:base,httpOnly:true});await resize(390);await visit('/admin/facebook-posts.php');
        await evaluate("document.getElementById('fb-results').scrollIntoView({behavior:'instant',block:'start'})");await delay(700);await shot('facebook-posts-mobile-cards');return;
    }
    for(let i=0;i<roles.length;i++){
        await send('Network.setCookie',{...accounts[i],url:base,httpOnly:true});await resize(1440);await visit('/admin/facebook-posts.php');
        assert.ok(await evaluate("Boolean(document.querySelector('.facebook-management'))"));
        assert.ok(await evaluate("document.querySelectorAll('[data-post-id]').length>0"));
        assert.equal(await evaluate("Boolean(document.querySelector('[data-edit-category]'))"),i===0);
        assert.equal(await evaluate("Boolean(document.querySelector('[data-pin]'))"),i===0);
        assert.equal(await evaluate("/Warning:|Fatal error:|Undefined array/.test(document.body.textContent)"),false);
        for(const width of [1440,820,390]){
            await resize(width);assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Page overflow '+width);
            if(i===0)await shot('facebook-posts-'+width);
        }
        await evaluate("document.querySelector('[data-preview]').click()");
        assert.ok(await evaluate("document.getElementById('fb-preview').open"));
        assert.ok(await evaluate("document.getElementById('fb-preview-caption').textContent.length>0"));
        assert.equal(await evaluate("getComputedStyle(document.getElementById('fb-preview-link')).color"),'rgb(255, 255, 255)');
        assert.equal(await evaluate("document.documentElement.scrollWidth>innerWidth"),false);
        if(i===0)await shot('facebook-posts-preview-mobile');
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await until(()=>evaluate("!document.getElementById('fb-preview').open"),'Escape close failed');
        await until(()=>evaluate("Boolean(document.activeElement.dataset.preview)"),'Preview focus was not restored');
        const status=await evaluate("fetch('/admin/action/facebook-posts-handler.php',{method:'POST',body:new URLSearchParams({action:'pin',item_id:'123_456',csrf_token:'invalid'})}).then(r=>r.status)");
        assert.equal(status,403);
        if(i>0){
            const denied=await evaluate("fetch('/admin/action/facebook-posts-handler.php',{method:'POST',body:new URLSearchParams({action:'pin',item_id:'123_456',csrf_token:document.querySelector('meta[name=cms-csrf]').content})}).then(r=>r.status)");
            assert.equal(denied,403);
        }
        await visit('/admin/facebook-posts.php?search=qa-no-match-xyz');
        assert.match(await evaluate("document.querySelector('.fb-empty').textContent"),/No posts match/);
        await visit('/admin/facebook-posts.php?search%5B%5D=x&category%5B%5D=x&pinned%5B%5D=x&from=2026-02-30');
        assert.ok(await evaluate("document.querySelectorAll('[data-post-id]').length>0"));
    }
    await send('Network.setCookie',{...accounts[0],url:base,httpOnly:true});await resize(1440);await visit('/admin/facebook-posts.php');
    const original=await evaluate("JSON.parse(document.getElementById('fb-page-data').textContent)");
    const api=values=>evaluate(`fetch('/admin/action/facebook-posts-handler.php',{method:'POST',body:new URLSearchParams({...${JSON.stringify(values)},csrf_token:document.querySelector('meta[name=cms-csrf]').content})}).then(async r=>({status:r.status,data:await r.json()}))`);
    assert.equal((await api({action:'pin',item_id:[]})).status,422);
    const first=Object.values(original.posts)[0];
    await evaluate("document.querySelector('[data-edit-category]').click()");
    assert.ok(await evaluate("document.getElementById('fb-category-dialog').open"));
    if(Number(first.category_locked)){
        await evaluate("document.getElementById('fb-category-choice').value='none';document.getElementById('fb-category-form').requestSubmit()");
        await until(()=>evaluate("!document.getElementById('fb-category-dialog').open"),'Category save failed');
        assert.match(await evaluate("document.getElementById('fb-feedback').textContent"),/Category updated/);
        assert.equal(await evaluate("document.querySelector('.fb-category').textContent"),'Uncategorized');
        assert.equal((await api({action:'category',item_id:first.fb_post_id,category_choice:first.category_id||'none'})).status,200);
    }else{
        // Saving the current automatic choice preserves the assignment and lock mode.
        await evaluate("document.getElementById('fb-category-choice').value='auto';document.getElementById('fb-category-form').requestSubmit()");
        await until(()=>evaluate("!document.getElementById('fb-category-dialog').open"),'Automatic category save failed');
    }
    await visit('/admin/facebook-posts.php');
    const unpinned=Object.values(original.posts).find(p=>!Number(p.is_pinned));
    if(unpinned&&original.pinned.length>=original.limit){
        const full=await api({action:'pin',item_id:unpinned.fb_post_id});assert.equal(full.status,422);assert.match(full.data.error,/five slideshow slots/);
    }
    // Exercise a reversible Facebook pin change; restore the original pin timestamp in cleanup.
    const snapshotFile='cache/facebook-pin-snapshot.json';
    const snapshot=JSON.parse(php('cache/facebook-pin-restore.php',['snapshot']));fs.writeFileSync(snapshotFile,JSON.stringify(snapshot));
    try{
        const pinnedFacebook=original.pinned.find(p=>p.source==='facebook');
        if(pinnedFacebook){
            const button=`[data-pin="${pinnedFacebook.item_id}"]`;
            if(await evaluate(`Boolean(document.querySelector('${button}'))`)){
                await evaluate(`document.querySelector('${button}').click()`);
                await until(()=>evaluate("!document.querySelector('.facebook-management[aria-busy]')"),'Unpin busy');
                assert.match(await evaluate(`document.querySelector('${button}').textContent`),/Pin/);
                await evaluate(`document.querySelector('${button}').click()`);
                await until(()=>evaluate("!document.querySelector('.facebook-management[aria-busy]')"),'Repin busy');
                assert.match(await evaluate(`document.querySelector('${button}').textContent`),/Unpin/);
            }else if(unpinned){
                await evaluate("document.getElementById('fb-slideshow-manager').open=true");
                await evaluate(`document.querySelector('[data-unpin-id="${pinnedFacebook.item_id}"]').click()`);
                await until(()=>evaluate("!document.querySelector('.facebook-management[aria-busy]')"),'Manager unpin busy');
                assert.equal(await evaluate("document.querySelector('.fb-slot-meter').getAttribute('aria-valuenow')"),'4');
                const current=`[data-pin="${unpinned.fb_post_id}"]`;
                await evaluate(`document.querySelector('${current}').click()`);
                await until(()=>evaluate("!document.querySelector('.facebook-management[aria-busy]')"),'Pin busy');
                assert.match(await evaluate(`document.querySelector('${current}').textContent`),/Unpin/);
                await evaluate(`document.querySelector('${current}').click()`);
                await until(()=>evaluate("!document.querySelector('.facebook-management[aria-busy]')"),'Unpin busy');
                assert.match(await evaluate(`document.querySelector('${current}').textContent`),/Pin/);
                assert.equal((await api({action:'pin',item_id:pinnedFacebook.item_id})).status,200);
            }
        }else if(unpinned&&original.pinned.length<original.limit){
            assert.equal((await api({action:'pin',item_id:unpinned.fb_post_id})).status,200);
            assert.equal((await api({action:'unpin',item_id:unpinned.fb_post_id})).status,200);
        }
    }finally{php('cache/facebook-pin-restore.php',['restore']);}
    await visit('/admin/facebook-posts.php?pinned=1');
    const filteredId=await evaluate("document.querySelector('[data-post-id]').dataset.postId");
    try{
        await evaluate("document.querySelector('[data-pin]').click()");
        await until(()=>evaluate("!document.querySelector('.facebook-management[aria-busy]')"),'Filtered update busy');
        assert.equal(await evaluate(`Boolean(document.querySelector('[data-post-id="${filteredId}"]'))`),false);
        assert.equal(await evaluate("document.querySelector('[name=pinned]').value"),'1');
        assert.equal(await evaluate("document.querySelectorAll('[data-post-id]').length"),original.pinned.length-1);
    }finally{php('cache/facebook-pin-restore.php',['restore']);}
    await resize(390);await visit('/admin/facebook-posts.php');
    await evaluate("document.getElementById('fb-results').scrollIntoView()");await shot('facebook-posts-mobile-cards');
    await visit('/admin/facebook-posts.php?size=10&page=2&category=none');
    assert.equal(await evaluate("document.querySelector('[name=category]').value"),'none');
    await send('Network.deleteCookies',{name:accounts[0].name,url:base});
    assert.match(await evaluate("fetch('/admin/facebook-posts.php').then(r=>r.url)"),/\/(?:login|admin\/login\.php)/);
    assert.equal(errors.length,0,'Browser script exceptions');
    console.log('Facebook browser checks passed: three roles, responsive widths, previews and focus, filters, malformed inputs, category save, CSRF and permission denials, shared pin cap and reversible pin actions.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{for(const s of sessions)php('cache/activity-log-session.php',['destroy',s.value]);}finally{socket?.close();browser.kill();}});
