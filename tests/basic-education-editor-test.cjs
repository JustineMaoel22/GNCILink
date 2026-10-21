const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
let pending, posted;
const field = { getAttribute: key => ({ 'data-ve-field': 'basic-text', 'data-ve-key': 'text_01' })[key] };
const doc = {
    getElementById: id => id === 'veBasicData' ? { textContent: JSON.stringify({ page: 'index', fields: { text_01: { value: 'Original' } } }) } : null,
    querySelector: () => null
};
const context = vm.createContext({
    $: () => null, $$: () => [field], D: { getElementById: () => ({}), addEventListener() {} }, S: { url: '/basic-edu-dept.php' }, C: {},
    makeField: (el, change) => { el.change = change; }, entityRoute: () => ({ page: 'basic-education' }),
    fieldOf: () => null, pickFile: (accept, cb) => cb({ name: 'logo.png', size: 100 }),
    showPic() {}, previewUrl: () => 'blob:test', renderToolbar() {}, chrome() {}, MB: 1024 * 1024,
    plural: (n, word) => `${n} ${word}`, window: { confirm: () => true, addEventListener() {} },
    run: (label, work) => { pending = work(); },
    sendContent: async (fields, files) => { posted = { fields, files }; return { ok: true }; },
    confirmPublish(message, done) { done(); },
    reloadEditor() {}, toast(message) { throw Error(message); }
});
vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
(async () => {
    const adapter = context.EntityAdapter();
    adapter.mount(doc);
    assert.equal(typeof field.change, 'function', 'Landing page text is editable without a department record');
    assert.equal(adapter.actions().primary.disabled, true);
    field.change(field, 'Edited heading');
    assert.equal(adapter.dirtyCount(), 1);
    assert.equal(adapter.actions().primary.disabled, false);
    adapter.runPrimary();
    await pending;
    assert.equal(posted.fields.action, 'save_basic_education_content');
    assert.equal(JSON.parse(posted.fields.texts).text_01, 'Edited heading');
    assert.equal(adapter.dirtyCount(), 0);
    const logo = { tagName: 'IMG', style: {}, getAttribute: () => '7', removeAttribute() {} };
    const selected = adapter.pick({ closest: selector => selector === '[data-ve-basic-logo-id]' ? logo : null });
    assert.equal(selected.kind, 'basic-logo');
    adapter.toolbar(selected).find(item => item.label === 'Replace logo').run();
    assert.equal(adapter.dirtyCount(), 1);
    adapter.runPrimary();
    await pending;
    assert.equal(posted.fields.action, 'save_department_media');
    assert.equal(posted.fields.dept_id, '7');
    assert.equal(posted.files.logo.name, 'logo.png');
    console.log('PASS: Basic Education landing page supports edit, dirty state, Publish, and successful-save cleanup');
})().catch(error => { console.error(error); process.exitCode = 1; });
