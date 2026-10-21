<?php
// CLI-only rendering checks for the shared department settings drawer.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
initSession();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'Super Admin';

function renderDepartmentManager(bool $basic): string {
    $csrfToken = generateCSRFToken();
    ob_start();
    require __DIR__ . '/../components/' . ($basic ? 'basic-edu-media-manager.php' : 'department-media-manager.php');
    return ob_get_clean();
}

$scripts = [];
foreach ([false, true] as $basic) {
    $html = renderDepartmentManager($basic);
    preg_match_all('/data-dept-id="(\d+)"/', $html, $matches);
    $actual = array_map('intval', $matches[1]);
    $expected = [];
    $basicSlugs = ['casa-gradeschool', 'hs-monte', 'jhs-regular', 'shs-regular'];
    foreach (getDepartments() as $department) {
        $include = $basic ? in_array($department['slug'], $basicSlugs, true) : publicDepartmentIsCollege($department);
        if (!$include) continue;
        $expected[] = (int)$department['dept_id'];
        $url = $basic
            ? '/pages/academics/basic-edu/basic-edu-department/' . $department['slug'] . '.php'
            : '/pages/academics/college/college-department.php?slug=' . $department['slug'];
        if (!str_contains($html, 'data-public-url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"')) {
            throw new RuntimeException('Incorrect public link: ' . $department['slug']);
        }
    }
    sort($actual); sort($expected);
    if (!$actual || $actual !== $expected) throw new RuntimeException('Incorrect department scope');
    if ($basic && count($actual) !== 4) throw new RuntimeException('Expected four Basic Education departments');
    if (substr_count($html, '<details class="data-card mb-3 dept-media-card dept-acc"') !== count($actual)) {
        throw new RuntimeException('Departments must use shared collapsible editing cards');
    }
    if (str_contains($html, 'id="btn-add-department"') === $basic) throw new RuntimeException('Creation controls must remain college-only');
    foreach (['data-tab="text"', 'data-tab="logo"', 'data-tab="programs"', 'data-tab="dean"', 'data-tab="more"', 'btn-save-all'] as $control) {
        if (!str_contains($html, $control)) throw new RuntimeException('Missing shared control: ' . $control);
    }
    if (str_contains($html, 'basic-edu-media-intro')) throw new RuntimeException('Legacy Basic Education form is still mounted');
    preg_match_all('~<script>(.*?)</script>~s', $html, $inline);
    array_push($scripts, ...$inline[1]);
}
if (in_array('--scripts', $argv, true)) {
    echo json_encode($scripts, JSON_THROW_ON_ERROR);
} else {
    echo "PASS: shared collapsible department editors, correct college/basic scopes, public links, editing tabs and college-only creation.\n";
}
