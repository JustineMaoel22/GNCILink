const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-audience-'));
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

    await evaluate(`document.body.innerHTML='<form><div id="depts"></div><div id="programs"></div></form>'`);
    await evaluate(fs.readFileSync(path.join(__dirname,'../assets/js/multi-audience-select.js'),'utf8'));
    await evaluate(`document.head.innerHTML='<meta name="viewport" content="width=device-width,initial-scale=1">';
        const style=document.createElement('style');style.textContent=${JSON.stringify(fs.readFileSync(path.join(__dirname,'../assets/css/multi-audience-select.css'),'utf8'))};document.head.append(style);`);
    await evaluate(`window.changes=0;
        window.depts=new GncMultiSelect(document.getElementById('depts'),{name:'dept_ids[]',items:[{value:'1',label:'One'},{value:'2',label:'Two'},{value:'3',label:'Empty'}],onChange:()=>changes++});
        window.programs=new GncMultiSelect(document.getElementById('programs'),{name:'programs[]',items:[{value:'A',label:'Alpha',badge:'A',departmentId:'1'},{value:'B',label:'Beta',departmentId:'2'},{value:'C',label:'Another',departmentId:'1'}]});
        programs.bindDepartments(depts);
        window.options=()=>Array.from(document.querySelectorAll('#programs .gnc-ms-checkbox'),cb=>cb.value);`);
    assert.deepEqual(await evaluate('options()'),['A','B','C']);
    assert.equal(await evaluate("getComputedStyle(programs.allCheckbox.closest('label')).display"),'flex');
    await evaluate("programs.setSelected(['A'])");
    assert.deepEqual(await evaluate('depts.getSelected()'),['1'],'Choosing one program selects its owning department');
    assert.deepEqual(await evaluate('programs.getSelected()'),['A'],'Automatic department selection does not add sibling programs');
    assert.deepEqual(await evaluate("new FormData(document.querySelector('form')).getAll('dept_ids[]')"),['1'],'Owning department is submitted');
    await evaluate("depts.deselectAll();programs.setSelected(['A','B'])");
    assert.deepEqual(await evaluate('depts.getSelected()'),['1','2'],'Program-only edit values select every owning department');
    assert.deepEqual(await evaluate('programs.getSelected()'),['A','B']);
    await evaluate('depts.deselectAll()');
    await evaluate("depts.setSelected(['1'])");
    assert.deepEqual(await evaluate('options()'),['A','C']);
    assert.equal(await evaluate("getComputedStyle(programs.allCheckbox.closest('label')).display"),'none');
    assert.equal(await evaluate("getComputedStyle(programs.listEl.querySelector('.gnc-ms-divider')).display"),'none');
    await evaluate("programs.filterOptions('alpha');programs.filterOptions('')");
    assert.equal(await evaluate("getComputedStyle(programs.allCheckbox.closest('label')).display"),'none','Clearing search must not restore the unrestricted option');
    assert.deepEqual(await evaluate('programs.getSelected()'),['A','C']);
    assert.deepEqual(await evaluate("new FormData(document.querySelector('form')).getAll('programs[]')"),['A','C']);
    await evaluate("programs.setSelected(['B','C'])");
    assert.deepEqual(await evaluate('programs.getSelected()'),['C']);
    await evaluate("depts.setSelected(['1','2'])");
    assert.deepEqual(await evaluate('programs.getSelected()'),['C','B'],'New departments select their programs while preserving manual choices in existing departments');
    await evaluate("depts.setSelected(['1','2']);programs.setSelected(['A','B']);depts.setSelected(['2'])");
    assert.deepEqual(await evaluate('options()'),['B']);
    assert.deepEqual(await evaluate('programs.getSelected()'),['B']);
    await evaluate("depts.setSelected(['3'])");
    assert.deepEqual(await evaluate('options()'),[]);
    assert.deepEqual(await evaluate('programs.getSelected()'),[]);
    await evaluate("depts.deselectAll()");
    assert.equal(await evaluate("getComputedStyle(programs.allCheckbox.closest('label')).display"),'flex');
    assert.deepEqual(await evaluate('options()'),['A','B','C']);
    assert.deepEqual(await evaluate('programs.getSelected()'),[]);
    await evaluate("depts.setSelected(['1']);programs.selectAllBtn.click()");
    assert.deepEqual(await evaluate('programs.getSelected()'),['A','C']);
    await evaluate('programs.deselectAllBtn.click()');
    assert.deepEqual(await evaluate('programs.getSelected()'),[]);
    assert.equal(await evaluate("programs.tagsAreaEl.textContent.includes('Whole selected departments')"),true,'Empty programs must describe department-level targeting');
    await evaluate("depts.setSelected(['1','2']);programs.setSelected(['B']);depts.setSelected(['1','2','3'])");
    assert.deepEqual(await evaluate('programs.getSelected()'),['B'],'Adding another department must preserve a whole-department selection in the first');
    await evaluate("depts.setSelected(['1','2']);programs.setSelected(['B'])");
    assert.deepEqual(await evaluate("new FormData(document.querySelector('form')).getAll('dept_ids[]')"),['1','2']);
    assert.deepEqual(await evaluate("new FormData(document.querySelector('form')).getAll('programs[]')"),['B'],'A cleared department must not erase another department program selection');
    await evaluate("programs.searchInput.value='another';programs.filterOptions('another');depts.setSelected(['2']);programs.deselectAll();programs.selectAllBtn.click()");
    assert.deepEqual(await evaluate('programs.getSelected()'),[]);
    assert.equal(await evaluate('changes > 0'),true);
    await evaluate(fs.readFileSync(path.join(__dirname,'../assets/js/admin-form-audience.js'),'utf8'));
    await evaluate(`const form=document.querySelector('form');form.id='eventModal';
        for(const id of ['depts','programs']){const label=document.createElement('label');label.id=id+'-label';label.textContent=id;document.getElementById(id).before(label);}
        const formStyle=document.createElement('style');formStyle.textContent=${JSON.stringify(fs.readFileSync(path.join(__dirname,'../assets/css/event-form.css'),'utf8'))};document.head.append(formStyle);
        GncAdminFormAudience.enhance([['depts','depts-label','Search departments'],['programs','programs-label','Search programs']]);`);
    const click = async (selector, touch, padding = false) => {
        const point=await evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(selector)});el.scrollIntoView({block:'nearest'});const r=el.getBoundingClientRect();return {x:${padding ? 'r.right-3' : 'r.x+r.width/2'},y:r.y+r.height/2};})()`);
        if(touch){
            await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[point]});
            await delay(150);
            await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
        }else{
            await send('Input.dispatchMouseEvent',{type:'mousePressed',...point,button:'left',clickCount:1});
            await delay(150); // A real press lasts long enough for deferred focusout handlers to run.
            await send('Input.dispatchMouseEvent',{type:'mouseReleased',...point,button:'left',clickCount:1});
        }
        await delay(80); // Include the admin form's deferred focusout/close behavior.
    };
    await evaluate("window.itemChanges=0;depts.wrapper.addEventListener('gnc-multiselect:change',()=>itemChanges++);");
    for(const [width,touch] of [[1440,false],[768,true],[390,true]]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:touch});
        await send('Emulation.setTouchEmulationEnabled',{enabled:touch});
        await evaluate("depts.deselectAll();programs.deselectAll();depts.openDropdown();window.itemChanges=0;");
        const dept='#depts .gnc-ms-item-option[data-value="1"]';
        await click(dept+' .gnc-ms-option-text',touch);
        assert.deepEqual(await evaluate('depts.getSelected()'),['1']);
        assert.deepEqual(await evaluate('programs.getSelected()'),['A','C'],'Selecting a department checks its programs');
        assert.equal(await evaluate('itemChanges'),1);
        assert.equal(await evaluate(`document.querySelector('${dept}').classList.contains('is-checked')`),true);
        assert.equal(await evaluate(`document.querySelector('${dept}').getBoundingClientRect().height>=44`),true);
        await click(dept,touch,true);
        assert.deepEqual(await evaluate('depts.getSelected()'),[]);
        await click(dept+' input',touch);
        assert.deepEqual(await evaluate('depts.getSelected()'),['1']);
        assert.equal(await evaluate('itemChanges'),3,'Each interaction must toggle once');
        await click('#depts .gnc-ms-item-option[data-value="2"]',touch,true);
        assert.deepEqual(await evaluate('depts.getSelected()'),['1','2']);
        assert.deepEqual(await evaluate('programs.getSelected()'),['A','C','B']);
        await evaluate('programs.deselectAll();programs.openDropdown()');
        const program='#programs .gnc-ms-item-option[data-value="A"]';
        await click(program+' .gnc-ms-option-badge',touch);
        await click('#programs .gnc-ms-item-option[data-value="B"]',touch,true);
        assert.deepEqual(await evaluate('programs.getSelected()'),['A','B']);
        await click(program+' .gnc-ms-option-text',touch);
        assert.deepEqual(await evaluate('programs.getSelected()'),['B']);
        await click(program+' input',touch);
        assert.deepEqual(await evaluate('programs.getSelected()'),['B','A']);
        await evaluate(`document.querySelector('${program} input').focus()`);
        await send('Input.dispatchKeyEvent',{type:'keyDown',key:' ',code:'Space',windowsVirtualKeyCode:32});
        await send('Input.dispatchKeyEvent',{type:'keyUp',key:' ',code:'Space',windowsVirtualKeyCode:32});
        assert.deepEqual(await evaluate('programs.getSelected()'),['B']);
    }
    console.log('PASS: full-row, text, badge, checkbox, keyboard and multi-selection interactions at desktop, tablet and mobile widths.');
    console.log('PASS: department filtering, multiple departments, empty department, reset, edit values, submitted values, search and All controls.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(socket)socket.close();browser.kill();});
