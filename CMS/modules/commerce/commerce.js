// File: modules/commerce/commerce.js
$(function () {
  var dashboard = $(".commerce-dashboard");
  if (!dashboard.length) return;

  // ── DOM refs ────────────────────────────────────────────────────────────────
  var tableBody     = $("#commerceTableBody");
  var tableWrapper  = $("#commerceTableWrapper");
  var searchInput   = $("#commerceSearch");
  var statusFilter  = $("#commerceStatusFilter");
  var matchCount    = $("#commerceMatchCount");
  var bulkBar       = $("#commerceBulkBar");
  var bulkCount     = $("#commerceBulkCount");
  var selectAll     = $("#commerceSelectAll");
  var addBtn        = $("#commerceAddBtn");
  var refreshBtn    = $("#commerceRefreshBtn");
  var inlineAlert   = $("#commerceInlineAlert");
  var toast         = $("#commerceToast");
  var modalOverlay  = $("#commerceModalOverlay");
  var modalTitle    = $("#commerceModalTitle");
  var modalSave     = $("#commerceModalSave");
  var modalCancel   = $("#commerceModalCancel");
  var modalClose    = $("#commerceModalClose");
  var formError     = $("#commerceFormError");
  var form          = $("#commerceProductForm");

  var CSRF = $('meta[name="csrf-token"]').attr("content") || "";
  var allProducts = [];
  var toastTimer   = null;

  // ── Toast ───────────────────────────────────────────────────────────────────
  function showToast(msg, isError) {
    if (toastTimer) clearTimeout(toastTimer);
    toast
      .text(msg)
      .css("background", isError ? "#dc2626" : "#111827")
      .addClass("show");
    toastTimer = setTimeout(function () {
      toast.removeClass("show");
    }, 3500);
  }

  // ── Stat counters ───────────────────────────────────────────────────────────
  function updateStats(products) {
    var total    = products.length;
    var active   = 0, inactive = 0, draft = 0, inventory = 0;
    products.forEach(function (p) {
      if (p.status === "active")        active++;
      else if (p.status === "inactive") inactive++;
      else                              draft++;
      if (p.track_stock) inventory += parseInt(p.stock, 10) || 0;
    });
    $("#commerceTotalCount").text(total);
    $("#commerceActiveCount").text(active);
    $("#commerceInactiveCount").text(inactive + draft);
    $("#commerceInventoryCount").text(inventory);
  }

  // ── Build a table row from a product object ─────────────────────────────────
  function statusBadge(status) {
    var label = status.charAt(0).toUpperCase() + status.slice(1);
    return (
      '<span class="commerce-status-badge commerce-status-badge--' +
      escHtml(status) +
      '">' + escHtml(label) + "</span>"
    );
  }

  function escHtml(str) {
    return String(str || "")
      .replace(/&/g,  "&amp;")
      .replace(/</g,  "&lt;")
      .replace(/>/g,  "&gt;")
      .replace(/"/g,  "&quot;")
      .replace(/'/g,  "&#39;");
  }

  function buildRow(p, isAdmin) {
    var thumb = p.image
      ? '<img class="commerce-product-thumb" src="' + escHtml(p.image) + '" alt="" loading="lazy">'
      : '<div class="commerce-product-thumb-placeholder"><i class="fas fa-box" aria-hidden="true"></i></div>';
    var sku  = p.sku  ? '<div class="commerce-product-sku">SKU: ' + escHtml(p.sku) + "</div>" : "";
    var price = "$" + parseFloat(p.price || 0).toFixed(2);
    var stock = p.track_stock ? parseInt(p.stock, 10) || 0 : "—";
    var cat   = p.category ? escHtml(p.category) : "—";
    var deleteBtn = isAdmin
      ? '<button class="commerce-btn commerce-btn--sm commerce-btn--danger" data-delete="' +
          p.id + '" data-name="' + escHtml(p.name) +
          '" title="Delete product"><i class="fas fa-trash" aria-hidden="true"></i></button>'
      : "";
    var pJson = escHtml(JSON.stringify(p));
    var searchText = ((p.name || "") + " " + (p.sku || "") + " " + (p.category || "")).toLowerCase();

    return (
      '<tr class="commerce-product-row" data-id="' + p.id +
        '" data-status="' + escHtml(p.status || "active") +
        '" data-search="' + escHtml(searchText) + '">' +
      '<td class="col-cb"><input type="checkbox" class="commerce-row-cb" value="' + p.id + '" aria-label="Select"></td>' +
      "<td>" + thumb + "</td>" +
      '<td><div class="commerce-product-name">' + escHtml(p.name) + "</div>" + sku + "</td>" +
      "<td>" + cat + "</td>" +
      '<td class="col-price">' + price + "</td>" +
      '<td class="col-stock">' + stock + "</td>" +
      "<td>" + statusBadge(p.status || "active") + "</td>" +
      '<td><div class="commerce-row-actions">' +
        '<button class="commerce-btn commerce-btn--sm" style="background:#f3f4f6;color:#374151;border:1px solid #e5e7eb;" data-edit="' +
          pJson + '" title="Edit product"><i class="fas fa-pen" aria-hidden="true"></i></button>' +
        deleteBtn +
      "</div></td>" +
      "</tr>"
    );
  }

  // ── Render the table from allProducts ──────────────────────────────────────
  var _isAdmin = !!$("[data-delete]").length || !!$("#commerceBulkDelete").length;

  function renderTable(products) {
    updateStats(products);
    if (!products.length) {
      tableWrapper.html(
        '<div class="commerce-empty" id="commerceEmpty">' +
        '<i class="fas fa-store" aria-hidden="true"></i>' +
        '<p>No products yet.</p>' +
        '<p class="commerce-empty-hint">Click <strong>Add Product</strong> to create your first listing.</p>' +
        '</div>'
      );
      matchCount.text("0 products");
      return;
    }

    var rows = products.map(function (p) { return buildRow(p, _isAdmin); }).join("");
    tableWrapper.html(
      '<table class="commerce-table" id="commerceTable">' +
      "<thead><tr>" +
      '<th class="col-cb"><input type="checkbox" id="commerceSelectAll" aria-label="Select all"></th>' +
      '<th class="col-img"></th>' +
      "<th>Product</th><th>Category</th>" +
      '<th class="col-price">Price</th><th class="col-stock">Stock</th>' +
      "<th>Status</th><th></th>" +
      "</tr></thead>" +
      "<tbody id='commerceTableBody'>" + rows + "</tbody></table>"
    );
    selectAll = $("#commerceSelectAll");
    tableBody = $("#commerceTableBody");
    bindRowEvents();
    applyFilters();
  }

  // ── Filter ─────────────────────────────────────────────────────────────────
  function applyFilters() {
    var q    = searchInput.val().toLowerCase().trim();
    var stat = statusFilter.val();
    var visible = 0;

    $("#commerceTableBody .commerce-product-row").each(function () {
      var $row    = $(this);
      var search  = ($row.data("search") || "").toLowerCase();
      var rowStat = ($row.data("status") || "").toLowerCase();
      var show    = true;
      if (q    && search.indexOf(q)    === -1) show = false;
      if (stat && rowStat !== stat)            show = false;
      $row.toggle(show);
      if (show) visible++;
    });
    matchCount.text(visible === 1 ? "1 product" : visible + " products");
    updateBulkBar();
  }

  // ── Bulk selection ─────────────────────────────────────────────────────────
  function selectedIds() {
    var ids = [];
    $("#commerceTableBody .commerce-row-cb:checked").each(function () {
      ids.push(parseInt($(this).val(), 10));
    });
    return ids;
  }

  function updateBulkBar() {
    var ids = selectedIds();
    if (ids.length) {
      bulkBar.addClass("is-visible");
      bulkCount.text(ids.length + " selected");
    } else {
      bulkBar.removeClass("is-visible");
    }
  }

  function bindRowEvents() {
    // Select all
    $(document).off("change", "#commerceSelectAll").on("change", "#commerceSelectAll", function () {
      var checked = $(this).prop("checked");
      $("#commerceTableBody .commerce-product-row:visible .commerce-row-cb").prop("checked", checked);
      updateBulkBar();
    });
    // Individual checkbox
    $(document).off("change", ".commerce-row-cb").on("change", ".commerce-row-cb", function () {
      var allVisible = $("#commerceTableBody .commerce-product-row:visible .commerce-row-cb");
      var checked    = allVisible.filter(":checked");
      $("#commerceSelectAll").prop({
        checked:       checked.length === allVisible.length && allVisible.length > 0,
        indeterminate: checked.length > 0 && checked.length < allVisible.length,
      });
      $(".commerce-product-row").toggleClass("is-selected", false);
      $(".commerce-row-cb:checked").closest("tr").addClass("is-selected");
      updateBulkBar();
    });
    // Edit button
    $(document).off("click", "[data-edit]").on("click", "[data-edit]", function () {
      var raw = $(this).attr("data-edit");
      var p;
      try { p = JSON.parse(raw); } catch (e) { return; }
      openModal(p);
    });
    // Delete button
    $(document).off("click", "[data-delete]").on("click", "[data-delete]", function () {
      var id   = parseInt($(this).data("delete"), 10);
      var name = $(this).data("name") || "this product";
      if (!confirm("Delete "" + name + ""? This cannot be undone.")) return;
      doDelete(id);
    });
  }

  // ── Modal ──────────────────────────────────────────────────────────────────
  function openModal(product) {
    formError.hide().text("");
    form[0].reset();
    if (product && product.id) {
      modalTitle.text("Edit Product");
      $("#productId").val(product.id);
      $("#productName").val(product.name || "");
      $("#productSku").val(product.sku || "");
      $("#productSlug").val(product.slug || "");
      $("#productPrice").val(product.price || "");
      $("#productComparePrice").val(product.compare_price || "");
      $("#productCost").val(product.cost || "");
      $("#productWeight").val(product.weight || "");
      $("#productStock").val(product.stock || 0);
      $("#productTrackStock").prop("checked", !!product.track_stock);
      $("#productStatus").val(product.status || "active");
      $("#productCategory").val(product.category || "");
      $("#productTags").val(product.tags || "");
      $("#productImage").val(product.image || "");
      $("#productDescription").val(product.description || "");
    } else {
      modalTitle.text("Add Product");
      $("#productId").val("");
      $("#productStatus").val("active");
    }
    modalOverlay.addClass("is-open");
    setTimeout(function () { $("#productName").trigger("focus"); }, 50);
  }

  function closeModal() {
    modalOverlay.removeClass("is-open");
  }

  addBtn.on("click", function () { openModal(null); });
  modalClose.on("click", closeModal);
  modalCancel.on("click", closeModal);
  modalOverlay.on("click", function (e) {
    if ($(e.target).is(modalOverlay)) closeModal();
  });
  $(document).on("keydown", function (e) {
    if (e.key === "Escape") closeModal();
  });

  // ── Save ───────────────────────────────────────────────────────────────────
  modalSave.on("click", function () {
    var name = $.trim($("#productName").val());
    if (!name) {
      formError.text("Product name is required.").show();
      $("#productName").trigger("focus");
      return;
    }
    formError.hide();

    var payload = {
      id:            parseInt($("#productId").val(), 10) || undefined,
      name:          name,
      sku:           $.trim($("#productSku").val()),
      slug:          $.trim($("#productSlug").val()),
      price:         parseFloat($("#productPrice").val()) || 0,
      compare_price: parseFloat($("#productComparePrice").val()) || 0,
      cost:          parseFloat($("#productCost").val()) || 0,
      weight:        parseFloat($("#productWeight").val()) || 0,
      stock:         parseInt($("#productStock").val(), 10) || 0,
      track_stock:   $("#productTrackStock").is(":checked") ? 1 : 0,
      status:        $("#productStatus").val(),
      category:      $.trim($("#productCategory").val()),
      tags:          $.trim($("#productTags").val()),
      image:         $.trim($("#productImage").val()),
      description:   $.trim($("#productDescription").val()),
    };
    if (!payload.id) delete payload.id;

    modalSave.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Saving…');

    $.ajax({
      url:         "modules/commerce/save_product.php",
      method:      "POST",
      contentType: "application/json",
      data:        JSON.stringify(payload),
      headers:     { "X-CSRF-Token": CSRF },
    })
      .done(function (res) {
        if (res.error) {
          formError.text(res.error).show();
          return;
        }
        // Upsert into allProducts
        var found = false;
        for (var i = 0; i < allProducts.length; i++) {
          if (allProducts[i].id === res.id) {
            allProducts[i] = res;
            found = true;
            break;
          }
        }
        if (!found) allProducts.push(res);
        renderTable(allProducts);
        closeModal();
        showToast(found ? "Product updated." : "Product created.");
      })
      .fail(function (xhr) {
        var msg = "Failed to save product.";
        try { msg = JSON.parse(xhr.responseText).error || msg; } catch (e) {}
        formError.text(msg).show();
      })
      .always(function () {
        modalSave.prop("disabled", false).html('<i class="fas fa-save"></i> Save Product');
      });
  });

  // ── Delete ─────────────────────────────────────────────────────────────────
  function doDelete(id) {
    $.ajax({
      url:         "modules/commerce/delete_product.php",
      method:      "POST",
      contentType: "application/json",
      data:        JSON.stringify({ id: id }),
      headers:     { "X-CSRF-Token": CSRF },
    })
      .done(function (res) {
        if (res.error) { showToast(res.error, true); return; }
        allProducts = allProducts.filter(function (p) { return p.id !== id; });
        renderTable(allProducts);
        showToast("Product deleted.");
      })
      .fail(function () { showToast("Failed to delete product.", true); });
  }

  // ── Bulk actions ────────────────────────────────────────────────────────────
  function doBulk(action) {
    var ids = selectedIds();
    if (!ids.length) return;
    if (action === "delete" && !confirm("Delete " + ids.length + " product(s)? This cannot be undone.")) return;

    $.ajax({
      url:         "modules/commerce/bulk_action.php",
      method:      "POST",
      contentType: "application/json",
      data:        JSON.stringify({ action: action, ids: ids }),
      headers:     { "X-CSRF-Token": CSRF },
    })
      .done(function (res) {
        if (res.status !== "success") { showToast(res.message || "Error.", true); return; }
        // Refresh from server
        loadProducts();
        showToast(res.affected + " product(s) " + action + "d.");
      })
      .fail(function () { showToast("Bulk action failed.", true); });
  }

  $("#commerceBulkActivate").on("click",   function () { doBulk("activate");   });
  $("#commerceBulkDeactivate").on("click", function () { doBulk("deactivate"); });
  $("#commerceBulkDelete").on("click",     function () { doBulk("delete");     });

  // ── Refresh ─────────────────────────────────────────────────────────────────
  function loadProducts() {
    refreshBtn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.getJSON("modules/commerce/list_products.php")
      .done(function (data) {
        allProducts = Array.isArray(data) ? data : [];
        renderTable(allProducts);
      })
      .fail(function () {
        inlineAlert.text("Unable to load products.").show();
      })
      .always(function () {
        refreshBtn.prop("disabled", false).html('<i class="fas fa-rotate"></i> Refresh');
      });
  }

  refreshBtn.on("click", loadProducts);

  // ── Filter events ───────────────────────────────────────────────────────────
  searchInput.on("input", applyFilters);
  statusFilter.on("change", applyFilters);

  // ── Boot ────────────────────────────────────────────────────────────────────
  var raw = dashboard.data("products");
  if (typeof raw === "string") {
    try { raw = JSON.parse(raw); } catch (e) { raw = []; }
  }
  allProducts = Array.isArray(raw) ? raw : [];
  updateStats(allProducts);
  bindRowEvents();
  applyFilters();
  // If table was server-rendered, no re-render needed; just keep stats in sync.
  // Re-render only when data is loaded fresh via loadProducts().
});
