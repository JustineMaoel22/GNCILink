<?php
/** Remove only retired scholarship photo references; keep PDFs and physical files. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$options = getopt('', ['apply', 'restore', 'archive:']);
$db = getDB();
$prefix = 'scholarship_image_';
$where = 'LEFT(field_key, ' . strlen($prefix) . ') = ' . $db->quote($prefix);
$rows = static fn() => $db->query('SELECT * FROM tuition_fee_content WHERE ' . $where . ' ORDER BY field_key')->fetchAll(PDO::FETCH_ASSOC);
$insert = static function (string $table, array $records) use ($db): void {
    foreach ($records as $record) {
        $columns = array_keys($record);
        foreach ($columns as $column) {
            if (!preg_match('/\A[a-zA-Z0-9_]+\z/D', $column)) throw new RuntimeException('Invalid archive column.');
        }
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $db->prepare($sql)->execute(array_values($record));
    }
};
try {
    if (!isset($options['apply']) && !isset($options['restore'])) {
        echo count($rows()) . " retired scholarship photo record(s) found. No changes made.\n";
        exit;
    }
    if (isset($options['apply'], $options['restore'])) throw new RuntimeException('Choose apply or restore.');
    if (DB_HOST !== 'db') throw new RuntimeException('This cleanup is restricted to the local Docker database.');
    $path = $options['archive'] ?? '';
    $directory = realpath(dirname($path));
    $root = realpath(__DIR__ . '/..');
    if (!$path || !$directory || $directory === $root || str_starts_with($directory . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Provide --archive in a private directory outside the web root.');
    }
    $schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    $db->beginTransaction();
    if (isset($options['restore'])) {
        $archive = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (($archive['format'] ?? '') !== 'gnci-scholarship-photos-v1' || ($archive['schema'] ?? '') !== $schema) throw new RuntimeException('Wrong archive or database.');
        foreach ($archive['rows'] as $record) {
            if (!str_starts_with($record['field_key'] ?? '', $prefix)) throw new RuntimeException('Archive contains unrelated records.');
        }
        $insert('tuition_fee_content', $archive['rows']);
        $db->commit();
        echo count($archive['rows']) . " scholarship photo reference(s) restored.\n";
        exit;
    }
    $records = $db->query('SELECT * FROM tuition_fee_content WHERE ' . $where . ' ORDER BY field_key FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    if (!$records) { $db->rollBack(); echo "No retired scholarship photo records to delete.\n"; exit; }
    $unrelated = $db->query('SELECT * FROM tuition_fee_content WHERE NOT (' . $where . ') ORDER BY field_key')->fetchAll(PDO::FETCH_ASSOC);
    $data = json_encode(['format'=>'gnci-scholarship-photos-v1', 'schema'=>$schema, 'created_at'=>gmdate('c'), 'rows'=>$records], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    // Exclusive creation prevents overwriting an earlier rollback archive.
    $previousMask = umask(0077);
    try { $handle = fopen($path, 'x'); } finally { umask($previousMask); }
    if (!$handle) throw new RuntimeException('Could not create a new private archive.');
    try { if (fwrite($handle, $data) !== strlen($data) || !fflush($handle)) throw new RuntimeException('Archive write incomplete.'); }
    finally { fclose($handle); }
    $restored = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    if ($restored['rows'] !== $records) throw new RuntimeException('Archive verification failed.');
    // Rehearse restoring the complete rows into an isolated temporary table.
    $db->exec('CREATE TEMPORARY TABLE scholarship_photo_restore_check LIKE tuition_fee_content');
    $insert('scholarship_photo_restore_check', $restored['rows']);
    if ($db->query('SELECT * FROM scholarship_photo_restore_check ORDER BY field_key')->fetchAll(PDO::FETCH_ASSOC) !== $records) throw new RuntimeException('Restore verification failed.');
    $deleted = $db->exec('DELETE FROM tuition_fee_content WHERE ' . $where);
    if ($deleted !== count($records) || $rows()) throw new RuntimeException('Cleanup verification failed.');
    if ($db->query('SELECT * FROM tuition_fee_content WHERE NOT (' . $where . ') ORDER BY field_key')->fetchAll(PDO::FETCH_ASSOC) !== $unrelated) throw new RuntimeException('Unrelated content changed; rolling back.');
    $db->commit();
    echo $deleted . " retired scholarship photo record(s) deleted; all other tuition/scholarship records preserved.\n";
    echo "Verified rollback archive: " . $path . "\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'Scholarship photo cleanup stopped: ' . $error->getMessage() . "\n");
    exit(1);
}
