<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/alumni-page-content.php';
$db = getDB();
$db->beginTransaction();
try {
    $category = $db->prepare('SELECT category_id FROM categories WHERE category_name=?');
    $category->execute(['Alumni']);
    $alumniCategory = $category->fetchColumn();
    if (!$alumniCategory) throw new RuntimeException('Run the Alumni migration first.');
    $insert = $db->prepare('INSERT INTO events (category_id,title,location,start_date,end_date,status) VALUES (?,?,?,?,?,?)');
    $expected = [];
    foreach ([
        [$alumniCategory, 'Ongoing reunion', 'Campus hall', '2098-12-31 00:00:00', '2099-01-02 00:00:00', 'published'],
        [$alumniCategory, 'Alumni gathering', 'Campus hall', '2099-01-05 09:00:00', '2099-01-05 12:00:00', 'published'],
        [$alumniCategory, 'Community day', '', '2099-01-10 00:00:00', null, 'published'],
        [$alumniCategory, 'Fourth event', '', '2099-01-20 00:00:00', null, 'published'],
        [$alumniCategory, 'Private draft', '', '2099-01-01 00:00:00', null, 'draft'],
        [$alumniCategory, 'Pending approval', '', '2099-01-01 00:00:00', null, 'pending'],
        [$alumniCategory, 'Archived event', '', '2099-01-01 00:00:00', null, 'archived'],
        [$alumniCategory, 'Past event', '', '2098-01-01 00:00:00', null, 'published'],
        [null, 'Uncategorized event', '', '2099-01-01 00:00:00', null, 'published'],
    ] as $index => $values) {
        $insert->execute($values);
        if ($index < 3) $expected[] = (int)$db->lastInsertId();
    }
    $events = alumniUpcomingEvents($db, '2099-01-01');
    if (array_map('intval', array_column($events, 'event_id')) !== $expected) throw new RuntimeException('Incorrect category, publishing, date filtering, ordering, or limit.');
    foreach (['events_title'=>'What’s', 'events_subtitle'=>'happening?', 'events_intro'=>'From class reunions to conversations with leaders, there is always a reason to come back.', 'events_image'=>'/assets/images/gnc-church.jpg'] as $key => $value) {
        if (alumniPageValidate($key, $value) !== $value) throw new RuntimeException('Event content validation failed.');
    }
    try {
        alumniPageValidate('events_image', '/uploads/../config/config.php');
        throw new RuntimeException('Unsafe background accepted.');
    } catch (InvalidArgumentException $expectedError) {}
    if (in_array('--preview', $argv, true)) {
        $preview = false;
        $alumni = alumniPageContent(false);
        $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        $plain = static fn($key) => $escape($alumni[$key] ?? '');
        $mark = static fn($key, $label) => '';
        ob_start();
        include __DIR__ . '/../components/alumni-events.php';
        file_put_contents(__DIR__ . '/../cache/alumni-events-fixture.html', ob_get_clean());
    }
    echo "PASS: Alumni-only published events, ongoing dates, ordering, three-event limit, editable fields and invalid image rejection. Fixtures rolled back.\n";
} finally {
    $db->rollBack();
}
