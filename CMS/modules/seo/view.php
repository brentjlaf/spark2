<?php
// File: modules/seo/view.php
//
// This module powers the SEO dashboard and detail view inside the CMS. It
// analyses page content generated from templates and surfaces actionable SEO
// metrics that are consumed by the accompanying JavaScript UI.
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/score_history.php';
require_login(); // Only authenticated administrators may access the dashboard.

// Load the site structure and settings needed to render pages for analysis.
$pagesFile = __DIR__ . '/../../data/pages.json';
$pages = read_json_file($pagesFile);
$settings = get_site_settings();
$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);

// Normalise the base path for building asset/template URLs.
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');

/**
 * Wrapper for strlen that prefers multibyte support for accurate character
 * counts in templates and metadata.
 */
function seo_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function describe_robots_directive(string $value): string
{
    $normalized = sanitize_robots_directive($value);
    $map = [
        'index,follow' => 'Index & follow',
        'index,nofollow' => 'Index, nofollow',
        'noindex,follow' => 'Noindex, follow',
        'noindex,nofollow' => 'Noindex & nofollow',
    ];

    return $map[$normalized] ?? ucfirst(str_replace(',', ', ', $normalized));
}

/**
 * Render a page template while stripping editor-specific markup so we can
 * analyse the final HTML the visitor would receive.
 */
function capture_template_html(string $templateFile, array $settings, array $menus, string $scriptBase): string
{
    $page = ['content' => '{{CONTENT}}'];
    $themeBase = $scriptBase . '/theme';
    ob_start();
    include $templateFile;
    $html = ob_get_clean();
    $html = preg_replace('/<div class="drop-area"><\\/div>/', '{{CONTENT}}', $html, 1);
    if (strpos($html, '{{CONTENT}}') === false) {
        $html .= '{{CONTENT}}';
    }
    $html = preg_replace('#<templateSetting[^>]*>.*?<\\/templateSetting>#si', '', $html);
    $html = preg_replace('#<div class="block-controls"[^>]*>.*?<\\/div>#si', '', $html);
    $html = str_replace('draggable="true"', '', $html);
    $html = preg_replace('#\\sdata-ts="[^"]*"#i', '', $html);
    $html = preg_replace('#\\sdata-(?:blockid|template|original|active|custom_[A-Za-z0-9_-]+)="[^"]*"#i', '', $html);
    return $html;
}

/**
 * Resolve a page's template and merge its content into the template shell so
 * the DOM parser can operate on a complete page.
 */
function build_page_html(array $page, array $settings, array $menus, string $scriptBase, ?string $templateDir): string
{
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

/**
 * Provide a friendly summary sentence based on the calculated score and
 * presence of critical issues.
 */
function describe_seo_health(int $score, int $criticalIssues): string
{
    if ($score >= 90 && $criticalIssues === 0) {
        return 'This page is fully optimised for search with only minor enhancement opportunities.';
    }
    if ($score >= 75) {
        return 'This page performs well for SEO, but targeted improvements could boost visibility further.';
    }
    if ($score >= 55) {
        return 'This page has noticeable SEO gaps that should be addressed to stay competitive.';
    }
    return 'This page has critical SEO blockers that may prevent it from ranking effectively.';
}

/**
 * Create a human readable summary of the violation counts for quick display.
 */
function summarize_seo_violations(array $violations): string
{
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
        return 'No outstanding SEO issues detected';
    }

    return implode(', ', $parts) . ' issue' . ($violations['total'] === 1 ? '' : 's');
}

/**
 * Map plain-text issue descriptions to severity and tailored recommendations.
 */
