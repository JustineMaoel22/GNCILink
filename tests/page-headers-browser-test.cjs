const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-headers-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    ['--headless=new','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],
    {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(r=>setTimeout(r,ms));
let socket;
async function until(fn,message) {
    for(let end=Date.now()+30000;Date.now()<end;) { if(await fn()) return; await delay(50); }
    throw Error(message);
}
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const p=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(p.exceptionDetails)throw Error(JSON.stringify(p.exceptionDetails));return p.result.value;};
    await send('Page.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    const routes=process.argv.length>2?process.argv.slice(2):['/casa-gradeschool','/departments/cased','/graduate-school','/basic-edu-dept','/college-dept',
        '/administration','/vision-mission','/core-values','/logo-meaning','/admission-req',
        '/basic-education-admission-req','/enrollment-procedures','/tuition-fees','/online-payment-guide',
        '/scholarship','/student-achievement','/student-org','/campus-event','/community-activity',
        '/athletics-sports','/news-and-announcement','/alumni','/contact-us','/faq','/privacy-policy',
        '/programs/beed','/ma-business-admin','/gnc-history','/the-devs','/'];
    const report=[];
    for(const width of [390,820,1031,1032,1440]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:false});
        for(const route of routes) {
            await evaluate('window.__oldHeaderPage=true');
            await send('Page.navigate',{url:'http://localhost:8080'+route});
            await until(()=>evaluate("!window.__oldHeaderPage && document.readyState==='complete' && !!document.querySelector('h1,.history-hero .hero-title')").catch(()=>false),'Page unavailable '+route);
            await evaluate('document.fonts.ready.then(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))))');
            const info=await evaluate(`(()=>{
                const headings=[...document.querySelectorAll('h1,.history-hero .hero-title')].filter(e=>e.getBoundingClientRect().width>0 && e.closest('[class*="hero"],.faq-heading,.gnc-legal-main header'));
                return {headings:headings.map(e=>{const r=e.getBoundingClientRect(),banner=e.closest('[class$="-hero"],[class*="-hero "],.hero-page,.hero-slideshow,.hero-section,.faq-heading,.gnc-legal-main header'),b=banner?.getBoundingClientRect();return {text:e.textContent.trim(),alignment:getComputedStyle(e).textAlign,centered:getComputedStyle(e).getPropertyValue('--gnc-center-page-header').trim(),position:getComputedStyle(e).position,offset:b?Math.round((r.left+r.width/2)-(b.left+b.width/2)):null};}),overflow:document.documentElement.scrollWidth>innerWidth+1};
            })()`);
            if(!info.headings.length && await evaluate("document.querySelector('h1')?.textContent.trim()==='Page not available'")) {
                console.log('Skipped unpublished page '+route+' at '+width+'px.');
                continue;
            }
            assert.ok(info.headings.length, 'Header found '+route);
            if(width<1032) {
                for(const heading of info.headings) assert.equal(heading.alignment,'center',route+' at '+width+' must center '+heading.text);
                for(const heading of info.headings) if(heading.offset!==null)assert.ok(Math.abs(heading.offset)<5,route+' at '+width+' heading is offset '+heading.offset+'px');
                assert.equal(info.overflow,false,'No horizontal overflow '+route+' at '+width);
            } else {
                assert.ok(info.headings.every(h=>h.centered!== '1'),'Desktop does not activate narrow header layout '+route);
            }
            if(route==='/departments/cased') {
                const saved=await evaluate('JSON.stringify(GNCTextLayout.snapshot())');
                const position=await evaluate("getComputedStyle(document.querySelector('.dept-hero-title')).position");
                assert.equal(position,width<1032?'static':'absolute','Saved department geometry only on desktop');
                assert.equal(await evaluate('JSON.stringify(GNCTextLayout.snapshot())'),saved,'No CMS settings mutated');
                if(width===1031) {
                    // Reproduce hosted-style positioned child headings without persisting data.
                    await evaluate(`(()=>{const changes={};for(const [id,el] of GNCTextLayout.targets()) {
                        if(el.matches('[data-dept-header-prefix]'))changes[id]={desktop:{value:{x:3.38,y:-.06,w:30.59,h:45,fontSize:36,textAlign:'left'}}};
                        if(el.matches('[data-dept-header-subject]'))changes[id]={desktop:{value:{x:2.96,y:-5.92,w:28.14,h:160,fontSize:64,textAlign:'left'}}};
                    }GNCTextLayout.setChanges(changes);})()`);
                    assert.equal(await evaluate("getComputedStyle(document.querySelector('.dept-hero-subject')).textAlign"),'center','Saved left alignment cannot undo tablet centering');
                    assert.equal(await evaluate("document.querySelector('.dept-hero-prefix').getBoundingClientRect().bottom <= document.querySelector('.dept-hero-subject').getBoundingClientRect().top"),true,'Prefix and subject do not overlap');
                }
            }
            if([390,1032].includes(width)&&['/casa-gradeschool','/administration','/departments/cased'].includes(route)) {
                await evaluate("document.querySelector('[data-privacy-action=reject]')?.click();document.querySelectorAll('dialog[open]').forEach(d=>d.close())");
                const shot=await send('Page.captureScreenshot',{format:'png'});
                fs.mkdirSync('cache/page-headers',{recursive:true});
                fs.writeFileSync(`cache/page-headers/${route.split('/').pop()}-${width}.png`,Buffer.from(shot.data,'base64'));
            }
            report.push({route,width,...info});
        }
        console.log('Passed '+report.filter(item=>item.width===width).length+' public header checks at '+width+'px.');
    }
    fs.mkdirSync('cache/page-headers',{recursive:true});
    fs.writeFileSync('cache/page-headers/results.json',JSON.stringify(report,null,2));
    console.log('All public header checks passed; saved department placement and fonts remain stored.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();browser.kill();});
