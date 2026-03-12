<?php
// File: view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/search_helpers.php';
require_once __DIR__ . '/../../includes/page_schedule.php';
require_login();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$typesParam = $_GET['types'] ?? [];
$selectedTypes = [];
if (is_string($typesParam) && $typesParam !== '') {
    $selectedTypes = array_filter(array_map('trim', explode(',', $typesParam)));
} elseif (is_array($typesParam)) {
    foreach ($typesParam as $typeValue) {
        if (is_string($typeValue) && trim($typeValue) !== '') {
            $selectedTypes[] = trim($typeValue);
        }
    }
}

$normalizedTypes = array_map('strtolower', $selectedTypes);
$searchResult = perform_search($q, ['types' => $normalizedTypes]);
$results = $searchResult['results'];
$typeCounts = $searchResult['counts'];
$resultCount = count($results);
$resultSummary = $resultCount === 1
    ? 'Showing 1 result'
    : 'Showing ' . number_format($resultCount) . ' results';
$querySuffix = $q !== ''
    ? ' for &ldquo;' . htmlspecialchars($q) . '&rdquo;'
    : '';

if ($q !== '') {
    $historyRecords = push_search_history($q);
} else {
    $historyRecords = get_search_history();
}

$searchSuggestions = get_search_suggestions();

