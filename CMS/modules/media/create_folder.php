<?php
// File: create_folder.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$folder = sanitize_text($_POST['folder'] ?? '');
if ($folder === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Folder name required']);
    exit;
}

$normalized = strtolower($folder);
$invalidNames = ['.', '..', 'con', 'prn', 'aux', 'nul', 'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9'];
if (in_array($normalized, $invalidNames, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder name']);
    exit;
}

if (preg_match('/[\\\/]/', $folder)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid folder name']);
    exit;
}

$root = dirname(__DIR__, 2);
$uploadsDir = $root . '/uploads';
$requested = basename($folder);
$normalizedRequested = strtolower($requested);

$existingFolders = glob($uploadsDir . '/*', GLOB_ONLYDIR) ?: [];
$normalizedExisting = array_map(function($path){
    return strtolower(basename($path));
}, $existingFolders);

$generalExists = in_array('general', $normalizedExisting, true);

if ($normalizedRequested === 'general') {
    if ($generalExists) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'The General folder already exists']);
        exit;
    }
} elseif (in_array($normalizedRequested, $normalizedExisting, true)) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'A folder with that name already exists']);
    exit;
}

$dir = $uploadsDir . '/' . $requested;

if (is_dir($dir)) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Folder already exists']);
    exit;
}

if (!@mkdir($dir, 0777, true)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to create folder']);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Folder created successfully']);
