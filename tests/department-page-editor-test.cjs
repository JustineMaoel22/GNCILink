const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
let pending, posted, postedFiles, uploadTarget, fail = true;
const bannerLayer = { getBoundingClientRect: () => ({ width: 1440, height: 560 }) };
const labels = { dept_id: 1, fields: { leadership_title: { value: 'Head of Office', label: 'Leadership heading', max: 255 } } };
const field = { getAttribute: name => ({ 'data-ve-field': 'dept-page-text', 'data-ve-key': 'leadership_title' })[name] };
const doc = { getElementById: id => id === 'veDeptPageData' ? { textContent: JSON.stringify(labels) } : id === 'veDeptData' ? { textContent: JSON.stringify({ dept_id: 1 }) } : null, querySelector: selector => selector === '[data-dept-banner-image]' ? bannerLayer : null };
const context = vm.createContext({
    $: () => null, $$: () => [field], D: { getElementById: () => ({}), addEventListener() {} }, S: { url: '/college-department.php?slug=cased' }, C: {},
    makeField: (el, change) => { el.change = change; }, entityRoute: () => ({ page: 'college-departments', sub: 'cased' }),
    renderToolbar() {}, chrome() {}, plural: (n, word) => `${n} ${word}`,
    window: { addEventListener() {} }, confirmPublish: (message, done) => done(),
    run: (message, work) => { pending = work(); },
    sendContent: async (fields, files) => { posted = fields; postedFiles = files; return fail ? { ok: false, error: 'Retry' } : { ok: true, fields: { leadership_title: { value: 'Leadership team', label: 'Leadership heading', max: 255 } } }; },
    reloadEditor() {}, toast() {}
});
context.pickFile = (accept, callback, personPhoto, target) => { uploadTarget = target; };
vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
(async () => {
    const adapter = context.EntityAdapter(); adapter.mount(doc);
    const bannerTools = adapter.toolbar({kind: 'dimg', which: 'dept-banner', el: {ownerDocument: doc}});
    bannerTools.find(tool => tool.primary).run();
    assert.equal(uploadTarget, bannerLayer, 'Banner crop frame uses the full header, even when selected from the small add-background control');
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
    const banner = { name: 'department-header.png' };
    adapter.draftState().dFiles.banner = banner;
    fail = true; adapter.runPrimary(); await pending;
    assert.equal(posted.action, 'save_department_banner');
    assert.equal(postedFiles.banner, banner, 'Banner file is sent independently of the department logo');
    assert.equal(adapter.dirtyCount(), 1, 'Failed banner upload remains pending');
    fail = false; adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0, 'Successful banner upload clears pending changes');
    adapter.draftState().dRemove.banner = true;
    adapter.runPrimary(); await pending;
    assert.equal(posted.action, 'save_department_banner');
    assert.equal(posted.remove_banner, '1');
    assert.equal(adapter.dirtyCount(), 0, 'Successful removal clears pending changes');
    console.log('PASS: shared department text is editable, publishes through the existing save flow and preserves failed saves for retry');
})().catch(error => { console.error(error); process.exitCode = 1; });
