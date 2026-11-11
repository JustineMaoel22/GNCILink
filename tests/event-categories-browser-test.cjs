// Read-only checks of the local Events category dropdown, including an empty calendar.
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const sessionTool = args => {
    const result = spawnSync('docker', ['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php', ...args], {encoding:'utf8', windowsHide:true});
    if (result.status !== 0) throw Error(result.stderr || result.stdout);
    return result.stdout;
};
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-event-categories-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true, stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(fn, message) {
    for (let end=Date.now()+20000; Date.now()<end;) { if (await fn()) return; await delay(100); }
    throw Error(message);
}
let socket, session;
(async () => {
    session = JSON.parse(sessionTool(['Super Admin']));
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab => tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, {once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message', event => { const msg=JSON.parse(event.data); if (pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); } });
    const send = (method, params={}) => new Promise((resolve,reject) => { const id=++serial; pending.set(id,msg => msg.error ? reject(Error(JSON.stringify(msg.error))) : resolve(msg.result)); socket.send(JSON.stringify({id,method,params})); });
    const evaluate = async expression => { const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true}); if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails)); return result.result.value; };
    await send('Page.enable'); await send('Network.enable');
    await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});
    await send('Page.navigate',{url:'http://localhost:8080/admin/events'});
    await until(() => evaluate("document.readyState==='complete'&&!!document.getElementById('eventCategory')").catch(()=>false),'Events dropdown unavailable');
    const options = await evaluate("Array.from(document.getElementById('eventCategory').options).filter(o=>o.value&&!o.hidden).map(o=>o.textContent.trim())");
    assert.deepEqual(options, ['Alumni','Holiday','School Activities','School Event','Suspension']);
    assert.equal(await evaluate("document.getElementById('eventCategory').value"), '');
    const audience = await evaluate(`(() => {
        const departments=document.getElementById('event-dept-multiselect')._gncMsInstance;
        const programs=document.getElementById('event-program-multiselect')._gncMsInstance;
        const items=programs.config.items.slice();
        const ids=departments.config.items.map(item=>String(item.value)).filter(id=>items.some(p=>p.departmentId===id));
        if (!ids.length) throw Error('No department with programs available for verification');
        const selected=ids.slice(0,2);
        departments.setSelected([selected[0]]);
        const first={actual:programs.getSelected().sort(),expected:items.filter(p=>p.departmentId===selected[0]).map(p=>String(p.value)).sort()};
        departments.setSelected(selected);
        const multiple={actual:programs.getSelected().sort(),expected:items.filter(p=>selected.includes(p.departmentId)).map(p=>String(p.value)).sort()};
        const subset=programs.getSelected().slice(0,1);
        programs.setSelected(subset);
        const manual=programs.getSelected();
        departments.deselectAll();programs.deselectAll();
        return {first,multiple,manual,subset,reset:programs.getSelected()};
    })()`);
    assert.deepEqual(audience.first.actual, audience.first.expected, 'Selecting a department must select all its programs');
    assert.deepEqual(audience.multiple.actual, audience.multiple.expected, 'Multiple departments must select their combined programs');
    assert.deepEqual(audience.manual, audience.subset, 'Manual program choices must remain editable');
    assert.deepEqual(audience.reset, [], 'Clearing departments must clear automatic selections');
    for (const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'), 'Events page overflows');
    }
    console.log('PASS: Events offers its five event types including Alumni, defaults to Not set, and fits desktop/mobile widths. No content changed.');
    console.log('PASS: actual Events department selections automatically select their programs, combine departments, allow manual adjustments and reset.');
})().catch(error => { console.error(error); process.exitCode=1; }).finally(() => {
    if (session) sessionTool(['destroy', session.value]);
    socket?.close(); browser.kill();
});
