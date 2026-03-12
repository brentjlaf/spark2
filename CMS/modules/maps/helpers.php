<?php
// File: modules/maps/helpers.php
require_once __DIR__ . '/../../includes/data.php';

if (!function_exists('maps_data_paths')) {
    function maps_data_paths(): array
    {
        $baseDir = __DIR__ . '/../../data';
        return [
            'locations' => $baseDir . '/map_locations.json',
            'categories' => $baseDir . '/map_categories.json',
        ];
    }
}

if (!function_exists('maps_default_categories')) {
    function maps_default_categories(): array
    {
        return [
            [
                'id' => 'map_cat_general',
                'name' => 'General',
                'slug' => 'general',
                'color' => '#2D70F5',
                'icon' => 'fa-location-dot',
                'sort_order' => 1,
                'is_default' => true,
            ],
            [
                'id' => 'map_cat_office',
                'name' => 'Offices',
                'slug' => 'offices',
                'color' => '#00A389',
                'icon' => 'fa-building',
                'sort_order' => 2,
                'is_default' => true,
            ],
        ];
    }
}

if (!function_exists('maps_generate_id')) {
    function maps_generate_id(string $prefix = 'maps'): string
    {
        $prefix = trim($prefix) !== '' ? trim($prefix) : 'maps';
        try {
            $random = bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            $random = uniqid('', true);
            $random = str_replace('.', '', $random);
        }
        return strtolower($prefix) . '_' . $random;
    }
}

if (!function_exists('maps_slugify')) {
    function maps_slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = trim((string) $value, '-');
        return $value;
    }
}

if (!function_exists('maps_unique_category_slug')) {
    function maps_unique_category_slug(string $desired, array $categories, ?string $currentId = null): string
    {
        $slug = maps_slugify($desired);
        if ($slug === '') {
            $slug = 'category';
        }
        $base = $slug;
        $suffix = 2;
        $existing = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            if ($currentId !== null && $id === $currentId) {
                continue;
            }
            $key = strtolower((string) ($category['slug'] ?? ''));
            if ($key !== '') {
                $existing[$key] = true;
            }
        }
        $candidate = strtolower($slug);
        while ($candidate === '' || isset($existing[$candidate])) {
            $slug = $base . '-' . $suffix;
            $candidate = strtolower($slug);
            $suffix++;
        }
        return $slug;
    }
}

if (!function_exists('maps_unique_location_slug')) {
    function maps_unique_location_slug(string $desired, array $locations, ?string $currentId = null): string
    {
        $slug = maps_slugify($desired);
        if ($slug === '') {
            $slug = 'location';
        }
        $base = $slug;
        $suffix = 2;
        $existing = [];
        foreach ($locations as $location) {
            if (!is_array($location)) {
                continue;
            }
            $id = (string) ($location['id'] ?? '');
            if ($currentId !== null && $id === $currentId) {
                continue;
            }
            $key = strtolower((string) ($location['slug'] ?? ''));
            if ($key !== '') {
                $existing[$key] = true;
            }
        }
        $candidate = strtolower($slug);
        while ($candidate === '' || isset($existing[$candidate])) {
            $slug = $base . '-' . $suffix;
            $candidate = strtolower($slug);
            $suffix++;
        }
        return $slug;
    }
}

if (!function_exists('maps_ensure_storage')) {
    function maps_ensure_storage(): void
    {
        $paths = maps_data_paths();
        foreach ($paths as $key => $path) {
            if (!is_file($path)) {
                if ($key === 'categories') {
                    write_json_file($path, maps_default_categories());
                    continue;
                }
                file_put_contents($path, "[]\n");
                continue;
            }
            if ($key === 'categories') {
                $data = read_json_file($path);
                if (!is_array($data) || empty($data)) {
                    write_json_file($path, maps_default_categories());
                }
            }
        }
    }
}

