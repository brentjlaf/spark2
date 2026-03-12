<?php
// File: search_helpers.php
require_once __DIR__ . '/data.php';

/**
 * Load and cache the unified search index for pages, blog posts, and media.
 *
 * @return array
 */
function get_search_index()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $pagesFile = __DIR__ . '/../data/pages.json';
    $postsFile = __DIR__ . '/../data/blog_posts.json';
    $mediaFile = __DIR__ . '/../data/media.json';

    $pages = get_cached_json($pagesFile);
    $posts = get_cached_json($postsFile);
    $media = get_cached_json($mediaFile);

    $cache = build_search_index($pages, $posts, $media);
    return $cache;
}

/**
 * Build the search index from the supplied datasets.
 *
 * @param array $pages
 * @param array $posts
 * @param array $media
 * @return array
 */
function build_search_index(array $pages, array $posts, array $media)
{
    $index = [];

    foreach ($pages as $page) {
        $index[] = build_page_entry($page);
    }

    foreach ($posts as $post) {
        $index[] = build_post_entry($post);
    }

    foreach ($media as $item) {
        $index[] = build_media_entry($item, $pages, $posts);
    }

    return $index;
}

/**
 * Return a list of suggestion candidates derived from the index.
 *
 * @param int $limit
 * @return array
 */
function get_search_suggestions($limit = 60)
{
    $index = get_search_index();
    $seen = [];
    $suggestions = [];

    foreach ($index as $entry) {
        $candidates = $entry['suggestions'];
        foreach ($candidates as $candidate) {
            $value = search_normalize_text($candidate['value']);
            if ($value === '') {
                continue;
            }
            $key = $value . '|' . search_normalize_text($candidate['type']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $suggestions[] = [
                'value' => $candidate['value'],
                'type' => $candidate['type'],
                'label' => $candidate['label'],
            ];
            if (count($suggestions) >= $limit) {
                break 2;
            }
        }
    }

    return $suggestions;
}

/**
 * Execute a search query against the index.
 *
 * @param string $query
 * @param array $filters
 * @return array
 */
function perform_search($query, array $filters = [])
{
    $query = trim((string) $query);
    $index = get_search_index();
    $indexCounts = get_index_type_counts($index);
    if ($query === '') {
        return [
            'results' => [],
            'counts' => $indexCounts,
        ];
    }

    $terms = extract_search_terms($query);
    if (!$terms) {
        return [
            'results' => [],
            'counts' => $indexCounts,
        ];
    }

    // Feature 17: Try MySQL FULLTEXT search first when a database is available.
    // Falls back transparently to the in-memory scorer below if the DB is
    // unreachable, the table doesn't exist yet, or no rows are matched.
    try {
        require_once __DIR__ . '/db.php';
        if (is_database_configured()) {
            require_once __DIR__ . '/search_index.php';
            $ftResult = sparkcms_fulltext_search(
                get_db_connection(),
                $query,
                ['limit' => 100, 'only_published' => false]
            );
            if (!empty($ftResult['results'])) {
                return sparkcms_merge_fulltext_results($ftResult['results'], $index, $query, $filters);
            }
        }
    } catch (Throwable $_ftEx) {
        // Fall through to the in-memory search.
    }

    $selectedTypes = [];
    if (!empty($filters['types']) && is_array($filters['types'])) {
        foreach ($filters['types'] as $type) {
            $type = search_normalize_text(trim($type));
            if ($type !== '') {
                $selectedTypes[] = $type;
            }
        }
    }

    $results = [];
    $typeCounts = ['Page' => 0, 'Post' => 0, 'Media' => 0];

    foreach ($index as $entry) {
        $score = score_entry_against_terms($entry, $terms);
        if ($score === null) {
            continue;
        }

        $snippet = build_result_snippet($entry['plain_text'], $terms, $entry['type']);
        $result = [
            'id' => $entry['id'],
            'type' => $entry['type'],
            'title' => $entry['title'],
            'slug' => $entry['slug'],
            'score' => $score,
            'snippet' => $snippet,
            'record' => $entry['record'],
        ];

        if (isset($typeCounts[$entry['type']])) {
            $typeCounts[$entry['type']]++;
        }

        $typeKey = search_normalize_text($entry['type']);
        if ($selectedTypes && !in_array($typeKey, $selectedTypes, true)) {
            continue;
        }

        $results[] = $result;
    }

    usort($results, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return strcasecmp($a['title'], $b['title']);
        }
        return $a['score'] <=> $b['score'];
    });

    return [
        'results' => $results,
        'counts' => $typeCounts,
    ];
}

