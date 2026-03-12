<?php
// File: list_products.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

header('Content-Type: application/json');

$file     = __DIR__ . '/../../data/commerce.json';
$products = read_json_file($file);
if (!is_array($products)) {
    $products = [];
}

$status = strtolower(trim($_GET['status'] ?? ''));
$search = strtolower(trim($_GET['search'] ?? ''));

if ($status !== '' || $search !== '') {
    $products = array_values(array_filter($products, function ($p) use ($status, $search) {
        if ($status !== '' && ($p['status'] ?? 'active') !== $status) {
            return false;
        }
        if ($search !== '') {
            $hay = strtolower(($p['name'] ?? '') . ' ' . ($p['sku'] ?? '') . ' ' . ($p['description'] ?? ''));
            if (strpos($hay, $search) === false) {
                return false;
            }
        }
        return true;
    }));
}

echo json_encode(array_values($products));
