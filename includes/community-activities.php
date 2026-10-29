<?php
require_once __DIR__.'/image-framing.php';
require_once __DIR__.'/content-publish-policy.php';

const COMMUNITY_ACTIVITY_FIELDS = [
    'hero_title'=>255, 'hero_description'=>2000, 'heading_first'=>120,
    'heading_accent'=>120, 'heading_last'=>120, 'intro'=>3000,
    'list_heading'=>120, 'empty_title'=>255, 'empty_description'=>2000,
    'impact_heading'=>255, 'impact_empty'=>2000,
    'closing_title'=>255, 'closing_description'=>2000,
];
const COMMUNITY_IMPACT_TEXT_DEFAULTS = ['impact_heading'=>'Community Impact','impact_empty'=>'No community extension activity news has been published yet.',
    'closing_title'=>'Join the Mission','closing_description'=>'Together, we create brighter, stronger communities.'];
const COMMUNITY_CLOSING_IMAGE_DEFAULT = '/assets/images/DSC_2965.JPG';

/** Only fixed, actionable messages reach the authenticated editor; SQL and paths stay private. */
function communityActivityPublishError(Throwable $error): string {
    if($error instanceof Error){
        if(str_contains($error->getMessage(),'undefined function array_is_list('))return 'Publishing requires PHP 8.1 or newer. Update the PHP version for this website in your hosting panel.';
        if(str_contains($error->getMessage(),'undefined function mb_strlen('))return 'Publishing requires the PHP mbstring extension. Enable mbstring for this website in your hosting panel.';
        if($error->getMessage()==='Class "finfo" not found')return 'Image uploads require the PHP fileinfo extension. Enable fileinfo for this website in your hosting panel.';
    }
    if($error instanceof PDOException){
        if($error->getCode()==='42S02')return 'A required database table is missing. Install the community activities and image framing migrations in the database used by this website.';
        if($error->getCode()==='42S22')return 'The hosted database has an outdated table structure. Check the community activities and image framing table columns against their migrations.';
        if(in_array((int)($error->errorInfo[1]??0),[1044,1045,1142,1143],true))return 'The website database account lacks permission for this operation. Ask your host to check its database access.';
    }
    if($error instanceof RuntimeException){
        if($error->getMessage()==='Install the community activities migration.')return 'The community page record is missing. Import the community activities migration into the database used by this website.';
        if(in_array($error->getMessage(),['Image storage is unavailable.','Could not store the image.'],true))return 'The server could not save the uploaded image. Ask your host to check write access and available space for uploads/community-activities.';
    }
    return 'Could not publish the page. Your edits are available to retry. The server encountered an unexpected error; ask your host for the PHP log entry beginning "Community activities publish:".';
}

/** Preserve the first existing photo when opening older page records. Once
 * saved, the shared image is independent of activity order and deletion. */
function communityActivityShowcaseImage(array $content): string {
    if(array_key_exists('showcase_image',$content))return (string)$content['showcase_image'];
    foreach($content['activities']??[] as $row)if(!empty($row['image']))return (string)$row['image'];
    return '';
}

function communityActivityContent(?PDO $db = null): ?array {
    try {
        $raw=($db??getDB())->query('SELECT content FROM community_activity_page WHERE page_id=1')->fetchColumn();
        return $raw===false?null:json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    } catch(PDOException $e) {
        if($e->getCode()==='42S02')return null;
        throw $e;
    }
}