/**
 * Convert FULLTEXT database rows into the standard perform_search() output format
 * by enriching each row with snippet and record data from the in-memory index.
 *
 * @param array  $ftRows   Rows returned by sparkcms_fulltext_search().
 * @param array  $index    In-memory search index from get_search_index().
 * @param string $query    Original user query (used to generate snippets).
 * @param array  $filters  Same filters array as perform_search().
 * @return array{results: array, counts: array}
 */
function sparkcms_merge_fulltext_results(array $ftRows, array $index, string $query, array $filters): array
{
    // Build a fast lookup: "type:id" (lowercase type) => in-memory entry.
    $lookup = [];
    foreach ($index as $entry) {
        $lookup[strtolower($entry['type']) . ':' . $entry['id']] = $entry;
    }

    $selectedTypes = [];
    if (!empty($filters['types']) && is_array($filters['types'])) {
        foreach ($filters['types'] as $t) {
            $v = search_normalize_text(trim((string)$t));
            if ($v !== '') {
                $selectedTypes[] = $v;
            }
        }
    }

    $terms      = extract_search_terms($query);
    $results    = [];
    $typeCounts = ['Page' => 0, 'Post' => 0, 'Media' => 0];

    foreach ($ftRows as $row) {
        $key   = strtolower((string)($row['type'] ?? '')) . ':' . (int)($row['record_id'] ?? 0);
        $entry = $lookup[$key] ?? null;
        if ($entry === null) {
            // Record exists in the DB index but not in the JSON data (stale row); skip.
            continue;
        }

        $typeKey = $entry['type']; // 'Page', 'Post', or 'Media'
        if (isset($typeCounts[$typeKey])) {
            $typeCounts[$typeKey]++;
        }

        if ($selectedTypes && !in_array(search_normalize_text($typeKey), $selectedTypes, true)) {
            continue;
        }

        $results[] = [
            'id'      => $entry['id'],
            'type'    => $entry['type'],
            'title'   => $entry['title'],
            'slug'    => $entry['slug'],
            'score'   => isset($row['score']) ? (float)$row['score'] : 0.0,
            'snippet' => build_result_snippet($entry['plain_text'], $terms, $entry['type']),
            'record'  => $entry['record'],
        ];
    }

    return ['results' => $results, 'counts' => $typeCounts];
}

/**
 * Extract search terms supporting quoted phrases.
 *
 * @param string $query
 * @return array
 */
function extract_search_terms($query)
{
    $query = search_normalize_text($query);
    preg_match_all('/"([^"]+)"|\'([^\']+)\'|(\S+)/u', $query, $matches);
    $stopWords = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'in',
        'is', 'it', 'of', 'on', 'or', 'that', 'the', 'to', 'with',
    ];
    $terms = [];
    foreach ($matches[0] as $i => $match) {
        $term = $matches[1][$i] ?? $matches[2][$i] ?? $matches[3][$i] ?? '';
        $term = trim($term);
        $term = trim(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $term));
        if ($term !== '' && !in_array($term, $stopWords, true)) {
            $terms[] = $term;
        }
    }
    return array_values(array_unique($terms));
}

/**
 * Score an index entry against the supplied search terms.
 *
 * @param array $entry
 * @param array $terms
 * @return float|null
 */
function score_entry_against_terms(array $entry, array $terms)
{
    $score = 0.0;
    $matched = 0;
    foreach ($terms as $term) {
        $termScore = match_term_against_entry($entry, $term);
        if ($termScore === null) {
            continue;
        }
        $matched++;
        $score += $termScore;
    }
    if ($matched === 0) {
        return null;
    }

    $missing = count($terms) - $matched;
    $coveragePenalty = $missing * 2.5;
    $averageScore = $score / $matched;
    return $averageScore + $coveragePenalty;
}

/**
 * Match an individual term against the entry fields.
 *
 * @param array $entry
 * @param string $term
 * @return float|null
 */
