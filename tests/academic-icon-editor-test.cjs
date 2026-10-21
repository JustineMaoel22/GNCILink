const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
async function test(page) {
    let popup, pending, request, fail = true, reloads = 0;
    const payload = { page, fields: { icon_01: { type: 'icon', value: 'svg:grad-hat-white' } }, icons: {
        'svg:grad-hat-white': { label: 'Graduation', path: '/assets/images/svg/grad-hat-white.svg' },
        'bi:book': { label: 'Book', glyph: 'book' },
        'material:science': { label: 'Science', material: 'science' }
    } };
    const doc = { getElementById: id => id === 'veBasicData' ? { textContent: JSON.stringify(payload) } : null, querySelector: () => null,
        createElement: tag => ({ tagName: tag, setAttribute() {} }) };
    const target = { getAttribute: () => 'icon_01', replaceChildren(node) { this.icon = node; } };
    const context = vm.createContext({
        $: () => null, $$: () => [], D: { getElementById: () => ({}), addEventListener() {} }, S: { url: '/', doc }, C: {},
        window: { addEventListener() {} }, document: { getElementById: () => ({}) }, fieldOf: () => null, renderToolbar() {}, chrome() {}, closePop() {},
        h: (tag, props, children) => ({ tag, props, value: props.value || '', children: children || [], focus() {}, replaceChildren(...nodes) { this.children = nodes; } }), icon: name => ({ name }),
        popBelowToolbar: content => { popup = content; }, plural: (n, word) => `${n} ${word}`,
        confirmPublish: (message, done) => done(), entityRoute: () => ({ page }), run: (label, work) => { pending = work(); },
        sendContent: async fields => { request = fields; return fail ? { ok: false, error: 'Please retry' } : { ok: true }; },
        toast() {}, reloadEditor() { reloads++; }
    });
    vm.runInContext(source.slice(source.indexOf('        function libraryIconPicker('),source.indexOf('    function EntityAdapter(')),context);
    vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
    const adapter = context.EntityAdapter(); adapter.mount(doc);
    const selected = adapter.pick({ closest: selector => selector === '[data-ve-icon="academic-feature"]' ? target : null });
    assert.equal(selected.kind, 'academic-icon');
    const open = () => adapter.toolbar(selected).find(item => item.label === 'Change icon').run();
    const grid = () => popup.children.find(item => item.props.class.includes('ve-academic-icon-grid'));
    const button = label => grid().children.find(item => item.props.title.startsWith(label + ' ('));
    open(); assert.equal(adapter.dirtyCount(), 0, 'Opening the picker does not change content');
    button('Graduation').props.on.click(); assert.equal(adapter.dirtyCount(), 0, 'Choosing the current icon keeps the page clean');
    open(); button('Book').props.on.click();
    assert.equal(target.icon.className, 'bi bi-book', 'The chosen icon appears immediately');
    assert.equal(adapter.dirtyCount(), 1);
    adapter.runPrimary(); await pending;
    assert.equal(request.page, page); assert.equal(JSON.parse(request.texts).icon_01, 'bi:book');
    assert.equal(adapter.dirtyCount(), 1, 'Failed saves keep the icon pending');
    assert.equal(reloads, 0);
    fail = false; adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0); assert.equal(reloads, 1);
    open(); button('Book').props.on.click(); assert.equal(adapter.dirtyCount(), 0, 'Saved selection becomes the new baseline');
    open(); button('Graduation').props.on.click(); assert.equal(adapter.dirtyCount(), 1, 'Original artwork can be restored');
    open();
    const search = popup.children.find(item => item.props.type === 'search');
    const family = popup.children.find(item => item.tag === 'select');
    search.value = 'science'; search.props.on.input();
    assert.equal(grid().children.length, 1, 'Search finds Material Symbols');
    family.value = 'bi'; family.props.on.change();
    assert.equal(grid().children.length, 0, 'Library filter applies alongside search');
    family.value = 'material'; family.props.on.change();
    button('Science').props.on.click();
    assert.equal(target.icon.className, 'material-symbols-outlined');
    assert.equal(target.icon.textContent, 'science', 'Material Symbols update the live preview');
    adapter.runPrimary(); await pending;
    assert.equal(JSON.parse(request.texts).icon_01, 'material:science', 'Material Symbols publish through the same save flow');
}
async function testBadge() {
    let popup, pending, request, fail = true;
    const choices = { 'material:school': { label: 'School', material: 'school' }, 'bi:book': { label: 'Book', glyph: 'book' } };
    const program = { program_id: 1, items: { badge: [{ item_id: 10, title: '4 Years', subtitle: 'Program', body: '', icon: 'material:school', letter: '' }] }, fields: {}, content: {}, icons: choices };
    const glyph = { classList: ['material-symbols-outlined', 'badge-icon'], className: 'material-symbols-outlined badge-icon', textContent: 'school' };
    const card = { getAttribute: () => 'badge:0', closest: () => card, querySelector: () => glyph };
    const doc = { getElementById: id => id === 'veProgData' ? { textContent: JSON.stringify(program) } : null, querySelector: () => null };
    const context = vm.createContext({
        $: () => null, $$: () => [], D: { getElementById: () => ({}), addEventListener() {} }, S: { url: '/', doc, sel: { el: card } }, C: {},
        window: { addEventListener() {} }, document: { getElementById: () => ({}) }, fieldOf: () => null, renderToolbar() {}, chrome() {}, closePop() {},
        h: (tag, props, children) => ({ tag, props, value: props.value || '', children: (children || []).filter(Boolean), focus() {}, replaceChildren(...nodes) { this.children = nodes; } }), icon: name => ({ name }),
        popBelowToolbar: content => { popup = content; }, plural: (n, word) => `${n} ${word}`,
        confirmPublish: (message, done) => done(), entityRoute: () => ({ page: 'programs' }), run: (label, work) => { pending = work(); },
        send: async (url, fields) => { request = fields; return fail ? { ok: false, error: 'Retry' } : { ok: true }; }, toast() {}, reloadEditor() {}
    });
    vm.runInContext(source.slice(source.indexOf('        function libraryIconPicker('),source.indexOf('    function EntityAdapter(')),context);
    vm.runInContext(source.slice(source.indexOf('    function EntityAdapter()'), source.indexOf('    function FeedAdapter()')), context);
    const adapter = context.EntityAdapter(); adapter.mount(doc);
    const selected = adapter.pick({ closest: selector => selector === '[data-ve-item]' ? card : null });
    adapter.toolbar(selected).find(item => item.title === 'Change the icon').run();
    const family = popup.children.find(item => item.tag === 'select');
    assert.deepEqual(Array.from(family.children, item => item.props.value), ['', 'material', 'bi'], 'Badge picker offers both libraries');
    family.value = 'bi'; family.props.on.change();
    const grid = popup.children.find(item => item.props.class.includes('ve-academic-icon-grid'));
    grid.children.find(item => item.props.title === 'Book (Bootstrap Icons)').props.on.click();
    assert.equal(glyph.className.trim(), 'bi bi-book badge-icon', 'Switching libraries preserves badge styling');
    assert.equal(glyph.textContent, '');
    assert.equal(adapter.dirtyCount(), 1);
    adapter.runPrimary(); await pending;
    assert.equal(request.action, 'save_program_items'); assert.equal(request.item_type, 'badge');
    assert.equal(JSON.parse(request.items)[0].icon, 'bi:book');
    assert.equal(adapter.dirtyCount(), 1, 'Failed badge saves retain the chosen icon');
    fail = false; adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0);
}
(async () => {
    await test('index'); await test('college-index'); await testBadge();
    console.log('PASS: both listings search and filter Bootstrap and Material icons, preview and publish selections, preserve failed saves and restore original icons');
    console.log('PASS: program badges share the searchable library picker and publish Bootstrap icon changes without losing badge styling');
})().catch(error => { console.error(error); process.exitCode = 1; });
