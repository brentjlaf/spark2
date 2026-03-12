<!-- File: view.php -->
<div class="content-section" id="blogs">
    <div class="blog-dashboard">
        <header class="a11y-hero blog-hero">
            <div class="a11y-hero-content blog-hero-content">
                <div>
                    <span class="hero-eyebrow blog-hero-eyebrow">Publishing Pipeline</span>
                    <h2 class="a11y-hero-title blog-hero-title">Editorial Dashboard</h2>
                    <p class="a11y-hero-subtitle blog-hero-subtitle">Plan, publish, and measure the health of your content pipeline.</p>
                </div>
                <div class="a11y-hero-actions blog-hero-actions">
                    <button type="button" class="blog-btn blog-btn--ghost" id="categoriesBtn">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <span>Manage Categories</span>
                    </button>
                    <button type="button" class="blog-btn blog-btn--primary" id="newPostBtn">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                        <span>New Post</span>
                    </button>
                    <span class="a11y-hero-meta blog-hero-meta">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        <span id="blogsLastUpdated">No posts yet</span>
                    </span>
                </div>
            </div>
            <div class="a11y-overview-grid blog-overview-grid">
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Total Posts</div>
                    <div class="a11y-overview-value blog-overview-value" id="totalPosts">0</div>
                </div>
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Published</div>
                    <div class="a11y-overview-value blog-overview-value" id="publishedPosts">0</div>
                </div>
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Drafts</div>
                    <div class="a11y-overview-value blog-overview-value" id="draftPosts">0</div>
                </div>
                <div class="a11y-overview-card blog-overview-card">
                    <div class="a11y-overview-label blog-overview-label">Scheduled</div>
                    <div class="a11y-overview-value blog-overview-value" id="scheduledPosts">0</div>
                </div>
            </div>
        </header>

        <div class="blog-controls">
            <div class="blog-controls-primary">
                <label class="blog-search" for="blogSearchInput">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input type="search" id="blogSearchInput" placeholder="Search posts by title, slug, excerpt, or tag" aria-label="Search posts">
                </label>
                <div class="blog-filter-toolbar">
                    <div class="blog-filter-group" role="group" aria-label="Filter posts by status">
                        <button type="button" class="blog-filter-btn active" data-blog-filter="all" aria-pressed="true">
                            All Posts <span class="blog-filter-count" data-count="all">0</span>
                        </button>
                        <button type="button" class="blog-filter-btn" data-blog-filter="published" aria-pressed="false">
                            Published <span class="blog-filter-count" data-count="published">0</span>
                        </button>
                        <button type="button" class="blog-filter-btn" data-blog-filter="drafts" aria-pressed="false">
                            Drafts <span class="blog-filter-count" data-count="drafts">0</span>
                        </button>
                        <button type="button" class="blog-filter-btn" data-blog-filter="scheduled" aria-pressed="false">
                            Scheduled <span class="blog-filter-count" data-count="scheduled">0</span>
                        </button>
                    </div>
                    <div class="blog-filter-selects">
                        <div class="blog-select-filter">
                            <select id="categoryFilter">
                                <option value="">All Categories</option>
                            </select>
                        </div>
                        <div class="blog-select-filter">
                            <select id="authorFilter">
                                <option value="">All Authors</option>
                            </select>
                        </div>
                    </div>
                    <div class="blog-controls-secondary">
                        <div class="blog-select-filter blog-select-filter--actions">
                            <label>&nbsp;</label>
                            <button type="button" class="blog-btn blog-btn--subtle" id="clearFilters">
                                <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk action toolbar for blogs (shown when rows are selected) -->
        <div class="bulk-toolbar" id="blogsBulkToolbar" hidden aria-live="polite">
            <span class="bulk-toolbar__count" id="blogsBulkCount">0 selected</span>
            <div class="bulk-toolbar__actions">
                <button type="button" class="blog-btn blog-btn--subtle" id="blogsBulkPublishBtn">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i> Publish
                </button>
                <button type="button" class="blog-btn blog-btn--subtle" id="blogsBulkUnpublishBtn">
                    <i class="fa-solid fa-eye-slash" aria-hidden="true"></i> Unpublish
                </button>
                <button type="button" class="blog-btn blog-btn--danger" id="blogsBulkDeleteBtn">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i> Delete
                </button>
                <button type="button" class="blog-btn blog-btn--ghost" id="blogsBulkClearBtn">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i> Clear
                </button>
            </div>
        </div>

        <section class="table-card blog-table-card">
            <header class="blog-table-header">
                <div>
                    <h3>Content pipeline</h3>
                    <p>Track statuses, schedules, and author activity at a glance.</p>
                </div>
                <span class="blog-posts-count" id="postsCount">0 posts</span>
            </header>
            <div class="blog-table-wrapper">
                <table class="data-table blog-table">
                    <thead>
                        <tr>
                            <th scope="col" class="blog-list-select-heading">
                                <input type="checkbox" id="selectAllPosts" aria-label="Select all posts">
                            </th>
                            <th scope="col" aria-sort="none">
                                <button type="button" class="blog-sort-btn" data-blog-sort="title" data-default-direction="asc">
                                    <span>Title</span>
                                    <span class="blog-sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th scope="col" aria-sort="none">
                                <button type="button" class="blog-sort-btn" data-blog-sort="author" data-default-direction="asc">
                                    <span>Author</span>
                                    <span class="blog-sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th scope="col" aria-sort="none">
                                <button type="button" class="blog-sort-btn" data-blog-sort="category" data-default-direction="asc">
                                    <span>Category</span>
                                    <span class="blog-sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th scope="col" aria-sort="none">
                                <button type="button" class="blog-sort-btn" data-blog-sort="status" data-default-direction="desc">
                                    <span>Status</span>
                                    <span class="blog-sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th scope="col" aria-sort="none">
                                <button type="button" class="blog-sort-btn" data-blog-sort="date" data-default-direction="desc">
                                    <span>Date</span>
                                    <span class="blog-sort-indicator" aria-hidden="true"></span>
                                </button>
                            </th>
                            <th scope="col" class="blog-table-actions-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="postsTableBody"></tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<div class="modal blog-modal" id="postModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="postModalDescription">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closeModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Post settings</span>
                <div class="blog-modal__title-row">
                    <h2 class="blog-modal__title" id="modalTitle">New Post</h2>
                    <span class="status-badge status-draft" data-blog-status-badge aria-label="Status: Draft">Draft</span>
                </div>
                <p class="blog-modal__description" id="postModalDescription">Craft your article content, assign categories, and schedule publication.</p>
                <!-- Draft recovery banner (Feature 5) -->
                <div class="draft-recovery-banner" id="blogDraftRecoveryBanner" hidden role="alert">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    <span id="blogDraftRecoveryText">An autosaved draft exists.</span>
                    <button type="button" class="c-button c-button--secondary c-button--sm" id="blogRestoreDraftBtn">Restore Draft</button>
                    <button type="button" class="c-button c-button--ghost c-button--sm" id="blogDiscardDraftBtn">Discard</button>
                </div>
            </header>
            <div class="blog-modal__body">
                <form id="postForm" class="blog-modal__form">
                    <div class="blog-modal__layout">
                        <div class="blog-modal__main">
                            <div class="form-group blog-modal__field">
                                <label for="postTitle">Title *</label>
                                <input type="text" id="postTitle" name="title" required>
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postSlug">Slug</label>
                                <input type="text" id="postSlug" name="slug" placeholder="auto-generated-from-title">
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postExcerpt">Excerpt</label>
                                <textarea id="postExcerpt" name="excerpt" placeholder="Brief description of the post..."></textarea>
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postImage">Featured Image</label>
                                <div class="blog-image-input">
                                    <input type="text" id="postImage" name="image" placeholder="Select an image from the Media Library" aria-describedby="postImageHint">
                                    <button type="button" class="blog-btn blog-btn--subtle" id="chooseFeaturedImage">
                                        <i class="fa-solid fa-images" aria-hidden="true"></i>
                                        <span>Select from Media Library</span>
                                    </button>
                                </div>
                                <p class="blog-modal__hint" id="postImageHint">Choose an image from the Media Library or paste a trusted image URL to highlight this post.</p>
                                <div class="blog-image-preview" id="postImagePreview" aria-live="polite"></div>
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postImageAlt">Image alternative text</label>
                                <input type="text" id="postImageAlt" name="imageAlt" placeholder="Describe the featured image for screen readers">
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postContent">Content *</label>
                                <div class="editor-container blog-modal__editor">
                                    <div id="postContent" class="editor-content" contenteditable="true"></div>
                                </div>
                            </div>
                        </div>
                        <aside class="blog-modal__sidebar" aria-label="Post settings sidebar">
                            <div class="form-group blog-modal__field">
                                <label for="postCategory">Category *</label>
                                <select id="postCategory" name="category" required>
                                    <option value="">Select Category</option>
                                </select>
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postAuthor">Author *</label>
                                <select id="postAuthor" name="author" required>
                                    <option value="">Select Author</option>
                                </select>
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postStatus">Status *</label>
                                <select id="postStatus" name="status" required>
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="scheduled">Scheduled</option>
                                </select>
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="publishDate">Publish Date</label>
                                <input type="datetime-local" id="publishDate" name="publishDate">
                                <!-- Scheduling summary (Feature 7) -->
                                <div class="schedule-summary" id="blogScheduleSummary" hidden>
                                    <i class="fa-solid fa-calendar-alt" aria-hidden="true"></i>
                                    <span id="blogScheduleSummaryText"></span>
                                </div>
                            </div>
                            <div class="form-group blog-modal__field">
                                <label for="postTags">Tags</label>
                                <input type="text" id="postTags" name="tags" placeholder="Separate tags with commas">
                            </div>
                        </aside>
                    </div>
                    <input type="hidden" id="postId" name="id">
                </form>
            </div>
            <footer class="blog-modal__footer">
                <button type="button" class="c-button c-button--secondary" id="cancelBtn">Cancel</button>
                <button type="submit" class="c-button c-button--primary" id="saveBtn" form="postForm">Save Post</button>
            </footer>
        </div>
    </div>
