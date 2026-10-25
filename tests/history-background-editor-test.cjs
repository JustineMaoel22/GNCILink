const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/js/content-edit.js', 'utf8');
const styles = {};
const quote = { textContent: 'Keep the closing quote', children: ['quote-left', 'quote-text', 'quote-right'], style: { setProperty(key, value) { styles[key] = value; } } };
const attrs = { 'data-ve-history-background': '', 'data-ve-default-image': '/assets/images/gnc-header.png', 'data-ve-has-image': '0' };
const button = { closest: () => quote, hasAttribute: key => Object.hasOwn(attrs, key), getAttribute: key => attrs[key], setAttribute(key, value) { attrs[key] = value; } };
const requests = [];
const revoked = [];
let response = { ok: true, image_url: '/uploads/history/new.jpg' };
const context = vm.createContext({
    C: { page: { slug: 'history' } }, S: { doc: { querySelector: () => button } },
    previewUrl: () => 'blob:quote-background',
    URL: { revokeObjectURL: url => revoked.push(url) },
    cssEsc: value => value, renderToolbar() {}, chrome() {}, toast() {},
    sendContent(data, uploads) { requests.push({ data, uploads }); return Promise.resolve(response); },
});
vm.runInContext(source.slice(source.indexOf('        var page = C.page.slug;', source.indexOf('function SectionsAdapter()')), source.indexOf('        var secs =', source.indexOf('function SectionsAdapter()'))), context);
(async () => {
    const file = { name: 'new.jpg' };
    const selected = { key: 'history_quote', el: button };
    context.stagePhoto(selected, file);
    assert.equal(styles['--history-quote-background'], 'url("blob:quote-background")');
    assert.equal(quote.textContent, 'Keep the closing quote');
    assert.deepEqual(quote.children, ['quote-left', 'quote-text', 'quote-right']);
    assert.equal(Object.keys(context.photos).length, 1);
    response = { ok: false, error: 'Try again' };
    await context.publishPhotos();
    assert.equal(Object.keys(context.photos).length, 1, 'A failed publish keeps the pending image for retry');
    response = { ok: true, image_url: '/uploads/history/new.jpg' };
    await context.publishPhotos();
    assert.equal(requests[1].data.section_key, 'history_quote');
    assert.equal(requests[1].uploads.image, file);
    assert.equal(styles['--history-quote-background'], 'url("/uploads/history/new.jpg")');
    assert.equal(button.textContent, 'Change background image');
    assert.equal(Object.keys(context.photos).length, 0);
    assert(revoked.includes('blob:quote-background'));
    context.stagePhoto(selected, null);
    assert.equal(styles['--history-quote-background'], 'url("/assets/images/gnc-header.png")');
    response = { ok: true, image_url: null };
    await context.publishPhotos();
    assert.equal(requests[2].data.remove_image, '1');
    assert.equal(attrs['data-ve-has-image'], '0');
    assert.equal(quote.textContent, 'Keep the closing quote');
    console.log('PASS: quote background preview, publish, failure/retry, restoring default and quote text preservation.');
})().catch(error => { console.error(error); process.exitCode = 1; });
