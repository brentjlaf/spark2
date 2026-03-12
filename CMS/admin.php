<?php
// File: admin.php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/search_helpers.php';
require_login();

$pagesFile = __DIR__ . '/data/pages.json';
$pages = get_cached_json($pagesFile);

$settings = get_site_settings();
$globalSearchHistory = get_search_history();
$globalSearchSuggestions = get_search_suggestions();
$historyAttr = htmlspecialchars(json_encode($globalSearchHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$suggestionsAttr = htmlspecialchars(json_encode($globalSearchSuggestions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
$adminFavicon = 'images/favicon.png';
$faviconSetting = $settings['favicon'] ?? '';
if (is_string($faviconSetting) && $faviconSetting !== '' && preg_match('#^https?://#i', $faviconSetting)) {
    $adminFavicon = $faviconSetting;
} elseif (!empty($settings['favicon'])) {
    $adminFavicon = ltrim($settings['favicon'], '/');
}

// Feature 15: Cache-busting helper — appends ?v=<hex-mtime> to local asset URLs.
function sparkcms_asset_v(string $relPath): string {
    static $cache = [];
    if (isset($cache[$relPath])) {
        return $cache[$relPath];
    }
    $abs = __DIR__ . '/' . ltrim($relPath, '/\\');
    $mt  = @filemtime($abs);
    $cache[$relPath] = $mt ? '?v=' . sprintf('%x', $mt) : '';
    return $cache[$relPath];
}

// Pre-compute per-module JS versions so the dynamic $.getScript() call can
// append the correct ?v= string without a round-trip to the server.
$_sparkCmsModules = [
    'accessibility', 'analytics', 'blogs', 'changelog', 'commerce', 'dashboard', 'events',
    'forms', 'logs', 'maps', 'media', 'menus', 'pages',
    'search', 'seo', 'settings', 'sitemap', 'speed', 'users',
];
$_sparkCmsModuleVersions = [];
foreach ($_sparkCmsModules as $_mod) {
    $abs = __DIR__ . '/modules/' . $_mod . '/' . $_mod . '.js';
    $mt  = @filemtime($abs);
    $_sparkCmsModuleVersions[$_mod] = $mt ? sprintf('%x', $mt) : '';
}
unset($_mod, $abs, $mt, $_sparkCmsModules);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars(($settings['site_name'] ?? 'SparkCMS') . ' Admin Dashboard'); ?></title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token()); ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="modal-utils.js<?php echo sparkcms_asset_v('modal-utils.js'); ?>"></script>
    <script src="notifications.js<?php echo sparkcms_asset_v('notifications.js'); ?>"></script>
    <script src="modules/search/search.js<?php echo sparkcms_asset_v('modules/search/search.js'); ?>"></script>
    <?php include __DIR__ . '/includes/admin_assets.php'; ?>
    <link rel="stylesheet" href="spark-cms.css<?php echo sparkcms_asset_v('spark-cms.css'); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($adminFavicon); ?>" />

</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="images/logo.png" alt="Logo" style="height:40px;">
                <?php else: ?>
                    <?php echo htmlspecialchars(($settings['site_name'] ?? 'SparkCMS') . ' Admin'); ?>
                <?php endif; ?>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Overview</div>
                    <div class="nav-item active" data-section="dashboard">
                        <div class="nav-icon"><i class="fas fa-tachometer-alt"></i></div>
                        <div class="nav-text">Dashboard</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Content</div>
                    <div class="nav-item" data-section="pages">
                        <div class="nav-icon"><i class="far fa-file-alt"></i></div>
                        <div class="nav-text">Pages</div>
                    </div>
                    <div class="nav-item" data-section="blogs">
                        <div class="nav-icon"><i class="fas fa-blog"></i></div>
                        <div class="nav-text">Blogs</div>
                    </div>
                    <div class="nav-item" data-section="events">
                        <div class="nav-icon"><i class="fas fa-ticket"></i></div>
                        <div class="nav-text">Events</div>
                    </div>
                    <div class="nav-item" data-section="commerce">
                        <div class="nav-icon"><i class="fas fa-store"></i></div>
                        <div class="nav-text">Commerce</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Site</div>
                    <div class="nav-item" data-section="media">
                        <div class="nav-icon"><i class="fas fa-images"></i></div>
                        <div class="nav-text">Media Library</div>
                    </div>
                    <div class="nav-item" data-section="maps">
                        <div class="nav-icon"><i class="fas fa-map-location-dot"></i></div>
                        <div class="nav-text">Maps</div>
                    </div>
                    <div class="nav-item" data-section="menus">
                        <div class="nav-icon"><i class="fas fa-bars"></i></div>
                        <div class="nav-text">Menus</div>
                    </div>
                    <div class="nav-item" data-section="forms">
                        <div class="nav-icon"><i class="fas fa-clipboard"></i></div>
                        <div class="nav-text">Forms</div>
                    </div>
                </div>

                <?php if (user_is_admin()): ?>
                <div class="nav-section">
                    <div class="nav-section-title">Administration</div>
                    <div class="nav-item" data-section="users">
                        <div class="nav-icon"><i class="fas fa-users"></i></div>
                        <div class="nav-text">Users</div>
                    </div>
                    <div class="nav-item" data-section="settings">
                        <div class="nav-icon"><i class="fas fa-cog"></i></div>
                        <div class="nav-text">Settings</div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <div class="nav-item" data-section="analytics">
                        <div class="nav-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="nav-text">Analytics</div>
                    </div>
                    <div class="nav-item" data-section="seo">
                        <div class="nav-icon"><i class="fas fa-search"></i></div>
                        <div class="nav-text">SEO</div>
                    </div>
                    <div class="nav-item" data-section="logs">
                        <div class="nav-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div class="nav-text">Logs</div>
                    </div>
                    <div class="nav-item" data-section="speed">
                        <div class="nav-icon"><i class="fas fa-gauge-high"></i></div>
                        <div class="nav-text">Performance</div>
                    </div>
                    <div class="nav-item" data-section="accessibility">
                        <div class="nav-icon"><i class="fas fa-universal-access"></i></div>
                        <div class="nav-text">Accessibility</div>
                    </div>
                    <div class="nav-item" data-section="changelog">
                        <div class="nav-icon"><i class="fas fa-rocket"></i></div>
                        <div class="nav-text">What's New</div>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-item" data-section="logout">
                        <div class="nav-icon"><i class="fas fa-sign-out-alt"></i></div>
                        <div class="nav-text"><a href="logout.php" style="color: inherit; text-decoration: none;">Logout</a></div>
                    </div>
                </div>
            </nav>
        </div>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="page-title" id="pageTitle">Dashboard</div>
                <div class="top-bar-actions">
                    <div class="search-box" data-search-history="<?php echo $historyAttr; ?>" data-search-suggestions="<?php echo $suggestionsAttr; ?>">
                        <input type="text" class="search-input" placeholder="Search...">
                        <div class="search-icon"><i class="fas fa-search"></i></div>
                    </div>
                    <div class="user-menu">
                        <?php
                        $currentUser = $_SESSION['user'];
                        $initials = strtoupper(substr($currentUser['username'] ?? 'U', 0, 2));
                        $displayRole = ucfirst($currentUser['role'] ?? 'viewer');
                        ?>
                        <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($currentUser['username'] ?? ''); ?></div>
                            <div class="user-role" style="font-size:11px;opacity:0.7;"><?php echo htmlspecialchars($displayRole); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area" id="contentContainer"></div>
        </div>
    <script>window.SPARKCMS_ASSET_VERSIONS=<?php echo json_encode($_sparkCmsModuleVersions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
    <script>
// Send CSRF token with every jQuery AJAX request
$.ajaxSetup({
  headers: { 'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content') }
});
$(function(){
  function parseDataAttr(value){
    if(!value){ return []; }
    try { return JSON.parse(value); }
    catch(error){ return []; }
  }

  function loadModule(section, params){
    var url = "modules/"+section+"/view.php";
    if(params){ url += "?" + params; }
    $("#contentContainer").load(url, function(){
      $("#contentContainer .content-section").addClass("active");
      if(section === 'search' && window.SparkSearch){
        window.SparkSearch.bootstrapFromModule($("#contentContainer #search"));
      } else {
        var _jsV = (window.SPARKCMS_ASSET_VERSIONS && window.SPARKCMS_ASSET_VERSIONS[section])
            ? '?v=' + window.SPARKCMS_ASSET_VERSIONS[section] : '';
        $.getScript("modules/"+section+"/"+section+".js"+_jsV).fail(function(){});
      }
    });
  }
  window.loadModule = loadModule;
  $(".nav-item").click(function(){
    var section=$(this).data("section");
    if(typeof section === 'string'){ section = section.trim(); }
    if(section==="logout"){ window.location="logout.php"; return; }
    $(".nav-item").removeClass("active");
    $(this).addClass("active");
    $("#pageTitle").text($(this).find(".nav-text").text());
    loadModule(section);
    if(window.innerWidth <= 1024){
        $("#sidebar").removeClass("mobile-open");
        $("#sidebarOverlay").removeClass("active");
    }
  });

  var $searchBox = $('.search-box');
  var $searchInput = $searchBox.find('.search-input');
  if(window.SparkSearch){
    window.SparkSearch.mount({
      input: $searchInput,
      container: $searchBox,
      history: parseDataAttr($searchBox.attr('data-search-history')),
      suggestions: parseDataAttr($searchBox.attr('data-search-suggestions')),
      onSubmit: function(query){
        $(".nav-item").removeClass("active");
        loadModule('search','q='+encodeURIComponent(query));
      }
    });
  } else {
    $searchInput.on('keypress', function(e){
      if(e.which===13){
        e.preventDefault();
        var q = $(this).val();
        if(q.trim()!==""){
          $(".nav-item").removeClass("active");
          loadModule('search','q='+encodeURIComponent(q));
        }
      }
    });
  }

  $('#menuToggle').on('click', function(){
    $('#sidebar').toggleClass('mobile-open');
    $('#sidebarOverlay').toggleClass('active');
  });

  $('#sidebarOverlay').on('click', function(){
    $('#sidebar').removeClass('mobile-open');
    $(this).removeClass('active');
  });

  var moduleTitles = {
    search: 'Manage search index',
    sitemap: 'Review sitemap',
    events: 'Event management overview',
    maps: 'Location directory'
  };

  $(document).on('sparkcms:navigate', function(event, data){
    if(!data || typeof data.section !== 'string'){ return; }
    var section = data.section.trim();
    if(section === ''){ return; }

    var $targetNav = $(".nav-item[data-section='" + section + "']");
    if($targetNav.length){
      $targetNav.trigger('click');
      return;
    }

    if(typeof loadModule !== 'function'){ return; }

    $(".nav-item").removeClass("active");

    var title = moduleTitles[section];
    if(!title){
      title = section.replace(/_/g, ' ');
      title = title.replace(/\b\w/g, function(char){ return char.toUpperCase(); });
    }
    $('#pageTitle').text(title);

    loadModule(section);

    if(window.innerWidth <= 1024){
      $('#sidebar').removeClass('mobile-open');
      $('#sidebarOverlay').removeClass('active');
    }
  });
  loadModule("dashboard");
});
    </script>
</div>

<footer class="footer">
    © 2025 SparkCMS
</footer>

</body>
</html>
