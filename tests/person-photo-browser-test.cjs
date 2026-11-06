const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-photo-'));
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
    const portrait = fs.readFileSync(path.join(__dirname,'../uploads/administration/086f12f12d75f5d4788185497d0df834.jpg')).toString('base64');
    await evaluate(`document.head.innerHTML='<meta name="viewport" content="width=device-width,initial-scale=1"><style>*{box-sizing:border-box}body{font-family:Arial,sans-serif}</style>';
        window.portrait=${JSON.stringify(portrait)};
        document.body.innerHTML='<label>Full name<input id="adm-person-name" value="Sir CJ"></label><input type="file" id="adm-person-photo" data-profile-key="person:2">';
        window.CE_CONFIG={csrf:'test-csrf'}; window.accepted=0;window.requests=[];
        document.getElementById('adm-person-photo').addEventListener('change',()=>accepted++);
        window.fetch=async (url,options)=>{requests.push(Object.fromEntries(options.body));return {json:async()=>window.reply};};`);
    await evaluate(fs.readFileSync(path.join(__dirname,'../assets/js/photo-check.js'),'utf8'));
    const pick = async reply => {
        await evaluate(`(()=>{window.reply=${JSON.stringify(reply)};const transfer=new DataTransfer();transfer.items.add(new File([Uint8Array.from(atob(window.portrait),c=>c.charCodeAt(0))],'portrait.jpg',{type:'image/jpeg'}));const input=document.getElementById('adm-person-photo');input.files=transfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await delay(100);
    };
    const match = {key:'person:1',name:'Existing <img onerror="alert(1)"> Person',image:'data:image/jpeg;base64,'+portrait,similarity:.8,high_confidence:true};
    const blocked = {success:true,checked:true,ok:false,reason:'duplicate_face',message:'Please verify the person’s photo.',matches:[match],can_review:false};
    const repeated = [match,{...match,key:'office:2'},{...match,key:'dean:3',similarity:.9}];
    await pick({...blocked,matches:repeated});
    assert.equal(await evaluate('accepted'),0);
    assert.equal(await evaluate('document.querySelectorAll(".pc-compare img").length'),2);
    assert.equal(await evaluate('document.querySelectorAll(".pc-upload img").length'),1);
    assert.equal(await evaluate('document.querySelectorAll(".pc-existing img").length'),1);
    assert.equal(await evaluate('document.querySelector(".pc-existing small").textContent'),'Used in 3 profiles');
    assert.equal(await evaluate('document.querySelectorAll(".pc-compare [onerror]").length'),0);
    assert.deepEqual(await evaluate('requests[0].person_name'), 'Sir CJ');
    assert.equal(await evaluate('requests[0].profile_key'),'person:2');
    assert.equal(await evaluate('document.querySelectorAll(".pc-actions button").length'),1);
    await evaluate('document.querySelector(".pc-actions button").click()');
    assert.equal(await evaluate('document.getElementById("adm-person-photo").files.length'),0);
    await pick({...blocked,matches:[...repeated,{...match,key:'person:9',name:'Another Person'}]});
    assert.equal(await evaluate('document.querySelectorAll(".pc-upload img").length'),1);
    assert.equal(await evaluate('document.querySelectorAll(".pc-existing img").length'),2);
    await evaluate('document.querySelector(".pc-actions button").click()');
    await pick({...blocked,ok:true,can_review:false});
    assert.equal(await evaluate('accepted'),0);
    await evaluate('document.querySelectorAll(".pc-actions button")[1].click()');
    assert.equal(await evaluate('accepted'),1);
    await pick({...blocked,can_review:true,matches:repeated});
    assert.equal(await evaluate('document.querySelector(".pc-box textarea")'),null);
    await evaluate(`window.reply={success:true,checked:true,ok:true,reviewed:true,reason:'duplicate_face'};
        document.querySelectorAll('.pc-actions button')[1].click();`);
    await until(() => evaluate('accepted===2'), 'Review did not accept photo');
    assert.equal(await evaluate('requests.at(-1).review_duplicate'),'1');
    assert.equal(await evaluate('requests.at(-1).review_reason'),undefined);
    await pick({success:true,checked:false,ok:false,reason:'unavailable',message:'Try again.'});
    assert.equal(await evaluate('accepted'),2);
    assert.equal(await evaluate('document.getElementById("adm-person-photo").files.length'),0);
    await evaluate('document.querySelector(".pc-btn").click()');
    await pick({success:true,checked:true,ok:true,reason:'clear'});
    assert.equal(await evaluate('accepted'),3);
    // Capture the actual comparison layout at phone and desktop widths.
    await pick({...blocked,can_review:true});
    fs.mkdirSync(path.join(__dirname,'../cache/person-photo-tests'),{recursive:true});
    for (const width of [390,1440]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<500});
        assert.equal(await evaluate('document.querySelector(".pc-box").getBoundingClientRect().width <= innerWidth'),true);
        const screenshot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,`../cache/person-photo-tests/comparison-${width}.png`),Buffer.from(screenshot.data,'base64'));
    }
    console.log('Personnel photo browser: comparison, escaping, blocking, acknowledgement, review, outage and responsive checks passed.');
})().catch(error => { console.error(error); process.exitCode=1; }).finally(() => { if(socket) socket.close(); browser.kill(); });
