<?php
// File: generate.php
// Manually trigger sitemap regeneration and return metadata for the UI.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sitemap_helpers.php';
require_login();

header('Content-Type: application/json');

try {
    $cmsRoot = realpath(__DIR__ . '/../../') ?: (__DIR__ . '/../../');

    // Compute base URL for the response metadata (and passed to the helper).
    $scheme     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if (substr($scriptBase, -4) === '/CMS') {
        $scriptBase = substr($scriptBase, 0, -4);
    }
    $scriptBase = rtrim($scriptBase, '/');
    $baseUrl    = $scheme . '://' . $host . $scriptBase;

    // Build the entries array (used both for writing and for the JSON response).
    $entries     = sparkcms_get_sitemap_entries($cmsRoot, $baseUrl);
    $sitemapPath = dirname(rtrim($cmsRoot, '/\\')) . '/sitemap.xml';

    if (!sparkcms_write_sitemap_xml($entries, $sitemapPath)) {
        throw new RuntimeException('Unable to write sitemap file.');
    }

    $generatedAt = filemtime($sitemapPath) ?: time();

    echo json_encode([
        'success'              => true,
        'message'              => 'Sitemap regenerated successfully.',
        'entryCount'           => count($entries),
        'generatedAt'          => $generatedAt,
        'generatedAtFormatted' => date('F j, Y g:i a', $generatedAt),
        'entries'              => $entries,
        'sitemapUrl'           => $baseUrl . '/sitemap.xml',
        'generator'            => class_exists('DOMDocument') ? 'dom' : 'simple',
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to regenerate sitemap.',
        'error'   => $exception->getMessage(),
    ]);
}
