(function ($) {
  "use strict";

  /**
   * Retrieve the preloaded module payload if present. The PHP view injects
   * window.__SEO_MODULE_DATA__ so the dashboard can be hydrated without
   * additional HTTP requests.
   */
  function getModuleData() {
    if (
      window.__SEO_MODULE_DATA__ &&
      typeof window.__SEO_MODULE_DATA__ === "object"
    ) {
      return window.__SEO_MODULE_DATA__;
    }
    return { pages: [], stats: {} };
  }

  /**
   * Convert the Date instance from stats.lastScan into a human friendly
   * string. We attempt to use toLocaleString and gracefully degrade when the
   * runtime lacks full internationalisation support.
   */
  function formatLastScanTimestamp(date) {
    if (!(date instanceof Date) || isNaN(date.getTime())) {
      return "";
    }
    var options = {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    };
    try {
      return date.toLocaleString(undefined, options);
    } catch (error) {
      // Fallback for environments without locale support
      var month = date.toString().split(" ")[1] || "";
      var day = date.getDate();
      var year = date.getFullYear();
      var hours = date.getHours();
      var minutes = date.getMinutes();
      var period = hours >= 12 ? "PM" : "AM";
      hours = hours % 12;
      if (hours === 0) {
        hours = 12;
      }
      var minuteStr = minutes < 10 ? "0" + minutes : String(minutes);
      return (
        month +
        " " +
        day +
        ", " +
        year +
        " " +
        hours +
        ":" +
        minuteStr +
        " " +
        period
      );
    }
  }

  /**
   * Ensure score-like values become integers between 0 and 100. The server
   * may provide strings so we defensively parse and clamp.
   */
  function normalizeScore(value, fallback) {
    var number = Number(value);
    if (Number.isFinite && Number.isFinite(number)) {
      return Math.max(0, Math.min(100, Math.round(number)));
    }
    number = parseFloat(value);
    if (!isNaN(number)) {
      return Math.max(0, Math.min(100, Math.round(number)));
    }
    return fallback;
  }

  /**
   * Locale-aware, case-insensitive comparison used as a secondary sort tie
   * breaker for page titles and URLs.
   */
  function compareStrings(a, b) {
    return String(a || "").localeCompare(String(b || ""), undefined, {
      sensitivity: "base",
    });
  }

  /**
   * Translate the change between current and previous scores into metadata
   * that powers the UI badge.
   */
  function getScoreDeltaMeta(current, previous) {
    var currentScore = normalizeScore(current, 0);
    var previousScore =
      typeof previous === "undefined" || previous === null
        ? currentScore
        : normalizeScore(previous, currentScore);
    var delta = currentScore - previousScore;
    var absolute = Math.abs(delta);
    var className = "score-delta--even";
    var srText = "No change since last scan.";
    if (delta > 0) {
      className = "score-delta--up";
      srText =
        "Improved by " +
        absolute +
        " " +
        (absolute === 1 ? "point" : "points") +
        " since last scan.";
    } else if (delta < 0) {
      className = "score-delta--down";
      srText =
        "Regressed by " +
        absolute +
        " " +
        (absolute === 1 ? "point" : "points") +
        " since last scan.";
    }
    var display = delta === 0 ? "0" : (delta > 0 ? "+" : "−") + absolute;
    return {
      className: className,
      display: display,
      srText: srText,
    };
  }

  /**
   * Build the HTML used beside scores to communicate improvements or
   * regressions.
   */
  function renderScoreDelta(current, previous) {
    var meta = getScoreDeltaMeta(current, previous);
    return (
      '<span class="score-delta ' +
      meta.className +
      '"><span aria-hidden="true">' +
      meta.display +
      '</span><span class="sr-only">' +
      meta.srText +
      "</span></span>"
    );
  }

  /**
   * Normalise the optimisation level into a slug used for CSS modifiers.
   */
  function getLevelBadge(level) {
    var normalized = String(level || "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "-");
    return "level-" + (normalized || "");
  }

  /**
   * Choose the UI class for the score ring based on thresholds.
   */
  function getScoreQualityClass(score) {
    if (score >= 90) {
      return "seo-score--excellent";
    }
    if (score >= 75) {
      return "seo-score--good";
    }
    if (score >= 60) {
      return "seo-score--fair";
    }
    return "seo-score--poor";
  }

  /**
   * Reduce the violation counts object into the summary sentence displayed
   * throughout the UI.
   */
  function formatSeoViolations(violations) {
    if (!violations) {
      return "No outstanding SEO issues";
    }
    var total = Number(violations.total || 0);
    if (!total) {
      return "No outstanding SEO issues";
    }
    var parts = [];
    if (violations.critical) {
      parts.push(violations.critical + " critical");
    }
    if (violations.serious) {
      parts.push(violations.serious + " serious");
    }
    if (violations.moderate) {
      parts.push(violations.moderate + " moderate");
    }
    if (violations.minor) {
      parts.push(violations.minor + " minor");
    }
    return (
      total + " total" + (parts.length ? " (" + parts.join(", ") + ")" : "")
    );
  }

  /**
   * Build the HTML list summarising the collected page metrics for the
   * modal detail view.
   */
  function renderMetricList(metrics) {
    if (!metrics) {
      return "";
    }
    function boolLabel(value, positive, negative) {
      return value ? positive : negative;
    }
    function formatRobotsDirective(value) {
      var normalized = normalizeRobotsDirective(value);
      var labels = {
        "index,follow": "Index & follow",
        "index,nofollow": "Index, nofollow",
        "noindex,follow": "Noindex, follow",
        "noindex,nofollow": "Noindex & nofollow",
      };
      return labels[normalized] || normalized;
    }
    function normalizeRobotsDirective(value) {
      var normalized = String(value || "")
        .toLowerCase()
        .replace(/[\s;|]+/g, ",");
      var parts = normalized.split(",").filter(function (part) {
        return part.length;
      });
      var indexDirective = "index";
      var followDirective = "follow";
      parts.forEach(function (part) {
        if (part === "index" || part === "noindex") {
          indexDirective = part;
        }
        if (part === "follow" || part === "nofollow") {
          followDirective = part;
        }
      });
      var directive = indexDirective + "," + followDirective;
      var allowed = {
        "index,follow": true,
        "index,nofollow": true,
        "noindex,follow": true,
        "noindex,nofollow": true,
      };
      return allowed[directive] ? directive : "index,follow";
    }
    var links = metrics.links || {};
    var items = [];
    items.push(
      '<li><span class="label">Title length</span><span class="value">' +
        (metrics.titleLength || 0) +
        ' characters</span><span class="hint">Keep between 50–60 characters</span></li>',
    );
    items.push(
      '<li><span class="label">Meta description</span><span class="value">' +
        (metrics.metaDescriptionLength || 0) +
        ' characters</span><span class="hint">Aim for 120–160 characters</span></li>',
    );
    items.push(
      '<li><span class="label">Canonical URL</span><span class="value">' +
        boolLabel(metrics.hasCanonical, "Present", "Missing") +
        '</span><span class="hint">Prevent duplicate content</span></li>',
    );
    items.push(
      '<li><span class="label">Word count</span><span class="value">' +
        (metrics.wordCount || 0) +
        '</span><span class="hint">Support in-depth coverage</span></li>',
    );
    items.push(
      '<li><span class="label">H1 headings</span><span class="value">' +
        (metrics.h1Count || 0) +
        '</span><span class="hint">Use a single descriptive H1</span></li>',
    );
    items.push(
      '<li><span class="label">Structured data</span><span class="value">' +
        boolLabel(metrics.hasStructuredData, "Detected", "Not detected") +
        '</span><span class="hint">Enhance rich results</span></li>',
    );
    items.push(
      '<li><span class="label">Images</span><span class="value">' +
        (metrics.images || 0) +
        '</span><span class="hint">' +
        ((metrics.missingAlt || 0) > 0
          ? metrics.missingAlt + " missing alt text"
          : "All images described") +
        "</span></li>",
    );
    items.push(
      '<li><span class="label">Open Graph</span><span class="value">' +
        boolLabel(metrics.hasOpenGraph, "Configured", "Missing") +
        '</span><span class="hint">Optimise social sharing</span></li>',
    );
    items.push(
      '<li><span class="label">Internal links</span><span class="value">' +
        (links.internal || 0) +
        '</span><span class="hint">Strengthen crawl paths</span></li>',
    );
    items.push(
      '<li><span class="label">External links</span><span class="value">' +
        (links.external || 0) +
        '</span><span class="hint">Provide authoritative references</span></li>',
    );
    items.push(
      '<li><span class="label">Robots directives</span><span class="value">' +
        formatRobotsDirective(metrics.robotsDirective) +
        '</span><span class="hint">Tune crawl and indexing behaviour</span></li>',
    );
    return items.join("");
  }

  /**
   * Ensure impact strings align with the known severity palette before
   * applying CSS classes.
   */
  function normalizeImpact(impact) {
    var value = String(impact || "").toLowerCase();
    if (["critical", "serious", "moderate", "minor"].indexOf(value) !== -1) {
      return value;
    }
    return "review";
  }

  /**
   * Calculate the value used for ordering in the dashboard table/grid for
   * the requested sort key.
   */
  function getSortValue(page, key) {
    if (!page) {
      return 0;
    }

    switch (key) {
      case "title":
        return (page.title || "").toLowerCase();
      case "issues":
        if (page.violations && typeof page.violations.total === "number") {
          return page.violations.total;
        }
        return 0;
      case "score":
      default:
        return normalizeScore(page.seoScore, 0);
    }
  }

  /**
   * Produce a sorted copy of the page array without mutating the original
   * collection supplied by the server.
   */
  function sortPages(pages, sortKey, direction) {
    if (!Array.isArray(pages)) {
      return [];
    }

    var dir = direction === "asc" ? 1 : -1;
    var items = pages.slice();

    items.sort(function (a, b) {
      var aVal = getSortValue(a, sortKey);
      var bVal = getSortValue(b, sortKey);

      if (aVal === bVal) {
        return (
          compareStrings(a && a.title, b && b.title) ||
          compareStrings(a && a.url, b && b.url)
        );
      }

      if (aVal < bVal) {
        return -1 * dir;
      }
      if (aVal > bVal) {
        return 1 * dir;
      }
      return 0;
    });

    return items;
  }

  /**
   * Check if the page qualifies for the currently selected optimisation
   * state filter.
   */
  function matchesFilter(page, filter) {
    switch (filter) {
      case "critical":
        return page.optimizationLevel === "Critical";
      case "needs-work":
        return page.optimizationLevel === "Needs Improvement";
      case "optimized":
        return page.optimizationLevel === "Optimised";
      default:
        return true;
    }
  }

  /**
   * Determine whether the page should appear for the active search query by
   * concatenating relevant text snippets.
   */
  function matchesQuery(page, query) {
    if (!query) {
      return true;
    }
    var haystacks = [
      page.title || "",
      page.url || "",
      page.statusMessage || "",
      page.summaryLine || "",
    ];
    if (page.issues && page.issues.preview) {
      var previewText = page.issues.preview
        .map(function (item) {
          if (typeof item === "string") {
            return item;
          }
          if (item && typeof item === "object") {
            return String(item.description || "");
          }
          return "";
        })
        .join(" ");
      haystacks.push(previewText);
    }
    return haystacks.join(" ").toLowerCase().indexOf(query) !== -1;
  }

  /**
   * Update the DOM order of grid cards/table rows to match the sorted
   * results while preserving existing nodes.
   */
  function reorderElements($container, selector, order) {
    var orderMap = {};
    order.forEach(function (slug, index) {
      orderMap[String(slug || "")] = index;
    });
    var elements = $container.find(selector).get();
    elements.sort(function (a, b) {
      var aSlug = $(a).data("page-slug");
      var bSlug = $(b).data("page-slug");
      var aIndex = Object.prototype.hasOwnProperty.call(orderMap, aSlug)
        ? orderMap[aSlug]
        : Number.MAX_SAFE_INTEGER;
      var bIndex = Object.prototype.hasOwnProperty.call(orderMap, bSlug)
        ? orderMap[bSlug]
        : Number.MAX_SAFE_INTEGER;
      if (aIndex === bIndex) {
        return 0;
      }
      return aIndex < bIndex ? -1 : 1;
    });
    elements.forEach(function (element) {
      $container.append(element);
    });
  }

  /**
   * Show or hide the provided DOM elements by toggling both visual and
   * accessibility attributes.
   */
  function updateVisibility($elements, visibleSlugs) {
    $elements.each(function () {
      var $el = $(this);
      var slug = String($el.data("page-slug") || "");
      if (visibleSlugs.has(slug)) {
        $el.removeAttr("hidden").attr("aria-hidden", "false").show();
      } else {
        $el.attr("hidden", "hidden").attr("aria-hidden", "true").hide();
      }
    });
  }

  /**
   * Mirror the latest scan timestamp in both data attributes and visible
   * text so the dashboard reflects the current value.
   */
  function updateLastScanDisplay($root, lastScan) {
    if (!lastScan) {
      return;
    }
    $root.attr("data-last-scan", lastScan);
    $root.find(".seo-last-scan-value").text(lastScan);
  }

  /**
   * Update the KPI counters at the top of the dashboard (total pages,
   * average score, etc.).
   */
  function updateStats($root, stats) {
    if (!stats) {
      return;
    }
    $root.find("#seoStatTotalPages").text(stats.totalPages || 0);
    $root.find("#seoStatAvgScore").text((stats.avgScore || 0) + "%");
    $root.find("#seoStatCritical").text(stats.criticalIssues || 0);
    $root.find("#seoStatOptimized").text(stats.optimizedPages || 0);
    if (stats.filterCounts) {
      Object.keys(stats.filterCounts).forEach(function (key) {
        $root
          .find('.a11y-filter-count[data-count="' + key + '"]')
          .text(stats.filterCounts[key]);
      });
    }
    if (stats.lastScan) {
      updateLastScanDisplay($root, stats.lastScan);
    }
  }

  /**
   * Aggregate stats for the dashboard cards based on the provided page
   * results. Existing stats from the server are merged so we can maintain
   * values such as detailBaseUrl.
   */
  function calculateDashboardStats(pages, previousStats) {
    var totalPages = Array.isArray(pages) ? pages.length : 0;
    var totalScore = 0;
    var criticalIssues = 0;
    var optimizedPages = 0;
    var needsWork = 0;
    var filterCounts = {
      all: totalPages,
      critical: 0,
      "needs-work": 0,
      optimized: 0,
    };

    if (Array.isArray(pages)) {
      pages.forEach(function (page) {
        var score = normalizeScore(page && page.seoScore, 0);
        totalScore += score;

        var violations = page && page.violations ? page.violations : {};
        criticalIssues += Number(violations.critical || 0);

        var level = String(
          (page && page.optimizationLevel) || "",
        ).toLowerCase();
        if (level === "optimised" || level === "optimized") {
          optimizedPages++;
          filterCounts.optimized++;
        } else if (
          level === "needs improvement" ||
          level === "needs-improvement"
        ) {
          needsWork++;
          filterCounts["needs-work"]++;
        } else if (level === "critical") {
          filterCounts.critical++;
        }
      });
    }

    var nextStats = {};
    if (previousStats && typeof previousStats === "object") {
      Object.keys(previousStats).forEach(function (key) {
        nextStats[key] = previousStats[key];
      });
    }

    nextStats.totalPages = totalPages;
    nextStats.avgScore = totalPages ? Math.round(totalScore / totalPages) : 0;
    nextStats.criticalIssues = criticalIssues;
    nextStats.optimizedPages = optimizedPages;
    nextStats.needsWork = needsWork;
    nextStats.filterCounts = nextStats.filterCounts || {};
    Object.keys(filterCounts).forEach(function (key) {
      nextStats.filterCounts[key] = filterCounts[key];
    });
    nextStats.lastScan = formatLastScanTimestamp(new Date());

    return nextStats;
  }

  /**
   * Attach all interactive behaviour for the dashboard list view including
   * sorting, filtering, searching, modal handling, and the mock scan action.
   */
  function initDashboard($root, pages, pagesMap, stats, moduleData) {
    stats = stats || {};
    updateStats($root, stats);

    var currentFilter = "all";
    var currentSortKey = "score";
    var sortDirection = "desc";
    var currentView = "grid";
    var searchQuery = "";
    var detailBaseUrl = stats.detailBaseUrl || "";

    var $grid = $root.find(".seo-page-grid");
    var $gridCards = $grid.find(".seo-page-card");
    var $table = $root.find(".seo-page-table");
    var $tableBody = $table.find("tbody");
    var $tableRows = $tableBody.find("tr");
    var $modal = $("#seoPageDetail");
    var $modalClose = $("#seoDetailClose");
    var $modalTitle = $("#seoDetailTitle");
    var $modalUrl = $("#seoDetailUrl");
    var $modalDescription = $("#seoDetailDescription");
    var $modalScore = $("#seoDetailScore");
    var $modalLevel = $("#seoDetailLevel");
    var $modalSignals = $("#seoDetailSignals");
    var $modalMetrics = $("#seoDetailMetrics");
    var $modalIssues = $("#seoDetailIssues");
    var $fullAuditBtn = $modal.find('[data-seo-action="full-audit"]');
    var $sortSelect = $root.find("#seoSortSelect");
    var $sortDirectionBtn = $root.find("#seoSortDirection");
    var $sortDirectionLabel = $root.find("#seoSortDirectionLabel");
    var activeSlug = null;
    var lastFocusedElement = null;

    $grid.attr("aria-hidden", "false");
    $table.attr("aria-hidden", "true");
    if ($modal.length) {
      $modal.attr("aria-hidden", "true");
    }

    // Hide the modal and restore focus to whichever element triggered it.
    function closeModal() {
      if (!$modal.length) {
        return;
      }
      $modal
        .attr("hidden", "hidden")
        .attr("aria-hidden", "true")
        .removeClass("is-visible");
      activeSlug = null;
      if (
        lastFocusedElement &&
        typeof lastFocusedElement.focus === "function"
      ) {
        lastFocusedElement.focus();
      }
      lastFocusedElement = null;
    }

    // Populate the modal with a given page's data and show it on screen.
    function openModal(page) {
      if (!page) {
        return;
      }
      if (!$modal.length) {
        if (detailBaseUrl) {
          window.location.href =
            detailBaseUrl + encodeURIComponent(page.slug || "");
        }
        return;
      }

      activeSlug = page.slug || "";
      lastFocusedElement = document.activeElement;

      var score = normalizeScore(page.seoScore, 0);
      var deltaHtml = renderScoreDelta(score, page.previousScore);
      var scoreClass = getScoreQualityClass(score);
      var levelClass = getLevelBadge(page.optimizationLevel);

      $modalTitle.text(page.title || "SEO details");
      $modalUrl.text(page.url || "");
      $modalDescription.text(page.summaryLine || page.statusMessage || "");
      $modalScore
        .removeClass(
          "seo-score--excellent seo-score--good seo-score--fair seo-score--poor",
        )
        .addClass(scoreClass)
        .html(
          '<span class="score-indicator__number">' +
            score +
            "%</span>" +
            deltaHtml,
        );
      $modalLevel
        .attr("class", "a11y-detail-level " + levelClass)
        .text(page.optimizationLevel || "Unknown");
      $modalSignals.text(formatSeoViolations(page.violations));
      $modalMetrics.html(renderMetricList(page.metrics));

      $modalIssues.empty();
      if (
        page.issues &&
        Array.isArray(page.issues.details) &&
        page.issues.details.length
      ) {
        page.issues.details.forEach(function (issue) {
          var impact = normalizeImpact(issue.impact);
          var label = impact.charAt(0).toUpperCase() + impact.slice(1);
          var $item = $("<li>");
          $("<span>", {
            class: "issue",
            text: issue.description || "",
          }).appendTo($item);
          $("<span>", {
            class: "impact impact-" + impact,
            text: label,
          }).appendTo($item);
          $("<span>", {
            class: "tip",
            text: issue.recommendation || "",
          }).appendTo($item);
          $modalIssues.append($item);
        });
      } else {
        $modalIssues.append(
          $("<li>", {
            class: "no-issues",
            text: "No outstanding SEO issues detected.",
          }),
        );
      }

      $modal
        .attr("hidden", false)
        .attr("aria-hidden", "false")
        .addClass("is-visible");
      if ($modalClose.length) {
        $modalClose.trigger("focus");
      }

      if (window.sessionStorage) {
        // Remember the last opened slug so the UI could restore it on
        // subsequent visits if desired.
        try {
          window.sessionStorage.setItem("seo:lastViewedSlug", activeSlug);
        } catch (error) {
          // ignore storage errors
        }
      }
    }

    // Helper used by click/keyboard handlers to open the corresponding
    // modal entry from the slug stored on the DOM node.
    function openModalBySlug(slug) {
      var page = pagesMap[slug];
      if (page) {
        openModal(page);
      }
    }

    /**
     * Update the toggle button so its icon, aria state, and label reflect
     * the current sort direction.
     */
    function updateSortDirectionControl(direction) {
      if (!$sortDirectionBtn.length) {
        return;
      }

      var isDesc = direction !== "asc";
      var iconClass = isDesc
        ? "fas fa-sort-amount-down-alt"
        : "fas fa-sort-amount-up";
      var labelText = isDesc ? "High to low" : "Low to high";

      $sortDirectionBtn.attr("data-direction", direction);
      $sortDirectionBtn.attr(
        "aria-label",
        "Toggle sort direction (" + labelText + ")",
      );
      $sortDirectionBtn.attr("aria-pressed", isDesc ? "true" : "false");
      $sortDirectionBtn.find("i").attr("class", iconClass);
      if ($sortDirectionLabel.length) {
        $sortDirectionLabel.text(labelText);
      }
    }

    /**
     * Recalculate the filtered and sorted result set then sync the grid
     * and table UI with that data.
     */
    function applyFilters() {
      var normalizedQuery = searchQuery.trim().toLowerCase();
      var filtered = pages.filter(function (page) {
        return (
          matchesFilter(page, currentFilter) &&
          matchesQuery(page, normalizedQuery)
        );
      });

      var sorted = sortPages(filtered, currentSortKey, sortDirection);
      var order = sorted.map(function (page) {
        return page.slug || "";
      });
      var visibleSet = new Set(order);

      if (order.length === 0) {
        $root.addClass("seo-dashboard--empty");
      } else {
        $root.removeClass("seo-dashboard--empty");
      }

      if (order.length) {
        reorderElements($grid, ".seo-page-card", order);
        reorderElements($tableBody, "tr", order);
      }

      $gridCards = $grid.find(".seo-page-card");
      $tableRows = $tableBody.find("tr");

      updateVisibility($gridCards, visibleSet);
      updateVisibility($tableRows, visibleSet);
    }

    if ($sortSelect.length) {
      currentSortKey = String($sortSelect.val() || currentSortKey);
      $sortSelect.on("change", function () {
        currentSortKey = String($(this).val() || "score");
        applyFilters();
      });
    }

    if ($sortDirectionBtn.length) {
      var direction = String(
        $sortDirectionBtn.data("direction") || sortDirection,
      );
      if (direction === "asc" || direction === "desc") {
        sortDirection = direction;
      }
      updateSortDirectionControl(sortDirection);
      $sortDirectionBtn.on("click", function () {
        sortDirection = sortDirection === "asc" ? "desc" : "asc";
        updateSortDirectionControl(sortDirection);
        applyFilters();
      });
    }

    applyFilters();

    $root.find(".a11y-filter-btn").on("click", function () {
      var $btn = $(this);
      var filter = String($btn.data("seo-filter") || "all");
      currentFilter = filter;
      $root
        .find(".a11y-filter-btn")
        .removeClass("active")
        .attr("aria-pressed", "false");
      $btn.addClass("active").attr("aria-pressed", "true");
      applyFilters();
    });

    $root.find("#seoSearchInput").on("input", function () {
      searchQuery = String($(this).val() || "");
      applyFilters();
    });

    $root.find(".seo-view-btn").on("click", function () {
      var $btn = $(this);
      var view = String($btn.data("seo-view") || "grid");
      if (view === currentView) {
        return;
      }
      currentView = view;
      $root
        .find(".seo-view-btn")
        .removeClass("active")
        .attr("aria-pressed", "false");
      $btn.addClass("active").attr("aria-pressed", "true");
      if (currentView === "table") {
        $grid.attr("hidden", "hidden").attr("aria-hidden", "true");
        $table.removeAttr("hidden").attr("aria-hidden", "false");
      } else {
        $table.attr("hidden", "hidden").attr("aria-hidden", "true");
        $grid.removeAttr("hidden").attr("aria-hidden", "false");
      }
    });

    var $scanAllBtn = $root.find('[data-seo-action="scan-all"]');
    if ($scanAllBtn.length) {
      // Mock the scan flow by recalculating stats after a short delay.
      $scanAllBtn.on("click", function () {
        var $btn = $(this);
        if ($btn.prop("disabled")) {
          return;
        }
        var $icon = $btn.find("i");
        var originalIcon = $icon.attr("class");
        $btn.prop("disabled", true).addClass("is-loading");
        $icon.attr("class", "fas fa-spinner fa-spin");
        $btn.find("span").text("Scanning...");

        window.setTimeout(function () {
          stats = calculateDashboardStats(pages, stats);
          if (moduleData && typeof moduleData === "object") {
            moduleData.stats = stats;
          }
          updateStats($root, stats);

          $btn.prop("disabled", false).removeClass("is-loading");
          $icon.attr("class", originalIcon);
          $btn.find("span").text("Scan All Pages");
        }, 900);
      });
    }

    $grid.on("click", ".seo-page-card", function (event) {
      var $target = $(event.target);
      if ($target.closest("[data-seo-action]").length) {
        return;
      }
      var slug = String($(this).data("page-slug") || "");
      openModalBySlug(slug);
    });

    $grid.on("keydown", ".seo-page-card", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        var slug = String($(this).data("page-slug") || "");
        openModalBySlug(slug);
      }
    });

    $tableBody.on("click", "tr", function () {
      var slug = String($(this).data("page-slug") || "");
      openModalBySlug(slug);
    });

    $tableBody.on("keydown", "tr", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        var slug = String($(this).data("page-slug") || "");
        openModalBySlug(slug);
      }
    });

    if ($modal.length) {
      $modal.on("click", function (event) {
        if (event.target === this) {
          closeModal();
        }
      });

      $modalClose.on("click", function () {
        closeModal();
      });

      $(document)
        .off("keydown.seoModal")
        .on("keydown.seoModal", function (event) {
          if (event.key === "Escape" && $modal.hasClass("is-visible")) {
            closeModal();
          }
        });

      $fullAuditBtn.on("click", function () {
        if (!activeSlug) {
          return;
        }

        var page = pagesMap[activeSlug] || {};
        var title = page.title ? '"' + page.title + '"' : "this page";

        alert(
          "Generating a full SEO audit for " +
            title +
            "\n\nThis would trigger an in-depth review of metadata, structured data, content quality, and linking signals without leaving the dashboard.",
        );
      });
    }
  }

  /**
   * Bind behaviours for the individual page detail view (severity filters
   * and the rescan button).
   */
  function initDetail($root) {
    var $issueCards = $root.find("[data-impact]");
    var $buttons = $root.find("[data-seo-severity]");
    var $status = $("#seoIssueFilterStatus");

    // Toggle which issue cards are visible for the selected severity and
    // update the live region so screen reader users receive feedback.
    function updateIssueVisibility(filter) {
      var visibleCount = 0;
      $issueCards.each(function () {
        var $card = $(this);
        var impact = String($card.data("impact") || "");
        var shouldShow = filter === "all" || impact === filter;
        if (shouldShow) {
          $card.removeAttr("hidden").show();
          visibleCount++;
        } else {
          $card.attr("hidden", "hidden").hide();
        }
      });
      if (visibleCount === 0) {
        $("#seoNoIssuesMessage").removeAttr("hidden").show();
      } else {
        $("#seoNoIssuesMessage").attr("hidden", "hidden").hide();
      }
      if ($status.length) {
        $status.text(
          visibleCount +
            " issues visible for severity filter “" +
            filter +
            "”.",
        );
      }
    }

    $buttons.on("click", function () {
      var $btn = $(this);
      var filter = String($btn.data("seo-severity") || "all");
      $buttons.removeClass("active").attr("aria-pressed", "false");
      $btn.addClass("active").attr("aria-pressed", "true");
      updateIssueVisibility(filter);
    });

    // Simulate a page re-scan when the button is clicked to reinforce what
    // the real integration would do.
    $root.find('[data-seo-action="rescan-page"]').on("click", function () {
      alert(
        "Re-scanning this page...\n\nA fresh crawl would analyse metadata, headings, internal links, and structured data to refresh the SEO score.",
      );
    });

    updateIssueVisibility("all");
  }

  // Kick off the dashboard/detail initialisers once the DOM is ready.
  $(function () {
    var moduleData = getModuleData();
    var pages = Array.isArray(moduleData.pages) ? moduleData.pages : [];
    var stats = moduleData.stats || {};
    var pagesMap = {};
    // Create a quick lookup by slug for modal interactions.
    pages.forEach(function (page) {
      pagesMap[String(page.slug || "")] = page;
    });

    var $dashboard = $(".seo-dashboard");
    if ($dashboard.length) {
      initDashboard($dashboard, pages, pagesMap, stats, moduleData);
    }

    var $detail = $(".seo-detail-page");
    if ($detail.length) {
      initDetail($detail);
    }
  });
})(jQuery);
