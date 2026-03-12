// File: blogs.js
$(document).ready(function () {
  let posts = [];
  let categories = [];
  let authors = [];
  let nextPostId = 1;
  let isLoadingPosts = false;
  let postsLoadError = false;
  let selectedPostIds = new Set(); // bulk selection (Feature 8)

  const $postsTableBody = $("#postsTableBody");
  const $postsCountLabel = $("#postsCount");
  const $sortButtons = $("[data-blog-sort]");
  const sortState = { key: null, direction: "asc" };
  let mediaItems = [];
  let mediaLoaded = false;

  const $searchInput = $("#blogSearchInput");
  const $statusFilterButtons = $("[data-blog-filter]");
  let activeStatusFilter = "all";
  const STATUS_BADGE_MAP = {
    draft: { label: "Draft", className: "status-draft" },
    published: { label: "Published", className: "status-published" },
    scheduled: { label: "Scheduled", className: "status-scheduled" },
    archived: { label: "Archived", className: "status-archived" },
  };

  function getStatusBadgeInfo(status) {
    const key = String(status || "").toLowerCase();
    return (
      STATUS_BADGE_MAP[key] || { label: "Draft", className: "status-draft" }
    );
  }

  const STATUS_BADGE_CLASSES = Object.values(STATUS_BADGE_MAP)
    .map((entry) => entry.className)
    .join(" ");

  function updateStatusBadge($badge, status) {
    if (!$badge || !$badge.length) {
      return;
    }
    const info = getStatusBadgeInfo(status);
    $badge.removeClass(STATUS_BADGE_CLASSES).addClass(info.className);
    $badge.text(info.label);
    $badge.attr("aria-label", `Status: ${info.label}`);
  }

  function renderTableMessage(message, options = {}) {
    const opts = Object.assign({ isError: false, countLabel: null }, options);
    const className = opts.isError ? "text-danger" : "text-muted";
    $postsTableBody.html(
      `<tr><td colspan="6" class="text-center ${className}">${message}</td></tr>`,
    );
    if (typeof opts.countLabel === "string") {
      $postsCountLabel.text(opts.countLabel);
    }
  }

  function normalizePostFromServer(rawPost) {
    const post = Object.assign({}, rawPost || {});
    const parsedId = parseInt(post.id, 10);
    post.id = Number.isNaN(parsedId) ? post.id : parsedId;
    post.title = (post.title || "").trim() || "Untitled Post";
    post.slug = (post.slug || "").trim();
    post.excerpt = (post.excerpt || "").trim();
    post.content = post.content || "";
    post.category = (post.category || "").trim() || "Uncategorized";
    post.author = (post.author || "").trim() || "Unknown Author";
    const status = (post.status || "").trim().toLowerCase();
    post.status = status || "draft";
    if (Array.isArray(post.tags)) {
      post.tags = post.tags.join(", ");
    } else {
      post.tags = (post.tags || "").toString();
    }
    post.publishDate = (post.publishDate || "").trim();
    post.image = (post.image || "").trim();
    post.imageAlt = (post.imageAlt || "").trim();
    if (!post.createdAt) {
      post.createdAt = post.publishDate || new Date().toISOString();
    }
    return post;
  }

  function refreshCategoriesFromPosts() {
    const unique = new Set(categories);
    posts.forEach((post) => {
      if (post.category) {
        unique.add(post.category);
      }
    });
    categories = Array.from(unique).sort((a, b) => a.localeCompare(b));
  }

  function recalculateNextPostId() {
    const maxId = posts.reduce((max, post) => {
      const id = parseInt(post.id, 10);
      return Number.isNaN(id) ? max : Math.max(max, id);
    }, 0);
    nextPostId = maxId + 1;
  }

  function sendJsonRequest(url, options, defaultErrorMessage) {
    return fetch(url, options).then((response) => {
      return response.text().then((text) => {
        const trimmedText = (text || "").trim();
        let data = null;
        if (trimmedText) {
          try {
            data = JSON.parse(trimmedText);
          } catch (parseError) {
            if (response.ok) {
              throw new Error(defaultErrorMessage);
            }
          }
        }
        if (response.ok) {
          return data === null ? {} : data;
        }
        const message =
          data && data.error ? data.error : trimmedText || defaultErrorMessage;
        throw new Error(message || defaultErrorMessage);
      });
    });
  }

  function loadPostsFromServer() {
    if (isLoadingPosts) {
      return;
    }
    isLoadingPosts = true;
    postsLoadError = false;
    renderTableMessage("Loading posts…", { countLabel: "Loading posts…" });
    $.getJSON("modules/blogs/list_posts.php")
      .done(function (data) {
        if (Array.isArray(data)) {
          postsLoadError = false;
          posts = data.map(normalizePostFromServer);
          recalculateNextPostId();
          refreshCategoriesFromPosts();
          updateStats();
          populateFilters();
          renderPosts();
          renderCategories();
        } else {
          postsLoadError = false;
          posts = [];
          refreshCategoriesFromPosts();
          recalculateNextPostId();
          updateStats();
          populateFilters();
          renderTableMessage("No blog posts found.", {
            countLabel: "Showing 0 posts",
          });
          renderCategories();
        }
      })
      .fail(function () {
        postsLoadError = true;
        posts = [];
        refreshCategoriesFromPosts();
        recalculateNextPostId();
        updateStats();
        populateFilters();
        renderTableMessage(
          "Unable to load blog posts. Please try again later.",
          { isError: true, countLabel: "Showing 0 posts" },
        );
        renderCategories();
      })
      .always(function () {
        isLoadingPosts = false;
      });
  }

  function getCmsBasePath() {
    if (window.__cmsBasePath !== undefined) {
      return window.__cmsBasePath;
    }
    const path = window.location.pathname || "";
    const cmsSegment = "/CMS";
    const markerWithSlash = `${cmsSegment}/`;
    let base = "";
    const markerIndex = path.indexOf(markerWithSlash);
    if (markerIndex !== -1) {
      base = `${path.substring(0, markerIndex)}${cmsSegment}`;
    } else {
      const fallbackIndex = path.indexOf(cmsSegment);
      base =
        fallbackIndex !== -1
          ? `${path.substring(0, fallbackIndex)}${cmsSegment}`
          : "";
    }
    window.__cmsBasePath = base.replace(/\/$/, "");
    return window.__cmsBasePath;
  }

  function isAbsoluteResource(path) {
    return (
      /^https?:\/\//i.test(path || "") ||
      (typeof path === "string" &&
        (path.startsWith("//") || path.startsWith("/")))
    );
  }

  function resolveCmsPath(relativePath) {
    const value = String(relativePath || "");
    if (!value) {
      return "";
    }
    if (isAbsoluteResource(value)) {
      return value;
    }
    const base = getCmsBasePath().replace(/\/$/, "");
    const cleaned = value.replace(/^\.\/+/, "").replace(/^\/+/, "");
    if (!cleaned) {
      return "";
    }
    if (!base) {
      return `/${cleaned}`;
    }
    return `${base}/${cleaned}`;
  }

  function normalizeImageValue(value) {
    const raw = (value || "").trim();
    if (!raw) {
      return "";
    }
    if (/^https?:\/\//i.test(raw)) {
      try {
        const parsed = new URL(raw, window.location.origin);
        if (parsed.origin === window.location.origin) {
          return normalizeImageValue(parsed.pathname);
        }
      } catch (err) {
        return raw;
      }
      return raw;
    }
    if (raw.startsWith("//") || raw.startsWith("data:")) {
      return raw;
    }
    if (raw.startsWith("/")) {
      return raw.replace(/\/+/g, "/");
    }
    const base = getCmsBasePath().replace(/\/$/, "");
    const cleaned = raw.replace(/^\.\/+/, "").replace(/^\/+/, "");
    if (!cleaned) {
      return "";
    }
    const baseName = base.replace(/^\//, "");
    if (baseName && cleaned.startsWith(`${baseName}/`)) {
      return `/${cleaned}`;
    }
    if (base) {
      return `${base}/${cleaned}`;
    }
    return `/${cleaned}`;
  }

  function renderMediaPicker(items) {
    const grid = $("#mediaPickerGrid");
    if (!items.length) {
      grid.html(
        '<p class="blog-media-picker__status">No images found in the media library.</p>',
      );
      return;
    }
    const html = items
      .map((item) => {
        const filePath = resolveCmsPath(item.file);
        const thumbPath = resolveCmsPath(item.thumbnail || item.file);
        const safeFile = escapeAttribute(filePath);
        const safeThumb = escapeAttribute(thumbPath);
        const safeName = escapeAttribute(
          item.name || item.file || "Media item",
        );
        return `
                <button type="button" class="blog-media-picker__item" data-file="${safeFile}" role="option">
                    <span class="blog-media-picker__thumb"><img src="${safeThumb}" alt="${safeName}"></span>
                    <span class="blog-media-picker__name">${safeName}</span>
                </button>
            `;
      })
      .join("");
    grid.html(html);
  }

  function loadMediaLibrary() {
    const grid = $("#mediaPickerGrid");
    grid.html('<p class="blog-media-picker__status">Loading images…</p>');
    return $.getJSON("modules/media/list_media.php", {
      sort: "name",
      order: "asc",
    })
      .done(function (data) {
        mediaItems = Array.isArray(data.media)
          ? data.media.filter(
              (item) => (item.type || "") === "images" && item.file,
            )
          : [];
        mediaLoaded = true;
        if (!mediaItems.length) {
          grid.html(
            '<p class="blog-media-picker__status">No images found in the media library.</p>',
          );
          return;
        }
        renderMediaPicker(mediaItems);
      })
      .fail(function () {
        grid.html(
          '<p class="blog-media-picker__status blog-media-picker__status--error">Unable to load the media library. Please try again.</p>',
        );
      });
  }

  function openMediaPicker() {
    $("#mediaPickerSearch").val("");
    openModal("mediaPickerModal");
    if (mediaLoaded && mediaItems.length) {
      renderMediaPicker(mediaItems);
      return;
    }
    loadMediaLibrary();
  }

  function escapeAttribute(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function getPostInitial(post) {
    const source = (post.title || post.slug || "?").trim();
    return source ? source.charAt(0).toUpperCase() : "?";
  }

  function updateImagePreview(url, altText) {
    const preview = $("#postImagePreview");
    if (url) {
      const safeAlt = escapeAttribute(altText || "Featured blog image");
      preview.html(`<img src="${url}" alt="${safeAlt}">`).addClass("has-image");
    } else {
      preview.empty().removeClass("has-image");
    }
  }

  function getPreviewAltText() {
    const explicit = $("#postImageAlt").val();
    if (explicit && explicit.trim()) {
      return explicit.trim();
    }
    const title = $("#postTitle").val();
    if (title && title.trim()) {
      return title.trim();
    }
    return "Featured blog image";
  }

  function setImagePreviewFromPost(post) {
    updateImagePreview(post.image || "", post.imageAlt || post.title || "");
  }

  window.__selectImageFromPicker = function (url) {
    if (!url) {
      return;
    }
    const normalized = normalizeImageValue(url);
    $("#postImage").val(normalized).trigger("change");
    closeModal("mediaPickerModal");
  };

  // Load authors from user accounts
  function loadAuthors() {
    $.getJSON("modules/users/list_users.php", function (data) {
      authors = data.map((u) => u.username);
      populateAuthorOptions();
    });
  }

  function populateAuthorOptions() {
    const authorOptions = authors
      .map((a) => `<option value="${a}">${a}</option>`)
      .join("");
    const currentFilterAuthor = $("#authorFilter").val() || "";
    const currentPostAuthor = $("#postAuthor").val() || "";
    $("#authorFilter").html(
      `<option value="">All Authors</option>${authorOptions}`,
    );
    $("#postAuthor").html(
      `<option value="">Select Author</option>${authorOptions}`,
    );
    if (currentFilterAuthor && authors.includes(currentFilterAuthor)) {
      $("#authorFilter").val(currentFilterAuthor);
    }
    if (currentPostAuthor && authors.includes(currentPostAuthor)) {
      $("#postAuthor").val(currentPostAuthor);
    }
  }

  setActiveStatusFilter("all");
  setSort("date", "desc", { silent: true });
  loadAuthors();
  loadPostsFromServer();

  $("#newPostBtn").click(function () {
    openPostModal();
  });
  $("#categoriesBtn").click(function () {
    openCategoriesModal();
  });
  $("#closeModal, #cancelBtn").click(function () {
    closeModal("postModal");
  });
  $("#closeCategoriesModal").click(function () {
    closeModal("categoriesModal");
  });
  $("#categoriesDoneBtn").click(function () {
    closeModal("categoriesModal");
  });
  $("#closePreviewModal, #closePreviewBtn").click(function () {
    closeModal("postPreviewModal");
  });
  $("#editPreviewBtn").click(function () {
    const id = parseInt($(this).data("id"));
    closeModal("postPreviewModal");
    openPostModal(id);
  });
  $("#postForm").submit(function (e) {
    e.preventDefault();
    savePost();
  });
  $("#addCategoryBtn").click(function () {
    addCategory();
  });
  $("#newCategoryName").keypress(function (e) {
    if (e.which === 13) {
      addCategory();
    }
  });
  $("#categoryFilter, #authorFilter").change(function () {
    renderPosts();
  });
  $searchInput.on("input", function () {
    renderPosts();
  });
  $sortButtons.on("click", function () {
    const $btn = $(this);
    const key = $btn.data("blogSort");
    if (!key) {
      return;
    }
    let direction = getDefaultSortDirection($btn);
    if (sortState.key === key) {
      direction = sortState.direction === "asc" ? "desc" : "asc";
    }
    setSort(key, direction);
  });
  $statusFilterButtons.on("click", function () {
    const filter = $(this).data("blog-filter");
    if (!filter || filter === activeStatusFilter) {
      return;
    }
    setActiveStatusFilter(filter);
    renderPosts();
  });
  $("#clearFilters").click(function () {
    $("#categoryFilter, #authorFilter").val("");
    $searchInput.val("");
    setActiveStatusFilter("all");
    renderPosts();
  });
  $("#postTitle").on("input", function () {
    const value = $(this).val();
    if (!$("#postId").val()) {
      const slug = value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
      $("#postSlug").val(slug);
    }
    const imageUrl = $("#postImage").val().trim();
    if (imageUrl && !$("#postImageAlt").val().trim()) {
      updateImagePreview(imageUrl, value.trim());
    }
  });

  $("#postImage").on("input", function () {
    const url = $(this).val().trim();
    const alt = getPreviewAltText();
    updateImagePreview(url, alt);
  });

  $("#postImage").on("change", function () {
    const normalized = normalizeImageValue($(this).val());
    $(this).val(normalized);
    const alt = getPreviewAltText();
    updateImagePreview(normalized, alt);
  });

  $("#chooseFeaturedImage").on("click", function (e) {
    e.preventDefault();
    openMediaPicker();
  });

  $("#mediaPickerGrid").on("click", ".blog-media-picker__item", function () {
    const file = $(this).data("file");
    if (!file) {
      return;
    }
    $("#postImage").val(file).trigger("change");
    closeModal("mediaPickerModal");
  });

  $("#mediaPickerSearch").on("input", function () {
    const term = $(this).val().toLowerCase().trim();
    if (!mediaItems.length) {
      return;
    }
    if (!term) {
      renderMediaPicker(mediaItems);
      return;
    }
    const filtered = mediaItems.filter((item) => {
      const name = (item.name || "").toLowerCase();
      let tags = "";
      if (Array.isArray(item.tags)) {
        tags = item.tags.join(" ").toLowerCase();
      } else if (typeof item.tags === "string") {
        tags = item.tags.toLowerCase();
      }
      const file = (item.file || "").toLowerCase();
      return name.includes(term) || tags.includes(term) || file.includes(term);
    });
    renderMediaPicker(filtered);
  });

  $("#closeMediaPickerModal").click(function () {
    closeModal("mediaPickerModal");
  });

  $("#postImageAlt").on("input change", function () {
    const url = $("#postImage").val().trim();
    if (url) {
      const alt = getPreviewAltText();
      updateImagePreview(url, alt);
    }
  });

  function updateStats() {
    const total = posts.length;
    const published = posts.filter((p) => p.status === "published").length;
    const drafts = posts.filter((p) => p.status === "draft").length;
    const scheduled = posts.filter((p) => p.status === "scheduled").length;
    $("#totalPosts").text(total);
    $("#publishedPosts").text(published);
    $("#draftPosts").text(drafts);
    $("#scheduledPosts").text(scheduled);
    updateStatusFilterCounts({
      all: total,
      published,
      drafts,
      scheduled,
    });
  }

  function populateFilters() {
    const categoryOptions = categories
      .map((c) => `<option value="${c}">${c}</option>`)
      .join("");
    const currentFilterCategory = $("#categoryFilter").val() || "";
    const currentPostCategory = $("#postCategory").val() || "";
    $("#categoryFilter").html(
      `<option value="">All Categories</option>${categoryOptions}`,
    );
    $("#postCategory").html(
      `<option value="">Select Category</option>${categoryOptions}`,
    );
    if (currentFilterCategory && categories.includes(currentFilterCategory)) {
      $("#categoryFilter").val(currentFilterCategory);
    }
    if (currentPostCategory && categories.includes(currentPostCategory)) {
      $("#postCategory").val(currentPostCategory);
    }
    populateAuthorOptions();
  }

  function setActiveStatusFilter(filter) {
    activeStatusFilter = filter;
    $statusFilterButtons.removeClass("active").attr("aria-pressed", "false");
    const $target = $statusFilterButtons.filter(
      `[data-blog-filter="${filter}"]`,
    );
    if ($target.length) {
      $target.addClass("active").attr("aria-pressed", "true");
    }
  }

  function updateStatusFilterCounts(counts) {
    Object.entries(counts).forEach(([key, value]) => {
      $(`.blog-filter-count[data-count="${key}"]`).text(value);
    });
  }

  function compareStrings(a, b) {
    return (a || "")
      .toString()
      .localeCompare((b || "").toString(), undefined, {
        sensitivity: "base",
        numeric: true,
      });
  }

  function parseDateValue(value) {
    if (!value) {
      return 0;
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? 0 : date.getTime();
  }

  const statusRank = {
    draft: 1,
    scheduled: 2,
    published: 3,
  };

  const sortComparators = {
    title(a, b) {
      const primary = compareStrings(a.title, b.title);
      if (primary !== 0) {
        return primary;
      }
      return compareStrings(a.slug, b.slug);
    },
    author(a, b) {
      const primary = compareStrings(a.author, b.author);
      if (primary !== 0) {
        return primary;
      }
      return compareStrings(a.title, b.title);
    },
    category(a, b) {
      const primary = compareStrings(a.category, b.category);
      if (primary !== 0) {
        return primary;
      }
      return compareStrings(a.title, b.title);
    },
    status(a, b) {
      const rankA = statusRank[(a.status || "").toLowerCase()] || 0;
      const rankB = statusRank[(b.status || "").toLowerCase()] || 0;
      if (rankA !== rankB) {
        return rankA - rankB;
      }
      return compareStrings(a.title, b.title);
    },
    date(a, b) {
      const diff =
        parseDateValue(a.publishDate || a.createdAt) -
        parseDateValue(b.publishDate || b.createdAt);
      if (diff !== 0) {
        return diff;
      }
      return compareStrings(a.title, b.title);
    },
  };

  function getSortComparator(key) {
    if (key && typeof sortComparators[key] === "function") {
      return sortComparators[key];
    }
    return sortComparators.date;
  }

  function updateSortIndicators() {
    if (!$sortButtons.length) {
      return;
    }
    $sortButtons.each(function () {
      const $btn = $(this);
      const key = $btn.data("blogSort");
      const isActive = sortState.key === key;
      let ariaValue = "none";

      $btn.removeClass("is-active sort-asc sort-desc");
      if (isActive) {
        ariaValue = sortState.direction === "asc" ? "ascending" : "descending";
        $btn.addClass("is-active");
        $btn.addClass(sortState.direction === "asc" ? "sort-asc" : "sort-desc");
      }

      const $th = $btn.closest("th");
      if ($th.length) {
        $th.attr("aria-sort", ariaValue);
      }
    });
  }

  function getDefaultSortDirection($btn) {
    const attr = ($btn.data("defaultDirection") || "").toString().toLowerCase();
    return attr === "desc" ? "desc" : "asc";
  }

  function setSort(key, direction, options = {}) {
    if (!key) {
      return;
    }
    const normalizedDirection = direction === "desc" ? "desc" : "asc";
    const hasChanged =
      sortState.key !== key || sortState.direction !== normalizedDirection;

    sortState.key = key;
    sortState.direction = normalizedDirection;
    updateSortIndicators();

    if (options.silent) {
      return;
    }
    if (hasChanged || options.forceRender) {
      renderPosts();
    }
  }

  function renderPosts() {
    updateSortIndicators();
    if (postsLoadError) {
      renderTableMessage("Unable to load blog posts. Please try again later.", {
        isError: true,
        countLabel: "Showing 0 posts",
      });
      return;
    }
    if (isLoadingPosts && !posts.length) {
      renderTableMessage("Loading posts…", { countLabel: "Loading posts…" });
      return;
    }

    let filtered = [...posts];
    const category = $("#categoryFilter").val();
    const author = $("#authorFilter").val();
    const search = ($searchInput.val() || "").toString().toLowerCase().trim();

    switch (activeStatusFilter) {
      case "published":
        filtered = filtered.filter((p) => p.status === "published");
        break;
      case "drafts":
        filtered = filtered.filter((p) => p.status === "draft");
        break;
      case "scheduled":
        filtered = filtered.filter((p) => p.status === "scheduled");
        break;
      default:
        break;
    }

    if (category) filtered = filtered.filter((p) => p.category === category);
    if (author) filtered = filtered.filter((p) => p.author === author);
    if (search) {
      filtered = filtered.filter((p) => {
        const title = (p.title || "").toLowerCase();
        const slug = (p.slug || "").toLowerCase();
        const excerpt = (p.excerpt || "").toLowerCase();
        const tags = (p.tags || "").toLowerCase();
        return (
          title.includes(search) ||
          slug.includes(search) ||
          excerpt.includes(search) ||
          tags.includes(search)
        );
      });
    }
    const sortKey = sortState.key || "date";
    const comparator = getSortComparator(sortKey);
    const directionMultiplier = sortState.direction === "desc" ? -1 : 1;
    filtered.sort((a, b) => {
      const result = comparator(a, b);
      if (result !== 0) {
        return result * directionMultiplier;
      }
      return compareStrings(a.title, b.title) * directionMultiplier;
    });

    const countLabel = filtered.length === 1 ? "post" : "posts";
    $postsCountLabel.text(`Showing ${filtered.length} ${countLabel}`);

    if (!filtered.length) {
      const message = posts.length
        ? "No posts match the current filters."
        : "No blog posts available yet.";
      $postsTableBody.html(
        `<tr><td colspan="6" class="text-center text-muted">${message}</td></tr>`,
      );
      updateLastUpdated();
      return;
    }

    const tbody = $postsTableBody;
    tbody.empty();
    filtered.forEach((post) => {
      const safeTitleAttr = escapeAttribute(post.title);
      const safeSlugAttr = escapeAttribute(post.slug);
      const safeAuthorAttr = escapeAttribute(post.author);
      const safeCategoryAttr = escapeAttribute(post.category);
      const safeStatusAttr = escapeAttribute(post.status);
      const safeDateAttr = escapeAttribute(
        post.publishDate || post.createdAt || "",
      );
      const safeAlt = escapeAttribute(
        post.imageAlt || `Featured image for ${post.title || "blog post"}`,
      );
      const thumbnail = post.image
        ? `<div class="blog-table-thumb"><img src="${post.image}" alt="${safeAlt}"></div>`
        : `<div class="blog-table-thumb blog-table-thumb--empty" aria-hidden="true"><span>${getPostInitial(post)}</span></div>`;
      const statusInfo = getStatusBadgeInfo(post.status);
      const isChecked = selectedPostIds.has(post.id);
      const row = $(`
                <tr
                    data-blog-post
                    data-id="${post.id}"
                    data-title="${safeTitleAttr}"
                    data-slug="${safeSlugAttr}"
                    data-author="${safeAuthorAttr}"
                    data-category="${safeCategoryAttr}"
                    data-status="${safeStatusAttr}"
                    data-date="${safeDateAttr}"
                >
                    <td class="blog-list-select-cell">
                        <input type="checkbox" class="blog-row-checkbox" data-id="${post.id}"
                            aria-label="Select ${safeTitleAttr}"${isChecked ? " checked" : ""}>
                    </td>
                    <td>
                        <div class="blog-post-cell">
                            ${thumbnail}
                            <div>
                                <div class="post-title">${post.title}</div>
                            </div>
                        </div>
                        ${post.image ? "" : '<span class="sr-only">No featured image</span>'}
                    </td>
                    <td>
                        <div class="author-info">
                            <div class="author-avatar">${post.author
                              .split(" ")
                              .map((n) => n[0])
                              .join("")}</div>
                            <span>${post.author}</span>
                        </div>
                    </td>
                    <td><span class="category-tag">${post.category}</span></td>
                    <td><span class="status-badge ${statusInfo.className}" aria-label="Status: ${statusInfo.label}">${statusInfo.label}</span></td>
                    <td>${formatDate(post.publishDate || post.createdAt)}</td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-sm btn-secondary view-btn" data-id="${post.id}"><i class="fa-solid fa-eye btn-icon" aria-hidden="true"></i><span class="btn-label">View</span></button>
                            <button class="btn btn-sm btn-secondary edit-post-btn" data-id="${post.id}"><i class="fa-solid fa-pen-to-square btn-icon" aria-hidden="true"></i><span class="btn-label">Edit</span></button>
                            ${post.status !== "published" ? `<button class="btn btn-sm btn-primary publish-btn" data-id="${post.id}"><i class="fa-solid fa-paper-plane btn-icon" aria-hidden="true"></i><span class="btn-label">Publish</span></button>` : ""}
                            <button class="btn btn-sm btn-danger delete-btn" data-id="${post.id}"><i class="fa-solid fa-trash btn-icon" aria-hidden="true"></i><span class="btn-label">Delete</span></button>
                        </div>
                    </td>
                </tr>`);
      tbody.append(row);
    });
    updateLastUpdated();
    $(".view-btn").click(function () {
      const id = parseInt($(this).data("id"));
      viewPost(id);
    });
    $(".edit-post-btn").click(function () {
      const id = parseInt($(this).data("id"));
      editPost(id);
    });
    $(".publish-btn").click(function () {
      const id = parseInt($(this).data("id"));
      publishPost(id);
    });
    $(".delete-btn").click(function () {
      const id = parseInt($(this).data("id"));
      deletePost(id);
    });
  }

  function openPostModal(id = null) {
    const $modalStatusBadge = $("[data-blog-status-badge]");
    if (id) {
      const post = posts.find((p) => p.id === id);
      $("#modalTitle").text("Edit Post");
      $("#postId").val(post.id);
      $("#postTitle").val(post.title);
      $("#postSlug").val(post.slug);
      $("#postExcerpt").val(post.excerpt);
      $("#postImage").val(post.image || "");
      $("#postImageAlt").val(post.imageAlt || "");
      $("#postContent").html(post.content);
      $("#postCategory").val(post.category);
      $("#postAuthor").val(post.author);
      $("#postStatus").val(post.status);
      $("#postTags").val(post.tags);
      if (post.publishDate) {
        $("#publishDate").val(
          new Date(post.publishDate).toISOString().slice(0, 16),
        );
      } else {
        $("#publishDate").val("");
      }
      setImagePreviewFromPost(post);
      updateStatusBadge($modalStatusBadge, post.status);
    } else {
      $("#modalTitle").text("New Post");
      $("#postForm")[0].reset();
      $("#postId").val("");
      $("#postContent").html("");
      $("#publishDate").val("");
      setImagePreviewFromPost({});
      updateStatusBadge($modalStatusBadge, $("#postStatus").val() || "draft");
    }
    openModal("postModal");
  }

  function viewPost(id) {
    const post = posts.find((p) => p.id === id);
    if (!post) return;
    $("#previewTitle").text(post.title);
    updateStatusBadge($("[data-blog-preview-status-badge]"), post.status);
    const statusInfo = getStatusBadgeInfo(post.status);
    const metaHtml = `
            <div class="blog-preview-meta__row">
                <div class="author-info blog-preview-author">
                    <div class="author-avatar">${post.author
                      .split(" ")
                      .map((n) => n[0])
                      .join("")}</div>
                    <div class="blog-preview-author__details">
                        <span class="blog-preview-author__name">${post.author}</span>
                        <span class="blog-preview-date">${formatDate(post.publishDate || post.createdAt)}</span>
                    </div>
                </div>
                <div class="blog-preview-labels">
                    <span class="category-tag">${post.category}</span>
                    <span class="status-badge ${statusInfo.className}" aria-label="Status: ${statusInfo.label}">${statusInfo.label}</span>
                </div>
            </div>`;
    $("#previewMeta").html(metaHtml);
    if (post.image) {
      const previewAlt = escapeAttribute(
        post.imageAlt || `Featured image for ${post.title || "blog post"}`,
      );
      $("#previewImage").html(
        `<figure class="blog-preview-image"><img src="${post.image}" alt="${previewAlt}"></figure>`,
      );
    } else {
      $("#previewImage").empty();
    }
    $("#previewContent").html(post.content);
    $("#editPreviewBtn").data("id", post.id);
    openModal("postPreviewModal");
  }

  function editPost(id) {
    openPostModal(id);
  }

  function deletePost(id) {
    confirmModal("Are you sure you want to delete this post?").then((ok) => {
      if (!ok) {
        return;
      }
      sendJsonRequest(
        resolveCmsPath("modules/blogs/delete_post.php"),
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ id }),
        },
        "Failed to delete post.",
      )
        .then(() => {
          posts = posts.filter((p) => p.id !== id);
          refreshCategoriesFromPosts();
          populateFilters();
          recalculateNextPostId();
          renderPosts();
          updateStats();
          renderCategories();
        })
        .catch((err) => {
          console.error(err);
          alert(err.message || "Failed to delete post.");
        });
    });
  }

  function savePost() {
    const form = $("#postForm")[0];
    if (!form) {
      console.error("Post form element not found.");
      return;
    }
    const isValid =
      typeof form.checkValidity === "function" ? form.checkValidity() : true;
    if (!isValid) {
      if (typeof form.reportValidity === "function") {
        form.reportValidity();
      }
      return;
    }
    const formData = new FormData(form);
    const imageUrl = normalizeImageValue(formData.get("image"));
    const imageAlt = (formData.get("imageAlt") || "").trim();
    const publishDateValue = formData.get("publishDate");
    const tagsValue = formData.get("tags");
    const data = {
      title: (formData.get("title") || "").toString().trim(),
      slug: (formData.get("slug") || "").toString().trim(),
      excerpt: (formData.get("excerpt") || "").toString().trim(),
      content: $("#postContent").html(),
      category: (formData.get("category") || "").toString().trim(),
      author: (formData.get("author") || "").toString().trim(),
      status: (formData.get("status") || "").toString().trim(),
      tags: tagsValue == null ? "" : tagsValue.toString(),
      publishDate: publishDateValue ? publishDateValue.toString().trim() : "",
      image: imageUrl,
      imageAlt,
    };
    const id = $("#postId").val();
    if (id) {
      data.id = parseInt(id, 10);
    }

    sendJsonRequest(
      resolveCmsPath("modules/blogs/save_post.php"),
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      },
      "Failed to save post.",
    )
      .then((saved) => {
        const normalized = normalizePostFromServer(saved);
        const existingIndex = posts.findIndex((p) => p.id === normalized.id);
        if (existingIndex !== -1) {
          posts[existingIndex] = Object.assign(
            {},
            posts[existingIndex],
            normalized,
          );
        } else {
          posts.push(normalized);
        }
        refreshCategoriesFromPosts();
        populateFilters();
        recalculateNextPostId();
        closeModal("postModal");
        renderPosts();
        updateStats();
        renderCategories();
      })
      .catch((err) => {
        console.error(err);
        alert(err.message || "Failed to save post.");
      });
  }

  function publishPost(id) {
    const post = posts.find((p) => p.id === id);
    if (!post) return;
    confirmModal("Publish this post now?").then((ok) => {
      if (!ok) return;
      sendJsonRequest(
        resolveCmsPath("modules/blogs/publish_post.php"),
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ id }),
        },
        "Failed to publish post.",
      )
        .then((updated) => {
          const normalized = normalizePostFromServer(updated);
          const existingIndex = posts.findIndex((p) => p.id === normalized.id);
          if (existingIndex !== -1) {
            posts[existingIndex] = Object.assign(
              {},
              posts[existingIndex],
              normalized,
            );
          } else {
            posts.push(normalized);
          }
          refreshCategoriesFromPosts();
          recalculateNextPostId();
          renderPosts();
          updateStats();
        })
        .catch((err) => {
          console.error(err);
          alert(err.message || "Failed to publish post.");
        });
    });
  }

  function openCategoriesModal() {
    renderCategories();
    openModal("categoriesModal");
  }

  function renderCategories() {
    const list = $("#categoriesList");
    list.empty();
    if (!categories.length) {
      list.html(
        '<p class="blog-category-empty">No categories yet. Create your first topic above.</p>',
      );
      return;
    }

    const items = categories
      .map((cat) => {
        const count = posts.filter((p) => p.category === cat).length;
        const countLabel = count === 1 ? "post" : "posts";
        return `
                <div class="blog-category-item">
                    <div class="blog-category-item__info">
                        <span class="blog-category-item__name">${cat}</span>
                        <span class="blog-category-item__count">${count} ${countLabel}</span>
                    </div>
                    <button type="button" class="c-button c-button--danger c-button--sm delete-category-btn" data-category="${cat}">Delete</button>
                </div>`;
      })
      .join("");
    list.html(items);
    $(".delete-category-btn").click(function () {
      const cat = $(this).data("category");
      deleteCategory(cat);
    });
  }

  function addCategory() {
    const name = $("#newCategoryName").val().trim();
    if (name && !categories.includes(name)) {
      categories.push(name);
      $("#newCategoryName").val("");
      renderCategories();
      populateFilters();
    }
  }

  function deleteCategory(name) {
    confirmModal(
      `Are you sure you want to delete the category "${name}"?`,
    ).then((ok) => {
      if (ok) {
        categories = categories.filter((c) => c !== name);
        posts.forEach((p) => {
          if (p.category === name) p.category = "Uncategorized";
        });
        renderCategories();
        populateFilters();
        renderPosts();
      }
    });
  }

  function updateLastUpdated() {
    let latest = null;
    posts.forEach((post) => {
      const baseValue = post.publishDate || post.createdAt;
      const date = new Date(baseValue);
      if (!Number.isNaN(date.getTime())) {
        if (!latest || date > latest) {
          latest = date;
        }
      }
    });

    if (latest) {
      $("#blogsLastUpdated").text(`Updated ${formatDate(latest)}`);
    } else {
      $("#blogsLastUpdated").text("No posts yet");
    }
  }

  function formatDate(value) {
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) {
      return "—";
    }
    return d.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  $(window).click(function (e) {
    if (e.target.id === "postModal") closeModal("postModal");
    if (e.target.id === "categoriesModal") closeModal("categoriesModal");
    if (e.target.id === "postPreviewModal") closeModal("postPreviewModal");
    if (e.target.id === "mediaPickerModal") closeModal("mediaPickerModal");
  });

  $("#postStatus").on("change", function () {
    updateStatusBadge($("[data-blog-status-badge]"), $(this).val());
    updateBlogScheduleSummary();
  });

  $("#publishDate").on("change input", updateBlogScheduleSummary);

  // ─────────────────────────────────────────────────────────────────────
  // Feature 5: Autosave / Draft Recovery (blogs)
  // ─────────────────────────────────────────────────────────────────────
  let blogAutosaveTimer = null;
  let blogIsDirty = false;
  let blogCurrentDraft = null;

  function markBlogDirty() { blogIsDirty = true; }

  $("#postTitle, #postSlug, #postExcerpt, #postCategory, #postAuthor, " +
    "#postStatus, #publishDate, #postTags, #postImage, #postImageAlt")
    .on("input change", markBlogDirty);
  // ContentEditable editor triggers input
  $("#postContent").on("input", markBlogDirty);

  function collectBlogFormData() {
    const id = $("#postId").val();
    return {
      id:          id ? parseInt(id, 10) : 0,
      title:       $("#postTitle").val(),
      slug:        $("#postSlug").val(),
      excerpt:     $("#postExcerpt").val(),
      content:     $("#postContent").html(),
      category:    $("#postCategory").val(),
      author:      $("#postAuthor").val(),
      status:      $("#postStatus").val(),
      tags:        $("#postTags").val(),
      publishDate: $("#publishDate").val(),
      image:       $("#postImage").val(),
      imageAlt:    $("#postImageAlt").val(),
    };
  }

  function saveBlogDraftNow() {
    const id = parseInt($("#postId").val(), 10);
    if (!id || !blogIsDirty) return;
    const csrfToken = $('meta[name="csrf-token"]').attr("content") || "";
    sendJsonRequest(
      resolveCmsPath("modules/blogs/save_draft.php"),
      {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
        body: JSON.stringify(collectBlogFormData()),
      },
      "Draft save failed."
    ).then(function (res) {
      if (res && res.status === "success") blogIsDirty = false;
    }).catch(function () {});
  }

  function startBlogAutosave() {
    if (blogAutosaveTimer) return;
    blogAutosaveTimer = setInterval(function () {
      if (blogIsDirty) saveBlogDraftNow();
    }, 30000);
  }

  function stopBlogAutosave() {
    if (blogAutosaveTimer) { clearInterval(blogAutosaveTimer); blogAutosaveTimer = null; }
  }

  function showBlogDraftBanner(draft) {
    blogCurrentDraft = draft;
    const savedDate = draft.saved_at
      ? new Date(draft.saved_at * 1000).toLocaleString() : "unknown time";
    $("#blogDraftRecoveryText").text("Autosaved draft from " + savedDate + " — restore it?");
    $("#blogDraftRecoveryBanner").removeAttr("hidden");
  }

  function hideBlogDraftBanner() {
    blogCurrentDraft = null;
    $("#blogDraftRecoveryBanner").attr("hidden", "hidden");
  }

  function applyBlogDraft(draft) {
    if (!draft) return;
    if (draft.title    !== undefined) $("#postTitle").val(draft.title).trigger("input");
    if (draft.slug     !== undefined) $("#postSlug").val(draft.slug);
    if (draft.excerpt  !== undefined) $("#postExcerpt").val(draft.excerpt);
    if (draft.content  !== undefined) $("#postContent").html(draft.content);
    if (draft.category !== undefined) $("#postCategory").val(draft.category);
    if (draft.author   !== undefined) $("#postAuthor").val(draft.author);
    if (draft.status   !== undefined) {
      $("#postStatus").val(draft.status);
      updateStatusBadge($("[data-blog-status-badge]"), draft.status);
    }
    if (draft.tags !== undefined) $("#postTags").val(draft.tags);
    if (draft.publishDate !== undefined) {
      try {
        const d = new Date(draft.publishDate);
        $("#publishDate").val(isNaN(d.getTime()) ? "" : d.toISOString().slice(0, 16));
      } catch (e) { $("#publishDate").val(""); }
    }
    if (draft.image    !== undefined) $("#postImage").val(draft.image).trigger("change");
    if (draft.imageAlt !== undefined) $("#postImageAlt").val(draft.imageAlt);
    updateBlogScheduleSummary();
    markBlogDirty();
  }

  function checkBlogDraft(postId, postCreatedAt) {
    if (!postId) return;
    $.getJSON(resolveCmsPath("modules/blogs/get_draft.php"), { id: postId })
      .done(function (res) {
        if (res.status !== "ok" || !res.draft) return;
        const draftTs = res.draft.saved_at || 0;
        const postTs  = postCreatedAt ? new Date(postCreatedAt).getTime() / 1000 : 0;
        if (draftTs > postTs) showBlogDraftBanner(res.draft);
      });
  }

  $("#blogRestoreDraftBtn").on("click", function () {
    applyBlogDraft(blogCurrentDraft);
    hideBlogDraftBanner();
  });
  $("#blogDiscardDraftBtn").on("click", hideBlogDraftBanner);

  // Augment openPostModal to hook autosave and draft check
  const _origOpenPostModal = openPostModal;
  openPostModal = function (id) {
    _origOpenPostModal(id || null);
    hideBlogDraftBanner();
    blogIsDirty = false;
    if (id) {
      const post = posts.find(function (p) { return p.id === id; });
      startBlogAutosave();
      checkBlogDraft(id, post ? (post.publishDate || post.createdAt || "") : "");
    } else {
      stopBlogAutosave();
    }
  };

  $("#closeModal, #cancelBtn").off("click").on("click", function () {
    stopBlogAutosave();
    hideBlogDraftBanner();
    closeModal("postModal");
  });

  // ─────────────────────────────────────────────────────────────────────
  // Feature 7: Scheduling Summary (blogs)
  // ─────────────────────────────────────────────────────────────────────
  function updateBlogScheduleSummary() {
    const status      = $("#postStatus").val();
    const publishDate = $("#publishDate").val();
    const $summary    = $("#blogScheduleSummary");
    const $text       = $("#blogScheduleSummaryText");

    if (!publishDate && status !== "scheduled") {
      $summary.attr("hidden", "hidden");
      return;
    }

    let msg = "";
    if (publishDate) {
      const d = new Date(publishDate);
      if (!isNaN(d.getTime())) {
        const now = new Date();
        if (status === "scheduled" || d > now) {
          msg = "Scheduled to publish " + d.toLocaleString(undefined, {
            year: "numeric", month: "short", day: "numeric",
            hour: "numeric", minute: "2-digit",
          });
        } else if (status === "published") {
          msg = "Published " + d.toLocaleString(undefined, {
            year: "numeric", month: "short", day: "numeric",
          });
        }
      }
    } else if (status === "scheduled") {
      msg = "Status is Scheduled — set a publish date above.";
    }

    if (msg) { $text.text(msg); $summary.removeAttr("hidden"); }
    else { $summary.attr("hidden", "hidden"); }
  }

  // ─────────────────────────────────────────────────────────────────────
  // Feature 8: Bulk Actions — Blogs
  // ─────────────────────────────────────────────────────────────────────
  function updateBlogsBulkToolbar() {
    const count  = selectedPostIds.size;
    const total  = $(".blog-row-checkbox").length;
    const $bar   = $("#blogsBulkToolbar");
    if (count > 0) {
      $bar.removeAttr("hidden");
      $("#blogsBulkCount").text(count + " selected");
    } else {
      $bar.attr("hidden", "hidden");
    }
    $("#selectAllPosts")
      .prop("indeterminate", count > 0 && count < total)
      .prop("checked", total > 0 && count === total);
  }

  $(document).on("change", ".blog-row-checkbox", function () {
    const id = parseInt($(this).data("id"), 10);
    if ($(this).is(":checked")) selectedPostIds.add(id);
    else selectedPostIds.delete(id);
    updateBlogsBulkToolbar();
  });

  $("#selectAllPosts").on("change", function () {
    const checked = $(this).is(":checked");
    selectedPostIds.clear();
    $(".blog-row-checkbox").prop("checked", checked).each(function () {
      if (checked) selectedPostIds.add(parseInt($(this).data("id"), 10));
    });
    updateBlogsBulkToolbar();
  });

  $("#blogsBulkClearBtn").on("click", function () {
    selectedPostIds.clear();
    $(".blog-row-checkbox").prop("checked", false);
    $("#selectAllPosts").prop("checked", false).prop("indeterminate", false);
    updateBlogsBulkToolbar();
  });

  function executeBulkBlogAction(action) {
    const ids = Array.from(selectedPostIds);
    if (!ids.length) return;
    const label = action === "delete"
      ? "Delete " + ids.length + " post(s)? This cannot be undone."
      : (action === "publish" ? "Publish" : "Unpublish") + " " + ids.length + " post(s)?";
    confirmModal(label).then(function (ok) {
      if (!ok) return;
      const csrfToken = $('meta[name="csrf-token"]').attr("content") || "";
      sendJsonRequest(
        resolveCmsPath("modules/blogs/bulk_action.php"),
        {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
          body: JSON.stringify({ action: action, ids: ids }),
        },
        "Bulk action failed."
      ).then(function (res) {
        if (res && res.status === "success") {
          selectedPostIds.clear();
          updateBlogsBulkToolbar();
          loadPostsFromServer();
        }
      }).catch(function (err) {
        alert(err.message || "An error occurred during the bulk action.");
      });
    });
  }

  $("#blogsBulkPublishBtn").on("click",   function () { executeBulkBlogAction("publish"); });
  $("#blogsBulkUnpublishBtn").on("click", function () { executeBulkBlogAction("unpublish"); });
  $("#blogsBulkDeleteBtn").on("click",    function () { executeBulkBlogAction("delete"); });
});
