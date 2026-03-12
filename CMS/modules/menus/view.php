<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);
if (!is_array($menus)) {
    $menus = [];
}

$lastUpdatedTimestamp = get_storage_last_modified($menusFile);
$lastUpdatedIso = $lastUpdatedTimestamp ? date(DATE_ATOM, $lastUpdatedTimestamp) : '';
$lastUpdatedDisplay = $lastUpdatedTimestamp ? date('M j, Y g:i A', $lastUpdatedTimestamp) : 'Not available';

function count_menu_items_recursive(?array $items): int
{
    if (empty($items)) {
        return 0;
    }

    $count = 0;
    foreach ($items as $item) {
        $count++;
        if (!empty($item['children']) && is_array($item['children'])) {
            $count += count_menu_items_recursive($item['children']);
        }
    }

    return $count;
}

function menu_has_nested_children(?array $items): bool
{
    if (empty($items)) {
        return false;
    }

    foreach ($items as $item) {
        if (!empty($item['children']) && is_array($item['children'])) {
            return true;
        }
    }

    return false;
}

function menu_max_depth(?array $items, int $depth = 1): int
{
    if (empty($items)) {
        return $depth === 1 ? 0 : $depth - 1;
    }

    $maxDepth = $depth;
    foreach ($items as $item) {
        if (!empty($item['children']) && is_array($item['children'])) {
            $maxDepth = max($maxDepth, menu_max_depth($item['children'], $depth + 1));
        }
    }

    return $maxDepth;
}

$totalMenus = count($menus);
$totalLinks = 0;
$menusWithNested = 0;
$deepestLevel = 0;

foreach ($menus as $menu) {
    $items = isset($menu['items']) && is_array($menu['items']) ? $menu['items'] : [];
    $linkCount = count_menu_items_recursive($items);
    $totalLinks += $linkCount;

    $depth = menu_max_depth($items);
    $deepestLevel = max($deepestLevel, $depth);

    if (menu_has_nested_children($items)) {
        $menusWithNested++;
    }
}

