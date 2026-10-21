<?php
require_once __DIR__ . '/../config/config.php';

/** Display metadata only. These helpers never rewrite the image file. */
function imageFramingNormalize(array $value): array
{
    $result = [];
    foreach (['x' => [0, 100, 50], 'y' => [0, 100, 50], 'zoom' => [1, 4, 1]] as $key => [$min, $max, $default]) {
        $number = $value[$key] ?? $default;
        if ((!is_int($number) && !is_float($number)) || !is_finite((float)$number) || $number < $min || $number > $max) {
            throw new InvalidArgumentException('Image position must be between 0 and 100, and zoom between 1 and 4.');
        }
        $result[$key] = round((float)$number, 4);
    }
    return $result;
}

function imageFramingPath(string $path): ?string
{
    if (!preg_match('#^/(uploads|assets/images)/[^?\x00-\x20<>"\\\\]+\.(jpe?g|png|webp|gif|svg)$#iD', $path) || str_contains($path, '..') || str_contains($path, '%')) return null;
    $file = realpath(dirname(__DIR__) . $path);
    $root = realpath(dirname(__DIR__) . (str_starts_with($path, '/uploads/') ? '/uploads' : '/assets/images'));
    if (!$file || !$root || !is_file($file) || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/')) return null;
    return $path;
}

function imageFramingAll(): array
{
    try {
        $result = [];
        foreach (getDB()->query('SELECT image_path,position_x,position_y,zoom FROM image_display_settings')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['image_path']] = ['x' => (float)$row['position_x'], 'y' => (float)$row['position_y'], 'zoom' => (float)$row['zoom']];
        }
        return $result;
    } catch (PDOException $e) { if ($e->getCode() === '42S02') return []; throw $e; }
}

function imageFramingSave(string $path, array $value, ?int $userId): void
{
    if (!imageFramingPath($path)) throw new InvalidArgumentException('Choose an image stored on this website.');
    $value = imageFramingNormalize($value);
    getDB()->prepare('INSERT INTO image_display_settings (image_path,position_x,position_y,zoom,updated_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE position_x=VALUES(position_x),position_y=VALUES(position_y),zoom=VALUES(zoom),updated_by=VALUES(updated_by)')
        ->execute([$path, $value['x'], $value['y'], $value['zoom'], $userId]);
}

/** Read framing attached to a multipart upload, matched to the original file bytes. */
function imageFramingSaveUpload(string $path, string $absoluteFile): void
{
    if (empty($_POST['image_framing']) || !preg_match('/\.(jpe?g|png|webp|gif)$/i', $path)) return;
    static $metadata = null;
    if ($metadata === null) {
        $json = (string)$_POST['image_framing'];
        if (strlen($json) > 65536) throw new InvalidArgumentException('Too many image display settings.');
        $metadata = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($metadata) || count($metadata) > 100) throw new InvalidArgumentException('Invalid image display settings.');
        foreach ($metadata as $hash => $frame) {
            if (!preg_match('/^[a-f0-9]{64}$/D', (string)$hash) || !is_array($frame)) throw new InvalidArgumentException('Invalid image display settings.');
            imageFramingNormalize($frame);
        }
    }
    $hash = hash_file('sha256', $absoluteFile);
    if (isset($metadata[$hash])) imageFramingSave($path, $metadata[$hash], isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
}
