<?php
// File: dashboard_data.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/settings.php';
require_login();

$pagesFile = __DIR__ . '/../../data/pages.json';
$mediaFile = __DIR__ . '/../../data/media.json';
$usersFile = __DIR__ . '/../../data/users.json';
$menusFile = __DIR__ . '/../../data/menus.json';
$formsFile = __DIR__ . '/../../data/forms.json';
$postsFile = __DIR__ . '/../../data/blog_posts.json';
$historyFile = __DIR__ . '/../../data/page_history.json';
$eventsFile = __DIR__ . '/../../data/events.json';
$eventOrdersFile = __DIR__ . '/../../data/event_orders.json';
$dataDirectory = __DIR__ . '/../../data';

$pages = read_json_file($pagesFile);
$media = read_json_file($mediaFile);
$users = read_json_file($usersFile);
$settings = get_site_settings();
$menus = read_json_file($menusFile);
$forms = read_json_file($formsFile);
$posts = read_json_file($postsFile);
$history = read_json_file($historyFile);
$events = read_json_file($eventsFile);
$eventOrders = read_json_file($eventOrdersFile);

if (!is_array($pages)) {
    $pages = [];
}
if (!is_array($media)) {
    $media = [];
}
if (!is_array($users)) {
    $users = [];
}
if (!is_array($settings)) {
    $settings = [];
}
if (!is_array($menus)) {
    $menus = [];
}
if (!is_array($forms)) {
    $forms = [];
}
if (!is_array($posts)) {
    $posts = [];
}
if (!is_array($history)) {
    $history = [];
}
if (!is_array($events)) {
    $events = [];
}
if (!is_array($eventOrders)) {
    $eventOrders = [];
}

$events = array_values(array_filter($events, 'is_array'));
$eventOrders = array_values(array_filter($eventOrders, 'is_array'));

$views = 0;
foreach ($pages as $p) {
    $views += $p['views'] ?? 0;
}

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

$templateDir = realpath(__DIR__ . '/../../../theme/templates/pages');
$cacheFile = $dataDirectory . '/dashboard_cache.json';

function dashboard_collect_template_mtimes(?string $templateDir): array
{
    if (!$templateDir || !is_dir($templateDir)) {
        return [];
    }

    $mtimes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($templateDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $relativePath = ltrim(substr($path, strlen($templateDir)), DIRECTORY_SEPARATOR);
        $mtimes[$relativePath] = $file->getMTime();
    }

    ksort($mtimes);

    return $mtimes;
}

function dashboard_load_cache(string $cacheFile): ?array
{
    if (!is_file($cacheFile)) {
        return null;
    }

    $contents = @file_get_contents($cacheFile);
    if ($contents === false) {
        return null;
    }

    $format = null;
    $decoded = cms_decode_payload_with_format($contents, $format);
    if (!is_array($decoded)) {
        return null;
    }

    if ($format === 'json') {
        @file_put_contents($cacheFile, cms_encode_payload($decoded));
    }

    return $decoded;
}

function dashboard_cache_is_valid(?array $cache, array $signature): bool
{
    if (!is_array($cache) || !isset($cache['signature']) || !is_array($cache['signature'])) {
        return false;
    }

    if (($cache['signature']['pagesMtime'] ?? null) !== ($signature['pagesMtime'] ?? null)) {
        return false;
    }

    if (($cache['signature']['templates'] ?? null) !== ($signature['templates'] ?? null)) {
        return false;
    }

    $requiredKeys = [
        'accessibilitySummary',
        'seoSummary',
        'speedSummary',
        'analyticsSummary',
        'largestPage',
    ];
    foreach ($requiredKeys as $key) {
        if (!isset($cache[$key]) || !is_array($cache[$key])) {
            return false;
        }
    }

    return true;
}

