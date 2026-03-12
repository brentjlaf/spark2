<?php
// File: modules/changelog/view.php  –  What's New / System Changelog
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

$file    = __DIR__ . '/../../data/changelog.json';
$entries = read_json_file($file);
if (!is_array($entries)) $entries = [];

// Sort: version desc, date desc
usort($entries, function ($a, $b) {
    $vc = version_compare($b['version'] ?? '0', $a['version'] ?? '0');
    if ($vc !== 0) return $vc;
    return strcmp($b['date'] ?? '', $a['date'] ?? '');
});

// Stats
$total        = count($entries);
$versions     = [];
$catCounts    = [];
$moduleCounts = [];
$latestVer    = '';
$latestDate   = '';
foreach ($entries as $e) {
    $ver = $e['version'] ?? '1.0.0';
    $cat = $e['category'] ?? 'feature';
    $mod = $e['module']   ?? 'system';
    if (!isset($versions[$ver])) $versions[$ver] = 0;
    $versions[$ver]++;
    $catCounts[$cat]    = ($catCounts[$cat]    ?? 0) + 1;
    $moduleCounts[$mod] = ($moduleCounts[$mod] ?? 0) + 1;
    if ($latestVer === '' || version_compare($ver, $latestVer, '>')) {
        $latestVer  = $ver;
        $latestDate = $e['date'] ?? '';
    }
}

$versionCount  = count($versions);
$featureCount  = $catCounts['feature']     ?? 0;
$improvCount   = $catCounts['improvement'] ?? 0;
$fixCount      = ($catCounts['fix'] ?? 0) + ($catCounts['security'] ?? 0);

