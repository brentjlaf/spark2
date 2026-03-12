<?php
// Role-based access control
//
// Role hierarchy: admin > editor > viewer
//   admin  - full access (content + users + settings)
//   editor - manage all content but cannot access users or settings
//   viewer - read-only; all write API calls are rejected server-side

function current_user_role(): string
{
    return $_SESSION['user']['role'] ?? 'viewer';
}

function user_is_admin(): bool
{
    return current_user_role() === 'admin';
}

function user_is_editor_or_above(): bool
{
    return in_array(current_user_role(), ['admin', 'editor'], true);
}

function require_admin(): void
{
    if (!user_is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Administrator access required.']);
        exit;
    }
}

function require_editor(): void
{
    if (!user_is_editor_or_above()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Editor access required.']);
        exit;
    }
}
