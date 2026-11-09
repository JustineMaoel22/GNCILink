const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const source=fs.readFileSync('assets/js/request-guard.js','utf8');
function setup(fetch,signal=AbortSignal){
    const context={window:{fetch},document:{body:null},location:{href:'https://gnci.test/',origin:'https://gnci.test'},URL,Request,Response,AbortController,AbortSignal:signal,Date,setTimeout,clearTimeout};
    vm.runInNewContext(source,context);return context.window;
}
(async()=>{
    let count=0;
    const guarded=setup(async()=>{count++;return new Response('{}',{status:429,headers:{'Retry-After':'60'}});});
    assert.equal((await guarded.fetch('/api/test.php')).status,429);
    assert.equal((await guarded.fetch('/api/test.php?new=1',{method:'POST'})).status,429);
    assert.equal(count,1,'cooldown blocks query/action variations');
    await guarded.fetch('https://external.test/api');assert.equal(count,2,'third party fetch unchanged');
    let seen;
    const normal=setup(async(input,options)=>{seen=options;return new Response('ok');});
    const controller=new AbortController();await normal.fetch('/admin/action/save.php',{method:'POST',body:'x',signal:controller.signal});
    assert.equal(seen.body,'x');assert.equal(seen.method,'POST');assert.ok(seen.signal);
    controller.abort();assert.ok(seen.signal.aborted,'caller cancellation preserved');
    const request=new Request('https://gnci.test/api/test.php',{method:'POST',body:'body'});
    await normal.fetch(request);assert.ok(seen.signal,'Request objects supported');
    let failures=0;
    const failed=setup(async()=>{failures++;throw Error('offline');});
    await assert.rejects(failed.fetch('/save',{method:'POST'}));assert.equal(failures,1,'mutations never retried');
    let duration;
    const timeoutSignal={any:AbortSignal.any.bind(AbortSignal),timeout:ms=>{duration=ms;const c=new AbortController();setTimeout(()=>c.abort(Error('timeout')),5);return c.signal;}};
    const stalled=setup((input,options)=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(options.signal.reason))),timeoutSignal);
    await assert.rejects(stalled.fetch('/read'));assert.equal(duration,30000,'read deadline');
    await assert.rejects(stalled.fetch('/save',{method:'POST'}));assert.equal(duration,120000,'mutation deadline');
    let requests=0,resolve;
    const context={fetch:()=>{requests++;return new Promise(r=>resolve=r);},AbortSignal,Date,URL,URLSearchParams,globalThis:{}};
    vm.runInNewContext(fs.readFileSync('assets/js/recommendation-engine.js','utf8'),context);
    const engine=context.globalThis.GNCRecommendations;
    const a=engine.catalog(),b=engine.catalog();assert.equal(a,b);assert.equal(requests,1);
    resolve(new Response('{"items":[]}'));await a;await engine.catalog();assert.equal(requests,1,'completed cache reused');
    console.log('PASS: cooldown, cancellation, Request support, no mutation retries, recommendation deduplication/cache.');
})().catch(error=>{console.error(error);process.exitCode=1;});
