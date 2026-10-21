<?php
function athleticsSportsDefaults(): array {
    return ['speed'=>1,'images'=>['hero'=>'/assets/images/athletics-and-sports-header.png','showcase'=>'/assets/images/athletic_scholar.png'],'texts'=>[
        'hero_title'=>'Athletics and Sports',
        'hero_description'=>"Celebrate GNC's athletic spirit through sports, teamwork, and school pride. Building character on and off the field.",
        'programs_title'=>'Our Sports Programs',
        'programs_description'=>'Developing talent, discipline, and leadership through a wide range of athletic opportunities.',
        'teams_title'=>'GNCI Varsity Teams',
        'highlights_title'=>'Competition Highlights',
        'facilities_title'=>'Facilities',
        'facebook_kicker'=>'Stay Updated',
        'facebook_title'=>'Follow Us on Facebook',
        'facebook_description'=>'Get the latest announcements, events, and news from GNC Sports Club.',
    ],'sports'=>[
        ['id'=>'basketball','name'=>'Basketball','type'=>'','image'=>''],
        ['id'=>'indoor-volleyball','name'=>'Volleyball','type'=>'Indoor','image'=>''],
        ['id'=>'beach-volleyball','name'=>'Volleyball','type'=>'Beach','image'=>''],
    ],'teams'=>[
        ['id'=>'green-mamba','name'=>'GNC Green Mamba','image'=>''],
        ['id'=>'montessori-falcons','name'=>'Montessori Falcons','image'=>''],
    ],'facilities'=>[
        ['id'=>'covered-court','name'=>'Covered Court','image'=>'/assets/images/Covered Courts.jpg'],
        ['id'=>'classroom','name'=>'Classroom','image'=>'/assets/images/classroom.JPG'],
    ]];
}

function athleticsSportsContent(): array {
    $stmt = null;
    try {
        $stmt=getDB()->prepare("SELECT content FROM basic_education_content WHERE page_key='athletics-sports' AND field_key='page'");
        $stmt->execute();$value=$stmt->fetchColumn();
        if ($value !== false) {
            $content=json_decode($value,true,512,JSON_THROW_ON_ERROR);
            if (isset($content['texts'],$content['sports'])) return athleticsSportsWithImages($content);
        }
    } catch (Throwable $error) {
        if ($error instanceof PDOException && (string)$error->getCode()==='42S02') return athleticsSportsDefaults();
        error_log('Athletics content: '.$error->getMessage());
    }
    return athleticsSportsDefaults();
}

function athleticsSportsWithImages(array $content): array {
    $content['texts']=array_replace(athleticsSportsDefaults()['texts'],$content['texts']);
    $content['teams']=$content['teams']??athleticsSportsDefaults()['teams'];
    $content['facilities']=$content['facilities']??athleticsSportsDefaults()['facilities'];
    $content['speed']=$content['speed']??1;
    $content['images']=array_replace(athleticsSportsDefaults()['images'],$content['images']??[]);
    // Replace the old bundled placeholder photos; retain every uploaded photo.
    foreach ($content['sports'] as &$sport) {
        if (in_array($sport['image']??'',['/assets/images/athletics-and-sports-header.png','/assets/images/athletic_scholar.png'],true)) $sport['image']='';
    }
    unset($sport);
    return $content;
}

