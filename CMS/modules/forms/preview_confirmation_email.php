<?php
// File: modules/forms/preview_confirmation_email.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../../forms/confirmation_email_template.php';

require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    return;
}

$settings = get_site_settings();
$siteName = sanitize_text((string) ($settings['site_name'] ?? ''));

$subjectInput = isset($_POST['subject']) ? (string) $_POST['subject'] : '';
$subject = sanitize_text($subjectInput);
if ($subject === '') {
    $subject = $siteName !== '' ? 'Thanks for contacting ' . $siteName : 'Thanks for your submission';
}

$title = isset($_POST['title']) ? (string) $_POST['title'] : '';
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';

$fromNameInput = isset($_POST['from_name']) ? (string) $_POST['from_name'] : '';
$fromName = sanitize_text($fromNameInput);
if ($fromName === '') {
    $fromName = $siteName !== '' ? $siteName : 'Website team';
}

$fromEmailInput = isset($_POST['from_email']) ? trim((string) $_POST['from_email']) : '';
$fromEmail = $fromEmailInput;
if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    $fallbackEmail = isset($settings['admin_email']) ? trim((string) $settings['admin_email']) : '';
    if (filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = $fallbackEmail;
    }
}

if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Provide a valid From email address to generate a preview.']);
    return;
}

$config = [
    'subject' => $subject,
    'title' => $title,
    'description' => $description,
    'from_name' => $fromName,
    'from_email' => $fromEmail,
];

$html = build_confirmation_email_html($settings, $config);
if (!is_string($html) || $html === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to generate the confirmation email preview.']);
    return;
}

echo json_encode([
    'success' => true,
    'html' => $html,
    'subject' => $subject,
    'from_name' => $fromName,
    'from_email' => $fromEmail,
]);
