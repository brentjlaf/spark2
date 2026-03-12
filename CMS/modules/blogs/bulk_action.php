<?php
// File: bulk_action.php (blogs)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload  = json_decode($rawInput, true);
if (!is_array($payload)) {
  $payload = $_POST;
}

$action = trim((string)($payload['action'] ?? ''));
$ids    = $payload['ids'] ?? [];
if (is_string($ids)) {
  $ids = json_decode($ids, true) ?: [];
}

if (!is_array($ids) || empty($ids)) {
  echo json_encode(['status' => 'error', 'message' => 'No posts selected.']);
  exit;
}

$ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));

if (!in_array($action, ['publish', 'unpublish', 'delete'], true)) {
  echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
  exit;
}

if ($action === 'delete') {
  require_admin();
}

$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = read_json_file($postsFile);
if (!is_array($posts)) {
  echo json_encode(['status' => 'error', 'message' => 'Unable to read posts.']);
  exit;
}

$affected = 0;
foreach ($posts as &$p) {
  if (!in_array((int)$p['id'], $ids)) {
    continue;
  }
  if ($action === 'publish') {
    $p['status']      = 'published';
    $p['publishDate'] = date('c');
    $affected++;
  } elseif ($action === 'unpublish') {
    $p['status'] = 'draft';
    $affected++;
  } elseif ($action === 'delete') {
    $p['__delete__'] = true;
    $affected++;
  }
}
unset($p);

if ($action === 'delete') {
  $posts = array_values(array_filter($posts, fn($p) => empty($p['__delete__'])));
}

write_json_file($postsFile, array_values($posts));

// Feature 16: Regenerate sitemap after bulk publish/unpublish/delete.
try {
    require_once __DIR__ . '/../../includes/sitemap_helpers.php';
    sparkcms_regenerate_sitemap(realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../');
} catch (Throwable $_e) { /* non-critical */ }

// Feature 17: Bulk-update the full-text search index.
try {
    require_once __DIR__ . '/../../includes/db.php';
    if (is_database_configured()) {
        require_once __DIR__ . '/../../includes/search_index.php';
        $pdo = get_db_connection();
        foreach ($ids as $_pid) {
            if ($action === 'delete') {
                sparkcms_remove_from_index($pdo, 'post', $_pid);
            } else {
                foreach ($posts as $_p) {
                    if ((int)($_p['id'] ?? 0) === $_pid) {
                        $_body = ($_p['excerpt'] ?? '') . ' ' . ($_p['tags'] ?? '') . ' ' . strip_tags($_p['content'] ?? '');
                        sparkcms_index_record($pdo, 'post', $_pid, $_p['title'] ?? '', $_body, $_p['slug'] ?? '', ($_p['status'] ?? '') === 'published');
                        break;
                    }
                }
            }
        }
    }
} catch (Throwable $_e) { /* non-critical */ }

// Feature 18: Audit log.
$_auditAction = match ($action) {
    'publish'   => 'Bulk published posts',
    'unpublish' => 'Bulk unpublished posts',
    'delete'    => 'Bulk deleted posts',
    default     => 'Bulk action on posts',
};
sparkcms_audit($_auditAction, 'post', 'Multiple posts', [
    count($ids) . ' post(s) selected',
    $affected . ' affected',
]);

echo json_encode(['status' => 'success', 'affected' => $affected]);
