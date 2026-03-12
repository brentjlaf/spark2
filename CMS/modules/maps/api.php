<?php
// File: modules/maps/api.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/helpers.php';

require_login();

maps_ensure_storage();

$locations = maps_read_locations();
$categories = maps_read_categories();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list_locations';
$action = strtolower(trim((string) $action));

$writeActions = ['save_location', 'delete_location', 'reorder_locations', 'save_category', 'delete_category'];
if (in_array($action, $writeActions, true)) {
    verify_csrf_token();
    require_editor();
}

switch ($action) {
    case 'overview':
        handle_overview($locations, $categories);
        break;
    case 'list_locations':
        handle_list_locations($locations, $categories);
        break;
    case 'get_location':
        handle_get_location($locations, $categories);
        break;
    case 'save_location':
        handle_save_location($locations, $categories);
        break;
    case 'delete_location':
        handle_delete_location($locations, $categories);
        break;
    case 'reorder_locations':
        handle_reorder_locations($locations, $categories);
        break;
    case 'list_categories':
        handle_list_categories($categories, $locations);
        break;
    case 'save_category':
        handle_save_category($categories);
        break;
    case 'delete_category':
        handle_delete_category($categories, $locations);
        break;
    case 'geocode_address':
        handle_geocode_address();
        break;
    default:
        respond_json(['error' => 'Unknown action.'], 400);
}

