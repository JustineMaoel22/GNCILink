<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
$db=getDB();
// Connection-local shadow tables keep all real settings and audit records intact.
$db->exec('CREATE TEMPORARY TABLE admin_settings (setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT NOT NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE activity_logs (log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),record_id INT NULL,description TEXT,created_at DATETIME) ENGINE=InnoDB');
session_start();$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','csrf_token'=>str_repeat('a',64),'_initiated'=>true];
$mode=$argv[1]??'helpers';
if($mode!=='helpers'){
    $_SERVER['REQUEST_METHOD']='POST';$_SERVER['HTTP_HOST']='localhost';
    $_POST=['action'=>'save_page_suggestions','page_suggestions_enabled'=>'0','csrf_token'=>$_SESSION['csrf_token']];
    if($mode==='worker')$_SESSION['_rbac_worker']=['route'=>'/admin/settings.php'];
    else $_SERVER['HTTP_X_REQUESTED_WITH']='XMLHttpRequest';
    if($mode==='csrf')$_POST['csrf_token']='invalid';
    if($mode==='invalid')$_POST['page_suggestions_enabled']=[];
    if($mode==='unknown')$_POST['action']='unknown';
    if($mode==='ai')$_POST=['action'=>'save_ai_image_checker','ai_image_checker_enabled'=>'0','csrf_token'=>$_SESSION['csrf_token']];
    ob_start();
    register_shutdown_function(function()use($db,$mode){
        $raw=ob_get_clean();$result=json_decode($raw,true);
        if(!is_array($result))throw new RuntimeException('Settings endpoint must return JSON.');
        $success=in_array($mode,['ajax','worker','ai'],true);
        if(($result['success']??null)!==$success)throw new RuntimeException('Unexpected settings response.');
        $audits=(int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
        if($audits!==($success?1:0))throw new RuntimeException('Audit record mismatch.');
        if($success&&($result['value']??null)!=='0')throw new RuntimeException('Response does not match stored setting.');
        if(!$success&&(http_response_code()!==422||(int)$db->query('SELECT COUNT(*) FROM admin_settings')->fetchColumn()!==0))throw new RuntimeException('Invalid input changed settings.');
        echo "Settings endpoint $mode passed. No persistent data changed.\n";
    });
    require __DIR__.'/../admin/settings.php';exit;
}
$checks=0;
function settingsCheck(bool $condition,string $message): void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
function settingsRefuses(callable $call,string $message): void{try{$call();}catch(RuntimeException|InvalidArgumentException $e){settingsCheck(true,$message);return;}throw new RuntimeException($message);}
settingsCheck(pageSuggestionsEnabled()&&aiImageCheckerEnabled(),'Existing default behavior');
foreach(['savePageSuggestionsSetting'=>'page_suggestions_enabled','saveAiImageCheckerSetting'=>'ai_image_checker_enabled'] as $save=>$key){
    foreach(['0','1'] as $value){
        $save(['csrf_token'=>$_SESSION['csrf_token'],$key=>$value]);
        $q=$db->prepare('SELECT setting_value FROM admin_settings WHERE setting_key=?');$q->execute([$key]);settingsCheck($q->fetchColumn()===$value,'Stored toggle value');
    }
    foreach([null,[],true,'2',"0' OR 1=1"] as $value)settingsRefuses(fn()=>$save(['csrf_token'=>$_SESSION['csrf_token'],$key=>$value]),'Reject malformed toggle');
    settingsRefuses(fn()=>$save(['csrf_token'=>'bad',$key=>'0']),'Reject invalid CSRF');
    settingsRefuses(fn()=>$save(['csrf_token'=>[],$key=>'0']),'Reject array CSRF');
    $_SESSION['user_role']='The Mirror';settingsRefuses(fn()=>$save(['csrf_token'=>$_SESSION['csrf_token'],$key=>'0']),'Mirror cannot change global settings');
    $_SESSION['user_role']='Super Admin';
}
settingsCheck((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()===4,'Each successful change logged');
echo "$checks settings helper checks passed. No persistent data changed.\n";
