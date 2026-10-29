const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),http=require('node:http');
const {spawn,execFileSync}=require('node:child_process');
const markup=execFileSync('php',['chatbot/widget.php'],{encoding:'utf8',windowsHide:true});
const server=http.createServer((req,res)=>{
    const pathname=new URL(req.url,'http://localhost').pathname;
    if(['/chatbot/chatbot.js','/chatbot/chatbot.css'].includes(pathname)){
        res.setHeader('Content-Type',pathname.endsWith('.js')?'application/javascript':'text/css');return res.end(fs.readFileSync('.'+pathname));
    }
    if(pathname!=='/'){res.writeHead(404);return res.end();}
    res.setHeader('Content-Type','text/html; charset=utf-8');
    res.end('<meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;height:4000px}html{scroll-behavior:smooth}</style><main style="transform:translateZ(0)">'+markup+'</main>');
});
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
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await send('Page.navigate',{url:'http://127.0.0.1:'+server.address().port});
    await until(()=>evaluate('!!window.GNCBot&&document.getElementById("gncbot").classList.contains("gncbot-ready")'),'Chatbot ready');
    await evaluate('window.scrollTo({top:700,behavior:"instant"});GNCBot.open()');
    assert.equal(await evaluate('document.body.classList.contains("gncbot-noscroll")'),true,'Page locked on mobile');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".gncbot-panel")).animationName'),'gncbot-in','Opening animates');
    // Simulate the visual viewport becoming shorter and panning when a keyboard opens.
    await evaluate(`window.testViewport=new EventTarget();testViewport.height=410;testViewport.offsetTop=95;Object.defineProperty(window,'visualViewport',{value:testViewport,configurable:true});window.dispatchEvent(new Event('resize'));document.querySelector('.gncbot-input').focus({preventScroll:true})`);
    await pause(250);
    assert.equal(await evaluate(`(()=>{const p=document.querySelector('.gncbot-panel').getBoundingClientRect(),f=document.querySelector('.gncbot-form').getBoundingClientRect();return Math.abs(p.top-95)<2&&Math.abs(p.height-410)<2&&f.bottom<=505&&f.top>=95;})()`),true,'Panel and composer fit keyboard viewport');
    await evaluate(`document.querySelector('.gncbot-input').value='A draft question';const box=document.querySelector('.gncbot-conversation');box.scrollTop=box.scrollHeight;`);
    assert.equal(await evaluate(`document.querySelector('.gncbot-header').getBoundingClientRect().top>=95`),true,'Header remains visible during conversation scrolling');
    await evaluate('GNCBot.close()');
    assert.equal(await evaluate('!document.querySelector(".gncbot-panel").hidden&&getComputedStyle(document.querySelector(".gncbot-panel")).animationName==="gncbot-out"'),true,'Closing animates before hiding');
    await pause(250);
    assert.equal(await evaluate('document.querySelector(".gncbot-panel").hidden&&window.scrollY===700&&!document.body.classList.contains("gncbot-noscroll")'),true,'Close restores page position');
    await evaluate('GNCBot.open();GNCBot.close();GNCBot.open()');await pause(250);
    assert.equal(await evaluate('!document.querySelector(".gncbot-panel").hidden&&document.querySelector(".gncbot-input").value==="A draft question"'),true,'Rapid reopen retains draft');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await evaluate('GNCBot.close()');
    assert.equal(await evaluate('document.querySelector(".gncbot-panel").hidden'),true,'Reduced motion closes immediately');
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await evaluate('GNCBot.open()');
    assert.equal(await evaluate('!document.body.classList.contains("gncbot-noscroll")&&document.querySelector(".gncbot-panel").getBoundingClientRect().width===390'),true,'Desktop remains floating');
    console.log('PASS: mobile viewport offset/height, fixed composer/header, scroll restoration, open/close animations, rapid reopening, draft retention, reduced motion and desktop layout.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    socket?.close();browser?.kill();server.close();await pause(500);
    if(profile&&path.dirname(path.resolve(profile))===path.resolve(os.tmpdir())&&path.basename(profile).startsWith('gnci-chat-mobile-')){try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}}
});
