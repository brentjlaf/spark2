<?php
// File: list_settings.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

header('Content-Type: application/json');

$settings = get_site_settings();

echo json_encode($settings);
?>
