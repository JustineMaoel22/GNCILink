<?php
/** Real multipart publishing with Fileinfo disabled; all writes use temporary tables. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function announcementUploadAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$db = getDB();
$user = $db->query("SELECT user_id,credential_version FROM users WHERE role='Super Admin' AND status='active' AND must_change_password=0 LIMIT 1")->fetch();
announcementUploadAssert((bool)$user, 'An active local Super Admin is required.');
session_id('gnc-announcement-upload-' . bin2hex(random_bytes(12)));
initSession();
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['user_role'] = 'Super Admin';
$_SESSION['credential_version'] = (int)$user['credential_version'];
$token = generateCSRFToken(); $cookie = session_name() . '=' . session_id();
session_write_close();
$router = tempnam(sys_get_temp_dir(), 'gnc-announcement-router-');
$image = tempnam(sys_get_temp_dir(), 'gnc-announcement-image-');
$log = tmpfile(); $server = null; $port = random_int(20000,40000);
$setup = '<?php require ' . var_export(dirname(__DIR__) . '/config/config.php', true) . '; require '
    . var_export(dirname(__DIR__) . '/admin/admin-functions.php', true) . ';' . <<<'PHP'

$db=getDB();
foreach (['announcements','news','media_library','announcement_images','news_images','activity_logs','image_display_settings'] as $table) {
    $db->exec('CREATE TEMPORARY TABLE `upload_test_'.$table.'` LIKE `'.$table.'`');
    $db->exec('ALTER TABLE `upload_test_'.$table.'` RENAME TO `'.$table.'`');
}
register_shutdown_function(static function() use ($db) {
    $media=$db->query('SELECT file_path FROM media_library')->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['announcements'=>$db->query('SELECT status,featured_image FROM announcements')->fetchAll(),
        'news'=>$db->query('SELECT status,featured_image FROM news')->fetchAll(),
        'media'=>count($media),'gallery'=>(int)$db->query('SELECT COUNT(*) FROM announcement_images')->fetchColumn(),
        'errors'=>$_SESSION['flash_errors']??[],'fatal'=>error_get_last()['type']??null]);
    foreach ($media as $path) {
        if (preg_match('#^/uploads/(announcements|news)/[a-f0-9]{32}\.(jpg|png|gif|webp)$#D',$path)) @unlink(GNCI_UPLOAD_TEST_ROOT.$path);
    }
});
PHP;
file_put_contents($router, '<?php define("GNCI_UPLOAD_TEST_ROOT",' . var_export(dirname(__DIR__),true) . '); ?>' . $setup
    . 'require GNCI_UPLOAD_TEST_ROOT . (str_contains($_SERVER["REQUEST_URI"],"create-news") ? "/admin/action/create-news.php" : "/admin/action/create-announcement.php");');
$post = static function(array $fields, ?string $upload = null, bool $authenticated = true, bool $gallery = false, bool $news = false) use ($port,$cookie): array {
    if ($upload) $fields['image'] = new CURLFile($upload,'image/png','photo.png');
    if ($gallery) $fields['gallery_images[0]'] = new CURLFile($upload,'image/png','extra.png');
    $curl = curl_init('http://127.0.0.1:'.$port.'/admin/action/create-'.($news?'news':'announcement').'.php');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,
        CURLOPT_POSTFIELDS=>$fields,CURLOPT_COOKIE=>$authenticated?$cookie:'']);
    $body=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    announcementUploadAssert(is_string($body), 'HTTP request failed.');
    return [$status,json_decode($body,true,512,JSON_THROW_ON_ERROR)];
};
try {
    $command=[PHP_BINARY,'-d','display_errors=0','-d','disable_classes=finfo','-d','disable_functions=finfo_open','-S','127.0.0.1:'.$port,$router];
    $server=proc_open($command,[0=>['pipe','r'],1=>$log,2=>$log],$pipes,dirname(__DIR__));
    announcementUploadAssert(is_resource($server),'Could not start local PHP server.'); fclose($pipes[0]);
    for ($i=0;$i<50;$i++) { $probe=@fsockopen('127.0.0.1',$port,$errno,$error,.1); if($probe){fclose($probe);break;} usleep(100000); }
    file_put_contents($image,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII='));
    $fields=['action'=>'create','title'=>'Isolated upload test','content'=>'<p>Test body</p>','status'=>'published','program'=>'ALL','publish_destination'=>'website','csrf_token'=>$token];
    foreach ([false,true] as $news) {
        foreach (['draft','published'] as $state) {
            [$status,$result]=$post(array_replace($fields,['status'=>$state]),$image,true,!$news,$news);
            $rows=$result[$news?'news':'announcements'];
            announcementUploadAssert($status===302 && count($rows)===1 && $rows[0]['status']===$state && $rows[0]['featured_image']>0,'Image must save with requested visibility: '.json_encode($result));
            announcementUploadAssert($result['media']===($news?1:2) && ($news || $result['gallery']===1),'Featured/gallery images must be saved.');
        }
    }
    [$status,$result]=$post($fields);
    announcementUploadAssert($status===302 && count($result['announcements'])===1 && $result['media']===0,'Image remains optional.');
    [$status,$result]=$post(array_replace($fields,['csrf_token'=>'invalid']),$image);
    announcementUploadAssert($status===302 && !$result['announcements'] && $result['media']===0,'Invalid CSRF must prevent writes.');
    [$status,$result]=$post($fields,$image,false);
    announcementUploadAssert($status===302 && !$result['announcements'] && $result['media']===0,'Anonymous upload must prevent writes.');
    file_put_contents($image,'<?php echo "fake PNG";');
    [$status,$result]=$post($fields,$image);
    announcementUploadAssert($status===302 && !$result['announcements'] && $result['media']===0 && $result['errors'],'Spoofed PNG must be rejected without a fatal error.');
    echo "PASS: announcement featured/gallery and news images, draft/publish, optional image, spoofed image, CSRF and anonymous requests without Fileinfo; temporary tables only.\n";
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    @unlink($router); @unlink($image); fclose($log);
}
