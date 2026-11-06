<?php
/** Local read-only inventory. Never prints table records, credentials or stored SQL bodies. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
if(DB_HOST!=='db')throw new RuntimeException('Use the local Docker database.');
$db=getDB();$schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();$tables=[];
foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row){
    $name=$row[0];$quoted='`'.str_replace('`','``',$name).'`';
    $tables[$name]=['rows'=>(int)$db->query('SELECT COUNT(*) FROM '.$quoted)->fetchColumn(),'inbound'=>[],'outbound'=>[],'stored_dependencies'=>[]];
}
$q=$db->prepare('SELECT TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME IS NOT NULL AND (TABLE_SCHEMA=? OR REFERENCED_TABLE_SCHEMA=?)');$q->execute([$schema,$schema]);
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $fk){
    if($fk['REFERENCED_TABLE_SCHEMA']===$schema && isset($tables[$fk['REFERENCED_TABLE_NAME']]))$tables[$fk['REFERENCED_TABLE_NAME']]['inbound'][]=$fk;
    if($fk['TABLE_SCHEMA']===$schema && isset($tables[$fk['TABLE_NAME']]))$tables[$fk['TABLE_NAME']]['outbound'][]=$fk;
}
$definitions=[
    'view'=>['SELECT TABLE_NAME AS object_name,VIEW_DEFINITION AS body FROM information_schema.VIEWS WHERE TABLE_SCHEMA=?'],
    'trigger'=>['SELECT TRIGGER_NAME AS object_name,ACTION_STATEMENT AS body FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=?'],
    'routine'=>['SELECT ROUTINE_NAME AS object_name,ROUTINE_DEFINITION AS body FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=?'],
    'event'=>['SELECT EVENT_NAME AS object_name,EVENT_DEFINITION AS body FROM information_schema.EVENTS WHERE EVENT_SCHEMA=?'],
];$objects=[];
foreach($definitions as $type=>[$sql]){
    $q=$db->prepare($sql);$q->execute([$schema]);$items=$q->fetchAll(PDO::FETCH_ASSOC);$objects[$type]=count($items);
    foreach($items as $item)foreach($tables as $table=>&$details){
        if($item['body']===null || preg_match('/\b'.preg_quote($table,'/').'\b/i',(string)$item['body']))$details['stored_dependencies'][]=['type'=>$type,'name'=>$item['object_name'],'definition_visible'=>$item['body']!==null];
    }
    unset($details);
}
ksort($tables);echo json_encode(['schema'=>$schema,'stored_objects'=>$objects,'tables'=>$tables],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
