<?php
function firstLoginPasswordReady(): bool {
    try { getDB()->query('SELECT must_change_password,password_changed_at,credential_version FROM users LIMIT 0');return true; }
    catch(PDOException $e){return false;}
}
function generateTemporaryPassword(): string {
    // Cryptographically random characters; omit easily confused letters and digits.
    $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%*?';
    do {
        $password='';
        for($i=0;$i<12;$i++)$password.=$alphabet[random_int(0,strlen($alphabet)-1)];
    } while(!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/[0-9]/',$password)||!preg_match('/[^A-Za-z0-9]/',$password));
    return $password;
}
function validateNewPassword(string $password,string $confirmation): void {
    if(!hash_equals($password,$confirmation))throw new InvalidArgumentException('The new passwords do not match.');
    if(!preg_match('//u',$password)||preg_match_all('/./us',$password)<12||strlen($password)>72||preg_match('/[\x00-\x1f\x7f]/',$password))throw new InvalidArgumentException('Use a password between 12 and 72 characters (maximum 72 bytes) without control characters.');
    $simple=preg_replace('/[^a-z0-9]/','',strtolower($password));
    if(count(array_unique(preg_split('//u',$password,-1,PREG_SPLIT_NO_EMPTY)))<4||preg_match('/^(password|qwerty|letmein|welcome|admin|administrator|iloveyou|1234567890)+[0-9]*$/',$simple))throw new InvalidArgumentException('Choose a less common password or a longer, unique passphrase.');
}
function changeFirstLoginPassword(int $userId,string $current,string $new,string $confirm): void {
    changeUserPassword($userId,$current,$new,$confirm,true);
}
function changeUserPassword(int $userId,string $current,string $new,string $confirm,bool $firstLoginOnly=false): void {
    validateNewPassword($new,$confirm);
    $db=getDB();$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT password,must_change_password,credential_version,login_attempts,locked_until,status FROM users WHERE user_id=? FOR UPDATE');$q->execute([$userId]);$user=$q->fetch();
        if(!$user||$user['status']!=='active')throw new RuntimeException('This account is unavailable.');
        if($firstLoginOnly&&empty($user['must_change_password']))throw new RuntimeException('This account no longer requires a first-login password change.');
        if(!empty($user['locked_until'])&&strtotime($user['locked_until'])>time())throw new RuntimeException('Too many incorrect attempts. Please wait before trying again.');
        if(!password_verify($current,$user['password'])){
            $attempts=(!empty($user['locked_until'])?0:(int)$user['login_attempts'])+1;$lock=$attempts>=MAX_LOGIN_ATTEMPTS?date('Y-m-d H:i:s',time()+LOCKOUT_DURATION):null;
            $db->prepare('UPDATE users SET login_attempts=?,locked_until=? WHERE user_id=?')->execute([$attempts,$lock,$userId]);$db->commit();
            logActivity($userId,'PASSWORD_CHANGE_FAILED','users',$userId,'Incorrect current password.');
            if($lock)authAudit($userId,'LOGIN_LOCKOUT','Password verification locked for 15 minutes after five failures.');
            throw new InvalidArgumentException($firstLoginOnly?'The current temporary password is incorrect.':'The current password is incorrect.');
        }
        if(password_verify($new,$user['password']))throw new InvalidArgumentException($firstLoginOnly?'Choose a new password different from your temporary password.':'Choose a new password different from your current password.');
        authCheckCompromisedPassword($new);
        $version=(int)$user['credential_version']+1;
        $db->prepare('UPDATE users SET password=?,must_change_password=0,password_changed_at=NOW(),credential_version=?,remember_token=NULL,remember_token_expires_at=NULL,otp_code=NULL,otp_expires_at=NULL,login_attempts=0,locked_until=NULL WHERE user_id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$version,$userId]);
        $db->prepare('DELETE FROM auth_password_resets WHERE user_id=?')->execute([$userId]);
        logActivity($userId,'PASSWORD_CHANGED','users',$userId,$firstLoginOnly?'Completed mandatory first-login password change; previous credentials revoked.':'Changed own password; previous credentials revoked.');
        $db->commit();
        if(session_status()===PHP_SESSION_ACTIVE)session_regenerate_id(true);
        $_SESSION['must_change_password']=false;$_SESSION['credential_version']=$version;
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