function match_term_against_entry(array $entry, $term)
{
    $bestScore = null;
    $term = search_normalize_text($term);
    $termLength = strlen($term);
    if ($termLength === 0) {
        return null;
    }
    $isPhrase = strpos($term, ' ') !== false;
    $allowFuzzy = !$isPhrase && $termLength >= 3;
    $threshold = max(1, (int) ceil(strlen($term) * 0.4));

    foreach ($entry['fields'] as $field) {
        $text = $field['value'];
        if ($text === '') {
            continue;
        }

        $fieldWeight = (float) $field['weight'];

        if (strpos($text, $term) !== false) {
            $score = $fieldWeight;

            // Reward stronger signal when the field starts with the term.
            if (strpos($text, $term) === 0) {
                $score -= 0.35;
            }

            // Reward exact token matches (e.g. searching "hero" in title words).
            $fieldWords = extract_tokens($text);
            if (in_array($term, $fieldWords, true)) {
                $score -= 0.2;
            }

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
            }
            continue;
        }

        if (!$allowFuzzy) {
            continue;
        }

        // Calculate fuzzy match per-field to avoid over-ranking unrelated fields.
        $distance = minimum_levenshtein_distance($term, extract_tokens($text));
        if ($distance === null || $distance > $threshold) {
            continue;
        }

        $score = $fieldWeight + ($distance * 0.1);
        if ($bestScore === null || $score < $bestScore) {
            $bestScore = $score;
        }
    }

    return $bestScore;
}

/**
 * Extract normalized tokens from a field for ranking and fuzzy matching.
 *
 * @param string $value
 * @return array
 */
function extract_tokens($value)
{
    $value = search_normalize_text((string) $value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/[^\p{L}\p{N}]+/u', $value);
    if (!is_array($parts)) {
        return [];
    }

    $tokens = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $tokens[] = $part;
    }

    return array_values(array_unique($tokens));
}

/**
 * Calculate the minimum Levenshtein distance between a term and a list of words.
 *
 * @param string $term
 * @param array $words
 * @return int|null
 */
function minimum_levenshtein_distance($term, array $words)
{
    $termLength = strlen($term);
    if ($termLength === 0) {
        return null;
    }

    $closest = null;
    foreach ($words as $word) {
        if ($word === '') {
            continue;
        }
        if (abs(strlen($word) - $termLength) > max(3, (int) ceil($termLength * 0.6))) {
            continue;
        }
        $distance = levenshtein($term, $word);
        if ($closest === null || $distance < $closest) {
            $closest = $distance;
            if ($distance === 0) {
                break;
            }
        }
    }

    return $closest;
}

/**
 * Build a snippet of text around the matched terms.
 *
 * @param string $plainText
 * @param array $terms
 * @param string $type
 * @param int $length
 * @return string
 */
function build_result_snippet($plainText, array $terms, $type, $length = 180)
{
    $plainText = trim(preg_replace('/\s+/', ' ', $plainText));
    if ($plainText === '') {
        return '';
    }

    $lower = strtolower($plainText);
    $position = null;
    foreach ($terms as $term) {
        $pos = strpos($lower, $term);
        if ($pos !== false) {
            $position = $pos;
            break;
        }
    }

    if ($position === null) {
        $position = 0;
    }

    $start = max(0, $position - (int) ($length / 2));
    $snippet = substr($plainText, $start, $length);

    if ($start > 0) {
        $snippet = '…' . ltrim($snippet);
    }
    if ($start + $length < strlen($plainText)) {
        $snippet = rtrim($snippet) . '…';
    }

    foreach ($terms as $term) {
        $escaped = preg_quote($term, '/');
        $snippet = preg_replace('/(' . $escaped . ')/i', '<mark>$1</mark>', $snippet);
    }

    return $snippet;
}

/**
 * Build an index entry for a page record.
 *
 * @param array $page
 * @return array
 */
function build_page_entry(array $page)
{
    $content = is_string($page['content'] ?? '') ? $page['content'] : '';
    $plain = strip_tags($content);
    $metadata = implode(' ', array_filter([
        $page['meta_title'] ?? '',
        $page['meta_description'] ?? '',
        $page['og_title'] ?? '',
        $page['og_description'] ?? '',
        $page['canonical_url'] ?? '',
    ]));

    $fields = [
        ['name' => 'title', 'value' => search_normalize_text((string) ($page['title'] ?? '')), 'weight' => 1],
        ['name' => 'slug', 'value' => search_normalize_text((string) ($page['slug'] ?? '')), 'weight' => 1.5],
        ['name' => 'metadata', 'value' => search_normalize_text($metadata), 'weight' => 2],
        ['name' => 'content', 'value' => search_normalize_text($plain), 'weight' => 3],
    ];

    return [
        'id' => $page['id'] ?? null,
        'type' => 'Page',
        'title' => (string) ($page['title'] ?? ''),
        'slug' => (string) ($page['slug'] ?? ''),
        'fields' => $fields,
        'words' => extract_words_from_fields($fields),
        'plain_text' => $plain,
        'record' => $page,
        'suggestions' => build_entry_suggestions($page, 'Page'),
    ];
}

/**
 * Build an index entry for a blog post record.
 *
 * @param array $post
 * @return array
 */
