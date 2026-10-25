<?php
/** Requirements transcribed from the supplied Basic Education enrollment sheets. */
function basicAdmissionRequirements(): array
{
    $new = ['Form 137', 'Form 138 with LRN (Original Copy)', '2×2 picture (2 pcs)', 'PSA Birth Certificate (3 copies)', 'Certificate of Good Moral Character'];
    $transfer = ['Form 137', 'Form 138 with LRN', 'ESC Certificate', 'PSA Birth Certificate', 'Certificate of Good Moral Character', 'Recent 2×2 picture (3 pcs)', 'Letter from Parents (Reason for Transferring)'];
    $esc = ['Recent 1×1 picture (2 pcs)', 'Grade 6 Report Card (Photocopy)', 'Latest Income Tax Return for the Previous Year / Certificate of Tax Exemption or Municipal Certification of Unemployment of Parents'];
    $voucher = ['System Generated Certificate that the student is an ESC Grantee', 'Qualified Voucher Recipient (QVR) Certificate for Non-ESC Grantees'];
    $group = static fn($title, $items) => ['title' => $title, 'items' => $items];
    $highSchool = [
        'new' => $group('New Students', $new),
        'transferees' => $group('Transferees', $transfer),
        'old' => $group('Old Students', ['Form 138']),
        'esc' => $group('ESC Application', $esc),
        'voucher' => $group('SHS Voucher Application', $voucher),
    ];
    $montessori = $highSchool;
    $montessori['transferees']['items'][3] = 'PSA Birth Certificate (3 pcs)';
    $casaTransfer = $transfer;
    $casaTransfer[5] = '2×2 picture (3 pcs)';
    return [
        'casa' => ['label' => 'CASA & Grade School Montessori', 'title' => 'CASA and Grade School Montessori', 'groups' => [
            'new' => $group('New Students', $new),
            'transferees' => $group('Transferees', $casaTransfer),
            'old' => $group('Old Students', ['Form 138']),
        ]],
        'montessori' => ['label' => 'JHS & SHS Montessori', 'title' => 'Junior and Senior High School Montessori', 'groups' => $montessori],
        'jhs-regular' => ['label' => 'Junior High School Regular', 'title' => 'Junior High School Regular', 'groups' => $highSchool],
        'shs-regular' => ['label' => 'Senior High School Regular', 'title' => 'Senior High School Regular', 'groups' => [
            'grade-11' => $group('Incoming Grade 11 Students and Transferees', [
                'Original Report Card', 'Certificate of Good Moral Character', 'Photocopy of Birth Certificate',
                'Form 137', 'Original Copy of ESC Certificate (For grantees)', 'Recent 2×2 picture (2 pcs)',
            ]),
        ]],
    ];
}

/** Seed content for the existing section-based editor; page layout stays in PHP/CSS. */
function basicAdmissionPresentationDefaults(): array
{
    $fields = [
        'hero_title' => ['title' => 'Page heading', 'content' => 'Basic Education Admission Requirements'],
        'hero_intro' => ['title' => 'Page description', 'content' => 'Find the enrollment requirements for CASA and Grade School Montessori, Junior and Senior High School Montessori, and the Junior and Senior High School Regular programs.'],
    ];
    foreach (basicAdmissionRequirements() as $key => $school) {
        $fields['school_label_' . $key] = ['title' => $school['label'] . ' tab label', 'content' => $school['label']];
    }
    return $fields;
}

function basicAdmissionSectionDefaults(): array
{
    $sections = [];
    foreach (basicAdmissionRequirements() as $schoolKey => $school) {
        foreach ($school['groups'] as $key => $group) {
            $h = static fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            $sections[$schoolKey . '_' . $key] = [
                'title' => $school['label'] . ' — ' . $group['title'],
                'content' => '<h2>' . $h($group['title']) . '</h2><p>Enrollment requirements</p><ol>'
                    . implode('', array_map(static fn($item) => '<li>' . $h($item) . '</li>', $group['items'])) . '</ol>',
            ];
        }
    }
    return $sections + basicAdmissionPresentationDefaults();
}

/** Register missing header fields when an authorized user opens the editor. */
function ensureBasicAdmissionHeaderSections(): void
{
    $db = getDB();
    $page = $db->prepare("SELECT page_id FROM pages WHERE slug = ? AND status = 'active'");
    $page->execute(['basic-education-admission-requirements']);
    $pageId = $page->fetchColumn();
    if (!$pageId) return;

    $check = $db->prepare('SELECT section_id FROM page_sections WHERE page_id = ? AND section_key = ?');
    $insert = $db->prepare("INSERT INTO page_sections (page_id, section_key, title, content, status, display_order) VALUES (?, ?, ?, ?, 'published', ?)");
    $defaults = basicAdmissionPresentationDefaults();
    foreach (['hero_title', 'hero_intro'] as $order => $key) {
        $check->execute([$pageId, $key]);
        if (!$check->fetchColumn()) {
            $insert->execute([$pageId, $key, $defaults[$key]['title'], $defaults[$key]['content'], $order]);
        }
    }
}
