<?php
/**
 * Graduate School landing page.
 *
 * The page reuses the dynamic department renderer. Its content and program
 * cards are stored in the existing departments/programs tables and edited in
 * the Content Editor preview.
 */
require_once __DIR__ . '/../../../components/department-public.php';

// Create the landing-page record and migrate the legacy program cards when a
// manager first opens this page in Content Editor. Public requests stay read-only.
$cePreview = cePreviewCanManage();
if ($cePreview) ensureGraduateSchoolDepartment();

$_GET['slug'] = 'graduate-school';
require __DIR__ . '/../college/college-department.php';
