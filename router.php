<?php
// File: router.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && file_exists($file)) {
    return false; // serve the requested resource as-is
}
require __DIR__ . '/index.php';
