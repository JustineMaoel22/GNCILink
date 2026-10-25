const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-calendar-widget-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base=process.env.BASE_URL||'http://localhost:8080';
    await send('Page.enable');await send('Network.enable');
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*cdn.jsdelivr.net*','*cdnjs.cloudflare.com*','*facebook.com*','*youtube.com*']});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"try{sessionStorage.setItem('gncIntroSeen','1')}catch(_){}"});

    for (const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+'/pages/news-and-announcement.php'});
        await until(()=>evaluate('document.readyState==="complete" && Boolean(document.querySelector("#calendarProgramWidget:not([hidden])"))'),'News filter did not load');
        await evaluate('document.getElementById("calendarProgramToggle").scrollIntoView({block:"center"});document.getElementById("calendarProgramToggle").click()');
        assert.equal(await evaluate('document.getElementById("calendarProgramToggle").getAttribute("aria-expanded")'),'true');
        assert.equal(await evaluate('document.querySelectorAll("#calendarProgramPanel .gnc-departments-list-item").length>0'),true);
        assert.equal(await evaluate('document.querySelectorAll("#calendarProgramPanel .gnc-programs-list-item").length>0'),true);
        assert.equal(await evaluate('(()=>{const r=document.getElementById("calendarProgramPanel").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.height>100})()'),true,'Panel fits viewport');
        const codes=await evaluate('Array.from(document.querySelectorAll("#calendarProgramPanel .gnc-programs-code"), el=>el.textContent.trim())');
        assert(codes.includes('BSIT'), 'College programs remain available');
        assert(codes.some(code=>code.startsWith('GS-')), 'Graduate School programs remain available');
        for (const basicCode of ['HAS','HAS2','IDT','IDT2','JHSHP']) assert(!codes.includes(basicCode), 'Basic Education program excluded: '+basicCode);
        const url=await evaluate('document.querySelector("#calendarProgramPanel .gnc-departments-list-item").href');
        await send('Page.navigate',{url});
        await until(()=>evaluate('document.readyState==="complete" && Boolean(document.querySelector(".gnc-calendar-department-active"))'),'Department filter did not apply');
        assert.equal(await evaluate('document.querySelector(".na-chips").textContent.includes("Department:")'),true);
        await evaluate('document.getElementById("calendarProgramToggle").click();document.querySelector(".gnc-program-widget-close").click()');
        assert.equal(await evaluate('document.getElementById("calendarProgramPanel").hidden'),true);
    }
    await send('Page.navigate',{url:base+'/'});
    await until(()=>evaluate('document.readyState!=="loading" && Boolean(document.querySelector("#calendarProgramWidget:not([hidden])"))'),'Home filter did not load');
    await evaluate('document.getElementById("calendarProgramToggle").scrollIntoView({block:"center"});document.getElementById("calendarProgramToggle").click()');
    assert.equal(await evaluate('document.getElementById("calendarProgramPanel").hidden'),false);
    assert.equal(await evaluate('document.getElementById("calendarProgramTitle").textContent.trim()'),'Calendar Categories');
    assert.equal(await evaluate('new URL(document.querySelector("#calendarProgramPanel .gnc-departments-list-item").href).hash'),'#events');
    console.log('PASS: homepage filter preserved; desktop/mobile news dropdown, college/graduate programs and departments, department filtering and close action.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
