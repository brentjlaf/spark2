<?php
// File: search_index.php
// MySQL FULLTEXT search index for SparkCMS.
// Gracefully no-ops when no database is configured.

require_once __DIR__ . '/db.php';

/** Table that holds the unified full-text search index. */
define('SPARKCMS_SEARCH_TABLE', 'cms_search_index');

/**
 * Create the search index table with a FULLTEXT index if it does not already exist.
 * Safe to call on every request; uses a static flag to avoid redundant DDL checks.
 */
function sparkcms_ensure_search_table(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $table = SPARKCMS_SEARCH_TABLE;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `{$table}` (
            `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `type`       VARCHAR(32)   NOT NULL,
            `record_id`  INT UNSIGNED  NOT NULL,
            `title`      VARCHAR(512)  NOT NULL DEFAULT '',
            `body`       MEDIUMTEXT    NOT NULL,
            `slug`       VARCHAR(512)  NOT NULL DEFAULT '',
            `published`  TINYINT(1)    NOT NULL DEFAULT 1,
            `indexed_at` INT UNSIGNED  NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE  KEY `uq_type_record` (`type`, `record_id`),
            FULLTEXT KEY `ft_title_body`  (`title`, `body`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Insert or update a single record in the search index.
 *
 * @param PDO    $pdo
 * @param string $type      'page' | 'post' | 'media'
 * @param int    $recordId  The record's integer primary key.
 * @param string $title
 * @param string $body      Raw/HTML body text – tags are stripped automatically.
 * @param string $slug
 * @param bool   $published Whether the record is publicly visible.
 */
function sparkcms_index_record(
    PDO    $pdo,
    string $type,
    int    $recordId,
    string $title,
    string $body,
    string $slug,
    bool   $published = true
): void {
    sparkcms_ensure_search_table($pdo);

    $table = SPARKCMS_SEARCH_TABLE;
    $stmt  = $pdo->prepare("
        INSERT INTO `{$table}`
            (`type`, `record_id`, `title`, `body`, `slug`, `published`, `indexed_at`)
        VALUES
            (:type, :record_id, :title, :body, :slug, :published, :indexed_at)
        ON DUPLICATE KEY UPDATE
            `title`      = VALUES(`title`),
            `body`       = VALUES(`body`),
            `slug`       = VALUES(`slug`),
            `published`  = VALUES(`published`),
            `indexed_at` = VALUES(`indexed_at`)
    ");
    $stmt->execute([
        ':type'       => $type,
        ':record_id'  => $recordId,
        ':title'      => mb_substr($title, 0, 512),
        ':body'       => strip_tags($body),
        ':slug'       => mb_substr($slug, 0, 512),
        ':published'  => $published ? 1 : 0,
        ':indexed_at' => time(),
    ]);
}

/**
 * Remove a single record from the search index.
 */
function sparkcms_remove_from_index(PDO $pdo, string $type, int $recordId): void
{
    sparkcms_ensure_search_table($pdo);

    $table = SPARKCMS_SEARCH_TABLE;
    $stmt  = $pdo->prepare(
        "DELETE FROM `{$table}` WHERE `type` = :type AND `record_id` = :record_id"
    );
    $stmt->execute([':type' => $type, ':record_id' => $recordId]);
}

/**
 * Execute a FULLTEXT boolean-mode search.
 *
 * Short words (< 3 chars) are searched without the min-length requirement workaround;
 * longer words get a trailing wildcard so "spark" matches "sparkcms".
 *
 * @param PDO    $pdo
 * @param string $query           Raw user query.
 * @param array  $options {
 *     limit          int   (default 100)
 *     types          array  e.g. ['page','post'] – empty means all
 *     only_published bool  (default false – caller filters by published if needed)
 * }
 * @return array{results: array, engine: string}
 */
function sparkcms_fulltext_search(PDO $pdo, string $query, array $options = []): array
{
    sparkcms_ensure_search_table($pdo);

    $table   = SPARKCMS_SEARCH_TABLE;
    $limit   = min((int)($options['limit'] ?? 100), 500);
    $types   = $options['types'] ?? [];
    $onlyPub = $options['only_published'] ?? false;

    // Sanitise the query – strip boolean-mode operators MySQL would misinterpret.
    $clean = trim(preg_replace('/[+\-><()\[\]~*"@]+/', ' ', $query));
    if ($clean === '') {
        return ['results' => [], 'engine' => 'fulltext'];
    }

    // Build a boolean-mode query: each word ≥ 3 chars gets a + prefix and * suffix.
    $words     = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
    $boolParts = [];
    foreach ($words as $word) {
        if (mb_strlen($word) >= 3) {
            $boolParts[] = '+' . $word . '*';
        } else {
            $boolParts[] = $word;
        }
    }
    $boolQuery = implode(' ', $boolParts);

    $where  = ["MATCH(`title`, `body`) AGAINST (:q IN BOOLEAN MODE)"];
    $params = [':q' => $boolQuery, ':q_score' => $boolQuery];

    if ($onlyPub) {
        $where[] = '`published` = 1';
    }
    if (!empty($types)) {
        $placeholders = [];
        foreach ($types as $i => $t) {
            $key              = ':type_' . (int)$i;
            $placeholders[]   = $key;
            $params[$key]     = $t;
        }
        $where[] = '`type` IN (' . implode(', ', $placeholders) . ')';
    }

    $sql = "
        SELECT `type`, `record_id`, `title`, `slug`, `published`,
               MATCH(`title`, `body`) AGAINST (:q_score IN BOOLEAN MODE) AS `score`
        FROM   `{$table}`
        WHERE  " . implode(' AND ', $where) . "
        ORDER  BY `score` DESC, `title` ASC
        LIMIT  {$limit}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return ['results' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'engine' => 'fulltext'];
}

/**
 * Rebuild the entire search index by clearing it and re-indexing all
 * pages and blog posts from the JSON data files.
 *
 * @param PDO    $pdo
 * @param string $cmsRoot Absolute path to the CMS/ directory.
 */
function sparkcms_rebuild_search_index(PDO $pdo, string $cmsRoot): void
{
    sparkcms_ensure_search_table($pdo);
    require_once $cmsRoot . '/includes/data.php';

    $table = SPARKCMS_SEARCH_TABLE;
    $pdo->exec("DELETE FROM `{$table}`");

    // Index pages
    $pages = read_json_file($cmsRoot . '/data/pages.json');
    if (is_array($pages)) {
        foreach ($pages as $page) {
            $id = (int)($page['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $body = ($page['meta_description'] ?? '') . ' ' . strip_tags($page['content'] ?? '');
            sparkcms_index_record(
                $pdo, 'page', $id,
                (string)($page['title'] ?? ''),
                $body,
                (string)($page['slug'] ?? ''),
                !empty($page['published'])
            );
        }
    }

    // Index blog posts
    $posts = read_json_file($cmsRoot . '/data/blog_posts.json');
    if (is_array($posts)) {
        foreach ($posts as $post) {
            $id = (int)($post['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $body = ($post['excerpt'] ?? '') . ' ' . ($post['tags'] ?? '') . ' ' . strip_tags($post['content'] ?? '');
            sparkcms_index_record(
                $pdo, 'post', $id,
                (string)($post['title'] ?? ''),
                $body,
                (string)($post['slug'] ?? ''),
                ($post['status'] ?? '') === 'published'
            );
        }
    }
}
