<?php
// File: update_tags.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);

$id = sanitize_text($_POST['id'] ?? '');
$tags = sanitize_tags(explode(',', $_POST['tags'] ?? ''));

foreach ($media as &$item) {
    if ($item['id'] === $id) {
        $item['tags'] = $tags;
        break;
    }
}
write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success']);