function dashboard_capture_template_html(string $templateFile, array $settings, array $menus, string $scriptBase): string {
    $page = ['content' => '{{CONTENT}}'];
    $themeBase = $scriptBase . '/theme';

    $templateContents = @file_get_contents($templateFile);
    if ($templateContents === false) {
        return '{{CONTENT}}';
    }

    $namespace = '__DashboardTemplate' . md5($templateFile);
    $wrappedContents = "<?php namespace {$namespace}; ?>\n" . $templateContents;

    $tempDirectory = $settings['temp_dir'] ?? $settings['temp_path'] ?? sys_get_temp_dir();
    if (!is_string($tempDirectory) || trim($tempDirectory) === '' || !is_dir($tempDirectory) || !is_writable($tempDirectory)) {
        return '{{CONTENT}}';
    }

    $temporaryFile = @tempnam($tempDirectory, 'dash_tpl_');
    if ($temporaryFile === false) {
        return '{{CONTENT}}';
    }

    $currentWorkingDirectory = getcwd();
    $html = '';
    ob_start();

    try {
        $bytesWritten = @file_put_contents($temporaryFile, $wrappedContents);
        if ($bytesWritten === false) {
            ob_end_clean();
            $html = '{{CONTENT}}';
            return $html;
        }

        chdir(dirname($templateFile));
        include $temporaryFile;
        $html = (string)ob_get_clean();
    } catch (Throwable $exception) {
        ob_end_clean();
        $html = '{{CONTENT}}';
    } finally {
        chdir($currentWorkingDirectory);
        if (is_string($temporaryFile) && $temporaryFile !== '') {
            @unlink($temporaryFile);
        }
    }

    if ($html === '') {
        $html = '{{CONTENT}}';
    }

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

function dashboard_build_page_html(array $page, array $settings, array $menus, string $scriptBase, ?string $templateDir): string {
    static $templateCache = [];

    if (!$templateDir) {
        return (string)($page['content'] ?? '');
    }

    $templateName = !empty($page['template']) ? basename((string)$page['template']) : 'page.php';
    $templateFile = $templateDir . DIRECTORY_SEPARATOR . $templateName;
    if (!is_file($templateFile)) {
        return (string)($page['content'] ?? '');
    }

    if (!isset($templateCache[$templateFile])) {
        $templateCache[$templateFile] = dashboard_capture_template_html($templateFile, $settings, $menus, $scriptBase);
    }

    $templateHtml = $templateCache[$templateFile];
    $content = (string)($page['content'] ?? '');
    return str_replace('{{CONTENT}}', $content, $templateHtml);
}

function dashboard_count_menu_items(array $items): int
{
    $total = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $total++;
        if (!empty($item['children']) && is_array($item['children'])) {
            $total += dashboard_count_menu_items($item['children']);
        }
    }
    return $total;
}

function dashboard_strlen(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int)mb_strlen($value);
    }

    return strlen($value);
}

function dashboard_status_label(string $status): string
{
    switch ($status) {
        case 'urgent':
            return 'Action required';
        case 'warning':
            return 'Needs attention';
        default:
            return 'On track';
    }
}

$cacheSignature = [
    'pagesMtime' => is_file($pagesFile) ? filemtime($pagesFile) : null,
    'templates' => dashboard_collect_template_mtimes($templateDir),
];
$dashboardCache = dashboard_load_cache($cacheFile);
$cacheIsValid = dashboard_cache_is_valid($dashboardCache, $cacheSignature);
$cacheGeneratedAt = null;

$accessibilitySummary = [
    'accessible' => 0,
    'needs_review' => 0,
    'missing_alt' => 0,
    'issues' => 0,
];

$seoSummary = [
    'optimised' => 0,
    'missing_title' => 0,
    'missing_description' => 0,
    'long_title' => 0,
    'description_length' => 0,
    'duplicate_slugs' => 0,
    'issues' => 0,
];

$speedSummary = [
    'fast' => 0,
    'monitor' => 0,
    'slow' => 0,
];

$analyticsSummary = [
    'totalViews' => 0,
    'averageViews' => 0,
    'topPage' => null,
    'topViews' => 0,
];

$largestPage = ['title' => null, 'length' => 0];

