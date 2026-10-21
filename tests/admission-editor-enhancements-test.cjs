const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('assets/js/content-edit.js','utf8');
function extract(name){const start=source.indexOf('        function '+name+'(');assert(start>=0,name);const end=source.indexOf('\n        function ',start+1);return source.slice(start,end);}
class Node {
    constructor(tag,attrs={},classes=[]){this.tag=tag;this.attrs=attrs;this.children=[];this.parentNode=null;this.textContent='';this.innerHTML='';this.classList={values:new Set(classes),add:(v)=>this.classList.values.add(v),remove:(v)=>this.classList.values.delete(v),contains:(v)=>this.classList.values.has(v),toggle:(v,on)=>on?this.classList.values.add(v):this.classList.values.delete(v)};}
    getAttribute(k){return this.attrs[k]??null;} hasAttribute(k){return Object.hasOwn(this.attrs,k);} setAttribute(k,v){this.attrs[k]=v;}
    matches(selector){return selector.split(',').some(part=>{const s=part.trim();if(s==='ol > li')return this.tag==='li'&&this.parentNode?.tag==='ol';if(s[0]==='.')return this.classList.contains(s.slice(1));if(s[0]==='['){const m=s.match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);return !!m&&this.hasAttribute(m[1])&&(m[2]===undefined||this.getAttribute(m[1])===m[2]);}return this.tag===s;});}
    closest(s){for(let n=this;n;n=n.parentNode)if(n.matches(s))return n;return null;}
    querySelector(s){return this.querySelectorAll(s)[0]||null;}
    querySelectorAll(s){return this.children.flatMap(c=>[...(c.matches(s)?[c]:[]),...c.querySelectorAll(s)]);}
    insertBefore(n,ref){n.remove();const i=ref?this.children.indexOf(ref):this.children.length;assert(i>=0);this.children.splice(i,0,n);n.parentNode=this;}
    appendChild(n){this.insertBefore(n,null);return n;}
    remove(){if(this.parentNode){const p=this.parentNode;p.children.splice(p.children.indexOf(this),1);this.parentNode=null;}}
    get nextSibling(){if(!this.parentNode)return null;return this.parentNode.children[this.parentNode.children.indexOf(this)+1]||null;}
    contains(n){return n===this||this.children.some(c=>c.contains(n));}
    click(){this.clicked=(this.clicked||0)+1;} focus(){this.focused=true;} setPointerCapture(){}
    getBoundingClientRect(){return this.rect||{left:100,right:200,top:100,bottom:140};}
}
function makeDoc(root){const handlers={};return {handlers,addEventListener:(name,fn)=>(handlers[name]??=[]).push(fn),emit(name,event){event.type=name;event.preventDefault=()=>{};(handlers[name]||[]).forEach(fn=>fn(event));},elementFromPoint(){return this.hit;},getElementById(id){return [root,...root.querySelectorAll('[id]')].find(n=>n.getAttribute('id')===id)||null;},defaultView:{innerHeight:1000,scrollBy(){}}};}
// Pointer, touch/pointer cancellation, gap hit testing and keyboard reordering use the actual handlers.
{
    const scope=new Node('section',{'data-admission-scope':'college'}),tabs=scope.appendChild(new Node('div'));
    const a=tabs.appendChild(new Node('span',{},['ve-admission-category'])),b=tabs.appendChild(new Node('span',{},['ve-admission-category']));
    const handle=b.appendChild(new Node('button',{'data-ve-admission-drag-category':'b'}));
    const doc=makeDoc(scope);doc.hit=a;let changed=0,chrome=0;
    const context=vm.createContext({S:{preview:false,busy:false,sel:{}},select(){},byEl:()=>({el:{__ve:{onChange(){changed++;}}}}),sectionElOf:n=>n,locked:()=>false,markAdmissionLayout(){},chrome(){chrome++;},Math,Array});
    vm.runInContext(extract('bindAdmissionReordering'),context);context.bindAdmissionReordering(doc);
    const pointer=(target,x,y)=>({target,clientX:x,clientY:y,pointerId:1,button:0});
    doc.emit('pointerdown',pointer(handle,300,120));doc.emit('pointermove',pointer(handle,150,120));
    assert.equal(a.classList.contains('ve-admission-drop-target'),true);doc.emit('pointerup',pointer(handle,150,120));
    assert.deepEqual(tabs.children,[b,a],'Category pointer drag changes order');
    doc.emit('keydown',{target:handle,key:'ArrowRight'});assert.deepEqual(tabs.children,[a,b],'Category keyboard reordering works');
    doc.emit('pointerdown',pointer(handle,300,120));doc.emit('pointermove',pointer(handle,150,120));doc.emit('pointercancel',pointer(handle,150,120));assert.deepEqual(tabs.children,[a,b],'Cancelling a drag keeps order');
    // Gap beside the target rectangle still gives a usable drop target.
    doc.hit=tabs;doc.emit('pointerdown',pointer(handle,300,120));doc.emit('pointermove',pointer(handle,210,120));doc.emit('pointerup',pointer(handle,210,120));assert.deepEqual(tabs.children,[b,a]);
    const list=scope.appendChild(new Node('ol')),first=list.appendChild(new Node('li')),second=list.appendChild(new Node('li')),itemHandle=second.appendChild(new Node('button',{'data-ve-admission-drag-item':''}));
    doc.hit=first;doc.emit('pointerdown',pointer(itemHandle,300,120));doc.emit('pointermove',pointer(itemHandle,150,120));doc.emit('pointerup',pointer(itemHandle,150,120));
    assert.deepEqual(list.children,[second,first]);assert.equal(changed,1,'Item movement marks content dirty');
    doc.emit('keydown',{target:itemHandle,key:'ArrowDown'});assert.deepEqual(list.children,[first,second]);assert(chrome>0);
}
// Deletions retain the actual nodes and exact insertion points for Undo.
{
    const scope=new Node('section',{'data-admission-scope':'college'}),tabs=scope.appendChild(new Node('div')),panels=scope.appendChild(new Node('div'));
    const wrapper=tabs.appendChild(new Node('span',{},['ve-admission-category'])),tab=wrapper.appendChild(new Node('button',{id:'tab-a',role:'tab'}));
    const other=tabs.appendChild(new Node('span',{},['ve-admission-category']));other.appendChild(new Node('button',{role:'tab'}));
    const panel=panels.appendChild(new Node('div',{'aria-labelledby':'tab-a'},['req-panel'])),el=panel.appendChild(new Node('div',{'data-ve-section':'a'})),heading=el.appendChild(new Node('h2'));heading.textContent='Category A';
    const list=el.appendChild(new Node('ol')),first=list.appendChild(new Node('li')),second=list.appendChild(new Node('li')),button=first.appendChild(new Node('button'));
    let edits=0;const s={key:'a',title:'Category A',el,base:'',dirty:false,isNew:false};el.__ve={onChange(){edits++;s.dirty=true;}};
    let prompt;const doc=makeDoc(scope),context=vm.createContext({admissionUndo:[],secs:[s],C:{canPublish:true},S:{doc},byEl:()=>s,sectionElOf:()=>el,locked:()=>false,select(){},chrome(){},markAdmissionLayout(){},markAdmissionChanges(){},html:()=>'',askText:opts=>{prompt=opts;}});
    for(const name of ['pushAdmissionUndo','undoAdmissionDeletion','deleteAdmissionItem','deleteAdmissionCategory','renameAdmissionCategory'])vm.runInContext(extract(name),context);
    context.deleteAdmissionItem(button);assert.deepEqual(list.children,[second]);context.undoAdmissionDeletion(scope);assert.deepEqual(list.children,[first,second]);assert.equal(edits,2);
    context.deleteAdmissionCategory('a');assert.equal(panel.parentNode,null);assert.equal(s.removed,true);assert.equal(context.admissionUndo.length,1);
    context.undoAdmissionDeletion(scope);assert.equal(panel.parentNode,panels);assert.equal(tabs.children[0],wrapper);assert.equal(s.removed,false);assert.equal(s.dirty,false);
    context.renameAdmissionCategory('a',tab);assert.equal(prompt.value,'Category A');prompt.done('Renamed category');assert.equal(heading.textContent,'Renamed category');assert.equal(s.title,'Renamed category');assert.equal(s.dirty,true);
}
// Highlights use clean content and stable item identity, without rebuilding tab text while typing.
{
    const el=new Node('div',{},['admission-req-content']),heading=el.appendChild(new Node('h2'));heading.textContent='Category';
    const one=new Node('li'),two=new Node('li'),t1=one.appendChild(new Node('span',{'data-ve-admission-item-text':''})),t2=two.appendChild(new Node('span',{'data-ve-admission-item-text':''}));t1.innerHTML='First';t2.innerHTML='Second';
    let items=[one,two],tabWrites=0;const tab={getAttribute:key=>key==='data-target'?'a':null,get textContent(){return 'Category';},set textContent(v){tabWrites++;}};
    const s={key:'a',el,title:'Category',dirty:false};
    const context=vm.createContext({S:{doc:{}},$$:(selector,root)=>selector==='ol > li'?items:[tab],cleanHtml:value=>value,markAdmissionLayout(){},Map});
    for(const name of ['admissionTabKey','rememberAdmissionItems','markAdmissionChanges'])vm.runInContext(extract(name),context);
    context.rememberAdmissionItems(s);s.dirty=true;t1.innerHTML='Edited';context.markAdmissionChanges(s);assert.equal(one.classList.contains('ve-admission-item-unsaved'),true);assert.equal(two.classList.contains('ve-admission-item-unsaved'),false);
    items=[two,one];context.markAdmissionChanges(s);assert.equal(two.classList.contains('ve-admission-item-unsaved'),true);assert.equal(tabWrites,0,'Typing does not rebuild matching tab labels');
    context.rememberAdmissionItems(s);s.dirty=false;context.markAdmissionChanges(s);assert.equal(one.classList.contains('ve-admission-item-unsaved'),false);assert.equal(two.classList.contains('ve-admission-item-unsaved'),false);
}
console.log('PASS: pointer and keyboard reordering, wrapped-row gap targeting, cancelled drags, exact Undo restoration, category rename, and unsaved highlights.');
