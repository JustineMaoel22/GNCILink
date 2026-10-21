<?php
/** Intrinsic dimensions reserve image space before lazy loading begins. */
function gncImageDimensionAttributes(string $url, int $fallbackWidth, int $fallbackHeight): string
{
    static $cache=[];
    if (!isset($cache[$url])) {
        $dimensions=[$fallbackWidth,$fallbackHeight];
        $root=realpath(dirname(__DIR__));
        $path=parse_url($url,PHP_URL_PATH);
        $file=is_string($path)?realpath($root.'/'.ltrim(rawurldecode($path),'/')):false;
        if ($file && str_starts_with($file,$root.DIRECTORY_SEPARATOR) && is_file($file)) {
            $size=@getimagesize($file);
            if ($size && $size[0]>0 && $size[1]>0) $dimensions=[(int)$size[0],(int)$size[1]];
        }
        $cache[$url]=$dimensions;
    }
    return ' width="'.$cache[$url][0].'" height="'.$cache[$url][1].'" style="aspect-ratio: '.$cache[$url][0].' / '.$cache[$url][1].';"';
}
