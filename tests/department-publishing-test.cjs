const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

// Exercise the real editor actions without a database or destructive requests.
const source = fs.readFileSync(path.join(__dirname, '../assets/js/content-edit.js'), 'utf8');
function between(start, end) {
    const from = source.indexOf(start);
    const to = source.indexOf(end, from);
    assert.ok(from >= 0 && to > from);
    return source.slice(from, to);
}
let confirm = true, response = {ok: true};
const requests = [];
const context = vm.createContext({
    DD: {kind: 'college', dept_id: 42, dept_name: 'Test Department'},
    dRemove: {}, S: {publishing: false},
    window: {AdminDialogs: {confirm: async () => confirm}},
    closePop() {}, chrome() {}, toast() {},
    sendContent: async fields => { requests.push(fields); return response; },
    resetPending: () => { context.dRemove = {}; }
});
vm.runInContext(between('        async function removeDepartment()', '        function deptOptions(')
    + between('        function saveDept()', '        // ── programs'), context);

(async () => {
    confirm = false;
    await context.removeDepartment();
    assert.equal(context.dRemove.department, undefined);
    confirm = true;
    await context.removeDepartment();
    assert.equal(context.dRemove.department, true);
    assert.equal(requests.length, 0, 'Staging must not delete the department');
    await context.removeDepartment();
    assert.equal(context.dRemove.department, undefined, 'Cancel must clear deletion');
    assert.equal(requests.length, 0);
    await context.removeDepartment();
    assert.equal((await context.saveDept()).did, 0);
    assert.equal(requests.length, 0, 'Deletion requires Publish');
    context.S.publishing = true;
    response = {ok: false, error: 'Conflict'};
    assert.equal((await context.saveDept()).errs[0], 'Conflict');
    assert.equal(context.dRemove.department, true, 'Failed publishing must retain deletion');
    response = {ok: true};
    assert.equal((await context.saveDept()).departmentDeleted, true);
    assert.equal(context.dRemove.department, undefined);
    assert.equal(requests.length, 2);
    assert.ok(requests.every(r => r.action === 'delete_department' && r.dept_id === 42));
    console.log('PASS: department deletion staging, cancellation, publish guard, failure retry and success.');
})().catch(error => { console.error(error); process.exitCode = 1; });
