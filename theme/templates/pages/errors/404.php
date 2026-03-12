<?php
// File: 404.php
// Template: errors/404
// Variables provided by index.php: $settings, $menus, $page, $scriptBase, $themeBase
$siteName = $settings['site_name'] ?? 'My Site';
if (!empty($settings['logo'])) {
    $logo = $scriptBase . '/CMS/' . ltrim($settings['logo'], '/');
} else {
    $logo = $themeBase . '/images/logo.png';
}
$mainMenu = $menus[0]['items'] ?? [];
$footerMenu = $menus[1]['items'] ?? [];
$social = $settings['social'] ?? [];

require_once dirname(__DIR__) . '/includes/menu_helpers.php';

?>
<?php $bodyClass = 'page-template min-h-screen flex flex-col'; ?>
<?php include __DIR__ . "/../../partials/head.php"; ?>


		<!-- Default Page -->
		<div id="app" class="min-h-screen bg-slate-50 flex flex-col">

                        <?php include __DIR__ . "/../../partials/header.php"; ?>

                        <!-- Main -->
                        <main id="main-area" class="flex-1">
                                <div class="drop-area"></div>
                        </main>

                        <!-- Footer -->
                        <?php include __DIR__ . "/../../partials/footer.php"; ?>
