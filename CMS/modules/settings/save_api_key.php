<?php
// File: modules/settings/save_api_key.php
// Create a new API key  OR  update / toggle an existing one.
//
// Create  (no id):  { name, permissions[] }
// Update  (id + name):  { id, name, permissions[], enabled }
// Toggle  (id, no name):  { id, enabled }
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/api_auth.php';
require_login();
verify_csrf_token();
require_admin();

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$keysFile = __DIR__ . '/../../data/api_keys.json';
$keys     = read_json_file($keysFile);
if (!is_array($keys)) {
    $keys = [];
}

$id      = trim($body['id']   ?? '');
$hasName = array_key_exists('name', $body);
$name    = $hasName ? sanitize_text(trim($body['name'])) : null;

// ── Validate permissions ───────────────────────────────────────────────────
function extract_permissions(array $body): array {
    $allowed = ['read', 'write', 'delete'];
    $requested = (array)($body['permissions'] ?? ['read']);
    $result = array_values(array_intersect($requested, $allowed));
    return $result ?: ['read']; // default to read-only if nothing valid provided
}

// ── CREATE a new key ───────────────────────────────────────────────────────
if ($id === '') {
    if ($name === null || $name === '') {
        http_response_code(422);
        echo json_encode(['error' => '"name" is required.']);
        exit;
    }

    $newKey = [
        'id'          => uniqid('key_', true),
        'key'         => sparkcms_generate_api_key(),
        'name'        => $name,
        'permissions' => extract_permissions($body),
        'enabled'     => true,
        'created_at'  => time(),
        'last_used_at' => null,
    ];

    $keys[] = $newKey;
    write_json_file($keysFile, $keys);
    echo json_encode(['status' => 'created', 'key' => $newKey]);
    exit;
}

// ── UPDATE or TOGGLE an existing key ──────────────────────────────────────
$found = false;
foreach ($keys as &$k) {
    if ($k['id'] !== $id) {
        continue;
    }
    $found = true;

    if ($hasName) {
        // Full update
        if ($name === '') {
            http_response_code(422);
            echo json_encode(['error' => '"name" cannot be empty.']);
            exit;
        }
        $k['name']        = $name;
        $k['permissions'] = extract_permissions($body);
        $k['enabled']     = isset($body['enabled']) ? (bool)$body['enabled'] : $k['enabled'];
    } elseif (array_key_exists('enabled', $body)) {
        // Toggle only
        $k['enabled'] = (bool)$body['enabled'];
    }

    write_json_file($keysFile, $keys);
    $safe = $k;
    unset($safe['key']); // Do not return the full key on updates
    echo json_encode(['status' => 'updated', 'key' => $safe]);
    exit;
}
unset($k);

if (!$found) {
    http_response_code(404);
    echo json_encode(['error' => 'API key not found.']);
}
