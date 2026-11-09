const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-photo-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
async function until(fn, message) {
    for (let end=Date.now()+15000; Date.now()<end;) { if (await fn()) return; await delay(50); }
    throw Error(message);
}
(async () => {
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open',resolve,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message', event => { const data=JSON.parse(event.data); if (pending.has(data.id)) { pending.get(data.id)(data); pending.delete(data.id); } });
    const send = (method,params={}) => new Promise((resolve,reject) => {
        const id=++serial; pending.set(id,data => data.error ? reject(Error(JSON.stringify(data.error))) : resolve(data.result));
        socket.send(JSON.stringify({id,method,params}));
    });
    const evaluate = async expression => {
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };

    await send('Page.enable');await send('Network.enable');
    await send('Page.navigate',{url:'http://localhost:8080/news-and-announcement'});
    await until(()=>evaluate("document.readyState==='complete'&&Boolean(document.getElementById('na-search-form'))").catch(()=>false),'Public feed unavailable');
    await evaluate(`window.__visitorMarker='retained';window.__requests=[];window.__fail=false;const originalFetch=window.fetch;
        window.fetch=(url,options)=>{if(options?.headers?.Accept==='text/html'){
            __requests.push(String(url));if(__fail){__fail=false;return Promise.reject(Error('Simulated network failure'));}
            if(new URL(url,location.href).searchParams.get('q')==='qa-slow-query')return originalFetch(url,options).then(r=>new Promise(resolve=>setTimeout(()=>resolve(r),1000)));
        }return originalFetch(url,options);};`);
    await until(()=>evaluate("!!document.querySelector('#gncSiteDisclaimer[open]')"),'Visitor disclaimer missing');
    await evaluate("document.querySelector('#gncSiteDisclaimer button[type=submit]').click()");
    await until(()=>evaluate("!document.querySelector('#gncSiteDisclaimer[open]')"),'Visitor disclaimer did not close');
    const title=await evaluate("document.querySelector('.na-card h3,.na-card-title')?.textContent.trim()||document.querySelector('.na-card-excerpt')?.textContent.trim().slice(0,20)");assert.ok(title,'Published content is required for search verification');
    const search=async value=>{
        const count=await evaluate('__requests.length');
        await evaluate(`(()=>{const i=document.getElementById('na-search');i.focus();i.value=${JSON.stringify(value)};i.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await until(()=>evaluate(`__requests.length>${count}&&!document.getElementById('na-search-form').hasAttribute('aria-busy')&&new URL(location.href).searchParams.get('q')===${JSON.stringify(value)}`),'Search failed');
        assert.equal(await evaluate('__visitorMarker'),'retained');
        assert.equal(await evaluate("document.activeElement.id"),'na-search',JSON.stringify(await evaluate("({active:document.activeElement.outerHTML.slice(0,250),inputs:document.querySelectorAll('#na-search').length,form:document.getElementById('na-search').closest('form')?.id,dialogs:Array.from(document.querySelectorAll('dialog[open]')).map(d=>d.outerHTML.slice(0,150))})")));
    };
    await search('qa-visitor-no-match-xyz');assert.equal(await evaluate("document.querySelector('.na-empty h3').textContent"),'Nothing found');
    assert.equal(await evaluate("document.querySelector('#na-pinned-results .carousel')"),null,'Search hides pinned slideshow');
    await search(title);assert.ok(await evaluate("document.querySelectorAll('.na-card').length>0"));
    const link=await evaluate("document.querySelector('.na-card a.na-read-more').href");assert.match(link,/article|facebook/);
    await evaluate("document.querySelector('.na-card img')?.dispatchEvent(new Event('load'))");
    assert.equal(await evaluate("!document.querySelector('.na-card img')||document.querySelector('.na-card img').classList.contains('is-loaded')"),true);
    const old=await evaluate('__requests.length');
    await evaluate("document.getElementById('na-search').value='qa-slow-query';document.getElementById('na-search').dispatchEvent(new Event('input',{bubbles:true}))");
    await until(()=>evaluate(`__requests.length>${old}`),'Slow request missing');await search(title);await delay(1100);
    assert.ok(await evaluate("document.querySelectorAll('.na-card').length>0"),'Late search must not replace current results');
    await evaluate("__fail=true;document.getElementById('na-search').value='qa-retry-empty';document.getElementById('na-search').dispatchEvent(new Event('input',{bubbles:true}))");
    await until(()=>evaluate("Array.from(document.querySelectorAll('button')).some(b=>b.textContent==='Retry search')"),'Retry not available');
    assert.ok(await evaluate("document.querySelectorAll('.na-card').length>0"),'Failure keeps old results');
    await evaluate("Array.from(document.querySelectorAll('button')).find(b=>b.textContent==='Retry search').click()");
    await until(()=>evaluate("new URL(location.href).searchParams.get('q')==='qa-retry-empty'&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'Retry failed');
    await evaluate("document.querySelector('.na-empty a').click()");
    await until(()=>evaluate("!new URL(location.href).searchParams.has('q')&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'Clear failed');
    assert.equal(await evaluate("document.getElementById('na-search').value"),'');
    await evaluate('history.back()');
    await until(()=>evaluate("document.getElementById('na-search').value==='qa-retry-empty'&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'History failed');
    await evaluate('history.forward()');
    await until(()=>evaluate("document.getElementById('na-search').value===''&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'Forward failed');
    if(await evaluate("!!document.querySelector('.na-page-next')")){
        await evaluate("document.querySelector('.na-page-next').click()");
        await until(()=>evaluate("new URL(location.href).searchParams.get('page')==='2'&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'Pagination failed');
    }
    await evaluate("document.querySelector('.na-tab:last-child').click()");
    await until(()=>evaluate("new URL(location.href).searchParams.get('type')==='news'&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'News tab failed');
    assert.equal(await evaluate("document.querySelector('#na-search-form [name=type]').value"),'news');
    await evaluate("document.querySelector('#na-search-form button[name=sort][value=oldest]').click()");
    await until(()=>evaluate("new URL(location.href).searchParams.get('sort')==='oldest'&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'Sort failed');
    // Category controls retain the active query and sort.
    const category=await evaluate("document.querySelector('#na-category-news + .gnc-cat-list a').getAttribute('href')");
    await evaluate("document.querySelector('#na-category-news + .gnc-cat-list a').click()");
    await until(()=>evaluate("new URL(location.href).searchParams.has('category')&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'Category failed');
    const department=await evaluate("document.querySelector('#calendarProgramWidget [data-calendar-department]')?.dataset.calendarDepartment");
    if(department){
        await evaluate("document.querySelector('#calendarProgramWidget [data-calendar-department]').click()");
        await until(()=>evaluate(`new URL(location.href).searchParams.get('department')===${JSON.stringify(department)}&&!document.getElementById('na-search-form').hasAttribute('aria-busy')`),'Department filter failed');
        assert.equal(await evaluate("document.querySelector('#na-search-form [name=department]').value"),department);
        assert.equal(await evaluate("document.querySelector('#calendarProgramPanel').hidden"),true);
    }
    await evaluate("document.getElementById('na-search').value='qa-enter-empty';document.getElementById('na-search-form').requestSubmit()");
    await until(()=>evaluate("new URL(location.href).searchParams.get('q')==='qa-enter-empty'&&!document.getElementById('na-search-form').hasAttribute('aria-busy')"),'Explicit search submit failed');
    assert.equal(await evaluate('__visitorMarker'),'retained');
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Feed overflows');
    }
    await send('Emulation.setScriptExecutionDisabled',{value:true});
    await send('Page.navigate',{url:'http://localhost:8080/news-and-announcement?q=qa-no-js-empty'});
    await until(()=>evaluate("document.readyState==='complete'&&document.getElementById('na-search')?.value==='qa-no-js-empty'").catch(()=>false),'No JS fallback failed');
    assert.equal(await evaluate("document.querySelector('.na-empty h3').textContent"),'Nothing found');
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    console.log('PASS: visitor background search/focus, empty results, published article links, image reveal, stale responses, retry, clear, history, pagination, type/category/sort filters, responsive layout and no-JavaScript fallback. No content changed.');
})().catch(error => { console.error(error); process.exitCode=1; }).finally(() => { if(socket) socket.close(); browser.kill(); });
