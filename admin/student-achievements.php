<?php
// Preserve existing bookmarks while all editing happens on the visual canvas.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
initSession();
requireLogin();
if (!hasPermission('manage_page_content')) { http_response_code(403); exit('You cannot manage student achievements.'); }
header('Location: /admin/content-edit.php?page=student-achievements', true, 303);
exit;
