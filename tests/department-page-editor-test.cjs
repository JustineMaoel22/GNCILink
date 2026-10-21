const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
let pending, posted, fail = true;
const labels = { dept_id: 1, fields: { leadership_title: { value: 'Head of Office', label: 'Leadership heading', max: 255 } } };
const field = { getAttribute: name => ({ 'data-ve-field': 'dept-page-text', 'data-ve-key': 'leadership_title' })[name] };
const doc = { getElementById: id => id === 'veDeptPageData' ? { textContent: JSON.stringify(labels) } : id === 'veDeptData' ? { textContent: JSON.stringify({ dept_id: 1 }) } : null, querySelector: () => null };
const context = vm.createContext({
    $: () => null, $$: () => [field], D: { getElementById: () => ({}), addEventListener() {} }, S: { url: '/college-department.php?slug=cased' }, C: {},
    makeField: (el, change) => { el.change = change; }, entityRoute: () => ({ page: 'college-departments', sub: 'cased' }),
    renderToolbar() {}, chrome() {}, plural: (n, word) => `${n} ${word}`,
    window: { addEventListener() {} }, confirmPublish: (message, done) => done(),
    run: (message, work) => { pending = work(); },
    sendContent: async fields => { posted = fields; return fail ? { ok: false, error: 'Retry' } : { ok: true, fields: { leadership_title: { value: 'Leadership team', label: 'Leadership heading', max: 255 } } }; },
    reloadEditor() {}, toast() {}
});
vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
(async () => {
    const adapter = context.EntityAdapter(); adapter.mount(doc);
    field.change(field, 'Leadership team');
    assert.equal(adapter.dirtyCount(), 1);
    adapter.runPrimary(); await pending;
    assert.equal(posted.action, 'save_department_page_text');
    assert.equal(posted.dept_id, 1);
    assert.deepEqual(JSON.parse(posted.texts), { leadership_title: 'Leadership team' });
    assert.equal(adapter.dirtyCount(), 1, 'Failed saves keep pending text');
    fail = false; adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0, 'Publish clears saved text changes');
    field.change(field, 'Leadership team');
    assert.equal(adapter.dirtyCount(), 0, 'Editor compares subsequent edits with the refreshed saved value');
    console.log('PASS: shared department text is editable, publishes through the existing save flow and preserves failed saves for retry');
})().catch(error => { console.error(error); process.exitCode = 1; });
