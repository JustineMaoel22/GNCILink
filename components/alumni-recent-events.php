<?php
require_once __DIR__ . '/../includes/campus-event-posts.php';
require_once __DIR__ . '/../includes/facebook-post-url.php';
$alumniPosts = campusEventPosts(getDB(), $_GET, 'Alumni', true);
$alumniEventsPageUrl = static function (int $page) use ($preview): string {
    $query = ['event_page' => $page];
    if ($preview) $query['ce_preview'] = '1';
    return '/pages/alumni.php?' . http_build_query($query) . '#alumni-recent-events';
};
if (isset($alumni['recent_events_title'])):
?>
<section class="campus-recent-events alumni-recent-events" id="alumni-recent-events" aria-labelledby="alumni-recent-heading">
    <h2 id="alumni-recent-heading"<?= $mark('recent_events_title', 'Recent alumni events heading') ?>><?= $plain('recent_events_title') ?></h2>
    <?php if (!$alumniPosts['items']): ?>
        <p class="campus-recent-empty">No alumni events have been published yet.</p>
    <?php else: ?>
    <div class="campus-recent-grid">
        <?php foreach ($alumniPosts['items'] as $post):
            $date = new DateTimeImmutable($post['post_date'], new DateTimeZone('Asia/Manila'));
            $isFacebook = $post['post_type'] === 'facebook';
            $isEvent = $post['post_type'] === 'event';
            $label = $isEvent ? 'Alumni Event' : ($post['post_type'] === 'news' ? 'News' : 'Announcement');
            $title = $isFacebook ? (preg_split('/\R+/u', trim($post['title']))[0] ?: 'Alumni event') : $post['title'];
            $url = $isEvent ? null : ($isFacebook
                ? gncFacebookPostUrl($post['permalink_url'], (string)$post['post_id'], defined('FB_PAGE_ID') ? FB_PAGE_ID : null)
                : ($post['post_type'] === 'news' ? '/news-article.php' : '/announcement-article.php') . '?slug=' . rawurlencode($post['slug']));
            $image = (string)($post['image_path'] ?? '');
            if ($image !== '' && !preg_match('~^https?://~i', $image)) $image = '/' . ltrim($image, '/');
            $editorMark = !$isFacebook ? ceManageMark('data-ve-post="' . ceAttr($post['post_type']) . '" data-ve-id="' . (int)$post['post_id'] . '" data-ve-date="' . $date->format('Y-m') . '" data-ve-label="' . ceAttr($title) . '"') : '';
        ?>
        <<?= $isEvent ? 'article' : 'a' ?> class="campus-recent-card"<?= $url ? ' href="' . $escape($url) . '"' : '' ?><?= $isFacebook ? ' target="_blank" rel="noopener noreferrer"' : '' ?><?= $editorMark ?>>
            <?php if ($image !== ''): ?><img class="campus-recent-image" src="<?= $escape($image) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><div class="campus-recent-placeholder gnc-empty-post-image" aria-hidden="true"></div><?php endif; ?>
            <time class="campus-recent-date" datetime="<?= $date->format('Y-m-d') ?>" aria-label="<?= $date->format('F j, Y') ?>"><span><?= strtoupper($date->format('M')) ?></span><strong><?= $date->format('d') ?></strong><span><?= $date->format('Y') ?></span></time>
            <div class="campus-recent-content"><span class="campus-recent-type"><?= $escape($label) ?><?= $isFacebook ? ' · From Facebook' : '' ?></span><h3><?= $escape($title) ?></h3><span class="campus-recent-meta"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= $date->format('F j, Y') ?></span></div>
        </<?= $isEvent ? 'article' : 'a' ?>>
        <?php endforeach; ?>
    </div>
    <?php if ($alumniPosts['pages'] > 1): ?>
    <nav class="campus-recent-pagination" aria-label="Alumni recent events pages">
        <?php if ($alumniPosts['page'] > 1): ?><a data-alumni-events-page href="<?= $escape($alumniEventsPageUrl($alumniPosts['page'] - 1)) ?>" rel="prev" aria-label="Previous page"><i class="bi bi-chevron-left" aria-hidden="true"></i></a><?php endif; ?>
        <?php for ($p = 1; $p <= $alumniPosts['pages']; $p++): ?>
            <?php if ($p === 1 || $p === $alumniPosts['pages'] || abs($p - $alumniPosts['page']) <= 1): ?><a data-alumni-events-page href="<?= $escape($alumniEventsPageUrl($p)) ?>"<?= $p === $alumniPosts['page'] ? ' aria-current="page"' : '' ?> aria-label="Page <?= $p ?>"><?= $p ?></a><?php elseif (abs($p - $alumniPosts['page']) === 2): ?><span aria-hidden="true">…</span><?php endif; ?>
        <?php endfor; ?>
        <?php if ($alumniPosts['page'] < $alumniPosts['pages']): ?><a data-alumni-events-page href="<?= $escape($alumniEventsPageUrl($alumniPosts['page'] + 1)) ?>" rel="next" aria-label="Next page"><i class="bi bi-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
