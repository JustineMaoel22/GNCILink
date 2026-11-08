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
    const base='http://localhost:8080';
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1536,height:900,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-departments&sub=cased'});
    await until(()=>evaluate("window.VE?.state?.doc&&document.getElementById('veLoader').hidden"),'Editor unavailable');
    await evaluate('document.fonts.ready');
    for(const width of [1920,1536,1366,1199,1032,820,575,390,320]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});await delay(250);
        const m=await evaluate(`(()=>{const top=document.querySelector('.ve-top'),els=[...top.querySelectorAll('button,a,summary,select')].filter(e=>e.getClientRects().length&&!e.closest('.ve-menu-list'));return {height:top.getBoundingClientRect().height,overflow:els.filter(e=>{const r=e.getBoundingClientRect();return r.left<-.5||r.right>innerWidth+.5}).map(e=>e.id||e.className),devices:[...document.querySelectorAll('.ve-devices button')].filter(e=>e.getClientRects().length).length,nav:document.querySelector('.ve-navigation').getBoundingClientRect().top,actions:document.querySelector('.ve-actions').getBoundingClientRect().top,canvas:document.querySelector('.ve-canvas').getBoundingClientRect().height};})()`);
        console.log(width,JSON.stringify(m));assert.deepEqual(m.overflow,[],'Controls fit at '+width);assert.equal(m.devices,3);assert.ok(m.canvas>400);
        if(width===1536)assert.ok(Math.abs(m.nav-m.actions)<5,'Toolbar fits one row on desktop');
        if([1536,820,390].includes(width)){
            const shot=await send('Page.captureScreenshot',{format:'png'});
            fs.writeFileSync(path.join(__dirname,'editor-toolbar-'+width+'.png'),Buffer.from(shot.data,'base64'));
        }
    }
    await evaluate("document.querySelector('.ve-devices [data-device=tablet]').click()");
    assert.equal(await evaluate('VE.state.device'),'tablet');
    await evaluate("document.getElementById('vePageMenu').open=true");
    const menu=await evaluate(`(()=>{const r=document.querySelector('.ve-menu-list').getBoundingClientRect();return {left:r.left,right:r.right}})()`);assert.ok(menu.left>=0&&menu.right<=320);
    for(const route of ['home','programs']){
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page='+route});
        await until(()=>evaluate("window.VE?.state?.doc&&document.getElementById('veLoader').hidden"),'Editor unavailable');
        for(const width of [390,820,1536]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});await delay(250);
            const clipped=await evaluate(`!![...document.querySelectorAll('.ve-top button,.ve-top summary,.ve-top select,.ve-top > a')].filter(e=>e.getClientRects().length&&!e.closest('.ve-menu-list')).find(e=>{const r=e.getBoundingClientRect();return r.left<0||r.right>innerWidth+.5})`);
            assert.equal(clipped,false,route+' controls fit at '+width);
        }
    }
    console.log('PASS: responsive toolbar fits desktop, tablet and phone; device switching and page menu remain accessible.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(socket)socket.close();browser.kill();});

