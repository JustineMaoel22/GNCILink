<?php
/** One-time conversion of saved Basic Education detail content. No JSON is needed. */
function prepareBasicEducationProgramMigration(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS content_migrations (
        migration_key VARCHAR(100) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

function migrateBasicEducationPrograms(PDO $db): array {
    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();
    $counts = ['programs' => 0, 'fields' => 0, 'already_applied' => false];
    try {
        $record = $db->prepare('INSERT IGNORE INTO content_migrations (migration_key) VALUES (?)');
        $record->execute(['basic-education-shared-programs-v1']);
        if (!$record->rowCount()) {
            if ($ownsTransaction) $db->commit();
            $counts['already_applied'] = true;
            return $counts;
        }
        $legacy = [];
        try {
            foreach ($db->query('SELECT page_key, field_key, content FROM basic_education_content')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $legacy[$row['page_key']][$row['field_key']] = (string)$row['content'];
            }
        } catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; }
        $fieldInsert = $db->prepare('INSERT IGNORE INTO department_page_content (dept_id, field_key, content) VALUES (?, ?, ?)');
        foreach (['casa-gradeschool' => 4, 'jhs-regular' => 4, 'hs-monte' => 18, 'shs-regular' => 16] as $slug => $offset) {
            $find = $db->prepare('SELECT * FROM departments WHERE slug = ?');
            $find->execute([$slug]);
            $dept = $find->fetch(PDO::FETCH_ASSOC);
            if (!$dept) {
                if (!empty($legacy[$slug])) throw new RuntimeException('Create the missing Basic Education department before migrating: ' . $slug);
                continue;
            }
            $values = $legacy[$slug] ?? [];
            $map = ['overview_label' => 2, 'overview_title' => 3, 'leadership_label' => $offset,
                'leadership_title' => $offset + 1, 'leadership_intro' => $offset + 2,
                'profile_label' => $offset + 3, 'contacts_label' => $offset + 4];
            if ($offset > 4) $map += ['programs_label' => 4, 'programs_title' => 5];
            foreach ($map as $field => $number) {
                $fieldInsert->execute([$dept['dept_id'], $field, $values['text_' . str_pad((string)$number, 2, '0', STR_PAD_LEFT)] ?? '']);
                $counts['fields'] += $fieldInsert->rowCount();
            }
            $fieldInsert->execute([$dept['dept_id'], 'hero_title', trim($values['text_01'] ?? '') !== '' ? $values['text_01'] : $dept['dept_name']]);
            $counts['fields'] += $fieldInsert->rowCount();
            $slots = $slug === 'hs-monte' ? 6 : ($slug === 'shs-regular' ? 5 : 0);
            for ($slot = 1; $slot <= $slots; $slot++) {
                $name = $values['text_' . str_pad((string)(6 + ($slot - 1) * 2), 2, '0', STR_PAD_LEFT)] ?? '';
                $description = $values['text_' . str_pad((string)(7 + ($slot - 1) * 2), 2, '0', STR_PAD_LEFT)] ?? '';
                $path = $values['image_' . str_pad((string)$slot, 2, '0', STR_PAD_LEFT)] ?? '';
                if (trim($name . $description . $path) === '') continue;
                $imageId = null;
                if ($path !== '') {
                    $media = $db->prepare('SELECT media_id FROM media_library WHERE file_path = ? LIMIT 1');
                    $media->execute([$path]);
                    $imageId = $media->fetchColumn() ?: null;
                    if (!$imageId) {
                        $absolute = dirname(__DIR__) . '/' . ltrim($path, '/');
                        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        $insertMedia = $db->prepare('INSERT INTO media_library (file_name, file_type, file_size, file_path, created_by, created_at) VALUES (?, ?, ?, ?, NULL, NOW())');
                        $insertMedia->execute([basename($path), $extension === 'jpeg' ? 'jpg' : $extension, is_file($absolute) ? filesize($absolute) : 0, $path]);
                        $imageId = (int)$db->lastInsertId();
                    }
                }
                // Use a unique code and route; do not merge similarly named programs.
                $codeBase = 'BE' . $dept['dept_id'];
                $code = $codeBase . '-' . $slot;
                $check = $db->prepare('SELECT 1 FROM programs WHERE program_code = ?');
                for ($suffix = 1; ; $suffix++) {
                    $check->execute([$code]);
                    if (!$check->fetchColumn()) break;
                    $code = $codeBase . '-' . $slot . $suffix;
                    if (strlen($code) > 10) throw new RuntimeException('Could not assign a migration program code.');
                }
                $routeBase = 'basic-' . $slug . '-' . $slot;
                $route = $routeBase;
                $checkSlug = $db->prepare("SELECT 1 FROM programs WHERE slug = ? UNION SELECT 1 FROM slug_history WHERE old_slug = ? AND `type` = 'program' LIMIT 1");
                for ($suffix = 2; ; $suffix++) {
                    $checkSlug->execute([$route, $route]);
                    if (!$checkSlug->fetchColumn()) break;
                    $route = $routeBase . '-' . $suffix;
                }
                $insert = $db->prepare('INSERT INTO programs (program_code, slug, program_name, description, dept_id, image_media_id, is_published) VALUES (?, ?, ?, ?, ?, ?, 1)');
                $insert->execute([$code, $route, $name, $description, $dept['dept_id'], $imageId]);
                $counts['programs']++;
            }
        }
        if ($ownsTransaction) $db->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction) $db->rollBack();
        throw $e;
    }
    return $counts;
}
