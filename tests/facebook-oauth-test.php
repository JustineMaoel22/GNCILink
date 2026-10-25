<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/facebook-oauth.php';
$allowed = true;
function hasPermission(string $permission): bool { return $GLOBALS['allowed']; }
function logActivity(...$args): bool { return true; }
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
function rejected(callable $work, string $contains): void {
    try { $work(); } catch (FacebookPublishingError $e) { check(str_contains($e->getMessage(), $contains), 'Wrong error: '.$e->getMessage()); return; }
    throw new RuntimeException('Expected rejection: '.$contains);
}
foreach (['PAGE_ID'=>'123456','PAGE_TOKEN'=>'legacy-dummy','APP_ID'=>'789','APP_SECRET'=>'dummy-secret','ENVIRONMENT'=>'test','REDIRECT_URI'=>'http://localhost:8080/admin/action/facebook-oauth.php','TOKEN_KEY'=>base64_encode(random_bytes(32))] as $key=>$value) putenv('FB_PUBLISH_'.$key.'='.$value);
$_SESSION=['user_id'=>1];
$db=getDB();
$migration=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../sql/facebook-publishing-migration.sql'));
foreach(explode(';',str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$migration)) as $sql) if(trim($sql)!=='')$db->exec($sql);
$db->exec("INSERT INTO facebook_publishing_connection VALUES(1,'123456','Previously connected Test Page','test',1,1,NOW())");
check(facebookOAuthReady(),'OAuth schema ready');
$encrypted=facebookTokenEncrypt('secret-never-displayed');
check(!str_contains($encrypted,'secret-never-displayed') && facebookTokenDecrypt($encrypted)==='secret-never-displayed','Encrypted token roundtrip');
$raw=base64_decode($encrypted);$raw[30]=chr(ord($raw[30])^1);
rejected(fn()=>facebookTokenDecrypt(base64_encode($raw)),'could not be read');
$url=facebookOAuthStart();parse_str(parse_url($url,PHP_URL_QUERY),$query);
check($query['response_type']==='code' && !isset($query['access_token']) && $query['scope']==='pages_show_list,pages_read_engagement,pages_manage_posts','Authorization code flow and scopes, no token in browser URL');
rejected(fn()=>facebookOAuthConsumeState('wrong-state'),'could not be verified');
rejected(fn()=>facebookOAuthConsumeState($query['state']),'could not be verified');
facebookOAuthStart();$state=$_SESSION['facebook_oauth_state']['value'];$_SESSION['facebook_oauth_state']['expires']=time()-1;
rejected(fn()=>facebookOAuthConsumeState($state),'expired');
facebookOAuthStart();$state=$_SESSION['facebook_oauth_state']['value'];$_SESSION['user_id']=2;
rejected(fn()=>facebookOAuthConsumeState($state),'verified');$_SESSION['user_id']=1;
facebookOAuthStart();$state=$_SESSION['facebook_oauth_state']['value'];facebookOAuthConsumeState($state);
rejected(fn()=>facebookOAuthConsumeState($state),'verified');
putenv('FB_PUBLISH_REDIRECT_URI=http://unsafe.example/admin/action/facebook-oauth.php');
rejected(fn()=>facebookOAuthSettings(),'one-time setup');
putenv('FB_PUBLISH_REDIRECT_URI=http://localhost:8080/admin/action/facebook-oauth.php');
$calls=[];$failure=false;$batches=0;
$graph=function($method,$path,$params,$auth=null)use(&$calls,&$failure,&$batches){
    check($method==='GET','Connection never sends publishing writes');$calls[]=$path;
    if($path==='oauth/access_token')return ['access_token'=>isset($params['code'])?'short-user-token':'long-user-token'];
    if($path==='me/accounts'){
        check($auth==='long-user-token','User token used only on backend for Page listing');
        $batches++;
        if($batches===1)return ['data'=>[['id'=>'999','name'=>'Official Page excluded during testing','access_token'=>'other-token','tasks'=>['CREATE_CONTENT']]],'paging'=>['next'=>'https://untrusted.invalid/ignored','cursors'=>['after'=>'next-batch']]];
        check(($params['after']??'')==='next-batch','Cursor pagination uses fixed Graph endpoint, never provider next URL');
        return ['data'=>[['id'=>'123456','name'=>'Test Page','access_token'=>'page-secret-token','tasks'=>['CREATE_CONTENT']]]];
    }
    if($failure)throw new FacebookPublishingError('Simulated validation failure');
    if($path==='debug_token'){
        check($params['input_token']==='page-secret-token','Selected Page token validated');
        return ['data'=>['is_valid'=>true,'app_id'=>'789','type'=>'PAGE','scopes'=>['pages_show_list','pages_read_engagement','pages_manage_posts']]];
    }
    check($path==='me' && $auth==='page-secret-token','Selected Page identity uses selected token, never legacy token');
    return ['id'=>'123456','name'=>'Test Page'];
};
facebookOAuthComplete('auth-code',$graph);
$pages=facebookOAuthPending();check(count($pages)===1&&isset($pages['123456'])&&!isset($pages['999']),'Only test Page offered');
check(!str_contains(json_encode($_SESSION),'page-secret-token')&&!str_contains(json_encode($_SESSION),'long-user-token'),'Session contains only encrypted tokens');
check((int)$db->query('SELECT COUNT(*) FROM facebook_publishing_tokens')->fetchColumn()===0,'Sign-in alone does not connect or persist credentials');
rejected(fn()=>facebookOAuthConnect('999',true,$graph),'Choose an authorized Page');
rejected(fn()=>facebookOAuthConnect('123456',false,$graph),'confirm');
$allowed=false;rejected(fn()=>facebookOAuthConnect('123456',true,$graph),'Super Admin');$allowed=true;
$failure=true;rejected(fn()=>facebookOAuthConnect('123456',true,$graph),'Simulated');
check((int)$db->query('SELECT COUNT(*) FROM facebook_publishing_tokens')->fetchColumn()===0,'Failed validation leaves connection unchanged');
check(facebookPublishingConnection()['page_name']==='Previously connected Test Page'&&facebookPublishingConnection()['enabled']==1,'Failed replacement preserves existing active connection');
$failure=false;facebookOAuthConnect('123456',true,$graph);
$stored=$db->query('SELECT * FROM facebook_publishing_tokens')->fetch();
check($stored['encrypted_token']!=='page-secret-token'&&facebookTokenDecrypt($stored['encrypted_token'])==='page-secret-token','Only ciphertext stored');
check(facebookPublishingCredentials()['token']==='page-secret-token','Publisher uses connected OAuth token instead of legacy env');
check(facebookPublishingConnection()['enabled']==1&&facebookOAuthPending()===[],'Confirmed Page connected, pending selection consumed');
check((int)$db->query('SELECT COUNT(*) FROM announcement_facebook_deliveries')->fetchColumn()===0,'Connection never reposts announcements');
rejected(fn()=>facebookOAuthConnect('123456',true,$graph),'Choose an authorized Page');
putenv('FB_PUBLISH_PAGE_ID=999');rejected(fn()=>facebookPublishingCredentials(),'allowed Facebook destination changed');putenv('FB_PUBLISH_PAGE_ID=123456');
putenv('FB_PUBLISH_ENVIRONMENT=official');check(facebookOAuthPageAllowed('999'),'Official mode permits explicit future selection');
rejected(fn()=>facebookPublishingCredentials(),'destination changed');putenv('FB_PUBLISH_ENVIRONMENT=test');
facebookOAuthDisconnect();
check(facebookPublishingConnection()['enabled']==0&&(int)$db->query('SELECT COUNT(*) FROM facebook_publishing_tokens')->fetchColumn()===0,'Disconnect removes local OAuth credentials and disables connection');
echo "PASS: encrypted token storage/tamper checks, OAuth state/expiry/replay/user binding, callback URL validation, server-only exchanges/cursor pagination, test Page restriction, selection/authorization, failed connection preservation, publisher credentials, no reposting and disconnect. Temporary tables; no real Meta requests or saved-site changes.\n";
