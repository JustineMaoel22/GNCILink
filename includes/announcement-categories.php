<?php
/** Additional announcement topic tags; category_id remains the primary legacy tag. */
function announcementCategoriesReady(PDO $db): bool {
    static $ready = [];
    $key = spl_object_id($db);
    if (isset($ready[$key])) return $ready[$key];
    try { $db->query('SELECT announcement_id FROM announcement_categories LIMIT 0'); return $ready[$key] = true; }
    catch (PDOException $error) { if ($error->getCode() === '42S02') return $ready[$key] = false; throw $error; }
}

function normalizeAnnouncementCategoryIds(PDO $db, $values): array {
    if (!is_array($values) || count($values) > 100) throw new InvalidArgumentException('Choose up to 100 valid announcement categories.');
    $ids = [];
    foreach ($values as $value) {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D', (string)$value)) throw new InvalidArgumentException('Choose valid announcement categories.');
        $ids[] = (int)$value;
    }
    $ids = array_values(array_unique($ids));
    if ($ids) {
        $query = $db->prepare('SELECT COUNT(*) FROM categories WHERE category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $query->execute($ids);
        if ((int)$query->fetchColumn() !== count($ids)) throw new InvalidArgumentException('An announcement category no longer exists. Refresh and select it again.');
    }
    if (count($ids) > 1 && !announcementCategoriesReady($db)) throw new InvalidArgumentException('Run database/migrate-announcement-categories.php to enable multiple categories.');
    return $ids;
}

function saveAnnouncementCategories(PDO $db, int $id, array $ids): void {
    if (!announcementCategoriesReady($db)) return;
    $db->prepare('DELETE FROM announcement_categories WHERE announcement_id=?')->execute([$id]);
    $insert = $db->prepare('INSERT INTO announcement_categories (announcement_id,category_id) VALUES (?,?)');
    foreach ($ids as $categoryId) $insert->execute([$id, $categoryId]);
}

/** Hydrate all tags in one query, retaining the primary category first. */
function attachAnnouncementCategories(PDO $db, array $rows): array {
    $extras = [];
    $ids = array_column($rows, 'announcement_id');
    if ($ids && announcementCategoriesReady($db)) {
        $query = $db->prepare('SELECT ac.announcement_id,c.category_id,c.category_name FROM announcement_categories ac JOIN categories c ON c.category_id=ac.category_id WHERE ac.announcement_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY c.category_name');
        $query->execute($ids);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $tag) $extras[$tag['announcement_id']][(int)$tag['category_id']] = $tag['category_name'];
    }
    foreach ($rows as &$row) {
        $tags = [];
        if (!empty($row['category_id'])) $tags[(int)$row['category_id']] = $extras[$row['announcement_id']][(int)$row['category_id']] ?? $row['category_name'] ?? '';
        $tags += $extras[$row['announcement_id']] ?? [];
        $row['category_ids'] = array_keys($tags);
        $row['category_names'] = array_values($tags);
        $row['category_name'] = implode(', ', array_filter($row['category_names'], 'strlen'));
    }
    unset($row);
    return $rows;
}

/** One placeholder, no joining the result set: multiple matching tags never duplicate a post. */
function announcementCategoryCondition(PDO $db, string $alias, bool $byName = false): string {
    if (!preg_match('/^[a-z][a-z0-9_]*$/D', $alias)) throw new InvalidArgumentException('Invalid announcement alias.');
    if (!$byName) return announcementCategoriesReady($db)
        ? "? IN (SELECT $alias.category_id UNION ALL SELECT mc.category_id FROM announcement_categories mc WHERE mc.announcement_id=$alias.announcement_id)"
        : "$alias.category_id=?";
    $column = $byName ? 'category_name' : 'category_id';
    $extra = announcementCategoriesReady($db) ? " OR EXISTS (SELECT 1 FROM announcement_categories mc WHERE mc.announcement_id=$alias.announcement_id AND mc.category_id=matched_category.category_id)" : '';
    return "EXISTS (SELECT 1 FROM categories matched_category WHERE matched_category.$column=? AND (matched_category.category_id=$alias.category_id$extra))";
}
