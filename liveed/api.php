<?php
// File: api.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($requestMethod, ['GET', 'HEAD', 'OPTIONS'], true)) {
    verify_csrf_token();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function respond_ok($data = null, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => true, 'data' => $data]);
}

function respond_error(string $code, string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ]);
}

switch ($action) {
    case 'list-blocks':
        $blocksDir = __DIR__ . '/../theme/templates/blocks';
        $blocks = [];
        if (is_dir($blocksDir)) {
            $paths = glob($blocksDir . '/*.php');
            if ($paths !== false) {
                $blocks = array_map('basename', $paths);
                sort($blocks, SORT_STRING);
            }
        }
        respond_ok(['blocks' => $blocks]);
        break;

    case 'load-block':
        $block = isset($_GET['file']) ? basename($_GET['file']) : '';
        $blockPath = realpath(__DIR__ . '/../theme/templates/blocks/' . $block);
        $base = realpath(__DIR__ . '/../theme/templates/blocks');
        if (!$block || !$base || !$blockPath || strpos($blockPath, $base) !== 0 || !file_exists($blockPath)) {
            respond_error('BLOCK_NOT_FOUND', 'Requested block template was not found.', 404);
            break;
        }
        $contents = file_get_contents($blockPath);
        if ($contents === false) {
            respond_error('BLOCK_READ_FAILED', 'Unable to read the requested block template.', 500);
            break;
        }
        respond_ok(['html' => $contents]);
        break;

    case 'load-draft':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            respond_error('INVALID_ID', 'Invalid ID.', 400);
            break;
        }
        $draft = load_page_draft($id);
        if (!is_array($draft)) {
            respond_ok(['id' => $id, 'content' => '', 'timestamp' => 0]);
            break;
        }
        $draft['content'] = sanitize_html((string) ($draft['content'] ?? ''));
        respond_ok($draft);
        break;

    case 'save-content':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $pagesFile = __DIR__ . '/../CMS/data/pages.json';
        $pages = read_json_file($pagesFile);
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $content = sanitize_html($_POST['content'] ?? '');
        if (!$id) {
            respond_error('INVALID_ID', 'Invalid ID.', 400);
            break;
        }
        foreach ($pages as &$p) {
            if ((int) $p['id'] === $id) {
                $p['content'] = $content;
                $p['last_modified'] = time();
                break;
            }
        }
        unset($p);
        write_json_file($pagesFile, $pages);
        require_once __DIR__ . '/../CMS/modules/sitemap/generate.php';
        delete_page_draft($id);
        respond_ok(['id' => $id, 'saved' => true]);
        break;

    case 'save-draft':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $content = sanitize_html($_POST['content'] ?? '');
        $timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : time();
        if (!$id) {
            respond_error('INVALID_ID', 'Invalid ID.', 400);
            break;
        }
        if (!save_page_draft($id, $content, $timestamp)) {
            respond_error('DRAFT_SAVE_FAILED', 'Unable to save draft.', 500);
            break;
        }
        respond_ok(['id' => $id, 'timestamp' => $timestamp, 'saved' => true]);
        break;

    default:
        respond_error('UNKNOWN_ACTION', 'Unknown action.', 400);
}
