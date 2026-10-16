<?php if (!isset($_GET['ce_preview']) && http_response_code() !== 404 && !defined('GNC_RECOMMENDATIONS_RENDERED')):
define('GNC_RECOMMENDATIONS_RENDERED', true); ?>
<link rel="stylesheet" href="/assets/css/recommendations.css?v=1">
<section class="gnc-rec" id="gncRecommendations" aria-labelledby="gncRecTitle" hidden>
    <div class="gnc-rec-inner">
        <div class="gnc-rec-heading"><div><span class="gnc-rec-eyebrow">Discover GNC</span><h2 id="gncRecTitle">You May Also Like</h2></div>
            <button type="button" class="gnc-rec-control" id="gncRecSettings" aria-expanded="false" aria-controls="gncRecPrivacy">Personalization settings</button></div>
        <p id="gncRecSummary">Explore popular pages and recent updates.</p>
        <div id="gncRecPrivacy" class="gnc-rec-privacy" hidden>
            <p>Personalize suggestions using your visits and active reading time. Your browsing profile stays in this browser for up to 30 days and is never sent to our server. You can turn this off and clear it anytime.</p>
            <button type="button" id="gncRecEnable">Enable personalization</button>
            <button type="button" id="gncRecDisable">Use general suggestions &amp; clear history</button>
            <span id="gncRecStatus" role="status"></span>
        </div>
        <div class="gnc-rec-grid" id="gncRecCards"></div>
    </div>
</section>
<script src="/assets/js/recommendation-engine.js?v=1" defer></script>
<script src="/assets/js/recommendations.js?v=1" defer></script>
<?php endif; ?>
