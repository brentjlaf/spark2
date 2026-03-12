<?php
// File: view.php  –  Activity audit log dashboard
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/data.php';
require_login();

// ── Helpers ───────────────────────────────────────────────────────────────────
function normalize_action_label(?string $action): string {
    $label = trim((string)($action ?? ''));
    return $label !== '' ? $label : 'Updated content';
}

function slugify_action_label(string $label): string {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label));
    $slug = trim((string)$slug, '-');
    return $slug !== '' ? $slug : 'unknown';
}

function describe_time_ago(?int $timestamp, bool $forHero = false): string {
    if (!$timestamp) return $forHero ? 'No activity yet' : 'Unknown time';
    $diff = time() - $timestamp;
    if ($diff < 0)      return 'Scheduled update';
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   { $m = (int)floor($diff / 60);   return $m . ' min' . ($m === 1 ? '' : 's') . ' ago'; }
    if ($diff < 86400)  { $h = (int)floor($diff / 3600); return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago'; }
    if ($diff < 604800) { $d = (int)floor($diff / 86400); return $d . ' day' . ($d === 1 ? '' : 's') . ' ago'; }
    return date('M j, Y g:i A', $timestamp);
}

// ── Source 1: page_history.json ───────────────────────────────────────────────
$pagesFile  = __DIR__ . '/../../data/pages.json';
$pages      = read_json_file($pagesFile);
$pageLookup = [];
if (is_array($pages)) {
    foreach ($pages as $p) {
        if (isset($p['id'])) $pageLookup[$p['id']] = $p['title'];
    }
}

$historyData = read_json_file(__DIR__ . '/../../data/page_history.json');
$logs = [];

if (is_array($historyData)) {
    foreach ($historyData as $pid => $entries) {
        if (!is_array($entries)) continue;
        foreach ($entries as $entry) {
            $actionLabel = normalize_action_label($entry['action'] ?? '');
            $context     = $entry['context'] ?? (is_numeric($pid) ? 'page' : 'system');
            $details     = $entry['details'] ?? [];
            if (!is_array($details)) $details = $details !== '' ? [$details] : [];
            $pageTitle   = $entry['page_title'] ?? ($context === 'system' ? 'System activity' : ($pageLookup[$pid] ?? 'Unknown'));
            $logs[] = [
                'time'        => (int)($entry['time'] ?? 0),
                'user'        => $entry['user'] ?? '',
                'page_title'  => $pageTitle,
                'action'      => $actionLabel,
                'action_slug' => slugify_action_label($actionLabel),
                'details'     => $details,
                'context'     => $context,
                'meta'        => $entry['meta'] ?? new stdClass(),
            ];
        }
    }
}

// ── Source 2: audit_log.json ──────────────────────────────────────────────────
$auditData = read_json_file(__DIR__ . '/../../data/audit_log.json');
if (is_array($auditData)) {
    foreach ($auditData as $entry) {
        if (!is_array($entry)) continue;
        $actionLabel = normalize_action_label($entry['action'] ?? '');
        $context     = $entry['context'] ?? 'system';
        $details     = $entry['details'] ?? [];
        if (!is_array($details)) $details = $details !== '' ? [$details] : [];
        $logs[] = [
            'time'        => (int)($entry['time'] ?? 0),
            'user'        => $entry['user'] ?? '',
            'page_title'  => $entry['subject'] ?? 'System activity',
            'action'      => $actionLabel,
            'action_slug' => slugify_action_label($actionLabel),
            'details'     => $details,
            'context'     => $context,
            'meta'        => new stdClass(),
        ];
    }
}

usort($logs, fn($a, $b) => $b['time'] <=> $a['time']);

// ── Stats ─────────────────────────────────────────────────────────────────────
$now            = time();
$totalLogs      = count($logs);
$lastActivity   = $totalLogs > 0 ? $logs[0]['time'] : null;
$last24Hours    = 0;
$last7Days      = 0;
$uniqueUsers    = [];
$uniqueSubjects = [];
$actionsSummary = [];
$userOptions    = [];
$contextCounts  = [];

foreach ($logs as $log) {
    $ts = (int)$log['time'];
    if ($ts >= $now - 86400)  $last24Hours++;
    if ($ts >= $now - 604800) $last7Days++;

    $uName = trim((string)($log['user'] ?? ''));
    $uKey  = strtolower($uName);
    if ($uName !== '') {
        $uniqueUsers[$uKey] = true;
        $userOptions[$uKey] = $userOptions[$uKey] ?? $uName;
    } else {
        $userOptions['system'] = 'System';
    }

    $subKey = strtolower(trim($log['page_title']));
    if ($subKey !== '') $uniqueSubjects[$subKey] = true;

    $slug = $log['action_slug'];
    if (!isset($actionsSummary[$slug])) {
        $actionsSummary[$slug] = ['label' => $log['action'], 'slug' => $slug, 'count' => 0];
    }
    $actionsSummary[$slug]['count']++;

    $ctx = $log['context'] ?? 'system';
    $contextCounts[$ctx] = ($contextCounts[$ctx] ?? 0) + 1;
}

usort($actionsSummary, fn($a, $b) => $b['count'] <=> $a['count']);
uasort($userOptions, fn($a, $b) => strcasecmp($a, $b));
if (isset($userOptions['system'])) {
    $sys = $userOptions['system'];
    unset($userOptions['system']);
    $userOptions = ['system' => $sys] + $userOptions;
}

$topActions         = array_slice($actionsSummary, 0, 4);
$topAction          = $actionsSummary[0] ?? null;
$logsJson           = htmlspecialchars(json_encode($logs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
$matchCountLabel    = $totalLogs === 1 ? '1 entry' : ($totalLogs > 0 ? $totalLogs . ' entries' : 'No entries to display');
$uniqueUsersCount   = count($uniqueUsers);
$uniqueSubjectCount = count($uniqueSubjects);
$lastActivityLabel  = describe_time_ago($lastActivity, true);
$lastActivityExact  = $lastActivity ? date('M j, Y g:i A', $lastActivity) : 'No recent activity';
$recentSubject      = $totalLogs > 0 ? htmlspecialchars($logs[0]['page_title'], ENT_QUOTES, 'UTF-8') : '';
$topActionLabel     = $topAction ? htmlspecialchars($topAction['label'], ENT_QUOTES, 'UTF-8') : '—';
$topActionCountText = $topAction ? $topAction['count'] . ' entries' : 'No recorded actions yet';
$editorsHint        = $uniqueUsersCount === 0 ? 'No editor activity yet'
                    : ($uniqueUsersCount === 1 ? 'Team member contributing recently' : 'Editors with recent changes');
?>
<style>
.logs-ctx-badge{display:inline-flex;align-items:center;padding:.15rem .5rem;border-radius:12px;font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}
.logs-ctx-badge--page{background:#dbeafe;color:#1d4ed8}
.logs-ctx-badge--post{background:#ede9fe;color:#6d28d9}
.logs-ctx-badge--media{background:#ccfbf1;color:#0f766e}
.logs-ctx-badge--user{background:#ffedd5;color:#c2410c}
.logs-ctx-badge--settings{background:#f3f4f6;color:#374151}
.logs-ctx-badge--commerce{background:#d1fae5;color:#065f46}
.logs-ctx-badge--system{background:#fee2e2;color:#991b1b}
.logs-ctx-badge--default{background:#f3f4f6;color:#6b7280}
.logs-context-filters{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-bottom:.4rem}
.logs-ctx-filter-btn{padding:.28rem .65rem;border-radius:20px;border:1px solid #e5e7eb;background:#f9fafb;font-size:.75rem;font-weight:600;cursor:pointer;transition:background .12s,border-color .12s;color:#374151}
.logs-ctx-filter-btn.active,.logs-ctx-filter-btn:hover{background:#111827;color:#fff;border-color:#111827}
.logs-search-wrap{flex:1 1 180px;min-width:0}
.logs-search-input{width:100%;padding:.38rem .75rem;border:1px solid #d1d5db;border-radius:6px;font-size:.85rem;box-sizing:border-box}
.logs-search-input:focus{outline:none;border-color:#374151;box-shadow:0 0 0 3px rgba(55,65,81,.1)}
.logs-controls-row{display:flex;align-items:flex-start;gap:.75rem;flex-wrap:wrap;margin-bottom:.5rem}
.logs-controls-col{display:flex;flex-direction:column;gap:.4rem}
.logs-controls-col--grow{flex:1 1 auto}
.logs-controls-col--shrink{flex-shrink:0}
</style>
<div class="content-section" id="logs">
    <div class="logs-dashboard a11y-dashboard" data-logs="<?php echo $logsJson; ?>" data-endpoint="modules/logs/list_logs.php">
        <header class="a11y-hero logs-hero">
            <div class="a11y-hero-content logs-hero-content">
                <div>
                    <span class="hero-eyebrow logs-hero-eyebrow">Activity Timeline</span>
                    <h2 class="a11y-hero-title logs-hero-title">Activity Logs</h2>
                    <p class="a11y-hero-subtitle logs-hero-subtitle">Monitor publishing events, workflow actions, and content edits across all modules.</p>
                </div>
                <div class="a11y-hero-actions logs-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--ghost" id="logsRefreshBtn">
                        <i class="fas fa-rotate" aria-hidden="true"></i>
                        <span>Refresh</span>
                    </button>
                    <div class="a11y-hero-meta-group logs-hero-meta-group">
                        <span class="a11y-hero-meta logs-hero-meta-item">
                            <span class="logs-hero-meta__label">Last activity</span>
                            <span class="logs-hero-meta__value" id="logsLastActivity" title="<?php echo htmlspecialchars($lastActivityExact, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($lastActivityLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </span>
                        <span class="a11y-hero-meta logs-hero-meta-item">
                            <span class="logs-hero-meta__label">Past 24 hours</span>
                            <span class="logs-hero-meta__value" id="logsPast24h"><?php echo $last24Hours; ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="a11y-overview-grid logs-overview-grid">
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Total events</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsTotalCount"><?php echo $totalLogs; ?></div>
                    <div class="logs-stat-hint"><span id="logsLast7Days"><?php echo $last7Days; ?></span> in the last 7 days</div>
                </div>
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Active editors</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsUserCount"><?php echo $uniqueUsersCount; ?></div>
                    <div class="logs-stat-hint"><?php echo htmlspecialchars($editorsHint, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Items updated</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsPageCount"><?php echo $uniqueSubjectCount; ?></div>
                    <div class="logs-stat-hint">
                        <?php if ($uniqueSubjectCount > 0): ?>Most recent: <?php echo $recentSubject; ?><?php else: ?>Waiting for the first edit<?php endif; ?>
                    </div>
                </div>
                <div class="a11y-overview-card logs-overview-card">
                    <div class="a11y-overview-label logs-stat-label">Most common action</div>
                    <div class="a11y-overview-value logs-stat-value" id="logsTopActionLabel"><?php echo $topActionLabel; ?></div>
                    <div class="logs-stat-hint" id="logsTopActionCount"><?php echo htmlspecialchars($topActionCountText, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>
        </header>

        <section class="logs-activity" aria-label="Activity feed">
            <div class="logs-activity-header">
                <div class="logs-activity-intro">
                    <div class="logs-activity-heading">
                        <h3>Recent activity</h3>
                        <span class="logs-activity-match-count" id="logsMatchCount"><?php echo htmlspecialchars($matchCountLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <p class="logs-activity-description">Monitor edits, publishing events, and system jobs across all content types.</p>
                </div>
            </div>

            <div class="logs-controls">
                <div class="logs-controls-row">
                    <div class="logs-controls-col logs-controls-col--grow">
                        <div class="logs-filters" id="logsFilters">
                            <button type="button" class="logs-filter-btn active" data-filter="all">
                                <span>All activity</span>
                                <span class="logs-filter-count" id="logsAllCount"><?php echo $totalLogs; ?></span>
                            </button>
                            <?php foreach ($topActions as $action): ?>
                                <button type="button" class="logs-filter-btn" data-filter="<?php echo htmlspecialchars($action['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <span><?php echo htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="logs-filter-count" data-filter-count="<?php echo htmlspecialchars($action['slug'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $action['count']; ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="logs-context-filters" id="logsContextFilters">
                            <button type="button" class="logs-ctx-filter-btn active" data-ctx="all">All contexts</button>
                            <?php
                            $ctxLabels = ['page'=>'Pages','post'=>'Posts','media'=>'Media','user'=>'Users','settings'=>'Settings','commerce'=>'Commerce','system'=>'System'];
                            foreach ($ctxLabels as $ctxKey => $ctxLabel):
                                if (!empty($contextCounts[$ctxKey])):
                            ?>
                                <button type="button" class="logs-ctx-filter-btn" data-ctx="<?php echo $ctxKey; ?>">
                                    <?php echo $ctxLabel; ?> <span style="opacity:.65">(<?php echo $contextCounts[$ctxKey]; ?>)</span>
                                </button>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <div class="logs-controls-col logs-controls-col--shrink">
                        <div class="logs-search-wrap">
                            <input type="text" class="logs-search-input" id="logsSearch" placeholder="Search logs…" aria-label="Search activity logs">
                        </div>
                        <div class="logs-user-filter">
                            <label class="logs-user-filter__label" for="logsUserFilter">View activity by</label>
                            <select class="logs-user-filter__select" id="logsUserFilter"<?php echo count($userOptions) === 0 ? ' disabled' : ''; ?>>
                                <option value="all">All editors</option>
                                <?php foreach ($userOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="logs-activity-table-wrapper" id="logsTimeline">
<?php if (empty($logs)): ?>
                <div class="logs-empty">
                    <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                    <p>No activity recorded yet.</p>
                    <p class="logs-empty-hint">Updates will appear here as your team edits content.</p>
                </div>
<?php else: ?>
                <div class="logs-activity-table-scroll">
                    <table class="logs-activity-table">
                        <thead>
                            <tr>
                                <th scope="col">Action</th>
                                <th scope="col">Context</th>
                                <th scope="col">Subject</th>
                                <th scope="col">Editor</th>
                                <th scope="col">Details</th>
                                <th scope="col">When</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($logs as $log):
    $timestamp   = (int)$log['time'];
    $relative    = describe_time_ago($timestamp, false);
    $absolute    = $timestamp ? date('M j, Y g:i A', $timestamp) : 'Unknown time';
    $ctx         = $log['context'] ?? 'system';
    $detailsText = !empty($log['details']) && is_array($log['details']) ? ' ' . implode(' ', $log['details']) : '';
    $searchText  = strtolower(trim(($log['user'] ?? '') . ' ' . ($log['page_title'] ?? '') . ' ' . $log['action'] . ' ' . $ctx . $detailsText));
?>
                            <tr class="logs-activity-row"
                                data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>"
                                data-action="<?php echo htmlspecialchars($log['action_slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-context="<?php echo htmlspecialchars($ctx, ENT_QUOTES, 'UTF-8'); ?>"
                                data-user="<?php echo htmlspecialchars(strtolower($log['user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="logs-activity-cell logs-activity-cell--action" data-label="Action">
                                    <span class="logs-activity-badge"><?php echo htmlspecialchars($log['action'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td class="logs-activity-cell" data-label="Context">
                                    <span class="logs-ctx-badge logs-ctx-badge--<?php echo htmlspecialchars($ctx, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($ctx), ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--page" data-label="Subject">
                                    <span class="logs-activity-page"><?php echo htmlspecialchars($log['page_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--user" data-label="Editor">
                                    <span class="logs-activity-user"><?php echo $log['user'] !== '' ? htmlspecialchars($log['user'], ENT_QUOTES, 'UTF-8') : 'System'; ?></span>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--details" data-label="Details">
<?php if (!empty($log['details']) && is_array($log['details'])): ?>
                                    <ul class="logs-activity-details">
<?php foreach ($log['details'] as $detail): ?>
                                        <li><?php echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8'); ?></li>
<?php endforeach; ?>
                                    </ul>
<?php else: ?>
                                    <span class="logs-activity-details-empty">—</span>
<?php endif; ?>
                                </td>
                                <td class="logs-activity-cell logs-activity-cell--time" data-label="When">
                                    <time datetime="<?php echo $timestamp ? date('c', $timestamp) : ''; ?>" class="logs-activity-time" title="<?php echo htmlspecialchars($absolute, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($relative, ENT_QUOTES, 'UTF-8'); ?>
                                    </time>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
<?php endif; ?>
            </div>
        </section>
    </div>
</div>
