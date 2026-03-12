<?php
// File: rebuild_index.php
// Admin-only endpoint: wipe and rebuild the MySQL FULLTEXT search index
// from the current JSON data files.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/db.php';
require_login();
verify_csrf_token();
require_admin();

header('Content-Type: application/json');

try {
    if (!is_database_configured()) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'message' => 'Database is not configured. The full-text search index requires MySQL.',
        ]);
        exit;
    }

    require_once __DIR__ . '/../../includes/search_index.php';
    $cmsRoot = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');
    sparkcms_rebuild_search_index(get_db_connection(), $cmsRoot);

    echo json_encode([
        'success' => true,
        'message' => 'Full-text search index rebuilt successfully.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to rebuild index: ' . $e->getMessage(),
    ]);
}
