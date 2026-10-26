const assert=require('node:assert/strict'),{spawnSync}=require('node:child_process');
function docker(args){const r=spawnSync('docker',args,{encoding:'utf8',windowsHide:true});if(r.status!==0)throw Error(r.stderr||r.stdout);return r.stdout.trim();}
let database,container;
(async()=>{
 database=JSON.parse(docker(['compose','exec','-T','web','php','tests/rbac-fixture.php','create'])).database;container='gnci-delete-test-'+database.slice(-12);
 docker(['compose','run','--rm','-d','--name',container,'-p','127.0.0.1:8095:8095','-e','GNC_RBAC_TEST_DB='+database,'web','php','-S','0.0.0.0:8095','tests/rbac-test-router.php']);
 const fixture=action=>docker(['exec',container,'php','tests/user-deletion-fixture.php',database,action]);
 const session=(...args)=>JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php',...args.slice(0,1),database,...args.slice(1)]));
 fixture('setup');const inspect=()=>JSON.parse(fixture('inspect'));const base='http://127.0.0.1:8095';
 const admin=session('session','Super Admin'),mirror=session('session','The Mirror'),editor=session('session','Content Editor'),target=session('session-user','4');
 const trusted=session('auth-prep','4','trusted');
 const request=(ctx,url,data)=>fetch(base+url,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:ctx.cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams({csrf_token:ctx.csrf,...data}):undefined});
 const remove=(extra={})=>request(admin,'/admin/users.php',{action:'delete',user_id:'4',current_password:'Synthetic-admin-confirmation-2026!',...extra});
 let r=await request(admin,'/admin/users.php');assert.equal(r.status,200);const html=await r.text();assert(html.includes('data-delete-user="4"')&&!html.includes('data-delete-user="1"'));assert(html.includes('name="current_password"')&&html.includes('Your current Super Admin password'));
 await remove({csrf_token:'wrong'});assert(inspect().users.map(Number).includes(4),'Invalid CSRF preserves account');
 r=await remove({current_password:''});assert((await r.text()).includes('Enter your current Super Admin password'));
 r=await remove({current_password:'Synthetic-target-password-2026!'});assert((await r.text()).includes('current password is incorrect'),'Target password must not authorize deletion');assert(inspect().users.map(Number).includes(4));
 r=await remove({user_id:'1'});assert((await r.text()).includes('cannot delete your own account'));
 r=await remove({user_id:'99999'});assert((await r.text()).includes('User not found'));
 r=await remove({user_id:'5'});assert((await r.text()).includes('protected records'));assert(inspect().users.map(Number).includes(5),'Unsafe cascading schema blocks deletion');
 for(const ctx of [mirror,editor])assert.equal((await request(ctx,'/admin/users.php',{action:'delete',user_id:'4',current_password:'Synthetic-admin-confirmation-2026!'})).status,403,'Only Super Admin can delete');
 assert.equal((await fetch(base+'/admin/users.php',{redirect:'manual',method:'POST'})).status,302,'Anonymous request blocked');
 fixture('clear-limits');for(let n=0;n<5;n++)await remove({current_password:'wrong'});
 r=await remove();assert((await r.text()).includes('Too many deletion attempts'));assert(inspect().users.map(Number).includes(4),'Rate limit prevents password brute force');fixture('clear-limits');
 await require('./user-deletion-browser-check.cjs')({base,cookie:admin.cookie});
 r=await remove();assert.equal(r.status,302);assert.equal(r.headers.get('location'),'/admin/users.php');
 const after=inspect();assert(!after.users.map(Number).includes(4));assert(after.users.map(Number).includes(1));assert.equal(after.posts.length,1);assert.equal(after.posts[0].content,'Published content retained');assert.equal(after.submissions[0].status,'rejected');assert.equal(after.reset_count,0);assert.equal(after.deletion_logs,1);
 assert.equal((await request(target,'/admin/admin-dash.php')).status,401,'Deleted account session is invalidated');
 const login=await request(trusted,'/admin/auth/auth_handler.php',{action:'login',email:'delete4@example.invalid',password:'Synthetic-target-password-2026!'});assert.equal(login.status,401,'Deleted account cannot authenticate with password or remembered device');
 console.log('PASS: password-confirmed deletion, own-account and role protections, CSRF, rate limiting, unsafe-cascade rejection, preserved content, rejected pending proposals, removed recovery token, audit and revoked access.');
})().catch(e=>{console.error(e);process.exitCode=1;}).finally(()=>{
 if(container&&/^gnci-delete-test-[a-f0-9]{12}$/.test(container))try{docker(['stop',container]);}catch{}
 if(database&&/^gnci_rbac_test_[a-f0-9]{12}$/.test(database))try{docker(['compose','exec','-T','web','php','tests/rbac-fixture.php','drop',database]);}catch(e){console.error(e.message);process.exitCode=1;}
});
