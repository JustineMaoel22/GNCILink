<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/admin-functions.php';
require_once __DIR__ . '/../includes/facebook-publishing.php';
require_once __DIR__ . '/../includes/facebook-oauth.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
initSession();
requireLogin();
requirePermission('manage_settings');
$error = $_SESSION['facebook_integration_error'] ?? '';
$notice = $_SESSION['facebook_integration_notice'] ?? '';
unset($_SESSION['facebook_integration_notice'], $_SESSION['facebook_integration_error']);
$ready = facebookPublishingReady();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) throw new FacebookPublishingError('Invalid request token. Reload and try again.');
        if (!$ready) throw new FacebookPublishingError('Run sql/facebook-publishing-migration.sql first.');
        $action = $_POST['action'] ?? '';
        if ($action === 'oauth_start') {
            header('Location: ' . facebookOAuthStart());
            exit;
        }
        if ($action === 'connect_page') {
            facebookOAuthConnect(is_string($_POST['page_id'] ?? null) ? $_POST['page_id'] : '', ($_POST['authorize_page'] ?? '') === '1');
            $_SESSION['facebook_integration_notice'] = 'Facebook Page connected. No announcements were reposted.';
        } elseif ($action === 'cancel_connection') {
            unset($_SESSION['facebook_oauth_pending']);
            $_SESSION['facebook_integration_notice'] = 'Page selection cancelled. Your existing connection has not changed.';
        } elseif ($action === 'disconnect') {
            facebookOAuthDisconnect();
            $_SESSION['facebook_integration_notice'] = 'Facebook disconnected. Existing Facebook posts remain on Facebook.';
        } else {
        facebookPublishingLocked(function () {
            $action = $_POST['action'] ?? '';
            $db = getDB();
            if ($action === 'reconcile') {
                $id = (int)($_POST['announcement_id'] ?? 0);
                $stmt = $db->prepare('SELECT * FROM announcement_facebook_deliveries WHERE announcement_id=?');
                $stmt->execute([$id]);
                $delivery = $stmt->fetch();
                if (!$delivery || !in_array($delivery['state'], ['sending', 'unknown'], true)) throw new FacebookPublishingError('Only uncertain deliveries can be reconciled.');
                $postId = trim($_POST['post_id'] ?? '');
                if ($postId !== '') {
                    if (!preg_match('/^' . preg_quote($delivery['page_id'], '/') . '_\d+$/D', $postId)) throw new FacebookPublishingError('Enter the full PageID_PostID for the original Page.');
                    $config = facebookPublishingCredentials();
                    if ($config['page'] !== $delivery['page_id']) throw new FacebookPublishingError('Restore credentials for the original Page to verify this delivery.');
                    facebookValidatePage();
                    $post = facebookGraphRequest('GET', $postId, ['fields' => 'id,is_published,permalink_url']);
                    if (empty($post['is_published']) || ($post['id'] ?? '') !== $postId) throw new FacebookPublishingError('A published post could not be verified.');
                    $db->prepare("UPDATE announcement_facebook_deliveries SET state='published',post_id=?,post_url=?,last_error=NULL,updated_at=NOW() WHERE announcement_id=?")
                        ->execute([$postId, facebookPublishingPostUrl($post['permalink_url'] ?? null, $postId), $id]);
                    $message = 'Delivery matched to the existing Facebook post. No new post was sent.';
                } else {
                    if (($_POST['confirmed_absent'] ?? '') !== '1') throw new FacebookPublishingError('Check the original Page and confirm that no post exists first.');
                    $db->prepare("UPDATE announcement_facebook_deliveries SET state='failed',last_error='Administrator checked the original Page and confirmed no post exists.',updated_at=NOW() WHERE announcement_id=?")->execute([$id]);
                    $message = 'Retry unlocked. Edit the announcement and explicitly select Facebook publishing to retry.';
                }
            } else { throw new FacebookPublishingError('Unknown action.'); }
            logActivity((int)$_SESSION['user_id'], 'FACEBOOK_' . strtoupper($action), null, null, $message);
            $_SESSION['facebook_integration_notice'] = $message;
        });
        }
        header('Location: /admin/facebook-integration.php');
        exit;
    } catch (FacebookPublishingError $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { $error = 'Facebook settings could not be saved. No automatic publishing will be attempted.'; }
}
$connection = facebookPublishingConnection();
$oauthConfigured = false;
try { facebookOAuthSettings(); $oauthConfigured = facebookOAuthReady(); }
catch (FacebookPublishingError $e) { /* Show a plain setup notice to administrators. */ }
$pendingPages = facebookOAuthPending();
$deliveries = $ready ? getDB()->query('SELECT * FROM announcement_facebook_deliveries ORDER BY updated_at DESC LIMIT 50')->fetchAll() : [];
$pageTitle = 'Facebook Integration';
require __DIR__ . '/../components/header-admin.php';
$escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="page-header"><div><h1>Facebook Integration</h1><p>Connect Facebook to publish announcements to your Page.</p></div></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= $escape($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success" role="status"><?= $escape($notice) ?></div><?php endif; ?>
<?php if (!$oauthConfigured): ?><div class="alert alert-warning">Facebook connection needs one-time setup by your site developer. You do not need to enter an access token. Website publishing remains available.</div><?php endif; ?>
<section class="data-card p-4 mb-4">
    <h2 class="data-card-title">Connected Facebook Page</h2>
    <p><?= $connection ? $escape($connection['page_name']) . ' — ' . $escape($connection['page_id']) . ' (' . $escape($connection['environment']) . ')' : 'No Page connected.' ?>
        <strong><?= $connection && $connection['enabled'] ? 'Enabled' : 'Disconnected' ?></strong></p>
    <p class="text-muted">Sign into Facebook, allow Page access, then choose the Page you want to connect. Access is handled securely for you. No old announcements are sent automatically.</p>
    <?php if (env('FB_PUBLISH_ENVIRONMENT', 'test') === 'test'): ?><p class="alert alert-info">Test mode: only the configured test Page can be connected.</p><?php endif; ?>
    <?php if ($pendingPages): ?>
    <form method="post" class="mb-3" id="facebook-page-selection">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="connect_page">
        <label class="form-label" for="facebook-page-choice">Choose your Facebook Page</label>
        <select class="form-select mb-3" id="facebook-page-choice" name="page_id" required>
            <option value="">Choose a Page</option>
            <?php foreach ($pendingPages as $page): ?><option value="<?= $escape($page['id']) ?>"><?= $escape($page['name']) ?> · <?= $escape($page['id']) ?></option><?php endforeach; ?>
        </select>
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="authorize_page" value="1" required> I authorize publishing new announcements to the selected Page.</label>
        <button class="btn btn-gnc-gold">Connect selected Page</button>
    </form>
    <form method="post" class="mb-3"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="cancel_connection"><button class="btn btn-outline-secondary">Cancel Page selection</button></form>
    <?php endif; ?>
    <form method="post" class="mb-3" id="facebook-connect-form">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="oauth_start">
        <button class="btn btn-gnc-gold" <?= !$oauthConfigured ? 'disabled' : '' ?>><i class="bi bi-facebook me-2" aria-hidden="true"></i>Connect Facebook</button>
    </form>
    <form method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="disconnect"><button class="btn btn-outline-secondary" <?= !$ready ? 'disabled' : '' ?>>Disconnect Page</button></form>
</section>
<section class="data-card p-4">
    <h2 class="data-card-title">Recent Facebook deliveries</h2>
    <p class="text-muted">An uncertain delivery is locked to prevent duplicates. Check the original Page first. Matching a post or unlocking a retry never sends a post.</p>
    <?php if (!$deliveries): ?><p>No publishing attempts.</p><?php endif; ?>
    <?php foreach ($deliveries as $delivery): ?>
    <div class="border rounded p-3 mb-3">
        <p>Announcement #<?= (int)$delivery['announcement_id'] ?> · Page <?= $escape($delivery['page_id']) ?> · <strong><?= $escape($delivery['state']) ?></strong></p>
        <?php if ($delivery['post_url']): ?><a href="<?= $escape(facebookPublishingViewPostUrl($delivery['post_url'], $delivery['post_id'] ?? '')) ?>" target="_blank" rel="noopener noreferrer">View Facebook post</a><?php endif; ?>
        <?php if ($delivery['last_error']): ?><p class="text-danger"><?= $escape($delivery['last_error']) ?></p><?php endif; ?>
        <?php if (in_array($delivery['state'], ['sending', 'unknown'], true)): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="reconcile"><input type="hidden" name="announcement_id" value="<?= (int)$delivery['announcement_id'] ?>">
            <label class="form-label" for="reconcile-<?= (int)$delivery['announcement_id'] ?>">Existing Facebook post ID (PageID_PostID)</label>
            <input class="form-control mb-2" id="reconcile-<?= (int)$delivery['announcement_id'] ?>" name="post_id" placeholder="PageID_PostID">
            <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirmed_absent" value="1"> If no ID: I checked the original Page and confirmed that no matching post exists. Allow a manual retry.</label>
            <button class="btn btn-outline-secondary">Reconcile delivery</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/../components/footer-admin.php'; ?>
