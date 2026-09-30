<?php
/**
 * GNC – public-site program helpers.
 *
 * Include once at the top of a program page (after the page defines
 * CURRENT_PROGRAM):
 *
 *   define('CURRENT_PROGRAM', 'BAEL');
 *   require_once __DIR__ . '/../../../../components/program-public.php';
 *
 *   $currentProgram = publicProgram(CURRENT_PROGRAM);                       // curriculum PDF
 *   $programDept    = publicDepartment($currentProgram['dept_slug'] ?? '');  // its department (seal/logo)
 *
 * Everything comes from the database, so a PDF uploaded in Content Editor →
 * Programs, or a logo changed in Content Editor → College Departments, shows
 * up on the program page automatically.
 *
 * (Loads department-public.php too, which provides publicDepartment(), the
 * config/database connection, and the PDF viewer.)
 */
require_once __DIR__ . '/department-public.php';

/**
 * The program with this code (BAEL, BSCE, ...), or null if it isn't in the
 * `programs` table. Callers must cope with null: the page should still
 * render, just without the PDF.
 */
function publicProgram(string $code): ?array {
    return getProgramByCode($code);
}