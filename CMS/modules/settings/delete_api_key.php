<?php
// File: modules/settings/delete_api_key.php
// Permanently revoke (delete) an API key by its internal id.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();
verify_csrf_token();
require_admin();

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$id = trim($body['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => '"id" is required.']);
    exit;
}

$keysFile = __DIR__ . '/../../data/api_keys.json';
$keys     = read_json_file($keysFile);
if (!is_array($keys)) {
    $keys = [];
}

$before = count($keys);
$keys   = array_values(array_filter($keys, fn($k) => ($k['id'] ?? '') !== $id));

if (count($keys) === $before) {
    http_response_code(404);
    echo json_encode(['error' => 'API key not found.']);
    exit;
}

write_json_file($keysFile, $keys);
echo json_encode(['status' => 'deleted']);
