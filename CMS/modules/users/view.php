<!-- File: view.php -->
<div class="content-section" id="users">
    <div class="a11y-dashboard users-dashboard" data-last-refresh="">
        <header class="a11y-hero users-hero">
            <div class="a11y-hero-content">
                <div>
                    <span class="hero-eyebrow users-hero-eyebrow">Team Directory</span>
                    <h2 class="a11y-hero-title">Team access &amp; permissions</h2>
                    <p class="a11y-hero-subtitle">Review account health, monitor roles, and invite new collaborators from a single, polished dashboard.</p>
                </div>
                <div class="a11y-hero-actions">
                    <button type="button" class="a11y-btn a11y-btn--primary" id="usersNewBtn">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        <span>Add teammate</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--ghost" id="usersRefreshBtn">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                        <span>Refresh</span>
                    </button>
                    <span class="a11y-hero-meta users-last-sync">
                        <i class="fa-solid fa-clock" aria-hidden="true"></i>
                        Last updated <span id="usersLastSync">just now</span>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid users-overview">
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="usersStatTotal">0</div>
                    <div class="a11y-overview-label">Total members</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="usersStatActive">0</div>
                    <div class="a11y-overview-label">Active accounts</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="usersStatAdmins">0</div>
                    <div class="a11y-overview-label">Administrators</div>
                </div>
                <div class="a11y-overview-card">
                    <div class="a11y-overview-value" id="usersStatRecent">0</div>
                    <div class="a11y-overview-label">New this month</div>
                </div>
            </div>
        </header>

        <div class="a11y-controls users-controls">
            <label class="a11y-search" for="usersSearchInput">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="usersSearchInput" placeholder="Search by name, role, or status" aria-label="Search team members">
            </label>
            <div class="a11y-filter-group" role="group" aria-label="User filters">
                <button type="button" class="a11y-filter-btn active" data-users-filter="all">All team <span class="a11y-filter-count" data-count="all">0</span></button>
                <button type="button" class="a11y-filter-btn" data-users-filter="active">Active <span class="a11y-filter-count" data-count="active">0</span></button>
                <button type="button" class="a11y-filter-btn" data-users-filter="inactive">Inactive <span class="a11y-filter-count" data-count="inactive">0</span></button>
                <button type="button" class="a11y-filter-btn" data-users-filter="admin">Admins <span class="a11y-filter-count" data-count="admin">0</span></button>
                <button type="button" class="a11y-filter-btn" data-users-filter="editor">Editors <span class="a11y-filter-count" data-count="editor">0</span></button>
            </div>
            <div class="a11y-view-toggle" role="group" aria-label="Toggle layout">
                <button type="button" class="a11y-view-btn active" data-users-view="grid" aria-label="Grid view">
                    <i class="fas fa-th-large" aria-hidden="true"></i>
                </button>
                <button type="button" class="a11y-view-btn" data-users-view="table" aria-label="Table view">
                    <i class="fas fa-list" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="a11y-pages-grid users-grid" id="usersGrid" role="list"></div>

        <div class="a11y-table-view users-table" id="usersTableView" hidden>
            <div class="a11y-table-header">
                <div>Member</div>
                <div>Role</div>
                <div>Status</div>
                <div>Last login</div>
                <div>Actions</div>
            </div>
            <div id="usersTableBody"></div>
        </div>

        <div class="empty-state users-empty" id="usersEmptyState" hidden aria-labelledby="usersEmptyTitle" aria-describedby="usersEmptyDescription">
            <div class="empty-state__icon" aria-hidden="true">
                <i class="fas fa-users"></i>
            </div>
            <div class="empty-state__content">
                <h3 class="empty-state__title" id="usersEmptyTitle">No team members match your filters</h3>
                <p class="empty-state__description" id="usersEmptyDescription">Try adjusting your search terms or select a different filter option.</p>
            </div>
            <button type="button" class="a11y-btn a11y-btn--primary empty-state__cta" id="usersEmptyCta">
                <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                <span>Invite teammate</span>
            </button>
        </div>
    </div>

    <div class="a11y-page-detail users-drawer" id="usersDrawer" hidden role="dialog" aria-modal="true" aria-labelledby="userFormTitle">
        <div class="a11y-detail-content">
            <button type="button" class="a11y-detail-close" id="userFormClose" aria-label="Close user form">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
            <header class="a11y-detail-modal-header">
                <h2 id="userFormTitle">Add teammate</h2>
                <p class="a11y-detail-description" id="userFormSubtitle">Invite a new user or update their access level.</p>
            </header>
            <form id="userForm" class="users-form" autocomplete="off">
                <input type="hidden" name="id" id="userId">
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="form-input" name="username" id="username" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" class="form-input" name="password" id="password" placeholder="Leave blank to keep current password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="role">Role</label>
                    <select class="form-select" name="role" id="role">
                        <option value="admin">Administrator</option>
                        <option value="editor">Editor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" name="status" id="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="users-form-actions">
                    <button type="submit" class="a11y-btn a11y-btn--primary" id="userFormSubmit">
                        <i class="fas fa-save" aria-hidden="true"></i>
                        <span>Save changes</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--ghost" id="userFormCancel">
                        <span>Cancel</span>
                    </button>
                    <button type="button" class="a11y-btn a11y-btn--secondary users-delete" id="userDeleteBtn">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        <span>Delete user</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
