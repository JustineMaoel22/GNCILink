<?php
require_once __DIR__ . '/../includes/text-layout.php';
$gncTextLayout=[];
try { $gncTextLayout=textLayoutLoad(getDB(),$_SERVER['REQUEST_URI']??'/'); }
catch (InvalidArgumentException $e) { /* Admin screens do not have public placements. */ }
?>
<script>window.GNC_TEXT_LAYOUT=<?= json_encode($gncTextLayout,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="/assets/js/text-layout.js?v=<?= filemtime(__DIR__.'/../assets/js/text-layout.js') ?>"></script>