function classify_seo_issue(string $issue): array
{
    $lower = strtolower($issue);

    if (strpos($lower, 'title') !== false) {
        return [
            'impact' => strpos($lower, 'missing') !== false ? 'critical' : 'serious',
            'recommendation' => 'Craft a descriptive title tag between 30-65 characters that reflects the page intent and primary keyword.'
        ];
    }

    if (strpos($lower, 'meta description') !== false) {
        return [
            'impact' => strpos($lower, 'missing') !== false ? 'serious' : 'moderate',
            'recommendation' => 'Add a unique meta description of 70-160 characters highlighting the core value proposition.'
        ];
    }

    if (strpos($lower, 'h1') !== false) {
        return [
            'impact' => 'serious',
            'recommendation' => 'Use a single H1 heading that summarises the page topic and includes the target keyword.'
        ];
    }

    if (strpos($lower, 'word count') !== false) {
        return [
            'impact' => 'moderate',
            'recommendation' => 'Expand the on-page content to at least 300 words to provide sufficient context for search engines.'
        ];
    }

    if (strpos($lower, 'canonical') !== false) {
        return [
            'impact' => 'moderate',
            'recommendation' => 'Add a canonical URL to signal the preferred version of this content and avoid duplicate issues.'
        ];
    }

    if (strpos($lower, 'open graph') !== false || strpos($lower, 'social preview') !== false) {
        return [
            'impact' => 'minor',
            'recommendation' => 'Include Open Graph tags (og:title, og:description, og:image) to improve social sharing and click-through rates.'
        ];
    }

    if (strpos($lower, 'structured data') !== false || strpos($lower, 'schema') !== false) {
        return [
            'impact' => 'minor',
            'recommendation' => 'Add structured data markup (e.g., JSON-LD) to qualify for rich results and enhanced listings.'
        ];
    }

    if (strpos($lower, 'alt text') !== false) {
        return [
            'impact' => 'moderate',
            'recommendation' => 'Provide descriptive alternative text for images to support accessibility and image search visibility.'
        ];
    }

    if (strpos($lower, 'internal link') !== false) {
        return [
            'impact' => 'moderate',
            'recommendation' => 'Add internal links to relevant pages to improve crawlability and distribute authority.'
        ];
    }

    if (strpos($lower, 'noindex') !== false) {
        return [
            'impact' => 'critical',
            'recommendation' => 'Remove the noindex directive unless this page should be hidden from search engines.'
        ];
    }

    return [
        'impact' => 'minor',
        'recommendation' => 'Review this recommendation to ensure the page follows on-page SEO best practices.'
    ];
}

/**
 * Strip markup and count words from rendered HTML to gauge content depth.
 */
function extract_word_count(string $html): int
{
    $clean = preg_replace('#<(script|style|noscript|template)[^>]*>.*?<\\/\\1>#si', ' ', $html);
    $text = strip_tags($clean);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    if ($text === '') {
        return 0;
    }
    return str_word_count($text);
}

/**
 * Count the number of internal versus external anchor links on a page.
 */
function count_links(DOMDocument $doc): array
{
    $internal = 0;
    $external = 0;

    $anchors = $doc->getElementsByTagName('a');
    foreach ($anchors as $anchor) {
        $href = trim($anchor->getAttribute('href'));
        if ($href === '' || strpos($href, '#') === 0 || stripos($href, 'javascript:') === 0) {
            continue;
        }
        if (preg_match('#^https?://#i', $href)) {
            $external++;
        } else {
            $internal++;
        }
    }

    return ['internal' => $internal, 'external' => $external];
}

// Suppress DOM warnings triggered by imperfect HTML from page content.
libxml_use_internal_errors(true);

// Accumulate per-page reports and capture the timestamp for display.
$report = [];
$lastScan = date('M j, Y g:i A');

