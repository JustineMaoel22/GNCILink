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
        const role=roles[i],superAdmin=i===0,mirror=i===2;
        const data=JSON.parse(php('cache/dashboard-snapshot.php',[role]));
        await send('Network.setCookie',{...accounts[i],url:base,httpOnly:true});await resize(1440);await visit('/admin/admin-dash.php');
        assert.ok(await evaluate("document.querySelector('.dashboard-page')!==null"));
        const values=await evaluate("[...document.querySelectorAll('.dashboard-stat>strong')].map(e=>e.textContent)");
        const expected=[data.stats.total_announcements,data.stats.total_news,...(mirror?[]:[data.stats.upcoming_events]),data.stats.pending_approvals].map(n=>n===null?'Unavailable':Number(n).toLocaleString('en-US'));
        assert.deepEqual(values,expected,'Counts match database for '+role);
        const recent=await evaluate("[...document.querySelectorAll('#recent-content .dashboard-item-main>a')].map(e=>e.textContent)");
        assert.deepEqual(recent,data.recent.map(item=>item.title),'Recent content matches scoped database records');
        const actions=await evaluate("[...document.querySelectorAll('.dashboard-actions a')].map(e=>({text:e.textContent,href:e.getAttribute('href')}))");
        assert.equal(actions.some(a=>a.text==='Manage users'),superAdmin);
        assert.equal(actions.some(a=>a.text==='Review approvals'),superAdmin);
        assert.equal(actions.some(a=>a.text==='Plan events'),!mirror);
        assert.equal(actions.some(a=>a.text==='Edit website pages'),!mirror);
        assert.equal(await evaluate("document.querySelector('.dashboard-activity-list')!==null||document.querySelector('#dashboard-activity-title')!==null"),superAdmin);
        assert.equal(await evaluate("document.getElementById('dashboard-submissions-title')!==null"),!superAdmin);
        assert.equal(await evaluate("document.getElementById('website-activity')!==null"),!mirror);
        assert.ok(await evaluate("document.getElementById('dashClockTime').textContent.length>0"));
        assert.ok(!actions.some(a=>a.href.includes('/logs/')||a.href.includes('.php/')));
        await shot('dashboard-'+(superAdmin?'super':mirror?'mirror':'editor'));
        for(const width of [820,390]){await resize(width);await evaluate('scrollTo(0,0)');assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'No overflow for '+role+' at '+width);if(superAdmin)await shot('dashboard-'+width);}
        if(superAdmin&&data.analytics){
            await evaluate("document.getElementById('website-activity').scrollIntoView({block:'start',behavior:'instant'})");
            assert.equal(await evaluate("document.querySelectorAll('.dashboard-bar').length"),14);
            await shot('dashboard-analytics-mobile');
            await evaluate("document.querySelector('.dashboard-daily-totals summary').click()");
            assert.equal(await evaluate("document.querySelectorAll('.dashboard-daily-totals tbody tr').length"),14);
            await evaluate("document.querySelector('.dashboard-range a').click()");
            await until(()=>evaluate("document.readyState==='complete'&&document.querySelectorAll('.dashboard-bar').length===7"),'Seven-day range failed');await closeTour();
            assert.equal(await evaluate("document.querySelector('.dashboard-range [aria-current]').textContent"),'7 days');
            await visit('/admin/admin-dash.php?days=30');assert.equal(await evaluate("document.querySelectorAll('.dashboard-bar').length"),30);
            await visit('/admin/admin-dash.php?days%5B%5D=30');assert.equal(await evaluate("document.querySelectorAll('.dashboard-bar').length"),14);
        }
        // Create shortcuts open existing forms without submitting content.
        await visit('/admin/admin-dash.php');
        await evaluate("[...document.querySelectorAll('.dashboard-actions a')].find(a=>a.textContent==='Write news article').click()");
        await until(()=>evaluate("Boolean(document.querySelector('#newsModal.show'))"),'News creation shortcut failed');
        await visit('/admin/admin-dash.php');
        await evaluate("[...document.querySelectorAll('.dashboard-actions a')].find(a=>a.textContent==='Create announcement').click()");
        await until(()=>evaluate("Boolean(document.querySelector('#announcementModal.show'))"),'Announcement creation shortcut failed');
    }
    await send('Network.setCookie',{...accounts[0],url:base,httpOnly:true});
    await send('Emulation.setScriptExecutionDisabled',{value:true});await visit('/admin/admin-dash.php');
    assert.equal(await evaluate("document.querySelectorAll('.dashboard-stat').length"),4);
    await send('Emulation.setScriptExecutionDisabled',{value:false});
    await send('Network.deleteCookies',{name:accounts[0].name,url:base});
    const guest=await evaluate("fetch('/admin/admin-dash.php').then(r=>r.url)");assert.ok(/\/(?:admin\/login\.php|login)(?:[?#]|$)/.test(guest));
    assert.equal(errors.length,0,'No browser script exceptions');
    console.log('Browser checks passed: actual scoped counts and recent content for all three roles, restricted links, 1440/820/390px overflow, 7/14/30-day analytics, daily totals, malformed days, creation shortcuts, clock, no-JS and guest redirect. No content, analytics or onboarding records changed.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{try{for(const s of sessions)php('cache/activity-log-session.php',['destroy',s.value]);}finally{socket?.close();browser.kill();}});
