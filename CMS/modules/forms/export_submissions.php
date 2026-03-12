<?php
// File: modules/forms/export_submissions.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$formId = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
if ($formId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'A valid form_id parameter is required.']);
    return;
}

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);
if (!is_array($forms)) {
    $forms = [];
}

$formName = '';
foreach ($forms as $form) {
    if (!is_array($form)) {
        continue;
    }
    if ((int) ($form['id'] ?? 0) === $formId) {
        $formName = isset($form['name']) ? (string) $form['name'] : '';
        break;
    }
}

$submissionsFile = __DIR__ . '/../../data/form_submissions.json';
$submissions = read_json_file($submissionsFile);
if (!is_array($submissions)) {
    $submissions = [];
}

$filtered = array_values(array_filter($submissions, static function ($submission) use ($formId) {
    if (!is_array($submission)) {
        return false;
    }
    if (!isset($submission['form_id'])) {
        return false;
    }
    return (int) $submission['form_id'] === $formId;
}));

$extractTimestamp = static function (array $entry): int {
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

$fieldKeys = [];
$metaKeys = [];
foreach ($filtered as $submission) {
    $data = isset($submission['data']) && is_array($submission['data']) ? $submission['data'] : [];
    foreach ($data as $key => $_) {
        $fieldKeys[$key] = true;
    }
    $meta = isset($submission['meta']) && is_array($submission['meta']) ? $submission['meta'] : [];
    if (isset($submission['ip']) && !isset($meta['ip'])) {
        $meta['ip'] = $submission['ip'];
    }
    foreach ($meta as $key => $_) {
        $metaKeys[$key] = true;
    }
}

$fieldColumns = array_keys($fieldKeys);
sort($fieldColumns, SORT_NATURAL | SORT_FLAG_CASE);
$metaColumns = array_keys($metaKeys);
sort($metaColumns, SORT_NATURAL | SORT_FLAG_CASE);

$slugify = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim($value, '-');
    return $value !== '' ? $value : 'form';
};

$filenameParts = ['form', (string) $formId];
if ($formName !== '') {
    $filenameParts[] = $slugify($formName);
}
$filenameParts[] = 'submissions.csv';
$filename = implode('-', $filenameParts);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$handle = fopen('php://output', 'w');
if ($handle === false) {
    http_response_code(500);
    echo 'Unable to open output stream';
    return;
}

$writeRow = static function ($resource, array $row): void {
    $converted = array_map(static function ($value) {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return implode(', ', array_map(static function ($item) {
                return is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }, $value));
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }, $row);
    fputcsv($resource, $converted);
};

$headers = ['Form ID', 'Submission ID', 'Submitted At', 'Source'];
foreach ($fieldColumns as $column) {
    $headers[] = 'Field: ' . $column;
}
foreach ($metaColumns as $column) {
    $headers[] = 'Metadata: ' . $column;
}

$writeRow($handle, $headers);

foreach ($filtered as $submission) {
    $data = isset($submission['data']) && is_array($submission['data']) ? $submission['data'] : [];
    $meta = isset($submission['meta']) && is_array($submission['meta']) ? $submission['meta'] : [];
    if (isset($submission['ip']) && !isset($meta['ip'])) {
        $meta['ip'] = $submission['ip'];
    }

    $timestamp = $extractTimestamp($submission);
    $submittedAt = '';
    if ($timestamp > 0) {
        $submittedAt = date(DATE_ATOM, $timestamp);
    } else {
        foreach (['submitted_at', 'created_at', 'timestamp'] as $key) {
            if (!empty($submission[$key])) {
                $submittedAt = (string) $submission[$key];
                break;
            }
        }
    }

    $row = [
        $submission['form_id'] ?? $formId,
        $submission['id'] ?? '',
        $submittedAt,
        $submission['source'] ?? ($meta['source'] ?? ''),
    ];

    foreach ($fieldColumns as $column) {
        $row[] = $data[$column] ?? '';
    }
    foreach ($metaColumns as $column) {
        $row[] = $meta[$column] ?? '';
    }

    $writeRow($handle, $row);
}

fclose($handle);