$singleLevelMenus = max(0, $totalMenus - $menusWithNested);
$averageLinks = $totalMenus > 0 ? round($totalLinks / $totalMenus, 1) : 0;
$filterCounts = [
    'all' => $totalMenus,
    'nested' => $menusWithNested,
    'single' => $singleLevelMenus,
];
?>
<div class="content-section" id="menus">
    <div class="menu-dashboard" data-last-updated="<?php echo htmlspecialchars($lastUpdatedIso, ENT_QUOTES); ?>">
        <header class="a11y-hero menu-hero">
            <div class="a11y-hero-content menu-hero-content">
                <div>
                    <span class="hero-eyebrow menu-hero-eyebrow">Navigation Health</span>
                    <h2 class="a11y-hero-title menu-hero-title">Navigation Menus</h2>
                    <p class="a11y-hero-subtitle menu-hero-subtitle">Craft intuitive navigation experiences and keep every menu in sync with your site's structure.</p>
                </div>
                <div class="a11y-hero-actions menu-hero-actions">
                    <button type="button" class="menu-btn menu-btn--primary" id="newMenuBtn">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <span>New Menu</span>
                    </button>
                    <button type="button" class="menu-btn menu-btn--icon" id="refreshMenusBtn" aria-label="Refresh menus">
                        <i class="fas fa-rotate" aria-hidden="true"></i>
                    </button>
                    <span class="a11y-hero-meta menu-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last updated: <span class="js-last-updated"><?php echo htmlspecialchars($lastUpdatedDisplay, ENT_QUOTES); ?></span>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid menu-overview-grid">
                <div class="a11y-overview-card menu-overview-card">
                    <div class="a11y-overview-label menu-overview-label">Total Menus</div>
                    <div class="a11y-overview-value menu-overview-value" id="menuStatTotal"><?php echo $totalMenus; ?></div>
                </div>
                <div class="a11y-overview-card menu-overview-card">
                    <div class="a11y-overview-label menu-overview-label">Total Links</div>
                    <div class="a11y-overview-value menu-overview-value" id="menuStatLinks"><?php echo $totalLinks; ?></div>
                </div>
                <div class="a11y-overview-card menu-overview-card">
                    <div class="a11y-overview-label menu-overview-label">Menus with Submenus</div>
                    <div class="a11y-overview-value menu-overview-value" id="menuStatNested"><?php echo $menusWithNested; ?></div>
                </div>
                <div class="a11y-overview-card menu-overview-card">
                    <div class="a11y-overview-label menu-overview-label">Average Links per Menu</div>
                    <div class="a11y-overview-value menu-overview-value" id="menuStatAverage"><?php echo $averageLinks; ?></div>
                </div>
            </div>
        </header>

        <div class="menu-controls">
            <label class="menu-search" for="menuSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="menuSearchInput" placeholder="Search menus by name or link" aria-label="Search menus">
            </label>
            <div class="menu-filter-group" role="group" aria-label="Menu filters">
                <button type="button" class="menu-filter-btn active" data-menu-filter="all">All Menus <span class="menu-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="menu-filter-btn" data-menu-filter="nested">With submenus <span class="menu-filter-count" data-count="nested"><?php echo $filterCounts['nested']; ?></span></button>
                <button type="button" class="menu-filter-btn" data-menu-filter="single">Single level <span class="menu-filter-count" data-count="single"><?php echo $filterCounts['single']; ?></span></button>
            </div>
        </div>

        <div class="menu-table" id="menuTable" aria-live="polite">
            <div class="menu-table-header">
                <div>Name</div>
                <div>Links</div>
                <div>Structure</div>
                <div>Actions</div>
            </div>
            <div class="menu-table-body" id="menuTableBody"></div>
        </div>

        <div class="empty-state menu-empty-search" id="menuNoResults" hidden aria-labelledby="menuNoResultsTitle" aria-describedby="menuNoResultsDescription">
            <div class="empty-state__icon" aria-hidden="true">
                <i class="fas fa-filter"></i>
            </div>
            <div class="empty-state__content">
                <h3 class="empty-state__title" id="menuNoResultsTitle">No menus match your filters</h3>
                <p class="empty-state__description" id="menuNoResultsDescription">Try adjusting the search or switching to a different filter.</p>
            </div>
            <button type="button" class="menu-btn menu-btn--primary empty-state__cta" id="menuNoResultsCta">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span>Create menu</span>
            </button>
        </div>

        <div class="empty-state menu-empty-state" id="menuEmptyState" <?php echo $totalMenus > 0 ? 'hidden' : ''; ?> aria-labelledby="menuEmptyStateTitle" aria-describedby="menuEmptyStateDescription">
            <div class="empty-state__icon" aria-hidden="true">
                <i class="fas fa-sitemap"></i>
            </div>
            <div class="empty-state__content">
                <h3 class="empty-state__title" id="menuEmptyStateTitle">No menus yet</h3>
                <p class="empty-state__description" id="menuEmptyStateDescription">Create your first navigation menu to help visitors find key pages.</p>
            </div>
            <button type="button" class="menu-btn menu-btn--primary empty-state__cta" id="emptyStateCreateMenu">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <span>Create Menu</span>
            </button>
        </div>
    </div>

    <div class="menu-editor-card" id="menuFormCard" aria-hidden="true">
        <header class="menu-editor-header">
            <div>
                <h3 id="menuFormTitle">Add Menu</h3>
                <p class="menu-editor-subtitle">Drag and drop to arrange links and nest submenus.</p>
            </div>
            <button type="button" class="menu-editor-close" id="closeMenuEditor" aria-label="Close menu editor">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </header>
        <form id="menuForm">
            <input type="hidden" name="id" id="menuId">
            <div class="form-group">
                <label class="form-label" for="menuName">Menu Name</label>
                <input type="text" class="form-input" name="name" id="menuName" required>
            </div>
            <div class="form-group">
                <label class="form-label">Menu Items</label>
                <p class="menu-editor-hint">Choose a page or custom URL, then drag handles to reorder or nest items.</p>
                <ul id="menuItems" class="menu-list"></ul>
                <button type="button" class="menu-btn menu-btn--secondary menu-btn--sm" id="addMenuItem">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    <span>Add Item</span>
                </button>
            </div>
            <div class="menu-form-actions">
                <button type="submit" class="menu-btn menu-btn--primary">Save Menu</button>
                <button type="button" class="menu-btn menu-btn--ghost" id="cancelMenuEdit">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
window.menuDashboardInitialData = <?php echo json_encode($menus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
