<?php
// File: modules/settings/list_api_keys.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();
require_admin();

header('Content-Type: application/json');

$keysFile = __DIR__ . '/../../data/api_keys.json';
$keys     = read_json_file($keysFile);
if (!is_array($keys)) {
    $keys = [];
}

// Never expose the raw key value in list responses.
// Return only a short hint of the last 8 characters for identification.
$safe = array_map(function (array $k): array {
    $raw       = $k['key'] ?? '';
    $k['key_hint'] = $raw !== '' ? '…' . substr($raw, -8) : '';
    unset($k['key']);
    return $k;
}, $keys);

echo json_encode(['status' => 'ok', 'keys' => array_values($safe)]);
