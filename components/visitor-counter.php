<?php

if (!function_exists('gncVisitorToday')) {

    /** Today's date in the school's timezone (not the server's). */
    function gncVisitorToday(): string {
        return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
    }

    function gncVisitorEnsureTable(PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS site_visitors (
                id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                visitor_hash CHAR(64)     NOT NULL,
                visit_date   DATE         NOT NULL,
                first_seen   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_visitor_day (visit_date, visitor_hash),
                KEY idx_visitor (visitor_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /** @return array{today:int,total:int} */
    function gncVisitorStats(PDO $db): array {
        $query = function () use ($db): array {
            $stmt = $db->prepare("
                SELECT
                    SUM(visit_date = :today)       AS today_count,
                    COUNT(DISTINCT visitor_hash)   AS total_count
                FROM site_visitors
            ");
            $stmt->execute([':today' => gncVisitorToday()]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'today' => (int) ($row['today_count'] ?? 0),
                'total' => (int) ($row['total_count'] ?? 0),
            ];
        };

        try {
            return $query();
        } catch (Throwable $e) {
            // Most likely the table doesn't exist yet: create it and retry once.
            try {
                gncVisitorEnsureTable($db);
                return $query();
            } catch (Throwable $e2) {
                return ['today' => 0, 'total' => 0];
            }
        }
    }

    /** Records this visitor for today. Safe to call repeatedly (INSERT IGNORE). */
    function gncVisitorRecord(PDO $db, string $visitorId): void {
        gncVisitorEnsureTable($db);
        $stmt = $db->prepare("
            INSERT IGNORE INTO site_visitors (visitor_hash, visit_date)
            VALUES (:hash, :today)
        ");
        $stmt->execute([
            ':hash'  => hash('sha256', $visitorId),
            ':today' => gncVisitorToday(),
        ]);
    }

    function gncVisitorIsBot(string $userAgent): bool {
        if ($userAgent === '') return true;
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|curl|wget|python-requests|headless|facebookexternalhit|preview|monitor|uptime/i',
            $userAgent
        );
    }
}