const assert=require('node:assert/strict');
const http=require('node:http');
module.exports=async function({base,docker,container,database}){
 const session=()=>({cookie:'',csrf:''});
 const request=async(ctx,path,data)=>{const r=await fetch(base+path,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:ctx.cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams({csrf_token:ctx.csrf,...data}):undefined});for(const h of r.headers.getSetCookie()){const p=h.split(';')[0];if(p.startsWith('PHPSESSID='))ctx.cookie=p;}return r;};
 const token=()=>JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php','reset-token',database,'4'])).token;
 const anon=session();let r=await request(anon,'/admin/login.php');let html=await r.text();anon.csrf=/name="csrf_token" value="([^"]+)"/.exec(html)[1];assert(r.headers.getSetCookie().some(x=>/HttpOnly/i.test(x)&&/SameSite=Lax/i.test(x)),'Session cookie protections');
 const hostedStatus=(path,method='GET')=>new Promise((resolve,reject)=>{const req=http.request(base+path,{method,headers:{Host:'public.example.invalid','X-Forwarded-Proto':'https'}},res=>{res.resume();res.on('end',()=>resolve(res.statusCode));});req.on('error',reject);req.end();});
 assert.equal(await hostedStatus('/admin/login.php'),503,'Untrusted forwarded HTTPS cannot bypass hosted transport configuration');
 assert.equal(await hostedStatus('/admin/auth/auth_handler.php','POST'),403,'Hosted HTTP authentication POST is rejected');
 r=await request(anon,'/admin/auth/auth_handler.php',{action:'login',email:'absent@example.invalid',password:'invalid',csrf_token:'wrong'});assert.equal(r.status,403,'Login CSRF enforced');
 r=await request(anon,'/admin/auth/auth_handler.php',{action:'login',email:"' OR 1=1 --",password:'invalid'});assert.equal(r.status,401);assert.equal((await r.json()).error,'Invalid username/email or password');
 r=await request(anon,'/admin/forgot-password.php',{email:'absent@example.invalid'});assert.match(await r.text(),/If an account exists with this email, a password reset link has been sent\./);
 r=await request(anon,'/admin/forgot-password.php',{email:'absent@example.invalid',csrf_token:'wrong'});assert.match(await r.text(),/session token expired/);
 const stale=JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php','session-user',database,'4']));
 const reset=token();r=await request(anon,'/admin/reset-password.php?token='+reset);assert.equal(r.status,303);assert.equal(r.headers.get('location'),'/admin/reset-password.php','Token removed from address bar');
 r=await request(anon,'/admin/reset-password.php');html=await r.text();assert(!html.includes(reset),'Reset token not embedded in HTML');assert.match(html,/Confirm New Password/);anon.csrf=/name="csrf_token" value="([^"]+)"/.exec(html)[1];
 const password='a fresh recovery phrase for testing';
 r=await request(anon,'/admin/reset-password.php',{new_password:password,confirm_password:password,csrf_token:'wrong'});assert.match(await r.text(),/session token expired/);
 // The test server uses deliberately unavailable SMTP for this HTTP success path.
 r=await request(anon,'/admin/reset-password.php',{new_password:password,confirm_password:password});assert.equal(r.status,303);assert.equal(r.headers.get('location'),'/admin/login.php?password_reset=1');
 r=await request(anon,'/admin/announcement.php');assert.equal(r.status,302,'Reset never automatically logs in');
 r=await request(stale,'/admin/announcement.php');assert.equal(r.status,401,'Existing session revoked by reset');
 r=await request(anon,'/admin/reset-password.php?token='+reset);assert.equal(r.status,303);r=await request(anon,'/admin/reset-password.php');assert.match(await r.text(),/invalid or expired/);
 const logout=JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php','session-user',database,'4']));r=await request(logout,'/admin/auth/logout.php');assert.equal(r.status,200,'GET logout is confirmation only');r=await request(logout,'/admin/auth/logout.php',{csrf_token:'wrong'});assert.equal(r.status,403);r=await request(logout,'/admin/auth/logout.php',{});assert.equal(r.status,303);assert(!r.headers.getSetCookie().some(x=>x.startsWith('gnc_remember_me=')),'Ordinary logout preserves trusted-device cookie');r=await request(logout,'/admin/announcement.php');assert.equal(r.status,302);
 console.log('PASS: HTTP login/recovery CSRF, SQL-injection input, generic errors, clean reset URLs, single use, logged-out reset completion, session revocation and logout session invalidation.');
};
