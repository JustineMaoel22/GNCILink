// Read-only/unauthenticated checks against the running local website. No provider calls.
const assert=require('node:assert/strict'),fs=require('node:fs');
const base=process.env.GNCI_TEST_URL||'http://localhost:8080';
(async()=>{
    let checked=0;
    for(const file of fs.readdirSync('admin/action').filter(file=>file.endsWith('.php'))){
        const response=await fetch(base+'/admin/action/'+file,{method:'POST',redirect:'manual',body:new URLSearchParams({action:'get'})});
        assert.ok([302,303,401,403,405].includes(response.status),file+' must reject anonymous POST: '+response.status);checked++;
    }
    const calendar=await fetch(base+'/api/calendar-day.php?date=2026-11-09');
    assert.equal(calendar.status,200);assert.ok(Array.isArray((await calendar.json()).events));
    assert.equal((await fetch(base+'/api/calendar-day.php?date=invalid')).status,400);
    const catalog=await fetch(base+'/api/recommendations.php');assert.equal(catalog.status,200);assert.ok(Array.isArray((await catalog.json()).items));
    assert.equal((await fetch(base+'/api/privacy-preferences.php',{method:'POST'})).status,403);
    const status=await fetch(base+'/chatbot/chatbot.php',{method:'POST',headers:{'Content-Type':'application/json','X-GNCI-Chat':'1'},body:JSON.stringify({action:'status'})});
    assert.equal(status.status,200);assert.equal((await status.json()).ok,true);
    const login=await fetch(base+'/admin/login');assert.equal(login.status,200);assert.ok((await login.text()).includes('/assets/js/request-guard.js'));
    console.log(`PASS: ${checked} admin handlers reject anonymous POST; calendar, validation, recommendations, privacy CSRF, chatbot status, and login script delivery.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
