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
        pageCheck(in_array($original['header_mobile_background_hidden']['value'],['0','1'],true),'Existing mobile background preference is valid');
        saveDepartmentPageContent($id,['header_mobile_background_hidden'=>'1']);
        pageCheck(departmentPageContent($dept,true)['header_mobile_background_hidden']['value']==='1','Mobile background visibility persists');
        saveDepartmentPageContent($id,['header_mobile_background_hidden'=>'0']);
        foreach (['yes','', '2'] as $badMobileVisibility) {
            try {saveDepartmentPageContent($id,['header_mobile_background_hidden'=>$badMobileVisibility]);throw new RuntimeException('Invalid mobile background visibility accepted');}
            catch(InvalidArgumentException $expected) {}
        }
        saveDepartmentPageContent($id, ['header_prefix'=>'SCHOOL OF', 'header_subject'=>'Test header title', 'header_extra_text'=>'Additional <header> text']);
        pageCheck(departmentPageContent($dept, true)['header_prefix']['value']==='SCHOOL OF', 'Header prefix persists independently');
        pageCheck(departmentPageText($dept,'header_extra_text')==='Additional &lt;header&gt; text', 'Extra header text is escaped');
        pageCheck(getDepartmentById($id)['dept_name']===$dept['dept_name'], 'Header-only edits preserve department identity');
        saveDepartmentPageContent($id, ['header_extra_text'=>'']);
        pageCheck(departmentPageText($dept,'header_extra_text')==='', 'Extra header text can be removed');
        saveDepartmentPageContent($id, ['header_logo_hidden' => '1', 'header_text_hidden' => '1']);
        $header = departmentPageContent($dept, true);
        pageCheck($header['header_logo_hidden']['value'] === '1' && $header['header_text_hidden']['value'] === '1', 'Header removals persist independently');
        $unchanged = getDepartmentById($id);
        pageCheck(($unchanged['logo_media_id'] ?? null) === ($dept['logo_media_id'] ?? null) && $unchanged['dept_name'] === $dept['dept_name'], 'Header removal preserves shared logo and department name');
        saveDepartmentPageContent($id, ['header_logo_hidden' => '0', 'header_text_hidden' => '0']);
        pageCheck(departmentPageContent($dept, true)['header_logo_hidden']['value'] === '0', 'Header logo can be restored');
        foreach (['yes', '', '2'] as $invalidVisibility) {
            try { saveDepartmentPageContent($id, ['header_logo_hidden' => $invalidVisibility]); throw new RuntimeException('Invalid visibility accepted'); }
            catch (InvalidArgumentException $expected) {}
        }
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
