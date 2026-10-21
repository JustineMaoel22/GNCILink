const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-home-warnings-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,session;
const {execFileSync}=require('node:child_process');
const php=(...args)=>execFileSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/student-organizations-test.php',...args],{encoding:'utf8',windowsHide:true});
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
    const issues=[];
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Audits.issueAdded')issues.push(packet.params.issue);});
    await send('Audits.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    session=JSON.parse(php('session'));
    await send('Network.setCookie',{name:session.name,value:session.id,url:base});
    for(const url of [base+'/',base+'/admin/content-edit.php?page=home']) {
        issues.length=0;
        await send('Page.navigate',{url});await until(()=>evaluate(`location.href===${JSON.stringify(url)}&&document.readyState==='complete'`),'Page not loaded');
        if(url.includes('/admin/'))await until(()=>evaluate('Boolean(document.getElementById("veFrame")?.contentDocument?.querySelector(".gnc-president-photo"))'),'Editor homepage missing');
        await delay(500);
        const dom=await evaluate(`(()=>{const docs=[document];const frame=document.getElementById('veFrame');if(frame?.contentDocument)docs.push(frame.contentDocument);return docs.map(doc=>({fields:Array.from(doc.querySelectorAll('input:not([type=hidden]),select,textarea')).filter(el=>!el.id&&!el.name).map(el=>el.className),images:Array.from(doc.querySelectorAll('img[loading=lazy]')).filter(el=>!el.hasAttribute('width')||!el.hasAttribute('height')).map(el=>el.className)}))})()`);
        for(const entry of dom){assert.equal(entry.fields.length,0,'Form controls have id or name: '+entry.fields);assert.equal(entry.images.length,0,'Lazy images reserve dimensions: '+entry.images);}
        const relevant=issues.filter(issue=>issue.code==='LazyLoadImageIssue'||issue.details.genericIssueDetails?.errorType==='FormEmptyIdAndNameAttributesForInputError');
        assert.deepEqual(relevant,[],'Reported DevTools warnings cleared');
        console.log('PASS: no missing form identifiers or lazy-image dimension warnings on '+(url.includes('/admin/')?'homepage Visual Editor':'public homepage'));
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(session)try{php('destroy_session',session.id);}catch{}
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-home-warnings-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
