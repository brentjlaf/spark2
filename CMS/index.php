<?php
// File: index.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/page_schedule.php';
// Load pages from JSON
$pagesFile = __DIR__ . '/data/pages.json';
$pages = get_cached_json($pagesFile);

$settings = get_site_settings();

$menusFile = __DIR__ . '/data/menus.json';
$menus = get_cached_json($menusFile);

$blogFile = __DIR__ . '/data/blog_posts.json';
$blogPosts = get_cached_json($blogFile);
$blogPost = null;
$mediaFile = __DIR__ . '/data/media.json';
$mediaItems = get_cached_json($mediaFile);

$logged_in = is_logged_in();

// Base paths used by theme templates
$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -4) === '/CMS') {
    $scriptBase = substr($scriptBase, 0, -4);
}
$scriptBase = rtrim($scriptBase, '/');

function sparkcms_parse_blog_limit($value) {
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $limit ?: 6;
}

function sparkcms_normalize_blog_category($value) {
    return strtolower(trim((string) $value));
}

function sparkcms_is_blog_post_live($post) {
    if (!is_array($post)) {
        return false;
    }
    $status = strtolower((string) ($post['status'] ?? ''));
    if ($status === 'published') {
        return true;
    }
    if ($status === 'scheduled') {
        $publishDate = $post['publishDate'] ?? '';
        if ($publishDate === '') {
            return false;
        }
        $timestamp = strtotime($publishDate);
        if ($timestamp === false) {
            return false;
        }
        return $timestamp <= time();
    }
    return false;
}

function sparkcms_format_blog_date($value) {
    if (empty($value)) {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }
    return date('M j, Y', $timestamp);
}

function sparkcms_resolve_blog_detail_url($scriptBase, $prefix, $slug) {
    if (empty($slug)) {
        return '#';
    }
    $detail = trim((string) $prefix);
    if ($detail === '') {
        $detail = '/blogs';
    }
    if (preg_match('#^https?://#i', $detail)) {
        return rtrim($detail, '/') . '/' . rawurlencode($slug);
    }
    $detail = trim($detail, '/');
    $base = trim((string) $scriptBase);
    $path = $detail !== '' ? $detail . '/' . rawurlencode($slug) : rawurlencode($slug);
    if ($base === '' || $base === '/') {
        return '/' . $path;
    }
    return rtrim($base, '/') . '/' . $path;
}

function sparkcms_add_class(DOMElement $element, $class) {
    $classes = preg_split('/\s+/', trim($element->getAttribute('class')));
    $classes = array_filter($classes, 'strlen');
    if (!in_array($class, $classes, true)) {
        $classes[] = $class;
    }
    if ($classes) {
        $element->setAttribute('class', implode(' ', $classes));
    }
}

function sparkcms_remove_class(DOMElement $element, $class) {
    $classes = preg_split('/\s+/', trim($element->getAttribute('class')));
    $classes = array_filter($classes, function ($item) use ($class) {
        return $item !== '' && $item !== $class;
    });
    if ($classes) {
        $element->setAttribute('class', implode(' ', $classes));
    } else {
        $element->removeAttribute('class');
    }
}

function sparkcms_create_blog_article(DOMDocument $dom, array $post, $detailUrl, $metaEnabled, $excerptEnabled) {
    $article = $dom->createElement('article');
    $article->setAttribute('class', 'blog-item');

    $titleEl = $dom->createElement('h3');
    $titleEl->setAttribute('class', 'blog-title');
    $linkEl = $dom->createElement('a');
    $linkEl->setAttribute('href', $detailUrl);
    $titleText = $post['title'] ?? 'Untitled Post';
    $linkEl->appendChild($dom->createTextNode($titleText));
    $titleEl->appendChild($linkEl);
    $article->appendChild($titleEl);

    if ($metaEnabled) {
        $parts = [];
        if (!empty($post['author'])) {
            $parts[] = $post['author'];
        }
        $dateValue = sparkcms_format_blog_date($post['publishDate'] ?? $post['createdAt'] ?? '');
        if ($dateValue !== '') {
            $parts[] = $dateValue;
        }
        if ($parts) {
            $metaEl = $dom->createElement('div');
            $metaEl->setAttribute('class', 'blog-meta');
            foreach ($parts as $value) {
                $span = $dom->createElement('span');
                $span->appendChild($dom->createTextNode($value));
                $metaEl->appendChild($span);
            }
            $article->appendChild($metaEl);
        }
    }

    if ($excerptEnabled && !empty($post['excerpt'])) {
        $excerptEl = $dom->createElement('p');
        $excerptEl->setAttribute('class', 'blog-excerpt');
        $excerptEl->appendChild($dom->createTextNode(strip_tags($post['excerpt'])));
        $article->appendChild($excerptEl);
    }

    $readMoreEl = $dom->createElement('a');
    $readMoreEl->setAttribute('class', 'blog-read-more');
    $readMoreEl->setAttribute('href', $detailUrl);
    $readMoreEl->appendChild($dom->createTextNode('Read more '));
    $arrow = $dom->createElement('span');
    $arrow->setAttribute('aria-hidden', 'true');
    $arrow->appendChild($dom->createTextNode('→'));
    $readMoreEl->appendChild($arrow);
    $article->appendChild($readMoreEl);

    return $article;
}

