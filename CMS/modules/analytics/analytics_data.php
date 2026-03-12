<?php
// File: analytics_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/analytics.php';
require_once __DIR__ . '/../../includes/analytics_provider.php';
require_login();

$dataset = load_analytics_dataset();
$entries = isset($dataset['entries']) && is_array($dataset['entries'])
    ? $dataset['entries']
    : [];
$meta = isset($dataset['meta']) && is_array($dataset['meta']) ? $dataset['meta'] : [];
$trends = isset($dataset['trends']) && is_array($dataset['trends']) ? $dataset['trends'] : [];
$source = isset($dataset['source']) ? (string) $dataset['source'] : 'local';

$lastUpdatedTimestamp = isset($meta['last_updated']) ? (int) $meta['last_updated'] : time();
$lastUpdatedDisplay = $lastUpdatedTimestamp > 0
    ? date('M j, Y g:i a', $lastUpdatedTimestamp)
    : null;
$sourceLabel = isset($meta['source_label'])
    ? (string) $meta['source_label']
    : ($source === 'google' ? 'Google Analytics' : 'CMS sample data');
$lastUpdatedText = $lastUpdatedDisplay
    ? 'Data refreshed ' . $lastUpdatedDisplay
    : 'Data refreshed moments ago';
$metaLabel = $lastUpdatedText . ' • Source: ' . $sourceLabel;
$lastUpdatedIso = isset($meta['last_updated_iso']) ? (string) $meta['last_updated_iso'] : '';
if ($lastUpdatedIso === '' && $lastUpdatedTimestamp > 0) {
    $lastUpdatedIso = date(DATE_ATOM, $lastUpdatedTimestamp);
}

$response = [
    'entries' => $entries,
    'source' => $source,
    'meta' => array_merge($meta, [
        'label' => $metaLabel,
        'source_label' => $sourceLabel,
        'last_updated_iso' => $lastUpdatedIso,
        'trends' => $trends,
    ]),
];

header('Content-Type: application/json');
echo json_encode($response);
