<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/admin-functions.php';initSession();requireLogin();requirePermission('manage_users');
header('Cache-Control: no-store, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
$temporaryPassword=null;$createdEmail='';
$error='';$success=$_SESSION['users_notice']??'';unset($_SESSION['users_notice']);$roles=['Super Admin','Content Editor','The Mirror'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_string($_POST['csrf_token']??null)||!verifyCSRFToken($_POST['csrf_token']))throw new RuntimeException('Invalid request token.');
  $role=$_POST['role']??'';$db=getDB();$action=$_POST['action']??'';
  if($action!=='delete'&&!in_array($role,$roles,true))throw new InvalidArgumentException('Choose a valid role.');
  if($action==='delete'){
   require_once __DIR__.'/../includes/user-deletion.php';
   if(!is_string($_POST['current_password']??null))throw new InvalidArgumentException('Enter your current Super Admin password.');
   deleteAdminUser((int)($_POST['user_id']??0),$_POST['current_password']);
   $success='User deleted. Their published content has been retained.';
  }elseif($action==='create'){
   $first=trim($_POST['first_name']??'');$last=trim($_POST['last_name']??'');$email=trim($_POST['email']??'');
   if($first===''||$last===''||mb_strlen($first)>100||mb_strlen($last)>100||strlen($email)>255||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid name and email address.');
   if(!firstLoginPasswordReady())throw new RuntimeException('Account creation needs the first-login password database migration. Ask your developer to import sql/first-login-password-migration.sql.');
   $password=generateTemporaryPassword();
   $db->prepare('INSERT INTO users(first_name,last_name,email,password,role,status,must_change_password) VALUES(?,?,?,?,?,\'active\',1)')->execute([$first,$last,$email,password_hash($password,PASSWORD_DEFAULT),$role]);$id=(int)$db->lastInsertId();
   $temporaryPassword=$password;$createdEmail=$email;unset($password);
   logActivity((int)$_SESSION['user_id'],'CREATE_USER','users',$id,'Created an account with role '.$role.'; mandatory password change enabled.');$success='User account created. Copy the temporary password now; it will not be displayed again.';
  }elseif($action==='role'){
   $id=(int)($_POST['user_id']??0);if($id===(int)$_SESSION['user_id']&&$role!=='Super Admin')throw new RuntimeException('You cannot remove your own Super Admin access.');
   $db->beginTransaction();$db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' FOR UPDATE")->fetchAll();$q=$db->prepare('SELECT role FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetchColumn();if($old===false)throw new RuntimeException('Account not found.');
   if($old==='Super Admin'&&$role!=='Super Admin'&&(int)$db->query("SELECT COUNT(*) FROM users WHERE role='Super Admin' AND status='active'")->fetchColumn()<=1)throw new RuntimeException('Keep at least one active Super Admin.');
   $db->prepare('UPDATE users SET role=?,remember_token=NULL,remember_token_expires_at=NULL WHERE user_id=?')->execute([$role,$id]);logActivity((int)$_SESSION['user_id'],'ASSIGN_ROLE','users',$id,'Changed role from '.$old.' to '.$role);$db->commit();$success='Role updated. Existing sessions use the new permissions on their next request.';
  }else throw new RuntimeException('Unknown action.');
  if($temporaryPassword===null){$_SESSION['users_notice']=$success;header('Location: /admin/users.php');exit;}
 }catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();$error=$e instanceof PDOException?'The account could not be saved. Check whether the email is already registered and the RBAC migration is installed.':$e->getMessage();}
}
$users=getDB()->query('SELECT user_id,first_name,last_name,email,role,status FROM users ORDER BY first_name,last_name')->fetchAll();$pageTitle='Users';require __DIR__.'/../components/header-admin.php';$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="page-header"><div><h1>Users</h1><p>Create accounts and assign their admin permissions.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?= $e($success) ?></div><?php endif;?>
<section class="data-card p-4 mb-4"><h2 class="data-card-title">Create user</h2><form method="post" data-user-confirm="Create this admin account with the selected role?" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="create"><div class="row g-3">
<?php foreach(['first_name'=>'First name','last_name'=>'Last name','email'=>'Email'] as $key=>$label):?><div class="col-md-4"><label class="form-label" for="user-<?= $key ?>"><?= $label ?></label><input class="form-control" id="user-<?= $key ?>" name="<?= $key ?>" type="<?= $key==='email'?'email':'text' ?>" maxlength="<?= $key==='email'?255:100 ?>" required></div><?php endforeach;?>
<div class="col-md-6"><label class="form-label" for="new-role">Role</label><select class="form-select" id="new-role" name="role"><?php foreach($roles as $role):?><option<?= $role==='Content Editor'?' selected':'' ?>><?= $e($role) ?></option><?php endforeach;?></select></div><div class="col-12"><p class="form-text mb-0">A strong temporary password will be generated automatically. The account owner must change it on first login.</p></div></div><button class="btn btn-gnc-gold mt-3">Create user</button></form></section>
<section class="data-card p-4 mb-4" aria-labelledby="new-account-instructions">
    <h2 class="data-card-title" id="new-account-instructions">Instructions for new accounts</h2>
    <p>Use an email address the account owner can access. After creating the account, copy the temporary password before closing the confirmation and share it privately with the owner. Passwords are not emailed automatically.</p>
    <ol class="mb-3">
        <li>Open <a href="/admin/login.php">Admin Login</a> and enter the account email and temporary password.</li>
        <li>Enter the six-digit verification code sent to that email. Check the spam or junk folder if it does not appear.</li>
        <li>On the Change Password screen, enter the temporary password as the current password. Choose a different, unique password of at least 12 characters and enter it again to confirm.</li>
        <li>Save the new password, then select Continue to open the admin panel. Use the new password for future logins.</li>
    </ol>
    <p class="mb-0">Select <strong>Remember this device</strong> only on a private device. After completing account setup, select it on your next login and complete verification to trust that browser for 30 days. Use <strong>Forgot password</strong> on the login page if the password is lost.</p>
