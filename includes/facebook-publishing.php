<?php
/** Outbound publishing only. Never uses the existing FB_ACCESS_TOKEN feed credentials. */
class FacebookPublishingError extends RuntimeException {
    public bool $uncertain;
    public function __construct(string $message, bool $uncertain = false) {
        parent::__construct($message);
        $this->uncertain = $uncertain;
    }
}

function facebookPublishingReady(): bool {
    try {
        getDB()->query('SELECT connection_id FROM facebook_publishing_connection LIMIT 1');
        getDB()->query('SELECT announcement_id FROM announcement_facebook_deliveries LIMIT 1');
        getDB()->query('SELECT request_key FROM facebook_publishing_requests LIMIT 1');
        return true;
    } catch (PDOException $e) { return false; }
}

function facebookPublishingConnection(): ?array {
    if (!facebookPublishingReady()) return null;
    return getDB()->query('SELECT * FROM facebook_publishing_connection WHERE connection_id = 1')->fetch() ?: null;
}

function facebookPublishingCredentials(): array {
    $page = (string)env('FB_PUBLISH_PAGE_ID', '');
    $token = (string)env('FB_PUBLISH_PAGE_TOKEN', '');
    $version = (string)env('FB_PUBLISH_GRAPH_VERSION', 'v26.0');
    $mode = (string)env('FB_PUBLISH_ENVIRONMENT', 'test');
    // OAuth connections keep credentials encrypted in backend storage. Legacy .env
    // connections continue working until an administrator chooses to reconnect.
    try {
        $stored = getDB()->query('SELECT page_id, encrypted_token, environment FROM facebook_publishing_tokens WHERE connection_id=1')->fetch();
    } catch (PDOException $e) {
        // Only a pre-upgrade database may use the legacy fallback. A storage
        // failure must not silently replace a confirmed OAuth credential.
        if ($e->getCode() !== '42S02') throw new FacebookPublishingError('Stored Facebook access is unavailable. Please contact the site developer.');
        $stored = false;
    }
    if ($stored) {
        require_once __DIR__ . '/facebook-oauth.php';
        if ($stored['environment'] !== $mode || !facebookOAuthPageAllowed($stored['page_id'])) {
            throw new FacebookPublishingError('The allowed Facebook destination changed. Ask a Super Admin to reconnect Facebook.');
        }
        $page = $stored['page_id'];
        $token = facebookTokenDecrypt($stored['encrypted_token']);
    }
    if (!preg_match('/^\d+$/D', $page) || $token === '' || !preg_match('/^v\d+\.0$/D', $version)
        || !in_array($mode, ['test', 'official'], true)) {
        throw new FacebookPublishingError('Configure the FB_PUBLISH_* settings in the server .env first.');
    }
    return compact('page', 'token', 'version', 'mode');
}

/** No redirects, token in Authorization header, no raw provider errors in output/logs. */
function facebookGraphRequest(string $method, string $path, array $params, ?string $auth = null): array {
    $version = (string)env('FB_PUBLISH_GRAPH_VERSION', 'v26.0');
    if (!preg_match('/^v\d+\.0$/D', $version)) throw new FacebookPublishingError('Ask the site developer to check Facebook API configuration.');
    $auth = $auth ?? facebookPublishingCredentials()['token'];
    $secret = (string)env('FB_PUBLISH_APP_SECRET', '');
    if ($secret !== '' && !str_contains($auth, '|')) $params['appsecret_proof'] = hash_hmac('sha256', $auth, $secret);
    if (!function_exists('curl_init')) throw new FacebookPublishingError('PHP cURL is required for Facebook publishing.');
    $url = 'https://graph.facebook.com/' . $version . '/' . $path;
    if ($method === 'GET') $url .= '?' . http_build_query($params);
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $auth]]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        $multipart = (bool)array_filter($params, fn($value) => $value instanceof CURLFile);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $params : http_build_query($params));
    }
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if ($raw === false || $status >= 500 || !is_array($data)) {
        throw new FacebookPublishingError('Meta did not confirm the result. Check the Page before retrying.', $method === 'POST');
    }
    if (isset($data['error']) || $status >= 400) {
        $code = (int)($data['error']['code'] ?? 0);
        $message = $code === 190 ? 'Facebook access expired or was revoked. Open Facebook Integration and select Connect Facebook again.'
            : (in_array($code, [10, 200], true) ? 'Facebook permission denied. Check Page access and pages_show_list, pages_read_engagement, pages_manage_posts.'
            : (in_array($code, [4, 17, 32, 613], true) ? 'Facebook rate limit reached. Wait before retrying.' : 'Facebook rejected the request (code ' . $code . '). Check the token, Page permissions and content.'));
        throw new FacebookPublishingError($message, $method === 'POST' && !empty($data['error']['is_transient']));
    }
    return $data;
}

