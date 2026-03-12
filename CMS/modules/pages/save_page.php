<?php
// File: save_page.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/page_schedule.php';

require_login();
verify_csrf_token();
require_editor();
$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
if (!is_array($pages)) {
    $pages = [];
}

foreach ($pages as &$pageRef) {
    if (!is_array($pageRef)) {
        continue;
    }
    $pageRef['publish_at'] = sanitize_datetime_local($pageRef['publish_at'] ?? '');
    $pageRef['unpublish_at'] = sanitize_datetime_local($pageRef['unpublish_at'] ?? '');
}
unset($pageRef);

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$title = sanitize_text($_POST['title'] ?? '');
$slug = sanitize_text($_POST['slug'] ?? '');

function slugify($text){
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'page';
}

function format_publish_schedule_value($value) {
    $value = sanitize_datetime_local($value ?? '');
    if ($value === '') {
        return 'Immediate';
    }
    $timestamp = sparkcms_datetime_local_to_timestamp($value);
    if ($timestamp === null) {
        return 'Immediate';
    }
    return date('M j, Y g:i A', $timestamp);
}

function format_unpublish_schedule_value($value) {
    $value = sanitize_datetime_local($value ?? '');
    if ($value === '') {
        return 'Not scheduled';
    }
    $timestamp = sparkcms_datetime_local_to_timestamp($value);
    if ($timestamp === null) {
        return 'Not scheduled';
    }
    return date('M j, Y g:i A', $timestamp);
}

if ($slug === '') {
    $slug = $title;
}
$slug = slugify($slug);
$content = trim($_POST['content'] ?? '');
// strip script tags to avoid XSS in stored content
$content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
$published = isset($_POST['published']) ? (bool)$_POST['published'] : false;
$publish_at = sanitize_datetime_local($_POST['publish_at'] ?? '');
$unpublish_at = sanitize_datetime_local($_POST['unpublish_at'] ?? '');
$template = sanitize_text($_POST['template'] ?? '');
if ($template === '') {
    $template = 'page.php';
}
$meta_title = sanitize_text($_POST['meta_title'] ?? '');
$meta_description = sanitize_text($_POST['meta_description'] ?? '');
$canonical_url = sanitize_url($_POST['canonical_url'] ?? '');
$og_title = sanitize_text($_POST['og_title'] ?? '');
$og_description = sanitize_text($_POST['og_description'] ?? '');
$og_image = sanitize_url($_POST['og_image'] ?? '');
$access = sanitize_text($_POST['access'] ?? 'public');
$robots = sanitize_robots_directive($_POST['robots'] ?? sparkcms_default_robots_directive());

if ($title === '') {
    http_response_code(400);
    echo 'Missing fields';
    exit;
}

