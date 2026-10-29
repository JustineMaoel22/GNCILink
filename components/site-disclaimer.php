<?php
if (defined('GNC_SITE_DISCLAIMER') || isset($_GET['ce_preview'])) return;
define('GNC_SITE_DISCLAIMER', true);
?>
<link rel="stylesheet" href="/assets/css/site-disclaimer.css?v=<?= filemtime(__DIR__.'/../assets/css/site-disclaimer.css') ?>">
<dialog id="gncSiteDisclaimer" aria-labelledby="gncDisclaimerTitle" aria-describedby="gncDisclaimerMessage">
    <h2 id="gncDisclaimerTitle">Website notice</h2>
    <p id="gncDisclaimerMessage">Currently work in progress so some information may not be available as of this time</p>
    <form method="dialog"><button type="submit" autofocus>Understood</button></form>
</dialog>
<script src="/assets/js/site-disclaimer.js?v=<?= filemtime(__DIR__.'/../assets/js/site-disclaimer.js') ?>" defer></script>
