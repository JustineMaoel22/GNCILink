<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/enrollment-procedures-content.php';
function stepHttpCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$sessionId = 'gnc-steps-test-' . bin2hex(random_bytes(12));
$token = bin2hex(random_bytes(32));
$userId = (int)getDB()->query("SELECT user_id FROM users WHERE status='active' AND role='Super Admin' LIMIT 1")->fetchColumn();
stepHttpCheck($userId > 0, 'An active admin is required');
function stepTestSession(string $id, int $userId, string $token, string $role): string {
    session_id($id); session_start();
    $_SESSION = ['user_id'=>$userId, 'user_role'=>$role, '_initiated'=>true, 'last_activity'=>time(), 'csrf_token'=>$token];
    $cookie = session_name() . '=' . $id;
    session_write_close();
    return $cookie;
}
function stepTestRequest(array $body, string $cookie, string $route = '/admin/enrollment-steps.php'): array {
    $curl = curl_init('http://localhost' . $route);
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_COOKIE=>$cookie,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60]);
    $result = curl_exec($curl);
    if ($result === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    return [$status,$result];
}
$good = tempnam(sys_get_temp_dir(),'gnc-step-svg-'); $bad = tempnam(sys_get_temp_dir(),'gnc-step-bad-');
$cookie = stepTestSession($sessionId,$userId,$token,'Super Admin');
$before = basicEnrollmentSteps(); $revision = basicEnrollmentRevision($before);
$folder = dirname(__DIR__) . '/uploads/enrollment-procedures';
$beforeFiles = glob($folder.'/*') ?: [];
$beforeMedia = (int)getDB()->query('SELECT COUNT(*) FROM media_library')->fetchColumn();
try {
    [$status] = stepTestRequest([], ''); stepHttpCheck($status === 302, 'Anonymous request redirects to login');
    [$status] = stepTestRequest(['csrf_token'=>'bad'], $cookie); stepHttpCheck($status === 403, 'Bad CSRF refused');
    $restricted = stepTestSession($sessionId,$userId,$token,'Content Editor');
    [$status] = stepTestRequest(['csrf_token'=>$token], $restricted); stepHttpCheck($status === 403, 'Text-only editor cannot publish steps');
    $cookie = stepTestSession($sessionId,$userId,$token,'Super Admin');
    [$status] = stepTestRequest(['csrf_token'=>$token,'revision'=>$revision,'steps'=>'{broken'], $cookie); stepHttpCheck($status === 422, 'Malformed JSON refused');
    $idA = bin2hex(random_bytes(16)); $idB = bin2hex(random_bytes(16));
    $rows = array_merge($before,[['step_id'=>$idA,'title'=>'TEST upload','location'=>'','instruction'=>'TEST upload rollback','illustration'=>''],['step_id'=>$idB,'title'=>'TEST invalid upload','location'=>'','instruction'=>'TEST upload rollback','illustration'=>'']]);
    file_put_contents($good,'<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script><rect width="10" height="10" fill="green" onload="alert(1)"/></svg>');
    file_put_contents($bad,'not an image');
    // The first SVG is processed; the second invalid file forces atomic rollback and file cleanup.
    [$status,$body] = stepTestRequest(['csrf_token'=>$token,'revision'=>$revision,'steps'=>json_encode($rows),'illustration_'.$idA=>new CURLFile($good,'image/svg+xml','test.svg'),'illustration_'.$idB=>new CURLFile($bad,'image/png','bad.png')],$cookie);
    stepHttpCheck($status === 422 && str_contains($body,'Upload an SVG, PNG, JPG, or WEBP'), 'Invalid file rejected after first SVG: '.$body);
    stepHttpCheck(basicEnrollmentSteps() === $before, 'Upload failure does not publish partial steps');
    stepHttpCheck((int)getDB()->query('SELECT COUNT(*) FROM media_library')->fetchColumn() === $beforeMedia, 'Uploaded media record rolled back');
    stepHttpCheck((glob($folder.'/*') ?: []) === $beforeFiles, 'Failed request leaves no new illustration files');
    stepHttpCheck(sanitizeSvgFile($good), 'Shared SVG sanitizer accepts Storyset-style SVG');
    $sanitized = file_get_contents($good);
    stepHttpCheck(!str_contains($sanitized,'<script') && !str_contains($sanitized,'onload='), 'SVG active content removed');
    $images = enrollmentIllustrations();
    $changes = ['fresh-1'=>['reset'=>false,'expected'=>$images['fresh-1']['url']], 'fresh-2'=>['reset'=>false,'expected'=>$images['fresh-2']['url']]];
    [$status,$body] = stepTestRequest(['csrf_token'=>$token,'images'=>json_encode($changes),'image_fresh-1'=>new CURLFile($good,'image/svg+xml','test.svg'),'image_fresh-2'=>new CURLFile($bad,'image/png','bad.png')],$cookie,'/admin/action/enrollment-illustrations-handler.php');
    stepHttpCheck($status === 422, 'Invalid illustration batch refused');
    stepHttpCheck(enrollmentIllustrations() === $images, 'Failed inline upload batch preserves all published illustrations');
    stepHttpCheck((int)getDB()->query('SELECT COUNT(*) FROM media_library')->fetchColumn() === $beforeMedia, 'Inline media records rolled back');
    stepHttpCheck((glob($folder.'/*') ?: []) === $beforeFiles, 'Inline upload failure leaves no new files');
    $newId=bin2hex(random_bytes(16));
    $newStep=['step_id'=>$newId,'title'=>'TEST canvas upload','location'=>'','instruction'=>'TEST atomic rollback','illustration'=>'/assets/images/svg/enrollment-procedures/fresh-1.svg'];
    $canvasChanges=['basic-'.$newId=>['reset'=>false,'expected'=>$newStep['illustration']],'fresh-2'=>['reset'=>false,'expected'=>$images['fresh-2']['url']]];
    [$status,$body]=stepTestRequest(['csrf_token'=>$token,'steps'=>json_encode(array_merge($before,[$newStep])),'revision'=>$revision,'images'=>json_encode($canvasChanges),'image_basic-'.$newId=>new CURLFile($good,'image/svg+xml','test.svg'),'image_fresh-2'=>new CURLFile($bad,'image/png','bad.png')],$cookie,'/admin/action/enrollment-canvas-handler.php');
    stepHttpCheck($status===422,'Invalid canvas upload refused');
    stepHttpCheck(basicEnrollmentSteps()===$before && enrollmentIllustrations()===$images,'Failed upload rolls back canvas step creation and illustrations together');
    stepHttpCheck((int)getDB()->query('SELECT COUNT(*) FROM media_library')->fetchColumn()===$beforeMedia,'Canvas media records rolled back');
    stepHttpCheck((glob($folder.'/*') ?: [])===$beforeFiles,'Canvas rollback removes stored upload files');
    [$status]=stepTestRequest(['csrf_token'=>$token,'steps'=>'null','images'=>'{}'],$cookie,'/admin/action/enrollment-canvas-handler.php');
    stepHttpCheck($status===422,'Invalid null step collection refused');
    $restricted = stepTestSession($sessionId,$userId,$token,'Content Editor');
    [$status] = stepTestRequest(['csrf_token'=>$token,'images'=>'{}'],$restricted,'/admin/action/enrollment-illustrations-handler.php');
    stepHttpCheck($status === 403, 'Text-only editor cannot publish inline illustrations');
    [$status]=stepTestRequest(['csrf_token'=>$token,'steps'=>'[]','revision'=>$revision,'images'=>'{}'],$restricted,'/admin/action/enrollment-canvas-handler.php');
    stepHttpCheck($status===403,'Text-only editor cannot publish canvas steps');

} finally {
    @unlink($good); @unlink($bad);
    session_id($sessionId); session_start(); $_SESSION=[]; session_destroy();
}
echo "PASS: HTTP login/permission/CSRF/JSON checks, SVG sanitization, invalid upload rejection, and atomic upload rollback. Live steps and media unchanged.\n";
