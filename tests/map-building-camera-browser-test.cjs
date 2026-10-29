const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),http=require('node:http');
const {spawn,execFileSync}=require('node:child_process');
const server=http.createServer((req,res)=>{res.setHeader('Content-Type','text/html');res.end(fs.readFileSync('campus_navigator.html'));});
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,label){for(let end=Date.now()+15000;Date.now()<end;){if(await work())return;await pause(50);}throw Error(label);}
let browser,socket,profile;
(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-chat-mobile-'));
    browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial,timer=setTimeout(()=>reject(Error('Timed out: '+method)),15000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:620,deviceScaleFactor:1,mobile:true});
    await send('Page.navigate',{url:'http://127.0.0.1:'+server.address().port});
    await until(()=>evaluate('!!window.followRoute'),'Map ready');
    for(const [width,height] of [[390,620],[360,640],[740,360]]){
      await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:true});
      assert.equal(await evaluate('getComputedStyle(document.getElementById("legend-toggle")).display!=="none"'),true,'Legend button visible on mobile');
      await evaluate('document.getElementById("legend-toggle").click()');
      assert.equal(await evaluate(`['legend-buildings','legend-rooms'].every(id=>getComputedStyle(document.getElementById(id)).display!=='none')`),true,'Building, parking and floor keys available');
      await evaluate("go('beta')");
      assert.equal(await evaluate('getComputedStyle(document.getElementById("legend")).opacity'), '1','Route selection does not hide legend');
      await evaluate('document.getElementById("legend-toggle").click()');
      assert.equal(await evaluate('document.getElementById("legend-toggle").getAttribute("aria-expanded")'),'false','Legend collapses');
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    assert.equal(await evaluate('getComputedStyle(document.getElementById("legend-buildings")).display!=="none"'),true,'Desktop legend remains visible');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:620,deviceScaleFactor:1,mobile:true});
    async function checkBuilding(id){
      await evaluate(`clear(true);document.getElementById('c-${id}').click()`);await pause(3900);
      assert.equal(await evaluate(`(()=>{
        const b=document.getElementById('b-${id}').getBBox(),p=svg.createSVGPoint();
        p.x=b.x+b.width/2;p.y=b.y+b.height/2;
        const target=p.matrixTransform(document.getElementById('world').transform.baseVal.consolidate().matrix),v=svg.viewBox.baseVal;
        const x=v.width>=1422?711:Math.min(Math.max(target.x,v.width/2),1422-v.width/2);
        const y=v.height>=2304?1152:Math.min(Math.max(target.y,v.height/2),2304-v.height/2);
        return current==='${id}'&&Math.abs(v.x+v.width/2-x)<2&&Math.abs(v.y+v.height/2-y)<2;
      })()`),true,'Camera focuses '+id+' rather than the previous building');
    }
    await evaluate("go('parking1')");await pause(500);
    const nearGate=await evaluate('svg.viewBox.baseVal.y');
    await pause(1500);
    assert.ok(await evaluate('svg.viewBox.baseVal.y')<nearGate-200,'Camera moves along trail from main gate toward Parking 1');
    for(const id of ['parking1','beta','banzali','tanjangco','goseco','parking2'])await checkBuilding(id);
    await evaluate("go('parking1');go('beta');go('banzali')");await pause(3900);
    assert.equal(await evaluate("current==='banzali'&&svg.viewBox.baseVal.y>800"),true,'Rapid building changes finish at latest destination');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await send('Page.reload');await until(()=>evaluate('!!window.followRoute'),'Reduced-motion reload');
    await checkBuilding('beta');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await checkBuilding('parking1');await checkBuilding('beta');
    console.log('PASS: zoom and movement along trail, six final building centers, rapid selection changes, reduced motion and desktop camera.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    socket?.close();browser?.kill();server.close();await pause(500);
    if(profile&&path.dirname(path.resolve(profile))===path.resolve(os.tmpdir())&&path.basename(profile).startsWith('gnci-chat-mobile-')){try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}}
});
