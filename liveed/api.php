<?php
// File: api.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_login();

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
        header('Content-Type: application/json');
        echo json_encode(['blocks' => $blocks]);
        break;

    case 'load-block':
        $block = isset($_GET['file']) ? basename($_GET['file']) : '';
        $blockPath = realpath(__DIR__ . '/../theme/templates/blocks/' . $block);
        $base = realpath(__DIR__ . '/../theme/templates/blocks');
        if ($blockPath && strpos($blockPath, $base) === 0 && file_exists($blockPath)) {
            readfile($blockPath);
        }
        break;

    case 'load-draft':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$id) {
            http_response_code(400);
            echo 'Invalid ID';
            break;
        }
        $draft = load_page_draft($id);
        $draft['content'] = sanitize_html((string) ($draft['content'] ?? ''));
        header('Content-Type: application/json');
        echo json_encode($draft);
        break;

    case 'save-content':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $pagesFile = __DIR__ . '/../CMS/data/pages.json';
        $pages = read_json_file($pagesFile);
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $content = sanitize_html($_POST['content'] ?? '');
        if (!$id) {
            http_response_code(400);
            echo 'Invalid ID';
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
        echo 'OK';
        break;

    case 'save-draft':
        require_once __DIR__ . '/../CMS/includes/data.php';
        require_once __DIR__ . '/../CMS/includes/sanitize.php';
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $content = sanitize_html($_POST['content'] ?? '');
        $timestamp = isset($_POST['timestamp']) ? intval($_POST['timestamp']) : time();
        if (!$id) {
            http_response_code(400);
            echo 'Invalid ID';
            break;
        }
        if (!save_page_draft($id, $content, $timestamp)) {
            http_response_code(500);
            echo 'Unable to save draft';
            break;
        }
        echo 'OK';
        break;

    default:
        http_response_code(400);
        echo 'Unknown action';
}
