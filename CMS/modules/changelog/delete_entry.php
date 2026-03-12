<?php
// File: delete_entry.php  –  disabled: changelog is system-managed only
require_once __DIR__ . '/../../includes/auth.php';
require_login();

header('Content-Type: application/json');
http_response_code(403);
echo json_encode(['error' => 'Changelog entries are managed by the system and cannot be deleted manually.']);
