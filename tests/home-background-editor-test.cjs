const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8').replace(/\r\n/g, '\n');
const start = source.indexOf('    function HomeAdapter()');
const end = source.indexOf('\n    }\n', start) + '\n    }\n'.length;

async function check(key, initial) {
    let picker, pending, sent, fail = true, reloads = 0;
    const original = key === 'news_background' ? 'assets/images/DSC_2965.JPG' : 'assets/images/classroom.JPG';
    let content = { [key]: { type: 'image', value: initial || original, default: original, label: 'Section background' } };
    const area = { style: { setProperty(name, value) { this[name] = value; } } };
    const attrs = { 'data-ve-key': key };
    const button = {
        getAttribute: name => attrs[name], setAttribute: (name, value) => { attrs[name] = value; },
        closest: selector => selector === '[data-ve-img="home-background"]' ? button : selector === 'section' ? area : null
    };
    const context = vm.createContext({
        C: { home: { slides: [], president: {}, content } }, S: { url: '/' }, $: () => null,
        normMsg: value => value, fieldOf: () => null, renderToolbar() {}, chrome() {},
        pickFile: (...args) => { picker = args; }, previewUrl: () => 'blob:new-background',
        confirmPublish: (_, done) => done(), run: (_, work) => { pending = work(); },
        toast() {}, plural: (n, word) => `${n} ${word}`,
        sendContent: async (fields, files) => {
            sent = { fields, files };
            if (fail) return { ok: false, error: 'Retry' };
            const reset = JSON.parse(fields.reset_images).includes(key);
            content = { [key]: { ...content[key], value: reset ? original : 'uploads/new.jpg' } };
            return { ok: true, content };
        },
        fetch: async () => ({ json: async () => ({ slides: [] }) }),
        loadFrame: () => { reloads++; }
    });
    vm.runInContext(source.slice(start, end), context);
    const adapter = context.HomeAdapter();
    const selection = adapter.pick(button);
    assert.equal(selection.area, area);
    assert.equal(adapter.dirtyCount(), 0);
    const change = () => adapter.toolbar(selection).find(item => item.label === 'Change background image').run();
    const reset = () => adapter.toolbar(selection).find(item => item.label === 'Use original').run();
    change();
    assert.equal(adapter.dirtyCount(), 0, 'Opening the picker makes no change');
    assert.equal(picker[3], area, 'Picker targets the section, not its small control');
    const file = { name: 'background.jpg' };
    picker[1](file);
    assert.equal(area.style['--home-section-background'], 'url("blob:new-background")');
    assert.equal(attrs['data-ve-background-value'], 'blob:new-background', 'Unsaved feedback sees the new background');
    assert.equal(adapter.dirtyCount(), 1);
    assert.equal(adapter.actions().primary.disabled, false);
    reset();
    assert.equal(adapter.dirtyCount(), initial ? 1 : 0, 'Restoring an unchanged default clears pending work');
    change(); picker[1](file);
    adapter.runPrimary(); await pending;
    assert.equal(sent.fields.action, 'save_home_content');
    assert.equal(sent.files['img_' + key], file);
    assert.equal(adapter.dirtyCount(), 1, 'Failed save retains the selected upload');
    fail = false;
    adapter.runPrimary(); await pending;
    assert.equal(adapter.dirtyCount(), 0);
    assert.equal(reloads, 1);
    reset();
    adapter.runPrimary(); await pending;
    assert.deepEqual(JSON.parse(sent.fields.reset_images), [key]);
    assert.equal(adapter.dirtyCount(), 0, 'Saved reset becomes the new baseline');
}
(async () => {
    await check('news_background');
    await check('programs_background', 'uploads/saved.jpg');
    const page = fs.readFileSync('index.php', 'utf8');
    const css = fs.readFileSync('assets/css/index-style.css', 'utf8');
    for (const key of ['news_background', 'programs_background']) {
        assert.ok(page.includes(`data-ve-key="${key}"`));
        assert.ok(page.includes(`homeText('${key}')`));
    }
    assert.ok(css.includes('html.ve-previewing .home-background-tools { display: none; }'));
    console.log('Homepage background editing checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
