const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/photo-check.js', 'utf8');
async function check(response, allowed) {
    let handler, forwarded = 0;
    const element = () => ({ style: {}, setAttribute() {}, appendChild() {}, querySelector: () => ({ addEventListener() {}, focus() {} }) });
    const document = { head: element(), body: element(), createElement: element, addEventListener: (type, listener) => { handler = listener; } };
    const file = { size: 10 };
    const input = { tagName: 'INPUT', type: 'file', name: 'dean_photo', files: [file], value: 'photo.png', dispatchEvent() { forwarded++; } };
    vm.runInNewContext(source, { document, URL, console: { info() {}, warn() {} }, window: { location: { href: 'http://localhost/admin/content-edit.php' } },
        FormData: class { append() {} }, fetch: async () => ({ json: async () => response }), Event: class {}, setTimeout() {}, clearTimeout() {} });
    handler({ target: input, stopImmediatePropagation() {} });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(forwarded, allowed ? 1 : 0);
    assert.equal(input.value, allowed ? 'photo.png' : '');
}
(async () => {
    await check({ success: true, ok: true, checked: false, reason: 'disabled' }, true);
    await check({ success: true, ok: true, checked: true, reason: null }, true);
    await check({ success: true, ok: false, checked: true, reason: 'ai_generated' }, false);
    await check({ success: true, ok: true, checked: false, reason: 'unavailable' }, false);
    await check({ success: false, ok: true, checked: false, reason: 'disabled' }, false);
    console.log('PASS: disabled checks allow editor changes, enabled checks require approval, rejection and unavailable responses remain blocked');
})().catch(error => { console.error(error); process.exitCode = 1; });
