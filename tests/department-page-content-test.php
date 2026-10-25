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
        saveDepartmentPageContent($id, ['leadership_title' => 'New <heading>', 'contacts_label' => '', 'vision_title' => 'Our vision', 'vision_text' => '<p>Learning &amp; service</p>', 'mission_text' => '<p>Educate future leaders.</p><script>alert(1)</script>']);
        pageCheck(departmentPageText($dept, 'leadership_title') === 'New &lt;heading&gt;', 'Saved text is safely rendered');
        pageCheck(departmentPageText($dept, 'contacts_label') === '', 'Cleared labels stay empty');
        saveDepartmentPageContent($id, ['vision_icon' => 'bi:bullseye', 'mission_icon' => 'material:school']);
        pageCheck(departmentPageContent($dept, true)['vision_icon']['value'] === 'bi:bullseye', 'Vision icon persists');
        pageCheck(str_contains(departmentStatementIcon($dept, 'vision_icon'), 'bi-bullseye'), 'Public vision renderer uses saved Bootstrap icon');
        pageCheck(str_contains(departmentStatementIcon($dept, 'mission_icon'), '>school</span>'), 'Public mission renderer uses saved Material icon');
        pageCheck(departmentPageText($dept, 'vision_title') === 'Our vision', 'Vision heading persists');
        pageCheck(str_contains(departmentPageText($dept, 'vision_text'), 'Learning &amp; service'), 'Vision rich text persists');
        pageCheck(str_contains(departmentPageText($dept, 'mission_text'), 'Educate future leaders.') && !str_contains(departmentPageText($dept, 'mission_text'), '<script'), 'Mission persists and unsafe HTML is removed');
        saveDepartmentPageContent($id, ['vision_text' => '', 'mission_text' => '']);
        pageCheck(departmentPageText($dept, 'vision_text') === '' && departmentPageText($dept, 'mission_text') === '', 'Optional vision and mission can be cleared');
        foreach ([['unknown_key' => 'x'], ['phone_label' => str_repeat('a', 101)], ['leadership_title' => []], ['vision_icon' => '<script>bad()</script>'], ['mission_icon' => 'bi:not-a-real-icon'], ['vision_icon' => 'svg:grad-hat-white']] as $invalid) {
            try { saveDepartmentPageContent($id, $invalid); throw new RuntimeException('Invalid field was accepted'); }
            catch (InvalidArgumentException $expected) {}
        }
    }
} finally { $db->rollBack(); }
echo "PASS: all department types share editable fields, Basic Education content is retained, saves round-trip safely, empty text stays empty and invalid edits are rejected; writes rolled back.\n";
