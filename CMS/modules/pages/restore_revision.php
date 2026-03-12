<?php
// File: restore_revision.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/page_schedule.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
  $payload = $_POST;
}

$pageId     = isset($payload['page_id']) ? (int)$payload['page_id'] : 0;
$revIndex   = isset($payload['rev_index']) ? (int)$payload['rev_index'] : -1;

if ($pageId <= 0 || $revIndex < 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid parameters.']);
  exit;
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!is_array($historyData)) {
  echo json_encode(['status' => 'error', 'message' => 'No history found.']);
  exit;
}

$entries = $historyData[$pageId] ?? [];
usort($entries, function ($a, $b) {
  return ($b['time'] ?? 0) - ($a['time'] ?? 0);
});

if (!isset($entries[$revIndex]) || empty($entries[$revIndex]['snapshot'])) {
  echo json_encode(['status' => 'error', 'message' => 'Revision not found or has no snapshot.']);
  exit;
}

$snap = $entries[$revIndex]['snapshot'];

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
if (!is_array($pages)) {
  echo json_encode(['status' => 'error', 'message' => 'Unable to load pages.']);
  exit;
}

$found = false;
foreach ($pages as &$p) {
  if ((int)$p['id'] === $pageId) {
    $p['title']           = sanitize_text($snap['title'] ?? $p['title']);
    $p['content']         = $snap['content'] ?? $p['content'];
    $p['published']       = (bool)($snap['published'] ?? $p['published']);
    $p['publish_at']      = sanitize_datetime_local($snap['publish_at'] ?? '');
    $p['unpublish_at']    = sanitize_datetime_local($snap['unpublish_at'] ?? '');
    $p['template']        = sanitize_text($snap['template'] ?? $p['template']);
    $p['meta_title']      = sanitize_text($snap['meta_title'] ?? '');
    $p['meta_description'] = sanitize_text($snap['meta_description'] ?? '');
    $p['canonical_url']   = sanitize_url($snap['canonical_url'] ?? '');
    $p['og_title']        = sanitize_text($snap['og_title'] ?? '');
    $p['og_description']  = sanitize_text($snap['og_description'] ?? '');
    $p['og_image']        = sanitize_url($snap['og_image'] ?? '');
    $p['access']          = sanitize_text($snap['access'] ?? 'public');
    $p['robots']          = sanitize_robots_directive($snap['robots'] ?? sparkcms_default_robots_directive());
    $p['last_modified']   = time();
    $found = true;
    break;
  }
}
unset($p);

if (!$found) {
  echo json_encode(['status' => 'error', 'message' => 'Page not found.']);
  exit;
}

write_json_file($pagesFile, $pages);

// Log the restore action
$user = $_SESSION['user']['username'] ?? 'Unknown';
$revTime = $entries[$revIndex]['time'] ?? 0;
if (!isset($historyData[$pageId])) {
  $historyData[$pageId] = [];
}
$historyData[$pageId][] = [
  'time'    => time(),
  'user'    => $user,
  'action'  => 'Restored revision from ' . date('M j, Y g:i A', $revTime),
  'details' => ['Revision restored by ' . $user],
  'context' => 'page',
  'page_id' => $pageId,
];
$historyData[$pageId] = array_slice($historyData[$pageId], -20);
write_json_file($historyFile, $historyData);

echo json_encode(['status' => 'success']);
