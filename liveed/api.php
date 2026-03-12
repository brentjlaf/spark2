<?php
// File: api.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function respond_ok($data = null)
{
    echo json_encode(['ok' => true, 'data' => $data]);
}

function respond_error($code, $message, $status = 400)
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

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
        if ($blockPath && strpos($blockPath, $base) === 0 && file_exists($blockPath)) {
            $html = file_get_contents($blockPath);
            respond_ok(['content' => $html === false ? '' : $html]);
        } else {
            respond_error('NOT_FOUND', 'Block template not found', 404);
        }
        break;

    case 'load-draft':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            respond_error('INVALID_ID', 'Invalid ID', 400);
            break;
        }
        $draft = load_page_draft($id);
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
            respond_error('INVALID_ID', 'Invalid ID', 400);
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
        respond_ok(['saved' => true]);
        break;

    case 'save-draft':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $content = sanitize_html($_POST['content'] ?? '');
        $timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : time();
        if (!$id) {
            respond_error('INVALID_ID', 'Invalid ID', 400);
            break;
        }
        if (!save_page_draft($id, $content, $timestamp)) {
            respond_error('SAVE_FAILED', 'Unable to save draft', 500);
            break;
        }
        respond_ok(['saved' => true]);
        break;

    default:
        respond_error('UNKNOWN_ACTION', 'Unknown action', 400);
}
