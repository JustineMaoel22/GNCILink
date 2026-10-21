<?php
require_once __DIR__ . '/../includes/image-framing.php';
$gncFramingAdmin = function_exists('isLoggedIn') && isLoggedIn() && function_exists('generateCSRFToken');
$gncFramingSettings = imageFramingAll();
$gncFramingHashes = [];
if (!$gncFramingAdmin) {
    // Public pages can resolve their displayed photos without exposing paths to draft uploads.
    foreach ($gncFramingSettings as $path => $value) $gncFramingHashes[hash('sha256', $path)] = $value;
    $gncFramingSettings = [];
}
?>
<link rel="stylesheet" href="/assets/css/image-framing.css?v=<?= filemtime(__DIR__ . '/../assets/css/image-framing.css') ?>">
<script>window.GNC_IMAGE_FRAMING_CONFIG = <?= json_encode(['admin' => $gncFramingAdmin, 'csrf' => $gncFramingAdmin ? generateCSRFToken() : '', 'settings' => $gncFramingSettings, 'hashedSettings' => $gncFramingHashes], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/assets/js/image-framing.js?v=<?= filemtime(__DIR__ . '/../assets/js/image-framing.js') ?>"></script>
