<?php
// File: modules/speed/view.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/score_history.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$settings = get_site_settings();
$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');

function capture_template_html(string $templateFile, array $settings, array $menus, string $scriptBase): string {
    $page = ['content' => '{{CONTENT}}'];
    $themeBase = $scriptBase . '/theme';
    ob_start();
    include $templateFile;
    $html = ob_get_clean();
    $html = preg_replace('/<div class="drop-area"><\/div>/', '{{CONTENT}}', $html, 1);
    if (strpos($html, '{{CONTENT}}') === false) {
        $html .= '{{CONTENT}}';
    }
    $html = preg_replace('#<templateSetting[^>]*>.*?</templateSetting>#si', '', $html);
    $html = preg_replace('#<div class="block-controls"[^>]*>.*?</div>#si', '', $html);
    $html = str_replace('draggable="true"', '', $html);
    $html = preg_replace('#\sdata-ts="[^"]*"#i', '', $html);
    $html = preg_replace('#\sdata-(?:blockid|template|original|active|custom_[A-Za-z0-9_-]+)="[^"]*"#i', '', $html);
    return $html;
}

function build_page_html(array $page, array $settings, array $menus, string $scriptBase, ?string $templateDir): string {
    static $templateCache = [];

    if (!$templateDir) {
        return (string)($page['content'] ?? '');
    }

    $templateName = !empty($page['template']) ? basename($page['template']) : 'page.php';
    $templateFile = $templateDir . DIRECTORY_SEPARATOR . $templateName;
    if (!is_file($templateFile)) {
        return (string)($page['content'] ?? '');
    }

    if (!isset($templateCache[$templateFile])) {
        $templateCache[$templateFile] = capture_template_html($templateFile, $settings, $menus, $scriptBase);
    }

    $templateHtml = $templateCache[$templateFile];
    $content = (string)($page['content'] ?? '');
    return str_replace('{{CONTENT}}', $content, $templateHtml);
}

function describe_performance_grade(string $grade): string {
    switch ($grade) {
        case 'A':
            return 'This page is highly optimized and should feel fast for most visitors.';
        case 'B':
            return 'Overall performance is strong with a few opportunities for speed gains.';
        case 'C':
            return 'This page needs optimization to avoid noticeable slowdowns during peak traffic.';
        default:
            return 'Heavy assets or blocking scripts are likely to cause a slow experience. Prioritize fixes soon.';
    }
}

function summarize_alerts(array $alerts): string {
    $parts = [];
    if (!empty($alerts['critical'])) {
        $parts[] = $alerts['critical'] . ' critical';
    }
    if (!empty($alerts['serious'])) {
        $parts[] = $alerts['serious'] . ' major';
    }
    if (!empty($alerts['moderate'])) {
        $parts[] = $alerts['moderate'] . ' moderate';
    }
    if (!empty($alerts['minor'])) {
        $parts[] = $alerts['minor'] . ' minor';
    }

    if (empty($parts)) {
        return 'No outstanding alerts detected';
    }

    $total = $alerts['total'] ?? array_sum($alerts);
    return $total . ' total (' . implode(', ', $parts) . ')';
}

function grade_to_score_class(string $grade): string {
    switch ($grade) {
        case 'A':
            return 'speed-score--a';
        case 'B':
            return 'speed-score--b';
        case 'C':
            return 'speed-score--c';
        default:
            return 'speed-score--d';
    }
}

function grade_to_badge_class(string $grade): string {
    switch ($grade) {
        case 'A':
            return 'grade-a';
        case 'B':
            return 'grade-b';
        case 'C':
            return 'grade-c';
        default:
            return 'grade-d';
    }
}

function speed_format_change(float $value, int $decimals = 0): string
{
    $absValue = number_format(abs($value), $decimals, '.', '');
    if ($value > 0) {
        return '+' . $absValue;
    }
    if ($value < 0) {
        return '-' . $absValue;
    }

    return number_format(0, $decimals, '.', '');
}

