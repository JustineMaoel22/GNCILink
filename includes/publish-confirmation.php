<?php
/** A short-lived confirmation covers one multi-request publishing operation. */
function publishNeedsConfirmation(string $route, array $post): bool {
    // These handlers publish unconditionally, regardless of an action field.
    if (in_array($route,['/admin/action/alumni-voices-handler.php','/admin/enrollment-steps.php'],true)) return true;
    $action = $route==='/admin/events.php'?($post['ajax_action']??$post['action']??''):($post['action']??$post['ajax_action']??'');
    if (!is_string($action)) return false;
    if ($route === '/admin/personnel.php') return $action === 'publish';
    if ($route === '/admin/submissions.php') return $action === 'approve';
    if ($route === '/admin/action/editor-draft-handler.php') return in_array($action,['complete_review','record_publication'],true);
    if ($route === '/admin/events.php' && $action === 'import_confirm') return ($post['import_status'] ?? '') === 'published';
    if ($route === '/admin/action/page-content-handler.php' && $action === 'save' && ($post['status'] ?? '') === 'draft') {
        $id = filter_var($post['slide_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === 0) return false;
        if ($id !== false && $id !== null && $id > 0) {
            $query = getDB()->prepare('SELECT status FROM hero_slides WHERE slide_id=?');
            $query->execute([$id]);
            // Editing a live slide, including hiding it, still changes the public page.
            if ($query->fetchColumn() === 'draft') return false;
        }
    }
    if ($route === '/admin/settings.php' || !rbacShouldQueue($route, $post)) return false;
    if (in_array($action, ['save_draft','submit','withdraw','discard','reject','request_revision','delete','bulk_delete','delete_section'], true)) return false;
    if (in_array($route, ['/admin/action/create-news.php','/admin/action/create-announcement.php','/admin/events.php'], true)) {
        return in_array($action, ['publish','approve'], true) || (($post['status'] ?? '') === 'published');
    }
    return true;
}

function publishConfirmationValid(string $token): bool {
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
    $record = $_SESSION['publish_confirmations'][hash('sha256', $token)] ?? null;
    return is_array($record) && ($record['expires'] ?? 0) > time()
        && ($record['user'] ?? 0) === (int)($_SESSION['user_id'] ?? -1)
        && ($record['version'] ?? -1) === (int)($_SESSION['credential_version'] ?? -2);
}

function requirePublishConfirmation(): void {
    // Each target handler keeps its own CSRF validation (Events uses DB tokens).
    $token = $_SERVER['HTTP_X_GNCI_PUBLISH_TOKEN'] ?? $_POST['publish_token'] ?? '';
    if (is_string($token) && publishConfirmationValid($token)) return;
    http_response_code(428);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode(['success'=>false,'reauth_required'=>true,'error'=>'Enter your current Super Admin password before publishing.']);
    exit;
}

function issuePublishConfirmation(string $password): string {
    if (!rbacIsSuper()) throw new RuntimeException('Only Super Admin can confirm publication.');
    if ($password === '' || strlen($password) > 4096) throw new InvalidArgumentException('Enter your current password.');
    $id = (int)$_SESSION['user_id'];
    if (!authRateAllowed('publish-password', (string)$id, 5, 900)) throw new RuntimeException('Too many confirmation attempts. Try again in 15 minutes.');
    $q = getDB()->prepare('SELECT password,status,role,credential_version,locked_until FROM users WHERE user_id=?');
    $q->execute([$id]); $user = $q->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['status'] !== 'active' || rbacRole($user['role']) !== 'Super Admin'
        || (int)$user['credential_version'] !== (int)($_SESSION['credential_version'] ?? -1)) throw new RuntimeException('Sign in again before publishing.');
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) throw new RuntimeException('Your account is temporarily locked. Try again later.');
    if (!password_verify($password, $user['password'])) {
        authAudit($id, 'PUBLISH_CONFIRMATION_FAILED', 'Publishing password confirmation failed.');
        throw new InvalidArgumentException('Your current password is incorrect. Nothing has been published.');
    }
    // Successful confirmations do not use up the incorrect-password allowance.
    getDB()->prepare('DELETE FROM auth_rate_limits WHERE bucket_hash=?')->execute([hash('sha256', 'publish-password:'.$id)]);
    $records = array_filter($_SESSION['publish_confirmations'] ?? [], static fn($r) => ($r['expires'] ?? 0) > time());
    $records = array_slice($records, -7, null, true);
    $token = bin2hex(random_bytes(32));
    $records[hash('sha256', $token)] = ['expires'=>time()+600,'version'=>(int)$user['credential_version'],'user'=>$id];
    $_SESSION['publish_confirmations'] = $records;
    return $token;
}
