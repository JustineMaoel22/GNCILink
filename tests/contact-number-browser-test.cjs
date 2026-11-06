const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const sessions=[];
function docker(args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr);return r.stdout;}
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-contact-'));
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
    socket.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:true});});
    await evaluate(`document.head.innerHTML='<meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{font:15px Arial;margin:16px}form{max-width:420px}</style>';
        document.body.innerHTML='<form><label>Phone<input id="number" data-contact-number value="09123456789"></label><input id="local" data-contact-extension value="101"></form>';
        window.submits=0;document.querySelector('form').addEventListener('submit',()=>window.submits++);`);
    await evaluate(fs.readFileSync(path.join(__dirname,'../assets/js/contact-number.js'),'utf8'));
    await evaluate(`document.head.appendChild(Object.assign(document.createElement('style'),{textContent:${JSON.stringify(fs.readFileSync(path.join(__dirname,'../assets/css/contact-number.css'),'utf8'))}}));`);
    const enter = value => evaluate(`(()=>{const f=document.getElementById('number').__contact.input;f.value=${JSON.stringify(value)};f.setSelectionRange(f.value.length,f.value.length);f.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.getElementById("number").__contact.input.value'),'912-345-6789');
    await enter('09123456789');
    assert.equal(await evaluate('document.getElementById("number").value'),'+63-912-345-6789');
    await enter('9123456789999');
    assert.equal(await evaluate('document.getElementById("number").value'),'+63-912-345-6789');
    await enter('abc912!345@6789');
    assert.equal(await evaluate('document.getElementById("number").value'),'+63-912-345-6789');
    await enter('9123');
    assert.equal(await evaluate('document.getElementById("number").__contact.validate()'),false);
    await evaluate(`document.querySelector('form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));`);
    assert.equal(await evaluate('window.submits'),0,'Incomplete values cannot submit');
    await enter('9123456789');
    await evaluate(`document.querySelectorAll('#number + .contact-control .contact-type button')[1].click();`);
    await enter('9004473');
    await evaluate(`(()=>{const ext=document.getElementById('number').__contact.extension;ext.value='abc00123';ext.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.getElementById("number").value'),'045-900-4473 loc. 00123');
    await evaluate(`document.querySelectorAll('#number + .contact-control .contact-type button')[0].click();`);
    assert.equal(await evaluate('document.getElementById("number").value'),'+63-912-345-6789');
    assert.equal(await evaluate('document.querySelector("#number + .contact-control .contact-extension").hidden'),true);
    await evaluate(`document.querySelectorAll('#number + .contact-control .contact-type button')[1].click();`);
    assert.equal(await evaluate('document.getElementById("number").value'),'045-900-4473 loc. 00123');
    await evaluate(`(()=>{const f=document.getElementById('number').__contact.input;f.setSelectionRange(4,4);f.dispatchEvent(new InputEvent('beforeinput',{inputType:'deleteContentBackward',bubbles:true,cancelable:true}));})()`);
    assert.equal(await evaluate('document.getElementById("number").__contact.input.value'),'904-473','Backspace crosses separators');
    await evaluate(`(()=>{const f=document.getElementById('number').__contact.input,dt=new DataTransfer();dt.setData('text/plain','09123456789');f.dispatchEvent(new ClipboardEvent('paste',{clipboardData:dt,bubbles:true,cancelable:true}));})()`);
    assert.equal(await evaluate('document.getElementById("number").value'),'+63-912-345-6789','Pasted local mobile auto-converts and selects type');
    await evaluate(`(()=>{const f=document.getElementById('local').__contact.input;f.value='12abc345678';f.setSelectionRange(10,10);f.dispatchEvent(new Event('input',{bubbles:true}));})()`);
    assert.equal(await evaluate('document.getElementById("local").value'),'123456');
    await evaluate(`document.getElementById('number').value='legacy / multiple numbers';GNCContact.refresh(document.getElementById('number'));`);
    assert.equal(await evaluate('document.getElementById("number").value'),'legacy / multiple numbers');
    assert.equal(await evaluate('document.getElementById("number").__contact.validate()'),true);

    // Check the real editor integration without publishing or changing stored contacts.
    const admin=JSON.parse(docker(['Super Admin']));sessions.push(admin);
    const base=process.env.TEST_BASE_URL || 'http://localhost:8080';
    await send('Network.enable'); await send('Network.setCookie',{...admin,url:base,httpOnly:true});
    async function visit(route) {
        await send('Page.navigate',{url:base+route});
        await until(()=>evaluate('Boolean(window.VE_CONFIG && window.GNCContact && document.getElementById("veLoader")?.hidden && document.getElementById("veFrame")?.contentDocument?.readyState === "complete")'),'Editor unavailable');
    }
    await visit('/admin/content-edit.php?page=contact-us');
    await until(()=>evaluate('Boolean(document.getElementById("veFrame").contentDocument.querySelector("[data-ve-section=contact_phone]")?.__ve)'),'Contact field not bound');
    await evaluate(`document.getElementById('veFrame').contentDocument.querySelector('[data-ve-section=contact_phone]').click()`);
    await until(()=>evaluate('Boolean(document.querySelector(".ve-pop .contact-control"))'),'Contact number popover not opened');
    assert.equal(await evaluate('document.getElementById("veFrame").contentDocument.querySelector("[data-ve-section=contact_phone]").isContentEditable'),false);
    await evaluate(`(()=>{const f=document.querySelector('.ve-pop .contact-main input');f.value='123';f.setSelectionRange(3,3);f.dispatchEvent(new Event('input',{bubbles:true}));Array.from(document.querySelectorAll('.ve-pop button')).find(b=>b.textContent==='Apply').click();})()`);
    assert.equal(await evaluate('Boolean(document.querySelector(".ve-pop .contact-control"))'),true,'Incomplete contact cannot apply');
    await evaluate(`(()=>{const f=document.querySelector('.ve-pop .contact-main input'),dt=new DataTransfer();dt.setData('text/plain','045-900-4473 loc. 123');f.dispatchEvent(new ClipboardEvent('paste',{clipboardData:dt,bubbles:true,cancelable:true}));})()`);
    fs.mkdirSync(path.join(__dirname,'../cache/contact-tests'),{recursive:true});
    for(const width of [390,768,1440]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:width<500});
        await delay(150);
        assert.equal(await evaluate('(()=>{const r=document.querySelector(".ve-pop .contact-control").getBoundingClientRect();return r.left>=0 && r.right<=innerWidth;})()'),true);
        const shot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,`../cache/contact-tests/contact-${width}.png`),Buffer.from(shot.data,'base64'));
    }
    await evaluate(`Array.from(document.querySelectorAll('.ve-pop button')).find(b=>b.textContent==='Apply').click()`);
    assert.equal(await evaluate('document.getElementById("veFrame").contentDocument.querySelector("[data-ve-section=contact_phone]").textContent'),'045-900-4473 loc. 123');
    assert.equal(await evaluate('document.getElementById("veFrame").contentDocument.querySelector("[data-contact-channel=phone]").getAttribute("href")'),'tel:0459004473;ext=123');
    for (const action of ['save_draft','publish']) {
        const csrf=await evaluate('CE_CONFIG.csrf');
        const response=await fetch(base+'/admin/action/page-review-handler.php',{method:'POST',headers:{Cookie:admin.name+'='+admin.value},body:new URLSearchParams({action,csrf_token:csrf,page:'contact-us',section_key:'contact_phone',content:'abc123',allow_incomplete:'1'})});
        const result={status:response.status,data:await response.json()};
        assert.equal(result.data.success,false);
        if(action==='save_draft') assert.match(result.data.error,/complete mobile number/);
        else assert.equal(result.status,428,'Publishing still requires password confirmation');
    }
    await visit('/admin/content-edit.php?page=administration&section=heads');
    await until(()=>evaluate('Boolean(document.getElementById("adm-office-phone")?.__contact)'),'Office settings contact control missing');
    const editor=JSON.parse(docker(['Content Editor']));sessions.push(editor);
    const editorCookie=editor.name+'='+editor.value;
    const editorHtml=await(await fetch(base+'/admin/content-edit.php?page=contact-us',{headers:{Cookie:editorCookie}})).text();
    const editorCsrf=editorHtml.match(/csrf(?:Token)?\s*:\s*"([a-f0-9]+)"/)[1];
    for (const [route,fields] of [
        ['/admin/action/page-review-handler.php',{action:'save_draft',page:'contact-us',section_key:'contact_phone',content:'not a telephone'}],
        ['/admin/action/page-content-handler.php',{action:'admin_save_office',phone:'not a telephone'}],
        ['/admin/action/page-content-handler.php',{action:'save_department_dean',dean_phone:'not a telephone'}],
    ]) {
        const response=await fetch(base+route,{method:'POST',headers:{Cookie:editorCookie},body:new URLSearchParams({csrf_token:editorCsrf,...fields})});
        const result=await response.json();assert.equal(response.status,422);assert.equal(result.success,false);assert.match(result.error,/complete mobile number/);
    }
    console.log('Contact controls: formatting, limits, paste, deletion, type memory, extensions, legacy values, responsive layouts, live draft rejection and publication authorization passed.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{
    if(socket)socket.close();browser.kill();for(const session of sessions)docker(['destroy',session.value]);
});
