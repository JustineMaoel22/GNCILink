const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const origin = 'https://gncipampanga.com';
class BrowserRequest extends Request {
    constructor(input,options){super(typeof input==='string'?new URL(input,origin):input,options);}
}
const handlers = {}, stores = new Map();
let failNetwork = false, skip = 0, claimed = 0, publicResponse = true, privateResponse = false, requests = 0;
const key = request => new URL(typeof request === 'string' ? request : request.url,origin).href;
const caches = {
    async keys(){return [...stores.keys()];},
    async delete(name){return stores.delete(name);},
    async open(name){
        if (!stores.has(name)) stores.set(name,new Map());
        const store=stores.get(name);
        return {
            async match(request){return store.get(key(request))?.clone();},
            async put(request,response){store.set(key(request),response.clone());},
            async delete(request){return store.delete(key(request));},
            async keys(){return [...store.keys()].map(url=>new Request(url));},
            async addAll(requests){for(const request of requests)store.set(key(request),new Response('offline shell'));}
        };
    }
};
const self = {
    GNCI_PWA:{version:'test-v2',paths:['/','/vision-mission','/contact-us','/news-article'],adminPaths:['/login','/dashboard','/content-editor']},
    location:new URL(origin+'/service-worker.js'),
    clients:{async claim(){claimed++;},async get(){return null;}},
    async skipWaiting(){skip++;},
    addEventListener(name,fn){handlers[name]=fn;}
};
const context = vm.createContext({self,caches,URL,Request:BrowserRequest,Response,Headers,AbortController,Set,Date,Promise,setTimeout,clearTimeout,
    fetch:async()=>{
        requests++;
        if(failNetwork)throw Error('offline');
        const headers={'Content-Type':'text/html'};
        if(publicResponse)headers['X-GNCI-PWA-Cache']='public';
        if(privateResponse)headers['Cache-Control']='private, no-store';
        return new Response('<html><head></head><body>Published information</body></html>',{headers});
    }});
const run=source=>vm.runInContext(source,context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../assets/js/pwa-worker.js'),'utf8'),context);
async function event(name,value={}){let work;handlers[name]({...value,waitUntil:p=>{work=p;}});await work;}
(async()=>{
    for(const url of ['/', '/vision-mission','/departments/cased','/programs/beed','/news-article?slug=example'])
        assert.equal(run(`publicPage(new URL(${JSON.stringify(origin+url)}))`),true,url);
    for(const url of ['/admin/admin-dash.php','/dashboard','/login','/logout','/content-editor','/api/search.php','/chatbot/api.php','/uploads/private.pdf','/vision-mission?ce_preview=1','/vision-mission?token=secret','/vision-mission?q=personal','/unknown'])
        assert.equal(run(`publicPage(new URL(${JSON.stringify(origin+url)}))`),false,url);
    assert.equal(run(`publicPage(new URL('https://other.example/vision-mission'))`),false);
    for(const pathname of ['/assets/js/content-edit.js','/assets/css/admin-dashboard.css','/assets/css/base-style.css?secret=1','/uploads/private.png'])
        assert.equal(run(`staticAsset(new URL(${JSON.stringify(origin+pathname)}),{destination:'style'})`),false,pathname);
    assert.equal(run(`staticAsset(new URL('${origin}/assets/images/Section%20Header.png'),{destination:'image'})`),true);
    for(const request of [new Request(origin+'/vision-mission',{method:'POST',body:'private'}),new Request(origin+'/api/search.php'),new Request(origin+'/login')]){
        let intercepted=false;handlers.fetch({request,respondWith(){intercepted=true;}});
        assert.equal(intercepted,false,'Private/API/POST requests are untouched');
    }
    await (await caches.open('gncilink-pwa-old-shell')).put('/',new Response('old'));
    await caches.open('another-app-cache');
    await event('install');assert.equal(skip,0,'Updates never activate automatically while old pages are open');
    await event('activate');assert.equal(claimed,1);assert.equal(stores.has('gncilink-pwa-old-shell'),false);assert.equal(stores.has('another-app-cache'),true);
    const navigation=()=>run(`navigate(new Request('${origin}/vision-mission'))`);
    assert.match(await(await navigation()).text(),/Published information/);
    failNetwork=true;
    assert.match(await(await navigation()).text(),/name="gnci-offline"/,'Previously visited public HTML is marked as an offline copy');
    assert.equal(await(await run(`navigate(new Request('${origin}/departments/new'))`)).text(),'offline shell');
    failNetwork=false;publicResponse=false;
    await navigation(); // A now-private response removes the old public entry too.
    failNetwork=true;
    assert.equal(await(await navigation()).text(),'offline shell','Unmarked/session responses never enter the document cache');
    failNetwork=false;publicResponse=true;privateResponse=true;
    await navigation();failNetwork=true;
    assert.equal(await(await navigation()).text(),'offline shell','Private/no-store overrides any public marker');
    await event('message',{source:{url:origin+'/api/private'},data:{type:'SKIP_WAITING'}});assert.equal(skip,0);
    await event('message',{source:{url:origin+'/vision-mission'},data:{type:'SKIP_WAITING'}});assert.equal(skip,1,'A public client must explicitly accept the update');
    await event('message',{source:{url:origin+'/admin/dashboard'},data:{type:'SKIP_WAITING'}});assert.equal(skip,1,'Admin clients cannot activate the visitor worker');
    const beforeAdminWarm=requests;
    await event('message',{source:{url:origin+'/dashboard'},data:{type:'CACHE_CURRENT_PAGE'}});
    assert.equal(requests,beforeAdminWarm,'Admin clients never warm an offline page copy');
    failNetwork=false;privateResponse=false;
    const beforeVersioned=requests;
    await run(`asset(new Request('${origin}/assets/css/base-style.css?v=release-2'))`);
    await run(`asset(new Request('${origin}/assets/css/base-style.css?v=release-2'))`);
    assert.equal(requests-beforeVersioned,1,'Versioned assets reuse the exact cached resource');
    await run(`asset(new Request('${origin}/assets/css/base-style.css?v=release-2',{cache:'reload'}))`);
    assert.equal(requests-beforeVersioned,2,'Explicit reload retrieves a fresh asset');
    const beforeUnversioned=requests;
    await run(`asset(new Request('${origin}/assets/css/base-style.css'))`);
    await run(`asset(new Request('${origin}/assets/css/base-style.css'))`);
    assert.equal(requests-beforeUnversioned,2,'Unversioned assets remain network-first');
    self.clients.get=async()=>({url:origin+'/content-editor?page=home'});
    let result;
    handlers.fetch({request:new Request(origin+'/assets/css/base-style.css?v=private-client'),clientId:'admin',respondWith:p=>{result=p;}});
    await result;
    assert.equal(await(await caches.open('gncilink-pwa-test-v2-assets')).match(origin+'/assets/css/base-style.css?v=private-client'),undefined,'Admin client resources do not enter PWA caches');
    console.log('PASS: public-only route policy, cache opt-in/no-store enforcement, offline fallback, POST/API/auth exclusions, version cleanup, and update consent.');
})().catch(error=>{console.error(error);process.exitCode=1;});
