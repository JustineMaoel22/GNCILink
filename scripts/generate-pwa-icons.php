<?php
/** Resize the existing school seal, without redrawing/changing the logo. CLI only. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!extension_loaded('gd')) throw new RuntimeException('PHP GD is required to regenerate PWA icons.');
$root = dirname(__DIR__);
$svg = file_get_contents($root.'/assets/images/logos/gnc-logo-v1.svg');
if (!preg_match('~data:image/png;base64,([A-Za-z0-9+/=\s]+)~', $svg, $match)) throw new RuntimeException('Expected embedded school seal PNG.');
$logo = imagecreatefromstring(base64_decode($match[1], true));
if (!$logo) throw new RuntimeException('Unable to read the school seal.');
$directory = $root.'/assets/images/pwa';
if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new RuntimeException('Cannot create icon directory.');
foreach ([['icon-192.png',192,.88,false],['icon-512.png',512,.88,false],['maskable-512.png',512,.68,true],['apple-touch-icon.png',180,.88,false]] as [$file,$size,$ratio,$maskable]) {
    $image = imagecreatetruecolor($size,$size);
    $background = $maskable ? imagecolorallocate($image,9,64,36) : imagecolorallocate($image,249,246,241);
    imagefill($image,0,0,$background);
    $edge = (int)round($size*$ratio); $offset = (int)floor(($size-$edge)/2);
    imagecopyresampled($image,$logo,$offset,$offset,0,0,$edge,$edge,imagesx($logo),imagesy($logo));
    if (!imagepng($image,$directory.'/'.$file,9)) throw new RuntimeException('Cannot save icon.');
    imagedestroy($image);
    echo $file.' '.$size.'x'.$size.PHP_EOL;
}
// Separate administrative launcher artwork. The seal itself stays untouched.
foreach ([['admin-icon-192.png',192,false],['admin-icon-512.png',512,false],['admin-maskable-512.png',512,true],['admin-apple-touch-icon.png',180,false]] as [$file,$size,$maskable]) {
    $canvas = imagecreatetruecolor(1024,1024);
    $green = imagecolorallocate($canvas,9,64,36); $gold = imagecolorallocate($canvas,234,186,59);
    imagefill($canvas,0,0,$green);
    $sealSize = $maskable ? 510 : 610;
    $sealTop = $maskable ? 190 : 100;
    imagecopyresampled($canvas,$logo,(int)((1024-$sealSize)/2),$sealTop,0,0,$sealSize,$sealSize,imagesx($logo),imagesy($logo));
    $left=262; $right=762; $top=735; $bottom=851;
    imagefilledrectangle($canvas,$left,$top,$right,$bottom,$gold);
    $font='/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    if (is_readable($font) && function_exists('imagettftext')) {
        $bounds=imagettfbbox(48,0,$font,'ADMIN');
        imagettftext($canvas,48,0,(int)((1024-($bounds[2]-$bounds[0]))/2),816,$green,$font,'ADMIN');
    } else {
        $label=imagecreatetruecolor(45,15);imagefill($label,0,0,imagecolorallocate($label,234,186,59));
        imagestring($label,5,0,0,'ADMIN',imagecolorallocate($label,9,64,36));
        imagecopyresampled($canvas,$label,377,748,0,0,270,90,45,15);imagedestroy($label);
    }
    $image=imagecreatetruecolor($size,$size);
    imagecopyresampled($image,$canvas,0,0,0,0,$size,$size,1024,1024);
    if (!imagepng($image,$directory.'/'.$file,9)) throw new RuntimeException('Cannot save admin icon.');
    imagedestroy($image);imagedestroy($canvas);
    echo $file.' '.$size.'x'.$size.PHP_EOL;
}
imagedestroy($logo);
