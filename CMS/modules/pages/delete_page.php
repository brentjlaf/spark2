<?php
// File: delete_page.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();
$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
if (!is_array($pages) || empty($pages)) {
    exit('No pages');
}
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$deletedPage = null;
foreach ($pages as $p) {
    if ($p['id'] == $id) { $deletedPage = $p; break; }
}
$pages = array_filter($pages, function($p) use ($id) { return $p['id'] != $id; });
write_json_file($pagesFile, array_values($pages));

// Remove the deleted page from any menus
$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);
if (is_array($menus) && !empty($menus)) {
    $pageSlug = $deletedPage['slug'] ?? '';

    $removePageFromItems = function (array $items) use (&$removePageFromItems, $id, $pageSlug) {
        $cleaned = [];
        foreach ($items as $item) {
            $itemType = $item['type'] ?? '';
            $itemPage = isset($item['page']) ? (int)$item['page'] : null;
            $itemLink = isset($item['link']) ? rtrim($item['link'], '/') : null;
            $pageLink = $pageSlug !== '' ? '/' . trim($pageSlug, '/') : null;

            $matchesPage = ($itemType === 'page' && $itemPage === $id);
            if (!$matchesPage && $pageLink !== null && $itemLink !== null) {
                $matchesPage = $itemLink === $pageLink;
            }

            if ($matchesPage) {
                continue;
            }

            if (!empty($item['children']) && is_array($item['children'])) {
                $item['children'] = $removePageFromItems($item['children']);
                if (empty($item['children'])) {
                    unset($item['children']);
                }
            }

            $cleaned[] = $item;
        }
        return $cleaned;
    };

    $menusUpdated = false;
    foreach ($menus as &$menu) {
        $originalItems = $menu['items'] ?? [];
        $menu['items'] = $removePageFromItems($originalItems);
        if ($menu['items'] !== $originalItems) {
            $menusUpdated = true;
        }
    }
    unset($menu);

    if ($menusUpdated) {
        write_json_file($menusFile, $menus);
    }
}

// Feature 16: Regenerate sitemap after page deletion.
require_once __DIR__ . '/../../includes/sitemap_helpers.php';
sparkcms_regenerate_sitemap(realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../');

// Feature 17: Remove deleted page from the full-text search index.
try {
    require_once __DIR__ . '/../../includes/db.php';
    if (is_database_configured()) {
        require_once __DIR__ . '/../../includes/search_index.php';
        sparkcms_remove_from_index(get_db_connection(), 'page', $id);
    }
} catch (Throwable $_e) { /* non-critical */ }

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$action = 'deleted page';
$details = [];
if ($deletedPage) {
    $details[] = 'Title: ' . ($deletedPage['title'] ?? 'Unknown');
    $details[] = 'Slug: ' . ($deletedPage['slug'] ?? '');
    if (!empty($deletedPage['template'])) {
        $details[] = 'Template: ' . $deletedPage['template'];
    }
    $details[] = 'Previous visibility: ' . (!empty($deletedPage['published']) ? 'Published' : 'Unpublished');
}
if ($deletedPage && !empty($deletedPage['template'])) {
    $action .= ' (' . $deletedPage['template'] . ')';
}
$historyData[$id][] = [
    'time' => time(),
    'user' => $user,
    'action' => $action,
    'details' => $details,
    'context' => 'page',
    'page_id' => $id,
];
$historyData[$id] = array_slice($historyData[$id], -20);

if (!isset($historyData['__system__'])) {
    $historyData['__system__'] = [];
}
$historyData['__system__'][] = [
    'time' => time(),
    'user' => '',
    'action' => 'Regenerated sitemap',
    'details' => [
        'Automatic sitemap refresh after deleting page ID ' . $id,
    ],
    'context' => 'system',
    'meta' => [
        'trigger' => 'sitemap_regeneration',
        'page_id' => $id,
    ],
    'page_title' => 'CMS Backend',
];
$historyData['__system__'] = array_slice($historyData['__system__'], -50);
write_json_file($historyFile, $historyData);

echo 'OK';
