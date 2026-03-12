<?php
// Migration script: creates MySQL tables for CMS JSON entities and imports existing data
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/migration.php';

$dataDir = realpath(__DIR__ . '/../data');
if (!$dataDir) {
    echo "Unable to locate data directory.\n";
    exit(1);
}

echo "Backing up JSON files...\n";
$backupDir = cms_run_json_migration(get_db_connection(), $dataDir);
echo "Backups stored in {$backupDir}.\n";
echo "Migration complete.\n";
?>
