<?php
require_once __DIR__.'/protected-admin.php';
/** Role changes require the acting Super Admin's current password on every save. */
function changeAdminUserRole(int $targetId,string $role,string $password): bool {
    if(!rbacIsSuper()||!hasPermission('assign_roles'))throw new RuntimeException('Only Super Admin can change user roles.');
    if(!in_array($role,['Super Admin','Content Editor','The Mirror'],true))throw new InvalidArgumentException('Choose a valid role.');
    $actorId=(int)$_SESSION['user_id'];
    if($targetId<1)throw new InvalidArgumentException('Choose a valid user.');
    if($targetId===$actorId&&$role!=='Super Admin')throw new RuntimeException('You cannot remove your own Super Admin access.');
    if($password===''||strlen($password)>4096)throw new InvalidArgumentException('Enter your current Super Admin password to save this role change.');
    if(!authRateAllowed('assign-role-password',(string)$actorId,5,900))throw new RuntimeException('Too many confirmation attempts. Wait 15 minutes before trying again.');
    $db=getDB();$db->beginTransaction();
    try {
        $admins=$db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $q=$db->prepare('SELECT password,role,status,credential_version,locked_until FROM users WHERE user_id=? FOR UPDATE');$q->execute([$actorId]);$actor=$q->fetch(PDO::FETCH_ASSOC);
        if(!$actor||$actor['role']!=='Super Admin'||$actor['status']!=='active'||(int)$actor['credential_version']!==(int)($_SESSION['credential_version']??-1))throw new RuntimeException('Sign in again before changing a user role.');
        if(!empty($actor['locked_until'])&&strtotime($actor['locked_until'])>time())throw new RuntimeException('Your account is temporarily locked. Please try again later.');
        if(!password_verify($password,$actor['password'])) {
            authAudit($actorId,'ASSIGN_ROLE_FAILED','Role change refused: incorrect confirmation password.');
            $db->commit(); // Retain the failure audit; no role has been changed.
            throw new InvalidArgumentException('Your current password is incorrect. The role was not changed.');
        }
        $q=$db->prepare('SELECT email,role,status FROM users WHERE user_id=? FOR UPDATE');$q->execute([$targetId]);$target=$q->fetch(PDO::FETCH_ASSOC);
        if(!$target)throw new InvalidArgumentException('Account not found.');
        if(isProtectedDefaultAdminUser($target))throw new RuntimeException('The Default Admin role is protected and cannot be changed.');
        if($target['role']==='Super Admin'&&$target['status']==='active'&&$role!=='Super Admin'&&count($admins)<=1)throw new RuntimeException('Keep at least one active Super Admin.');
        $changed=rbacRole($target['role'])!==$role;
        if($changed){
            $db->prepare('UPDATE users SET role=?,remember_token=NULL,remember_token_expires_at=NULL WHERE user_id=?')->execute([$role,$targetId]);
            if(!logActivity($actorId,'ASSIGN_ROLE','users',$targetId,'Changed role from '.$target['role'].' to '.$role))throw new RuntimeException('Could not record the role change. No permissions were changed.');
        }
        $db->prepare('DELETE FROM auth_rate_limits WHERE bucket_hash=?')->execute([hash('sha256','assign-role-password:'.$actorId)]);
        $db->commit();return $changed;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
