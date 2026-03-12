<?php
// File: analytics_provider.php
// Centralizes retrieval of analytics entries from either Google Analytics or the local CMS dataset.

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/google_analytics_client.php';

/**
 * Retrieve analytics entries prioritized from Google Analytics if configured.
 *
 * @return array{entries: array<int, array<string, mixed>>, source: string, meta: array<string, mixed>}
 */
function load_analytics_dataset(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $settings = get_site_settings();
    $measurementId = isset($settings['googleAnalytics']) ? trim((string) $settings['googleAnalytics']) : '';

    if ($measurementId !== '' && GoogleAnalyticsDataClient::isSupported()) {
        try {
            $client = GoogleAnalyticsDataClient::create();
            if ($client) {
                $report = $client->fetchPageReport($measurementId, ['limit' => 250]);
                if (is_array($report) && isset($report['entries']) && is_array($report['entries'])) {
                    $entries = array_map('normalize_analytics_entry', $report['entries']);
                    usort($entries, 'sort_analytics_entries');
                    $trends = analytics_generate_trends($entries);

                    $fetchedAt = isset($report['fetchedAt']) ? (int) $report['fetchedAt'] : time();
                    $cached = [
                        'entries' => $entries,
                        'trends' => $trends,
                        'source' => 'google',
                        'meta' => [
                            'last_updated' => $fetchedAt,
                            'last_updated_iso' => date(DATE_ATOM, $fetchedAt),
                            'property_id' => $report['propertyId'] ?? null,
                            'measurement_id' => $measurementId,
                            'source_label' => 'Google Analytics',
                        ],
                    ];
                    return $cached;
                }
            }
        } catch (Throwable $e) {
            error_log('Analytics: Google Analytics integration failed - ' . $e->getMessage());
        }
    }

    $cached = load_local_analytics_dataset();
    return $cached;
}

/**
 * @return array{entries: array<int, array<string, mixed>>, source: string, meta: array<string, mixed>}
 */
function load_local_analytics_dataset(): array
{
    $pagesFile = __DIR__ . '/../data/pages.json';
    $pages = read_json_file($pagesFile);
    $entries = [];
    $lastUpdated = 0;

    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        $slug = isset($page['slug']) ? (string) $page['slug'] : '';
        $views = isset($page['views']) ? (int) $page['views'] : 0;
        $entries[] = normalize_analytics_entry([
            'title' => isset($page['title']) ? (string) $page['title'] : 'Untitled',
            'slug' => $slug,
            'views' => $views,
            'previousViews' => analytics_previous_views($slug, $views),
        ]);

        $modified = isset($page['last_modified']) ? (int) $page['last_modified'] : 0;
        if ($modified > $lastUpdated) {
            $lastUpdated = $modified;
        }
    }

    usort($entries, 'sort_analytics_entries');

    if ($lastUpdated <= 0) {
        $lastUpdated = time();
    }

    return [
        'entries' => $entries,
        'trends' => analytics_generate_trends($entries),
        'source' => 'local',
        'meta' => [
            'last_updated' => $lastUpdated,
            'last_updated_iso' => date(DATE_ATOM, $lastUpdated),
            'source_label' => 'CMS sample data',
        ],
    ];
}

/**
 * Normalize an analytics entry array.
 *
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function normalize_analytics_entry(array $entry): array
{
    $title = isset($entry['title']) && $entry['title'] !== ''
        ? (string) $entry['title']
        : 'Untitled';
    $slug = isset($entry['slug']) ? (string) $entry['slug'] : '';
    $slug = trim($slug);
    if ($slug === '/') {
        $slug = '';
    }
    $slug = ltrim($slug, '/');

    $views = isset($entry['views']) ? (int) $entry['views'] : 0;
    $previousViews = isset($entry['previousViews']) ? (int) $entry['previousViews'] : $views;

    return [
        'title' => $title,
        'slug' => $slug,
        'views' => max(0, $views),
        'previousViews' => max(0, $previousViews),
    ];
}

/**
 * Sort helper to order entries by descending views.
 */
function sort_analytics_entries(array $a, array $b): int
{
    $aViews = isset($a['views']) ? (int) $a['views'] : 0;
    $bViews = isset($b['views']) ? (int) $b['views'] : 0;
    return $bViews <=> $aViews;
}
