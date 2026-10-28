<?php
/** Local HTTP regression: event tokens must share MySQL's expiry clock. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';

function eventCsrfCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$db = getDB();
$user = $db->query("SELECT user_id, role, credential_version FROM users WHERE status='active' AND role='Super Admin' AND must_change_password=0 LIMIT 1")->fetch();
eventCsrfCheck((bool)$user, 'An active Super Admin is required.');
$eventId = (int)$db->query('SELECT event_id FROM events LIMIT 1')->fetchColumn();
eventCsrfCheck($eventId > 0, 'An existing event is required for the read check.');
// Reproduce hosting where PHP and MySQL use different time zones. Use a
// separate local PHP server so the normal development server is unaffected.
$dbOffset = (int)$db->query('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())')->fetchColumn();
$timezone = $dbOffset < 0 ? 'Pacific/Kiritimati' : 'Etc/GMT+12';
$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
eventCsrfCheck($listener !== false, 'Could not reserve a local test port.');
$address = stream_socket_get_name($listener, false);
fclose($listener);
$log = tmpfile();
$server = proc_open([PHP_BINARY, '-d', 'date.timezone=' . $timezone, '-S', $address, '-t', dirname(__DIR__)],
    [0=>['pipe', 'r'], 1=>$log, 2=>$log], $pipes, dirname(__DIR__));
eventCsrfCheck(is_resource($server), 'Could not start the local PHP test server.');
fclose($pipes[0]);
session_id('gnc-events-csrf-' . bin2hex(random_bytes(16)));
session_start();
$_SESSION = ['user_id'=>(int)$user['user_id'], 'user_role'=>$user['role'],
    'credential_version'=>(int)$user['credential_version'], '_initiated'=>true, 'last_activity'=>time()];
$sessionId = session_id();
$cookie = session_name() . '=' . $sessionId;
session_write_close();
$request = static function (?array $body, bool $authenticated = true) use ($cookie, $address): array {
    $curl = curl_init('http://' . $address . '/admin/events.php');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20]);
    if ($authenticated) curl_setopt($curl, CURLOPT_COOKIE, $cookie);
    if ($body !== null) curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body]);
    $response = curl_exec($curl);
    if ($response === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return [$status, $response];
};
$token = null;
try {
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    [$status, $html] = $request(null);
    eventCsrfCheck($status === 200 && (bool)preg_match('/id="csrfToken" value="([^"]+)"/', $html, $match), 'Event page renders a token.');
    $token = $match[1];
    $query = $db->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM csrf_tokens WHERE user_id=? AND token=?');
    $query->execute([$user['user_id'], $token]);
    $remaining = (int)$query->fetchColumn();
    eventCsrfCheck($remaining > 3500 && $remaining <= 3600, 'Fresh event token must have one hour remaining on the database clock; got ' . $remaining . ' seconds.');
    $body = ['ajax_action'=>'fetch_one', 'event_id'=>$eventId, 'csrf_token'=>$token];
    for ($i = 0; $i < 2; $i++) {
        [$status, $response] = $request($body);
        $data = json_decode($response, true);
        eventCsrfCheck($status === 200 && !empty($data['success']) && (int)$data['event']['event_id'] === $eventId, 'Repeated event clicks accept the same valid token.');
    }
    foreach (['fetch_one', 'create', 'update', 'delete'] as $action) {
        foreach (['', 'invalid'] as $invalid) {
            [, $response] = $request(array_merge($body, ['ajax_action'=>$action, 'csrf_token'=>$invalid]));
            $data = json_decode($response, true);
            eventCsrfCheck(isset($data['success']) && !$data['success'] && strpos($data['message'], 'security token') !== false, $action . ' rejects missing/invalid CSRF.');
        }
    }
    [$status, $response] = $request($body, false);
    eventCsrfCheck($status !== 200 || empty(json_decode($response, true)['success']), 'Unauthenticated event lookup rejected.');
    $db->prepare('UPDATE csrf_tokens SET expires_at=DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE user_id=? AND token=?')->execute([$user['user_id'], $token]);
    foreach (['fetch_one', 'create', 'update', 'delete'] as $action) {
        [, $response] = $request(array_merge($body, ['ajax_action'=>$action]));
        $data = json_decode($response, true);
        eventCsrfCheck(isset($data['success']) && !$data['success'] && strpos($data['message'], 'security token') !== false, $action . ' rejects expired CSRF.');
    }
} finally {
    if ($token !== null) $db->prepare('DELETE FROM csrf_tokens WHERE user_id=? AND token=?')->execute([$user['user_id'], $token]);
    session_id($sessionId); session_start(); $_SESSION = []; session_destroy();
    proc_terminate($server); proc_close($server); fclose($log);
}
echo "PASS: mismatched PHP/MySQL time zones, one-hour expiry, repeated event clicks, missing/invalid/expired tokens for all actions, and unauthenticated access. No events changed.\n";
