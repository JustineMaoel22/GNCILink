const assert = require('node:assert/strict');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path');
const {spawn,spawnSync} = require('node:child_process');
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'gnci-gaze-'));
const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'], {windowsHide:true,stdio:'ignore'});
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket,auth,mirrorAuth;
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
    const send = (method,params={},sessionId) => new Promise((resolve,reject) => {
        const id=++serial; pending.set(id,data => data.error ? reject(Error(JSON.stringify(data.error))) : resolve(data.result));
        socket.send(JSON.stringify({id,method,params,...(sessionId?{sessionId}:{})}));
    });
    const evaluate = async expression => {
        const result=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
        if (result.exceptionDetails) throw Error(JSON.stringify(result.exceptionDetails));
        return result.result.value;
    };





    await send('Page.enable');await send('Network.enable');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
    const base='http://localhost:8080';
    const visit=async route=>{await send('Page.navigate',{url:base+route});await until(()=>evaluate("document.readyState==='complete'"),'Page unavailable: '+route);};
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await visit('/admin/dashboard');
    assert.equal(await evaluate('location.pathname'),'/admin/login','App launch retains authentication redirect');
    await until(()=>evaluate("navigator.serviceWorker.controller?.scriptURL.endsWith('/admin/service-worker.js')"),'Shared worker registration unavailable');
    assert.equal(await evaluate("document.querySelector('link[rel=manifest]').getAttribute('href')"),'/admin-manifest.json');
    let manifest=await send('Page.getAppManifest');assert.deepEqual(manifest.errors,[]);
    const app=JSON.parse(manifest.data);assert.equal(app.id,'/admin/');assert.equal(app.start_url,'/admin/login');assert.equal(app.display,'standalone');assert.equal(app.scope,'/admin/');
    assert.ok(app.icons.every(icon=>icon.src.includes('/admin-')),'Admin launcher uses distinct icon files');
    assert.match(await evaluate("document.querySelector('link[rel=apple-touch-icon]').getAttribute('href')"),/admin-apple-touch-icon/);
    const dimensions=await evaluate(`Promise.all(${JSON.stringify(['/assets/images/pwa/admin-icon-192.png','/assets/images/pwa/admin-icon-512.png','/assets/images/pwa/admin-maskable-512.png','/assets/images/pwa/admin-apple-touch-icon.png'])}.map(url=>new Promise(resolve=>{const image=new Image();image.onload=()=>resolve([image.naturalWidth,image.naturalHeight]);image.onerror=()=>resolve(null);image.src=url;})))`);
    assert.deepEqual(dimensions,[[192,192],[512,512],[512,512],[180,180]]);
    const install=await send('Page.getInstallabilityErrors');assert.deepEqual(install.installabilityErrors,[],'Admin app passes Chromium installability checks');
    await evaluate("document.getElementById('gncPwaInstall').click()");
    await until(()=>evaluate("!!document.getElementById('gncPwaInstallHelp')?.open"),'Login installation instructions unavailable');
    assert.match(await evaluate("document.getElementById('gncPwaInstallHelp').textContent"),/GNCILink Admin/);
    auth=JSON.parse(session(['Super Admin']));await send('Network.setCookie',{...auth,url:base,httpOnly:true});
    // Exercise the existing login JavaScript's successful redirect without
    // submitting credentials or sending OTP email. The test session is already authenticated.
    await evaluate(`(()=>{document.getElementById('gncPwaInstallHelp').close();const original=window.fetch;window.fetch=(url,options)=>String(url).includes('/admin/auth/auth_handler.php')?Promise.resolve(new Response(JSON.stringify({success:true,redirect:'/admin/admin-dash.php'}),{status:200,headers:{'Content-Type':'application/json'}})):original(url,options);document.getElementById('email').value='test@example.invalid';document.getElementById('password').value='local-only-placeholder';document.getElementById('login-form').requestSubmit();})()`);
    await until(()=>evaluate("document.readyState==='complete'&&location.pathname==='/admin/dashboard'"),'Successful login must stay in the admin scope');
    await visit('/admin/login');
    assert.equal(await evaluate('location.pathname'),'/admin/dashboard','Authenticated launcher goes directly to the dashboard');
    const sidebar=await evaluate("[...document.querySelectorAll('.gnc-sidebar a[href]')].map(a=>a.getAttribute('href')).filter(h=>h.startsWith('/'))");
    assert.ok(sidebar.length>5&&sidebar.every(h=>h.startsWith('/admin/')),'Entire admin sidebar remains in the installed scope');
    for(const page of ['dashboard','content-editor','submissions','activity-logs','settings','manage-news','manage-announcements','manage-events','manage-users','admin-help','account-password']){
        await visit('/admin/'+page);
        assert.ok((await evaluate('location.pathname')).startsWith('/admin/'),'Administrative navigation stays scoped: '+page);
        assert.equal(await evaluate("document.querySelector('link[rel=manifest]')?.getAttribute('href')"),'/admin-manifest.json',page+' uses only the admin manifest');
        await until(()=>evaluate("navigator.serviceWorker.controller?.scriptURL.endsWith('/admin/service-worker.js')"),'Admin worker controls '+page);
    }
    for(const bookmark of ['/dashboard','/login','/settings','/content-editor']){
        const destination=await evaluate(`fetch(${JSON.stringify(bookmark)},{cache:'no-store'}).then(r=>({status:r.status,path:new URL(r.url).pathname}))`);
        assert.equal(destination.status,200,'Existing bookmark works: '+bookmark);
        assert.ok(destination.path.startsWith('/admin/'),'Existing bookmark redirects inside app scope');
    }
    await visit('/admin/dashboard');
    assert.equal(await evaluate('location.pathname'),'/admin/dashboard');
    const headers=await evaluate("fetch('/admin/dashboard',{cache:'no-store'}).then(r=>({cache:r.headers.get('Cache-Control'),marker:r.headers.get('X-GNCI-PWA-Cache')}))");
    assert.match(headers.cache,/no-store/);assert.equal(headers.marker,null);
    for(const width of [1440,820,390]){
        await send('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});await delay(200);
        assert.equal(await evaluate("!!document.getElementById('gncPwaInstall')"),false,'Authenticated admin pages have no install button at '+width);
        if([1440,390].includes(width)){
            fs.mkdirSync(path.join(__dirname,'../cache/pwa'),{recursive:true});
            const shot=await send('Page.captureScreenshot',{format:'png'});
            fs.writeFileSync(path.join(__dirname,'../cache/pwa/admin-'+width+'.png'),Buffer.from(shot.data,'base64'));
        }
    }
    await evaluate("window.adminPwaDocumentToken=Math.random()");
    const token=await evaluate('adminPwaDocumentToken');
    await send('Network.emulateNetworkConditions',{offline:true,latency:0,downloadThroughput:0,uploadThroughput:0});
    await until(()=>evaluate("document.getElementById('gncPwaNotice')?.textContent.includes('Admin panel is offline')"),'Admin offline notice missing');
    const result=await evaluate("fetch('/admin/dashboard',{cache:'no-store'}).then(()=>false).catch(()=>true)");
    assert.equal(result,true,'Offline admin requests fail rather than replay cached private HTML');
    await send('Network.emulateNetworkConditions',{offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1});
    await until(()=>evaluate("!document.getElementById('gncPwaNotice')"),'Recovery dismisses connection notice');
    assert.equal(await evaluate('adminPwaDocumentToken'),token,'Reconnection does not reload administrative work');
    await visit('/admin/content-editor?page=college-departments&sub=cased');
    await until(()=>evaluate("window.VE?.state?.doc&&document.getElementById('veLoader').hidden"),'Editor unavailable');
    assert.equal(await evaluate("document.querySelector('link[rel=manifest]').getAttribute('href')"),'/admin-manifest.json');
    assert.equal(await evaluate("!!VE.state.doc.querySelector('link[rel=manifest]')"),false,'Editor preview does not acquire install UI');
    await visit('/admin/content-editor?page=home');
    await until(()=>evaluate("window.VE?.state?.doc&&document.getElementById('veLoader').hidden"),'Homepage editor unavailable');
    assert.equal(await evaluate("!!document.getElementById('veSettingsBtn')"),false,'Homepage full-form entry point is removed');
    assert.equal(await evaluate("!!document.getElementById('president-name')"),false,'President full form is not rendered');
    assert.equal(await evaluate("!!document.getElementById('gncPwaInstall')"),false,'Homepage editor has no install button');
    assert.ok(await evaluate("!!VE.state.doc.querySelector('[data-ve-field=\"pres-name\"]')?.__ve"),'President inline editing remains available');
    await visit('/admin/news.php?embed=1');
    assert.equal(await evaluate("!!document.querySelector('link[rel=manifest]')"),false,'Embedded editors have no installation UI');
    const keys=await evaluate("caches.keys().then(async names=>(await Promise.all(names.filter(n=>n.startsWith('gncilink-pwa-')).map(async n=>(await(await caches.open(n)).keys()).map(r=>r.url)))).flat())");
    assert.ok(keys.every(url=>!/(?:\/dashboard|\/login|\/content-editor|\/admin\/|\/api\/)/.test(new URL(url).pathname)),'Admin/auth/API documents never enter Cache Storage');
    const targets=await send('Target.getTargets');
    const target=targets.targetInfos.find(t=>t.type==='service_worker'&&t.url===base+'/admin/service-worker.js');
    const workerSession=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true})).sessionId;
    await send('Network.enable',{},workerSession);
    const offline={offline:true,latency:0,downloadThroughput:0,uploadThroughput:0};
    const online={offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1};
    await send('Network.emulateNetworkConditions',offline,workerSession);
    await send('Network.emulateNetworkConditions',offline);
    await visit('/admin/dashboard');
    assert.ok(await evaluate("document.body.textContent.includes('Reconnect to GNCILink Admin')"),'Offline cold launch is a generic connection screen');
    assert.equal(await evaluate("!!document.querySelector('.topbar-user,.dashboard-page')"),false,'Offline launch exposes no private dashboard');
    await send('Network.emulateNetworkConditions',online,workerSession);
    await send('Network.emulateNetworkConditions',online);
    await visit('/admin/login');
    assert.equal(await evaluate('location.pathname'),'/admin/dashboard');
    await visit('/');
    assert.equal(await evaluate("document.querySelector('link[rel=manifest]').getAttribute('href')"),'/manifest.json','Public app identity remains separate');
    await until(()=>evaluate("navigator.serviceWorker.getRegistrations().then(regs=>regs.some(r=>r.scope===location.origin+'/'&&r.active)&&regs.some(r=>r.scope===location.origin+'/admin/'&&r.active))"),'Visitor and admin workers coexist in the same browser profile');
    const apps=await evaluate("Promise.all(['/manifest.json','/admin-manifest.json'].map(url=>fetch(url).then(r=>r.json())))");
    assert.notEqual(apps[0].id,apps[1].id);assert.notEqual(apps[0].start_url,apps[1].start_url);assert.notEqual(apps[0].icons[0].src,apps[1].icons[0].src);
    mirrorAuth=JSON.parse(session(['The Mirror']));await send('Network.setCookie',{...mirrorAuth,url:base,httpOnly:true});
    await visit('/admin/login');
    const permissions=await evaluate("Promise.all(['/admin/manage-users','/manage-users','/admin/manage-news'].map(url=>fetch(url).then(r=>r.status)))");
    assert.deepEqual(permissions,[403,403,200],'Scoped URLs and old aliases preserve role restrictions');
    console.log('PASS: separate admin identity and Chromium installability, authentication redirects, install controls at desktop/tablet/phone sizes, online-only private pages, connection recovery without reload, and editor/embed exclusions.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{if(auth)session(['destroy',auth.value]);if(mirrorAuth)session(['destroy',mirrorAuth.value]);if(socket)socket.close();browser.kill();});


