<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/admin-help.php';
$checks=0;
function checkHelp(bool $condition,string $message): void {
    global $checks;if(!$condition)throw new RuntimeException($message);$checks++;
}
foreach(['Super Admin','Content Editor','The Mirror','Content Admin','Office Staff'] as $role){
    $_SESSION=['user_id'=>1,'user_role'=>$role];
    $topics=adminHelpTopics();$ids=array_column($topics,'id');
    checkHelp(count($ids)===count(array_unique($ids)),'Unique help IDs');
    checkHelp(in_array('getting-started',$ids,true)&&in_array('account-security',$ids,true),'Basic help available to all roles');
    checkHelp(in_array('users',$ids,true)===rbacIsSuper(),'User management visibility');
    checkHelp(in_array('activity-logs',$ids,true)===rbacIsSuper(),'Log visibility');
    checkHelp(in_array('events',$ids,true)===(rbacRole()!=='The Mirror'),'Event visibility');
    checkHelp(in_array('content-editor',$ids,true)===(rbacRole()!=='The Mirror'),'Content editor visibility');
    foreach($topics as $topic){
        checkHelp(!empty($topic['steps'])&&!empty($topic['faqs'])&&!empty($topic['summary']),'Complete guide content');
        if($topic['route']){
            checkHelp(rbacRouteAllowed($role,$topic['route']),'Help destination allowed by role');
            checkHelp(is_file(__DIR__.'/..'.$topic['route']),'Help destination exists');
        }
    }
    $results=adminHelpSearch($topics,'How do I reset my password?');
    checkHelp(($results[0]['id']??'')==='account-security','Natural phrase search');
    checkHelp((adminHelpSearch($topics,'publish an announcement')[0]['id']??'')==='announcements','Task search ranking');
    checkHelp(!adminHelpSearch($topics,'no such topic abcxyz'),'No matching results');
    checkHelp(!adminHelpSearch($topics,'<script>alert(12345)</script>'),'Search input stays data');
    checkHelp(!adminHelpSearch($topics,'how do i'),'Stopwords do not return unrelated guides');
    $results=adminHelpSearch($topics,'calendar import');
    checkHelp((bool)$results===(rbacRole()!=='The Mirror'),'Search never leaks restricted topics');
    $submissions=$topics[array_search('submissions',$ids,true)];
    checkHelp($submissions['title']===(rbacIsSuper()?'Approvals':'My Submissions'),'Role-specific submission title');
}
echo "$checks help content, search and permission checks passed. No database records changed.\n";
