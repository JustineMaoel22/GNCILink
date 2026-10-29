const assert = require('node:assert/strict');
const feedback = require('../assets/js/editor-feedback.js');

// Minimal DOM fixture: exercise the shared controller without a browser or saved content.
class Element {
    constructor(tag, doc, attrs = {}) {
        this.tagName = tag.toUpperCase(); this.ownerDocument = doc; this.attrs = {...attrs};
        this.children = []; this.parentNode = null; this.style = {}; this.value = ''; this.textContent = '';
        this.hidden = false; this.offsetWidth = 90; this.offsetHeight = 24;
        this.classList = {contains: name => this.names().includes(name), toggle: (name, on) => {
            const names = new Set(this.names()); if (on) names.add(name); else names.delete(name);
            this.attrs.class = [...names].join(' ');
        }};
    }
    names() { return (this.attrs.class || '').split(/\s+/); }
    get className() { return this.attrs.class || ''; } set className(v) { this.attrs.class = v; }
    get isConnected() { return this === this.ownerDocument.body || this === this.ownerDocument.head || !!this.parentNode?.isConnected; }
    getAttribute(key) { return this.attrs[key] ?? null; }
    hasAttribute(key) { return Object.hasOwn(this.attrs, key); }
    setAttribute(key, value) { this.attrs[key] = String(value); }
    removeAttribute(key) { delete this.attrs[key]; }
    matches(selector) {
        return selector.split(',').some(s => {
            s = s.trim();
            if (s[0] === '.') return this.classList.contains(s.slice(1));
            if (s[0] === '[') return this.hasAttribute(s.slice(1, -1));
            return this.tagName.toLowerCase() === s;
        });
    }
    closest(selector) { for (let n = this; n; n = n.parentNode) if (n.matches(selector)) return n; return null; }
    querySelectorAll(selector) { return this.children.flatMap(n => [...(n.matches(selector) ? [n] : []), ...n.querySelectorAll(selector)]); }
    appendChild(node) { node.remove(); this.children.push(node); node.parentNode = this; return node; }
    remove() { if (this.parentNode) { this.parentNode.children.splice(this.parentNode.children.indexOf(this), 1); this.parentNode = null; } }
    contains(node) { return node === this || this.children.some(n => n.contains(node)); }
    getBoundingClientRect() { return {left:100,top:100,right:300,bottom:140,width:200,height:40}; }
}
function document() {
    const handlers = new Map();
    const doc = {
        defaultView: {innerWidth:1000,innerHeight:800,addEventListener(){},removeEventListener(){},requestAnimationFrame(fn){fn();return 1;},cancelAnimationFrame(){}},
        createElement(tag) { return new Element(tag, doc); },
        querySelectorAll(s) { return this.body.querySelectorAll(s); },
        querySelector() { return null; },
        addEventListener(name, fn) { handlers.set(name, fn); },
        removeEventListener(name) { handlers.delete(name); },
        emit(name, target) { handlers.get(name)?.({target}); }
    };
    doc.head = doc.createElement('head'); doc.body = doc.createElement('body'); return doc;
}
function fixture() {
    const doc = document(), ui = document(), status = {pending:0,busy:false,preview:false}; let forced = [];
    const add = (tag, attrs, parent = doc.body) => parent.appendChild(new Element(tag, doc, attrs));
    const field = add('h2', {'data-ve-field':'title'}); field.value = 'Original'; field.__ve = {field:true};
    const media = add('img', {'data-ve-img':'photo',src:'/photo.jpg'});
    const icon = add('span', {'data-ve-icon':'payment'}), glyph = add('i',{class:'bi bi-receipt'},icon);
    const grid = add('div', {'data-ve-grid':'people'});
    const one = add('article', {'data-ve-person':'1'},grid), two = add('article', {'data-ve-person':'2'},grid);
    const instance = feedback.create({document:doc,uiDocument:ui,frame:{getBoundingClientRect:()=>({left:0,top:50,width:1000,height:800})},state:()=>status,serializeText:el=>el.value,forcedTargets:()=>forced});
    return {doc,ui,status,field,media,icon,glyph,grid,one,two,add,instance,force:els=>forced=els};
}
const dirty = el => el.classList.contains('ve-feedback-dirty');
{
    const f = fixture(); assert(!dirty(f.field));
    f.field.value = 'Changed'; f.status.pending = 1; f.instance.refresh(); assert(dirty(f.field)); assert(!dirty(f.media));
    f.status.busy = true; f.instance.refresh(); assert(dirty(f.field),'Publishing keeps highlights');
    f.status.busy = false; f.instance.refresh(); assert(dirty(f.field),'Failed publishing keeps highlights');
    f.field.value = 'Original'; f.instance.refresh(); assert(!dirty(f.field),'Reverting clears highlight even with another pending change');
    f.field.value = 'Published'; f.instance.refresh(); f.status.pending = 0; f.instance.refresh(); assert(!dirty(f.field),'Successful publish resets baseline');
    f.status.pending = 1; f.instance.refresh(); assert(!dirty(f.field),'No stale baseline after publish');
    f.instance.dispose(); assert.equal(f.ui.body.children.length,0); assert.equal(f.doc.head.children.length,0);
}
{
    const f = fixture(); f.status.pending = 1;
    f.media.setAttribute('src','blob:new-image'); f.instance.refresh(); assert(dirty(f.media));
    f.glyph.className = 'bi bi-credit-card'; f.instance.refresh(); assert(dirty(f.icon),'Icon replacement is visible');
    f.media.setAttribute('src','/photo.jpg'); f.media.style.objectPosition = '20% 70%'; f.instance.refresh(); assert(dirty(f.media),'Image reposition is visible');
    f.grid.appendChild(f.one); f.instance.refresh(); assert(dirty(f.grid),'Reordering highlights the affected grid');
    const newField = f.add('p', {'data-ve-field':'new'}); newField.__ve = {field:true}; f.instance.refresh(); assert(dirty(newField),'New content is highlighted');
    newField.remove(); f.instance.refresh();
    f.force([f.two]); f.instance.refresh(); assert(dirty(f.two),'Adapter metadata changes are highlighted');
    assert(!dirty(f.grid),'Prefer changed child to duplicate ancestor outlines');
    f.instance.dispose(); assert(!dirty(f.media));
}
{
    const f = fixture(), hint = f.ui.body.children[0];
    f.doc.emit('mouseover',f.field); assert.equal(hint.textContent,'Click to edit'); assert(!hint.hidden);
    assert.equal(f.doc.body.querySelectorAll('.ve-edit-feedback-hint').length,0,'Tooltip stays outside saved canvas content');
    f.doc.emit('pointerdown',f.field); assert(hint.hidden);
    f.status.preview = true; f.doc.emit('mouseover',f.media); assert(hint.hidden,'Preview hides hints');
    f.status.preview = false; f.field.setAttribute('contenteditable','false'); f.doc.emit('mouseover',f.field); assert(hint.hidden,'Locked text has no edit hint');
    const controls = f.add('div',{'data-ve-admission-item-tools':''}); const button = f.add('button',{},controls);
    f.doc.emit('mouseover',button); assert(hint.hidden,'Editor controls have no misleading edit hints');
    f.doc.emit('mouseover',f.icon); assert(!hint.hidden,'Icons get the shared hint');
    f.status.busy = true; f.instance.refresh(); assert(hint.hidden);
    assert(f.doc.head.children[0].textContent.includes('html:not(.ve-previewing)'),'Preview hides gold outlines');
    f.instance.dispose();
}
{
    const f = fixture();
    const section = f.add('div',{'data-ve-section':'requirements'});
    const sec = {el:section,has_draft:true,dirty:false,review:'none'}; section.__ve = {sec};
    f.instance.refresh(); assert(dirty(section)); assert.equal(section.getAttribute('data-ve-feedback'),'draft');
    sec.has_draft = false; f.instance.refresh(); assert(!dirty(section),'Publishing a saved draft clears its highlight');
    const image = f.add('img',{'data-ve-img':'section-photo',src:'/photo.jpg'},section);
    f.instance.refresh(); f.status.pending = 1; image.style.objectPosition = '80% 30%'; f.instance.refresh();
    assert(dirty(image),'Crop-only changes inside otherwise clean text sections stay highlighted');
    f.instance.dispose();
}
{
    const f = fixture();
    const pdf = f.add('a',{'data-ve-fee-pdf':'college'});
    const categories = {college:{documents:[{id:1,title:'Fees',url:'/fees.pdf'}]}};
    f.doc.defaultView.GNCTuitionFees = {categories}; f.instance.refresh();
    f.status.pending = 1; categories.college.documents[0].title = 'Updated fees'; f.instance.refresh(); assert(dirty(pdf));
    const before = feedback.signature(f.icon,()=> ''); f.glyph.className += ' ve-hover is-active';
    assert.equal(feedback.signature(f.icon,()=> ''),before,'Transient editor classes do not change icon content');
    f.instance.dispose();
}
console.log('PASS: shared text, image, crop, icon, order, PDF, added-content, metadata, draft, publish/revert, hover, Preview and cleanup feedback.');
