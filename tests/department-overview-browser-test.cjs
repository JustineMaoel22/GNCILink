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

    const editor = fs.readFileSync('assets/js/content-edit.js', 'utf8');
    const sanitizer = editor.slice(editor.indexOf('    var DROP ='), editor.indexOf('    function countWords('));
    const commands = editor.slice(editor.indexOf('    function exec(cmd,'), editor.indexOf('    function setStatus('));
    const helpers = editor.slice(editor.indexOf('    function blocksText('), editor.indexOf('    /** Keep pasted/new paragraphs'));
    const styles = editor.slice(editor.indexOf('    var FRAME_CSS ='), editor.indexOf('    function injectStyle('));
    const esc = editor.slice(editor.indexOf('    function esc('), editor.indexOf('\n', editor.indexOf('    function esc(')));
    await evaluate(`(() => { ${styles} const style=document.createElement('style');style.textContent=FRAME_CSS;document.head.appendChild(style); })()`);
    for (const [width,height] of [[1440,900],[768,1024],[390,844]]) {
        await send('Emulation.setDeviceMetricsOverride', {width,height,deviceScaleFactor:1,mobile:false});
        await delay(150);
        const result = await evaluate(`(() => {
            ${sanitizer}
            ${helpers}
            ${esc}
            const PLAIN='plaintext-only';
            const content=document.querySelector('.dept-about-content');
            let wrapper=content.querySelector('[data-ve-overview]');
            if(wrapper) { wrapper.replaceWith(...wrapper.childNodes); }
            const snapshot=()=>Array.from(content.querySelectorAll('p,ul,li')).map(el=>{
                const css=getComputedStyle(el),rect=el.getBoundingClientRect(),parent=content.getBoundingClientRect();
                return {tag:el.tagName,text:el.textContent,font:css.fontFamily,size:css.fontSize,weight:css.fontWeight,line:css.lineHeight,margin:css.margin,padding:css.padding,list:css.listStyleType,width:rect.width,height:rect.height,y:Math.round((rect.top-parent.top)*100)/100};
            });
            const before=snapshot();
            wrapper=document.createElement('div');wrapper.className='dept-about-raw';
            wrapper.setAttribute('data-ve-overview','');wrapper.setAttribute('data-ve-field','dept-about');wrapper.setAttribute('data-ve-multiline','1');
            Array.from(content.children).filter(el=>el.matches('p,ul,ol')).forEach(el=>wrapper.appendChild(el));
            content.appendChild(wrapper); makeField(wrapper,()=>{});
            const after=snapshot();
            const plain=readField(wrapper);
            const html=wrapper.innerHTML;
            wrapper.innerHTML=plain;
            const roundtrip=snapshot();
            const plainAgain=readField(wrapper);
            return {before,after,roundtrip,plain,plainAgain,editable:wrapper.getAttribute('contenteditable')};
        })()`);
        assert.deepEqual(result.after,result.before,`Editor typography and layout match live at ${width}px`);
        assert.deepEqual(result.roundtrip,result.before,`Rich-text save/reload preserves paragraph/list formatting at ${width}px`);
        assert.equal(result.plainAgain,result.plain,'Serialization is stable');
        assert.equal(result.editable,'true');
        assert.match(result.plain,/<ul[^>]*>/,'List markup is saved');
    }

    const formatted = await evaluate(`(() => {
        ${sanitizer}
        ${helpers}
        ${esc}
        ${commands}
        const PLAIN='plaintext-only';
        function renderToolbar() {} function chrome() {}
        function askText(options) { options.done('/pages/contact-us.php'); }
        const el=document.createElement('div'); document.body.appendChild(el);
        el.setAttribute('data-ve-overview','');el.setAttribute('data-ve-multiline','1');
        el.innerHTML='<p>Format these words</p>';
        let saved='';makeField(el,(node,value)=>saved=value);
        const S={win:window,doc:document,sel:{el}};
        el.focus(); const range=document.createRange();range.selectNodeContents(el.firstChild);
        const selection=getSelection();selection.removeAllRanges();selection.addRange(range);
        for(const title of ['Bold','Italic','Underline','Add or change a link']) richTextTools().find(item=>item.title?.startsWith(title)).run();
        const inline=saved;
        richTextTools().find(item=>item.type==='select').onChange('h3');
        richTextTools().find(item=>item.title==='Align right').run();
        const heading=saved;
        richTextTools().find(item=>item.title==='Numbered list').run();
        const list=saved;
        const unsafe=cleanHtml('<p onclick="bad()">Safe <strong>bold</strong><a href="javascript:bad()">link</a></p><script>bad()</script>');
        el.remove();return {inline,heading,list,unsafe};
    })()`);
    assert.match(formatted.inline,/<strong>/);assert.match(formatted.inline,/<em>/);assert.match(formatted.inline,/<u>/);assert.match(formatted.inline,/href="\/pages\/contact-us.php"/);
    assert.match(formatted.heading,/<h3[^>]*style="text-align:right"/);
    assert.match(formatted.list,/<ol>/);
    assert.doesNotMatch(formatted.unsafe,/onclick|javascript:|<script/);
    console.log('PASS: matching live/editor layouts at three widths, HTML round trip, formatting toolbar commands, links, headings, alignment, numbered lists and safe HTML');

})().catch(error => { console.error(error); process.exitCode = 1; }).finally(async () => {
    if (socket) socket.close(); browser.kill();
    // Only remove this test-created, uniquely named directory under the OS temp root.
    await delay(1000);
    if (path.dirname(profile) === os.tmpdir() && path.basename(profile).startsWith('gnci-office-map-')) {
        try { fs.rmSync(profile, { recursive: true, force: true, maxRetries: 3, retryDelay: 200 }); } catch {}
    }
});
