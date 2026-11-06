<?php
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/admin-functions.php';initSession();requireLogin();requirePermission('manage_users');
header('Cache-Control: no-store, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
$temporaryPassword=null;$createdEmail='';$createdName='';$createdRole='';
$createValues=['first_name'=>'','last_name'=>'','email'=>'','role'=>'Content Editor'];$createErrors=[];
if(($_POST['action']??'')==='create')foreach($createValues as $key=>$default)$createValues[$key]=is_string($_POST[$key]??null)?trim($_POST[$key]):'';
$error='';$success=$_SESSION['users_notice']??'';unset($_SESSION['users_notice']);$roles=['Super Admin','Content Editor','The Mirror'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!is_string($_POST['csrf_token']??null)||!verifyCSRFToken($_POST['csrf_token']))throw new RuntimeException('Invalid request token.');
  $role=$_POST['role']??'';$db=getDB();$action=$_POST['action']??'';
  if($action!=='delete'&&!in_array($role,$roles,true)){
   if($action==='create')$createErrors['role']='Choose one of the available roles.';
   throw new InvalidArgumentException('Choose a valid role.');
  }
  if($action==='delete'){
   require_once __DIR__.'/../includes/user-deletion.php';
   if(!is_string($_POST['current_password']??null))throw new InvalidArgumentException('Enter your current Super Admin password.');
   deleteAdminUser((int)($_POST['user_id']??0),$_POST['current_password']);
   $success='User deleted. Their published content has been retained.';
  }elseif($action==='create'){
   $first=$createValues['first_name'];$last=$createValues['last_name'];$email=$createValues['email'];
   foreach(['first_name'=>$first,'last_name'=>$last] as $key=>$value)if($value===''||mb_strlen($value)>100)$createErrors[$key]=$value===''?'Enter a '.($key==='first_name'?'first':'last').' name.':'Use 100 characters or fewer.';
   if(strlen($email)>255||!filter_var($email,FILTER_VALIDATE_EMAIL))$createErrors['email']='Enter a valid email address the account owner can access.';
   if($createErrors)throw new InvalidArgumentException('Please check the highlighted fields. The account has not been created.');
   $emailCheck=$db->prepare('SELECT user_id FROM users WHERE email=? LIMIT 1');$emailCheck->execute([$email]);
   if($emailCheck->fetchColumn()){
    $createErrors['email']='An account already uses this email address. Use a different email or manage the existing account below.';
    throw new InvalidArgumentException('This email address is already registered. The account has not been created.');
   }
   if(!firstLoginPasswordReady())throw new RuntimeException('Account creation needs the first-login password database migration. Ask your developer to import sql/first-login-password-migration.sql.');
   $password=generateTemporaryPassword();
   $db->prepare('INSERT INTO users(first_name,last_name,email,password,role,status,must_change_password) VALUES(?,?,?,?,?,\'active\',1)')->execute([$first,$last,$email,password_hash($password,PASSWORD_DEFAULT),$role]);$id=(int)$db->lastInsertId();
   $temporaryPassword=$password;$createdEmail=$email;$createdName=trim($first.' '.$last);$createdRole=$role;unset($password);
   $createValues=['first_name'=>'','last_name'=>'','email'=>'','role'=>'Content Editor'];
   logActivity((int)$_SESSION['user_id'],'CREATE_USER','users',$id,'Created an account with role '.$role.'; mandatory password change enabled.');$success='User account created. Copy the temporary password now; it will not be displayed again.';
  }elseif($action==='role'){
   require_once __DIR__.'/../includes/user-roles.php';
   if(!is_string($_POST['current_password']??null))throw new InvalidArgumentException('Enter your current Super Admin password to save this role change.');
   $changed=changeAdminUserRole((int)($_POST['user_id']??0),$role,$_POST['current_password']);
   $success=$changed?'Role updated. Existing sessions use the new permissions on their next request.':'No role changes to save.';
  }else throw new RuntimeException('Unknown action.');
  if($temporaryPassword===null){$_SESSION['users_notice']=$success;header('Location: /admin/users.php');exit;}
 }catch(Throwable $e){
  unset($password);
  if(isset($db)&&$db->inTransaction())$db->rollBack();
  if($e instanceof PDOException&&($_POST['action']??'')==='create'&&(int)($e->errorInfo[1]??0)===1062){
   $createErrors['email']='An account already uses this email address. Use a different email or manage the existing account below.';
   $error='This email address is already registered. The account has not been created.';
  }else $error=$e instanceof PDOException?'The account could not be saved. Please try again or contact your system administrator.':$e->getMessage();
 }
}
$users=getDB()->query('SELECT user_id,first_name,last_name,email,role,status FROM users ORDER BY first_name,last_name')->fetchAll();$pageTitle='Users';require __DIR__.'/../components/header-admin.php';$e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$userPictures=profilePictureUrls(array_column($users,'user_id'));
?>
<div class="page-header"><div><h1>Users</h1><p>Create accounts and assign their admin permissions.</p></div></div>
<?php if($error):?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif;?><?php if($success):?><div class="alert alert-success" role="status"><?= $e($success) ?></div><?php endif;?>
<link rel="stylesheet" href="/assets/css/user-create.css?v=<?= filemtime(__DIR__.'/../assets/css/user-create.css') ?>">
<section class="data-card user-create-card mb-3" aria-labelledby="create-user-heading">
  <header class="user-create-heading">
    <div><h2 id="create-user-heading">Create user</h2><p>Set up an account and choose the access it needs.</p></div>
    <span class="user-required-note">All fields are required <span aria-hidden="true" class="text-danger">*</span></span>
  </header>
  <form method="post" id="create-user-form" data-user-confirm="Create this admin account with the selected role?" autocomplete="off" novalidate>
    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
    <input type="hidden" name="action" value="create">
    <div class="user-create-body">
      <div id="create-user-feedback" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
      <div class="row g-3">
        <?php foreach(['first_name'=>['First name','e.g. Juan'],'last_name'=>['Last name','e.g. Dela Cruz'],'email'=>['Email address','you@example.com']] as $key=>[$label,$ph]): ?>
        <div class="col-12 col-sm-6">
          <label class="form-label" for="user-<?= $key ?>"><?= $label ?> <span class="text-danger" aria-hidden="true">*</span></label>
          <input class="form-control<?= isset($createErrors[$key])?' is-invalid':'' ?>" id="user-<?= $key ?>" name="<?= $key ?>" type="<?= $key==='email'?'email':'text' ?>" maxlength="<?= $key==='email'?255:100 ?>" value="<?= $e($createValues[$key]) ?>" placeholder="<?= $e($ph) ?>" required aria-describedby="user-<?= $key ?>-error<?= $key==='email'?' user-email-help':'' ?>"<?= isset($createErrors[$key])?' aria-invalid="true"':'' ?> spellcheck="false">
          <div class="invalid-feedback" id="user-<?= $key ?>-error" aria-live="polite"><?= $e($createErrors[$key]??'') ?></div>
          <?php if($key==='email'): ?><div class="form-text" id="user-email-help">Use an inbox the owner can access. Login verification codes go here.</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="col-12 col-sm-6">
          <label class="form-label" for="new-role">Role <span class="text-danger" aria-hidden="true">*</span></label>
          <select class="form-select<?= isset($createErrors['role'])?' is-invalid':'' ?>" id="new-role" name="role" required aria-describedby="user-role-help user-role-error"<?= isset($createErrors['role'])?' aria-invalid="true"':'' ?>>
            <option value="">Choose a role</option>
            <?php foreach($roles as $role): ?><option value="<?= $e($role) ?>"<?= $createValues['role']===$role?' selected':'' ?>><?= $e($role) ?></option><?php endforeach; ?>
          </select>
          <div class="invalid-feedback" id="user-role-error" aria-live="polite"><?= $e($createErrors['role']??'') ?></div>
          <p class="user-role-help" id="user-role-help" role="status"></p>
        </div>
      </div>
      <div class="user-setup-note"><i class="bi bi-shield-lock" aria-hidden="true"></i><div><strong>No password to enter</strong><p>A secure temporary password is generated automatically. The owner must change it on first login. Passwords are not emailed.</p></div></div>
    </div>
    <footer class="user-create-footer"><span>Review the email and role before creating the account.</span><button type="submit" class="btn btn-gnc-gold" id="create-user-button"><i class="bi bi-person-plus" aria-hidden="true"></i> <span>Create user</span></button></footer>
  </form>
