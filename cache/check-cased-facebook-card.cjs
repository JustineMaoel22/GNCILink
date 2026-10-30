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
    await send('Page.enable');await send('Network.enable');
    const requests=[];socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.method==='Network.requestWillBeSent')requests.push(p.params.request.url);});
    await send('Network.setBlockedURLs',{urls:['*facebook.com*','*fbcdn.net*','*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*maps.google.com*']});
    await send('Page.navigate',{url:'http://localhost:8080/pages/academics/college/departments/cased'});
    await until(()=>evaluate('document.readyState==="complete"&&!!document.querySelector(".dept-fb-link")'),'CASED card available');
    await evaluate("document.getElementById('gncSiteDisclaimer')?.close();GNCPrivacy.setCategory('external',true)");
    assert.equal(await evaluate(`Array.from(document.querySelectorAll('iframe')).some(el=>(el.getAttribute('src')||el.getAttribute('data-privacy-src')||'').includes('facebook.com'))`),false,'No Facebook iframe remains on CASED');
    assert.equal(requests.some(url=>/facebook.com|fbcdn.net/.test(url)),false,'No Facebook scripts or frames requested');
    assert.equal(await evaluate(`(()=>{const a=document.querySelector('.dept-fb-link');return a.href.includes('facebook.com/')&&a.target==='_blank'&&a.rel.includes('noopener');})()`),true,'Stored Facebook link retained');
    for(const width of [390,1440]){
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<600});
      await evaluate("document.querySelector('.dept-fb-link').scrollIntoView({block:'center',behavior:'instant'})");await pause(1000);
      assert.equal(await evaluate(`(()=>{const r=document.querySelector('.dept-fb-link').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height>60;})()`),true,'Card fits viewport');
      const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync('cache/cased-facebook-card-'+width+'.png',Buffer.from(shot.data,'base64'));
    }
    console.log('PASS: real localhost CASED card, original Facebook URL, no Facebook frames or requests after embed opt-in, and layout at 390/1440px.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    socket?.close();browser?.kill();server.close();await pause(500);
    if(profile&&path.dirname(path.resolve(profile))===path.resolve(os.tmpdir())&&path.basename(profile).startsWith('gnci-chat-mobile-')){try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}}
});
