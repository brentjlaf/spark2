<?php
// File: delete_media.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);
$id = sanitize_text($_POST['id'] ?? '');
if ($id === '') {
    echo json_encode(['status' => 'error']);
    exit;
}
$root = dirname(__DIR__, 2);
$new = [];
foreach ($media as $item) {
    if ($item['id'] === $id) {
        $file = $root . '/' . $item['file'];
        if (is_file($file)) @unlink($file);
        if (!empty($item['thumbnail'])) {
            $thumb = $root . '/' . $item['thumbnail'];
            if (is_file($thumb)) @unlink($thumb);
        }
    } else {
        $new[] = $item;
    }
}
write_json_file($mediaFile, array_values($new));

echo json_encode(['status' => 'success']);

