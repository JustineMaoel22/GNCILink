<?php
/**
 * Multi-Audience Support for Announcements & Events
 *
 * Provides storage, retrieval, and normalization for announcements and events
 * targeted to one, multiple, or all departments and academic programs.
 */

if (!function_exists('ensureMultiAudienceTables')) {
    function ensureMultiAudienceTables(PDO $db): void {
        static $ensured = false;
        if ($ensured) return;
        try {
            $db->exec("
                CREATE TABLE IF NOT EXISTS `announcement_departments` (
                  `announcement_id` int NOT NULL,
                  `dept_id` int NOT NULL,
                  PRIMARY KEY (`announcement_id`, `dept_id`),
                  KEY `idx_ann_dept` (`dept_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `announcement_programs` (
                  `announcement_id` int NOT NULL,
                  `program_code` varchar(20) NOT NULL,
                  PRIMARY KEY (`announcement_id`, `program_code`),
                  KEY `idx_ann_prog` (`program_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `event_departments` (
                  `event_id` int NOT NULL,
                  `dept_id` int NOT NULL,
                  PRIMARY KEY (`event_id`, `dept_id`),
                  KEY `idx_evt_dept` (`dept_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS `event_programs` (
                  `event_id` int NOT NULL,
                  `program_code` varchar(20) NOT NULL,
                  PRIMARY KEY (`event_id`, `program_code`),
                  KEY `idx_evt_prog` (`program_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $ensured = true;
        } catch (Throwable $e) {
            error_log('ensureMultiAudienceTables error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('normalizeMultiAudienceInput')) {
    /**
     * Normalizes submitted department IDs and program codes.
     * Returns an array with:
     * - 'dept_ids': array of ints (empty means ALL/Everyone)
     * - 'programs': array of uppercase program codes (empty means ALL/Everyone)
     * - 'primary_dept_id': int|null for backwards compatibility
     * - 'primary_program': string ('ALL' or single code) for backwards compatibility
     */
    function normalizeMultiAudienceInput($rawDepts, $rawPrograms, ?array $validPrograms = null): array {
        if ($validPrograms === null) {
            if (function_exists('getProgramCategories')) {
                $validPrograms = getProgramCategories();
            } else {
                $validPrograms = [];
            }
        }

        // Normalize departments
        $deptIds = [];
        if (is_array($rawDepts)) {
            foreach ($rawDepts as $d) {
                if ($d === 'ALL' || $d === '' || $d === null) continue;
                $val = (int)$d;
                if ($val > 0) $deptIds[] = $val;
            }
        } elseif ($rawDepts !== null && $rawDepts !== '' && $rawDepts !== 'ALL') {
            $val = (int)$rawDepts;
            if ($val > 0) $deptIds[] = $val;
        }
        $deptIds = array_values(array_unique($deptIds));

        // Normalize programs
        $programs = [];
        if (is_array($rawPrograms)) {
            foreach ($rawPrograms as $p) {
                $code = trim((string)$p);
                if ($code === '' || $code === 'ALL') continue;
                if (empty($validPrograms) || isset($validPrograms[$code])) {
                    $programs[] = $code;
                }
            }
        } elseif ($rawPrograms !== null && $rawPrograms !== '' && $rawPrograms !== 'ALL') {
            $code = trim((string)$rawPrograms);
            if ($code !== '' && (empty($validPrograms) || isset($validPrograms[$code]))) {
                $programs[] = $code;
            }
        }
        $programs = array_values(array_unique($programs));

        $primaryDeptId = count($deptIds) === 1 ? $deptIds[0] : null;
        $primaryProgram = count($programs) === 1 ? $programs[0] : 'ALL';

        return [
            'dept_ids'        => $deptIds,
            'programs'        => $programs,
            'primary_dept_id' => $primaryDeptId,
            'primary_program' => $primaryProgram,
        ];
    }
}

if (!function_exists('saveAnnouncementAudience')) {
    function saveAnnouncementAudience(PDO $db, int $announcementId, array $deptIds, array $programs): void {
        if ($announcementId <= 0) return;
        ensureMultiAudienceTables($db);

        try {
            // Sync departments
            $stmtDel = $db->prepare("DELETE FROM announcement_departments WHERE announcement_id = ?");
            $stmtDel->execute([$announcementId]);

            if (!empty($deptIds)) {
                $stmtIns = $db->prepare("INSERT IGNORE INTO announcement_departments (announcement_id, dept_id) VALUES (?, ?)");
                foreach ($deptIds as $deptId) {
                    $deptId = (int)$deptId;
                    if ($deptId > 0) $stmtIns->execute([$announcementId, $deptId]);
                }
            }

            // Sync programs
            $stmtDelProg = $db->prepare("DELETE FROM announcement_programs WHERE announcement_id = ?");
            $stmtDelProg->execute([$announcementId]);

            if (!empty($programs)) {
                $stmtInsProg = $db->prepare("INSERT IGNORE INTO announcement_programs (announcement_id, program_code) VALUES (?, ?)");
                foreach ($programs as $prog) {
                    $prog = trim((string)$prog);
                    if ($prog !== '' && $prog !== 'ALL') {
                        $stmtInsProg->execute([$announcementId, $prog]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('saveAnnouncementAudience error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('saveEventAudience')) {
    function saveEventAudience(PDO $db, int $eventId, array $deptIds, array $programs): void {
        if ($eventId <= 0) return;
        ensureMultiAudienceTables($db);

        try {
            // Sync departments
            $stmtDel = $db->prepare("DELETE FROM event_departments WHERE event_id = ?");
            $stmtDel->execute([$eventId]);

            if (!empty($deptIds)) {
                $stmtIns = $db->prepare("INSERT IGNORE INTO event_departments (event_id, dept_id) VALUES (?, ?)");
                foreach ($deptIds as $deptId) {
                    $deptId = (int)$deptId;
                    if ($deptId > 0) $stmtIns->execute([$eventId, $deptId]);
                }
            }

            // Sync programs
            $stmtDelProg = $db->prepare("DELETE FROM event_programs WHERE event_id = ?");
            $stmtDelProg->execute([$eventId]);

            if (!empty($programs)) {
                $stmtInsProg = $db->prepare("INSERT IGNORE INTO event_programs (event_id, program_code) VALUES (?, ?)");
                foreach ($programs as $prog) {
                    $prog = trim((string)$prog);
                    if ($prog !== '' && $prog !== 'ALL') {
                        $stmtInsProg->execute([$eventId, $prog]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('saveEventAudience error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('getAnnouncementAudience')) {
    function getAnnouncementAudience(PDO $db, int $announcementId, ?int $legacyDeptId = null, ?string $legacyProgram = null): array {
        ensureMultiAudienceTables($db);
        $deptIds = [];
        $programs = [];

        try {
            $stmt = $db->prepare("SELECT dept_id FROM announcement_departments WHERE announcement_id = ? ORDER BY dept_id ASC");
            $stmt->execute([$announcementId]);
            $deptIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {}

        try {
            $stmt = $db->prepare("SELECT program_code FROM announcement_programs WHERE announcement_id = ? ORDER BY program_code ASC");
            $stmt->execute([$announcementId]);
            $programs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {}

        if (empty($deptIds) && $legacyDeptId !== null && (int)$legacyDeptId > 0) {
            $deptIds = [(int)$legacyDeptId];
        }
        if (empty($programs) && $legacyProgram !== null && $legacyProgram !== 'ALL' && $legacyProgram !== '') {
            $programs = [$legacyProgram];
        }

        return [
            'dept_ids' => $deptIds,
            'programs' => $programs,
        ];
    }
}

if (!function_exists('getEventAudience')) {
    function getEventAudience(PDO $db, int $eventId, ?int $legacyDeptId = null, ?string $legacyProgram = null): array {
        ensureMultiAudienceTables($db);
        $deptIds = [];
        $programs = [];

        try {
            $stmt = $db->prepare("SELECT dept_id FROM event_departments WHERE event_id = ? ORDER BY dept_id ASC");
            $stmt->execute([$eventId]);
            $deptIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {}

        try {
            $stmt = $db->prepare("SELECT program_code FROM event_programs WHERE event_id = ? ORDER BY program_code ASC");
            $stmt->execute([$eventId]);
            $programs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {}

        if (empty($deptIds) && $legacyDeptId !== null && (int)$legacyDeptId > 0) {
            $deptIds = [(int)$legacyDeptId];
        }
        if (empty($programs) && $legacyProgram !== null && $legacyProgram !== 'ALL' && $legacyProgram !== '') {
            $programs = [$legacyProgram];
        }

        return [
            'dept_ids' => $deptIds,
            'programs' => $programs,
        ];
    }
}

if (!function_exists('getAnnouncementAudiencesBatch')) {
    /**
     * Batch fetch audiences for multiple announcements in 2 indexed queries.
     * Returns: [ id => [ 'dept_ids' => [...], 'programs' => [...] ], ... ]
     */
    function getAnnouncementAudiencesBatch(PDO $db, array $announcements): array {
        if (empty($announcements)) return [];
        ensureMultiAudienceTables($db);

        $result = [];
        $ids = [];
        foreach ($announcements as $a) {
            $id = (int)($a['announcement_id'] ?? 0);
            if ($id <= 0) continue;
            $ids[] = $id;
            $legacyDept = !empty($a['dept_id']) ? (int)$a['dept_id'] : null;
            $legacyProg = (!empty($a['program']) && $a['program'] !== 'ALL') ? $a['program'] : null;
            $result[$id] = [
                'dept_ids' => $legacyDept !== null ? [$legacyDept] : [],
                'programs' => $legacyProg !== null ? [$legacyProg] : [],
            ];
        }

        if (empty($ids)) return $result;

        $inClause = implode(',', array_fill(0, count($ids), '?'));

        try {
            $stmt = $db->prepare("SELECT announcement_id, dept_id FROM announcement_departments WHERE announcement_id IN ($inClause)");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $groupedDepts = [];
            foreach ($rows as $r) {
                $aid = (int)$r['announcement_id'];
                $did = (int)$r['dept_id'];
                $groupedDepts[$aid][] = $did;
            }
            foreach ($groupedDepts as $aid => $dList) {
                if (isset($result[$aid])) {
                    $result[$aid]['dept_ids'] = $dList;
                }
            }
        } catch (Throwable $e) {}

        try {
            $stmt = $db->prepare("SELECT announcement_id, program_code FROM announcement_programs WHERE announcement_id IN ($inClause)");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $groupedProgs = [];
            foreach ($rows as $r) {
                $aid = (int)$r['announcement_id'];
                $code = $r['program_code'];
                $groupedProgs[$aid][] = $code;
            }
            foreach ($groupedProgs as $aid => $pList) {
                if (isset($result[$aid])) {
                    $result[$aid]['programs'] = $pList;
                }
            }
        } catch (Throwable $e) {}

        return $result;
    }
}

if (!function_exists('getEventAudiencesBatch')) {
    /**
     * Batch fetch audiences for multiple events in 2 indexed queries.
     * Returns: [ id => [ 'dept_ids' => [...], 'programs' => [...] ], ... ]
     */
    function getEventAudiencesBatch(PDO $db, array $events): array {
        if (empty($events)) return [];
        ensureMultiAudienceTables($db);

        $result = [];
        $ids = [];
        foreach ($events as $ev) {
            $id = (int)($ev['event_id'] ?? 0);
            if ($id <= 0) continue;
            $ids[] = $id;
            $legacyDept = !empty($ev['dept_id']) ? (int)$ev['dept_id'] : null;
            $legacyProg = (!empty($ev['program']) && $ev['program'] !== 'ALL') ? $ev['program'] : null;
            $result[$id] = [
                'dept_ids' => $legacyDept !== null ? [$legacyDept] : [],
                'programs' => $legacyProg !== null ? [$legacyProg] : [],
            ];
        }

        if (empty($ids)) return $result;

        $inClause = implode(',', array_fill(0, count($ids), '?'));

        try {
            $stmt = $db->prepare("SELECT event_id, dept_id FROM event_departments WHERE event_id IN ($inClause)");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $groupedDepts = [];
            foreach ($rows as $r) {
                $eid = (int)$r['event_id'];
                $did = (int)$r['dept_id'];
                $groupedDepts[$eid][] = $did;
            }
            foreach ($groupedDepts as $eid => $dList) {
                if (isset($result[$eid])) {
                    $result[$eid]['dept_ids'] = $dList;
                }
            }
        } catch (Throwable $e) {}

        try {
            $stmt = $db->prepare("SELECT event_id, program_code FROM event_programs WHERE event_id IN ($inClause)");
            $stmt->execute($ids);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $groupedProgs = [];
            foreach ($rows as $r) {
                $eid = (int)$r['event_id'];
                $code = $r['program_code'];
                $groupedProgs[$eid][] = $code;
            }
            foreach ($groupedProgs as $eid => $pList) {
                if (isset($result[$eid])) {
                    $result[$eid]['programs'] = $pList;
                }
            }
        } catch (Throwable $e) {}

        return $result;
    }
}

