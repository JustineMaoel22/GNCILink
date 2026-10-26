const assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
function docker(args){const r=spawnSync('docker',args,{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout.trim();}
let database,container;
(async()=>{
 database=JSON.parse(docker(['compose','exec','-T','web','php','tests/rbac-fixture.php','create'])).database;
 container='gnci-onboarding-test-'+database.slice(-12);
 docker(['compose','run','--rm','-d','--name',container,'-p','127.0.0.1:8094:8094','-e','GNC_RBAC_TEST_DB='+database,'web','php','-S','0.0.0.0:8094','tests/rbac-test-router.php']);
 const fixture=(action)=>docker(['exec',container,'php','tests/onboarding-fixture.php',database,action]);
 const session=role=>JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php','session',database,role]));
 const base='http://127.0.0.1:8094';
 async function request(ctx,url,data){return fetch(base+url,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:ctx.cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams({csrf_token:ctx.csrf,...data}):undefined});}
 const roles=['Super Admin','Content Editor','The Mirror'],sessions=roles.map(session);
 // Missing migration leaves Help and authentication working; auto tutorial is disabled.
 let r=await request(sessions[0],'/admin/help.php');assert.equal(r.status,200);
 const before=await r.text();assert(before.includes('Help / User Guide'));
 fixture('install');
 for(const [i,role] of roles.entries()){
  const ctx=sessions[i];r=await request(ctx,'/admin/help.php');assert.equal(r.status,200);
  const html=await r.text(),config=JSON.parse(/id="adminOnboardingData">([^<]+)<\/script>/.exec(html)[1]);
  assert.equal(config.auto,false,'Help is manual');assert.equal(config.persist,true);
  const titles=config.steps.map(s=>s.title);
  assert(titles.includes('Announcements')&&titles.includes('News'));
  assert.equal(titles.includes('Users'),i===0);assert.equal(titles.includes('Activity Logs'),i===0);
  assert.equal(titles.includes('Approvals'),i===0);assert.equal(titles.includes('My Submissions'),i!==0);
  assert.equal(titles.includes('Content Editor'),i!==2);assert.equal(titles.includes('Dashboard'),true);
  const home='/admin/admin-dash.php';r=await request(ctx,home);
  const homeHtml=await r.text();assert.equal(JSON.parse(/id="adminOnboardingData">([^<]+)<\/script>/.exec(homeHtml)[1]).auto,true,'First eligible workspace shows guide');
  r=await request(ctx,'/admin/action/onboarding-handler.php');assert.equal(r.status,405);
  r=await request(ctx,'/admin/action/onboarding-handler.php',{status:'completed',csrf_token:'wrong'});assert.equal(r.status,403);
  r=await request(ctx,'/admin/action/onboarding-handler.php',{status:'unknown'});assert.equal(r.status,422);
  r=await request(ctx,'/admin/action/onboarding-handler.php',{status:i===1?'skipped':'completed',user_id:'999'});assert.equal(r.status,200);assert.equal((await r.json()).success,true);
  const rows=JSON.parse(fixture('inspect'));assert(rows.some(row=>Number(row.user_id)===i+1));assert(!rows.some(row=>Number(row.user_id)===999),'Cannot modify another user');
  const fresh=session(role);r=await request(fresh,home);const after=await r.text();assert.equal(JSON.parse(/id="adminOnboardingData">([^<]+)<\/script>/.exec(after)[1]).auto,false,'Completion persists into a new session');
 }
 r=await fetch(base+'/admin/help.php',{redirect:'manual'});assert.equal(r.status,302);
 r=await fetch(base+'/admin/action/onboarding-handler.php',{redirect:'manual',method:'POST'});assert.equal(r.status,302);
 fixture('reset');
 await require('./onboarding-browser-check.cjs')({base,sessions,inspect:()=>JSON.parse(fixture('inspect'))});
 fixture('force-password');const forced=session('The Mirror');
 r=await request(forced,'/admin/help.php');assert.equal(r.status,302);assert.equal(r.headers.get('location'),'/admin/change-password.php');
 r=await request(forced,'/admin/action/onboarding-handler.php',{status:'completed'});assert.equal(r.status,403);
 console.log('PASS: role-scoped help, first workspace auto-start, completion/skip persistence across sessions, no cross-user writes, invalid status/CSRF/anonymous rejection, mandatory password gate and additive migration.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{
 if(container&&/^gnci-onboarding-test-[a-f0-9]{12}$/.test(container))try{docker(['stop',container]);}catch{}
 if(database&&/^gnci_rbac_test_[a-f0-9]{12}$/.test(database))try{docker(['compose','exec','-T','web','php','tests/rbac-fixture.php','drop',database]);}catch(e){console.error(e.message);process.exitCode=1;}
});