if ($cacheIsValid) {
    $accessibilitySummary = $dashboardCache['accessibilitySummary'];
    $seoSummary = $dashboardCache['seoSummary'];
    $speedSummary = $dashboardCache['speedSummary'];
    $analyticsSummary = $dashboardCache['analyticsSummary'];
    $largestPage = $dashboardCache['largestPage'];
    $cacheGeneratedAt = $dashboardCache['generatedAt'] ?? null;
} else {
    $libxmlPrevious = libxml_use_internal_errors(true);

    $slugCounts = [];
    foreach ($pages as $page) {
        $slug = strtolower(trim((string)($page['slug'] ?? '')));
        if ($slug === '') {
            continue;
        }
        if (!isset($slugCounts[$slug])) {
            $slugCounts[$slug] = 0;
        }
        $slugCounts[$slug]++;
    }

    $genericLinkTerms = [
        'click here',
        'read more',
        'learn more',
        'here',
        'more',
        'this page',
    ];

    foreach ($pages as $page) {
        $pageHtml = dashboard_build_page_html($page, $settings, $menus, $scriptBase, $templateDir);

        $doc = new DOMDocument();
        $loaded = trim($pageHtml) !== '' && $doc->loadHTML('<?xml encoding="utf-8" ?>' . $pageHtml);

        $missingAlt = 0;
        $genericLinks = 0;
        $landmarks = 0;
        $h1Count = 0;
        $seoIssues = 0;

        if ($loaded) {
            $images = $doc->getElementsByTagName('img');
            foreach ($images as $img) {
                $alt = trim($img->getAttribute('alt'));
                if ($alt === '') {
                    $missingAlt++;
                }
            }

            $h1Count = $doc->getElementsByTagName('h1')->length;

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
            $issues[] = 'missing_alt';
            $accessibilitySummary['missing_alt'] += $missingAlt;
        }

        if ($h1Count === 0 || $h1Count > 1) {
            $issues[] = 'h1_count';
        }

        if ($genericLinks > 0) {
            $issues[] = 'generic_links';
        }

        if ($landmarks === 0) {
            $issues[] = 'landmarks';
        }

        if (empty($issues)) {
            $accessibilitySummary['accessible']++;
        } else {
            $accessibilitySummary['needs_review']++;
        }

        $accessibilitySummary['issues'] += count($issues);

        $metaTitle = trim((string)($page['meta_title'] ?? ''));
        if ($metaTitle === '') {
            $seoSummary['missing_title']++;
            $seoIssues++;
        } else {
            $titleLength = dashboard_strlen($metaTitle);
            if ($titleLength > 60) {
                $seoSummary['long_title']++;
                $seoIssues++;
            }
        }

        $metaDescription = trim((string)($page['meta_description'] ?? ''));
        if ($metaDescription === '') {
            $seoSummary['missing_description']++;
            $seoIssues++;
        } else {
            $descriptionLength = dashboard_strlen($metaDescription);
            if ($descriptionLength < 50 || $descriptionLength > 160) {
                $seoSummary['description_length']++;
                $seoIssues++;
            }
        }

        if ($seoIssues === 0) {
            $seoSummary['optimised']++;
        }

        $seoSummary['issues'] += $seoIssues;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($libxmlPrevious);

    foreach ($slugCounts as $slug => $count) {
        if ($count > 1) {
            $seoSummary['duplicate_slugs'] += $count - 1;
            $seoSummary['issues'] += $count - 1;
        }
    }
}

$totalPages = count($pages);
$accessibilityScore = $totalPages > 0 ? round(($accessibilitySummary['accessible'] / $totalPages) * 100) : 0;

$pagesPublished = 0;
$pagesDraft = 0;

foreach ($pages as $page) {
    if (!empty($page['published'])) {
        $pagesPublished++;
    } else {
        $pagesDraft++;
    }

    if (!$cacheIsValid) {
        $content = strip_tags((string)($page['content'] ?? ''));
        $length = strlen($content);
        if ($length > $largestPage['length']) {
            $largestPage = [
                'title' => (string)($page['title'] ?? ''),
                'length' => $length,
            ];
        }

        if ($length < 5000) {
            $speedSummary['fast']++;
        } elseif ($length < 15000) {
            $speedSummary['monitor']++;
        } else {
            $speedSummary['slow']++;
        }
    }
}

$mediaTotalSize = 0;
foreach ($media as $item) {
    if (isset($item['size']) && is_numeric($item['size'])) {
        $mediaTotalSize += (int)$item['size'];
    }
}

$usersByRole = [];
foreach ($users as $user) {
    $role = strtolower((string)($user['role'] ?? 'unknown'));
    if ($role === '') {
        $role = 'unknown';
    }
    if (!isset($usersByRole[$role])) {
        $usersByRole[$role] = 0;
    }
    $usersByRole[$role]++;
}

$postsByStatus = [
    'published' => 0,
    'draft' => 0,
    'scheduled' => 0,
    'other' => 0,
];
foreach ($posts as $post) {
    $status = strtolower(trim((string)($post['status'] ?? '')));
    if ($status === '') {
        $status = 'other';
    }
    if (!array_key_exists($status, $postsByStatus)) {
        $status = 'other';
    }
    $postsByStatus[$status]++;
}

$formsFields = 0;
foreach ($forms as $form) {
    if (!empty($form['fields']) && is_array($form['fields'])) {
        $formsFields += count($form['fields']);
    }
}

$menuItems = 0;
foreach ($menus as $menu) {
    if (!empty($menu['items']) && is_array($menu['items'])) {
        $menuItems += dashboard_count_menu_items($menu['items']);
    }
}

$logEntries = 0;
$latestLogTime = null;
foreach ($history as $entries) {
    if (!is_array($entries)) {
        continue;
    }
    $logEntries += count($entries);
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $time = isset($entry['time']) ? (int)$entry['time'] : null;
        if ($time) {
            if ($latestLogTime === null || $time > $latestLogTime) {
                $latestLogTime = $time;
            }
        }
    }
}
$logsLastActivity = $latestLogTime ? date('c', $latestLogTime) : null;

