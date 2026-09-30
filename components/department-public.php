<?php

if (!function_exists('getDB')) {
    require_once __DIR__ . '/../config/config.php';
}
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/pdf-viewer.php';

function publicDepartmentsBySlug(): array {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (getDepartments() as $d) {
            if (!empty($d['slug'])) {
                $map[$d['slug']] = $d;
            }
        }
    }
    return $map;
}

function publicDepartment(string $slug): ?array {
    return publicDepartmentsBySlug()[$slug] ?? null;
}

function publicDepartmentDean(?array $dept): array {
    return [
        'photo_url' => $dept['dean_photo_url'] ?? null,
        'name'      => $dept['dean_name']   ?? '',
        'title'     => $dept['dean_title']  ?? '',
        'bio'       => $dept['dean_bio']    ?? '',
        'phone'     => $dept['dean_phone']  ?? '',
        'email'     => $dept['dean_email']  ?? '',
        'office'    => $dept['dean_office'] ?? '',
        'hours'     => $dept['dean_hours']  ?? '',
    ];
}