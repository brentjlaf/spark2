<?php
// File: save_settings.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();
verify_csrf_token();
require_admin();

header('Content-Type: application/json');

$settingsFile = get_settings_file_path();
ensure_settings_table();

$settings = read_json_file($settingsFile);
if (!is_array($settings)) {
    $settings = [];
}

$settings['site_name'] = sanitize_text($_POST['site_name'] ?? ($settings['site_name'] ?? ''));
$settings['tagline'] = sanitize_text($_POST['tagline'] ?? ($settings['tagline'] ?? ''));
$settings['admin_email'] = sanitize_text($_POST['admin_email'] ?? ($settings['admin_email'] ?? ''));
$settings['timezone'] = sanitize_text($_POST['timezone'] ?? ($settings['timezone'] ?? 'America/Denver'));
$settings['googleAnalytics'] = sanitize_text($_POST['googleAnalytics'] ?? ($settings['googleAnalytics'] ?? ''));
$settings['googleSearchConsole'] = sanitize_text($_POST['googleSearchConsole'] ?? ($settings['googleSearchConsole'] ?? ''));
$settings['facebookPixel'] = sanitize_text($_POST['facebookPixel'] ?? ($settings['facebookPixel'] ?? ''));
$settings['generateSitemap'] = isset($_POST['generateSitemap']);
$settings['allowIndexing'] = isset($_POST['allowIndexing']);

$payments = is_array($settings['payments'] ?? null) ? $settings['payments'] : [];
$stripeSettings = is_array($payments['stripe'] ?? null) ? $payments['stripe'] : [];
$stripeSettings['enabled'] = isset($_POST['stripe_enabled']);
$stripeMode = sanitize_text($_POST['stripe_mode'] ?? ($stripeSettings['mode'] ?? 'test'));
$stripeSettings['mode'] = $stripeMode === 'live' ? 'live' : 'test';
$stripeSettings['publishable_key'] = sanitize_text($_POST['stripe_publishable_key'] ?? ($stripeSettings['publishable_key'] ?? ''));
$stripeSettings['secret_key'] = sanitize_text($_POST['stripe_secret_key'] ?? ($stripeSettings['secret_key'] ?? ''));
$stripeSettings['webhook_secret'] = sanitize_text($_POST['stripe_webhook_secret'] ?? ($stripeSettings['webhook_secret'] ?? ''));
$payments['stripe'] = $stripeSettings;
$settings['payments'] = $payments;

function delete_upload_file($relativePath, $uploadsRoot, $baseDir)
{
    if (!is_string($relativePath) || $relativePath === '') {
        return;
    }

    $relativePath = ltrim($relativePath, '/');
    if (strpos($relativePath, 'uploads/') !== 0) {
        return;
    }

    if (!$uploadsRoot || !$baseDir) {
        return;
    }

    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $relativePath;
    $realFullPath = realpath($fullPath);

    if ($realFullPath === false) {
        return;
    }

    if (strpos($realFullPath, $uploadsRoot) === 0 && is_file($realFullPath)) {
        @unlink($realFullPath);
    }
}

$uploadDir = __DIR__ . '/../../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$baseDir = realpath(__DIR__ . '/../../');
$uploadsRoot = realpath($uploadDir);

$previousLogo = $settings['logo'] ?? null;
$previousFavicon = $settings['favicon'] ?? null;

$logoCleared = !empty($_POST['clear_logo']);
$faviconCleared = !empty($_POST['clear_favicon']);
$newLogo = $previousLogo;
$newFavicon = $previousFavicon;
$logoUploaded = false;
$faviconUploaded = false;

$allowedImageTypes = [
    'image/gif',
    'image/jpeg',
    'image/png',
    'image/webp',
];
$allowedFaviconTypes = array_merge($allowedImageTypes, [
    'image/x-icon',
    'image/vnd.microsoft.icon',
    'image/svg+xml',
]);
$maxUploadSize = 5 * 1024 * 1024; // 5 MB

function validate_image_upload(array $file, array $allowedTypes, int $maxSize, string $fieldLabel)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || empty($file['tmp_name'])) {
        return ['valid' => true];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'message' => sprintf('%s upload failed. Please try again.', $fieldLabel),
        ];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return [
            'valid' => false,
            'message' => sprintf('%s upload failed validation.', $fieldLabel),
        ];
    }

    if (isset($file['size']) && $file['size'] > $maxSize) {
        return [
            'valid' => false,
            'message' => sprintf('%s must be smaller than %d MB.', $fieldLabel, (int) ($maxSize / (1024 * 1024))),
        ];
    }

    $mime = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
    }

    if (!$mime && function_exists('getimagesize')) {
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo && isset($imageInfo['mime'])) {
            $mime = $imageInfo['mime'];
        }
    }

    if (!$mime || !in_array($mime, $allowedTypes, true)) {
        return [
            'valid' => false,
            'message' => sprintf('%s must be a valid image file.', $fieldLabel),
        ];
    }

    return ['valid' => true];
}

