<?php
/** Shared application settings. Missing settings preserve existing behavior. */
function pageSuggestionsEnabled(): bool {
    try {
        return getDB()->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'page_suggestions_enabled'")->fetchColumn() !== '0';
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') return true;
        throw $e;
    }
}

function savePageSuggestionsSetting(array $input): void {
    if (!hasPermission('manage_settings')) throw new RuntimeException('Your role cannot change website settings.');
    if (!is_string($input['csrf_token'] ?? null) || !verifyCSRFToken($input['csrf_token'])) {
        throw new InvalidArgumentException('Your session has expired. Please refresh the page.');
    }
    $value=$input['page_suggestions_enabled']??null;
    if (!is_string($value) || !in_array($value,['0','1'],true)) throw new InvalidArgumentException('Choose On or Off for the suggested pages popup.');
    $statement=getDB()->prepare("INSERT INTO admin_settings (setting_key,setting_value) VALUES ('page_suggestions_enabled',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $statement->execute([$value]);
    logActivity((int)$_SESSION['user_id'],'UPDATE','admin_settings',null,'Suggested pages popup turned '.($value==='1'?'on':'off'));
}

function aiImageCheckerEnabled(): bool {
    try {
        $value = getDB()->query("SELECT setting_value FROM admin_settings WHERE setting_key = 'ai_image_checker_enabled'")->fetchColumn();
        return $value !== '0';
    } catch (PDOException $e) {
        // Existing installations keep checking photos until the migration runs.
        if ($e->getCode() === '42S02') return true;
        throw $e;
    }
}

function saveAiImageCheckerSetting(array $input): void {
    if (!hasPermission('manage_settings')) throw new RuntimeException('Only Super Admin can change settings.');
    if (!is_string($input['csrf_token'] ?? null) || !verifyCSRFToken($input['csrf_token'])) {
        throw new InvalidArgumentException('Your session has expired. Please refresh the page.');
    }
    $value = $input['ai_image_checker_enabled'] ?? null;
    if (!is_string($value) || !in_array($value, ['0', '1'], true)) throw new InvalidArgumentException('Choose On or Off for the AI image checker.');
    $statement = getDB()->prepare("INSERT INTO admin_settings (setting_key, setting_value) VALUES ('ai_image_checker_enabled', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $statement->execute([$value]);
    logActivity((int)$_SESSION['user_id'], 'UPDATE', 'admin_settings', null, 'AI image checker turned ' . ($value === '1' ? 'on' : 'off'));
}
