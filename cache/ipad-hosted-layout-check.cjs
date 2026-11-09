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
    await send('Emulation.setDeviceMetricsOverride',{width:1032,height:1376,deviceScaleFactor:1,mobile:true});
    for(const base of ['http://localhost:8080','https://gncipampanga.com']){
        await send('Page.navigate',{url:base+'/departments/cased'});
        await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.dept-hero-title')"),'Department unavailable');await delay(1000);
        console.log(base,JSON.stringify(await evaluate(`(()=>{const selectors=['.dept-hero','.dept-hero-title','.dept-hero-prefix','.dept-hero-subject','.dept-hero-content','.dept-hero-inner'];return {headingScale:!!window.GNCTextLayout,marker:!!document.querySelector('[data-gnc-responsive-heading]'),items:selectors.map(s=>{const e=document.querySelector(s),r=e.getBoundingClientRect(),c=getComputedStyle(e);return {s,x:r.x,y:r.y,w:r.width,h:r.height,font:c.fontSize,line:c.lineHeight,position:c.position,style:e.getAttribute('style')}}),assets:[...document.querySelectorAll('script[src],link[rel=stylesheet]')].map(e=>e.src||e.href).filter(s=>/text-layout|college-dept/.test(s))}})()`)));
        console.log('SAVED',base,JSON.stringify(await evaluate("Array.from(GNCTextLayout.targets()).filter(([id,el])=>el.closest('[data-gnc-responsive-heading]')).map(([id,el])=>({id,tag:el.className,box:GNC_TEXT_LAYOUT[id]}))")));
        const asset=await evaluate("fetch('/assets/js/text-layout.js').then(r=>r.text())");console.log('JS_MATCH',base,asset===fs.readFileSync('assets/js/text-layout.js','utf8'));
        const shot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,base.startsWith('https')?'ipad-live.png':'ipad-local.png'),Buffer.from(shot.data,'base64'));
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});
