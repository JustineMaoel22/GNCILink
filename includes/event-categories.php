<?php
/** Switch only after the Events foreign key points to the dedicated catalog. */
function eventCategoryTable(PDO $db): string {
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') return 'categories';
    // SHOW CREATE also respects connection-local fixture tables that shadow Events.
    $definition = $db->query('SHOW CREATE TABLE events')->fetch(PDO::FETCH_NUM);
    return preg_match('/REFERENCES\s+`?event_categories`?\s*\(/i', $definition[1] ?? '') ? 'event_categories' : 'categories';
}

function eventCategoryChoices(PDO $db, bool $includeLegacy = false): array {
    $table = eventCategoryTable($db);
    $where = $table === 'event_categories'
        ? ($includeLegacy ? '' : ' WHERE is_active=1')
        : " WHERE category_name IN ('School Event','School Activities','Holiday','Suspension','Alumni')";
    return $db->query("SELECT * FROM $table$where ORDER BY category_name,category_id")->fetchAll(PDO::FETCH_ASSOC);
}

function validateEventCategory(PDO $db, ?int $id, ?int $previous = null): void {
    if ($id === null) return;
    $table = eventCategoryTable($db);
    $stmt = $db->prepare("SELECT category_id FROM $table WHERE category_id=?");
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Choose a valid event category.');
    if ($id === $previous) return; // Preserve an existing legacy assignment when editing.
    if (!in_array($id, array_map('intval', array_column(eventCategoryChoices($db), 'category_id')), true)) {
        throw new InvalidArgumentException('Choose an available event category.');
    }
}
