<?php
/** Read-only by default. --refresh-links updates only verified local delivery URLs;
 * never publishes/edits/deletes a Facebook post or changes tokens/delivery state. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/facebook-publishing.php';
$refreshLinks = ($argv[1] ?? '') === '--refresh-links';
$visibility = in_array('--visibility', $argv, true);
try {
    $config = facebookPublishingCredentials();
    $publishedPostIds = null;
    if ($visibility) {
        $page = ['configured_page_id' => $config['page']];
        foreach (['name', 'link', 'is_published', 'is_restricted'] as $field) {
            try { $value = facebookGraphRequest('GET', $config['page'], ['fields' => $field]); $page[$field] = $value[$field] ?? null; }
            catch (FacebookPublishingError $e) { $page[$field] = 'Not available from Meta with current access.'; }
        }
        echo json_encode(['page_visibility' => $page], JSON_UNESCAPED_SLASHES) . PHP_EOL;
        try {
            $listing = facebookGraphRequest('GET', $config['page'] . '/published_posts', ['fields' => 'id', 'limit' => 25]);
            if (isset($listing['data']) && is_array($listing['data'])) $publishedPostIds = array_column($listing['data'], 'id');
        } catch (FacebookPublishingError $e) { /* A missing list is not proof that a post is missing. */ }
    }
    $rows = getDB()->query("SELECT announcement_id,page_id,state,post_id,post_url FROM announcement_facebook_deliveries WHERE post_id IS NOT NULL ORDER BY updated_at DESC LIMIT 5")->fetchAll();
    if (!$rows) echo "No saved outbound Facebook post IDs found.\n";
    foreach ($rows as $row) {
        $result = ['announcement_id' => (int)$row['announcement_id'], 'page_id' => $row['page_id'], 'state' => $row['state'], 'post_id' => $row['post_id'], 'saved_url' => $row['post_url']];
        if ($row['page_id'] !== $config['page'] || !preg_match('/^\d+_\d+$/D', $row['post_id'])) {
            $result['check'] = 'Skipped: post is not on the currently configured Page.';
        } else {
            try {
                $post = facebookGraphRequest('GET', $row['post_id'], ['fields' => 'id,is_published,permalink_url']);
                $result['is_published'] = $post['is_published'] ?? null;
                $result['meta_permalink'] = $post['permalink_url'] ?? null;
                if ($visibility) {
                    $result['in_latest_25_published_posts'] = $publishedPostIds === null ? null : in_array($row['post_id'], $publishedPostIds, true);
                    try {
                        $author = facebookGraphRequest('GET', $row['post_id'], ['fields' => 'from']);
                        $result['post_author_id'] = $author['from']['id'] ?? null;
                    } catch (FacebookPublishingError $e) { $result['post_author_id'] = null; }
                    foreach (['is_hidden', 'privacy'] as $field) {
                        try {
                            $value = facebookGraphRequest('GET', $row['post_id'], ['fields' => $field]);
                            $result[$field] = $field === 'privacy' ? ($value['privacy']['value'] ?? null) : ($value[$field] ?? null);
                        } catch (FacebookPublishingError $e) { $result[$field] = 'Not available from Meta with current access.'; }
                    }
                }
                if ($refreshLinks && ($post['id'] ?? '') === $row['post_id'] && !empty($post['is_published']) && !empty($post['permalink_url'])) {
                    $url = facebookPublishingPostUrl($post['permalink_url'], $row['post_id']);
                    $stmt = getDB()->prepare("UPDATE announcement_facebook_deliveries SET post_url=? WHERE announcement_id=? AND post_id=? AND page_id=? AND state='published'");
                    $stmt->execute([$url, $row['announcement_id'], $row['post_id'], $row['page_id']]);
                    $result['link_refreshed'] = $stmt->rowCount() === 1;
                }
            } catch (FacebookPublishingError $e) { $result['check'] = $e->getMessage(); }
        }
        echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
} catch (FacebookPublishingError $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
catch (Throwable $e) { fwrite(STDERR, "Post diagnostics could not finish. No credentials are printed.\n"); exit(1); }
