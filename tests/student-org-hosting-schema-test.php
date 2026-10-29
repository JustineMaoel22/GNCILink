<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';

// Run each schema in a fresh process because capability checks are request-cached.
$schema = $argv[1] ?? '';
if (!in_array($schema, ['legacy', 'current'], true)) throw new InvalidArgumentException('Choose legacy or current.');
$db = getDB();
function hostingOrgCheck(bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
}

// Connection-local temporary tables shadow real tables; no persistent data is changed.
$db->exec('CREATE TEMPORARY TABLE student_org_categories (category_id INT PRIMARY KEY, name VARCHAR(100), display_order INT)');
$db->exec('CREATE TEMPORARY TABLE student_org_gallery (image_id INT PRIMARY KEY, org_id INT, media_id INT, display_order INT)');
$db->exec('CREATE TEMPORARY TABLE student_org_page_content (field_key VARCHAR(80) PRIMARY KEY, content TEXT)');
$archiveColumn = $schema === 'current' ? ', archived_at DATETIME NULL' : '';
$db->exec("CREATE TEMPORARY TABLE student_organizations (
    org_id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(180) UNIQUE, name VARCHAR(255), category_id INT NULL,
    description TEXT, about_text TEXT, vision TEXT, mission TEXT, facebook_url VARCHAR(500),
    logo_media_id INT NULL, cover_media_id INT NULL, status VARCHAR(20) DEFAULT 'draft', display_order INT DEFAULT 0
    {$archiveColumn})");
$db->exec("INSERT INTO student_org_categories VALUES (1,'Academic',0)");
// MySQL cannot join the same temporary table twice, so read existing media only.
$media = $db->query('SELECT media_id,file_path FROM media_library ORDER BY media_id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
hostingOrgCheck((bool)$media, 'Local fixture requires one existing media record');
$db->prepare("INSERT INTO student_organizations (slug,name,category_id,status,logo_media_id,display_order) VALUES
    ('published-org','Published organization',1,'published',?,1),
    ('draft-org','Unpublished organization',NULL,'draft',NULL,2)")->execute([$media['media_id']]);
if ($schema === 'current') {
    $db->exec("INSERT INTO student_organizations (slug,name,status,archived_at) VALUES ('archived-org','Archived organization','published',NOW())");
} else {
    // Confirm that the previous public query fails on this hosting schema.
    try {
        $db->query("SELECT o.* FROM student_organizations o WHERE o.status='published' AND o.archived_at IS NULL");
        throw new RuntimeException('Legacy fixture unexpectedly supports archives');
    } catch (PDOException $e) {
        hostingOrgCheck($e->getCode() === '42S22', 'Reproduce missing archive column');
    }
}

$public = studentOrganizations();
hostingOrgCheck(array_column($public, 'slug') === ['published-org'], 'Only active published organizations are public');
hostingOrgCheck(array_key_exists('archived_at', $public[0]) && $public[0]['archived_at'] === null, 'Legacy records expose a null archive value');
hostingOrgCheck($public[0]['category_name'] === 'Academic' && $public[0]['logo_url'] === $media['file_path'], 'Category and media relationships survive');
hostingOrgCheck(count(studentOrganizations(true)) === ($schema === 'current' ? 3 : 2), 'Editor retains unpublished and archived records');
$saved = saveStudentOrganization(['org_id' => $public[0]['org_id'], 'name' => 'Updated organization']);
hostingOrgCheck($saved['slug'] === 'published-org' && $saved['name'] === 'Updated organization' && $saved['status'] === 'published', 'Ordinary editing works on both schemas');
if ($schema === 'legacy') {
    try {
        saveStudentOrganization(['org_id' => $public[0]['org_id'], 'archived' => '1']);
        throw new LogicException('Archiving must require its migration');
    } catch (RuntimeException $e) {
        hostingOrgCheck(str_contains($e->getMessage(), 'migrate-student-org-archive.php'), 'Missing migration has an actionable error');
    }
} else {
    saveStudentOrganization(['org_id' => $public[0]['org_id'], 'archived' => '1']);
    hostingOrgCheck(studentOrganizations() === [], 'Archiving still hides published content');
    saveStudentOrganization(['org_id' => $public[0]['org_id'], 'archived' => '0']);
    hostingOrgCheck(count(studentOrganizations()) === 1, 'Restoring keeps publication status');
}

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = '/pages/student%20life/student%20organization%20and%20leadership%20program/student-org';
ob_start();
require __DIR__.'/../pages/student life/student organization and leadership program/student-org.php';
$html = ob_get_clean();
hostingOrgCheck(str_contains($html, 'Updated organization') && str_contains($html, '<footer'), 'Public listing renders through the footer');
hostingOrgCheck(!str_contains($html, 'Unpublished organization') && !str_contains($html, 'Archived organization'), 'Public HTML excludes private organizations');
echo "PASS: {$schema} hosting schema, public visibility, media, editing, archive behavior and full listing render. Temporary tables expire on disconnect.\n";
