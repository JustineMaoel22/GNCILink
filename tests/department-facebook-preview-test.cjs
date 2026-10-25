const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
class Element {
    constructor(tag) { this.tag = tag; this.attrs = {}; this.style = {}; this.children = []; }
    setAttribute(key, value) { this.attrs[key] = value; }
    appendChild(child) { child.parent = this; this.children.push(child); }
    closest() { return this.parent?.className === 'dept-fb-box' ? this.parent : null; }
    replaceWith(next) { const parent = this.parent; parent.children[parent.children.indexOf(this)] = next; next.parent = parent; }
}
const root = new Element('section');
const oldBox = new Element('div'); oldBox.className = 'dept-fb-box';
const oldFrame = new Element('iframe'); oldFrame.src = 'old-page'; oldBox.appendChild(oldFrame);
const oldPicker = new Element('div'); oldPicker.setAttribute('data-ve-pick', 'dept-facebook'); oldBox.appendChild(oldPicker); root.appendChild(oldBox);
const pending = {};
let apply;
const context = vm.createContext({
    S: { doc: { createElement: tag => new Element(tag), querySelector: () => {
        const box = root.children[0]; return box.className === 'dept-fb-box' ? box.children[1] : box;
    } } }, DD: {},
    dVal: key => pending[key] || (key === 'dept_name' ? 'College of Business Administration' : 'old-page'),
    dSet: (key, value) => { pending[key] = value; },
    formPop: (el, title, fields, label, done) => { apply = done; },
    renderToolbar() {}, chrome() {}, toast() {},
});
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
vm.runInContext(source.slice(source.indexOf('        function previewDepartmentFacebook('), source.indexOf('        function academicIconPicker(')), context);
function setLink(url) { context.editFacebook(); apply({ url }); }
setLink('  https://www.facebook.com/GNC.CBA  ');
assert.equal(pending.facebook_url, 'https://www.facebook.com/GNC.CBA');
assert.equal(new URL(root.children[0].children[0].src).searchParams.get('href'), pending.facebook_url, 'Apply refreshes the preview before publishing');
setLink('https://www.facebook.com/another-page');
assert.equal(new URL(root.children[0].children[0].src).searchParams.get('href'), pending.facebook_url, 'Repeated edits update the current frame');
setLink('');
assert.equal(root.children[0].className, 've-add-tile', 'Removing the link previews the add placeholder');
setLink('https://www.facebook.com/restored-page');
assert.equal(root.children[0].className, 'dept-fb-box', 'A page can be added again without reloading');
const before = root.children[0];
setLink('https://example.com/invalid');
assert.equal(root.children[0], before, 'Invalid URLs leave the preview unchanged');
assert.equal(pending.facebook_url, 'https://www.facebook.com/restored-page');
console.log('PASS: Apply previews changed Facebook links; removal, re-adding, repeated edits, URL trimming and validation work without publishing.');
