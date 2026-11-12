<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi-audience.php';

$db = getDB();
// Connection-local tables shadow the real audiences; no announcement is created.
foreach (['announcement', 'event'] as $type) {
    $db->exec("CREATE TEMPORARY TABLE {$type}_departments ({$type}_id INT NOT NULL, dept_id INT NOT NULL, PRIMARY KEY ({$type}_id,dept_id)) ENGINE=InnoDB");
    $db->exec("CREATE TEMPORARY TABLE {$type}_programs ({$type}_id INT NOT NULL, program_code VARCHAR(20) NOT NULL, PRIMARY KEY ({$type}_id,program_code)) ENGINE=InnoDB");
}
$db->exec('CREATE TEMPORARY TABLE publishing_transaction_probe (id INT PRIMARY KEY) ENGINE=InnoDB');
function publishingCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

try {
    if (($argv[1] ?? '') === 'bootstrap') ensureMultiAudienceTables($db);
    $db->beginTransaction();
    $db->exec('INSERT INTO publishing_transaction_probe VALUES (1)');
    saveAnnouncementAudience($db, 1, [2], ['TEST']);
    publishingCheck($db->inTransaction(), 'Audience setup unexpectedly committed the publishing transaction.');
    $db->rollBack();
    publishingCheck((int)$db->query('SELECT COUNT(*) FROM publishing_transaction_probe')->fetchColumn() === 0, 'The publishing write must roll back.');
    publishingCheck((int)$db->query('SELECT COUNT(*) FROM announcement_departments')->fetchColumn() === 0, 'Department assignments must roll back.');
    publishingCheck((int)$db->query('SELECT COUNT(*) FROM announcement_programs')->fetchColumn() === 0, 'Program assignments must roll back.');

    $db->beginTransaction();
    $db->exec('INSERT INTO publishing_transaction_probe VALUES (2)');
    saveAnnouncementAudience($db, 1, [2, 3], ['TEST']);
    $db->commit();
    publishingCheck((int)$db->query('SELECT COUNT(*) FROM publishing_transaction_probe')->fetchColumn() === 1, 'Publishing transaction must commit normally.');
    publishingCheck((int)$db->query('SELECT COUNT(*) FROM announcement_departments')->fetchColumn() === 2, 'Both audience departments must be saved.');

    // A failed audience write must reach the caller, which rolls back the save.
    $db->exec('ALTER TABLE announcement_programs RENAME COLUMN program_code TO unavailable_program_code');
    $db->beginTransaction();
    $db->exec('INSERT INTO publishing_transaction_probe VALUES (3)');
    $failed = false;
    try { saveAnnouncementAudience($db, 1, [4], ['TEST']); }
    catch (PDOException $e) { $failed = true; }
    publishingCheck($failed, 'Audience errors must not be hidden from the publishing transaction.');
    $db->rollBack();
    publishingCheck((int)$db->query('SELECT COUNT(*) FROM publishing_transaction_probe')->fetchColumn() === 1, 'Failed save must roll back its publishing write.');
    publishingCheck((int)$db->query('SELECT COUNT(*) FROM announcement_departments')->fetchColumn() === 2, 'Failed save must preserve the prior audience.');
    echo "PASS: publishing commit, rollback, audience assignments and audience failure propagation; temporary fixtures only, no Facebook requests.\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
