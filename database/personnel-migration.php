<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../includes/personnel.php';

function personnelMigrationApply(PDO $db): void {
    if ($db->inTransaction()) throw new LogicException('Run personnel migration outside a transaction.');
    if (personnelEnabled($db,true)) return;
    $db->exec('CREATE TABLE IF NOT EXISTS content_migrations(migration_key VARCHAR(100) NOT NULL PRIMARY KEY,applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS personnel(personnel_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,full_name VARCHAR(255) NOT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX personnel_name(full_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    foreach (personnelDefinitions() as $kind=>$d) {
        $q=$db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$q->execute([$d['table'],'personnel_id']);
        if (!(bool)$q->fetchColumn()) $db->exec("ALTER TABLE {$d['table']} ADD COLUMN personnel_id INT UNSIGNED NULL,ADD CONSTRAINT personnel_{$kind}_fk FOREIGN KEY(personnel_id) REFERENCES personnel(personnel_id) ON DELETE RESTRICT");
    }
    contentListTransaction($db,static function () use($db) {
        foreach (personnelDefinitions() as $d) {
            $timestamp=$db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$timestamp->execute([$d['table'],'updated_at']);
            $preserveTimestamp=$timestamp->fetchColumn()?',updated_at=updated_at':'';
            $rows=$db->query("SELECT {$d['id']} AS id,{$d['name']} AS full_name,personnel_id FROM {$d['table']} ORDER BY {$d['id']} FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                if ($row['personnel_id']!==null || trim((string)$row['full_name'])==='') continue;
                // One identity per existing placement. Homonyms and variants require human review.
                $db->prepare('INSERT INTO personnel(full_name) VALUES(?)')->execute([$row['full_name']]);
                $db->prepare("UPDATE {$d['table']} SET personnel_id=?{$preserveTimestamp} WHERE {$d['id']}=?")->execute([(int)$db->lastInsertId(),$row['id']]);
            }
            $count=$db->query("SELECT COUNT(*) FROM {$d['table']} p LEFT JOIN personnel n ON n.personnel_id=p.personnel_id WHERE TRIM(COALESCE(p.{$d['name']},''))<>'' AND (n.personnel_id IS NULL OR BINARY n.full_name<>BINARY p.{$d['name']})")->fetchColumn();
            if ((int)$count!==0) throw new RuntimeException('Personnel backfill verification failed.');
        }
        $db->prepare('INSERT INTO content_migrations(migration_key) VALUES(?)')->execute([GNCI_PERSONNEL_MIGRATION]);
    });
    personnelEnabled($db,true);
}

function personnelMigrationRollback(PDO $db): void {
    if (!personnelEnabled($db,true)) return;
    contentListTransaction($db,static function () use($db) {
        foreach (personnelDefinitions() as $d) $db->exec("UPDATE {$d['table']} p JOIN personnel n ON n.personnel_id=p.personnel_id SET p.{$d['name']}=n.full_name");
        $db->prepare('DELETE FROM content_migrations WHERE migration_key=?')->execute([GNCI_PERSONNEL_MIGRATION]);
    });
    personnelEnabled($db,true);
    // Retain all identities and references for recovery; no DROP or DELETE of personnel.
}
