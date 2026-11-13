<?php
/** Compare the complete editable announcement, including same-second changes. */
class AnnouncementEditConflict extends RuntimeException {}

function announcementEditVersion(array $record, array $gallery, array $audience): string {
    $state=[];
    foreach(['announcement_id','title','slug','content','status','featured_image','category_id','program','dept_id','updated_at'] as $field)$state[$field]=(string)($record[$field]??'');
    $categories=array_map('strval',$record['category_ids']??(!empty($record['category_id'])?[$record['category_id']]:[]));sort($categories,SORT_STRING);
    $state['categories']=$categories;
    $state['gallery']=array_map(static function($image){return array_map('strval',[(int)$image['image_id'],(int)$image['media_id'],(int)$image['display_order']]);},$gallery);
    foreach(['dept_ids','programs'] as $field){$values=array_map('strval',$audience[$field]??[]);sort($values,SORT_STRING);$state[$field]=$values;}
    return hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
}

/** Caller keeps this transaction open through content, gallery and audience writes. */
function announcementCheckEditVersion(PDO $db,int $id,$expected): void {
    if(!$db->inTransaction())throw new LogicException('Announcement version checks require a transaction.');
    if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new AnnouncementEditConflict('Reopen this announcement before saving. Your entered text is kept below.',409);
    $query=$db->prepare('SELECT * FROM announcements WHERE announcement_id=? FOR UPDATE');$query->execute([$id]);$record=$query->fetch(PDO::FETCH_ASSOC);
    if(!$record)throw new AnnouncementEditConflict('This announcement is no longer available. Your entered text is kept below.',409);
    $read=static function(string $sql)use($db,$id):array{$query=$db->prepare($sql);$query->execute([$id]);return $query->fetchAll(PDO::FETCH_ASSOC);};
    $record['category_ids']=!empty($record['category_id'])?[(int)$record['category_id']]:[];
    if(announcementCategoriesReady($db)){
        $extra=$read('SELECT category_id FROM announcement_categories WHERE announcement_id=? ORDER BY category_id FOR UPDATE');
        $record['category_ids']=array_values(array_unique(array_merge($record['category_ids'],array_column($extra,'category_id'))));
    }
    try{$gallery=$read('SELECT image_id,media_id,display_order FROM announcement_images WHERE announcement_id=? ORDER BY display_order,image_id FOR UPDATE');}
    catch(PDOException $error){if($error->getCode()!=='42S02')throw $error;$gallery=[];}
    $audience=['dept_ids'=>array_column($read('SELECT dept_id FROM announcement_departments WHERE announcement_id=? ORDER BY dept_id FOR UPDATE'),'dept_id'),
        'programs'=>array_column($read('SELECT program_code FROM announcement_programs WHERE announcement_id=? ORDER BY program_code FOR UPDATE'),'program_code')];
    if(!$audience['dept_ids']&&!empty($record['dept_id']))$audience['dept_ids']=[$record['dept_id']];
    if(!$audience['programs']&&!empty($record['program'])&&$record['program']!=='ALL')$audience['programs']=[$record['program']];
    if(!hash_equals(announcementEditVersion($record,$gallery,$audience),$expected))throw new AnnouncementEditConflict('This announcement changed in another tab or editor. Your changes were not saved. Your entered text is kept below; copy it, reopen the latest announcement, and reconcile your changes before saving.',409);
}
