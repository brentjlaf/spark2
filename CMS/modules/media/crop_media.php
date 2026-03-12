<?php
// File: crop_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);

$id = sanitize_text($_POST['id'] ?? '');
$imageData = $_POST['image'] ?? '';
$newVersion = isset($_POST['new_version']) && $_POST['new_version'] == '1';
$format = sanitize_text($_POST['format'] ?? 'jpeg');

if ($id === '' || strpos($imageData, 'data:image') !== 0) {
    echo json_encode(['status' => 'error']);
    exit;
}

$root = dirname(__DIR__, 2);
$index = null;
foreach ($media as $i => $m) {
    if ($m['id'] === $id) { $index = $i; break; }
}
if ($index === null) {
    echo json_encode(['status' => 'error']);
    exit;
}

$entry = $media[$index];
$folder = $entry['folder'];
$baseDir = $root . '/uploads' . ($folder ? '/' . basename($folder) : '');
if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

$extMap = ['jpeg'=>'jpg','jpg'=>'jpg','png'=>'png','webp'=>'webp'];
$ext = $extMap[$format] ?? 'jpg';

$binary = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $imageData));

if ($newVersion) {
    $base = pathinfo($entry['name'], PATHINFO_FILENAME);
    $filename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9._-]/','_', $base) . '.' . $ext;
    $filePath = $baseDir . '/' . $filename;
    file_put_contents($filePath, $binary);
    $thumbDir = $baseDir . '/thumbs';
    if (!is_dir($thumbDir)) mkdir($thumbDir, 0777, true);
    $thumbPath = $thumbDir . '/' . $filename;
    create_thumbnail($filePath, $thumbPath, 200);
    $media[] = [
        'id' => uniqid(),
        'name' => $filename,
        'file' => str_replace($root . '/', '', $filePath),
        'folder' => $entry['folder'],
        'size' => filesize($filePath),
        'type' => 'images',
        'uploaded_at' => time(),
        'thumbnail' => str_replace($root . '/', '', $thumbPath),
        'tags' => $entry['tags'] ?? [],
        'order' => count($media)
    ];
} else {
    $oldPath = $root . '/' . $entry['file'];
    $base = pathinfo($entry['name'], PATHINFO_FILENAME);
    $filename = $base . '.' . $ext;
    $filePath = $baseDir . '/' . $filename;
    if ($filePath !== $oldPath && file_exists($oldPath)) {
        @unlink($oldPath);
    }
    file_put_contents($filePath, $binary);
    $entry['name'] = $filename;
    $entry['file'] = str_replace($root . '/', '', $filePath);
    $entry['size'] = filesize($filePath);
    $thumbPath = $entry['thumbnail'] ? $root . '/' . $entry['thumbnail'] : ($baseDir . '/thumbs/' . basename($filePath));
    create_thumbnail($filePath, $thumbPath, 200);
    $entry['thumbnail'] = str_replace($root . '/', '', $thumbPath);
    $media[$index] = $entry;
}

write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success']);

function create_thumbnail($src, $dest, $maxWidth) {
    $info = @getimagesize($src);
    if (!$info) return;
    list($width, $height, $type) = $info;
    $ratio = $maxWidth / $width;
    $newW = $maxWidth;
    $newH = (int)($height * $ratio);
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
        default: return;
    }
    if (!$img) return;
    $thumb = imagecreatetruecolor($newW, $newH);
    imagecopyresampled($thumb, $img, 0,0,0,0,$newW,$newH,$width,$height);
    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($thumb, $dest, 80); break;
        case IMAGETYPE_PNG:  imagepng($thumb, $dest); break;
        case IMAGETYPE_GIF:  imagegif($thumb, $dest); break;
        case IMAGETYPE_WEBP: imagewebp($thumb, $dest); break;
    }
    imagedestroy($img);
    imagedestroy($thumb);
}
