<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../../includes/publish-confirmation.php';
initSession(); requireLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') rbacFail('Use POST for password confirmation.', 405);
if (!rbacIsSuper() || !authCsrfValid()) rbacFail('You cannot confirm this publication.', 403);
try {
    if (($_POST['action'] ?? '') === 'end') {
        $token = $_POST['publish_token'] ?? '';
        if (is_string($token)) unset($_SESSION['publish_confirmations'][hash('sha256', $token)]);
        echo json_encode(['success'=>true]); exit;
    }
    $password = $_POST['password'] ?? null;
    if (!is_string($password)) throw new InvalidArgumentException('Enter your current password.');
    $token = issuePublishConfirmation($password);
    unset($_POST['password'], $password);
    echo json_encode(['success'=>true,'token'=>$token]);
} catch (InvalidArgumentException $e) { rbacFail($e->getMessage(), 422); }
catch (PDOException $e) { error_log('Publish password confirmation storage unavailable.'); rbacFail('Could not verify your password. Try again later.', 503); }
catch (RuntimeException $e) { rbacFail($e->getMessage(), 403); }
catch (Throwable $e) { error_log('Publish password confirmation unavailable.'); rbacFail('Could not verify your password. Try again later.', 503); }
