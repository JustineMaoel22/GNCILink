<?php
/** Shared people; roles, contact details and photo placements remain section-owned. */
require_once __DIR__.'/normalized-content-lists.php';
const GNCI_PERSONNEL_MIGRATION='shared-personnel-v1';

function personnelDefinitions(): array {
    return [
        'person'=>['table'=>'admin_people','id'=>'person_id','name'=>'name'],
        'office'=>['table'=>'admin_offices','id'=>'office_id','name'=>'head_name'],
        'dean'=>['table'=>'departments','id'=>'dept_id','name'=>'dean_name'],
        'president'=>['table'=>'homepage_president','id'=>'id','name'=>'name'],
    ];
}
function personnelEnabled(PDO $db, bool $refresh=false): bool {
    static $cache; $cache ??= new WeakMap();
    if ($refresh) unset($cache[$db]);
    if (!isset($cache[$db])) {
        try {$q=$db->prepare('SELECT 1 FROM content_migrations WHERE migration_key=?');$q->execute([GNCI_PERSONNEL_MIGRATION]);$cache[$db]=(bool)$q->fetchColumn();}
        catch (PDOException $e) {if ((string)$e->getCode()!=='42S02') throw $e;$cache[$db]=false;}
    }
    return $cache[$db];
}
function personnelProfiles(PDO $db): array {
    if (!personnelEnabled($db)) return [];
    static $cache; $cache ??= new WeakMap();$version=$GLOBALS['personnel_revision']??0;
    if (isset($cache[$db]) && $cache[$db]['version']===$version) return $cache[$db]['rows'];
    $rows=[];
    foreach (personnelDefinitions() as $kind=>$d) {
        $found=$db->query("SELECT p.{$d['id']} AS record_id,p.personnel_id,n.full_name FROM {$d['table']} p LEFT JOIN personnel n ON n.personnel_id=p.personnel_id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($found as $row) $rows[$kind.':'.$row['record_id']]=$row;
    }
    $cache[$db]=['version'=>$version,'rows'=>$rows];return $rows;
}
function personnelHydrate(string $kind, array $rows): array {
    $db=getDB(); if (!personnelEnabled($db)) return $rows;
    $d=personnelDefinitions()[$kind];
    // Read once per collection, not once per record. Avoid a stale cross-write name cache.
    $map=personnelProfiles($db);
    foreach ($rows as &$row) {
        $person=$map[$kind.':'.$row[$d['id']]]??null;
        $row['personnel_id']=$person['personnel_id']??null;
        if ($person && $person['personnel_id']!==null) $row[$d['name']]=$person['full_name'];
    }
    unset($row);return $rows;
}
function personnelName(string $name): string {
    $name=preg_replace('/\s+/u',' ',trim($name));
    if ($name===null || mb_strlen($name)>255) throw new InvalidArgumentException('Enter a full name of up to 255 characters.');
    return $name;
}

/** Files cannot roll back with SQL. Check old media only after the enclosing save ends. */
function personnelDeferMediaCleanup(int $mediaId): bool {
    if (empty($GLOBALS['personnel_write_identity'])) return false;
    $db=getDB();if(!$db->inTransaction())return false;
    $schema=(string)$db->query('SELECT DATABASE()')->fetchColumn();
    static $pending=[];$key=$schema.':'.$mediaId;
    if(isset($pending[$key]))return true;$pending[$key]=true;
    register_shutdown_function(static function()use($db,$schema,$mediaId){
        try {
            if($db->inTransaction() || (string)$db->query('SELECT DATABASE()')->fetchColumn()!==$schema)return;
            // After commit, old media may be unreferenced. After rollback, references protect it.
            if(empty($GLOBALS['personnel_write_identity']))deleteMediaIfUnreferenced($mediaId);
        } catch(Throwable $e){error_log('Deferred personnel media cleanup was skipped.');}
    });
    return true;
}
function personnelMirrorName(PDO $db,int $id,string $name): void {
    foreach (personnelDefinitions() as $d) {
        $db->prepare("UPDATE {$d['table']} SET {$d['name']}=? WHERE personnel_id=?")->execute([$name,$id]);
    }
}

/** Wrap the existing save, name change and reference in one transaction. */
function personnelWrite(string $kind,int $recordId,string $name,callable $save) {
    $db=getDB();
    if (!personnelEnabled($db)) return $save($name);
    try {return contentListTransaction($db,static function () use($db,$kind,$recordId,$name,$save) {
        $d=personnelDefinitions()[$kind]; $name=personnelName($name);
        $q=$db->prepare("SELECT personnel_id FROM {$d['table']} WHERE {$d['id']}=? FOR UPDATE");$q->execute([$recordId]);$id=(int)$q->fetchColumn();
        if ($id) {
            $q=$db->prepare('SELECT full_name FROM personnel WHERE personnel_id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetchColumn();
            if ($name==='') {
                $db->prepare("UPDATE {$d['table']} SET personnel_id=NULL WHERE {$d['id']}=?")->execute([$recordId]);$id=0;
            } elseif ($before!==$name) {
                $db->prepare('UPDATE personnel SET full_name=? WHERE personnel_id=?')->execute([$name,$id]);
                personnelMirrorName($db,$id,$name);
                if (!logActivity((int)($_SESSION['user_id']??0),'UPDATE','personnel',$id,'Updated shared personnel name from '.$before.' to '.$name)) throw new RuntimeException('Could not record the shared name change.');
            }
        } elseif ($name!=='') {
            $db->prepare('INSERT INTO personnel(full_name) VALUES(?)')->execute([$name]);$id=(int)$db->lastInsertId();
        }
        $GLOBALS['personnel_revision']=($GLOBALS['personnel_revision']??0)+1;
        $key=$kind.':'.$recordId;$previous=$GLOBALS['personnel_write_identity'][$key]??null;
        $GLOBALS['personnel_write_identity'][$key]=$id;
        try {$result=$save($name);} finally {
            if ($previous===null) unset($GLOBALS['personnel_write_identity'][$key]);else $GLOBALS['personnel_write_identity'][$key]=$previous;
        }
        $savedId=$recordId>0?$recordId:(int)$result;
        if ($id && $savedId) $db->prepare("UPDATE {$d['table']} SET personnel_id=? WHERE {$d['id']}=?")->execute([$id,$savedId]);
        $GLOBALS['personnel_revision']=($GLOBALS['personnel_revision']??0)+1;
        if (is_array($result)) {$result['personnel_id']=$id?:null;$result[$d['name']]=$name;}
        return $result;
    });} catch(Throwable $e) {$GLOBALS['personnel_revision']=($GLOBALS['personnel_revision']??0)+1;throw $e;}
}

/** Explicit Super Admin review links placements; equal names alone never merge people. */
function personnelLink(PDO $db,string $profile,int $personnelId): void {
    if (!rbacIsSuper()) throw new RuntimeException('Only Super Admin can link personnel identities.');
    if (!preg_match('/\A(person|office|dean|president):([1-9][0-9]*)\z/D',$profile,$m)) throw new InvalidArgumentException('Choose a valid profile.');
    if (!personnelEnabled($db)) throw new RuntimeException('Personnel normalization is not enabled.');
    $d=personnelDefinitions()[$m[1]];
    contentListTransaction($db,static function () use($db,$d,$m,$personnelId) {
        $q=$db->prepare("SELECT personnel_id,{$d['name']} AS full_name FROM {$d['table']} WHERE {$d['id']}=? FOR UPDATE");$q->execute([(int)$m[2]]);$placement=$q->fetch(PDO::FETCH_ASSOC);
        if (!$placement) throw new InvalidArgumentException('This profile no longer exists.');
        if ($personnelId===0) {
            $name=personnelName((string)$placement['full_name']);
            if ($name==='') throw new InvalidArgumentException('Set a full name before creating a separate person.');
            $db->prepare('INSERT INTO personnel(full_name) VALUES(?)')->execute([$name]);$personnelId=(int)$db->lastInsertId();
        } else {
            $q=$db->prepare('SELECT full_name FROM personnel WHERE personnel_id=? FOR UPDATE');$q->execute([$personnelId]);$name=$q->fetchColumn();
            if ($name===false) throw new InvalidArgumentException('The selected person no longer exists.');
        }
        $db->prepare("UPDATE {$d['table']} SET personnel_id=?,{$d['name']}=? WHERE {$d['id']}=?")->execute([$personnelId,$name,(int)$m[2]]);
        if (!logActivity((int)($_SESSION['user_id']??0),'UPDATE',$d['table'],(int)$m[2],'Reviewed personnel identity link: '.$m[1].' profile, person '.(int)$placement['personnel_id'].' to person '.$personnelId)) throw new RuntimeException('Could not record the identity review.');
        $GLOBALS['personnel_revision']=($GLOBALS['personnel_revision']??0)+1;
    });
}
