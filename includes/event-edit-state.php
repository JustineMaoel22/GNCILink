<?php
function eventAllDayStorageReady(PDO $db): bool {
    return (bool)$db->query("SHOW COLUMNS FROM events LIKE 'is_all_day'")->fetch(PDO::FETCH_ASSOC);
}
/** Read the event and its audience from one transaction for an editor baseline. */
function eventEditState(PDO $db,int $id): array {
    if(!$db->inTransaction())throw new LogicException('Event snapshots require a transaction.');
    $q=$db->prepare('SELECT * FROM events WHERE event_id=? FOR UPDATE');$q->execute([$id]);$record=$q->fetch(PDO::FETCH_ASSOC);
    if(!$record)throw new InvalidArgumentException('Event not found.');
    $audience=[];
    foreach(['dept_ids'=>['event_departments','dept_id'],'programs'=>['event_programs','program_code']] as $key=>[$table,$column]){
        try{$q=$db->prepare("SELECT $column FROM $table WHERE event_id=? ORDER BY $column FOR UPDATE");$q->execute([$id]);$audience[$key]=array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN));}
        catch(PDOException $error){if($error->getCode()!=='42S02')throw $error;$audience[$key]=[];}
    }
    if(!$audience['dept_ids']&&!empty($record['dept_id']))$audience['dept_ids']=[(string)$record['dept_id']];
    if(!$audience['programs']&&!empty($record['program'])&&$record['program']!=='ALL')$audience['programs']=[$record['program']];
    $normalized=array_map(static fn($value)=>$value===null?null:(string)$value,$record);ksort($normalized);
    $version=hash('sha256',json_encode([$normalized,$audience],JSON_THROW_ON_ERROR));
    return ['record'=>$record,'audience'=>$audience,'version'=>$version];
}
