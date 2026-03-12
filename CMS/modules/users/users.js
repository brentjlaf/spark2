// File: users.js
$(function () {
  const state = {
    users: [],
    filtered: [],
    filter: "all",
    view: "grid",
    search: "",
  };

  const $grid = $("#usersGrid");
  const $tableView = $("#usersTableView");
  const $tableBody = $("#usersTableBody");
  const $emptyState = $("#usersEmptyState");
  const $filterButtons = $("[data-users-filter]");
  const $viewButtons = $("[data-users-view]");
  const $searchInput = $("#usersSearchInput");
  const $newBtn = $("#usersNewBtn");
  const $refreshBtn = $("#usersRefreshBtn");
  const $lastSync = $("#usersLastSync");
  const $emptyCta = $("#usersEmptyCta");

  const $drawer = $("#usersDrawer");
  const $form = $("#userForm");
  const $formTitle = $("#userFormTitle");
  const $formSubtitle = $("#userFormSubtitle");
  const $formClose = $("#userFormClose");
  const $formCancel = $("#userFormCancel");
  const $deleteBtn = $("#userDeleteBtn");
  const $userId = $("#userId");
  const $username = $("#username");
  const $password = $("#password");
  const $role = $("#role");
  const $status = $("#status");

  const $statTotal = $("#usersStatTotal");
  const $statActive = $("#usersStatActive");
  const $statAdmins = $("#usersStatAdmins");
  const $statRecent = $("#usersStatRecent");

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function nowLabel() {
    const now = new Date();
    return now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
  }

  function formatRelative(timestamp) {
    if (!timestamp || Number(timestamp) <= 0) {
      return "No activity";
    }
    const ms = Number(timestamp) * 1000;
    const diff = Date.now() - ms;
    if (diff < 0) {
      return "Scheduled";
    }
    const minutes = Math.floor(diff / 60000);
    if (minutes < 1) {
      return "Just now";
    }
    if (minutes < 60) {
      return minutes + " minute" + (minutes === 1 ? "" : "s") + " ago";
    }
    const hours = Math.floor(minutes / 60);
    if (hours < 24) {
      return hours + " hour" + (hours === 1 ? "" : "s") + " ago";
    }
    const days = Math.floor(hours / 24);
    if (days < 30) {
      return days + " day" + (days === 1 ? "" : "s") + " ago";
    }
    const months = Math.floor(days / 30);
    if (months < 12) {
      return months + " month" + (months === 1 ? "" : "s") + " ago";
    }
    const years = Math.floor(months / 12);
    return years + " year" + (years === 1 ? "" : "s") + " ago";
  }

  function roleLabel(role) {
    switch (role) {
      case "admin":
        return "Administrator";
      case "editor":
        return "Editor";
      default:
        return role ? role.charAt(0).toUpperCase() + role.slice(1) : "Editor";
    }
  }

  function statusLabel(status) {
    return status === "inactive" ? "Inactive" : "Active";
  }

  function statusClass(status) {
    return status === "inactive"
      ? "user-status--inactive"
      : "user-status--active";
  }

  function roleClass(role) {
    return role === "admin" ? "user-role--admin" : "user-role--editor";
  }

  function computeRecent(users) {
    const threshold = Math.floor(Date.now() / 1000) - 30 * 24 * 60 * 60;
    return users.filter((user) => Number(user.created_at) > threshold).length;
  }

  function updateStats(users) {
    const total = users.length;
    const active = users.filter((user) => user.status === "active").length;
    const admins = users.filter((user) => user.role === "admin").length;
    const recent = computeRecent(users);

    $statTotal.text(total);
    $statActive.text(active);
    $statAdmins.text(admins);
    $statRecent.text(recent);
  }

  function updateFilterCounts(users) {
    const counts = {
      all: users.length,
      active: users.filter((user) => user.status === "active").length,
      inactive: users.filter((user) => user.status === "inactive").length,
      admin: users.filter((user) => user.role === "admin").length,
      editor: users.filter((user) => user.role === "editor").length,
    };

    $filterButtons.each(function () {
      const $btn = $(this);
      const type = $btn.data("users-filter");
      const $count = $btn.find(".a11y-filter-count");
      if ($count.length && Object.prototype.hasOwnProperty.call(counts, type)) {
        $count.text(counts[type]);
      }
    });
  }

  function matchesFilter(user) {
    switch (state.filter) {
      case "active":
        return user.status === "active";
      case "inactive":
        return user.status === "inactive";
      case "admin":
        return user.role === "admin";
      case "editor":
        return user.role === "editor";
      default:
        return true;
    }
  }

  function matchesSearch(user) {
    if (!state.search) {
      return true;
    }
    const q = state.search;
    const haystack = [
      user.username,
      roleLabel(user.role),
      statusLabel(user.status),
    ]
      .join(" ")
      .toLowerCase();
    return haystack.indexOf(q) !== -1;
  }

  function buildCard(user) {
    const card = [
      '<article class="a11y-page-card users-card" role="listitem" tabindex="0" data-user-id="' +
        user.id +
        '">',
      '<div class="a11y-page-card__header">',
      '<div class="users-status-badge ' +
        statusClass(user.status) +
        '">' +
        statusLabel(user.status) +
        "</div>",
      '<h3 class="a11y-page-title">' + escapeHtml(user.username) + "</h3>",
      '<p class="a11y-page-url">' + roleLabel(user.role) + "</p>",
      "</div>",
      '<div class="a11y-page-card__metrics">',
      '<div><span class="label">Last login</span><span class="value">' +
        escapeHtml(formatRelative(user.last_login)) +
        "</span></div>",
      '<div><span class="label">Member since</span><span class="value">' +
        escapeHtml(formatRelative(user.created_at)) +
        "</span></div>",
      '<div><span class="label">Role</span><span class="value"><span class="users-role-badge ' +
        roleClass(user.role) +
        '">' +
        roleLabel(user.role) +
        "</span></span></div>",
      "</div>",
      '<div class="a11y-page-card__issues">',
      '<div class="users-card-footer">',
      '<span class="users-card-meta">Status: <strong>' +
        statusLabel(user.status) +
        "</strong></span>",
      '<button type="button" class="a11y-btn a11y-btn--secondary" data-users-action="manage" data-user-id="' +
        user.id +
        '">',
      '<i class="fas fa-user-cog" aria-hidden="true"></i><span>Manage access</span>',
      "</button>",
      "</div>",
      "</div>",
      "</article>",
    ];
    return card.join("");
  }

  function buildTableRow(user) {
    const row = [
      '<div class="a11y-table-row" role="row" data-user-id="' + user.id + '">',
      '<div class="a11y-table-cell">',
      '<div class="title">' + escapeHtml(user.username) + "</div>",
      '<div class="subtitle">Member since ' +
        escapeHtml(formatRelative(user.created_at)) +
        "</div>",
      "</div>",
      '<div class="a11y-table-cell"><span class="users-role-badge ' +
        roleClass(user.role) +
        '">' +
        roleLabel(user.role) +
        "</span></div>",
      '<div class="a11y-table-cell"><span class="users-status-badge ' +
        statusClass(user.status) +
        '">' +
        statusLabel(user.status) +
        "</span></div>",
      '<div class="a11y-table-cell">' +
        escapeHtml(formatRelative(user.last_login)) +
        "</div>",
      '<div class="a11y-table-cell actions">',
      '<button type="button" class="a11y-btn a11y-btn--icon" data-users-action="manage" data-user-id="' +
        user.id +
        '">',
      '<i class="fas fa-user-cog" aria-hidden="true"></i>',
      '<span class="sr-only">Manage ' + escapeHtml(user.username) + "</span>",
      "</button>",
      "</div>",
      "</div>",
    ];
    return row.join("");
  }

  function render() {
    if (!state.filtered.length) {
      $grid.empty().attr("hidden", true);
      $tableView.attr("hidden", true);
      $emptyState.attr("hidden", false);
      return;
    }

    $emptyState.attr("hidden", true);

    if (state.view === "grid") {
      $grid.attr("hidden", false);
      $tableView.attr("hidden", true);
      $grid.empty();
      const cards = state.filtered.map(buildCard).join("");
      $grid.html(cards);
    } else {
      $grid.attr("hidden", true).empty();
      $tableView.attr("hidden", false);
      const rows = state.filtered.map(buildTableRow).join("");
      $tableBody.html(rows);
    }
  }

  function applyFilters() {
    state.filtered = state.users.filter(function (user) {
      return matchesFilter(user) && matchesSearch(user);
    });
    render();
  }

  function closeDrawer() {
    $drawer.attr("hidden", true).removeClass("is-visible");
    $form[0].reset();
    $userId.val("");
    $password.val("");
    $deleteBtn.hide().data("userId", "");
  }

  function openDrawer(user) {
    if (user) {
      $formTitle.text("Edit team member");
      $formSubtitle.text(
        "Update permissions, reset passwords, or deactivate an account.",
      );
      $userId.val(user.id);
      $username.val(user.username);
      $password.val("");
      $role.val(user.role);
      $status.val(user.status);
      $deleteBtn.show().data("userId", user.id);
    } else {
      $formTitle.text("Add teammate");
      $formSubtitle.text("Invite a new user or update their access level.");
      $userId.val("");
      $username.val("");
      $password.val("");
      $role.val("editor");
      $status.val("active");
      $deleteBtn.hide().data("userId", "");
    }

    $drawer.attr("hidden", false).addClass("is-visible");
    setTimeout(function () {
      $username.trigger("focus");
    }, 100);
  }

  function findUser(id) {
    id = Number(id);
    return (
      state.users.find(function (user) {
        return Number(user.id) === id;
      }) || null
    );
  }

  function fetchUsers() {
    $.getJSON("modules/users/list_users.php", function (data) {
      state.users = Array.isArray(data) ? data.slice() : [];
      state.users.sort(function (a, b) {
        return String(a.username).localeCompare(String(b.username));
      });
      $lastSync.text(nowLabel());
      updateStats(state.users);
      updateFilterCounts(state.users);
      applyFilters();
    });
  }

  $filterButtons.on("click", function () {
    const $btn = $(this);
    const filter = $btn.data("users-filter");
    if (!filter) {
      return;
    }
    state.filter = filter;
    $filterButtons.removeClass("active");
    $btn.addClass("active");
    applyFilters();
  });

  $viewButtons.on("click", function () {
    const $btn = $(this);
    const view = $btn.data("users-view");
    if (!view || state.view === view) {
      return;
    }
    state.view = view;
    $viewButtons.removeClass("active");
    $btn.addClass("active");
    render();
  });

  $searchInput.on("input", function () {
    state.search = ($(this).val() || "").toLowerCase();
    applyFilters();
  });

  $grid.on("click", '[data-users-action="manage"]', function (event) {
    event.stopPropagation();
    const id = $(this).data("user-id");
    const user = findUser(id);
    openDrawer(user);
  });

  $grid.on("click", ".users-card", function (event) {
    if ($(event.target).closest("[data-users-action]").length) {
      return;
    }
    const id = $(this).data("user-id");
    const user = findUser(id);
    openDrawer(user);
  });

  $grid.on("keydown", ".users-card", function (event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      const id = $(this).data("user-id");
      const user = findUser(id);
      openDrawer(user);
    }
  });

  $tableBody.on("click", '[data-users-action="manage"]', function () {
    const id = $(this).data("user-id");
    const user = findUser(id);
    openDrawer(user);
  });

  $newBtn.on("click", function () {
    openDrawer(null);
  });

  $emptyCta.on("click", function () {
    $newBtn.trigger("click");
  });

  $refreshBtn.on("click", function () {
    fetchUsers();
  });

  $formClose.on("click", closeDrawer);
  $formCancel.on("click", closeDrawer);

  $form.on("submit", function (event) {
    event.preventDefault();
    const payload = $form.serialize();
    $.post("modules/users/save_user.php", payload, function () {
      closeDrawer();
      fetchUsers();
    });
  });

  $deleteBtn.on("click", function () {
    const id = $(this).data("userId");
    if (!id) {
      return;
    }
    confirmModal("Delete this user? This action cannot be undone.").then(
      function (ok) {
        if (!ok) {
          return;
        }
        $.post("modules/users/delete_user.php", { id: id }, function () {
          closeDrawer();
          fetchUsers();
        });
      },
    );
  });

  fetchUsers();
});
