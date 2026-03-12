<?php
// File: save_draft.php (blogs)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload  = json_decode($rawInput, true);
if (!is_array($payload)) {
  $payload = $_POST;
}

$id = isset($payload['id']) ? (int)$payload['id'] : 0;
if ($id <= 0) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid post ID.']);
  exit;
}

$draft = [
  'id'          => $id,
  'title'       => trim((string)($payload['title'] ?? '')),
  'slug'        => trim((string)($payload['slug'] ?? '')),
  'excerpt'     => trim((string)($payload['excerpt'] ?? '')),
  'content'     => (string)($payload['content'] ?? ''),
  'category'    => trim((string)($payload['category'] ?? '')),
  'author'      => trim((string)($payload['author'] ?? '')),
  'status'      => strtolower(trim((string)($payload['status'] ?? 'draft'))),
  'tags'        => trim((string)($payload['tags'] ?? '')),
  'publishDate' => trim((string)($payload['publishDate'] ?? '')),
  'image'       => trim((string)($payload['image'] ?? '')),
  'imageAlt'    => trim((string)($payload['imageAlt'] ?? '')),
  'saved_at'    => time(),
  'saved_by'    => $_SESSION['user']['username'] ?? 'unknown',
];

$draftDir = __DIR__ . '/../../data/drafts';
if (!is_dir($draftDir)) {
  mkdir($draftDir, 0777, true);
}

$draftFile = $draftDir . '/blog_' . $id . '.json';
write_json_file($draftFile, $draft);

echo json_encode(['status' => 'success', 'saved_at' => $draft['saved_at']]);
