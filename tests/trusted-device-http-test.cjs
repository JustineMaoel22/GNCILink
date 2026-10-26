// Real HTTP/database checks with synthetic accounts, isolated from the site's records.
const assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
function docker(args){const r=spawnSync('docker',args,{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout.trim();}
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));let database,container;
(async()=>{
 database=JSON.parse(docker(['compose','exec','-T','web','php','tests/rbac-fixture.php','create'])).database;
 container='gnci-trusted-test-'+database.slice(-12);
 docker(['compose','run','--rm','-d','--name',container,'-p','127.0.0.1:8093:8093','-e','GNC_RBAC_TEST_DB='+database,'-e','GNC_RBAC_TEST_HTTPS=1','web','php','-S','0.0.0.0:8093','tests/rbac-test-router.php']);
 const fixture=(action,...args)=>JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php',action,database,...args]));
 const state=(mode='inspect',...args)=>fixture('trusted-state',mode,...args);
 const base='http://127.0.0.1:8093',jar=new Map();let csrf;
 async function request(url,data){
  const r=await fetch(base+url,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:[...jar].map(([k,v])=>k+'='+v).join('; '),'X-Forwarded-Proto':'https',...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams({csrf_token:csrf,...data}):undefined});
  for(const cookie of r.headers.getSetCookie()){const pair=cookie.split(';')[0],i=pair.indexOf('='),k=pair.slice(0,i),v=pair.slice(i+1);if(!v||/max-age=0/i.test(cookie))jar.delete(k);else jar.set(k,v);}return r;
 }
 for(let n=0;n<40;n++){try{if((await request('/admin/login.php')).ok)break;}catch{}await pause(250);}
 state('setup');
 async function remember(form=false){
  const p=fixture('auth-prep','1','otp-remember');jar.set('PHPSESSID',p.cookie.split('=')[1]);csrf=p.csrf;
  const r=await request(form?'/admin/auth/verify-otp.php':'/admin/auth/auth_handler.php',form?Object.fromEntries([...('123456')].map((v,i)=>['otp_'+(i+1),v])):{action:'verify_otp',otp:'123456'});
  if(!form)assert.equal((await r.json()).success,true);else assert.equal(r.status,302);
  const cookie=r.headers.getSetCookie().find(v=>v.startsWith('gnc_remember_me='));
  assert(cookie&&/HttpOnly/i.test(cookie)&&/Secure/i.test(cookie)&&/SameSite=Strict/i.test(cookie),'Trust cookie uses Secure, HttpOnly and SameSite');
  assert(Date.parse(/expires=([^;]+)/i.exec(cookie)[1])>Date.now()+29*86400000,'Trust lasts 30 days');
  assert.match(jar.get('gnc_remember_me'),/^[a-f0-9]{64}$/);assert.equal(Number(state().remembered),1);return jar.get('gnc_remember_me');
 }
 async function logout(forget=false){const page=await request('/admin/auth/logout.php');assert.equal(page.status,200);csrf=/name="csrf_token" value="([^"]+)"/.exec(await page.text())[1];const r=await request('/admin/auth/logout.php',forget?{forget_device:'1'}:{});assert.equal(r.status,303);return r;}
 async function login(token){
  if(token===null)jar.delete('gnc_remember_me');else if(token!==undefined)jar.set('gnc_remember_me',token);
  const page=await request('/admin/login.php');assert.equal(page.status,200);csrf=/name="csrf_token" value="([^"]+)"/.exec(await page.text())[1];
  return request('/admin/auth/auth_handler.php',{action:'login',email:'rbac1@example.invalid',password:'Synthetic-trusted-password-2026!',remember_me:'0'});
 }
 const token=await remember(),expiry=state().remember_token_expires_at;
 await logout();assert.equal(jar.get('gnc_remember_me'),token);assert.equal(Number(state().remembered),1);
 let r=await login();assert.equal((await r.json()).redirect,'/admin/admin-dash.php','Trust survives logout without rechecking remember');
 assert.equal(state().remember_token_expires_at,expiry,'Trusted login does not extend expiry');
 state('expire-session',jar.get('PHPSESSID'));r=await request('/admin/admin-dash.php');assert.match(r.headers.get('location'),/timeout=1/);assert.equal(jar.get('gnc_remember_me'),token);
 r=await login();assert.equal((await r.json()).redirect,'/admin/admin-dash.php','Trust survives session timeout');
 jar.delete('PHPSESSID');r=await login();assert.equal((await r.json()).redirect,'/admin/admin-dash.php','Trust survives session loss');
 r=await request('/admin/auth/logout.php',{forget_device:'1',csrf_token:'wrong'});assert.equal(r.status,403);assert.equal(Number(state().remembered),1);
 await logout(true);assert(!jar.has('gnc_remember_me'));assert.equal(Number(state().remembered),0);
 async function requiresOtp(candidate,label){
  jar.delete('PHPSESSID');state('clear-otp');r=await login(candidate);
  // The isolated router uses unavailable local SMTP, so new OTP issuance fails
  // delivery with 503. No real account or external mail provider is used.
  assert.equal(r.status,503,label);assert.equal(Number(state().has_otp),1,label+' requires OTP');
 }
 await requiresOtp(token,'Revoked device');await requiresOtp(null,'New browser');await requiresOtp('malformed','Malformed token');await requiresOtp('a'.repeat(64),'Forged token');
 const formToken=await remember(true);await logout();state('expire');await requiresOtp(formToken,'Expired device');
 console.log('PASS: OTP API/form issuance; Secure/HttpOnly cookies; logout, session expiry/loss; fixed 30-day expiry; CSRF-protected revocation; unrecognized, forged, malformed, expired and revoked browsers require OTP.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{
 if(container&&/^gnci-trusted-test-[a-f0-9]{12}$/.test(container))try{docker(['stop',container]);}catch(e){console.error(e.message);}
 if(database&&/^gnci_rbac_test_[a-f0-9]{12}$/.test(database))try{docker(['compose','exec','-T','web','php','tests/rbac-fixture.php','drop',database]);}catch(e){console.error(e.message);process.exitCode=1;}
});
