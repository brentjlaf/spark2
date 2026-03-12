<?php
// File: logout.php
require_once __DIR__ . '/includes/auth.php';
session_destroy();
header('Location: ' . sparkcms_login_path());
exit;
