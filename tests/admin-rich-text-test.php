<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/rich-text.php';
$checks = 0;
function rtCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$html = '<h1>Heading one</h1><h2>Heading two</h2><h3>Heading three</h3>'
    . '<p class="ql-align-center"><strong>Bold</strong> <em>Italic</em> <u>Underline</u> <s>Strike</s></p>'
    . '<ol><li>Number</li></ol><ul><li>Bullet</li></ul><blockquote class="ql-align-right">Quote</blockquote>'
    . '<p class="ql-align-justify"><a href="https://example.com">Link</a></p>';
$stored = richTextToStorage($html);
foreach (['<h1>', '<h2>', '<h3>', '<strong>', '<em>', '<u>', '<s>', '<ol>', '<ul>', '<li>', '<blockquote', 'href="https://example.com"', 'text-align:center', 'text-align:right', 'text-align:justify'] as $format) {
    rtCheck(str_contains($stored, $format), 'Missing stored format: ' . $format);
}
rtCheck(sectionToEditorHtml($stored) === $stored, 'Reopening retains every supported format.');
rtCheck(renderSectionContent($stored) === $stored, 'Public rendering retains every supported format.');
rtCheck(richTextNormalize($html) === $stored, 'Shared inline-editor storage retains formats.');
rtCheck(sectionToEditorHtml('<h1>Heading</h1>') === '<h1>Heading</h1>', 'A lone H1 is detected as HTML.');
rtCheck(sectionToEditorHtml('<s>Strike</s>') === '<s>Strike</s>', 'A lone strike is detected as HTML.');
rtCheck(richTextSanitize('<strike>Legacy</strike>') === '<s>Legacy</s>', 'Legacy strikethrough is normalized.');
rtCheck(richTextToStorage('<p>Plain</p><p>Text</p>') === "Plain\n\nText", 'Plain-text storage stays compatible.');
rtCheck(sectionToEditorHtml("Plain\n\nText") === '<p>Plain</p><p>Text</p>', 'Legacy text reopens as paragraphs.');
$unsafe = richTextSanitize('<h1 onclick="bad()">Title</h1><p class="ql-align-center malicious" style="position:fixed;background:url(javascript:bad())">Text<script>bad()</script><a href="javascript:bad()">Bad link</a></p>');
rtCheck(!preg_match('/onclick|javascript|script|position|background|malicious/', $unsafe), 'Unsupported attributes, scripts and unsafe links are removed.');
rtCheck(str_contains($unsafe, 'text-align:center'), 'Safe alignment survives removal of unsafe attributes.');
echo "$checks rich-text storage, reopening, rendering, compatibility and security checks passed.\n";
