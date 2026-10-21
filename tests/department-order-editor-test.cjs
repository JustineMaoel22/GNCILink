const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/content-edit.js','utf8');
async function check(page,scope){
    let dirty=true,fail=true,work,posted,reloads=0,disposed=0;
    const ordering={scope,dirty:()=>dirty,order:()=>[2,1],saved(){dirty=false;},consumeClick:()=>true,dispose(){disposed++;}};
    const doc={getElementById:id=>id==='veBasicData'?{textContent:JSON.stringify({page,fields:{}})}:null,querySelector:()=>null};
    const context=vm.createContext({$:()=>null,$$:()=>[],D:{getElementById:()=>({}),addEventListener(){}},S:{doc,url:'/'},C:{},MB:1024*1024,
        window:{addEventListener(){},GNCDepartmentOrder:{create:()=>ordering}},
        fieldOf:()=>null,renderToolbar(){},chrome(){},entityRoute:()=>({page:'college-departments',sub:''}),plural:(n,w)=>`${n} ${w}`,
        confirmPublish(message,fn){fn();},run(label,fn){work=fn();},sendContent:async fields=>{posted=fields;return fail?{ok:false,error:'Try again'}:{ok:true};},
        reloadEditor(){reloads++;},toast(){},select(){},closePop(){}});
    vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'),source.indexOf('    function FeedAdapter()')),context);
    const adapter=context.EntityAdapter();adapter.mount(doc);
    assert.equal(adapter.dirtyCount(),1);assert.equal(adapter.actions().primary.label,'Publish');assert.equal(adapter.actions().primary.disabled,false);
    assert(adapter.consumeClick());adapter.runPrimary();await work;
    assert.equal(posted.action,'save_department_order');assert.equal(posted.scope,scope);assert.deepEqual(JSON.parse(posted.order),[2,1]);
    assert.equal(adapter.dirtyCount(),1,'Failed publishing retains pending order');assert.equal(reloads,0);
    fail=false;adapter.runPrimary();await work;assert.equal(adapter.dirtyCount(),0);assert.equal(reloads,1);assert.equal(adapter.actions().primary.disabled,true);
    adapter.mount(doc);assert.equal(disposed,1,'Remount releases old drag listeners');
}
(async()=>{await check('college-index','college');await check('index','basic-education');console.log('PASS: both department editors stage order, enable Publish, retain failed saves, retry, reset and dispose.');})().catch(e=>{console.error(e);process.exitCode=1;});