$historyDataAttr = htmlspecialchars(json_encode($historyRecords, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$suggestionsAttr = htmlspecialchars(json_encode($searchSuggestions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
$selectedTypesAttr = htmlspecialchars(json_encode($normalizedTypes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');

function search_status_badge(string $status): array {
    $map = [
        'draft' => ['label' => 'Draft', 'class' => 'status-draft'],
        'published' => ['label' => 'Published', 'class' => 'status-published'],
        'scheduled' => ['label' => 'Scheduled', 'class' => 'status-scheduled'],
        'archived' => ['label' => 'Archived', 'class' => 'status-archived'],
    ];
    $key = strtolower($status);
    return $map[$key] ?? $map['draft'];
}
?>
<div class="content-section" id="search" data-query="<?php echo htmlspecialchars($q); ?>" data-history="<?php echo $historyDataAttr; ?>" data-suggestions="<?php echo $suggestionsAttr; ?>" data-selected-types="<?php echo $selectedTypesAttr; ?>">
    <div class="search-dashboard a11y-dashboard">
        <header class="a11y-hero search-hero">
            <div class="a11y-hero-content search-hero-content">
                <div>
                    <span class="hero-eyebrow search-hero-eyebrow">Unified Index</span>
                    <h2 class="a11y-hero-title search-hero-title">Unified Search</h2>
                    <p class="a11y-hero-subtitle search-hero-subtitle">Surface pages, posts, and media without leaving the dashboard.</p>
                </div>
                <div class="a11y-hero-actions search-hero-actions">
                    <span class="a11y-hero-meta search-hero-meta">
                        <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        <?php echo $resultSummary . $querySuffix; ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid search-overview-grid">
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Pages</div>
                    <div class="a11y-overview-value" id="searchCountPages"><?php echo number_format($typeCounts['Page']); ?></div>
                </div>
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Posts</div>
                    <div class="a11y-overview-value" id="searchCountPosts"><?php echo number_format($typeCounts['Post']); ?></div>
                </div>
                <div class="a11y-overview-card search-overview-card">
                    <div class="a11y-overview-label">Media</div>
                    <div class="a11y-overview-value" id="searchCountMedia"><?php echo number_format($typeCounts['Media']); ?></div>
                </div>
            </div>
        </header>

        <?php if (!empty($historyRecords)): ?>
        <section class="search-history-card" aria-labelledby="searchHistoryTitle">
            <header class="search-history-card__header">
                <div>
                    <h3 class="search-history-card__title" id="searchHistoryTitle">Recent searches</h3>
                    <p class="search-history-card__description">Jump back to your frequent queries.</p>
                </div>
            </header>
            <div class="search-history-chips">
                <?php foreach ($historyRecords as $history): ?>
                    <button class="search-history-chip" type="button" data-search-term="<?php echo htmlspecialchars($history['term']); ?>">
                        <span class="search-history-chip__term"><?php echo htmlspecialchars($history['term']); ?></span>
                        <span class="search-history-chip__meta"><?php echo number_format($history['count']); ?>×</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="a11y-detail-card search-results-card">
            <header class="search-results-card__header">
                <div class="search-results-card__intro">
                    <h3 class="search-results-card__title">Search results</h3>
                    <p class="search-results-card__description">Review matches across pages, posts, and media.</p>
                </div>
                <span class="search-results-card__meta"><?php echo $resultSummary . $querySuffix; ?></span>
            </header>
            <div class="search-filters" role="group" aria-label="Filter by content type">
                <?php foreach ($typeCounts as $typeLabel => $count):
                    $lowerType = strtolower($typeLabel);
                    $isChecked = empty($normalizedTypes) || in_array($lowerType, $normalizedTypes, true);
                ?>
                <label class="search-filters__option">
                    <input type="checkbox" value="<?php echo htmlspecialchars($typeLabel); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                    <span><?php echo htmlspecialchars($typeLabel); ?> <small>(<?php echo number_format($count); ?>)</small></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="search-results-table">
                <table class="data-table search-table">
                    <thead>
                        <tr><th scope="col">Type</th><th scope="col">Title</th><th scope="col">Slug</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($results): ?>
                            <?php foreach ($results as $r): ?>
                                <?php
                                    $record = $r['record'];
                                    if(($r['type'] ?? '') === 'Post') {
                                        $viewUrl = '../blogs/' . urlencode($record['slug'] ?? $r['slug']);
                                        $statusKey = strtolower($record['status'] ?? 'draft');
                                    } elseif(($r['type'] ?? '') === 'Media') {
                                        $viewUrl = '../' . ltrim($record['file'] ?? $r['slug'], '/');
                                        $statusKey = 'archived';
                                    } else {
                                        $viewUrl = '../?page=' . urlencode($record['slug'] ?? $r['slug']);
                                        if(isset($_SESSION['user'])) {
                                            $viewUrl = '../liveed/builder.php?id=' . urlencode($record['id'] ?? $r['id']);
                                        }
                                        $scheduleInfo = sparkcms_page_schedule_info($record);
                                        $statusKey = $scheduleInfo['state'] === 'expired' ? 'archived' : $scheduleInfo['state'];
                                    }
                                    $statusBadge = search_status_badge($statusKey);
                                ?>
                                <tr data-id="<?php echo htmlspecialchars((string) $r['id']); ?>" data-type="<?php echo htmlspecialchars($r['type']); ?>" data-score="<?php echo htmlspecialchars(number_format($r['score'], 4, '.', '')); ?>">
                                    <td><?php echo htmlspecialchars($r['type'] ?? ''); ?></td>
                                    <td>
                                        <div class="search-result-title"><?php echo htmlspecialchars($r['title']); ?></div>
                                        <?php if (!empty($r['snippet'])): ?>
                                            <div class="search-result-snippet"><?php echo $r['snippet']; ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($r['slug']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo htmlspecialchars($statusBadge['class']); ?>" aria-label="<?php echo htmlspecialchars('Status: ' . $statusBadge['label']); ?>">
                                            <?php echo htmlspecialchars($statusBadge['label']); ?>
                                        </span>
                                    </td>
                                    <td><a class="btn btn-secondary" href="<?php echo $viewUrl; ?>" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square btn-icon" aria-hidden="true"></i><span class="btn-label">View</span></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="search-empty-row" data-filter-empty="true" style="display:none;"><td colspan="5">No results match the selected filters.</td></tr>
                        <?php else: ?>
                            <tr class="search-empty-row"><td colspan="5">No results found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
