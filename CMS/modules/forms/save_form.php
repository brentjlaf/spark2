<?php
// File: save_form.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$name = sanitize_text($_POST['name'] ?? '');
$fieldsData = isset($_POST['fields']) ? json_decode($_POST['fields'], true) : [];
$confirmationData = isset($_POST['confirmation_email']) ? json_decode($_POST['confirmation_email'], true) : [];
$fields = [];
foreach ($fieldsData as $field) {
    if (!is_array($field)) continue;
    $item = [
        'type' => sanitize_text($field['type'] ?? 'text'),
        'label' => sanitize_text($field['label'] ?? ''),
        'name' => sanitize_text($field['name'] ?? '')
    ];
    if (isset($field['required'])) $item['required'] = !empty($field['required']);
    if (isset($field['options'])) $item['options'] = sanitize_text($field['options']);
    $fields[] = $item;
}

if (!is_array($confirmationData)) {
    $confirmationData = [];
}

$sanitizeFieldName = static function ($value): string {
    $value = is_string($value) ? $value : '';
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $sanitized = preg_replace('/[^a-z0-9_\-]/i', '_', $value);
    return $sanitized !== null ? $sanitized : '';
};

$rawDescription = isset($confirmationData['description']) ? (string) $confirmationData['description'] : '';
$confirmation = [
    'enabled' => !empty($confirmationData['enabled']),
    'email_field' => $sanitizeFieldName($confirmationData['email_field'] ?? ''),
    'from_name' => sanitize_text($confirmationData['from_name'] ?? ''),
    'from_email' => filter_var(trim((string) ($confirmationData['from_email'] ?? '')), FILTER_SANITIZE_EMAIL) ?: '',
    'subject' => sanitize_text($confirmationData['subject'] ?? ''),
    'title' => sanitize_text($confirmationData['title'] ?? ''),
    'description' => trim(strip_tags($rawDescription)),
];

if ($name === '') {
    http_response_code(400);
    echo 'Missing name';
    exit;
}

if ($id) {
    foreach ($forms as &$f) {
        if ($f['id'] == $id) {
            $f['name'] = $name;
            $f['fields'] = $fields;
            $f['confirmation_email'] = $confirmation;
            break;
        }
    }
    unset($f);
} else {
    $id = 1;
    foreach ($forms as $f) {
        if ($f['id'] >= $id) $id = $f['id'] + 1;
    }
    $forms[] = ['id' => $id, 'name' => $name, 'fields' => $fields, 'confirmation_email' => $confirmation];
}

write_json_file($formsFile, $forms);

echo 'OK';
?>