</section>
<section class="data-card p-4"><h2 class="data-card-title">Existing users</h2><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Role</th><th>Actions</th></tr></thead><tbody>
<?php foreach($users as $user): ?>
<tr><td><?= $e($user['first_name'].' '.$user['last_name']) ?></td><td><?= $e($user['email']) ?></td><td><?= $e($user['status']) ?></td><td><form method="post" class="d-flex gap-2" data-user-confirm="Apply this role change? Permissions take effect on the next request."><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>"><select class="form-select" name="role" aria-label="Role for <?= $e($user['first_name']) ?>"><?php foreach($roles as $role):?><option<?= rbacRole($user['role'])===$role?' selected':'' ?>><?= $e($role) ?></option><?php endforeach;?></select><button class="btn btn-outline-secondary">Save</button></form></td>
<td><?php if((int)$user['user_id']!==(int)$_SESSION['user_id']): ?><button type="button" class="btn btn-outline-danger btn-sm" data-delete-user="<?= (int)$user['user_id'] ?>" data-user-name="<?= $e($user['first_name'].' '.$user['last_name']) ?>">Delete</button><?php else: ?><span class="text-muted small">Your account</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></section>
<dialog id="delete-user-dialog" class="gnc-dialog gnc-dialog-danger" aria-labelledby="delete-user-title" aria-describedby="delete-user-description"><div class="gnc-dialog-content">
<h2 id="delete-user-title">Delete user?</h2><p id="delete-user-description">Delete <strong id="delete-user-name"></strong>? This permanently removes their account and access. Published content will be retained. Pending submissions will be closed.</p>
<form method="post" action="/admin/users.php" id="delete-user-form">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" id="delete-user-id">
<label class="form-label" for="delete-user-password">Your current Super Admin password</label><input type="password" class="form-control mb-3" name="current_password" id="delete-user-password" autocomplete="current-password" required>
<div class="gnc-dialog-actions"><button type="button" class="gnc-dialog-cancel" id="cancel-delete-user">Cancel</button><button type="submit" class="gnc-dialog-accept">Delete user</button></div></form></div></dialog>
<script src="/assets/js/user-deletion.js?v=<?= filemtime(__DIR__.'/../assets/js/user-deletion.js') ?>"></script>
<script>document.addEventListener('submit',async event=>{const form=event.target;if(!form.matches('[data-user-confirm]'))return;event.preventDefault();if(await AdminDialogs.confirm(form.dataset.userConfirm,{title:'Confirm user change',confirmText:'Confirm'})){form.querySelector('button').disabled=true;HTMLFormElement.prototype.submit.call(form);}});</script>
<?php if($temporaryPassword!==null):?>
<dialog id="temporary-password-dialog" class="gnc-dialog" aria-labelledby="temporary-password-title"><div class="gnc-dialog-content"><h2 id="temporary-password-title">User account created</h2><p>Account: <?= $e($createdEmail) ?></p><p>Copy this temporary password and share it privately with the account owner. It is shown only now. They must choose a new password before using admin features.</p><label class="form-label" for="temporary-password">Temporary password</label><input class="form-control mb-3" id="temporary-password" type="text" value="<?= $e($temporaryPassword) ?>" readonly autocomplete="off" spellcheck="false"><p id="password-copy-status" role="status"></p><div class="gnc-dialog-actions"><button class="gnc-dialog-cancel" id="copy-temporary-password" type="button">Copy Password</button><button class="gnc-dialog-accept" type="button" id="close-temporary-password">Done</button></div></div></dialog>
<script>(()=>{const dialog=document.getElementById('temporary-password-dialog'),input=document.getElementById('temporary-password'),status=document.getElementById('password-copy-status');dialog.showModal();document.getElementById('copy-temporary-password').addEventListener('click',async()=>{try{if(navigator.clipboard?.writeText)await navigator.clipboard.writeText(input.value);else{input.focus();input.select();if(!document.execCommand('copy'))throw Error();}status.textContent='Password copied.';}catch{input.focus();input.select();status.textContent='Select the password and copy it manually.';}});document.getElementById('close-temporary-password').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>{input.value='';input.removeAttribute('value');dialog.remove();});})();</script>
<?php unset($temporaryPassword);endif;?>
<?php require __DIR__.'/../components/footer-admin.php';?>
