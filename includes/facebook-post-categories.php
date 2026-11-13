<?php
/** Manual topic tags extend the legacy primary category; automatic posts keep their classifier. */
function facebookPostCategoriesReady(PDO $db): bool {
    try{$db->query('SELECT fb_post_id FROM fb_post_categories LIMIT 0');return true;}
    catch(PDOException $error){if($error->getCode()==='42S02')return false;throw $error;}
}
/** Public News categories use string codes while cached Facebook tags use numeric IDs. */
function facebookPostNewsCategoryKeys(array $names,array $newsLabels): array {
    $keys=[];
    foreach($names as $name){
        if(isset($newsLabels[$name]))$keys[]=$name;
        foreach($newsLabels as $code=>$label)if($label===$name)$keys[]=$code;
    }
    return array_values(array_unique($keys));
}
function facebookPostCategoryCondition(PDO $db,string $alias='f',bool $byName=false): string {
    if(!preg_match('/^[a-z][a-z0-9_]*$/D',$alias))throw new InvalidArgumentException('Invalid category alias.');
    if(!$byName)return facebookPostCategoriesReady($db)
        ? "? IN (SELECT $alias.category_id UNION ALL SELECT fc.category_id FROM fb_post_categories fc WHERE fc.fb_post_id=$alias.fb_post_id AND $alias.category_locked=1)"
        : "$alias.category_id=?";
    $extra=facebookPostCategoriesReady($db)?" OR ($alias.category_locked=1 AND EXISTS(SELECT 1 FROM fb_post_categories fc WHERE fc.fb_post_id=$alias.fb_post_id AND fc.category_id=matched_category.category_id))":'';
    return "EXISTS(SELECT 1 FROM categories matched_category WHERE matched_category.".($byName?'category_name':'category_id')."=? AND (matched_category.category_id=$alias.category_id$extra))";
}
function attachFacebookPostCategories(PDO $db,array $rows): array {
    $ids=array_column($rows,'fb_post_id');$extra=[];
    if($ids&&facebookPostCategoriesReady($db)){
        $q=$db->prepare('SELECT fc.fb_post_id,c.category_id,c.category_name FROM fb_post_categories fc JOIN categories c ON c.category_id=fc.category_id WHERE fc.fb_post_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY c.category_name,c.category_id');$q->execute($ids);
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $tag)$extra[$tag['fb_post_id']][(int)$tag['category_id']]=$tag['category_name'];
    }
    foreach($rows as &$row){
        $tags=[];if(!empty($row['category_id']))$tags[(int)$row['category_id']]=$row['category_name']??'';
        if(!empty($row['category_locked']))$tags+=$extra[$row['fb_post_id']]??[];
        $row['category_ids']=array_keys($tags);$row['category_names']=array_values($tags);$row['category_name']=implode(', ',array_filter($row['category_names'],'strlen'));
    }unset($row);return $rows;
}
function setFacebookPostCategories(string $id,string $mode,$values=[]): bool {
    if(!in_array($mode,['auto','manual'],true))throw new InvalidArgumentException('Choose automatic or manual categories.');
    if(!is_array($values)||count($values)>100)throw new InvalidArgumentException('Choose up to 100 valid categories.');
    $ids=[];
    foreach($values as $value){if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value))throw new InvalidArgumentException('Choose valid categories.');$ids[]=(int)$value;}
    $ids=array_values(array_unique($ids));$db=getDB();$ready=facebookPostCategoriesReady($db);
    if($mode==='manual'&&count($ids)>1&&!$ready)throw new InvalidArgumentException('Install database/migrate-facebook-post-categories.php before choosing multiple categories.');
    if($ids){$q=$db->prepare('SELECT COUNT(*) FROM categories WHERE category_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);if((int)$q->fetchColumn()!==count($ids))throw new InvalidArgumentException('A selected category no longer exists. Reload and try again.');}
    $owns=!$db->inTransaction();if($owns)$db->beginTransaction();
    try{
        $q=$db->prepare('SELECT message FROM fb_posts_cache WHERE fb_post_id=? FOR UPDATE');$q->execute([$id]);$message=$q->fetchColumn();if($message===false)throw new InvalidArgumentException('This post is no longer available.');
        if($mode==='auto'){require_once __DIR__.'/../config/fb-categorizer.php';$primary=gncCategoryIdForMessage((string)$message,gncLoadCategoryMap($db));$ids=[];}else $primary=$ids[0]??null;
        $db->prepare('UPDATE fb_posts_cache SET category_id=?,category_locked=? WHERE fb_post_id=?')->execute([$primary,$mode==='manual'?1:0,$id]);
        if($ready){$db->prepare('DELETE FROM fb_post_categories WHERE fb_post_id=?')->execute([$id]);$insert=$db->prepare('INSERT INTO fb_post_categories(fb_post_id,category_id) VALUES(?,?)');foreach($ids as $category)$insert->execute([$id,$category]);}
        logActivity((int)$_SESSION['user_id'],'UPDATE','fb_posts_cache',null,'Set Facebook post categories ('.$mode.'): '.$id);
        if($owns)$db->commit();return true;
    }catch(Throwable $error){if($owns&&$db->inTransaction())$db->rollBack();throw $error;}
}
