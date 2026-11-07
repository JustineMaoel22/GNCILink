const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
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
    await send('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:'http://localhost:8080/pages/news-and-announcement.php'});
    await until(()=>evaluate("Boolean(window.GNCBot && document.querySelector('#gncbot.gncbot-ready'))"),'Chatbot unavailable');
    await delay(2600);
    await evaluate("document.querySelectorAll('dialog[open]').forEach(dialog=>dialog.close());document.querySelector('[data-privacy-action=reject]')?.click()");
    const move=async(x,y)=>{await send('Input.dispatchMouseEvent',{type:'mouseMoved',x,y});await delay(200);};
    const state=()=>evaluate("({tracking:document.getElementById('gncbot').classList.contains('gncbot-tracking'),x:document.getElementById('gncbot').style.getPropertyValue('--gb-look-x'),filter:getComputedStyle(document.querySelector('.gncbot-mascot')).filter})");
    const screenshot=async name=>{const clip=await evaluate("(()=>{const b=document.querySelector('.gncbot-launcher').getBoundingClientRect();return {x:b.x-12,y:b.y-12,width:b.width+24,height:b.height+24,scale:2};})()");const result=await send('Page.captureScreenshot',{format:'png',clip});fs.writeFileSync(path.join(__dirname,'../cache/'+name+'.png'),Buffer.from(result.data,'base64'));};
    await screenshot('gaze-neutral');
    await move(20,20);
    const left=await state();assert.equal(left.tracking,true);assert.ok(parseFloat(left.x)<0);assert.ok(!left.filter.includes('url('),'No eye distortion filter');
    await screenshot('gaze-left');
    await move(1278,880);
    const right=await state();assert.ok(parseFloat(right.x)>0);assert.notEqual(left.x,right.x);
    await evaluate("window.dispatchEvent(new Event('blur'))");assert.equal((await state()).tracking,false);
    await move(20,20);await evaluate('GNCBot.open()');assert.equal((await state()).tracking,false);
    assert.equal(await evaluate("document.querySelector('.gncbot-launcher').getAttribute('aria-expanded')"),'true');
    assert.equal(await evaluate("document.querySelector('.gncbot-mascot').src.includes('chatbot-excited.png') && getComputedStyle(document.querySelector('.gncbot-mascot')).display!=='none'"),true,'Opening chat shows the excited reaction');
    await move(500,400);assert.equal((await state()).tracking,false);await evaluate('GNCBot.close()');await delay(350);
    assert.equal(await evaluate("document.querySelector('.gncbot-mascot').getAttribute('src')===document.querySelector('.gncbot-mascot').dataset.idleSrc"),true,'Closing restores the normal mascot');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await move(20,20);assert.equal((await state()).tracking,false);
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'no-preference'}]});
    await evaluate("document.dispatchEvent(new PointerEvent('pointermove',{pointerType:'touch',clientX:20,clientY:20,bubbles:true}))");await delay(50);assert.equal((await state()).tracking,false);
    console.log('PASS: head-only cursor direction, no eye distortion, reset, chat open/close, reduced motion, and touch-pointer exclusion.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});
