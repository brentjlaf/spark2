// File: modules/changelog/changelog.js
$(function () {
  var dashboard = $(".cl-dashboard");
  if (!dashboard.length) return;

  var isAdmin      = dashboard.data("is-admin") === 1 || dashboard.data("is-admin") === "1";
  var CSRF         = $('meta[name="csrf-token"]').attr("content") || "";
  var allEntries   = [];
  var currentCat   = "";
  var currentMod   = "";
  var currentVer   = "";
  var currentQ     = "";
  var toastTimer   = null;

  // ── DOM refs ─────────────────────────────────────────────────────────────────
  var timeline     = $("#clTimeline");
  var searchInput  = $("#clSearch");
  var modFilter    = $("#clModuleFilter");
  var verFilter    = $("#clVersionFilter");
  var matchCount   = $("#clMatchCount");
  var catPills     = $("#clCatPills");
  var addBtn       = $("#clAddBtn");
  var modalOverlay = $("#clModalOverlay");
  var modalTitle   = $("#clModalTitle");
  var modalSave    = $("#clModalSave");
  var modalCancel  = $("#clModalCancel");
  var modalClose   = $("#clModalClose");
  var formError    = $("#clFormError");
  var stepsList    = $("#clStepsList");
  var addStepBtn   = $("#clAddStep");
  var toast        = $("#clToast");

  var CAT_LABEL = { feature: "Feature", improvement: "Improvement", fix: "Fix", security: "Security" };
  var CAT_CLASS = { feature: "cl-cat-badge--feature", improvement: "cl-cat-badge--improvement", fix: "cl-cat-badge--fix", security: "cl-cat-badge--security" };
  var MOD_LABEL = {
    pages:"Pages", blogs:"Blogs", media:"Media Library", menus:"Menus", forms:"Forms",
    events:"Events", maps:"Maps", users:"Users", settings:"Settings", analytics:"Analytics",
    seo:"SEO", sitemap:"Sitemap", speed:"Performance", accessibility:"Accessibility",
    search:"Search", logs:"Logs", commerce:"Commerce", dashboard:"Dashboard", system:"System",
  };

  // ── Toast ────────────────────────────────────────────────────────────────────
  function showToast(msg, isError) {
    if (toastTimer) clearTimeout(toastTimer);
    toast.text(msg).css("background", isError ? "#dc2626" : "#111827").addClass("show");
    toastTimer = setTimeout(function () { toast.removeClass("show"); }, 3500);
  }

  // ── Escape HTML ──────────────────────────────────────────────────────────────
  function esc(str) {
    return String(str || "")
      .replace(/&/g,  "&amp;")
      .replace(/</g,  "&lt;")
      .replace(/>/g,  "&gt;")
      .replace(/"/g,  "&quot;")
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

    var actionsHtml = "";
    if (isAdmin) {
      var eJson = esc(JSON.stringify(e));
      actionsHtml =
        '<div class="cl-entry-actions">' +
        '<button class="cl-btn cl-btn--sm cl-btn--edit" data-edit="' + eJson + '" title="Edit entry"><i class="fas fa-pen"></i></button>' +
        '<button class="cl-btn cl-btn--sm cl-btn--danger" data-delete="' + e.id + '" data-name="' + esc(e.title) + '" title="Delete"><i class="fas fa-trash"></i></button>' +
        '</div>';
    }

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
        actionsHtml +
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
      timeline.html(
        '<div class="cl-empty"><i class="fas fa-rocket" aria-hidden="true"></i>' +
        '<p>No entries match the current filters.</p></div>'
      );
      matchCount.text("0 entries");
      return;
    }

    // Group by version
    var groups = {};
    entries.forEach(function (e) {
      var v = e.version || "1.0.0";
      if (!groups[v]) groups[v] = [];
      groups[v].push(e);
    });

    // Sort versions desc
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

  // ── Filter events ─────────────────────────────────────────────────────────────
  searchInput.on("input", function () { currentQ = $(this).val(); applyFilters(); });
  modFilter.on("change",  function () { currentMod = $(this).val(); applyFilters(); });
  verFilter.on("change",  function () { currentVer = $(this).val(); applyFilters(); });

  catPills.on("click", "button", function () {
    currentCat = $(this).data("cat") || "";
    catPills.find("button").removeClass("active");
    $(this).addClass("active");
    applyFilters();
  });

  // ── Usage toggle (delegated) ─────────────────────────────────────────────────
  timeline.on("click", ".cl-usage-toggle", function () {
    var $btn   = $(this);
    var $steps = $btn.next(".cl-usage-steps");
    var open   = $steps.hasClass("open");
    $steps.toggleClass("open", !open);
    $btn.toggleClass("open", !open).attr("aria-expanded", !open);
  });

  // ── Edit / Delete delegated events ───────────────────────────────────────────
  timeline.on("click", "[data-edit]", function () {
    var raw = $(this).attr("data-edit");
    var e;
    try { e = JSON.parse(raw); } catch (err) { return; }
    openModal(e);
  });

  timeline.on("click", "[data-delete]", function () {
    var id   = parseInt($(this).data("delete"), 10);
    var name = $(this).data("name") || "this entry";
    if (!confirm("Delete "" + name + ""? This cannot be undone.")) return;
    doDelete(id);
  });

  // ── Modal ────────────────────────────────────────────────────────────────────
  function addStepRow(value) {
    var li = $('<li class="cl-step-item"></li>');
    var input = $('<input type="text" class="cl-step-input" placeholder="Step description…">').val(value || "");
    var remove = $('<button type="button" class="cl-step-remove" title="Remove step"><i class="fas fa-times"></i></button>');
    remove.on("click", function () { li.remove(); });
    li.append(input).append(remove);
    stepsList.append(li);
    return input;
  }

  function openModal(entry) {
    formError.hide().text("");
    $("#clForm")[0].reset();
    stepsList.empty();

    if (entry && entry.id) {
      modalTitle.text("Edit Entry");
      $("#clEntryId").val(entry.id);
      $("#clTitle").val(entry.title || "");
      $("#clVersion").val(entry.version || "");
      $("#clDate").val(entry.date || "");
      $("#clCategory").val(entry.category || "feature");
      $("#clModule").val(entry.module || "system");
      $("#clDescription").val(entry.description || "");
      $("#clBenefit").val(entry.benefit || "");
      $("#clTags").val(Array.isArray(entry.tags) ? entry.tags.join(", ") : (entry.tags || ""));
      var usage = Array.isArray(entry.usage) ? entry.usage : [];
      usage.forEach(function (s) { addStepRow(s); });
    } else {
      modalTitle.text("Add Changelog Entry");
      $("#clEntryId").val("");
      $("#clDate").val(new Date().toISOString().slice(0, 10));
      $("#clCategory").val("feature");
      $("#clModule").val("system");
    }

    modalOverlay.addClass("is-open");
    setTimeout(function () { $("#clTitle").trigger("focus"); }, 50);
  }

  function closeModal() { modalOverlay.removeClass("is-open"); }

  if (addBtn.length) addBtn.on("click", function () { openModal(null); });
  modalClose.on("click",  closeModal);
  modalCancel.on("click", closeModal);
  modalOverlay.on("click", function (e) { if ($(e.target).is(modalOverlay)) closeModal(); });
  $(document).on("keydown", function (e) { if (e.key === "Escape") closeModal(); });

  addStepBtn.on("click", function () {
    var $input = addStepRow("");
    $input.trigger("focus");
  });

  // ── Save ──────────────────────────────────────────────────────────────────────
  modalSave.on("click", function () {
    var title = $.trim($("#clTitle").val());
    var ver   = $.trim($("#clVersion").val());
    if (!title) { formError.text("Title is required.").show(); return; }
    if (!ver)   { formError.text("Version is required.").show(); return; }
    formError.hide();

    var usage = [];
    stepsList.find(".cl-step-input").each(function () {
      var v = $.trim($(this).val());
      if (v) usage.push(v);
    });

    var payload = {
      id:          parseInt($("#clEntryId").val(), 10) || undefined,
      title:       title,
      version:     ver,
      date:        $("#clDate").val(),
      category:    $("#clCategory").val(),
      module:      $("#clModule").val(),
      description: $.trim($("#clDescription").val()),
      benefit:     $.trim($("#clBenefit").val()),
      usage:       usage,
      tags:        $.trim($("#clTags").val()),
    };
    if (!payload.id) delete payload.id;

    modalSave.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');

    $.ajax({
      url:         "modules/changelog/save_entry.php",
      method:      "POST",
      contentType: "application/json",
      data:        JSON.stringify(payload),
      headers:     { "X-CSRF-Token": CSRF },
    })
      .done(function (res) {
        if (res && res.error) { formError.text(res.error).show(); return; }
        // Reload entries via fresh fetch
        loadEntries();
        closeModal();
        showToast("Entry saved.");
      })
      .fail(function () { formError.text("Failed to save entry. Please try again.").show(); })
      .always(function () { modalSave.prop("disabled", false).html('<i class="fas fa-save"></i> Save Entry'); });
  });

  // ── Delete ────────────────────────────────────────────────────────────────────
  function doDelete(id) {
    $.ajax({
      url:         "modules/changelog/delete_entry.php",
      method:      "POST",
      contentType: "application/json",
      data:        JSON.stringify({ id: id }),
      headers:     { "X-CSRF-Token": CSRF },
    })
      .done(function (res) {
        if (res && res.error) { showToast(res.error, true); return; }
        allEntries = allEntries.filter(function (e) { return e.id !== id; });
        applyFilters();
        showToast("Entry deleted.");
      })
      .fail(function () { showToast("Failed to delete entry.", true); });
  }

  // ── Load / refresh ────────────────────────────────────────────────────────────
  function loadEntries() {
    $.getJSON("modules/changelog/list_entries.php")
      .done(function (data) {
        allEntries = Array.isArray(data) ? data : [];
        applyFilters();
      })
      .fail(function () { showToast("Unable to refresh entries.", true); });
  }

  // ── Boot ──────────────────────────────────────────────────────────────────────
  var raw = dashboard.data("entries");
  if (typeof raw === "string") {
    try { raw = JSON.parse(raw); } catch (e) { raw = []; }
  }
  allEntries = Array.isArray(raw) ? raw : [];
  // Initial render is done server-side; JS only takes over after a filter/save.
  // Wire up filter listeners — don't re-render unless user interacts.
  matchCount.text(allEntries.length === 1 ? "1 entry" : allEntries.length + " entries");
});
