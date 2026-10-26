<?php
if (!empty($gncEmbedded) || !empty($_SESSION['must_change_password']) || !isLoggedIn()) return;
require_once __DIR__ . '/../includes/admin-onboarding.php';
$onboardingStatus = adminOnboardingState((int)$_SESSION['user_id']);
$onboardingHome = parse_url(rbacHome(), PHP_URL_PATH);
$onboardingAuto = $onboardingStatus === 'pending' && rbacRequestRoute() === $onboardingHome;
$onboardingSteps = [['title' => 'Welcome to GNC Admin Panel', 'text' => 'Your account is ready. This short guide introduces your ' . rbacRole() . ' workspace. It only explains controls; it will not edit or publish content.', 'selector' => '.topbar-breadcrumb']];
foreach (adminOnboardingGuides() as $guide) {
    $onboardingSteps[] = ['title' => $guide['title'], 'text' => $guide['text'], 'selector' => $guide['route'] === '/admin/settings.php' ? '.topbar-right a[href="/admin/settings.php"]' : '.sidebar-link[href="' . $guide['route'] . '"]'];
}
$onboardingSteps[] = ['title' => 'Check the public website', 'text' => 'This shortcut opens the website in another tab. Review your published changes there. Drafts and pending submissions are not public yet.', 'selector' => '.topbar-right a[title="View Public Site"]'];
$onboardingSteps[] = ['title' => 'Create and save content', 'text' => 'Use this button to open a new content form. Complete its fields, check the content and use its save or submit control. Look for the success message before leaving. This tutorial will not open or submit a form for you.', 'selector' => '#btn-new-announcement, #btn-new-news', 'optional' => true];
$onboardingSteps[] = ['title' => 'Help whenever you need it', 'text' => 'Open Help / User Guide anytime to read the instructions or restart this walkthrough. Select Finish to save completion, or Skip to dismiss the automatic guide.', 'selector' => '[data-onboarding-help]'];
?>
<link rel="stylesheet" href="/assets/css/admin-onboarding.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-onboarding.css') ?>">
<script type="application/json" id="adminOnboardingData"><?= json_encode(['auto' => $onboardingAuto, 'persist' => $onboardingStatus !== null, 'steps' => $onboardingSteps, 'csrf' => $csrfToken], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/admin-onboarding.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin-onboarding.js') ?>" defer></script>