</section>
<details class="data-card user-account-help mb-4">
  <summary>How to set up a new account <span>First login, password change, and trusted devices</span></summary>
  <div class="user-account-help-body">
    <p>Copy the temporary password from the confirmation and share it privately with the owner. Passwords are not emailed automatically.</p>
    <ol>
      <li>Open <a href="/admin/login.php">Admin Login</a> with the account email and temporary password.</li>
      <li>Enter the six-digit verification code sent to that email. Check spam or junk if needed.</li>
      <li>Enter the temporary password as the current password, then choose and confirm a different password of at least 12 characters.</li>
      <li>Save the password and select Continue. Use the new password for future logins.</li>
    </ol>
    <p>On the next login, choose <strong>Remember this device</strong> only on a private device and complete verification to trust that browser for 30 days. If the password is lost, use <strong>Forgot password</strong> on the login page.</p>
  </div>
</details>
<section class="data-card p-4" id="existing-users"><h2 class="data-card-title">Existing users</h2><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Role</th><th>Actions</th></tr></thead><tbody>
<?php foreach($users as $user): ?>
<tr><td><div class="user-list-person"><span class="user-list-avatar" aria-hidden="true"><?php if(isset($userPictures[(int)$user['user_id']])): ?><img src="<?= $e($userPictures[(int)$user['user_id']]) ?>" alt="" width="36" height="36" loading="lazy"><?php else: ?><?= $e(mb_strtoupper(mb_substr(trim($user['first_name']),0,1).mb_substr(trim($user['last_name']),0,1))?:'?') ?><?php endif; ?></span><span><?= $e($user['first_name'].' '.$user['last_name']) ?></span></div></td><td><?= $e($user['email']) ?></td><td><?= $e($user['status']) ?></td><td><form method="post" class="d-flex gap-2" data-saved-role="<?= $e(rbacRole($user['role'])) ?>" data-user-confirm="Apply this role change? Permissions take effect on the next request."><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>"><select class="form-select" name="role" aria-label="Role for <?= $e($user['first_name']) ?>"><?php foreach($roles as $role):?><option<?= rbacRole($user['role'])===$role?' selected':'' ?>><?= $e($role) ?></option><?php endforeach;?></select><button type="submit" class="btn btn-outline-secondary" disabled>Save</button></form></td>
<td><?php if((int)$user['user_id']!==(int)$_SESSION['user_id']): ?><button type="button" class="btn btn-outline-danger btn-sm" data-delete-user="<?= (int)$user['user_id'] ?>" data-user-name="<?= $e($user['first_name'].' '.$user['last_name']) ?>">Delete</button><?php else: ?><span class="text-muted small">Your account</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div></section>
<dialog id="delete-user-dialog" class="gnc-dialog gnc-dialog-danger" aria-labelledby="delete-user-title" aria-describedby="delete-user-description"><div class="gnc-dialog-content">
<h2 id="delete-user-title">Delete user?</h2><p id="delete-user-description">Delete <strong id="delete-user-name"></strong>? This permanently removes their account and access. Published content will be retained. Pending submissions will be closed.</p>
<form method="post" action="/admin/users.php" id="delete-user-form">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" id="delete-user-id">
<label class="form-label" for="delete-user-password">Your current Super Admin password</label><input type="password" class="form-control mb-3" name="current_password" id="delete-user-password" autocomplete="current-password" required>
<div class="gnc-dialog-actions"><button type="button" class="gnc-dialog-cancel" id="cancel-delete-user">Cancel</button><button type="submit" class="gnc-dialog-accept">Delete user</button></div></form></div></dialog>
<script src="/assets/js/user-deletion.js?v=<?= filemtime(__DIR__.'/../assets/js/user-deletion.js') ?>"></script>
<dialog id="role-user-dialog" class="gnc-dialog" aria-labelledby="role-user-title" aria-describedby="role-user-description"><div class="gnc-dialog-content">
<h2 id="role-user-title">Confirm role change</h2><p id="role-user-description">Change <strong id="role-user-name"></strong> to <strong id="role-user-value"></strong>? Enter your current Super Admin password to save the change.</p>
<form method="post" action="/admin/users.php" id="role-user-form">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="role"><input type="hidden" name="user_id" id="role-user-id"><input type="hidden" name="role" id="role-user-selected">
<label class="form-label" for="role-user-password">Your current Super Admin password</label><input type="password" class="form-control mb-3" name="current_password" id="role-user-password" autocomplete="current-password" maxlength="4096" required>
<div class="gnc-dialog-actions"><button type="button" class="gnc-dialog-cancel" id="cancel-role-user">Cancel</button><button type="submit" class="gnc-dialog-accept">Save role</button></div></form></div></dialog>
<script src="/assets/js/user-roles.js?v=<?= filemtime(__DIR__.'/../assets/js/user-roles.js') ?>"></script>
<?php if($temporaryPassword!==null):?>
<dialog id="temporary-password-dialog" class="gnc-dialog user-password-dialog" aria-labelledby="temporary-password-title" aria-describedby="temporary-password-description">
  <div class="user-password-heading"><span class="user-created-icon" aria-hidden="true">✓</span><div><h2 id="temporary-password-title">Account created</h2><p id="temporary-password-description">Save the temporary password and share it privately with the account owner.</p></div></div>
  <div class="user-password-body">
    <dl class="user-created-details">
      <div><dt>Name</dt><dd><?= $e($createdName) ?></dd></div>
      <div><dt>Email</dt><dd><?= $e($createdEmail) ?></dd></div>
      <div><dt>Role</dt><dd><?= $e($createdRole) ?></dd></div>
    </dl>
    <label class="form-label" for="temporary-password">Temporary password</label>
    <div class="user-password-control">
      <input class="form-control" id="temporary-password" type="password" value="<?= $e($temporaryPassword) ?>" readonly autocomplete="off" spellcheck="false" aria-describedby="temporary-password-reminder">
      <button type="button" class="btn btn-outline-secondary" id="toggle-temporary-password" aria-controls="temporary-password" aria-pressed="false">Show</button>
    </div>
    <p id="password-copy-status" role="status" aria-live="polite"></p>
    <div class="user-password-reminder" id="temporary-password-reminder"><strong>Shown only this once.</strong> Passwords are not emailed. Copy it securely before closing. The owner will verify their email and must change this password on first login.</div>
  </div>
  <div class="user-password-footer"><button class="btn btn-gnc-gold" id="copy-temporary-password" type="button" autofocus>Copy password</button><button class="btn btn-outline-secondary" type="button" id="close-temporary-password">Done</button></div>
</dialog>
<?php unset($temporaryPassword);endif;?>
<script src="/assets/js/user-create.js?v=<?= filemtime(__DIR__.'/../assets/js/user-create.js') ?>"></script>
<?php require __DIR__.'/../components/footer-admin.php';?>
