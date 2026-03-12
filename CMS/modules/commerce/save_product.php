<?php
// File: save_product.php  –  create or update a product
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
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

$name = sanitize_text($body['name'] ?? '');
if ($name === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Product name is required.']);
    exit;
}

$file     = __DIR__ . '/../../data/commerce.json';
$products = read_json_file($file);
if (!is_array($products)) {
    $products = [];
}

$id           = isset($body['id']) && is_numeric($body['id']) && (int)$body['id'] > 0 ? (int)$body['id'] : null;
$price        = is_numeric($body['price']         ?? null) ? round((float)$body['price'],         2) : 0.00;
$comparePrice = is_numeric($body['compare_price'] ?? null) ? round((float)$body['compare_price'], 2) : 0.00;
$cost         = is_numeric($body['cost']          ?? null) ? round((float)$body['cost'],          2) : 0.00;
$weight       = is_numeric($body['weight']        ?? null) ? round((float)$body['weight'],        3) : 0.000;
$stock        = is_numeric($body['stock']         ?? null) ? max(0, (int)$body['stock'])              : 0;
$trackStock   = !empty($body['track_stock']);
$status       = in_array($body['status'] ?? '', ['active', 'inactive', 'draft'], true)
                ? $body['status'] : 'active';

$slug = sanitize_text($body['slug'] ?? '');
if ($slug === '') {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-') ?: 'product';
}

$now = date('c');

if ($id !== null) {
    // ── Update existing ─────────────────────────────────────────────────────
    $found = false;
    foreach ($products as &$p) {
        if ((int)($p['id'] ?? 0) !== $id) {
            continue;
        }
        $p = array_merge($p, [
            'name'          => $name,
            'slug'          => $slug,
            'sku'           => sanitize_text($body['sku']         ?? ''),
            'description'   => trim($body['description']          ?? ''),
            'price'         => $price,
            'compare_price' => $comparePrice,
            'cost'          => $cost,
            'stock'         => $stock,
            'track_stock'   => $trackStock,
            'status'        => $status,
            'category'      => sanitize_text($body['category']    ?? ''),
            'tags'          => sanitize_text($body['tags']        ?? ''),
            'image'         => sanitize_url($body['image']        ?? ''),
            'weight'        => $weight,
            'updated_at'    => $now,
        ]);
        $record = $p;
        $found  = true;
        break;
    }
    unset($p);

    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found.']);
        exit;
    }

    sparkcms_audit('Updated product', 'commerce', $name, [
        'Status: ' . $status,
        'Price: $' . number_format($price, 2),
        $trackStock ? 'Stock: ' . $stock : 'Stock: untracked',
    ]);
} else {
    // ── Create new ───────────────────────────────────────────────────────────
    $maxId = 0;
    foreach ($products as $p) {
        $maxId = max($maxId, (int)($p['id'] ?? 0));
    }

    $record = [
        'id'            => $maxId + 1,
        'name'          => $name,
        'slug'          => $slug,
        'sku'           => sanitize_text($body['sku']         ?? ''),
        'description'   => trim($body['description']          ?? ''),
        'price'         => $price,
        'compare_price' => $comparePrice,
        'cost'          => $cost,
        'stock'         => $stock,
        'track_stock'   => $trackStock,
        'status'        => $status,
        'category'      => sanitize_text($body['category']    ?? ''),
        'tags'          => sanitize_text($body['tags']        ?? ''),
        'image'         => sanitize_url($body['image']        ?? ''),
        'weight'        => $weight,
        'created_at'    => $now,
        'updated_at'    => $now,
    ];
    $products[] = $record;

    sparkcms_audit('Created product', 'commerce', $name, [
        'SKU: ' . ($record['sku'] ?: 'N/A'),
        'Price: $' . number_format($price, 2),
        'Status: ' . $status,
    ]);
}

if (!write_json_file($file, array_values($products))) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save product.']);
    exit;
}

echo json_encode($record);
