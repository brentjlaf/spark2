<?php
// File: builder.php
require_once __DIR__ . '/../CMS/includes/auth.php';
require_once __DIR__ . '/../CMS/includes/data.php';
require_once __DIR__ . '/../CMS/includes/settings.php';
require_once __DIR__ . '/../CMS/includes/sanitize.php';
require_login();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$pagesFile = __DIR__ . '/../CMS/data/pages.json';
$pages = read_json_file($pagesFile);
$page = null;
foreach ($pages as $p) {
    if ((int)$p['id'] === $id) { $page = $p; break; }
}
if (!$page) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

$scriptBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if (substr($scriptBase, -7) === '/liveed') {
    $scriptBase = substr($scriptBase, 0, -7);
}
$themeBase = $scriptBase . '/theme';

// Load settings and menus for the theme template
$settings = get_site_settings();
$menusFile = __DIR__ . '/../CMS/data/menus.json';
$menus = read_json_file($menusFile);

// Render the theme page template with a canvas placeholder
$templateFile = realpath(__DIR__ . '/../theme/templates/pages/page.php');
ob_start();
include $templateFile;
$themeHtml = ob_get_clean();
$placeholderText = !empty($settings['canvas_placeholder'])
    ? htmlspecialchars($settings['canvas_placeholder'])
    : 'Drag blocks from the palette to start building your page';
$canvasPlaceholder = '
<div class="canvas-placeholder" role="status">
  <div class="placeholder-inner">
    <div class="placeholder-body">
      <div class="placeholder-intro">
        <i class="fa-regular fa-hand-point-up" aria-hidden="true"></i>
        <div class="intro-text">' . $placeholderText . '</div>
      </div>
      <ol class="placeholder-steps">
        <li><strong>Step 1:</strong> Drag blocks from the palette onto this canvas.</li>
        <li><strong>Step 2:</strong> Click the pencil icon on a block to edit its content.</li>
        <li><strong>Step 3:</strong> Use <kbd>Ctrl</kbd>+<kbd>Z</kbd> / <kbd>Ctrl</kbd>+<kbd>Y</kbd> to undo or redo changes.</li>
      </ol>
    </div>
    <button type="button" class="placeholder-helper-toggle" data-helper-toggle aria-expanded="false">
      <i class="fa-regular fa-circle-play" aria-hidden="true"></i>
      <span>Watch how</span>
    </button>
    <div class="placeholder-helper" hidden>
      <div class="helper-heading">
        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
        Quick tips
      </div>
      <ul class="helper-list">
        <li>Drop a layout block first, then add text or media inside it.</li>
        <li>Hover any block to reveal the pencil, drag, duplicate, or delete controls.</li>
        <li>Undo/redo anytime with <kbd>Ctrl</kbd>+<kbd>Z</kbd> and <kbd>Ctrl</kbd>+<kbd>Y</kbd>.</li>
      </ul>
    </div>
  </div>
</div>';
$canvasContent = $page['content'] ? sanitize_html((string) $page['content']) : $canvasPlaceholder;
$themeHtml = preg_replace('/<div class="drop-area"><\\/div>/', '<div id="canvas" class="canvas">' . $canvasContent . '</div>', $themeHtml);

$cssFiles = [
    'builder-core.css',
    'builder-settings.css',
    'builder-palette.css',
    'builder-modal.css',
    'builder-media.css',
    'builder-view.css',
];
$headInject = '';
foreach ($cssFiles as $css) {
    $headInject .= "<link rel=\"stylesheet\" href=\"{$scriptBase}/liveed/css/$css\">";
}
$headInject .= "<link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css\"/>";
$themeHtml = preg_replace('/<head>/', '<head>' . $headInject, $themeHtml, 1);

$builderHeader = '<header class="builder-header" title="Drag to reposition">'
    . '<div  class="title-top"><div class="title">Editing: ' . htmlspecialchars($page['title']) . '</div> '
    . '<button type="button" class="manual-save-btn btn btn-primary"><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i><span class="btn-label">Save</span></button></div>'
    . '<div id="saveStatus" class="save-status"></div>'
    . '</header>';

$paletteFooter = '<div class="footer"><div class="action-row">'
    . '<button class="action-btn undo-btn"><i class="fas fa-undo"></i><span>Undo</span></button>'
    . '<button class="action-btn redo-btn"><i class="fas fa-redo"></i><span>Redo</span></button>'
    . '</div><div class="footer-links">'
    . '<span id="lastSavedTime" class="last-saved-time">Last saved: ' . date('Y-m-d H:i', $page['last_modified']) . '</span>'
    . '</div></div>';

$builderStart = '<div class="builder"><button type="button" id="viewModeToggle" class="view-toggle" title="View mode"><i class="fa-solid fa-eye"></i></button><aside class="block-palette">'
    . $builderHeader
    . '<h2 class="blocks-title">Blocks</h2><div class="palette-search-container"><i class="fa-solid fa-search search-icon"></i><input type="text" class="palette-search" placeholder="Search blocks"></div><div class="palette-items"></div>'
    . $paletteFooter
    . '</aside><main class="canvas-container">';

$mediaPickerHtml = '<div id="mediaPickerModal" class="modal">'
    . '<div class="modal-content media-picker">'
    . '<div class="picker-sidebar"><ul id="pickerFolderList"></ul></div>'
    . '<div class="picker-main"><div id="pickerImageGrid" class="picker-grid"></div>'
    . '<div class="modal-footer"><button type="button" class="btn btn-secondary" id="mediaPickerClose"><i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i><span class="btn-label">Close</span></button></div>'
    . '</div></div></div>'
    . '<div id="pickerEditModal" class="modal">'
    . '<div class="modal-content"><div class="crop-container"><img id="pickerEditImage" src="" style="max-width:100%;"></div>'
    . '<div class="modal-footer"><input type="range" id="pickerScale" min="0.5" max="3" step="0.1" value="1">'
    . '<button class="btn btn-secondary" id="pickerEditCancel"><i class="fa-solid fa-circle-xmark btn-icon" aria-hidden="true"></i><span class="btn-label">Cancel</span></button>'
    . '<button class="btn btn-primary" id="pickerEditSave"><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i><span class="btn-label">Save</span></button></div>'
    . '</div></div>'
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">'
    . '<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>';

$builderEnd = '</main><div id="settingsPanel" class="settings-panel"><div class="settings-header"><span class="title">Settings</span><button type="button" class="close-btn">&times;</button></div><div class="settings-content"></div></div>'
    . $mediaPickerHtml . '</div>'
    . '<script>window.builderPageId = ' . json_encode($page['id']) . ';window.builderBase = ' . json_encode($scriptBase) . ';window.builderSlug = ' . json_encode($page['slug']) . ';window.builderLastModified = ' . json_encode($page['last_modified']) . ';</script>'
    . '<script type="module" src="' . $scriptBase . '/liveed/builder.js"></script>'
    . '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>';

$themeHtml = preg_replace('/<body([^>]*)>/', '<body$1>' . $builderStart, $themeHtml, 1);
$themeHtml = preg_replace('/<\/body>/', $builderEnd . '</body>', $themeHtml, 1);

echo $themeHtml;
