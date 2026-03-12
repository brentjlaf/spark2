<?php
// File: modules/sitemap/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
if (!is_array($pages)) {
    $pages = [];
}

$publishedPages = array_values(array_filter($pages, function ($page) {
    return !empty($page['published']);
}));

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');
$baseUrl = $scheme . '://' . $host . $scriptBase;

$sitemapPath = __DIR__ . '/../../../sitemap.xml';
$lastGenerated = is_file($sitemapPath) ? filemtime($sitemapPath) : null;
$lastGeneratedLabel = $lastGenerated ? date('F j, Y g:i a', $lastGenerated) : 'Not generated yet';

$entries = array_map(function ($page) use ($baseUrl) {
    $slug = ltrim((string)($page['slug'] ?? ''), '/');
    $lastModified = isset($page['last_modified']) ? (int)$page['last_modified'] : time();
    return [
        'title' => (string)($page['title'] ?? ''),
        'slug' => $slug,
        'url' => $baseUrl . '/' . $slug,
        'lastmod' => date('F j, Y', $lastModified),
    ];
}, $publishedPages);

$entryCount = count($entries);
$statusMessage = $entryCount > 0
    ? 'Sitemap currently lists ' . number_format($entryCount) . ' published page' . ($entryCount === 1 ? '' : 's') . '.'
    : 'Publish pages to populate the sitemap.';
?>
<div class="content-section" id="sitemap" data-endpoint="modules/sitemap/generate.php">
    <div class="sitemap-dashboard a11y-dashboard">
        <header class="a11y-hero sitemap-hero">
            <div class="a11y-hero-content sitemap-hero-content">
                <div>
                    <span class="hero-eyebrow sitemap-hero-eyebrow">Index Coverage</span>
                    <h2 class="a11y-hero-title sitemap-hero-title">Sitemap overview</h2>
                    <p class="a11y-hero-subtitle sitemap-hero-subtitle">
                        Review the URLs included in your sitemap and regenerate it after publishing new content.
                    </p>
                </div>
                <div class="a11y-hero-actions sitemap-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="sitemapRegenerate">
                        <i class="fas fa-rotate" aria-hidden="true"></i>
                        <span>Regenerate sitemap</span>
                    </button>
                    <?php if ($entryCount > 0 && is_file($sitemapPath)): ?>
                        <a class="a11y-btn a11y-btn--ghost" href="../sitemap.xml" target="_blank" rel="noopener">
                            <i class="fas fa-up-right-from-square" aria-hidden="true"></i>
                            <span>View sitemap.xml</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="a11y-overview-grid sitemap-overview-grid">
                <div class="a11y-overview-card sitemap-overview-card">
                    <div class="a11y-overview-label">Published URLs</div>
                    <div class="a11y-overview-value" id="sitemapEntryCount"><?php echo number_format($entryCount); ?></div>
                </div>
                <div class="a11y-overview-card sitemap-overview-card">
                    <div class="a11y-overview-label">Last generated</div>
                    <div class="a11y-overview-value" id="sitemapLastGenerated"><?php echo htmlspecialchars($lastGeneratedLabel); ?></div>
                </div>
                <div class="a11y-overview-card sitemap-overview-card">
                    <div class="a11y-overview-label">Sitemap URL</div>
                    <div class="a11y-overview-value sitemap-overview-url" id="sitemapBaseUrl"><?php echo htmlspecialchars($baseUrl . '/sitemap.xml'); ?></div>
                </div>
            </div>
        </header>

        <section class="a11y-detail-card sitemap-status-card">
            <header class="sitemap-status-card__header">
                <div class="sitemap-status-card__intro">
                    <h3 class="sitemap-status-card__title">Sitemap status</h3>
                    <p class="sitemap-status-card__description">Keep search engines informed of your published content.</p>
                </div>
                <span class="sitemap-status-card__meta" id="sitemapStatusMessage"><?php echo htmlspecialchars($statusMessage); ?></span>
            </header>
            <div class="sitemap-status-card__body">
                <div class="sitemap-table"<?php echo $entries ? '' : ' style="display:none;"'; ?>>
                    <table class="data-table">
                        <thead>
                            <tr><th scope="col">Title</th><th scope="col">URL</th><th scope="col">Status</th><th scope="col">Last modified</th></tr>
                        </thead>
                        <tbody id="sitemapTableBody">
                            <?php if ($entries): ?>
                                <?php foreach ($entries as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($entry['title']); ?></td>
                                        <td><a href="<?php echo htmlspecialchars($entry['url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($entry['url']); ?></a></td>
                                        <td>
                                            <span class="status-badge status-published" aria-label="Status: Published">Published</span>
                                        </td>
                                        <td><?php echo htmlspecialchars($entry['lastmod']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <p id="sitemapEmptyMessage"<?php echo $entries ? ' style="display:none;"' : ''; ?>>No published pages are currently included in the sitemap.</p>
            </div>
        </section>
    </div>
</div>
