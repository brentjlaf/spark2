<?php
// File: auth.php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/payload.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/permissions.php';

ensure_site_timezone();

$usersFile = __DIR__ . '/../data/users.json';
$usersTable = cms_schema_for_json($usersFile)['table'] ?? 'cms_users';
initialize_users_table($usersTable);
$users = read_json_file($usersFile);

if (empty($users)) {
    seed_default_admin($usersTable);
    $users = read_json_file($usersFile);
    if (empty($users)) {
        render_installation_required('No admin account exists. Set ADMIN_USERNAME and ADMIN_PASSWORD to create the first user.');
    }
}

function initialize_users_table(string $table): void
{
    try {
        $pdo = get_db_connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(255) NOT NULL UNIQUE,
            `role` VARCHAR(50) DEFAULT 'admin',
            `status` VARCHAR(50) DEFAULT 'active',
            `created_at` INT DEFAULT 0,
            `last_login` INT NULL,
            `password` VARCHAR(255) NOT NULL,
            `payload` LONGTEXT NOT NULL,
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Throwable $e) {
        // Table initialization best-effort; failures handled downstream
    }
}

function seed_default_admin(string $table): void
{
    try {
        $pdo = get_db_connection();
        $exists = $pdo->query("SELECT COUNT(*) as c FROM `{$table}`")->fetchColumn();
        if ((int)$exists > 0) {
            return;
        }

        $username = getenv('ADMIN_USERNAME') ?: 'admin';
        $password = getenv('ADMIN_PASSWORD') ?: 'password';

        $payload = [
            'id' => 1,
            'username' => $username,
            'role' => 'admin',
            'status' => 'active',
            'created_at' => time(),
            'last_login' => null
        ];
        $stmt = $pdo->prepare("INSERT INTO `{$table}` (`id`,`payload`,`username`,`role`,`status`,`created_at`,`last_login`,`password`) VALUES (1,?,?,?,?,?,?,?)");
        $stmt->execute([
            cms_encode_payload($payload),
            $payload['username'],
            $payload['role'],
            $payload['status'],
            $payload['created_at'],
            $payload['last_login'],
            password_hash($password, PASSWORD_DEFAULT),
        ]);
    } catch (Throwable $e) {
        render_installation_required('Unable to create the initial administrator account. Please verify your database and environment configuration.', $e);
    }
}

function find_user($username) {
    global $users;
    foreach ($users as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }
    return null;
}

function update_user_login(array $user): array
{
    global $users, $usersFile;
    $user['last_login'] = time();
    foreach ($users as &$existing) {
        if ($existing['id'] == $user['id']) {
            $existing = $user;
            break;
        }
    }
    unset($existing);
    write_json_file($usersFile, $users);
    return $user;
}

function sparkcms_login_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($scriptName === '' || $scriptName[0] !== '/') {
        $scriptName = '/' . ltrim($scriptName, '/');
    }

    $cmsRoot = preg_replace('#/CMS(?:/.*)?$#', '/CMS', $scriptName);
    if ($cmsRoot === null || $cmsRoot === $scriptName && strpos($scriptName, '/CMS') === false) {
        $cmsRoot = '/CMS';
    }

    return rtrim($cmsRoot, '/') . '/login.php';
}

function require_login() {
    if (!isset($_SESSION['user'])) {
        header('Location: ' . sparkcms_login_path());
        exit;
    }
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

// --- Rate limiting constants ---
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_SECONDS', 900); // 15 minutes

function ensure_login_attempts_table(): void
{
    try {
        $pdo = get_db_connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `cms_login_attempts` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ip` VARCHAR(45) NOT NULL,
            `attempted_at` INT NOT NULL,
            INDEX `idx_ip_time` (`ip`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } catch (Throwable $e) {
        // Best-effort; rate limiting is skipped if table cannot be created
    }
}

function get_client_ip(): string
{
    // Use REMOTE_ADDR only — do not trust forwarded headers without a trusted proxy
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function count_recent_login_attempts(string $ip): int
{
    try {
        $pdo = get_db_connection();
        $since = time() - LOGIN_LOCKOUT_SECONDS;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM `cms_login_attempts` WHERE `ip` = ? AND `attempted_at` > ?');
        $stmt->execute([$ip, $since]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function record_login_attempt(string $ip): void
{
    try {
        $pdo = get_db_connection();
        $pdo->prepare('INSERT INTO `cms_login_attempts` (`ip`, `attempted_at`) VALUES (?, ?)')
            ->execute([$ip, time()]);
        // Prune records older than the lockout window to keep the table small
        $cutoff = time() - LOGIN_LOCKOUT_SECONDS;
        $pdo->prepare('DELETE FROM `cms_login_attempts` WHERE `attempted_at` < ?')->execute([$cutoff]);
    } catch (Throwable $e) {
        // Best-effort
    }
}

function clear_login_attempts(string $ip): void
{
    try {
        $pdo = get_db_connection();
        $pdo->prepare('DELETE FROM `cms_login_attempts` WHERE `ip` = ?')->execute([$ip]);
    } catch (Throwable $e) {
        // Best-effort
    }
}

function get_lockout_remaining(string $ip): int
{
    try {
        $pdo = get_db_connection();
        $since = time() - LOGIN_LOCKOUT_SECONDS;
        $stmt = $pdo->prepare('SELECT MIN(`attempted_at`) FROM `cms_login_attempts` WHERE `ip` = ? AND `attempted_at` > ?');
        $stmt->execute([$ip, $since]);
        $oldest = (int) $stmt->fetchColumn();
        if ($oldest === 0) {
            return 0;
        }
        return max(0, ($oldest + LOGIN_LOCKOUT_SECONDS) - time());
    } catch (Throwable $e) {
        return 0;
    }
}
?>