$searchBreakdown = [
    'pages' => $totalPages,
    'posts' => count($posts),
    'media' => count($media),
];
$searchIndexCount = array_sum($searchBreakdown);

$settingsCount = is_array($settings) ? count($settings) : 0;
$socialCount = (isset($settings['social']) && is_array($settings['social'])) ? count($settings['social']) : 0;

$sitemapEntries = 0;
foreach ($pages as $page) {
    if (!empty($page['published'])) {
        $sitemapEntries++;
    }
}

$topPage = null;
foreach ($pages as $page) {
    $pageViews = (int)($page['views'] ?? 0);
    if (!$topPage || $pageViews > $topPage['views']) {
        $topPage = [
            'title' => (string)($page['title'] ?? ''),
            'views' => $pageViews,
        ];
    }
}

if (!$cacheIsValid) {
    $analyticsSummary = [
        'totalViews' => $views,
        'averageViews' => $totalPages > 0 ? (int)round($views / $totalPages) : 0,
        'topPage' => $topPage['title'] ?? null,
        'topViews' => $topPage['views'] ?? 0,
    ];
}

if (!$cacheIsValid) {
    $cacheGeneratedAt = gmdate(DATE_ATOM);
    $cachePayload = [
        'signature' => $cacheSignature,
        'generatedAt' => $cacheGeneratedAt,
        'accessibilitySummary' => $accessibilitySummary,
        'seoSummary' => $seoSummary,
        'speedSummary' => $speedSummary,
        'analyticsSummary' => $analyticsSummary,
        'largestPage' => $largestPage,
    ];

    if (is_dir($dataDirectory)) {
        @file_put_contents($cacheFile, cms_encode_payload($cachePayload));
    }
}

$eventsTotal = count($events);
$eventsPublished = 0;
$eventsUpcoming = 0;
$eventsTicketsSold = 0;
$eventsRevenue = 0.0;
$eventsPendingOrders = 0;
$eventsCurrency = 'USD';
if (!empty($settings['events_currency'])) {
    $eventsCurrency = strtoupper((string)$settings['events_currency']);
} elseif (!empty($settings['currency'])) {
    $eventsCurrency = strtoupper((string)$settings['currency']);
}

$now = time();
foreach ($events as $event) {
    $status = strtolower(trim((string)($event['status'] ?? 'draft')));
    if ($status === 'published') {
        $eventsPublished++;
    }

    $start = isset($event['start']) ? strtotime((string)$event['start']) : false;
    if ($status === 'published' && $start !== false && $start >= $now) {
        $eventsUpcoming++;
    }
}

foreach ($eventOrders as $order) {
    $status = strtolower(trim((string)($order['status'] ?? 'paid')));
    $amount = isset($order['amount']) ? (float)$order['amount'] : 0.0;
    $tickets = 0;
    if (!empty($order['tickets']) && is_array($order['tickets'])) {
        foreach ($order['tickets'] as $ticket) {
            if (!is_array($ticket)) {
                continue;
            }
            $tickets += max(0, (int)($ticket['quantity'] ?? 0));
        }
    }

    if ($status === 'refunded') {
        continue;
    }

    $eventsTicketsSold += $tickets;
    $eventsRevenue += $amount;

    if ($status !== 'paid' && $status !== 'completed') {
        $eventsPendingOrders++;
    }
}

