const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
let pop, pending, posted, uploaded, reloads = 0, fail = true;
const file = { name: 'office-head.jpg' }, portrait = { src: '/old.jpg', style: {} };
const office = { id: 5, office: 'Business Office', name: 'Office Head', role: 'Accountant', contact: { phone: '123' }, services: ['Help'], image: '/old.jpg' };
const card = { ownerDocument: {}, querySelector: selector => selector === '[data-ve-img="office-photo"]' ? portrait : null, setAttribute() {} };
const selected = { kind: 'office', id: 5, el: card };
const h = (tag, props = {}, children = []) => ({ tag, ...props, children });
const context = vm.createContext({ C: { admin: { sections: [{ key: 'heads', people: [], offices: [office] }] }, page: { section: 'heads' } },
    D: { addEventListener() {} }, S: { url: '/administration.php' }, $$: () => [], h, tb: { getBoundingClientRect: () => ({ left: 0, bottom: 0 }) },
    pickPersonPhoto: callback => callback(file), previewUrl: () => 'blob:pending-photo', renderToolbar() {}, chrome() {},
    openPop: content => { pop = content; }, closePop() {}, toast() {}, clip: text => text, plural: (n, word) => `${n} ${word}`,
    confirmPublish: (message, callback) => callback(), run: (label, work) => { pending = work(); },
    sendContent: async (fields, files) => { posted = fields; uploaded = files; return fail ? { ok: false, error: 'Retry' } : { ok: true }; },
    loadFrame() { reloads++; }
});
vm.runInContext(source.slice(source.indexOf('    function AdminAdapter()'), source.indexOf('        function libraryIconPicker(')), context);
(async () => {
    const adapter = context.AdminAdapter();
    adapter.toolbar(selected).find(item => item.label === 'Photo').run();
    assert.equal(portrait.src, 'blob:pending-photo');
    adapter.toolbar(selected).find(item => item.label === 'Edit details').run();
    const fields = pop.children.filter(child => child.tag === 'label').map(label => label.children[1]);
    fields[0].value = 'Updated Business Office'; fields[3].value = '456';
    pop.children.find(child => child.class === 've-span2 ve-pop-row').children[1].on.click();
    assert.equal(reloads, 0, 'Applying details must not reload away a pending photo');
    assert.equal(posted, undefined, 'Details wait for Publish');
    assert.equal(portrait.src, 'blob:pending-photo');
    assert.equal(adapter.dirtyCount(), 1, 'Photo and details share the office pending entry');
    adapter.toolbar(selected).find(item => item.label === 'Edit details').run();
    assert.equal(pop.children.find(child => child.tag === 'label').children[1].value, 'Updated Business Office', 'Reopening details retains pending values');
    adapter.runPrimary(); await pending;
    assert.equal(posted.office_name, 'Updated Business Office'); assert.equal(posted.phone, '456');
    assert.equal(uploaded.photo, file, 'Publish submits the pending photo with the edited details');
    assert.equal(adapter.dirtyCount(), 1, 'Failed save retains the complete office edit');
    fail = false; adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0); assert.equal(reloads, 1);
    console.log('PASS: uploading then editing office details retains the photo, waits for Publish, submits both together and preserves failed saves for retry');
})().catch(error => { console.error(error); process.exitCode = 1; });