</div>

<div class="modal blog-modal" id="categoriesModal" role="dialog" aria-modal="true" aria-labelledby="categoriesModalTitle" aria-describedby="categoriesModalDescription">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closeCategoriesModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Categories</span>
                <h2 class="blog-modal__title" id="categoriesModalTitle">Manage Categories</h2>
                <p class="blog-modal__description" id="categoriesModalDescription">Create, rename, and remove topics to keep your editorial calendar organized.</p>
            </header>
            <div class="blog-modal__body">
                <div class="form-group blog-modal__field">
                    <label for="newCategoryName">Add New Category</label>
                    <div class="blog-modal__inline-input">
                        <input type="text" id="newCategoryName" placeholder="Category name">
                        <button type="button" class="c-button c-button--primary c-button--sm" id="addCategoryBtn">Add</button>
                    </div>
                </div>
                <div id="categoriesList" class="blog-category-list" aria-live="polite"></div>
            </div>
            <footer class="blog-modal__footer">
                <button type="button" class="c-button c-button--secondary" id="categoriesDoneBtn">Done</button>
            </footer>
        </div>
    </div>
</div>

<div class="modal blog-modal" id="postPreviewModal" role="dialog" aria-modal="true" aria-labelledby="previewTitle">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closePreviewModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Post preview</span>
                <div class="blog-modal__title-row">
                    <h2 class="blog-modal__title" id="previewTitle"></h2>
                    <span class="status-badge status-draft" data-blog-preview-status-badge aria-label="Status: Draft">Draft</span>
                </div>
                <p class="blog-modal__description">Review your content before publishing or return to make edits.</p>
            </header>
            <div class="blog-modal__body">
                <div id="previewMeta" class="blog-preview-meta"></div>
                <div id="previewImage"></div>
                <div id="previewContent" class="blog-preview-content"></div>
            </div>
            <footer class="blog-modal__footer">
                <button type="button" class="c-button c-button--secondary" id="closePreviewBtn">Close</button>
                <button type="button" class="c-button c-button--primary" id="editPreviewBtn">Edit</button>
            </footer>
        </div>
    </div>
</div>

<div class="modal blog-modal" id="mediaPickerModal" role="dialog" aria-modal="true" aria-labelledby="mediaPickerTitle">
    <div class="modal-content">
        <div class="blog-modal__surface">
            <button type="button" class="blog-modal__close" id="closeMediaPickerModal" aria-label="Close">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <header class="blog-modal__header">
                <span class="blog-modal__subtitle">Media library</span>
                <h2 class="blog-modal__title" id="mediaPickerTitle">Select a featured image</h2>
                <p class="blog-modal__description">Choose from the media library to quickly set a featured image for your post.</p>
            </header>
            <div class="blog-modal__body blog-media-picker">
                <div class="blog-media-picker__toolbar">
                    <label class="blog-media-picker__search" for="mediaPickerSearch">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" id="mediaPickerSearch" placeholder="Search media by name or tag" aria-label="Search media">
                    </label>
                </div>
                <div id="mediaPickerGrid" class="blog-media-picker__grid" role="listbox" aria-label="Media library images" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>
