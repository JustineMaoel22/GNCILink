const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
async function check(page) {
    let pending, posted, uploaded, fail = true, reloads = 0;
    const data = { page, fields: {} };
    const doc = { getElementById: id => id === 'veBasicData' ? { textContent: JSON.stringify(data) } : null, querySelector: () => null };
    const file = { size: 100, name: 'new-logo.svg' };
    const image = { tagName: 'IMG', style: {}, removeAttribute() {}, getAttribute: () => '7' };
    const context = vm.createContext({
        $: () => null, $$: () => [], D: { getElementById: () => ({}), addEventListener() {} },
        S: { url: '/', doc }, C: {}, window: { addEventListener() {} }, MB: 1024 * 1024,
        fieldOf: () => null, renderToolbar() {}, chrome() {}, bindEditing() {},
        pickFile: (accept, callback) => { assert.ok(accept.includes('svg')); callback(file); }, previewUrl: () => 'blob:preview-logo',
        entityRoute: () => ({ page: 'college-departments', sub: '' }), plural: (n, word) => `${n} ${word}`,
        confirmPublish(message, callback) { callback(); }, run(label, work) { pending = work(); },
        sendContent: async (fields, files) => { posted = fields; uploaded = files; return fail ? { ok: false, error: 'Retry' } : { ok: true }; },
        reloadEditor() { reloads++; }, toast() {}
    });
    vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
    const adapter = context.EntityAdapter();
    adapter.mount(doc);
    const selected = adapter.pick({ closest: selector => selector === '[data-ve-basic-logo-id]' ? image : null });
    assert.equal(selected.kind, 'basic-logo');
    assert.equal(selected.prevent, true, 'Logo click edits instead of following the department link');
    adapter.toolbar(selected).find(action => action.label === 'Replace logo').run();
    assert.equal(image.src, 'blob:preview-logo');
    assert.equal(adapter.dirtyCount(), 1);
    adapter.runPrimary(); await pending;
    assert.equal(posted.action, 'save_department_media');
    assert.equal(posted.dept_id, '7', 'Saves to the department record shared by listing and detail page');
    assert.equal(uploaded.logo, file);
    assert.equal(adapter.dirtyCount(), 1, 'Failed upload remains pending');
    fail = false;
    adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0);
    assert.equal(reloads, 1);
}
(async () => {
    await check('college-index');
    await check('index');
    console.log('PASS: college and Basic Education listing logos select, preview and publish to the shared department record; failed uploads remain pending');
})().catch(error => { console.error(error); process.exitCode = 1; });
