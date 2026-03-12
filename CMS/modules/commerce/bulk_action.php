<?php
// File: bulk_action.php (commerce)  –  bulk activate / deactivate / delete
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload  = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = trim((string)($payload['action'] ?? ''));
$ids    = $payload['ids'] ?? [];
if (is_string($ids)) {
    $ids = json_decode($ids, true) ?: [];
}

if (!is_array($ids) || empty($ids)) {
    echo json_encode(['status' => 'error', 'message' => 'No products selected.']);
    exit;
}

$ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));

if (!in_array($action, ['activate', 'deactivate', 'delete'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit;
}

if ($action === 'delete') {
    require_admin();
}

$file     = __DIR__ . '/../../data/commerce.json';
$products = read_json_file($file);
if (!is_array($products)) {
    echo json_encode(['status' => 'error', 'message' => 'Unable to read products.']);
    exit;
}

$affected = 0;
foreach ($products as &$p) {
    if (!in_array((int)($p['id'] ?? 0), $ids)) {
        continue;
    }
    if ($action === 'activate') {
        $p['status']     = 'active';
        $p['updated_at'] = date('c');
        $affected++;
    } elseif ($action === 'deactivate') {
        $p['status']     = 'inactive';
        $p['updated_at'] = date('c');
        $affected++;
    } elseif ($action === 'delete') {
        $p['__delete__'] = true;
        $affected++;
    }
}
unset($p);

if ($action === 'delete') {
    $products = array_values(array_filter($products, fn($p) => empty($p['__delete__'])));
}

write_json_file($file, array_values($products));

$actionLabel = match ($action) {
    'activate'   => 'Bulk activated products',
    'deactivate' => 'Bulk deactivated products',
    'delete'     => 'Bulk deleted products',
    default      => 'Bulk action on products',
};
sparkcms_audit($actionLabel, 'commerce', 'Multiple products', [
    count($ids) . ' product(s) selected',
    $affected . ' affected',
]);

echo json_encode(['status' => 'success', 'affected' => $affected]);
