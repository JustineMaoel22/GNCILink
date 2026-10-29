const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),http=require('node:http');
const {spawn,execFileSync}=require('node:child_process');
const markup=execFileSync('php',['components/privacy-controls.php'],{encoding:'utf8',windowsHide:true});
const server=http.createServer((req,res)=>{
    if(req.url==='/assets/js/privacy-consent.js'){res.setHeader('Content-Type','application/javascript');return res.end(fs.readFileSync('assets/js/privacy-consent.js'));}
    if(req.url==='/assets/css/privacy.css'){res.setHeader('Content-Type','text/css');return res.end(fs.readFileSync('assets/css/privacy.css'));}
    if(req.url.startsWith('/api/')){res.setHeader('Content-Type','application/json');return res.end('{}');}
    res.setHeader('Content-Type','text/html; charset=utf-8');
    res.end('<meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/privacy.css"><button data-privacy-action="manage">Privacy &amp; cookie settings</button>'+markup+'<script src="/assets/js/privacy-consent.js" defer></script>');
});
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,label){for(let end=Date.now()+15000;Date.now()<end;){if(await work())return;await pause(50);}throw Error(label);}
let browser,socket,profile;
(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const base='http://127.0.0.1:'+server.address().port;
    profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-privacy-touch-'));
    browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;const timer=setTimeout(()=>{pending.delete(id);reject(Error('Timed out: '+method));},15000);pending.set(id,p=>{clearTimeout(timer);p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result);});socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    async function tap(selector){const point=await evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(selector)});el.scrollIntoView({block:'center'});const r=el.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()`);await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[point]});await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});}
    await send('Page.enable');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await send('Emulation.setTouchEmulationEnabled',{enabled:true});
    async function visit(){await send('Page.navigate',{url:base});await until(()=>evaluate('document.readyState==="complete"&&!!window.GNCPrivacy'),'Privacy UI unavailable');}
    const script=await send('Page.addScriptToEvaluateOnNewDocument',{source:'Object.defineProperty(navigator,"doNotTrack",{value:"0"});Object.defineProperty(navigator,"globalPrivacyControl",{value:false});'});
    await visit();
    await tap('[data-privacy-action=manage]');
    await until(()=>evaluate('document.getElementById("gncPrivacyDialog").open'),'Touch opens settings');
    assert.equal(await evaluate('document.getElementById("gncPrivacySignal").hidden'),true);
    for(const category of ['analytics','personalization','external']){
        await tap('input[name='+category+']');
        assert.equal(await evaluate(`document.querySelector('[name=${category}]').checked`),true,'Checkbox responds to touch');
    }
    await tap('#gncPrivacyForm [type=submit]');
    await until(()=>evaluate('!document.getElementById("gncPrivacyDialog").open'),'Save closes settings');
    await visit();await tap('[data-privacy-action=manage]');
    assert.equal(await evaluate('Array.from(document.querySelectorAll("#gncPrivacyForm input")).every(el=>el.checked&&!el.disabled)'),true,'Choices persist');
    await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:script.identifier});
    for(const [property,value,label] of [['doNotTrack','1','Do Not Track'],['globalPrivacyControl',true,'Global Privacy Control']]){
        const setup=await send('Page.addScriptToEvaluateOnNewDocument',{source:`Object.defineProperty(navigator,${JSON.stringify(property)},{value:${JSON.stringify(value)}});`});
        await visit();await tap('[data-privacy-action=manage]');
        assert.equal(await evaluate('Array.from(document.querySelectorAll("#gncPrivacyForm input")).every(el=>el.disabled&&!el.checked)'),true,'Browser signal keeps all options off');
        assert.equal(await evaluate(`(()=>{const signal=document.getElementById('gncPrivacySignal'),input=document.querySelector('#gncPrivacyForm input');return !signal.hidden&&signal.textContent.includes(${JSON.stringify(label)})&&signal.getBoundingClientRect().bottom<input.getBoundingClientRect().top;})()`),true,'Explanation appears above locked choices');
        await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:setup.identifier});
    }
    console.log('PASS: mobile touch opens settings, selects all categories, saves and restores choices; DNT and GPC lock options with a visible explanation above controls.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    socket?.close();browser?.kill();server.close();await pause(500);
    if(profile&&path.dirname(path.resolve(profile))===path.resolve(os.tmpdir())&&path.basename(profile).startsWith('gnci-privacy-touch-')){
        try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
    }
});
