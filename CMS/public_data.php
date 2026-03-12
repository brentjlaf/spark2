<?php
// File: CMS/public_data.php
// Public data endpoints that proxy database-backed JSON entities.
require_once __DIR__ . '/includes/data.php';

function respond_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$routes = [
    'blog_posts' => __DIR__ . '/data/blog_posts.json',
    'commerce' => __DIR__ . '/data/commerce.json',
    'map_locations' => __DIR__ . '/data/map_locations.json',
    'map_categories' => __DIR__ . '/data/map_categories.json',
    'events' => __DIR__ . '/data/events.json',
    'event_categories' => __DIR__ . '/data/event_categories.json',
];

$action = strtolower(trim((string) ($_GET['action'] ?? '')));
if ($action === '') {
    respond_json(['error' => 'Missing action.'], 400);
}

if (!isset($routes[$action])) {
    respond_json(['error' => 'Unknown action.'], 404);
}

$data = read_json_file($routes[$action]);
respond_json($data);