function build_post_entry(array $post)
{
    $content = is_string($post['content'] ?? '') ? $post['content'] : '';
    $excerpt = is_string($post['excerpt'] ?? '') ? $post['excerpt'] : '';
    $plain = strip_tags($content . ' ' . $excerpt);
    $metadata = implode(' ', array_filter([
        $post['category'] ?? '',
        $post['author'] ?? '',
        $post['tags'] ?? '',
    ]));

    $fields = [
        ['name' => 'title', 'value' => search_normalize_text((string) ($post['title'] ?? '')), 'weight' => 1],
        ['name' => 'slug', 'value' => search_normalize_text((string) ($post['slug'] ?? '')), 'weight' => 1.5],
        ['name' => 'metadata', 'value' => search_normalize_text($metadata), 'weight' => 2],
        ['name' => 'content', 'value' => search_normalize_text($plain), 'weight' => 3],
    ];

    return [
        'id' => $post['id'] ?? null,
        'type' => 'Post',
        'title' => (string) ($post['title'] ?? ''),
        'slug' => (string) ($post['slug'] ?? ''),
        'fields' => $fields,
        'words' => extract_words_from_fields($fields),
        'plain_text' => $plain,
        'record' => $post,
        'suggestions' => build_entry_suggestions($post, 'Post'),
    ];
}

/**
 * Build an index entry for a media record.
 *
 * @param array $media
 * @param array $pages
 * @param array $posts
 * @param array $pageContents
 * @param array $postContents
 * @return array
 */
function build_media_entry(array $media, array $pages, array $posts)
{
    $name = (string) ($media['name'] ?? ($media['file'] ?? ''));
    $filename = (string) ($media['file'] ?? '');
    $tags = '';
    if (!empty($media['tags']) && is_array($media['tags'])) {
        $tags = implode(', ', $media['tags']);
    } elseif (is_string($media['tags'] ?? null)) {
        $tags = $media['tags'];
    }
    $altText = (string) ($media['alt'] ?? '');
    $description = (string) ($media['description'] ?? '');

    $context = gather_media_context($filename, $pages, $posts);
    if ($altText !== '') {
        $context[] = $altText;
    }
    if ($description !== '') {
        $context[] = $description;
    }

    $plain = trim(implode(' ', $context));

    $fields = [
        ['name' => 'name', 'value' => search_normalize_text($name), 'weight' => 1],
        ['name' => 'file', 'value' => search_normalize_text($filename), 'weight' => 1.5],
        ['name' => 'tags', 'value' => search_normalize_text($tags), 'weight' => 2],
        ['name' => 'context', 'value' => search_normalize_text($plain), 'weight' => 2.5],
    ];

    return [
        'id' => $media['id'] ?? ($media['file'] ?? ''),
        'type' => 'Media',
        'title' => $name,
        'slug' => $filename,
        'fields' => $fields,
        'words' => extract_words_from_fields($fields),
        'plain_text' => $plain,
        'record' => $media,
        'suggestions' => build_entry_suggestions($media, 'Media'),
    ];
}

/**
 * Gather contextual strings for a media file by scanning pages and posts.
 *
 * @param string $filename
 * @param array $pages
 * @param array $posts
 * @return array
 */
function gather_media_context($filename, array $pages, array $posts)
{
    $context = [];
    if ($filename === '') {
        return $context;
    }

    foreach ($pages as $page) {
        $content = (string) ($page['content'] ?? '');
        $matches = extract_image_context($content, $filename);
        foreach ($matches as $match) {
            $context[] = $match;
        }
    }

    foreach ($posts as $post) {
        $content = (string) ($post['content'] ?? '');
        $matches = extract_image_context($content, $filename);
        foreach ($matches as $match) {
            $context[] = $match;
        }
        if (stripos($post['excerpt'] ?? '', $filename) !== false) {
            $context[] = $post['excerpt'];
        }
    }

    return $context;
}

/**
 * Extract image context strings (alt text and surrounding text) from HTML content.
 *
 * @param string $html
 * @param string $filename
 * @return array
 */
function extract_image_context($html, $filename)
{
    $results = [];
    if ($html === '' || stripos($html, $filename) === false) {
        return $results;
    }

    if (!class_exists('DOMDocument')) {
        return $results;
    }

    $wrapped = '<div>' . $html . '</div>';
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (!$dom->loadHTML($wrapped)) {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $results;
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        if ($src === '') {
            continue;
        }
        if (stripos($src, $filename) === false) {
            continue;
        }
        $alt = trim($img->getAttribute('alt'));
        if ($alt !== '') {
            $results[] = $alt;
        }
        $title = trim($img->getAttribute('title'));
        if ($title !== '') {
            $results[] = $title;
        }
        $parentText = trim(preg_replace('/\s+/', ' ', $img->parentNode->textContent ?? ''));
        if ($parentText !== '') {
            $results[] = $parentText;
        }
    }

    return $results;
}

