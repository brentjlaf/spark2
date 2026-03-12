<!-- File: view.php -->
<div class="content-section active" id="dashboard">
    <div class="dashboard-shell a11y-dashboard">
        <header class="a11y-hero dashboard-hero">
            <div class="a11y-hero-content dashboard-hero-content">
                <div>
                    <span class="hero-eyebrow dashboard-hero-eyebrow">Operations Overview</span>
                    <h2 class="a11y-hero-title">Operations Dashboard</h2>
                    <p class="a11y-hero-subtitle">
                        Monitor the health of your content, media, and events operations from one polished overview.
                    </p>
                </div>
                <div class="a11y-hero-actions dashboard-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="dashboardRefresh">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                        <span>Refresh insights</span>
                    </button>
                    <a class="a11y-btn a11y-btn--ghost" href="../" target="_blank" rel="noopener">
                        <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                        <span>View site</span>
                    </a>
                    <span class="a11y-hero-meta dashboard-hero-meta" id="dashboardLastUpdated">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        Insights update automatically every few moments.
                    </span>
                    <span class="sr-only" id="dashboardMetricsStatus" role="status" aria-live="polite">
                        Loading dashboard metrics…
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid dashboard-overview-grid" aria-busy="true">
                <div class="a11y-overview-card dashboard-overview-card">
                    <div class="dashboard-overview-icon pages" aria-hidden="true">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div class="dashboard-overview-body">
                        <div class="a11y-overview-value" data-stat="pages">0</div>
                        <div class="a11y-overview-label">Total Pages</div>
                        <div class="dashboard-overview-meta" data-stat="pages-breakdown">Published: 0 • Drafts: 0</div>
                    </div>
                </div>
                <div class="a11y-overview-card dashboard-overview-card">
                    <div class="dashboard-overview-icon media" aria-hidden="true">
                        <i class="fa-solid fa-images"></i>
                    </div>
                    <div class="dashboard-overview-body">
                        <div class="a11y-overview-value" data-stat="media">0</div>
                        <div class="a11y-overview-label">Media Files</div>
                        <div class="dashboard-overview-meta" data-stat="media-size">Library size: 0 KB</div>
                    </div>
                </div>
                <div class="a11y-overview-card dashboard-overview-card">
                    <div class="dashboard-overview-icon users" aria-hidden="true">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="dashboard-overview-body">
                        <div class="a11y-overview-value" data-stat="users">0</div>
                        <div class="a11y-overview-label">Team Members</div>
                        <div class="dashboard-overview-meta" data-stat="users-breakdown">Admins: 0 • Editors: 0</div>
                    </div>
                </div>
                <div class="a11y-overview-card dashboard-overview-card">
                    <div class="dashboard-overview-icon views" aria-hidden="true">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <div class="dashboard-overview-body">
                        <div class="a11y-overview-value" data-stat="views">0</div>
                        <div class="a11y-overview-label">Total Views</div>
                        <div class="dashboard-overview-meta" data-stat="views-average">Average per page: 0</div>
                        <div class="dashboard-overview-meta" data-stat="views-top-page">No views recorded yet</div>
                    </div>
                </div>
                <div class="a11y-overview-card dashboard-overview-card">
                    <div class="dashboard-overview-icon accessibility" aria-hidden="true">
                        <i class="fa-solid fa-universal-access"></i>
                    </div>
                    <div class="dashboard-overview-body">
                        <div class="a11y-overview-value" data-stat="accessibility-score">0%</div>
                        <div class="a11y-overview-label">Accessibility</div>
                        <div class="dashboard-overview-meta" data-stat="accessibility-breakdown">Compliant: 0 &bull; Needs review: 0</div>
                        <div class="dashboard-overview-meta" data-stat="accessibility-alt">Alt text issues: 0</div>
                    </div>
                </div>
                <div class="a11y-overview-card dashboard-overview-card">
                    <div class="dashboard-overview-icon alerts" aria-hidden="true">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="dashboard-overview-body">
                        <div class="a11y-overview-value" data-stat="alerts">0</div>
                        <div class="a11y-overview-label">Open Alerts</div>
                        <div class="dashboard-overview-meta" data-stat="alerts-breakdown">Accessibility reviews pending: 0</div>
                    </div>
                </div>
            </div>
        </header>

        <section class="dashboard-panel dashboard-module-panel" aria-labelledby="dashboardSectionModules">
            <header class="dashboard-panel-header">
                <div>
                    <h3 class="dashboard-panel-title" id="dashboardSectionModules">Module insights</h3>
                    <p class="dashboard-panel-description">Understand where attention is needed across features like Pages, Events, and more.</p>
                </div>
            </header>
            <div class="dashboard-module-card-grid" aria-live="polite" aria-busy="true" id="dashboardModuleCards" role="list">
                <article class="dashboard-module-card placeholder" role="listitem" aria-label="Module data loading">
                    <a class="dashboard-module-link" href="#" aria-disabled="true" tabindex="-1" aria-labelledby="dashboard-module-placeholder-name dashboard-module-placeholder-status" aria-describedby="dashboard-module-placeholder-primary">
                        <header class="dashboard-module-card-header">
                            <div class="dashboard-module-card-title">
                                <span class="dashboard-module-name" id="dashboard-module-placeholder-name">Loading…</span>
                                <span class="dashboard-module-status" id="dashboard-module-placeholder-status">Please wait</span>
                            </div>
                            <p class="dashboard-module-primary" id="dashboard-module-placeholder-primary">Fetching module insights</p>
                        </header>
                    </a>
                    <p class="dashboard-module-secondary">Module metrics will appear here once data is available.</p>
                    <footer class="dashboard-module-card-footer">
                        <span class="dashboard-module-trend" aria-hidden="true">Preparing data</span>
                        <button type="button" class="dashboard-module-cta a11y-btn a11y-btn--secondary" disabled aria-disabled="true">Loading</button>
                    </footer>
                </article>
            </div>
        </section>
    </div>
</div>