function speed_calculate_change(float $current, ?float $previous): array
{
    $hasBaseline = $previous !== null;
    $absoluteChange = $hasBaseline ? $current - (float)$previous : 0.0;
    $direction = 'neutral';
    if ($absoluteChange > 0) {
        $direction = 'positive';
    } elseif ($absoluteChange < 0) {
        $direction = 'negative';
    }

    $percentChange = null;
    if ($hasBaseline) {
        if ($previous == 0.0) {
            $percentChange = $absoluteChange == 0.0 ? 0.0 : null;
        } else {
            $percentChange = ($absoluteChange / $previous) * 100;
        }
    }

    return [
        'current' => $current,
        'previous' => $previous,
        'absolute' => $absoluteChange,
        'percent' => $percentChange,
        'direction' => $direction,
        'hasBaseline' => $hasBaseline,
    ];
}

function speed_render_delta(?array $delta, string $statLabel, string $unitSingular, ?string $unitPlural = null, int $absoluteDecimals = 0, int $percentDecimals = 1): string
{
    $unitPlural = $unitPlural ?? $unitSingular . 's';
    $statLabel = trim($statLabel);

    if (!$delta || empty($delta['hasBaseline'])) {
        $srText = $statLabel . ' baseline established. No previous data available yet.';
        return '<div class="a11y-overview-delta" aria-live="polite">'
            . '<span class="speed-delta__value speed-delta__value--neutral speed-delta__value--baseline" aria-hidden="true">'
            . '<i class="fas fa-circle-dot" aria-hidden="true"></i>'
            . '<span>Baseline set</span>'
            . '</span>'
            . '<span class="sr-only">' . htmlspecialchars($srText, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div>';
    }

    $direction = $delta['direction'] ?? 'neutral';
    $directionClass = 'speed-delta__value--' . $direction;
    $absolute = (float)($delta['absolute'] ?? 0.0);
    $percentRaw = $delta['percent'];
    $percentAvailable = $percentRaw !== null;

    $percentClass = 'speed-delta__value ' . ($percentAvailable ? $directionClass : 'speed-delta__value--neutral speed-delta__value--empty');
    $absoluteClass = 'speed-delta__value ' . $directionClass;

    $icon = 'fa-minus';
    if ($direction === 'positive') {
        $icon = 'fa-arrow-trend-up';
    } elseif ($direction === 'negative') {
        $icon = 'fa-arrow-trend-down';
    }

    if (!$percentAvailable) {
        $icon = 'fa-circle-question';
    }

    $percentText = $percentAvailable
        ? speed_format_change((float)$percentRaw, $percentDecimals) . '%'
        : 'No trend';
    $absoluteUnit = abs($absolute) === 1.0 ? $unitSingular : $unitPlural;
    $absoluteText = speed_format_change($absolute, $absoluteDecimals) . ' ' . $absoluteUnit;

    $absNumber = number_format(abs($absolute), $absoluteDecimals, '.', '');
    $percentNumber = $percentAvailable ? number_format(abs((float)$percentRaw), $percentDecimals, '.', '') : null;

    if ($absolute == 0.0) {
        $srSummary = $statLabel . ' remained unchanged compared to the previous scan.';
    } else {
        $srSummary = $statLabel . ' ' . ($absolute > 0 ? 'increased' : 'decreased') . ' by ' . $absNumber . ' ' . $absoluteUnit . ' compared to the previous scan.';
    }

    if ($percentAvailable) {
        if ((float)$percentRaw === 0.0) {
            $srSummary .= ' There was no percentage change.';
        } else {
            $srSummary .= ' That is ' . ((float)$percentRaw > 0 ? 'up ' : 'down ') . $percentNumber . ' percent.';
        }
    } else {
        $srSummary .= ' Percentage change is not available yet.';
    }

    $html = '<div class="a11y-overview-delta" aria-live="polite">';
    $html .= '<span class="' . $percentClass . '" aria-hidden="true">';
    $html .= '<i class="fas ' . $icon . '" aria-hidden="true"></i>';
    $html .= '<span>' . htmlspecialchars($percentText, ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '</span>';
    $html .= '<span class="' . $absoluteClass . '" aria-hidden="true">';
    $html .= htmlspecialchars($absoluteText, ENT_QUOTES, 'UTF-8');
    $html .= '</span>';
    $html .= '<span class="sr-only">' . htmlspecialchars($srSummary, ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '</div>';

    return $html;
}

libxml_use_internal_errors(true);

$snapshotFile = __DIR__ . '/../../data/speed_snapshot.json';
$previousSnapshotRaw = read_json_file($snapshotFile);
$previousSnapshot = is_array($previousSnapshotRaw) ? $previousSnapshotRaw : [];

$report = [];
$totalPages = 0;
$scoreSum = 0;
$criticalAlertsTotal = 0;
$fastPages = 0;
$slowPages = 0;
$pageEntryMap = [];
$filterCounts = [
    'all' => 0,
    'slow' => 0,
    'monitor' => 0,
    'fast' => 0,
];
$heaviestPage = null;
$scanTimestamp = date('M j, Y g:i A');

foreach ($pages as $page) {
    $title = $page['title'] ?? 'Untitled';
    $slug = $page['slug'] ?? '';
    $path = '/' . ltrim($slug, '/');
    $pageHtml = build_page_html($page, $settings, $menus, $scriptBase, $templateDir);

    $doc = new DOMDocument();
    $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

    $htmlBytes = strlen($pageHtml);
    $htmlSizeKb = round($htmlBytes / 1024, 1);
    $wordCount = str_word_count(strip_tags($pageHtml));

    $imageCount = 0;
    $scriptCount = 0;
    $inlineScriptCount = 0;
    $stylesheetCount = 0;
    $inlineStyleBlocks = 0;
    $iframeCount = 0;
    $domNodes = 0;

    if ($loaded) {
        $domNodes = $doc->getElementsByTagName('*')->length;
        $images = $doc->getElementsByTagName('img');
        $imageCount = $images->length;

        $scripts = $doc->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $scriptCount++;
            if (!$script->hasAttribute('src')) {
                $inlineScriptCount++;
            }
        }

        $links = $doc->getElementsByTagName('link');
        foreach ($links as $link) {
            if (strtolower($link->getAttribute('rel')) === 'stylesheet') {
                $stylesheetCount++;
            }
        }

        $styles = $doc->getElementsByTagName('style');
        $inlineStyleBlocks = $styles->length;

        $iframes = $doc->getElementsByTagName('iframe');
        $iframeCount = $iframes->length;
    } else {
        $domNodes = max(0, substr_count($pageHtml, '<'));
        $imageCount = substr_count(strtolower($pageHtml), '<img');
        $scriptCount = substr_count(strtolower($pageHtml), '<script');
        $stylesheetCount = substr_count(strtolower($pageHtml), 'rel="stylesheet"');
        $inlineScriptCount = max(0, $scriptCount - substr_count(strtolower($pageHtml), 'src='));
        $inlineStyleBlocks = substr_count(strtolower($pageHtml), '<style');
        $iframeCount = substr_count(strtolower($pageHtml), '<iframe');
    }

    $estimatedWeightKb = round($htmlSizeKb + ($imageCount * 45) + ($scriptCount * 12) + ($stylesheetCount * 8), 1);
    $avgImageWeight = $imageCount > 0 ? round($estimatedWeightKb / $imageCount, 1) : 0;

    $issues = [];
    $addIssue = static function (array &$issues, string $impact, string $description, string $recommendation): void {
        $issues[] = [
            'impact' => $impact,
            'description' => $description,
            'recommendation' => $recommendation,
        ];
    };

    if ($estimatedWeightKb > 900) {
        $addIssue($issues, 'critical', 'Estimated page weight exceeds 900 KB', 'Compress large assets, enable caching, and consider splitting content across lighter templates.');
    } elseif ($estimatedWeightKb > 600) {
        $addIssue($issues, 'serious', 'Estimated page weight above 600 KB', 'Minify HTML, compress imagery, and lazy-load non-critical resources.');
    } elseif ($estimatedWeightKb > 400) {
        $addIssue($issues, 'moderate', 'Estimated page weight above 400 KB', 'Audit media assets and remove unused scripts or styles to reduce payload.');
    }

    if ($imageCount > 15) {
        $addIssue($issues, 'serious', $imageCount . ' images detected', 'Use responsive image sizes, next-gen formats, and defer offscreen assets.');
    } elseif ($imageCount > 9) {
        $addIssue($issues, 'moderate', $imageCount . ' images detected', 'Review gallery content and apply lazy loading for below-the-fold imagery.');
    }

    if ($scriptCount > 7) {
        $addIssue($issues, 'serious', $scriptCount . ' scripts included', 'Bundle and defer non-critical JavaScript to shorten the main thread.');
    } elseif ($scriptCount > 4) {
        $addIssue($issues, 'moderate', $scriptCount . ' scripts included', 'Audit third-party embeds and remove unused libraries to improve speed.');
    }

    if ($stylesheetCount + $inlineStyleBlocks > 6) {
        $addIssue($issues, 'minor', 'Multiple blocking stylesheets detected', 'Combine styles where possible and inline only the critical CSS.');
    }

    if ($inlineScriptCount > 0) {
        $addIssue($issues, 'minor', $inlineScriptCount . ' inline script block(s)', 'Move inline logic into external files to improve caching and diagnostics.');
    }

    if ($domNodes > 1500) {
        $addIssue($issues, 'moderate', 'Large DOM tree with ' . $domNodes . ' nodes', 'Simplify nested layouts and remove unnecessary wrappers to speed up rendering.');
    } elseif ($domNodes > 1000) {
        $addIssue($issues, 'minor', 'DOM tree approaching heavy threshold (' . $domNodes . ' nodes)', 'Consider breaking long pages into sections and trimming unused markup.');
    }

    if ($iframeCount > 0) {
        $addIssue($issues, 'minor', $iframeCount . ' embedded frame(s)', 'Lazy-load embedded media or replace with preview placeholders to reduce startup cost.');
    }

    $critical = 0;
    $serious = 0;
    $moderate = 0;
    $minor = 0;
    foreach ($issues as $issue) {
        switch ($issue['impact']) {
            case 'critical':
                $critical++;
                break;
            case 'serious':
                $serious++;
                break;
            case 'moderate':
                $moderate++;
                break;
            default:
                $minor++;
                break;
        }
    }

    $alerts = [
        'critical' => $critical,
        'serious' => $serious,
        'moderate' => $moderate,
        'minor' => $minor,
    ];
    $alerts['total'] = array_sum($alerts);

    $score = 100;
    $score -= max(0, $estimatedWeightKb - 300) * 0.08;
    $score -= max(0, $imageCount - 8) * 1.5;
    $score -= max(0, $scriptCount - 5) * 2.5;
    $score -= max(0, ($stylesheetCount + $inlineStyleBlocks) - 4) * 1.2;
    $score -= max(0, $domNodes - 900) * 0.02;
    $score -= $inlineScriptCount * 0.5;
    $score = max(0, min(100, (int)round($score)));

    if ($alerts['total'] === 0 && $score > 96) {
        $score = 98;
    }

    if ($score >= 90) {
        $grade = 'A';
    } elseif ($score >= 80) {
        $grade = 'B';
    } elseif ($score >= 70) {
        $grade = 'C';
    } else {
        $grade = 'D';
    }

    $performanceCategory = 'fast';
    if ($alerts['critical'] > 0 || $score < 70) {
        $performanceCategory = 'slow';
    } elseif ($score < 90 || $alerts['serious'] > 0 || $grade === 'C') {
        $performanceCategory = 'monitor';
    }

    switch ($performanceCategory) {
        case 'fast':
            $filterCounts['fast']++;
            $fastPages++;
            break;
        case 'monitor':
            $filterCounts['monitor']++;
            break;
        case 'slow':
            $filterCounts['slow']++;
            $slowPages++;
            break;
    }

    $filterCounts['all']++;
    $totalPages++;
    $scoreSum += $score;
    $criticalAlertsTotal += $alerts['critical'];

    if ($heaviestPage === null || $estimatedWeightKb > $heaviestPage['weight']) {
        $heaviestPage = [
            'title' => $title,
            'slug' => $slug,
            'weight' => $estimatedWeightKb,
            'url' => $path,
        ];
    }

    $issuePreview = array_slice(array_map(static function ($detail) {
        return $detail['description'];
    }, $issues), 0, 4);
    if (empty($issuePreview)) {
        $issuePreview = ['No outstanding alerts'];
    }

    $previousScore = derive_previous_score('speed', $slug !== '' ? $slug : ($title !== '' ? $title : (string) $pageIndex), $score);

    $pageData = [
        'title' => $title,
        'slug' => $slug,
        'url' => $path,
        'path' => $path,
        'template' => $page['template'] ?? '',
        'performanceScore' => $score,
        'previousScore' => $previousScore,
        'grade' => $grade,
        'gradeClass' => grade_to_badge_class($grade),
        'scoreClass' => grade_to_score_class($grade),
        'alerts' => $alerts,
        'warnings' => $alerts['serious'] + $alerts['moderate'] + $alerts['minor'],
        'lastScanned' => $scanTimestamp,
        'pageType' => !empty($page['template']) ? 'Template: ' . basename($page['template']) : 'Standard Page',
        'performanceCategory' => $performanceCategory,
        'issues' => [
            'preview' => $issuePreview,
            'details' => $issues,
        ],
        'metrics' => [
            'weightKb' => $estimatedWeightKb,
            'htmlSizeKb' => $htmlSizeKb,
            'imageCount' => $imageCount,
            'scriptCount' => $scriptCount,
            'stylesheetCount' => $stylesheetCount,
            'inlineScripts' => $inlineScriptCount,
            'inlineStyles' => $inlineStyleBlocks,
            'domNodes' => $domNodes,
            'wordCount' => $wordCount,
            'avgImageWeight' => $avgImageWeight,
            'iframeCount' => $iframeCount,
        ],
    ];

    $pageData['statusMessage'] = describe_performance_grade($grade);
    $pageData['summaryLine'] = sprintf(
        'Performance score %d%%. %s.',
        $score,
        summarize_alerts($alerts)
    );

    $report[] = $pageData;
    $pageEntryMap[$slug] = $pageData;
}

$avgScore = $totalPages > 0 ? (int)round($scoreSum / $totalPages) : 0;
$lastScan = $scanTimestamp;

$moduleUrl = $_SERVER['PHP_SELF'] . '?module=speed';
$detailSlug = isset($_GET['page']) ? sanitize_text($_GET['page']) : null;
$detailSlug = $detailSlug !== null ? trim($detailSlug) : null;

$selectedPage = null;
if ($detailSlug !== null && $detailSlug !== '') {
    $selectedPage = $pageEntryMap[$detailSlug] ?? null;
}

libxml_clear_errors();

$dashboardStats = [
    'totalPages' => $totalPages,
    'avgScore' => $avgScore,
    'criticalAlerts' => $criticalAlertsTotal,
    'fastPages' => $fastPages,
    'slowPages' => $slowPages,
    'filterCounts' => $filterCounts,
    'moduleUrl' => $moduleUrl,
    'detailBaseUrl' => $moduleUrl . '&page=',
    'lastScan' => $lastScan,
    'heaviestPage' => $heaviestPage,
];

$previousTotals = [
    'totalPages' => isset($previousSnapshot['totalPages']) ? (float)$previousSnapshot['totalPages'] : null,
    'avgScore' => isset($previousSnapshot['avgScore']) ? (float)$previousSnapshot['avgScore'] : null,
    'criticalAlerts' => isset($previousSnapshot['criticalAlerts']) ? (float)$previousSnapshot['criticalAlerts'] : null,
    'slowPages' => isset($previousSnapshot['slowPages']) ? (float)$previousSnapshot['slowPages'] : null,
];

$dashboardStats['deltas'] = [
    'totalPages' => speed_calculate_change((float)$totalPages, $previousTotals['totalPages']),
    'avgScore' => speed_calculate_change((float)$avgScore, $previousTotals['avgScore']),
    'criticalAlerts' => speed_calculate_change((float)$criticalAlertsTotal, $previousTotals['criticalAlerts']),
    'slowPages' => speed_calculate_change((float)$slowPages, $previousTotals['slowPages']),
];

$currentSnapshot = [
    'timestamp' => time(),
    'totalPages' => $totalPages,
    'avgScore' => $avgScore,
    'criticalAlerts' => $criticalAlertsTotal,
    'slowPages' => $slowPages,
];
write_json_file($snapshotFile, $currentSnapshot);
?>
<div class="content-section" id="performance">
<?php if ($selectedPage): ?>
    <div class="a11y-detail-page" id="speedDetailPage" data-page-slug="<?php echo htmlspecialchars($selectedPage['slug'], ENT_QUOTES); ?>">
        <?php
            $currentScore = (int) ($selectedPage['performanceScore'] ?? 0);
            $previousScore = (int) ($selectedPage['previousScore'] ?? $currentScore);
            $deltaMeta = describe_score_delta($currentScore, $previousScore);
        ?>
        <header class="a11y-detail-header">
            <a href="<?php echo htmlspecialchars($moduleUrl, ENT_QUOTES); ?>" class="a11y-back-link" id="speedBackToDashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Back to Performance Dashboard</span>
            </a>
            <div class="a11y-detail-actions">
                <button type="button" class="a11y-btn a11y-btn--ghost" data-speed-action="rescan-page">
                    <i class="fas fa-rotate" aria-hidden="true"></i>
                    <span>Rescan Page</span>
                </button>
                <button type="button" class="a11y-btn a11y-btn--secondary" data-speed-action="export-page-report">
                    <i class="fas fa-file-export" aria-hidden="true"></i>
                    <span>Export Speed Report</span>
                </button>
            </div>
        </header>

        <section class="a11y-health-card">
            <div class="a11y-health-score">
                <div class="score-indicator score-indicator--hero">
                    <div class="a11y-health-score__value">
                        <span class="score-indicator__number"><?php echo $currentScore; ?></span><span>%</span>
                    </div>
                    <span class="score-delta <?php echo htmlspecialchars($deltaMeta['class'], ENT_QUOTES); ?>">
                        <span aria-hidden="true"><?php echo htmlspecialchars($deltaMeta['display'], ENT_QUOTES); ?></span>
                        <span class="sr-only"><?php echo htmlspecialchars($deltaMeta['srText'], ENT_QUOTES); ?></span>
                    </span>
                </div>
                <div class="a11y-health-score__label">Performance Score</div>
                <span class="a11y-health-score__badge <?php echo htmlspecialchars($selectedPage['gradeClass']); ?>"><?php echo htmlspecialchars($selectedPage['grade']); ?></span>
            </div>
            <div class="a11y-health-summary">
                <h1><?php echo htmlspecialchars($selectedPage['title']); ?></h1>
                <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['url']); ?></p>
                <p><?php echo htmlspecialchars($selectedPage['statusMessage']); ?></p>
                <p class="a11y-health-overview"><?php echo htmlspecialchars($selectedPage['summaryLine']); ?></p>
                <div class="a11y-quick-stats">
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['weightKb']; ?> KB</div>
                        <div class="a11y-quick-stat__label">Estimated Weight</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['imageCount']; ?></div>
                        <div class="a11y-quick-stat__label">Images</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['scriptCount']; ?></div>
                        <div class="a11y-quick-stat__label">Scripts</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['alerts']['critical']; ?></div>
                        <div class="a11y-quick-stat__label">Critical Alerts</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="a11y-detail-grid">
            <article class="a11y-detail-card">
                <h2>Performance checkpoints</h2>
                <div class="a11y-detail-metrics">
                    <div>
                        <span class="a11y-detail-metric__label">HTML payload</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['htmlSizeKb']; ?> KB</span>
                        <span class="a11y-detail-metric__hint">Keep markup lean and remove unused sections.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Scripts</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['scriptCount']; ?> total (<?php echo $selectedPage['metrics']['inlineScripts']; ?> inline)</span>
                        <span class="a11y-detail-metric__hint">Defer non-critical logic and audit third-party tags.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Stylesheets</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['stylesheetCount']; ?> linked, <?php echo $selectedPage['metrics']['inlineStyles']; ?> inline</span>
                        <span class="a11y-detail-metric__hint">Inline only critical CSS, load the rest asynchronously.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">DOM nodes</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['domNodes']; ?></span>
                        <span class="a11y-detail-metric__hint">Keep below 1,500 for optimal rendering.</span>
                    </div>
                </div>
            </article>
            <article class="a11y-detail-card">
                <h2>Alert breakdown</h2>
                <ul class="a11y-violation-list">
                    <li><span>Critical</span><span><?php echo $selectedPage['alerts']['critical']; ?></span></li>
                    <li><span>Major</span><span><?php echo $selectedPage['alerts']['serious']; ?></span></li>
                    <li><span>Moderate</span><span><?php echo $selectedPage['alerts']['moderate']; ?></span></li>
                    <li><span>Minor</span><span><?php echo $selectedPage['alerts']['minor']; ?></span></li>
                </ul>
                <div class="a11y-detail-meta">
                    <div>
                        <span class="a11y-detail-meta__label">Last scanned</span>
                        <span class="a11y-detail-meta__value"><?php echo htmlspecialchars($dashboardStats['lastScan']); ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-meta__label">Page type</span>
                        <span class="a11y-detail-meta__value"><?php echo htmlspecialchars($selectedPage['pageType']); ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-meta__label">Warnings</span>
                        <span class="a11y-detail-meta__value"><?php echo $selectedPage['warnings']; ?></span>
                    </div>
                </div>
            </article>
        </section>

        <section class="a11y-detail-issues">
            <div class="a11y-detail-issues__header">
                <h2>Optimization opportunities</h2>
                <span><?php echo $selectedPage['alerts']['total']; ?> total</span>
            </div>
            <?php if (!empty($selectedPage['issues']['details'])): ?>
                <div class="a11y-issue-list">
                    <?php foreach ($selectedPage['issues']['details'] as $issue): ?>
                        <article class="a11y-issue-card impact-<?php echo htmlspecialchars($issue['impact']); ?>">
                            <header>
                                <h3><?php echo htmlspecialchars($issue['description']); ?></h3>
                                <span class="a11y-impact-badge impact-<?php echo htmlspecialchars($issue['impact']); ?>"><?php echo ucfirst($issue['impact']); ?></span>
                            </header>
                            <p><?php echo htmlspecialchars($issue['recommendation']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="a11y-detail-success">This page passed the automated checks with no remaining alerts.</p>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="a11y-dashboard" data-last-scan="<?php echo htmlspecialchars($dashboardStats['lastScan'], ENT_QUOTES); ?>">
        <header class="a11y-hero">
            <div class="a11y-hero-content">
                <div>
                    <span class="hero-eyebrow speed-hero-eyebrow">Performance Pulse</span>
                    <h2 class="a11y-hero-title">Performance &amp; Speed Dashboard</h2>
                    <p class="a11y-hero-subtitle">Track asset weight, script usage, and rendering health across every published page.</p>
                </div>
                <div class="a11y-hero-actions">
                    <button type="button" id="speedScanAllBtn" class="a11y-btn a11y-btn--primary" data-speed-action="scan-all">
                        <i class="fas fa-gauge-high" aria-hidden="true"></i>
                        <span>Run Speed Scan</span>
                    </button>
                    <?php if (!empty($heaviestPage)): ?>
                    <button type="button" class="a11y-btn a11y-btn--ghost" data-speed-action="view-heaviest" data-speed-slug="<?php echo htmlspecialchars($heaviestPage['slug'] ?? '', ENT_QUOTES); ?>" title="Estimated weight: <?php echo isset($heaviestPage['weight']) ? htmlspecialchars(number_format((float)$heaviestPage['weight'], 1), ENT_QUOTES) . ' KB' : ''; ?>">
                        <i class="fas fa-weight-hanging" aria-hidden="true"></i>
                        <span>Heaviest page: <?php echo htmlspecialchars($heaviestPage['title'] ?? ''); ?></span>
                    </button>
                    <?php endif; ?>
                    <span class="a11y-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last scan: <?php echo htmlspecialchars($dashboardStats['lastScan']); ?>
                    </span>
                    <span class="a11y-hero-meta">
                        <i class="fas fa-bolt" aria-hidden="true"></i>
                        High-performing pages: <?php echo $dashboardStats['fastPages']; ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatTotalPages"><?php echo $dashboardStats['totalPages']; ?></div>
                    <div class="a11y-overview-label">Total Pages</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['totalPages'] ?? null, 'Total pages', 'page'); ?>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatAvgScore"><?php echo $dashboardStats['avgScore']; ?>%</div>
                    <div class="a11y-overview-label">Average Score</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['avgScore'] ?? null, 'Average score', 'point', 'points'); ?>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatCritical"><?php echo $dashboardStats['criticalAlerts']; ?></div>
                    <div class="a11y-overview-label">Critical Alerts</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['criticalAlerts'] ?? null, 'Critical alerts', 'alert'); ?>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="speedStatSlow"><?php echo $dashboardStats['slowPages']; ?></div>
                    <div class="a11y-overview-label">Pages needing attention</div>
                    <?php echo speed_render_delta($dashboardStats['deltas']['slowPages'] ?? null, 'Pages needing attention', 'page'); ?>
                </div>
            </div>
        </header>
        <div class="a11y-controls">
            <label class="a11y-search" for="speedSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="speedSearchInput" placeholder="Search pages by title, URL, or alert" aria-label="Search performance results">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="Performance filters">
                <button type="button" class="a11y-filter-btn active" data-speed-filter="all">All Pages <span class="a11y-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-speed-filter="slow">Slow pages <span class="a11y-filter-count" data-count="slow"><?php echo $filterCounts['slow']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-speed-filter="monitor">Needs attention <span class="a11y-filter-count" data-count="monitor"><?php echo $filterCounts['monitor']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-speed-filter="fast">Performing well <span class="a11y-filter-count" data-count="fast"><?php echo $filterCounts['fast']; ?></span></button>
            </div>
            <div class="a11y-sort-group" role="group" aria-label="Sort results">
                <label for="speedSortSelect">Sort by</label>
                <select id="speedSortSelect">
                    <option value="score" selected>Performance score</option>
                    <option value="title">Title</option>
                    <option value="alerts">Total alerts</option>
                    <option value="weight">Estimated weight</option>
                </select>
                <button type="button" class="a11y-sort-direction" id="speedSortDirection" data-direction="desc" aria-label="Toggle sort direction (High to low)" aria-pressed="true">
                    <i class="fas fa-sort-amount-down-alt" aria-hidden="true"></i>
                    <span class="a11y-sort-direction__text" id="speedSortDirectionLabel">High to low</span>
                </button>
            </div>
            <div class="a11y-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="a11y-view-btn active" data-speed-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="a11y-view-btn" data-speed-view="table" aria-label="Table view">
                    <i class="fas fa-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <div class="a11y-action-bar">
            <div class="a11y-bulk-actions">
                <button type="button" class="a11y-btn a11y-btn--secondary" id="speedDownloadReport">
                    <i class="fas fa-download" aria-hidden="true"></i>
                    <span>Download Speed Report</span>
                </button>
            </div>
        </div>
        <div class="a11y-pages-grid" id="speedPagesGrid" role="list"></div>
        <div class="a11y-table-view" id="speedTableView" hidden>
            <div class="a11y-table-header">
                <div>Page</div>
                <div>Score</div>
                <div>Grade</div>
                <div>Alerts</div>
                <div>Est. Weight</div>
                <div>Last Scanned</div>
                <div>Action</div>
            </div>
            <div id="speedTableBody"></div>
        </div>
        <div class="empty-state" id="speedEmptyState" hidden aria-labelledby="speedEmptyTitle" aria-describedby="speedEmptyDescription">
            <div class="empty-state__icon" aria-hidden="true">
                <i class="fas fa-gauge-high"></i>
            </div>
            <div class="empty-state__content">
                <h3 class="empty-state__title" id="speedEmptyTitle">No pages match your filters</h3>
                <p class="empty-state__description" id="speedEmptyDescription">Try adjusting the search or choosing a different performance segment.</p>
            </div>
            <button type="button" class="a11y-btn a11y-btn--primary empty-state__cta" id="speedEmptyCta">
                <i class="fas fa-gauge-simple-high" aria-hidden="true"></i>
                <span>Scan all pages</span>
            </button>
        </div>
    </div>
    <div class="a11y-page-detail" id="speedPageDetail" hidden role="dialog" aria-modal="true" aria-labelledby="speedDetailTitle">
        <div class="a11y-detail-content">
            <button type="button" class="a11y-detail-close" id="speedDetailClose" aria-label="Close performance details">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <header class="a11y-detail-modal-header">
                <h2 id="speedDetailTitle">Page performance details</h2>
                <p id="speedDetailUrl" class="a11y-detail-url"></p>
                <p id="speedDetailDescription" class="a11y-detail-description"></p>
            </header>
            <div class="a11y-detail-modal-body">
                <div class="a11y-detail-badges">
                    <span class="a11y-detail-score score-indicator score-indicator--badge" id="speedDetailScore"></span>
                    <span class="a11y-detail-level" id="speedDetailGrade"></span>
                    <span class="a11y-detail-violations" id="speedDetailAlerts"></span>
                </div>
                <ul class="a11y-detail-metric-list" id="speedDetailMetrics"></ul>
                <div class="a11y-detail-issues-list">
                    <h3>Key findings</h3>
                    <ul id="speedDetailIssues"></ul>
                </div>
            </div>
            <footer class="a11y-detail-modal-footer">
                <button type="button" class="a11y-btn a11y-btn--primary" data-speed-action="full-diagnose">
                    <i class="fas fa-gauge-simple-high" aria-hidden="true"></i>
                    <span>Launch detailed audit</span>
                </button>
            </footer>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
window.speedDashboardData = <?php echo json_encode($report, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.speedDashboardStats = <?php echo json_encode($dashboardStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