function facebookValidatePage(?callable $graph = null, ?array $credentials = null): array {
    $graph = $graph ?? 'facebookGraphRequest';
    $config = $credentials ?? facebookPublishingCredentials();
    $appId = (string)env('FB_PUBLISH_APP_ID', '');
    $secret = (string)env('FB_PUBLISH_APP_SECRET', '');
    if ($appId === '' || $secret === '') throw new FacebookPublishingError('Set FB_PUBLISH_APP_ID and FB_PUBLISH_APP_SECRET to verify token permissions.');
    $debug = $graph('GET', 'debug_token', ['input_token' => $config['token']], $appId . '|' . $secret)['data'] ?? [];
    if (empty($debug['is_valid'])) {
        throw new FacebookPublishingError('Facebook access is invalid or expired. Open Facebook Integration and select Connect Facebook again.');
    }
    if ((string)($debug['app_id'] ?? '') !== $appId) {
        throw new FacebookPublishingError('The token was issued by a different Meta app. FB_PUBLISH_APP_ID and FB_PUBLISH_APP_SECRET must belong to the app selected when generating this Page token.');
    }
    if (($debug['type'] ?? '') !== 'PAGE') {
        throw new FacebookPublishingError(($debug['type'] ?? '') === 'USER'
            ? 'FB_PUBLISH_PAGE_TOKEN contains a User Access Token. In Graph API Explorer, select your test Page under Page Access Tokens and copy that Page token into .env.'
            : 'Meta did not identify this token as a Page Access Token. Generate a Page token for your test Page using the configured Meta app.');
    }
    foreach (['expires_at', 'data_access_expires_at'] as $field) {
        if (!empty($debug[$field]) && (int)$debug[$field] <= time()) throw new FacebookPublishingError('Facebook access has expired. Open Facebook Integration and select Connect Facebook again.');
    }
    foreach (['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'] as $scope) {
        if (!in_array($scope, $debug['scopes'] ?? [], true)) throw new FacebookPublishingError('Missing Facebook permission: ' . $scope . '. Connect Facebook again and allow the requested Page permissions.');
        foreach ($debug['granular_scopes'] ?? [] as $grant) {
            if (($grant['scope'] ?? '') === $scope && isset($grant['target_ids']) && !in_array($config['page'], $grant['target_ids'], true)) {
                throw new FacebookPublishingError('The token does not authorize this Page.');
            }
        }
    }
    $page = $graph('GET', 'me', ['fields' => 'id,name'], $config['token']);
    if (($page['id'] ?? '') !== $config['page']) throw new FacebookPublishingError('The Page token does not match FB_PUBLISH_PAGE_ID. Nothing was published.');
    return $page;
}

/** Serialize publishing and connection changes; also works across PHP workers. */
function facebookPublishingLocked(callable $work) {
    $db = getDB();
    if ((int)$db->query("SELECT GET_LOCK('gnci_facebook_publishing', 0)")->fetchColumn() !== 1) {
        throw new FacebookPublishingError('Another Facebook operation is running. Please try again shortly.');
    }
    try { return $work(); }
    finally { $db->query("SELECT RELEASE_LOCK('gnci_facebook_publishing')"); }
}

