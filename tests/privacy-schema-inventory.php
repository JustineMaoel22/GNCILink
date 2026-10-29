<?php
// Read-only metadata inventory. Never prints records, credentials or tokens.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$query=getDB()->prepare('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,ORDINAL_POSITION');
$query->execute([DB_NAME]);
$tables=[];
foreach($query as $column) $tables[$column['TABLE_NAME']][]=['column'=>$column['COLUMN_NAME'],'type'=>$column['COLUMN_TYPE'],'nullable'=>$column['IS_NULLABLE']];
echo json_encode($tables,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
