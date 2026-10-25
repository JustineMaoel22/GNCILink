<?php
require_once __DIR__.'/content-publish-policy.php';

const FAQ_TEXT_LIMITS=['title'=>255,'intro'=>2000,'empty_title'=>255,'empty_description'=>2000];

function faqPageContent(PDO $db,bool $lock=false):?array {
    $suffix=$lock&&$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $meta=$db->query('SELECT content FROM faq_page WHERE page_id=1'.$suffix)->fetchColumn();
    if($meta===false)return null;
    $texts=json_decode($meta,true,16,JSON_THROW_ON_ERROR);
    $rows=$db->query('SELECT faq_id,question,answer FROM faqs ORDER BY display_order,faq_id'.$suffix)->fetchAll(PDO::FETCH_ASSOC);
    return ['texts'=>$texts,'items'=>array_map(static fn($row)=>['id'=>'faq-'.$row['faq_id'],'question'=>$row['question'],'answer'=>$row['answer']],$rows)];
}

function faqPageRevision(array $content):string {
    return hash('sha256',json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function faqValidate(array $input,array $current):array {
    if(!is_array($input['texts']??null)||!is_array($input['items']??null)||!array_is_list($input['items'])||count($input['items'])>200)throw new InvalidArgumentException('Use up to 200 FAQs.');
    $texts=[];
    foreach(FAQ_TEXT_LIMITS as $key=>$max){
        $value=$input['texts'][$key]??null;
        if(!is_string($value)||mb_strlen($value)>$max)throw new InvalidArgumentException('Check the FAQ page headings and introductions.');
        $texts[$key]=trim($value);
    }
    $known=array_column($current['items'],null,'id');$seen=[];$items=[];
    foreach($input['items'] as $row){
        if(!is_array($row))throw new InvalidArgumentException('Invalid FAQ.');
        $id=$row['id']??null;
        if(!is_string($id)||isset($seen[$id])||(!isset($known[$id])&&!preg_match('/\Afaq-new-[a-z0-9-]{1,64}\z/D',$id)))throw new InvalidArgumentException('An FAQ changed or its ID is invalid. Reload before applying your changes.');
        $seen[$id]=true;$item=['id'=>$id];
        foreach(['question'=>1000,'answer'=>20000] as $key=>$max){
            $value=$row[$key]??null;
            if(!is_string($value)||mb_strlen($value)>$max||(!contentPublishAllowsIncomplete()&&trim($value)===''))throw new InvalidArgumentException('Each FAQ needs a question and answer within the allowed lengths.');
            $item[$key]=trim($value);
        }
        $items[]=$item;
    }
    return ['texts'=>$texts,'items'=>$items];
}

/** The faqs table contains published content only. Private drafts use the
 * existing Content Editor draft store, including staged deletions and order. */
function faqPublish(PDO $db,array $input,string $revision,int $userId):array {
    $db->beginTransaction();
    try{
        $current=faqPageContent($db,true);
        if(!$current)throw new RuntimeException('Install FAQ storage first.');
        if(!hash_equals(faqPageRevision($current),$revision))throw new InvalidArgumentException('The FAQs changed in another editor. Your draft is safe. Reload the published page before applying your changes.');
        $content=faqValidate($input,$current);$kept=[];
        $update=$db->prepare('UPDATE faqs SET question=?,answer=?,display_order=? WHERE faq_id=?');
        $insert=$db->prepare('INSERT INTO faqs(question,answer,display_order,created_by) VALUES(?,?,?,?)');
        foreach($content['items'] as $order=>$row){
            if(str_starts_with($row['id'],'faq-new-')){
                $insert->execute([$row['question'],$row['answer'],$order,$userId]);$kept[]=(int)$db->lastInsertId();
            }else{
                $id=(int)substr($row['id'],4);$update->execute([$row['question'],$row['answer'],$order,$id]);$kept[]=$id;
            }
        }
        $delete=$db->prepare('DELETE FROM faqs WHERE faq_id=?');
        foreach($current['items'] as $row){$id=(int)substr($row['id'],4);if(!in_array($id,$kept,true))$delete->execute([$id]);}
        $db->prepare('UPDATE faq_page SET content=?,updated_at=CURRENT_TIMESTAMP WHERE page_id=1')->execute([json_encode($content['texts'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $saved=faqPageContent($db);$db->commit();return $saved;
    }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
}
