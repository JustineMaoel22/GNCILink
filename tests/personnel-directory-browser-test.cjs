const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const sessions=[];
function docker(args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr);return r.stdout;}
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-personnel-'));
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
    const base=process.env.TEST_BASE_URL||'http://localhost:8080';
    const admin=JSON.parse(docker(['Super Admin']));sessions.push(admin);
    await send('Page.enable');await send('Network.enable');await send('Network.setCookie',{...admin,url:base,httpOnly:true});
    await send('Page.navigate',{url:base+'/admin/personnel.php'});
    await until(()=>evaluate('Boolean(document.querySelector(".personnel-link-form"))'),'Personnel directory unavailable');
    const count=await evaluate('document.querySelectorAll(".personnel-profile").length');assert.ok(count>0);
    await evaluate(`const search=document.getElementById('personnel-search');search.value='no-such-person-qa-unique';search.dispatchEvent(new Event('input'));`);
    assert.equal(await evaluate('Array.from(document.querySelectorAll(".personnel-profile")).filter(row=>!row.hidden).length'),0);
    await evaluate(`document.getElementById('personnel-search').value='';document.getElementById('personnel-search').dispatchEvent(new Event('input'));document.querySelector('.personnel-profile').open=true;`);
    assert.equal(await evaluate('document.querySelector(".personnel-match-name").textContent.length>0'),true);
    await evaluate(`(()=>{const select=document.querySelector('.personnel-link-form select');select.value='0';select.dispatchEvent(new Event('change'));})()`);
    assert.match(await evaluate('document.querySelector(".personnel-match-name").textContent'),/separate identity/);
    assert.equal(await evaluate('document.querySelector("[name=confirmed]").checked'),false);
    const invalid=await fetch(base+'/admin/personnel.php',{method:'POST',headers:{Cookie:admin.name+'='+admin.value},body:new URLSearchParams({profile:'person:1',personnel_id:'0',confirmed:'1'})});
    assert.equal(invalid.status,403,'Missing CSRF rejected');
    const csrf=await evaluate('document.querySelector("[name=csrf_token]").value');
    const guarded=await fetch(base+'/admin/personnel.php',{method:'POST',headers:{Cookie:admin.name+'='+admin.value},body:new URLSearchParams({action:'publish',csrf_token:csrf,profile:'person:1',personnel_id:'0',confirmed:'1'})});
    assert.equal(guarded.status,428,'Identity changes require the existing publication password confirmation');
    const missingAction=await fetch(base+'/admin/personnel.php',{method:'POST',headers:{Cookie:admin.name+'='+admin.value},body:new URLSearchParams({csrf_token:csrf,profile:'person:1',personnel_id:'0',confirmed:'1'})});
    assert.equal(missingAction.status,422,'Omitting the publication action cannot bypass confirmation');
    fs.mkdirSync(path.join(__dirname,'../cache/personnel-tests'),{recursive:true});
    for(const width of [390,1440]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:width<500});
        await delay(150);
        assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Directory stays within viewport');
        const shot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,`../cache/personnel-tests/directory-${width}.png`),Buffer.from(shot.data,'base64'));
    }
    const editor=JSON.parse(docker(['Content Editor']));sessions.push(editor);
    const editorCookie=editor.name+'='+editor.value;
    const response=await fetch(base+'/admin/personnel.php',{method:'POST',headers:{Cookie:editorCookie},body:new URLSearchParams({profile:'person:1',personnel_id:'0',confirmed:'1'})});
    assert.equal(response.status,403,'Content Editor cannot directly link people');
    const reader=await(await fetch(base+'/admin/personnel.php',{headers:{Cookie:editorCookie}})).text();
    assert.ok(reader.includes('Ask a Super Admin'));
    assert.ok(!reader.includes('class="personnel-link-form'));
    console.log('Personnel directory: search, comparison preview, explicit confirmation, CSRF, role restrictions and responsive layouts passed. No identities were changed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();for(const session of sessions)docker(['destroy',session.value]);});
