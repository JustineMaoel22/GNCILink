<?php
require_once __DIR__ . '/../includes/text-layout.php';
$gncTextLayout=[];
try { $gncTextLayout=textLayoutLoad(getDB(),$_SERVER['REQUEST_URI']??'/'); }
catch (InvalidArgumentException $gncTextLayoutError) { /* Preserve the including page's $e escaping helper. */ }
?>
<script>window.GNC_TEXT_LAYOUT=<?= json_encode($gncTextLayout,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script>window.GNC_TEXT_FONTS=<?= json_encode(textLayoutFonts(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="/assets/js/text-layout.js?v=<?= filemtime(__DIR__.'/../assets/js/text-layout.js') ?>"></script>
<script src="/assets/js/inline-text-style.js?v=<?= filemtime(__DIR__.'/../assets/js/inline-text-style.js') ?>"></script>
