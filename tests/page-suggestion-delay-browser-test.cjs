const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket,auth,mirrorAuth;
const session=args=>{const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
async function until(fn, message) {
    for (let end=Date.now()+15000; Date.now()<end;) { if (await fn()) return; await delay(50); }
    throw Error(message);
}
(async () => {
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open',resolve,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message', event => { const data=JSON.parse(event.data); if (pending.has(data.id)) { pending.get(data.id)(data); pending.delete(data.id); } });
    const send = (method,params={},sessionId) => new Promise((resolve,reject) => {
        const id=++serial; pending.set(id,data => data.error ? reject(Error(JSON.stringify(data.error))) : resolve(data.result));
        socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));
    });
    const evaluate = async expression => {
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };





    await send('Page.enable');await send('Network.enable');
    const base='http://localhost:8080';
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Page.navigate',{url:base+'/admin/settings'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.getElementById('page_suggestion_delay_seconds')"),'Delay setting unavailable');
    const form="document.getElementById('page_suggestion_delay_seconds').form";
    assert.equal(await evaluate("document.getElementById('page_suggestion_delay_seconds').closest('section').id"),'website-preferences','Delay belongs to Website Preferences');
    assert.equal(await evaluate("document.getElementById('page_suggestions_enabled').closest('section').id"),'website-preferences','Toggle and delay share one card');
    assert.equal(await evaluate(form+".dataset.autoSave"),'0');
    let calls=0;
    await send('Fetch.enable',{patterns:[{urlPattern:'*settings.php*',requestStage:'Request'}]});
    socket.addEventListener('message',async event=>{
        const data=JSON.parse(event.data);if(data.method!=='Fetch.requestPaused')return;
        const {requestId,request}=data.params;
        if(request.method!=='POST'){await send('Fetch.continueRequest',{requestId});return;}
        const body=new URLSearchParams(request.postData||'');
        assert.equal(body.get('action'),'save_page_suggestion_delay');assert.ok(body.get('csrf_token'));calls++;
        await send('Fetch.fulfillRequest',{requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify({success:true,value:body.get('page_suggestion_delay_seconds'),message:'Page suggestion delay saved.'})).toString('base64')});
    });
    await evaluate("(()=>{const el=document.getElementById('page_suggestion_delay_seconds');el.value=el.value==='120'?'90':'120';el.dispatchEvent(new Event('input',{bubbles:true}));el.form.requestSubmit();})()");
    await until(()=>evaluate(form+".querySelector('[data-settings-feedback]').textContent==='Page suggestion delay saved.'"),'Save not confirmed');assert.equal(calls,1);
    for(const width of [1440,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await evaluate("document.getElementById('page-suggestion-timing').scrollIntoView({block:'center',behavior:'instant'})");
        assert.equal(await evaluate("(()=>{const r=document.getElementById('page_suggestion_delay_seconds').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth})()"),true);
    }
    await send('Fetch.disable');
    mirrorAuth=JSON.parse(session(['Content Editor']));await send('Network.setCookie',{...mirrorAuth,url:base,httpOnly:true});
    await send('Page.navigate',{url:base+'/admin/settings'});
    await until(()=>evaluate("document.readyState==='complete'&&!!document.getElementById('student_email_url')"),'Content Editor settings unavailable');
    assert.equal(await evaluate("!!document.getElementById('page_suggestion_delay_seconds')"),false,'Timing control is Super Admin only');
    // Run the actual popup script in an isolated page with a deterministic clock and catalog.
    await send('Page.navigate',{url:'about:blank'});
    await until(()=>evaluate("location.href==='about:blank'"),'Blank test page unavailable');
    const code=fs.readFileSync(path.join(__dirname,'../assets/js/recommendations.js'),'utf8');
    for(const [setting,expected] of [['120',120000],['5',5000],['3600',3600000],['bad',60000]]){
        const result=await evaluate(`(async()=>{
            document.body.innerHTML='<dialog id="gncRecommendations" data-delay-seconds="${setting}"><div id="gncRecPrivacy"></div><button id="gncRecSettings"></button><button id="gncRecClose"></button><button id="gncRecDismiss"></button><div id="gncRecCards"></div><p id="gncRecSummary"></p><button id="gncRecEnable"></button><button id="gncRecDisable"></button><p id="gncRecStatus"></p></dialog>';
            const realTimeout=window.setTimeout,realInterval=window.setInterval,realNow=Date.now;
            let now=100000,queue=[];Date.now=()=>now;Object.defineProperty(performance,'now',{configurable:true,value:()=>0});
            window.setTimeout=(fn,ms)=>{queue.push({fn,ms});return queue.length};window.setInterval=()=>0;
            window.GNCRecommendations={canonical:x=>x,rank:()=>[{url:'/about',title:'About',description:'About GNC',category:'About',image:'/image.png'}]};
            const realFetch=window.fetch;window.fetch=async()=>({ok:true,json:async()=>({items:[]})});
            try{
                (0,eval)(${JSON.stringify(code)});
                await Promise.resolve();await Promise.resolve();await Promise.resolve();await Promise.resolve();
                const first=queue.shift();if(!first)throw Error('Popup did not schedule');
                const popup=document.getElementById('gncRecommendations');const before=popup.open;
                now+=first.ms;first.fn();return {delay:first.ms,before,after:popup.open};
            }finally{window.fetch=realFetch;window.setTimeout=realTimeout;window.setInterval=realInterval;Date.now=realNow;delete performance.now;document.querySelector('dialog[open]')?.close();}
        })()`);
        assert.deepEqual(result,{delay:expected,before:false,after:true});
    }
    console.log('PASS: Super Admin-only timing UI, explicit save and CSRF, responsive field, actual popup scheduling at configured boundaries and invalid-value fallback. No real settings changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(mirrorAuth)session(['destroy',mirrorAuth.value]);if(socket)socket.close();browser.kill();});
