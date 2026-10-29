<?php
$feedbackPath=(string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if (isset($_GET['ce_preview']) || str_starts_with($feedbackPath,'/admin') || http_response_code()===404 || defined('GNC_FEEDBACK_RENDERED')) return;
define('GNC_FEEDBACK_RENDERED',true);
// Public responder URL supplied by the site owner. No deployment or secret is required.
$feedbackFormUrl='https://docs.google.com/forms/d/e/1FAIpQLSextXO7NUBv8LnF-PArXycrebe1ViNF9YsdQGP23yRRTaYsrQ/viewform';
$feedbackEscape=static fn($value)=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/visitor-feedback.css?v=<?= filemtime(__DIR__.'/../assets/css/visitor-feedback.css') ?>">
<button type="button" id="gncFeedbackTrigger" class="gnc-feedback-trigger" aria-label="Rate us: open feedback form" aria-haspopup="dialog" aria-controls="gncVisitorFeedback" hidden>
    <span aria-hidden="true">☆</span> RATE US
</button>
<dialog id="gncVisitorFeedback" class="gnc-feedback" aria-labelledby="gncFeedbackTitle" aria-describedby="gncFeedbackDescription" hidden>
    <div class="gnc-feedback-inner">
        <button type="button" class="gnc-feedback-close" aria-label="Close feedback dialog" data-feedback-dismiss>&times;</button>
        <span class="gnc-feedback-eyebrow">GNCILink</span>
        <h2 id="gncFeedbackTitle">Rate Your Experience</h2>
        <p id="gncFeedbackDescription">Help us improve GNCILink by rating your experience.</p>
        <iframe id="gncFeedbackGoogleForm" class="gnc-feedback-form" title="GNCI Website Feedback form" data-src="<?= $feedbackEscape($feedbackFormUrl.'?embedded=true') ?>" referrerpolicy="strict-origin-when-cross-origin"></iframe>
        <p class="gnc-feedback-help">Submit your answers in the Google Form above, then choose I've submitted to pause reminders.</p>
        <a class="gnc-feedback-fallback" href="<?= $feedbackEscape($feedbackFormUrl) ?>" target="_blank" rel="noopener noreferrer">Open form in a new tab</a>
        <div class="gnc-feedback-actions">
            <button type="button" class="gnc-feedback-secondary" data-feedback-dismiss>Not Now</button>
            <button type="button" id="gncFeedbackSubmitted" class="gnc-feedback-primary">I've submitted</button>
        </div>
    </div>
</dialog>
<script src="/assets/js/visitor-feedback.js?v=<?= filemtime(__DIR__.'/../assets/js/visitor-feedback.js') ?>" defer></script>
