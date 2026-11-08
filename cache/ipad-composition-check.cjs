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
    const metrics=`(()=>{const hero=document.querySelector('.dept-hero').getBoundingClientRect();return ['.dept-hero-title','.dept-hero-prefix','.dept-hero-subject'].map(s=>{const e=document.querySelector(s),r=e.getBoundingClientRect(),c=getComputedStyle(e);return {s,x:(r.x-hero.x)/hero.width,y:(r.y-hero.y)/hero.width,w:r.width/hero.width,h:r.height/hero.width,font:parseFloat(c.fontSize)/hero.width,text:e.textContent,family:c.fontFamily,weight:c.fontWeight,spacing:c.letterSpacing,html:e.innerHTML,transform:c.transform,zoom:c.zoom,fonts:[...document.fonts].filter(f=>f.family.includes("Noto")).map(f=>({family:f.family,status:f.status})),firstLine:(()=>{const a=document.createRange();a.selectNodeContents(e);return [...a.getClientRects()].map(r=>({w:r.width,h:r.height}))})()}})})()`;
    for(const width of [1920,1032]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1376,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:'http://localhost:8080/departments/cased'});
        await until(()=>evaluate("document.readyState==='complete'&&!!window.GNCTextLayout"),'Department unavailable');
        await evaluate('document.fonts.ready');await delay(500);
        console.log(width,JSON.stringify(await evaluate(metrics)));
        await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close());document.querySelector('[data-privacy-action=reject]')?.click()");await delay(400);
        const shot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,'ipad-composition-'+width+'.png'),Buffer.from(shot.data,'base64'));
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});