// Analyse each page the CMS knows about and compute SEO metrics.
foreach ($pages as $page) {
    $title = $page['title'] ?? 'Untitled';
    $slug = $page['slug'] ?? '';
    $pageHtml = build_page_html($page, $settings, $menus, $scriptBase, $templateDir);
    $storedRobotsDirective = sanitize_robots_directive($page['robots'] ?? sparkcms_default_robots_directive());

    $doc = new DOMDocument();
    $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

    // Initialise the metrics structure used by both the dashboard and detail views.
    $metrics = [
        'title' => '',
        'titleLength' => 0,
        'metaDescription' => '',
        'metaDescriptionLength' => 0,
        'h1Count' => 0,
        'wordCount' => 0,
        'images' => 0,
        'missingAlt' => 0,
        'links' => ['internal' => 0, 'external' => 0],
        'hasCanonical' => false,
        'hasStructuredData' => false,
        'hasOpenGraph' => false,
        'isNoindex' => strpos($storedRobotsDirective, 'noindex') !== false,
        'robotsDirective' => $storedRobotsDirective,
    ];

    if ($loaded) {
        // Extract metadata and structural signals when the HTML parses correctly.
        $titles = $doc->getElementsByTagName('title');
        if ($titles->length > 0) {
            $metrics['title'] = trim($titles->item(0)->textContent);
            $metrics['titleLength'] = seo_strlen($metrics['title']);
        }

        $metaTags = $doc->getElementsByTagName('meta');
        foreach ($metaTags as $meta) {
            $name = strtolower(trim($meta->getAttribute('name')));
            $property = strtolower(trim($meta->getAttribute('property')));
            if ($name === 'description') {
                $metrics['metaDescription'] = trim($meta->getAttribute('content'));
                $metrics['metaDescriptionLength'] = seo_strlen($metrics['metaDescription']);
            }
            if ($name === 'robots') {
                $normalizedRobots = sanitize_robots_directive($meta->getAttribute('content'));
                $metrics['robotsDirective'] = $normalizedRobots;
                $metrics['isNoindex'] = strpos($normalizedRobots, 'noindex') !== false;
            }
            if (strpos($property, 'og:') === 0) {
                $metrics['hasOpenGraph'] = true;
            }
        }

        $linkTags = $doc->getElementsByTagName('link');
        foreach ($linkTags as $link) {
            $rel = strtolower(trim($link->getAttribute('rel')));
            if ($rel === 'canonical' && trim($link->getAttribute('href')) !== '') {
                $metrics['hasCanonical'] = true;
            }
        }

        $scripts = $doc->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $type = strtolower(trim($script->getAttribute('type')));
            if ($type === 'application/ld+json' && trim($script->textContent) !== '') {
                $metrics['hasStructuredData'] = true;
                break;
            }
        }

        $h1s = $doc->getElementsByTagName('h1');
        $metrics['h1Count'] = $h1s->length;

        $images = $doc->getElementsByTagName('img');
        $metrics['images'] = $images->length;
        foreach ($images as $img) {
            $alt = trim($img->getAttribute('alt'));
            if ($alt === '') {
                $metrics['missingAlt']++;
            }
        }

        $metrics['links'] = count_links($doc);
        $metrics['wordCount'] = extract_word_count($pageHtml);

        if (!$metrics['hasOpenGraph']) {
            // Some themes use property attributes instead of name, so double check.
            foreach ($metaTags as $meta) {
                if (strtolower(trim($meta->getAttribute('property'))) === 'og:title') {
                    $metrics['hasOpenGraph'] = true;
                    break;
                }
            }
        }
    } else {
        // Even if the DOM fails to load, fall back to a word count estimate.
        $metrics['wordCount'] = extract_word_count($pageHtml);
    }

    // Ensure every page exposes an appropriately sized meta description.
    if ($metrics['metaDescriptionLength'] === 0) {
        // Reuse stored metadata fields if the rendered HTML lacks them.
        $fallbackDescription = trim((string)($page['meta_description'] ?? ''));
        if ($fallbackDescription !== '') {
            $metrics['metaDescription'] = $fallbackDescription;
            $metrics['metaDescriptionLength'] = seo_strlen($fallbackDescription);
        }
    }

    if (!$metrics['hasCanonical']) {
        // Treat canonical URLs defined in the CMS as equivalent to link tags.
        $fallbackCanonical = trim((string)($page['canonical_url'] ?? ''));
        if ($fallbackCanonical !== '') {
            $metrics['hasCanonical'] = true;
        }
    }

    $issues = [];
    // Track counts by severity so we can build the score and summaries.
    $violations = [
        'critical' => 0,
        'serious' => 0,
        'moderate' => 0,
        'minor' => 0,
        'total' => 0,
    ];

    $addIssue = static function (string $description, string $impact) use (&$issues, &$violations) {
        $violations[$impact]++;
        $violations['total']++;
        $issues[] = $description;
    };

    if ($metrics['titleLength'] === 0) {
        // Pull from stored meta fields when the live HTML is missing a title.
        $fallbackTitle = trim((string)($page['meta_title'] ?? ''));
        if ($fallbackTitle === '') {
            $fallbackTitle = trim((string)($page['title'] ?? ''));
        }
        if ($fallbackTitle !== '') {
            $metrics['title'] = $fallbackTitle;
            $metrics['titleLength'] = seo_strlen($fallbackTitle);
        }
    }

    if ($metrics['titleLength'] === 0) {
        $addIssue('Page title is missing', 'critical');
    } else {
        if ($metrics['titleLength'] < 30 || $metrics['titleLength'] > 65) {
            $addIssue('Page title length is outside the recommended 30-65 characters', 'serious');
        }
    }

    if ($metrics['metaDescriptionLength'] === 0) {
        $addIssue('Meta description is missing', 'serious');
    } elseif ($metrics['metaDescriptionLength'] < 70 || $metrics['metaDescriptionLength'] > 160) {
        $addIssue('Meta description length should be between 70-160 characters', 'moderate');
    }

    // Validate heading structure because it guides crawlers and assistive tech.
    if ($metrics['h1Count'] === 0) {
        $addIssue('No H1 heading found on the page', 'serious');
    } elseif ($metrics['h1Count'] > 1) {
        $addIssue('Multiple H1 headings detected', 'moderate');
    }

    // Reward deeper content and flag thin pages that need attention.
    if ($metrics['wordCount'] < 150) {
        $addIssue('Word count is below 150 words', 'serious');
    } elseif ($metrics['wordCount'] < 300) {
        $addIssue('Word count is below 300 words', 'moderate');
    }

    if ($metrics['links']['internal'] < 3) {
        $addIssue('Add more internal links to related content', 'moderate');
    }

    if ($metrics['missingAlt'] > 0) {
        $addIssue(sprintf('%d image%s missing alt text', $metrics['missingAlt'], $metrics['missingAlt'] === 1 ? ' is' : 's are'), 'moderate');
    }

    if (!$metrics['hasCanonical']) {
        $addIssue('Canonical URL tag is missing', 'moderate');
    }

    if (!$metrics['hasOpenGraph']) {
        $addIssue('Open Graph tags missing for social sharing', 'minor');
    }

    if (!$metrics['hasStructuredData']) {
        $addIssue('Structured data markup not detected', 'minor');
    }

    if ($metrics['isNoindex']) {
        $addIssue('Robots meta tag blocks indexing (noindex)', 'critical');
    }

    // Deduct weighted penalties from 100 to approximate an SEO score.
    $score = 100;
    $score -= $violations['critical'] * 18;
    $score -= $violations['serious'] * 12;
    $score -= $violations['moderate'] * 7;
    $score -= $violations['minor'] * 4;
    if ($violations['total'] === 0) {
        // Perfect pages cap at 98 to leave headroom for future enhancements.
        $score = 98;
    }
    $score = max(0, min(100, (int)round($score)));

    $report[] = [
        'title' => $title,
        'slug' => $slug,
        'template' => $page['template'] ?? '',
        'metrics' => $metrics,
        'issues' => $issues,
        'violations' => $violations,
        'score' => $score,
    ];
}

