const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
const framing = fs.readFileSync('assets/js/image-framing.js', 'utf8');
const updates = [], requests = [], messages = [];
let confirmation, fail = false, otherChanges = 0, otherPublishes = 0;
const context = vm.createContext({
    C: {}, drafts: null,
    window: { GNCImageFraming: { set: (path, value) => updates.push({ path, value }) } },
    S: { busy: false, win: { GNCImageFraming: { set: (path, value) => updates.push({ path, value }) } } },
    same: (a, b) => JSON.stringify(a) === JSON.stringify(b),
    plural: (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`,
    chrome() {}, setStatus() {}, console: { error() {} },
    toast: message => messages.push(message),
    confirmPublish: (message, accept) => { confirmation = accept; },
    send: async (url, fields) => { requests.push({ url, fields }); return fail ? { ok: false, error: 'Position save failed' } : { ok: true }; }
});
vm.runInContext(source.slice(source.indexOf('    var imagePositions = {}'), source.indexOf('    var S = {')), context);
vm.runInContext(source.slice(source.indexOf('    function run(label, fn)'), source.indexOf('    function shortcutSave()')), context);
vm.runInContext(source.slice(source.indexOf('    function withImagePositions(adapter)'), source.indexOf('    withImagePositions(S.adapter);')), context);
const adapter = {
    dirtyCount: () => otherChanges,
    status: () => ({ text: 'Everything here is live', tone: 'ok' }),
    actions: () => ({ primary: { disabled: otherChanges === 0 }, secondary: { disabled: false } }),
    runPrimary: () => context.run('Publishing…', () => { otherPublishes++; otherChanges = 0; })
};
context.withImagePositions(adapter);
const path = '/uploads/administration/portrait.jpg';
const base = { x: 50, y: 50, zoom: 1 }, moved = { x: 50, y: 20, zoom: 1 };
const stage = (value = moved) => context.window.VEImageFraming.stage(path, value, base);

(async () => {
    stage();
    assert.equal(requests.length, 0, 'Accepting a crop must not write to the server');
    assert.equal(updates.length, 2, 'Parent and iframe preview both receive the crop');
    assert.equal(adapter.dirtyCount(), 1);
    assert.equal(adapter.actions().primary.disabled, false, 'Crop alone enables Publish');
    assert.equal(adapter.actions().secondary.disabled, true, 'Crop cannot be saved through a text-only draft action');
    assert.equal(adapter.status().tone, 'warn');
    stage(base);
    assert.equal(adapter.dirtyCount(), 0, 'Returning to the original position removes the pending change');
    stage(); stage({ x: 50, y: 10, zoom: 1 });
    assert.equal(adapter.dirtyCount(), 1, 'Repeated positioning of one photo counts as one change');
    adapter.runPrimary();
    assert.equal(requests.length, 0, 'Publish waits for its confirmation');
    confirmation();
    while (context.S.busy) await new Promise(resolve => setImmediate(resolve));
    assert.equal(requests.length, 1);
    assert.equal(JSON.parse(requests[0].fields.framing).y, 10);
    assert.equal(adapter.dirtyCount(), 0);
    assert.equal(adapter.actions().primary.disabled, true);
    stage(); fail = true;
    adapter.runPrimary(); confirmation();
    while (context.S.busy) await new Promise(resolve => setImmediate(resolve));
    assert.equal(adapter.dirtyCount(), 1, 'Failed saves remain pending and can be retried');
    assert.equal(messages.at(-1), 'Position save failed');
    fail = false; otherChanges = 1;
    assert.equal(adapter.dirtyCount(), 2);
    await adapter.runPrimary();
    assert.equal(otherPublishes, 1, 'Combined crop and content edits both publish');
    assert.equal(adapter.dirtyCount(), 0);
    stage(); context.discardImagePositions();
    assert.equal(adapter.dirtyCount(), 0);
    assert.equal(updates.at(-1).value.y, 50, 'Discard restores the original preview position');

    // Verify that framing dialogs in the iframe use the parent editor bridge.
    const child = { parent: context.window };
    context.child = child;
    const lookup = vm.createContext({ window: child });
    vm.runInContext(framing.slice(framing.indexOf('    function visualEditor()'), framing.indexOf('    async function dialog(')), lookup);
    assert.equal(lookup.visualEditor(), context.window.VEImageFraming);
    assert.match(framing, /else if\(editor\)\{editor\.stage\(/, 'Editor crop dialog stages positions instead of posting them');
    console.log('PASS: crop-only and combined publishing, confirmation, retry, discard, unchanged crops, and iframe integration.');
})().catch(error => { console.error(error); process.exitCode = 1; });