$entriesJson   = htmlspecialchars(json_encode(array_values($entries), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');

$catLabels  = ['feature' => 'Feature', 'improvement' => 'Improvement', 'fix' => 'Fix', 'security' => 'Security'];
$moduleLabels = [
    'pages'       => 'Pages',        'blogs'    => 'Blogs',        'media'      => 'Media Library',
    'menus'       => 'Menus',        'forms'    => 'Forms',        'events'     => 'Events',
    'maps'        => 'Maps',         'users'    => 'Users',        'settings'   => 'Settings',
    'analytics'   => 'Analytics',    'seo'      => 'SEO',          'sitemap'    => 'Sitemap',
    'speed'       => 'Performance',  'accessibility' => 'Accessibility', 'search' => 'Search',
    'logs'        => 'Logs',         'commerce' => 'Commerce',     'dashboard'  => 'Dashboard',
    'system'      => 'System',
];
?>
<style>
/* ── Changelog module styles ──────────────────────────────────────────────── */
.cl-hero{background:linear-gradient(135deg,#1e1b4b 0%,#312e81 60%,#4f46e5 100%)}
.cl-hero-eyebrow{color:rgba(255,255,255,.72);font-size:.75rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;display:block;margin-bottom:.35rem}
.cl-hero-title{color:#fff;font-size:1.75rem;font-weight:800;margin:0 0 .4rem;letter-spacing:-.01em}
.cl-hero-subtitle{color:rgba(255,255,255,.8);margin:0;font-size:.9rem}
.cl-hero-content{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;padding:1.5rem 1.75rem 1rem}
.cl-hero-actions{display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;margin-top:.6rem}
.cl-overview-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1px;background:rgba(255,255,255,.15);border-top:1px solid rgba(255,255,255,.15)}
.cl-overview-card{background:rgba(0,0,0,.18);padding:1rem 1.25rem;text-align:center}
.cl-stat-label{font-size:.7rem;font-weight:600;color:rgba(255,255,255,.68);text-transform:uppercase;letter-spacing:.06em}
.cl-stat-value{font-size:1.6rem;font-weight:800;color:#fff;line-height:1.1;margin:.2rem 0 .15rem}
.cl-stat-hint{font-size:.72rem;color:rgba(255,255,255,.62)}

/* Toolbar */
.cl-section{padding:1.25rem 1.75rem}
.cl-toolbar{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1.1rem}
.cl-search{padding:.42rem .8rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem;min-width:220px;flex:1 1 200px}
.cl-search:focus{outline:none;border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.12)}
.cl-select{padding:.42rem .65rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem;background:#fff}
.cl-pill-row{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.85rem}
.cl-pill{padding:.28rem .7rem;border-radius:20px;border:1px solid #e5e7eb;background:#f9fafb;font-size:.76rem;font-weight:600;cursor:pointer;transition:background .12s,border-color .12s;color:#374151}
.cl-pill.active,.cl-pill:hover{background:#1e1b4b;color:#fff;border-color:#1e1b4b}
.cl-match{font-size:.8rem;color:#6b7280;margin-left:auto;flex-shrink:0}

/* Buttons */
.cl-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;border-radius:6px;font-size:.8rem;font-weight:600;border:none;cursor:pointer;transition:background .15s;text-decoration:none}
.cl-btn--primary{background:#4f46e5;color:#fff}.cl-btn--primary:hover{background:#4338ca}
.cl-btn--ghost{background:transparent;border:1px solid rgba(255,255,255,.45);color:#fff}.cl-btn--ghost:hover{background:rgba(255,255,255,.15)}
.cl-btn--sm{padding:.28rem .6rem;font-size:.74rem}
.cl-btn--danger{background:#ef4444;color:#fff}.cl-btn--danger:hover{background:#dc2626}
.cl-btn--edit{background:#f3f4f6;color:#374151;border:1px solid #e5e7eb}.cl-btn--edit:hover{background:#e5e7eb}

/* Version group */
.cl-version-group{margin-bottom:2rem}
.cl-version-header{display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:2px solid #e5e7eb}
.cl-version-badge{background:#1e1b4b;color:#fff;font-size:.72rem;font-weight:800;padding:.3rem .7rem;border-radius:6px;letter-spacing:.04em;font-family:monospace}
.cl-version-date{font-size:.8rem;color:#9ca3af}
.cl-version-count{font-size:.76rem;color:#6b7280;margin-left:auto}

/* Entry cards */
.cl-entry{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:1.1rem 1.3rem;margin-bottom:.75rem;transition:box-shadow .15s,border-color .15s;position:relative}
.cl-entry:hover{box-shadow:0 4px 16px rgba(0,0,0,.07);border-color:#c7d2fe}
.cl-entry-header{display:flex;align-items:flex-start;gap:.75rem;margin-bottom:.55rem;flex-wrap:wrap}
.cl-entry-badges{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;flex-shrink:0}
.cl-cat-badge{display:inline-flex;align-items:center;padding:.18rem .55rem;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
.cl-cat-badge--feature    {background:#ede9fe;color:#5b21b6}
.cl-cat-badge--improvement{background:#dbeafe;color:#1d4ed8}
.cl-cat-badge--fix        {background:#fef3c7;color:#92400e}
.cl-cat-badge--security   {background:#fee2e2;color:#991b1b}
.cl-mod-badge{display:inline-flex;align-items:center;padding:.18rem .55rem;border-radius:20px;font-size:.68rem;font-weight:600;background:#f3f4f6;color:#374151;white-space:nowrap}
.cl-entry-title{font-size:1rem;font-weight:700;color:#111827;margin:0;flex:1 1 auto}
.cl-entry-date{font-size:.75rem;color:#9ca3af;white-space:nowrap;margin-left:auto;flex-shrink:0}
.cl-entry-actions{display:flex;gap:.3rem;flex-shrink:0;margin-left:.5rem}

.cl-entry-desc{font-size:.875rem;color:#374151;line-height:1.6;margin:0 0 .7rem}

.cl-benefit-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:.6rem .9rem;margin-bottom:.7rem;display:flex;align-items:flex-start;gap:.5rem}
.cl-benefit-icon{color:#16a34a;flex-shrink:0;margin-top:.1rem;font-size:.85rem}
.cl-benefit-text{font-size:.825rem;color:#15803d;line-height:1.5;margin:0}

.cl-usage-toggle{display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;color:#4f46e5;background:none;border:none;cursor:pointer;padding:.1rem 0;margin-bottom:0}
.cl-usage-toggle i{font-size:.7rem;transition:transform .2s}
.cl-usage-toggle.open i{transform:rotate(90deg)}
.cl-usage-steps{display:none;margin:.45rem 0 0 0;padding:0;list-style:none}
.cl-usage-steps.open{display:block}
.cl-usage-steps li{font-size:.82rem;color:#374151;padding:.3rem 0 .3rem 1.4rem;position:relative;line-height:1.5}
.cl-usage-steps li::before{content:counter(step-counter);counter-increment:step-counter;position:absolute;left:0;top:.3rem;width:18px;height:18px;background:#ede9fe;color:#5b21b6;border-radius:50%;font-size:.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;line-height:1}
.cl-usage-steps{counter-reset:step-counter}

.cl-empty{padding:3rem 1.5rem;text-align:center;color:#9ca3af}
.cl-empty i{font-size:2.5rem;color:#d1d5db;display:block;margin-bottom:.75rem}

/* Modal */
.cl-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center}
.cl-modal-overlay.is-open{display:flex}
.cl-modal{background:#fff;border-radius:12px;width:min(700px,96vw);max-height:92vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.28)}
.cl-modal-header{padding:1.1rem 1.4rem;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between}
.cl-modal-title{font-size:1rem;font-weight:700;color:#111827;margin:0}
.cl-modal-close{background:none;border:none;font-size:1.1rem;color:#9ca3af;cursor:pointer;padding:.25rem .4rem;border-radius:4px}
.cl-modal-close:hover{color:#111827;background:#f3f4f6}
.cl-modal-body{padding:1.25rem 1.4rem;overflow-y:auto;flex:1}
.cl-modal-footer{padding:.9rem 1.4rem;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:.6rem}
.cl-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.85rem 1rem}
.cl-form-group{display:flex;flex-direction:column;gap:.3rem}
.cl-form-group.full{grid-column:1/-1}
.cl-form-label{font-size:.78rem;font-weight:600;color:#374151}
.cl-form-input,.cl-form-select,.cl-form-textarea{padding:.42rem .7rem;border:1px solid #d1d5db;border-radius:6px;font-size:.85rem;width:100%;box-sizing:border-box;background:#fff}
.cl-form-input:focus,.cl-form-select:focus,.cl-form-textarea:focus{outline:none;border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.cl-form-textarea{min-height:80px;resize:vertical}
.cl-form-hint{font-size:.71rem;color:#9ca3af}
.cl-form-section{font-size:.74rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;padding:.4rem 0 .15rem;border-bottom:1px solid #f3f4f6;grid-column:1/-1;margin:.15rem 0}
.cl-form-error{background:#fee2e2;color:#991b1b;padding:.5rem .8rem;border-radius:6px;font-size:.82rem;margin-bottom:.65rem;display:none}

/* Usage step list in modal */
.cl-steps-list{list-style:none;padding:0;margin:.35rem 0 0}
.cl-step-item{display:flex;align-items:flex-start;gap:.4rem;margin-bottom:.35rem}
.cl-step-input{flex:1;padding:.35rem .6rem;border:1px solid #d1d5db;border-radius:5px;font-size:.82rem}
.cl-step-input:focus{outline:none;border-color:#4f46e5}
.cl-step-remove{background:none;border:none;color:#ef4444;cursor:pointer;font-size:.85rem;padding:.2rem .3rem;flex-shrink:0}
.cl-add-step{background:none;border:1px dashed #c7d2fe;color:#4f46e5;font-size:.78rem;font-weight:600;border-radius:5px;padding:.3rem .6rem;cursor:pointer;width:100%;margin-top:.3rem;transition:background .12s}
.cl-add-step:hover{background:#ede9fe}

.cl-toast{position:fixed;bottom:1.5rem;right:1.5rem;background:#111827;color:#fff;padding:.65rem 1.1rem;border-radius:8px;font-size:.85rem;z-index:2000;display:none;box-shadow:0 4px 16px rgba(0,0,0,.25)}
.cl-toast.show{display:block;animation:clFadeUp .2s ease}
@keyframes clFadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:640px){.cl-form-grid{grid-template-columns:1fr}.cl-hero-content{padding:1rem 1.1rem .75rem}.cl-section{padding:1rem 1.1rem}}
</style>

<div class="content-section" id="changelog">
    <div class="cl-dashboard" data-entries="<?php echo $entriesJson; ?>">

        <!-- Hero -->
        <header class="cl-hero">
            <div class="cl-hero-content">
                <div>
                    <span class="cl-hero-eyebrow"><i class="fas fa-rocket" aria-hidden="true"></i> &nbsp;SparkCMS Updates</span>
                    <h2 class="cl-hero-title">What's New</h2>
                    <p class="cl-hero-subtitle">Every feature, improvement, and fix — with full details on what it does and how to use it.</p>
                </div>
                <div class="cl-hero-actions">
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.25rem;margin-top:.25rem">
                        <span style="color:rgba(255,255,255,.7);font-size:.75rem">Latest version</span>
                        <span style="color:#fff;font-size:1.1rem;font-weight:800;font-family:monospace">v<?php echo htmlspecialchars($latestVer, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($latestDate): ?>
                        <span style="color:rgba(255,255,255,.6);font-size:.72rem"><?php echo htmlspecialchars(date('M j, Y', strtotime($latestDate)), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="cl-overview-grid">
                <div class="cl-overview-card">
                    <div class="cl-stat-label">Total Updates</div>
                    <div class="cl-stat-value" id="clTotalCount"><?php echo $total; ?></div>
                    <div class="cl-stat-hint"><?php echo $versionCount; ?> release<?php echo $versionCount !== 1 ? 's' : ''; ?></div>
                </div>
                <div class="cl-overview-card">
                    <div class="cl-stat-label">New Features</div>
                    <div class="cl-stat-value"><?php echo $featureCount; ?></div>
                    <div class="cl-stat-hint">Brand-new capabilities</div>
                </div>
                <div class="cl-overview-card">
                    <div class="cl-stat-label">Improvements</div>
                    <div class="cl-stat-value"><?php echo $improvCount; ?></div>
                    <div class="cl-stat-hint">Enhanced existing tools</div>
                </div>
                <div class="cl-overview-card">
                    <div class="cl-stat-label">Fixes &amp; Security</div>
                    <div class="cl-stat-value"><?php echo $fixCount; ?></div>
                    <div class="cl-stat-hint">Bugs resolved</div>
                </div>
            </div>
        </header>

        <!-- Controls -->
        <section class="cl-section">
            <div class="cl-toolbar">
                <input type="text" class="cl-search" id="clSearch" placeholder="Search updates…" aria-label="Search changelog">
                <select class="cl-select" id="clModuleFilter" aria-label="Filter by module">
                    <option value="">All modules</option>
                    <?php foreach ($moduleLabels as $key => $label): ?>
                        <?php if (!empty($moduleCounts[$key])): ?>
                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $moduleCounts[$key]; ?>)</option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <select class="cl-select" id="clVersionFilter" aria-label="Filter by version">
                    <option value="">All versions</option>
                    <?php foreach (array_keys($versions) as $v): ?>
                        <option value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>">v<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="cl-match" id="clMatchCount"><?php echo $total === 1 ? '1 entry' : $total . ' entries'; ?></span>
            </div>
            <div class="cl-pill-row" id="clCatPills">
                <button class="cl-pill active" data-cat="">All</button>
                <button class="cl-pill" data-cat="feature"><i class="fas fa-star" style="font-size:.65rem"></i> Features</button>
                <button class="cl-pill" data-cat="improvement"><i class="fas fa-arrow-up" style="font-size:.65rem"></i> Improvements</button>
                <button class="cl-pill" data-cat="fix"><i class="fas fa-wrench" style="font-size:.65rem"></i> Fixes</button>
                <button class="cl-pill" data-cat="security"><i class="fas fa-shield" style="font-size:.65rem"></i> Security</button>
            </div>

            <!-- Entry timeline rendered by JS -->
            <div id="clTimeline">
<?php
// Group by version
$groups = [];
foreach ($entries as $e) {
    $v = $e['version'] ?? '1.0.0';
    $groups[$v][] = $e;
}
// Sort versions desc
uksort($groups, fn($a, $b) => version_compare($b, $a));

foreach ($groups as $ver => $group):
    $groupDate = $group[0]['date'] ?? '';
    $groupDateLabel = $groupDate ? date('F j, Y', strtotime($groupDate)) : '';
?>
                <div class="cl-version-group" data-version="<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="cl-version-header">
                        <span class="cl-version-badge">v<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($groupDateLabel): ?><span class="cl-version-date"><?php echo htmlspecialchars($groupDateLabel, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                        <span class="cl-version-count"><?php echo count($group); ?> update<?php echo count($group) !== 1 ? 's' : ''; ?></span>
                    </div>
<?php foreach ($group as $e):
    $eId    = (int)($e['id'] ?? 0);
    $eCat   = $e['category'] ?? 'feature';
    $eMod   = $e['module']   ?? 'system';
    $eTitle = $e['title']    ?? '';
    $eDesc  = $e['description'] ?? '';
    $eBen   = $e['benefit']     ?? '';
    $eDate  = $e['date']        ?? '';
    $eUsage = $e['usage']       ?? [];
    $eDateLabel = $eDate ? date('M j, Y', strtotime($eDate)) : '';
    $modLabel   = $moduleLabels[$eMod] ?? ucfirst($eMod);
    $catLabel   = $catLabels[$eCat]    ?? ucfirst($eCat);
    $searchText = strtolower($eTitle . ' ' . $eDesc . ' ' . $eBen . ' ' . $eMod . ' ' . $eCat);
?>
                    <div class="cl-entry"
                         data-id="<?php echo $eId; ?>"
                         data-cat="<?php echo htmlspecialchars($eCat, ENT_QUOTES, 'UTF-8'); ?>"
                         data-module="<?php echo htmlspecialchars($eMod, ENT_QUOTES, 'UTF-8'); ?>"
                         data-version="<?php echo htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'); ?>"
                         data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="cl-entry-header">
                            <div class="cl-entry-badges">
                                <span class="cl-cat-badge cl-cat-badge--<?php echo htmlspecialchars($eCat, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="cl-mod-badge"><i class="fas fa-puzzle-piece" style="font-size:.6rem;opacity:.6"></i> <?php echo htmlspecialchars($modLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <h4 class="cl-entry-title"><?php echo htmlspecialchars($eTitle, ENT_QUOTES, 'UTF-8'); ?></h4>
                            <?php if ($eDateLabel): ?><span class="cl-entry-date"><?php echo htmlspecialchars($eDateLabel, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                        </div>
                        <?php if ($eDesc): ?><p class="cl-entry-desc"><?php echo htmlspecialchars($eDesc, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <?php if ($eBen): ?>
                        <div class="cl-benefit-box">
                            <i class="fas fa-circle-check cl-benefit-icon" aria-hidden="true"></i>
                            <p class="cl-benefit-text"><strong>Why it matters:</strong> <?php echo htmlspecialchars($eBen, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($eUsage)): ?>
                        <button type="button" class="cl-usage-toggle" aria-expanded="false">
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            How to use
                        </button>
                        <ol class="cl-usage-steps">
                            <?php foreach ($eUsage as $step): ?>
                            <li><?php echo htmlspecialchars($step, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <?php endif; ?>
                    </div>
<?php endforeach; ?>
                </div>
<?php endforeach; ?>
<?php if (empty($entries)): ?>
                <div class="cl-empty">
                    <i class="fas fa-rocket" aria-hidden="true"></i>
                    <p>No changelog entries yet.</p>
                </div>
<?php endif; ?>
            </div>
        </section>
    </div>
</div>
