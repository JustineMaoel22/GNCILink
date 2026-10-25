<?php
/** Original College requirements, also used to seed the section-based editor. */
function collegeAdmissionSectionDefaults(): array
{
    $groups = [
        'freshmen' => ['Incoming Freshmen', 'All incoming freshmen are required to submit the following documents during the admission process.', [
            'Original Form 138 (Report Card).',
            'Certificate of Good Moral Character signed by the Principal or Guidance Counselor.',
            'Photocopy of PSA Birth Certificate.',
            'Photocopy of Marriage Certificate (if applicable).',
            'Medical Certificate.',
            'Three (3) pieces of your recent 2×2 ID photo.',
            'Accomplished Student Profile Form.',
            'Test Result from the Guidance Testing Center.',
        ]],
        'transferees' => ['Transferees', 'Transferees must submit the following documents, in addition to their transfer credentials, during the admission process.', [
            'Honorable Dismissal or Transfer Credentials.',
            'Successful Interview with the Dean.',
            'Certified True Copy of Grades (From Previous School).',
            'Certificate of Good Moral Character signed by the Principal or Guidance Counselor.',
            'Photocopy of PSA Birth Certificate.',
            'Photocopy of PSA Marriage Certificate (If Applicable).',
            'Three (3) pieces of your recent 2×2 ID photo.',
        ]],
    ];
    $sections = ['hero_intro' => ['title' => 'Page introduction', 'content' => '<p>GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their readiness to benefit from the school’s quality education and enriching intellectual, social, and spiritual environment.</p>']];
    $h = static fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    foreach ($groups as $key => [$title, $intro, $items]) {
        $sections[$key] = ['title' => $title, 'content' => '<h2>' . $h($title) . '</h2><p>' . $h($intro) . '</p><ol>'
            . implode('', array_map(static fn($item) => '<li>' . $h($item) . '</li>', $items)) . '</ol>'];
    }
    $sections['hero_title'] = ['title' => 'Page heading', 'content' => 'College Admission Requirements'];
    return $sections;
}

/** Add the editable heading to existing installations without replacing content. */
function ensureCollegeAdmissionHeadingSection(): void
{
    $db = getDB();
    $page = $db->prepare("SELECT page_id FROM pages WHERE slug = ? AND status = 'active'");
    $page->execute(['college-admission-requirements']);
    $pageId = $page->fetchColumn();
    if (!$pageId) return;
    $check = $db->prepare('SELECT section_id FROM page_sections WHERE page_id = ? AND section_key = ?');
    $check->execute([$pageId, 'hero_title']);
    if ($check->fetchColumn()) return;
    $field = collegeAdmissionSectionDefaults()['hero_title'];
    $db->prepare("INSERT INTO page_sections (page_id, section_key, title, content, status, display_order) VALUES (?, 'hero_title', ?, ?, 'published', 0)")
        ->execute([$pageId, $field['title'], $field['content']]);
}
