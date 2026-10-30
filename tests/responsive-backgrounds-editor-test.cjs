const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/content-edit.js','utf8');
let model={},accepted,fail=false;const requests=[];
const api={changes:()=>model,stage:(t,value,file,device)=>{(model[t.selector]||={})[device]={value,file};},accept:settings=>{accepted=settings;model={};}};
const c=vm.createContext({window:{},S:{device:'mobile',url:'/pages/about/gnc-history.php',win:{GNCResponsiveBackgrounds:api}},chrome(){},renderToolbar(){},
    send:async(url,fields,files)=>{requests.push({url,fields,files});return fail?{ok:false,error:'Retry needed'}:{ok:true,settings:{'#banner':{mobile:{path:'/uploads/saved.png'}}}};}});
vm.runInContext(source.slice(source.indexOf('    var imagePositions = {}'),source.indexOf('    var S = {')),c);
const frame={path:'blob:preview',kind:'background',fit:'contain',x:20,y:30,zoom:1.5};
const file=new File(['image bytes'],'portrait.png',{type:'image/png'});
class Reader{readAsDataURL(blob){blob.arrayBuffer().then(data=>{this.result='data:'+blob.type+';base64,'+Buffer.from(data).toString('base64');this.onload();},()=>this.onerror());}}
const draftsContext=vm.createContext({window:{},document:{},Blob,File,FileReader:Reader,URL,WeakRef,atob,Uint8Array,fetch});
vm.runInContext(fs.readFileSync('assets/js/editor-drafts.js','utf8'),draftsContext);
(async()=>{
    c.stageResponsiveBackground({selector:'#banner'},frame,file,'mobile');
    c.stageResponsiveBackground({selector:'#banner'},null,null,'tablet');
    assert.equal(requests.length,0,'Preview never posts live data');assert.equal(c.backgroundChangeCount(),2);
    // Check binary draft packing, source-URL remapping, and exact approval state round trip.
    const url=URL.createObjectURL(file);draftsContext.window.GNCEditorDrafts.trackUrl(url,file);
    const snapshot={version:1,state:{responsiveBackgrounds:{'#banner':{mobile:{value:{...frame,path:url},file},tablet:{value:null,file:null}}}},preview:{}};
    const encoded=await draftsContext.window.GNCEditorDrafts.encode(snapshot);
    const decoded=draftsContext.window.GNCEditorDrafts.decode(JSON.parse(encoded));
    const entry=decoded.state.responsiveBackgrounds['#banner'];assert.equal(await entry.mobile.file.text(),'image bytes');assert.equal(entry.mobile.value.x,20);assert.equal(entry.tablet.value,null);assert(entry.mobile.value.path.startsWith('blob:'));
    URL.revokeObjectURL(url);URL.revokeObjectURL(entry.mobile.value.path);
    fail=true;await assert.rejects(c.publishResponsiveBackgrounds(),/Retry needed/);assert.equal(c.backgroundChangeCount(),2,'Failed publish retains device edits');
    fail=false;await c.publishResponsiveBackgrounds();
    const r=requests.at(-1),changes=JSON.parse(r.fields.changes);assert.equal(changes['#banner'].mobile.upload,'background_0');assert.equal(changes['#banner'].mobile.path,'');assert.equal(changes['#banner'].tablet,null);assert.equal(changes['#banner'].desktop,undefined);assert.equal(r.files.background_0,file);assert.equal(c.backgroundChangeCount(),0);assert(accepted['#banner'].mobile);
    console.log('PASS: private device preview, binary draft restoration, reset state, publish payload, retry and unrelated devices preserved.');
})().catch(e=>{console.error(e);process.exitCode=1;});
