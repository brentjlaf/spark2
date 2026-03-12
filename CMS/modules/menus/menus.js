// File: menus.js
$(function () {
  let pages = [];
  let menuData = Array.isArray(window.menuDashboardInitialData)
    ? window.menuDashboardInitialData
    : [];
  let searchTerm = "";
  let activeFilter = "all";

  const $menuDashboard = $(".menu-dashboard");
  const $menuTable = $("#menuTable");
  const $menuTableBody = $("#menuTableBody");
  const $menuEmptyState = $("#menuEmptyState");
  const $menuNoResults = $("#menuNoResults");
  const $filterButtons = $(".menu-filter-btn");
  const $lastUpdatedLabel = $menuDashboard.find(".js-last-updated");

  function normalizeMenuResponse(response) {
    if (Array.isArray(response)) {
      return { menus: response, lastUpdated: null };
    }

    if (response && typeof response === "object") {
      const menus = Array.isArray(response.menus) ? response.menus : [];
      const lastUpdated =
        typeof response.lastUpdated === "string" && response.lastUpdated.trim()
          ? response.lastUpdated
          : null;

      return { menus, lastUpdated };
    }

    return { menus: [], lastUpdated: null };
  }

  function formatLastUpdated(timestamp) {
    if (!timestamp) {
      return "Not available";
    }

    const date = new Date(timestamp);
    if (Number.isNaN(date.getTime())) {
      return "Not available";
    }

    return new Intl.DateTimeFormat(undefined, {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }).format(date);
  }

  function updateLastUpdatedLabel(timestamp) {
    const formatted = formatLastUpdated(timestamp);
    $lastUpdatedLabel.text(formatted);
    if (timestamp) {
      $menuDashboard.attr("data-last-updated", timestamp);
    } else {
      $menuDashboard.removeAttr("data-last-updated");
    }
  }

  function escapeHtml(value) {
    return $("<div>")
      .text(value == null ? "" : value)
      .html();
  }

  function loadPages(callback) {
    $.getJSON("modules/pages/list_pages.php", function (data) {
      pages = Array.isArray(data) ? data : [];
      if (callback) callback();
    });
  }

  function countItems(items) {
    if (!Array.isArray(items)) {
      return 0;
    }
    let c = 0;
    items.forEach((it) => {
      c++;
      if (Array.isArray(it.children) && it.children.length) {
        c += countItems(it.children);
      }
    });
    return c;
  }

  function hasNested(items) {
    if (!Array.isArray(items)) {
      return false;
    }
    return items.some(
      (it) => Array.isArray(it.children) && it.children.length > 0,
    );
  }

  function maxDepth(items, depth = 1) {
    if (!Array.isArray(items) || !items.length) {
      return depth === 1 ? 0 : depth - 1;
    }
    let max = depth;
    items.forEach((it) => {
      if (Array.isArray(it.children) && it.children.length) {
        max = Math.max(max, maxDepth(it.children, depth + 1));
      }
    });
    return max;
  }

  function collectLabels(items, results) {
    if (!Array.isArray(items)) {
      return results;
    }
    items.forEach((item) => {
      if (item.label) {
        results.push(String(item.label).toLowerCase());
      }
      if (Array.isArray(item.children) && item.children.length) {
        collectLabels(item.children, results);
      }
    });
    return results;
  }

  function buildPreview(items) {
    if (!Array.isArray(items) || !items.length) {
      return "";
    }
    const labels = [];
    items.forEach((item) => {
      if (item.label) {
        labels.push(item.label);
      }
    });
    if (!labels.length) {
      return "";
    }
    const preview = labels
      .slice(0, 3)
      .map((label) => String(label))
      .join(" • ");
    return labels.length > 3 ? preview + "…" : preview;
  }

  function matchesSearch(menu) {
    if (!searchTerm) {
      return true;
    }
    const lowerName = String(menu.name || "").toLowerCase();
    if (lowerName.includes(searchTerm)) {
      return true;
    }
    const labels = collectLabels(menu.items, []);
    return labels.some((label) => label.includes(searchTerm));
  }

  function matchesFilter(menu) {
    if (activeFilter === "nested") {
      return hasNested(menu.items);
    }
    if (activeFilter === "single") {
      return !hasNested(menu.items);
    }
    return true;
  }

  function updateDashboardStats() {
    const totalMenus = menuData.length;
    const totalLinks = menuData.reduce(
      (sum, menu) => sum + countItems(menu.items),
      0,
    );
    const nestedMenus = menuData.reduce(
      (sum, menu) => sum + (hasNested(menu.items) ? 1 : 0),
      0,
    );
    const averageLinks =
      totalMenus > 0 ? Math.round((totalLinks / totalMenus) * 10) / 10 : 0;

    $("#menuStatTotal").text(totalMenus);
    $("#menuStatLinks").text(totalLinks);
    $("#menuStatNested").text(nestedMenus);
    $("#menuStatAverage").text(averageLinks);
  }

  function updateFilterCounts() {
    const counts = {
      all: menuData.length,
      nested: menuData.filter((menu) => hasNested(menu.items)).length,
      single: menuData.filter((menu) => !hasNested(menu.items)).length,
    };

    Object.keys(counts).forEach((key) => {
      $filterButtons
        .filter(`[data-menu-filter="${key}"]`)
        .find(".menu-filter-count")
        .text(counts[key]);
    });
  }

  function renderMenus() {
    const totalMenus = menuData.length;
    const filteredMenus = menuData
      .filter((menu) => matchesFilter(menu) && matchesSearch(menu))
      .sort((a, b) => String(a.name || "").localeCompare(String(b.name || "")));

    if (!totalMenus) {
      $menuTable.hide();
      $menuNoResults.prop("hidden", true);
      $menuEmptyState.prop("hidden", false);
      $menuTableBody.empty();
      return;
    }

    $menuEmptyState.prop("hidden", true);

    if (!filteredMenus.length) {
      $menuTable.hide();
      $menuNoResults.prop("hidden", false);
      $menuTableBody.empty();
      return;
    }

    $menuNoResults.prop("hidden", true);
    $menuTable.show();
    $menuTableBody.empty();

    filteredMenus.forEach((menu) => {
      const itemCount = countItems(menu.items);
      const depth = maxDepth(menu.items);
      const nested = depth > 1;
      const structureLabel = nested ? "Nested menu" : "Single level";
      const structureMeta = nested ? `Depth ${depth}` : "Top-level links";
      const preview = buildPreview(menu.items);

      const $row = $('<div class="menu-table-row"></div>').attr(
        "data-id",
        menu.id,
      );

      const $nameCell = $(
        '<div class="menu-table-cell menu-table-cell--name"></div>',
      );
      $nameCell.append(
        $('<div class="menu-name"></div>').text(menu.name || "Untitled menu"),
      );
      if (preview) {
        $nameCell.append($('<div class="menu-preview"></div>').text(preview));
      }

      const $linksCell = $(
        '<div class="menu-table-cell menu-table-cell--links"></div>',
      );
      $linksCell.append($('<span class="menu-count"></span>').text(itemCount));
      $linksCell.append($('<span class="menu-count-label">Links</span>'));

      const $structureCell = $(
        '<div class="menu-table-cell menu-table-cell--structure"></div>',
      );
      $structureCell.append(
        $('<span class="menu-structure-badge"></span>').text(structureLabel),
      );
      $structureCell.append(
        $('<span class="menu-structure-meta"></span>').text(structureMeta),
      );

      const $actionsCell = $(
        '<div class="menu-table-cell menu-table-actions"></div>',
      );
      const $editBtn = $(
        '<button type="button" class="menu-btn menu-btn--ghost menu-btn--sm editMenu">' +
          '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>' +
          "<span>Edit</span>" +
          "</button>",
      );
      const $deleteBtn = $(
        '<button type="button" class="menu-btn menu-btn--danger menu-btn--sm deleteMenu">' +
          '<i class="fa-solid fa-trash" aria-hidden="true"></i>' +
          "<span>Delete</span>" +
          "</button>",
      );
      $actionsCell.append($editBtn, $deleteBtn);

      $row.append($nameCell, $linksCell, $structureCell, $actionsCell);
      $menuTableBody.append($row);
    });
  }

  function setMenuData(data) {
    menuData = Array.isArray(data) ? data : [];
    updateDashboardStats();
    updateFilterCounts();
    renderMenus();
  }

  function loadMenus(showLoading = true) {
    const $refreshBtn = $("#refreshMenusBtn");
    if (showLoading) {
      $refreshBtn.addClass("is-loading");
    }
    $.getJSON("modules/menus/list_menus.php", function (data) {
      const response = normalizeMenuResponse(data);
      setMenuData(response.menus);
      updateLastUpdatedLabel(response.lastUpdated);
    }).always(function () {
      $refreshBtn.removeClass("is-loading");
    });
  }

  function toggleFields($item) {
    const type = $item.find(".type-select").val();
    if (type === "page") {
      $item.find(".page-select").show();
      $item.find(".link-input").hide();
    } else {
      $item.find(".page-select").hide();
      $item.find(".link-input").show();
    }
  }

  function prepareItem(item) {
    item = item || {};
    if (!item.link && item.slug) {
      item.link = "/" + String(item.slug).replace(/^\/+/, "");
    }
    if (!item.type) {
      if (item.link && item.link.startsWith("/")) {
        item.type = "page";
        const slug = item.link.substring(1);
        const p = pages.find((pg) => pg.slug === slug);
        if (p) item.page = p.id;
      } else {
        item.type = "custom";
      }
    }
    return item;
  }

  function initSortable($list) {
    $list.sortable({
      handle: ".drag-handle",
      connectWith: ".menu-list",
      placeholder: "menu-placeholder",
    });
  }

  function createItemElement(item) {
    item = prepareItem(item);
    const pageOptions = pages
      .map(
        (p) =>
          '<option value="' + p.id + '">' + escapeHtml(p.title) + "</option>",
      )
      .join("");
    const $li = $("<li></li>");
    const $item = $('<div class="menu-item"></div>');
    $item.append(
      '<span class="drag-handle action-icon-button has-tooltip" role="button" tabindex="0" aria-label="Move menu item" data-tooltip="Drag to reorder"><i class="fa-solid fa-up-down-left-right action-icon" aria-hidden="true"></i></span>',
    );
    $item.append(
      '<select class="form-select type-select"><option value="page">Link to Page</option><option value="custom">Custom Link</option></select>',
    );
    $item.append(
      '<select class="form-select page-select">' + pageOptions + "</select>",
    );
    $item.append(
      '<input type="text" class="form-input link-input" placeholder="URL">',
    );
    $item.append(
      '<input type="text" class="form-input label-input" placeholder="Title">',
    );
    $item.append(
      '<label class="menu-item-checkbox"><input type="checkbox" class="new-tab"> <span>New Tab</span></label>',
    );
    $item.append(
      '<button type="button" class="menu-chip-btn addChild"><i class="fas fa-level-down-alt" aria-hidden="true"></i><span>Sub</span></button>',
    );
    $item.append(
      '<button type="button" class="menu-chip-btn menu-chip-btn--danger removeItem"><i class="fas fa-trash" aria-hidden="true"></i><span>Remove</span></button>',
    );
    $li.append($item);
    $li.append('<ul class="menu-list"></ul>');
    $item.find(".type-select").val(item.type);
    if (item.page) $item.find(".page-select").val(item.page);
    $item.find(".link-input").val(item.link || "");
    $item.find(".label-input").val(item.label || "");
    $item.find(".new-tab").prop("checked", !!item.new_tab);
    toggleFields($item);
    return $li;
  }

  function addItem(item, $parent) {
    $parent = $parent || $("#menuItems");
    const $el = createItemElement(item || {});
    $parent.append($el);
    initSortable($el.children("ul"));
    if (item && Array.isArray(item.children)) {
      item.children.forEach((ch) => addItem(ch, $el.children("ul")));
    }
  }

  function gatherItems($list) {
    const items = [];
    $list.children("li").each(function () {
      const $it = $(this).children(".menu-item");
      const item = {
        type: $it.find(".type-select").val(),
        page: parseInt($it.find(".page-select").val(), 10) || null,
        link: $it.find(".link-input").val(),
        label: $it.find(".label-input").val(),
        new_tab: $it.find(".new-tab").is(":checked"),
      };
      const children = gatherItems($(this).children("ul"));
      if (children.length) item.children = children;
      items.push(item);
    });
    return items;
  }

  function resetMenuForm() {
    const form = $("#menuForm")[0];
    if (form) {
      form.reset();
    }
    $("#menuId").val("");
    $("#menuItems").empty();
  }

  function openMenuEditor(title) {
    $("#menuFormTitle").text(title);
    $("#menuFormCard").addClass("is-visible").attr("aria-hidden", "false");
    setTimeout(() => {
      $("#menuName").trigger("focus");
    }, 10);
  }

  function closeMenuEditor() {
    $("#menuFormCard").removeClass("is-visible").attr("aria-hidden", "true");
    resetMenuForm();
  }

  $("#addMenuItem").on("click", function () {
    addItem();
  });

  initSortable($("#menuItems"));

  $("#menuItems").on("click", ".removeItem", function () {
    $(this).closest("li").remove();
  });

  $("#menuItems").on("click", ".addChild", function () {
    const $li = $(this).closest("li");
    addItem({}, $li.children("ul"));
  });

  $("#menuItems").on("change", ".type-select", function () {
    toggleFields($(this).closest(".menu-item"));
  });

  $("#newMenuBtn").on("click", function () {
    resetMenuForm();
    addItem();
    openMenuEditor("Create Menu");
  });

  $("#emptyStateCreateMenu").on("click", function () {
    $("#newMenuBtn").trigger("click");
  });

  $("#menuNoResultsCta").on("click", function () {
    $("#newMenuBtn").trigger("click");
  });

  $("#closeMenuEditor").on("click", function () {
    closeMenuEditor();
  });

  $("#cancelMenuEdit").on("click", function () {
    closeMenuEditor();
  });

  $menuTableBody.on("click", ".editMenu", function () {
    const id = $(this).closest(".menu-table-row").data("id");
    $.getJSON("modules/menus/list_menus.php", function (data) {
      const response = normalizeMenuResponse(data);
      menuData = response.menus;
      const menu = menuData.find((m) => String(m.id) === String(id));
      if (!menu) {
        return;
      }
      resetMenuForm();
      $("#menuId").val(menu.id);
      $("#menuName").val(menu.name || "");
      if (Array.isArray(menu.items) && menu.items.length) {
        menu.items.forEach((it) => addItem(it));
      } else {
        addItem();
      }
      openMenuEditor("Edit Menu");
      setMenuData(menuData);
      updateLastUpdatedLabel(response.lastUpdated);
    });
  });

  $menuTableBody.on("click", ".deleteMenu", function () {
    const row = $(this).closest(".menu-table-row");
    const id = row.data("id");
    confirmModal("Delete this menu?").then((ok) => {
      if (!ok) return;
      $.post("modules/menus/delete_menu.php", { id }, function () {
        loadMenus();
      });
    });
  });

  $("#menuForm").on("submit", function (e) {
    e.preventDefault();
    const data = {
      id: $("#menuId").val(),
      name: $("#menuName").val(),
      items: JSON.stringify(gatherItems($("#menuItems"))),
    };
    $.post("modules/menus/save_menu.php", data, function () {
      closeMenuEditor();
      loadMenus();
    });
  });

  $("#menuSearchInput").on("input", function () {
    searchTerm = $(this).val().trim().toLowerCase();
    renderMenus();
  });

  $filterButtons.on("click", function () {
    const $btn = $(this);
    if ($btn.hasClass("active")) {
      return;
    }
    $filterButtons.removeClass("active");
    $btn.addClass("active");
    activeFilter = $btn.data("menu-filter");
    renderMenus();
  });

  $("#refreshMenusBtn").on("click", function () {
    loadMenus();
  });

  updateLastUpdatedLabel($menuDashboard.attr("data-last-updated"));
  setMenuData(menuData);
  loadPages(function () {
    setMenuData(menuData);
    loadMenus(false);
  });
});
