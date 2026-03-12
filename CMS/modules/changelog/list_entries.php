<?php
// File: list_entries.php  –  returns all changelog entries sorted by version/date desc
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$file    = __DIR__ . '/../../data/changelog.json';
$entries = read_json_file($file);
if (!is_array($entries)) {
    $entries = [];
}

usort($entries, function ($a, $b) {
    $vc = version_compare($b['version'] ?? '0', $a['version'] ?? '0');
    if ($vc !== 0) return $vc;
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

header('Content-Type: application/json');
echo json_encode(array_values($entries));
