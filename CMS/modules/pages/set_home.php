<?php
// File: set_home.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();
verify_csrf_token();
require_editor();

$slug = sanitize_text($_POST['slug'] ?? '');
if ($slug === '') {
    http_response_code(400);
    echo 'Missing slug';
    exit;
}

$settingsFile = get_settings_file_path();
$settings = read_json_file($settingsFile);
$settings['homepage'] = $slug;
write_json_file($settingsFile, $settings);
set_site_settings_cache($settings);

echo 'OK';
?>
