<?php
// File: get_draft.php (blogs)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid post ID.']);
  exit;
}

$draftFile = __DIR__ . '/../../data/drafts/blog_' . $id . '.json';
if (!file_exists($draftFile)) {
  echo json_encode(['status' => 'none']);
  exit;
}

$draft = read_json_file($draftFile);
if (!is_array($draft) || !isset($draft['saved_at'])) {
  echo json_encode(['status' => 'none']);
  exit;
}

echo json_encode(['status' => 'ok', 'draft' => $draft]);
