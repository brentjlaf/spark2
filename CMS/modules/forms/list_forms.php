<?php
// File: list_forms.php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$formsFile = __DIR__ . '/../../data/forms.json';
$forms = read_json_file($formsFile);

echo json_encode($forms);
?>
