const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=os.tmpdir();
const draftMarker='tuition-manager-regression-'+Date.now();
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-tuition-manager-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message){for(let end=Date.now()+30000;Date.now()<end;){if(await work())return;await delay(50);}throw Error(message);}
let socket,cleanupDraft;
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
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*facebook.com*','*youtube.com*']});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"try{sessionStorage.setItem('gncIntroSeen','1')}catch(_){}"});

    await send('Network.setCookie',{name:adminSession.name,value:adminSession.value,url:base,httpOnly:true});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:`(()=>{let config;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return config},set(value){config=value;if(value&&value.page&&value.page.url){const u=new URL(value.page.url,location.origin);u.searchParams.set('draft_test',${JSON.stringify(draftMarker)});value.page.url=u.pathname+u.search;}}});})();`});


    for(const [width,height] of [[1440,900],[1024,600],[390,700]]){
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await evaluate('window.__veLeaving=true');
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page=tuition-fees'});
        await until(()=>evaluate('Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&VE.state.doc.defaultView.GNCTuitionFees&&document.getElementById("veLoader").hidden)'),'Tuition editor missing');
        await evaluate(`(()=>{const api=VE.state.doc.defaultView.GNCTuitionFees;api.categories.college.documents=Array.from({length:30},(_,i)=>({id:90000+i,title:'PDF schedule '+(i+1),url:'/fixture.pdf',size:'1 KB'}));api.show('college');document.getElementById('veSecondary').click();})()`);
        assert.equal(await evaluate('document.querySelectorAll("#vePop input").length'),30);
        assert.equal(await evaluate('(()=>{const p=document.getElementById("vePop"),r=p.getBoundingClientRect(),h=document.querySelector(".ve-top").getBoundingClientRect();return r.top>=h.bottom&&r.bottom<=innerHeight&&r.left>=0&&r.right<=innerWidth&&p.scrollHeight>p.clientHeight&&getComputedStyle(p).overflowY==="auto";})()'),true,'Long PDF manager scrolls below the toolbar at '+width+'px');
        const point=await evaluate('(()=>{const r=document.getElementById("vePop").getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+100};})()');
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',...point});
        await send('Input.dispatchMouseEvent',{type:'mouseWheel',...point,deltaX:0,deltaY:500});
        await until(()=>evaluate('document.getElementById("vePop").scrollTop>0'),'Mouse wheel cannot scroll PDFs');
        await evaluate('document.getElementById("vePop").scrollTop=document.getElementById("vePop").scrollHeight');
        assert.equal(await evaluate('(()=>{const b=[...document.querySelectorAll("#vePop button")].find(b=>b.textContent==="Add PDF"),r=b.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight&&b.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2));})()'),true,'Add PDF stays reachable after scrolling');
        await evaluate('(()=>{const input=[...document.querySelectorAll("#vePop input")].at(-1);input.value="Renamed last PDF";input.dispatchEvent(new Event("input",{bubbles:true}));})()');
        await delay(150);
        fs.writeFileSync(path.join(auditDirectory,'tuition-manager-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        assert.equal(await evaluate('(()=>{const b=document.getElementById("vePrimary"),r=b.getBoundingClientRect();return !b.disabled&&b.contains(document.elementFromPoint(r.left+r.width/2,r.top+r.height/2));})()'),true,'Publish stays enabled and unobstructed with the manager open at '+width+'px');
        fs.writeFileSync(path.join(auditDirectory,'tuition-manager-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        if(width===390){
            await send('Emulation.setDeviceMetricsOverride',{width:768,height:500,deviceScaleFactor:1,mobile:false});await delay(200);
            assert.equal(await evaluate('(()=>{const r=document.getElementById("vePop").getBoundingClientRect(),h=document.querySelector(".ve-top").getBoundingClientRect();return r.top>=h.bottom&&r.bottom<=innerHeight;})()'),true,'Open manager adapts after resizing');
        }
    }
    let posted='';
    socket.addEventListener('message',async event=>{const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;posted=packet.params.request.postData||'';await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify({success:true})).toString('base64')});});
    await send('Fetch.enable',{patterns:[{urlPattern:'*tuition-fees-handler.php*'}]});
    const point=await evaluate('(()=>{const r=document.getElementById("vePrimary").getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()');
    await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...point});await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...point});
    await until(()=>evaluate('Boolean(document.querySelector(".ve-publish-dialog[open]"))'),'Publish not clickable from open manager');
    await evaluate('document.querySelector(".ve-publish-accept").click()');
    await until(()=>Boolean(posted),'No AJAX publication request');
    assert(posted.includes('Renamed last PDF'),'Scrolled edits reach publishing');
    console.log('PASS: 30 PDFs scroll with mouse wheel; last row and Add PDF are reachable; Publish remains clickable beside the manager on desktop/tablet/mobile and after resizing. Live publication intercepted.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-tuition-manager-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