// Summarise totals for KPI cards.
$totalPages = count($report);
$scoreSum = 0;
$criticalIssues = 0;
$optimizedPages = 0;
$needsWork = 0;

// Prepare both an ordered list and quick lookup for the frontend payload.
$pageEntries = [];
$pageEntryMap = [];

foreach ($report as $entry) {
    $slug = $entry['slug'];
    $path = '/' . ltrim($slug, '/');

    $violations = $entry['violations'];
    $score = $entry['score'];
    $scoreSum += $score;
    $criticalIssues += (int)$violations['critical'];

    if ($score >= 90 && $violations['critical'] === 0) {
        $optimizationLevel = 'Optimised';
        $optimizedPages++;
    } elseif ($score >= 60) {
        $optimizationLevel = 'Needs Improvement';
        $needsWork++;
    } else {
        $optimizationLevel = 'Critical';
    }

    $issueDetails = [];
    // Pair each short issue description with metadata for the modal view.
    foreach ($entry['issues'] as $issueText) {
        $detail = classify_seo_issue($issueText);
        $impact = strtolower((string)($detail['impact'] ?? ''));
        if ($impact === '') {
            $impact = 'review';
        }
        $issueDetails[] = [
            'description' => $issueText,
            'impact' => $impact,
            'recommendation' => $detail['recommendation'],
        ];
    }

    $issuePreview = array_slice(array_map(static function ($detail) {
        $impact = strtolower((string)($detail['impact'] ?? ''));
        if ($impact === '') {
            $impact = 'review';
        }
        return [
            'description' => $detail['description'],
            'impact' => $impact,
        ];
    }, $issueDetails), 0, 4);

    if (empty($issuePreview)) {
        $issuePreview = [[
            'description' => 'No outstanding SEO issues',
            'impact' => 'pass',
        ]];
    }

    // Persist historic comparisons so the UI can show score deltas.
    $previousScore = derive_previous_score('seo', $slug !== '' ? $slug : ($entry['title'] !== '' ? $entry['title'] : (string)count($pageEntries)), $score);

    // Shape the data used by the dashboard and modal components.
    $pageData = [
        'title' => $entry['title'],
        'slug' => $slug,
        'url' => $path,
        'path' => $path,
        'template' => $entry['template'],
        'seoScore' => $score,
        'previousScore' => $previousScore,
        'optimizationLevel' => $optimizationLevel,
        'violations' => $violations,
        'warnings' => $violations['moderate'] + $violations['minor'],
        'lastScanned' => $lastScan,
        'pageType' => !empty($entry['template']) ? 'Template: ' . basename($entry['template']) : 'Standard Page',
        'statusMessage' => describe_seo_health($score, (int)$violations['critical']),
        'summaryLine' => sprintf('SEO health score: %d%%. %s.', $score, summarize_seo_violations($violations)),
        'issues' => [
            'preview' => $issuePreview,
            'details' => $issueDetails,
        ],
        'metrics' => $entry['metrics'],
    ];

    $pageEntries[] = $pageData;
    $pageEntryMap[$slug] = $pageData;
}

$avgScore = $totalPages > 0 ? (int)round($scoreSum / $totalPages) : 0;

// Provide totals for each filter pill shown in the dashboard.
$filterCounts = [
    'all' => $totalPages,
    'critical' => 0,
    'needs-work' => $needsWork,
    'optimized' => $optimizedPages,
];

foreach ($pageEntries as $page) {
    if ($page['optimizationLevel'] === 'Critical') {
        $filterCounts['critical']++;
    }
}

// Build URLs for linking back to the dashboard and opening detail views.
$scriptPath = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
$queryParams = is_array($_GET) ? $_GET : [];
unset($queryParams['page']);
$queryParams = array_merge(['module' => 'seo'], $queryParams);
$moduleQuery = http_build_query($queryParams);
$moduleUrl = $scriptPath . ($moduleQuery !== '' ? '?' . $moduleQuery : '');
$moduleAnchorUrl = $moduleUrl . '#seo';
$detailSlug = isset($_GET['page']) ? sanitize_text($_GET['page']) : null;
$detailSlug = $detailSlug !== null ? trim($detailSlug) : null;

