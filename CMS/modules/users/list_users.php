<?php
// File: list_users.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();
require_admin();

$usersFile = __DIR__ . '/../../data/users.json';
$users = read_json_file($usersFile);

$clean = [];
foreach ($users as $u) {
    $clean[] = [
        'id' => $u['id'],
        'username' => $u['username'],
        'role' => $u['role'] ?? 'editor',
        'status' => $u['status'] ?? 'active',
        'created_at' => $u['created_at'] ?? 0,
        'last_login' => $u['last_login'] ?? null
    ];
}

echo json_encode($clean);
?>
