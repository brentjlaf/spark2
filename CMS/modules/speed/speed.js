(function ($) {
  "use strict";

  function loadSpeedModule(params) {
    const $container = $("#contentContainer");
    if (!$container.length || typeof $.fn.load !== "function") {
      return false;
    }

    const query = params ? "?" + params : "";
    $container.load("modules/speed/view.php" + query, function () {
      $container.find(".content-section").addClass("active");
      $.getScript("modules/speed/speed.js").fail(function () {
        // no-op if the script fails to reload
      });
    });
    return true;
  }

  function getScoreClass(score) {
    if (score >= 90) {
      return "speed-score--a";
    }
    if (score >= 80) {
      return "speed-score--b";
    }
    if (score >= 70) {
      return "speed-score--c";
    }
    return "speed-score--d";
  }

  function normalizeScoreValue(value, fallback) {
    const number = Number(value);
    if (Number.isFinite(number)) {
      return Math.max(0, Math.min(100, Math.round(number)));
    }
    return fallback;
  }

  function getScoreDeltaMeta(current, previous) {
    const currentScore = normalizeScoreValue(current, 0);
    const previousScore = normalizeScoreValue(previous, currentScore);
    const delta = currentScore - previousScore;
    const absolute = Math.abs(delta);
    let className = "score-delta--even";
    let srText = "No change since last scan.";
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
    const display = delta === 0 ? "0" : (delta > 0 ? "+" : "−") + absolute;
    return {
      display: display,
      className: className,
      srText: srText,
    };
  }

  function renderScoreDelta(current, previous) {
    const meta = getScoreDeltaMeta(current, previous);
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

  function getGradeBadge(grade) {
    switch ((grade || "").toUpperCase()) {
      case "A":
        return "grade-a";
      case "B":
        return "grade-b";
      case "C":
        return "grade-c";
      default:
        return "grade-d";
    }
  }

  function getImpactLabel(impact) {
    switch (impact) {
      case "critical":
        return "Critical";
      case "serious":
        return "Major";
      case "moderate":
        return "Moderate";
      case "minor":
        return "Minor";
      default:
        return "Review";
    }
  }

  function formatAlertSummary(alerts) {
    if (!alerts) {
      return "";
    }
    const segments = [];
    if (alerts.critical) {
      segments.push(alerts.critical + " critical");
    }
    if (alerts.serious) {
      segments.push(alerts.serious + " major");
    }
    if (alerts.moderate) {
      segments.push(alerts.moderate + " moderate");
    }
    if (alerts.minor) {
      segments.push(alerts.minor + " minor");
    }
    if (!segments.length) {
      return "No outstanding alerts";
    }
    return (
      (alerts.total || segments.length) + " total (" + segments.join(", ") + ")"
    );
  }

  function renderMetricList(metrics) {
    if (!metrics) {
      return "";
    }
    const weight = metrics.weightKb || 0;
    const images = metrics.imageCount || 0;
    const scripts = metrics.scriptCount || 0;
    const stylesheets =
      (metrics.stylesheetCount || 0) + (metrics.inlineStyles || 0);
    const domNodes = metrics.domNodes || 0;
    const iframeCount = metrics.iframeCount || 0;
    const avgImageWeight = metrics.avgImageWeight || 0;
    return [
      '<li><span class="label">Estimated weight</span><span class="value">' +
        weight +
        ' KB</span><span class="hint">Aim for under 500 KB for primary content.</span></li>',
      '<li><span class="label">Images</span><span class="value">' +
        images +
        '</span><span class="hint">' +
        (images > 10
          ? "Consider lazy loading and compression."
          : "Image usage looks healthy.") +
        "</span></li>",
      '<li><span class="label">Average image weight</span><span class="value">' +
        avgImageWeight +
        ' KB</span><span class="hint">Smaller than 50 KB keeps pages snappy.</span></li>',
      '<li><span class="label">Scripts</span><span class="value">' +
        scripts +
        '</span><span class="hint">' +
        (scripts > 5
          ? "Bundle and defer non-critical code."
          : "Script budget within limits.") +
        "</span></li>",
      '<li><span class="label">Stylesheets</span><span class="value">' +
        stylesheets +
        '</span><span class="hint">Deliver only the CSS needed for first paint.</span></li>',
      '<li><span class="label">DOM nodes</span><span class="value">' +
        domNodes +
        '</span><span class="hint">Keep below 1,500 for smooth rendering.</span></li>',
      '<li><span class="label">Embeds</span><span class="value">' +
        iframeCount +
        '</span><span class="hint">Lazy-load video or map embeds to avoid jank.</span></li>',
    ].join("");
  }

  function filterPages(data, filter, query) {
    return data.filter(function (page) {
      let matchesFilter = true;
      switch (filter) {
        case "slow":
          matchesFilter = page.performanceCategory === "slow";
          break;
        case "monitor":
          matchesFilter = page.performanceCategory === "monitor";
          break;
        case "fast":
          matchesFilter = page.performanceCategory === "fast";
          break;
        default:
          matchesFilter = true;
      }

      if (!matchesFilter) {
        return false;
      }

      if (!query) {
        return true;
      }

      const haystacks = [
        page.title || "",
        page.url || "",
        page.path || "",
        page.summaryLine || "",
        page.statusMessage || "",
        page.grade || "",
        page.performanceCategory || "",
      ];
      if (page.issues && Array.isArray(page.issues.preview)) {
        haystacks.push(page.issues.preview.join(" "));
      }
      return haystacks.join(" ").toLowerCase().indexOf(query) !== -1;
    });
  }

  function getSortValue(page, key) {
    if (!page) {
      return 0;
    }
    switch (key) {
      case "title":
        return (page.title || "").toLowerCase();
      case "alerts":
        if (page.alerts && typeof page.alerts.total === "number") {
          return page.alerts.total;
        }
        return page.warnings || 0;
      case "weight":
        if (page.metrics && typeof page.metrics.weightKb === "number") {
          return page.metrics.weightKb;
        }
        return 0;
      case "score":
      default:
        return typeof page.performanceScore === "number"
          ? page.performanceScore
          : 0;
    }
  }

  function sortPages(pages, key, direction) {
    if (!Array.isArray(pages)) {
      return [];
    }
    const dir = direction === "asc" ? 1 : -1;
    const items = pages.slice();
    items.sort(function (a, b) {
      const aVal = getSortValue(a, key);
      const bVal = getSortValue(b, key);

      if (aVal === bVal) {
        const aTitle = (a.title || "").toLowerCase();
        const bTitle = (b.title || "").toLowerCase();
        if (aTitle < bTitle) {
          return -1;
        }
        if (aTitle > bTitle) {
          return 1;
        }
        return 0;
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

  function updateFilterPills(data, $buttons) {
    const counts = {
      all: data.length,
      slow: 0,
      monitor: 0,
      fast: 0,
    };

    data.forEach(function (page) {
      if (page.performanceCategory === "slow") {
        counts.slow += 1;
      } else if (page.performanceCategory === "monitor") {
        counts.monitor += 1;
      } else if (page.performanceCategory === "fast") {
        counts.fast += 1;
      }
    });

    $buttons.each(function () {
      const $btn = $(this);
      const type = $btn.data("speed-filter");
      const $count = $btn.find(".a11y-filter-count");
      if ($count.length && Object.prototype.hasOwnProperty.call(counts, type)) {
        $count.text(counts[type]);
      }
    });
  }

  function createIssueTags(page) {
    if (!page.issues || !Array.isArray(page.issues.preview)) {
      return "";
    }
    let severityClass = "";
    if (page.alerts && page.alerts.critical > 0) {
      severityClass = "critical";
    } else if (page.alerts && page.alerts.serious > 0) {
      severityClass = "serious";
    }
    return page.issues.preview
      .map(function (issue) {
        return (
          '<span class="a11y-issue-tag ' +
          severityClass +
          '">' +
          issue +
          "</span>"
        );
      })
      .join("");
  }

  function createCard(page) {
    const $card = $("<article>", {
      class: "a11y-page-card",
      tabindex: 0,
      role: "listitem",
      "data-slug": page.slug,
    });

    const weight =
      page.metrics && page.metrics.weightKb
        ? page.metrics.weightKb + " KB"
        : "—";
    const alertsTotal =
      page.alerts && typeof page.alerts.total !== "undefined"
        ? page.alerts.total
        : page.warnings || 0;

    const score = normalizeScoreValue(page.performanceScore, 0);
    const deltaHtml = renderScoreDelta(score, page.previousScore);
    const cardHtml = [
      '<div class="a11y-page-card__header">',
      '<div class="a11y-page-card__title">',
      '<h3 class="a11y-page-title">' + (page.title || "Untitled") + "</h3>",
      '<p class="a11y-page-url">' + (page.url || "") + "</p>",
      "</div>",
      '<div class="score-indicator score-indicator--card">',
      '<div class="a11y-page-score ' +
        getScoreClass(score) +
        '"><span class="score-indicator__number">' +
        score +
        "%</span></div>",
      deltaHtml,
      "</div>",
      "</div>",
      '<div class="a11y-page-card__metrics">',
      '<div><span class="label">Grade</span><span class="value ' +
        getGradeBadge(page.grade) +
        '">' +
        (page.grade || "—") +
        "</span></div>",
      '<div><span class="label">Alerts</span><span class="value">' +
        alertsTotal +
        "</span></div>",
      '<div><span class="label">Weight</span><span class="value">' +
        weight +
        "</span></div>",
      "</div>",
      '<div class="a11y-page-card__issues">',
      '<div class="a11y-issue-tags">' + createIssueTags(page) + "</div>",
      "</div>",
    ].join("");

    $card.html(cardHtml);
    return $card;
  }

  function createTableRow(page) {
    const $row = $("<div>", {
      class: "a11y-table-row",
      role: "row",
      "data-slug": page.slug,
    });

    const alertsSummary = formatAlertSummary(page.alerts);
    const weight =
      page.metrics && page.metrics.weightKb
        ? page.metrics.weightKb + " KB"
        : "—";

    const score = normalizeScoreValue(page.performanceScore, 0);
    const deltaHtml = renderScoreDelta(score, page.previousScore);
    const rowHtml = [
      '<div class="a11y-table-cell">',
      '<div class="title">' + (page.title || "Untitled") + "</div>",
      '<div class="subtitle">' + (page.url || "") + "</div>",
      "</div>",
      '<div class="a11y-table-cell score">',
      '<div class="score-indicator score-indicator--table">',
      '<span class="a11y-table-score ' +
        getScoreClass(score) +
        '"><span class="score-indicator__number">' +
        score +
        "%</span></span>",
      deltaHtml,
      "</div>",
      "</div>",
      '<div class="a11y-table-cell level"><span class="' +
        getGradeBadge(page.grade) +
        '">' +
        (page.grade || "—") +
        "</span></div>",
      '<div class="a11y-table-cell">' + alertsSummary + "</div>",
      '<div class="a11y-table-cell">' + weight + "</div>",
      '<div class="a11y-table-cell">' + (page.lastScanned || "") + "</div>",
      '<div class="a11y-table-cell actions"><button type="button" class="a11y-btn a11y-btn--icon" data-speed-action="open-detail" data-slug="' +
        page.slug +
        '"><i class="fas fa-gauge-high" aria-hidden="true"></i><span class="sr-only">Open detail</span></button></div>',
    ].join("");

    $row.html(rowHtml);
    return $row;
  }

  $(function () {
    const data = Array.isArray(window.speedDashboardData)
      ? window.speedDashboardData
      : [];
    const stats = window.speedDashboardStats || {};
    const dataMap = {};
    data.forEach(function (page) {
      dataMap[page.slug] = page;
    });

    const $grid = $("#speedPagesGrid");
    const $tableView = $("#speedTableView");
    const $tableBody = $("#speedTableBody");
    const $empty = $("#speedEmptyState");
    const $filterButtons = $("[data-speed-filter]");
    const $viewButtons = $("[data-speed-view]");
    const $searchInput = $("#speedSearchInput");
    const $sortSelect = $("#speedSortSelect");
    const $sortDirectionBtn = $("#speedSortDirection");
    const $sortDirectionLabel = $("#speedSortDirectionLabel");
    const $modal = $("#speedPageDetail");
    const $modalClose = $("#speedDetailClose");
    const $modalMetrics = $("#speedDetailMetrics");
    const $modalIssues = $("#speedDetailIssues");
    const $modalScore = $("#speedDetailScore");
    const $modalGrade = $("#speedDetailGrade");
    const $modalAlerts = $("#speedDetailAlerts");
    const $modalTitle = $("#speedDetailTitle");
    const $modalUrl = $("#speedDetailUrl");
    const $modalDescription = $("#speedDetailDescription");
    const $fullAuditBtn = $modal.find('[data-speed-action="full-diagnose"]');
    const $scanAllBtn = $("#speedScanAllBtn");
    const $emptyCta = $("#speedEmptyCta");
    const $downloadReportBtn = $("#speedDownloadReport");
    const $heroHeaviest = $('[data-speed-action="view-heaviest"]');

    let currentFilter = "all";
    let currentView = "grid";
    let currentSort = "score";
    let sortDirection = "desc";
    let filteredPages = data.slice();
    let activeSlug = null;

    if ($sortSelect.length) {
      currentSort = $sortSelect.val() || currentSort;
    }
    if ($sortDirectionBtn.length) {
      const dir = $sortDirectionBtn.data("direction");
      if (dir === "asc" || dir === "desc") {
        sortDirection = dir;
      }
    }

    function updateSortDirectionDisplay() {
      if (!$sortDirectionBtn.length) {
        return;
      }
      const isAsc = sortDirection === "asc";
      const iconClass = isAsc
        ? "fas fa-sort-amount-up"
        : "fas fa-sort-amount-down-alt";
      let labelText = isAsc ? "Low to high" : "High to low";
      if (currentSort === "title") {
        labelText = isAsc ? "A to Z" : "Z to A";
      }
      $sortDirectionBtn.attr("data-direction", sortDirection);
      $sortDirectionBtn.attr(
        "aria-label",
        "Toggle sort direction (" + labelText + ")",
      );
      $sortDirectionBtn.attr("aria-pressed", isAsc ? "false" : "true");
      const $icon = $sortDirectionBtn.find("i");
      if ($icon.length) {
        $icon.attr("class", iconClass);
      }
      if ($sortDirectionLabel.length) {
        $sortDirectionLabel.text(labelText);
      }
    }

    filteredPages = sortPages(filteredPages, currentSort, sortDirection);
    updateSortDirectionDisplay();

    function closeModal() {
      activeSlug = null;
      $modal.attr("hidden", true).removeClass("is-visible");
    }

    function openModal(page) {
      if (!page) {
        return;
      }
      activeSlug = page.slug;
      $modalTitle.text(page.title || "Performance details");
      $modalUrl.text(page.url || "");
      $modalDescription.text(page.summaryLine || page.statusMessage || "");
      const modalScore = normalizeScoreValue(page.performanceScore, 0);
      const modalDelta = renderScoreDelta(modalScore, page.previousScore);
      $modalScore
        .removeClass(
          "speed-score--a speed-score--b speed-score--c speed-score--d",
        )
        .addClass(getScoreClass(modalScore))
        .html(
          '<span class="score-indicator__number">' +
            modalScore +
            "%</span>" +
            modalDelta,
        );
      $modalGrade.text(page.grade || "—");
      $modalGrade
        .removeClass("grade-a grade-b grade-c grade-d")
        .addClass(getGradeBadge(page.grade));
      $modalAlerts.text(formatAlertSummary(page.alerts));
      $modalMetrics.html(renderMetricList(page.metrics));

      if (
        page.issues &&
        Array.isArray(page.issues.details) &&
        page.issues.details.length
      ) {
        const issueItems = page.issues.details.map(function (issue) {
          const impact = getImpactLabel(issue.impact);
          return (
            '<li><span class="issue">' +
            issue.description +
            '</span><span class="impact impact-' +
            issue.impact +
            '">' +
            impact +
            '</span><span class="tip">' +
            issue.recommendation +
            "</span></li>"
          );
        });
        $modalIssues.html(issueItems.join(""));
      } else {
        $modalIssues.html(
          '<li class="no-issues">No outstanding alerts detected.</li>',
        );
      }

      $modal.attr("hidden", false).addClass("is-visible");
    }

    function render() {
      if (!$grid.length) {
        return;
      }

      if (!filteredPages.length) {
        $grid.empty().attr("hidden", true);
        $tableView.attr("hidden", true);
        $empty.attr("hidden", false);
        return;
      }

      $empty.attr("hidden", true);

      if (currentView === "grid") {
        $grid.attr("hidden", false);
        $tableView.attr("hidden", true);
        $grid.empty();
        filteredPages.forEach(function (page) {
          $grid.append(createCard(page));
        });
      } else {
        $grid.attr("hidden", true).empty();
        $tableView.attr("hidden", false);
        $tableBody.empty();
        filteredPages.forEach(function (page) {
          $tableBody.append(createTableRow(page));
        });
      }
    }

    function applyFilters() {
      const query = ($searchInput.val() || "").toLowerCase();
      const filtered = filterPages(data, currentFilter, query);
      filteredPages = sortPages(filtered, currentSort, sortDirection);
      render();
    }

    if ($grid.length) {
      updateFilterPills(data, $filterButtons);
      render();
    }

    if ($sortSelect.length) {
      $sortSelect.on("change", function () {
        currentSort = $(this).val() || "score";
        updateSortDirectionDisplay();
        applyFilters();
      });
    }

    if ($sortDirectionBtn.length) {
      $sortDirectionBtn.on("click", function () {
        sortDirection = sortDirection === "asc" ? "desc" : "asc";
        updateSortDirectionDisplay();
        applyFilters();
      });
    }

    $filterButtons.on("click", function () {
      const $btn = $(this);
      currentFilter = $btn.data("speed-filter") || "all";
      $filterButtons.removeClass("active");
      $btn.addClass("active");
      applyFilters();
    });

    $viewButtons.on("click", function () {
      const $btn = $(this);
      currentView = $btn.data("speed-view") || "grid";
      $viewButtons.removeClass("active");
      $btn.addClass("active");
      render();
    });

    $searchInput.on("input", function () {
      applyFilters();
    });

    $grid.on("click", ".a11y-page-card", function () {
      const slug = $(this).data("slug");
      openModal(dataMap[slug]);
    });

    $grid.on("keydown", ".a11y-page-card", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        const slug = $(this).data("slug");
        openModal(dataMap[slug]);
      }
    });

    $tableBody.on("click", ".a11y-table-row", function (event) {
      const $target = $(event.target);
      if ($target.closest('[data-speed-action="open-detail"]').length) {
        return;
      }
      const slug = $(this).data("slug");
      openModal(dataMap[slug]);
    });

    $tableBody.on(
      "click",
      '[data-speed-action="open-detail"]',
      function (event) {
        event.stopPropagation();
        const slug = $(this).data("slug");
        if (!loadSpeedModule("page=" + encodeURIComponent(slug))) {
          if (stats.detailBaseUrl) {
            window.location.href =
              stats.detailBaseUrl + encodeURIComponent(slug);
          }
        }
      },
    );

    if ($modal.length) {
      $(document).off("keydown.speedModal");
      $modal.on("click", function (event) {
        if (event.target === this) {
          closeModal();
        }
      });

      $modalClose.on("click", function () {
        closeModal();
      });

      $(document).on("keydown.speedModal", function (event) {
        if (event.key === "Escape" && $modal.hasClass("is-visible")) {
          closeModal();
        }
      });

      $fullAuditBtn.on("click", function () {
        if (!activeSlug) {
          return;
        }
        if (!loadSpeedModule("page=" + encodeURIComponent(activeSlug))) {
          if (stats.detailBaseUrl) {
            window.location.href =
              stats.detailBaseUrl + encodeURIComponent(activeSlug);
          }
        }
      });
    }

    if ($scanAllBtn.length) {
      $scanAllBtn.on("click", function () {
        const $btn = $(this);
        if ($btn.prop("disabled")) {
          return;
        }

        const $icon = $btn.find("i");
        const originalIcon = $icon.attr("class");
        const $label = $btn.find("span");
        const originalText = $label.text();

        $btn.prop("disabled", true).addClass("is-loading");
        $icon.attr("class", "fas fa-spinner fa-spin");
        $label.text("Scanning...");

        window.setTimeout(function () {
          const reloaded = loadSpeedModule("");
          if (!reloaded) {
            if (stats && stats.moduleUrl) {
              window.location.href = stats.moduleUrl;
              return;
            }

            $icon.attr("class", originalIcon);
            $label.text(originalText);
            $btn.prop("disabled", false).removeClass("is-loading");
          }
        }, 900);
      });
    }

    if ($emptyCta.length) {
      $emptyCta.on("click", function () {
        $scanAllBtn.trigger("click");
      });
    }

    if ($downloadReportBtn.length) {
      $downloadReportBtn.on("click", function () {
        window.alert(
          "Preparing performance export...\n\nThe report will include asset weight, script budgets, lazy-loading recommendations, and before/after comparisons.",
        );
      });
    }

    if ($heroHeaviest.length) {
      $heroHeaviest.on("click", function () {
        const slug = $(this).data("speed-slug");
        if (!slug) {
          return;
        }
        if (!loadSpeedModule("page=" + encodeURIComponent(slug))) {
          if (stats.detailBaseUrl) {
            window.location.href =
              stats.detailBaseUrl + encodeURIComponent(slug);
          }
        }
      });
    }

    const $detailPage = $("#speedDetailPage");
    if ($detailPage.length) {
      const pageSlug = $detailPage.data("page-slug");
      $("#speedBackToDashboard").on("click", function (event) {
        event.preventDefault();
        if (!loadSpeedModule("")) {
          if (stats.moduleUrl) {
            window.location.href = stats.moduleUrl;
          }
        }
      });
      const pageData = dataMap[pageSlug];
      const $rescanBtn = $detailPage.find('[data-speed-action="rescan-page"]');
      const $exportBtn = $detailPage.find(
        '[data-speed-action="export-page-report"]',
      );

      if ($rescanBtn.length) {
        $rescanBtn.on("click", function () {
          window.alert(
            "Re-running speed diagnostics for this page...\n\nThis would trigger a fresh performance audit capturing Core Web Vitals, asset payloads, and blocking resources.",
          );
        });
      }

      if ($exportBtn.length) {
        $exportBtn.on("click", function () {
          window.alert(
            "Exporting detailed speed report...\n\nThe export would include filmstrips, resource waterfalls, and suggested optimizations for this specific page.",
          );
        });
      }

      if (pageData) {
        const $detailMetrics = $detailPage.find(".a11y-detail-metrics");
        const $detailIssuesList = $detailPage.find(".a11y-issue-list");
        if ($detailMetrics.length) {
          $detailMetrics.attr("data-score", pageData.performanceScore);
        }
        if (
          $detailIssuesList.length &&
          (!pageData.issues ||
            !pageData.issues.details ||
            !pageData.issues.details.length)
        ) {
          $detailIssuesList.replaceWith(
            '<p class="a11y-detail-success">This page passed the automated checks with no remaining alerts.</p>',
          );
        }
      }
    }
  });
})(jQuery);
