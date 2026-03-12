<?php
// File: bulk_action.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

$action = sanitize_text($_POST['action'] ?? '');
$ids    = json_decode($_POST['ids'] ?? '[]', true);

if (!is_array($ids) || empty($ids)) {
  echo json_encode(['status' => 'error', 'message' => 'No pages selected.']);
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

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
if (!is_array($pages)) {
  echo json_encode(['status' => 'error', 'message' => 'Unable to read pages.']);
  exit;
}

$affected = 0;
foreach ($pages as &$p) {
  if (!in_array((int)$p['id'], $ids)) {
    continue;
  }
  if ($action === 'publish') {
    $p['published']     = true;
    $p['last_modified'] = time();
    $affected++;
  } elseif ($action === 'unpublish') {
    $p['published']     = false;
    $p['last_modified'] = time();
    $affected++;
  } elseif ($action === 'delete') {
    $p['__delete__'] = true;
    $affected++;
  }
}
unset($p);

if ($action === 'delete') {
  $pages = array_values(array_filter($pages, fn($p) => empty($p['__delete__'])));
}

write_json_file($pagesFile, $pages);

// Feature 16: Regenerate sitemap after any bulk page change.
require_once __DIR__ . '/../../includes/sitemap_helpers.php';
sparkcms_regenerate_sitemap(realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../');

// Feature 17: Bulk-update the full-text search index.
try {
    require_once __DIR__ . '/../../includes/db.php';
    if (is_database_configured()) {
        require_once __DIR__ . '/../../includes/search_index.php';
        $pdo = get_db_connection();
        foreach ($ids as $_pid) {
            if ($action === 'delete') {
                sparkcms_remove_from_index($pdo, 'page', $_pid);
            } else {
                foreach ($pages as $_p) {
                    if ((int)($_p['id'] ?? 0) === $_pid) {
                        $_body = ($_p['meta_description'] ?? '') . ' ' . strip_tags($_p['content'] ?? '');
                        sparkcms_index_record($pdo, 'page', $_pid, $_p['title'] ?? '', $_body, $_p['slug'] ?? '', !empty($_p['published']));
                        break;
                    }
                }
            }
        }
    }
} catch (Throwable $_e) { /* non-critical */ }

echo json_encode(['status' => 'success', 'affected' => $affected]);
