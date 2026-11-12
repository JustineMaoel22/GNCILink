<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/rich-text.php';
require_once __DIR__ . '/../components/contact-page-content.php';
$checks = 0;
foreach (['facebook', 'instagram', 'linkedin', 'tiktok'] as $network) {
    $url = contactPageDefaults()['contact_' . $network]['content'];
    if (!contactPageSocialUrlValid($network, $url)) throw new RuntimeException('Default URL rejected.');
    $checks++;
}
foreach (['instagram', 'linkedin', 'tiktok'] as $network) {
    $key = 'contact_' . $network;
    $url = 'https://www.' . $network . '.com/new-school-profile';
    if (contactPageValidate($key, $url) !== $url) throw new RuntimeException('Replacement URL changed.');
    $checks++;
    foreach (['javascript:alert(1)', 'https://' . $network . '.com.evil.example/profile', 'https://evil.example/' . $network . '.com', 'https://user:pass@' . $network . '.com/profile', "https://www.$network.com/profile\nother"] as $invalid) {
        try {
            contactPageValidate($key, $invalid);
            throw new RuntimeException('Unsafe URL accepted.');
        } catch (InvalidArgumentException $expected) { $checks++; }
    }
}
echo "Contact social links: $checks checks passed.\n";
