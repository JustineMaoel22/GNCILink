<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/unused-table-cleanup.php';
$options=getopt('',['prepare','apply','rollback','maintenance','backup-verified','archive:']);
$modes=array_intersect(['prepare','apply','rollback'],array_keys($options));
if(count($modes)>1){fwrite(STDERR,"Choose one operation.\n");exit(1);}
$mutating=(bool)$modes;$db=getDB();$locked=false;
try{
    if($mutating){
        if(!isset($options['maintenance'],$options['backup-verified'],$options['archive']))throw new RuntimeException('Stop writers, verify a restorable backup and provide --archive outside the web root.');
        $path=(string)$options['archive'];$directory=realpath(dirname($path));$root=realpath(__DIR__.'/..');
        if(!$directory||$directory===$root||str_starts_with($directory.DIRECTORY_SEPARATOR,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Rollback archive must be outside the web root.');
        if(DB_HOST!=='db')throw new RuntimeException('This reviewed cleanup is authorized for local Docker only.');
        $locked=(int)$db->query("SELECT GET_LOCK('gnci_unused_tables_cleanup',0)")->fetchColumn()===1;
        if(!$locked)throw new RuntimeException('Another cleanup is running.');
        if(isset($options['prepare'])){
            unusedTablesValidate($db,$root);unusedTablesArchive($db,$path);unusedTablesVerifyRollback($db,$path);
            echo "Rollback archive restored and verified in an isolated schema.\n";
        }elseif(isset($options['apply'])){
            $receipt=json_decode((string)file_get_contents($path.'.verified.json'),true,16,JSON_THROW_ON_ERROR);
            if(($receipt['format']??'')!=='gnci-unused-rollback-verified-v1'||($receipt['schema']??'')!==(string)$db->query('SELECT DATABASE()')->fetchColumn()||!hash_equals($receipt['archive_sha256']??'',(string)hash_file('sha256',$path)))throw new RuntimeException('Prepare and verify the current rollback archive first.');
            unusedTablesApply($db,$root,$path);echo "Reviewed cleanup completed.\n";
        }else{unusedTablesRollback($db,$path);echo "Archived tables and records restored.\n";}
    }
    foreach(GNCI_UNUSED_TABLES as $table)echo $table.': '.(unusedTableExists($db,$table)?'present':'removed')."\n";
}catch(Throwable $e){error_log('Unused-table cleanup: '.$e->getMessage());fwrite(STDERR,"Cleanup stopped. No unrelated tables were targeted. Inspect the private log and retain the rollback archive.\n");exit(1);}
finally{if($locked)$db->query("SELECT RELEASE_LOCK('gnci_unused_tables_cleanup')");}
