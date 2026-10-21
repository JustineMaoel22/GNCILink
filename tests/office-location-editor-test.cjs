const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
let pop, pending, posted, fail = true;
const base = { name: 'CASED Office', building_id: 'limlingan', floor: '2', room: '202', details: '' };
const data = { dept_id: 1, can_edit_office: true, office_location: base, office_buildings: { limlingan: 'Limlingan Hall', banzali: 'Banzali Hall' } };
const card = { getAttribute: () => 'office-location', getBoundingClientRect: () => ({ left: 0, bottom: 40 }), querySelector: () => ({ textContent: '' }) };
const doc = { getElementById: id => id === 'veDeptData' ? { textContent: JSON.stringify(data) } : null, querySelector: () => null };
const context = vm.createContext({
    $: () => null, $$: () => [], D: { getElementById: () => ({}), addEventListener() {} },
    S: { url: '/college-department.php?slug=cased', doc, frame: { getBoundingClientRect: () => ({ left: 0, top: 0, height: 800 }) } }, C: {},
    h: (tag, attrs, children) => ({ tag, ...attrs, children, addEventListener() {}, focus() {} }),
    openPop: content => { pop = content; }, closePop() {}, renderToolbar() {}, chrome() {}, MB: 1024 * 1024,
    plural: (n, word) => `${n} ${word}`, window: { confirm: () => true, addEventListener() {} },
    entityRoute: () => ({ page: 'college-departments' }), fieldOf: () => null,
    run: (label, work) => { pending = work(); },
    sendContent: async fields => { posted = fields; return fail ? { ok: false, error: 'Try again' } : { ok: true, department: { ...data, office_location: JSON.parse(fields.location) } }; },
    confirmPublish(message, done) { done(); },
    reloadEditor() {}, toast() {}
});
vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
(async () => {
    const adapter = context.EntityAdapter();
    adapter.mount(doc);
    adapter.pick({ closest: selector => selector === '[data-ve-pick]' ? card : null });
    const rows = pop.children;
    const inputs = rows.slice(1, 6).map(row => row.children[1]);
    assert.equal(inputs[1].tag, 'select');
    assert.equal(inputs[1].children[2].value, 'banzali');
    inputs[0].value = 'Moved office'; inputs[1].value = 'banzali'; inputs[2].value = '3'; inputs[3].value = '301'; inputs[4].value = 'Use stairs';
    rows[6].children[0].on.click();
    assert.equal(adapter.dirtyCount(), 1);
    adapter.runPrimary(); await pending;
    assert.equal(posted.action, 'save_department_office_location');
    assert.equal(JSON.parse(posted.location).building_id, 'banzali');
    assert.equal(adapter.dirtyCount(), 1, 'Failed save preserves pending edits');
    fail = false;
    adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0, 'Successful Publish clears pending location');
    data.can_edit_office = false; adapter.mount(doc); pop = null;
    adapter.pick({ closest: selector => selector === '[data-ve-pick]' ? card : null });
    assert.equal(pop, null, 'Other roles cannot open the location editor');
    console.log('PASS: building selection, pending changes, Publish, failed-save retry and editor role access');
})().catch(error => { console.error(error); process.exitCode = 1; });
