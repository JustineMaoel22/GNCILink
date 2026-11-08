const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket,auth;
const session=args=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
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





    await send('Page.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    const base='http://localhost:8080';
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    const visit=async url=>{
        await send('Page.navigate',{url});
        await until(()=>evaluate("window.VE?.state?.doc&&document.getElementById('veLoader').hidden&&!document.getElementById('veOutlineBtn').disabled"),'Editor unavailable: '+url);
    };
    await visit(base+'/admin/content-edit.php?page=home');
    const pages=await evaluate("[...document.querySelectorAll('#vePageMenu [data-ve-nav]')].map(a=>a.href).filter(url=>!/[?&]page=(hub|review)(?:&|$)/.test(url))");
    for(const url of [...new Set(pages)]){
        await visit(url);
        const before=await evaluate('VE.adapter().dirtyCount()');
        assert.equal(await evaluate("document.getElementById('veOutlineBtn').hidden"),false);
        await evaluate("document.getElementById('veOutlineBtn').click()");
        const rows=await evaluate("[...document.querySelectorAll('.ve-outline-row')].map(e=>e.textContent.trim())");
        assert.ok(rows.length>0,'Every editor page offers a section destination: '+url);
        assert.ok(rows.every(Boolean),'Section destinations have labels');
        assert.ok(await evaluate("[...document.querySelectorAll('.ve-outline-row')].every(e=>e.querySelector('.ve-dot')&&e.querySelector('small')?.textContent)"),'Every section has a status dot and label');
        await evaluate("[...document.querySelectorAll('.ve-outline-row')].at(-1).click()");await delay(100);
        assert.equal(await evaluate("document.getElementById('vePop').hidden"),true,'Choosing a section closes the menu');
        assert.equal(await evaluate('VE.adapter().dirtyCount()'),before,'Jumping to a section does not edit its content');
        console.log(new URL(url).searchParams.get('page')+': '+rows.join(' | '));
    }
    await visit(base+'/admin/content-edit.php?page=college-departments&sub=cased');
    const before=await evaluate('VE.adapter().dirtyCount()');
    await evaluate(`(()=>{const section=VE.state.doc.createElement('section');section.innerHTML='<h2>New dynamic section</h2>';section.id='outline-test-section';VE.state.doc.querySelector('main').appendChild(section);})()`);
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});await delay(150);
        await evaluate("document.getElementById('veOutlineBtn').click()");
        const menu=await evaluate(`(()=>{const el=document.querySelector('.ve-outline'),r=el.closest('.ve-pop').getBoundingClientRect();return {left:r.left,right:r.right,bottom:r.bottom,labels:[...el.querySelectorAll('strong')].map(e=>e.textContent)}})()`);
        assert.ok(menu.left>=0&&menu.right<=width+1&&menu.bottom<=900,'Menu fits viewport at '+width);
        assert.ok(menu.labels.includes('New dynamic section'),'New sections are discovered when menu reopens');
        await evaluate("[...document.querySelectorAll('.ve-outline-row')].find(e=>e.querySelector('strong').textContent==='New dynamic section').click()");
        assert.equal(await evaluate("VE.state.doc.activeElement.id"),'outline-test-section','Keyboard focus follows section navigation');
        assert.equal(await evaluate('VE.adapter().dirtyCount()'),before);
    }
    // Status changes must reflect unpublished content, rather than always say Live.
    await evaluate("VE.state.doc.getElementById('outline-test-section').setAttribute('data-ve-status','draft');document.getElementById('veOutlineBtn').click()");
    assert.equal(await evaluate("[...document.querySelectorAll('.ve-outline-row')].find(e=>e.querySelector('strong').textContent==='New dynamic section').querySelector('small').textContent"),'Draft · not live');
    await evaluate("VE.state.doc.getElementById('outline-test-section').removeAttribute('data-ve-status');document.getElementById('veOutlineBtn').click()");
    assert.equal(await evaluate("[...document.querySelectorAll('.ve-outline-row')].find(e=>e.querySelector('strong').textContent==='New dynamic section').querySelector('small').textContent"),'Live');
    await evaluate(`(()=>{const el=VE.state.doc.querySelector('[data-dept-header-subject]');window.originalOutlineTitle=el.textContent;el.textContent+=' updated';el.dispatchEvent(new VE.state.win.Event('input',{bubbles:true}));document.getElementById('veOutlineBtn').click();})()`);
    assert.ok(await evaluate("[...document.querySelectorAll('.ve-outline-row small')].some(e=>e.textContent==='Unsaved')"),'Edited section shows an unsaved warning');
    await evaluate(`(()=>{const el=VE.state.doc.querySelector('[data-dept-header-subject]');el.textContent=originalOutlineTitle;el.dispatchEvent(new VE.state.win.Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('VE.adapter().dirtyCount()'),before,'Reverting the edit restores the original state');
    // Existing text-page menus keep their per-section publication indicators.
    await visit(base+'/admin/content-edit.php?page=vision-mission');
    await evaluate("document.getElementById('veOutlineBtn').click()");
    assert.deepEqual(await evaluate("[...document.querySelectorAll('.ve-outline-row strong')].map(e=>e.textContent)"),['Vision','Mission']);
    assert.ok(await evaluate("[...document.querySelectorAll('.ve-outline-row small')].every(e=>e.textContent.length>0)"));
    console.log('PASS: all page-picker editors expose section navigation; jumping preserves content, dynamic sections appear, responsive menus fit, and text-page status indicators remain.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(socket)socket.close();browser.kill();});
