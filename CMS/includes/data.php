<?php
// File: data.php
// Utility functions for reading/writing CMS payload files with simple in-memory caching
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/migration.php';
require_once __DIR__ . '/payload.php';

/**
 * Ensure the mapped database table exists for the provided schema.
 *
 * Tables are created on-demand so admin actions always persist to the database
 * even if the installer or seed script has not been executed.
 */
function ensure_schema_table(array $schema): void
{
    static $ensured = [];
    $table = $schema['table'] ?? '';
    if ($table === '' || isset($ensured[$table])) {
        return;
    }

    try {
        $pdo = get_db_connection();
        $exists = $pdo->prepare('SHOW TABLES LIKE ?');
        $exists->execute([$table]);
        if ($exists->fetchColumn()) {
            $ensured[$table] = true;
            return;
        }

        $primary = $schema['primary'] ?? 'id';
        $jsonColumn = $schema['json_column'] ?? 'payload';
        $columns = is_array($schema['columns'] ?? null) ? $schema['columns'] : [];
        $indexes = is_array($schema['indexes'] ?? null) ? $schema['indexes'] : [];

        // Prefer numeric primary keys for conventional id/*_id entities.
        $isNumericId = $primary === 'id' || str_ends_with($primary, '_id');
        $primaryType = $isNumericId ? 'INT' : 'VARCHAR(191)';
        $primaryExtras = $isNumericId ? 'AUTO_INCREMENT' : '';

        $columnDefs = ["`{$primary}` {$primaryType} {$primaryExtras} PRIMARY KEY", "`{$jsonColumn}` LONGTEXT NOT NULL"];

        foreach ($columns as $columnName => $sourceKey) {
            if ($columnName === $primary || $columnName === $jsonColumn) {
                continue;
            }
            $columnDefs[] = "`{$columnName}` VARCHAR(255) NULL";
        }

        foreach ($indexes as $indexColumn) {
            if ($indexColumn === $primary) {
                continue;
            }
            if (!isset($columns[$indexColumn])) {
                $columnDefs[] = "`{$indexColumn}` VARCHAR(255) NULL";
            }
            $columnDefs[] = "INDEX `idx_{$table}_{$indexColumn}` (`{$indexColumn}`)";
        }

        $createSql = "CREATE TABLE `{$table}` (" . implode(',', $columnDefs) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($createSql);
        $ensured[$table] = true;
    } catch (Throwable $e) {
        // Table creation should not block requests; failures will be surfaced when queries run.
    }
}

/**
 * Read and decode a JSON file or mapped database table.
 *
 * @param string $file Path to the JSON file
 * @return array Decoded JSON data or empty array on failure
 */
function read_json_file($file) {
    $schema = cms_schema_for_json($file);
    $isUserFile = basename($file) === 'users.json';

    if ($schema) {
        if (is_database_configured()) {
            return read_table_as_array($schema);
        }

        $message = $isUserFile
            ? 'User accounts require a configured database. Please run the installer to finish setup.'
            : 'Database storage is required for CMS content. Please configure your database credentials and rerun this action.';
        render_installation_required($message);
        return [];
    }

    if (!file_exists($file)) {
        return [];
    }
    $contents = file_get_contents($file);
    if ($contents === false) {
        return [];
    }

    $format = null;
    $decoded = cms_decode_payload_with_format($contents, $format);
    if ($format === 'json') {
        @file_put_contents($file, cms_encode_payload($decoded));
    }

    return is_array($decoded) ? $decoded : [];
}

/**
 * Persist an array to the JSON file or mapped database table using pretty print formatting.
 *
 * @param string $file  Path to the JSON file
 * @param mixed  $data  Data to encode
 * @return bool True on success, false on failure
 */
