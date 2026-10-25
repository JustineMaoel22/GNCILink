<?php
require_once __DIR__ . '/facebook-publishing.php';

function facebookOAuthReady(): bool {
    if (!facebookPublishingReady()) return false;
    try { getDB()->query('SELECT connection_id FROM facebook_publishing_tokens LIMIT 1'); return true; }
    catch (PDOException $e) { return false; }
}

function facebookTokenKey(): string {
    $key = base64_decode((string)env('FB_PUBLISH_TOKEN_KEY', ''), true);
    if ($key === false || strlen($key) !== 32 || !function_exists('openssl_encrypt')) {
        throw new FacebookPublishingError('Facebook connection needs one-time setup by the site developer.');
    }
    return $key;
}

function facebookTokenEncrypt(string $token): string {
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($token, 'aes-256-gcm', facebookTokenKey(), OPENSSL_RAW_DATA, $iv, $tag, 'gnci-facebook-page-token', 16);
    if ($cipher === false) throw new FacebookPublishingError('Facebook access could not be stored securely. Please try again.');
    return base64_encode($iv . $tag . $cipher);
}

function facebookTokenDecrypt(string $encrypted): string {
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 29) throw new FacebookPublishingError('Stored Facebook access could not be read. Connect Facebook again.');
    $token = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', facebookTokenKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), 'gnci-facebook-page-token');
    if ($token === false || $token === '') throw new FacebookPublishingError('Stored Facebook access could not be read. Connect Facebook again.');
    return $token;
}

function facebookOAuthPageAllowed(string $id): bool {
    $mode = (string)env('FB_PUBLISH_ENVIRONMENT', 'test');
    if (!preg_match('/^\d+$/D', $id)) return false;
    if ($mode === 'official') return true;
    $testId = (string)env('FB_PUBLISH_PAGE_ID', '');
    return $mode === 'test' && preg_match('/^\d+$/D', $testId) && hash_equals($testId, $id);
}

