<?php
/** Delete an account only after reauthenticating the acting Super Admin. */
function deleteAdminUser(int $targetId, string $password): void {
    if (!hasPermission('delete_user') || !rbacIsSuper()) throw new RuntimeException('Only Super Admin can delete users.');
    $actorId = (int)$_SESSION['user_id'];
    if ($targetId < 1) throw new InvalidArgumentException('Choose a valid user.');
    if ($targetId === $actorId) throw new InvalidArgumentException('You cannot delete your own account.');
    if ($password === '' || strlen($password) > 4096) throw new InvalidArgumentException('Enter your current Super Admin password.');
    if (!authRateAllowed('delete-user-password', (string)$actorId, 5, 900)) throw new RuntimeException('Too many deletion attempts. Wait 15 minutes before trying again.');
    $db = getDB(); $db->beginTransaction();
    try {
        $admins = $db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
        $q = $db->prepare('SELECT password,role,status,credential_version,locked_until FROM users WHERE user_id=? FOR UPDATE');$q->execute([$actorId]);$actor = $q->fetch();
        if (!$actor || $actor['role'] !== 'Super Admin' || $actor['status'] !== 'active'
            || (int)$actor['credential_version'] !== (int)($_SESSION['credential_version'] ?? -1)) throw new RuntimeException('Sign in again before deleting a user.');
        if (!empty($actor['locked_until']) && strtotime($actor['locked_until']) > time()) throw new RuntimeException('Your account is temporarily locked. Please try again later.');
        if (!password_verify($password, $actor['password'])) {
            authAudit($actorId,'DELETE_USER_FAILED','User deletion refused: incorrect confirmation password.');
            // Audit must survive the rollback below.
            $db->commit();
            throw new InvalidArgumentException('Your current password is incorrect. The user was not deleted.');
        }
        $q = $db->prepare('SELECT user_id,role,status FROM users WHERE user_id=? FOR UPDATE');$q->execute([$targetId]);$target = $q->fetch();
        if (!$target) throw new InvalidArgumentException('User not found.');
        if ($target['role'] === 'Super Admin' && $target['status'] === 'active' && count($admins) <= 1) throw new RuntimeException('Keep at least one active Super Admin.');
        // Do not allow hosting schema differences to cascade-delete authored content.
        $refs = $db->query("SELECT k.TABLE_NAME,k.COLUMN_NAME,c.IS_NULLABLE,r.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
            JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=k.TABLE_SCHEMA AND c.TABLE_NAME=k.TABLE_NAME AND c.COLUMN_NAME=k.COLUMN_NAME
            WHERE k.REFERENCED_TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME='users' AND k.REFERENCED_COLUMN_NAME='user_id'")->fetchAll();
        foreach ($refs as $ref) {
            if ($ref['DELETE_RULE'] === 'SET NULL') continue;
            if (!preg_match('/^[a-zA-Z0-9_]+$/D',$ref['TABLE_NAME']) || !preg_match('/^[a-zA-Z0-9_]+$/D',$ref['COLUMN_NAME'])) {
                throw new RuntimeException('This account has protected records. Ask your developer to review its relationships before deleting it.');
            }
            $q = $db->prepare('SELECT 1 FROM `'.$ref['TABLE_NAME'].'` WHERE `'.$ref['COLUMN_NAME'].'`=? LIMIT 1');
            $q->execute([$targetId]);
            if (!$q->fetchColumn()) continue;
            if (!in_array($ref['DELETE_RULE'],['RESTRICT','NO ACTION'],true) || $ref['IS_NULLABLE'] !== 'YES') {
                throw new RuntimeException('This account has protected records. Ask your developer to review its relationships before deleting it.');
            }
            $db->prepare('UPDATE `'.$ref['TABLE_NAME'].'` SET `'.$ref['COLUMN_NAME'].'`=NULL WHERE `'.$ref['COLUMN_NAME'].'`=?')->execute([$targetId]);
        }
        $db->prepare('DELETE FROM auth_password_resets WHERE user_id=?')->execute([$targetId]);
        // Optional installations may not yet have these additive tables.
        foreach (['admin_onboarding','content_editor_drafts'] as $table) {
            try {$db->prepare("DELETE FROM `$table` WHERE user_id=?")->execute([$targetId]);}
            catch (PDOException $e) {if ($e->getCode() !== '42S02') throw $e;}
        }
        try {
            $db->prepare("UPDATE rbac_submissions SET status='rejected',reason='Account deleted by a Super Admin.',reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE user_id=? AND status IN ('pending','draft','failed')")->execute([$actorId,$targetId]);
        } catch (PDOException $e) {if ($e->getCode() !== '42S02') throw $e;}
        if (!logActivity($actorId,'DELETE_USER','users',$targetId,'Deleted user account #'.$targetId.'; authored content retained and pending submissions closed.')) throw new RuntimeException('Could not record the deletion. No account was deleted.');
        $db->prepare('DELETE FROM users WHERE user_id=?')->execute([$targetId]);
        $db->commit();
    } catch (Throwable $e) {if ($db->inTransaction()) $db->rollBack(); throw $e;}
}
