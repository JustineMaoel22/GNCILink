<?php
// CLI-only checks; synthetic sessions only, no database changes or real logins.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';

if (($argv[1] ?? '') === 'child') {
    $expired = ($argv[2] ?? '') === 'expired';
    $storageLifetime = (int)($argv[3] ?? 300);
    ini_set('session.gc_probability', '0');
    ini_set('session.gc_maxlifetime', (string)$storageLifetime);
    ini_set('session.cookie_lifetime', '300');
    session_id('gnci-lifetime-test-' . bin2hex(random_bytes(16)));
    session_start();
    $_SESSION = ['user_id'=>1, 'user_role'=>'Super Admin', '_initiated'=>true,
        'last_activity'=>time()-($expired ? SESSION_TIMEOUT+1 : 301), 'csrf_token'=>'synthetic-token'];
    session_write_close();
    register_shutdown_function(function () use ($expired) {
        $result = ['logged_in'=>isLoggedIn(), 'timeout'=>SESSION_TIMEOUT,
            'storage_lifetime'=>(int)ini_get('session.gc_maxlifetime'),
            'cookie_lifetime'=>(int)session_get_cookie_params()['lifetime'],
            'csrf_preserved'=>($_SESSION['csrf_token'] ?? '') === 'synthetic-token',
            'activity_updated'=>abs(time()-(int)($_SESSION['last_activity'] ?? 0))<=2];
        if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION=[]; session_destroy(); }
        echo json_encode($result);
    });
    initSession();
    exit;
}

foreach ([['active',300], ['active',3600], ['expired',300]] as [$mode,$storageLifetime]) {
    $process = proc_open([PHP_BINARY,__FILE__,'child',$mode,(string)$storageLifetime],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start session check.');
    fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $status=proc_close($process);
    $result=json_decode($output,true);
    if ($status!==0 || !is_array($result)) throw new RuntimeException('Session check failed: '.$error);
    if ($result['timeout']<=301) throw new RuntimeException('This environment explicitly configures an idle timeout of five minutes or less.');
    if ($result['storage_lifetime']<max($result['timeout'],$storageLifetime) || $result['cookie_lifetime']!==0)
        throw new RuntimeException('PHP session retention does not match the application timeout.');
    if ($result['logged_in']!==($mode==='active')) throw new RuntimeException('Idle session expiry was incorrect.');
    if ($mode==='active' && (!$result['csrf_preserved'] || !$result['activity_updated']))
        throw new RuntimeException('A five-minute-old session lost its token or did not refresh its activity.');
}
echo "PASS: five-minute-old sessions remain valid, activity and CSRF survive, short cookie/storage defaults are corrected, longer retention is preserved, and expired sessions are rejected. Synthetic sessions removed.\n";
