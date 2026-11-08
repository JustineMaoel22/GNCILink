const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const origin='https://gncipampanga.com',handlers={};
let offline=false,claimed=0,accepted=0;
const self={location:new URL(origin+'/admin/service-worker.js'),clients:{async claim(){claimed++;}},async skipWaiting(){accepted++;},addEventListener(name,fn){handlers[name]=fn;}};
const context=vm.createContext({self,URL,Request,Response,fetch:async request=>{
    assert.equal(request.cache,'no-store','Private navigation bypasses the HTTP cache');
    if(offline)throw Error('offline');
    return new Response('private dashboard',{headers:{'Cache-Control':'private, no-store'}});
}});
const source=fs.readFileSync(path.join(__dirname,'../admin/service-worker.php'),'utf8');
vm.runInContext(source.slice(source.indexOf('?>')+2),context);
async function event(name,data){let work;handlers[name]({...data,waitUntil:p=>{work=p;}});await work;}
(async()=>{
    await event('activate',{});assert.equal(claimed,1);
    const navigate=async url=>{
        const request=new Request(url);Object.defineProperty(request,'mode',{value:'navigate'});
        let response;handlers.fetch({request,respondWith:p=>{response=p;}});return response;
    };
    assert.equal(await(await navigate(origin+'/admin/dashboard')).text(),'private dashboard');
    offline=true;
    const fallback=await navigate(origin+'/admin/dashboard');
    assert.equal(fallback.status,503);assert.match(fallback.headers.get('Cache-Control'),/no-store/);
    assert.ok(!(await fallback.text()).includes('private dashboard'),'Disconnected launch never replays private content');
    for(const request of [new Request(origin+'/admin/action/save.php',{method:'POST',body:'sensitive'}),new Request(origin+'/api/search.php'),new Request(origin+'/assets/css/pwa.css')]){
        let intercepted=false;handlers.fetch({request,respondWith(){intercepted=true;}});assert.equal(intercepted,false,'API/form/assets keep native behavior');
    }
    assert.equal(await navigate(origin+'/'),undefined,'Admin worker does not intercept visitor navigation');
    await event('message',{source:{url:origin+'/'},data:{type:'SKIP_WAITING'}});assert.equal(accepted,0,'Visitor cannot activate the admin worker');
    await event('message',{source:{url:origin+'/admin/login'},data:{type:'SKIP_WAITING'}});assert.equal(accepted,1);
    console.log('PASS: admin worker is scoped, network-only, stores no private pages, preserves POST/API behavior, and accepts only admin update requests.');
})().catch(error=>{console.error(error);process.exitCode=1;});
