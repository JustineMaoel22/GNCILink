<?php
/** Shared single-host request budgets. No sessions, SQL, raw IPs or payloads in storage. */
function gncRequestIp(): string {
    $peer = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $trusted = array_filter(array_map('trim', explode(',', (string)getenv('REQUEST_TRUSTED_PROXIES'))));
    $header = (string)getenv('REQUEST_CLIENT_IP_HEADER');
    if ($header !== '' && in_array($peer, $trusted, true)) {
        $candidate = trim(explode(',', (string)($_SERVER[$header] ?? ''))[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) $peer = $candidate;
    }
    return filter_var($peer, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($peer)) : 'unknown';
}

/** Fixed windows start at first use; denied requests never extend the window.
 * 256 bounded shards avoid one file per attacker-controlled IP and unbounded cleanup.
 * @return array{allowed:bool,retry_after:int,first_denial:bool}
 */
function gncRequestConsume(string $key, int $limit, int $seconds, ?int $now = null, ?string $directory = null): array {
    $now = $now ?? time();
    $directory = $directory ?? (getenv('REQUEST_LIMIT_DIR') ?: sys_get_temp_dir().'/gnci-requests-'.substr(hash('sha256', __DIR__),0,16));
    if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Limiter unavailable');
    $hash = hash('sha256', $key);
    $file = @fopen($directory.'/'.substr($hash,0,2).'.json','c+');
    if (!$file) throw new RuntimeException('Limiter unavailable');
    $locked = false;
    try {
        $deadline = microtime(true)+0.1;
        do {
            $locked = flock($file,LOCK_EX|LOCK_NB);
            if ($locked) break;
            usleep(2000);
        } while (microtime(true)<$deadline);
        if (!$locked) throw new RuntimeException('Limiter busy');
        $raw = stream_get_contents($file,1048577);
        $rows = $raw === '' ? [] : json_decode($raw,true);
        if (!is_array($rows) || strlen($raw)>1048576) throw new RuntimeException('Limiter storage invalid');
        foreach ($rows as $id=>$row) if (($row['expires']??0)<=$now) unset($rows[$id]);
        if (!isset($rows[$hash]) && count($rows)>=4096) throw new RuntimeException('Limiter capacity reached');
        $row = $rows[$hash] ?? ['count'=>0,'expires'=>$now+$seconds,'reported'=>false];
        $allowed = $row['count']<$limit;
        $first = !$allowed && !$row['reported'];
        if ($allowed) $row['count']++;
        else $row['reported']=true;
        $rows[$hash]=$row;
        $json=json_encode($rows,JSON_THROW_ON_ERROR);
        rewind($file);
        if (!ftruncate($file,0) || fwrite($file,$json)!==strlen($json) || !fflush($file)) throw new RuntimeException('Limiter write failed');
        return ['allowed'=>$allowed,'retry_after'=>max(1,$row['expires']-$now),'first_denial'=>$first];
    } finally {
        if ($locked) flock($file,LOCK_UN);
        fclose($file);
    }
}

function gncRequestReject(int $status, int $retry): void {
    http_response_code($status);
    header('Cache-Control: no-store');
    header('Retry-After: '.$retry);
    header('Content-Type: application/json; charset=utf-8');
    $message=$status===429 ? 'Too many requests. Please wait '.$retry.' seconds before trying again.' : 'This service is temporarily busy. Please wait a minute and try again.';
    $route=gncRequestRoute();
    if (str_contains($_SERVER['HTTP_ACCEPT']??'','text/html') && !str_starts_with($route,'/api/') && !str_contains($route,'/action/') && !str_contains($route,'/chatbot/') && ($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='XMLHttpRequest') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Please try again later</title><body><main><h1>Please try again later</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p><p>Use your browser’s Back button to return to the previous page.</p></main></body></html>';
        exit;
    }
    echo json_encode(['success'=>false,'ok'=>false,'error'=>$message,'message'=>$message,'retry_after'=>$retry]);
    exit;
}

function gncRequestLimit(string $scope, string $identity, int $limit, int $seconds): void {
    try { $result=gncRequestConsume($scope.':'.$identity,$limit,$seconds); }
    catch (Throwable $e) {
        // Log at most once/minute per PHP worker host, without paths or request data.
        $marker=sys_get_temp_dir().'/gnci-limit-error-'.substr(hash('sha256',__DIR__),0,16);
        if (!is_file($marker) || @filemtime($marker)<time()-60) { @touch($marker); error_log('[request-protection] storage_unavailable'); }
        gncRequestReject(503,60);
    }
    if (!$result['allowed']) {
        if ($result['first_denial']) error_log('[request-protection] limited scope='.$scope.' bucket='.substr(hash('sha256',$scope.':'.$identity),0,16));
        gncRequestReject(429,$result['retry_after']);
    }
}

/** Provider budgets count actual attempts, including retries, across every entry point. */
function gncIntegrationReserve(string $provider, int $hourly, int $daily): bool {
    try {
        foreach ([['hour', $hourly,3600],['day',$daily,86400]] as [$window,$limit,$seconds]) {
            $result=gncRequestConsume('provider:'.$provider.':'.$window,$limit,$seconds);
            if (!$result['allowed']) {
                if ($result['first_denial']) error_log('[request-protection] provider_limited provider='.$provider);
                return false;
            }
        }
        return true;
    } catch(Throwable $e) { return false; }
}

/** Returns [policy, IP/minute, user/minute, IP/hour, user/hour]. */
function gncRequestPolicy(string $route, string $method, array $query, array $post, bool $upload): ?array {
    if ($route==='/chatbot/chatbot.php') return ['chatbot-transport',120,0,1800,0]; // Message allowance remains separate.
    if ($route==='/api/recommendations.php') return ['recommendations',120,0,2400,0];
    if ($route==='/api/calendar-day.php') return ['calendar',180,0,3600,0];
    if ($route==='/api/track-visit.php') return ['analytics',120,0,2400,0];
    if ($route==='/api/privacy-preferences.php') return ['privacy',120,0,2400,0];
    if ($route==='/pages/contact-us.php' && $method==='POST') return ['contact',5,0,20,0];
    if (str_starts_with($route,'/admin/')) {
        if (in_array($route,['/admin/auth/auth_handler.php','/admin/auth/verify-otp.php','/admin/forgot-password.php','/admin/reset-password.php'],true)) {
            if ($method!=='POST') return null;
            return ['authentication',20,20,100,100];
        }
        if ($route==='/admin/action/editor-presence-handler.php') return ['presence',600,180,12000,5000];
        if (in_array($route,['/admin/action/photo-check-handler.php','/admin/action/optimize-image.php'],true)) return ['image-processing',120,40,1200,400];
        if (str_contains($route,'facebook') || str_contains($route,'backfill')) {
            if ($method==='POST' || str_contains($route,'oauth')) return ['integration',60,20,600,120];
        }
        if ($upload) return ['uploads',180,60,1800,600];
        if ($route==='/admin/action/editor-draft-handler.php') return ['drafts',300,120,6000,2400];
        if ($method==='POST') return ['admin-write',600,240,12000,6000];
        if (str_contains($route,'/action/')) return ['admin-read',600,240,12000,6000];
    }
    foreach (['q','search','keyword','ajax','partial','evt_month','evt_year','program','department','page','p','category'] as $key) if (isset($query[$key])) return ['search-read',180,120,3600,2400];
    return null;
}

function gncRequestRoute(): string {
    $root=str_replace('\\','/',realpath(__DIR__.'/..'));
    $file=str_replace('\\','/',realpath($_SERVER['SCRIPT_FILENAME']??'') ?: '');
    return str_starts_with($file,$root.'/') ? substr($file,strlen($root)) : '';
}

function gncRequestBoundary(bool $user = false): void {
    if (PHP_SAPI==='cli') return;
    static $done=[];
    $kind=$user?'user':'ip';
    if (isset($done[$kind])) return;
    $done[$kind]=true;
    $route=gncRequestRoute();
    $policy=gncRequestPolicy($route,$_SERVER['REQUEST_METHOD']??'GET',$_GET,$_POST,!empty($_FILES));
    if (!$policy) return;
    [$name,$ipMinute,$userMinute,$ipHour,$userHour]=$policy;
    if ($user && empty($_SESSION['user_id'])) return;
    $identity=$user?'user:'.(int)$_SESSION['user_id']:'ip:'.gncRequestIp();
    $minute=$user?$userMinute:$ipMinute; $hour=$user?$userHour:$ipHour;
    if (!$minute) return;
    // Route buckets cannot be bypassed by changing query/action parameters or aliases.
    gncRequestLimit($name.':'.$route.':'.$kind.':minute',$identity,$minute,60);
    gncRequestLimit($name.':'.$route.':'.$kind.':hour',$identity,$hour,3600);
    if ($name==='uploads' || $name==='image-processing') gncRequestLimit('media-total:'.$kind,$identity,$user?800:2400,3600);
    if (!$user) register_shutdown_function(static function() use ($name,$route,$identity) {
        $status=http_response_code();
        if ($status<400 || $status===429) return;
        try { $sample=gncRequestConsume('log:'.$route.':'.$identity.':'.$status,1,60); }
        catch(Throwable $e) { return; }
        if ($sample['allowed']) error_log('[request-protection] failed policy='.$name.' status='.$status.' bucket='.substr(hash('sha256',$identity),0,16));
    });
}
