const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path');
const {spawnSync} = require('node:child_process');
const base = process.env.TEST_BASE_URL || 'http://localhost:8080';
const sessions = [];
const session = role => {
    const command = spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',role],{encoding:'utf8',windowsHide:true});
    if (command.status !== 0) throw Error('Local test session unavailable: '+command.stderr);
    const data = JSON.parse(command.stdout); sessions.push(data);
    return `${data.name}=${data.value}`;
};
const endpoint = (body,cookie) => fetch(base+'/admin/action/photo-check-handler.php',{method:'POST',body,headers:cookie?{Cookie:cookie}:{},redirect:'manual'});
(async () => {
    let response = await endpoint(new FormData());
    assert.equal(response.status,401);
    const admin = session('Super Admin');
    response = await endpoint(new FormData(),admin);
    assert.equal(response.status,403);
    const mirror = session('The Mirror');
    response = await endpoint(new FormData(),mirror);
    assert.equal(response.status,403);
    const editor = session('Content Editor');
    const page = await (await fetch(base+'/admin/content-edit.php?page=administration',{headers:{Cookie:editor}})).text();
    const token = page.match(/csrf(?:Token)?\s*:\s*"([a-f0-9]+)"/);
    assert.ok(token,'CSRF token absent from editor');
    const body = new FormData(); body.set('csrf_token',token[1]);
    body.set('photo',new Blob([fs.readFileSync(path.join(__dirname,'../uploads/administration/086f12f12d75f5d4788185497d0df834.jpg'))],{type:'image/jpeg'}),'portrait.jpg');
    body.set('person_name','Face validation endpoint test'); body.set('profile_key','president:1');
    body.set('review_duplicate','1');
    response = await endpoint(body,editor);
    assert.equal(response.status,403,'Content Editor must not grant review');
    body.delete('review_duplicate');
    response = await endpoint(body,editor);
    const result = await response.json();
    assert.equal(result.success,true);
    assert.equal(response.headers.get('cache-control'),'no-store');
    assert.ok(!('detail' in result) && !('review_key' in result));
    if (result.reason === 'disabled') {
        assert.equal(result.checked,false,'Disabled checker must skip duplicate comparison');
        assert.equal(result.ok,true,'Valid images must pass when optional checking is disabled');
        assert.ok(!('matches' in result),'Disabled checking must not return duplicate matches');
    } else if (process.env.FACE_CHECK_ALLOW_UNAVAILABLE === '1' && result.reason === 'unavailable') {
        assert.equal(result.ok,false,'Missing runtime must block');
    } else {
        assert.equal(result.checked,true,'The deployed runtime must complete real face comparison');
        assert.notEqual(result.reason,'unavailable','The deployed runtime must be available');
    }
    console.log('Personnel photo endpoint: authentication, CSRF, role restrictions, private responses and configured checker behavior passed.');
})().catch(error => { console.error(error); process.exitCode=1; }).finally(() => {
    for (const data of sessions) spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','destroy',data.value],{encoding:'utf8',windowsHide:true});
});
