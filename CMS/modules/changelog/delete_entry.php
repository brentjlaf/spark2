<?php
// File: delete_entry.php  –  delete a changelog entry (admin only)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
verify_csrf_token();
require_admin();

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$id = isset($body['id']) && is_numeric($body['id']) && (int)$body['id'] > 0 ? (int)$body['id'] : 0;
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'A valid entry ID is required.']);
    exit;
}

$file    = __DIR__ . '/../../data/changelog.json';
$entries = read_json_file($file);
if (!is_array($entries)) {
    $entries = [];
}

$found        = false;
$deletedTitle = '';
foreach ($entries as $index => $e) {
    if ((int)($e['id'] ?? 0) === $id) {
        $deletedTitle = $e['title'] ?? "Entry #{$id}";
        unset($entries[$index]);
        $found = true;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['error' => 'Entry not found.']);
    exit;
}

if (!write_json_file($file, array_values($entries))) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete entry.']);
    exit;
}

sparkcms_audit('Deleted changelog entry', 'system', $deletedTitle, ['ID: ' . $id]);

echo json_encode(['success' => true, 'id' => $id]);
