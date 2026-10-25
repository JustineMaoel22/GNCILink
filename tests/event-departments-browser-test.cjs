const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
function sessionCommand(...args) {
    const result = spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','tests/admin-design-session.php',...args],{encoding:'utf8',windowsHide:true});
    if (result.status !== 0) throw Error(result.stderr || result.stdout || result.error);
    return result.stdout;
}
const session = JSON.parse(sessionCommand('create'));
const profile = fs.mkdtempSync(path.join(os.tmpdir(),'gnci-event-audience-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve=>setTimeout(resolve,ms));
async function until(work,message) { for(let end=Date.now()+30000;Date.now()<end;) { if(await work()) return; await delay(100); } throw Error(message); }
let socket;
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome did not start');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message',event=>{const packet=JSON.parse(event.data);if(packet.id&&pending.has(packet.id)){pending.get(packet.id)(packet);pending.delete(packet.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,result=>result.error?reject(Error(JSON.stringify(result.error))):resolve(result.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const base='http://localhost:8080';
    await send('Page.enable'); await send('Network.enable');
    await send('Network.setCookie',{name:session.name,value:session.value,url:base,httpOnly:true});
    await send('Page.navigate',{url:base+'/admin/events.php'});
    await until(()=>evaluate("Boolean(document.getElementById('eventDepartment') && window.bootstrap && document.getElementById('addEventBtn'))"),'Events UI unavailable');
    // The modal handlers are registered after the shared libraries load.
    await delay(500);
    for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});
        await evaluate("document.getElementById('addEventBtn').click()");
        await until(()=>evaluate("Boolean(document.querySelector('.modal.show #eventDepartment'))"),'Add Event modal did not open');
        await delay(400);
        const result=await evaluate(`(()=>{
            const department=document.getElementById('eventDepartment'),program=document.getElementById('eventProgram');
            const before=Array.from(program.options,option=>option.value);
            const choose=()=>department.dispatchEvent(new Event('change',{bubbles:true}));
            department.value=department.options[1].value; choose();
            const departmentOnly=program.value==='ALL' && !program.required && /this department/.test(program.selectedOptions[0].textContent);
            const option=Array.from(program.options).find(option=>option.value!=='ALL' && !option.disabled);
            if(option) program.value=option.value;
            department.value=department.options[2].value; choose();
            const resetOnChange=program.value==='ALL';
            department.value=''; choose();
            const everyone=program.value==='ALL' && Array.from(program.options).every(option=>!option.disabled);
            const modal=department.closest('.modal-content').getBoundingClientRect();
            return {before,departmentOnly,resetOnChange,everyone,fits:modal.width<=innerWidth};
        })()`);
        assert(result.departmentOnly,'Department can be selected without a program');
        assert(result.resetOnChange,'Changing department clears the prior program');
        assert(result.everyone,'Clearing department restores all eligible programs');
        assert(result.fits,'Modal fits viewport');
        for(const code of ['JHSHP','HAS','IDT','EHSS','AE','ABE','HAS2','IDT2','EHSS2','AE2','ABE2']) assert(!result.before.includes(code),'Basic Education stays excluded: '+code);
        await evaluate("bootstrap.Modal.getInstance(document.querySelector('.modal.show')).hide()");
        await until(()=>evaluate("!document.querySelector('.modal.show')"),'Modal did not close');
        await delay(400);
    }
    console.log('PASS: desktop/mobile department-only selection, optional program, reset on department change, eligible program choices and modal width. No events saved.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{
    sessionCommand('destroy',session.value);
    if(socket)socket.close();browser.kill();await delay(500);
    if(path.dirname(profile)===os.tmpdir()&&path.basename(profile).startsWith('gnci-event-audience-'))try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200});}catch{}
});
