<?php
/** Personal avatars use existing settings storage; keys are always derived from identity. */
function profilePictureUrls(array $userIds): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$userIds),fn($id)=>$id>0)));
    if(!$ids)return [];
    $keys=array_map(fn($id)=>'user_profile_picture_'.$id,$ids);$pictures=[];
    try{
        $q=getDB()->prepare('SELECT setting_key,setting_value FROM admin_settings WHERE setting_key IN ('.implode(',',array_fill(0,count($keys),'?')).')');$q->execute($keys);
        foreach($q->fetchAll(PDO::FETCH_KEY_PAIR) as $key=>$value){
            if(!is_string($value)||strlen($value)>64000)continue;
            $bytes=base64_decode($value,true);
            if($bytes===false||substr($bytes,0,3)!=="\xff\xd8\xff")continue;
            $pictures[(int)substr($key,strlen('user_profile_picture_'))]='data:image/jpeg;base64,'.$value;
        }
    }catch(PDOException $e){}
    return $pictures;
}
function profilePictureUrl(int $userId): ?string {
    try{
        $q=getDB()->prepare('SELECT setting_value FROM admin_settings WHERE setting_key=?');
        $q->execute(['user_profile_picture_'.$userId]);$value=$q->fetchColumn();
        if(!is_string($value)||strlen($value)>64000)return null;
        $bytes=base64_decode($value,true);
        if($bytes===false||substr($bytes,0,3)!=="\xff\xd8\xff")return null;
        return 'data:image/jpeg;base64,'.$value;
    }catch(PDOException $e){return null;}
}

function profilePictureEncode(string $path,array $frame=[]): string {
    $frame=imageFramingNormalize($frame);
    $info=@getimagesize($path);
    if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true))throw new InvalidArgumentException('Choose a valid JPG, PNG or WebP image.');
    if($info[0]>10000||$info[1]>10000||$info[0]*$info[1]>20000000)throw new InvalidArgumentException('Use an image with no more than 20 million pixels and 10,000 pixels on either side.');
    if(class_exists('Imagick')){
        $image=new Imagick();
        try{
            $image->readImage($path.'[0]');$image->autoOrient();
            $side=min($image->getImageWidth(),$image->getImageHeight())/$frame['zoom'];
            $image->cropImage(max(1,(int)round($side)),max(1,(int)round($side)),(int)round(($image->getImageWidth()-$side)*$frame['x']/100),(int)round(($image->getImageHeight()-$side)*$frame['y']/100));
            $image->setImagePage(0,0,0,0);
            $image->thumbnailImage(256,256,true);
            $image->setImageBackgroundColor('white');$flat=$image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            try{$flat->stripImage();$flat->setImageFormat('jpeg');$flat->setImageCompressionQuality(82);$bytes=$flat->getImageBlob();}finally{$flat->clear();}
        }finally{$image->clear();}
    }elseif(function_exists('imagecreatetruecolor')){
        $loader=[IMAGETYPE_JPEG=>'imagecreatefromjpeg',IMAGETYPE_PNG=>'imagecreatefrompng',IMAGETYPE_WEBP=>'imagecreatefromwebp'][$info[2]];
        if(!function_exists($loader))throw new RuntimeException('This image format is unavailable on this server. Try a JPG or PNG.');
        $source=@$loader($path);if(!$source)throw new InvalidArgumentException('The image could not be read. Choose another image.');
        $side=min($info[0],$info[1])/$frame['zoom'];$width=$height=max(1,min(256,(int)round($side)));
        $canvas=imagecreatetruecolor($width,$height);
        try{
            imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
            imagecopyresampled($canvas,$source,0,0,(int)round(($info[0]-$side)*$frame['x']/100),(int)round(($info[1]-$side)*$frame['y']/100),$width,$height,max(1,(int)round($side)),max(1,(int)round($side)));
            ob_start();imagejpeg($canvas,null,82);$bytes=ob_get_clean();
        }finally{imagedestroy($source);imagedestroy($canvas);}
    }else throw new RuntimeException('Profile pictures require an image processor on the server. Contact your administrator.');
    $encoded=base64_encode($bytes);
    if(strlen($encoded)>64000)throw new InvalidArgumentException('This image could not be reduced enough. Choose a simpler or smaller image.');
    return $encoded;
}

function saveOwnProfilePicture(string $action,?array $file): void {
    if(!isLoggedIn()||!empty($_SESSION['must_change_password'])||!in_array(rbacRole(),['Super Admin','Content Editor','The Mirror'],true))throw new RuntimeException('Sign in to update your profile picture.');
    if(!in_array($action,['upload','remove'],true))throw new InvalidArgumentException('Choose a valid profile action.');
    $value=null;
    if($action==='upload'){
        if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('Choose a JPG, PNG or WebP image up to 5 MB.');
        $path=$file['tmp_name']??null;
        if(!is_string($path)||!is_uploaded_file($path))throw new InvalidArgumentException('The uploaded image was not received. Please choose it again.');
        if(filesize($path)>5*1024*1024)throw new InvalidArgumentException('Choose an image up to 5 MB.');
        $frame=[];
        if(isset($_POST['image_framing'])){
            if(!is_string($_POST['image_framing'])||strlen($_POST['image_framing'])>65536)throw new InvalidArgumentException('Invalid image position. Choose your picture again.');
            $metadata=json_decode($_POST['image_framing'],true,16);
            if(!is_array($metadata))throw new InvalidArgumentException('Invalid image position. Choose your picture again.');
            $frame=$metadata[hash_file('sha256',$path)]??[];
            if(!is_array($frame))throw new InvalidArgumentException('Invalid image position. Choose your picture again.');
        }
        $value=profilePictureEncode($path,$frame);
    }
    $db=getDB();$db->beginTransaction();
    try{
        $key='user_profile_picture_'.(int)$_SESSION['user_id'];
        if($value===null)$db->prepare('DELETE FROM admin_settings WHERE setting_key=?')->execute([$key]);
        else $db->prepare('INSERT INTO admin_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
        if(!logActivity((int)$_SESSION['user_id'],'UPDATE','users',(int)$_SESSION['user_id'],$value===null?'Removed own profile picture.':'Updated own profile picture.'))throw new RuntimeException('The profile change could not be recorded. Please try again.');
        $db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
