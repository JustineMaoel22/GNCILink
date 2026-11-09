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
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1536,height:1100,deviceScaleFactor:1,mobile:false});
    const click=async point=>{await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:point.x,y:point.y});await send('Input.dispatchMouseEvent',{type:'mousePressed',x:point.x,y:point.y,button:'left',clickCount:1});await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:point.x,y:point.y,button:'left',clickCount:1});};
    for(const slug of ['cba','cased','coa']) {
        await evaluate('window.__oldHeaderEditor=true');
        await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-departments&sub='+slug});
        await until(()=>evaluate("!window.__oldHeaderEditor&&window.VE?.state?.doc?.querySelector('[data-dept-header-prefix]')&&document.getElementById('veLoader').hidden"),'Editor unavailable '+slug);
        await evaluate('VE.state.doc.fonts.ready');await delay(300);
        if(slug==='cba') {
            // Published CBA positions move the prefix over the subject's empty layout slot.
            // Apply only to this browser preview; do not save any CMS data.
            await evaluate(`(()=>{const w=VE.state.win,changes={};for(const [id,el] of w.GNCTextLayout.targets()){
                if(el.matches('[data-dept-header-prefix]'))changes[id]={desktop:{value:{x:.6562,y:14.6964,w:39.2749,h:40,runs:[{start:0,end:11,text:'College of ',size:32}]}}};
                if(el.matches('[data-dept-header-subject]'))changes[id]={desktop:{value:{x:.6562,y:14.6939,w:26.4567,h:160,runs:[{start:0,end:23,text:'Business Administration',size:64}]}}};
            }w.GNCTextLayout.setChanges(changes);})()`);
        }
        for(const part of ['prefix','subject']) {
            const selector='[data-dept-header-'+part+']';
            const metrics=await evaluate(`(()=>{const d=VE.state.doc,el=d.querySelector(${JSON.stringify(selector)}),walker=d.createTreeWalker(el,NodeFilter.SHOW_TEXT);let node;while((node=walker.nextNode())&&!node.textContent.trim()){}const range=d.createRange();range.setStart(node,0);range.setEnd(node,Math.min(5,node.length));const r=range.getBoundingClientRect(),x=r.x+r.width/2,y=r.y+r.height/2,hit=d.elementFromPoint(x,y),fr=VE.state.frame.getBoundingClientRect();return {x:fr.x+x,y:fr.y+y,correct:!!hit?.closest(${JSON.stringify(selector)}),hit:hit?.outerHTML.slice(0,220),editable:el.isContentEditable};})()`);
            assert.equal(metrics.correct,true,slug+' '+part+' must receive clicks on its visible text; intercepted by '+metrics.hit);
            assert.equal(metrics.editable,true,slug+' '+part+' stays editable');
            await click(metrics);await delay(100);
            assert.equal(await evaluate(`VE.state.sel?.el===VE.state.doc.querySelector(${JSON.stringify(selector)})`),true,slug+' '+part+' is selected by a real mouse click');
        }
        console.log('PASS: '+slug+' prefix and subject receive real editor clicks.');
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(socket)socket.close();browser.kill();});
