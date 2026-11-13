const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,spawnSync}=require('node:child_process');
const sessions=[];
function docker(args){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',...args],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout;}
const profile=fs.mkdtempSync(path.join(os.tmpdir(),'gnci-home-slides-'));
const browser=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:'ignore'});
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(fn,message){for(let end=Date.now()+30000;Date.now()<end;){if(await fn())return;await delay(100);}throw Error(message);}
let socket;
// Keep the real password dialog/fetch wrapper and presence checks. Mock content writes,
// password replies and private snapshots so no real slides or approvals change.
function intercept(snapshot=null,review=false){return `window.__writes=[];window.__snap=${JSON.stringify(snapshot)};window.__pw=0;window.__submitted=0;window.__deleted=0;window.__approved=0;
    let configuration;Object.defineProperty(window,'VE_CONFIG',{configurable:true,get(){return configuration;},set(value){configuration=value;if(${review})value.reviewSubmission=123;if(value.home?.slides?.length){value.home.slides[0].status='draft';window.__serverSlides=structuredClone(value.home.slides);}}});
    const original=window.fetch;window.fetch=async function(url,opts){
        const target=String(url),body=opts?.body,action=body?.get?.('action');
        const reply=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
        if(target.includes('publish-confirmation-handler')){if(action==='end')return reply({success:true});__pw++;return body.get('password')==='qa-correct'?reply({success:true,token:'a'.repeat(64)}):reply({success:false,error:'Your current password is incorrect. Nothing has been published.'},422);}
        if(target.includes('editor-draft-handler')){
            if(action==='save')__snap=body.get('snapshot');if(action==='delete')__snap=null;if(action==='submit_review')__submitted++;if(action==='complete_review')__approved++;
            return reply({success:true,revision:__snap?1:0,draft:action==='load'&&__snap?{revision:1,snapshot:JSON.parse(__snap)}:null,submission_id:123,message:'Submitted for Super Admin approval.'});
        }
        if(target.includes('page-content-handler')){
            if(!opts?.method||opts.method==='GET')return reply({success:true,slides:__serverSlides});
            __writes.push({action,status:body.get('status'),id:Number(body.get('slide_id')),type:body.get('media')?.type||null,confirmed:new Headers(opts.headers).has('X-GNCI-Publish-Token'),hasPassword:!!body.get('current_password')});
            if(action==='delete'){
                if(body.get('current_password')!=='qa-correct')return reply({success:false,error:'Your current password is incorrect. Nothing was deleted or submitted.'},403);
                if(configuration.requiresApproval)return reply({success:true,submitted:true,message:'Submitted for approval.'},202);
                __deleted++;__serverSlides=__serverSlides.filter(s=>Number(s.slide_id)!==Number(body.get('slide_id')));return reply({success:true});
            }
            if(action==='save'){
                if(!new Headers(opts.headers).has('X-GNCI-Publish-Token'))return reply({success:false,reauth_required:true,error:'Password required.'},428);
                if(window.__failVideo&&body.get('media')?.type==='video/mp4'){__failVideo=false;return reply({success:false,error:'Simulated video upload failure.'},422);}
                const id=Number(body.get('slide_id'))||9000+__serverSlides.length;
                const slide={slide_id:id,title:body.get('title'),subtitle:body.get('subtitle'),status:body.get('status'),media_type:body.get('media')?.type.startsWith('video/')?'video':'image',media_path:'/uploads/hero-slides/qa-preview.png',show_on_mobile:1,show_gradient:1};
                __serverSlides=__serverSlides.filter(s=>Number(s.slide_id)!==id).concat(slide);return reply({success:true,slide_id:id,slide});
            }
            return reply({success:true});
        }return original.apply(this,arguments);
    };`;}
