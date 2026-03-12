<?php
// File: rename_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);

$id = sanitize_text($_POST['id'] ?? '');
$newName = sanitize_text($_POST['name'] ?? '');
if ($id === '' || $newName === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}
$root = dirname(__DIR__, 2);
$found = false;
foreach ($media as &$item) {
    if ($item['id'] === $id) {
        $oldPath = $root . '/' . $item['file'];
        $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($newName, PATHINFO_FILENAME)) . '.' . $ext;
        $newRel = dirname($item['file']) . '/' . $safe;
        $newPath = $root . '/' . $newRel;

        if (!file_exists($oldPath)) {
            echo json_encode(['status' => 'error', 'message' => 'File not found']);
            exit;
        }
        if (file_exists($newPath)) {
            echo json_encode(['status' => 'error', 'message' => 'File already exists']);
            exit;
        }
        if (!rename($oldPath, $newPath)) {
            echo json_encode(['status' => 'error', 'message' => 'Rename failed']);
            exit;
        }

        @touch($newPath);
        if (!empty($item['thumbnail'])) {
            $thumbOld = $root . '/' . $item['thumbnail'];
            $thumbNewRel = dirname($item['thumbnail']) . '/' . $safe;
            $thumbNew = $root . '/' . $thumbNewRel;
            @rename($thumbOld, $thumbNew);
            @touch($thumbNew);
            $item['thumbnail'] = $thumbNewRel;
        }
        $item['name'] = $safe;
        $item['file'] = $newRel;
        $found = true;
        break;
    }
}

if (empty($found)) {
    echo json_encode(['status' => 'error', 'message' => 'File not found']);
    exit;
}

write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success']);
