<?php
// File: get_usage.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();

header('Content-Type: application/json');

$id = sanitize_text($_GET['id'] ?? '');
if ($id === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing media identifier.'
    ]);
    exit;
}

$mediaFile = __DIR__ . '/../../data/media.json';
$media = read_json_file($mediaFile);
$mediaItem = null;
foreach ($media as $item) {
    if (($item['id'] ?? '') === $id) {
        $mediaItem = $item;
        break;
    }
}

if (!$mediaItem) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Media item not found.'
    ]);
    exit;
}

$filePath = trim((string)($mediaItem['file'] ?? ''));
if ($filePath === '') {
    echo json_encode([
        'status' => 'success',
        'file' => '',
        'usage' => []
    ]);
    exit;
}

$needles = [$filePath];
if ($filePath[0] !== '/') {
    $needles[] = '/' . $filePath;
}
$needles = array_values(array_unique(array_filter($needles)));

$root = dirname(__DIR__, 2);
$dataDir = $root . '/data';
$usage = [];

$sources = [
    [
        'file' => $dataDir . '/pages.json',
        'type' => 'Page',
        'describe' => function(array $page): array {
            $name = $page['title'] ?? ($page['slug'] ?? ('Page #' . ($page['id'] ?? '?')));
            $details = [];
            if (!empty($page['slug'])) {
                $details[] = 'Slug: ' . $page['slug'];
            }
            if (isset($page['id'])) {
                $details[] = 'ID: ' . $page['id'];
            }
            return [
                'name' => $name,
                'detail' => implode(' • ', $details)
            ];
        }
    ],
    [
        'file' => $dataDir . '/blog_posts.json',
        'type' => 'Blog Post',
        'describe' => function(array $post): array {
            $name = $post['title'] ?? ($post['slug'] ?? ('Post #' . ($post['id'] ?? '?')));
            $details = [];
            if (!empty($post['slug'])) {
                $details[] = 'Slug: ' . $post['slug'];
            }
            if (!empty($post['status'])) {
                $details[] = 'Status: ' . ucfirst((string)$post['status']);
            }
            return [
                'name' => $name,
                'detail' => implode(' • ', $details)
            ];
        }
    ],
    [
        'file' => $dataDir . '/events.json',
        'type' => 'Event',
        'describe' => function(array $event): array {
            $name = $event['title'] ?? ($event['id'] ?? 'Event');
            $details = [];
            if (!empty($event['id'])) {
                $details[] = 'ID: ' . $event['id'];
            }
            if (!empty($event['status'])) {
                $details[] = 'Status: ' . ucfirst((string)$event['status']);
            }
            return [
                'name' => $name,
                'detail' => implode(' • ', $details)
            ];
        }
    ],
];

foreach ($sources as $source) {
    $records = read_json_file($source['file']);
    $usage = array_merge(
        $usage,
        collect_usage($records, $needles, $source['type'], $source['describe'])
    );
}

$settingsFile = $dataDir . '/settings.json';
$settings = read_json_file($settingsFile);
$matches = gather_matches($settings, $needles);
if (!empty($matches)) {
    $fields = format_fields($matches);
    $usage[] = [
        'type' => 'Settings',
        'name' => 'Site Settings',
        'details' => !empty($fields) ? 'Fields: ' . implode(', ', $fields) : null
    ];
}

usort($usage, function(array $a, array $b) {
    $typeCompare = strcasecmp($a['type'] ?? '', $b['type'] ?? '');
    if ($typeCompare !== 0) {
        return $typeCompare;
    }
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

echo json_encode([
    'status' => 'success',
    'file' => $filePath,
    'usage' => array_values(array_map(function(array $item) {
        if (!isset($item['details']) || $item['details'] === null || $item['details'] === '') {
            unset($item['details']);
        }
        return $item;
    }, $usage))
]);

function collect_usage($items, array $needles, string $type, callable $descriptor): array {
    $results = [];
    if (!is_array($items)) {
        return $results;
    }
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $matches = gather_matches($item, $needles);
        if (empty($matches)) {
            continue;
        }
        $fields = format_fields($matches);
        $meta = $descriptor($item);
        $entry = [
            'type' => $type,
            'name' => $meta['name'] ?? $type
        ];
        $detailParts = [];
        if (!empty($meta['detail'])) {
            $detailParts[] = $meta['detail'];
        }
        if (!empty($fields)) {
            $detailParts[] = 'Fields: ' . implode(', ', $fields);
        }
        if (!empty($detailParts)) {
            $entry['details'] = implode(' • ', $detailParts);
        }
        $results[] = $entry;
    }
    return $results;
}

function gather_matches($data, array $needles, string $path = ''): array {
    if (is_object($data)) {
        $data = (array) $data;
    }
    $matches = [];
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $childPath = $path === '' ? (string)$key : $path . '.' . $key;
            $matches = array_merge($matches, gather_matches($value, $needles, $childPath));
        }
    } elseif (is_string($data)) {
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($data, $needle) !== false) {
                $matches[] = $path;
                break;
            }
        }
    }
    return $matches;
}

function format_fields(array $paths): array {
    $fields = [];
    foreach ($paths as $path) {
        $fields[] = normalize_field($path);
    }
    $fields = array_unique(array_filter($fields));
    sort($fields, SORT_NATURAL | SORT_FLAG_CASE);
    return $fields;
}

function normalize_field(string $path): string {
    if ($path === '') {
        return 'value';
    }
    $parts = explode('.', $path);
    foreach ($parts as $part) {
        $part = (string)$part;
        if ($part === '' || ctype_digit($part)) {
            continue;
        }
        return $part;
    }
    return end($parts) ?: 'value';
}
