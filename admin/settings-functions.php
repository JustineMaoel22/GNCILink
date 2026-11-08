<?php
/** Shared application settings. Missing settings preserve existing behavior. */
const GNC_STUDENT_EMAIL_DEFAULT_URL = 'https://login.microsoftonline.com/';

function validateStudentEmailUrl($value): string {
    if (!is_string($value)) throw new InvalidArgumentException('Enter a valid student email website URL.');
    $value=trim($value);
    $parts=parse_url($value);
    if ($value==='' || strlen($value)>2048 || preg_match('~[\x00-\x20\x7f\\\\]~',$value)
        || !filter_var($value,FILTER_VALIDATE_URL) || !is_array($parts)
        || !in_array(strtolower($parts['scheme']??''),['https','http'],true)
        || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('Use a complete http:// or https:// URL without embedded login credentials (maximum 2048 characters).');
    }
    return $value;
}

function studentEmailUrl(): string {
    try {
        $value=getDB()->query("SELECT setting_value FROM admin_settings WHERE setting_key='student_email_url'")->fetchColumn();
        if ($value===false) return GNC_STUDENT_EMAIL_DEFAULT_URL;
        try { return validateStudentEmailUrl($value); }
        catch (InvalidArgumentException $e) { return GNC_STUDENT_EMAIL_DEFAULT_URL; }
    } catch (PDOException $e) {
        if ($e->getCode()==='42S02') return GNC_STUDENT_EMAIL_DEFAULT_URL;
        throw $e;
    }
}

function saveStudentEmailUrlSetting(array $input): void {
    if (!hasPermission('manage_settings')) throw new RuntimeException('Your role cannot change website settings.');
    if (!is_string($input['csrf_token']??null) || !verifyCSRFToken($input['csrf_token'])) throw new InvalidArgumentException('Your session has expired. Please refresh the page.');
    $value=validateStudentEmailUrl($input['student_email_url']??null);
    $statement=getDB()->prepare("INSERT INTO admin_settings (setting_key,setting_value) VALUES ('student_email_url',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $statement->execute([$value]);
    logActivity((int)$_SESSION['user_id'],'UPDATE','admin_settings',null,'Student email access URL updated');
}

function validatePageSuggestionDelay($value): int {
    if (!is_string($value) || !preg_match('/^[0-9]{1,4}$/D', $value) || (int)$value < 5 || (int)$value > 3600) {
        throw new InvalidArgumentException('Enter a whole number from 5 to 3600 seconds.');
    }
    return (int)$value;
}

function pageSuggestionDelay(): int {
    try {
        $value=getDB()->query("SELECT setting_value FROM admin_settings WHERE setting_key='page_suggestion_delay_seconds'")->fetchColumn();
        try { return validatePageSuggestionDelay($value); }
        catch (InvalidArgumentException $e) { return 60; }
    } catch (PDOException $e) {
        if ($e->getCode()==='42S02') return 60;
        throw $e;
    }
}

function savePageSuggestionDelaySetting(array $input): void {
    if (!rbacIsSuper() || !hasPermission('manage_settings')) throw new RuntimeException('Only Super Admin can change the page suggestion delay.');
    if (!is_string($input['csrf_token']??null) || !verifyCSRFToken($input['csrf_token'])) throw new InvalidArgumentException('Your session has expired. Please refresh the page.');
    $value=validatePageSuggestionDelay($input['page_suggestion_delay_seconds']??null);
    $statement=getDB()->prepare("INSERT INTO admin_settings (setting_key,setting_value) VALUES ('page_suggestion_delay_seconds',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $statement->execute([(string)$value]);
    logActivity((int)$_SESSION['user_id'],'UPDATE','admin_settings',null,'Page suggestion delay set to '.$value.' seconds');
}

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
