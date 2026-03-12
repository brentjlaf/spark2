<?php
// File: save_user.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_admin();

$usersFile = __DIR__ . '/../../data/users.json';
$users = read_json_file($usersFile);

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
$username = sanitize_text($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = sanitize_text($_POST['role'] ?? 'editor');
$status = sanitize_text($_POST['status'] ?? 'active');
$allowedRoles = ['admin', 'editor', 'viewer'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'editor';
}
$allowedStatus = ['active', 'inactive'];
if (!in_array($status, $allowedStatus, true)) {
    $status = 'active';
}

if ($username === '') {
    http_response_code(400);
    echo 'Missing username';
    exit;
}

$isNewUser = $id === null;
if ($isNewUser && $password === '') {
    http_response_code(400);
    echo 'Password required for new users';
    exit;
}

if ($id) {
    foreach ($users as &$u) {
        if ($u['id'] == $id) {
            $u['username'] = $username;
            if ($password !== '') {
                $u['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $u['role'] = $role;
            $u['status'] = $status;
            break;
        }
    }
    unset($u);
} else {
    $id = 1;
    foreach ($users as $u) {
        if ($u['id'] >= $id) $id = $u['id'] + 1;
    }
    $users[] = [
        'id' => $id,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'status' => $status,
        'created_at' => time(),
        'last_login' => null
    ];
}

write_json_file($usersFile, $users);
echo 'OK';
?>