function respond_json(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function parse_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function handle_overview(array $locations, array $categories): void
{
    $published = 0;
    $draft = 0;
    foreach ($locations as $location) {
        $status = strtolower((string) ($location['status'] ?? 'draft'));
        if ($status === 'published') {
            $published++;
        } else {
            $draft++;
        }
    }

    $grouped = maps_locations_grouped_by_category($locations);
    $categoryStats = [];
    foreach ($categories as $category) {
        $id = (string) ($category['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $categoryStats[] = [
            'id' => $id,
            'name' => $category['name'] ?? 'Category',
            'count' => isset($grouped[$id]) ? count($grouped[$id]) : 0,
            'color' => $category['color'] ?? '#666666',
        ];
    }
    $categoryStats[] = [
        'id' => 'uncategorized',
        'name' => 'Uncategorized',
        'count' => isset($grouped['uncategorized']) ? count($grouped['uncategorized']) : 0,
        'color' => '#9CA3AF',
    ];

    respond_json([
        'stats' => [
            'total' => count($locations),
            'published' => $published,
            'draft' => $draft,
            'categories' => count($categories),
        ],
        'categories' => $categoryStats,
    ]);
}

function handle_list_locations(array $locations, array $categories): void
{
    $categoryLookup = [];
    foreach ($categories as $category) {
        if (!is_array($category)) {
            continue;
        }
        $id = (string) ($category['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $categoryLookup[$id] = [
            'id' => $id,
            'name' => $category['name'] ?? 'Category',
            'slug' => $category['slug'] ?? '',
            'color' => $category['color'] ?? '#666666',
            'icon' => $category['icon'] ?? 'fa-location-dot',
        ];
    }

    $rows = [];
    foreach ($locations as $location) {
        if (!is_array($location)) {
            continue;
        }
        $id = (string) ($location['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $categoriesForLocation = [];
        $categoryIds = $location['category_ids'] ?? [];
        if (!is_array($categoryIds)) {
            $categoryIds = [];
        }
        foreach ($categoryIds as $categoryId) {
            $categoryId = (string) $categoryId;
            if ($categoryId === '' || !isset($categoryLookup[$categoryId])) {
                continue;
            }
            $categoriesForLocation[] = $categoryLookup[$categoryId];
        }
        $address = [
            'street' => $location['address']['street'] ?? '',
            'city' => $location['address']['city'] ?? '',
            'region' => $location['address']['region'] ?? '',
            'postal_code' => $location['address']['postal_code'] ?? '',
            'country' => $location['address']['country'] ?? '',
        ];

        $coordinates = [
            'lat' => null,
            'lng' => null,
        ];
        if (isset($location['coordinates']['lat']) && is_numeric($location['coordinates']['lat'])) {
            $coordinates['lat'] = (float) $location['coordinates']['lat'];
        }
        if (isset($location['coordinates']['lng']) && is_numeric($location['coordinates']['lng'])) {
            $coordinates['lng'] = (float) $location['coordinates']['lng'];
        }

        $rows[] = [
            'id' => $id,
            'name' => $location['name'] ?? 'Untitled location',
            'slug' => $location['slug'] ?? '',
            'status' => $location['status'] ?? 'draft',
            'updated_at' => $location['updated_at'] ?? '',
            'city' => $address['city'],
            'region' => $address['region'],
            'address' => $address,
            'coordinates' => $coordinates,
            'description' => $location['description'] ?? '',
            'categories' => $categoriesForLocation,
        ];
    }

    respond_json(['locations' => $rows]);
}

function handle_get_location(array $locations, array $categories): void
{
    $id = $_GET['id'] ?? '';
    $id = trim((string) $id);
    if ($id === '') {
        respond_json(['error' => 'Missing location id.'], 400);
    }

    $location = maps_find_location($locations, $id);
    if ($location === null) {
        respond_json(['error' => 'Location not found.'], 404);
    }

    $categoryLookup = [];
    foreach ($categories as $category) {
        if (!is_array($category)) {
            continue;
        }
        $categoryLookup[(string) ($category['id'] ?? '')] = $category;
    }

    $location['categories'] = [];
    $categoryIds = $location['category_ids'] ?? [];
    if (is_array($categoryIds)) {
        foreach ($categoryIds as $categoryId) {
            $categoryId = (string) $categoryId;
            if ($categoryId === '' || !isset($categoryLookup[$categoryId])) {
                continue;
            }
            $location['categories'][] = $categoryLookup[$categoryId];
        }
    }

    respond_json(['location' => $location]);
}

function handle_save_location(array $locations, array $categories): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }

    $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
    $existing = $id !== '' ? maps_find_location($locations, $id) : null;

    $name = isset($payload['name']) ? sanitize_text((string) $payload['name']) : '';
    if ($name === '') {
        respond_json(['error' => 'Name is required.'], 422);
    }

    $status = strtolower(sanitize_text((string) ($payload['status'] ?? 'draft')));
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    $slugInput = isset($payload['slug']) ? sanitize_text((string) $payload['slug']) : '';
    $slug = maps_unique_location_slug($slugInput !== '' ? $slugInput : $name, $locations, $existing['id'] ?? null);

    $address = [
        'street' => sanitize_text((string) ($payload['address']['street'] ?? '')),
        'city' => sanitize_text((string) ($payload['address']['city'] ?? '')),
        'region' => sanitize_text((string) ($payload['address']['region'] ?? '')),
        'postal_code' => sanitize_text((string) ($payload['address']['postal_code'] ?? '')),
        'country' => sanitize_text((string) ($payload['address']['country'] ?? '')),
    ];

    $coordinates = [
        'lat' => null,
        'lng' => null,
    ];
    if (isset($payload['coordinates']['lat']) && is_numeric($payload['coordinates']['lat'])) {
        $coordinates['lat'] = max(-90, min(90, (float) $payload['coordinates']['lat']));
    }
    if (isset($payload['coordinates']['lng']) && is_numeric($payload['coordinates']['lng'])) {
        $coordinates['lng'] = max(-180, min(180, (float) $payload['coordinates']['lng']));
    }

    $contact = [
        'phone' => sanitize_text((string) ($payload['contact']['phone'] ?? '')),
        'email' => filter_var(($payload['contact']['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? strtolower((string) $payload['contact']['email']) : '',
        'website' => sanitize_url((string) ($payload['contact']['website'] ?? '')),
    ];

    $tags = [];
    if (isset($payload['tags'])) {
        if (is_string($payload['tags'])) {
            $tags = array_filter(array_map('sanitize_text', array_map('trim', explode(',', $payload['tags']))));
        } elseif (is_array($payload['tags'])) {
            $tags = sanitize_tags($payload['tags']);
        }
    }

    $categoryIds = [];
    $provided = $payload['category_ids'] ?? [];
    if (is_string($provided)) {
        $provided = array_filter(array_map('trim', explode(',', $provided)));
    }
    if (is_array($provided)) {
        $validIds = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $validIds[(string) ($category['id'] ?? '')] = true;
        }
        foreach ($provided as $categoryId) {
            $categoryId = (string) $categoryId;
            if ($categoryId === '' || !isset($validIds[$categoryId])) {
                continue;
            }
            if (!in_array($categoryId, $categoryIds, true)) {
                $categoryIds[] = $categoryId;
            }
        }
    }

    $imageIds = [];
    if (isset($payload['image_ids'])) {
        if (is_string($payload['image_ids'])) {
            $imageIds = array_filter(array_map('trim', explode(',', $payload['image_ids'])));
        } elseif (is_array($payload['image_ids'])) {
            foreach ($payload['image_ids'] as $imageId) {
                $imageId = trim((string) $imageId);
                if ($imageId === '') {
                    continue;
                }
                $imageIds[] = $imageId;
            }
        }
    }

    $description = isset($payload['description']) ? trim((string) $payload['description']) : '';
    $hours = isset($payload['hours']) ? trim((string) $payload['hours']) : '';
    $accessibility = isset($payload['accessibility_notes']) ? trim((string) $payload['accessibility_notes']) : '';

    $now = date(DATE_ATOM);

    if ($existing !== null) {
        $existing['name'] = $name;
        $existing['slug'] = $slug;
        $existing['status'] = $status;
        $existing['description'] = $description;
        $existing['address'] = $address;
        $existing['coordinates'] = $coordinates;
        $existing['contact'] = $contact;
        $existing['category_ids'] = $categoryIds;
        $existing['image_ids'] = array_values($imageIds);
        $existing['tags'] = array_values($tags);
        $existing['hours'] = $hours;
        $existing['accessibility_notes'] = $accessibility;
        $existing['updated_at'] = $now;
        if (empty($existing['created_at'])) {
            $existing['created_at'] = $now;
        }
        foreach ($locations as $index => $location) {
            if ((string) ($location['id'] ?? '') === $existing['id']) {
                $locations[$index] = $existing;
                break;
            }
        }
    } else {
        $newLocation = [
            'id' => maps_generate_id('map_loc'),
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'description' => $description,
            'address' => $address,
            'coordinates' => $coordinates,
            'contact' => $contact,
            'category_ids' => $categoryIds,
            'image_ids' => array_values($imageIds),
            'tags' => array_values($tags),
            'hours' => $hours,
            'accessibility_notes' => $accessibility,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $locations[] = $newLocation;
    }

    if (!maps_write_locations($locations)) {
        respond_json(['error' => 'Failed to save locations.'], 500);
    }

    handle_list_locations(maps_read_locations(), $categories);
}

function handle_delete_location(array $locations, array $categories): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }

    $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
    if ($id === '') {
        respond_json(['error' => 'Missing location id.'], 400);
    }

    $updated = [];
    $found = false;
    foreach ($locations as $location) {
        if (!is_array($location)) {
            continue;
        }
        if ((string) ($location['id'] ?? '') === $id) {
            $found = true;
            continue;
        }
        $updated[] = $location;
    }

    if (!$found) {
        respond_json(['error' => 'Location not found.'], 404);
    }

    if (!maps_write_locations($updated)) {
        respond_json(['error' => 'Unable to delete location.'], 500);
    }

    handle_list_locations(maps_read_locations(), $categories);
}

function handle_geocode_address(): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_GET;
    }

    $query = isset($payload['query']) ? trim((string) $payload['query']) : '';
    if ($query === '') {
        respond_json(['error' => 'Provide an address to look up.'], 422);
    }

    $coordinates = maps_geocode_address($query);
    if ($coordinates === null) {
        respond_json(['error' => 'Unable to determine coordinates for the provided address.'], 404);
    }

    respond_json(['coordinates' => $coordinates]);
}

function handle_reorder_locations(array $locations, array $categories): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }

    $order = $payload['order'] ?? [];
    if (!is_array($order)) {
        respond_json(['error' => 'Invalid order payload.'], 422);
    }

    $lookup = [];
    foreach ($locations as $location) {
        if (!is_array($location)) {
            continue;
        }
        $lookup[(string) ($location['id'] ?? '')] = $location;
    }

    $newList = [];
    foreach ($order as $id) {
        $id = (string) $id;
        if ($id === '' || !isset($lookup[$id])) {
            continue;
        }
        $newList[] = $lookup[$id];
        unset($lookup[$id]);
    }

    foreach ($lookup as $remaining) {
        $newList[] = $remaining;
    }

    if (!maps_write_locations($newList)) {
        respond_json(['error' => 'Failed to save new order.'], 500);
    }

    handle_list_locations(maps_read_locations(), $categories);
}

