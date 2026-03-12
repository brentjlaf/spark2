<?php
// File: backfill_schedule.php
// Ensure existing page entries include schedule fields

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/page_schedule.php';

require_login();

header('Content-Type: application/json');

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);

$updatedCount = 0;

foreach ($pages as &$page) {
    if (!is_array($page)) {
        continue;
    }

    $normalizedPublish = sanitize_datetime_local($page['publish_at'] ?? '');
    $normalizedUnpublish = sanitize_datetime_local($page['unpublish_at'] ?? '');

    $needsUpdate = false;
    if (!array_key_exists('publish_at', $page) || $page['publish_at'] !== $normalizedPublish) {
        $page['publish_at'] = $normalizedPublish;
        $needsUpdate = true;
    }
    if (!array_key_exists('unpublish_at', $page) || $page['unpublish_at'] !== $normalizedUnpublish) {
        $page['unpublish_at'] = $normalizedUnpublish;
        $needsUpdate = true;
    }

    if ($needsUpdate) {
        $updatedCount++;
    }
}
unset($page);

if ($updatedCount > 0) {
    if (!write_json_file($pagesFile, $pages)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to update pages.json with schedule defaults.'
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'updated' => $updatedCount,
]);
