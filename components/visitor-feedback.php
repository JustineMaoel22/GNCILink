<?php
$feedbackPath=(string)parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if (isset($_GET['ce_preview']) || str_starts_with($feedbackPath,'/admin') || http_response_code()===404 || defined('GNC_FEEDBACK_RENDERED')) return;
define('GNC_FEEDBACK_RENDERED',true);
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
        <form id="gncFeedbackRatings" class="gnc-feedback-form gnc-feedback-ratings" hidden>
            <p class="gnc-feedback-help">Answer all six questions. 1 = Lowest (Poor), 5 = Highest (Excellent).</p>
            <div id="gncFeedbackQuestions"></div>
            <button type="submit" class="gnc-feedback-primary">Submit ratings</button>
        </form>
        <p id="gncFeedbackStatus" role="status" aria-live="polite">Loading feedback...</p>
        <p class="gnc-feedback-help" id="gncFeedbackHelp">All ratings are required. After submitting, choose I've submitted to check your saved response.</p>
        <div class="gnc-feedback-actions">
            <button type="button" class="gnc-feedback-secondary" data-feedback-dismiss>Not Now</button>
            <button type="button" id="gncFeedbackSubmitted" class="gnc-feedback-primary">I've submitted</button>
        </div>
    </div>
</dialog>
<script src="/assets/js/visitor-feedback.js?v=<?= filemtime(__DIR__.'/../assets/js/visitor-feedback.js') ?>" defer></script>
