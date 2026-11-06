<?php
/** Explicitly reviewed obsolete tables only. No heuristic bulk deletion. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
const GNCI_UNUSED_TABLES=['office_services','program_sections','community_extension_page'];

function unusedTableExists(PDO $db,string $table): bool {
    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? AND table_type=?');$q->execute([$table,'BASE TABLE']);return (bool)$q->fetchColumn();
}
function unusedTableSnapshot(PDO $db,string $table): array {
    if(!in_array($table,GNCI_UNUSED_TABLES,true))throw new InvalidArgumentException('Table is outside the reviewed cleanup.');
    $ddl=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
    $columns=array_column($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC),'Field');$rows=[];
    foreach($db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_NUM) as $row)$rows[]=array_map(static fn($value)=>$value===null?null:base64_encode((string)$value),$row);
    usort($rows,static fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));
    return ['ddl'=>$ddl,'columns'=>$columns,'rows'=>$rows];
}
function unusedTableEquivalent(array $left,array $right): bool {
    foreach([&$left,&$right] as &$snapshot)$snapshot['ddl']=preg_replace('/ CHARACTER SET ([a-z0-9]+)(?= COLLATE \1_)/i','',$snapshot['ddl']);
    unset($snapshot);return $left===$right;
}
function unusedTableRuntimeReferences(string $root,string $table): array {
    $files=glob($root.'/*.php')?:[];
    foreach(['admin','api','chatbot','components','config','includes','pages','assets/js'] as $folder){
        $path=$root.'/'.$folder;if(!is_dir($path))continue;
        $iterator=new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),static function($file){
            return !$file->isDir()||!in_array($file->getFilename(),['vendor','node_modules','cache','image-originals'],true);
        });
        foreach(new RecursiveIteratorIterator($iterator) as $file){
            $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
            if(!$file->isFile()||!in_array(strtolower($file->getExtension()),['php','js'],true)||preg_match('~(^|/)(vendor|node_modules|cache)(/|$)~',$relative))continue;
            $files[]=$file->getPathname();
        }
    }
    $hits=[];
    foreach($files as $file){
        foreach(file($file) as $number=>$line){
            if(!preg_match('/\b'.preg_quote($table,'/').'\b/i',$line))continue;
            // The logical list kind maps to admin_office_services, not office_services.
            $physical=preg_match('/\b(?:FROM|JOIN|INTO|UPDATE|TABLE|REFERENCES)\s+[`\'" ]*'.preg_quote($table,'/').'\b/i',$line);
            if($table==='office_services'&&!$physical&&(preg_match('/\b(?:contentListsHydrate|contentListReplace)\s*\(/',$line)||preg_match('/[\'"]office_services[\'"]\s*=>.*[\'"]table[\'"]\s*=>\s*[\'"]admin_office_services[\'"]/',$line)))continue;
            $hits[]=str_replace('\\','/',substr($file,strlen($root)+1)).':'.($number+1);
        }
    }
    return $hits;
}
function unusedTablesValidate(PDO $db,string $root): void {
    foreach(GNCI_UNUSED_TABLES as $table){
        if(!unusedTableExists($db,$table))continue;
        $hits=unusedTableRuntimeReferences($root,$table);
        if($hits)throw new RuntimeException('Runtime references require review for '.$table.': '.implode(', ',$hits));
        $q=$db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME=?');$q->execute([$table]);
        if((int)$q->fetchColumn()!==0)throw new RuntimeException('An incoming foreign key still uses '.$table.'.');
        $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE EVENT_OBJECT_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?');$q->execute([$table]);
        if((int)$q->fetchColumn()!==0)throw new RuntimeException('A trigger still uses '.$table.'.');
        $count=(int)$db->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
        if(($table!=='community_extension_page'&&$count!==0)||($table==='community_extension_page'&&$count>1))throw new RuntimeException('Unexpected data requires review for '.$table.'.');
    }
    foreach([
        "SELECT VIEW_DEFINITION AS body FROM information_schema.VIEWS WHERE TABLE_SCHEMA NOT IN ('mysql','sys','information_schema','performance_schema')",
        "SELECT ACTION_STATEMENT AS body FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA NOT IN ('mysql','sys','information_schema','performance_schema')",
        "SELECT ROUTINE_DEFINITION AS body FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA NOT IN ('mysql','sys','information_schema','performance_schema')",
        "SELECT EVENT_DEFINITION AS body FROM information_schema.EVENTS WHERE EVENT_SCHEMA NOT IN ('mysql','sys','information_schema','performance_schema')",
    ] as $sql){
        foreach($db->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $body){
            if($body===null)throw new RuntimeException('A stored definition is hidden; cleanup cannot establish safety.');
            foreach(GNCI_UNUSED_TABLES as $table)if(preg_match('/\b'.preg_quote($table,'/').'\b/i',$body))throw new RuntimeException('A stored object still uses '.$table.'.');
        }
    }
}
function unusedTablesArchive(PDO $db,string $path): array {
    $schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();$tables=[];
    foreach(GNCI_UNUSED_TABLES as $table)if(unusedTableExists($db,$table))$tables[$table]=unusedTableSnapshot($db,$table);
    if(is_file($path)){
        $archive=unusedTablesReadArchive($path);
        if($archive['schema']!==$schema)throw new RuntimeException('Archive belongs to another schema.');
        foreach($tables as $table=>$snapshot)if(!isset($archive['tables'][$table])||!unusedTableEquivalent($archive['tables'][$table],$snapshot))throw new RuntimeException('A table changed since it was archived.');
        return $archive;
    }
    if(!$tables)throw new RuntimeException('No remaining tables to archive; use the original verified archive for rollback.');
    $archive=['format'=>'gnci-unused-tables-v1','schema'=>$schema,'created_at'=>gmdate('c'),'tables'=>$tables];
    $json=json_encode($archive,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);
    $stream=fopen($path,'x');if(!$stream)throw new RuntimeException('Could not create the private rollback archive.');
    try{if(fwrite($stream,$json)!==strlen($json))throw new RuntimeException('Rollback archive was not fully written.');}finally{fclose($stream);}
    if(file_put_contents($path.'.sha256',hash('sha256',$json),LOCK_EX)===false)throw new RuntimeException('Could not record the rollback archive checksum.');
    return unusedTablesReadArchive($path);
}
function unusedTablesReadArchive(string $path): array {
    $json=file_get_contents($path);$expected=trim((string)file_get_contents($path.'.sha256'));
    if($json===false||!preg_match('/\A[a-f0-9]{64}\z/D',$expected)||!hash_equals($expected,hash('sha256',$json)))throw new RuntimeException('Rollback archive checksum failed.');
    $archive=json_decode($json,true,64,JSON_THROW_ON_ERROR);
    if(($archive['format']??'')!=='gnci-unused-tables-v1'||!is_array($archive['tables']??null))throw new RuntimeException('Unsupported rollback archive.');
    foreach($archive['tables'] as $table=>$snapshot){
        if(!in_array($table,GNCI_UNUSED_TABLES,true)||!str_starts_with($snapshot['ddl']??'','CREATE TABLE `'.$table.'`')||str_contains($snapshot['ddl'],';'))throw new RuntimeException('Unreviewed schema definition in archive.');
        foreach($snapshot['columns'] as $column)if(!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$column))throw new RuntimeException('Invalid archived column.');
    }
    return $archive;
}
function unusedTablesVerifyRollback(PDO $db,string $archivePath): void {
    $archive=unusedTablesReadArchive($archivePath);$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();
    if($archive['schema']!==$source)throw new RuntimeException('Archive belongs to another schema.');
    $clone='gnci_unused_verify_'.bin2hex(random_bytes(6));$created=false;$copy=tempnam(sys_get_temp_dir(),'unused-verify-');
    try{
        $ddl=[];foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row)$ddl[]=$db->query('SHOW CREATE TABLE `'.str_replace('`','``',$row[0]).'`')->fetch(PDO::FETCH_NUM)[1];
        $db->exec("CREATE DATABASE `$clone` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;$db->exec("USE `$clone`");
        $db->exec('SET FOREIGN_KEY_CHECKS=0');try{foreach($ddl as $sql)$db->exec($sql);}finally{$db->exec('SET FOREIGN_KEY_CHECKS=1');}
        $testArchive=$archive;$testArchive['schema']=$clone;$json=json_encode($testArchive,JSON_THROW_ON_ERROR);
        file_put_contents($copy,$json);file_put_contents($copy.'.sha256',hash('sha256',$json));
        unusedTablesRollback($db,$copy);
        foreach($archive['tables'] as $table=>$snapshot)if(!unusedTableEquivalent(unusedTableSnapshot($db,$table),$snapshot))throw new RuntimeException('Actual rollback verification failed for '.$table.'.');
        $receipt=['format'=>'gnci-unused-rollback-verified-v1','schema'=>$source,'archive_sha256'=>hash_file('sha256',$archivePath),'verified_at'=>gmdate('c'),'tables'=>array_keys($archive['tables'])];
        if(file_put_contents($archivePath.'.verified.json',json_encode($receipt,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT),LOCK_EX)===false)throw new RuntimeException('Could not record rollback verification.');
    }finally{
        if($db->inTransaction())$db->rollBack();$db->exec('USE `'.str_replace('`','``',$source).'`');
        if($created&&preg_match('/\Agnci_unused_verify_[a-f0-9]{12}\z/D',$clone)&&$clone!==$source)$db->exec("DROP DATABASE `$clone`");
        if(is_file($copy))unlink($copy);if(is_file($copy.'.sha256'))unlink($copy.'.sha256');
    }
}
function unusedTablesApply(PDO $db,string $root,string $archivePath): void {
    if($db->inTransaction())throw new LogicException('Table DDL must run outside a transaction.');
    unusedTablesValidate($db,$root);$archive=unusedTablesArchive($db,$archivePath);
    $remaining=[];
    foreach(GNCI_UNUSED_TABLES as $table)if(unusedTableExists($db,$table)){
        if(!unusedTableEquivalent(unusedTableSnapshot($db,$table),$archive['tables'][$table]))throw new RuntimeException('Archived table changed; stop other writers.');
        $remaining[]='`'.$table.'`';
    }
    if($remaining)$db->exec('DROP TABLE '.implode(',',$remaining));
}
function unusedTablesRollback(PDO $db,string $archivePath): void {
    if($db->inTransaction())throw new LogicException('Table DDL must run outside a transaction.');
    $archive=unusedTablesReadArchive($archivePath);
    if($archive['schema']!==(string)$db->query('SELECT DATABASE()')->fetchColumn())throw new RuntimeException('Archive belongs to another schema.');
    foreach($archive['tables'] as $table=>$snapshot){
        if(unusedTableExists($db,$table)){
            $current=unusedTableSnapshot($db,$table);
            if(unusedTableEquivalent($current,$snapshot))continue;
            $emptySnapshot=$snapshot;$emptySnapshot['rows']=[];
            if($current['rows']||!unusedTableEquivalent($current,$emptySnapshot))throw new RuntimeException('Existing table differs; rollback will not overwrite '.$table.'.');
        }else{$db->exec($snapshot['ddl']);}
        $columns=implode(',',array_map(static fn($c)=>'`'.$c.'`',$snapshot['columns']));
        $q=$db->prepare('INSERT INTO `'.$table.'`('.$columns.') VALUES('.implode(',',array_fill(0,count($snapshot['columns']),'?')).')');
        $db->beginTransaction();
        try{
            foreach($snapshot['rows'] as $row){$values=[];foreach($row as $value){$decoded=$value===null?null:base64_decode($value,true);if($decoded===false)throw new RuntimeException('Invalid archived value.');$values[]=$decoded;}$q->execute($values);}
            $restored=unusedTableSnapshot($db,$table);
            if($restored['rows']!==$snapshot['rows']||$restored['columns']!==$snapshot['columns'])throw new RuntimeException('Restored records did not match the archive.');
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