function communityActivityRevision(array $content): string {
    return hash('sha256',json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function communityActivityValidate(array $input,array $current): array {
    if(!isset($input['texts'],$input['activities']) || !is_array($input['texts']) || !is_array($input['activities']) || !array_is_list($input['activities']) || count($input['activities'])>100)throw new InvalidArgumentException('Use up to 100 activities.');
    $texts=[];
    foreach(COMMUNITY_ACTIVITY_FIELDS as $key=>$limit){
        $value=array_key_exists($key,$input['texts'])?$input['texts'][$key]
            :(isset(COMMUNITY_IMPACT_TEXT_DEFAULTS[$key])?($current['texts'][$key]??COMMUNITY_IMPACT_TEXT_DEFAULTS[$key]):null);
        if(!is_string($value)||mb_strlen(trim($value))>$limit)throw new InvalidArgumentException('Check the page headings and introductions.');
        $texts[$key]=trim($value);
    }
    if(($texts['hero_title']===''||$texts['empty_title']==='')&&!contentPublishAllowsIncomplete())throw new InvalidArgumentException('Enter a page title and an empty-state heading.');
    $known=array_column($current['activities'],null,'id');$activities=[];$seen=[];
    foreach($input['activities'] as $row){
        if(!is_array($row))throw new InvalidArgumentException('Invalid activity.');
        $id=$row['id']??null;
        if(!is_string($id)||!preg_match('/\Aactivity-[a-z0-9-]{1,64}\z/D',$id)||isset($seen[$id]))throw new InvalidArgumentException('Invalid or repeated activity.');
        $seen[$id]=true;$item=['id'=>$id];
        foreach(['title'=>255,'description'=>12000,'category'=>100] as $key=>$limit){
            $value=$row[$key]??null;
            if(!is_string($value)||(!contentPublishAllowsIncomplete()&&trim($value)==='')||mb_strlen(trim($value))>$limit)throw new InvalidArgumentException('Each activity needs a title, description and category within the allowed lengths.');
            $item[$key]=trim($value);
        }
        // Posted URLs never become stored image paths.
        $item['image']=!empty($row['remove_image'])?'':($known[$id]['image']??'');
        $activities[]=$item;
    }
    return ['texts'=>$texts,'hero_image'=>$current['hero_image'],'showcase_image'=>communityActivityShowcaseImage($current),'closing_image'=>$current['closing_image']??COMMUNITY_CLOSING_IMAGE_DEFAULT,'activities'=>$activities];
}

function communityActivityUpload(array $file): string {
    $file=imageUploadPrepare($file);
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name']))throw new InvalidArgumentException('The image upload failed. Please try again.');
    $size=filesize($file['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    if(!$size||$size>5*1024*1024||!isset($types[$mime])||!getimagesize($file['tmp_name']))throw new InvalidArgumentException('Choose a JPG, PNG, WebP or GIF image of up to 5 MB.');
    $directory=dirname(__DIR__).'/uploads/community-activities';
    if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new RuntimeException('Image storage is unavailable.');
    $path='/uploads/community-activities/'.bin2hex(random_bytes(16)).'.'.$types[$mime];
    if(!move_uploaded_file($file['tmp_name'],dirname(__DIR__).$path))throw new RuntimeException('Could not store the image.');
    try{imageFramingSaveUpload($path,dirname(__DIR__).$path);}catch(Throwable $e){unlink(dirname(__DIR__).$path);throw $e;}
    return $path;
}

function communityActivitySave(PDO $db,array $input,array $files,string $revision): array {
    $created=[];$db->beginTransaction();
    try{
        $raw=$db->query('SELECT content FROM community_activity_page WHERE page_id=1 FOR UPDATE')->fetchColumn();
        if($raw===false)throw new RuntimeException('Install the community activities migration.');
        $current=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals(communityActivityRevision($current),$revision))throw new InvalidArgumentException('This page changed in another editor. Your draft is safe. Reload the published page before applying your changes.');
        $content=communityActivityValidate($input,$current);
        $allowed=['hero_image','closing_image','showcase_image'];foreach($content['activities'] as $row)$allowed[]='image_'.$row['id'];
        foreach($files as $key=>$file)if(!in_array($key,$allowed,true))throw new InvalidArgumentException('An uploaded image belongs to an activity that is no longer on the page.');
        if(isset($files['hero_image'])){$path=communityActivityUpload($files['hero_image']);$created[]=$path;$content['hero_image']=$path;}
        if(isset($files['closing_image'])){$path=communityActivityUpload($files['closing_image']);$created[]=$path;$content['closing_image']=$path;}
        if(isset($files['showcase_image'])){$path=communityActivityUpload($files['showcase_image']);$created[]=$path;$content['showcase_image']=$path;}
        $legacyUpload=false;
        foreach($content['activities'] as &$row){
            $key='image_'.$row['id'];
            if(isset($files[$key])){$path=communityActivityUpload($files[$key]);$created[]=$path;$row['image']=$path;$legacyUpload=true;}
        }unset($row);
        if($legacyUpload&&!array_key_exists('showcase_image',$current)&&!isset($files['showcase_image']))$content['showcase_image']=communityActivityShowcaseImage(['activities'=>$content['activities']]);
        $db->prepare('UPDATE community_activity_page SET content=?,updated_at=CURRENT_TIMESTAMP WHERE page_id=1')->execute([json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);
        $db->commit();return $content;
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        foreach($created as $path)if(is_file(dirname(__DIR__).$path))unlink(dirname(__DIR__).$path);
        throw $e;
    }
}
