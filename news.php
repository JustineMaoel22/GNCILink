<?php
/**
 * PUBLIC SITE ROOT: /news.php   (rename this file to news.php)
 *
 * Stopgap so old "Read more" links like news.php?slug=my-article keep working.
 * It simply forwards them to the new article page.
 */
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;

if ($slug !== '') {
    header('Location: /news-article.php?slug=' . rawurlencode($slug), true, 302);
    exit;
}
if ($id > 0) {
    header('Location: /news-article.php?id=' . $id, true, 302);
    exit;
}
// No article asked for: go back to the homepage.
header('Location: /index.php', true, 302);
exit;