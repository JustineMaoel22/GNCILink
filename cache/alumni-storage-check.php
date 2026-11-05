<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../admin/admin-functions.php';
require __DIR__ . '/../components/alumni-page-content.php';
initSession();
$db = getDB();
$_SESSION['user_id'] = (int)$db->query("SELECT user_id FROM users WHERE role='Super Admin' AND status='active' LIMIT 1")->fetchColumn();
function alumniAssert($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db->beginTransaction();
try {
    $original = getSectionContent('alumni', 'intro');
    alumniAssert(saveSectionDraft('alumni', 'intro', 'Alumni draft verification'), 'Draft failed');
    alumniAssert(getSectionContent('alumni', 'intro') === $original, 'Draft leaked to public');
    alumniAssert(getSectionContent('alumni', 'intro', true) === 'Alumni draft verification', 'Draft preview failed');
    alumniAssert(publishSection('alumni', 'intro'), 'Publish failed');
    alumniAssert(getSectionContent('alumni', 'intro') === 'Alumni draft verification', 'Publish not visible');
    alumniAssert(saveSectionDraft('alumni', 'intro', 'Discard me'), 'Second draft failed');
    alumniAssert(discardSectionDraft('alumni', 'intro'), 'Discard failed');
    alumniAssert(getSectionContent('alumni', 'intro', true) === 'Alumni draft verification', 'Discard did not restore published value');
    foreach (['javascript:alert(1)', '/uploads/../config/config.php', 'https://example.com/image.jpg'] as $bad) {
        try { alumniPageValidate('hero_image', $bad); throw new RuntimeException('Invalid image accepted'); }
        catch (InvalidArgumentException $expected) {}
    }
    try { alumniPageValidate('unknown', 'test'); throw new RuntimeException('Unknown field accepted'); }
    catch (InvalidArgumentException $expected) {}
    try { alumniPageValidate('title_start', str_repeat('x', 101)); throw new RuntimeException('Oversized field accepted'); }
    catch (InvalidArgumentException $expected) {}
    echo "PASS: draft isolation, publish, discard, field/image validation. All changes rolled back.\n";
} finally { $db->rollBack(); }
