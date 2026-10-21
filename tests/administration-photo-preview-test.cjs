const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
for (const kind of ['office', 'person']) {
    const file = { name: 'portrait.jpg' };
    const portrait = { src: '/portrait-before.jpg', style: {} };
    const watermark = { src: '/watermark.svg', style: {} };
    let uploadTarget;
    const selector = kind === 'office' ? '[data-ve-img="office-photo"]' : '[data-ve-img="person-photo"]';
    const selected = { kind, id: 5, el: { querySelector: query => query === selector ? portrait : query === 'img' ? watermark : null } };
    const edits = { office: {}, person: {} };
    const context = vm.createContext({ edits, pickPersonPhoto(callback, target) { uploadTarget = target; callback(file); }, previewUrl: () => 'blob:new-portrait', renderToolbar() {}, chrome() {} });
    vm.runInContext(source.slice(source.indexOf('        function photoOf(sel)'), source.indexOf('        function editOffice(sel)')), context);
    context.replacePhoto(selected);
    assert.equal(portrait.src, 'blob:new-portrait');
    assert.equal(watermark.src, '/watermark.svg', 'Decorative watermark stays unchanged');
    assert.equal(uploadTarget, portrait, 'Crop dialog measures the portrait rather than the card decoration');
    assert.equal(edits[kind][5].file, file);
    context.removePhoto(selected);
    assert.equal(portrait.style.opacity, '.2');
    assert.equal(watermark.style.opacity, undefined);
    assert.equal(edits[kind][5].remove, true);
}
console.log('PASS: office and person uploads preview in the portrait, framing targets the portrait, removal dims only the portrait and watermarks remain unchanged');
