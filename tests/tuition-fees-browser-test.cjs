const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-tuition-test-'));
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
    const page = base + '/pages/admission/tuition-fees.php';
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`
        if(new URLSearchParams(location.search).has('test-document')) {
            const observer=new MutationObserver(()=>{
                const data=document.getElementById('feeDocumentsData');
                if(!data || !data.textContent.trim())return;
                const categories=JSON.parse(data.textContent);
                categories.college.documents=[{title:'College Fee Schedule Test',url:'/__tuition-test.pdf',size:'12 KB'}];
                observer.disconnect();data.textContent=JSON.stringify(categories);
            });observer.observe(document,{childList:true,subtree:true});
        }
    `});
    await send('Fetch.enable',{patterns:[{urlPattern:'*/__tuition-test.pdf*'}]});
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.method==='Fetch.requestPaused'){
        send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/pdf'}],body:Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0/Kids[]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF').toString('base64')});
    }});
    async function navigate(url){await send('Page.navigate',{url});await until(()=>evaluate(`location.href===${JSON.stringify(url)}&&document.readyState==='complete'`),'Tuition page did not load');}
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await navigate(page);
        assert.equal(await evaluate('document.querySelectorAll("[data-fee-category]").length'),5);
        assert.equal(await evaluate('document.getElementById("feeDownload").hidden'),true,'Empty category has no download');
        await evaluate('document.querySelector("[data-fee-category=graduate-school]").click()');
        assert.equal(await evaluate('document.getElementById("feeEmptyCategory").textContent'),'Graduate School');
        assert.equal(await evaluate('new URLSearchParams(location.search).get("category")'),'graduate-school');
        await evaluate('document.getElementById("feeSearch").value="basic";document.getElementById("feeSearch").dispatchEvent(new Event("input"))');
        assert.equal(await evaluate('Array.from(document.querySelectorAll("[data-fee-category]")).filter(e=>!e.hidden).length'),1);
        await evaluate('document.getElementById("feeSearch").value="no-such-document";document.getElementById("feeSearch").dispatchEvent(new Event("input"))');
        assert.equal(await evaluate('document.getElementById("feeSearchStatus").hidden'),false);
        assert.equal(await evaluate('document.querySelector(".tuition-documents").getBoundingClientRect().width<=innerWidth'),true);
        console.log('PASS '+width+'px: category switching, search, empty state and responsive width');
        await navigate(page+'?test-document=1');
        assert.equal(await evaluate('document.getElementById("feeDocumentTitle").textContent'),'College Fee Schedule Test');
        assert.equal(await evaluate('document.getElementById("feePdfFrame").hidden'),false);
        assert.equal(await evaluate('document.getElementById("feeDownload").getAttribute("href")'),'/__tuition-test.pdf');
        await evaluate('document.querySelector("[data-fee-category=other-fees]").click()');
        assert.equal(await evaluate('document.getElementById("feePdfFrame").hasAttribute("src")'),false,'Old PDF is cleared for an empty category');
        assert.equal(await evaluate('document.getElementById("feeDownload").hasAttribute("href")'),false);
        console.log('PASS '+width+'px: PDF display, download URL, stale document cleanup');
        if(width===1440) {
            await evaluate('document.querySelector(".tuition-documents").scrollIntoView({behavior:"instant",block:"start"})');await delay(2000);
            const screenshot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(os.tmpdir(),'gnci-tuition-layout.png'),Buffer.from(screenshot.data,'base64'));
        }
    }
    console.log('No database changes or uploaded fixtures.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-tuition-test-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
