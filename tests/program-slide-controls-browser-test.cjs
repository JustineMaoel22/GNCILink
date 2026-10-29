const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=os.tmpdir();
const draftMarker='slide-controls-regression-'+Date.now();
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-slide-controls-'));
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


    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=programs&sub=bael'});
    await until(()=>evaluate('Boolean(window.VE&&VE.state.doc&&!VE.state.busy&&document.getElementById("veLoader").hidden&&document.querySelector("#veDock .ve-thumb"))'),'Program slide editor unavailable');
    await evaluate('Array.from(document.querySelectorAll("#veDock button")).find(b=>b.textContent.includes("Facility slides")).click()');
    const original=await evaluate('JSON.parse(VE.state.doc.getElementById("veFacData").textContent).slides');
    assert(original.length>1,'Exercise a facility list containing multiple slides');
    async function open(){
        await evaluate('document.querySelector("#veDock .ve-thumb").click()');
        await until(()=>evaluate('!document.getElementById("vePop").hidden'),'Slide popup missing');
    }
    async function clickControl(text){
        const point=await evaluate(`(()=>{const button=[...document.querySelectorAll('#vePop button')].find(b=>b.textContent.trim()===${JSON.stringify(text)});if(!button)return null;const r=button.getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()`);
        assert(point,'Missing action: '+text);
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',...point});
        await send('Input.dispatchMouseEvent',{type:'mousePressed',button:'left',clickCount:1,...point});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',button:'left',clickCount:1,...point});
    }
    for(const width of [1440,768,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await evaluate('document.getElementById("vePop").hidden=true;document.querySelector("#veDock .ve-thumb").scrollIntoView({block:"nearest",inline:"center",behavior:"instant"})');
        await delay(150);
        const point=await evaluate('(()=>{const image=document.querySelector("#veDock .ve-thumb img"),r=image.getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()');
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',...point});
        await until(()=>evaluate('Boolean(document.querySelector(".image-frame-trigger:not([hidden])"))'),'Thumbnail hover trigger missing at '+width+'px');
        await open();
        assert.equal(await evaluate('Boolean(document.querySelector(".image-frame-trigger:not([hidden])"))'),false,'Opening a slide popup hides the floating Reposition at '+width+'px');
        const accessible=await evaluate(`(()=>{const pop=document.getElementById('vePop'),r=pop.getBoundingClientRect(),buttons=[...pop.querySelectorAll('button')];return r.left>=0&&r.right<=innerWidth&&buttons.every(button=>{const b=button.getBoundingClientRect();return b.left>=r.left&&b.right<=r.right&&b.top>=r.top&&b.bottom<=r.bottom&&button.contains(document.elementFromPoint(b.left+b.width/2,b.top+b.height/2));});})()`);
        assert.equal(accessible,true,'All controls are within the popup and receive clicks at '+width+'px');
        await clickControl('Reposition image');
        await until(()=>evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),'Facility Reposition did not open');
        assert.equal(await evaluate('document.getElementById("vePop").hidden'),true,'Reposition closes the slide popup');
        await evaluate('Array.from(document.querySelectorAll(".image-frame-dialog[open] button")).find(button=>button.textContent==="Cancel").click()');
        await until(()=>evaluate('!document.querySelector(".image-frame-dialog[open]")'),'Crop dialog did not close');
        await open();
        // Moving across form fields must not bring back the floating control over the popup.
        const formPoint=await evaluate('(()=>{const r=document.querySelector("#vePop textarea").getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()');
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',...formPoint});await delay(100);
        assert.equal(await evaluate('Boolean(document.querySelector(".image-frame-trigger:not([hidden])"))'),false,'Form interaction cannot reactivate hover reposition');
        fs.writeFileSync(path.join(auditDirectory,'slide-controls-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    }
    let posted;
    await evaluate('window.AdminDialogs.confirm=async()=>{window.slideDeleteConfirmed=(window.slideDeleteConfirmed||0)+1;return true;};document.addEventListener("ve:facilities-changed",event=>{window.slideDeleteResult=event.detail.items;});');
    socket.addEventListener('message',async event=>{
        const packet=JSON.parse(event.data);if(packet.method!=='Fetch.requestPaused')return;
        posted=packet.params.request.postData||'';
        const match=/name="items"\r?\n\r?\n([^\r\n]+)/.exec(posted);const rows=JSON.parse(match[1]);
        await send('Fetch.fulfillRequest',{requestId:packet.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify({success:true,items:rows})).toString('base64')});
    });
    await send('Fetch.enable',{patterns:[{urlPattern:'*program-page-handler.php*'}]});
    await clickControl('Delete');
    await until(()=>evaluate('Boolean(VE.adapter().draftState().pRows.facility&&!VE.state.busy&&document.getElementById("veLoader").hidden)'),'Delete did not complete');
    assert.equal(await evaluate('window.slideDeleteConfirmed'),1,'Delete fires independently of Reposition');
    assert.equal(posted,undefined,'Delete must not call the live handler');
    const rows=await evaluate('VE.adapter().draftState().pRows.facility');
    assert.equal(rows.length,original.length-1);
    assert.deepEqual(rows.map(row=>row.item_id),original.slice(1).map(row=>row.item_id),'Deletion removes only the selected slide');
    assert.equal(await evaluate('Boolean(document.querySelector(".image-frame-dialog[open]"))'),false,'Delete never opens Reposition');
    await open();
    assert.equal(await evaluate('Boolean(document.querySelector(".image-frame-trigger:not([hidden])"))'),false,'Popup controls stay clear after deletion');
    await evaluate(`(async()=>{const route=new URL(VE.state.url,location.origin);['ce_preview','_','_ve','add_org'].forEach(key=>route.searchParams.delete(key));route.searchParams.sort();const post=async fields=>{const response=await fetch('/admin/action/editor-draft-handler.php',{method:'POST',body:new URLSearchParams({csrf_token:VE_CONFIG.csrf,page:'programs',route:route.pathname+route.search,...fields})});return response.json();};const loaded=await post({action:'load'});if(loaded.draft)await post({action:'delete',revision:loaded.draft.revision});})()`);
    console.log('PASS: facility popup buttons stay unobstructed at desktop/tablet/mobile; Reposition opens independently and Delete changes only the private draft. No live writes.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});
    if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-slide-controls-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
