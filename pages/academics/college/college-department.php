<?php
// Shared layout for College, Basic Education and Graduate School departments.
$departmentRequestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$departmentSlug = isset($_GET['slug']) && is_string($_GET['slug']) ? $_GET['slug'] : '';
if (preg_match('~^/pages/academics/college/college-department/([a-z0-9-]+)/?$~D', $departmentRequestPath, $departmentPathMatch)) {
    // The path identifies the department, even if a conflicting query was supplied.
    $_GET['slug'] = $departmentPathMatch[1];
} elseif (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)
    && preg_match('~^/pages/academics/college/college-department(?:\.php)?$~D', $departmentRequestPath)
    && preg_match('/^[a-z0-9-]+$/D', $departmentSlug)) {
    $departmentQuery = $_GET;
    unset($departmentQuery['slug']);
    $departmentQueryString = http_build_query($departmentQuery, '', '&', PHP_QUERY_RFC3986);
    header('Location: /pages/academics/college/college-department/' . rawurlencode($departmentSlug)
        . ($departmentQueryString !== '' ? '?' . $departmentQueryString : ''), true, 302);
    exit;
}
require __DIR__ . '/../../../components/department-page.php';
