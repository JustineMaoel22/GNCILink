<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function pageCheck(bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException($label);
}
$db = getDB();
$departments = $db->query("SELECT * FROM departments WHERE slug IN ('cased', 'casa-gradeschool', 'graduate-school')")->fetchAll(PDO::FETCH_ASSOC);
pageCheck(count($departments) === 3, 'Representative departments exist for all three layouts');
$db->beginTransaction();
try {
    foreach ($departments as $dept) {
        $id = (int)$dept['dept_id'];
        $original = departmentPageContent($dept);
        pageCheck(isset($original['leadership_title'], $original['profile_label'], $original['contacts_label']), 'Common editable fields are available');
        if ($dept['slug'] === 'casa-gradeschool') {
            $saved = $db->prepare('SELECT content FROM department_page_content WHERE dept_id = ? AND field_key = ?');
            $saved->execute([$id, 'leadership_intro']);
            pageCheck($original['leadership_intro']['value'] === (string)$saved->fetchColumn(), 'Basic Education introduction uses the shared content table');
        }
        saveDepartmentPageContent($id, ['leadership_title' => 'New <heading>', 'contacts_label' => '']);
        pageCheck(departmentPageText($dept, 'leadership_title') === 'New &lt;heading&gt;', 'Saved text is safely rendered');
        pageCheck(departmentPageText($dept, 'contacts_label') === '', 'Cleared labels stay empty');
        foreach ([['unknown_key' => 'x'], ['phone_label' => str_repeat('a', 101)], ['leadership_title' => []]] as $invalid) {
            try { saveDepartmentPageContent($id, $invalid); throw new RuntimeException('Invalid field was accepted'); }
            catch (InvalidArgumentException $expected) {}
        }
    }
} finally { $db->rollBack(); }
echo "PASS: all department types share editable fields, Basic Education content is retained, saves round-trip safely, empty text stays empty and invalid edits are rejected; writes rolled back.\n";
