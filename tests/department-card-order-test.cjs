const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
class Node {
    constructor(tag,attrs={}){this.tag=tag;this.attrs={...attrs};this.children=[];this.parentNode=null;this.style={};this.names=new Set();this.classList={add:n=>this.names.add(n),remove:n=>this.names.delete(n),contains:n=>this.names.has(n)};}
    get attributes(){return Object.entries(this.attrs).map(([name,value])=>({name,value}));}
    set className(v){this.names=new Set(v.split(' '));} get className(){return [...this.names].join(' ');}
    getAttribute(k){return this.attrs[k]??null;}setAttribute(k,v){this.attrs[k]=String(v);}removeAttribute(k){delete this.attrs[k];}
    matches(s){return s.split(',').some(q=>{q=q.trim();return q==='*'||(q[0]==='['?Object.hasOwn(this.attrs,q.slice(1,-1)):this.tag===q);});}
    closest(s){for(let n=this;n;n=n.parentNode)if(n.matches(s))return n;return null;}
    querySelectorAll(s){return this.children.flatMap(n=>[...(n.matches(s)?[n]:[]),...n.querySelectorAll(s)]);}
    querySelector(s){return this.querySelectorAll(s)[0]||null;}
    insertBefore(n,before){n.remove();const i=before?this.children.indexOf(before):this.children.length;assert(i>=0);this.children.splice(i,0,n);n.parentNode=this;return n;}
    appendChild(n){return this.insertBefore(n,null);}remove(){if(this.parentNode){this.parentNode.children.splice(this.parentNode.children.indexOf(this),1);this.parentNode=null;}}
    cloneNode(deep){const n=new Node(this.tag,this.attrs);n.className=this.className;if(deep)this.children.forEach(c=>n.appendChild(c.cloneNode(true)));return n;}
    getBoundingClientRect(){return {left:100,top:100,width:250,height:300};}setPointerCapture(){}releasePointerCapture(){}focus(){this.focused=true;}
}
function fixture(scope){
    let timer=null,now=1000,changed=0,started=0,announced=0;const handlers=new Map(),winHandlers=new Map(),status={preview:false,busy:false};
    const body=new Node('body'),html=new Node('html'),grid=body.appendChild(new Node('div',{'data-ve-department-grid':scope}));
    const one=grid.appendChild(new Node('article',{'data-ve-department-card':'1'})),two=grid.appendChild(new Node('article',{'data-ve-department-card':'2'})),three=grid.appendChild(new Node('article',{'data-ve-department-card':'3'}));
    const view={innerHeight:1000,getSelection:()=>({removeAllRanges(){}}),scrollBy(){},addEventListener:(n,f)=>winHandlers.set(n,f),removeEventListener:n=>winHandlers.delete(n)};
    const doc={body,documentElement:html,defaultView:view,querySelector:s=>body.querySelector(s),createElement:t=>new Node(t),createComment:()=>new Node('#comment'),elementFromPoint:()=>doc.hit,addEventListener:(n,f)=>handlers.set(n,f),removeEventListener:n=>handlers.delete(n)};
    const context=vm.createContext({module:{exports:{}},setTimeout(fn,ms){assert.equal(ms,350);timer=fn;return 1;},clearTimeout(){timer=null;},Date:{now:()=>now}});
    vm.runInContext(fs.readFileSync('assets/js/department-card-order.js','utf8'),context);
    const api=context.module.exports.create(doc,{state:()=>status,start(){started++;},changed(){changed++;},announce(){announced++;}});
    const emit=(name,target,extra={})=>{const event={target,button:0,pointerId:1,pointerType:'mouse',clientX:120,clientY:130,preventDefault(){this.prevented=true;},...extra};handlers.get(name)?.(event);return event;};
    return {api,doc,status,one,two,three,grid,html,body,handlers,winHandlers,emit,hold(){const fn=timer;timer=null;fn?.();},advance(){now+=500;},get counts(){return {changed,started,announced};}};
}
for(const scope of ['college','basic-education']){
    const f=fixture(scope),handle=f.one.querySelector('[data-ve-department-drag]');
    assert.equal(f.api.scope,scope);assert(!f.api.dirty());
    f.emit('pointerdown',f.one);f.emit('pointerup',f.one);f.hold();assert(!f.api.consumeClick(),'Quick clicks still open departments');assert.equal(f.counts.started,0);
    f.emit('pointerdown',handle);f.hold();assert(f.api.consumeClick());assert(f.html.classList.contains('ve-dragging'));
    f.doc.hit=f.three;f.emit('pointermove',handle,{clientX:600});assert(f.three.classList.contains('ve-drag-target'));
    f.emit('pointerup',handle);assert.deepEqual(Array.from(f.api.order()),[3,2,1]);assert(f.api.dirty());assert.equal(f.counts.changed,1);assert(f.api.consumeClick(),'Drop suppresses accidental navigation');
    f.advance();assert(!f.api.consumeClick());
    f.emit('pointerdown',handle);f.hold();f.doc.hit=f.two;f.emit('pointermove',handle);f.emit('pointercancel',handle);assert.deepEqual(Array.from(f.api.order()),[3,2,1]);assert(!f.html.classList.contains('ve-dragging'));
    f.advance();f.emit('pointerdown',handle);f.emit('pointermove',handle,{clientX:200});f.hold();assert(!f.api.consumeClick(),'Moving before the hold threshold cancels dragging');
    f.status.preview=true;f.emit('pointerdown',handle);f.hold();assert(!f.html.classList.contains('ve-dragging'));f.status.preview=false;
    f.status.busy=true;f.emit('pointerdown',handle);f.hold();assert(!f.html.classList.contains('ve-dragging'));f.status.busy=false;
    f.emit('keydown',handle,{key:'ArrowLeft'});assert.deepEqual(Array.from(f.api.order()),[3,1,2]);assert(handle.focused);
    f.api.saved();assert(!f.api.dirty(),'Successful publishing establishes a new baseline');
    f.emit('keydown',handle,{key:'ArrowRight'});assert(f.api.dirty());f.emit('keydown',handle,{key:'ArrowLeft'});assert(!f.api.dirty(),'Reverting an order disables Publish again');
    f.emit('pointerdown',handle);f.hold();f.emit('keydown',handle,{key:'Escape'});assert(!f.html.classList.contains('ve-dragging'));
    f.api.dispose();assert.equal(f.handlers.size,0);assert.equal(f.winHandlers.size,0);assert.equal(f.grid.querySelectorAll('[data-ve-department-drag]').length,0);
}
console.log('PASS: College and Basic Education hold-to-swap, quick-click navigation, cancellation, Preview/busy guards, keyboard, publish baseline, revert and cleanup.');
