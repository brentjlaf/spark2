<?php
// File: save_draft.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/page_schedule.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid page ID.']);
  exit;
}

$draft = [
  'id'              => $id,
  'title'           => sanitize_text($_POST['title'] ?? ''),
  'slug'            => sanitize_text($_POST['slug'] ?? ''),
  'content'         => trim($_POST['content'] ?? ''),
  'published'       => !empty($_POST['published']) ? 1 : 0,
  'publish_at'      => sanitize_datetime_local($_POST['publish_at'] ?? ''),
  'unpublish_at'    => sanitize_datetime_local($_POST['unpublish_at'] ?? ''),
  'template'        => sanitize_text($_POST['template'] ?? 'page.php'),
  'meta_title'      => sanitize_text($_POST['meta_title'] ?? ''),
  'meta_description' => sanitize_text($_POST['meta_description'] ?? ''),
  'canonical_url'   => sanitize_url($_POST['canonical_url'] ?? ''),
  'og_title'        => sanitize_text($_POST['og_title'] ?? ''),
  'og_description'  => sanitize_text($_POST['og_description'] ?? ''),
  'og_image'        => sanitize_url($_POST['og_image'] ?? ''),
  'access'          => sanitize_text($_POST['access'] ?? 'public'),
  'robots'          => sanitize_robots_directive($_POST['robots'] ?? sparkcms_default_robots_directive()),
  'saved_at'        => time(),
  'saved_by'        => $_SESSION['user']['username'] ?? 'unknown',
];

$draftDir = __DIR__ . '/../../data/drafts';
if (!is_dir($draftDir)) {
  mkdir($draftDir, 0777, true);
}

$draftFile = $draftDir . '/page_' . $id . '.json';
write_json_file($draftFile, $draft);

echo json_encode(['status' => 'success', 'saved_at' => $draft['saved_at']]);
