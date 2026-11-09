const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-photo-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
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

    await send('Page.enable');await send('Network.enable');await send('Performance.enable');await send('LayerTree.enable');
    await send('Emulation.setDeviceMetricsOverride',{width:1920,height:950,deviceScaleFactor:1,mobile:false});
    let layers=[];socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.method==='LayerTree.layerTreeDidChange')layers=p.params.layers||[];});
    const report=[];
    for(const origin of ['http://localhost:8080','https://gncipampanga.com']){
        for(const category of ['board-of-trustees','board-of-administration']){
            await evaluate('window.__oldAdministrationPage=true');
            await send('Page.navigate',{url:origin+'/administration?section='+category});
            await until(()=>evaluate("!window.__oldAdministrationPage&&document.readyState==='complete'&&!!document.querySelector('.admin-grid')").catch(()=>false),'Administration page unavailable '+origin);
            await evaluate("document.querySelector('.admin-grid').scrollIntoView({block:'start',behavior:'instant'})");
            await evaluate("Promise.all(Array.from(document.querySelectorAll('.admin-photo-card img')).map(img=>{img.loading='eager';return img.decode().catch(()=>{});})).then(()=>true)");
            const info=await evaluate(`(()=>({cards:document.querySelectorAll('.admin-photo-card').length,images:Array.from(document.querySelectorAll('.admin-photo-card img')).map(i=>({src:i.getAttribute('src'),width:i.naturalWidth,height:i.naturalHeight,display:Math.round(i.getBoundingClientRect().width)})),blur:getComputedStyle(document.querySelector('.admin-badge')).backdropFilter,animations:document.getAnimations().map(a=>({name:a.animationName,state:a.playState}))}))()`);
            const reasons=[];
            await delay(250);
            for(const layer of layers.filter(l=>l.drawsContent)){
                try{const r=await send('LayerTree.compositingReasons',{layerId:layer.layerId});if(r.compositingReasons.length)reasons.push({width:layer.width,height:layer.height,reasons:r.compositingReasons});}catch{}
            }
            for(const mode of ['original','without-backdrop']){
                if(mode==='without-backdrop')await evaluate("document.head.appendChild(Object.assign(document.createElement('style'),{textContent:'.admin-badge{backdrop-filter:none!important;-webkit-backdrop-filter:none!important}'}))");
                const timing=await evaluate(`new Promise(resolve=>{const gaps=[];let previous=performance.now(),step=0;function tick(now){gaps.push(now-previous);previous=now;window.scrollBy(0,step<45?24:-24);if(++step<90)requestAnimationFrame(tick);else resolve({frames:gaps.length,over33ms:gaps.filter(t=>t>33).length,max:Math.round(Math.max(...gaps)),mean:Math.round(gaps.reduce((a,b)=>a+b,0)/gaps.length)});}requestAnimationFrame(tick);})`);
                report.push({origin,category,mode,timing,...(mode==='original'?{info,reasons}:{}),layerCount:layers.length});
            }
        }
    }
    fs.writeFileSync('cache/administration-render-profile.json',JSON.stringify(report,null,2));
    console.log(JSON.stringify(report.map(({origin,category,mode,timing,info,reasons,layerCount})=>({origin,category,mode,timing,layerCount,...(info?{cards:info.cards,blur:info.blur,decodedMB:Math.round(info.images.reduce((s,i)=>s+i.width*i.height*4,0)/1048576),largeImages:info.images.filter(i=>i.width>1920||i.height>1920),reasons}:{} )})),null,2));
})().catch(error => { console.error(error); process.exitCode=1; }).finally(() => { if(socket) socket.close(); browser.kill(); });
