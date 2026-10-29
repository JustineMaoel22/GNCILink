<?php
require_once __DIR__.'/../includes/privacy.php';
if (defined('GNC_PRIVACY_CONTROLS') || isset($_GET['ce_preview'])) return;
define('GNC_PRIVACY_CONTROLS',true);
?>
<section class="gnc-privacy-banner" id="gncPrivacyBanner" aria-labelledby="gncPrivacyHeading" hidden>
    <div><h2 id="gncPrivacyHeading">Your privacy choices</h2>
    <p>Optional visitor statistics, personalized suggestions and reminders, and embedded Google/Meta services stay off until you choose. Essential security and requested services remain available.</p></div>
    <div class="gnc-privacy-actions">
        <button type="button" data-privacy-action="accept">Accept optional</button>
        <button type="button" data-privacy-action="reject">Reject optional</button>
        <button type="button" data-privacy-action="manage">Manage choices</button>
    </div>
</section>
<dialog class="gnc-privacy-dialog" id="gncPrivacyDialog" aria-labelledby="gncPrivacyDialogTitle">
    <h2 id="gncPrivacyDialogTitle">Privacy &amp; cookie settings</h2>
    <p>Choose separately. You can change your choices here at any time. We remember them in a first-party cookie for 180 days.</p>
    <p><strong>Essential:</strong> security/session cookies, your chosen privacy settings, and storage needed for services you request, such as chat. These are separate from optional tracking. Site fonts and libraries also contact their delivery providers.</p>
    <form id="gncPrivacyForm">
        <label><input type="checkbox" name="analytics"> <span><strong>Visitor statistics</strong> — a random one-year visitor cookie, its hashed identifier and visit dates, and page-view totals. No visitor profile is attached to individual page totals.</span></label>
        <label><input type="checkbox" name="personalization"> <span><strong>Personalization &amp; reminders</strong> — recent page interests and reading time in this browser for up to 30 days; local timing/dismissal of feedback invitations for 7 or 30 days.</span></label>
        <label><input type="checkbox" name="external"> <span><strong>Embedded services</strong> — Google Maps, Facebook page plugins and the Google Forms feedback form may receive your IP address, browser information and provider cookies. Feedback answers go to the form owner through Google. The feedback invitation also needs personalization enabled.</span></label>
        <p>Provider information: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Google privacy</a> and <a href="https://www.facebook.com/privacy/policy/" target="_blank" rel="noopener noreferrer">Meta privacy</a>. External links open those providers only when you follow them.</p>
        <p id="gncPrivacySignal" role="status"></p>
        <div class="gnc-privacy-actions"><button type="submit">Save choices</button><button type="button" data-privacy-action="reject">Reject optional</button><button type="button" data-privacy-action="close">Close</button></div>
    </form>
</dialog>
