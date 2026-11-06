const assert=require('node:assert/strict');
const {spawnSync}=require('node:child_process');
const base=process.env.TEST_BASE_URL||'http://localhost:8080';
const sessions=[];
function session(role){const r=spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php',role],{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error('Local test session unavailable');const s=JSON.parse(r.stdout);sessions.push(s);return s;}
async function page(route,cookie){const r=await fetch(base+route,{headers:cookie?{Cookie:cookie}:{},signal:AbortSignal.timeout(20000)});const html=await r.text();assert.equal(r.status,200,route);assert.ok(!/Fatal error:|SQLSTATE\[|Uncaught PDOException|doesn.t exist/i.test(html),route+' has an error');return html;}
(async()=>{
    for(const route of ['/','/pages/about/administration.php','/pages/academics/college/college-dept.php','/pages/student%20life/community-activity.php','/pages/faq.php']){
        const html=await page(route);assert.match(html,/<html/i,route+' returned HTML');
    }
    const admin=session('Super Admin'),cookie=admin.name+'='+admin.value;
    for(const panel of ['administration','programs','community-activities']){
        const html=await page('/admin/content-edit.php?page='+panel,cookie);assert.ok(html.includes('id="veApp"'),panel+' editor shell missing');assert.ok(html.includes('window.VE_CONFIG'),panel+' editor configuration missing');
    }
    console.log('Post-cleanup HTTP checks: 5 public pages and 3 authenticated editors passed. No content was submitted.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{for(const s of sessions)spawnSync('docker',['compose','exec','-T','--user','www-data','web','php','cache/activity-log-session.php','destroy',s.value],{encoding:'utf8',windowsHide:true});});
