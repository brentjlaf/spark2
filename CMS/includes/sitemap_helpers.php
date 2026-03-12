<?php
// File: sitemap_helpers.php
// Shared helpers for generating sitemap.xml from published pages and blog posts.
// Used by generate.php (manual trigger) and auto-triggered on page/post saves.

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/page_schedule.php';

/**
 * Derive the site base URL from the current request context.
 * Returns an empty string in CLI mode (no HTTP context available).
 */
function _sparkcms_sitemap_base_url(): string
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return '';
    }
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'];
    $base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    // Strip /CMS suffix so the base URL points to the site root, not the admin.
    if (substr($base, -4) === '/CMS') {
        $base = substr($base, 0, -4);
    }
    return $scheme . '://' . $host . rtrim($base, '/');
}

/**
 * Build the list of sitemap URL entries from published pages and blog posts.
 *
 * @param string $cmsRoot Absolute path to the CMS/ directory.
 * @param string $baseUrl Base site URL (auto-detected from request context if empty).
 * @return array  Each element contains 'url', 'lastmod' (Y-m-d), 'lastmodHuman', 'title', 'slug', 'type'.
 */
function sparkcms_get_sitemap_entries(string $cmsRoot, string $baseUrl = ''): array
{
    if ($baseUrl === '') {
        $baseUrl = _sparkcms_sitemap_base_url();
    }
    $cmsRoot = rtrim($cmsRoot, '/\\');
    $entries = [];

    // Published pages
    $pages = read_json_file($cmsRoot . '/data/pages.json');
    if (is_array($pages)) {
        foreach ($pages as $page) {
            $info = sparkcms_page_schedule_info($page);
            if (!$info['is_live']) {
                continue;
            }
            $slug      = ltrim((string)($page['slug'] ?? ''), '/');
            $lastMod   = isset($page['last_modified']) ? (int)$page['last_modified'] : time();
            $entries[] = [
                'type'         => 'page',
                'title'        => (string)($page['title'] ?? ''),
                'slug'         => $slug,
                'url'          => $baseUrl . '/' . $slug,
                'lastmod'      => date('Y-m-d', $lastMod),
                'lastmodHuman' => date('F j, Y', $lastMod),
            ];
        }
    }

    // Published blog posts
    $posts = read_json_file($cmsRoot . '/data/blog_posts.json');
    if (is_array($posts)) {
        foreach ($posts as $post) {
            if (($post['status'] ?? '') !== 'published') {
                continue;
            }
            $slug = ltrim((string)($post['slug'] ?? ''), '/');
            if ($slug === '') {
                continue;
            }
            $lastMod = time();
            if (!empty($post['publishDate'])) {
                $ts = strtotime($post['publishDate']);
                if ($ts !== false) {
                    $lastMod = $ts;
                }
            }
            $entries[] = [
                'type'         => 'post',
                'title'        => (string)($post['title'] ?? ''),
                'slug'         => $slug,
                'url'          => $baseUrl . '/blog/' . $slug,
                'lastmod'      => date('Y-m-d', $lastMod),
                'lastmodHuman' => date('F j, Y', $lastMod),
            ];
        }
    }

    return $entries;
}

/**
 * Write a sitemap.xml file from the given entries array.
 * Uses DOMDocument when available, with a string-builder fallback.
 *
 * @param array  $entries      From sparkcms_get_sitemap_entries().
 * @param string $sitemapPath  Absolute filesystem path where sitemap.xml will be written.
 * @return bool  True on success, false on failure.
 */
function sparkcms_write_sitemap_xml(array $entries, string $sitemapPath): bool
{
    try {
        if (class_exists('DOMDocument')) {
            $dom    = new DOMDocument('1.0', 'UTF-8');
            $urlset = $dom->createElement('urlset');
            $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            foreach ($entries as $entry) {
                $url = $dom->createElement('url');
                $url->appendChild($dom->createElement('loc',     $entry['url']));
                $url->appendChild($dom->createElement('lastmod', $entry['lastmod']));
                $urlset->appendChild($url);
            }

            $dom->appendChild($urlset);
            $dom->formatOutput = true;

            return $dom->save($sitemapPath) !== false;
        }

        // String-builder fallback
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];
        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'     . htmlspecialchars($entry['url'],     ENT_XML1) . '</loc>';
            $lines[] = '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1) . '</lastmod>';
            $lines[] = '  </url>';
        }
        $lines[] = '</urlset>';

        return file_put_contents($sitemapPath, implode("\n", $lines)) !== false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Convenience function: build entries and write sitemap.xml in one call.
 *
 * @param string $cmsRoot Absolute path to the CMS/ directory.
 * @return bool  True on success, false on failure.
 */
function sparkcms_regenerate_sitemap(string $cmsRoot): bool
{
    $cmsRoot     = rtrim($cmsRoot, '/\\');
    $entries     = sparkcms_get_sitemap_entries($cmsRoot);
    $sitemapPath = dirname($cmsRoot) . '/sitemap.xml';
    return sparkcms_write_sitemap_xml($entries, $sitemapPath);
}