$logoValidation = validate_image_upload($_FILES['logo'] ?? [], $allowedImageTypes, $maxUploadSize, 'Logo');
if (!$logoValidation['valid']) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $logoValidation['message'],
    ]);
    exit;
}

$faviconValidation = validate_image_upload($_FILES['favicon'] ?? [], $allowedFaviconTypes, $maxUploadSize, 'Favicon');
if (!$faviconValidation['valid']) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $faviconValidation['message'],
    ]);
    exit;
}

$ogValidation = validate_image_upload($_FILES['ogImage'] ?? [], $allowedImageTypes, $maxUploadSize, 'Open graph image');
if (!$ogValidation['valid']) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $ogValidation['message'],
    ]);
    exit;
}

if (!empty($_FILES['logo']['name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $safe = 'logo_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
        $newLogo = 'uploads/' . $safe;
        $logoUploaded = true;
    }
}

if (!empty($_FILES['favicon']['name']) && is_uploaded_file($_FILES['favicon']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['gif', 'jpg', 'jpeg', 'png', 'webp', 'ico', 'svg'];
    if (!in_array($ext, $allowedExtensions, true)) {
        $ext = 'png';
    }
    $safe = 'favicon_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['favicon']['tmp_name'], $dest)) {
        $newFavicon = 'uploads/' . $safe;
        $faviconUploaded = true;
    }
}

if ($logoCleared && !$logoUploaded) {
    $newLogo = null;
}

if ($faviconCleared && !$faviconUploaded) {
    $newFavicon = null;
}

if ($newLogo !== null) {
    $settings['logo'] = $newLogo;
} else {
    unset($settings['logo']);
}

if ($newFavicon !== null) {
    $settings['favicon'] = $newFavicon;
} else {
    unset($settings['favicon']);
}

if ($previousLogo && $previousLogo !== $newLogo) {
    delete_upload_file($previousLogo, $uploadsRoot, $baseDir);
}

if ($previousFavicon && $previousFavicon !== $newFavicon) {
    delete_upload_file($previousFavicon, $uploadsRoot, $baseDir);
}

$social = is_array($settings['social'] ?? null) ? $settings['social'] : [];
$socialFields = [
    'facebook',
    'twitter',
    'instagram',
    'linkedin',
    'youtube',
    'tiktok',
    'pinterest',
    'snapchat',
    'reddit',
    'threads',
    'mastodon',
    'github',
    'dribbble',
    'twitch',
    'whatsapp',
];
foreach ($socialFields as $field) {
    $social[$field] = sanitize_url($_POST[$field] ?? ($social[$field] ?? ''));
}
$settings['social'] = $social;

$openGraph = is_array($settings['open_graph'] ?? null) ? $settings['open_graph'] : [];
$previousOgImage = $openGraph['image'] ?? null;
$openGraph['title'] = sanitize_text($_POST['ogTitle'] ?? ($openGraph['title'] ?? ''));
$openGraph['description'] = sanitize_text($_POST['ogDescription'] ?? ($openGraph['description'] ?? ''));

$ogCleared = !empty($_POST['clear_og_image']);
$newOgImage = $previousOgImage;
$ogUploaded = false;

if (!empty($_FILES['ogImage']['name']) && is_uploaded_file($_FILES['ogImage']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['ogImage']['name'], PATHINFO_EXTENSION));
    $safe = 'og_' . uniqid('', true) . '.' . $ext;
    $dest = $uploadDir . '/' . $safe;
    if (move_uploaded_file($_FILES['ogImage']['tmp_name'], $dest)) {
        $newOgImage = 'uploads/' . $safe;
        $ogUploaded = true;
    }
}

if ($ogCleared && !$ogUploaded) {
    $newOgImage = null;
}

if ($newOgImage !== null) {
    $openGraph['image'] = $newOgImage;
} else {
    unset($openGraph['image']);
}

if ($previousOgImage && $previousOgImage !== $newOgImage) {
    delete_upload_file($previousOgImage, $uploadsRoot, $baseDir);
}

$settings['open_graph'] = $openGraph;
$settings['last_updated'] = date('c');

if (!write_json_file($settingsFile, $settings)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to save settings. Please try again.',
    ]);
    exit;
}

set_site_settings_cache($settings);

echo json_encode([
    'status' => 'ok',
    'last_updated' => $settings['last_updated'],
    'logo' => $settings['logo'] ?? null,
    'favicon' => $settings['favicon'] ?? null,
    'open_graph' => [
        'image' => $openGraph['image'] ?? null,
    ],
]);
