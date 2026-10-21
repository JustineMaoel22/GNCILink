const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const editor = fs.readFileSync('assets/js/content-edit.js', 'utf8');
const handlers = [];
let visualDirty = 0, drawerDirty = false, navigations = 0, previewRefreshes = 0;
const document = { querySelector: selector => drawerDirty && selector.includes('.dept-media-card') ? {} : null };
const context = vm.createContext({
    document, D: document, S: { url: '/pages/academics/college/college-department.php?slug=cased', adapter: { dirtyCount: () => visualDirty } },
    window: { addEventListener: (name, handler) => handlers.push(handler), location: { pathname: '/admin/content-edit.php', reload: () => navigations++ } },
    card: { dataset: { dirty: '0' } },
    loadFrame: () => previewRefreshes++, toast() {}, toastNextLoad() {}
});
Object.defineProperty(context.window.location, 'href', { set() { navigations++; } });
vm.runInContext(editor.slice(editor.indexOf('    var leaving = false;'), editor.indexOf("    D.addEventListener('click', async function (e) {", editor.indexOf('    var leaving = false;'))), context);
vm.runInContext(editor.slice(editor.indexOf('    function reloadEditor('), editor.indexOf('    /** Cancel:')), context);
for (const file of ['components/department-media-manager.php', 'components/program-page-manager.php', 'components/homepage-president-manager.php']) {
    const source = fs.readFileSync(file, 'utf8');
    const start = source.indexOf("    window.addEventListener('beforeunload', function (");
    vm.runInContext(source.slice(start, source.indexOf('    });', start) + 7), context);
}
function warned() {
    let warnings = 0;
    handlers.forEach(handler => handler({ preventDefault() { warnings++; } }));
    return warnings;
}
visualDirty = 1;
assert.equal(warned(), 1, 'Normal unsaved visual edits still warn before leaving');
visualDirty = 0;
vm.runInContext("reloadEditor('Published.', {page:'college-departments',sub:'cased'}, true)", context);
assert.equal(navigations, 1, 'Successful Publish refreshes the saved editor');
drawerDirty = true;
context.card.dataset.dirty = '1';
assert.equal(warned(), 0, 'All managers recognize an intentional editor refresh');
context.window.__veLeaving = false;
vm.runInContext('leaving = false', context);
context.card.dataset.dirty = '0';
navigations = 0;
vm.runInContext("reloadEditor('Published.', {page:'college-departments',sub:'cased'}, true)", context);
assert.equal(navigations, 0, 'Publish keeps independently unsaved settings on the page');
assert.equal(previewRefreshes, 1, 'Only the preview refreshes when More settings has pending edits');
assert.equal(warned(), 1, 'Unsaved settings still warn if the user later leaves');
console.log('PASS: successful Publish avoids leave warnings, unsaved drawer values survive, and real unsaved edits remain protected');
