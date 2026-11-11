<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
// Use a fresh anonymous session; never modify an existing browser session.
session_id(bin2hex(random_bytes(24)));
session_start();
$token=bin2hex(random_bytes(32));
$_SESSION=['_initiated'=>true,'last_activity'=>time()-SESSION_TIMEOUT-60,'csrf_token'=>$token];
$mode=$argv[1]??'anonymous';
if($mode==='signed-in'){$_SESSION['user_id']=1;$_SESSION['user_role']='Super Admin';}
if($mode==='otp')$_SESSION['temp_user_id']=1;
session_write_close();
register_shutdown_function(static function() use ($mode): void {
    if(in_array($mode,['signed-in','otp'],true)&&empty($_SESSION)&&session_status()!==PHP_SESSION_ACTIVE){
        echo "PASS: $mode session still expires after inactivity.\n";
        return;
    }
    if(empty($GLOBALS['loginWaitPassed'])){
        fwrite(STDERR,"FAIL: anonymous login wait triggered session expiry.\n");
        exit(1);
    }
});
initSession();
if($mode!=='anonymous')throw new RuntimeException('Authenticated or pending session did not expire.');
if(($_SESSION['csrf_token']??null)!==$token||isLoggedIn())throw new RuntimeException('Anonymous login state was not preserved.');
if(time()-(int)$_SESSION['last_activity']>5)throw new RuntimeException('Activity timestamp was not refreshed.');
session_destroy();
$GLOBALS['loginWaitPassed']=true;
echo "PASS: anonymous login survives inactivity while preserving CSRF and remaining signed out.\n";
