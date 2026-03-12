<?php
// File: move_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

function move_file_with_fallback(string $from, string $to): bool {
    error_clear_last();
    if (@rename($from, $to)) {
        @touch($to);
        return true;
    }
    error_clear_last();
    if (@copy($from, $to)) {
        @unlink($from);
        @touch($to);
        return true;
    }
    return false;
}

$id = sanitize_text($_POST['id'] ?? '');
$destination = sanitize_text($_POST['folder'] ?? '');

if ($id === '' || $destination === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid move request.']);
    exit;
}

$targetFolder = basename($destination);
if ($targetFolder === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid destination folder.']);
    exit;
}

$root = dirname(__DIR__, 2);
$uploadsRoot = $root . '/uploads';
$targetDir = $uploadsRoot . '/' . $targetFolder;

if (!is_dir($targetDir)) {
    echo json_encode(['status' => 'error', 'message' => 'Destination folder does not exist.']);
    exit;
}

$mediaFile = $root . '/data/media.json';
$media = read_json_file($mediaFile);
if (!is_array($media)) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to read media library.']);
    exit;
}

$found = false;
foreach ($media as &$item) {
    if (($item['id'] ?? '') !== $id) {
        continue;
    }

    $found = true;
    $currentFolder = $item['folder'] ?? '';
    if ($currentFolder === $targetFolder) {
        echo json_encode(['status' => 'success', 'message' => 'File already in destination folder.']);
        exit;
    }

    $originalPath = $root . '/' . $item['file'];
    if (!is_file($originalPath)) {
        echo json_encode(['status' => 'error', 'message' => 'File not found on disk.']);
        exit;
    }

    $fileName = basename($item['file']);
    $newRelative = 'uploads/' . $targetFolder . '/' . $fileName;
    $newPath = $root . '/' . $newRelative;

    if (file_exists($newPath)) {
        echo json_encode(['status' => 'error', 'message' => 'A file with the same name already exists in the destination folder.']);
        exit;
    }

    $thumbNewRelative = null;
    $thumbNewPath = null;
    $thumbSource = null;
    if (!empty($item['thumbnail'])) {
        $thumbSource = $root . '/' . $item['thumbnail'];
        if (is_file($thumbSource)) {
            $thumbName = basename($item['thumbnail']);
            $thumbDir = $targetDir . '/thumbs';
            if (!is_dir($thumbDir)) {
                if (!mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
                    echo json_encode(['status' => 'error', 'message' => 'Unable to prepare destination thumbnails directory.']);
                    exit;
                }
            }
            $thumbNewRelative = 'uploads/' . $targetFolder . '/thumbs/' . $thumbName;
            $thumbNewPath = $root . '/' . $thumbNewRelative;
            if (file_exists($thumbNewPath)) {
                echo json_encode(['status' => 'error', 'message' => 'A thumbnail with the same name already exists in the destination folder.']);
                exit;
            }
        }
    }

    if (!is_dir(dirname($newPath))) {
        if (!mkdir(dirname($newPath), 0777, true) && !is_dir(dirname($newPath))) {
            echo json_encode(['status' => 'error', 'message' => 'Unable to prepare destination folder.']);
            exit;
        }
    }

    if (!move_file_with_fallback($originalPath, $newPath)) {
        $error = error_get_last();
        $details = $error['message'] ?? 'Unable to move the file.';
        echo json_encode(['status' => 'error', 'message' => preg_replace('/^warning:\s*/i', '', $details)]);
        exit;
    }

    if ($thumbSource && $thumbNewPath) {
        if (!move_file_with_fallback($thumbSource, $thumbNewPath)) {
            $thumbError = error_get_last();
            move_file_with_fallback($newPath, $originalPath);
            $details = $thumbError['message'] ?? 'Unable to move the thumbnail.';
            echo json_encode(['status' => 'error', 'message' => preg_replace('/^warning:\s*/i', '', $details)]);
            exit;
        }
    }

    $item['folder'] = $targetFolder;
    $item['file'] = $newRelative;
    if ($thumbNewRelative) {
        $item['thumbnail'] = $thumbNewRelative;
    }

    break;
}
unset($item);

if (!$found) {
    echo json_encode(['status' => 'error', 'message' => 'Media item not found.']);
    exit;
}

write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success']);