$selectedPage = null;
if ($detailSlug !== null && $detailSlug !== '') {
    // When a slug is supplied in the URL, preload the detail page view.
    $selectedPage = $pageEntryMap[$detailSlug] ?? null;
}

// Clean up any parsing errors stored during DOM processing.
libxml_clear_errors();

// Prepare the stats block consumed by the frontend to avoid recalculation.
$dashboardStats = [
    'totalPages' => $totalPages,
    'avgScore' => $avgScore,
    'criticalIssues' => $criticalIssues,
    'optimizedPages' => $optimizedPages,
    'needsWork' => $needsWork,
    'filterCounts' => $filterCounts,
    'moduleUrl' => $moduleUrl,
    'detailBaseUrl' => $moduleUrl . (strpos($moduleUrl, '?') === false ? '?' : '&') . 'page=',
    'lastScan' => $lastScan,
];
?>
<div class="content-section" id="seo">
<?php if ($selectedPage): ?>
    <div class="a11y-detail-page seo-detail-page" id="seoDetailPage" data-page-slug="<?php echo htmlspecialchars($selectedPage['slug'], ENT_QUOTES); ?>">
        <?php
            $currentScore = (int)($selectedPage['seoScore'] ?? 0);
            $previousScore = (int)($selectedPage['previousScore'] ?? $currentScore);
            $deltaMeta = describe_score_delta($currentScore, $previousScore);

            // Tally issues by severity to power the filter buttons.
            $impactCounts = [
                'critical' => 0,
                'serious' => 0,
                'moderate' => 0,
                'minor' => 0,
                'review' => 0,
            ];
            foreach ($selectedPage['issues']['details'] as $issueDetail) {
                $impact = strtolower($issueDetail['impact']);
                if (isset($impactCounts[$impact])) {
                    $impactCounts[$impact]++;
                } else {
                    $impactCounts['review']++;
                }
            }
            $issueDetailCount = array_sum($impactCounts);
        ?>
        <header class="a11y-detail-header">
            <a href="<?php echo htmlspecialchars($moduleAnchorUrl, ENT_QUOTES); ?>" class="a11y-back-link" id="seoBackToDashboard">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <span>Back to SEO Dashboard</span>
            </a>
            <div class="a11y-detail-actions">
                <button type="button" class="a11y-btn a11y-btn--ghost" data-seo-action="rescan-page">
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
                <div class="a11y-health-score__label">SEO Score</div>
                <span class="a11y-health-score__badge level-<?php echo strtolower(str_replace(' ', '-', $selectedPage['optimizationLevel'])); ?>"><?php echo htmlspecialchars($selectedPage['optimizationLevel']); ?></span>
            </div>
            <div class="a11y-health-summary">
                <h1><?php echo htmlspecialchars($selectedPage['title']); ?></h1>
                <p class="a11y-health-url"><?php echo htmlspecialchars($selectedPage['url']); ?></p>
                <p><?php echo htmlspecialchars($selectedPage['statusMessage']); ?></p>
                <p class="a11y-health-overview"><?php echo htmlspecialchars($selectedPage['summaryLine']); ?></p>
                <div class="a11y-quick-stats">
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['wordCount']; ?></div>
                        <div class="a11y-quick-stat__label">Words</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['links']['internal']; ?></div>
                        <div class="a11y-quick-stat__label">Internal Links</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value">H1: <?php echo $selectedPage['metrics']['h1Count']; ?></div>
                        <div class="a11y-quick-stat__label">Heading Structure</div>
                    </div>
                    <div class="a11y-quick-stat">
                        <div class="a11y-quick-stat__value"><?php echo $selectedPage['metrics']['missingAlt']; ?></div>
                        <div class="a11y-quick-stat__label">Missing Alt</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="a11y-detail-grid" aria-labelledby="seoMetricsHeading">
            <article class="a11y-detail-card seo-detail-card">
                <div class="seo-detail-card__header">
                    <h2 id="seoMetricsHeading">Key SEO signals</h2>
                    <p class="seo-detail-card__hint">Focus on the fundamentals that influence crawlability, relevance, and click-through rate.</p>
                </div>
                <div class="seo-detail-card__metrics">
                    <div class="seo-metric-group">
                        <h3>Metadata</h3>
                        <ul class="seo-metric-list">
                            <li><span>Title length</span><span><?php echo $selectedPage['metrics']['titleLength']; ?> characters</span></li>
                            <li><span>Meta description</span><span><?php echo $selectedPage['metrics']['metaDescriptionLength']; ?> characters</span></li>
                            <li><span>Canonical URL</span><span><?php echo $selectedPage['metrics']['hasCanonical'] ? 'Present' : 'Missing'; ?></span></li>
                        </ul>
                    </div>
                    <div class="seo-metric-group">
                        <h3>Content depth</h3>
                        <ul class="seo-metric-list">
                            <li><span>Word count</span><span><?php echo $selectedPage['metrics']['wordCount']; ?></span></li>
                            <li><span>H1 headings</span><span><?php echo $selectedPage['metrics']['h1Count']; ?></span></li>
                            <li><span>Structured data</span><span><?php echo $selectedPage['metrics']['hasStructuredData'] ? 'Detected' : 'Not detected'; ?></span></li>
                        </ul>
                    </div>
                    <div class="seo-metric-group">
                        <h3>Media &amp; sharing</h3>
                        <ul class="seo-metric-list">
                            <li><span>Images</span><span><?php echo $selectedPage['metrics']['images']; ?></span></li>
                            <li><span>Missing alt text</span><span><?php echo $selectedPage['metrics']['missingAlt']; ?></span></li>
                            <li><span>Open Graph</span><span><?php echo $selectedPage['metrics']['hasOpenGraph'] ? 'Configured' : 'Missing'; ?></span></li>
                        </ul>
                    </div>
                    <div class="seo-metric-group">
                        <h3>Linking</h3>
                        <ul class="seo-metric-list">
                            <li><span>Internal links</span><span><?php echo $selectedPage['metrics']['links']['internal']; ?></span></li>
                            <li><span>External links</span><span><?php echo $selectedPage['metrics']['links']['external']; ?></span></li>
                            <li><span>Robots directives</span><span><?php echo htmlspecialchars(describe_robots_directive($selectedPage['metrics']['robotsDirective'])); ?></span></li>
                        </ul>
                    </div>
                </div>
            </article>
        </section>

        <section class="a11y-detail-issues seo-detail-issues" aria-labelledby="seoIssuesHeading">
            <header class="seo-detail-issues__header">
                <div>
                    <h2 id="seoIssuesHeading">Actionable SEO fixes</h2>
                    <p>Address these issues to strengthen relevance signals and organic performance.</p>
                </div>
                <span class="seo-detail-issues__count"><?php echo $issueDetailCount; ?> <?php echo $issueDetailCount === 1 ? 'issue' : 'issues'; ?></span>
            </header>
            <?php if ($issueDetailCount > 0): ?>
                <div class="a11y-severity-filters" role="group" aria-label="Filter issues by severity">
                    <button type="button" class="a11y-severity-btn active" data-seo-severity="all" aria-pressed="true" aria-label="Show all issues (<?php echo $issueDetailCount; ?>)">
                        All <span aria-hidden="true">(<?php echo $issueDetailCount; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="critical" aria-pressed="false" aria-label="Show critical issues (<?php echo $impactCounts['critical']; ?>)">
                        Critical <span aria-hidden="true">(<?php echo $impactCounts['critical']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="serious" aria-pressed="false" aria-label="Show serious issues (<?php echo $impactCounts['serious']; ?>)">
                        Serious <span aria-hidden="true">(<?php echo $impactCounts['serious']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="moderate" aria-pressed="false" aria-label="Show moderate issues (<?php echo $impactCounts['moderate']; ?>)">
                        Moderate <span aria-hidden="true">(<?php echo $impactCounts['moderate']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="minor" aria-pressed="false" aria-label="Show minor issues (<?php echo $impactCounts['minor']; ?>)">
                        Minor <span aria-hidden="true">(<?php echo $impactCounts['minor']; ?>)</span>
                    </button>
                    <button type="button" class="a11y-severity-btn" data-seo-severity="review" aria-pressed="false" aria-label="Show review issues (<?php echo $impactCounts['review']; ?>)">
                        Review <span aria-hidden="true">(<?php echo $impactCounts['review']; ?>)</span>
                    </button>
                </div>
                <div class="sr-only" id="seoIssueFilterStatus" role="status" aria-live="polite"></div>
                <div class="a11y-issue-list">
                    <?php foreach ($selectedPage['issues']['details'] as $issue): ?>
                        <article class="a11y-issue-card impact-<?php echo htmlspecialchars($issue['impact']); ?>" data-impact="<?php echo htmlspecialchars(strtolower($issue['impact'])); ?>">
                            <header>
                                <h3><?php echo htmlspecialchars($issue['description']); ?></h3>
                                <span class="a11y-impact-badge impact-<?php echo htmlspecialchars($issue['impact']); ?>"><?php echo ucfirst($issue['impact']); ?></span>
                            </header>
                            <p><?php echo htmlspecialchars($issue['recommendation']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="a11y-detail-empty" id="seoNoIssuesMessage" hidden>No issues match this severity filter.</p>
            <?php else: ?>
                <p class="a11y-detail-success">This page passes the automated SEO checks with no outstanding issues.</p>
            <?php endif; ?>
        </section>
    </div>
<?php else: ?>
    <div class="seo-dashboard a11y-dashboard" data-last-scan="<?php echo htmlspecialchars($lastScan, ENT_QUOTES); ?>">
        <header class="seo-hero">
            <div class="seo-hero-content">
                <div>
                    <span class="hero-eyebrow seo-hero-eyebrow">Optimisation Signals</span>
                    <h2 class="seo-hero-title">SEO Optimisation Dashboard</h2>
                    <p class="seo-hero-subtitle">Measure on-page signals and prioritise fixes that improve organic visibility.</p>
                </div>
                <div class="seo-hero-actions">
                    <button type="button" id="seoScanAllPagesBtn" class="a11y-btn a11y-btn--primary" data-seo-action="scan-all">
                        <i class="fas fa-search" aria-hidden="true"></i>
                        <span>Scan All Pages</span>
                    </button>
                    <span class="seo-hero-meta">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        Last scan: <span class="seo-last-scan-value"><?php echo htmlspecialchars($lastScan); ?></span>
                    </span>
                </div>
            </div>
            <div class="seo-overview-grid">
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatTotalPages"><?php echo $totalPages; ?></div>
                    <div class="seo-overview-label">Total Pages</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatAvgScore"><?php echo $avgScore; ?>%</div>
                    <div class="seo-overview-label">Average SEO Score</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatCritical"><?php echo $criticalIssues; ?></div>
                    <div class="seo-overview-label">Critical Issues</div>
                </div>
                <div class="seo-overview-card">
                    <div class="seo-overview-value" id="seoStatOptimized"><?php echo $optimizedPages; ?></div>
                    <div class="seo-overview-label">Optimised Pages</div>
                </div>
            </div>
        </header>

        <div class="seo-controls">
            <label class="seo-search" for="seoSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="seoSearchInput" placeholder="Search pages by title, URL, or issue" aria-label="Search SEO results">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="SEO filters">
                <button type="button" class="a11y-filter-btn active" data-seo-filter="all">All Pages <span class="a11y-filter-count" data-count="all"><?php echo $filterCounts['all']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-seo-filter="critical">Critical Issues <span class="a11y-filter-count" data-count="critical"><?php echo $filterCounts['critical']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-seo-filter="needs-work">Needs Work <span class="a11y-filter-count" data-count="needs-work"><?php echo $filterCounts['needs-work']; ?></span></button>
                <button type="button" class="a11y-filter-btn" data-seo-filter="optimized">Optimised <span class="a11y-filter-count" data-count="optimized"><?php echo $filterCounts['optimized']; ?></span></button>
            </div>
            <div class="a11y-sort-group" role="group" aria-label="Sort pages">
                <label for="seoSortSelect">Sort by</label>
                <select id="seoSortSelect" class="a11y-sort-select">
                    <option value="score" selected>SEO score</option>
                    <option value="title">Title</option>
                    <option value="issues">Total issues</option>
                </select>
                <button type="button" class="a11y-sort-direction" id="seoSortDirection" data-direction="desc" aria-label="Toggle sort direction (High to low)" aria-pressed="true">
                    <i class="fas fa-sort-amount-down-alt" aria-hidden="true"></i>
                    <span class="a11y-sort-direction__text" id="seoSortDirectionLabel">High to low</span>
                </button>
            </div>
            <div class="seo-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="seo-view-btn active" data-seo-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="seo-view-btn" data-seo-view="table" aria-label="Table view">
                    <i class="fas fa-table" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <section class="seo-page-grid" aria-live="polite">
            <?php foreach ($pageEntries as $page): ?>
                <article class="seo-page-card" data-page-slug="<?php echo htmlspecialchars($page['slug']); ?>" data-seo-score="<?php echo (int)$page['seoScore']; ?>" data-seo-level="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $page['optimizationLevel'])), ENT_QUOTES); ?>" data-issues="<?php echo (int)$page['violations']['total']; ?>" tabindex="0" role="button" aria-haspopup="dialog" aria-label="View SEO summary for <?php echo htmlspecialchars($page['title']); ?>">
                    <header>
                        <div class="seo-page-score score-indicator <?php echo $page['seoScore'] >= 90 ? 'seo-score--excellent' : ($page['seoScore'] >= 75 ? 'seo-score--good' : ($page['seoScore'] >= 60 ? 'seo-score--fair' : 'seo-score--poor')); ?>">
                            <span class="score-indicator__number"><?php echo (int)$page['seoScore']; ?></span><span>%</span>
                        </div>
                        <span class="seo-level-badge level-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $page['optimizationLevel'])), ENT_QUOTES); ?>"><?php echo htmlspecialchars($page['optimizationLevel']); ?></span>
                    </header>
                    <h3><?php echo htmlspecialchars($page['title']); ?></h3>
                    <p class="seo-page-url"><?php echo htmlspecialchars($page['url']); ?></p>
                    <p class="seo-page-summary"><?php echo htmlspecialchars($page['summaryLine']); ?></p>
                    <ul class="seo-page-metrics">
                        <li><span>Word count</span><span><?php echo $page['metrics']['wordCount']; ?></span></li>
                        <li><span>Internal links</span><span><?php echo $page['metrics']['links']['internal']; ?></span></li>
                        <li><span>Missing alt</span><span><?php echo $page['metrics']['missingAlt']; ?></span></li>
                    </ul>
                    <div class="seo-page-issues">
                        <?php foreach ($page['issues']['preview'] as $issue): ?>
                            <?php
                                $issueDescription = is_array($issue) ? ($issue['description'] ?? '') : (string)$issue;
                                $issueImpact = is_array($issue) ? ($issue['impact'] ?? 'review') : 'review';
                                $impactSlug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string)$issueImpact));
                                $impactSlug = trim($impactSlug, '-');
                                $impactClass = $impactSlug !== '' ? ' seo-issue-chip--' . htmlspecialchars($impactSlug, ENT_QUOTES) : '';
                            ?>
                            <span class="seo-issue-chip<?php echo $impactClass; ?>"><?php echo htmlspecialchars($issueDescription); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <footer>
                        <button type="button" class="seo-card-link" aria-label="See detailed SEO guidance for <?php echo htmlspecialchars($page['title']); ?>">
                            <span>See detailed SEO guidance</span>
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </button>
                        <span class="sr-only">Press Enter to open SEO details for <?php echo htmlspecialchars($page['title']); ?>.</span>
                    </footer>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="seo-page-table" hidden>
            <table>
                <caption class="sr-only">SEO summary table</caption>
                <thead>
                    <tr>
                        <th scope="col">Page</th>
                        <th scope="col">Score</th>
                        <th scope="col">Issues</th>
                        <th scope="col">Word count</th>
                        <th scope="col">Internal links</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pageEntries as $page): ?>
                        <tr data-page-slug="<?php echo htmlspecialchars($page['slug']); ?>" data-seo-score="<?php echo (int)$page['seoScore']; ?>" data-issues="<?php echo (int)$page['violations']['total']; ?>" tabindex="0" role="button" aria-haspopup="dialog" aria-label="View SEO summary for <?php echo htmlspecialchars($page['title']); ?>">
                            <th scope="row">
                                <div class="seo-table-title"><?php echo htmlspecialchars($page['title']); ?></div>
                                <div class="seo-table-url"><?php echo htmlspecialchars($page['url']); ?></div>
                            </th>
                            <td><?php echo (int)$page['seoScore']; ?>%</td>
                            <td><?php echo (int)$page['violations']['total']; ?></td>
                            <td><?php echo (int)$page['metrics']['wordCount']; ?></td>
                            <td><?php echo (int)$page['metrics']['links']['internal']; ?></td>
                            <td><?php echo htmlspecialchars($page['optimizationLevel']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <div class="a11y-page-detail seo-page-detail" id="seoPageDetail" hidden role="dialog" aria-modal="true" aria-labelledby="seoDetailTitle">
            <div class="a11y-detail-content">
                <button type="button" class="a11y-detail-close" id="seoDetailClose" aria-label="Close SEO details">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
                <header class="a11y-detail-modal-header">
                    <h2 id="seoDetailTitle">Page SEO Details</h2>
                    <p id="seoDetailUrl" class="a11y-detail-url"></p>
                    <p id="seoDetailDescription" class="a11y-detail-description"></p>
                </header>
                <div class="a11y-detail-modal-body">
                    <div class="a11y-detail-badges">
                        <span class="a11y-detail-score score-indicator score-indicator--badge" id="seoDetailScore"></span>
                        <span class="a11y-detail-level" id="seoDetailLevel"></span>
                        <span class="a11y-detail-violations" id="seoDetailSignals"></span>
                    </div>
                    <ul class="a11y-detail-metric-list" id="seoDetailMetrics"></ul>
                    <div class="a11y-detail-issues-list">
                        <h3>Key SEO findings</h3>
                        <ul id="seoDetailIssues"></ul>
                    </div>
                </div>
            </div>
        </div>

        <footer class="seo-dashboard-footer">
            <p>SEO data last analysed on <span class="seo-last-scan-value"><?php echo htmlspecialchars($lastScan); ?></span>. Refresh the scan whenever you publish new content or update templates.</p>
        </footer>
    </div>
<?php endif; ?>
</div>
<script>
    // Expose the processed dataset for the JavaScript module to hydrate the UI.
    window.__SEO_MODULE_DATA__ = <?php echo json_encode([
        'pages' => $pageEntries,
        'stats' => $dashboardStats,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
</script>
