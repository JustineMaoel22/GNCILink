<?php
/** Ordered relational storage; existing forms still exchange newline-separated text. */
const GNCI_CONTENT_LISTS_MIGRATION = 'normalized-content-lists-v1';

function contentListDefinition(string $kind): array {
    $definitions = [
        'office_services' => ['parent'=>'admin_offices', 'id'=>'office_id', 'legacy'=>'services', 'table'=>'admin_office_services', 'value'=>'service_name'],
        'achievement_achievers' => ['parent'=>'student_achievements', 'id'=>'achievement_id', 'legacy'=>'achievers', 'table'=>'student_achievement_achievers', 'value'=>'achiever_name'],
    ];
    if (!isset($definitions[$kind])) throw new InvalidArgumentException('Unknown content list.');
    return $definitions[$kind];
}

function contentListLines(string $text): array {
    // Preserve duplicate names/services and their order, matching the existing UI.
    $lines = preg_split('/\R/u', $text);
    if ($lines === false) throw new InvalidArgumentException('Content must be valid UTF-8.');
    return array_values(array_filter(array_map('trim', $lines), static fn($line) => $line !== ''));
}

function contentListsEnabled(PDO $db, bool $refresh = false): bool {
    static $cache;
    $cache ??= new WeakMap();
    if ($refresh) unset($cache[$db]);
    if (!isset($cache[$db])) {
        try {
            $query = $db->prepare('SELECT 1 FROM content_migrations WHERE migration_key=?');
            $query->execute([GNCI_CONTENT_LISTS_MIGRATION]);
            $cache[$db] = (bool)$query->fetchColumn();
        } catch (PDOException $e) {
            // Only a missing migration table denotes an older installation.
            if (($e->errorInfo[1] ?? null) !== 1146) throw $e;
            $cache[$db] = false;
        }
    }
    return $cache[$db];
}

/** Hydrate the existing PHP payload without GROUP_CONCAT truncation or N+1 queries. */
function contentListsHydrate(PDO $db, string $kind, array $rows): array {
    $definition = contentListDefinition($kind);
    if (!$rows || !contentListsEnabled($db)) return $rows;
    $ids = array_values(array_unique(array_map(static fn($row) => (int)$row[$definition['id']], $rows)));
    $lists = [];
    foreach (array_chunk($ids, 500) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $statement = $db->prepare("SELECT {$definition['id']}, {$definition['value']} FROM {$definition['table']}
            WHERE {$definition['id']} IN ($placeholders) ORDER BY {$definition['id']}, display_order");
        $statement->execute($chunk);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $entry) {
            $lists[(int)$entry[$definition['id']]][] = $entry[$definition['value']];
        }
    }
    foreach ($rows as &$row) {
        // An empty normalized list is authoritative; never resurrect legacy text.
        $row[$definition['legacy']] = implode("\n", $lists[(int)$row[$definition['id']]] ?? []);
    }
    unset($row);
    return $rows;
}

/** Caller must save the parent and list in the same transaction. */
function contentListReplace(PDO $db, string $kind, int $id, string $text): void {
    $definition = contentListDefinition($kind);
    if (!$db->inTransaction()) throw new LogicException('Content lists require a transaction.');
    $lines = contentListLines($text);
    $db->prepare("DELETE FROM {$definition['table']} WHERE {$definition['id']}=?")->execute([$id]);
    $insert = $db->prepare("INSERT INTO {$definition['table']} ({$definition['id']}, display_order, {$definition['value']}) VALUES (?,?,?)");
    foreach ($lines as $order => $line) $insert->execute([$id, $order, $line]);
}

/** Preserve enclosing editor/approval transactions, including rollback on child failure. */
function contentListTransaction(PDO $db, callable $operation) {
    $ownsTransaction = !$db->inTransaction();
    $savepoint = 'content_list_' . bin2hex(random_bytes(8));
    if ($ownsTransaction) $db->beginTransaction();
    else $db->exec("SAVEPOINT $savepoint");
    try {
        $result = $operation();
        if ($ownsTransaction) $db->commit();
        else $db->exec("RELEASE SAVEPOINT $savepoint");
        return $result;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            if ($ownsTransaction) $db->rollBack();
            else {
                $db->exec("ROLLBACK TO SAVEPOINT $savepoint");
                $db->exec("RELEASE SAVEPOINT $savepoint");
            }
        }
        throw $e;
    }
}
