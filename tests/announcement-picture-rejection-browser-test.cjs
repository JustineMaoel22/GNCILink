// Local browser regression: file selection only; no content is submitted.
const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn, spawnSync} = require('node:child_process');
function sessionTool(args) {
    const result = spawnSync('docker', ['compose', 'exec', '-T', '--user', 'www-data', 'web', 'php', 'cache/activity-log-session.php', ...args], {encoding:'utf8', windowsHide:true});
    if (result.status !== 0) throw Error(result.stderr || result.stdout);
    return result.stdout;
}
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-picture-rejection-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], {windowsHide:true, stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(fn) {
    for (const end = Date.now() + 20000; Date.now() < end;) { if (await fn()) return; await delay(100); }
    throw Error('Browser/page unavailable');
}
let socket, session;
(async () => {
    session = JSON.parse(sessionTool(['Super Admin']));
    await until(() => fs.existsSync(path.join(profile, 'DevToolsActivePort')));
    const port = fs.readFileSync(path.join(profile, 'DevToolsActivePort'), 'utf8').split('\n')[0];
    const tabs = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, {once:true}));
    let serial = 0;
    const pending = new Map();
    socket.addEventListener('message', event => { const msg = JSON.parse(event.data); if (pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); } });
    const send = (method, params = {}) => new Promise((resolve, reject) => { const id = ++serial; pending.set(id, msg => msg.error ? reject(Error(JSON.stringify(msg.error))) : resolve(msg.result)); socket.send(JSON.stringify({id, method, params})); });
    const evaluate = async expression => { const result = await send('Runtime.evaluate', {expression, returnByValue:true, awaitPromise:true}); if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails)); return result.result.value; };
    await send('Page.enable'); await send('Network.enable');
    await send('Network.setCookie', {...session, url:'http://localhost:8080', httpOnly:true});
    for (const kind of ['announcement', 'news']) {
        await send('Page.navigate', {url:'http://localhost:8080/admin/' + kind + '.php'});
        await until(() => evaluate(`document.getElementById('${kind}-form')?.dataset.uxReady==='true'`).catch(() => false));
        for (const id of ['modal-image-input', 'modal-gallery-input']) {
            for (const [name, type] of [['bad.pdf','application/pdf'], ['bad.svg','image/svg+xml'], ['bad.txt','text/plain'], ['bad.jpg','application/pdf'], ['bad.exe','image/png']]) {
                const result = await evaluate(`(() => {
                    const input=document.getElementById(${JSON.stringify(id)}), batch=new DataTransfer();
                    batch.items.add(new File(['%PDF-1.7'],${JSON.stringify(name)},{type:${JSON.stringify(type)}}));
                    input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));
                    return {count:input.files.length,message:document.getElementById(input.id+'-error').textContent,preview:document.getElementById('modal-image-preview').getAttribute('src')};
                })()`);
                assert.equal(result.count, 0, kind + ': retained ' + name);
                assert.match(result.message, /File rejected/);
                assert.ok(!result.preview?.startsWith('data:application/pdf'), 'PDF reached the picture preview');
            }
        }
        const cleared = await evaluate(`(async () => {
            const canvas=document.createElement('canvas');canvas.width=8;canvas.height=8;
            canvas.getContext('2d').fillRect(0,0,8,8);
            const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
            function choose(id,file){const input=document.getElementById(id),batch=new DataTransfer();input.setAttribute('data-no-image-framing','');batch.items.add(file);input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));}
            for(const id of ['modal-image-input','modal-gallery-input'])choose(id,new File([blob],'valid.png',{type:'image/png'}));
            await new Promise(resolve=>setTimeout(resolve,150));
            const before=document.querySelectorAll('#modal-gallery-new img').length;
            for(const id of ['modal-image-input','modal-gallery-input'])choose(id,new File(['%PDF-1.7'],'bad.pdf',{type:'application/pdf'}));
            await new Promise(resolve=>setTimeout(resolve,150));
            const main=document.getElementById('modal-image-input'),gallery=document.getElementById('modal-gallery-input'),payload=new FormData(main.form);
            return {before,cover:document.getElementById('modal-image-preview').getAttribute('src')?.startsWith('data:image/png'),coverHidden:document.getElementById('modal-image-preview-wrap').style.display==='none',gallery:document.querySelectorAll('#modal-gallery-new img').length,galleryFiles:gallery.files.length,mainName:main.files[0]?.name,mainBytes:payload.get('image').size>0,galleryBytes:payload.get('gallery_images[]').size>0,valid:main.validity.valid&&gallery.validity.valid};
        })()`);
        assert.deepEqual(cleared, {before:1,cover:true,coverHidden:false,gallery:1,galleryFiles:1,mainName:'valid.png',mainBytes:true,galleryBytes:true,valid:true}, kind + ': rejection lost the previous valid upload');
        const accumulated = await evaluate(`(async () => {
            const input=document.getElementById('modal-gallery-input'), original=input.files[0];
            for(const name of ['second.png','third.png']){
                const batch=new DataTransfer();batch.items.add(new File([original],name,{type:'image/png'}));input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));
            }
            await new Promise(resolve=>setTimeout(resolve,150));
            const before=Array.from(input.files).map(file=>file.name);
            const bad=new DataTransfer();bad.items.add(new File(['%PDF-1.7'],'bad.pdf',{type:'application/pdf'}));input.files=bad.files;input.dispatchEvent(new Event('change',{bubbles:true}));
            return {before,after:Array.from(input.files).map(file=>file.name),thumbs:document.querySelectorAll('#modal-gallery-new img').length,uploads:new FormData(input.form).getAll('gallery_images[]').map(file=>file.name)};
        })()`);
        const expectedNames=['valid.png','second.png','third.png'];
        assert.deepEqual(accumulated,{before:expectedNames,after:expectedNames,thumbs:3,uploads:expectedNames},kind+': selecting gallery pictures individually lost earlier files');
        const limited = await evaluate(`(async () => {
            const input=document.getElementById('modal-gallery-input'),original=input.files[0],batch=new DataTransfer();
            for(let index=3;index<10;index++)batch.items.add(new File([original],'extra-'+index+'.png',{type:'image/png'}));
            input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));
            await new Promise(resolve=>setTimeout(resolve,150));
            const expected=Array.from(input.files).map(file=>file.name),alert=window.AdminDialogs.alert;window.AdminDialogs.alert=async()=>{};
            try{const extra=new DataTransfer();extra.items.add(new File([original],'over-limit.png',{type:'image/png'}));input.files=extra.files;input.dispatchEvent(new Event('change',{bubbles:true}));}finally{window.AdminDialogs.alert=alert;}
            return {count:input.files.length,kept:JSON.stringify(expected)===JSON.stringify(Array.from(input.files).map(file=>file.name)),thumbs:document.querySelectorAll('#modal-gallery-new img').length};
        })()`);
        assert.deepEqual(limited,{count:10,kept:true,thumbs:10},kind+': gallery limit discarded an earlier selection');
        if (kind === 'news') {
            const removal = await evaluate(`(async () => {
                const input=document.getElementById('modal-gallery-input');
                document.querySelector('#modal-gallery-new button[aria-label="Remove second.png"]').click();
                await new Promise(resolve=>setTimeout(resolve,150));
                const remaining=Array.from(input.files).map(file=>file.name),posted=new FormData(input.form).getAll('gallery_images[]').map(file=>file.name);
                const batch=new DataTransfer();batch.items.add(new File(['%PDF-1.7'],'bad.pdf',{type:'application/pdf'}));input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));
                const rejectedKept=JSON.stringify(remaining)===JSON.stringify(Array.from(input.files).map(file=>file.name));
                while(input.files.length){document.querySelector('#modal-gallery-new .gallery-thumb-remove').click();await new Promise(resolve=>setTimeout(resolve,30));}
                return {count:remaining.length,removed:!remaining.includes('second.png'),postedMatches:JSON.stringify(remaining)===JSON.stringify(posted),rejectedKept,emptyHidden:document.getElementById('modal-gallery-new-wrap').style.display==='none',thumbnails:document.querySelectorAll('#modal-gallery-new img').length};
            })()`);
            assert.deepEqual(removal,{count:9,removed:true,postedMatches:true,rejectedKept:true,emptyHidden:true,thumbnails:0},'News gallery removal did not update its files and previews');
        }
        const preserved = await evaluate(`(async () => {
            const canvas=document.createElement('canvas');canvas.width=8;canvas.height=8;const original=canvas.toDataURL();
            const preview=document.getElementById('modal-image-preview'),input=document.getElementById('modal-image-input');
            input.value='';
            preview.src=original;document.getElementById('modal-image-preview-wrap').style.display='block';document.getElementById('modal-remove-image-wrap').style.display='block';
            document.getElementById('${kind === 'news' ? 'newsModal' : 'announcementModal'}').dispatchEvent(new Event('show.bs.modal'));
            const batch=new DataTransfer();batch.items.add(new File(['%PDF-1.7'],'bad.pdf',{type:'application/pdf'}));input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));
            return preview.getAttribute('src')===original&&document.getElementById('modal-image-preview-wrap').style.display==='block';
        })()`);
        assert.equal(preserved, true, kind + ': rejected replacement removed the saved picture preview');
        const removedSaved=await evaluate(`(() => {
            document.getElementById('modal-remove-picture').click();
            const input=document.getElementById('modal-image-input'),batch=new DataTransfer();batch.items.add(new File(['%PDF-1.7'],'bad.pdf',{type:'application/pdf'}));input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));
            return {hidden:document.getElementById('modal-image-preview-wrap').style.display==='none',src:document.getElementById('modal-image-preview').getAttribute('src'),remove:new FormData(input.form).get('remove_image'),files:input.files.length};
        })()`);
        assert.deepEqual(removedSaved,{hidden:true,src:null,remove:'1',files:0},kind+': remove button failed to mark the saved picture for removal');
        // Exercise the real positioning dialog, including Cancel and Escape.
        await evaluate(`(() => {
            const input=document.getElementById('modal-image-input');input.removeAttribute('data-no-image-framing');
            const canvas=document.createElement('canvas');canvas.width=8;canvas.height=8;
            window.__cancelPicture=canvas.toDataURL();
        })()`);
        async function selectPositioned(name) {
            await evaluate(`(async () => {
                const blob=await(await fetch(__cancelPicture)).blob(),input=document.getElementById('modal-image-input'),batch=new DataTransfer();
                batch.items.add(new File([blob],${JSON.stringify(name)},{type:'image/png'}));input.files=batch.files;input.dispatchEvent(new Event('change',{bubbles:true}));
            })()`);
            await until(()=>evaluate(`Boolean(document.querySelector('.image-frame-dialog[open]'))`));
        }
        await selectPositioned('kept.png');
        await evaluate(`document.querySelector('.image-frame-save').click()`);
        await until(()=>evaluate(`!document.querySelector('.image-frame-dialog')&&document.getElementById('modal-image-input').files[0]?.name==='kept.png'`));
        await delay(100);
        const originalPreview=await evaluate(`document.getElementById('modal-image-preview').getAttribute('src')`);
        for(const cancel of ['button','escape']){
            await selectPositioned('cancelled-'+cancel+'.png');
            if(cancel==='button')await evaluate(`document.querySelector('.image-frame-actions button').click()`);
            else {await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});}
            await until(()=>evaluate(`!document.querySelector('.image-frame-dialog')`));
            const retained=await evaluate(`({name:document.getElementById('modal-image-input').files[0]?.name,uploaded:new FormData(document.getElementById('${kind}-form')).get('image').size>0,preview:document.getElementById('modal-image-preview').getAttribute('src')})`);
            assert.deepEqual(retained,{name:'kept.png',uploaded:true,preview:originalPreview},kind+': cancelling picture positioning lost the previous upload');
        }
        const removedNew=await evaluate(`(() => {
            document.getElementById('modal-remove-picture').click();const input=document.getElementById('modal-image-input');
            return input.files.length===0&&new FormData(input.form).get('image').size===0&&document.getElementById('modal-image-preview-wrap').style.display==='none';
        })()`);
        assert.equal(removedNew,true,kind+': remove button kept a new upload queued');
    }
    console.log('PASS: Announcements and News reject unsupported files, retain previous valid picture files and upload bytes, and preserve saved picture previews. No content submitted.');
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => {
    socket?.close(); browser.kill(); if (session) sessionTool(['destroy', session.value]);
});
