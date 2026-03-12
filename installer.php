<?php
require_once __DIR__ . '/CMS/includes/migration.php';
require_once __DIR__ . '/CMS/includes/data.php';

$envPath = __DIR__ . '/CMS/data/.env.php';
$dataDir = realpath(__DIR__ . '/CMS/data');
$sqlFile = __DIR__ . '/CMS/data/spark_seed.sql';

$installed = is_file($envPath);
$errors = [];
$successMessage = null;

function render_field($name, $label, $value = '', $type = 'text') {
    $escapedValue = htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    echo "<label>{$escapedLabel}<input type=\"{$type}\" name=\"{$name}\" value=\"{$escapedValue}\" required></label>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $charset = trim($_POST['db_charset'] ?? 'utf8mb4');

    if ($dbHost === '' || $dbName === '' || $dbUser === '' || $charset === '') {
        $errors[] = 'All fields except password are required.';
    }

    if (empty($errors)) {
        try {
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$charset}";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            $errors[] = 'Unable to connect to the database with the provided credentials.';
        }
    }

    if (empty($errors) && !$dataDir) {
        $errors[] = 'CMS data directory could not be located.';
    }

    if (empty($errors) && !is_file($sqlFile)) {
        $errors[] = 'MySQL seed file could not be found in CMS/data.';
    }

    if (empty($errors)) {
        $envContents = "<?php\nreturn [\n" .
            "    'DB_HOST' => '" . addslashes($dbHost) . "',\n" .
            "    'DB_NAME' => '" . addslashes($dbName) . "',\n" .
            "    'DB_USER' => '" . addslashes($dbUser) . "',\n" .
            "    'DB_PASS' => '" . addslashes($dbPass) . "',\n" .
            "    'DB_CHARSET' => '" . addslashes($charset) . "',\n" .
            "];\n";

        if (file_put_contents($envPath, $envContents) === false) {
            $errors[] = 'Failed to write environment file. Please check permissions for CMS/data.';
        } else {
            putenv("DB_HOST={$dbHost}");
            putenv("DB_NAME={$dbName}");
            putenv("DB_USER={$dbUser}");
            putenv("DB_PASS={$dbPass}");
            putenv("DB_CHARSET={$charset}");

            try {
                if ($installed) {
                    cms_drop_entity_tables($pdo);
                }

                cms_import_sql_file($pdo, $sqlFile);

                foreach (cms_entity_schemas() as $schema) {
                    cms_ensure_table($pdo, $schema);
                }

                if (function_exists('ensure_drafts_table')) {
                    ensure_drafts_table();
                }

                $successMessage = "Installation complete! Database populated from spark_seed.sql and missing tables ensured. You can now log in at <a href=\"CMS/login.php\">CMS/login.php</a>.";
            } catch (Throwable $e) {
                $errors[] = 'Database import failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Spark CMS Installer</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 640px; margin: 40px auto; background: white; padding: 24px 28px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        form { display: grid; grid-template-columns: 1fr; gap: 14px; }
        label { display: flex; flex-direction: column; font-weight: bold; }
        input { margin-top: 6px; padding: 10px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; }
        .actions { margin-top: 10px; }
        button { background: #2d7ff9; border: none; color: white; padding: 12px 16px; font-size: 15px; border-radius: 4px; cursor: pointer; }
        .notice { padding: 12px; background: #e8f4ff; border: 1px solid #cddff8; border-radius: 4px; margin-bottom: 12px; }
        .error { padding: 12px; background: #ffecec; border: 1px solid #f5c2c2; border-radius: 4px; color: #a40000; margin-bottom: 12px; }
        .success { padding: 12px; background: #e6ffed; border: 1px solid #b7f5cb; border-radius: 4px; color: #0b6b2a; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Spark CMS Installer</h1>
    <p>Enter your database details to configure Spark CMS. This will create a local environment file and import schema and sample data from the packaged MySQL seed file.</p>
    <?php if ($installed && !$successMessage): ?>
        <div class="notice">Existing installation detected. Submitting the form will overwrite <code>CMS/data/.env.php</code>, drop existing Spark CMS tables, and re-import the database seed.</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <div><?php echo $error; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($successMessage): ?>
        <div class="success"><?php echo $successMessage; ?></div>
    <?php else: ?>
        <form method="post">
            <?php render_field('db_host', 'Database Host', $_POST['db_host'] ?? 'localhost'); ?>
            <?php render_field('db_name', 'Database Name', $_POST['db_name'] ?? 'spark_cms'); ?>
            <?php render_field('db_user', 'Database User', $_POST['db_user'] ?? 'spark'); ?>
            <?php render_field('db_pass', 'Database Password', $_POST['db_pass'] ?? '', 'password'); ?>
            <?php render_field('db_charset', 'Database Charset', $_POST['db_charset'] ?? 'utf8mb4'); ?>
            <div class="actions">
                <button type="submit">Install Spark CMS</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
