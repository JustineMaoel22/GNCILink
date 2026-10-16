const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Exercise the real visual-editor picker and photo-check capture listener together.
let capture, response, previewCount = 0, requests = 0;
function element() {
    return { style: {}, setAttribute() {}, focus() {}, addEventListener() {},
        querySelector() { return element(); },
        appendChild(child) { child.parentNode = this; },
        removeChild(child) { child.parentNode = null; } };
}
const input = {
    tagName: 'INPUT', type: 'file', id: 'veFile', name: '', files: [], attrs: {},
    setAttribute(k, v) { this.attrs[k] = v; },
    removeAttribute(k) { delete this.attrs[k]; },
    hasAttribute(k) { return k in this.attrs; },
    click() {},
    dispatchEvent() {
        let stopped = false;
        capture({ target: this, stopImmediatePropagation() { stopped = true; } });
        if (!stopped && this.onchange) this.onchange();
    }
};
Object.defineProperty(input, 'value', { set(v) { if (v === '') this.files = []; } });
const context = vm.createContext({
    console, URL, Event, fileInput: input,
    window: { location: { href: 'http://localhost:8080/admin/content-edit.php?page=administration' }, CE_CONFIG: { csrf: 'test' } },
    document: { head: element(), body: element(), createElement: element, addEventListener(type, cb) { if (type === 'change') capture = cb; } },
    FormData: class { append() {} },
    fetch: async () => { requests++; return { json: async () => response }; },
    setTimeout() {}, clearTimeout() {}
});
const editor = fs.readFileSync('assets/js/content-edit.js', 'utf8');
vm.runInContext(editor.slice(editor.indexOf('    function pickFile('), editor.indexOf('    function previewUrl(')), context);
vm.runInContext(fs.readFileSync('assets/js/photo-check.js', 'utf8'), context);
async function choose(result) {
    response = result;
    context.pickPersonPhoto(() => previewCount++);
    input.files = [{ name: 'test.png', size: 100 }];
    input.dispatchEvent();
    await new Promise(setImmediate);
}
(async () => {
    await choose({ success: true, checked: true, ok: false, reason: 'not_person' });
    assert.equal(previewCount, 0, 'Non-person must never reach the preview callback');
    assert.equal(input.files.length, 0, 'Rejected file must be cleared');
    await choose({ success: true, checked: false, ok: false, reason: 'unavailable' });
    assert.equal(previewCount, 0, 'Unavailable checks must not preview');
    await choose({ success: true, checked: true, ok: true });
    assert.equal(previewCount, 1, 'Accepted portrait previews exactly once');
    const previousRequests = requests;
    context.pickFile('image/*', () => previewCount++);
    input.files = [{ name: 'logo.png', size: 100 }];
    input.dispatchEvent();
    assert.equal(requests, previousRequests, 'Ordinary images must not receive a person check');
    assert.equal(previewCount, 2);
    assert.equal((editor.match(/pickPersonPhoto\(function/g) || []).length, 4, 'All administration and president entry points use validation');
    assert.match(editor, /}, !logo\);/, 'Dean photos are checked while department logos are exempt');
    console.log('PASS: visual editor blocks rejected/unavailable photos, previews accepted portraits, and exempts ordinary images');
})().catch(error => { console.error(error); process.exitCode = 1; });
