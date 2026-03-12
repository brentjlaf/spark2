<?php
// File: login.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/settings.php';

$settings = get_site_settings();
ensure_login_attempts_table();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), (string) $csrfToken)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $ip = get_client_ip();
        $attempts = count_recent_login_attempts($ip);

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $remaining = get_lockout_remaining($ip);
            $error = 'Too many failed login attempts. Please try again in ' . ceil($remaining / 60) . ' minute(s).';
        } else {
            $username = sanitize_text($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $user = find_user($username);

            if ($user && ($user['status'] ?? 'active') === 'active' && password_verify($password, $user['password'])) {
                clear_login_attempts($ip);
                $user = update_user_login($user);
                $_SESSION['user'] = $user;
                header('Location: admin.php');
                exit;
            }

            record_login_attempt($ip);
            $attempts++;
            if ($attempts >= LOGIN_MAX_ATTEMPTS) {
                $error = 'Too many failed login attempts. Please try again in ' . ceil(LOGIN_LOCKOUT_SECONDS / 60) . ' minute(s).';
            } else {
                $left = LOGIN_MAX_ATTEMPTS - $attempts;
                $error = 'Invalid credentials. ' . $left . ' attempt(s) remaining before lockout.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">  <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login - <?php echo htmlspecialchars($settings['site_name'] ?? 'SparkCMS'); ?></title>
    <?php include __DIR__ . '/includes/admin_assets.php'; ?>
    <link rel="stylesheet" href="spark-cms.css">
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <label>Username <input type="text" name="username" required></label>
            <label>Password <input type="password" name="password" required></label>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-right-to-bracket btn-icon" aria-hidden="true"></i>
                <span class="btn-label">Login</span>
            </button>
        </form>
    </div>
</body>
</html>
