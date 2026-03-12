<?php
// File: update_order.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);

$order = json_decode($_POST['order'] ?? '[]', true);
if (!is_array($order)) $order = [];
$index = array_flip($order);
foreach ($media as &$item) {
    if (isset($index[$item['id']])) {
        $item['order'] = $index[$item['id']];
    }
}
usort($media, function($a,$b){ return ($a['order'] ?? 0) <=> ($b['order'] ?? 0); });
write_json_file($mediaFile, $media);

echo json_encode(['status' => 'success']);
