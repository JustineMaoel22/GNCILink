<?php
/**
 * PUBLIC SITE ROOT: /announcement.php
 *
 * Stopgap so old links like announcement.php?slug=my-announcement keep working.
 * It simply forwards them to the new announcement page.
 */
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;

if ($slug !== '') {
    header('Location: /announcement-article.php?slug=' . rawurlencode($slug), true, 302);
    exit;
}
if ($id > 0) {
    header('Location: /announcement-article.php?id=' . $id, true, 302);
    exit;
}
header('Location: /index.php', true, 302);
exit;