function sparkcms_hydrate_blog_lists($html, $scriptBase) {
    global $blogPosts;
    if (strpos($html, 'data-blog-list') === false || !class_exists('DOMDocument')) {
        return $html;
    }
    $published = array_values(array_filter($blogPosts, function ($post) {
        return sparkcms_is_blog_post_live($post);
    }));

    $libxmlState = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $loaded = $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($libxmlState);
    if (!$loaded) {
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $containers = $xpath->query('//*[@data-blog-list]');
    if (!$containers->length) {
        return $html;
    }

    foreach ($containers as $container) {
        if (!$container instanceof DOMElement) {
            continue;
        }

        $limit = sparkcms_parse_blog_limit($container->getAttribute('data-limit'));
        $category = sparkcms_normalize_blog_category($container->getAttribute('data-category'));
        $showExcerpt = strtolower((string) $container->getAttribute('data-show-excerpt'));
        $showMeta = strtolower((string) $container->getAttribute('data-show-meta'));
        $emptyMessage = trim($container->getAttribute('data-empty')) ?: 'No posts available.';
        $detailBase = $container->getAttribute('data-base');

        $itemsHostNodeList = $xpath->query('.//*[@data-blog-items]', $container);
        $itemsHost = $itemsHostNodeList->length ? $itemsHostNodeList->item(0) : $container;
        if (!$itemsHost instanceof DOMElement) {
            continue;
        }

        while ($itemsHost->firstChild) {
            $itemsHost->removeChild($itemsHost->firstChild);
        }

        $filtered = array_filter($published, function ($post) use ($category) {
            if ($category === '') {
                return true;
            }
            return sparkcms_normalize_blog_category($post['category'] ?? '') === $category;
        });

        usort($filtered, function ($a, $b) {
            $aDate = $a['publishDate'] ?? $a['createdAt'] ?? '';
            $bDate = $b['publishDate'] ?? $b['createdAt'] ?? '';
            $aTime = strtotime($aDate) ?: 0;
            $bTime = strtotime($bDate) ?: 0;
            return $bTime <=> $aTime;
        });

        if ($limit && count($filtered) > $limit) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        $emptyNodeList = $xpath->query('.//*[@data-blog-empty]', $container);
        $emptyNode = $emptyNodeList->length ? $emptyNodeList->item(0) : null;

        $excerptEnabled = !in_array($showExcerpt, ['no', 'false', '0'], true);
        $metaEnabled = !in_array($showMeta, ['no', 'false', '0'], true);

        if (!$filtered) {
            if ($emptyNode instanceof DOMElement) {
                while ($emptyNode->firstChild) {
                    $emptyNode->removeChild($emptyNode->firstChild);
                }
                $emptyNode->appendChild($dom->createTextNode($emptyMessage));
                sparkcms_remove_class($emptyNode, 'd-none');
            } else {
                $notice = $dom->createElement('div');
                $notice->setAttribute('class', 'blog-item blog-item--placeholder');
                $notice->appendChild($dom->createTextNode($emptyMessage));
                $itemsHost->appendChild($notice);
            }
            $container->setAttribute('data-blog-rendered', 'server');
            continue;
        }

        if ($emptyNode instanceof DOMElement) {
            sparkcms_add_class($emptyNode, 'd-none');
        }

        foreach ($filtered as $post) {
            $detailUrl = sparkcms_resolve_blog_detail_url($scriptBase, $detailBase, $post['slug'] ?? '');
            $article = sparkcms_create_blog_article($dom, $post, $detailUrl, $metaEnabled, $excerptEnabled);
            $itemsHost->appendChild($article);
        }

        $container->setAttribute('data-blog-rendered', 'server');
    }

    return $dom->saveHTML();
}

function sparkcms_build_placeholder_content($scriptBase, $siteName)
{
    $escapedSite = htmlspecialchars($siteName ?: 'Your Site', ENT_QUOTES, 'UTF-8');
    $homeUrl = ($scriptBase === '' || $scriptBase === '/') ? '/' : rtrim($scriptBase, '/') . '/';
    $contactUrl = rtrim($homeUrl, '/') . '/contact-us';

    return <<<HTML
<section class="placeholder-hero text-center py-5 bg-light">
    <div class="container">
        <p class="text-primary text-uppercase fw-semibold mb-2">Content coming soon</p>
        <h1 class="display-5 fw-bold mb-3">We're building this page</h1>
        <p class="lead text-muted mb-4">Add blocks in the CMS to replace this placeholder with tailored content for {$escapedSite}.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a class="btn btn-primary" href="{$homeUrl}">Return home</a>
            <a class="btn btn-outline-secondary" href="{$contactUrl}">Contact us</a>
        </div>
    </div>
</section>
<section class="placeholder-grid py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <span class="badge bg-primary-subtle text-primary mb-2">Highlights</span>
                        <h3 class="h5">Share your story</h3>
                        <p class="text-muted mb-0">Introduce visitors to what makes {$escapedSite} special with a welcome message and eye-catching imagery.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <span class="badge bg-primary-subtle text-primary mb-2">Guidance</span>
                        <h3 class="h5">Outline key actions</h3>
                        <p class="text-muted mb-0">Use calls to action so people can learn more, get support, or explore your services.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <span class="badge bg-primary-subtle text-primary mb-2">Next steps</span>
                        <h3 class="h5">Add your own blocks</h3>
                        <p class="text-muted mb-0">Drag in text, images, and forms from the block palette to replace this placeholder layout.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;
}

function render_theme_page($templateFile, $page, $scriptBase) {
    global $settings, $menus, $logged_in, $blogPosts, $blogPost;
    $themeBase = $scriptBase . '/theme';
    ob_start();
    include $templateFile;
    $html = ob_get_clean();
    $content = trim($page['content'] ?? '');
    if ($content === '') {
        $page['content'] = sparkcms_build_placeholder_content($scriptBase, $settings['site_name'] ?? '');
    }
    $html = preg_replace('/<div class="drop-area"><\/div>/', $page['content'], $html);
    if (!$logged_in) {
        $html = preg_replace('#<templateSetting[^>]*>.*?</templateSetting>#si', '', $html);
        $html = preg_replace('#<div class="block-controls"[^>]*>.*?</div>#si', '', $html);
        $html = str_replace('draggable="true"', '', $html);
        $html = preg_replace('#\sdata-ts="[^"]*"#i', '', $html);
        $html = preg_replace('#\sdata-(?:blockid|template|original|active|custom_[A-Za-z0-9_-]+)="[^"]*"#i', '', $html);
    }
    if (!$logged_in) {
        $html = sparkcms_hydrate_blog_lists($html, $scriptBase);
    }
    echo $html;
}

// Determine page slug
$slug = isset($_GET['page']) ? sanitize_text($_GET['page']) : ($settings['homepage'] ?? 'home');

$preview_mode = isset($_GET['preview']) && $logged_in;

if ($slug === 'search') {
    $q = isset($_GET['q']) ? sanitize_text($_GET['q']) : '';
    $results = [];
    $lower = strtolower($q);
    foreach ($pages as $p) {
        $scheduleInfo = sparkcms_page_schedule_info($p);
        if (!$logged_in && (!$scheduleInfo['is_live'] || ($p['access'] ?? 'public') !== 'public')) {
            continue;
        }
        if ($q === '' || stripos($p['title'], $lower) !== false || stripos($p['slug'], $lower) !== false || stripos($p['content'], $lower) !== false) {
            $results[] = $p;
        }
    }
    foreach ($blogPosts as $b) {
        if ($q === '' || stripos($b['title'], $lower) !== false || stripos($b['slug'], $lower) !== false || stripos($b['excerpt'], $lower) !== false || stripos($b['content'], $lower) !== false || stripos($b['tags'], $lower) !== false) {
            $results[] = ['title' => $b['title'], 'slug' => 'blogs/' . ltrim($b['slug'], '/')];
        }
    }
    foreach ($mediaItems as $m) {
        $tags = isset($m['tags']) && is_array($m['tags']) ? implode(',', $m['tags']) : '';
        if ($q === '' || stripos($m['name'], $lower) !== false || stripos($m['file'], $lower) !== false || stripos($tags, $lower) !== false) {
            $results[] = ['title' => $m['name'], 'slug' => ltrim($m['file'], '/')];
        }
    }
    $content = '<div class="search-results"><h1>Search Results';
    if ($q !== '') { $content .= ' for &quot;' . htmlspecialchars($q) . '&quot;'; }
    $content .= '</h1>';
    if ($results) {
        $content .= '<ul>';
        foreach ($results as $r) {
            $content .= '<li><a href="' . htmlspecialchars($scriptBase . '/' . $r['slug']) . '">' . htmlspecialchars($r['title']) . '</a></li>';
        }
        $content .= '</ul>';
    } else {
        $content .= '<p>No results found</p>';
    }
    $content .= '</div>';
    $page = ['title' => 'Search', 'content' => $content];
    $templateFile = realpath(__DIR__ . '/../theme/templates/pages/search.php');
    if ($templateFile && file_exists($templateFile)) {
        render_theme_page($templateFile, $page, $scriptBase);
    } else {
        echo $content;
    }
    exit;
}

$pageIndex = null;
$page = null;
$pageSource = 'pages';

if ($slug === 'blog') {
    $slug = 'blogs';
}

if (preg_match('#^(blog|blogs)/(.*)$#', $slug, $matches)) {
    $detailSegment = trim($matches[2]);
    if ($detailSegment !== '') {
        $detailSlug = rawurldecode($detailSegment);
        foreach ($blogPosts as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (($candidate['slug'] ?? '') !== $detailSlug) {
                continue;
            }
            if ($logged_in || sparkcms_is_blog_post_live($candidate)) {
                $blogPost = $candidate;
            }
            break;
        }
        if ($blogPost) {
            $pageSource = 'blog-detail';
            $slug = $matches[1];
            $detailTitle = $blogPost['title'] ?? 'Blog Post';
            $detailExcerpt = trim(strip_tags($blogPost['excerpt'] ?? ''));
            if ($detailExcerpt === '') {
                $detailExcerpt = trim(strip_tags($blogPost['content'] ?? ''));
            }
            if ($detailExcerpt !== '') {
                if (function_exists('mb_substr')) {
                    $detailExcerpt = mb_substr($detailExcerpt, 0, 160);
                } else {
                    $detailExcerpt = substr($detailExcerpt, 0, 160);
                }
            }
            $detailUrl = sparkcms_resolve_blog_detail_url($scriptBase, '/blogs', $blogPost['slug'] ?? '');
            $page = [
                'id' => 'blog-detail-' . ($blogPost['id'] ?? $detailSlug),
                'title' => $detailTitle,
                'content' => '',
                'template' => 'blog-detail.php',
                'meta_title' => $detailTitle,
                'meta_description' => $detailExcerpt,
                'og_title' => $detailTitle,
                'og_description' => $detailExcerpt,
                'og_image' => $blogPost['featured_image'] ?? ($blogPost['featuredImage'] ?? ''),
                'canonical_url' => $detailUrl,
                'access' => 'public',
                'robots' => 'index,follow'
            ];
        }
    } else {
        $slug = $matches[1];
    }
}

if ($pageSource === 'pages') {
    foreach ($pages as $i => $p) {
        if ($p['slug'] === $slug) {
            $pageIndex = $i;
            $page = $p;
            break;
        }
    }
}

if (!$page) {
    http_response_code(404);
    $page = [
        'title' => 'Page Not Found',
        'content' =>
            '<h1>Page Not Found</h1>' .
            '<p>The page you are looking for might have been moved or deleted.</p>' .
            '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
            ' or use the site search to find what you are looking for.</p>'
    ];
    $templateFile = realpath(__DIR__ . '/../theme/templates/pages/errors/404.php');
    if ($templateFile && file_exists($templateFile)) {
        render_theme_page($templateFile, $page, $scriptBase);
    } else {
        echo $page['content'];
    }
    exit;
}

if ($pageSource === 'pages') {
    $pageScheduleInfo = sparkcms_page_schedule_info($page);
    if (!$logged_in && !$pageScheduleInfo['is_live']) {
        http_response_code(404);
        $page = [
            'title' => 'Page Not Found',
            'content' =>
                '<h1>Page Not Found</h1>' .
                '<p>The page you are looking for might have been moved or deleted.</p>' .
                '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
                ' or use the site search to find what you are looking for.</p>'
        ];
        $templateFile = realpath(__DIR__ . '/../theme/templates/pages/errors/404.php');
        if ($templateFile && file_exists($templateFile)) {
            render_theme_page($templateFile, $page, $scriptBase);
        } else {
            echo $page['content'];
        }
        exit;
    }
}

if ($page) {
    $defaultRobotsDirective = sparkcms_default_robots_directive();
    $pageRobotsDirective = sanitize_robots_directive($page['robots'] ?? $defaultRobotsDirective);
    $page['robots'] = $pageRobotsDirective;
    if ($pageIndex !== null) {
        $pages[$pageIndex]['robots'] = $pageRobotsDirective;
    }
}

if (($page['access'] ?? 'public') !== 'public' && !$logged_in) {
    http_response_code(403);
    $page = [
        'title' => 'Restricted',
        'content' =>
            '<h1>Restricted</h1>' .
            '<p>You do not have permission to view this page.</p>' .
            '<p><a href="' . htmlspecialchars($scriptBase) . '/">Return to homepage</a>' .
            ' or <a href="' . htmlspecialchars($scriptBase) . '/CMS/login.php">log in</a> ' .
            'with an account that has access.</p>'
    ];
    $templateFile = realpath(__DIR__ . '/../theme/templates/pages/errors/403.php');
    if ($templateFile && file_exists($templateFile)) {
        render_theme_page($templateFile, $page, $scriptBase);
    } else {
        echo $page['content'];
    }
    exit;
}

// Increment views and persist
if ($pageIndex !== null) {
    $pages[$pageIndex]['views'] = ($pages[$pageIndex]['views'] ?? 0) + 1;
    $page = $pages[$pageIndex];
    write_json_file($pagesFile, $pages);
}

// If logged in show the page builder instead of the static page
if ($pageSource === 'pages' && $logged_in && !$preview_mode) {
    $_GET['id'] = $page['id'];
    require __DIR__ . '/../liveed/builder.php';
    return;
}

if ($preview_mode) {
    $logged_in = false;
}

$templateFile = null;
if (!empty($page['template'])) {
    $candidate = realpath(__DIR__ . '/../theme/templates/pages/' . $page['template']);
    if ($candidate && file_exists($candidate)) {
        $templateFile = $candidate;
    }
}
if ($templateFile && (!$logged_in || $preview_mode)) {
    render_theme_page($templateFile, $page, $scriptBase);
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<title><?php echo htmlspecialchars(($settings['site_name'] ?? 'SparkCMS') . ' - ' . $page['title']); ?></title>
<?php if (!empty($page['meta_title'])): ?>
    <meta name="title" content="<?php echo htmlspecialchars($page['meta_title']); ?>">
<?php endif; ?>
<?php if (!empty($page['meta_description'])): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page['meta_description']); ?>">
<?php endif; ?>
<?php if (!empty($page['canonical_url'])): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($page['canonical_url']); ?>">
<?php endif; ?>
<?php if (!empty($page['robots']) && $page['robots'] !== sparkcms_default_robots_directive()): ?>
    <meta name="robots" content="<?php echo htmlspecialchars($page['robots']); ?>">
<?php endif; ?>
<?php if (!empty($page['og_title'])): ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($page['og_title']); ?>">
<?php endif; ?>
<?php if (!empty($page['og_description'])): ?>
    <meta property="og:description" content="<?php echo htmlspecialchars($page['og_description']); ?>">
<?php endif; ?>
<?php if (!empty($page['og_image'])): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($page['og_image']); ?>">
<?php endif; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<?php if ($logged_in): ?>
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/root.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/skin.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/override.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/spark-cms.css">
<?php else: ?>
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/root.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/skin.css">
<link rel="stylesheet" href="<?php echo $scriptBase; ?>/theme/css/override.css">
<?php endif; ?>
</head>
<body>
<?php if ($logged_in): ?>
<?php else: ?>
    <div class="header">
        <div class="logo">
        <?php if (!empty($settings['logo'])): ?>
            <img src="<?php echo htmlspecialchars($scriptBase . '/CMS/' . $settings['logo']); ?>" alt="Logo" style="height:40px;">
        <?php else: ?>
            <?php echo htmlspecialchars($settings['site_name'] ?? 'SparkCMS'); ?>
        <?php endif; ?>
        </div>
        <div>
            <a class="btn btn-primary" href="<?php echo $scriptBase; ?>/CMS/admin.php">
                <i class="fa-solid fa-gauge-high btn-icon" aria-hidden="true"></i>
                <span class="btn-label">Admin</span>
            </a>
        </div>
    </div>
    <div class="content">
        <?php echo $page['content']; ?>
    </div>
    <footer class="footer">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['site_name'] ?? 'SparkCMS'); ?>
    </footer>
<?php endif; ?>
<?php if ($logged_in): ?>
<script src="<?php echo $scriptBase; ?>/theme/js/combined.js"></script>
<?php else: ?>
<script src="<?php echo $scriptBase; ?>/theme/js/combined.js"></script>
<?php endif; ?>
</body>
</html>
