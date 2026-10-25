const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const createSession=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','create'],{encoding:'utf8',windowsHide:true});
if(createSession.status!==0)throw Error(createSession.stderr||createSession.stdout);
const adminSession=JSON.parse(createSession.stdout);
const auditDirectory=path.join('cache','admin-design-audit');fs.mkdirSync(auditDirectory,{recursive:true});
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
    await send('Network.setBlockedURLs',{urls:['*fonts.googleapis.com*','*fonts.gstatic.com*','*facebook.com*','*youtube.com*']});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:"try{sessionStorage.setItem('gncIntroSeen','1')}catch(_){}"});

    await send('Network.setCookie',{name:adminSession.name,value:adminSession.value,url:base,httpOnly:true});
    const entries=[];
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        for(const [name,url] of [['dashboard','/admin/admin-dash.php'],['news','/admin/news.php'],['announcements','/admin/announcement.php'],['events','/admin/events.php'],['settings','/admin/settings.php'],['content-hub','/admin/content-edit.php?page=hub'],['content','/admin/content-edit.php'],['history-editor','/admin/content-edit.php?page=history'],['college-editor','/admin/content-edit.php?page=college-departments'],['program-editor','/admin/content-edit.php?page=programs'],['review','/admin/content-edit.php?page=review'],['administration-editor','/admin/content-edit.php?page=administration'],['graduate-editor','/admin/content-edit.php?page=graduate-school'],['basic-editor','/admin/content-edit.php?page=basic-education'],['tuition-editor','/admin/content-edit.php?page=tuition-fees'],['payment-editor','/admin/content-edit.php?page=online-payment-guide'],['organization-editor','/admin/content-edit.php?page=student-organizations'],['achievement-editor','/admin/content-edit.php?page=student-achievements'],['sports-editor','/admin/content-edit.php?page=athletics-sports']]) {
            await send('Page.navigate',{url:base+url});
            await until(()=>evaluate('document.readyState!=="loading"&&Boolean(document.querySelector(".gnc-main"))'),'Admin page did not load: '+name);
            await delay(1200);
            const metrics=await evaluate(`(()=>{function style(selector){const el=document.querySelector(selector);if(!el)return null;const c=getComputedStyle(el),r=el.getBoundingClientRect();return {font:c.fontFamily,fontSize:c.fontSize,color:c.color,background:c.backgroundColor,radius:c.borderRadius,shadow:c.boxShadow,transition:c.transitionDuration,width:r.width,height:r.height};}return {title:document.title,url:location.pathname,body:style('body'),heading:style('.page-header h1'),button:style('.page-header .btn,.page-header .btn-gnc-gold,#vePrimary'),card:style('.data-card'),badge:style('.status-badge,.ce-badge'),input:style('.form-control,.form-select'),horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,errors:/Fatal error|Parse error/.test(document.body.innerText),settingsHeaderDirection:document.querySelector('.page-header')?getComputedStyle(document.querySelector('.page-header')).flexDirection:null};})()`);
            entries.push({name,width,...metrics});
            if(process.env.AUDIT_SCREENSHOTS==='1'&&width===1440&&['dashboard','events','settings','content','content-hub','history-editor'].includes(name)) {
                const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(auditDirectory,name+'-'+(process.env.AUDIT_LABEL||'before')+'.png'),Buffer.from(shot.data,'base64'));
            }
            assert.equal(metrics.errors,false,name+' renders without PHP errors');
            assert.equal(metrics.horizontalOverflow,false,name+' fits '+width+'px');
            if(process.env.AUDIT_LABEL==='after') {
                const common=await evaluate(`(()=>{const wrap=document.createElement('div');wrap.hidden=true;wrap.innerHTML='<button class="btn-gnc-primary">Save</button><button class="btn-gnc">Apply</button><span class="status-badge draft">Draft</span><span class="ce-badge ce-badge-draft">Draft</span><span class="status-badge pending">Pending</span><span class="ce-badge ce-badge-pending">Pending</span><div class="gnc-toast success">Saved</div><div class="ann-toast">Saved</div><div class="evt-toast">Saved</div>';document.body.append(wrap);const styles=Array.from(wrap.children,el=>{const c=getComputedStyle(el);return {background:c.backgroundColor,color:c.color,radius:c.borderRadius,font:c.fontSize,shadow:c.boxShadow};});wrap.remove();return styles;})()`);
                assert.deepEqual(common[0],common[1],name+' Apply reuses Save style');
                assert.deepEqual(common[2],common[3],name+' draft badges match');
                assert.deepEqual(common[4],common[5],name+' pending badges match');
                assert.deepEqual(common[6],common[7],name+' news/announcement toast matches shared toast');
                assert.deepEqual(common[6],common[8],name+' event toast matches shared toast');
                if(['news','announcements','events'].includes(name)) {
                    assert.equal(metrics.button.background,'rgb(234, 186, 59)');assert.equal(metrics.button.radius,'8px');
                    const trigger={news:'#btn-new-news',announcements:'#btn-new-announcement',events:'#addEventBtnTop'}[name];
                    await evaluate('document.querySelector('+JSON.stringify(trigger)+').click()');
                    await until(()=>evaluate('Boolean(document.querySelector(".modal.show"))'),'Create dialog unavailable: '+name);
                    const dialog=await evaluate('(()=>{const modal=document.querySelector(".modal.show"),c=getComputedStyle(modal.querySelector(".modal-content"));return {radius:c.borderRadius,titleFont:getComputedStyle(modal.querySelector(".modal-title")).fontSize,submitColor:getComputedStyle(modal.querySelector("[type=submit]")).backgroundColor};})()');
                    assert.equal(dialog.radius,width<576?'0px':'12px',name+' retains fullscreen mobile dialog');assert.equal(dialog.titleFont,'16px');assert.equal(dialog.submitColor,'rgb(234, 186, 59)');
                    await evaluate('bootstrap.Modal.getInstance(document.querySelector(".modal.show")).hide()');
                }
                if(name==='settings') assert.equal(await evaluate('document.querySelector(".page-header p").parentElement===document.querySelector(".page-header h1").parentElement'),true);
            }
        }
    }
    if(process.env.AUDIT_LABEL==='after') {
        await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".ve-stage")).transitionDuration'),'0s');
        await send('Page.navigate',{url:base+'/admin/news.php'});
        await until(()=>evaluate('document.readyState!=="loading"&&Boolean(document.querySelector("#btn-new-news"))'),'News did not load for motion check');
        await evaluate('window.motionToast=document.createElement("div");motionToast.className="ann-toast show";document.body.append(motionToast)');
        assert.equal(await evaluate('getComputedStyle(motionToast).transitionDuration'),'0s');
        await evaluate('AdminDialogs.confirm("Log out of the admin panel?",{confirmText:"Log out"});void 0');
        await until(()=>evaluate('Boolean(document.querySelector(".gnc-dialog[open]"))'),'Confirmation dialog unavailable');
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".gnc-dialog")).borderRadius'),'12px');
        assert.equal(await evaluate('getComputedStyle(document.querySelector(".gnc-dialog-accept")).backgroundColor'),'rgb(9, 64, 36)');
        await evaluate('document.querySelector(".gnc-dialog-cancel").click()');
        await send('Emulation.setEmulatedMedia',{features:[]});
    }
    const destination=path.join(auditDirectory,(process.env.AUDIT_LABEL||'before')+'.json');fs.writeFileSync(destination,JSON.stringify(entries,null,2));
    console.log(JSON.stringify(entries.map(e=>({page:e.name,width:e.width,button:e.button&&[e.button.background,e.button.radius,e.button.fontSize],badge:e.badge&&[e.badge.background,e.badge.color],overflow:e.horizontalOverflow}))));
    console.log('Audit saved: '+destination);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php','destroy',adminSession.value],{encoding:'utf8',windowsHide:true});if(socket)socket.close();browser.kill();await delay(1000);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-calendar-widget-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
