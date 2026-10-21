const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
let resolveConfirmation, pendingSave, requests = [], reloads = 0, failDelete = false, notices = [];
const program = { program_id: 1, name: 'BEEd', items: { badge: [{ item_id: 10, title: 'New badge', subtitle: '', body: '', icon: 'school', letter: '' }] }, content: { overview: 'Original text' }, fields: { overview: {} } };
const field = { getAttribute: key => ({ 'data-ve-field': 'prog-text', 'data-ve-key': 'overview' })[key] };
const doc = { getElementById: id => id === 'veProgData' ? { textContent: JSON.stringify(program) } : null, querySelector: () => null };
const card = { getAttribute: () => 'badge:0' };
const context = vm.createContext({
    $: () => null, $$: () => [field], D: { getElementById: () => ({}), addEventListener() {} }, S: { url: '/program.php?slug=beed', doc }, C: {},
    window: { addEventListener() {}, AdminDialogs: { confirm: () => new Promise(resolve => { resolveConfirmation = resolve; }) } },
    renderToolbar() {}, chrome() {}, MB: 1024 * 1024, fieldOf: () => null,
    makeField: (el, change) => { el.change = change; },
    plural: (n, word) => `${n} ${word}`, entityRoute: () => ({ page: 'programs', sub: 'beed' }),
    run: (label, work) => { pendingSave = work(); },
    send: async (url, fields) => { requests.push({ ...fields, url }); return failDelete ? { ok: false, error: 'Delete failed' } : { ok: true }; },
    clip: text => text,
    reloadEditor: () => reloads++, toast(message) { notices.push(message); }
});
vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
const flush = () => new Promise(resolve => setImmediate(resolve));
(async () => {
    const adapter = context.EntityAdapter(); adapter.mount(doc);
    const selected = adapter.pick({ closest: selector => selector === '[data-ve-item]' ? card : null });
    const remove = adapter.toolbar(selected).find(item => item.title === 'Delete this card');
    remove.run();
    assert.equal(requests.length, 0, 'Opening confirmation must not delete');
    resolveConfirmation(false); await flush();
    assert.equal(requests.length, 0, 'Cancel must not send any request');
    assert.equal(adapter.dirtyCount(), 0, 'Cancel leaves original badges intact');
    field.change(field, 'Unsaved text');
    remove.run(); resolveConfirmation(true); await flush();
    // A second confirmation protects the independently edited program text.
    resolveConfirmation(false); await flush();
    assert.equal(requests.length, 0, 'Canceling the unsaved-changes popup also prevents deletion');
    assert.equal(adapter.dirtyCount(), 1, 'Unpublished text survives canceled deletion');
    adapter.mount(doc);
    remove.run(); resolveConfirmation(true); await flush(); await pendingSave;
    assert.equal(requests.length, 1, 'Confirm sends exactly one save');
    assert.equal(requests[0].action, 'save_program_items');
    assert.equal(requests[0].item_type, 'badge');
    assert.deepEqual(JSON.parse(requests[0].items), [], 'Only confirmed deletion removes the badge');
    assert.equal(reloads, 1);
    console.log('PASS: badge deletion waits for confirmation, Cancel preserves data, and Confirm saves exactly once');
    const programCard = { getAttribute: key => ({ 'data-ve-goto': '/program.php?slug=beed', 'data-ve-program-id': '37', 'data-ve-label': 'Elementary Education' })[key] };
    const selection = adapter.pick({ closest: selector => selector === '[data-ve-goto]' ? programCard : null });
    const deleteProgram = adapter.toolbar(selection).find(item => item.title === 'Delete this program');
    assert.equal(deleteProgram.label, 'Delete program');
    assert.equal(deleteProgram.danger, true);
    deleteProgram.run(); resolveConfirmation(false); await flush();
    assert.equal(requests.length, 1, 'Canceling program deletion sends no request');
    field.change(field, 'Unsaved department text');
    deleteProgram.run(); resolveConfirmation(true); await flush();
    resolveConfirmation(false); await flush();
    assert.equal(requests.length, 1, 'Canceling the unsaved-changes guard prevents program deletion');
    assert.equal(adapter.dirtyCount(), 1, 'Canceling keeps unpublished text');
    adapter.mount(doc); failDelete = true;
    deleteProgram.run(); resolveConfirmation(true); await flush(); await pendingSave;
    assert.equal(reloads, 1, 'A failed deletion keeps the current program card');
    assert.deepEqual(notices, ['Delete failed']);
    failDelete = false;
    deleteProgram.run(); resolveConfirmation(true); await flush(); await pendingSave;
    assert.equal(requests.length, 3, 'Each confirmed deletion attempt sends one request');
    assert.equal(requests[2].url, '/admin/action/program-page-handler.php');
    assert.equal(requests[2].action, 'delete_program');
    assert.equal(requests[2].program_id, 37, 'Deletes the selected program, not another card');
    assert.equal(reloads, 2, 'Only successful deletion refreshes the department');
    const navigationOnly = { ...selection, programId: 0 };
    assert.ok(!adapter.toolbar(navigationOnly).some(item => item.danger), 'Links without a program ID have no delete action');
    console.log('PASS: program cards offer deletion, respect cancellation and unsaved changes, report failures and refresh after successful deletion');
})().catch(error => { console.error(error); process.exitCode = 1; });
