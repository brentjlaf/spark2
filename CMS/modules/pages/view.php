<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/page_schedule.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$settings = get_site_settings();
$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');
$templates = [];
if ($templateDir) {
    foreach (glob($templateDir . '/*.php') as $t) {
        $name = basename($t);
        if ($name[0] === '_') {
            continue;
        }

        if ($name === 'blog-list.php') {
            continue;
        }

        $templates[] = $name;
    }
}
$homepage = $settings['homepage'] ?? 'home';
$homepageSlug = (string) $homepage;

// Page stats
$totalPages = count($pages);
$publishedPages = 0;
$draftPages = 0;
$totalViews = 0;
$restrictedPages = 0;
$lastUpdatedTimestamp = 0;
foreach ($pages as $p) {
    $scheduleInfo = sparkcms_page_schedule_info($p);
    if ($scheduleInfo['is_live']) {
        $publishedPages++;
    } else {
        $draftPages++;
    }

    if (($p['access'] ?? 'public') !== 'public') {
        $restrictedPages++;
    }

    if (!empty($p['last_modified'])) {
        $lastUpdatedTimestamp = max($lastUpdatedTimestamp, (int)$p['last_modified']);
    }

    $totalViews += $p['views'] ?? 0;
}

$lastUpdatedDisplay = $lastUpdatedTimestamp > 0 ? date('M j, Y g:i A', $lastUpdatedTimestamp) : 'No edits yet';
$filterCounts = [
    'all' => $totalPages,
    'published' => $publishedPages,
    'drafts' => $draftPages,
    'restricted' => $restrictedPages,
];
$pagesWord = $totalPages === 1 ? 'page' : 'pages';

