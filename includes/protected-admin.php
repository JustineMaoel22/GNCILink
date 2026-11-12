<?php
/** Stable login identity shared by account deletion and role-change protection. */
function isProtectedDefaultAdminUser(array $user): bool {
    return strcasecmp(trim((string)($user['email'] ?? '')), 'gnciweb.admin@gmail.com') === 0;
}