/**
 * Extract words from the fields for fuzzy matching.
 *
 * @param array $fields
 * @return array
 */
function extract_words_from_fields(array $fields)
{
    $words = [];
    foreach ($fields as $field) {
        $words = array_merge($words, extract_tokens((string) $field['value']));
    }
    return array_values(array_unique(array_slice($words, 0, 2000)));
}

/**
 * Build suggestion entries for a record.
 *
 * @param array $record
 * @param string $type
 * @return array
 */
function build_entry_suggestions(array $record, $type)
{
    $suggestions = [];
    $title = trim((string) ($record['title'] ?? ''));
    if ($title !== '') {
        $suggestions[] = [
            'value' => $title,
            'type' => $type,
            'label' => $title . ' · ' . $type,
        ];
    }

    $slug = trim((string) ($record['slug'] ?? ($record['file'] ?? '')));
    if ($slug !== '') {
        $suggestions[] = [
            'value' => $slug,
            'type' => $type,
            'label' => $slug . ' · ' . $type,
        ];
    }

    $keywords = [];
    if (!empty($record['tags'])) {
        if (is_string($record['tags'])) {
            $keywords = array_map('trim', explode(',', $record['tags']));
        } elseif (is_array($record['tags'])) {
            $keywords = $record['tags'];
        }
    }

    foreach ($keywords as $keyword) {
        $keyword = trim((string) $keyword);
        if ($keyword === '') {
            continue;
        }
        $suggestions[] = [
            'value' => $keyword,
            'type' => $type,
            'label' => $keyword . ' · ' . $type,
        ];
    }

    return $suggestions;
}

/**
 * Record the supplied term in the user's search history.
 *
 * @param string $term
 * @return array
 */
function push_search_history($term)
{
    $term = trim((string) $term);
    if ($term === '') {
        return get_search_history();
    }

    if (!isset($_SESSION['search_history'])) {
        $_SESSION['search_history'] = [];
    }

    $key = search_normalize_text($term);
    $records = $_SESSION['search_history'];
    if (!isset($records[$key])) {
        $records[$key] = [
            'term' => $term,
            'count' => 0,
            'last' => 0,
        ];
    }

    $records[$key]['count']++;
    $records[$key]['last'] = time();
    $records[$key]['term'] = $term;

    uasort($records, function ($a, $b) {
        if ($a['count'] === $b['count']) {
            return $b['last'] <=> $a['last'];
        }
        return $b['count'] <=> $a['count'];
    });

    if (count($records) > 15) {
        $records = array_slice($records, 0, 15, true);
    }

    $_SESSION['search_history'] = $records;
    return get_search_history();
}

/**
 * Retrieve the user's prioritized search history.
 *
 * @param int $limit
 * @return array
 */
function get_search_history($limit = 10)
{
    if (empty($_SESSION['search_history']) || !is_array($_SESSION['search_history'])) {
        return [];
    }

    $records = $_SESSION['search_history'];
    uasort($records, function ($a, $b) {
        if ($a['count'] === $b['count']) {
            return $b['last'] <=> $a['last'];
        }
        return $b['count'] <=> $a['count'];
    });

    $records = array_slice($records, 0, $limit, true);
    $history = [];
    foreach ($records as $item) {
        $history[] = [
            'term' => $item['term'],
            'count' => $item['count'],
            'last' => $item['last'],
        ];
    }
    return $history;
}

/**
 * Return the raw search history terms only.
 *
 * @param int $limit
 * @return array
 */
function get_search_history_terms($limit = 10)
{
    return array_map(function ($item) {
        return $item['term'];
    }, get_search_history($limit));
}

/**
 * Normalize text for searching (lowercase, trim).
 *
 * @param string $value
 * @return string
 */
function search_normalize_text($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

/**
 * Return index counts for each content type.
 *
 * @param array $index
 * @return array
 */
function get_index_type_counts(array $index)
{
    $counts = ['Page' => 0, 'Post' => 0, 'Media' => 0];
    foreach ($index as $entry) {
        if (!isset($entry['type'])) {
            continue;
        }
        if (isset($counts[$entry['type']])) {
            $counts[$entry['type']]++;
        }
    }
    return $counts;
}
?>
