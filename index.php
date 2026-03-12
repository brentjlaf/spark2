<?php
// File: index.php
require_once __DIR__ . '/CMS/includes/data.php';
require_once __DIR__ . '/CMS/includes/settings.php';
// Determine the requested path for clean URLs.
// When the CMS is installed in a subdirectory we need to strip that base
// directory from the request URI so that routing works correctly.
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$base = trim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base && strpos($path, $base) === 0) {
    $path = trim(substr($path, strlen($base)), '/');
}

// If a real file is requested, serve it directly. This allows access to
// backend scripts like CMS/login.php when using URL rewriting.
$settings = get_site_settings();
$homepage = $settings['homepage'] ?? 'home';

$requested = __DIR__ . '/' . $path;
if ($path !== '' && is_file($requested)) {
    $ext = strtolower(pathinfo($requested, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        require $requested;
    } else {
        $mimeMap = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'json' => 'application/json',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        if (isset($mimeMap[$ext])) {
            header('Content-Type: ' . $mimeMap[$ext]);
        }
        readfile($requested);
    }
    return;
}

// Allow requests like "/about-us.php" when URL rewriting isn't available
if (substr($path, -4) === '.php') {
    $path = substr($path, 0, -4);
}

if ($path === '' || $path === 'index') {
    $_GET['page'] = $_GET['page'] ?? $homepage;
} else {
    // Allow fallback to ?page= query if provided
    $_GET['page'] = $_GET['page'] ?? $path;
}

require __DIR__ . '/CMS/index.php';

