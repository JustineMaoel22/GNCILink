<?php
// These rendering checks do not connect to the database or touch real accounts.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/program-page-public.php';
function introCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$base = ['program_code' => 'BEEd', 'program_name' => 'Elementary Education', 'description' => 'Card description'];
foreach ([null, '{}', '{"subtitle":"Motto"}'] as $json) {
    $row = hydrateProgramPage($base + ['page_content' => $json]);
    introCheck($row['content']['hero_desc'] === 'Card description', 'Legacy pages retain their introduction when the field was never stored');
}
foreach (['', '   ', 'New introduction', '<p>New introduction</p>'] as $intro) {
    $row = hydrateProgramPage($base + ['page_content' => json_encode(['hero_desc' => $intro])]);
    introCheck($row['content']['hero_desc'] === $intro, 'An explicit introduction, including blank, survives loading');
    introCheck($row['description'] === 'Card description', 'Clearing the introduction preserves the card description');
    $reloaded = hydrateProgramPage($row + []);
    introCheck($reloaded['content']['hero_desc'] === $intro, 'Reload does not restore the card description');
}
introCheck(empty(programPageContentFields()['hero_desc']['warning_required']), 'Introduction is optional in publish checks');
$page = file_get_contents(__DIR__ . '/../pages/academics/college/program.php');
introCheck(str_contains($page, '$heroDesc   = trim($c[\'hero_desc\']);'), 'Rendering must use the saved introduction');
introCheck(str_contains($page, '$heroDesc !== \'\' || $cePreview'), 'Empty introduction stays editable but is hidden for visitors');
echo "PASS: program introductions may be blank, legacy introductions stay visible, and card descriptions stay separate.\n";
