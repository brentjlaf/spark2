<?php
// File: modules/accessibility/view.php
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

function describe_wcag_level(string $level): string {
    switch ($level) {
        case 'AAA':
            return 'This page exceeds WCAG AA standards with no detected blocking issues.';
        case 'AA':
            return 'This page meets WCAG AA accessibility requirements but still has areas to refine.';
        case 'Partial':
            return 'This page has partial WCAG compliance and should be prioritized for remediation.';
        default:
            return 'This page has critical accessibility blockers that need immediate attention.';
    }
}

function summarize_violations(array $violations): string {
    $parts = [];
    if (!empty($violations['critical'])) {
        $parts[] = $violations['critical'] . ' critical';
    }
    if (!empty($violations['serious'])) {
        $parts[] = $violations['serious'] . ' serious';
    }
    if (!empty($violations['moderate'])) {
        $parts[] = $violations['moderate'] . ' moderate';
    }
    if (!empty($violations['minor'])) {
        $parts[] = $violations['minor'] . ' minor';
    }

    if (empty($parts)) {
        return 'No outstanding violations detected';
    }

    return implode(', ', $parts) . ' issue' . ($violations['total'] === 1 ? '' : 's');
}

function classify_issue_detail(string $issue): array {
    $lower = strtolower($issue);

    if (strpos($lower, 'alt') !== false) {
        return [
            'impact' => 'critical',
            'recommendation' => 'Provide descriptive alternative text for all meaningful images to support screen reader users.'
        ];
    }

    if (strpos($lower, 'h1') !== false) {
        return [
            'impact' => 'serious',
            'recommendation' => 'Ensure each page uses a single, descriptive H1 heading to clarify document structure.'
        ];
    }

    if (strpos($lower, 'link') !== false) {
        return [
            'impact' => 'moderate',
            'recommendation' => 'Replace generic link labels with meaningful descriptions of the target destination.'
        ];
    }

    if (strpos($lower, 'landmark') !== false) {
        return [
            'impact' => 'minor',
            'recommendation' => 'Add structural landmarks such as <main>, <nav>, <header>, or <footer> for assistive navigation.'
        ];
    }

    return [
        'impact' => 'review',
        'recommendation' => 'Review this issue to ensure it aligns with WCAG 2.1 AA expectations.'
    ];
}

libxml_use_internal_errors(true);

$report = [];

$genericLinkTerms = [
    'click here',
    'read more',
    'learn more',
    'here',
    'more',
    'this page',
];

foreach ($pages as $page) {
    $title = $page['title'] ?? 'Untitled';
    $slug = $page['slug'] ?? '';
    $pageHtml = build_page_html($page, $settings, $menus, $scriptBase, $templateDir);

    $doc = new DOMDocument();
    $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

    $imageCount = 0;
    $missingAlt = 0;
    $headings = [
        'h1' => 0,
        'h2' => 0,
    ];
    $genericLinks = 0;
    $landmarks = 0;

    if ($loaded) {
        $images = $doc->getElementsByTagName('img');
        $imageCount = $images->length;
        foreach ($images as $img) {
            $alt = trim($img->getAttribute('alt'));
            if ($alt === '') {
                $missingAlt++;
            }
        }

        $h1s = $doc->getElementsByTagName('h1');
        $headings['h1'] = $h1s->length;
        $h2s = $doc->getElementsByTagName('h2');
        $headings['h2'] = $h2s->length;

        $anchors = $doc->getElementsByTagName('a');
        foreach ($anchors as $anchor) {
            $text = strtolower(trim($anchor->textContent));
            if ($text !== '') {
                foreach ($genericLinkTerms as $term) {
                    if ($text === $term) {
                        $genericLinks++;
                        break;
                    }
                }
            }
        }

        $landmarkTags = ['main', 'nav', 'header', 'footer'];
        foreach ($landmarkTags as $tag) {
            $landmarks += $doc->getElementsByTagName($tag)->length;
        }
    }

    $issues = [];

    if ($missingAlt > 0) {
        $issues[] = sprintf('%d image%s missing alt text', $missingAlt, $missingAlt === 1 ? ' is' : 's are');
    }

    if ($headings['h1'] === 0) {
        $issues[] = 'No H1 heading found';
    } elseif ($headings['h1'] > 1) {
        $issues[] = 'Multiple H1 headings detected';
    }

    if ($genericLinks > 0) {
        $issues[] = sprintf('%d link%s use generic text', $genericLinks, $genericLinks === 1 ? '' : 's');
    }

    if ($landmarks === 0) {
        $issues[] = 'Add landmark elements (main, nav, header, footer)';
    }

    $report[] = [
        'title' => $title,
        'slug' => $slug,
        'template' => $page['template'] ?? '',
        'image_count' => $imageCount,
        'missing_alt' => $missingAlt,
        'headings' => $headings,
        'generic_links' => $genericLinks,
        'landmarks' => $landmarks,
        'issues' => $issues,
    ];
}