if ($id) {
    // Update existing
    $old = null;
    $oldPublishAt = '';
    $oldUnpublishAt = '';
    foreach ($pages as &$p) {
        if ($p['id'] == $id) {
            $old = $p;
            $oldPublishAt = sanitize_datetime_local($p['publish_at'] ?? '');
            $oldUnpublishAt = sanitize_datetime_local($p['unpublish_at'] ?? '');
            $p['title'] = $title;
            $p['slug'] = $slug;
            $p['content'] = $content;
            $p['published'] = $published;
            $p['publish_at'] = $publish_at;
            $p['unpublish_at'] = $unpublish_at;
            $p['template'] = $template;
            $p['meta_title'] = $meta_title;
            $p['meta_description'] = $meta_description;
            $p['canonical_url'] = $canonical_url;
            $p['og_title'] = $og_title;
            $p['og_description'] = $og_description;
            $p['og_image'] = $og_image;
            $p['access'] = $access;
            $p['robots'] = $robots;
            $p['last_modified'] = time();
            $timestamp = $p['last_modified'];
            break;
        }
    }
    unset($p);
    $changes = [];
    $details = [];
    if ($old) {
        if ($old['title'] !== $title) {
            $details[] = 'Title: "' . $old['title'] . '" → "' . $title . '"';
        }
        if ($old['slug'] !== $slug) {
            $details[] = 'Slug: ' . $old['slug'] . ' → ' . $slug;
        }
        if ($old['template'] !== $template) {
            $details[] = 'Template: ' . $old['template'] . ' → ' . $template;
            $changes[] = 'Changed template';
        }
        if ($old['published'] != $published) {
            $details[] = 'Visibility: ' . ($old['published'] ? 'Published' : 'Unpublished') . ' → ' . ($published ? 'Published' : 'Unpublished');
            $changes[] = $published ? 'Published page' : 'Unpublished page';
        }
        if ($old['meta_title'] !== $meta_title) {
            $details[] = 'Meta title updated';
        }
        if (($old['meta_description'] ?? '') !== $meta_description) {
            $details[] = 'Meta description updated';
        }
        if (($old['canonical_url'] ?? '') !== $canonical_url) {
            $details[] = 'Canonical URL: ' . (($old['canonical_url'] ?? '') !== '' ? ($old['canonical_url'] ?? '') : 'none') . ' → ' . ($canonical_url !== '' ? $canonical_url : 'none');
        }
        if ($old['og_title'] !== $og_title) {
            $details[] = 'OG title: "' . $old['og_title'] . '" → "' . $og_title . '"';
        }
        if ($old['og_description'] !== $og_description) {
            $details[] = 'OG description updated';
        }
        if ($old['og_image'] !== $og_image) {
            $details[] = 'OG image: ' . ($old['og_image'] !== '' ? $old['og_image'] : 'none') . ' → ' . ($og_image !== '' ? $og_image : 'none');
        }
        if ($old['access'] !== $access) {
            $details[] = 'Access: ' . $old['access'] . ' → ' . $access;
        }
        $oldRobots = sanitize_robots_directive($old['robots'] ?? sparkcms_default_robots_directive());
        if ($oldRobots !== $robots) {
            $details[] = 'Robots: ' . $oldRobots . ' → ' . $robots;
        }
        if ($oldPublishAt !== $publish_at) {
            $details[] = 'Publish at: ' . format_publish_schedule_value($oldPublishAt) . ' → ' . format_publish_schedule_value($publish_at);
            if ($publish_at !== '' || $oldPublishAt !== '') {
                $changes[] = 'Updated publish schedule';
            }
        }
        if ($oldUnpublishAt !== $unpublish_at) {
            $details[] = 'Unpublish at: ' . format_unpublish_schedule_value($oldUnpublishAt) . ' → ' . format_unpublish_schedule_value($unpublish_at);
            if ($unpublish_at !== '' || $oldUnpublishAt !== '') {
                $changes[] = 'Updated unpublish schedule';
            }
        }
    }
    if (!$changes) {
        $changes[] = 'Updated page settings';
    }
    if (!$details) {
        $details[] = 'Saved without changing any settings.';
    }
    $action = implode('; ', $changes);
    unset($p);
} else {
    $id = 1;
    foreach ($pages as $p) {
        if ($p['id'] >= $id) $id = $p['id'] + 1;
    }
    $pages[] = [
        'id' => $id,
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'published' => $published,
        'publish_at' => $publish_at,
        'unpublish_at' => $unpublish_at,
        'template' => $template,
        'meta_title' => $meta_title,
        'meta_description' => $meta_description,
        'canonical_url' => $canonical_url,
        'og_title' => $og_title,
        'og_description' => $og_description,
        'og_image' => $og_image,
        'access' => $access,
        'robots' => $robots,
        'views' => 0,
        'last_modified' => time()
    ];
    $timestamp = $pages[array_key_last($pages)]['last_modified'];
    $details = [
        'Initial template: ' . $template,
        'Visibility: ' . ($published ? 'Published' : 'Unpublished'),
        'Access: ' . $access,
        'Robots: ' . $robots,
        'Publish at: ' . format_publish_schedule_value($publish_at),
        'Unpublish at: ' . format_unpublish_schedule_value($unpublish_at),
    ];
    $action = 'created page with template ' . $template;
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (!isset($historyData[$id])) $historyData[$id] = [];
$user = $_SESSION['user']['username'] ?? 'Unknown';
$historyData[$id][] = [
    'time' => $timestamp,
    'user' => $user,
    'action' => $action,
    'details' => $details,
    'context' => 'page',
    'page_id' => $id,
    'snapshot' => [
        'title'            => $title,
        'slug'             => $slug,
        'content'          => $content,
        'published'        => $published,
        'publish_at'       => $publish_at,
        'unpublish_at'     => $unpublish_at,
        'template'         => $template,
        'meta_title'       => $meta_title,
        'meta_description' => $meta_description,
        'canonical_url'    => $canonical_url,
        'og_title'         => $og_title,
        'og_description'   => $og_description,
        'og_image'         => $og_image,
        'access'           => $access,
        'robots'           => $robots,
    ],
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
        'Automatic sitemap refresh after updating "' . $title . '" (' . $slug . ')',
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

write_json_file($pagesFile, $pages);

// Feature 16: Regenerate sitemap using the shared helper.
require_once __DIR__ . '/../../includes/sitemap_helpers.php';
sparkcms_regenerate_sitemap(realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../');

// Feature 17: Update the full-text search index if a database is configured.
try {
    require_once __DIR__ . '/../../includes/db.php';
    if (is_database_configured()) {
        require_once __DIR__ . '/../../includes/search_index.php';
        $_body = $meta_description . ' ' . strip_tags($content);
        sparkcms_index_record(get_db_connection(), 'page', $id, $title, $_body, $slug, $published);
    }
} catch (Throwable $_e) { /* non-critical; search index update failure must not block the save */ }

header('Content-Type: text/plain; charset=UTF-8');
echo 'OK';
