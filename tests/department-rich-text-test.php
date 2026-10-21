<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function richCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$input = '<h3>Overview</h3><p style="text-align:center" onclick="bad()"><strong>Bold</strong> <em>Italic</em> <u>Underline</u> <a href="/pages/contact-us.php">Contact</a></p><ol><li>First</li></ol><p class="dept-offers-label">Courses:</p><ul class="dept-offers-list"><li>Course</li></ul><script>bad()</script><a href="javascript:bad()">Unsafe</a>';
$clean = cleanDepartmentDetails(['dept_name' => 'Rich text test', 'about_text' => $input])['about_text'];
foreach (['<h3>', '<strong>', '<em>', '<u>', '<ol>', 'text-align:center', 'href="/pages/contact-us.php"', 'class="dept-offers-label"', 'class="dept-offers-list"'] as $markup) {
    richCheck(strpos($clean, $markup) !== false, 'Preserves ' . $markup);
}
richCheck(!preg_match('/<script|onclick|javascript:/i', $clean), 'Removes unsafe HTML on the server');
richCheck(cleanDepartmentDetails(['dept_name' => 'Rich text test', 'about_text' => '<script>bad()</script>'])['about_text'] === null, 'Rejects script-only content');
$db = getDB();
$dept = $db->query('SELECT * FROM departments ORDER BY dept_id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$db->beginTransaction();
try {
    $stmt = $db->prepare('UPDATE departments SET about_text = ? WHERE dept_id = ?');
    $stmt->execute([$clean, $dept['dept_id']]);
    $saved = getDepartmentById((int)$dept['dept_id'])['about_text'];
    richCheck($saved === $clean && richTextSanitize($saved) === $clean, 'Database and public sanitizer preserve the formatting');
} finally { $db->rollBack(); }
echo "PASS: server sanitization, rich formatting, legacy overview styles and database round trip; test writes rolled back.\n";
