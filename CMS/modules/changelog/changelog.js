// File: modules/changelog/changelog.js
$(function () {
  var dashboard = $(".cl-dashboard");
  if (!dashboard.length) return;

  var allEntries   = [];
  var currentCat   = "";
  var currentMod   = "";
  var currentVer   = "";
  var currentQ     = "";

  // ── DOM refs ─────────────────────────────────────────────────────────────────
  var timeline     = $("#clTimeline");
  var searchInput  = $("#clSearch");
  var modFilter    = $("#clModuleFilter");
  var verFilter    = $("#clVersionFilter");
  var matchCount   = $("#clMatchCount");
  var catPills     = $("#clCatPills");

  var CAT_LABEL = { feature: "Feature", improvement: "Improvement", fix: "Fix", security: "Security" };
  var CAT_CLASS = { feature: "cl-cat-badge--feature", improvement: "cl-cat-badge--improvement", fix: "cl-cat-badge--fix", security: "cl-cat-badge--security" };
  var MOD_LABEL = {
    pages:"Pages", blogs:"Blogs", media:"Media Library", menus:"Menus", forms:"Forms",
    events:"Events", maps:"Maps", users:"Users", settings:"Settings", analytics:"Analytics",
    seo:"SEO", sitemap:"Sitemap", speed:"Performance", accessibility:"Accessibility",
    search:"Search", logs:"Logs", commerce:"Commerce", dashboard:"Dashboard", system:"System",
  };

  // ── Escape HTML ──────────────────────────────────────────────────────────────
  function esc(str) {
    return String(str || "")
      .replace(/&/g,  "&amp;")
      .replace(/</g,  "&lt;")
      .replace(/>/g,  "&gt;")
      .replace(/\"/g,  "&quot;")
      .replace(/'/g,  "&#39;");
  }

  // ── Build entry card HTML ────────────────────────────────────────────────────
  function buildCard(e) {
    var cat      = e.category || "feature";
    var mod      = e.module   || "system";
    var catLabel = CAT_LABEL[cat]  || cat.charAt(0).toUpperCase() + cat.slice(1);
    var modLabel = MOD_LABEL[mod]  || mod.charAt(0).toUpperCase() + mod.slice(1);
    var catCls   = CAT_CLASS[cat]  || "";
    var dateStr  = e.date ? new Date(e.date + "T12:00:00").toLocaleDateString(undefined, { year:"numeric", month:"short", day:"numeric" }) : "";
    var desc     = e.description || "";
    var benefit  = e.benefit     || "";
    var usage    = Array.isArray(e.usage) ? e.usage : [];
    var searchTxt = (e.title + " " + desc + " " + benefit + " " + mod + " " + cat + " " + (e.tags || []).join(" ")).toLowerCase();

    var benefitHtml = benefit
      ? '<div class="cl-benefit-box"><i class="fas fa-circle-check cl-benefit-icon" aria-hidden="true"></i><p class="cl-benefit-text"><strong>Why it matters:</strong> ' + esc(benefit) + "</p></div>"
      : "";

    var usageHtml = "";
    if (usage.length) {
      usageHtml =
        '<button type="button" class="cl-usage-toggle" aria-expanded="false"><i class="fas fa-chevron-right" aria-hidden="true"></i> How to use</button>' +
        '<ol class="cl-usage-steps">' +
        usage.map(function (s) { return "<li>" + esc(s) + "</li>"; }).join("") +
        "</ol>";
    }

    return (
      '<div class="cl-entry"' +
        ' data-id="' + e.id + '"' +
        ' data-cat="' + esc(cat) + '"' +
        ' data-module="' + esc(mod) + '"' +
        ' data-version="' + esc(e.version || "") + '"' +
        ' data-search="' + esc(searchTxt) + '">' +
      '<div class="cl-entry-header">' +
        '<div class="cl-entry-badges">' +
          '<span class="cl-cat-badge ' + catCls + '">' + esc(catLabel) + "</span>" +
          '<span class="cl-mod-badge"><i class="fas fa-puzzle-piece" style="font-size:.6rem;opacity:.6"></i> ' + esc(modLabel) + "</span>" +
        "</div>" +
        '<h4 class="cl-entry-title">' + esc(e.title) + "</h4>" +
        (dateStr ? '<span class="cl-entry-date">' + esc(dateStr) + "</span>" : "") +
      "</div>" +
      (desc ? '<p class="cl-entry-desc">' + esc(desc) + "</p>" : "") +
      benefitHtml +
      usageHtml +
      "</div>"
    );
  }

  // ── Render grouped timeline ──────────────────────────────────────────────────
  function renderTimeline(entries) {
    if (!entries.length) {
      timeline.html('<div class="cl-empty"><i class="fas fa-search" aria-hidden="true"></i><p>No updates match your filters.</p></div>');
      matchCount.text("0 entries");
      return;
    }

    var groups = {};
    entries.forEach(function (e) {
      var v = e.version || "1.0.0";
      if (!groups[v]) groups[v] = [];
      groups[v].push(e);
    });

    var sortedVersions = Object.keys(groups).sort(function (a, b) {
      return b.localeCompare(a, undefined, { numeric: true, sensitivity: "base" });
    });

    var html = "";
    sortedVersions.forEach(function (v) {
      var group     = groups[v];
      var groupDate = group[0] && group[0].date
        ? new Date(group[0].date + "T12:00:00").toLocaleDateString(undefined, { year:"numeric", month:"long", day:"numeric" })
        : "";
      html +=
        '<div class="cl-version-group" data-version="' + esc(v) + '">' +
        '<div class="cl-version-header">' +
          '<span class="cl-version-badge">v' + esc(v) + "</span>" +
          (groupDate ? '<span class="cl-version-date">' + esc(groupDate) + "</span>" : "") +
          '<span class="cl-version-count">' + group.length + " update" + (group.length !== 1 ? "s" : "") + "</span>" +
        "</div>" +
        group.map(buildCard).join("") +
        "</div>";
    });

    timeline.html(html);
    matchCount.text(entries.length === 1 ? "1 entry" : entries.length + " entries");
  }

  // ── Filter ───────────────────────────────────────────────────────────────────
  function applyFilters() {
    var q = currentQ.toLowerCase();
    var filtered = allEntries.filter(function (e) {
      if (currentCat && e.category !== currentCat) return false;
      if (currentMod && e.module   !== currentMod) return false;
      if (currentVer && e.version  !== currentVer) return false;
      if (q) {
        var hay = ((e.title || "") + " " + (e.description || "") + " " + (e.benefit || "") + " " + (e.module || "") + " " + (e.category || "") + " " + (Array.isArray(e.tags) ? e.tags.join(" ") : "")).toLowerCase();
        if (hay.indexOf(q) === -1) return false;
      }
      return true;
    });
    renderTimeline(filtered);
  }

  searchInput.on("input", function () { currentQ = $(this).val(); applyFilters(); });
  modFilter.on("change",  function () { currentMod = $(this).val(); applyFilters(); });
  verFilter.on("change",  function () { currentVer = $(this).val(); applyFilters(); });

  catPills.on("click", "button", function () {
    currentCat = $(this).data("cat") || "";
    catPills.find("button").removeClass("active");
    $(this).addClass("active");
    applyFilters();
  });

  timeline.on("click", ".cl-usage-toggle", function () {
    var $btn   = $(this);
    var $steps = $btn.next(".cl-usage-steps");
    var open   = $steps.hasClass("open");
    $steps.toggleClass("open", !open);
    $btn.toggleClass("open", !open).attr("aria-expanded", !open);
  });

  var raw = dashboard.data("entries");
  if (typeof raw === "string") {
    try { raw = JSON.parse(raw); } catch (e) { raw = []; }
  }
  allEntries = Array.isArray(raw) ? raw : [];
  matchCount.text(allEntries.length === 1 ? "1 entry" : allEntries.length + " entries");
});
