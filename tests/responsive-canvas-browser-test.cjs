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
    // Visitor pages must never load or activate editor scaling.
    for(const width of [390,820,1032,1366,1920]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        await send('Page.navigate',{url:base+'/departments/cased'});
        await until(()=>evaluate("document.readyState==='complete'&&location.pathname==='/departments/cased'&&Boolean(window.GNCTextLayout)"),'Visitor page unavailable');await delay(150);
        assert.equal(await evaluate("typeof GNCResponsiveCanvas"),'undefined','No canvas on visitor pages');
        assert.equal(await evaluate("document.documentElement.style.zoom"),'','Visitor zoom untouched');
        assert.equal(await evaluate("Boolean(document.querySelector('script[src*=responsive-canvas]'))"),false,'No editor scaling asset on visitor pages');
    }
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Page.navigate',{url:base+'/departments/cased?ce_preview=1'});
    await until(()=>evaluate("document.readyState==='complete'&&location.search.includes('ce_preview')"),'Standalone preview unavailable');await delay(150);
    assert.equal(await evaluate('typeof GNCResponsiveCanvas'),'undefined','Standalone preview stays unscaled');
    const metrics=`(()=>{const api=GNCResponsiveCanvas,selectors=['#mainNavbar','.dept-hero','.dept-hero-content','.dept-hero-title','.dept-about-inner','.dept-logo'];return {device:api.device(),items:selectors.map(selector=>{const el=document.querySelector(selector);if(!el)return null;const r=api.rect(el);return {selector,x:r.left,y:r.top,w:r.width,h:r.height}})};})()`;
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-departments&sub=cased'});
    await until(()=>evaluate("window.VE?.state?.win?.GNCResponsiveCanvas&&document.getElementById('veLoader').hidden"),'Editor unavailable');
    for(const device of ['desktop','tablet','mobile']){
        await evaluate(`document.querySelector('.ve-devices [data-device=${device}]').click()`);await delay(300);let reference;
        for(const width of [1440,1032,700]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(350);
            const current=await evaluate(`VE.state.win.eval(${JSON.stringify(metrics)})`);
            assert.equal(current.device,device,'Monitor resizing preserves selected editor device');
            if(!reference)reference=current;
            else current.items.forEach((a,i)=>{const b=reference.items[i];if(a&&b)for(const key of ['x','y','w','h'])assert.ok(Math.abs(a[key]-b[key])<4,`${device} ${width} ${a.selector} ${key}`);});
        }
    }
    // Check editing coordinates at a scaled desktop canvas.
    await evaluate("document.querySelector('.ve-devices [data-device=desktop]').click()");await delay(300);
    const edit=await evaluate(`(()=>{const w=VE.state.win,api=w.GNCTextLayout,el=w.document.querySelector('[data-dept-header-subject]');let stored;
        const controller=api.create({state:()=>({}),stage:(id,device,value)=>{stored=value;const changes={};changes[id]={[device]:{value}};api.setChanges(changes);}});
        controller.select(el);const before=parseFloat(w.getComputedStyle(el).fontSize);controller.changeFontSize(4);
        const result={font:stored.fontSize,expected:before+4,height:stored.h,renderHeight:w.GNCResponsiveCanvas.rect(el).height};controller.dispose();api.setChanges({});return result;})()`);
    assert.equal(edit.font,edit.expected,'Font edits use design pixels once');assert.ok(edit.height>0);

    await evaluate(`(()=>{const w=VE.state.win;window.dragChanges={};window.dragController=w.GNCTextLayout.create({state:()=>({}),stage:(id,device,value)=>{dragChanges[id]={[device]:{value}};w.GNCTextLayout.setChanges(dragChanges);}});const el=w.document.querySelector('[data-dept-header-subject]');dragController.select(el);dragController.toggle();dragController.toggleSnap();})()`);
    const pointerStart=await evaluate(`(()=>{const d=VE.state.doc,fr=VE.state.frame.getBoundingClientRect(),el=d.querySelector('[data-dept-header-subject]'),r=el.getBoundingClientRect(),b=d.querySelector('.text-layout-box:not([hidden]) [data-direction=move]').getBoundingClientRect();return {left:r.left,scale:VE.state.win.GNCResponsiveCanvas.scale(),x:fr.left+b.left+b.width/2,y:fr.top+b.top+b.height/2};})()`);
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:pointerStart.x,y:pointerStart.y});
    await send('Input.dispatchMouseEvent',{type:'mousePressed',x:pointerStart.x,y:pointerStart.y,button:'left',clickCount:1});
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:pointerStart.x+20,y:pointerStart.y,buttons:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:pointerStart.x+20,y:pointerStart.y,button:'left',clickCount:1});await delay(200);
    const pointerEnd=await evaluate("VE.state.doc.querySelector('[data-dept-header-subject]').getBoundingClientRect().left");
    assert.ok(Math.abs(pointerEnd-pointerStart.left-20)<2,'Real pointer drag follows screen coordinates once at scaled desktop size');
    await evaluate('dragController.dispose();VE.state.win.GNCTextLayout.setChanges({});');
    fs.mkdirSync(path.join(__dirname,'../cache/responsive-canvas'),{recursive:true});
    for(const [device,width] of [['desktop',1032],['tablet',820],['mobile',390]]){
        await send('Emulation.setDeviceMetricsOverride',{width:Math.max(1032,width),height:1000,deviceScaleFactor:1,mobile:false});
        await evaluate(`document.querySelector('.ve-devices [data-device=${device}]').click()`);await delay(400);
        const shot=await send('Page.captureScreenshot',{format:'png'});
        fs.writeFileSync(path.join(__dirname,`../cache/responsive-canvas/editor-${device}.png`),Buffer.from(shot.data,'base64'));
    }
    console.log('PASS: visitor pages and standalone previews remain unscaled; editor-only device previews retain geometry across monitor sizes; font edits and real pointer dragging use correct coordinates.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(socket)socket.close();browser.kill();});

