<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/rich-text.php';
require_once __DIR__ . '/../components/contact-page-content.php';
function contactCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function getPageSections(string $page): array {
    return [
        ['section_key' => 'contact_email', 'content' => 'live@example.com', 'draft_content' => 'draft@example.com'],
        ['section_key' => 'direct_0_office', 'content' => 'Office & Registry', 'draft_content' => 'New & Updated Office'],
    ];
}
foreach (contactPageDefaults() as $key => $field) contactCheck(contactPageValidate($key, $field['content']) === $field['content'], 'Default field remains valid: ' . $key);
contactCheck(count(contactPageDefaults()) === 74, 'All page text and directory fields are registered');
contactCheck(contactPageContent(false)['contact_email']['content'] === 'live@example.com', 'Public address always uses published content');
contactCheck(contactPageContent(true)['contact_email']['content'] === 'draft@example.com', 'Editor can preview unpublished email');
contactCheck(contactPageContent(false)['direct_0_office']['content'] === 'Office & Registry', 'Special characters remain readable');
contactCheck(contactPageValidate('hero_title', '<p>Contact &amp; Support</p>') === 'Contact & Support', 'Editor markup normalizes to plain text');
foreach ([
    ['contact_email', "test@example.com\r\nBcc: other@example.com"], ['contact_email', 'invalid'],
    ['contact_facebook', 'javascript:alert(1)'], ['contact_facebook', 'https://www.facebook.com.evil.example/page'],
    ['contact_phone', 'no digits'], ['direct_0_number', 'not a number'], ['hero_title', ''],
    ['hero_title', str_repeat('x', 151)], ['unknown', 'value'],
] as [$key, $value]) {
    try { contactPageValidate($key, $value); throw new RuntimeException('Invalid field was accepted: ' . $key); }
    catch (InvalidArgumentException $expected) {}
}
$page = file_get_contents(__DIR__ . '/../pages/contact-us.php');
contactCheck(str_contains($page, 'gncSendContactMail($contactLiveEmail,'), 'Mail delivery uses the published address');
contactCheck(str_contains($page, 'name="gnc_hp_7f3a"') && str_contains($page, 'name="csrf"'), 'Contact form honeypot and CSRF remain present');
echo "PASS: all 74 defaults, published/draft isolation, directory characters, email/URL/phone/length validation, published mail recipient and form protections. No database changes or email calls.\n";
