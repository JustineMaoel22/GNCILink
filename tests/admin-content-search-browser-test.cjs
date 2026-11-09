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

    const errors=[];socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='Runtime.exceptionThrown')errors.push(p.params.exceptionDetails.text);});
    for(const kind of ['news','announcement']){
        await evaluate('window.__oldSearchPage=true');
        await send('Page.navigate',{url:'http://localhost:8080/admin/'+kind+'.php'});
        const query=kind==='news'?'news-query':'ann-query',table=kind==='news'?'news-table':'announcements-table';
        await until(()=>evaluate(`!window.__oldSearchPage&&document.readyState==='complete'&&Boolean(window.GNCContentSearch)&&Boolean(document.getElementById(${JSON.stringify(query)}))`).catch(()=>false),'Page unavailable');
        await evaluate(`window.__searchMarker='retained';window.__searchRequests=[];window.__failNextSearch=false;
            const realFetch=window.fetch;
            window.fetch=(url,options)=>{
                if(options?.headers?.Accept==='text/html'){
                    __searchRequests.push(String(url));
                    if(__failNextSearch){__failNextSearch=false;return Promise.reject(Error('Simulated network failure'));}
                    if(new URL(url,location.href).searchParams.get('search')==='qa-slow-query')return realFetch(url,options).then(response=>new Promise(resolve=>setTimeout(()=>resolve(response),1000)));
                }
                return realFetch(url,options);
            };
        `);
        const title=await evaluate(`document.querySelector('#${table} .ann-title')?.textContent.trim()`);assert.ok(title,'Need an existing item to verify search');
        const input=`document.querySelector('#${query} [name=search]')`;
        const search=async text=>{
            const before=await evaluate('__searchRequests.length');
            await evaluate(`(()=>{const input=${input};input.focus();input.value=${JSON.stringify(text)};input.dispatchEvent(new Event('input',{bubbles:true}));})()`);
            await until(()=>evaluate(`__searchRequests.length>${before}&&!document.getElementById('${query}').hasAttribute('aria-busy')&&new URL(location.href).searchParams.get('search')===${JSON.stringify(text)}`),'Search did not finish: '+kind);
        };
        await search('qa-no-match-xyz');
        assert.match(await evaluate(`document.getElementById('${table}').textContent`),/No (?:news articles|announcements) match/);
        assert.equal(await evaluate('__searchMarker'),'retained');
        assert.equal(await evaluate(`document.activeElement===${input}`),true,'Search keeps keyboard focus');
        await search(title);
        assert.ok(await evaluate(`document.querySelectorAll('#${table} tbody .ann-row').length>0`));
        // Select a row, then verify the same bulk controller sees the refreshed rows.
        await evaluate(`document.querySelector('#${table} [data-bulk-item]')?.click()`);
        assert.equal(await evaluate("document.querySelector('[data-bulk-count]').textContent"),'1 selected');
        const before=await evaluate('__searchRequests.length');
        await evaluate(`(()=>{const i=${input};i.value='qa-slow-query';i.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await until(()=>evaluate(`__searchRequests.length>${before}`),'Delayed request not sent');
        await search(title);await delay(1100);
        assert.ok(await evaluate(`document.querySelectorAll('#${table} tbody .ann-row').length>0`),'Older results must not replace the current search');
        assert.equal(await evaluate("document.querySelector('[data-bulk-count]').textContent"),'0 selected');
        await evaluate(`window.__failNextSearch=true;const input=${input};input.value='qa-failure-query';input.dispatchEvent(new Event('input',{bubbles:true}));`);
        await until(()=>evaluate("Array.from(document.querySelectorAll('button')).some(b=>b.textContent==='Retry search')"),'Search failure needs retry feedback');
        assert.ok(await evaluate(`document.querySelectorAll('#${table} tbody .ann-row').length>0`),'Failure retains the previous results');
        await evaluate("Array.from(document.querySelectorAll('button')).find(b=>b.textContent==='Retry search').click()");
        await until(()=>evaluate("new URL(location.href).searchParams.get('search')==='qa-failure-query'&&!document.querySelector('form[data-async-list]').hasAttribute('aria-busy')"),'Retry failed');
        await evaluate("document.querySelector('.ann-empty-row a').click()");
        await until(()=>evaluate(`!new URL(location.href).searchParams.has('search')&&!document.getElementById('${query}').hasAttribute('aria-busy')`),'Clear filters failed');
        assert.equal(await evaluate(`${input}.value`),'');
        await evaluate('history.back()');
        await until(()=>evaluate(`new URL(location.href).searchParams.get('search')==='qa-failure-query'&&${input}.value==='qa-failure-query'&&!document.getElementById('${query}').hasAttribute('aria-busy')`),'Back must restore the search');
        await evaluate('history.forward()');
        await until(()=>evaluate(`!new URL(location.href).searchParams.has('search')&&!document.getElementById('${query}').hasAttribute('aria-busy')`),'Forward failed');
        // Exercise pagination without inserting persistent test records.
        await evaluate(`(()=>{const a=document.createElement('a');a.href='?search='+encodeURIComponent(${JSON.stringify(title)})+'&page=2&size=10';a.textContent='Test pagination';document.querySelector('[data-list-pagination]').append(a);a.click();})()`);
        await until(()=>evaluate(`new URL(location.href).searchParams.get('page')==='2'&&!document.getElementById('${query}').hasAttribute('aria-busy')`),'Pagination should update without navigation');
        assert.equal(await evaluate('__searchMarker'),'retained');
        const previewAttr=kind==='news'?'data-news-preview':'data-preview-id';
        await evaluate(`document.querySelector('#${table} [${previewAttr}]').click()`);
        await until(()=>evaluate("document.getElementById('ann-preview-dialog').open"),'Preview after search failed');
        assert.ok(await evaluate("document.getElementById('ann-preview-title').textContent.length>0"));
        await evaluate("document.getElementById('ann-preview-dialog').close()");
        const modal=kind==='news'?'newsModal':'announcementModal';
        await evaluate(`document.querySelector('.btn-edit-${kind}').click()`);
        await until(()=>evaluate(`document.getElementById('${modal}').classList.contains('show')`),'Edit after search failed');
        assert.ok(await evaluate("document.getElementById('modal-title').value.length>0"));
        await until(()=>evaluate(`bootstrap.Modal.getInstance(document.getElementById('${modal}'))._isTransitioning===false`),'Editor opening transition');
        await evaluate(`bootstrap.Modal.getInstance(document.getElementById('${modal}')).hide()`);
        await until(()=>evaluate(`document.getElementById('${modal}').style.display==='none'`),'Editor failed to close');
        await evaluate(`document.querySelector('.btn-delete-${kind}').click()`);
        const deleteModal=kind==='news'?'deleteNewsModal':'deleteAnnouncementModal';
        await until(()=>evaluate(`document.getElementById('${deleteModal}').classList.contains('show')`),'Delete dialog after search failed');
        assert.ok(await evaluate(`document.getElementById('delete-${kind}-id').value.length>0`));
        await until(()=>evaluate(`bootstrap.Modal.getInstance(document.getElementById('${deleteModal}'))._isTransitioning===false`),'Delete opening transition');
        await evaluate(`bootstrap.Modal.getInstance(document.getElementById('${deleteModal}')).hide()`);
        await until(()=>evaluate(`document.getElementById('${deleteModal}').style.display==='none'`),'Delete dialog failed to close');
        for(const width of [1440,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No overflow after search');
        }
        const archiveForm=await evaluate(`Boolean(document.querySelector('#${table} .ann-inline-form [name=action][value=archive]'))`);
        if(archiveForm){
            await evaluate(`document.querySelector('#${table} .ann-inline-form [name=action][value=archive]').form.requestSubmit()`);
            await until(()=>evaluate("Boolean(document.querySelector('.gnc-dialog[open]'))"),'Archive confirmation after search failed');
            assert.match(await evaluate("document.querySelector('.gnc-dialog[open]').textContent"),/Archive this (?:article|announcement)/i);
            await evaluate("document.querySelector('.gnc-dialog[open] .gnc-dialog-cancel').click()");
            await until(()=>evaluate("!document.querySelector('.gnc-dialog[open]')"),'Archive cancel failed');
        }
        // Prevent the test from submitting any real pin/unpin changes.
        await evaluate(`window.__pinSubmits=0;HTMLFormElement.prototype.submit=function(){__pinSubmits++;};const form=Array.from(document.querySelectorAll('#${table} .ann-inline-form')).find(f=>['pin','unpin'].includes(f.elements.action.value));if(!form)throw Error('Pin form absent');form.requestSubmit();`);
        await until(()=>evaluate('__pinSubmits===1'),'Pin action after search failed');
        assert.equal(await evaluate('__searchMarker'),'retained');
        console.log(kind+': no-refresh search, retained focus, empty results, out-of-order responses, retry, clear, history, pagination, previews, edit/delete dialogs, pin submission and bulk reset passed.');
    }
    await send('Emulation.setScriptExecutionDisabled',{value:true});
    for(const kind of ['news','announcement']){
        const query=kind==='news'?'news-query':'ann-query';
        await send('Page.navigate',{url:'http://localhost:8080/admin/'+kind+'.php?search=qa-no-javascript-match'});
        await until(()=>evaluate(`document.readyState==='complete'&&document.getElementById('${query}')?.querySelector('[name=search]').value==='qa-no-javascript-match'`).catch(()=>false),'No-JavaScript fallback failed');
        assert.match(await evaluate("document.querySelector('.ann-empty-row').textContent"),/No (?:news articles|announcements) match/);
    }
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    assert.deepEqual(errors,[],'Browser errors');
    console.log('No articles, announcements or pin assignments changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(session)sessionTool(['destroy',session.value]);socket?.close();browser.kill();});