$pagesStatus = 'ok';
if ($totalPages === 0) {
    $pagesStatus = 'urgent';
} elseif ($pagesDraft > 0) {
    $pagesStatus = 'warning';
}
$pagesTrend = $pagesDraft > 0
    ? $pagesDraft . ' drafts awaiting review'
    : 'All pages published';
$pagesCta = $totalPages === 0
    ? 'Create your first page'
    : ($pagesDraft > 0 ? 'Review drafts' : 'Manage pages');

$mediaCount = count($media);
$mediaStatus = $mediaCount === 0 ? 'urgent' : 'ok';
$mediaTrend = $mediaCount === 0
    ? 'Library is empty'
    : $mediaTotalSize . ' bytes stored';
$mediaCta = $mediaCount === 0 ? 'Upload media' : 'Open media library';

$postsTotal = count($posts);
$postsDraft = (int)$postsByStatus['draft'];
$postsScheduled = (int)$postsByStatus['scheduled'];
$blogsStatus = 'ok';
if ($postsTotal === 0) {
    $blogsStatus = 'urgent';
} elseif ($postsDraft > 0 || $postsScheduled > 0) {
    $blogsStatus = 'warning';
}
$blogsTrend = $postsDraft > 0
    ? $postsDraft . ' drafts awaiting publication'
    : ($postsScheduled > 0
        ? $postsScheduled . ' posts scheduled'
        : 'Publishing cadence on track');
$blogsCta = $postsTotal === 0 ? 'Write your first post' : ($postsDraft > 0 ? 'Publish drafts' : 'Manage posts');

$formsCount = count($forms);
$formsStatus = $formsCount === 0 ? 'urgent' : 'ok';
$formsTrend = 'Fields configured: ' . (int)$formsFields;
$formsCta = $formsCount === 0 ? 'Create a form' : 'Review submissions';

$menusCount = count($menus);
$menusStatus = $menuItems === 0 ? 'urgent' : 'ok';
$menusTrend = $menuItems === 0
    ? 'No navigation items configured'
    : (int)$menuItems . ' navigation items live';
$menusCta = $menusCount === 0 ? 'Create a menu' : 'Manage navigation';

$usersCount = count($users);
$adminCount = (int)($usersByRole['admin'] ?? 0);
$editorCount = (int)($usersByRole['editor'] ?? 0);
$usersStatus = $adminCount === 0 ? 'urgent' : ($usersCount === 0 ? 'urgent' : 'ok');
$usersTrend = $editorCount > 0
    ? $editorCount . ' editors collaborating'
    : 'Invite collaborators to join';
$usersCta = $adminCount === 0 ? 'Add an admin' : 'Manage team';

$analyticsStatus = $analyticsSummary['totalViews'] === 0 ? 'warning' : 'ok';
$analyticsTrend = 'Average views per page: ' . (int)$analyticsSummary['averageViews'];
$analyticsCta = $analyticsSummary['totalViews'] === 0 ? 'Set up tracking' : 'Explore analytics';

$accessibilityStatus = 'ok';
if ($accessibilitySummary['needs_review'] > 0 || $accessibilitySummary['missing_alt'] > 0) {
    $accessibilityStatus = 'warning';
}
if ($accessibilitySummary['accessible'] === 0 && ($accessibilitySummary['needs_review'] > 0 || $accessibilitySummary['missing_alt'] > 0)) {
    $accessibilityStatus = 'urgent';
}
$accessibilityTrend = $accessibilitySummary['missing_alt'] > 0
    ? $accessibilitySummary['missing_alt'] . ' images missing alt text'
    : 'Alt text coverage looks good';
$accessibilityCta = $accessibilitySummary['needs_review'] > 0 || $accessibilitySummary['missing_alt'] > 0
    ? 'Audit accessibility'
    : 'Review accessibility';

$logsStatus = $logEntries === 0 ? 'warning' : 'ok';
$logsTrend = $logsLastActivity ? 'Last activity ' . $logsLastActivity : 'No activity recorded yet';
$logsCta = 'View history';

$searchStatus = $searchIndexCount === 0 ? 'urgent' : 'ok';
$searchTrend = 'Indexed records: ' . (int)$searchIndexCount;
$searchCta = $searchIndexCount === 0 ? 'Build the search index' : 'Manage search index';

$settingsStatus = $socialCount === 0 ? 'warning' : 'ok';
$settingsTrend = $socialCount === 0
    ? 'No social links configured'
    : (int)$socialCount . ' social links live';
