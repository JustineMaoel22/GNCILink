<?php
if (defined('GNC_SITE_DISCLAIMER') || isset($_GET['ce_preview'])) return;
define('GNC_SITE_DISCLAIMER', true);
?>
<link rel="stylesheet" href="/assets/css/site-disclaimer.css?v=<?= filemtime(__DIR__.'/../assets/css/site-disclaimer.css') ?>">
<dialog id="gncSiteDisclaimer" aria-labelledby="gncDisclaimerTitle" aria-describedby="gncDisclaimerMessage">
    <h2 id="gncDisclaimerTitle">Website Notice</h2>
    <div id="gncDisclaimerMessage">
        <p>GNCILink is currently in BETA version as part of a <strong>capstone project</strong> and is not yet officially managed by Guagua National Colleges, Inc. (GNCI).</p>
        <p>Some features and information may be unavailable. If you encounter any bugs or errors, please report them through our Contact Us form. Thank you for your patience and understanding.</p>
    </div>
    <form method="dialog"><button type="submit" autofocus>Understood</button></form>
</dialog>
<script src="/assets/js/site-disclaimer.js?v=<?= filemtime(__DIR__.'/../assets/js/site-disclaimer.js') ?>" defer></script>
