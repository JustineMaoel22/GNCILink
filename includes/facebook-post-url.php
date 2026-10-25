<?php

/**
 * Resolve a cached Facebook permalink without changing the source record.
 * Numeric /{page}/posts/{post} links can fail on direct iOS app handoff.
 * Use the explicit story/Page pair, preferring the matching Graph identity.
 * Keep other post types intact: a reel/video/photo ID is not a story ID.
 */
function gncFacebookPostUrl(?string $permalink, ?string $graphPostId = null, ?string $pageId = null): string {
    $graph = [];
    preg_match('/\A([0-9]+)_([0-9]+)\z/', trim($graphPostId ?? ''), $graph);
    $storyUrl = static function (string $owner, string $post): string {
        return 'https://www.facebook.com/permalink.php?story_fbid=' . $post . '&id=' . $owner;
    };

    $url = trim(html_entity_decode($permalink ?? '', ENT_QUOTES, 'UTF-8'));
    $parts = parse_url($url);
    $valid = $parts !== false
        && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        && in_array(strtolower($parts['host'] ?? ''), ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'mbasic.facebook.com', 'web.facebook.com', 'touch.facebook.com'], true)
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
        && !preg_match('/[\x00-\x20\x7f\\\\]/', $url);

    if ($valid) {
        $post = [];
        if (preg_match('~\A/([0-9]+)/posts/([0-9]+)/?\z~', $parts['path'] ?? '', $post)) {
            // An inconsistent cache entry must not silently point to another story.
            if (!$graph || $graph[2] === $post[2]) {
                return $storyUrl($graph[1] ?? $post[1], $post[2]);
            }
        }
        return $url;
    }

    // Missing/invalid source URLs still have a deterministic, public HTTPS target.
    if ($graph) return $storyUrl($graph[1], $graph[2]);
    $pageId = trim($pageId ?? '');
    return 'https://www.facebook.com/' . (preg_match('/\A[0-9]+\z/', $pageId) ? $pageId : '');
}
