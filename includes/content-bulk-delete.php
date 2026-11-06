<?php
/** Bulk deletion uses the same content tables/FK cleanup as individual deletion. */
function bulkContentIds($value): array {
    if(!is_array($value)||!$value||count($value)>500)throw new InvalidArgumentException('Select between 1 and 500 items.');
    $ids=[];
    foreach($value as $id){
        if((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$id))throw new InvalidArgumentException('Invalid content selection.');
        $ids[]=(int)$id;
    }
    $ids=array_values(array_unique($ids));sort($ids,SORT_NUMERIC);return $ids;
}
function bulkContentTable(string $kind): string {
    return ['news'=>'news','announcement'=>'announcements','event'=>'events'][$kind]??throw new InvalidArgumentException('Invalid content type.');
}
function bulkContentRows(PDO $db,string $kind,array $ids,bool $lock=false): array {
    $table=bulkContentTable($kind);$ids=bulkContentIds($ids);
    $q=$db->prepare("SELECT {$kind}_id AS id,user_id,title FROM `$table` WHERE {$kind}_id IN (".implode(',',array_fill(0,count($ids),'?')).") ORDER BY {$kind}_id".($lock?' FOR UPDATE':''));
    $q->execute($ids);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)!==count($ids))throw new RuntimeException('Some selected items no longer exist. Refresh the list before deleting.');
    $all=hasPermission('delete_'.$kind);$own=hasPermission('delete_own_'.$kind);
    foreach($rows as $row)if(!$all&&!($own&&(int)$row['user_id']===(int)$_SESSION['user_id']))throw new RuntimeException('You cannot delete one or more selected items. Nothing was deleted.');
    return $rows;
}
function deleteSelectedContent(string $kind,$selection): int {
    $ids=bulkContentIds($selection);$table=bulkContentTable($kind);$db=getDB();$db->beginTransaction();
    try{
        $rows=bulkContentRows($db,$kind,$ids,true);
        $q=$db->prepare("DELETE FROM `$table` WHERE {$kind}_id IN (".implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);
        if($q->rowCount()!==count($ids))throw new RuntimeException('The selection changed. Nothing was deleted. Refresh and try again.');
        foreach($rows as $row)if(!logActivity((int)$_SESSION['user_id'],'DELETE',$table,(int)$row['id'],'Bulk deletion: '.$row['title']))throw new RuntimeException('Could not record the deletion. Nothing was deleted.');
        $db->commit();return count($ids);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
