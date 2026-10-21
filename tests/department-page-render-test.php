<?php
// CLI-only check of authorized editor markup. No HTTP authentication bypass.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
$slug = $argv[1] ?? 'cased';
if (!in_array($slug, ['basic-index', 'college-index', 'cased', 'casa-gradeschool', 'hs-monte', 'shs-regular', 'jhs-regular', 'graduate-school'], true)) exit(1);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$_SERVER['PHP_SELF'] = '/pages/academics/college/college-department.php';
initSession();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'Super Admin';
$_GET = ['slug' => $slug, 'ce_preview' => '1'];
ob_start();
if ($slug === 'college-index') require __DIR__ . '/../pages/academics/college/college-dept.php';
elseif ($slug === 'basic-index') require __DIR__ . '/../pages/academics/basic-edu/basic-edu-dept.php';
else require __DIR__ . '/../components/department-page.php';
$html = ob_get_clean();
if (in_array($slug, ['college-index', 'basic-index'], true)) {
    if (substr_count($html, 'data-ve-field="basic-text"') < 10 || !str_contains($html, 'id="veBasicData"') || substr_count($html, 'data-ve-icon="academic-feature"') !== 4) {
        throw new RuntimeException('College listing must expose all ten fields and its editor payload');
    }
    if ($slug === 'college-index') {
        foreach ($publicDepartments as $department) {
            if (substr_count($html, 'data-ve-basic-logo-id="' . (int)$department['dept_id'] . '"') !== 1) {
                throw new RuntimeException('Each college card must expose its own editable department logo');
            }
        }
    }
    echo "PASS: $slug exposes editable text, all four feature icons and department card logo controls to the Visual Editor.\n";
    exit;
}
foreach (['data-ve-field="dept-page-text"', 'id="veDeptPageData"', 'data-ve-field="dean-name"', 'data-ve-img="dean-photo"', 'data-ve-img="dept-logo"', 'dept-dean-contacts-grid'] as $marker) {
    if (!str_contains($html, $marker)) throw new RuntimeException("Missing $marker for $slug");
}
if (str_contains($html, 'id="veBasicData"')) throw new RuntimeException('Department pages must not depend on legacy content slots');
foreach (['id="veDeptProgramsData"', 'id="veDepartmentProgramTemplate"', 'data-ve-program-grid', 'data-ve-field="dept-program-name"', 'data-ve-program-image', 'data-ve-add="program"'] as $marker) {
    if (!str_contains($html, $marker)) throw new RuntimeException('Missing inline program card markup: ' . $marker);
}
echo "PASS: $slug renders the shared layout with editable headings, contacts and media.\n";
