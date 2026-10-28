<?php
/** HTTP upload regression with Fileinfo disabled; all content tables are temporary. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/payment-guide-content.php';
function paymentPublishCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$db = getDB();
$user = $db->query("SELECT user_id,credential_version FROM users WHERE role='Super Admin' AND status='active' AND must_change_password=0 LIMIT 1")->fetch();
paymentPublishCheck((bool)$user, 'An active local admin is required.');
session_id('gnc-payment-test-' . bin2hex(random_bytes(12))); initSession();
$_SESSION['user_id'] = (int)$user['user_id']; $_SESSION['user_role'] = 'Super Admin';
$_SESSION['credential_version'] = (int)$user['credential_version'];
$token = generateCSRFToken(); $sessionId = session_id(); $cookie = session_name() . '=' . $sessionId;
session_write_close();
$router = tempnam(sys_get_temp_dir(), 'gnc-payment-router-');
$image = tempnam(sys_get_temp_dir(), 'gnc-payment-image-');
$log = tmpfile(); $server = null;
$port = random_int(20000,40000);
$withoutDom = ($argv[1] ?? '') === 'without-dom';
// Each HTTP request gets isolated temporary tables. Published records and
// existing artwork are never modified, including when validation fails.
$setup = '<?php set_exception_handler(static function($error){http_response_code(500);echo json_encode(["test_error"=>$error->getMessage()]);}); require ' . var_export(dirname(__DIR__) . '/components/payment-guide-content.php', true) . ';' . <<<'PHP'

$db=getDB();
foreach (['tuition_fee_content','activity_logs','image_display_settings'] as $table) {
    $schema=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
    // MySQL temporary tables cannot carry foreign keys. Keep columns and
    // indexes for handler compatibility, with no links to published records.
    $schema=preg_replace('/^\s*CONSTRAINT[^\n]*\n/m','',$schema);
    $schema=preg_replace('/,\n\)/',"\n)",$schema);
    $db->exec(preg_replace('/^CREATE TABLE /','CREATE TEMPORARY TABLE ',$schema));
}
register_shutdown_function(static function() {
    foreach ($GLOBALS['newFiles'] ?? [] as $path) {
        if (str_starts_with($path, dirname(__DIR__) . '/uploads/payment-guide/')) @unlink($path);
    }
});
PHP;
// The router lives in /tmp, so use the repository root for the cleanup guard.
$setup = str_replace("dirname(__DIR__) . '/uploads/payment-guide/'", var_export(dirname(__DIR__) . '/uploads/payment-guide/', true), $setup);
file_put_contents($router, $setup . 'require ' . var_export(dirname(__DIR__) . '/admin/action/payment-guide-handler.php', true) . ';');
$request = static function (array $fields, ?string $upload = null, bool $authenticated = true, string $extension = 'png') use ($port, $cookie): array {
    if ($upload !== null) $fields['image_gcash'] = new CURLFile($upload, $extension==='svg'?'image/svg+xml':'image/png', 'illustration.' . $extension);
    $curl = curl_init('http://127.0.0.1:' . $port . '/admin/action/payment-guide-handler.php');
    curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20,
        CURLOPT_POSTFIELDS=>$fields, CURLOPT_COOKIE=>$authenticated ? $cookie : '']);
    $body = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    curl_close($curl);
    return [$status, json_decode($body, true)];
};
try {
    $server = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'disable_classes=' . ($withoutDom ? 'finfo,DOMDocument' : 'finfo'), '-d', 'disable_functions=finfo_open',
        '-S', '127.0.0.1:' . $port, $router], [0=>['pipe','r'],1=>$log,2=>$log], $pipes, dirname(__DIR__));
    paymentPublishCheck(is_resource($server), 'Local test server failed to start.'); fclose($pipes[0]);
    for ($i=0;$i<50;$i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $error, .1);
        if ($probe) { fclose($probe); break; } usleep(100000);
    }
    file_put_contents($image, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII='));
    $fields = ['csrf_token'=>$token, 'images'=>json_encode(['gcash'=>['reset'=>false]]), 'texts'=>'{}'];
    [$status, $result] = $request($fields, $image);
    if ($withoutDom) {
        paymentPublishCheck($status===422 && str_contains($result['error'] ?? '', 'DOM/XML'), 'Unavailable sanitizer has an actionable hosting error.');
    } else {
    paymentPublishCheck($status===200 && !empty($result['success']), 'Raster illustration must publish without Fileinfo: HTTP ' . $status . ' ' . json_encode($result));
    file_put_contents($image, '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="#176b42"/></svg>');
    [$status, $result] = $request($fields, $image, true, 'svg');
    paymentPublishCheck($status===200 && !empty($result['success']), 'SVG illustration must publish without Fileinfo: ' . json_encode($result));
    file_put_contents($image, '<html>Not an SVG illustration</html>');
    [$status, $result] = $request($fields, $image, true, 'svg');
    paymentPublishCheck($status===422 && empty($result['success']), 'Invalid SVG rejected.');
    [$status] = $request(array_merge($fields, ['csrf_token'=>'invalid']), $image);
    paymentPublishCheck($status===403, 'Invalid CSRF rejected.');
    [$status] = $request($fields, $image, false);
    paymentPublishCheck($status===403, 'Unauthenticated publishing rejected.');
    file_put_contents($image, '<?php echo "fake illustration";');
    [$status, $result] = $request($fields, $image);
    paymentPublishCheck($status===422 && empty($result['success']), 'Non-image disguised as PNG rejected.');
    [$status, $result] = $request(['csrf_token'=>$token, 'images'=>json_encode(['gcash'=>['reset'=>true]]), 'texts'=>'{}']);
    paymentPublishCheck($status===200 && !empty($result['success']), 'Restore defaults still works.');
    }
} catch (Throwable $error) {
    rewind($log); fwrite(STDERR, stream_get_contents($log)); throw $error;
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    @unlink($router); @unlink($image); fclose($log);
    session_id($sessionId); session_start(); $_SESSION=[]; session_destroy();
}
echo $withoutDom ? "PASS: missing DOM/XML returns an actionable hosting error.\n" : "PASS: payment illustration publication without Fileinfo, invalid-image rejection, CSRF, authorization and restore. Temporary content tables and uploaded files cleaned up.\n";
