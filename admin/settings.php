<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
initSession();
requireLogin();
$canManageWebsiteSettings = hasPermission('manage_settings');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManageWebsiteSettings) rbacFail('Your role cannot change website settings.',403);
    try {
        saveAiImageCheckerSetting($_POST);
        $_SESSION['settings_saved'] = true;
        header('Location: /admin/settings.php');
        exit;
    } catch (InvalidArgumentException $e) {
        http_response_code(422);
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Admin settings save failed: ' . $e->getMessage());
        http_response_code(500);
        $error = 'Could not save settings. Please try again.';
    }
}
$enabled = $canManageWebsiteSettings ? aiImageCheckerEnabled() : true;
$saved = !empty($_SESSION['settings_saved']);
unset($_SESSION['settings_saved']);
$pageTitle = 'Settings';
require __DIR__ . '/../components/header-admin.php';
?>
<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p>Manage preferences for the admin panel.</p>
    </div>
</div>
<section class="data-card p-4 mb-4"><h2 class="data-card-title">Account Security</h2><p>Update your own password using your current password. Other sessions and remembered devices will be revoked after the change.</p><a href="/admin/account-password.php" class="btn btn-outline-secondary">Change Password</a></section>
<?php if ($canManageWebsiteSettings): ?>
<section class="data-card p-4 mb-4">
    <h2 class="data-card-title">Facebook Integration</h2>
    <p>Connect the test Facebook Page and review announcement publishing deliveries.</p>
    <a href="/admin/facebook-integration.php" class="btn btn-outline-secondary">Manage Facebook Integration</a>
</section>
<?php if ($saved): ?><div class="alert alert-success" role="status">Settings saved.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="data-card p-4" aria-labelledby="image-checker-heading" style="max-width:760px">
    <h2 id="image-checker-heading" class="data-card-title mb-2"><i class="bi bi-image me-2" aria-hidden="true"></i>AI image checker</h2>
    <p class="text-muted small" id="image-checker-description">Check uploaded people photos for non-person images and AI-generated content. This setting applies to all admin users. File type and size limits always apply.</p>
    <form method="post" action="/admin/settings.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-4">
            <label class="form-label" for="ai-image-checker">AI image checker</label>
            <select class="form-select" id="ai-image-checker" name="ai_image_checker_enabled" aria-describedby="image-checker-description" style="max-width:240px">
                <option value="1"<?= $enabled ? ' selected' : '' ?>>On</option>
                <option value="0"<?= !$enabled ? ' selected' : '' ?>>Off</option>
            </select>
        </div>
        <button type="submit" class="btn-gnc-primary">Save settings</button>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../components/footer-admin.php'; ?>
