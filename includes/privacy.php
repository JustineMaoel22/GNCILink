<?php
/** Website privacy preferences are choices, never authentication credentials. */
const GNC_PRIVACY_COOKIE = 'gnc_privacy_v1';
const GNC_PRIVACY_DAYS = 180;
function gncPrivacyChoices(): array {
    $none=['analytics'=>false,'personalization'=>false,'external'=>false];
    // A browser GPC/DNT signal keeps everything off by default (no banner opt-in is
    // assumed); only a cookie written by the visitor's own explicit choice can enable features.
    $raw=$_COOKIE[GNC_PRIVACY_COOKIE]??'';
    if (!is_string($raw) || strlen($raw)>600) return $none;
    $value=json_decode($raw,true);
    if (!is_array($value) || ($value['version']??null)!==1 || !is_int($value['at']??null)
        || $value['at']>time()+300 || $value['at']<time()-GNC_PRIVACY_DAYS*86400) return $none;
    foreach($none as $key=>$_) $none[$key]=($value[$key]??false)===true;
    return $none;
}
function gncPrivacyAllows(string $category): bool { return gncPrivacyChoices()[$category]??false; }
function gncPrivacyFrameSource(string $url): string {
    $attribute='data-privacy-src';
    // Authenticated visual editing is a separate, requested administrative use.
    if (isset($_GET['ce_preview']) && function_exists('cePreviewCanManage') && cePreviewCanManage()) $attribute='src';
    return $attribute.'="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"';
}

/** Publication stays off until the school supplies and approves its own facts. */
function gncLegalSettings(): array {
    return [
        'approved'=>filter_var(env('LEGAL_POLICIES_APPROVED','0'),FILTER_VALIDATE_BOOLEAN),
        'contact_office'=>trim((string)env('PRIVACY_CONTACT_OFFICE','')),
        'contact_email'=>trim((string)env('PRIVACY_CONTACT_EMAIL','')),
        'retention'=>trim((string)env('PRIVACY_RETENTION_STATEMENT','')),
        'lawful_bases'=>trim((string)env('PRIVACY_LAWFUL_BASES_STATEMENT','')),
        'providers'=>trim((string)env('PRIVACY_PROVIDERS_STATEMENT','')),
    ];
}
function gncLegalPublished(): bool {
    $settings=gncLegalSettings();
    return $settings['approved'] && $settings['contact_office']!==''
        && filter_var($settings['contact_email'],FILTER_VALIDATE_EMAIL)
        && $settings['retention']!=='' && $settings['lawful_bases']!=='' && $settings['providers']!=='';
}
