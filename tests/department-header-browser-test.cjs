// Runs against the local PHP site in an installed headless Chrome; no npm dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawn } = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-office-map-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(work, message) {
    const deadline = Date.now() + 30000;
    while (Date.now() < deadline) { const value = await work(); if (value) return value; await delay(100); }
    throw new Error(message);
}
let socket;
(async () => {
    await until(() => fs.existsSync(path.join(profile, 'DevToolsActivePort')), 'Chrome did not start');
    const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
    const target = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(target.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let serial = 0; const requests = new Map();
    socket.addEventListener('message', event => {
        const packet = JSON.parse(event.data);
        if (packet.id && requests.has(packet.id)) { requests.get(packet.id)(packet); requests.delete(packet.id); }
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const id = ++serial;
        requests.set(id, result => result.error ? reject(Error(JSON.stringify(result.error))) : resolve(result.result));
        socket.send(JSON.stringify({ id, method, params }));
    });
    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };
    await send('Network.enable');
    await send('Network.setBlockedURLs', { urls: ['*fonts.googleapis.com*', '*fonts.gstatic.com*', '*cloudflareinsights.com*', '*cdn.jsdelivr.net*', '*facebook.com*', '*cdnjs.cloudflare.com*', '*unpkg.com*', '*youtube.com*', '*google.com*'] });
    await send('Page.navigate', { url: (process.env.BASE_URL || 'http://localhost:8080') + '/pages/academics/college/college-department.php?slug=cased' });
    try {
        await until(() => evaluate("Boolean(document.querySelector('[data-office-location]') && document.getElementById('officeMapModal') && document.readyState !== 'loading')"), 'CASED office card unavailable');
    } catch (error) {
        console.error(await evaluate("JSON.stringify({url:location.href,ready:document.readyState,title:document.title,body:document.body?.innerText.slice(0,1000)})"));
        throw error;
    }

    fs.mkdirSync('cache/department-header', {recursive:true});
    await send('Emulation.setEmulatedMedia', {features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await until(()=>evaluate('Boolean(window.GNCTextLayout)'), 'Text layout controls unavailable');
    const wrapping=await evaluate(`(() => {
        const title=document.querySelector('.dept-hero-title');
        const originalMarker=title.getAttribute('data-gnc-text');
        if(!originalMarker)title.setAttribute('data-gnc-text','abcdef0123456789abcdef01');
        const id=Array.from(GNCTextLayout.targets()).find(([,el])=>el===title)[0];
        const resize=w=>{GNCTextLayout.setChanges({[id]:{desktop:{value:{x:0,y:0,w,h:100}}}});const subject=title.querySelector('.dept-hero-subject');return {title:title.getBoundingClientRect().width,width:subject.getBoundingClientRect().width,height:subject.getBoundingClientRect().height};};
        const narrow=resize(24),wide=resize(70);
        GNCTextLayout.setChanges({});
        if(!originalMarker)title.removeAttribute('data-gnc-text');
        return {narrow,wide};
    })()`);
    assert.ok(wrapping.wide.width>wrapping.narrow.width+200,'Widening the text box widens the inner department name');
    assert.ok(Math.abs(wrapping.wide.width-wrapping.wide.title)<1,'Department name uses the full resized text box');
    assert.ok(wrapping.wide.height<wrapping.narrow.height,'Widening the text box reduces wrapped lines');
    const hasLogo=await evaluate("Boolean(document.querySelector('.dept-hero-logo'))");
    if(hasLogo){
    const logoBefore=await evaluate(`(() => {
        window.logoChanges={};
        const logo=document.querySelector('.dept-hero-logo');
        window.logoControl=GNCTextLayout.create({state:()=>({busy:false,preview:false}),stage:(id,device,value)=>{logoChanges[id]={[device]:{value}};GNCTextLayout.setChanges(logoChanges);}});
        if(!logoControl.eligible(logo))throw Error('Header logo must have a stable layout marker');
        logoControl.select(logo);logoControl.toggle();logoControl.toggleSnap();
        const r=logo.getBoundingClientRect(),t=document.querySelector('.dept-hero-title').getBoundingClientRect();
        return {x:r.x,y:r.y,w:r.width,h:r.height,titleX:t.x,titleY:t.y};
    })()`);
    async function logoGesture(selector,dx,dy){
        const p=await evaluate(`(() => {const r=document.querySelector(${JSON.stringify(selector)}).getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2};})()`);
        await send('Input.dispatchMouseEvent',{type:'mousePressed',...p,button:'left',clickCount:1});
        await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:p.x+dx,y:p.y+dy,button:'left',buttons:1});
        await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:p.x+dx,y:p.y+dy,button:'left',clickCount:1});
    }
    await logoGesture('.text-layout-handle[data-direction="e"]',80,0);
    const logoResized=await evaluate(`(() => {const r=document.querySelector('.dept-hero-logo').getBoundingClientRect(),t=document.querySelector('.dept-hero-title').getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,titleX:t.x,titleY:t.y};})()`);
    assert.ok(logoResized.w>logoBefore.w+60,'Stretching the logo makes it visibly larger');
    assert.ok(Math.abs(logoResized.w/logoResized.h-logoBefore.w/logoBefore.h)<.01,'Logo stays proportional');
    assert.ok(Math.abs(logoResized.titleX-logoBefore.titleX)<1&&Math.abs(logoResized.titleY-logoBefore.titleY)<1,'Logo resizing preserves title placement');
    await logoGesture('.text-layout-move',50,35);
    const logoMoved=await evaluate(`(() => {const r=document.querySelector('.dept-hero-logo').getBoundingClientRect();return {x:r.x,y:r.y};})()`);
    assert.ok(logoMoved.x>logoResized.x+40,'Dragging moves the logo horizontally');
    // A tall logo may already fill the header height, so downward movement is clamped.
    assert.ok(logoMoved.y>=logoResized.y-1&&logoMoved.y<=logoResized.y+36,'Logo movement respects the header boundary');
    await logoGesture('.text-layout-handle[data-direction="e"]',-100,0);
    const logoShrunk=await evaluate("document.querySelector('.dept-hero-logo').getBoundingClientRect().width");
    assert.ok(logoShrunk<logoResized.w-80,'Logo can shrink as well as grow');
    await evaluate('logoControl.reset();logoControl.dispose();GNCTextLayout.setChanges({});');
    } else console.log('No published header logo; skipping optional logo gesture checks.');
    const fontSizing=await evaluate(`(() => {
        const title=document.querySelector('.dept-hero-title');
        const changes={};
        const control=GNCTextLayout.create({state:()=>({busy:false,preview:false}),stage:(id,device,value)=>{changes[id]={[device]:{value}};GNCTextLayout.setChanges(changes);}});
        control.select(title);
        control.changeFontSize(null);const defaultSize=control.fontSize();
        GNCTextLayout.setChanges({});
        const before=control.fontSize();
        control.changeFontSize(2);const bigger=control.fontSize();
        control.changeFontSize(-4);const smaller=control.fontSize();
        const saved=GNCTextLayout.snapshot();
        control.changeFontSize(null);const reset=control.fontSize();
        control.reset();control.dispose();GNCTextLayout.setChanges({});
        return {before,bigger,smaller,reset,saved,defaultSize};
    })()`);
    assert.equal(fontSizing.bigger,fontSizing.before+2,'Bigger text increases the rendered font size');
    assert.equal(fontSizing.smaller,fontSizing.before-2,'Smaller text reduces the rendered font size');
    assert.equal(fontSizing.reset,fontSizing.defaultSize,'Default text size restores the stylesheet font');
    assert.ok(Object.values(fontSizing.saved).some(devices=>devices.desktop?.fontSize===fontSizing.smaller),'Text size is included in placement metadata');
    for (const [width,height] of [[1440,900],[768,1024],[390,844]]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
        await delay(250);
        const state=await evaluate(`(() => {
            const hero=document.querySelector('.dept-intro-program .dept-hero');
            const layer=hero.querySelector('[data-dept-banner-image]');
            const title=hero.querySelector('h1'),logo=hero.querySelector('.dept-hero-logo');
            const h=hero.getBoundingClientRect(),b=layer.getBoundingClientRect(),t=title.getBoundingClientRect();
            const prefix=hero.querySelector('.dept-hero-prefix');
            const color=getComputedStyle(title).color;
            const before=getComputedStyle(document.querySelector('.dept-about h2')).color;
            hero.closest('.dept-intro-program').style.setProperty('--gnc-template-text','rgb(110, 30, 80)');
            const themed=getComputedStyle(title).color,after=getComputedStyle(document.querySelector('.dept-about h2')).color;
            hero.closest('.dept-intro-program').style.removeProperty('--gnc-template-text');
            return {heroHeight:h.height,layerHeight:b.height,titleInside:t.left>=h.left&&t.right<=h.right&&t.bottom<=h.bottom,logo:!!logo,headingCount:hero.querySelectorAll('h1').length,backgroundFit:getComputedStyle(layer.querySelector('img')).objectFit,overflow:document.documentElement.scrollWidth>innerWidth,prefix:prefix?.textContent.trim(),prefixSmaller:prefix&&parseFloat(getComputedStyle(prefix).fontSize)<parseFloat(getComputedStyle(title).fontSize),scopedTheme:themed==='rgb(110, 30, 80)'&&before===after};
        })()`);
        assert.ok(Math.abs(state.layerHeight-state.heroHeight)<=1,'Background fills the hero inside its border at '+width);
        assert.equal(state.headingCount,1);
        assert.equal(state.titleInside,true,'Title fits inside hero at '+width);
        assert.equal(state.logo,hasLogo);
        assert.equal(state.backgroundFit,'cover');
        assert.equal(state.prefix,'College of');
        assert.equal(state.prefixSmaller,true);
        assert.equal(state.scopedTheme,true,'Header color changes without recoloring other sections');
        assert.equal(state.overflow,false,'No horizontal overflow at '+width);
        const screenshot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync('cache/department-header/'+width+'.png',Buffer.from(screenshot.data,'base64'));
    }
    console.log('PASS: department hero layers background, logo and live heading without overflow at 1440, 768 and 390px.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});
