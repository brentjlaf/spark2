<?php
// File: export.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/analytics_provider.php';

require_login();

$filter = isset($_GET['filter']) ? strtolower((string) $_GET['filter']) : 'all';
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

$allowedFilters = ['all', 'top', 'growing', 'no-views'];
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$dataset = load_analytics_dataset();
$entries = isset($dataset['entries']) && is_array($dataset['entries'])
    ? $dataset['entries']
    : [];

$totalViews = 0;
$totalPages = count($entries);
foreach ($entries as $entry) {
    $totalViews += $entry['views'];
}

$averageViews = $totalPages > 0 ? $totalViews / $totalPages : 0;

foreach ($entries as $index => &$entry) {
    $status = 'growing';
    $label = 'Steady traffic';

    if ($entry['views'] === 0) {
        $status = 'no-views';
        $label = 'Needs promotion';
    } elseif ($index < 3 || $entry['views'] >= $averageViews) {
        $status = 'top';
        $label = 'Top performer';
    }

    $entry['status'] = $status;
    $entry['label'] = $label;
    $entry['rank'] = $index + 1;
}
unset($entry);

$searchTerm = $search;

$filtered = array_values(array_filter($entries, static function ($entry) use ($filter, $searchTerm) {
    if ($filter !== 'all' && $entry['status'] !== $filter) {
        return false;
    }

    if ($searchTerm === '') {
        return true;
    }

    $encoding = 'UTF-8';
    if (function_exists('mb_stripos')) {
        return (mb_stripos($entry['title'], $searchTerm, 0, $encoding) !== false)
            || (mb_stripos($entry['slug'], $searchTerm, 0, $encoding) !== false);
    }

    return (stripos($entry['title'], $searchTerm) !== false)
        || (stripos($entry['slug'], $searchTerm) !== false);
}));

$filename = 'analytics-export-' . date('Ymd-His') . '.csv';

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unable to generate export.']);
    exit;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

fputcsv($output, ['Title', 'Slug', 'Views', 'Segment', 'Rank']);

foreach ($filtered as $entry) {
    $slug = $entry['slug'] !== '' ? '/' . ltrim($entry['slug'], '/') : '/';
    fputcsv($output, [
        $entry['title'],
        $slug,
        $entry['views'],
        $entry['label'],
        $entry['rank'],
    ]);
}

fclose($output);
exit;