$orderedPages = [];
$homepagePageData = null;
foreach ($pages as $pageEntry) {
    $pageSlug = (string) ($pageEntry['slug'] ?? '');
    if ($homepageSlug !== '' && $pageSlug === $homepageSlug) {
        $homepagePageData = $pageEntry;
        continue;
    }
    $orderedPages[] = $pageEntry;
}
if ($homepagePageData !== null) {
    array_unshift($orderedPages, $homepagePageData);
} elseif (empty($orderedPages)) {
    $orderedPages = $pages;
}
?>
<div class="content-section" id="pages">
    <div class="pages-dashboard a11y-dashboard" data-last-updated="<?php echo htmlspecialchars($lastUpdatedDisplay, ENT_QUOTES); ?>">
        <header class="a11y-hero pages-hero">
            <div class="a11y-hero-content pages-hero-content">
                <div>
                    <span class="hero-eyebrow pages-hero-eyebrow">Content Inventory</span>
                    <h2 class="a11y-hero-title pages-hero-title">Pages</h2>
                    <p class="a11y-hero-subtitle pages-hero-subtitle">Keep your site structure organised and publish updates with confidence.</p>
                </div>
                <div class="a11y-hero-actions pages-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="newPageBtn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        <span>New Page</span>
                    </button>
                    <a class="a11y-btn a11y-btn--ghost" href="../" target="_blank" rel="noopener">
                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                        <span>View Site</span>
                    </a>
                    <span class="a11y-hero-meta pages-hero-meta">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        Last edit: <?php echo htmlspecialchars($lastUpdatedDisplay); ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid pages-overview-grid">
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Total Pages</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $totalPages; ?></div>
                </div>
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Published</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $publishedPages; ?></div>
                </div>
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Drafts</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $draftPages; ?></div>
                </div>
                <div class="a11y-overview-card pages-overview-card">
                    <div class="a11y-overview-label pages-overview-label">Total Views</div>
                    <div class="a11y-overview-value pages-overview-value"><?php echo $totalViews; ?></div>
                </div>
            </div>
        </header>

        <div class="pages-controls">
            <label class="pages-search" for="pagesSearchInput">
                <i class="fa-solid fa-search" aria-hidden="true"></i>
                <input type="search" id="pagesSearchInput" placeholder="Search pages by title or slug" aria-label="Search pages">
            </label>
            <div class="pages-filter-group" role="group" aria-label="Filter pages by status">
                <button type="button" class="pages-filter-btn active" data-pages-filter="all" aria-pressed="true">All Pages <span class="pages-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="published" aria-pressed="false">Published <span class="pages-filter-count" data-count="published"><?php echo $filterCounts['published']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="drafts" aria-pressed="false">Drafts <span class="pages-filter-count" data-count="drafts"><?php echo $filterCounts['drafts']; ?></span></button>
                <button type="button" class="pages-filter-btn" data-pages-filter="restricted" aria-pressed="false">Private <span class="pages-filter-count" data-count="restricted"><?php echo $filterCounts['restricted']; ?></span></button>
            </div>
        </div>

        <!-- Bulk action toolbar (shown when rows are selected) -->
        <div class="bulk-toolbar" id="pagesBulkToolbar" hidden aria-live="polite">
            <span class="bulk-toolbar__count" id="pagesBulkCount">0 selected</span>
            <div class="bulk-toolbar__actions">
                <button type="button" class="a11y-btn a11y-btn--secondary" id="bulkPublishBtn">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i> Publish
                </button>
                <button type="button" class="a11y-btn a11y-btn--secondary" id="bulkUnpublishBtn">
                    <i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish
                </button>
                <button type="button" class="a11y-btn a11y-btn--danger" id="bulkDeleteBtn">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                </button>
                <button type="button" class="a11y-btn a11y-btn--ghost" id="bulkClearBtn">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear
                </button>
            </div>
        </div>

        <section class="a11y-detail-card table-card pages-table-card" aria-labelledby="pagesInventoryTitle" aria-describedby="pagesInventoryDescription">
            <header class="table-header pages-table-header">
                <div class="table-header-text">
                    <h3 class="table-title" id="pagesInventoryTitle">Page inventory</h3>
                    <p class="table-description" id="pagesInventoryDescription">Manage publishing status, homepage selection, and metadata across all content.</p>
                </div>
                <span class="table-meta pages-table-meta" id="pagesVisibleCount" aria-live="polite">Showing <?php echo $totalPages . ' ' . $pagesWord; ?></span>
            </header>
            <table class="pages-list-view" id="pagesListView" aria-describedby="pagesInventoryDescription" data-homepage-slug="<?php echo htmlspecialchars($homepage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <thead>
                    <tr>
                        <th scope="col" class="pages-list-select-heading">
                            <input type="checkbox" id="selectAllPages" aria-label="Select all pages">
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="title" data-default-direction="asc">
                                <span>Page</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="status" data-default-direction="desc">
                                <span>Status</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="views" data-default-direction="desc">
                                <span>Views</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="updated" data-default-direction="desc">
                                <span>Last updated</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button type="button" class="pages-sort-btn" data-pages-sort="access" data-default-direction="asc">
                                <span>Access</span>
                                <span class="pages-sort-indicator" aria-hidden="true"></span>
                            </button>
                        </th>
                        <th scope="col" class="pages-list-actions-heading">Actions</th>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($orderedPages as $p): ?>
