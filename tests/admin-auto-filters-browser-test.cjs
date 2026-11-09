// Uses a temporary local admin session; no content is saved.
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const docker = args => {
    const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if(r.status!==0) throw Error(r.stderr||r.stdout);
    return r.stdout;
};
const sessions = [];
const session = role => {const s=JSON.parse(docker([role]));sessions.push(s);return s;};
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-logs-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket;
(async()=>{
    const admin=session('Super Admin');
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setCookie',{...admin,url:base,httpOnly:true});
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page load timeout');};

    const routes = [
        ['/admin/submissions.php', 'approval-filters', 'status', 'pending', 'q'],
        ['/admin/announcement.php', 'ann-query', 'status', 'published', 'search'],
        ['/admin/news.php', 'news-query', 'status', 'published', 'search'],
        ['/admin/logs.php', 'logs-filters', 'sort', 'oldest', 'q'],
        ['/admin/facebook-posts.php', null, 'pinned', '1', 'search']
    ];
    for (const [route, id, key, value, search] of routes) {
        await visit(route);
        await until(()=>evaluate(`document.readyState==='complete' && Boolean(document.querySelector(${JSON.stringify(id ? "#"+id : ".fb-filters")}))`),route+' filters unavailable');
        assert.ok(await evaluate("!document.querySelector('form[data-auto-filters] button[type=submit]')"),route+' Apply button remains');
        if (id === 'approval-filters') assert.equal(await evaluate("document.querySelector('[name=sort]').value"),'newest');
        for (const width of [1440,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
            assert.ok(await evaluate("document.querySelector('form[data-auto-filters]').getBoundingClientRect().right<=innerWidth"),route+' filter toolbar overflow');
        }
        await evaluate(`(()=>{const f=document.querySelector('form[data-auto-filters]');const field=f.elements.namedItem(${JSON.stringify(key)});field.value=${JSON.stringify(value)};field.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until(()=>evaluate(`new URL(location.href).searchParams.get(${JSON.stringify(key)})===${JSON.stringify(value)} && document.readyState==='complete' && Boolean(document.querySelector('form[data-auto-filters]:not([aria-busy])'))`),route+' did not auto-apply selection').catch(async error=>{console.error(await evaluate("JSON.stringify({url:location.href,forms:Array.from(document.forms).map(f=>({id:f.id,auto:f.hasAttribute('data-auto-filters')})),busy:document.querySelector('form[data-auto-filters]')?.getAttribute('aria-busy')})"));throw error;});
        await evaluate(`(()=>{window.__filterSubmits=0;const f=document.querySelector('form[data-auto-filters]');f.addEventListener('submit',()=>window.__filterSubmits++);const field=f.elements.namedItem(${JSON.stringify(search)});field.value='GNCI';field.dispatchEvent(new Event('input',{bubbles:true}));field.value='GNCI filter check';field.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await delay(100);
        assert.equal(await evaluate('window.__filterSubmits'),0,route+' search submitted before debounce');
        await until(()=>evaluate(`new URL(location.href).searchParams.get(${JSON.stringify(search)})==='GNCI filter check' && document.readyState==='complete' && Boolean(document.querySelector('form[data-auto-filters]:not([aria-busy])'))`),route+' search did not auto-apply');
        assert.equal(await evaluate(`new URL(location.href).searchParams.get(${JSON.stringify(key)})`),value,route+' lost selected filter');
        assert.ok(await evaluate("!new URL(location.href).searchParams.has('page')"),route+' did not reset pagination');
        await evaluate(`(()=>{const f=document.querySelector('form[data-auto-filters]');const field=f.elements.namedItem(${JSON.stringify(search)});field.value='';field.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await until(()=>evaluate(`new URL(location.href).searchParams.get(${JSON.stringify(search)})==='' && document.readyState==='complete' && Boolean(document.querySelector('form[data-auto-filters]:not([aria-busy])'))`),route+' clearing search did not apply');
        console.log(route+': automatic selection, debounced search, clearing search, combined filters, pagination reset and desktop/mobile toolbar passed.');
    }
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
    for(const s of sessions)docker(['destroy',s.value]);
    socket?.close();browser.kill();
});
