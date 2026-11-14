const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-headers-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless=new','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],
    {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(r=>setTimeout(r,ms));
let socket;
async function until(fn,message) {
    for(let end=Date.now()+30000;Date.now()<end;) { if(await fn()) return; await delay(50); }
    throw Error(message);
}
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const p=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(p.exceptionDetails)throw Error(JSON.stringify(p.exceptionDetails));return p.result.value;};
    await send('Page.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'no-preference'}]});

    for(const width of [390,820,1440]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:width<500});
        await send('Page.navigate',{url:'http://localhost:8080/administration?section=heads-of-student-services'});
        await until(()=>evaluate('document.readyState==="complete"&&!!document.querySelector("[data-hss-flip]")'),'Office cards ready');
        await evaluate(`document.querySelectorAll('.modal.show').forEach(d=>bootstrap.Modal.getInstance(d)?.hide());document.querySelector('[data-privacy-action=reject]')?.click();window.__card=document.querySelector('[data-hss-flip]');__card.scrollIntoView({block:'center'});`);
        await delay(250);
        await delay(500);
        await evaluate(`document.querySelectorAll('dialog[open]').forEach(dialog=>dialog.close());`);
        const size=await evaluate('__card.getBoundingClientRect().height');
        // Check the whole transition, not just the final face: the old 150ms
        // visibility timer left both faces unpainted after the eased rotation crossed 90deg.
        const trace=await evaluate(`(async()=>{
            const inner=__card.querySelector('.hss-flip-card-inner');
            const front=__card.querySelector('.hss-face-front'),back=__card.querySelector('.hss-face-back');
            const samples=[];
            async function flip(interrupt){
                __card.click();const start=performance.now();let reversed=false;
                while(performance.now()-start<450){
                    await new Promise(requestAnimationFrame);
                    const cosine=new DOMMatrixReadOnly(getComputedStyle(inner).transform).m11;
                    samples.push({cosine,front:getComputedStyle(front).visibility,back:getComputedStyle(back).visibility});
                    if(interrupt&&!reversed&&performance.now()-start>60){__card.click();reversed=true;}
                }
            }
            await flip(false);await flip(false);await flip(true);
            return samples;
        })()`);
        assert.ok(trace.some(sample=>Math.abs(sample.cosine)<.9),'Sampled moving card');
        for(const sample of trace){
            // Ignore the almost edge-on frame, where compositor/style sampling may
            // straddle the crossing; neither face has appreciable visible width there.
            if(Math.abs(sample.cosine)<.15)continue;
            assert.equal(sample.front,sample.cosine>0?'visible':'hidden','Front follows actual rotation');
            assert.equal(sample.back,sample.cosine>0?'hidden':'visible','Back follows actual rotation');
        }
        for(let n=0;n<4;n++){
            await evaluate('__card.click()');await delay(400);
            const flipped=n%2===0;
            assert.deepEqual(await evaluate(`[getComputedStyle(__card.querySelector('.hss-face-front')).visibility,getComputedStyle(__card.querySelector('.hss-face-back')).visibility,__card.getAttribute('aria-expanded')]`),flipped?['hidden','visible','true']:['visible','hidden','false']);
            assert.equal(await evaluate('__card.getBoundingClientRect().height'),size,'Stable card height');
        }
        await evaluate('__card.click()');await delay(400);
        const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync('cache/hss-flip-'+width+'.png',Buffer.from(shot.data,'base64'));
        console.log('Card face visibility, repeated flipping and stable height passed at '+width);
    }
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    assert.equal(await evaluate("getComputedStyle(__card.querySelector('.hss-face-front')).transitionDuration"),'0s','Reduced motion switches faces immediately');
    await evaluate('__card.click()');
    assert.deepEqual(await evaluate(`[getComputedStyle(__card.querySelector('.hss-face-front')).visibility,getComputedStyle(__card.querySelector('.hss-face-back')).visibility,__card.getAttribute('aria-expanded')]`),['visible','hidden','false'],'Reduced motion updates the face synchronously');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();browser.kill();});
