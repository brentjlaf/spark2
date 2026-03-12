<?php
// File: list_logs.php  –  returns merged audit + page-history log entries
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

// ── Helpers ──────────────────────────────────────────────────────────────────
function normalize_action_label(?string $action): string {
    $label = trim((string)($action ?? ''));
    return $label !== '' ? $label : 'Updated content';
}

function slugify_action_label(string $label): string {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label));
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : 'unknown';
}

$logs = [];

// ── Source 1: page_history.json ───────────────────────────────────────────────
$pagesFile  = __DIR__ . '/../../data/pages.json';
$pages      = read_json_file($pagesFile);
$pageLookup = [];
if (is_array($pages)) {
    foreach ($pages as $p) {
        if (isset($p['id'])) {
            $pageLookup[$p['id']] = $p['title'];
        }
    }
}

$historyFile = __DIR__ . '/../../data/page_history.json';
$historyData = read_json_file($historyFile);
if (is_array($historyData)) {
    foreach ($historyData as $pid => $entries) {
        if (!is_array($entries)) continue;
        foreach ($entries as $entry) {
            $actionLabel = normalize_action_label($entry['action'] ?? '');
            $context     = $entry['context'] ?? (is_numeric($pid) ? 'page' : 'system');
            $details     = $entry['details'] ?? [];
            if (!is_array($details)) {
                $details = $details !== '' ? [$details] : [];
            }
            $pageTitle = $entry['page_title'] ?? null;
            if ($pageTitle === null) {
                $pageTitle = $context === 'system' ? 'System activity' : ($pageLookup[$pid] ?? 'Unknown');
            }
            $logs[] = [
                'time'        => (int)($entry['time'] ?? 0),
                'user'        => $entry['user'] ?? '',
                'page_title'  => $pageTitle,
                'action'      => $actionLabel,
                'action_slug' => slugify_action_label($actionLabel),
                'details'     => $details,
                'context'     => $context,
                'source'      => 'history',
                'meta'        => $entry['meta'] ?? new stdClass(),
            ];
        }
    }
}

// ── Source 2: audit_log.json ──────────────────────────────────────────────────
$auditFile = __DIR__ . '/../../data/audit_log.json';
$auditData = read_json_file($auditFile);
if (is_array($auditData)) {
    foreach ($auditData as $entry) {
        if (!is_array($entry)) continue;
        $actionLabel = normalize_action_label($entry['action'] ?? '');
        $context     = $entry['context'] ?? 'system';
        $details     = $entry['details'] ?? [];
        if (!is_array($details)) {
            $details = $details !== '' ? [$details] : [];
        }
        $logs[] = [
            'time'        => (int)($entry['time'] ?? 0),
            'user'        => $entry['user'] ?? '',
            'page_title'  => $entry['subject'] ?? 'System activity',
            'action'      => $actionLabel,
            'action_slug' => slugify_action_label($actionLabel),
            'details'     => $details,
            'context'     => $context,
            'source'      => 'audit',
            'meta'        => new stdClass(),
        ];
    }
}

// ── Sort newest first ─────────────────────────────────────────────────────────
usort($logs, function ($a, $b) { return $b['time'] <=> $a['time']; });

header('Content-Type: application/json');
echo json_encode($logs);
