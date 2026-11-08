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
    if(in_array($mode,['delay','delay-invalid','delay-csrf'],true))$_POST=['action'=>'save_page_suggestion_delay','page_suggestion_delay_seconds'=>$mode==='delay-invalid'?'3601':'120','csrf_token'=>$mode==='delay-csrf'?'invalid':$_SESSION['csrf_token']];
    if(in_array($mode,['email','email-invalid','email-csrf'],true))$_POST=['action'=>'save_student_email_url','student_email_url'=>$mode==='email-invalid'?'javascript:alert(1)':'https://mail.example.edu/login?tenant=gnci','csrf_token'=>$mode==='email-csrf'?'invalid':$_SESSION['csrf_token']];
    ob_start();
    register_shutdown_function(function()use($db,$mode){
        $raw=ob_get_clean();$result=json_decode($raw,true);
        if(!is_array($result))throw new RuntimeException('Settings endpoint must return JSON.');
        $success=in_array($mode,['ajax','worker','ai','email','delay'],true);
        if(($result['success']??null)!==$success)throw new RuntimeException('Unexpected settings response.');
        $audits=(int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
        if($audits!==($success?1:0))throw new RuntimeException('Audit record mismatch.');
        if($success&&($result['value']??null)!==($mode==='email'?'https://mail.example.edu/login?tenant=gnci':($mode==='delay'?'120':'0')))throw new RuntimeException('Response does not match stored setting.');
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
settingsCheck(studentEmailUrl()===GNC_STUDENT_EMAIL_DEFAULT_URL,'Student email starts with the existing Microsoft URL');
$emailUrl='https://mail.example.edu/login?tenant=gnci&source=navbar';
saveStudentEmailUrlSetting(['csrf_token'=>$_SESSION['csrf_token'],'student_email_url'=>'  '.$emailUrl.'  ']);
settingsCheck(studentEmailUrl()===$emailUrl,'Student email URL is trimmed and persisted');
foreach([null,[],'','javascript:alert(1)','data:text/html,test','ftp://example.edu','//example.edu','https://user:password@example.edu/',"https://example.edu/\nprivate",'https://example.edu/\\path','https://example.edu/'.str_repeat('x',2048)] as $invalid){
    settingsRefuses(fn()=>saveStudentEmailUrlSetting(['csrf_token'=>$_SESSION['csrf_token'],'student_email_url'=>$invalid]),'Reject invalid/unsafe student email URLs');
}
settingsRefuses(fn()=>saveStudentEmailUrlSetting(['csrf_token'=>'bad','student_email_url'=>$emailUrl]),'Reject student email changes with invalid CSRF');
$_SESSION['user_role']='The Mirror';
settingsRefuses(fn()=>saveStudentEmailUrlSetting(['csrf_token'=>$_SESSION['csrf_token'],'student_email_url'=>$emailUrl]),'Mirror cannot change the student email URL');
$_SESSION['user_role']='Super Admin';
settingsCheck(studentEmailUrl()===$emailUrl,'Rejected changes leave the saved URL untouched');
settingsCheck((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()===5,'Student email changes are audited');
$_SERVER['REQUEST_URI']='/';$_SERVER['HTTP_HOST']='localhost';$_GET=[];
ob_start();require __DIR__.'/../components/navbar.php';$navbar=ob_get_clean();
preg_match_all('~<a\b[^>]*class="[^"]*btn-portal[^"]*"[^>]*href="([^"]+)"~',$navbar,$links);
settingsCheck(count($links[1])===2,'Desktop and mobile student email buttons exist');
foreach($links[1] as $link)settingsCheck(html_entity_decode($link,ENT_QUOTES|ENT_HTML5,'UTF-8')===$emailUrl,'Both student email buttons use the stored URL');
$db->exec("UPDATE admin_settings SET setting_value='javascript:alert(1)' WHERE setting_key='student_email_url'");
settingsCheck(studentEmailUrl()===GNC_STUDENT_EMAIL_DEFAULT_URL,'Unsafe stored data falls back to the original URL');
settingsCheck(pageSuggestionDelay()===60,'Missing suggestion delay preserves 60 second default');
foreach(['5','120','3600'] as $seconds){
    savePageSuggestionDelaySetting(['csrf_token'=>$_SESSION['csrf_token'],'page_suggestion_delay_seconds'=>$seconds]);
    settingsCheck(pageSuggestionDelay()===(int)$seconds,'Configured delay persists including boundaries');
}
foreach([null,[],true,120,'','0','4','3601','-5','1.5','1e2',' 60','999999999999'] as $invalid){
    settingsRefuses(fn()=>savePageSuggestionDelaySetting(['csrf_token'=>$_SESSION['csrf_token'],'page_suggestion_delay_seconds'=>$invalid]),'Reject invalid delay');
}
settingsRefuses(fn()=>savePageSuggestionDelaySetting(['csrf_token'=>'bad','page_suggestion_delay_seconds'=>'60']),'Delay requires CSRF');
foreach(['The Mirror','Content Editor'] as $role){
    $_SESSION['user_role']=$role;
    settingsRefuses(fn()=>savePageSuggestionDelaySetting(['csrf_token'=>$_SESSION['csrf_token'],'page_suggestion_delay_seconds'=>'60']),'Only Super Admin can change timing');
}
$_SESSION['user_role']='Super Admin';
settingsCheck(pageSuggestionDelay()===3600,'Rejected saves do not change timing');
settingsCheck((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()===8,'Successful delay changes are audited');
ob_start();require __DIR__.'/../components/recommendations.php';$popup=ob_get_clean();
settingsCheck(str_contains($popup,'data-delay-seconds="3600"'),'Public popup receives stored delay');
$db->exec("UPDATE admin_settings SET setting_value='bad' WHERE setting_key='page_suggestion_delay_seconds'");
settingsCheck(pageSuggestionDelay()===60,'Corrupt delay falls back to 60 seconds');
echo "$checks settings helper checks passed. No persistent data changed.\n";
