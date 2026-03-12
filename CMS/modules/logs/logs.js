// File: logs.js  –  Activity audit log dashboard
$(function () {
  var dashboard = $(".logs-dashboard");
  if (!dashboard.length) return;

  var timeline         = $("#logsTimeline");
  var matchCountEl     = $("#logsMatchCount");
  var filterContainer  = $("#logsFilters");
  var contextContainer = $("#logsContextFilters");
  var userFilter       = $("#logsUserFilter");
  var searchInput      = $("#logsSearch");
  var refreshBtn       = $("#logsRefreshBtn");
  var endpoint         = dashboard.data("endpoint");

  var statsEls = {
    total:          $("#logsTotalCount"),
    last7:          $("#logsLast7Days"),
    users:          $("#logsUserCount"),
    pages:          $("#logsPageCount"),
    topActionLabel: $("#logsTopActionLabel"),
    topActionCount: $("#logsTopActionCount"),
    lastActivity:   $("#logsLastActivity"),
    past24h:        $("#logsPast24h"),
  };

  var currentAction  = "all";
  var currentUser    = "all";
  var currentContext = "all";
  var currentSearch  = "";
  var allLogs        = [];

  // ── Utilities ───────────────────────────────────────────────────────────────
  function escapeHtml(str) {
    return $("<div>").text(str).html();
  }

  function getActionLabel(log) {
    var raw = log && typeof log.action !== "undefined" ? String(log.action) : "";
    return raw.trim() || "Updated content";
  }

  function slugifyAction(label) {
    return (
      label
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "") || "unknown"
    );
  }

  function normalizeUserValue(value) {
    if (value === null || typeof value === "undefined") return "";
    return String(value).trim();
  }

  function userKeyFromNormalized(n) { return n === "" ? "system" : n.toLowerCase(); }
  function userDisplayFromNormalized(n) { return n === "" ? "System" : n; }

  function formatAbsolute(ts) {
    if (!ts) return "No recent activity";
    return new Date(ts * 1000).toLocaleString();
  }

  function heroTime(ts) {
    if (!ts) return "No activity yet";
    var diff = Date.now() - ts * 1000;
    if (diff < 0) return "Scheduled update";
    var s = Math.floor(diff / 1000);
    if (s < 60)    return "Just now";
    if (s < 3600)  { var m = Math.floor(s / 60);   return m + " min" + (m === 1 ? "" : "s") + " ago"; }
    if (s < 86400) { var h = Math.floor(s / 3600);  return h + " hour" + (h === 1 ? "" : "s") + " ago"; }
    if (s < 604800){ var d = Math.floor(s / 86400); return d + " day" + (d === 1 ? "" : "s") + " ago"; }
    return formatAbsolute(ts);
  }

  function relativeTime(ts) {
    if (!ts) return "Unknown time";
    var diff = Date.now() - ts * 1000;
    if (diff < 0) return "Scheduled update";
    var s = Math.floor(diff / 1000);
    if (s < 60)    return "Just now";
    if (s < 3600)  { var m = Math.floor(s / 60);   return m + " min" + (m === 1 ? "" : "s"); }
    if (s < 86400) { var h = Math.floor(s / 3600);  return h + " hr" + (h === 1 ? "" : "s"); }
    if (s < 604800){ var d = Math.floor(s / 86400); return d + " day" + (d === 1 ? "" : "s"); }
    return formatAbsolute(ts);
  }

  // ── Context badge ───────────────────────────────────────────────────────────
  var CTX_CLASSES = {
    page:     "logs-ctx-badge--page",
    post:     "logs-ctx-badge--post",
    media:    "logs-ctx-badge--media",
    user:     "logs-ctx-badge--user",
    settings: "logs-ctx-badge--settings",
    commerce: "logs-ctx-badge--commerce",
    system:   "logs-ctx-badge--system",
  };

  function contextBadge(ctx) {
    var cls = CTX_CLASSES[ctx] || "logs-ctx-badge--default";
    return '<span class="logs-ctx-badge ' + cls + '">' + escapeHtml(ctx.charAt(0).toUpperCase() + ctx.slice(1)) + "</span>";
  }

  // ── Normalize raw log data ──────────────────────────────────────────────────
  function normalizeLogs(logs) {
    if (typeof logs === "string") {
      try { logs = JSON.parse(logs); } catch (e) { logs = []; }
    }
    if (!Array.isArray(logs)) return [];
    return logs.map(function (item) {
      var label = getActionLabel(item);
      var slug  = item && item.action_slug ? String(item.action_slug) : slugifyAction(label);
      var ctx   = item && item.context ? String(item.context) : "system";
      var rawDetails = item && typeof item.details !== "undefined" ? item.details : [];
      var details = [];
      if (Array.isArray(rawDetails)) {
        details = rawDetails.map(String);
      } else if (rawDetails !== null && rawDetails !== "" && typeof rawDetails !== "undefined") {
        details = [String(rawDetails)];
      }
      var normUser   = normalizeUserValue(item && item.user);
      var userKey    = userKeyFromNormalized(normUser);
      var userDisplay = userDisplayFromNormalized(normUser);
      var subject     = item && item.page_title ? String(item.page_title) : (ctx === "system" ? "System activity" : "Unknown");
      var searchText  = (userDisplay + " " + subject + " " + label + " " + ctx + " " + details.join(" ")).toLowerCase();

      return {
        time:        parseInt(item.time, 10) || 0,
        user:        normUser,
        user_display: userDisplay,
        user_key:    userKey,
        page_title:  subject,
        action:      label,
        action_slug: slug,
        context:     ctx,
        details:     details,
        search_text: searchText,
      };
    }).sort(function (a, b) { return b.time - a.time; });
  }

  // ── Action summary ──────────────────────────────────────────────────────────
  function summarizeActions(logs) {
    var summary = {};
    logs.forEach(function (log) {
      var slug = log.action_slug;
      if (!summary[slug]) summary[slug] = { slug: slug, label: log.action, count: 0 };
      summary[slug].count += 1;
    });
    return Object.values(summary).sort(function (a, b) { return b.count - a.count; });
  }

  // ── Context summary ─────────────────────────────────────────────────────────
  function summarizeContexts(logs) {
    var counts = {};
    logs.forEach(function (log) {
      var c = log.context || "system";
      counts[c] = (counts[c] || 0) + 1;
    });
    return counts;
  }

  // ── Build a table row ───────────────────────────────────────────────────────
  function buildRow(log) {
    var label       = log.action;
    var slug        = log.action_slug;
    var ts          = log.time || 0;
    var exact       = ts ? new Date(ts * 1000).toISOString() : "";
    var absolute    = formatAbsolute(ts);
    var relative    = relativeTime(ts);
    var ctx         = log.context || "system";
    var subject     = log.page_title || (ctx === "system" ? "System activity" : "Unknown");
    var userDisplay = log.user_display || "System";
    var details     = Array.isArray(log.details) ? log.details : (log.details ? [log.details] : []);
    var detailsHtml = details.length
      ? '<ul class="logs-activity-details">' + details.map(function (d) { return "<li>" + escapeHtml(d) + "</li>"; }).join("") + "</ul>"
      : '<span class="logs-activity-details-empty">—</span>';

    return (
      '<tr class="logs-activity-row"' +
        ' data-search="' + escapeHtml(log.search_text || "") + '"' +
        ' data-action="' + escapeHtml(slug) + '"' +
        ' data-context="' + escapeHtml(ctx) + '"' +
        ' data-user="' + escapeHtml(log.user_key || "") + '">' +
      '<td class="logs-activity-cell logs-activity-cell--action" data-label="Action">' +
        '<span class="logs-activity-badge">' + escapeHtml(label) + "</span>" +
      "</td>" +
      '<td class="logs-activity-cell" data-label="Context">' +
        contextBadge(ctx) +
      "</td>" +
      '<td class="logs-activity-cell logs-activity-cell--page" data-label="Subject">' +
        '<span class="logs-activity-page">' + escapeHtml(subject) + "</span>" +
      "</td>" +
      '<td class="logs-activity-cell logs-activity-cell--user" data-label="Editor">' +
        '<span class="logs-activity-user">' + escapeHtml(userDisplay) + "</span>" +
      "</td>" +
      '<td class="logs-activity-cell logs-activity-cell--details" data-label="Details">' +
        detailsHtml +
      "</td>" +
      '<td class="logs-activity-cell logs-activity-cell--time" data-label="When">' +
        '<time datetime="' + exact + '" class="logs-activity-time" title="' + escapeHtml(absolute) + '">' +
          escapeHtml(relative) +
        "</time>" +
      "</td>" +
      "</tr>"
    );
  }

  // ── Render timeline ─────────────────────────────────────────────────────────
  function updateTimeline(logs) {
    if (!logs.length) {
      var hasAny     = allLogs.length > 0;
      var isFiltered = currentAction !== "all" || currentUser !== "all" || currentContext !== "all" || currentSearch !== "";
      var msg  = hasAny && isFiltered ? "No activity matches the current filters." : "No activity recorded yet.";
      var hint = hasAny && isFiltered ? "Try adjusting your filters." : "Updates will appear here as your team edits content.";
      timeline.html(
        '<div class="logs-empty"><i class="fas fa-clipboard-list" aria-hidden="true"></i><p>' +
          escapeHtml(msg) + '</p><p class="logs-empty-hint">' + escapeHtml(hint) + "</p></div>"
      );
      return;
    }
    var rows  = logs.map(buildRow).join("");
    var table =
      '<div class="logs-activity-table-scroll">' +
      '<table class="logs-activity-table"><thead><tr>' +
      '<th scope="col">Action</th><th scope="col">Context</th>' +
      '<th scope="col">Subject</th><th scope="col">Editor</th>' +
      '<th scope="col">Details</th><th scope="col">When</th>' +
      "</tr></thead><tbody>" + rows + "</tbody></table></div>";
    timeline.html(table);
  }

  function updateMatchCount(count) {
    if (!matchCountEl.length) return;
    matchCountEl.text(count === 0 ? "No entries to display" : count === 1 ? "1 entry" : count + " entries");
  }

  // ── Stats update ────────────────────────────────────────────────────────────
  function updateStats(logs) {
    var total  = logs.length;
    var now    = Date.now();
    var past24 = logs.filter(function (l) { return l.time && now - l.time * 1000 <= 86400000; }).length;
    var last7  = logs.filter(function (l) { return l.time && now - l.time * 1000 <= 604800000; }).length;
    var users  = new Set(), pages = new Set();
    logs.forEach(function (l) {
      if (l.user)       users.add(l.user.toLowerCase());
      if (l.page_title) pages.add(l.page_title.toLowerCase());
    });

    var heroTs    = logs.length ? logs[0].time : 0;
    var heroLabel = heroTime(heroTs);
    var heroTitle = heroTs ? formatAbsolute(heroTs) : "No recent activity";

    if (statsEls.total.length)          statsEls.total.text(total);
    if (statsEls.last7.length)          statsEls.last7.text(last7);
    if (statsEls.users.length)          statsEls.users.text(users.size);
    if (statsEls.pages.length)          statsEls.pages.text(pages.size);
    if (statsEls.past24h.length)        statsEls.past24h.text(past24);
    if (statsEls.lastActivity.length)   statsEls.lastActivity.text(heroLabel).attr("title", heroTitle);
    $("#logsAllCount").text(total);

    var actions = summarizeActions(logs);
    if (statsEls.topActionLabel.length) {
      statsEls.topActionLabel.text(actions.length ? actions[0].label : "—");
      statsEls.topActionCount.text(actions.length ? actions[0].count + (actions[0].count === 1 ? " entry" : " entries") : "No recorded actions yet");
    }
  }

  // ── Render action filters ───────────────────────────────────────────────────
  function renderFilters(logs) {
    if (!filterContainer.length) return;
    var actions = summarizeActions(logs);
    if (currentAction !== "all" && !actions.some(function (a) { return a.slug === currentAction; })) {
      currentAction = "all";
    }

    filterContainer.empty();
    var allBtn = $('<button type="button" class="logs-filter-btn"></button>')
      .attr("data-filter", "all")
      .toggleClass("active", currentAction === "all")
      .append($("<span>").text("All activity"))
      .append($('<span class="logs-filter-count" id="logsAllCount"></span>').text(logs.length));
    filterContainer.append(allBtn);

    actions.slice(0, 4).forEach(function (action) {
      var btn = $('<button type="button" class="logs-filter-btn"></button>')
        .attr("data-filter", action.slug)
        .toggleClass("active", currentAction === action.slug)
        .append($("<span>").text(action.label))
        .append($('<span class="logs-filter-count"></span>').attr("data-filter-count", action.slug).text(action.count));
      filterContainer.append(btn);
    });
  }

  // ── Render context filters ──────────────────────────────────────────────────
  var CTX_LABEL_MAP = { page:"Pages", post:"Posts", media:"Media", user:"Users", settings:"Settings", commerce:"Commerce", system:"System" };

  function renderContextFilters(logs) {
    if (!contextContainer.length) return;
    var counts = summarizeContexts(logs);
    if (currentContext !== "all" && !counts[currentContext]) currentContext = "all";

    contextContainer.empty();
    var allBtn = $('<button type="button" class="logs-ctx-filter-btn"></button>')
      .attr("data-ctx", "all")
      .toggleClass("active", currentContext === "all")
      .text("All contexts");
    contextContainer.append(allBtn);

    var ctxOrder = ["page","post","media","user","settings","commerce","system"];
    ctxOrder.forEach(function (ctx) {
      if (!counts[ctx]) return;
      var label = CTX_LABEL_MAP[ctx] || (ctx.charAt(0).toUpperCase() + ctx.slice(1));
      var btn = $('<button type="button" class="logs-ctx-filter-btn"></button>')
        .attr("data-ctx", ctx)
        .toggleClass("active", currentContext === ctx)
        .html(escapeHtml(label) + ' <span style="opacity:.65">(' + counts[ctx] + ')</span>');
      contextContainer.append(btn);
    });
  }

  // ── User filter ─────────────────────────────────────────────────────────────
  function renderUserFilter(logs) {
    if (!userFilter.length) return;
    var userMap = new Map();
    logs.forEach(function (log) {
      var key   = log.user_key   || userKeyFromNormalized(normalizeUserValue(log.user));
      var label = log.user_display || userDisplayFromNormalized(normalizeUserValue(log.user));
      if (!userMap.has(key)) userMap.set(key, label);
    });
    if (currentUser !== "all" && !userMap.has(currentUser)) currentUser = "all";

    var sorted = Array.from(userMap.entries()).sort(function (a, b) {
      if (a[0] === "system") return -1;
      if (b[0] === "system") return 1;
      return a[1].toLowerCase().localeCompare(b[1].toLowerCase());
    });

    userFilter.empty();
    userFilter.append($('<option value="all">All editors</option>'));
    sorted.forEach(function (e) { userFilter.append($("<option></option>").val(e[0]).text(e[1])); });
    userFilter.prop("disabled", sorted.length === 0);
    userFilter.val(currentUser);
  }

  // ── Filtering pipeline ──────────────────────────────────────────────────────
  function logsForCurrentUser() {
    if (currentUser === "all") return allLogs;
    return allLogs.filter(function (l) { return l.user_key === currentUser; });
  }

  function applyFilters() {
    var userFiltered = logsForCurrentUser();
    var q = currentSearch.toLowerCase();

    var filtered = userFiltered.filter(function (log) {
      if (currentAction  !== "all" && log.action_slug !== currentAction)  return false;
      if (currentContext !== "all" && log.context     !== currentContext) return false;
      if (q && !(log.search_text || "").includes(q)) return false;
      return true;
    });

    updateTimeline(filtered);
    updateMatchCount(filtered.length);
  }

  // ── Batch render on new data ────────────────────────────────────────────────
  function setLogs(logs) {
    allLogs = normalizeLogs(logs);
    updateStats(allLogs);
    renderUserFilter(allLogs);
    renderFilters(logsForCurrentUser());
    renderContextFilters(logsForCurrentUser());
    applyFilters();
  }

  // ── Events ──────────────────────────────────────────────────────────────────
  filterContainer.on("click", "button", function () {
    var filter = $(this).data("filter") || "all";
    if (currentAction === filter) return;
    currentAction = filter;
    filterContainer.find("button").removeClass("active");
    $(this).addClass("active");
    applyFilters();
  });

  contextContainer.on("click", "button", function () {
    var ctx = $(this).data("ctx") || "all";
    if (currentContext === ctx) return;
    currentContext = ctx;
    contextContainer.find("button").removeClass("active");
    $(this).addClass("active");
    applyFilters();
  });

  if (userFilter.length) {
    userFilter.on("change", function () {
      currentUser = $(this).val() || "all";
      renderFilters(logsForCurrentUser());
      renderContextFilters(logsForCurrentUser());
      applyFilters();
    });
  }

  if (searchInput.length) {
    searchInput.on("input", function () {
      currentSearch = $(this).val() || "";
      applyFilters();
    });
  }

  if (refreshBtn.length) {
    refreshBtn.on("click", function () {
      if (!endpoint) return;
      refreshBtn.prop("disabled", true).addClass("is-loading");
      $.getJSON(endpoint)
        .done(function (data) { setLogs(data || []); })
        .fail(function () {
          var alert = $('<div class="logs-inline-alert" role="status">Unable to refresh activity right now.</div>');
          timeline.prepend(alert);
          setTimeout(function () { alert.fadeOut(250, function () { $(this).remove(); }); }, 4000);
        })
        .always(function () { refreshBtn.prop("disabled", false).removeClass("is-loading"); });
    });
  }

  // ── Boot ─────────────────────────────────────────────────────────────────────
  setLogs(dashboard.data("logs"));
});
