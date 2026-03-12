<?php
// File: get_history.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid page ID.']);
  exit;
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!is_array($historyData)) {
  $historyData = [];
}

$entries = $historyData[$id] ?? [];

// Sort by time descending (most recent first)
usort($entries, function ($a, $b) {
  return ($b['time'] ?? 0) - ($a['time'] ?? 0);
});

echo json_encode(['status' => 'ok', 'history' => $entries]);
