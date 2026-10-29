<?php
/** Compact navigation labels only; full CMS names remain unchanged. */
function gncBreadcrumbShortLabel(string $label, string $slug = ''): string {
    if (trim($slug) !== '') return strtoupper(trim($slug));
    $short = [
        'college departments'=>'College Departments', 'basic education departments'=>'Basic Education',
        'graduate school'=>'Graduate School', 'student organizations and leadership programs'=>'Organizations',
        'student organizations'=>'Organizations', 'community extension activities'=>'Community Activities',
        'enrollment procedures'=>'Enrollment', 'admission requirements'=>'Requirements',
        'online payment guide'=>'Payment Guide',
    ];
    $key = strtolower(trim($label));
    if (isset($short[$key])) return $short[$key];
    return $label;
}
