<?php
require_once __DIR__.'/../admin/settings-functions.php';
if (!isset($_GET['ce_preview']) && http_response_code() !== 404 && !defined('GNC_RECOMMENDATIONS_RENDERED') && pageSuggestionsEnabled()):
define('GNC_RECOMMENDATIONS_RENDERED', true); ?>
<link rel="stylesheet" href="/assets/css/recommendations.css?v=<?= filemtime(__DIR__.'/../assets/css/recommendations.css') ?>">
<dialog class="gnc-rec" id="gncRecommendations" aria-labelledby="gncRecTitle" aria-describedby="gncRecSummary" hidden>
    <div class="gnc-rec-inner">
        <button type="button" class="gnc-rec-close" id="gncRecClose" aria-label="Close page suggestions" autofocus><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        <div class="gnc-rec-heading"><div><span class="gnc-rec-eyebrow">Discover GNC</span><h2 id="gncRecTitle">You May Also Like</h2></div></div>
        <p id="gncRecSummary">Explore popular pages and recent updates.</p>
        <div id="gncRecPrivacy" class="gnc-rec-privacy" hidden>
            <p>Personalize suggestions using your visits and active reading time. Your browsing profile stays in this browser for up to 30 days and is never sent to our server. You can turn this off and clear it anytime.</p>
            <button type="button" id="gncRecEnable">Enable personalization</button>
            <button type="button" id="gncRecDisable">Use general suggestions &amp; clear history</button>
            <span id="gncRecStatus" role="status"></span>
        </div>
        <div class="gnc-rec-grid" id="gncRecCards"></div>
        <div class="gnc-rec-actions"><button type="button" class="gnc-rec-control" id="gncRecSettings" aria-expanded="false" aria-controls="gncRecPrivacy">Personalization settings</button><button type="button" id="gncRecDismiss">Keep exploring this page</button></div>
    </div>
</dialog>
<script src="/assets/js/recommendation-engine.js?v=1" defer></script>
<script src="/assets/js/recommendations.js?v=<?= filemtime(__DIR__.'/../assets/js/recommendations.js') ?>" defer></script>
<?php endif; ?>
