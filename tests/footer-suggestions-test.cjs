const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const engine=require('../assets/js/recommendation-engine.js');
const source=fs.readFileSync('assets/js/footer-suggestions.js','utf8'),now=Date.now(),day=86400000;
const item=(id,title,category,tags,popular=0)=>({id,title,category,tags,popular,url:'/page/'+id,description:'',date:0});
const items=[item('computing','College of Computing','Departments',['academics','dept:1']),item('it','Information Technology','Programs',['academics','dept:1']),item('cs','Computer Science','Programs',['academics','dept:1']),item('nursing','College of Nursing','Departments',['academics','dept:2']),item('news','School News','News',['updates']),item('announcement','School announcement','Announcements',['updates']),item('campus','Campus','Campus',[],500)];
class Node{constructor(){this.children=[];this.dataset={};this.hidden=true;}append(...children){this.children.push(...children);}replaceChildren(){this.children=[];}setAttribute(){}}
async function fixture({consent=true,history={},path='computing',restricted=false,session=new Map()}={}){
    let stored=JSON.stringify({version:1,consent:true,updated:now,history,shown:{}}),writes=0;
    const section=new Node(),links=new Node(),events={},win={GNCRecommendations:engine,GNCPrivacy:{allows:()=>consent},addEventListener:(name,fn)=>{events[name]=fn;}};win.top=win.self=win;
    const context={window:win,document:{getElementById:id=>id==='gncFooterSuggestions'?section:links,createElement:()=>new Node()},navigator:{globalPrivacyControl:restricted},location:{origin:'https://gnc.test',href:'https://gnc.test/page/'+path,search:''},sessionStorage:{getItem:k=>session.get(k)||null,setItem:(k,v)=>session.set(k,v),removeItem:k=>session.delete(k)},localStorage:{getItem:()=>stored,setItem:(k,value)=>{stored=value;writes++;}},fetch:async()=>({ok:true,json:async()=>({items})}),URL,URLSearchParams,AbortSignal,console};
    vm.runInNewContext(source,context);await new Promise(r=>setImmediate(r));
    return {links,section,events,get profile(){return JSON.parse(stored);},get writes(){return writes;},setConsent:value=>{consent=value;events['gnc:privacychange']();},replaceHistory:history=>{stored=JSON.stringify({version:1,updated:Date.now(),history,shown:{}});events.storage({key:'gnc_recommendations_v1'});}};
}
(async()=>{
    const active=await fixture({history:{computing:{visits:20,last:now,seconds:200}}});
    assert(active.links.children[0].href.endsWith('/it')||active.links.children[0].href.endsWith('/cs'),'Repeated department visits prioritize connected programs');
    assert(!active.links.children.some(a=>a.href.endsWith('/computing')),'Current page excluded');
    assert.equal(active.profile.history.computing.visits,20,'Refresh within 30 minutes does not inflate visits');
    assert(!active.section.hidden&&active.links.children.length===4,'Cards still render');
    active.replaceHistory({news:{visits:30,last:now,seconds:200}});
    assert(active.links.children.slice(0,3).some(a=>a.href.endsWith('/news')||a.href.endsWith('/announcement')),'Recent interests still influence the strongest matches alongside page context');
    const before=active.writes;active.setConsent(false);assert.equal(active.writes,before,'Revoking consent stops profile writes');assert(active.links.children[0].href.endsWith('/it')||active.links.children[0].href.endsWith('/cs'),'Consent off uses current-page context without stored interests');
    const fresh=await fixture({consent:false});assert.equal(fresh.writes,0,'No tracking without consent');
    const privateBrowser=await fixture({restricted:true});assert.equal(privateBrowser.writes,0,'Global Privacy Control prevents tracking');
    const expired=await fixture({path:'unknown',history:{computing:{visits:50,last:now-31*day,seconds:600}}});assert(expired.links.children[0].href.endsWith('/campus'),'Expired activity no longer influences suggestions');
    const session=new Map(),first=await fixture({path:'computing',session}),second=await fixture({path:'it',session});
    const ids=f=>f.links.children.map(a=>a.href).sort();assert.notDeepEqual(ids(first),ids(second),'Moving to another page changes the suggested set');
    const unseenA=await fixture({path:'unknown-a',session}),unseenB=await fixture({path:'unknown-b',session});assert.notDeepEqual(ids(unseenA),ids(unseenB),'Even pages absent from the catalog rotate a discovery card');
    const stable=ids(unseenB);unseenB.events['gnc:recommendation-profile-change']();assert.deepEqual(ids(unseenB),stable,'Profile updates do not randomly shuffle the current-page discovery card');
    console.log('PASS: department-to-program suggestions, changing interests, refresh deduplication, consent revocation, privacy signals, expiry and current-page exclusion.');
})().catch(e=>{console.error(e);process.exitCode=1;});
