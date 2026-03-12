<?php
// File: api/v1/index.php  –  SparkCMS REST API v1 front controller
//
// Clean-URL routing via CMS/api/.htaccess (mod_rewrite).
// Supports: Authorization: Bearer <key>  |  X-API-Key: <key>  |  ?api_key=<key>
//
// Resources (public = no auth needed for published content):
//   GET    /api/v1/posts[/{id}]        POST /api/v1/posts
//   PUT    /api/v1/posts/{id}          DELETE /api/v1/posts/{id}
//   GET    /api/v1/pages[/{id}]        POST /api/v1/pages
//   PUT    /api/v1/pages/{id}          DELETE /api/v1/pages/{id}
//   GET    /api/v1/media[/{id}]        (read key required)
//   GET    /api/v1/events[/{id}]       (public)
//   GET    /api/v1/locations[/{id}]    (public)
//   GET    /api/v1/commerce[/{id}]     POST/PUT/DELETE (write/delete key required)

$cmsRoot = dirname(__DIR__, 2);
require_once $cmsRoot . '/includes/data.php';
require_once $cmsRoot . '/includes/sanitize.php';
require_once $cmsRoot . '/includes/api_auth.php';

// ── CORS ─────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type');
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Route parsing ─────────────────────────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip everything up to (and including) /api/v1  e.g. /SparkCMS/CMS/api/v1/posts/3
$path     = preg_replace('#^.*?/api/v1/?#i', '', $uri);
$path     = trim($path, '/');
$segments = $path !== '' ? explode('/', $path) : [];
$resource = strtolower($segments[0] ?? '');
$id       = isset($segments[1]) && $segments[1] !== '' ? $segments[1] : null;

// ── Authentication ────────────────────────────────────────────────────────────
$apiKey = sparkcms_authenticate_api();

// ── Root discovery endpoint ───────────────────────────────────────────────────
if ($resource === '') {
    respond_json([
        'api'       => 'SparkCMS REST API',
        'version'   => 'v1',
        'resources' => [
            'posts'     => api_url('posts'),
            'pages'     => api_url('pages'),
            'media'     => api_url('media'),
            'events'    => api_url('events'),
            'locations' => api_url('locations'),
            'commerce'  => api_url('commerce'),
        ],
        'auth' => 'Pass your API key via  Authorization: Bearer <key>  or  X-API-Key: <key>',
    ]);
}

// ── Route dispatch ────────────────────────────────────────────────────────────
switch ($resource) {
    case 'posts':     handle_posts($method, $id, $apiKey, $cmsRoot);     break;
    case 'pages':     handle_pages($method, $id, $apiKey, $cmsRoot);     break;
    case 'media':     handle_media($method, $id, $apiKey, $cmsRoot);     break;
    case 'events':    handle_events($method, $id, $apiKey, $cmsRoot);    break;
    case 'locations': handle_locations($method, $id, $apiKey, $cmsRoot); break;
    case 'commerce':  handle_commerce($method, $id, $apiKey, $cmsRoot);  break;
    default:
        respond_error(404, 'not_found', "Unknown resource '{$resource}'. Available: posts, pages, media, events, locations, commerce.");
}

// ─────────────────────────────────────────────────────────────────────────────
// SHARED HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function respond_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function respond_error(int $status, string $code, string $message): never {
    respond_json(['error' => $message, 'code' => $code], $status);
}

function method_not_allowed(array $allowed): never {
    header('Allow: ' . implode(', ', $allowed));
    respond_error(405, 'method_not_allowed',
        'Method not allowed. Accepted methods: ' . implode(', ', $allowed));
}

/** Require the key to have a specific permission; send 401/403 otherwise. */
function api_require_permission(?array $key, string $perm): void {
    if ($key === null) {
        respond_error(401, 'unauthorized',
            'Authentication required. Provide a valid API key via the ' .
            '"Authorization: Bearer <key>" header or "X-API-Key: <key>" header.');
    }
    if (!in_array($perm, $key['permissions'] ?? [], true)) {
        respond_error(403, 'forbidden',
            "This API key does not have \"{$perm}\" permission.");
    }
}

