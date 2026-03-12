<?php
// File: save_entry.php  –  create or update a changelog entry (admin only)
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
verify_csrf_token();
require_admin();

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

// ── Validation ────────────────────────────────────────────────────────────────
$title = sanitize_text($body['title'] ?? '');
if ($title === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Title is required.']);
    exit;
}

$validCategories = ['feature', 'improvement', 'fix', 'security'];
$category = in_array($body['category'] ?? '', $validCategories, true) ? $body['category'] : 'feature';

$validModules = [
    'pages', 'blogs', 'media', 'menus', 'forms', 'events', 'maps',
    'users', 'settings', 'analytics', 'seo', 'sitemap', 'speed',
    'accessibility', 'search', 'logs', 'commerce', 'dashboard', 'system',
];
$module = in_array($body['module'] ?? '', $validModules, true) ? $body['module'] : 'system';

$version     = sanitize_text($body['version']     ?? '1.0.0');
$date        = sanitize_text($body['date']        ?? date('Y-m-d'));
$description = sanitize_text($body['description'] ?? '');
$benefit     = sanitize_text($body['benefit']     ?? '');

// Usage steps: accept as array or newline-delimited string
$usageRaw = $body['usage'] ?? [];
if (is_string($usageRaw)) {
    $usageRaw = array_filter(array_map('trim', explode("\n", $usageRaw)));
}
$usage = is_array($usageRaw) ? array_values(array_map('sanitize_text', array_filter($usageRaw))) : [];

// Tags
$tagsRaw = $body['tags'] ?? '';
if (is_array($tagsRaw)) {
    $tags = array_values(array_map('sanitize_text', array_filter($tagsRaw)));
} else {
    $tags = array_values(array_filter(array_map('trim', explode(',', sanitize_text($tagsRaw)))));
}

// ── Load existing entries ─────────────────────────────────────────────────────
$file    = __DIR__ . '/../../data/changelog.json';
$entries = read_json_file($file);
if (!is_array($entries)) {
    $entries = [];
}

$inputId = isset($body['id']) && is_numeric($body['id']) && (int)$body['id'] > 0 ? (int)$body['id'] : null;

if ($inputId !== null) {
    // ── Update ─────────────────────────────────────────────────────────────────
    $found = false;
    foreach ($entries as &$e) {
        if ((int)($e['id'] ?? 0) === $inputId) {
            $e['title']       = $title;
            $e['version']     = $version;
            $e['date']        = $date;
            $e['category']    = $category;
            $e['module']      = $module;
            $e['description'] = $description;
            $e['benefit']     = $benefit;
            $e['usage']       = $usage;
            $e['tags']        = $tags;
            $found = true;
            break;
        }
    }
    unset($e);
    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Entry not found.']);
        exit;
    }
    sparkcms_audit('Updated changelog entry', 'system', $title, ['Version: ' . $version]);
} else {
    // ── Create ─────────────────────────────────────────────────────────────────
    $maxId = 0;
    foreach ($entries as $e) {
        $maxId = max($maxId, (int)($e['id'] ?? 0));
    }
    $entry = [
        'id'          => $maxId + 1,
        'version'     => $version,
        'date'        => $date,
        'title'       => $title,
        'category'    => $category,
        'module'      => $module,
        'description' => $description,
        'benefit'     => $benefit,
        'usage'       => $usage,
        'tags'        => $tags,
    ];
    $entries[] = $entry;
    sparkcms_audit('Added changelog entry', 'system', $title, ['Version: ' . $version]);
}

// Sort by version desc, then date desc
usort($entries, function ($a, $b) {
    $vc = version_compare($b['version'] ?? '0', $a['version'] ?? '0');
    if ($vc !== 0) return $vc;
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

if (!write_json_file($file, $entries)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save entry.']);
    exit;
}

// Return the saved entry
$saved = null;
foreach ($entries as $e) {
    if ($e['title'] === $title && $e['module'] === $module && $e['version'] === $version) {
        $saved = $e;
        break;
    }
}

echo json_encode($saved ?? ['success' => true]);