$settingsCta = $socialCount === 0 ? 'Add social links' : 'Adjust settings';

$sitemapStatus = $sitemapEntries === 0 ? 'warning' : 'ok';
$sitemapTrend = $sitemapEntries === 0
    ? 'Publish pages to populate the sitemap'
    : (int)$sitemapEntries . ' URLs ready for sitemap.xml';
$sitemapCta = $sitemapEntries === 0 ? 'Publish pages' : 'Review sitemap';

$speedStatus = 'ok';
if ($speedSummary['slow'] > 0) {
    $speedStatus = $speedSummary['slow'] >= $speedSummary['fast'] ? 'urgent' : 'warning';
} elseif ($speedSummary['monitor'] > 0) {
    $speedStatus = 'warning';
}
$speedTrend = 'Slow pages: ' . (int)$speedSummary['slow'];
$speedCta = $speedSummary['slow'] > 0 ? 'Optimise slow pages' : 'Review performance';

$seoStatus = 'ok';
if ($seoSummary['missing_title'] > 0 || $seoSummary['missing_description'] > 0 || $seoSummary['duplicate_slugs'] > 0) {
    $seoStatus = 'urgent';
} elseif ($seoSummary['long_title'] > 0 || $seoSummary['description_length'] > 0) {
    $seoStatus = 'warning';
}
$seoTrend = 'Meta descriptions within best practice range';
if ($seoSummary['duplicate_slugs'] > 0) {
    $seoTrend = 'Duplicate slugs detected: ' . (int)$seoSummary['duplicate_slugs'];
} elseif ($seoSummary['missing_description'] > 0 || $seoSummary['missing_title'] > 0) {
    $seoTrend = (int)($seoSummary['missing_title'] + $seoSummary['missing_description']) . ' meta fields missing';
} elseif ($seoSummary['long_title'] > 0 || $seoSummary['description_length'] > 0) {
    $seoTrend = 'Metadata length alerts: ' . (int)($seoSummary['long_title'] + $seoSummary['description_length']);
}
$seoCta = $seoStatus === 'urgent' ? 'Fix SEO issues' : 'Review SEO settings';

$eventsStatus = 'ok';
if ($eventsTotal === 0) {
    $eventsStatus = 'urgent';
} elseif ($eventsPublished === 0 || $eventsUpcoming === 0 || $eventsPendingOrders > 0) {
    $eventsStatus = 'warning';
}
$eventsSecondary = 'Upcoming: ' . $eventsUpcoming . ' • Tickets sold: ' . $eventsTicketsSold;
$eventsTrend = $eventsPendingOrders > 0
    ? 'Pending orders: ' . $eventsPendingOrders
    : ($eventsRevenue > 0
        ? 'Revenue: ' . $eventsCurrency . ' ' . round($eventsRevenue, 2)
        : 'No ticket sales yet');
$eventsCta = $eventsTotal === 0
    ? 'Create an event'
    : ($eventsPendingOrders > 0 ? 'Review event orders' : 'Open events');

