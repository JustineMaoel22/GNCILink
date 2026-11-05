const assert=require('node:assert/strict'),fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const php=(file,args=[])=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php',file,...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
const sessions=[];let socket;
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-dashboard-'));
const browser=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
(async()=>{
    const roles=['Super Admin','Content Editor','The Mirror'];
    const accounts=roles.map(role=>{const account=JSON.parse(php('cache/activity-log-session.php',[role]));sessions.push(account);return account;});
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));let serial=0;const pending=new Map();let send;
    const errors=[];
    socket.addEventListener('message',event=>{const p=JSON.parse(event.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}
        if(p.method==='Fetch.requestPaused')send('Fetch.fulfillRequest',{requestId:p.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from('{"success":true}').toString('base64')});
        if(p.method==='Runtime.exceptionThrown')errors.push(p.params.exceptionDetails.text);
    });
    send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true,userGesture:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    const base='http://localhost:8080';await send('Page.enable');await send('Network.enable');await send('Runtime.enable');
    await send('Fetch.enable',{patterns:[{urlPattern:'*/admin/action/onboarding-handler.php*',requestStage:'Request'}]});
    const closeTour=async()=>{
        await delay(150);
        if(await evaluate("Boolean(document.querySelector('.gnc-tour[open]'))")){
            await evaluate("[...document.querySelectorAll('.gnc-tour button')].find(b=>b.textContent==='Skip').click()");
            await until(()=>evaluate("!document.querySelector('.gnc-tour')"),'Tour did not close');
        }
    };
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page unavailable');await closeTour();};
    const resize=async width=>{await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);};
    const shot=async name=>fs.writeFileSync('cache/'+name+'.png',Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
    for(let i=0;i<roles.length;i++){
        await send('Network.setCookie',{...accounts[i],url:base,httpOnly:true});
        await resize(1440);await visit('/admin/admin-dash.php');
        const original=await evaluate('location.href');
        assert.ok(await evaluate("document.getElementById('notifications-panel').hidden"));
        await evaluate("document.getElementById('notifications-toggle').click()");
        assert.equal(await evaluate('location.href'),original);
        assert.ok(await evaluate("!document.getElementById('notifications-panel').hidden"));
        assert.equal(await evaluate("document.getElementById('notifications-toggle').getAttribute('aria-expanded')"),'true');
        assert.ok(await evaluate("!document.querySelector('#notifications-panel [role=status]')"),'Notification data loads for '+roles[i]);
        await shot('notifications-'+i+'-desktop');
        await evaluate("document.getElementById('notifications-read').click()");
        assert.ok(await evaluate("document.querySelector('.topbar-notif-dot').hidden"));
        await visit('/admin/admin-dash.php');
        assert.ok(await evaluate("document.querySelector('.topbar-notif-dot').hidden"),'Read state survives reload');
        await evaluate("document.getElementById('notifications-toggle').click()");
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        assert.ok(await evaluate("document.getElementById('notifications-panel').hidden"));
        assert.ok(await evaluate("document.activeElement.id==='notifications-toggle'"));
        await evaluate("document.getElementById('notifications-toggle').click();document.querySelector('.topbar-breadcrumb').click()");
        assert.ok(await evaluate("document.getElementById('notifications-panel').hidden"));
        for(const width of [820,390,320]){
            await resize(width);await evaluate("document.getElementById('notifications-toggle').click()");
            assert.ok(await evaluate("(()=>{const r=document.getElementById('notifications-panel').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth})()"),'Panel fits '+width);
            if(width===390)await shot('notifications-'+i+'-mobile');
            await evaluate("document.getElementById('notifications-toggle').click()");
        }
    }
    assert.equal(errors.length,0,'No browser script exceptions');
    console.log('Notification checks passed for Super Admin, Content Editor and The Mirror: data loading, toggle without navigation, read persistence, Escape/focus, outside dismissal, and panel bounds at 1440/820/390/320px.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{for(const s of sessions)php('cache/activity-log-session.php',['destroy',s.value]);}finally{socket?.close();browser.kill();}});
