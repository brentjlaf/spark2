<?php
// File: rename_folder.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$old = sanitize_text($_POST['old'] ?? '');
$new = sanitize_text($_POST['new'] ?? '');
if ($old === '' || $new === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder']);
    exit;
}

$root = dirname(__DIR__,2);
$uploads = $root . '/uploads';
$oldBase = basename($old);
$newBase = basename($new);
$oldDir = $uploads . '/' . $oldBase;
$newDir = $uploads . '/' . $newBase;
$normalizedOld = strtolower($oldBase);
$normalizedNew = strtolower($newBase);

if (!is_dir($oldDir)) {
    echo json_encode(['status' => 'error', 'message' => 'Folder not found']);
    exit;
}

if ($oldDir === $newDir) {
    echo json_encode(['status' => 'error', 'message' => 'The folder name is unchanged.']);
    exit;
}

if ($normalizedOld === 'general') {
    echo json_encode(['status' => 'error', 'message' => 'The General folder cannot be renamed.']);
    exit;
}

$existingFolders = glob($uploads . '/*', GLOB_ONLYDIR) ?: [];
$normalizedExisting = array_map(function($path){
    return strtolower(basename($path));
}, $existingFolders);
$normalizedExisting = array_values(array_diff($normalizedExisting, [$normalizedOld]));

if ($normalizedNew === 'general') {
    echo json_encode(['status' => 'error', 'message' => 'Another General folder cannot be created.']);
    exit;
}

if (in_array($normalizedNew, $normalizedExisting, true) || file_exists($newDir)) {
    echo json_encode(['status' => 'error', 'message' => 'A folder with that name already exists.']);
    exit;
}

error_clear_last();
if (!@rename($oldDir, $newDir)) {
    $error = error_get_last();
    $details = $error['message'] ?? 'Rename failed due to an unknown error.';
    $details = preg_replace('/^warning:\s*/i', '', $details);
    if (strpos($details, 'rename(') !== false) {
        $details = preg_replace('/rename\([^)]*\):\s*/i', '', $details);
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Rename failed: ' . $details,
    ]);
    exit;
}

$mediaFile = $root . '/data/media.json';
$media = read_json_file($mediaFile);
foreach ($media as &$m) {
    if ($m['folder'] === $old) {
        $m['folder'] = basename($new);
        $m['file'] = preg_replace('#^uploads/' . preg_quote(basename($old), '#') . '#', 'uploads/' . basename($new), $m['file']);
        if (!empty($m['thumbnail'])) {
            $m['thumbnail'] = preg_replace('#^uploads/' . preg_quote(basename($old), '#') . '#', 'uploads/' . basename($new), $m['thumbnail']);
        }
    }
}
write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success']);
