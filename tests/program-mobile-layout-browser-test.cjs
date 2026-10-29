const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-program-mobile-'));
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

    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    for(const slug of ['beed','bael','bsba-fm']){
        for(const width of [320,375,390,575,768,1440]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:820,deviceScaleFactor:1,mobile:false});
            await send('Page.navigate',{url:base+'/pages/academics/college/program/'+slug});
            await until(()=>evaluate('document.readyState==="complete"&&Boolean(document.querySelector(".about-feature-seal-wrap img"))'),'Program not loaded: '+slug);
            await evaluate('document.querySelector("#gncPrivacyBanner [data-privacy-action=reject]")?.click()');
            await until(()=>evaluate('document.querySelector(".about-feature-seal-wrap img").complete'),'Seal image not loaded');
            await evaluate('(()=>{const feature=document.querySelector(".about-feature"),nav=document.getElementById("mainNavbar");window.scrollTo({top:scrollY+feature.getBoundingClientRect().top-(nav?.getBoundingClientRect().height||0)-16,behavior:"instant"});})()');
            await delay(250);
            const sizes=await evaluate(`(()=>{const cell=document.querySelector('.about-feature-seal').getBoundingClientRect(),img=document.querySelector('.about-feature-seal-wrap img').getBoundingClientRect(),ring=document.querySelector('.about-seal-ring').getBoundingClientRect(),heading=document.querySelector('.about-heading').getBoundingClientRect();return {cell:cell.toJSON(),img:img.toJSON(),ring:ring.toJSON(),heading:heading.toJSON()};})()`);
            fs.writeFileSync(path.join(os.tmpdir(),'program-mobile-'+slug+'-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
            if(width<=575){
                assert(sizes.ring.width*212/500>sizes.img.width/2+3,'Seal ring text stays outside the logo at '+width+'px ('+slug+')');
                assert(sizes.cell.width<=280,'Seal scales down to leave room for program text on phones');
                assert(sizes.heading.top>=sizes.ring.bottom,'Program heading stays below the complete seal ring');
            }
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'No page overflow: '+slug+' '+width);
            assert.equal(await evaluate(`Array.from(document.querySelectorAll('.template-title,.about-heading,.about-feature-seal,.template-badges,.template-vm-card,.why-list,.template-cta-card')).every(el=>{const r=el.getBoundingClientRect();return r.left>=-1&&r.right<=innerWidth+1;})`),true,'Program sections fit viewport: '+slug+' '+width);
        }
    }
    console.log('PASS: three program pages fit phone/tablet/desktop widths, phone seals stay compact, ring text clears the logo, and headings/sections stay inside the viewport. No content changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-program-mobile-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
