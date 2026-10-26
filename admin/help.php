<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
initSession(); requireLogin();
require_once __DIR__ . '/../includes/admin-onboarding.php';
$pageTitle = 'Help / User Guide';
require __DIR__ . '/../components/header-admin.php';
$guideEscape = static fn($s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header"><div><h1>Help / User Guide</h1><p>A guide to your <?= $guideEscape(rbacRole()) ?> workspace.</p></div><button type="button" class="btn btn-gnc-gold" data-start-onboarding>Start guided walkthrough</button></div>
<section class="data-card p-4 mb-4"><h2 class="data-card-title">Getting started</h2>
<ol><li>Sign in with the email and temporary password shared by your Super Admin.</li><li>Enter the verification code sent to your email. Check the spam folder if needed.</li><li>Choose your own password when prompted, save it, then continue to your workspace.</li><li>Use the sidebar to open a feature. On mobile, open the sidebar with the menu button.</li></ol>
<p class="mb-0">The walkthrough highlights the controls available to your role. Use Next or Back to move through the guide. Skip closes it and saves that choice. Finish records completion. You can restart it here anytime.</p></section>
<?php foreach (adminOnboardingGuides() as $guide): ?>
<section class="data-card p-4 mb-4"><h2 class="data-card-title"><?= $guideEscape($guide['title']) ?></h2><p><?= $guideEscape($guide['text']) ?></p><a href="<?= $guideEscape($guide['route']) ?>">Open <?= $guideEscape($guide['title']) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a></section>
<?php endforeach; ?>
<section class="data-card p-4 mb-4"><h2 class="data-card-title">Save, review and sign out</h2><p>Check the success or error message after saving. If something fails, keep the page open and correct the highlighted fields. Do not leave an editor with unsaved changes.</p><p>Use the public-site shortcut to check published content. On a shared device, select Forget this device when signing out. Never share passwords or verification codes.</p><p class="mb-0">If a menu or action is unavailable, ask your Super Admin about your assigned access. Use Forgot Password on the login page if you cannot sign in.</p></section>
<?php require __DIR__ . '/../components/footer-admin.php'; ?>
