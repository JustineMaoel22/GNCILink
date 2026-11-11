<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/department-page-content.php';

foreach (['con' => 'CON', ' CO-N ' => 'CO-N', 'C1' => 'C1', str_repeat('a', 20) => str_repeat('A', 20)] as $input => $expected) {
    if (cleanDepartmentShortCode($input) !== $expected) throw new RuntimeException('Short code normalization failed.');
}
foreach (['', ' ', 'C', '-CON', 'College of Nursing', '<CON>', "CON\nX", str_repeat('A', 21), [], null] as $input) {
    try {
        cleanDepartmentShortCode($input);
    } catch (InvalidArgumentException $e) {
        continue;
    }
    throw new RuntimeException('Invalid short code was accepted.');
}
echo "PASS: department short codes normalize case and reject empty, malformed, oversized and non-text inputs.\n";
