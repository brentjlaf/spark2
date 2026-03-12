<?php
// File: delete_product.php  –  remove a single product
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$body     = json_decode($rawInput, true);
if (!is_array($body)) {
    $body = $_POST;
}

$id = isset($body['id']) && is_numeric($body['id']) && (int)$body['id'] > 0
    ? (int)$body['id']
    : 0;

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'A valid product ID is required.']);
    exit;
}

$file     = __DIR__ . '/../../data/commerce.json';
$products = read_json_file($file);
if (!is_array($products)) {
    $products = [];
}

$found       = false;
$deletedName = '';
foreach ($products as $index => $p) {
    if ((int)($p['id'] ?? 0) === $id) {
        $deletedName = $p['name'] ?? "Product #{$id}";
        unset($products[$index]);
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found.']);
    exit;
}

if (!write_json_file($file, array_values($products))) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete product.']);
    exit;
}

sparkcms_audit('Deleted product', 'commerce', $deletedName, ['ID: ' . $id]);

echo json_encode(['success' => true, 'id' => $id]);
