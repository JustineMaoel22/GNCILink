<?php
/** Read-only regression checks; run with the configured development database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../inc/retriever.php';

function checkDeveloperResult(bool $ok, string $message): void
{
    if (!$ok) { throw new RuntimeException($message); }
}

$db = getDB();
foreach ([
    'who are the developers of this website?',
    'Who built this website?',
    'Who created GNCILink?',
    'Tell me about the Developers Corner.',
    'Who is the developer of this site?',
] as $question) {
    foreach ([[], ['program' => 'BSIT']] as $pageContext) {
        $result = (new GncbotRetriever($db, $pageContext))->retrieve($question);
        checkDeveloperResult(!$result['offTopic'], 'Website credits must be in scope: ' . $question);
        checkDeveloperResult(in_array('developers', $result['intents'], true), 'Missing developer intent: ' . $question);
        foreach (gncWebsiteDevelopers() as $member) {
            checkDeveloperResult(str_contains($result['context'], $member['name'] . ' - ' . $member['role']), 'Missing published team credit');
        }
        $url = gncbot_page_urls()['developers']['url'];
        checkDeveloperResult(in_array($url, $result['allowed'], true), 'Developer page link must be allowed');
        checkDeveloperResult(in_array($url, array_column($result['links'], 'url'), true), 'Developer page must be suggested');
    }
}
foreach (['How do I become a developer?', 'Who created Facebook?', 'Write a PHP sorting function.', 'What is the weather today?'] as $question) {
    $result = (new GncbotRetriever($db))->retrieve($question);
    checkDeveloperResult($result['offTopic'], 'Unrelated questions must remain out of scope: ' . $question);
}
echo "PASS: published developer credits and page links retrieved, including program pages; unrelated questions still rejected.\n";
