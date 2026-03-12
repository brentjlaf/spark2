<?php
// File: 404.php
http_response_code(404);
// Reuse cached settings data if available to avoid redundant disk access
require_once __DIR__ . '/CMS/includes/data.php';
require_once __DIR__ . '/CMS/includes/settings.php';
$menusFile = __DIR__ . '/CMS/data/menus.json';
$settings = get_site_settings();
$menus = get_cached_json($menusFile);
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');
$page = [
    'title' => 'Page Not Found',
    'content' =>
        '<h1>Page Not Found</h1>' .
        '<p>The page you are looking for might have been moved or deleted.</p>' .
        '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
        ' or use the site search to find what you are looking for.</p>'
];
$themeBase = $scriptBase . '/theme';
$templateFile = __DIR__ . '/theme/templates/pages/errors/404.php';
ob_start();
include $templateFile;
$html = ob_get_clean();
$html = preg_replace('/<div class="drop-area"><\\/div>/', $page['content'], $html);
echo $html;