<?php
    $id = isset($p['id']) ? (int) $p['id'] : 0;
    $title = isset($p['title']) && $p['title'] !== '' ? (string) $p['title'] : 'Untitled Page';
    $slug = isset($p['slug']) ? (string) $p['slug'] : '';
    $content = isset($p['content']) ? (string) $p['content'] : '';
    $templateName = isset($p['template']) && $p['template'] !== '' ? $p['template'] : 'page.php';
    $metaTitle = $p['meta_title'] ?? '';
    $metaDescription = $p['meta_description'] ?? '';
    $canonicalUrl = $p['canonical_url'] ?? '';
    $ogTitle = $p['og_title'] ?? '';
    $ogDescription = $p['og_description'] ?? '';
    $ogImage = $p['og_image'] ?? '';
    $robotsDirective = sanitize_robots_directive($p['robots'] ?? sparkcms_default_robots_directive());
    $accessRaw = $p['access'] ?? 'public';

    $scheduleInfo = sparkcms_page_schedule_info($p);
    $isPublished = !empty($p['published']);
    $isLive = $scheduleInfo['is_live'];
    $scheduleState = $scheduleInfo['state'];
    $publishAtValue = $scheduleInfo['publish_at'];
    $unpublishAtValue = $scheduleInfo['unpublish_at'];
    $statusBadgeClass = 'status-draft';
    $statusText = 'Draft';
    if ($scheduleState === 'published' && $isPublished) {
        $statusBadgeClass = 'status-published';
        $statusText = 'Published';
    } elseif ($scheduleState === 'scheduled') {
        $statusBadgeClass = 'status-scheduled';
        $statusText = 'Scheduled';
    } elseif ($scheduleState === 'expired') {
        $statusBadgeClass = 'status-archived';
        $statusText = 'Archived';
    }
    $statusNote = $scheduleInfo['detail'];
    $accessValue = strtolower((string) $accessRaw);
    $isRestricted = $accessValue !== 'public';
    $views = (int) ($p['views'] ?? 0);
    $viewsDisplay = number_format($views);
    $lastModified = isset($p['last_modified']) ? (int) $p['last_modified'] : 0;
    $modifiedDisplay = $lastModified > 0 ? date('M j, Y g:i A', $lastModified) : 'No edits yet';
    $viewUrl = '../?page=' . urlencode($slug);
    $accessLabel = $isRestricted ? 'Private' : 'Public';
    $isHomepageRow = $homepageSlug !== '' && $slug === $homepageSlug;