(async()=>{
    await until(()=>fs.existsSync(path.join(profile,'DevToolsActivePort')),'Chrome unavailable');
    const port=fs.readFileSync(path.join(profile,'DevToolsActivePort'),'utf8').split('\n')[0],tabs=await(await fetch('http://127.0.0.1:'+port+'/json/list')).json();
    socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>socket.addEventListener('open',r,{once:true}));
    let serial=0;const pending=new Map();socket.addEventListener('message',e=>{const p=JSON.parse(e.data);if(p.id&&pending.has(p.id)){pending.get(p.id)(p);pending.delete(p.id);}});
    const send=(method,params={})=>new Promise((resolve,reject)=>{const id=++serial;pending.set(id,p=>p.error?reject(Error(JSON.stringify(p.error))):resolve(p.result));socket.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;};
    await send('Page.enable');await send('Network.enable');
    let injected;
    async function visit(snapshot=null,review=false){
        if(injected)await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:injected.identifier});
        injected=await send('Page.addScriptToEvaluateOnNewDocument',{source:intercept(snapshot,review)});
        await send('Page.navigate',{url:'http://localhost:8080/admin/content-edit.php?page=home'});
        await until(()=>evaluate(`Boolean(document.querySelector('#veDock .ve-add-slide'))&&document.getElementById('veFrame')?.contentDocument?.querySelector('[data-ve-slide]')`),'Home editor unavailable');
        await evaluate(`window.AdminDialogs.confirm=async()=>true;window.ContentCompleteness=undefined;window.doc=()=>document.getElementById('veFrame').contentDocument;`);
    }
    async function password(value){await evaluate(`document.getElementById('publish-password').value=${JSON.stringify(value)};document.getElementById('publish-password').form.requestSubmit()`);}
    async function publish(){await evaluate(`document.getElementById('vePrimary').click()`);await until(()=>evaluate(`Boolean(document.querySelector('.ve-publish-accept'))`),'Publish confirmation unavailable');await evaluate(`document.querySelector('.ve-publish-accept').click()`);await until(()=>evaluate(`Boolean(document.getElementById('publish-password'))`),'Publishing did not request password');}
    async function upload(video){
        const before=await evaluate(`document.querySelectorAll('#veDock .ve-thumb').length`);
        await evaluate(`document.querySelector('#veDock .ve-add-slide').click()`);
        await until(()=>evaluate(`typeof document.getElementById('veFile').onchange==='function'`),'File picker unavailable');
        await evaluate(`(()=>{const input=document.getElementById('veFile'),dt=new DataTransfer();dt.items.add(new File([${video?"'video-fixture'":"Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='),c=>c.charCodeAt(0))"}],${video?"'qa-video.mp4'":"'qa-photo.png'"},{type:${video?"'video/mp4'":"'image/png'"}}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await until(()=>evaluate(`Boolean(document.querySelector('.image-frame-save'))||document.querySelectorAll('#veDock .ve-thumb').length>${before}`),'Media did not reach framing or preview');
        if(await evaluate(`Boolean(document.querySelector('.image-frame-save'))`))await evaluate(`document.querySelector('.image-frame-save').click()`);
        await until(()=>evaluate(`document.querySelectorAll('#veDock .ve-thumb').length===${before+1}`),'Media was not staged');
    }
    let reviewSnapshot;
    for(const role of ['Super Admin','Content Editor']){
        const session=JSON.parse(docker([role]));sessions.push(session);await send('Network.setCookie',{...session,url:'http://localhost:8080',httpOnly:true});
        await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});await visit();
        if(role==='Super Admin'){
            assert.equal(await evaluate(`document.getElementById('vePrimary').disabled`),false,'Stored draft cannot be published without unrelated edits');
            await publish();await password('wrong');await until(()=>evaluate(`document.querySelector('[data-publish-error]').textContent.includes('incorrect')`),'Wrong-password feedback missing');
            assert.deepEqual(await evaluate('__writes'),[],'A slide was saved before valid publication confirmation');
            await password('qa-correct');await until(()=>evaluate(`__writes.some(w=>w.action==='save'&&w.status==='published')`),'Stored draft was saved as draft instead of published');
            await visit();
        }
        await upload(false);await upload(true);
        assert.equal(await evaluate('__pw'),0,'Upload requested a publication password');assert.deepEqual(await evaluate('__writes'),[],'Upload wrote slide data before Save draft/Publish');
        await evaluate(`document.getElementById('veSaveDraft').click()`);await until(()=>evaluate('Boolean(__snap)'),'Private draft was not saved');
        assert.equal(await evaluate('__pw'),0,'Saving a private draft requested a password');
        const snapshot=await evaluate('__snap');assert.equal(JSON.parse(snapshot).files.length>=2,true,'Draft lost photo/video files');
        await visit(snapshot);await until(()=>evaluate(`doc().querySelectorAll('[data-ve-slide^="-"]').length===2`),'Photo/video draft did not restore');
        if(role==='Super Admin'){
            await evaluate('window.__failVideo=true');await publish();await password('qa-correct');
            await until(()=>evaluate(`document.body.textContent.includes('Simulated video upload failure.')&&!document.getElementById('vePrimary').disabled`),'Partial failure did not preserve pending changes');
            assert.equal(await evaluate(`doc().querySelectorAll('[data-ve-slide^="-"]').length`),1,'Successful photo kept a temporary ID and could be duplicated');
            await publish();await password('qa-correct');await until(()=>evaluate(`__writes.filter(w=>w.action==='save').length===3`),'Failed video could not be retried');
            const writes=await evaluate(`__writes.filter(w=>w.action==='save')`);assert.ok(writes.every(w=>w.status==='published'&&w.confirmed&&w.id===0));assert.deepEqual(writes.map(w=>w.type).sort(),['image/png','video/mp4','video/mp4']);
        }else{
            await evaluate(`document.getElementById('vePrimary').click()`);await until(()=>evaluate('__submitted===1'),'Content Editor did not submit a private approval snapshot');
            assert.deepEqual(await evaluate('__writes'),[],'Content Editor wrote the live slideshow');assert.equal(await evaluate('__pw'),0,'Submitting an approval snapshot requested a publishing password');
            reviewSnapshot=await evaluate('__snap');
        }
        await visit();await evaluate(`document.querySelector('#veDock .ve-thumb').click();document.querySelector('#veTb [title="Delete this slide"]').click()`);
        await until(()=>evaluate(`Boolean(document.getElementById('homeSlideDeletePassword'))`),'Slide deletion did not ask for password');
        await send('Emulation.setDeviceMetricsOverride',{width:390,height:1000,deviceScaleFactor:1,mobile:false});
        assert.ok(await evaluate(`document.querySelector('.gnc-dialog-danger').getBoundingClientRect().width<=innerWidth`));
        await evaluate(`document.getElementById('homeSlideDeletePassword').value='discard-this';document.querySelector('.gnc-dialog-danger .gnc-dialog-cancel').click()`);
        await until(()=>evaluate(`!document.getElementById('homeSlideDeletePassword')`),'Cancelled deletion dialog did not clear');
        assert.deepEqual(await evaluate('__writes'),[],'Cancelling deletion sent a delete request');
        await evaluate(`document.querySelector('#veTb [title="Delete this slide"]').click()`);
        await until(()=>evaluate(`Boolean(document.getElementById('homeSlideDeletePassword'))`),'Deletion dialog did not reopen');
        await evaluate(`document.getElementById('homeSlideDeletePassword').value='wrong';document.getElementById('homeSlideDeletePassword').form.requestSubmit()`);
        await until(()=>evaluate(`document.querySelector('[data-slide-delete-error]').textContent.includes('incorrect')`),'Incorrect deletion password did not show feedback');
        assert.equal(await evaluate(`document.getElementById('homeSlideDeletePassword').value`),'');assert.equal(await evaluate('__deleted'),0);
        if(role==='Super Admin'){const screenshot=path.join(profile,'delete-slide-mobile.png');fs.writeFileSync(screenshot,Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'));console.log('Deletion modal screenshot:',screenshot);}
        await evaluate(`document.getElementById('homeSlideDeletePassword').value='qa-correct';document.getElementById('homeSlideDeletePassword').form.requestSubmit()`);
        await until(()=>evaluate(`!document.getElementById('homeSlideDeletePassword')`),'Confirmed deletion did not close the modal');
        assert.equal(await evaluate('__deleted'),role==='Super Admin'?1:0,'Deletion approval routing changed');
        console.log(role+': upload, draft restore, publish/approval and password-protected deletion passed.');
    }
    await send('Network.setCookie',{...sessions[0],url:'http://localhost:8080',httpOnly:true});await visit(reviewSnapshot,true);
    await until(()=>evaluate(`doc().querySelectorAll('[data-ve-slide^="-"]').length===2`),'Approval snapshot lost staged slides');
    assert.equal(await evaluate(`document.getElementById('vePrimary').textContent`),'Approve and publish');
    await publish();await password('qa-correct');await until(()=>evaluate('__approved===1'),'Successful reviewer publication did not complete approval');
    assert.equal(await evaluate(`__writes.filter(w=>w.action==='save'&&w.status==='published'&&w.confirmed).length`),2);
    console.log('PASS: No real slides, publication tokens, private drafts or approvals changed; content/password responses mocked.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{socket?.close();browser.kill();for(const s of sessions)docker(['destroy',s.value]);});
