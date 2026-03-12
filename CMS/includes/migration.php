<?php
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/payload.php';

function cms_load_json_records(string $path, array $schema): array
{
    if (!file_exists($path)) {
        return [];
    }
    $raw = json_decode(file_get_contents($path), true);
    if (!$raw) {
        return [];
    }

    if ($schema['primary'] === 'setting_key' && array_values($raw) !== $raw) {
        $rows = [];
        foreach ($raw as $key => $value) {
            $rows[] = ['setting_key' => $key, 'value' => $value];
        }
        return $rows;
    }

    return $raw;
}

function cms_ensure_table(PDO $pdo, array $schema, array $records = []): void
{
    $primary = $schema['primary'];
    $jsonCol = $schema['json_column'];
    $columns = $schema['columns'];

    $primaryType = cms_infer_primary_type($records, $primary);
    $primaryDef = $primaryType === 'int'
        ? "INT AUTO_INCREMENT"
        : "VARCHAR(191)";

    $columnSql = [];
    foreach ($columns as $columnName => $sourceKey) {
        $columnSql[] = "`{$columnName}` VARCHAR(255) NULL";
    }
    $indexSql = [];
    foreach ($schema['indexes'] as $index) {
        $indexSql[] = "INDEX `idx_{$schema['table']}_{$index}` (`{$index}`)";
    }

    $sql = sprintf(
        "CREATE TABLE IF NOT EXISTS `%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        $schema['table'],
        implode(",\n", array_filter(array_merge([
            "`{$primary}` {$primaryDef} PRIMARY KEY",
            "`{$jsonCol}` LONGTEXT NOT NULL",
        ], $columnSql, $indexSql)))
    );

    $pdo->exec($sql);
}

function cms_import_records(PDO $pdo, array $schema, array $records): void
{
    $pdo->beginTransaction();
    $pdo->exec("TRUNCATE TABLE `{$schema['table']}`");

    $columns = array_merge([$schema['primary'], $schema['json_column']], array_keys($schema['columns']));
    $placeholders = rtrim(str_repeat('?,', count($columns)), ',');
    $stmt = $pdo->prepare(
        "INSERT INTO `{$schema['table']}` (`" . implode('`,`', $columns) . "`) VALUES ({$placeholders})"
    );

    $nextId = 1;
    foreach ($records as $record) {
        if (!is_array($record)) {
            continue;
        }
        $primaryValue = $record[$schema['primary']] ?? $nextId;
        $nextId = is_numeric($primaryValue) ? max($nextId + 1, (int)$primaryValue + 1) : $nextId + 1;
        $values = [$primaryValue, cms_encode_payload($record)];
        foreach ($schema['columns'] as $columnName => $sourceKey) {
            if ($schema['primary'] === 'setting_key' && $sourceKey === 'setting_key' && !isset($record['setting_key'])) {
                $values[] = $record['key'] ?? null;
                continue;
            }
            $values[] = $record[$sourceKey] ?? null;
        }
        $stmt->execute($values);
    }

    $pdo->commit();
}

function cms_import_sql_file(PDO $pdo, string $sqlFile): void
{
    if (!is_readable($sqlFile)) {
        throw new InvalidArgumentException("SQL file not readable: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException("Failed to read SQL file: {$sqlFile}");
    }

    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)));
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

function cms_drop_entity_tables(PDO $pdo): void
{
    $tables = array_map(static fn ($schema) => $schema['table'], cms_entity_schemas());
    $tables[] = 'cms_page_drafts';

    $pdo->exec('SET FOREIGN_KEY_CHECKS=0;');
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
}

function cms_run_json_migration(PDO $pdo, string $dataDir): string
{
    $backupDir = rtrim($dataDir, DIRECTORY_SEPARATOR) . '/backup-' . date('Ymd_His');
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    foreach (array_keys(cms_entity_schemas()) as $file) {
        $source = $dataDir . '/' . $file;
        if (is_file($source)) {
            copy($source, $backupDir . '/' . basename($source));
        }
    }

    foreach (cms_entity_schemas() as $file => $schema) {
        $jsonPath = $dataDir . '/' . $file;
        $records = cms_load_json_records($jsonPath, $schema);
        cms_ensure_table($pdo, $schema, $records);
        cms_import_records($pdo, $schema, $records);
    }

    return $backupDir;
}

function cms_infer_primary_type(array $records, string $primary): string
{
    if ($primary === 'id' || str_ends_with($primary, '_id')) {
        return 'int';
    }

    foreach ($records as $record) {
        if (isset($record[$primary]) && is_numeric($record[$primary])) {
            return 'int';
        }
    }
    return 'string';
}
?>