function write_json_file($file, $data) {
    $schema = cms_schema_for_json($file);
    $isUserFile = basename($file) === 'users.json';

    if ($schema) {
        if (is_database_configured()) {
            $pdo = get_db_connection();
            if (!cms_table_exists($pdo, $schema['table'])) {
                cms_ensure_table($pdo, $schema, is_array($data) ? $data : []);
            }
            $persisted = write_table_from_array($schema, $data);
            if ($persisted) {
                return true;
            }
        }

        $message = $isUserFile
            ? 'User accounts must be stored in the database. Please configure your database credentials and rerun this action.'
            : 'Database storage is required for CMS content. Please configure your database credentials and rerun this action.';
        render_installation_required($message);
        return false;
    }

    return file_put_contents($file, cms_encode_payload($data)) !== false;
}

/**
 * Determine the last modification time for a JSON-backed entity, preferring
 * database metadata when the JSON file is mapped to a table.
 */
function get_storage_last_modified(string $file): ?int
{
    $schema = cms_schema_for_json($file);
    if ($schema) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('SHOW TABLE STATUS LIKE ?');
            $stmt->execute([$schema['table']]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($status) {
                if (!empty($status['Update_time'])) {
                    return strtotime($status['Update_time']);
                }
                if (!empty($status['Create_time'])) {
                    return strtotime($status['Create_time']);
                }
            }
        } catch (Throwable $e) {
            // Ignore and fall back to file timestamps when available.
        }
    }

    if (is_file($file)) {
        $modified = filemtime($file);
        return $modified === false ? null : $modified;
    }

    return null;
}

/**
 * Load and decode a JSON file while caching the result within the request.
 *
 * @param string $file Path to the JSON file
 * @return array Decoded JSON data or empty array on failure
 */
function get_cached_json($file) {
    static $cache = [];
    if (!isset($cache[$file])) {
        $cache[$file] = read_json_file($file);
    }
    return $cache[$file];
}

/**
 * Map a table row set to the array format expected by callers.
 */
