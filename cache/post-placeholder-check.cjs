const fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {spawn}=require('node:child_process');
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-placeholder-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let socket;
(async()=>{
 for(let n=0;n<100&&!fs.existsSync(path.join(profile,'DevToolsActivePort'));n++)await delay(100);
 const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
 const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
 socket=new WebSocket(tabs[0].webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
 let id=0;const pending=new Map();socket.addEventListener('message',e=>{const m=JSON.parse(e.data);if(pending.has(m.id)){pending.get(m.id)(m);pending.delete(m.id);}});
 const send=(method,params={})=>new Promise((resolve,reject)=>{const n=++id;pending.set(n,m=>m.error?reject(Error(JSON.stringify(m.error))):resolve(m.result));socket.send(JSON.stringify({id:n,method,params}));});
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
 await send('Page.enable');
 await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 const homepage=process.argv.includes('--home');
 await send('Page.navigate',{url:'http://localhost:8080'+(homepage?'/':'/pages/news-and-announcement.php')});
 let ready=false;for(let n=0;n<150;n++){ready=await evaluate("document.readyState==='complete'&&!!document.querySelector('.na-card,.gnc-news-row')");if(ready)break;await delay(100);}if(!ready)throw Error('Feed unavailable');
 await delay(4500);
 await evaluate(`(()=>{document.querySelector('#gncSiteDisclaimer button')?.click();Array.from(document.querySelectorAll('button')).find(b=>b.textContent.trim()==='Reject optional')?.click();})()`);
 await delay(800);
 for(const width of [1440,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
  const result=await evaluate(`(()=>{const p=document.querySelector('.na-card-img-fallback,.gnc-news-img-fallback');if(!p)throw Error('No image-free post available');p.scrollIntoView({block:'center'});const s=getComputedStyle(p),wrap=p.closest('.skeleton-wrap');return {count:document.querySelectorAll('.gnc-empty-post-image').length,background:s.backgroundColor,image:s.backgroundImage,overlay:wrap?getComputedStyle(wrap,'::before').opacity:'0',overflow:document.documentElement.scrollWidth>innerWidth};})()`);
  if(result.background!=='rgb(238, 238, 238)'||!result.image.includes('post-image-placeholder.svg')||result.overflow)throw Error(JSON.stringify(result));
  await delay(800);const screenshot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(__dirname,`post-placeholder-${homepage?'home-':''}${width}.png`),Buffer.from(screenshot.data,'base64'));
  console.log('PASS public feed '+width+'px: '+JSON.stringify(result));
 }
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();browser.kill();});
