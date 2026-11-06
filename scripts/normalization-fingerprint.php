<?php
/** Local CLI backup verification: output only counts and hashes, never record values. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
if(DB_HOST!=='db')throw new RuntimeException('This helper requires the local Docker database service.');
$db=getDB();$schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();$projection=null;
if(($argv[1]??'')==='--original-columns'){
    $projection=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
    if(!is_array($projection))throw new InvalidArgumentException('Expected original column metadata.');
} elseif(isset($argv[1])){
    if(!preg_match('/\Agnci_personnel_restore_[a-f0-9]{12}\z/D',$argv[1]))throw new InvalidArgumentException('Only a generated restore-test schema is allowed.');
    $db->exec('USE `'.$argv[1].'`');
}
$tables=[];
foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $table){
    if($projection!==null&&!isset($projection[$table[0]]))continue;
    $quoted='`'.str_replace('`','``',$table[0]).'`';$rows=[];
    $select='*';$where='';
    if($projection!==null){
        $columns=$projection[$table[0]];
        foreach($columns as $column)if(!is_string($column)||!preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/D',$column))throw new InvalidArgumentException('Invalid original column.');
        $select=implode(',',array_map(static fn($column)=>'`'.$column.'`',$columns));
        if($table[0]==='content_migrations')$where=" WHERE migration_key<>'shared-personnel-v1'";
    }
    foreach($db->query('SELECT '.$select.' FROM '.$quoted.$where)->fetchAll(PDO::FETCH_NUM) as $row){$encoded=[];foreach($row as $value)$encoded[]=$value===null?null:base64_encode((string)$value);$rows[]=json_encode($encoded,JSON_THROW_ON_ERROR);}
    sort($rows,SORT_STRING);
    $ddl=$db->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
    // MySQL may rewrite an auto-increment counter after loading historical rows.
    $ddl=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$ddl);
    // A named collation already determines its charset; mysqldump may make it explicit.
    $ddl=preg_replace('/ CHARACTER SET ([a-z0-9]+)(?= COLLATE \1_)/i','',$ddl);
    $tables[$table[0]]=['rows'=>count($rows),'data_sha256'=>hash('sha256',implode("\0",$rows)),'schema_sha256'=>hash('sha256',$ddl),'schema_definition'=>$ddl];
}
ksort($tables);echo json_encode(['source_schema'=>$schema,'tables'=>$tables],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