function parse_pagination(): array {
    $page    = max(1, (int)($_GET['page']     ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    return [$page, $perPage];
}

function paginate(array $items, int $page, int $perPage): array {
    $total      = count($items);
    $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 0;
    $slice      = array_values(array_slice($items, ($page - 1) * $perPage, $perPage));
    return [
        'data' => $slice,
        'meta' => [
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
        ],
    ];
}

function read_body(): array {
    $raw     = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    return is_array($payload) ? $payload : [];
}

function api_url(string $resource): string {
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $script = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $prefix = preg_replace('#/api/v1.*#i', '', $script);
    return $base . $prefix . '/api/v1/' . $resource;
}

/** True if the request carries a valid API key with the given permission. */
function has_permission(?array $key, string $perm): bool {
    return $key !== null && in_array($perm, $key['permissions'] ?? [], true);
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOURCE: posts
// ─────────────────────────────────────────────────────────────────────────────
function handle_posts(string $method, ?string $id, ?array $apiKey, string $root): void {
    $file  = $root . '/data/blog_posts.json';
    $posts = read_json_file($file);
    if (!is_array($posts)) {
        $posts = [];
    }

    // ── Single-resource endpoints ──────────────────────────────────────────
    if ($id !== null) {
        $post = null;
        foreach ($posts as $p) {
            if ((string)($p['id'] ?? '') === $id) {
                $post = $p;
                break;
            }
        }
        if ($post === null) {
            respond_error(404, 'not_found', "Post {$id} not found.");
        }

        $isPublished = ($post['status'] ?? '') === 'published';

        switch ($method) {
            case 'GET':
                if (!$isPublished) {
                    api_require_permission($apiKey, 'read');
                }
                respond_json(['data' => $post]);

            case 'PUT':
                api_require_permission($apiKey, 'write');
                update_post($posts, $id, read_body(), $file);

            case 'DELETE':
                api_require_permission($apiKey, 'delete');
                $posts = array_values(
                    array_filter($posts, fn($p) => (string)($p['id'] ?? '') !== $id)
                );
                write_json_file($file, $posts);
                respond_json(['data' => null], 204);

            default:
                method_not_allowed(['GET', 'PUT', 'DELETE']);
        }
    }

    // ── Collection endpoints ───────────────────────────────────────────────
    switch ($method) {
        case 'GET':
            $fullRead = has_permission($apiKey, 'read');
            $status   = strtolower(trim($_GET['status']   ?? ''));
            $category = strtolower(trim($_GET['category'] ?? ''));
            $search   = strtolower(trim($_GET['search']   ?? ''));

            $results = array_values(array_filter($posts, function ($p) use ($fullRead, $status, $category, $search) {
                if (!$fullRead && ($p['status'] ?? '') !== 'published') {
                    return false;
                }
                if ($status   !== '' && strtolower($p['status']   ?? '') !== $status)   return false;
                if ($category !== '' && strtolower($p['category'] ?? '') !== $category) return false;
                if ($search   !== '') {
                    $hay = strtolower(($p['title'] ?? '') . ' ' . ($p['excerpt'] ?? '') . ' ' . ($p['author'] ?? ''));
                    if (strpos($hay, $search) === false) return false;
                }
                return true;
            }));

            // Sort newest first
            usort($results, fn($a, $b) =>
                strtotime($b['publishDate'] ?? '0') <=> strtotime($a['publishDate'] ?? '0')
            );

            [$page, $perPage] = parse_pagination();
            respond_json(paginate($results, $page, $perPage));

        case 'POST':
            api_require_permission($apiKey, 'write');
            create_post($posts, read_body(), $file);

        default:
            method_not_allowed(['GET', 'POST']);
    }
}

function update_post(array &$posts, string $id, array $body, string $file): void {
    foreach ($posts as &$p) {
        if ((string)($p['id'] ?? '') !== $id) continue;

        if (isset($body['title']))       $p['title']       = sanitize_text($body['title']);
        if (isset($body['slug']))        $p['slug']        = sanitize_text($body['slug']);
        if (isset($body['excerpt']))     $p['excerpt']     = sanitize_text($body['excerpt']);
        if (isset($body['content']))     $p['content']     = $body['content'];
        if (isset($body['category']))    $p['category']    = sanitize_text($body['category']);
        if (isset($body['author']))      $p['author']      = sanitize_text($body['author']);
        if (isset($body['image']))       $p['image']       = sanitize_url($body['image']);
        if (isset($body['imageAlt']))    $p['imageAlt']    = sanitize_text($body['imageAlt']);
        if (isset($body['publishDate'])) $p['publishDate'] = sanitize_text($body['publishDate']);
        if (isset($body['status']) && in_array($body['status'], ['draft', 'published', 'scheduled'], true)) {
            $p['status'] = $body['status'];
        }
        if (isset($body['tags'])) {
            $p['tags'] = is_array($body['tags'])
                ? array_map('sanitize_text', $body['tags'])
                : array_map('trim', explode(',', sanitize_text($body['tags'])));
        }

        write_json_file($file, $posts);
        respond_json(['data' => $p]);
    }
    unset($p);
    respond_error(404, 'not_found', 'Post not found.');
}

function create_post(array $posts, array $body, string $file): void {
    $title = sanitize_text($body['title'] ?? '');
    if ($title === '') {
        respond_error(422, 'validation_error', '"title" is required.');
    }

    $maxId = 0;
    foreach ($posts as $p) {
        $maxId = max($maxId, (int)($p['id'] ?? 0));
    }

    $status  = in_array($body['status'] ?? '', ['draft', 'published', 'scheduled'], true)
               ? $body['status'] : 'draft';
    $now     = date('c');
    $post    = [
        'id'          => $maxId + 1,
        'title'       => $title,
        'slug'        => sanitize_text($body['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title))),
        'excerpt'     => sanitize_text($body['excerpt'] ?? ''),
        'content'     => $body['content'] ?? '',
        'category'    => sanitize_text($body['category'] ?? ''),
        'author'      => sanitize_text($body['author'] ?? ''),
        'status'      => $status,
        'tags'        => is_array($body['tags'] ?? null) ? array_map('sanitize_text', $body['tags']) : [],
        'publishDate' => sanitize_text($body['publishDate'] ?? $now),
        'image'       => sanitize_url($body['image'] ?? ''),
        'imageAlt'    => sanitize_text($body['imageAlt'] ?? ''),
        'created_at'  => $now,
    ];

    $posts[] = $post;
    write_json_file($file, $posts);
    respond_json(['data' => $post], 201);
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOURCE: pages
// ─────────────────────────────────────────────────────────────────────────────
function handle_pages(string $method, ?string $id, ?array $apiKey, string $root): void {
    $file  = $root . '/data/pages.json';
    $pages = read_json_file($file);
    if (!is_array($pages)) {
        $pages = [];
    }

    if ($id !== null) {
        $page = null;
        foreach ($pages as $p) {
            if ((string)($p['id'] ?? '') === $id) {
                $page = $p;
                break;
            }
        }
        if ($page === null) {
            respond_error(404, 'not_found', "Page {$id} not found.");
        }

        $isPublished = (bool)($page['published'] ?? false);

        switch ($method) {
            case 'GET':
                if (!$isPublished) {
                    api_require_permission($apiKey, 'read');
                }
                respond_json(['data' => $page]);

            case 'PUT':
                api_require_permission($apiKey, 'write');
                update_page($pages, $id, read_body(), $file);

            case 'DELETE':
                api_require_permission($apiKey, 'delete');
                $pages = array_values(
                    array_filter($pages, fn($p) => (string)($p['id'] ?? '') !== $id)
                );
                write_json_file($file, $pages);
                respond_json(['data' => null], 204);

            default:
                method_not_allowed(['GET', 'PUT', 'DELETE']);
        }
    }

    switch ($method) {
        case 'GET':
            $fullRead = has_permission($apiKey, 'read');
            $search   = strtolower(trim($_GET['search'] ?? ''));

            $results = array_values(array_filter($pages, function ($p) use ($fullRead, $search) {
                if (!$fullRead && !($p['published'] ?? false)) return false;
                if ($search !== '') {
                    $hay = strtolower(($p['title'] ?? '') . ' ' . ($p['slug'] ?? ''));
                    if (strpos($hay, $search) === false) return false;
                }
                return true;
            }));

            [$page, $perPage] = parse_pagination();
            respond_json(paginate($results, $page, $perPage));

        case 'POST':
            api_require_permission($apiKey, 'write');
            create_page($pages, read_body(), $file);

        default:
            method_not_allowed(['GET', 'POST']);
    }
}

function update_page(array &$pages, string $id, array $body, string $file): void {
    foreach ($pages as &$p) {
        if ((string)($p['id'] ?? '') !== $id) continue;

        if (isset($body['title']))            $p['title']            = sanitize_text($body['title']);
        if (isset($body['slug']))             $p['slug']             = sanitize_text($body['slug']);
        if (isset($body['content']))          $p['content']          = $body['content'];
        if (array_key_exists('published', $body)) $p['published']    = (bool)$body['published'];
        if (isset($body['template']))         $p['template']         = sanitize_text($body['template']);
        if (isset($body['meta_title']))       $p['meta_title']       = sanitize_text($body['meta_title']);
        if (isset($body['meta_description'])) $p['meta_description'] = sanitize_text($body['meta_description']);
        $p['last_modified'] = time();

        write_json_file($file, $pages);
        respond_json(['data' => $p]);
    }
    unset($p);
    respond_error(404, 'not_found', 'Page not found.');
}

function create_page(array $pages, array $body, string $file): void {
    $title = sanitize_text($body['title'] ?? '');
    if ($title === '') {
        respond_error(422, 'validation_error', '"title" is required.');
    }

    $maxId = 0;
    foreach ($pages as $p) {
        $maxId = max($maxId, (int)($p['id'] ?? 0));
    }

    $slug = sanitize_text($body['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
    }

    $page = [
        'id'               => $maxId + 1,
        'title'            => $title,
        'slug'             => $slug,
        'content'          => $body['content'] ?? '',
        'published'        => (bool)($body['published'] ?? false),
        'template'         => sanitize_text($body['template'] ?? 'page.php'),
        'meta_title'       => sanitize_text($body['meta_title'] ?? ''),
        'meta_description' => sanitize_text($body['meta_description'] ?? ''),
        'views'            => 0,
        'last_modified'    => time(),
    ];

    $pages[] = $page;
    write_json_file($file, $pages);
    respond_json(['data' => $page], 201);
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOURCE: media
// ─────────────────────────────────────────────────────────────────────────────
function handle_media(string $method, ?string $id, ?array $apiKey, string $root): void {
    // Media always requires at minimum read permission
    api_require_permission($apiKey, 'read');
    if ($method !== 'GET') {
        method_not_allowed(['GET']);
    }

    $file  = $root . '/data/media.json';
    $media = read_json_file($file);
    if (!is_array($media)) {
        $media = [];
    }

    if ($id !== null) {
        foreach ($media as $m) {
            if ((string)($m['id'] ?? '') === $id) {
                respond_json(['data' => $m]);
            }
        }
        respond_error(404, 'not_found', "Media item {$id} not found.");
    }

    $folder = trim($_GET['folder'] ?? '');
    $type   = strtolower(trim($_GET['type']   ?? ''));
    $search = strtolower(trim($_GET['search'] ?? ''));

    $results = array_values(array_filter($media, function ($m) use ($folder, $type, $search) {
        if ($folder !== '' && ($m['folder'] ?? '') !== $folder) return false;
        if ($type   !== '' && strtolower($m['type'] ?? '') !== $type)  return false;
        if ($search !== '' && strpos(strtolower($m['name'] ?? ''), $search) === false) return false;
        return true;
    }));

    [$page, $perPage] = parse_pagination();
    respond_json(paginate($results, $page, $perPage));
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOURCE: events
// ─────────────────────────────────────────────────────────────────────────────
function handle_events(string $method, ?string $id, ?array $apiKey, string $root): void {
    if ($method !== 'GET') {
        method_not_allowed(['GET']);
    }

    $file   = $root . '/data/events.json';
    $events = read_json_file($file);
    if (!is_array($events)) {
        $events = [];
    }

    if ($id !== null) {
        foreach ($events as $e) {
            if ((string)($e['id'] ?? '') === $id) {
                respond_json(['data' => $e]);
            }
        }
        respond_error(404, 'not_found', "Event {$id} not found.");
    }

    [$page, $perPage] = parse_pagination();
    respond_json(paginate($events, $page, $perPage));
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOURCE: locations
// ─────────────────────────────────────────────────────────────────────────────
function handle_locations(string $method, ?string $id, ?array $apiKey, string $root): void {
    if ($method !== 'GET') {
        method_not_allowed(['GET']);
    }

    $file      = $root . '/data/map_locations.json';
    $locations = read_json_file($file);
    if (!is_array($locations)) {
        $locations = [];
    }

    if ($id !== null) {
        foreach ($locations as $l) {
            if ((string)($l['id'] ?? '') === $id) {
                respond_json(['data' => $l]);
            }
        }
        respond_error(404, 'not_found', "Location {$id} not found.");
    }

    [$page, $perPage] = parse_pagination();
    respond_json(paginate($locations, $page, $perPage));
}

// ─────────────────────────────────────────────────────────────────────────────
// RESOURCE: commerce
// ─────────────────────────────────────────────────────────────────────────────
function handle_commerce(string $method, ?string $id, ?array $apiKey, string $root): void {
    $file     = $root . '/data/commerce.json';
    $products = read_json_file($file);
    if (!is_array($products)) {
        $products = [];
    }

    // ── Single-resource endpoints ──────────────────────────────────────────
    if ($id !== null) {
        $product = null;
        foreach ($products as $p) {
            if ((string)($p['id'] ?? '') === $id) {
                $product = $p;
                break;
            }
        }
        if ($product === null) {
            respond_error(404, 'not_found', "Product {$id} not found.");
        }

        switch ($method) {
            case 'GET':
                // Active products are public; others require read permission
                if (($product['status'] ?? '') !== 'active') {
                    api_require_permission($apiKey, 'read');
                }
                respond_json(['data' => $product]);

            case 'PUT':
                api_require_permission($apiKey, 'write');
                commerce_update_product($products, $id, read_body(), $file);

            case 'DELETE':
                api_require_permission($apiKey, 'delete');
                $products = array_values(
                    array_filter($products, fn($p) => (string)($p['id'] ?? '') !== $id)
                );
                write_json_file($file, $products);
                respond_json(['data' => null], 204);

            default:
                method_not_allowed(['GET', 'PUT', 'DELETE']);
        }
    }

    // ── Collection endpoints ───────────────────────────────────────────────
    switch ($method) {
        case 'GET':
            $fullRead = has_permission($apiKey, 'read');
            $status   = strtolower(trim($_GET['status']   ?? ''));
            $category = strtolower(trim($_GET['category'] ?? ''));
            $search   = strtolower(trim($_GET['search']   ?? ''));

            $results = array_values(array_filter($products, function ($p) use ($fullRead, $status, $category, $search) {
                // Public callers only see active products
                if (!$fullRead && ($p['status'] ?? '') !== 'active') return false;
                if ($status   !== '' && strtolower($p['status']   ?? '') !== $status)   return false;
                if ($category !== '' && strtolower($p['category'] ?? '') !== $category) return false;
                if ($search   !== '') {
                    $hay = strtolower(($p['name'] ?? '') . ' ' . ($p['sku'] ?? '') . ' ' . ($p['description'] ?? ''));
                    if (strpos($hay, $search) === false) return false;
                }
                return true;
            }));

            [$page, $perPage] = parse_pagination();
            respond_json(paginate($results, $page, $perPage));

        case 'POST':
            api_require_permission($apiKey, 'write');
            commerce_create_product($products, read_body(), $file);

        default:
            method_not_allowed(['GET', 'POST']);
    }
}

function commerce_update_product(array &$products, string $id, array $body, string $file): void {
    foreach ($products as &$p) {
        if ((string)($p['id'] ?? '') !== $id) continue;

        if (isset($body['name']))          $p['name']          = sanitize_text($body['name']);
        if (isset($body['slug']))          $p['slug']          = sanitize_text($body['slug']);
        if (isset($body['sku']))           $p['sku']           = sanitize_text($body['sku']);
        if (isset($body['description']))   $p['description']   = (string)$body['description'];
        if (isset($body['category']))      $p['category']      = sanitize_text($body['category']);
        if (isset($body['tags']))          $p['tags']          = sanitize_text($body['tags']);
        if (isset($body['image']))         $p['image']         = sanitize_url($body['image']);
        if (is_numeric($body['price']          ?? null)) $p['price']          = round((float)$body['price'], 2);
        if (is_numeric($body['compare_price']  ?? null)) $p['compare_price']  = round((float)$body['compare_price'], 2);
        if (is_numeric($body['cost']           ?? null)) $p['cost']           = round((float)$body['cost'], 2);
        if (is_numeric($body['weight']         ?? null)) $p['weight']         = round((float)$body['weight'], 3);
        if (is_numeric($body['stock']          ?? null)) $p['stock']          = max(0, (int)$body['stock']);
        if (array_key_exists('track_stock', $body))      $p['track_stock']    = !empty($body['track_stock']);
        if (isset($body['status']) && in_array($body['status'], ['active', 'inactive', 'draft'], true)) {
            $p['status'] = $body['status'];
        }
        $p['updated_at'] = date('c');

        write_json_file($file, $products);
        respond_json(['data' => $p]);
    }
    unset($p);
    respond_error(404, 'not_found', 'Product not found.');
}

function commerce_create_product(array $products, array $body, string $file): void {
    $name = sanitize_text($body['name'] ?? '');
    if ($name === '') {
        respond_error(422, 'validation_error', '"name" is required.');
    }

    $maxId = 0;
    foreach ($products as $p) {
        $maxId = max($maxId, (int)($p['id'] ?? 0));
    }

    $slug = sanitize_text($body['slug'] ?? '');
    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-') ?: 'product';
    }

    $status = in_array($body['status'] ?? '', ['active', 'inactive', 'draft'], true)
              ? $body['status'] : 'active';
    $now    = date('c');

    $product = [
        'id'            => $maxId + 1,
        'name'          => $name,
        'slug'          => $slug,
        'sku'           => sanitize_text($body['sku']         ?? ''),
        'description'   => (string)($body['description']     ?? ''),
        'price'         => is_numeric($body['price']          ?? null) ? round((float)$body['price'], 2)         : 0.00,
        'compare_price' => is_numeric($body['compare_price']  ?? null) ? round((float)$body['compare_price'], 2) : 0.00,
        'cost'          => is_numeric($body['cost']           ?? null) ? round((float)$body['cost'], 2)          : 0.00,
        'weight'        => is_numeric($body['weight']         ?? null) ? round((float)$body['weight'], 3)        : 0.000,
        'stock'         => is_numeric($body['stock']          ?? null) ? max(0, (int)$body['stock'])             : 0,
        'track_stock'   => !empty($body['track_stock']),
        'status'        => $status,
        'category'      => sanitize_text($body['category']   ?? ''),
        'tags'          => sanitize_text($body['tags']       ?? ''),
        'image'         => sanitize_url($body['image']       ?? ''),
        'created_at'    => $now,
        'updated_at'    => $now,
    ];

    $products[] = $product;
    write_json_file($file, $products);
    respond_json(['data' => $product], 201);
}
