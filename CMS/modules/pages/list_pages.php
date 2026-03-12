<?php
// File: list_pages.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$settings = get_site_settings();
$homepageSlug = $settings['homepage'] ?? 'home';
$homePage = null;
$result = [];

foreach ($pages as $p) {
    $pageData = ['id' => $p['id'], 'title' => $p['title'], 'slug' => $p['slug']];

    if ($p['slug'] === $homepageSlug) {
        $homePage = $pageData;
        continue;
    }

    $result[] = $pageData;
}

if ($homePage !== null) {
    array_unshift($result, $homePage);
}

echo json_encode($result);
?>