$totalPages = count($report);
$lastScan = date('M j, Y g:i A');

$pageEntries = [];
$pageEntryMap = [];
$filterCounts = [
    'all' => $totalPages,
    'failing' => 0,
    'partial' => 0,
    'compliant' => 0,
];
$criticalIssues = 0;
$aaCompliant = 0;
$scoreSum = 0;

foreach ($report as $entry) {
    $slug = $entry['slug'];
    $path = '/' . ltrim($slug, '/');

    $critical = (int)$entry['missing_alt'];
    $serious = ($entry['headings']['h1'] === 0 || $entry['headings']['h1'] > 1) ? 1 : 0;
    $moderate = $entry['generic_links'] > 0 ? 1 : 0;
    $minor = $entry['landmarks'] === 0 ? 1 : 0;
    $violationsTotal = $critical + $serious + $moderate + $minor;

    $warnings = ($entry['generic_links'] > 0 ? $entry['generic_links'] : 0) + ($entry['landmarks'] === 0 ? 1 : 0);

    $score = 100;
    $score -= $critical * 15;
    $score -= $serious * 12;
    $score -= $moderate * 8;
    $score -= $minor * 5;
    if ($violationsTotal === 0) {
        $score = 98;
    }
    $score = max(0, min(100, $score));

    if ($violationsTotal === 0) {
        $wcagLevel = 'AAA';
    } elseif ($critical === 0 && $serious <= 1 && $score >= 80) {
        $wcagLevel = 'AA';
    } elseif ($score >= 60) {
        $wcagLevel = 'Partial';
    } else {
        $wcagLevel = 'Failing';
    }

    if (in_array($wcagLevel, ['AA', 'AAA'], true)) {
        $aaCompliant++;
    }

    if ($wcagLevel === 'Partial') {
        $filterCounts['partial']++;
    }

    if ($wcagLevel === 'Failing' || $critical > 0 || $score < 60) {
        $filterCounts['failing']++;
    }

    if (in_array($wcagLevel, ['AA', 'AAA'], true)) {
        $filterCounts['compliant']++;
    }

    $criticalIssues += $critical;
    $scoreSum += $score;

    $issueDetails = [];
    foreach ($entry['issues'] as $issueText) {
        $detail = classify_issue_detail($issueText);
        $issueDetails[] = [
            'description' => $issueText,
            'impact' => $detail['impact'],
            'recommendation' => $detail['recommendation'],
        ];
    }

    $issuePreview = array_slice(array_map(static function ($detail) {
        return $detail['description'];
    }, $issueDetails), 0, 4);

    if (empty($issuePreview)) {
        $issuePreview = ['No outstanding issues'];
    }

    $violations = [
        'critical' => $critical,
        'serious' => $serious,
        'moderate' => $moderate,
        'minor' => $minor,
        'total' => $violationsTotal,
    ];

    $previousScore = derive_previous_score('accessibility', $slug !== '' ? $slug : ($entry['title'] !== '' ? $entry['title'] : (string) count($pageEntries)), $score);

    $pageData = [
        'title' => $entry['title'],
        'slug' => $slug,
        'url' => $path,
        'path' => $path,
        'template' => $entry['template'],
        'accessibilityScore' => $score,
        'previousScore' => $previousScore,
        'wcagLevel' => $wcagLevel,
        'violations' => $violations,
        'warnings' => $warnings,
        'lastScanned' => $lastScan,
        'pageType' => !empty($entry['template']) ? 'Template: ' . basename($entry['template']) : 'Standard Page',
        'compliance' => $wcagLevel === 'Failing' ? 'Failing' : ($wcagLevel === 'Partial' ? 'Needs Work' : 'Compliant'),
        'issues' => [
            'preview' => $issuePreview,
            'details' => $issueDetails,
        ],
        'metrics' => [
            'images' => $entry['image_count'],
            'missingAlt' => $entry['missing_alt'],
            'headings' => $entry['headings'],
            'genericLinks' => $entry['generic_links'],
            'landmarks' => $entry['landmarks'],
        ],
    ];

    $pageData['statusMessage'] = describe_wcag_level($wcagLevel);
    $pageData['summaryLine'] = sprintf(
        'Current accessibility score: %d%%. %s.',
        $score,
        summarize_violations($violations)
    );

    $pageEntries[] = $pageData;
    $pageEntryMap[$slug] = $pageData;
}

