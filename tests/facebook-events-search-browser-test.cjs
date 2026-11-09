// Uses the local Docker site and a mocked import preview; never confirms an import.
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
    await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});

    const visit=async(route,selector)=>{
        await evaluate('window.__priorSearchPage=true');await send('Page.navigate',{url:'http://localhost:8080'+route});
        await until(()=>evaluate(`!window.__priorSearchPage&&document.readyState==='complete'&&Boolean(document.querySelector(${JSON.stringify(selector)}))`).catch(()=>false),'Page unavailable '+route);
    };
    await visit('/admin/facebook-posts.php','#fb-query');
    await evaluate("window.__searchPageMarker='retained';window.__requestCount=0;const realFetch=window.fetch;window.fetch=(url,options)=>{if(options?.headers?.Accept==='text/html')__requestCount++;return realFetch(url,options);}");
    const first=await evaluate("document.querySelector('.fb-preview-open').textContent.trim()");assert.ok(first);
    const apply=async value=>{
        const count=await evaluate('__requestCount');
        await evaluate(`(()=>{const input=document.querySelector('#fb-query [name=search]');input.focus();input.value=${JSON.stringify(value)};input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await until(()=>evaluate(`__requestCount>${count}&&!document.getElementById('fb-query').hasAttribute('aria-busy')&&new URL(location.href).searchParams.get('search')===${JSON.stringify(value)}`),'Facebook search failed');
        assert.equal(await evaluate('__searchPageMarker'),'retained');
        assert.equal(await evaluate("document.activeElement===document.querySelector('#fb-query [name=search]')"),true);
    };
    await apply('qa-fb-no-match-xyz');
    assert.match(await evaluate("document.querySelector('.fb-empty').textContent"),/No posts match/);
    await evaluate("document.querySelector('.fb-empty a').click()");
    await until(()=>evaluate("!new URL(location.href).searchParams.has('search')&&!document.getElementById('fb-query').hasAttribute('aria-busy')"),'Facebook clear failed');
    assert.equal(await evaluate("document.querySelector('#fb-query [name=search]').value"),'');
    const next=await evaluate("document.querySelector('[data-list-pagination] a:last-child').href");
    await evaluate("document.querySelector('[data-list-pagination] a:last-child').click()");
    await until(()=>evaluate("new URL(location.href).searchParams.get('page')==='2'&&!document.getElementById('fb-query').hasAttribute('aria-busy')"),'Facebook pagination failed');
    assert.equal(await evaluate('__searchPageMarker'),'retained');
    await evaluate('history.back()');
    await until(()=>evaluate("!new URL(location.href).searchParams.has('page')&&!document.getElementById('fb-query').hasAttribute('aria-busy')"),'Facebook history failed');
    await apply(first);
    assert.ok(await evaluate("document.querySelectorAll('[data-post-id]').length>0"));
    await evaluate("document.querySelector('#fb-results [data-preview]').click()");
    await until(()=>evaluate("document.getElementById('fb-preview').open"),'Facebook preview after search failed');
    assert.ok(await evaluate("document.getElementById('fb-preview-caption').textContent.length>0"));
    await evaluate("document.getElementById('fb-preview').close()");
    await evaluate("document.querySelector('#fb-results [data-edit-category]').click()");
    await until(()=>evaluate("document.getElementById('fb-category-dialog').open"),'Category editor after search failed');
    assert.ok(await evaluate("document.getElementById('fb-category-post').textContent.length>0"));
    await evaluate("document.getElementById('fb-category-dialog').close()");
    const categoryFilter=await evaluate("String(Object.values(JSON.parse(document.getElementById('fb-page-data').textContent).posts)[0].category_id||'none')");
    await evaluate(`const select=document.querySelector('#fb-query [name=category]');select.value=${JSON.stringify(categoryFilter)};select.dispatchEvent(new Event('change',{bubbles:true}));`);
    await until(()=>evaluate(`new URL(location.href).searchParams.get('category')===${JSON.stringify(categoryFilter)}&&!document.getElementById('fb-query').hasAttribute('aria-busy')`),'Facebook category filtering failed');
    // Simulate the pin endpoint only; do not alter server-side pin assignments.
    await evaluate(`window.__testPin=document.querySelector('#fb-results [data-pin]');window.__testRow=__testPin.closest('[data-post-id]');window.__testPostId=__testRow.dataset.postId;
        const oldFetch=window.fetch;window.fetch=(url,options)=>{
            if(String(url).includes('facebook-posts-handler.php')){const values=Object.fromEntries(options.body);window.__mockPinned=values.action==='pin'?[{source:'facebook',item_id:values.item_id,title:'UI check'}]:[];return Promise.resolve(Response.json({success:true,message:'UI check',pinned:__mockPinned}));}
            if(options?.headers?.Accept==='text/html'&&window.__mockPinned)return oldFetch(url,options).then(async response=>{const doc=new DOMParser().parseFromString(await response.text(),'text/html'),node=doc.getElementById('fb-page-data'),data=JSON.parse(node.textContent);data.pinned=__mockPinned;node.textContent=JSON.stringify(data);return new Response(doc.documentElement.outerHTML,{headers:{'Content-Type':'text/html'}});});
            return oldFetch(url,options);
        };__testPin.disabled=false;window.__wasPinned=__testRow.classList.contains('is-pinned');__testPin.click();`);
    await until(()=>evaluate("document.querySelector('.facebook-management').getAttribute('aria-busy')!=='true'"),'Pin update failed');
    assert.equal(await evaluate("Array.from(document.querySelectorAll('[data-post-id]')).find(row=>row.dataset.postId===__testPostId).classList.contains('is-pinned')"),!await evaluate('__wasPinned'));
    // Filters must remain enabled after pinning.
    assert.equal(await evaluate("document.querySelector('#fb-query [name=search]').disabled"),false);
    console.log('Facebook: background search, focus, empty results, clear, real pagination/history, preview/category dialogs and mocked pin updates passed.');
    await visit('/admin/events.php','#evtSearch');
    await evaluate("window.__eventSearchMarker='retained';window.__eventSearchRequests=0;const oldFetch=window.fetch;window.fetch=(...args)=>{__eventSearchRequests++;return oldFetch(...args);}");
    const title=await evaluate("document.querySelector('tr.evt-row').dataset.title");assert.ok(title);
    const initialRequests=await evaluate('__eventSearchRequests');
    await evaluate("(()=>{const input=document.getElementById('evtSearch');input.focus();input.value='qa-events-no-match-xyz';input.dispatchEvent(new Event('input',{bubbles:true}));})()");
    assert.equal(await evaluate("Array.from(document.querySelectorAll('tr.evt-row')).filter(row=>row.style.display!=='none').length"),0);
    assert.equal(await evaluate("document.getElementById('evtNoMatch').style.display"),'');
    await evaluate(`(()=>{const input=document.getElementById('evtSearch');input.value=${JSON.stringify(title)};input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.ok(await evaluate("Array.from(document.querySelectorAll('tr.evt-row')).some(row=>row.style.display!=='none')"));
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    assert.equal(await evaluate('__eventSearchMarker'),'retained');
    const status=await evaluate("Array.from(document.querySelectorAll('tr.evt-row')).find(row=>row.style.display!=='none').dataset.status");
    await evaluate(`document.querySelector('.evt-chip[data-filter=${JSON.stringify(status)}]').click()`);
    assert.equal(await evaluate("Array.from(document.querySelectorAll('tr.evt-row')).filter(row=>row.style.display!=='none').every(row=>row.dataset.status===document.querySelector('.evt-chip.active').dataset.filter)"),true);
    const category=await evaluate("Array.from(document.querySelectorAll('tr.evt-row')).find(row=>row.style.display!=='none').dataset.category");
    if(category)await evaluate(`document.getElementById('evtCatFilter').value=${JSON.stringify(category)};document.getElementById('evtCatFilter').dispatchEvent(new Event('change',{bubbles:true}));`);
    const dept=await evaluate("Array.from(document.getElementById('evtDeptFilter').options).find(option=>/^[0-9]+$/.test(option.value))?.value");
    if(dept){
        await evaluate(`document.getElementById('evtDeptFilter').value=${JSON.stringify(dept)};document.getElementById('evtDeptFilter').dispatchEvent(new Event('change',{bubbles:true}));`);
        assert.equal(await evaluate("Array.from(document.querySelectorAll('tr.evt-row')).filter(row=>row.style.display!=='none').every(row=>{const ids=JSON.parse(row.dataset.depts||'[]');return !ids.length||ids.includes(Number(document.getElementById('evtDeptFilter').value));})"),true);
    }
    assert.equal(await evaluate('__eventSearchRequests'),initialRequests,'Events search and filters make no network/navigation request');
    assert.equal(await evaluate('__eventSearchMarker'),'retained');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'));
    }
    console.log('Events: existing instant search, empty/matching results, Enter, combined status/category/department filters and zero search requests verified. No events or posts changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(session)sessionTool(['destroy',session.value]);socket?.close();browser.kill();});