$moduleSummaries = [
    [
        'id' => 'pages',
        'module' => 'Pages',
        'primary' => $totalPages . ' total pages',
        'secondary' => 'Published: ' . $pagesPublished . ' • Drafts: ' . $pagesDraft,
        'status' => $pagesStatus,
        'statusLabel' => dashboard_status_label($pagesStatus),
        'trend' => $pagesTrend,
        'cta' => $pagesCta,
    ],
    [
        'id' => 'media',
        'module' => 'Media',
        'primary' => $mediaCount . ' files',
        'secondary' => 'Library size: ' . $mediaTotalSize . ' bytes',
        'status' => $mediaStatus,
        'statusLabel' => dashboard_status_label($mediaStatus),
        'trend' => $mediaTrend,
        'cta' => $mediaCta,
    ],
    [
        'id' => 'blogs',
        'module' => 'Blogs',
        'primary' => $postsTotal . ' posts',
        'secondary' => 'Published: ' . $postsByStatus['published'] . ' • Draft: ' . $postsByStatus['draft'] . ' • Scheduled: ' . $postsByStatus['scheduled'],
        'status' => $blogsStatus,
        'statusLabel' => dashboard_status_label($blogsStatus),
        'trend' => $blogsTrend,
        'cta' => $blogsCta,
    ],
    [
        'id' => 'events',
        'module' => 'Events',
        'primary' => $eventsTotal . ' events',
        'secondary' => $eventsSecondary,
        'status' => $eventsStatus,
        'statusLabel' => dashboard_status_label($eventsStatus),
        'trend' => $eventsTrend,
        'cta' => $eventsCta,
    ],
    [
        'id' => 'forms',
        'module' => 'Forms',
        'primary' => $formsCount . ' forms',
        'secondary' => 'Fields configured: ' . $formsFields,
        'status' => $formsStatus,
        'statusLabel' => dashboard_status_label($formsStatus),
        'trend' => $formsTrend,
        'cta' => $formsCta,
    ],
    [
        'id' => 'menus',
        'module' => 'Menus',
        'primary' => $menusCount . ' menus',
        'secondary' => 'Navigation items: ' . $menuItems,
        'status' => $menusStatus,
        'statusLabel' => dashboard_status_label($menusStatus),
        'trend' => $menusTrend,
        'cta' => $menusCta,
    ],
    [
        'id' => 'users',
        'module' => 'Users',
        'primary' => $usersCount . ' users',
        'secondary' => 'Admins: ' . $adminCount . ' • Editors: ' . $editorCount,
        'status' => $usersStatus,
        'statusLabel' => dashboard_status_label($usersStatus),
        'trend' => $usersTrend,
        'cta' => $usersCta,
    ],
    [
        'id' => 'analytics',
        'module' => 'Analytics',
        'primary' => $analyticsSummary['totalViews'] . ' total views',
        'secondary' => $analyticsSummary['topPage'] ? 'Top page: ' . $analyticsSummary['topPage'] . ' (' . $analyticsSummary['topViews'] . ')' : 'No views recorded yet',
        'status' => $analyticsStatus,
        'statusLabel' => dashboard_status_label($analyticsStatus),
        'trend' => $analyticsTrend,
        'cta' => $analyticsCta,
    ],
    [
        'id' => 'accessibility',
        'module' => 'Accessibility',
        'primary' => $accessibilitySummary['accessible'] . ' compliant pages',
        'secondary' => 'Alt text issues: ' . $accessibilitySummary['missing_alt'],
        'status' => $accessibilityStatus,
        'statusLabel' => dashboard_status_label($accessibilityStatus),
        'trend' => $accessibilityTrend,
        'cta' => $accessibilityCta,
    ],
    [
        'id' => 'logs',
        'module' => 'Logs',
        'primary' => $logEntries . ' history entries',
        'secondary' => $logsLastActivity ? 'Last activity: ' . $logsLastActivity : 'No activity recorded yet',
        'status' => $logsStatus,
        'statusLabel' => dashboard_status_label($logsStatus),
        'trend' => $logsTrend,
        'cta' => $logsCta,
    ],
    [
        'id' => 'search',
        'module' => 'Search',
        'primary' => $searchIndexCount . ' indexed records',
        'secondary' => 'Pages: ' . $searchBreakdown['pages'] . ' • Posts: ' . $searchBreakdown['posts'] . ' • Media: ' . $searchBreakdown['media'],
        'status' => $searchStatus,
        'statusLabel' => dashboard_status_label($searchStatus),
        'trend' => $searchTrend,
        'cta' => $searchCta,
    ],
    [
        'id' => 'settings',
        'module' => 'Settings',
        'primary' => $settingsCount . ' configuration values',
        'secondary' => 'Social profiles: ' . $socialCount,
        'status' => $settingsStatus,
        'statusLabel' => dashboard_status_label($settingsStatus),
        'trend' => $settingsTrend,
        'cta' => $settingsCta,
    ],
    [
        'id' => 'seo',
        'module' => 'SEO',
        'primary' => $seoSummary['optimised'] . ' pages optimised',
        'secondary' => 'Meta issues: ' . (int)$seoSummary['issues'] . ' • Duplicate slugs: ' . (int)$seoSummary['duplicate_slugs'],
        'status' => $seoStatus,
        'statusLabel' => dashboard_status_label($seoStatus),
        'trend' => $seoTrend,
        'cta' => $seoCta,
    ],
    [
        'id' => 'sitemap',
        'module' => 'Sitemap',
        'primary' => $sitemapEntries . ' published URLs',
        'secondary' => 'Ready for export to sitemap.xml',
        'status' => $sitemapStatus,
        'statusLabel' => dashboard_status_label($sitemapStatus),
        'trend' => $sitemapTrend,
        'cta' => $sitemapCta,
    ],
    [
        'id' => 'speed',
        'module' => 'Speed',
        'primary' => 'Fast: ' . $speedSummary['fast'] . ' • Monitor: ' . $speedSummary['monitor'] . ' • Slow: ' . $speedSummary['slow'],
        'secondary' => $largestPage['title'] ? 'Heaviest content: ' . $largestPage['title'] : 'Content analysis based on page length',
        'status' => $speedStatus,
        'statusLabel' => dashboard_status_label($speedStatus),
        'trend' => $speedTrend,
        'cta' => $speedCta,
    ],
];

