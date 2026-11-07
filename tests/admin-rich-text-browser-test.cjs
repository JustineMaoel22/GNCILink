const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-rich-text-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket, auth;
const localSession = args => {const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;};
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


    auth=JSON.parse(localSession(['Super Admin']));
    await send('Network.setCookie',{...auth,url:'http://localhost:8080',httpOnly:true});
    await send('Page.enable');
    socket.addEventListener('message',event=>{const message=JSON.parse(event.data);if(message.method==='Page.javascriptDialogOpening')send('Page.handleJavaScriptDialog',{accept:true});});
    const setText=async(index=0,length=5)=>evaluate(`modalQuill.loadHtml('<p>Alpha Beta</p><p>Second line</p>');modalQuill.setSelection(${index},${length});`);
    let touch=false;
    const click=async selector=>{
        const point=await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});if(!e)throw Error('Missing '+${JSON.stringify(selector)});e.scrollIntoView({block:'center'});const r=e.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()`);
        if(touch){await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[point]});await delay(100);await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});}
        else{await send('Input.dispatchMouseEvent',{type:'mousePressed',...point,button:'left',clickCount:1});await delay(100);await send('Input.dispatchMouseEvent',{type:'mouseReleased',...point,button:'left',clickCount:1});}
        await delay(50);
    };
    for(const route of ['announcement','news']){
        await send('Page.navigate',{url:'http://localhost:8080/admin/'+route+'.php?modal=create'});
        await until(()=>evaluate("typeof modalQuill!=='undefined' && typeof modalQuill.loadHtml==='function' && document.querySelector('.modal.show')!==null"),'Editor unavailable');
        await delay(400);
        for(const width of [1440,768,390]){
            touch=width!==1440;
            await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:touch});
            await send('Emulation.setTouchEmulationEnabled',{enabled:touch});
            for(const format of ['bold','italic','underline','strike','blockquote']){
                await setText();
                await click('.ql-'+format);
                assert.equal(await evaluate(`modalQuill.getFormat(0,5).${format}`),true,`${route} ${width} ${format} apply`);
                assert.equal(await evaluate(`document.querySelector('.ql-${format}').classList.contains('ql-active')`),true);
                assert.equal(await evaluate(`Boolean(modalQuill.getFormat(11,5).${format})`),false,'Unselected line stays unchanged');
                await click('.ql-'+format);
                assert.equal(await evaluate(`Boolean(modalQuill.getFormat(0,5).${format})`),false,`${format} toggle off`);
            }
            for(const value of ['ordered','bullet']){
                await setText();await click('.ql-list[value="'+value+'"]');
                assert.equal(await evaluate('modalQuill.getFormat(0,5).list'),value);
                await click('.ql-list[value="'+value+'"]');
                assert.equal(await evaluate('Boolean(modalQuill.getFormat(0,5).list)'),false);
            }
            for(const heading of [1,2,3]){
                await setText();await click('.ql-header .ql-picker-label');await click('.ql-header .ql-picker-item[data-value="'+heading+'"]');
                assert.equal(await evaluate('modalQuill.getFormat(0,5).header'),heading);
                await click('.ql-header .ql-picker-label');await click('.ql-header .ql-picker-item:not([data-value])');
                assert.equal(await evaluate('Boolean(modalQuill.getFormat(0,5).header)'),false);
            }
            for(const align of ['center','right','justify']){
                await setText();await click('.ql-align .ql-picker-label');await click('.ql-align .ql-picker-item[data-value="'+align+'"]');
                assert.equal(await evaluate('modalQuill.getFormat(0,5).align'),align);
                await click('.ql-align .ql-picker-label');await click('.ql-align .ql-picker-item:not([data-value])');
                assert.equal(await evaluate('Boolean(modalQuill.getFormat(0,5).align)'),false);
            }
            await setText();await click('.ql-link');
            assert.equal(await evaluate("document.activeElement===modalQuill.theme.tooltip.textbox"),true,'Link input retains modal focus');
            await evaluate("modalQuill.theme.tooltip.textbox.value='https://example.com/one'");
            await click('.ql-tooltip .ql-action');
            assert.equal(await evaluate('modalQuill.getFormat(0,5).link'),'https://example.com/one');
            await evaluate("modalQuill.setSelection(2,0,'user')");await delay(50);
            await click('.ql-tooltip .ql-action');
            await evaluate("modalQuill.theme.tooltip.textbox.value='https://example.com/two'");await click('.ql-tooltip .ql-action');
            assert.equal(await evaluate('modalQuill.getFormat(0,5).link'),'https://example.com/two');
            await evaluate('modalQuill.setSelection(0,5)');await click('.ql-link');
            assert.equal(await evaluate('Boolean(modalQuill.getFormat(0,5).link)'),false);
            await setText(2,0);await click('.ql-bold');
            await send('Input.insertText',{text:'X'});
            assert.equal(await evaluate('modalQuill.getFormat(2,1).bold'),true,'Formatting at cursor applies to inserted text');
            await setText();await click('.ql-bold');await click('.ql-strike');await click('.ql-clean');
            assert.deepEqual(await evaluate('modalQuill.getFormat(0,5)'),{});
            assert.equal(await evaluate('modalQuill.getText()'),'Alpha Beta\nSecond line\n'.replaceAll('\\n','\n'));
            await evaluate(`modalQuill.loadHtml('<h1>Heading</h1><p style="text-align:right"><strong>Bold</strong> <em>Italic</em> <u>Underlined</u> <s>Strike</s> <a href="https://example.com">Link</a></p><ol><li>Number</li></ol><ul><li>Bullet</li></ul><blockquote>Quote</blockquote>');window.saved=modalQuill.root.innerHTML;window.preview=GncRichText.previewHtml(saved);modalQuill.loadHtml(saved);`);
            assert.equal(await evaluate("modalQuill.root.querySelector('h1')!==null && modalQuill.root.querySelector('s')!==null && modalQuill.root.querySelector('.ql-align-right')!==null"),true,'Reopen preserves headings, strike and alignment');
            assert.equal(await evaluate("new FormData(document.getElementById('modal-content-input').form).get('content')===modalQuill.root.innerHTML"),true,'Submitted content is synchronized');
            await click(route==='news'?'#news-draft-preview':'#ann-draft-preview');
            const previewId=route==='news'?'news-preview-message':'ann-preview-message';
            assert.equal(await evaluate(`document.querySelector('#${previewId} h1')!==null && document.querySelector('#${previewId} s')!==null && document.querySelector('#${previewId} p').style.textAlign==='right'`),true,'Draft preview retains formatting');
            await click(route==='news'?'[data-news-preview-close]':'[data-ann-preview-close]');
            await evaluate('document.getElementById("modal-content-input").form.dataset.testOnly="true"');
            console.log(`PASS: ${route}, ${width}px: every toolbar control, toggles, cursor, selection isolation, active states, reload, form data and preview.`);
        }
        await evaluate("window.onbeforeunload=null;window.addEventListener('beforeunload',e=>e.stopImmediatePropagation(),true)");
    }
    assert.equal(await evaluate(`(()=>{const html=GncRichText.previewHtml('<p class="ql-align-center" onclick="bad()"><s>Safe</s><a href="javascript:bad()">bad</a><script>bad()</script></p>');return !/onclick|javascript|script/.test(html)&&html.includes('text-align: center');})()`),true,'Preview sanitizer removes unsafe content');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)localSession(['destroy',auth.value]);if(socket)socket.close();browser.kill();});
