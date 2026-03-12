<?php
// File: save_post.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/audit.php';
require_login();
verify_csrf_token();
require_editor();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request payload.']);
    exit;
}

function respond_with_error(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

$title = trim((string)($payload['title'] ?? ''));
$content = isset($payload['content']) ? (string)$payload['content'] : '';
$category = trim((string)($payload['category'] ?? ''));
$author = trim((string)($payload['author'] ?? ''));
$status = strtolower(trim((string)($payload['status'] ?? 'draft')));
$slug = trim((string)($payload['slug'] ?? ''));
$excerpt = isset($payload['excerpt']) ? trim((string)$payload['excerpt']) : '';
$tagsRaw = $payload['tags'] ?? '';
$publishDateRaw = isset($payload['publishDate']) ? trim((string)$payload['publishDate']) : '';
$image = trim((string)($payload['image'] ?? ''));
$imageAlt = trim((string)($payload['imageAlt'] ?? ''));

if ($title === '') {
    respond_with_error(422, 'Title is required.');
}
if ($content === '') {
    respond_with_error(422, 'Content is required.');
}
if ($category === '') {
    respond_with_error(422, 'Category is required.');
}
if ($author === '') {
    respond_with_error(422, 'Author is required.');
}

$allowedStatuses = ['draft', 'published', 'scheduled'];
if (!in_array($status, $allowedStatuses, true)) {
    respond_with_error(422, 'Invalid status value.');
}

if ($slug === '') {
    $slug = preg_replace('~[^a-z0-9]+~i', '-', strtolower($title));
    $slug = trim((string)$slug, '-');
}

$tags = '';
if (is_array($tagsRaw)) {
    $tagsList = array_filter(array_map('trim', $tagsRaw), static function ($value) {
        return $value !== '';
    });
    $tags = implode(', ', $tagsList);
} else {
    $tags = trim((string)$tagsRaw);
}

$publishDate = $publishDateRaw;
if ($status === 'published') {
    $timestamp = strtotime($publishDateRaw);
    if ($publishDateRaw === '' || $timestamp === false || $timestamp > time()) {
        $publishDate = date('c');
    }
} elseif ($publishDateRaw !== '') {
    $timestamp = strtotime($publishDateRaw);
    if ($timestamp === false) {
        $publishDate = '';
    }
}

$postId = $payload['id'] ?? null;
$postId = is_numeric($postId) ? (int)$postId : null;

$postsFile = __DIR__ . '/../../data/blog_posts.json';
$posts = read_json_file($postsFile);
if (!is_array($posts)) {
    $posts = [];
}

$existingIndex = null;
if ($postId !== null && $postId > 0) {
    foreach ($posts as $index => $post) {
        if ((int)($post['id'] ?? 0) === $postId) {
            $existingIndex = $index;
            break;
        }
    }
    if ($existingIndex === null) {
        respond_with_error(404, 'Post not found.');
    }
}

if ($existingIndex === null) {
    $maxId = 0;
    foreach ($posts as $post) {
        $idValue = isset($post['id']) ? (int)$post['id'] : 0;
        if ($idValue > $maxId) {
            $maxId = $idValue;
        }
    }
    $postId = $maxId + 1;
    $createdAt = date('c');
    $record = [
        'id' => $postId,
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $content,
        'category' => $category,
        'author' => $author,
        'status' => $status,
        'publishDate' => $publishDate,
        'tags' => $tags,
        'image' => $image,
        'imageAlt' => $imageAlt,
        'createdAt' => $createdAt,
    ];
    $posts[] = $record;
} else {
    $existing = $posts[$existingIndex];
    $createdAt = isset($existing['createdAt']) && $existing['createdAt'] !== ''
        ? (string)$existing['createdAt']
        : date('c');
    $record = array_merge($existing, [
        'id' => $postId,
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $content,
        'category' => $category,
        'author' => $author,
        'status' => $status,
        'publishDate' => $publishDate,
        'tags' => $tags,
        'image' => $image,
        'imageAlt' => $imageAlt,
        'createdAt' => $createdAt,
    ]);
    $posts[$existingIndex] = $record;
}

if (!write_json_file($postsFile, array_values($posts))) {
    respond_with_error(500, 'Failed to save blog post.');
}

// Feature 16: Regenerate sitemap when a post is published (or was published before this edit).
if ($status === 'published' || (isset($existing['status']) && $existing['status'] === 'published')) {
    try {
        require_once __DIR__ . '/../../includes/sitemap_helpers.php';
        sparkcms_regenerate_sitemap(realpath(__DIR__ . '/../../') ?: __DIR__ . '/../../');
    } catch (Throwable $_e) { /* non-critical */ }
}

// Feature 17: Update the full-text search index.
try {
    require_once __DIR__ . '/../../includes/db.php';
    if (is_database_configured()) {
        require_once __DIR__ . '/../../includes/search_index.php';
        $_body = $excerpt . ' ' . $tags . ' ' . strip_tags($content);
        sparkcms_index_record(get_db_connection(), 'post', $postId, $title, $_body, $slug, $status === 'published');
    }
} catch (Throwable $_e) { /* non-critical */ }

// Feature 18: Audit log.
$_isNew = ($existingIndex === null);
sparkcms_audit(
    $_isNew ? 'Created post' : 'Updated post',
    'post',
    $title,
    [
        'Status: ' . $status,
        'Author: ' . $author,
        'Category: ' . $category,
    ]
);

echo json_encode($record);
?>
