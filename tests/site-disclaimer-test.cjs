const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const script=fs.readFileSync('assets/js/site-disclaimer.js','utf8');
function fixture({loaded=false,intro=false,entrance=false,dismissed=false}={}){
    let mutation,disconnected=false,shown=0;
    const events=new Map(),values=new Map(dismissed?[['gnc_site_notice_v1','dismissed']]:[]);
    const dialog={open:false,addEventListener:(name,fn)=>events.set('dialog:'+name,fn),showModal(){this.open=true;shown++;}};
    const document={readyState:loaded?'complete':'interactive',documentElement:{classList:{contains:()=>intro}},getElementById:id=>id==='gncSiteDisclaimer'?dialog:id==='gncPageEntrance'&&entrance?{}:null};
    const window={addEventListener:(name,fn)=>events.set(name,fn),removeEventListener:name=>events.delete(name)};window.top=window;window.self=window;
    vm.runInNewContext(script,{document,window,location:{pathname:'/'},sessionStorage:{getItem:key=>values.get(key),setItem:(key,value)=>values.set(key,value)},MutationObserver:class{constructor(fn){mutation=fn;}observe(){}disconnect(){disconnected=true;}}});
    return {shown:()=>shown,disconnected:()=>disconnected,load(){document.readyState='complete';events.get('load')?.();},finishIntro(){intro=false;mutation?.();},finishEntrance(){entrance=false;mutation?.();},dismiss(){events.get('dialog:close')?.();},values};
}
let f=fixture({intro:true,entrance:true});assert.equal(f.shown(),0);
f.load();assert.equal(f.shown(),0,'Page load alone must not interrupt intro');
f.finishIntro();assert.equal(f.shown(),0,'Wait for shared entrance too');
f.finishEntrance();assert.equal(f.shown(),1);assert.equal(f.disconnected(),true,'Observer stops after showing');
f.dismiss();assert.equal(f.values.get('gnc_site_notice_v1'),'dismissed');
f=fixture({loaded:true,entrance:true});assert.equal(f.shown(),0);f.finishEntrance();assert.equal(f.shown(),1);
f=fixture();f.finishEntrance();assert.equal(f.shown(),0,'Overlay completion alone must not bypass page load');f.load();assert.equal(f.shown(),1);
assert.equal(fixture({loaded:true}).shown(),1,'Already-loaded pages show immediately');
assert.equal(fixture({loaded:true,dismissed:true}).shown(),0,'Dismissed notices stay dismissed');
console.log('PASS: waits for load and both opening overlays, handles already-loaded pages, disconnects observer and preserves dismissal.');
