<?php
// File: delete_menu.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_login();
verify_csrf_token();
require_editor();

$menusFile = __DIR__ . '/../../data/menus.json';
$menus = read_json_file($menusFile);
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$menus = array_filter($menus, function($m) use ($id) { return $m['id'] != $id; });
write_json_file($menusFile, array_values($menus));
echo 'OK';
?>
