// Uses the local Docker site and a mocked import preview; never confirms an import.
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const sessionTool=args=>{
    const result=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});
    if(result.status!==0)throw Error(result.stderr||result.stdout);
    return result.stdout;
};
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-import-modal-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(fn,message){for(let end=Date.now()+20000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket,session;
(async()=>{
    session=JSON.parse(sessionTool(['Super Admin']));
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs=await(await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve=>socket.addEventListener('open',resolve,{once:true}));
    let serial=0;const pending=new Map();
    socket.addEventListener('message',event=>{const msg=JSON.parse(event.data);if(pending.has(msg.id)){pending.get(msg.id)(msg);pending.delete(msg.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,msg=>msg.error?reject(Error(JSON.stringify(msg.error))):resolve(msg.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    await send('Page.enable');await send('Network.enable');await send('Runtime.enable');
    socket.addEventListener('message',event=>{const msg=JSON.parse(event.data);if(msg.method==='Runtime.exceptionThrown')console.error(JSON.stringify(msg.params.exceptionDetails));});
    await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});

    for(const [route,root] of [['announcement.php','.announcement-management'],['news.php','.news-management'],['facebook-posts.php','.facebook-management']]){
        await evaluate('window.__previousPinPage=true');
        await send('Page.navigate',{url:'http://localhost:8080/admin/'+route});
        await until(()=>evaluate(`!window.__previousPinPage&&document.readyState==='complete'&&Boolean(document.querySelector(${JSON.stringify(root)}))`).catch(()=>false),'Page failed: '+route);
        await evaluate("if(document.querySelector('.gnc-tour[open]'))Array.from(document.querySelectorAll('.gnc-tour button')).find(b=>b.textContent==='Skip')?.click()");
        if(route==='facebook-posts.php'){
            assert.equal(await evaluate("document.querySelectorAll('.fb-table th').length"),4);
            assert.ok(await evaluate("document.querySelector('.fb-table [data-pin]')!==null"));
            await evaluate(`window.__pinRow=document.querySelector('.fb-table [data-post-id]');window.__pinButton=__pinRow.querySelector('[data-pin]');
                window.fetch=(url,options)=>{
                    if(!String(url).includes('facebook-posts-handler.php'))throw Error('Unexpected test request');
                    const values=Object.fromEntries(options.body);
                    return Promise.resolve(Response.json({success:true,message:'UI preview only',pinned:values.action==='pin'?[{source:'facebook',item_id:values.item_id,title:'UI preview only'}]:[]}));
                };
                if(__pinRow.classList.contains('is-pinned'))__pinButton.click();
            `);
            await until(()=>evaluate("document.querySelector('.facebook-management').getAttribute('aria-busy')!=='true'"),'Unpin preview failed');
            await evaluate('__pinButton.disabled=false;__pinButton.click()');
            await until(()=>evaluate("!__pinRow.querySelector('.fb-pin-status').hidden&&__pinButton.getAttribute('aria-pressed')==='true'"),'Pin indicator did not update');
            assert.equal(await evaluate("__pinRow.querySelector('.fb-pin-status').textContent.trim()"),'On homepage slideshow');
            assert.equal(await evaluate("__pinRow.querySelector('.fb-pin-status').closest('td')===__pinRow.querySelector('td')"),true);
            assert.ok(await evaluate("__pinButton.querySelector('.bi-pin-angle-fill')!==null"));
        }else{
            // A visual fixture only when the current page has no pinned record.
            await evaluate(`(()=>{if(!document.querySelector(${JSON.stringify(root)}+' .ann-pinned')){const cell=document.querySelector(${JSON.stringify(root)}+' .ann-title-cell');if(!cell)throw Error('No content row for badge preview');const badge=document.createElement('span');badge.className='ann-pinned'+(${JSON.stringify(route)}==='news.php'?' content-homepage-pin':'');badge.innerHTML='<i class="bi bi-pin-angle-fill" aria-hidden="true"></i> On homepage slideshow';cell.append(badge);}})()`);
        }
        const selector=route==='facebook-posts.php'?'.fb-pin-status.pinned':root+' .ann-pinned';
        const colors=await evaluate(`(()=>{const s=getComputedStyle(document.querySelector(${JSON.stringify(selector)}));return [s.color,s.backgroundColor,s.borderRadius];})()`);
        assert.deepEqual(colors,['rgb(120, 87, 0)','rgb(255, 243, 212)','5px']);
        for(const width of [1440,390]){
            await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            assert.ok(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Page overflow: '+route);
            await evaluate(`document.querySelector(${JSON.stringify(selector)}).scrollIntoView({block:'center',behavior:'instant'})`);
            await delay(100);
            fs.writeFileSync(path.join(profile,route+'-'+width+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));
        }
        if(route==='facebook-posts.php'){
            await evaluate('__pinButton.click()');
            await until(()=>evaluate("__pinRow.querySelector('.fb-pin-status').hidden&&__pinButton.getAttribute('aria-pressed')==='false'"),'Unpin did not hide the indicator');
        }
    }
    console.log('PASS: Announcement/News/Facebook badge consistency, desktop/mobile layout, mocked Facebook pin/unpin updates and active pin icon. No posts pinned or unpinned on the server. Screenshots: '+profile);
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(session)sessionTool(['destroy',session.value]);socket?.close();browser.kill();});
