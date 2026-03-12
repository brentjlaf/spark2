<?php
// File: delete_folder.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$folder = sanitize_text($_POST['folder'] ?? '');
if ($folder === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder']);
    exit;
}

$root = dirname(__DIR__, 2);
$base = basename($folder);
$dir = $root . '/uploads/' . $base;

if (strtolower($base) === 'general') {
    echo json_encode(['status' => 'error', 'message' => 'The General folder cannot be deleted.']);
    exit;
}
if (!is_dir($dir)) {
    echo json_encode(['status' => 'error', 'message' => 'Folder not found']);
    exit;
}

$mediaFile = $root . '/data/media.json';
$media = read_json_file($mediaFile);
$new = [];
foreach ($media as $m) {
    if ($m['folder'] === $folder) {
        $file = $root . '/' . $m['file'];
        if (is_file($file)) @unlink($file);
        if (!empty($m['thumbnail'])) {
            $thumb = $root . '/' . $m['thumbnail'];
            if (is_file($thumb)) @unlink($thumb);
        }
    } else {
        $new[] = $m;
    }
}
write_json_file($mediaFile, array_values($new));

// remove directory recursively
$iterator = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
$success = true;
foreach ($files as $file) {
    if ($file->isDir()) {
        if (!@rmdir($file->getPathname())) {
            $success = false;
        }
    } else {
        if (!@unlink($file->getPathname())) {
            $success = false;
        }
    }
}
if (!@rmdir($dir)) {
    $success = false;
}

if (!$success) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete folder']);
    exit;
}

echo json_encode(['status' => 'success']);

