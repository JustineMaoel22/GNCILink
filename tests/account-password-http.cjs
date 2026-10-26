const assert=require('node:assert/strict');
module.exports=async({base,docker,container,database})=>{
 const fixture=(action,...args)=>JSON.parse(docker(['exec',container,'php','tests/rbac-fixture.php',action,database,...args]));
 const inspect=()=>fixture('inspect');
 const request=async(ctx,url,data)=>{
  const r=await fetch(base+url,{redirect:'manual',method:data?'POST':'GET',headers:{Cookie:ctx.cookie,...(data?{'Content-Type':'application/x-www-form-urlencoded'}:{})},body:data?new URLSearchParams({csrf_token:ctx.csrf,...data}):undefined});
  for(const cookie of r.headers.getSetCookie()){const pair=cookie.split(';')[0];if(pair.startsWith('PHPSESSID='))ctx.cookie=pair;}return r;
 };
 const accounts=inspect().users.filter(u=>Number(u.user_id)>=4&&Number(u.must_change_password)===0).slice(0,3);
 assert.equal(accounts.length,3,'Completed accounts exist for all roles');
 for(const account of accounts){
  const id=Number(account.user_id);
  const ctx=fixture('session-user',String(id)),stale=fixture('session-user',String(id));
  fixture('auth-prep',String(id),'trusted');
  const before=inspect().users,version=before.find(u=>Number(u.user_id)===id).credential_version;
  let r=await request(ctx,'/admin/settings.php');assert.equal(r.status,200);assert((await r.text()).includes('Change Password'));
  r=await request(ctx,'/admin/account-password.php');assert.equal(r.status,200);const form=await r.text();assert(form.includes('Current Password')&&form.includes('Show passwords'));ctx.csrf=/name="csrf_token" value="([^"]+)"/.exec(form)[1];
  const fields={current_password:'Private-new-password-2026!',new_password:'Personal-orchard-password-'+id+'!',confirm_password:'Personal-orchard-password-'+id+'!'};
  for(const [change,message] of [[{csrf_token:'bad'},'session token expired'],[{current_password:'wrong'},'current password is incorrect'],[{confirm_password:'mismatch'},'do not match'],[{new_password:'weak',confirm_password:'weak'},'between 12 and 72'],[{new_password:fields.current_password,confirm_password:fields.current_password},'different from your current']]){
   r=await request(ctx,'/admin/account-password.php',{...fields,...change});assert((await r.text()).includes(message));
   assert.equal(inspect().users.find(u=>Number(u.user_id)===id).credential_version,version,'Rejected change preserves credentials');
  }
  const oldCookie=ctx.cookie;r=await request(ctx,'/admin/account-password.php',{...fields,user_id:'1'});assert.equal(r.status,303);assert.equal(r.headers.get('location'),'/admin/account-password.php');assert.notEqual(ctx.cookie,oldCookie,'Session ID rotates');
  const after=inspect().users,owner=after.find(u=>Number(u.user_id)===id);assert.equal(Number(owner.credential_version),Number(version)+1);assert(owner.password_is_hash&&!owner.has_remember_token);assert.equal(owner.must_change_password,0);
  assert.equal(after.find(u=>Number(u.user_id)===1).credential_version,before.find(u=>Number(u.user_id)===1).credential_version,'Forged user ID cannot target another account');
  r=await request(ctx,'/admin/account-password.php');assert((await r.text()).includes('Your password has been changed'));
  assert.equal((await request(stale,'/admin/admin-dash.php')).status,401,'Other session revoked');
  assert.equal((await request(ctx,'/admin/admin-dash.php')).status,200,'Current session remains usable');
 }
 assert.equal((await fetch(base+'/admin/account-password.php',{redirect:'manual'})).status,302,'Anonymous account access denied');
 console.log('PASS: all-role personal settings/password changes; current password, strength, match, reuse and CSRF checks; own-account targeting; hash storage; session rotation; other-session and trusted-token revocation.');
};