$avgScore = $totalPages > 0 ? round($scoreSum / $totalPages) : 0;

$moduleUrl = $_SERVER['PHP_SELF'] . '?module=accessibility';
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
    'criticalIssues' => $criticalIssues,
    'aaCompliant' => $aaCompliant,
    'filterCounts' => $filterCounts,
    'moduleUrl' => $moduleUrl,
    'detailBaseUrl' => $moduleUrl . '&page=',
    'lastScan' => $lastScan,
];
?>
<div class="content-section" id="accessibility">
<?php if ($selectedPage): ?>
    <div class="a11y-detail-page" id="a11yDetailPage" data-page-slug="<?php echo htmlspecialchars($selectedPage['slug'], ENT_QUOTES); ?>">
        <?php
            $currentScore = (int) ($selectedPage['accessibilityScore'] ?? 0);
            $previousScore = (int) ($selectedPage['previousScore'] ?? $currentScore);
            $deltaMeta = describe_score_delta($currentScore, $previousScore);
        ?>
        <header class="a11y-detail-header">
            <a href="<?php echo htmlspecialchars($moduleUrl, ENT_QUOTES); ?>" class="a11y-back-link" id="a11yBackToDashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Back to Dashboard</span>
            </a>
            <div class="a11y-detail-actions">
                <button type="button" class="a11y-btn a11y-btn--ghost" data-a11y-action="rescan-page">
                    <i class="fas fa-rotate" aria-hidden="true"></i>
                    <span>Rescan Page</span>
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
                <div class="a11y-health-score__label">Accessibility Score</div>
                <span class="a11y-health-score__badge level-<?php echo strtolower($selectedPage['wcagLevel']); ?>"><?php echo htmlspecialchars($selectedPage['wcagLevel']); ?></span>
            </div>
            <div class="a11y-health-summary">
                <h1><?php echo htmlspecialchars($selectedPage['title']); ?></h1>
                <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['url']); ?></p>
                <p><?php echo htmlspecialchars($selectedPage['statusMessage']); ?></p>
                <p class="a11y-health-overview"><?php echo htmlspecialchars($selectedPage['summaryLine']); ?></p>
                <div class="a11y-quick-stats">
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['images']; ?></div>
                        <div class="a11y-quick-stat__label">Images</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['missingAlt']; ?></div>
                        <div class="a11y-quick-stat__label">Missing Alt</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value">H1: <?php echo $selectedPage['metrics']['headings']['h1']; ?></div>
                        <div class="a11y-quick-stat__label">Primary Headings</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['violations']['critical']; ?></div>
                        <div class="a11y-quick-stat__label">Critical Issues</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="a11y-detail-grid">
            <article class="a11y-detail-card">
                <h2>Key accessibility checks</h2>
                <div class="a11y-detail-metrics">
                    <div>
                        <span class="a11y-detail-metric__label">Images analysed</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['images']; ?></span>
                        <span class="a11y-detail-metric__hint"><?php echo $selectedPage['metrics']['missingAlt'] > 0 ? $selectedPage['metrics']['missingAlt'] . ' image(s) missing alt text' : 'All images include alt text'; ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Headings</span>
                        <span class="a11y-detail-metric__value">H1: <?php echo $selectedPage['metrics']['headings']['h1']; ?> / H2: <?php echo $selectedPage['metrics']['headings']['h2']; ?></span>
                        <span class="a11y-detail-metric__hint">Ensure a single descriptive H1 per page.</span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Generic links</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['genericLinks']; ?></span>
                        <span class="a11y-detail-metric__hint"><?php echo $selectedPage['metrics']['genericLinks'] > 0 ? 'Replace generic link text with descriptive labels.' : 'All links descriptive'; ?></span>
                    </div>
                    <div>
                        <span class="a11y-detail-metric__label">Landmarks</span>
                        <span class="a11y-detail-metric__value"><?php echo $selectedPage['metrics']['landmarks']; ?></span>
                        <span class="a11y-detail-metric__hint"><?php echo $selectedPage['metrics']['landmarks'] > 0 ? 'Structural landmarks detected' : 'Add semantic landmarks for easier navigation.'; ?></span>
                    </div>
                </div>
            </article>
            <article class="a11y-detail-card">
                <h2>WCAG violation breakdown</h2>
                <ul class="a11y-violation-list">
                    <li><span>Critical</span><span><?php echo $selectedPage['violations']['critical']; ?></span></li>
                    <li><span>Serious</span><span><?php echo $selectedPage['violations']['serious']; ?></span></li>
                    <li><span>Moderate</span><span><?php echo $selectedPage['violations']['moderate']; ?></span></li>
                    <li><span>Minor</span><span><?php echo $selectedPage['violations']['minor']; ?></span></li>
                </ul>
                <div class="a11y-detail-meta">
                    <div>
                        <span class="a11y-detail-meta__label">Last scanned</span>
                        <span class="a11y-detail-meta__value"><?php echo htmlspecialchars($selectedPage['lastScanned']); ?></span>
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

        <?php
            $issueDetails = isset($selectedPage['issues']['details']) && is_array($selectedPage['issues']['details'])
                ? $selectedPage['issues']['details']
                : [];
            $issueDetailCount = count($issueDetails);
        ?>
        <section class="a11y-detail-issues">
            <div class="a11y-detail-issues__header">
                <h2>Accessibility issues</h2>
                <span id="a11yIssueCount">
                    <?php echo $issueDetailCount; ?> <?php echo $issueDetailCount === 1 ? 'issue' : 'issues'; ?>
                </span>
            </div>
            <?php if ($issueDetailCount > 0): ?>
                <?php
                    $impactCounts = [
                        'critical' => 0,
                        'serious' => 0,
                        'moderate' => 0,
                        'minor' => 0,
                        'review' => 0,
                    ];
                    foreach ($issueDetails as $detail) {
                        $impactKey = strtolower((string)($detail['impact'] ?? ''));
                        if (!array_key_exists($impactKey, $impactCounts)) {
                            $impactCounts[$impactKey] = 0;
                        }
                        $impactCounts[$impactKey]++;
                    }
                ?>
                <div class="a11y-severity-filters" role="group" aria-label="Filter issues by severity">
                    <button type="button" class="a11y-severity-btn active" data-a11y-severity="all" aria-pressed="true" aria-label="Show all issues (<?php echo $issueDetailCount; ?>)">
                        All <span aria-hidden="true">(<?php echo $issueDetailCount; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="critical" aria-pressed="false" aria-label="Show critical issues (<?php echo $impactCounts['critical']; ?>)">
                        Critical <span aria-hidden="true">(<?php echo $impactCounts['critical']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="serious" aria-pressed="false" aria-label="Show serious issues (<?php echo $impactCounts['serious']; ?>)">
                        Serious <span aria-hidden="true">(<?php echo $impactCounts['serious']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="moderate" aria-pressed="false" aria-label="Show moderate issues (<?php echo $impactCounts['moderate']; ?>)">
                        Moderate <span aria-hidden="true">(<?php echo $impactCounts['moderate']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="minor" aria-pressed="false" aria-label="Show minor issues (<?php echo $impactCounts['minor']; ?>)">
                        Minor <span aria-hidden="true">(<?php echo $impactCounts['minor']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-a11y-severity="review" aria-pressed="false" aria-label="Show review issues (<?php echo $impactCounts['review']; ?>)">
                        Review <span aria-hidden="true">(<?php echo $impactCounts['review']; ?>)</span>
                    </button>
                </div>
                <div class="sr-only" id="a11yIssueFilterStatus" role="status" aria-live="polite"></div>
                <div class="a11y-issue-list">
                    <?php foreach ($issueDetails as $issue): ?>
                        <article class="a11y-issue-card impact-<?php echo htmlspecialchars($issue['impact']); ?>" data-impact="<?php echo htmlspecialchars(strtolower($issue['impact'])); ?>">
                            <header>
                                <h3><?php echo htmlspecialchars($issue['description']); ?></h3>
                                <span class="a11y-impact-badge impact-<?php echo htmlspecialchars($issue['impact']); ?>"><?php echo ucfirst($issue['impact']); ?></span>
                            </header>
                            <p><?php echo htmlspecialchars($issue['recommendation']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="a11y-detail-empty" id="a11yNoIssuesMessage" hidden>No issues match this severity filter.</p>
            <?php else: ?>
                <p class="a11y-detail-success">This page passed the automated checks with no remaining issues.</p>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="a11y-dashboard" data-last-scan="<?php echo htmlspecialchars($lastScan, ENT_QUOTES); ?>">
        <header class="a11y-hero accessibility-hero">
            <div class="a11y-hero-content accessibility-hero-content">
                <div>
                    <span class="hero-eyebrow accessibility-hero-eyebrow">Accessibility Snapshot</span>
                    <h2 class="a11y-hero-title accessibility-hero-title">Accessibility Dashboard</h2>
                    <p class="a11y-hero-subtitle accessibility-hero-subtitle">Monitor WCAG compliance and keep your experience inclusive for every visitor.</p>
                </div>
                <div class="a11y-hero-actions accessibility-hero-actions">
                    <button type="button" id="scanAllPagesBtn" class="a11y-btn a11y-btn--primary" data-a11y-action="scan-all">
                        <i class="fas fa-universal-access" aria-hidden="true"></i>
                        <span>Scan All Pages</span>
                    </button>
                    <span class="a11y-hero-meta accessibility-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last scan: <?php echo htmlspecialchars($lastScan); ?>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatTotalPages"><?php echo $totalPages; ?></div>
                    <div class="a11y-overview-label">Total Pages</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatAvgScore"><?php echo $avgScore; ?>%</div>
                    <div class="a11y-overview-label">Average Score</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatCritical"><?php echo $criticalIssues; ?></div>
                    <div class="a11y-overview-label">Critical Issues</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="a11yStatCompliant"><?php echo $aaCompliant; ?></div>
                    <div class="a11y-overview-label">AA/AAA Compliant</div>
                </div>
            </div>
        </header>

        <div class="a11y-controls">
            <label class="a11y-search" for="a11ySearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="a11ySearchInput" placeholder="Search pages by title, URL, or issue" aria-label="Search accessibility results">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="Accessibility filters">
                <button type="button" class="a11y-filter-btn active" data-a11y-filter="all">All Pages <span class="a11y-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-a11y-filter="failing">Critical Issues <span class="a11y-filter-count" data-count="failing"><?php echo $filterCounts['failing']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-a11y-filter="partial">Needs Work <span class="a11y-filter-count" data-count="partial"><?php echo $filterCounts['partial']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-a11y-filter="compliant">WCAG Compliant <span class="a11y-filter-count" data-count="compliant"><?php echo $filterCounts['compliant']; ?></span></button>
            </div>
            <div class="a11y-sort-group" role="group" aria-label="Sort pages">
                <label for="a11ySortSelect">Sort by</label>
                <select id="a11ySortSelect">
                    <option value="score" selected>Accessibility score</option>
                    <option value="title">Title</option>
                    <option value="violations">Total violations</option>
                    <option value="warnings">Warnings</option>
                </select>
                <button type="button" class="a11y-sort-direction" id="a11ySortDirection" data-direction="desc" aria-label="Toggle sort direction (High to low)" aria-pressed="true">
                    <i class="fas fa-sort-amount-down-alt" aria-hidden="true"></i>
                    <span class="a11y-sort-direction__text" id="a11ySortDirectionLabel">High to low</span>
                </button>
            </div>
            <div class="a11y-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="a11y-view-btn active" data-a11y-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="a11y-view-btn" data-a11y-view="table" aria-label="Table view">
                    <i class="fas fa-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="a11y-pages-grid" id="a11yPagesGrid" role="list"></div>

        <div class="a11y-table-view" id="a11yTableView" hidden>
            <div class="a11y-table-header">
                <div>Page</div>
                <div>Score</div>
                <div>WCAG Level</div>
                <div>Violations</div>
                <div>Warnings</div>
                <div>Last Scanned</div>
                <div>Action</div>
            </div>
            <div id="a11yTableBody"></div>
        </div>

        <div class="empty-state" id="a11yEmptyState" hidden aria-labelledby="a11yEmptyTitle" aria-describedby="a11yEmptyDescription">
            <div class="empty-state__icon" aria-hidden="true">
                <i class="fas fa-universal-access"></i>
            </div>
            <div class="empty-state__content">
                <h3 class="empty-state__title" id="a11yEmptyTitle">No pages match your filters</h3>
                <p class="empty-state__description" id="a11yEmptyDescription">Try adjusting the search or changing the filter selection.</p>
            </div>
            <button type="button" class="a11y-btn a11y-btn--primary empty-state__cta" id="a11yEmptyCta">
                <i class="fas fa-universal-access" aria-hidden="true"></i>
                <span>Scan all pages</span>
            </button>
        </div>
    </div>

    <div class="a11y-page-detail" id="a11yPageDetail" hidden role="dialog" aria-modal="true" aria-labelledby="a11yDetailTitle">
        <div class="a11y-detail-content">
            <button type="button" class="a11y-detail-close" id="a11yDetailClose" aria-label="Close accessibility details">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <header class="a11y-detail-modal-header">
                <h2 id="a11yDetailTitle">Page Accessibility Details</h2>
                <p id="a11yDetailUrl" class="a11y-detail-url"></p>
                <p id="a11yDetailDescription" class="a11y-detail-description"></p>
            </header>
            <div class="a11y-detail-modal-body">
                <div class="a11y-detail-badges">
                    <span class="a11y-detail-score score-indicator score-indicator--badge" id="a11yDetailScore"></span>
                    <span class="a11y-detail-level" id="a11yDetailLevel"></span>
                    <span class="a11y-detail-violations" id="a11yDetailViolations"></span>
                </div>
                <ul class="a11y-detail-metric-list" id="a11yDetailMetrics"></ul>
                <div class="a11y-detail-issues-list">
                    <h3>Key findings</h3>
                    <ul id="a11yDetailIssues"></ul>
                </div>
            </div>
            <footer class="a11y-detail-modal-footer">
                <button type="button" class="a11y-btn a11y-btn--primary" data-a11y-action="full-audit">
                    <i class="fas fa-universal-access" aria-hidden="true"></i>
                    <span>Full Accessibility Audit</span>
                </button>
            </footer>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
window.a11yDashboardData = <?php echo json_encode($pageEntries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
window.a11yDashboardStats = <?php echo json_encode($dashboardStats, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
