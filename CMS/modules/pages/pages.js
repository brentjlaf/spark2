// File: pages.js
$(function () {
  const $searchInput = $("#pagesSearchInput");
  const $filterButtons = $("[data-pages-filter]");
  const $listView = $("#pagesListView");
  const $sortButtons = $("[data-pages-sort]");
  const $emptyState = $("#pagesEmptyState");
  const $visibleCount = $("#pagesVisibleCount");
  const $emptyCta = $("#pagesEmptyCta");
  let activeFilter = "all";
  const sortState = { key: null, direction: "asc" };
  let homepageSlug = ($listView.data("homepageSlug") || "").toString();
  const homepageIconHtml =
    '<i class="fa-solid fa-house pages-list-title-icon pages-list-title-icon--home" aria-hidden="true"></i>';
  const ROBOTS_DEFAULT = "index,follow";
  const $templateSelect = $("#template");
  const TEMP_TEMPLATE_CLASS = "template-option--temporary";
  const pageMediaState = {
    loaded: false,
    loading: false,
    items: [],
    currentSetter: null,
  };
  const pageMediaSelectors = {
    modal: document.getElementById("pageMediaPickerModal"),
    grid: document.querySelector("[data-page-media-grid]"),
    search: document.querySelector("[data-page-media-search]"),
  };
  const $pageSaveState = $("#pageModal [data-save-state]");
  const pageSaveLabels = {
    saving: "Saving…",
    saved: "Saved",
    unsaved: "Unsaved changes",
  };
  let pageIsSaving = false;
  let pageIsDirty = false;
  if ($("#robots").length) {
    $("#robots").val(ROBOTS_DEFAULT);
  }
  $("#cancelEdit").hide();

  function sanitizeToastMessage(value, fallback = "") {
    const raw = value === null || value === undefined ? "" : String(value);
    const container = document.createElement("div");
    container.innerHTML = raw;
    const text = (container.textContent || container.innerText || "").trim();
    if (text) {
      return text;
    }
    return fallback || "";
  }

  function toastSuccess(message) {
    const text = sanitizeToastMessage(message);
    if (!text) {
      return;
    }
    if (
      window.AdminNotifications &&
      typeof window.AdminNotifications.showSuccessToast === "function"
    ) {
      window.AdminNotifications.showSuccessToast(text);
    } else {
      alertModal(text);
    }
  }

  function toastError(message) {
    const text = sanitizeToastMessage(message);
    if (!text) {
      return;
    }
    if (
      window.AdminNotifications &&
      typeof window.AdminNotifications.showErrorToast === "function"
    ) {
      window.AdminNotifications.showErrorToast(text);
    } else {
      alertModal(text);
    }
  }

  function rememberToast(type, message) {
    const text = sanitizeToastMessage(message);
    if (!text) {
      return;
    }
    if (
      window.AdminNotifications &&
      typeof window.AdminNotifications.rememberToast === "function"
    ) {
      window.AdminNotifications.rememberToast(type, text);
    }
  }

  function extractErrorMessage(xhr, fallback) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
      return sanitizeToastMessage(xhr.responseJSON.message, fallback);
    }
    if (
      xhr &&
      typeof xhr.responseText === "string" &&
      xhr.responseText.trim().length
    ) {
      return sanitizeToastMessage(xhr.responseText, fallback);
    }
    return fallback;
  }

  function setPageSaveState(state) {
    if (!$pageSaveState.length) {
      return;
    }
    const nextState = pageSaveLabels[state] ? state : "saved";
    $pageSaveState.attr("data-state", nextState);
    $pageSaveState
      .find("[data-save-state-text]")
      .text(pageSaveLabels[nextState]);
    if (nextState === "saving") {
      $pageSaveState.attr("aria-busy", "true");
    } else {
      $pageSaveState.removeAttr("aria-busy");
    }
  }

  function resetPageSaveState() {
    pageIsDirty = false;
    pageIsSaving = false;
    setPageSaveState("saved");
  }

  function markPageDirty() {
    if (pageIsSaving) {
      return;
    }
    pageIsDirty = true;
    setPageSaveState("unsaved");
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return "";
    }
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function escapeAttribute(value) {
    if (value === null || value === undefined) {
      return "";
    }
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;")
      .replace(/`/g, "&#96;");
  }

  function filterPageMediaItems(search) {
    const normalized = (search || "").toString().trim().toLowerCase();
    const items = Array.isArray(pageMediaState.items)
      ? pageMediaState.items.filter((item) => {
          const type = (item?.type || "").toString().toLowerCase();
          return type === "images";
        })
      : [];
    if (!normalized) {
      return items;
    }
    return items.filter((item) => {
      const name = String(item?.name || "").toLowerCase();
      const file = String(item?.file || "").toLowerCase();
      let tags = "";
      if (Array.isArray(item?.tags)) {
        tags = item.tags.join(" ").toLowerCase();
      } else if (typeof item?.tags === "string") {
        tags = item.tags.toLowerCase();
      }
      return (
        name.includes(normalized) ||
        file.includes(normalized) ||
        tags.includes(normalized)
      );
    });
  }

  function renderPageMediaLibrary({
    status = "idle",
    items = [],
    search = "",
  } = {}) {
    const grid = pageMediaSelectors.grid;
    if (!grid) {
      return;
    }
    grid.setAttribute("aria-busy", status === "loading" ? "true" : "false");
    if (status === "loading") {
      grid.innerHTML = '<p class="page-media-status">Loading media…</p>';
      return;
    }
    if (status === "error") {
      grid.innerHTML =
        '<p class="page-media-status page-media-status--error">Unable to load the media library. Please try again.</p>';
      return;
    }
    const list = Array.isArray(items) ? items.slice() : [];
    if (list.length === 0) {
      if (search) {
        grid.innerHTML = `<p class="page-media-status">No images match &ldquo;${escapeHtml(search)}&rdquo;. Try a different keyword.</p>`;
      } else {
        grid.innerHTML =
          '<p class="page-media-status">No images found in the media library. Upload images in the Media module.</p>';
      }
      return;
    }
    list.sort((a, b) => {
      const aName = String(a?.name || a?.file || "").toLowerCase();
      const bName = String(b?.name || b?.file || "").toLowerCase();
      return aName.localeCompare(bName, undefined, { sensitivity: "base" });
    });
    grid.innerHTML = list
      .map((item) => {
        const file = escapeAttribute(item?.file || "");
        const thumbSource = escapeAttribute(
          item?.thumbnail || item?.file || "",
        );
        const name = escapeHtml(item?.name || item?.file || "Media item");
        return `
                        <button type="button" class="page-media-item" data-page-media-item data-file="${file}" role="option">
                            <span class="page-media-thumb"><img src="${thumbSource}" alt="${name}"></span>
                            <span class="page-media-name">${name}</span>
                        </button>
                    `;
      })
      .join("");
  }

  function loadPageMediaLibrary() {
    if (pageMediaState.loading) {
      return;
    }
    pageMediaState.loading = true;
    renderPageMediaLibrary({ status: "loading" });
    fetch("modules/media/list_media.php?sort=name&order=asc")
      .then((response) => {
        if (!response.ok) {
          throw new Error("Unable to load media");
        }
        return response.json();
      })
      .then((data) => {
        pageMediaState.items = Array.isArray(data?.media) ? data.media : [];
        pageMediaState.loaded = true;
        pageMediaState.loading = false;
        const term = pageMediaSelectors.search?.value || "";
        renderPageMediaLibrary({
          items: filterPageMediaItems(term),
          search: term,
        });
      })
      .catch(() => {
        pageMediaState.loading = false;
        renderPageMediaLibrary({ status: "error" });
      });
  }

  function openPageMediaPicker() {
    if (!pageMediaSelectors.modal) {
      return;
    }
    openModal("pageMediaPickerModal");
    if (pageMediaSelectors.search) {
      pageMediaSelectors.search.value = "";
    }
    if (pageMediaState.loaded) {
      renderPageMediaLibrary({ items: filterPageMediaItems(""), search: "" });
    } else {
      renderPageMediaLibrary({ status: "loading" });
      loadPageMediaLibrary();
    }
    setTimeout(() => {
      pageMediaSelectors.search?.focus();
    }, 120);
  }

  function closePageMediaPicker() {
    closeModal("pageMediaPickerModal");
    pageMediaState.currentSetter = null;
  }

  function initPageMediaPicker() {
    if (!pageMediaSelectors.modal) {
      return;
    }
    const closeButtons = pageMediaSelectors.modal.querySelectorAll(
      "[data-page-media-close]",
    );
    closeButtons.forEach((button) => {
      button.addEventListener("click", (event) => {
        event.preventDefault();
        closePageMediaPicker();
      });
    });
    if (pageMediaSelectors.search) {
      pageMediaSelectors.search.addEventListener("input", () => {
        const term = pageMediaSelectors.search?.value || "";
        if (!pageMediaState.loaded) {
          return;
        }
        renderPageMediaLibrary({
          items: filterPageMediaItems(term),
          search: term,
        });
      });
    }
    if (pageMediaSelectors.grid) {
      pageMediaSelectors.grid.addEventListener("click", (event) => {
        const target = event.target.closest("[data-page-media-item]");
        if (!target) {
          return;
        }
        const file = target.getAttribute("data-file") || "";
        if (file && typeof pageMediaState.currentSetter === "function") {
          pageMediaState.currentSetter(file);
        }
        pageMediaState.currentSetter = null;
        closePageMediaPicker();
      });
    }
    pageMediaSelectors.modal.addEventListener("click", (event) => {
      if (event.target === pageMediaSelectors.modal) {
        pageMediaState.currentSetter = null;
      }
    });
  }

  function initOgImagePicker() {
    const picker = document.querySelector("[data-page-og-image-picker]");
    if (!picker) {
      return null;
    }
    const input = picker.querySelector("#og_image");
    const preview = picker.querySelector("[data-page-media-preview]");
    const chooseBtn = picker.querySelector("[data-page-media-open]");
    const clearBtn = picker.querySelector("[data-page-media-clear]");
    const form = document.getElementById("pageForm");
    if (!input || !preview || !chooseBtn || !clearBtn) {
      return null;
    }

    function normalizeSelectedImageValue(value) {
      const normalized = typeof value === "string" ? value.trim() : "";
      if (!normalized) {
        return "";
      }
      if (/^(?:[a-z]+:)?\/\//i.test(normalized) || normalized.startsWith("data:")) {
        return normalized;
      }
      try {
        return new URL(normalized, `${window.location.origin}/`).toString();
      } catch {
        return normalized;
      }
    }

    function updatePreview(value, { updateInput = true } = {}) {
      const normalized = typeof value === "string" ? value.trim() : "";
      if (updateInput) {
        input.value = normalized;
      }
      if (normalized) {
        preview.innerHTML = `<img src="${escapeAttribute(normalized)}" alt="Selected OG image">`;
        preview.classList.add("has-image");
        clearBtn.hidden = false;
      } else {
        preview.innerHTML =
          '<span class="page-media-placeholder">No image selected yet.</span>';
        preview.classList.remove("has-image");
        clearBtn.hidden = true;
      }
    }

    chooseBtn.addEventListener("click", (event) => {
      event.preventDefault();
      pageMediaState.currentSetter = (value) => {
        updatePreview(normalizeSelectedImageValue(value));
      };
      openPageMediaPicker();
    });

    clearBtn.addEventListener("click", (event) => {
      event.preventDefault();
      updatePreview("");
    });

    input.addEventListener("input", () => {
      updatePreview(input.value, { updateInput: false });
    });

    if (form) {
      form.addEventListener("reset", () => {
        setTimeout(() => updatePreview("", { updateInput: false }), 0);
      });
    }

    updatePreview(input.value, { updateInput: false });

    return {
      setValue(value) {
        updatePreview(value);
      },
    };
  }

  initPageMediaPicker();
  const ogImagePicker = initOgImagePicker();

  function removeTemporaryTemplateOptions() {
    if (!$templateSelect.length) {
      return;
    }
    $templateSelect.find("option." + TEMP_TEMPLATE_CLASS).remove();
  }

  function openPageModal() {
    openModal("pageModal");
    resetPageSaveState();
  }

  function closePageModal() {
    closeModal("pageModal");
    $("#cancelEdit").hide();
    removeTemporaryTemplateOptions();
  }

  function getPageRows() {
    if (!$listView.length) {
      return $();
    }
    return $listView.find("tbody [data-page-item]");
  }

  function refreshHomepageIndicators(newSlug) {
    homepageSlug = (newSlug || "").toString();
    const normalizedSlug = homepageSlug;
    getPageRows().each(function () {
      const $row = $(this);
      const rowSlug = ($row.attr("data-slug") || "").toString();
      const isHomepageRow = normalizedSlug !== "" && rowSlug === normalizedSlug;
      $row.attr("data-homepage", isHomepageRow ? 1 : 0);
      $row.data("homepage", isHomepageRow ? 1 : 0);
      const $titleText = $row.find(".pages-list-title-text");
      if ($titleText.length) {
        $titleText.find(".pages-list-title-icon--home").remove();
        if (isHomepageRow) {
          $titleText.prepend(homepageIconHtml);
        }
      }
    });
  }

  function maybeUpdateHomepage(slug, shouldSetHomepage) {
    if (!shouldSetHomepage) {
      return null;
    }
    const normalizedSlug = (slug || "").toString();
    if (normalizedSlug === "" || normalizedSlug === homepageSlug) {
      return null;
    }
    return $.post("modules/pages/set_home.php", { slug: normalizedSlug });
  }

  function updateVisibleCount(count) {
    if (!$visibleCount.length) {
      return;
    }
    const label = count === 1 ? "page" : "pages";
    $visibleCount.text(`Showing ${count} ${label}`);
  }

  function refreshFilterCounts() {
    const counts = {
      all: 0,
      published: 0,
      drafts: 0,
      restricted: 0,
    };

    getPageRows().each(function () {
      const $row = $(this);
      counts.all++;
      if ($row.data("live") == 1) {
        counts.published++;
      } else {
        counts.drafts++;
      }
      const access = (($row.data("access") || "public") + "").toLowerCase();
      if (access !== "public") {
        counts.restricted++;
      }
    });

    Object.keys(counts).forEach(function (key) {
      $(`.pages-filter-count[data-count="${key}"]`).text(counts[key]);
    });
  }

  function rowMatchesFilter($row) {
    switch (activeFilter) {
      case "published":
        return $row.data("live") == 1;
      case "drafts":
        return $row.data("live") != 1;
      case "restricted":
        return (
          (($row.data("access") || "public") + "").toLowerCase() !== "public"
        );
      case "all":
      default:
        return true;
    }
  }

  function applyPageFilters() {
    if (!$listView.length) {
      return;
    }

    const query = ($searchInput.val() || "").toString().toLowerCase();
    let visible = 0;
    const $rows = getPageRows();
    $rows.each(function () {
      const $row = $(this);
      const title = (
        $row.find(".pages-list-title-text").text() || ""
      ).toLowerCase();
      const slugAttr = ($row.attr("data-slug") || "").toLowerCase();
      const slugData = (($row.data("slug") || "") + "").toLowerCase();
      const slug = slugAttr || slugData;
      const matchesQuery =
        !query || title.indexOf(query) !== -1 || slug.indexOf(query) !== -1;
      const matchesFilter = rowMatchesFilter($row);

      if (matchesQuery && matchesFilter) {
        $row.removeAttr("hidden");
        visible++;
      } else {
        $row.attr("hidden", "hidden");
      }
    });

    if ($emptyState.length) {
      if (visible === 0) {
        $emptyState.removeAttr("hidden");
      } else {
        $emptyState.attr("hidden", "hidden");
      }
    }

    if ($listView.length) {
      if (visible === 0) {
        $listView.attr("hidden", "hidden");
      } else {
        $listView.removeAttr("hidden");
      }
    }

    updateVisibleCount(visible);
    refreshFilterCounts();
    applySort();
  }

  function normalizeAccess(value) {
    return ((value || "public") + "").toLowerCase();
  }

  function compareStrings(a, b) {
    return (a || "")
      .toString()
      .localeCompare((b || "").toString(), undefined, {
        sensitivity: "base",
        numeric: true,
      });
  }

  function compareTitles($a, $b) {
    const titleA = ($a.data("title") || "").toString();
    const titleB = ($b.data("title") || "").toString();
    const primary = compareStrings(titleA, titleB);
    if (primary !== 0) {
      return primary;
    }
    return compareStrings(
      ($a.data("slug") || "").toString(),
      ($b.data("slug") || "").toString(),
    );
  }

  function parseNumber(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num : 0;
  }

  const comparators = {
    title: compareTitles,
    status: function ($a, $b) {
      const statusA = parseNumber($a.data("live")) === 1 ? 1 : 0;
      const statusB = parseNumber($b.data("live")) === 1 ? 1 : 0;
      if (statusA !== statusB) {
        return statusA - statusB;
      }
      return compareTitles($a, $b);
    },
    views: function ($a, $b) {
      return parseNumber($a.data("views")) - parseNumber($b.data("views"));
    },
    updated: function ($a, $b) {
      return (
        parseNumber($a.data("last_modified")) -
        parseNumber($b.data("last_modified"))
      );
    },
    access: function ($a, $b) {
      return compareStrings(
        normalizeAccess($a.data("access")),
        normalizeAccess($b.data("access")),
      );
    },
  };

  function applySort() {
    if (!$listView.length || !sortState.key) {
      return;
    }
    const comparator = comparators[sortState.key];
    if (typeof comparator !== "function") {
      return;
    }
    const $tbody = $listView.find("tbody");
    if (!$tbody.length) {
      return;
    }
    const rows = getPageRows().get();
    rows.sort(function (a, b) {
      const $a = $(a);
      const $b = $(b);
      const aIsHomepage = $a.data("homepage") == 1;
      const bIsHomepage = $b.data("homepage") == 1;

      if (aIsHomepage && !bIsHomepage) {
        return -1;
      }
      if (!aIsHomepage && bIsHomepage) {
        return 1;
      }

      const result = comparator($a, $b);
      if (sortState.direction === "asc") {
        return result;
      }
      return -result;
    });
    rows.forEach(function (row) {
      $tbody.append(row);
    });
  }

  function updateSortIndicators() {
    if (!$sortButtons.length) {
      return;
    }
    $sortButtons.each(function () {
      const $btn = $(this);
      const key = $btn.data("pagesSort");
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

  function setSort(key, direction) {
    if (!key) {
      return;
    }
    const normalizedDirection = direction === "desc" ? "desc" : "asc";
    sortState.key = key;
    sortState.direction = normalizedDirection;
    updateSortIndicators();
    applySort();
  }

  let slugEdited = false;
  function slugify(text) {
    return text
      .toString()
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "")
      .replace(/-+/g, "-");
  }

  $("#slug").on("input", function () {
    slugEdited = true;
  });
  $("#title").on("input", function () {
    if ($("#pageId").val() === "" && !slugEdited) {
      $("#slug").val(slugify($(this).val()));
    }
  });

  function formatTimestamp(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return "";
    }
    return date.toLocaleString(undefined, {
      year: "numeric",
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit",
    });
  }

  function parseLocalDateTime(value) {
    if (!value) {
      return null;
    }
    const parts = value.toString().split("T");
    if (parts.length !== 2) {
      return null;
    }
    const dateBits = parts[0].split("-").map(Number);
    if (dateBits.length !== 3 || dateBits.some(Number.isNaN)) {
      return null;
    }
    const timeBits = parts[1].split(":").map(Number);
    if (timeBits.length < 2 || timeBits.slice(0, 2).some(Number.isNaN)) {
      return null;
    }
    const [year, month, day] = dateBits;
    const [hour, minute] = timeBits;
    const parsed = new Date(year, month - 1, day, hour, minute);
    if (Number.isNaN(parsed.getTime())) {
      return null;
    }
    return parsed;
  }

  function evaluateScheduleState(data) {
    const publishedFlag = data && data.published ? 1 : 0;
    const publishAt = data && data.publish_at ? data.publish_at.toString() : "";
    const unpublishAt =
      data && data.unpublish_at ? data.unpublish_at.toString() : "";
    const publishDate = parseLocalDateTime(publishAt);
    const unpublishDate = parseLocalDateTime(unpublishAt);
    const now = new Date();

    let state = "draft";
    let isLive = 0;
    let statusText = "Draft";
    let statusClass = "status-draft";
    let statusNote = "";

    if (publishedFlag) {
      state = "published";
      isLive = 1;
      statusText = "Published";
      statusClass = "status-published";

      if (publishDate && now < publishDate) {
        state = "scheduled";
        isLive = 0;
        statusText = "Scheduled";
        statusClass = "status-scheduled";
        statusNote = publishDate
          ? `Publishes ${formatTimestamp(publishDate)}`
          : "";
      }

      if (unpublishDate) {
        if (now >= unpublishDate) {
          state = "expired";
          isLive = 0;
          statusText = "Archived";
          statusClass = "status-archived";
          statusNote = `Archived ${formatTimestamp(unpublishDate)}`;
        } else if (state !== "scheduled") {
          statusNote = `Unpublishes ${formatTimestamp(unpublishDate)}`;
        }
      }

      if (
        publishDate &&
        unpublishDate &&
        publishDate.getTime() >= unpublishDate.getTime()
      ) {
        if (now < publishDate) {
          state = "scheduled";
          isLive = 0;
          statusText = "Scheduled";
          statusClass = "status-scheduled";
          statusNote = publishDate
            ? `Publishes ${formatTimestamp(publishDate)}`
            : "";
        } else {
          state = "expired";
          isLive = 0;
          statusText = "Archived";
          statusClass = "status-archived";
          const reference = unpublishDate || publishDate;
          statusNote = reference
            ? `Archived ${formatTimestamp(reference)}`
            : "";
        }
      }
    }

    return {
      state,
      isLive,
      statusText,
      statusClass,
      statusNote,
      publishAt: publishAt || "",
      unpublishAt: unpublishAt || "",
    };
  }

  const PAGE_STATUS_CLASSES =
    "status-published status-draft status-scheduled status-archived";

  function updatePageModalStatusBadge(scheduleState) {
    const $badge = $("[data-page-status-badge]");
    if (!$badge.length || !scheduleState) {
      return;
    }
    $badge.removeClass(PAGE_STATUS_CLASSES);
    $badge.addClass(scheduleState.statusClass);
    $badge.text(scheduleState.statusText);
    $badge.attr("aria-label", `Status: ${scheduleState.statusText}`);
  }

  function getScheduleStateFromForm() {
    return evaluateScheduleState({
      published: $("#published").prop("checked") ? 1 : 0,
      publish_at: $("#publish_at").val(),
      unpublish_at: $("#unpublish_at").val(),
    });
  }

  function updatePageRow(data) {
    if (!data || !data.id) {
      return;
    }

    const $row = getPageRows().filter(`[data-id="${data.id}"]`);
    if (!$row.length) {
      return;
    }
    const publishedFlag = data.published ? 1 : 0;
    const accessValue = ((data.access || "public") + "").toLowerCase();
    const isRestricted = accessValue !== "public";
    const lastModifiedSeconds =
      typeof data.last_modified === "number"
        ? data.last_modified
        : Math.floor(Date.now() / 1000);
    const lastModifiedDate =
      lastModifiedSeconds > 0 ? new Date(lastModifiedSeconds * 1000) : null;
    const formattedTimestamp = lastModifiedDate
      ? formatTimestamp(lastModifiedDate)
      : "";
    const viewsCount =
      typeof data.views === "number"
        ? data.views
        : parseFloat($row.data("views")) || 0;
    const isHomepageRow = homepageSlug !== "" && data.slug === homepageSlug;
    const robotsDirective = normalizeRobotsDirective(data.robots);
    data.robots = robotsDirective;
    const scheduleState = evaluateScheduleState({
      published: publishedFlag,
      publish_at: data.publish_at,
      unpublish_at: data.unpublish_at,
    });
    data.publish_at = scheduleState.publishAt;
    data.unpublish_at = scheduleState.unpublishAt;
    const sharedAttributes = {
      "data-title": data.title,
      "data-slug": data.slug,
      "data-content": data.content,
      "data-template": data.template,
      "data-meta_title": data.meta_title,
      "data-meta_description": data.meta_description,
      "data-canonical_url": data.canonical_url,
      "data-og_title": data.og_title,
      "data-og_description": data.og_description,
      "data-og_image": data.og_image,
      "data-access": data.access,
      "data-robots": robotsDirective,
      "data-published": publishedFlag,
      "data-live": scheduleState.isLive ? 1 : 0,
      "data-schedule_state": scheduleState.state,
      "data-publish_at": scheduleState.publishAt,
      "data-unpublish_at": scheduleState.unpublishAt,
      "data-views": viewsCount,
      "data-last_modified": lastModifiedSeconds,
      "data-homepage": isHomepageRow ? 1 : 0,
    };

    $row.attr(sharedAttributes);

    $row.data("title", data.title);
    $row.data("slug", data.slug);
    $row.data("content", data.content);
    $row.data("template", data.template);
    $row.data("meta_title", data.meta_title);
    $row.data("meta_description", data.meta_description);
    $row.data("canonical_url", data.canonical_url);
    $row.data("og_title", data.og_title);
    $row.data("og_description", data.og_description);
    $row.data("og_image", data.og_image);
    $row.data("access", data.access);
    $row.data("robots", robotsDirective);
    $row.data("published", publishedFlag);
    $row.data("live", scheduleState.isLive ? 1 : 0);
    $row.data("schedule_state", scheduleState.state);
    $row.data("publish_at", scheduleState.publishAt);
    $row.data("unpublish_at", scheduleState.unpublishAt);
    $row.data("views", viewsCount);
    $row.data("last_modified", lastModifiedSeconds);
    $row.data("homepage", isHomepageRow ? 1 : 0);

    $row.find(".pages-list-title-text").text(data.title);
    $row.find(".pages-list-slug").text(`/${data.slug}`);

    const $rowStatusBadge = $row.find(".status-badge");
    $rowStatusBadge.removeClass(
      "status-published status-draft status-scheduled status-expired",
    );
    $rowStatusBadge.addClass(scheduleState.statusClass);
    $rowStatusBadge.text(scheduleState.statusText);
    $rowStatusBadge.attr("aria-label", `Status: ${scheduleState.statusText}`);

    const $statusNote = $row.find("[data-status-note]");
    if ($statusNote.length) {
      if (scheduleState.statusNote) {
        $statusNote.text(scheduleState.statusNote).removeAttr("hidden");
      } else {
        $statusNote.text("").attr("hidden", "hidden");
      }
    }

    const $rowViewLink = $row.find('[data-action="view"]').first();
    if ($rowViewLink.length) {
      $rowViewLink.attr("href", `../?page=${encodeURIComponent(data.slug)}`);
    }

    const $rowToggleBtn = $row.find(".togglePublishBtn");
    if ($rowToggleBtn.length) {
      $rowToggleBtn.text(publishedFlag ? "Unpublish" : "Publish");
    }

    const $rowUpdated = $row.find(".pages-list-updated");
    if ($rowUpdated.length) {
      if (formattedTimestamp) {
        $rowUpdated.text(`Updated ${formattedTimestamp}`);
      } else {
        $rowUpdated.text("No edits yet");
      }
    }

    const $rowAccess = $row.find(".pages-list-access");
    if ($rowAccess.length) {
      $rowAccess.text(isRestricted ? "Private" : "Public");
    }

    const $rowViews = $row.find(".pages-list-views");
    if ($rowViews.length) {
      $rowViews.text(Number(viewsCount).toLocaleString());
    }

    const $titleText = $row.find(".pages-list-title-text");
    if ($titleText.length) {
      $titleText.find(".pages-list-title-icon--home").remove();
      if (isHomepageRow) {
        $titleText.prepend(homepageIconHtml);
      }
    }

    const $badges = $row.find(".pages-list-badges");
    if ($badges.length) {
      if (isRestricted) {
        if (!$badges.find(".pages-card__badge--restricted").length) {
          $badges.append(
            '<span class="pages-card__badge pages-card__badge--restricted"><i class="fa-solid fa-lock" aria-hidden="true"></i>Private</span>',
          );
        }
      } else {
        $badges.find(".pages-card__badge--restricted").remove();
      }
    }
  }

  function findPageItem($el) {
    return $el.closest("[data-page-item]");
  }

  function buildPageRequestPayload(data, overrides) {
    const robotsDirective = normalizeRobotsDirective(data.robots);
    const basePayload = {
      title: data.title,
      slug: data.slug,
      content: data.content,
      published: data.published,
      publish_at: data.publish_at || "",
      unpublish_at: data.unpublish_at || "",
      template: data.template,
      meta_title: data.meta_title,
      meta_description: data.meta_description,
      canonical_url: data.canonical_url,
      og_title: data.og_title,
      og_description: data.og_description,
      og_image: data.og_image,
      access: data.access,
      robots: robotsDirective,
    };

    let payload;
    if (overrides && typeof overrides === "object") {
      payload = Object.assign({}, basePayload, overrides);
    } else {
      payload = basePayload;
    }

    payload.robots = normalizeRobotsDirective(payload.robots);
    return payload;
  }

  function getPageItemsById(id) {
    return $(`[data-page-item][data-id="${id}"]`);
  }

  $("#pageForm").on("input change", "input, textarea, select", function () {
    markPageDirty();
  });

  $("#pageForm").on("submit", function (e) {
    e.preventDefault();

    const $form = $(this);
    const isEditing = $("#pageId").val() !== "";
    const rawSlugValue = $("#slug").val();
    const slugSource = rawSlugValue || $("#title").val() || "";
    let normalizedSlug = slugify(slugSource);
    if (!normalizedSlug) {
      normalizedSlug = "page";
    }
    $("#slug").val(normalizedSlug);

    const pageData = {
      id: $("#pageId").val(),
      title: $("#title").val(),
      slug: normalizedSlug,
      content: $("#content").val(),
      template: $("#template").val(),
      meta_title: $("#meta_title").val(),
      meta_description: $("#meta_description").val(),
      canonical_url: $("#canonical_url").val(),
      og_title: $("#og_title").val(),
      og_description: $("#og_description").val(),
      og_image: $("#og_image").val(),
      access: $("#access").val(),
      robots: normalizeRobotsDirective($("#robots").val() || ROBOTS_DEFAULT),
      published: $("#published").is(":checked") ? 1 : 0,
      publish_at: $("#publish_at").val(),
      unpublish_at: $("#unpublish_at").val(),
    };
    pageData.robots = normalizeRobotsDirective(pageData.robots);

    const nowTimestamp = Math.floor(Date.now() / 1000);
    if (isEditing) {
      const $existingRow = getPageRows().filter(`[data-id="${pageData.id}"]`);
      const existingViews = $existingRow.length
        ? parseNumber($existingRow.data("views"))
        : 0;
      pageData.views = existingViews;
      pageData.last_modified = nowTimestamp;
    } else {
      pageData.views = 0;
      pageData.last_modified = nowTimestamp;
    }

    const $submitButton = $form.find('button[type="submit"]');
    const originalButtonHtml = $submitButton.html();
    $submitButton.prop("disabled", true).text("Saving...");
    pageIsSaving = true;
    setPageSaveState("saving");

    $.post("modules/pages/save_page.php", $form.serialize())
      .done(function () {
        const shouldSetHomepage = $("#homepage").is(":checked");
        slugEdited = false;
        closePageModal();
        pageIsDirty = false;
        setPageSaveState("saved");

        if (isEditing) {
          updatePageRow(pageData);
          applyPageFilters();
          toastSuccess("Page updated successfully.");
          const homepageRequest = maybeUpdateHomepage(
            pageData.slug,
            shouldSetHomepage,
          );
          if (homepageRequest) {
            homepageRequest
              .done(function () {
                refreshHomepageIndicators(pageData.slug);
                toastSuccess("Homepage updated successfully.");
              })
              .fail(function (xhr) {
                const message = extractErrorMessage(
                  xhr,
                  "Unable to update the homepage setting.",
                );
                toastError(message);
              });
          }
        } else {
          $("#pageForm")[0].reset();
          if (ogImagePicker) {
            ogImagePicker.setValue("");
          }
          $("#published").prop("checked", false);
          $("#homepage").prop("checked", false);
          $("#publish_at").val("");
          $("#unpublish_at").val("");
          const homepageRequest = maybeUpdateHomepage(
            pageData.slug,
            shouldSetHomepage,
          );
          if (homepageRequest) {
            homepageRequest
              .done(function () {
                rememberToast("success", "Homepage updated successfully.");
              })
              .fail(function (xhr) {
                const message = extractErrorMessage(
                  xhr,
                  "Unable to update the homepage setting.",
                );
                rememberToast("error", message);
              })
              .always(function () {
                rememberToast("success", "Page created successfully.");
                location.reload();
              });
            return;
          }
          rememberToast("success", "Page created successfully.");
          location.reload();
        }
      })
      .fail(function (xhr) {
        const message = extractErrorMessage(
          xhr,
          "An unexpected error occurred while saving the page.",
        );
        toastError(message);
        pageIsDirty = true;
        setPageSaveState("unsaved");
      })
      .always(function () {
        $submitButton.prop("disabled", false).html(originalButtonHtml);
        pageIsSaving = false;
      });
  });
  $(".deleteBtn").on("click", function () {
    const row = findPageItem($(this));
    if (!row.length) {
      return;
    }
    const pageId = row.data("id");
    confirmModal("Delete this page?").then((ok) => {
      if (ok) {
        $.post("modules/pages/delete_page.php", { id: pageId })
          .done(function () {
            getPageItemsById(pageId).remove();
            applyPageFilters();
            toastSuccess("Page deleted successfully.");
          })
          .fail(function (xhr) {
            const message = extractErrorMessage(
              xhr,
              "Unable to delete the page.",
            );
            toastError(message);
          });
      }
    });
  });
  $(".editBtn").on("click", function () {
    const row = findPageItem($(this));
    if (!row.length) {
      return;
    }
    $("#formTitle").text("Page Settings");
    $("#pageId").val(row.data("id"));
    $("#title").val(row.data("title"));
    // Use attribute to avoid any jQuery data caching that may return
    // the numeric id instead of the actual slug
    $("#slug").val(row.attr("data-slug"));
    $("#content").val(row.data("content"));
    $("#published").prop("checked", row.data("published") == 1);
    $("#publish_at").val(row.attr("data-publish_at") || "");
    $("#unpublish_at").val(row.attr("data-unpublish_at") || "");
    const tmpl = row.data("template") ? row.data("template") : "page.php";
    if ($templateSelect.length) {
      if (
        tmpl &&
        !$templateSelect.find('option[value="' + tmpl + '"]').length
      ) {
        $("<option>", {
          value: tmpl,
          text: tmpl,
          class: TEMP_TEMPLATE_CLASS,
        }).appendTo($templateSelect);
      }
      $templateSelect.val(tmpl);
    }
    $("#meta_title").val(row.data("meta_title"));
    $("#meta_description").val(row.data("meta_description"));
    $("#canonical_url").val(row.data("canonical_url"));
    $("#og_title").val(row.data("og_title"));
    $("#og_description").val(row.data("og_description"));
    $("#og_image").val(row.data("og_image"));
    if (ogImagePicker) {
      ogImagePicker.setValue(row.data("og_image"));
    }
    $("#access").val(row.data("access"));
    $("#robots").val(normalizeRobotsDirective(row.data("robots")));
    $("#homepage").prop("checked", row.data("homepage") == 1);
    $("#cancelEdit").show();
    updatePageModalStatusBadge(getScheduleStateFromForm());
    openPageModal();
    slugEdited = true;
  });
  $("#cancelEdit").on("click", function () {
    $("#formTitle").text("Add New Page");
    $("#pageId").val("");
    $("#pageForm")[0].reset();
    if (ogImagePicker) {
      ogImagePicker.setValue("");
    }
    $("#published").prop("checked", false);
    $("#canonical_url").val("");
    $("#robots").val(ROBOTS_DEFAULT);
    $("#homepage").prop("checked", false);
    $("#publish_at").val("");
    $("#unpublish_at").val("");
    updatePageModalStatusBadge(getScheduleStateFromForm());
    closePageModal();
    slugEdited = false;
  });
  $("#newPageBtn").on("click", function () {
    $("#formTitle").text("Add New Page");
    $("#pageId").val("");
    $("#pageForm")[0].reset();
    if (ogImagePicker) {
      ogImagePicker.setValue("");
    }
    $("#published").prop("checked", false);
    $("#content").val("");
    $("#canonical_url").val("");
    $("#cancelEdit").hide();
    $("#homepage").prop("checked", false);
    $("#robots").val(ROBOTS_DEFAULT);
    $("#publish_at").val("");
    $("#unpublish_at").val("");
    removeTemporaryTemplateOptions();
    updatePageModalStatusBadge(getScheduleStateFromForm());
    openPageModal();
    slugEdited = false;
  });

  if ($emptyCta.length) {
    $emptyCta.on("click", function () {
      $("#newPageBtn").trigger("click");
    });
  }

  $("#closePageModal").on("click", function () {
    closePageModal();
    slugEdited = false;
  });

  $("#published, #publish_at, #unpublish_at").on("change input", function () {
    updatePageModalStatusBadge(getScheduleStateFromForm());
  });

  if ($listView.length) {
    applyPageFilters();

    $searchInput.on("input", applyPageFilters);

    $filterButtons.on("click", function () {
      const $btn = $(this);
      const newFilter = $btn.data("pagesFilter");
      if (!newFilter) {
        return;
      }

      activeFilter = newFilter;
      $filterButtons.removeClass("active").attr("aria-pressed", "false");
      $btn.addClass("active").attr("aria-pressed", "true");
      applyPageFilters();
    });

    if ($sortButtons.length) {
      $sortButtons.on("click", function () {
        const $btn = $(this);
        const key = $btn.data("pagesSort");
        if (!key) {
          return;
        }
        const defaultDirection = ($btn.data("defaultDirection") || "asc")
          .toString()
          .toLowerCase();
        let direction = defaultDirection === "desc" ? "desc" : "asc";
        if (sortState.key === key) {
          direction = sortState.direction === "asc" ? "desc" : "asc";
        }
        setSort(key, direction);
      });
    }

    setSort("updated", "desc");
  }

  $(".copyBtn").on("click", function () {
    const row = findPageItem($(this));
    if (!row.length) {
      return;
    }
    const data = row.data();
    const payload = buildPageRequestPayload(data, {
      title: `${data.title} Copy`,
      slug: `${data.slug}-copy`,
    });
    $.post("modules/pages/save_page.php", payload)
      .done(function () {
        rememberToast("success", "Page duplicated successfully.");
        location.reload();
      })
      .fail(function (xhr) {
        const message = extractErrorMessage(
          xhr,
          "Unable to duplicate the page.",
        );
        toastError(message);
      });
  });

  $(".togglePublishBtn").on("click", function () {
    const row = findPageItem($(this));
    if (!row.length) {
      return;
    }
    const data = row.data();
    const newStatus = data.published ? 0 : 1;
    const payload = buildPageRequestPayload(data, {
      id: data.id,
      published: newStatus,
    });
    $.post("modules/pages/save_page.php", payload)
      .done(function () {
        const message = newStatus
          ? "Page published successfully."
          : "Page unpublished successfully.";
        rememberToast("success", message);
        location.reload();
      })
      .fail(function (xhr) {
        const message = extractErrorMessage(
          xhr,
          "Unable to update the publish status.",
        );
        toastError(message);
      });
  });

  refreshHomepageIndicators(homepageSlug);

  // ─────────────────────────────────────────────────────────────────────
  // Feature 5: Autosave / Draft Recovery
  // ─────────────────────────────────────────────────────────────────────
  let pageAutosaveTimer = null;
  let currentDraftData = null;

  function collectPageFormData() {
    return {
      id:               $("#pageId").val(),
      title:            $("#title").val(),
      slug:             $("#slug").val(),
      content:          $("#content").val(),
      published:        $("#published").is(":checked") ? 1 : 0,
      publish_at:       $("#publish_at").val(),
      unpublish_at:     $("#unpublish_at").val(),
      template:         $("#template").val(),
      meta_title:       $("#meta_title").val(),
      meta_description: $("#meta_description").val(),
      canonical_url:    $("#canonical_url").val(),
      og_title:         $("#og_title").val(),
      og_description:   $("#og_description").val(),
      og_image:         $("#og_image").val(),
      access:           $("#access").val(),
      robots:           $("#robots").val(),
    };
  }

  function saveDraftNow() {
    const id = $("#pageId").val();
    if (!id || !pageIsDirty) return;
    setPageSaveState("saving");
    $.post("modules/pages/save_draft.php", collectPageFormData())
      .done(function (res) {
        if (res && res.status === "success") {
          setPageSaveState("saved");
        }
      })
      .fail(function () {
        setPageSaveState("unsaved");
      });
  }

  function startPageAutosave() {
    stopPageAutosave();
    pageAutosaveTimer = setInterval(function () {
      if (pageIsDirty && !pageIsSaving) {
        saveDraftNow();
      }
    }, 30000);
  }

  function stopPageAutosave() {
    if (pageAutosaveTimer) {
      clearInterval(pageAutosaveTimer);
      pageAutosaveTimer = null;
    }
  }

  function showDraftRecoveryBanner(draft) {
    currentDraftData = draft;
    const savedDate = draft.saved_at
      ? new Date(draft.saved_at * 1000).toLocaleString()
      : "unknown time";
    $("#draftRecoveryText").text(
      "Autosaved draft from " + savedDate + " — restore it?"
    );
    $("#draftRecoveryBanner").removeAttr("hidden");
  }

  function hideDraftRecoveryBanner() {
    $("#draftRecoveryBanner").attr("hidden", "hidden");
    currentDraftData = null;
  }

  function applyDraftToForm(draft) {
    if (!draft) return;
    if (draft.title       !== undefined) $("#title").val(draft.title);
    if (draft.slug        !== undefined) $("#slug").val(draft.slug);
    if (draft.content     !== undefined) $("#content").val(draft.content);
    if (draft.published   !== undefined) $("#published").prop("checked", !!draft.published);
    if (draft.publish_at  !== undefined) $("#publish_at").val(draft.publish_at);
    if (draft.unpublish_at !== undefined) $("#unpublish_at").val(draft.unpublish_at);
    if (draft.template    !== undefined) $("#template").val(draft.template);
    if (draft.meta_title  !== undefined) $("#meta_title").val(draft.meta_title);
    if (draft.meta_description !== undefined) $("#meta_description").val(draft.meta_description);
    if (draft.canonical_url !== undefined) $("#canonical_url").val(draft.canonical_url);
    if (draft.og_title    !== undefined) $("#og_title").val(draft.og_title);
    if (draft.og_description !== undefined) $("#og_description").val(draft.og_description);
    if (draft.og_image    !== undefined) {
      $("#og_image").val(draft.og_image);
      if (ogImagePicker) ogImagePicker.setValue(draft.og_image);
    }
    if (draft.access  !== undefined) $("#access").val(draft.access);
    if (draft.robots  !== undefined) $("#robots").val(draft.robots);
    updatePageModalStatusBadge(getScheduleStateFromForm());
    markPageDirty();
  }

  function checkPageDraftOnOpen(pageId, pageLastModified) {
    if (!pageId) return;
    $.getJSON("modules/pages/get_draft.php", { id: pageId })
      .done(function (res) {
        if (
          res.status === "ok" &&
          res.draft &&
          res.draft.saved_at > (pageLastModified || 0)
        ) {
          showDraftRecoveryBanner(res.draft);
        }
      });
  }

  function deleteDraftFile(pageId) {
    // No dedicated endpoint needed — draft is overwritten on next save
    // or will be ignored once page is saved officially. We can signal
    // discard by saving current server data as draft (blank saved_at trick)
    // Instead we simply mark it stale by saving an empty placeholder.
    $.post("modules/pages/save_draft.php", { id: pageId, title: "__discarded__" });
  }

  $("#restoreDraftBtn").on("click", function () {
    if (!currentDraftData) return;
    applyDraftToForm(currentDraftData);
    hideDraftRecoveryBanner();
    toastSuccess("Draft restored. Save to make it permanent.");
  });

  $("#discardDraftBtn").on("click", function () {
    const id = $("#pageId").val();
    if (id) deleteDraftFile(id);
    hideDraftRecoveryBanner();
  });

  // Hook into editBtn to check for draft on open
  $(document).on("click", ".editBtn", function () {
    // Runs after the editBtn handler above; extract last_modified from row
    const row = findPageItem($(this));
    if (!row.length) return;
    const pageId      = row.data("id");
    const lastModified = row.data("last_modified") || 0;
    hideDraftRecoveryBanner();
    // Small delay so form fields are populated first
    setTimeout(function () {
      startPageAutosave();
      checkPageDraftOnOpen(pageId, lastModified);
    }, 100);
  });

  $(document).on("click", "#newPageBtn, #pagesEmptyCta", function () {
    hideDraftRecoveryBanner();
    stopPageAutosave();
  });

  $("#closePageModal").on("click", function () {
    stopPageAutosave();
    hideDraftRecoveryBanner();
  });

  // ─────────────────────────────────────────────────────────────────────
  // Feature 6: Revision History Modal
  // ─────────────────────────────────────────────────────────────────────
  let historyPageId = null;

  function formatHistoryTimestamp(ts) {
    if (!ts) return "Unknown time";
    const d = new Date(ts * 1000);
    return d.toLocaleString(undefined, {
      year: "numeric", month: "short", day: "numeric",
      hour: "numeric", minute: "2-digit",
    });
  }

  function renderPageHistory(entries) {
    const $list = $("#pageHistoryList");
    if (!entries || !entries.length) {
      $list.html('<p class="page-history-empty">No revision history for this page yet.</p>');
      return;
    }
    const html = entries.map(function (entry, idx) {
      const time    = formatHistoryTimestamp(entry.time);
      const user    = escapeHtml(entry.user || "Unknown");
      const action  = escapeHtml(entry.action || "Saved");
      const details = Array.isArray(entry.details)
        ? entry.details.map(function (d) {
            return "<li>" + escapeHtml(d) + "</li>";
          }).join("")
        : "";
      const hasSnapshot = entry.snapshot && entry.snapshot.title !== undefined;
      const restoreBtn = hasSnapshot
        ? '<button type="button" class="c-button c-button--secondary c-button--sm restore-revision-btn" data-rev-index="' + idx + '">Restore</button>'
        : "";
      return (
        '<div class="page-history-entry">' +
          '<div class="page-history-entry__meta">' +
            '<span class="page-history-entry__time"><i class="fa-solid fa-clock" aria-hidden="true"></i> ' + time + "</span>" +
            '<span class="page-history-entry__user"><i class="fa-solid fa-user" aria-hidden="true"></i> ' + user + "</span>" +
          "</div>" +
          '<div class="page-history-entry__action">' + action + "</div>" +
          (details ? '<ul class="page-history-entry__details">' + details + "</ul>" : "") +
          (restoreBtn ? '<div class="page-history-entry__restore">' + restoreBtn + "</div>" : "") +
        "</div>"
      );
    }).join('<hr class="page-history-divider">');
    $list.html(html);

    $list.find(".restore-revision-btn").on("click", function () {
      const revIdx = parseInt($(this).data("rev-index"), 10);
      confirmModal("Restore this revision? Current unsaved changes will be overwritten after save.").then(function (ok) {
        if (!ok) return;
        $.ajax({
          url:  "modules/pages/restore_revision.php",
          type: "POST",
          contentType: "application/json",
          data: JSON.stringify({ page_id: historyPageId, rev_index: revIdx }),
        })
          .done(function () {
            closeModal("pageHistoryModal");
            rememberToast("success", "Revision restored successfully.");
            location.reload();
          })
          .fail(function (xhr) {
            toastError(extractErrorMessage(xhr, "Failed to restore revision."));
          });
      });
    });
  }

  function openHistoryModal(pageId) {
    historyPageId = pageId;
    $("#pageHistoryList").html('<p class="page-history-loading">Loading history…</p>');
    openModal("pageHistoryModal");
    $.getJSON("modules/pages/get_history.php", { id: pageId })
      .done(function (res) {
        if (res.status === "ok") {
          renderPageHistory(res.history);
        } else {
          $("#pageHistoryList").html('<p class="page-history-empty">Unable to load history.</p>');
        }
      })
      .fail(function () {
        $("#pageHistoryList").html('<p class="page-history-empty text-danger">Error loading history.</p>');
      });
  }

  $(document).on("click", ".historyBtn", function () {
    const row = findPageItem($(this));
    if (!row.length) return;
    openHistoryModal(row.data("id"));
  });

  $("#closeHistoryModal, #closeHistoryModalFooter").on("click", function () {
    closeModal("pageHistoryModal");
  });

  $(window).on("click", function (e) {
    if (e.target.id === "pageHistoryModal") closeModal("pageHistoryModal");
  });

  // ─────────────────────────────────────────────────────────────────────
  // Feature 7: Scheduling UI — live summary card
  // ─────────────────────────────────────────────────────────────────────
  function updateScheduleSummaryCard() {
    const scheduleState = getScheduleStateFromForm();
    const $summary = $("#scheduleSummary");
    const $summaryText = $("#scheduleSummaryText");
    const publishAt   = $("#publish_at").val();
    const unpublishAt = $("#unpublish_at").val();
    const isPublished = $("#published").is(":checked");

    if (!isPublished && !publishAt && !unpublishAt) {
      $summary.attr("hidden", "hidden");
      return;
    }

    let msg = "";
    if (scheduleState.state === "scheduled") {
      msg = "Scheduled to publish " + scheduleState.statusNote.replace("Publishes ", "on ");
    } else if (scheduleState.state === "published") {
      msg = unpublishAt ? "Live · " + scheduleState.statusNote : "Live and published";
    } else if (scheduleState.state === "expired") {
      msg = "Archived · " + scheduleState.statusNote;
    } else if (publishAt) {
      msg = "Publish at: " + formatTimestamp(parseLocalDateTime(publishAt));
    }

    if (msg) {
      $summaryText.text(msg);
      $summary.removeAttr("hidden");
    } else {
      $summary.attr("hidden", "hidden");
    }
  }

  $("#published, #publish_at, #unpublish_at").on("change input", function () {
    updateScheduleSummaryCard();
  });

  // ─────────────────────────────────────────────────────────────────────
  // Feature 8: Bulk Actions — Pages
  // ─────────────────────────────────────────────────────────────────────
  function getSelectedPageIds() {
    return $(".page-row-checkbox:checked").map(function () {
      return parseInt($(this).data("id"), 10);
    }).get();
  }

  function updatePagesBulkToolbar() {
    const selected = getSelectedPageIds();
    const count = selected.length;
    const $toolbar = $("#pagesBulkToolbar");
    if (count > 0) {
      $toolbar.removeAttr("hidden");
      $("#pagesBulkCount").text(count + " selected");
    } else {
      $toolbar.attr("hidden", "hidden");
    }
    // Sync select-all state
    const total = $(".page-row-checkbox").length;
    $("#selectAllPages").prop("indeterminate", count > 0 && count < total);
    $("#selectAllPages").prop("checked", total > 0 && count === total);
  }

  $(document).on("change", ".page-row-checkbox", function () {
    updatePagesBulkToolbar();
  });

  $("#selectAllPages").on("change", function () {
    const checked = $(this).is(":checked");
    $(".page-row-checkbox").prop("checked", checked);
    updatePagesBulkToolbar();
  });

  $("#bulkClearBtn").on("click", function () {
    $(".page-row-checkbox").prop("checked", false);
    $("#selectAllPages").prop("checked", false).prop("indeterminate", false);
    updatePagesBulkToolbar();
  });

  function executeBulkPageAction(action) {
    const ids = getSelectedPageIds();
    if (!ids.length) return;
    const label = action === "delete"
      ? "Delete " + ids.length + " page(s)? This cannot be undone."
      : (action === "publish" ? "Publish" : "Unpublish") + " " + ids.length + " page(s)?";
    confirmModal(label).then(function (ok) {
      if (!ok) return;
      $.post("modules/pages/bulk_action.php", {
        action: action,
        ids: JSON.stringify(ids),
      })
        .done(function (res) {
          if (res && res.status === "success") {
            rememberToast("success", res.affected + " page(s) " + action + "d.");
            location.reload();
          }
        })
        .fail(function (xhr) {
          toastError(extractErrorMessage(xhr, "Bulk action failed."));
        });
    });
  }

  $("#bulkPublishBtn").on("click",   function () { executeBulkPageAction("publish"); });
  $("#bulkUnpublishBtn").on("click", function () { executeBulkPageAction("unpublish"); });
  $("#bulkDeleteBtn").on("click",    function () { executeBulkPageAction("delete"); });
});
function normalizeRobotsDirective(value) {
  const normalized = (value || "")
    .toString()
    .toLowerCase()
    .replace(/[\s;|]+/g, ",");
  const parts = normalized.split(",").filter(Boolean);
  let indexDirective = "index";
  let followDirective = "follow";
  parts.forEach(function (part) {
    if (part === "index" || part === "noindex") {
      indexDirective = part;
    }
    if (part === "follow" || part === "nofollow") {
      followDirective = part;
    }
  });
  const directive = indexDirective + "," + followDirective;
  const allowed = {
    "index,follow": true,
    "index,nofollow": true,
    "noindex,follow": true,
    "noindex,nofollow": true,
  };
  return allowed[directive] ? directive : ROBOTS_DEFAULT;
}