function handle_list_categories(array $categories, array $locations): void
{
    $counts = maps_locations_grouped_by_category($locations);
    $rows = [];
    foreach ($categories as $category) {
        if (!is_array($category)) {
            continue;
        }
        $id = (string) ($category['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $rows[] = [
            'id' => $id,
            'name' => $category['name'] ?? 'Category',
            'slug' => $category['slug'] ?? '',
            'color' => $category['color'] ?? '#666666',
            'icon' => $category['icon'] ?? 'fa-location-dot',
            'sort_order' => (int) ($category['sort_order'] ?? 0),
            'is_default' => !empty($category['is_default']),
            'count' => isset($counts[$id]) ? count($counts[$id]) : 0,
            'is_virtual' => false,
        ];
    }

    $rows[] = [
        'id' => 'uncategorized',
        'name' => 'Uncategorized',
        'slug' => '',
        'color' => '#9CA3AF',
        'icon' => 'fa-circle-exclamation',
        'sort_order' => PHP_INT_MAX,
        'is_default' => true,
        'count' => isset($counts['uncategorized']) ? count($counts['uncategorized']) : 0,
        'is_virtual' => true,
    ];

    respond_json(['categories' => $rows]);
}

function handle_save_category(array $categories): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }

    $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
    $existing = $id !== '' ? maps_find_category($categories, $id) : null;

    $name = isset($payload['name']) ? sanitize_text((string) $payload['name']) : '';
    if ($name === '') {
        respond_json(['error' => 'Category name is required.'], 422);
    }

    $slugInput = isset($payload['slug']) ? sanitize_text((string) $payload['slug']) : '';
    $slug = maps_unique_category_slug($slugInput !== '' ? $slugInput : $name, $categories, $existing['id'] ?? null);

    $color = isset($payload['color']) ? trim((string) $payload['color']) : '#666666';
    if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
        $color = '#666666';
    }
    $icon = isset($payload['icon']) ? sanitize_text((string) $payload['icon']) : 'fa-location-dot';
    $sortOrder = isset($payload['sort_order']) ? (int) $payload['sort_order'] : (count($categories) + 1);

    if ($existing !== null) {
        $existing['name'] = $name;
        $existing['slug'] = $slug;
        $existing['color'] = $color;
        $existing['icon'] = $icon;
        $existing['sort_order'] = $sortOrder;
        foreach ($categories as $index => $category) {
            if ((string) ($category['id'] ?? '') === $existing['id']) {
                $categories[$index] = $existing;
                break;
            }
        }
    } else {
        $categories[] = [
            'id' => maps_generate_id('map_cat'),
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_default' => false,
        ];
    }

    if (!maps_write_categories($categories)) {
        respond_json(['error' => 'Failed to save category.'], 500);
    }

    handle_list_categories(maps_read_categories(), maps_read_locations());
}

