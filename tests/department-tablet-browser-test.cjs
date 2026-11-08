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
    const inspect=`(()=>{const title=document.querySelector('.dept-hero-title'),content=document.querySelector('.dept-hero-content'),copy=document.querySelector('.dept-hero-copy');return {alignment:getComputedStyle(title).textAlign,direction:getComputedStyle(content).flexDirection,copyAlignment:getComputedStyle(copy).textAlign,contentWidth:Math.round(content.getBoundingClientRect().width),fontSize:getComputedStyle(title).fontSize,viewport:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth};})()`;
    const publicMetrics={};
    for(const width of [390,767,768,820,1024,1032,1199,1200,1366,1440,1536,1920]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1024,deviceScaleFactor:1,mobile:width<1100});
        await evaluate("document.querySelectorAll('.dept-hero-title').forEach(el=>el.remove())");
        await send('Page.navigate',{url:base+'/departments/cased'});
        await until(()=>evaluate("document.readyState==='complete' && Boolean(document.querySelector('.dept-hero-title'))"),'Department page unavailable');
        await delay(200);
        publicMetrics[width]=await evaluate(inspect);
        if([768,820].includes(width)){
            await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close());document.querySelector('[data-privacy-action=reject]')?.click()");
            fs.mkdirSync(path.join(__dirname,'../cache/department-tablet'),{recursive:true});
            const shot=await send('Page.captureScreenshot',{format:'png'});
            fs.writeFileSync(path.join(__dirname,`../cache/department-tablet/public-${width}.png`),Buffer.from(shot.data,'base64'));
        }
        assert.equal(publicMetrics[width].overflow,false,'No horizontal overflow at '+width);
        if(width<=1024)assert.equal(publicMetrics[width].alignment,'center','Phone and tablet headers must be centered');
        else assert.notEqual(publicMetrics[width].alignment,'center','Desktop retains its existing alignment');
    }
    // Keep an intentionally positioned desktop heading proportional on iPad Pro.
    // The changes exist only in this browser; nothing is saved to the CMS.
    const stressHeading=`(()=>{const changes={};for(const [id,el] of GNCTextLayout.targets()){
        if(el.matches('[data-gnc-responsive-heading]')) changes[id]={desktop:{value:{x:7,y:12,w:38,h:170,fontSize:80,textAlign:'center'}}};
        else if(el.matches('[data-dept-header-prefix]')) changes[id]={desktop:{value:{fontSize:38}}};
        else if(el.matches('[data-dept-header-subject]')) changes[id]={desktop:{value:{fontSize:80}}};
    }GNCTextLayout.setChanges(changes);window.headingSettings=JSON.stringify(GNCTextLayout.snapshot());})()`;
    await send('Emulation.setDeviceMetricsOverride',{width:1920,height:1100,deviceScaleFactor:1,mobile:false});await delay(300);
    await evaluate(stressHeading);
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.dept-hero-title')).position"),'absolute','Large desktops retain saved positions');
    const headingLines=`(()=>{const el=document.querySelector('[data-dept-header-subject]'),walker=document.createTreeWalker(el,NodeFilter.SHOW_TEXT),lines=[];let node;while(node=walker.nextNode()){for(const match of node.textContent.matchAll(/\\S+/g)){const range=document.createRange();range.setStart(node,match.index);range.setEnd(node,match.index+match[0].length);const top=range.getBoundingClientRect().top;let line=lines.find(line=>Math.abs(line.top-top)<2);if(!line){line={top,words:[]};lines.push(line);}line.words.push(match[0]);}}return lines.map(line=>line.words.join(' '));})()`;
    await evaluate('document.fonts.ready');
    const referenceLines=await evaluate(headingLines);
    // Exercise the actual saved title as well: its first line nearly fills
    // its box, so including the scrollbar in font scaling adds a third line.
    await evaluate('GNCTextLayout.setChanges({})');
    const savedLines=await evaluate(headingLines);
    for(const width of [1032,1366,1536]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1376,deviceScaleFactor:1,mobile:false});await delay(300);
        assert.deepEqual(await evaluate(headingLines),savedLines,'Saved department title keeps desktop line breaks at '+width);
        const separated=await evaluate(`(()=>{const prefix=document.querySelector('[data-dept-header-prefix]').getBoundingClientRect(),subject=document.querySelector('[data-dept-header-subject]').getBoundingClientRect();return prefix.bottom<=subject.top;})()`);
        assert.equal(separated,true,'College prefix stays above the title at '+width);
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1920,height:1100,deviceScaleFactor:1,mobile:false});await delay(300);
    await evaluate(stressHeading);
    const original=await evaluate(`(()=>{const t=document.querySelector('.dept-hero-title').getBoundingClientRect(),hero=document.querySelector('.dept-hero').getBoundingClientRect();return {x:(t.x-hero.x)/hero.width,y:(t.y-hero.y)/hero.height,w:t.width/hero.width,contentWidth:document.documentElement.clientWidth,font:parseFloat(getComputedStyle(document.querySelector('.dept-hero-title')).fontSize)};})()`);
    for(const width of [1032,1199,1200,1366,1440,1536,1680,1919]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1376,deviceScaleFactor:1,mobile:true});await delay(300);
        assert.equal(await evaluate('GNCTextLayout.device()'),'desktop','iPad Pro still uses desktop settings');
        assert.equal(await evaluate("getComputedStyle(document.querySelector('.dept-hero-title')).position"),'absolute','iPad Pro preserves saved heading placement');
        const scaled=await evaluate(`(()=>{const t=document.querySelector('.dept-hero-title').getBoundingClientRect(),hero=document.querySelector('.dept-hero').getBoundingClientRect();return {x:(t.x-hero.x)/hero.width,y:(t.y-hero.y)/hero.height,w:t.width/hero.width,font:parseFloat(getComputedStyle(document.querySelector('.dept-hero-title')).fontSize)};})()`);
        assert.ok(Math.abs(scaled.x-original.x)<.015,'Saved horizontal position stays proportional');
        assert.ok(Math.abs(scaled.w-original.w)<.01,'Saved heading width stays proportional');
        const contentWidth=await evaluate('document.documentElement.clientWidth');
        assert.ok(Math.abs(scaled.font-original.font*contentWidth/original.contentWidth)<.1,'Font scales with the content width, excluding the scrollbar');
        assert.equal(await evaluate("getComputedStyle(document.querySelector('.dept-hero-title')).textAlign"),'center','Saved text alignment is preserved');
        assert.deepEqual(await evaluate(headingLines),referenceLines,'iPad Pro preserves desktop heading line breaks');
        const bounds=await evaluate(`(()=>{const title=document.querySelector('.dept-hero-title').getBoundingClientRect(),hero=document.querySelector('.dept-hero').getBoundingClientRect(),breadcrumb=document.querySelector('.dept-breadcrumb').getBoundingClientRect();return {fits:title.bottom<=hero.bottom && title.left>=hero.left && title.right<=hero.right,belowBreadcrumb:title.top>=breadcrumb.bottom};})()`);
        assert.equal(bounds.fits,true,'Oversized title stays within the banner at '+width);
        assert.equal(bounds.belowBreadcrumb,true,'Heading does not overlap the breadcrumb');
        assert.equal(await evaluate('JSON.stringify(GNCTextLayout.snapshot())===headingSettings'),true,'Saved settings are preserved');
        if([1032,1536].includes(width)){
            await evaluate("document.querySelectorAll('dialog[open]').forEach(d=>d.close());document.querySelector('[data-privacy-action=reject]')?.click()");
            const shot=await send('Page.captureScreenshot',{format:'png'});
            fs.writeFileSync(path.join(__dirname,`../cache/department-tablet/desktop-scaled-${width}.png`),Buffer.from(shot.data,'base64'));
        }
    }
    await send('Emulation.setDeviceMetricsOverride',{width:1920,height:1100,deviceScaleFactor:1,mobile:false});await delay(300);
    assert.equal(await evaluate("getComputedStyle(document.querySelector('.dept-hero-title')).position"),'absolute','Returning to desktop restores saved placement');
    assert.ok(await evaluate("Math.abs(parseFloat(getComputedStyle(document.querySelector('.dept-hero-title')).fontSize)-80*document.documentElement.clientWidth/1920)<.01"));
    await send('Emulation.setDeviceMetricsOverride',{width:1536,height:1100,deviceScaleFactor:1,mobile:false});await delay(300);
    const editMetrics=await evaluate(`(()=>{
        const subject=document.querySelector('[data-dept-header-subject]');
        const before={font:parseFloat(getComputedStyle(subject).fontSize),height:subject.getBoundingClientRect().height};
        const controller=GNCTextLayout.create({state:()=>({busy:false,preview:false}),stage:(id,device,value)=>{
            window.testStage=value;const snapshot=GNCTextLayout.snapshot();snapshot[id]=snapshot[id]||{};snapshot[id][device]=value;
            const changes={};for(const [key,devices] of Object.entries(snapshot)){changes[key]={};for(const [name,box] of Object.entries(devices))changes[key][name]={value:box};}GNCTextLayout.setChanges(changes);
        }});
        controller.select(subject);controller.changeFontSize(4);
        const result={before,storedFont:testStage.fontSize,storedHeight:testStage.h,after:parseFloat(getComputedStyle(subject).fontSize)};
        controller.dispose();return result;
    })()`);
    assert.ok(Math.abs(editMetrics.after-editMetrics.before.font-4)<.1,'Font adjustment applies once at the rendered size');
    const editScale=await evaluate('document.documentElement.clientWidth/1920');
    assert.ok(Math.abs(editMetrics.storedFont-editMetrics.after/editScale)<.1,'Stored font stays in desktop-reference units');
    assert.ok(Math.abs(editMetrics.storedHeight-editMetrics.before.height/editScale)<.1,'Stored height is not double-scaled');
    auth=JSON.parse(session(['Super Admin']));
    await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
    await send('Page.navigate',{url:base+'/admin/content-edit.php?page=college-departments&sub=cased'});
    await until(()=>evaluate("document.getElementById('veFrame')?.contentDocument?.querySelector('.dept-hero-title') && !document.getElementById('veLoader')?.hidden===false"),'Department editor unavailable');
    await evaluate("document.querySelector('.ve-devices [data-device=tablet]').click()");await delay(700);
    for(const width of [768,820]){
        await evaluate(`document.getElementById('veFrame').style.width='${width}px';document.getElementById('veFrame').style.minWidth='${width}px'`);await delay(300);
        const metrics=await evaluate(`document.getElementById('veFrame').contentWindow.eval(${JSON.stringify(inspect)})`);
        for(const key of ['alignment','direction','copyAlignment'])assert.equal(metrics[key],publicMetrics[width][key],key+' public/editor agreement at '+width);
    }
    await evaluate("document.querySelector('.ve-devices [data-device=desktop]').click()");await delay(300);
    await evaluate("document.getElementById('veFrame').style.width='1032px';document.getElementById('veFrame').style.minWidth='1032px'");await delay(300);
    await evaluate(`document.getElementById('veFrame').contentWindow.eval(${JSON.stringify(stressHeading)})`);
    assert.equal(await evaluate("getComputedStyle(document.getElementById('veFrame').contentDocument.querySelector('.dept-hero-title')).position"),'absolute','Actual editor preview preserves the same proportional heading placement');
    console.log('PASS: desktop composition and line breaks stay consistent from 1032px through 1919px; full desktop, centered tablet, editor preview and scaled editing values remain correct.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(socket)socket.close();browser.kill();});
