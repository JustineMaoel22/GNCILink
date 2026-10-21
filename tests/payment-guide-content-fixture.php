<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/payment-guide-content.php';
$keys = ['hero_title', 'account_number', 'gcash_step_1', 'contact_link_label', 'contact_link_url'];
$db = getDB();
if (($argv[1] ?? '') === 'snapshot') {
    $values = [];
    foreach ($keys as $key) {
        $query = $db->prepare('SELECT content FROM tuition_fee_content WHERE field_key=?');
        $query->execute(['payment_text_' . $key]); $value = $query->fetchColumn();
        $values[$key] = $value === false ? null : $value;
    }
    echo base64_encode(json_encode($values, JSON_THROW_ON_ERROR));
} elseif (($argv[1] ?? '') === 'restore') {
    $values = json_decode(base64_decode($argv[2] ?? '', true), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($values) || array_keys($values) !== $keys) throw new RuntimeException('Invalid fixture snapshot.');
    $db->beginTransaction();
    try {
        foreach ($values as $key => $value) {
            if ($value === null) $db->prepare('DELETE FROM tuition_fee_content WHERE field_key=?')->execute(['payment_text_' . $key]);
            elseif (is_string($value)) $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)')->execute(['payment_text_' . $key, $value]);
            else throw new RuntimeException('Invalid fixture value.');
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
} else { throw new RuntimeException('Use snapshot or restore.'); }
