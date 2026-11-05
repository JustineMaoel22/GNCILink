<?php
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/alumni-page-content.php';
$preview = cePreviewActive();
$alumni = alumniPageContent($preview);
$activeSection = 'student-life';
$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$plain = static fn($key) => $escape(html_entity_decode(strip_tags($alumni[$key] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
$mark = static fn($key, $label) => ceMark('data-ve-section="' . ceAttr($key) . '" data-ve-label="' . ceAttr($label) . '" data-ve-plain-section data-ve-max="' . (in_array($key, ['intro', 'events_intro'], true) ? 2000 : 100) . '"');
$image = responsiveBackgroundPath(trim(strip_tags($alumni['hero_image'] ?? '')));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alumni</title>
    <link rel="icon" href="/assets/images/logos/gnc-logo-v1.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/base-style.css">
    <link rel="stylesheet" href="/assets/css/navbar-style.css">
    <link rel="stylesheet" href="/assets/css/footer-style.css">
    <link rel="stylesheet" href="/assets/css/recent-events.css?v=<?= filemtime(__DIR__ . '/../assets/css/recent-events.css') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/alumni-style.css?v=<?= filemtime(__DIR__ . '/../assets/css/alumni-style.css') ?>">
</head>
<body>
<?php include __DIR__ . '/../components/navbar.php'; ?>
<main class="alumni-page">
    <section class="alumni-hero" id="alumni-hero"<?= $image ? ' data-bg="' . $escape($image) . '" style="background-image:url(&quot;' . $escape($image) . '&quot;)"' : '' ?>>
        <?php if ($preview): ?><button type="button" class="alumni-background-edit" data-ve-background-for="alumni-hero"><i class="bi bi-image" aria-hidden="true"></i> Change background</button><?php endif; ?>
        <div class="alumni-hero-inner">
            <h1 class="alumni-title">
                <span class="alumni-title-line"><span<?= $mark('title_start', 'Heading opening') ?>><?= $plain('title_start') ?></span> <span class="alumni-gold"<?= $mark('title_accent', 'Heading gold word') ?>><?= $plain('title_accent') ?></span></span>
                <span class="alumni-title-line alumni-title-continuation"><span class="alumni-gold"<?= $mark('title_continues', 'Second line gold word') ?>><?= $plain('title_continues') ?></span> <span<?= $mark('title_end', 'Heading ending') ?>><?= $plain('title_end') ?></span></span>
            </h1>
            <p class="alumni-intro"<?= $mark('intro', 'Alumni introduction') ?>><?= $plain('intro') ?></p>
            <div class="alumni-actions"<?= ceMark('data-ve-section="actions" data-ve-label="Buttons: edit text and links"') ?>><?= renderSectionContent($alumni['actions'] ?? '') ?></div>
            <?php if ($preview): ?><button type="button" class="alumni-add-action" data-ve-alumni-add-action><i class="bi bi-plus-lg" aria-hidden="true"></i> Add button</button><?php endif; ?>
        </div>
    </section>
    <?php include __DIR__ . '/../components/alumni-voices.php'; ?>
    <?php include __DIR__ . '/../components/alumni-events.php'; ?>
    <?php include __DIR__ . '/../components/alumni-recent-events.php'; ?>
    <?php if (trim(strip_tags($alumni['stories'] ?? '')) !== ''): ?>
    <section id="alumni-stories" class="alumni-stories">
        <div<?= ceMark('data-ve-section="stories" data-ve-label="Alumni stories (optional)"') ?>><?= renderSectionContent($alumni['stories'] ?? '') ?></div>
    </section>
    <?php endif; ?>
    <?php include __DIR__ . '/../components/alumni-survey.php'; ?>
</main>
<?php include __DIR__ . '/../components/footer.php'; ?>
<?php include __DIR__ . '/../chatbot/widget.php'; ?>
</body>
</html>
