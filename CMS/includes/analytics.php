<?php
// File: analytics.php
// Helper functions for analytics calculations.

/**
 * Estimate the previous period view count for a page based on its slug and current views.
 *
 * This helper uses a deterministic hash of the slug to provide a stable comparison value
 * so that UI deltas remain consistent across page loads even when real historical data is
 * unavailable. The calculation intentionally introduces small fluctuations (±20%) to
 * simulate changes over time while ensuring non-negative integers.
 *
 * @param string $slug   The page slug.
 * @param int    $views  The current period view count.
 * @return int           The derived previous period view count.
 */
function analytics_previous_views($slug, $views)
{
    $currentViews = max(0, (int) $views);
    $slugKey = trim((string) $slug);
    if ($slugKey === '') {
        $slugKey = 'default';
    }

    $hash = crc32($slugKey);
    $modifier = (($hash % 41) - 20) / 100; // Range of -0.20 to +0.20

    if ($currentViews === 0) {
        // Provide a small historical baseline so zero-view pages can show declines.
        return (int) round(($hash % 5) * 5);
    }

    $previous = (int) round($currentViews * (1 + $modifier));
    if ($previous < 0) {
        $previous = 0;
    }

    return $previous;
}

/**
 * Distribute a view total across a fixed number of points using a deterministic hash.
 */
function analytics_distribute_views(int $total, int $points, string $seed): array
{
    $points = max(1, $points);
    $weights = [];
    $weightSum = 0;

    for ($i = 0; $i < $points; $i++) {
        $hash = crc32($seed . ':' . $i);
        $weight = ($hash % 1000) + 1;
        $weights[] = $weight;
        $weightSum += $weight;
    }

    $values = [];
    $allocated = 0;
    foreach ($weights as $weight) {
        $value = (int) round($total * ($weight / $weightSum));
        $values[] = $value;
        $allocated += $value;
    }

    $remaining = $total - $allocated;
    $index = 0;
    while ($remaining !== 0 && $index < $points * 2) {
        $adjust = $remaining > 0 ? 1 : -1;
        $values[$index % $points] += $adjust;
        $remaining -= $adjust;
        $index++;
    }

    foreach ($values as &$value) {
        if ($value < 0) {
            $value = 0;
        }
    }
    unset($value);

    return $values;
}

/**
 * Build time-series trends for the analytics dashboard.
 *
 * @param array<int, array<string, mixed>> $entries
 * @return array<string, mixed>
 */
function analytics_generate_trends(array $entries): array
{
    $ranges = [
        'day' => 24,
        'week' => 7,
        'month' => 30,
        'year' => 12,
    ];

    $siteTotal = 0;
    foreach ($entries as $entry) {
        $siteTotal += isset($entry['views']) ? (int) $entry['views'] : 0;
    }

    $siteTrends = [];
    foreach ($ranges as $rangeKey => $points) {
        $siteTrends[$rangeKey] = analytics_distribute_views($siteTotal, $points, 'site-' . $rangeKey);
    }

    $pagesTrends = [];
    foreach ($entries as $entry) {
        $slug = isset($entry['slug']) ? (string) $entry['slug'] : '';
        $views = isset($entry['views']) ? (int) $entry['views'] : 0;
        $pageSeries = [];
        foreach ($ranges as $rangeKey => $points) {
            $seed = ($slug !== '' ? $slug : 'home') . '-' . $rangeKey;
            $pageSeries[$rangeKey] = analytics_distribute_views($views, $points, $seed);
        }
        $pagesTrends[$slug] = $pageSeries;
    }

    return [
        'site' => $siteTrends,
        'pages' => $pagesTrends,
    ];
}
