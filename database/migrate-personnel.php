<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/personnel-migration.php';
$options=getopt('',['apply','rollback','maintenance','backup-verified']);
if (isset($options['apply'],$options['rollback'])) {fwrite(STDERR,"Choose one operation.\n");exit(1);}
$mutating=isset($options['apply'])||isset($options['rollback']);
if ($mutating && (!isset($options['maintenance'])||!isset($options['backup-verified']))) {fwrite(STDERR,"Stop writers and verify a restorable backup first. See docs/personnel-normalization.md.\n");exit(1);}
$db=getDB();$locked=false;
try {
    if ($mutating) {
        $locked=(int)$db->query("SELECT GET_LOCK('gnci_personnel_migration',0)")->fetchColumn()===1;
        if (!$locked) throw new RuntimeException('Another personnel migration is running.');
        if (isset($options['apply'])) personnelMigrationApply($db);else personnelMigrationRollback($db);
    }
    echo 'Shared personnel: '.(personnelEnabled($db,true)?'enabled':'not applied').".\n";
    if (personnelEnabled($db)) echo 'Identities: '.(int)$db->query('SELECT COUNT(*) FROM personnel')->fetchColumn().".\n";
} catch (Throwable $e) {error_log('Personnel migration failed: '.$e->getMessage());fwrite(STDERR,"Personnel migration failed. Keep writers stopped and inspect the private log.\n");exit(1);}
finally {if ($locked) $db->query("SELECT RELEASE_LOCK('gnci_personnel_migration')");}