?>
                    <tr class="pages-list-row"
                        data-id="<?php echo $id; ?>"
                        data-title="<?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-slug="<?php echo htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-content="<?php echo htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-published="<?php echo $isPublished ? 1 : 0; ?>"
                        data-live="<?php echo $isLive ? 1 : 0; ?>"
                        data-schedule_state="<?php echo htmlspecialchars($scheduleState, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-publish_at="<?php echo htmlspecialchars($publishAtValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-unpublish_at="<?php echo htmlspecialchars($unpublishAtValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-template="<?php echo htmlspecialchars($templateName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-meta_title="<?php echo htmlspecialchars($metaTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-meta_description="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-canonical_url="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-og_title="<?php echo htmlspecialchars($ogTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-og_description="<?php echo htmlspecialchars($ogDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-og_image="<?php echo htmlspecialchars($ogImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-robots="<?php echo htmlspecialchars($robotsDirective, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-access="<?php echo htmlspecialchars($accessRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                        data-views="<?php echo $views; ?>"
                        data-last_modified="<?php echo $lastModified; ?>"
                        data-homepage="<?php echo $isHomepageRow ? 1 : 0; ?>"
                        data-page-item="1"
                        data-view="list">
                        <td class="pages-list-cell pages-list-cell--select" data-label="">
                            <input type="checkbox" class="page-row-checkbox" data-id="<?php echo $id; ?>" aria-label="Select <?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        </td>
                        <td class="pages-list-cell pages-list-cell--title" data-label="Page">
                            <div class="pages-list-title">
                                <span class="pages-list-title-text">
                                    <?php if ($isHomepageRow): ?>
                                        <i class="fa-solid fa-house pages-list-title-icon pages-list-title-icon--home" aria-hidden="true"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                </span>
                                <span class="pages-list-slug"><?php echo '/' . htmlspecialchars($slug, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                            </div>
                            <div class="pages-list-badges">
                                <?php if ($isRestricted): ?>
                                    <span class="pages-card__badge pages-card__badge--restricted">
                                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                        Private
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="pages-list-cell pages-list-cell--status" data-label="Status">
                            <span class="status-badge <?php echo htmlspecialchars($statusBadgeClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" data-status-badge aria-label="<?php echo htmlspecialchars('Status: ' . $statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($statusText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                            </span>
                            <span class="pages-list-status-note" data-status-note<?php echo $statusNote === '' ? ' hidden' : ''; ?>>
                                <?php if ($statusNote !== ''): ?>
                                    <?php echo htmlspecialchars($statusNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--views" data-label="Views">
                            <span class="pages-list-views"><?php echo $viewsDisplay; ?></span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--updated" data-label="Last updated">
                            <span class="pages-list-updated">
                                <?php if ($lastModified > 0): ?>
                                    Updated <?php echo htmlspecialchars($modifiedDisplay); ?>
                                <?php else: ?>
                                    No edits yet
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--access" data-label="Access">
                            <span class="pages-list-access"><?php echo htmlspecialchars($accessLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                        </td>
                        <td class="pages-list-cell pages-list-cell--actions" data-label="Actions">
                            <div class="pages-list-actions">
                                <a class="a11y-btn a11y-btn--ghost pages-card__action" data-action="view" href="<?php echo $viewUrl; ?>" target="_blank" rel="noopener">
                                    View
                                </a>
                                <button type="button" class="a11y-btn a11y-btn--secondary pages-card__action editBtn">Settings</button>
                                <button type="button" class="a11y-btn a11y-btn--ghost pages-card__action copyBtn">Copy</button>
                                <button type="button" class="a11y-btn a11y-btn--secondary pages-card__action togglePublishBtn">
                                    <?php echo $isPublished ? 'Unpublish' : 'Publish'; ?>
                                </button>
                                <button type="button" class="a11y-btn a11y-btn--ghost pages-card__action historyBtn" title="View revision history">History</button>
                                <button type="button" class="a11y-btn a11y-btn--danger pages-card__action deleteBtn">Delete</button>
                            </div>
                        </td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <div class="empty-state pages-empty-state" id="pagesEmptyState" hidden aria-labelledby="pagesEmptyStateTitle" aria-describedby="pagesEmptyStateDescription">
            <div class="empty-state__icon" aria-hidden="true">
                <i class="fa-solid fa-file-circle-question"></i>
            </div>
            <div class="empty-state__content">
                <h3 class="empty-state__title" id="pagesEmptyStateTitle">No pages match your filters</h3>
                <p class="empty-state__description" id="pagesEmptyStateDescription">Try a different search term or choose another status filter.</p>
            </div>
            <button type="button" class="a11y-btn a11y-btn--primary empty-state__cta" id="pagesEmptyCta">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>New page</span>
            </button>
        </div>
    </div>

    <div id="pageModal" class="modal page-settings-modal" role="dialog" aria-modal="true" aria-labelledby="formTitle" aria-describedby="pageModalDescription">
        <div class="modal-content">
            <div class="page-modal-surface">
                <button type="button" class="page-modal-close" id="closePageModal" aria-label="Close page settings">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <header class="page-modal-header">
                    <div class="page-modal-header-content">
                        <div class="page-modal-title-row">
                            <h2 class="page-modal-title" id="formTitle">Add New Page</h2>
                            <span class="status-badge status-draft" data-page-status-badge aria-label="Status: Draft">Draft</span>
                        </div>
                        <p class="page-modal-description" id="pageModalDescription">Configure publishing, templates, and metadata before publishing your page.</p>
                    </div>
                    <!-- Draft recovery banner (Feature 5) -->
                    <div class="draft-recovery-banner" id="draftRecoveryBanner" hidden role="alert">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                        <span id="draftRecoveryText">An autosaved draft exists.</span>
                        <button type="button" class="a11y-btn a11y-btn--secondary a11y-btn--sm" id="restoreDraftBtn">Restore Draft</button>
                        <button type="button" class="a11y-btn a11y-btn--ghost a11y-btn--sm" id="discardDraftBtn">Discard</button>
                    </div>
                    <div class="editor-save-state" data-save-state data-state="saved" role="status" aria-live="polite" aria-atomic="true" tabindex="0">
                        <span class="editor-save-state__dot" aria-hidden="true"></span>
                        <span class="editor-save-state__text" data-save-state-text>Saved</span>
                    </div>
                </header>
                <form id="pageForm" class="page-modal-form">
                    <input type="hidden" name="id" id="pageId">
                    <input type="hidden" name="content" id="content">
                    <div class="page-modal-body">
                        <div class="page-modal-sections" id="pageSections">
                            <section class="page-modal-panel" aria-labelledby="pageSettingsHeading">
                                <h3 class="page-modal-panel-title" id="pageSettingsHeading">Page Settings</h3>
                                <div class="page-modal-grid">
                                    <div class="form-group">
                                        <label class="form-label" for="title">Title</label>
                                        <input type="text" class="form-input" name="title" id="title" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="slug">Slug</label>
                                        <input type="text" class="form-input" name="slug" id="slug" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="template">Template</label>
                                        <select class="form-select" name="template" id="template">
                                            <option value="page.php">page.php</option>
                                            <?php foreach ($templates as $t): ?>
                                            <?php if ($t !== 'page.php'): ?>
                                            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="access">Access</label>
                                        <select class="form-select" name="access" id="access">
                                            <option value="public">Public</option>
                                            <option value="private">Private</option>
                                        </select>
                                    </div>
                                    <div class="form-group page-modal-checkbox">
                                        <label class="form-label" for="published">Publishing</label>
                                        <label class="page-modal-toggle">
                                            <input type="checkbox" name="published" id="published">
                                            <span>Published</span>
                                        </label>
                                    </div>
                                    <div class="form-group page-modal-schedule">
                                        <label class="form-label" for="publish_at">Publish at</label>
                                        <input type="datetime-local" class="form-input" name="publish_at" id="publish_at">
                                        <p class="form-hint">Leave blank to publish immediately.</p>
                                    </div>
                                    <div class="form-group page-modal-schedule">
                                        <label class="form-label" for="unpublish_at">Unpublish at</label>
                                        <input type="datetime-local" class="form-input" name="unpublish_at" id="unpublish_at">
                                        <p class="form-hint">Leave blank to keep the page live indefinitely.</p>
                                    </div>
                                    <!-- Scheduling summary card (Feature 7) -->
                                    <div class="schedule-summary" id="scheduleSummary" hidden>
                                        <i class="fa-solid fa-calendar-alt" aria-hidden="true"></i>
                                        <span id="scheduleSummaryText"></span>
                                    </div>
                                    <div class="form-group page-modal-checkbox">
                                        <label class="form-label" for="homepage">Homepage</label>
                                        <label class="page-modal-toggle">
                                            <input type="checkbox" name="homepage" id="homepage">
                                            <span><i class="fa-solid fa-house" aria-hidden="true"></i> Set as homepage</span>
                                        </label>
                                    </div>
                                </div>
                            </section>
                            <section class="page-modal-panel" aria-labelledby="pageSeoHeading">
                                <h3 class="page-modal-panel-title" id="pageSeoHeading">SEO Options</h3>
                                <div class="page-modal-stack">
                                    <div class="form-group">
                                        <label class="form-label" for="meta_title">Meta Title <span class="form-label-note">50–60 characters</span></label>
                                        <input type="text" class="form-input" name="meta_title" id="meta_title">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="meta_description">Meta Description <span class="form-label-note">150–160 characters</span></label>
                                        <textarea class="form-textarea" name="meta_description" id="meta_description" rows="3"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="canonical_url">Canonical URL</label>
                                        <input type="url" class="form-input" name="canonical_url" id="canonical_url" placeholder="https://example.com/your-page">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="robots">Robots directives</label>
                                        <select class="form-select" name="robots" id="robots">
                                            <option value="index,follow">Index &amp; follow (default)</option>
                                            <option value="index,nofollow">Index, nofollow</option>
                                            <option value="noindex,follow">Noindex, follow</option>
                                            <option value="noindex,nofollow">Noindex &amp; nofollow</option>
                                        </select>
                                        <p class="form-hint">Control whether search engines index the page and follow its links.</p>
                                    </div>
                                </div>
                            </section>
                            <section class="page-modal-panel" aria-labelledby="pageOgHeading">
                                <h3 class="page-modal-panel-title" id="pageOgHeading">Social Open Graph</h3>
                                <div class="page-modal-stack">
                                    <div class="form-group">
                                        <label class="form-label" for="og_title">OG Title</label>
                                        <input type="text" class="form-input" name="og_title" id="og_title">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="og_description">OG Description</label>
                                        <textarea class="form-textarea" name="og_description" id="og_description" rows="3"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="og_image">OG Image URL</label>
                                        <div class="page-media-picker" data-page-og-image-picker>
                                            <div class="page-media-preview" data-page-media-preview aria-live="polite">
                                                <span class="page-media-placeholder">No image selected yet.</span>
                                            </div>
                                            <div class="page-media-actions">
                                                <button type="button" class="c-button c-button--secondary" data-page-media-open>
                                                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                                                    <span>Choose image</span>
                                                </button>
                                                <button type="button" class="c-button c-button--ghost" data-page-media-clear hidden>
                                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                    <span>Remove image</span>
                                                </button>
                                            </div>
                                            <input type="url" class="form-input page-media-input" name="og_image" id="og_image" placeholder="https://example.com/og-image.jpg">
                                        </div>
                                        <p class="form-hint">Select from the media library or paste a direct URL.</p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>
                    <footer class="page-modal-footer">
                        <button type="button" class="c-button c-button--secondary" id="cancelEdit">Cancel</button>
                        <button type="submit" class="c-button c-button--primary">Save Page</button>
                    </footer>
                </form>
            </div>
        </div>
    </div>

    <div class="modal page-media-modal" id="pageMediaPickerModal" role="dialog" aria-modal="true" aria-labelledby="pageMediaPickerTitle" aria-describedby="pageMediaPickerDescription">
        <div class="modal-content page-media-modal__content">
            <div class="page-media-modal__surface">
                <button type="button" class="page-media-modal__close" data-page-media-close aria-label="Close media picker">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <header class="page-media-modal__header">
                    <span class="page-media-modal__subtitle">Media library</span>
                    <h2 class="page-media-modal__title" id="pageMediaPickerTitle">Select an OG image</h2>
                    <p class="page-media-modal__description" id="pageMediaPickerDescription">Choose an image from the media library to populate the Open Graph preview.</p>
                </header>
                <div class="page-media-modal__body">
                    <label class="page-media-modal__search" for="pageMediaPickerSearch">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="pageMediaPickerSearch" placeholder="Search media by name or tag" aria-label="Search media library" data-page-media-search>
                    </label>
                    <div class="page-media-grid" data-page-media-grid role="listbox" aria-label="Media library images" aria-live="polite"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revision History Modal (Feature 6) -->
    <div class="modal page-history-modal" id="pageHistoryModal" role="dialog" aria-modal="true" aria-labelledby="pageHistoryTitle">
        <div class="modal-content page-history-modal__content">
            <div class="page-history-modal__surface">
                <button type="button" class="page-history-modal__close" id="closeHistoryModal" aria-label="Close history">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
                <header class="page-history-modal__header">
                    <span class="page-history-modal__subtitle">Revision history</span>
                    <h2 class="page-history-modal__title" id="pageHistoryTitle">Page History</h2>
                    <p class="page-history-modal__description">Browse past saves and restore any revision to its saved state.</p>
                </header>
                <div class="page-history-modal__body">
                    <div id="pageHistoryList" class="page-history-list" aria-live="polite">
                        <p class="page-history-loading">Loading history…</p>
                    </div>
                </div>
                <footer class="page-history-modal__footer">
                    <button type="button" class="c-button c-button--secondary" id="closeHistoryModalFooter">Close</button>
                </footer>
            </div>
        </div>
    </div>

</div>
