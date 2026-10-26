<?php
/** Role-filtered instructions for existing features; never grant permissions here. */
function adminOnboardingGuides(): array {
    $super = rbacIsSuper();
    $items = [];
    $add = static function ($permission, $route, $title, $text) use (&$items) {
        if ($permission === null || hasPermission($permission)) $items[] = compact('route', 'title', 'text');
    };
    $add(null, '/admin/admin-dash.php', 'Dashboard', (rbacRole() === 'The Mirror' ? 'Start here for your own news and announcement counts, recent posts and submission status.' : 'Start here for content counts, recent updates and website activity available to your role.') . ' Use the shortcuts or sidebar to open a feature; on a phone, use the menu button at the top.');
    $add('create_announcement', '/admin/announcement.php', 'Announcements', 'Open Announcements and choose New Announcement. Enter a clear title and message, review the available fields and check your work before saving. ' . ($super ? 'Choose the appropriate status before saving. Publishing makes the announcement visible to visitors.' : 'Submit your changes for approval. They do not become public until a Super Admin approves them.') . (rbacRole() === 'The Mirror' ? ' You can edit only your own content.' : ''));
    $add('create_news', '/admin/news.php', 'News', 'Open News to create a news article. Add a title, article text and any relevant image. Check names, dates and links before saving. ' . ($super ? 'Use the available status controls to save or publish the article.' : 'Send the article for approval, then follow its progress in My Submissions.'));
    $add('manage_events', '/admin/events.php', 'Events', 'Create or edit a campus event, confirm its dates and location, and review the event details before saving. ' . ($super ? 'Publish only when the details are ready for visitors.' : 'Your changes go to a Super Admin for approval.'));
    $add('create_announcement', '/admin/facebook-posts.php', 'Facebook Posts', rbacRole() === 'The Mirror' ? 'Browse posts imported from the school Facebook Page. Use the search box to find a post and open its original Facebook link. These posts are read only for your role.' : 'Review the imported Facebook posts. Open the original post when you need to check its source. Category and pin controls are available only to Super Admin.');
    $add('manage_page_content', '/admin/content-edit.php', 'Content Editor', 'Choose the website page you want to update. Click editable text or pictures in the preview. Save your draft to keep work in progress, preview and review your changes, then ' . ($super ? 'publish when ready. Check the public page afterward.' : 'submit the saved changes for approval. Saving a draft alone does not update the public website.'));
    if ($super) $add('manage_approvals', '/admin/submissions.php', 'Approvals', 'Open a pending submission and review the proposed changes before approving. Approval applies the changes to the website. Reject a submission with a clear reason when corrections are needed. Review website-page requests in the Content Editor review panel when shown there.');
    else $add('view_own_submissions', '/admin/submissions.php', 'My Submissions', 'Check whether your submitted changes are pending, approved or rejected. Read the reason for a rejected submission, correct the content and submit it again. A pending submission has not changed the public website.');
    $add('manage_users', '/admin/users.php', 'Users', 'Create an account with an accessible email and the correct role. Copy the one-time temporary password and share it privately. The new user verifies their email and changes that password before using admin features. Review roles carefully before changing access.');
    $add('view_logs', '/admin/logs.php', 'Activity Logs', 'Review recorded user actions to understand who changed content and when. Use the available filters to find the action you need. Do not share private account or security information.');
    $add(null, '/admin/settings.php', 'Settings', 'Open Account Security to change your own password. Enter your current password and confirm a new, unique password. Other sessions and remembered devices are revoked after a successful change.' . (hasPermission('manage_settings') ? ' Website settings are also available here; review each option before saving.' : ' Website-wide settings are restricted to roles with permission.'));
    return $items;
}

function adminOnboardingState(int $id): ?string {
    try {
        $q = getDB()->prepare('SELECT status FROM admin_onboarding WHERE user_id=?');
        $q->execute([$id]);
        return $q->fetchColumn() ?: 'pending';
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S02') error_log('Admin onboarding status unavailable.');
        return null; // Missing migration must not interrupt authentication or editing.
    }
}

function adminOnboardingSave(int $id, string $status): void {
    if (!in_array($status, ['completed', 'skipped'], true)) throw new InvalidArgumentException('Choose a valid tutorial action.');
    $q = getDB()->prepare('INSERT INTO admin_onboarding(user_id,status,finished_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),finished_at=NOW()');
    $q->execute([$id, $status]);
}
