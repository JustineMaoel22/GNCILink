const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket,auth;
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
    const send = (method,params={}) => new Promise((resolve,reject) => {
        const id=++serial; pending.set(id,data => data.error ? reject(Error(JSON.stringify(data.error))) : resolve(data.result));
        socket.send(JSON.stringify({id,method,params}));
    });
    const evaluate = async expression => {
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };





    await send('Page.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    const base='http://localhost:8080';
    const metrics=`(()=>{const e=document.querySelector('.hero-content'),b=document.querySelector('.hero-ctas'),r=e.getBoundingClientRect();return {align:getComputedStyle(e).textAlign,direction:getComputedStyle(b).flexDirection,center:r.left+r.width/2,body:document.body.getBoundingClientRect().width/2};})()`;
    for(const width of [390,768,820,1024,1280]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1024,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+'/'});
        await until(()=>evaluate("document.readyState==='complete'&&!!document.querySelector('.hero-content')"),'Homepage unavailable');
        const m=await evaluate(metrics);
        assert.equal(m.align,width<=1024?'center':'left');
        assert.equal(m.direction,width<=1024?'column':'row');
        if(width<=1024)assert.ok(Math.abs(m.center-m.body)<2,'Hero centered at '+width);
        console.log('Visitor '+width+': '+m.align+', buttons '+m.direction);
    }
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=home'});
    await until(()=>evaluate("window.VE?.state?.win?.GNCResponsiveCanvas&&document.getElementById('veLoader').hidden"),'Editor unavailable');
    for(const device of ['tablet','mobile','desktop']){
        await evaluate(`document.querySelector('.ve-devices [data-device=${device}]').click()`);await delay(400);
        const m=await evaluate(`VE.state.win.eval(${JSON.stringify(metrics)})`);
        assert.equal(m.align,device==='desktop'?'left':'center');
        assert.equal(m.direction,device==='desktop'?'row':'column');
        console.log('Editor '+device+': '+m.align+', buttons '+m.direction);
        if(device==='tablet'){
            const shot=await send('Page.captureScreenshot',{format:'png'});
            fs.writeFileSync(path.join(__dirname,'home-tablet-centered.png'),Buffer.from(shot.data,'base64'));
        }
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(socket)socket.close();browser.kill();});
