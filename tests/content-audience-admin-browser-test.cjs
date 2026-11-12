const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
const sessionTool = args => {
    const result = spawnSync('docker', ['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args], {encoding:'utf8',windowsHide:true});
    if (result.status !== 0) throw Error(result.stderr || result.stdout);
    return result.stdout;
};
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-content-audience-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(fn, message) { for (let end=Date.now()+20000;Date.now()<end;) { if(await fn())return;await delay(100); } throw Error(message); }
let socket, session;
(async () => {
    session = JSON.parse(sessionTool(['Super Admin']));
    await until(() => fs.existsSync(path.join(profile,'DevToolsActivePort')), 'Chrome unavailable');
    const port = fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0];
    const tabs = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
    socket = new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open',resolve,{once:true}));
    let serial=0; const pending=new Map();
    socket.addEventListener('message',event=>{const data=JSON.parse(event.data);if(pending.has(data.id)){pending.get(data.id)(data);pending.delete(data.id);}});
    const send = (method,params={}) => new Promise((resolve,reject)=>{const id=++serial;pending.set(id,data=>data.error?reject(Error(JSON.stringify(data.error))):resolve(data.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate = async expression => { const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(result.exceptionDetails)throw Error(JSON.stringify(result.exceptionDetails));return result.result.value; };
    await send('Page.enable');await send('Network.enable');
    await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});
    for (const kind of ['news','announcement']) {
        await evaluate('window.__oldAudiencePage=true');
        await send('Page.navigate',{url:'http://localhost:8080/admin/'+kind+'.php?modal=create'});
        await until(()=>evaluate("!window.__oldAudiencePage && document.readyState==='complete' && !!document.querySelector('.modal.show')").catch(()=>false),kind+' form unavailable');
        const prefix = kind === 'news' ? 'news' : 'modal';
        await evaluate(`window.audienceDepartments=document.getElementById('${prefix}-dept-multiselect')._gncMsInstance;window.audiencePrograms=document.getElementById('${prefix}-program-multiselect')._gncMsInstance;
            window.catalogue=audiencePrograms.config.items.slice();window.departmentIds=audienceDepartments.config.items.map(item=>String(item.value)).filter(id=>catalogue.filter(item=>String(item.departmentId)===id).length>=2).slice(0,2);`);
        assert.equal(await evaluate('departmentIds.length'),2,'Need two local departments with multiple programs');
        await evaluate('audienceDepartments.setSelected(departmentIds)');
        assert.equal(await evaluate('audiencePrograms.getSelected().length===catalogue.filter(item=>departmentIds.includes(String(item.departmentId))).length'),true,'New departments select their programs');
        await evaluate(`window.oneProgram=catalogue.find(item=>String(item.departmentId)===departmentIds[1]).value;audiencePrograms.setSelected([oneProgram]);`);
        const summary = kind === 'news' ? 'news-audience-help' : 'modal-audience-help';
        assert.match(await evaluate(`document.getElementById('${summary}').textContent`),/whole department/);
        assert.match(await evaluate(`document.getElementById('${summary}').textContent`),/Other departments are excluded/);
        const form = kind === 'news' ? 'news-form' : 'announcement-form';
        assert.deepEqual(await evaluate(`new FormData(document.getElementById('${form}')).getAll('programs[]')`),await evaluate('[oneProgram]'));
        // Exercise each page's real edit hydration without submitting any record.
        await evaluate(`openEditModal({dataset:{id:'0',title:'Browser audience fixture',status:'draft',depts:JSON.stringify(departmentIds),programs:JSON.stringify([oneProgram]),audience:JSON.stringify({dept_ids:departmentIds,programs:[oneProgram]}),category:'Achievements'}})`);
        assert.deepEqual(await evaluate('audiencePrograms.getSelected()'),await evaluate('[oneProgram]'));
        assert.deepEqual(await evaluate('audienceDepartments.getSelected()'),await evaluate('departmentIds'));
        for (const width of [1440,390]) {
            await send('Emulation.setDeviceMetricsOverride',{width,height:950,deviceScaleFactor:1,mobile:false});
            await evaluate('audiencePrograms.deselectAll()');
            assert.equal(await evaluate("audiencePrograms.tagsAreaEl.textContent.includes('Whole selected departments')"),true);
            assert.deepEqual(await evaluate('audienceDepartments.getSelected()'),await evaluate('departmentIds'));
            assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth'),true,'Page overflow');
            const overflow = await evaluate(`(()=>{const wrap=document.getElementById('${prefix}-program-multiselect');return wrap.scrollWidth>wrap.clientWidth+1})()`);
            assert.equal(overflow,false,'Audience control overflow');
        }
        await evaluate('resetModalForm()');
        assert.deepEqual(await evaluate('audienceDepartments.getSelected()'),[]);
        assert.deepEqual(await evaluate('audiencePrograms.getSelected()'),[]);
        console.log('PASS: '+kind+' actual form defaults, independent departments, submitted fields, edit hydration, clear/reset and desktop/mobile layout. No content submitted.');
    }
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(session)sessionTool(['destroy',session.value]);socket?.close();browser.kill();});