if (!function_exists('maps_read_locations')) {
    function maps_read_locations(): array
    {
        maps_ensure_storage();
        $paths = maps_data_paths();
        $locations = read_json_file($paths['locations']);
        if (!is_array($locations)) {
            return [];
        }
        $normalized = [];
        foreach ($locations as $location) {
            if (!is_array($location)) {
                continue;
            }
            $id = (string) ($location['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $normalized[$id] = $location;
        }
        uasort($normalized, static function ($a, $b) {
            $nameA = strtolower((string) ($a['name'] ?? ''));
            $nameB = strtolower((string) ($b['name'] ?? ''));
            return $nameA <=> $nameB;
        });
        return array_values($normalized);
    }
}

if (!function_exists('maps_read_categories')) {
    function maps_read_categories(): array
    {
        maps_ensure_storage();
        $paths = maps_data_paths();
        $categories = read_json_file($paths['categories']);
        if (!is_array($categories)) {
            return [];
        }
        return maps_sort_categories($categories);
    }
}

if (!function_exists('maps_sort_categories')) {
    function maps_sort_categories(array $categories): array
    {
        $normalized = [];
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            $id = (string) ($category['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $normalized[$id] = $category;
        }
        uasort($normalized, static function ($a, $b) {
            $orderA = (int) ($a['sort_order'] ?? 0);
            $orderB = (int) ($b['sort_order'] ?? 0);
            if ($orderA === $orderB) {
                $nameA = strtolower((string) ($a['name'] ?? ''));
                $nameB = strtolower((string) ($b['name'] ?? ''));
                return $nameA <=> $nameB;
            }
            return $orderA <=> $orderB;
        });
        return array_values($normalized);
    }
}

if (!function_exists('maps_geocode_address')) {
    function maps_geocode_address(string $query): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode($query);
        $headers = [
            'User-Agent: SparkCMSMaps/1.0 (support@sparkcms.local)',
            'Accept: application/json',
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 5,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SparkCMSMaps/1.0 (support@sparkcms.local)');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $response = curl_exec($ch);
            curl_close($ch);
        }

        if (!is_string($response) || trim($response) === '') {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded)) {
            return null;
        }

        $result = $decoded[0];
        if (!isset($result['lat'], $result['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $result['lat'],
            'lng' => (float) $result['lon'],
        ];
    }
}

if (!function_exists('maps_write_locations')) {
    function maps_write_locations(array $locations): bool
    {
        $paths = maps_data_paths();
        return write_json_file($paths['locations'], array_values($locations));
    }
}

if (!function_exists('maps_write_categories')) {
    function maps_write_categories(array $categories): bool
    {
        $paths = maps_data_paths();
        return write_json_file($paths['categories'], maps_sort_categories($categories));
    }
}

if (!function_exists('maps_find_location')) {
    function maps_find_location(array $locations, string $id): ?array
    {
        foreach ($locations as $location) {
            if (!is_array($location)) {
                continue;
            }
            if ((string) ($location['id'] ?? '') === $id) {
                return $location;
            }
        }
        return null;
    }
}

if (!function_exists('maps_find_category')) {
    function maps_find_category(array $categories, string $id): ?array
    {
        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }
            if ((string) ($category['id'] ?? '') === $id) {
                return $category;
            }
        }
        return null;
    }
}

if (!function_exists('maps_locations_grouped_by_category')) {
    function maps_locations_grouped_by_category(array $locations): array
    {
        $grouped = [];
        foreach ($locations as $location) {
            if (!is_array($location)) {
                continue;
            }
            $categories = $location['category_ids'] ?? [];
            if (!is_array($categories)) {
                $categories = [];
            }
            if (empty($categories)) {
                $grouped['uncategorized'][] = $location;
                continue;
            }
            foreach ($categories as $categoryId) {
                $categoryId = (string) $categoryId;
                if ($categoryId === '') {
                    continue;
                }
                if (!isset($grouped[$categoryId])) {
                    $grouped[$categoryId] = [];
                }
                $grouped[$categoryId][] = $location;
            }
        }
        return $grouped;
    }
}
