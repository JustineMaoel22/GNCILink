const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
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



    await send('Page.enable');
    const script=fs.readFileSync(path.join(__dirname,'../assets/js/page-entrance.js'),'utf8');
    const style=fs.readFileSync(path.join(__dirname,'../assets/css/page-entrance.css'),'utf8');
    const setup=async(explicit='',heading='News and Announcement',ready='loading')=>{
        await evaluate(`document.documentElement.className='';document.head.innerHTML='<title>History of GNC</title>';document.body.innerHTML='<div id="gncPageEntrance" aria-hidden="true"><i></i><div class="gnc-page-entrance-label"><span></span></div></div><div class="gh-open">History of GNC</div>';document.head.appendChild(Object.assign(document.createElement('style'),{textContent:${JSON.stringify(style)}}));window.entranceLabel=document.querySelector('#gncPageEntrance span');window.labels=[];new MutationObserver(()=>labels.push(entranceLabel.textContent)).observe(entranceLabel,{childList:true});document.getElementById('gncPageEntrance').dataset.openingTitle=${JSON.stringify(explicit)};Object.defineProperty(document,'readyState',{configurable:true,get:()=>${JSON.stringify(ready)}});`);
        await evaluate(script);
        if(ready==='loading'){
            assert.equal(await evaluate('entranceLabel.textContent'),'','No provisional history title');
            assert.equal(await evaluate("getComputedStyle(document.querySelector('.gnc-page-entrance-label')).visibility"),'hidden');
            assert.equal(await evaluate("getComputedStyle(document.querySelector('.gh-open')).display"),'none','Old page-specific transition stays hidden');
            await evaluate(`document.body.insertAdjacentHTML('beforeend','<main><h1>'+${JSON.stringify(heading)}+'</h1></main>');document.dispatchEvent(new Event('DOMContentLoaded'));`);
        }
    };
    await setup();
    assert.equal(await evaluate('entranceLabel.textContent'),'News and Announcement');
    assert.equal(await evaluate("document.documentElement.classList.contains('gnc-entrance-pending')"),false);
    assert.equal(await evaluate("labels.includes('History of GNC')"),false);
    await setup('News and Announcement','Stay Updated, Stay Informed');
    assert.equal(await evaluate('entranceLabel.textContent'),'News and Announcement','Explicit title overrides heading');
    await setup('Guagua National Colleges','Welcome');
    assert.equal(await evaluate('entranceLabel.textContent'),'Guagua National Colleges');
    await setup('Programs','','complete');
    assert.equal(await evaluate('entranceLabel.textContent'),'Programs','Already parsed pages work');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await evaluate("document.documentElement.className='';document.body.innerHTML='<div id=gncPageEntrance><span></span></div>'");await evaluate(script);
    assert.equal(await evaluate("document.getElementById('gncPageEntrance')===null"),true);
    console.log('PASS: no interim history title, delayed heading resolution, explicit titles, legacy overlay suppression, already-parsed pages and reduced motion.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});