function facebookOAuthSettings(): array {
    $appId = (string)env('FB_PUBLISH_APP_ID', '');
    $secret = (string)env('FB_PUBLISH_APP_SECRET', '');
    $redirect = (string)env('FB_PUBLISH_REDIRECT_URI', '');
    $version = (string)env('FB_PUBLISH_GRAPH_VERSION', 'v26.0');
    $parts = parse_url($redirect);
    $local = in_array($parts['host'] ?? '', ['localhost', '127.0.0.1'], true);
    if (!preg_match('/^\d+$/D', $appId) || $secret === '' || !filter_var($redirect, FILTER_VALIDATE_URL)
        || !in_array($parts['scheme'] ?? '', $local ? ['http', 'https'] : ['https'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || ($parts['path'] ?? '') !== '/admin/action/facebook-oauth.php' || !preg_match('/^v\d+\.0$/D', $version)
        || !in_array(env('FB_PUBLISH_ENVIRONMENT', 'test'), ['test', 'official'], true)
        || (env('FB_PUBLISH_ENVIRONMENT', 'test') === 'test' && !facebookOAuthPageAllowed((string)env('FB_PUBLISH_PAGE_ID', '')))) {
        throw new FacebookPublishingError('Facebook connection needs one-time setup by the site developer.');
    }
    facebookTokenKey();
    return compact('appId', 'secret', 'redirect', 'version');
}

function facebookOAuthStart(): string {
    if (!hasPermission('manage_settings')) throw new FacebookPublishingError('Only a Super Admin can connect Facebook.');
    if (!facebookOAuthReady()) throw new FacebookPublishingError('Facebook connection needs a database update by the site developer.');
    $config = facebookOAuthSettings();
    unset($_SESSION['facebook_oauth_pending']);
    $state = bin2hex(random_bytes(32));
    $_SESSION['facebook_oauth_state'] = ['value' => $state, 'user' => (int)$_SESSION['user_id'], 'expires' => time() + 600];
    return 'https://www.facebook.com/' . $config['version'] . '/dialog/oauth?' . http_build_query([
        'client_id' => $config['appId'], 'redirect_uri' => $config['redirect'], 'state' => $state,
        'response_type' => 'code', 'auth_type' => 'rerequest',
        'scope' => 'pages_show_list,pages_read_engagement,pages_manage_posts',
    ]);
}

function facebookOAuthConsumeState(string $state): void {
    $expected = $_SESSION['facebook_oauth_state'] ?? [];
    unset($_SESSION['facebook_oauth_state']); // One use, including failures/cancellation.
    if (!hasPermission('manage_settings') || empty($expected['value']) || ($expected['expires'] ?? 0) < time()
        || ($expected['user'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0) || !hash_equals($expected['value'], $state)) {
        throw new FacebookPublishingError('Facebook sign-in expired or could not be verified. Select Connect Facebook to try again.');
    }
}

/** Exchange codes and retrieve tokens server-side. Only Page names/IDs leave this layer. */
function facebookOAuthComplete(string $code, ?callable $graph = null): void {
    if (!hasPermission('manage_settings')) throw new FacebookPublishingError('Only a Super Admin can connect Facebook.');
    $graph = $graph ?? 'facebookGraphRequest';
    $config = facebookOAuthSettings();
    if ($code === '' || strlen($code) > 8192) throw new FacebookPublishingError('Facebook did not return a usable sign-in result. Please reconnect.');
    $appToken = $config['appId'] . '|' . $config['secret'];
    $short = $graph('GET', 'oauth/access_token', ['client_id' => $config['appId'], 'client_secret' => $config['secret'], 'redirect_uri' => $config['redirect'], 'code' => $code], $appToken);
    if (empty($short['access_token']) || !is_string($short['access_token'])) throw new FacebookPublishingError('Facebook sign-in could not be completed. Please reconnect.');
    $long = $graph('GET', 'oauth/access_token', ['grant_type' => 'fb_exchange_token', 'client_id' => $config['appId'], 'client_secret' => $config['secret'], 'fb_exchange_token' => $short['access_token']], $appToken);
    if (empty($long['access_token']) || !is_string($long['access_token'])) throw new FacebookPublishingError('Facebook sign-in could not be completed. Please reconnect.');
    $pages = [];
    $after = null;
    for ($batch = 0; $batch < 20; $batch++) {
        $params = ['fields' => 'id,name,access_token,tasks', 'limit' => 100];
        if ($after !== null) $params['after'] = $after;
        $result = $graph('GET', 'me/accounts', $params, $long['access_token']);
        foreach ($result['data'] ?? [] as $page) {
            $id = (string)($page['id'] ?? '');
            if (!facebookOAuthPageAllowed($id) || empty($page['access_token']) || !is_string($page['access_token']) || empty($page['name'])) continue;
            if (!array_intersect(['CREATE_CONTENT', 'MANAGE', 'PROFILE_PLUS_CREATE_CONTENT', 'PROFILE_PLUS_FULL_CONTROL'], $page['tasks'] ?? [])) continue;
            $pages[$id] = ['id' => $id, 'name' => (string)$page['name'], 'token' => facebookTokenEncrypt($page['access_token'])];
        }
        $after = !empty($result['paging']['next']) ? ($result['paging']['cursors']['after'] ?? null) : null;
        if (!is_string($after) || $after === '') break;
    }
    if (!$pages) throw new FacebookPublishingError('No eligible Page was found. Allow Page access during Facebook sign-in and make sure you can publish to the configured test Page.');
    $_SESSION['facebook_oauth_pending'] = ['pages' => $pages, 'user' => (int)$_SESSION['user_id'], 'expires' => time() + 600, 'mode' => (string)env('FB_PUBLISH_ENVIRONMENT', 'test')];
}

function facebookOAuthPending(): array {
    $pending = $_SESSION['facebook_oauth_pending'] ?? [];
    if (($pending['expires'] ?? 0) < time() || ($pending['user'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0)
        || ($pending['mode'] ?? '') !== env('FB_PUBLISH_ENVIRONMENT', 'test')) {
        unset($_SESSION['facebook_oauth_pending']); return [];
    }
    return $pending['pages'] ?? [];
}

function facebookOAuthConnect(string $pageId, bool $authorized, ?callable $graph = null): void {
    if (!hasPermission('manage_settings')) throw new FacebookPublishingError('Only a Super Admin can connect Facebook.');
    $pages = facebookOAuthPending();
    if (!$authorized || !isset($pages[$pageId]) || !facebookOAuthPageAllowed($pageId)) throw new FacebookPublishingError('Choose an authorized Page and confirm the destination before connecting.');
    facebookOAuthSettings();
    $token = facebookTokenDecrypt($pages[$pageId]['token']);
    $mode = (string)env('FB_PUBLISH_ENVIRONMENT', 'test');
    facebookPublishingLocked(function () use ($pageId, $token, $mode, $graph) {
        $page = facebookValidatePage($graph, ['page' => $pageId, 'token' => $token]);
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO facebook_publishing_tokens (connection_id,page_id,encrypted_token,environment,updated_at) VALUES (1,?,?,?,NOW()) ON DUPLICATE KEY UPDATE page_id=VALUES(page_id),encrypted_token=VALUES(encrypted_token),environment=VALUES(environment),updated_at=NOW()')
                ->execute([$pageId, facebookTokenEncrypt($token), $mode]);
            $db->prepare('INSERT INTO facebook_publishing_connection (connection_id,page_id,page_name,environment,enabled,connected_by,updated_at) VALUES (1,?,?,?,1,?,NOW()) ON DUPLICATE KEY UPDATE page_id=VALUES(page_id),page_name=VALUES(page_name),environment=VALUES(environment),enabled=1,connected_by=VALUES(connected_by),updated_at=NOW()')
                ->execute([$pageId, $page['name'], $mode, $_SESSION['user_id']]);
            $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        logActivity((int)$_SESSION['user_id'], 'FACEBOOK_CONNECT', null, null, 'Connected Page ' . $pageId . ' (' . $mode . ') using Facebook sign-in.');
    });
    unset($_SESSION['facebook_oauth_pending']);
}

function facebookOAuthDisconnect(): void {
    if (!hasPermission('manage_settings')) throw new FacebookPublishingError('Only a Super Admin can disconnect Facebook.');
    facebookPublishingLocked(function () {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->exec('UPDATE facebook_publishing_connection SET enabled=0,updated_at=NOW() WHERE connection_id=1');
            if (facebookOAuthReady()) $db->exec('DELETE FROM facebook_publishing_tokens WHERE connection_id=1');
            $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        logActivity((int)$_SESSION['user_id'], 'FACEBOOK_DISCONNECT', null, null, 'Facebook disconnected and locally stored OAuth credentials removed.');
    });
    unset($_SESSION['facebook_oauth_pending'], $_SESSION['facebook_oauth_state']);
}