function handle_delete_category(array $categories, array $locations): void
{
    $payload = parse_json_body();
    if (empty($payload)) {
        $payload = $_POST;
    }

    $id = isset($payload['id']) ? trim((string) $payload['id']) : '';
    if ($id === '') {
        respond_json(['error' => 'Missing category id.'], 400);
    }

    $category = maps_find_category($categories, $id);
    if ($category === null) {
        respond_json(['error' => 'Category not found.'], 404);
    }

    if (!empty($category['is_default'])) {
        respond_json(['error' => 'Default categories cannot be deleted.'], 403);
    }

    foreach ($locations as $location) {
        if (!is_array($location)) {
            continue;
        }
        $categoryIds = $location['category_ids'] ?? [];
        if (!is_array($categoryIds)) {
            continue;
        }
        foreach ($categoryIds as $categoryId) {
            if ((string) $categoryId === $id) {
                respond_json(['error' => 'Category is assigned to one or more locations.'], 409);
            }
        }
    }

    $updated = [];
    foreach ($categories as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        if ((string) ($existing['id'] ?? '') === $id) {
            continue;
        }
        $updated[] = $existing;
    }

    if (!maps_write_categories($updated)) {
        respond_json(['error' => 'Unable to delete category.'], 500);
    }

    handle_list_categories(maps_read_categories(), $locations);
}