function athleticsSportsRevision(array $content): string { return hash('sha256',json_encode(athleticsSportsWithImages($content),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }

function validateAthleticsSports(array $input,array $current): array {
    $speed=$input['speed']??$current['speed']??1;
    if ((!is_int($speed)&&!is_float($speed)) || !is_finite((float)$speed) || $speed<0.25 || $speed>3) throw new InvalidArgumentException('Choose a slideshow speed between 0.25 and 3.');
    if (!isset($input['sports'],$input['texts']) || !is_array($input['sports']) || !array_is_list($input['sports']) || count($input['sports'])>30 || !is_array($input['texts'])) throw new InvalidArgumentException('Use up to 30 sports programs.');
    $texts=[];
    foreach (athleticsSportsDefaults()['texts'] as $key=>$default) {
        $value=$input['texts'][$key]??$current['texts'][$key]??$default;
        if (!is_string($value) || strlen($value)> (str_ends_with($key,'title')?255:3000) || !trim($value)) throw new InvalidArgumentException('Add a heading and description for each section.');
        $texts[$key]=trim($value);
    }
    $known=array_column($current['sports'],null,'id');$sports=[];$seen=[];
    foreach ($input['sports'] as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Invalid sports program.');
        $id=$row['id']??'';$name=$row['name']??'';$type=$row['type']??'';
        if (!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)||isset($seen[$id])) throw new InvalidArgumentException('Invalid or repeated sports program.');
        if (!is_string($name)||!trim($name)||strlen($name)>120||!is_string($type)||strlen($type)>80) throw new InvalidArgumentException('Give each sport a name of up to 120 characters and an optional label of up to 80 characters.');
        $seen[$id]=true;
        // Uploaded paths come from the server; clients cannot substitute arbitrary image URLs.
        $sports[]=['id'=>$id,'name'=>trim($name),'type'=>trim($type),'image'=>$known[$id]['image']??''];
    }
    $teamInput=$input['teams']??athleticsSportsWithImages($current)['teams'];
    if(!is_array($teamInput)||!array_is_list($teamInput)||count($teamInput)>20)throw new InvalidArgumentException('Use up to 20 varsity team logos.');
    $knownTeams=array_column(athleticsSportsWithImages($current)['teams'],null,'id');$teams=[];$seen=[];
    foreach($teamInput as $team){
        if(!is_array($team))throw new InvalidArgumentException('Invalid varsity team.');
        $id=$team['id']??'';$name=$team['name']??'';
        if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)||isset($seen[$id])||!is_string($name)||!trim($name)||strlen($name)>120)throw new InvalidArgumentException('Give each team a unique logo slot and a name of up to 120 characters.');
        $seen[$id]=true;$teams[]=['id'=>$id,'name'=>trim($name),'image'=>$knownTeams[$id]['image']??''];
    }
    $facilityInput=$input['facilities']??athleticsSportsWithImages($current)['facilities'];
    if(!is_array($facilityInput)||!array_is_list($facilityInput)||count($facilityInput)>24)throw new InvalidArgumentException('Use up to 24 facility photos.');
    $knownFacilities=array_column(athleticsSportsWithImages($current)['facilities'],null,'id');$facilities=[];$seen=[];
    foreach($facilityInput as $facility){
        if(!is_array($facility))throw new InvalidArgumentException('Invalid facility.');
        $id=$facility['id']??'';$name=$facility['name']??'';
        if(!is_string($id)||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$id)||isset($seen[$id])||!is_string($name)||!trim($name)||strlen($name)>120)throw new InvalidArgumentException('Give each facility a name of up to 120 characters.');
        $seen[$id]=true;$facilities[]=['id'=>$id,'name'=>trim($name),'image'=>$knownFacilities[$id]['image']??''];
    }
    return ['texts'=>$texts,'sports'=>$sports,'teams'=>$teams,'facilities'=>$facilities,'images'=>athleticsSportsWithImages($current)['images'],'speed'=>$speed];
}

function saveAthleticsSports(array $input,array $files,string $revision): array {
    ensureAcademicContentTable();$db=getDB();$uploads=[];
    try {
        $db->beginTransaction();
        // A singleton row makes concurrent first-time publishes use the same lock.
        $stmt=$db->prepare("INSERT IGNORE INTO basic_education_content (page_key,field_key,content) VALUES ('athletics-sports','page',?)");
        $stmt->execute([json_encode(athleticsSportsDefaults(),JSON_THROW_ON_ERROR)]);
        $stmt=$db->query("SELECT content FROM basic_education_content WHERE page_key='athletics-sports' AND field_key='page' FOR UPDATE");
        $current=athleticsSportsWithImages(json_decode($stmt->fetchColumn(),true,512,JSON_THROW_ON_ERROR));
        if (!hash_equals(athleticsSportsRevision($current),$revision)) throw new InvalidArgumentException('This page changed in another editor. Reload before publishing.');
        $content=validateAthleticsSports($input,$current);$allowedFiles=['background_hero','background_showcase'];
        foreach(['sports'=>'sport_','teams'=>'team_','facilities'=>'facility_'] as $collection=>$prefix)foreach($content[$collection] as $item)$allowedFiles[]=$prefix.$item['id'];
        foreach ($files as $key=>$file) if(!in_array($key,$allowedFiles,true))throw new InvalidArgumentException('Invalid sports image upload.');
        foreach (['hero','showcase'] as $key) {
            $file=$files['background_'.$key]??null;
            if ($file && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                $upload=storeDepartmentUpload($file,'homepage_image');$uploads[]=$upload;$content['images'][$key]=$upload['path'];
            }
        }
        foreach ($content['sports'] as &$sport) {
            $file=$files['sport_'.$sport['id']]??null;
            if ($file && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                $upload=storeDepartmentUpload($file,'homepage_image');$uploads[]=$upload;$sport['image']=$upload['path'];
            }
        }
        unset($sport);
        foreach($content['teams'] as &$team){
            $file=$files['team_'.$team['id']]??null;
            if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                $upload=storeDepartmentUpload($file,'homepage_image');$uploads[]=$upload;$team['image']=$upload['path'];
            }
        }
        unset($team);
        foreach($content['facilities'] as &$facility){
            $file=$files['facility_'.$facility['id']]??null;
            if($file&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                $upload=storeDepartmentUpload($file,'homepage_image');$uploads[]=$upload;$facility['image']=$upload['path'];
            }
        }
        unset($facility);
        $stmt=$db->prepare("UPDATE basic_education_content SET content=? WHERE page_key='athletics-sports' AND field_key='page'");
        $stmt->execute([json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);$db->commit();
        return $content;
    } catch (Throwable $error) {
        if($db->inTransaction())$db->rollBack();foreach($uploads as $upload)discardStoredDepartmentUpload($upload);throw $error;
    }
}
