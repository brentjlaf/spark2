<?php
// File: list_categories.php
require_once __DIR__ . '/../../includes/data.php';

$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = read_json_file($postsFile);
$categories = [];
foreach ($posts as $p) {
    if (!empty($p['category']) && !in_array($p['category'], $categories)) {
        $categories[] = $p['category'];
    }
}
header('Content-Type: application/json');
echo json_encode(array_values($categories));
?>