$statusPriority = [
    'urgent' => 0,
    'warning' => 1,
    'ok' => 2,
];

usort($moduleSummaries, function (array $a, array $b) use ($statusPriority): int {
    $statusA = strtolower((string)($a['status'] ?? 'ok'));
    $statusB = strtolower((string)($b['status'] ?? 'ok'));
    $priorityA = $statusPriority[$statusA] ?? $statusPriority['ok'];
    $priorityB = $statusPriority[$statusB] ?? $statusPriority['ok'];

    if ($priorityA === $priorityB) {
        return strcasecmp((string)($a['module'] ?? ''), (string)($b['module'] ?? ''));
    }

    return $priorityA <=> $priorityB;
});

$generatedAt = $cacheGeneratedAt ?? gmdate(DATE_ATOM);

$data = [
    'pages' => $totalPages,
    'pagesPublished' => $pagesPublished,
    'pagesDraft' => $pagesDraft,
    'media' => count($media),
    'mediaSize' => $mediaTotalSize,
    'users' => count($users),
    'usersAdmins' => $usersByRole['admin'] ?? 0,
    'usersEditors' => $usersByRole['editor'] ?? 0,
    'views' => $views,
    'analyticsAvgViews' => $analyticsSummary['averageViews'],
    'analyticsTopPage' => $analyticsSummary['topPage'],
    'analyticsTopViews' => $analyticsSummary['topViews'],
    'blogsTotal' => count($posts),
    'blogsPublished' => $postsByStatus['published'],
    'blogsDraft' => $postsByStatus['draft'],
    'blogsScheduled' => $postsByStatus['scheduled'],
    'eventsTotal' => $eventsTotal,
    'eventsPublished' => $eventsPublished,
    'eventsUpcoming' => $eventsUpcoming,
    'eventsTicketsSold' => $eventsTicketsSold,
    'eventsRevenue' => $eventsRevenue,
    'eventsPendingOrders' => $eventsPendingOrders,
    'formsTotal' => count($forms),
    'formsFields' => $formsFields,
    'menusCount' => count($menus),
    'menuItems' => $menuItems,
    'accessibilityScore' => $accessibilityScore,
    'accessibilityCompliant' => $accessibilitySummary['accessible'],
    'accessibilityNeedsReview' => $accessibilitySummary['needs_review'],
    'accessibilityMissingAlt' => $accessibilitySummary['missing_alt'],
    'openAlerts' => $accessibilitySummary['needs_review'],
    'alertsAccessibility' => $accessibilitySummary['needs_review'],
    'logsEntries' => $logEntries,
    'logsLastActivity' => $logsLastActivity,
    'searchIndex' => $searchIndexCount,
    'searchBreakdown' => $searchBreakdown,
    'settingsCount' => $settingsCount,
    'settingsSocialLinks' => $socialCount,
    'sitemapEntries' => $sitemapEntries,
    'speedFast' => $speedSummary['fast'],
    'speedMonitor' => $speedSummary['monitor'],
    'speedSlow' => $speedSummary['slow'],
    'speedHeaviestPage' => $largestPage['title'],
    'speedHeaviestPageLength' => $largestPage['length'],
    'eventsCurrency' => $eventsCurrency,
    'seoOptimised' => $seoSummary['optimised'],
    'seoMissingTitle' => $seoSummary['missing_title'],
    'seoMissingDescription' => $seoSummary['missing_description'],
    'seoDescriptionLengthIssues' => $seoSummary['description_length'],
    'seoDuplicateSlugs' => $seoSummary['duplicate_slugs'],
    'seoIssues' => $seoSummary['issues'],
    'moduleSummaries' => $moduleSummaries,
    'generatedAt' => $generatedAt,
];

header('Content-Type: application/json');
echo json_encode($data);
