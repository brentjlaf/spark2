<?php
// Centralized PDO database connection helper
require_once __DIR__ . '/env_loader.php';

function is_database_configured(): bool
{
    $envPath = __DIR__ . '/../data/.env.php';
    return is_file($envPath)
        || getenv('DB_HOST') !== false
        || getenv('DB_NAME') !== false
        || getenv('DB_USER') !== false
        || getenv('DB_PASS') !== false;
}

function render_installation_required(string $reason, ?Throwable $exception = null): void
{
    $installerUrl = '/installer.php';
    $isCli = PHP_SAPI === 'cli';

    if (!headers_sent()) {
        header('HTTP/1.1 503 Service Unavailable');
        if (!$isCli) {
            header("Location: {$installerUrl}");
        } else {
            header('Content-Type: text/plain; charset=utf-8');
        }
    }

    $message = $reason;
    if ($isCli && $exception) {
        $message .= "\n\n" . $exception->getMessage();
    }

    if ($isCli) {
        echo "Spark CMS is not ready: {$message}\nPlease run the installer at {$installerUrl}.";
    } else {
        echo "<!DOCTYPE html>",
            "<html lang=\"en\">",
            "<head>",
            "<meta charset=\"UTF-8\">",
            "<title>Setup Required</title>",
            "<style>body{font-family:Arial,sans-serif;background:#f6f6f8;color:#1f1f1f;padding:32px;}",
            "main{max-width:720px;margin:0 auto;background:#fff;padding:28px;border-radius:8px;",
            "box-shadow:0 2px 10px rgba(0,0,0,0.08);}h1{margin-top:0;}a.button{display:inline-block;",
            "margin-top:16px;padding:12px 16px;background:#2d7ff9;color:#fff;text-decoration:none;border-radius:6px;}",
            "</style>",
            "</head>",
            "<body>",
            "<main>",
            "<h1>Spark CMS Setup</h1>",
            "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>",
            "<p><a class=\"button\" href=\"{$installerUrl}\">Go to installer</a></p>",
            "</main>",
            "</body>",
            "</html>";
    }

    exit;
}

/**
 * Get a shared PDO connection configured for UTF-8, exceptions, and prepared statements.
 *
 * Environment variables:
 *  - DB_HOST (default: localhost)
 *  - DB_NAME (default: spark_cms)
 *  - DB_USER (default: spark)
 *  - DB_PASS (default: spark)
 *  - DB_CHARSET (default: utf8mb4)
 */
function get_db_connection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_database_configured()) {
        render_installation_required('Configuration file CMS/data/.env.php is missing.');
    }

    load_env_from_file();

    $host = getenv('DB_HOST') ?: 'localhost';
    $name = getenv('DB_NAME') ?: 'spark_cms';
    $user = getenv('DB_USER') ?: 'spark';
    $pass = getenv('DB_PASS') ?: 'spark';
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET NAMES {$charset} COLLATE {$charset}_general_ci");
    } catch (Throwable $e) {
        render_installation_required('Unable to connect to the database. Please confirm your credentials or rerun the installer.', $e);
    }

    return $pdo;
}

function db_fetch_all(string $sql, array $params = []): array
{
    $stmt = get_db_connection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_execute(string $sql, array $params = []): bool
{
    $stmt = get_db_connection()->prepare($sql);
    return $stmt->execute($params);
}

?>
