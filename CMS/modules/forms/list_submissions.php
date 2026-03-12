<?php
// File: list_submissions.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$formId = isset($_GET['form_id']) ? (int)$_GET['form_id'] : null;
$summaryMode = isset($_GET['summary']) ? (string) $_GET['summary'] : null;
$submissionsFile = __DIR__ . '/../../data/form_submissions.json';
$submissions = read_json_file($submissionsFile);

if (!is_array($submissions)) {
    $submissions = [];
}

$submissions = array_values(array_filter($submissions, 'is_array'));

$extractTimestamp = function (array $entry): int {
    $candidates = ['submitted_at', 'created_at', 'timestamp'];
    foreach ($candidates as $key) {
        if (empty($entry[$key])) {
            continue;
        }
        $value = $entry[$key];
        if (is_numeric($value)) {
            $value = (float) $value;
            if ($value > 0) {
                return $value < 1_000_000_000_000 ? (int) round($value) : (int) round($value / 1000);
            }
            continue;
        }
        $time = strtotime((string) $value);
        if ($time !== false) {
            return $time;
        }
    }
    return 0;
};

if ($formId) {
    $submissions = array_values(array_filter($submissions, function ($submission) use ($formId) {
        if (!is_array($submission)) {
            return false;
        }
        if (!isset($submission['form_id'])) {
            return false;
        }
        return (int) $submission['form_id'] === $formId;
    }));
}

if ($summaryMode === 'per_form') {
    $perForm = [];

    foreach ($submissions as $submission) {
        if (!isset($submission['form_id'])) {
            continue;
        }

        $formKey = (int) $submission['form_id'];
        if ($formKey <= 0) {
            continue;
        }

        if (!isset($perForm[$formKey])) {
            $perForm[$formKey] = [
                'form_id' => $formKey,
                'submission_count' => 0,
                'last_submission' => null,
                '_last_timestamp' => 0,
                '_last_fallback' => null,
            ];
        }

        $perForm[$formKey]['submission_count']++;

        $timestamp = $extractTimestamp($submission);
        $fallback = null;
        foreach (['submitted_at', 'created_at', 'timestamp'] as $key) {
            if (!empty($submission[$key])) {
                $fallback = (string) $submission[$key];
                break;
            }
        }

        if ($timestamp > 0) {
            if ($timestamp >= $perForm[$formKey]['_last_timestamp']) {
                $perForm[$formKey]['_last_timestamp'] = $timestamp;
                $perForm[$formKey]['last_submission'] = date(DATE_ATOM, $timestamp);
                $perForm[$formKey]['_last_fallback'] = $fallback;
            }
        } elseif ($perForm[$formKey]['_last_timestamp'] === 0 && $fallback !== null) {
            $perForm[$formKey]['last_submission'] = $fallback;
            $perForm[$formKey]['_last_fallback'] = $fallback;
        }
    }

    $result = array_values(array_map(function (array $entry): array {
        $payload = [
            'form_id' => $entry['form_id'],
            'submission_count' => $entry['submission_count'],
        ];

        if ($entry['last_submission'] !== null) {
            $payload['last_submission'] = $entry['last_submission'];
        }

        return $payload;
    }, $perForm));

    usort($result, function (array $a, array $b): int {
        return $a['form_id'] <=> $b['form_id'];
    });

    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

usort($submissions, function ($a, $b) use ($extractTimestamp) {
    return $extractTimestamp($b) <=> $extractTimestamp($a);
});

$normalized = array_map(function ($submission) use ($extractTimestamp) {
    $normalized = [
        'id' => $submission['id'] ?? null,
        'form_id' => isset($submission['form_id']) ? (int) $submission['form_id'] : null,
    ];

    $data = $submission['data'] ?? [];
    if (!is_array($data)) {
        $data = [];
    }
    $normalized['data'] = (object) $data;

    $meta = $submission['meta'] ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }

    if (isset($submission['ip']) && !isset($meta['ip'])) {
        $meta['ip'] = $submission['ip'];
    }

    $normalized['meta'] = (object) $meta;

    $timestamp = $extractTimestamp($submission);
    if ($timestamp > 0) {
        $normalized['submitted_at'] = date(DATE_ATOM, $timestamp);
    } else {
        $fallback = null;
        foreach (['submitted_at', 'created_at', 'timestamp'] as $key) {
            if (!empty($submission[$key])) {
                $fallback = (string) $submission[$key];
                break;
            }
        }
        $normalized['submitted_at'] = $fallback;
    }

    if (isset($submission['source'])) {
        $normalized['source'] = $submission['source'];
    }

    return $normalized;
}, $submissions);

header('Content-Type: application/json');
echo json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