function read_table_as_array(array $schema): array
{
    ensure_schema_table($schema);
    try {
        $schemaColumns = array_keys($schema['columns'] ?? []);
        $selectColumns = array_unique(array_merge([$schema['primary'], $schema['json_column']], $schemaColumns));
        $quotedColumns = '`' . implode('`,`', $selectColumns) . '`';
        $rows = db_fetch_all("SELECT {$quotedColumns} FROM `{$schema['table']}` ORDER BY `{$schema['primary']}`");
        $decoded = [];
        foreach ($rows as $row) {
            $format = null;
            $payload = cms_decode_payload_with_format((string) $row[$schema['json_column']], $format);
            if (!is_array($payload)) {
                $payload = [];
            }

            if ($format === 'json') {
                try {
                    db_execute(
                        "UPDATE `{$schema['table']}` SET `{$schema['json_column']}` = ? WHERE `{$schema['primary']}` = ?",
                        [cms_encode_payload($payload), $row[$schema['primary']]]
                    );
                } catch (Throwable $e) {
                    // Best-effort conversion; ignore failures.
                }
            }
            if (!isset($payload[$schema['primary']])) {
                $payload[$schema['primary']] = $row[$schema['primary']];
            }

            foreach ($schema['columns'] ?? [] as $columnName => $sourceKey) {
                if (!array_key_exists($sourceKey, $payload) && array_key_exists($columnName, $row)) {
                    $payload[$sourceKey] = $row[$columnName];
                }
            }

            if ($schema['primary'] === 'setting_key') {
                $decoded[$row[$schema['primary']]] = $payload['value'] ?? $payload;
            } else {
                $decoded[] = $payload;
            }
        }
        return $decoded;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Replace table contents from an array of associative arrays.
 */
function write_table_from_array(array $schema, $data): bool
{
    if (!is_array($data)) {
        return false;
    }

    ensure_schema_table($schema);

    if ($schema['primary'] === 'setting_key' && array_values($data) !== $data) {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[] = ['setting_key' => $key, 'value' => $value];
        }
        $data = $normalized;
    }

    // Avoid duplicate column names when the schema maps the primary key as a column.
    $schemaColumns = [];
    foreach ($schema['columns'] as $columnName => $sourceKey) {
        if ($columnName === $schema['primary'] || $columnName === $schema['json_column']) {
            continue;
        }
        $schemaColumns[$columnName] = $sourceKey;
    }

    try {
        $pdo = get_db_connection();
        $pdo->beginTransaction();
        $existingKeysStmt = $pdo->prepare("SELECT `{$schema['primary']}` FROM `{$schema['table']}`");
        $existingKeysStmt->execute();
        $existingKeys = $existingKeysStmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        $existingKeyMap = [];
        foreach ($existingKeys as $key) {
            $existingKeyMap[(string) $key] = true;
        }

        $useNumericIds = $schema['primary'] === 'id' || str_ends_with($schema['primary'], '_id');
        $nextId = $useNumericIds && $existingKeys
            ? (int) max(array_map('intval', $existingKeys)) + 1
            : 1;

        $insertColumns = array_merge([$schema['primary'], $schema['json_column']], array_keys($schemaColumns));
        $placeholders = rtrim(str_repeat('?,', count($insertColumns)), ',');
        $updateColumns = array_merge([$schema['json_column']], array_keys($schemaColumns));
        $updateAssignments = implode(
            ', ',
            array_map(static fn ($col) => "`{$col}` = VALUES(`{$col}`)", $updateColumns)
        );
        $sql = "INSERT INTO `{$schema['table']}` (`" . implode('`,`', $insertColumns) . "`) VALUES ({$placeholders})"
            . " ON DUPLICATE KEY UPDATE {$updateAssignments}";
        $stmt = $pdo->prepare($sql);

        $rows = array_values($data);
        $writtenKeyMap = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $primaryValue = $row[$schema['primary']] ?? $nextId;
            $nextId = is_numeric($primaryValue) ? max($nextId + 1, (int) $primaryValue + 1) : $nextId + 1;
            $payloadData = $row;
            $excludedKeys = is_array($schema['exclude_from_payload'] ?? null) ? $schema['exclude_from_payload'] : [];
            foreach ($excludedKeys as $excludedKey) {
                unset($payloadData[$excludedKey]);
            }
            $payload = cms_encode_payload($payloadData);
            $values = [$primaryValue, $payload];
            foreach ($schemaColumns as $columnKey => $sourceKey) {
                $values[] = $row[$sourceKey] ?? null;
            }
            $stmt->execute($values);
            $writtenKeyMap[(string) $primaryValue] = $primaryValue;
        }

        if (empty($writtenKeyMap)) {
            $pdo->exec("DELETE FROM `{$schema['table']}`");
        } else {
            $keysToDelete = [];
            foreach ($existingKeys as $existingKey) {
                if (!isset($writtenKeyMap[(string) $existingKey])) {
                    $keysToDelete[] = $existingKey;
                }
            }
            if ($keysToDelete) {
                $deletePlaceholders = rtrim(str_repeat('?,', count($keysToDelete)), ',');
                $deleteSql = "DELETE FROM `{$schema['table']}` WHERE `{$schema['primary']}` IN ({$deletePlaceholders})";
                $deleteStmt = $pdo->prepare($deleteSql);
                $deleteStmt->execute($keysToDelete);
            }
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function cms_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_drafts_table(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo = get_db_connection();
    $sql = "CREATE TABLE IF NOT EXISTS `cms_page_drafts` (
        `page_id` INT PRIMARY KEY,
        `content` LONGTEXT NOT NULL,
        `updated_at` INT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    $ensured = true;
}

function load_page_draft(int $pageId): array
{
    ensure_drafts_table();
    $rows = db_fetch_all('SELECT content, updated_at FROM cms_page_drafts WHERE page_id = ?', [$pageId]);
    if (!$rows) {
        return ['content' => '', 'timestamp' => 0];
    }

    return ['content' => $rows[0]['content'], 'timestamp' => (int) $rows[0]['updated_at']];
}

function save_page_draft(int $pageId, string $content, int $timestamp): bool
{
    ensure_drafts_table();
    return db_execute(
        'INSERT INTO cms_page_drafts (page_id, content, updated_at) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = VALUES(updated_at)',
        [$pageId, $content, $timestamp]
    );
}

function delete_page_draft(int $pageId): void
{
    ensure_drafts_table();
    db_execute('DELETE FROM cms_page_drafts WHERE page_id = ?', [$pageId]);
}
?>
