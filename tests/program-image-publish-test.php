<?php
// docker compose exec -T --user www-data web php tests/program-image-publish-test.php [without-fileinfo|low-limits]
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function imagePublishAssert(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$mode = $argv[1] ?? 'normal';
$db = getDB();
$prefix = 'UPLOADTEST-' . bin2hex(random_bytes(5));
$programId = 0; $process = null; $paths = [];
$log = tempnam(sys_get_temp_dir(), 'gnci-upload-log-');
$port = random_int(20000, 40000);
initSession();
$_SESSION['user_id'] = (int)$db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' LIMIT 1")->fetchColumn();
imagePublishAssert($_SESSION['user_id'] > 0, 'An active local Super Admin is required.');
$_SESSION['user_role'] = 'Super Admin';
$_SESSION['credential_version'] = (int)$db->query('SELECT credential_version FROM users WHERE user_id='.(int)$_SESSION['user_id'])->fetchColumn();
$token = generateCSRFToken(); $sessionId = session_id(); $cookie = session_name() . '=' . $sessionId;
session_write_close();
function imagePublishPost(array $fields, ?string $path = null, bool $authenticated = true): array {
    global $port, $cookie, $prefix;
    if ($path) $fields['file_0'] = new CURLFile($path, 'image/png', $prefix . '.png');
    $curl = curl_init('http://127.0.0.1:' . $port . '/admin/action/program-page-handler.php');
    curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>20, CURLOPT_POSTFIELDS=>$fields,
        CURLOPT_COOKIE=>$authenticated ? $cookie : '']);
    $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    curl_close($curl);
    return [$status, json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
try {
    $dept = (int)$db->query("SELECT dept_id FROM departments WHERE department_type='college' AND archived_at IS NULL LIMIT 1")->fetchColumn();
    $programId = createProgramPage(['program_code'=>'T'.bin2hex(random_bytes(4)), 'program_name'=>$prefix, 'dept_id'=>$dept]);
    $command = [PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=1',
        '-d', 'upload_max_filesize=' . ($mode === 'low-limits' ? '2M' : '64M'),
        '-d', 'post_max_size=' . ($mode === 'low-limits' ? '4M' : '256M'),
        '-d', 'disable_classes=' . ($mode === 'without-fileinfo' ? 'Imagick,finfo' : 'Imagick')];
    if ($mode === 'without-fileinfo') array_push($command, '-d', 'disable_functions=finfo_open');
    array_push($command, '-S', '127.0.0.1:'.$port, '-t', dirname(__DIR__));
    $process = proc_open($command, [0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']], $pipes);
    imagePublishAssert(is_resource($process), 'Test PHP server failed to start.');
    fclose($pipes[0]);
    for ($i=0; $i<50; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $error, .1);
        if ($probe) { fclose($probe); break; }
        usleep(100000);
    }
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII=');
    $fixture = function(int $size) use ($png, &$paths): string {
        $path = tempnam(sys_get_temp_dir(), 'gnci-upload-'); $paths[] = $path;
        file_put_contents($path, $png . str_repeat("\0", $size - strlen($png)));
        return $path;
    };
    $small = $fixture(3*1024*1024); $boundary = $fixture(5*1024*1024); $large = $fixture(5*1024*1024+1);
    $fields = ['action'=>'save_program_items', 'program_id'=>$programId, 'item_type'=>'uniform',
        'csrf_token'=>$token, 'items'=>json_encode([['item_id'=>0,'title'=>'Test image']])];
    [$status] = imagePublishPost($fields, $small, false);
    imagePublishAssert($status===401, 'Anonymous upload must be rejected.');
    [$status,$result] = imagePublishPost(array_merge($fields,['csrf_token'=>'invalid']), $small);
    imagePublishAssert($status===403, 'Invalid CSRF token must be rejected: '.json_encode([$status,$result]));
    if ($mode === 'low-limits') {
        [$status,$result] = imagePublishPost($fields,$small);
        imagePublishAssert(empty($result['success']) && str_contains($result['error'], 'upload_max_filesize: 2M'), 'PHP file limit must be explained: '.json_encode($result));
        [$status,$result] = imagePublishPost($fields,$boundary);
        imagePublishAssert($status===413 && str_contains($result['error'], 'post_max_size: 4M'), 'Discarded body must report request limit, not CSRF.');
    } else {
        foreach (['uniform','facility','hero_slide'] as $type) {
            $fields['item_type']=$type;
            $fields['items']=json_encode([['item_id'=>0,'title'=>'Test image']]);
            $savedId=null;
            foreach ([$small,$boundary] as $path) {
                [$status,$result]=imagePublishPost($fields,$path);
                imagePublishAssert(!empty($result['success']) && count($result['items'])===1, "$type must publish a valid image: ".json_encode($result));
                if ($savedId!==null) imagePublishAssert($savedId===$result['items'][0]['item_id'], 'Replacing an image must retain its item ID.');
                $savedId=$result['items'][0]['item_id'];
                $fields['items']=json_encode([['item_id'=>$savedId,'title'=>'Test image']]);
                $url=$result['items'][0]['image_url'];
                imagePublishAssert(is_file(dirname(__DIR__).$url), 'Published file must exist.');
                imagePublishAssert(hash_file('sha256',dirname(__DIR__).$url)===hash_file('sha256',$path), 'Original image bytes must be preserved.');
            }
            $before=$db->query('SELECT COUNT(*) FROM media_library')->fetchColumn();
            [$status,$result]=imagePublishPost($fields,$large);
            imagePublishAssert(empty($result['success']) && str_contains($result['error'],'5 MB'), 'Without an optimizer, oversized images must be rejected with the 5 MB limit: '.json_encode($result));
            imagePublishAssert($before===$db->query('SELECT COUNT(*) FROM media_library')->fetchColumn(), 'Rejected image must not leave a media row.');
            $published=programPageItems($programId)[$type];
            imagePublishAssert(count($published)===1 && $published[0]['image_url']===$url, 'Rejected replacement must retain the published image.');
        }
        $invalid=tempnam(sys_get_temp_dir(),'gnci-invalid-');$paths[]=$invalid;file_put_contents($invalid,'<?php echo "fake image";');
        [$status,$result]=imagePublishPost($fields,$invalid);
        imagePublishAssert(empty($result['success']), 'A fake PNG must be rejected.');
    }
    $passed = true;
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if ($programId) {
        $banner=$db->prepare("SELECT image_path FROM program_items WHERE program_id=? AND item_type='hero_slide'");$banner->execute([$programId]);
        foreach($banner->fetchAll(PDO::FETCH_COLUMN) as $path) programPageDeleteBannerFile($path);
        $db->prepare('DELETE FROM program_items WHERE program_id=?')->execute([$programId]);
        $db->prepare('DELETE FROM programs WHERE program_id=?')->execute([$programId]);
        $db->prepare("DELETE FROM activity_logs WHERE (table_name='programs' AND record_id=?) OR description LIKE ?")->execute([$programId,'%'.$prefix.'%']);
    }
    $query=$db->prepare('SELECT media_id FROM media_library WHERE file_name=?');$query->execute([$prefix.'.png']);
    foreach($query->fetchAll(PDO::FETCH_COLUMN) as $id) deleteMediaIfUnreferenced((int)$id);
    foreach($paths as $path) @unlink($path);
    @unlink($log);
    session_id($sessionId);session_start();session_destroy();
}
if (!empty($passed)) echo "PASS: $mode image publication, size boundaries, preserved files, authorization and CSRF.\n";
