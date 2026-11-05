<?php
/** CLI maintenance only: preview by default, --apply keeps backups and optimizes local staff photos. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';

$apply = in_array('--apply', $argv, true);
if (!class_exists('Imagick')) { fwrite(STDERR, "Enable PHP Imagick before running the optimizer.\n"); exit(1); }
$root = realpath(__DIR__ . '/../uploads/administration');
if (!$root) { fwrite(STDERR, "Administration upload folder does not exist.\n"); exit(1); }
$backupFolder = __DIR__ . '/../config/image-originals';
$update = $apply ? getDB()->prepare('UPDATE media_library SET file_size = ? WHERE file_path = ?') : null;
$count = 0; $before = 0; $after = 0; $failed = 0;
foreach (new DirectoryIterator($root) as $entry) {
    if (!$entry->isFile() || $entry->isLink() || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $entry->getFilename())) continue;
    $path = $entry->getPathname();
    $info = @getimagesize($path);
    if (!$info || ($info[0] <= IMAGE_UPLOAD_MAX_WIDTH && $info[1] <= IMAGE_UPLOAD_MAX_HEIGHT && $entry->getSize() <= IMAGE_UPLOAD_MAX_BYTES)) continue;
    $count++;
    if (!$apply) {
        echo $entry->getFilename() . ': ' . $info[0] . 'x' . $info[1] . ', ' . round($entry->getSize() / 1024) . " KB\n";
        continue;
    }
    $temporary = null;
    try {
        // Never change a source until a complete optimized file and a verified backup exist.
        if (!is_dir($backupFolder) && !mkdir($backupFolder, 0700, true)) throw new RuntimeException('Cannot create the backup folder.');
        $originalHash = hash_file('sha256', $path);
        $backup = $backupFolder . '/' . $originalHash . '.' . strtolower($entry->getExtension());
        if (!is_file($backup) && !copy($path, $backup)) throw new RuntimeException('Cannot back up the source image.');
        if (hash_file('sha256', $backup) !== $originalHash) throw new RuntimeException('Backup verification failed.');
        $temporary = tempnam($root, 'opt-');
        if ($temporary === false || !copy($path, $temporary)) throw new RuntimeException('Cannot prepare the image.');
        imageUploadOptimizePath($temporary);
        clearstatcache(true, $temporary);
        $outputInfo = getimagesize($temporary);
        if (!$outputInfo || $outputInfo[0] > IMAGE_UPLOAD_MAX_WIDTH || $outputInfo[1] > IMAGE_UPLOAD_MAX_HEIGHT) throw new RuntimeException('Optimized image exceeds the dimension limit.');
        $oldSize = filesize($path); $newSize = filesize($temporary);
        // Record a reversible mapping before changing the file. Originals stay outside public uploads.
        $record = json_encode(['path' => '/uploads/administration/' . $entry->getFilename(), 'backup' => basename($backup), 'sha256' => $originalHash], JSON_UNESCAPED_SLASHES) . "\n";
        if (file_put_contents($backupFolder . '/manifest.jsonl', $record, FILE_APPEND | LOCK_EX) !== strlen($record)) throw new RuntimeException('Cannot record the backup.');
        chmod($temporary, fileperms($path) & 0777);
        if (!rename($temporary, $path)) throw new RuntimeException('Cannot replace the image.');
        $temporary = null;
        $update->execute([$newSize, '/uploads/administration/' . $entry->getFilename()]);
        $before += $oldSize; $after += $newSize;
        echo $entry->getFilename() . ': ' . round($oldSize / 1024) . ' -> ' . round($newSize / 1024) . " KB\n";
    } catch (Throwable $error) {
        $failed++;
        fwrite(STDERR, $entry->getFilename() . ': ' . $error->getMessage() . "\n");
    } finally {
        if (is_string($temporary) && is_file($temporary)) unlink($temporary);
    }
}
echo json_encode(['mode' => $apply ? 'apply' : 'preview', 'candidates' => $count, 'failed' => $failed, 'before_bytes' => $before, 'after_bytes' => $after]), "\n";
exit($failed ? 1 : 0);