function facebookPublishingLink(string $link): string {
    $link = trim($link);
    if ($link !== '' && (strlen($link) > 2000 || !filter_var($link, FILTER_VALIDATE_URL)
        || !in_array(strtolower(parse_url($link, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))) {
        throw new FacebookPublishingError('Use a valid http or https Facebook attachment link.');
    }
    return $link;
}

function facebookPublishingMessage(array $announcement): string {
    $html = preg_replace('/<\/(p|div|li|h[1-6])>|<br\s*\/?\s*>/i', "\n", $announcement['content']);
    // Preserve inline links as readable URLs in Facebook's plain text message.
    $html = preg_replace_callback('/<a\b[^>]*href=["\'](https?:\/\/[^"\']+)["\'][^>]*>(.*?)<\/a>/is',
        fn($m) => strip_tags($m[2]) . ' (' . $m[1] . ')', $html);
    $message = trim(html_entity_decode(strip_tags($announcement['title'] . "\n\n" . $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($message === '' || strlen($message) > 60000) throw new FacebookPublishingError('Facebook requires a message of at most 60,000 bytes.');
    return $message;
}

function facebookPublishingPhoto(string $path): CURLFile {
    $root = realpath(__DIR__ . '/../uploads/announcements');
    $file = realpath(__DIR__ . '/..' . $path);
    if (!$root || !$file || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/')) {
        throw new FacebookPublishingError('An announcement photo is missing or outside announcement upload storage.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true) || filesize($file) > 5 * 1024 * 1024) {
        throw new FacebookPublishingError('A Facebook photo has an unsupported type or exceeds 5 MB.');
    }
    return new CURLFile($file, $mime, basename($file));
}

/** Keep Meta's actual permalink; a Graph PageID_PostID is not a browser URL. */
function facebookPublishingPostUrl(?string $url, string $postId): string {
    $url = trim($url ?? '');
    $parts = parse_url($url);
    if ($parts !== false && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        && in_array(strtolower($parts['host'] ?? ''), ['facebook.com','www.facebook.com','m.facebook.com','web.facebook.com'], true)
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
        && !preg_match('/[\x00-\x20\x7f\\\\]/', $url)
        && !preg_match('~^/\d+_\d+/?$~D', $parts['path'] ?? '')) return $url;
    if (preg_match('/^(\d+)_(\d+)$/D', $postId, $match)) {
        return 'https://www.facebook.com/permalink.php?story_fbid=' . $match[2] . '&id=' . $match[1];
    }
    return 'https://www.facebook.com/';
}

function facebookPublishingResolvePostUrl(string $postId, callable $graph): string {
    try {
        $post = $graph('GET', $postId, ['fields' => 'id,permalink_url']);
        if (($post['id'] ?? '') === $postId) return facebookPublishingPostUrl($post['permalink_url'] ?? null, $postId);
    } catch (FacebookPublishingError $e) {
        // The feed write already succeeded. A link lookup failure must never
        // turn it into a failed/uncertain delivery or unlock a duplicate send.
    }
    return facebookPublishingPostUrl(null, $postId);
}

/** Match public-site link handling for iOS while retaining Meta's source URL. */
function facebookPublishingViewPostUrl(?string $url, string $postId): string {
    require_once __DIR__ . '/facebook-post-url.php';
    return gncFacebookPostUrl(facebookPublishingPostUrl($url, $postId), $postId);
}

function facebookPublishAnnouncement(int $id, string $link, string $confirmedPage, ?callable $graph = null): string {
    if (!hasPermission('publish_announcement')) throw new FacebookPublishingError('You do not have permission to publish announcements.');
    $graph = $graph ?? 'facebookGraphRequest';
    return facebookPublishingLocked(function () use ($id, $link, $confirmedPage, $graph) {
        $db = getDB();
        $connection = facebookPublishingConnection();
        $config = facebookPublishingCredentials();
        if (!$connection || !$connection['enabled'] || $connection['page_id'] !== $confirmedPage || $connection['page_id'] !== $config['page'] || $connection['environment'] !== $config['mode']) {
            throw new FacebookPublishingError('Connect and authorize the configured Page in Facebook Integration first.');
        }
        $stmt = $db->prepare('SELECT * FROM announcement_facebook_deliveries WHERE announcement_id = ?');
        $stmt->execute([$id]);
        $delivery = $stmt->fetch();
        if ($delivery && $delivery['state'] === 'published') return 'Facebook was already published; no duplicate was sent.';
        if ($delivery && in_array($delivery['state'], ['sending', 'unknown'], true)) throw new FacebookPublishingError('Facebook result needs review. A Super Admin must check and reconcile the delivery in Facebook Integration before another attempt.');
        if ($delivery && $delivery['page_id'] !== $config['page']) throw new FacebookPublishingError('This announcement has an attempt for a different Page. Switching Pages cannot repost it; create a new announcement explicitly.');
        $announcement = getAnnouncementById($id);
        if (!$announcement || !in_array($announcement['status'], ['published', 'draft'], true)) throw new FacebookPublishingError('Approve this announcement before publishing to Facebook.');
        $message = facebookPublishingMessage($announcement);
        $link = facebookPublishingLink($link);
        $paths = empty($announcement['image_path']) ? [] : [$announcement['image_path']];
        foreach (getAnnouncementImages($id) as $photo) $paths[] = $photo['file_path'];
        $paths = array_values(array_unique($paths));
        if (count($paths) > 10) throw new FacebookPublishingError('Facebook supports up to 10 photos here, including the main picture.');
        $photos = array_map('facebookPublishingPhoto', $paths);
        facebookValidatePage($graph); // Revalidate expiry, permissions and Page identity before any write.
        $db->prepare("INSERT INTO announcement_facebook_deliveries (announcement_id,page_id,state,attempted_by,updated_at) VALUES (?,?,'ready',?,NOW()) ON DUPLICATE KEY UPDATE attempted_by=VALUES(attempted_by)")
            ->execute([$id, $config['page'], $_SESSION['user_id']]);
        // Upload unpublished photos first. Failures here cannot create a public feed post.
        $params = ['message' => $message];
        foreach ($photos as $index => $photo) {
            $uploaded = $graph('POST', $config['page'] . '/photos', ['source' => $photo, 'published' => 'false']);
            if (empty($uploaded['id']) || !preg_match('/^\d+$/D', $uploaded['id'])) throw new FacebookPublishingError('Meta did not return an uploaded photo ID. No feed post was sent.');
            $params['attached_media[' . $index . ']'] = json_encode(['media_fbid' => $uploaded['id']]);
        }
        if ($link !== '') {
            if ($photos) $params['message'] .= "\n\n" . $link;
            else $params['link'] = $link;
        }
        // Persist before the irreversible request. A process crash leaves sending, which blocks retries.
        $db->prepare("UPDATE announcement_facebook_deliveries SET state='sending',last_error=NULL,updated_at=NOW() WHERE announcement_id=?")->execute([$id]);
        try {
            $result = $graph('POST', $config['page'] . '/feed', $params);
            $postId = $result['id'] ?? '';
            if (!preg_match('/^' . preg_quote($config['page'], '/') . '_\d+$/D', $postId)) throw new FacebookPublishingError('Meta returned no valid post ID. Check the Page before retrying.', true);
            $url = facebookPublishingResolvePostUrl($postId, $graph);
            $db->prepare("UPDATE announcement_facebook_deliveries SET state='published',post_id=?,post_url=?,last_error=NULL,updated_at=NOW() WHERE announcement_id=?")->execute([$postId, $url, $id]);
        } catch (FacebookPublishingError $e) {
            $db->prepare('UPDATE announcement_facebook_deliveries SET state=?,last_error=?,updated_at=NOW() WHERE announcement_id=?')->execute([$e->uncertain ? 'unknown' : 'failed', $e->getMessage(), $id]);
            throw $e;
        }
        logActivity((int)$_SESSION['user_id'], 'FACEBOOK_PUBLISH', 'announcements', $id, 'Published to Page ' . $config['page'] . ', post ' . $postId);
        return 'Facebook published successfully.';
    });
}
