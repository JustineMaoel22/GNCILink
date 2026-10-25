<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../includes/facebook-publishing.php';
$scenario = $argv[1] ?? 'facebook';
if (!in_array($scenario, ['website','facebook','both','update','editor','csrf','confirmation','replay'], true)) throw new RuntimeException('Invalid scenario');
$db = getDB();
// Shadows real tables for this PDO connection. Existing content is never changed.
foreach (['announcements','activity_logs','announcement_images'] as $table) {
    $db->exec("CREATE TEMPORARY TABLE `facebook_test_$table` LIKE `$table`");
    $db->exec("ALTER TABLE `facebook_test_$table` RENAME TO `$table`");
}
$migration = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/../sql/facebook-publishing-migration.sql'));
foreach (explode(';', str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $migration)) as $sql) if (trim($sql) !== '') $db->exec($sql);
$db->exec("INSERT INTO facebook_publishing_connection VALUES(1,'123456','Test Page','test',1,1,NOW())");
foreach (['PAGE_ID'=>'123456','PAGE_TOKEN'=>'dummy-never-sent','APP_ID'=>'789','APP_SECRET'=>'','ENVIRONMENT'=>'test'] as $key=>$value) putenv('FB_PUBLISH_'.$key.'='.$value);
session_id('gnc-facebook-handler-' . bin2hex(random_bytes(12)));
initSession();
$userId=(int)$db->query("SELECT user_id FROM users WHERE status='active' LIMIT 1")->fetchColumn();
if (!$userId) throw new RuntimeException('A local user is required');
$_SESSION['user_id']=$userId;
$_SESSION['user_role']=$scenario==='editor'?'Content Editor':'Super Admin';
$csrf=generateCSRFToken();
$_SERVER['REQUEST_METHOD']='POST';
$_POST=['action'=>'create','title'=>'Isolated handler test','content'=>'<p>Test body</p>','status'=>'published','program'=>'ALL',
    'csrf_token'=>$csrf,'publish_destination'=>$scenario==='website'?'website':($scenario==='both'?'both':'facebook'),
    'facebook_page_id'=>'123456','facebook_confirmed'=>'1','facebook_request_key'=>str_repeat('b',64)];
if ($scenario==='csrf') $_POST['csrf_token']='invalid';
if ($scenario==='confirmation') $_POST['facebook_confirmed']='0';
if (in_array($scenario,['update','replay'],true)) {
    $id=createAnnouncement(['title'=>'Original','content'=>'Body','status'=>'published','program'=>'ALL']);
    $_POST['announcement_id']=$id;
    if ($scenario==='update') {$_POST['action']='update';$db->exec('UPDATE announcements SET is_pinned=1 WHERE announcement_id='.(int)$id);}
    else $db->prepare('INSERT INTO facebook_publishing_requests VALUES(?,?,?,NOW())')->execute([$_POST['facebook_request_key'],$userId,$id]);
}
register_shutdown_function(function () use ($db,$scenario) {
    try {
        $rows=$db->query('SELECT * FROM announcements')->fetchAll();
        $blocked=in_array($scenario,['editor','csrf','confirmation'],true);
        if (count($rows)!==($blocked?0:1)) throw new RuntimeException('Unexpected saved record count');
        if (!$blocked) {
            $expected=in_array($scenario,['website','both','replay'],true)?'published':'draft';
            if ($rows[0]['status']!==$expected) throw new RuntimeException('Wrong website visibility');
            if ($scenario==='update' && $rows[0]['is_pinned']) throw new RuntimeException('Facebook Only retained homepage pin');
            if (!in_array($scenario,['website','replay'],true)) {
                $delivery=$db->query('SELECT * FROM announcement_facebook_deliveries')->fetch();
                if (!$delivery || $delivery['state']!=='failed') throw new RuntimeException('Failed preflight not recorded');
                if (!str_contains(implode(' ', $_SESSION['flash_errors']??[]),'FB_PUBLISH_APP_SECRET')) throw new RuntimeException('Expected safe failure before any Meta call');
                if ((int)$db->query('SELECT COUNT(*) FROM facebook_publishing_requests')->fetchColumn()!==1) throw new RuntimeException('Missing request receipt');
            }
        }
        echo 'PASS: '.$scenario." handler scenario, real announcement functions with temporary tables; no live API calls or persisted content changes.\n";
    } catch (Throwable $error) {fwrite(STDERR,'FAIL: '.$error->getMessage()."\n");exit(1);}
    finally {$_SESSION=[];session_destroy();}
});
require __DIR__ . '/../admin/action/create-announcement.php';
