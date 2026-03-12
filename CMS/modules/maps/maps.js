// File: modules/maps/maps.js
(function ($, window, document) {
  "use strict";

  var API_URL = "modules/maps/api.php";
  var root = $("#mapsModule");
  if (!root.length) {
    return;
  }

  var state = {
    locations: [],
    categories: [],
    filters: {
      search: "",
      category: "",
      status: "",
    },
    viewMode: "list",
  };
  var STATUS_BADGE_MAP = {
    draft: { label: "Draft", className: "status-draft" },
    published: { label: "Published", className: "status-published" },
  };
  var STATUS_BADGE_CLASSES = Object.keys(STATUS_BADGE_MAP)
    .map(function (key) {
      return STATUS_BADGE_MAP[key].className;
    })
    .join(" ");

  function getStatusBadgeInfo(status) {
    var key = String(status || "").toLowerCase();
    return STATUS_BADGE_MAP[key] || STATUS_BADGE_MAP.draft;
  }

  function applyStatusBadge($badge, status) {
    if (!$badge || !$badge.length) {
      return;
    }
    var info = getStatusBadgeInfo(status);
    $badge.removeClass(STATUS_BADGE_CLASSES).addClass(info.className);
    $badge.text(info.label);
    $badge.attr("aria-label", "Status: " + info.label);
  }

  function updateLocationModalStatusBadge(status) {
    applyStatusBadge(root.find("[data-map-status-badge]"), status);
  }

  var geocodeTimer = null;
  var geocodeRequest = null;
  var geocodePendingQuery = "";
  var geocodeLastSuccessfulQuery = "";
  var coordinatesAutoFilled = false;
  var coordinatesEditedManually = false;
  var geocodeStatusEl = root.find("#mapLocationGeocodeStatus");
  var geocodeDefaultStatus = "";
  var leafletDeferred = null;
  var mapInstance = null;
  var mapMarkers = [];
  var mapActivePopup = null;
  var mapPendingBounds = null;
  if (geocodeStatusEl.length) {
    geocodeDefaultStatus =
      geocodeStatusEl.attr("data-map-geocode-default") ||
      geocodeStatusEl.text().trim();
    if (geocodeDefaultStatus === "") {
      geocodeDefaultStatus =
        "Latitude and longitude update automatically from the address.";
    }
    setGeocodeStatus(geocodeDefaultStatus);
  }

  var initialData = root.attr("data-initial");
  if (initialData) {
    try {
      initialData = JSON.parse(initialData);
    } catch (error) {
      initialData = {};
    }
  } else {
    initialData = {};
  }

  bootstrap(initialData);
  bindEvents();
  render();

  function bootstrap(initial) {
    var locations = Array.isArray(initial.locations) ? initial.locations : [];
    var categories = Array.isArray(initial.categories)
      ? initial.categories
      : [];
    var counts = computeCategoryCounts(locations);
    state.categories = categories.map(function (category) {
      return normalizeCategory(category, counts[category.id] || 0, false);
    });
    state.categories.push(
      normalizeCategory(
        {
          id: "uncategorized",
          name: "Uncategorized",
          color: "#9CA3AF",
          icon: "fa-circle-exclamation",
          is_default: true,
        },
        counts.uncategorized || 0,
        true,
      ),
    );
    setLocations(locations);
  }

  function bindEvents() {
    root.on("input", "#mapLocationSearch", function () {
      state.filters.search = $(this).val().toLowerCase();
      render();
    });

    root.on("change", "#mapCategoryFilter", function () {
      state.filters.category = $(this).val();
      render();
    });

    root.on("change", "#mapStatusFilter", function () {
      state.filters.status = $(this).val();
      render();
    });

    root.on("change", "#mapLocationStatus", function () {
      updateLocationModalStatusBadge($(this).val() || "draft");
    });

    root.on("click", "[data-map-view]", function () {
      var view = $(this).attr("data-map-view");
      if (!view) {
        return;
      }
      if (view === state.viewMode) {
        return;
      }
      state.viewMode = view === "map" ? "map" : "list";
      render();
    });

    root.on(
      "input",
      "#mapLocationStreet, #mapLocationCity, #mapLocationRegion, #mapLocationPostal, #mapLocationCountry",
      function () {
        scheduleGeocodeLookup();
      },
    );

    root.on("input", "#mapLocationLat, #mapLocationLng", function () {
      if (coordinatesAreEmpty()) {
        coordinatesEditedManually = false;
        coordinatesAutoFilled = false;
        setGeocodeStatus(geocodeDefaultStatus);
        return;
      }
      coordinatesEditedManually = true;
      coordinatesAutoFilled = false;
    });

    root.on(
      "click",
      "#mapAddLocationBtn, #mapEmptyAddBtn, #mapNoResultsAddBtn, #mapMapEmptyAddBtn",
      function () {
        openLocationModal();
      },
    );

    root.on("click", "#mapRefreshBtn", function () {
      refreshAll();
    });

    root.on("click", "[data-map-dismiss]", function () {
      var targetId = $(this).attr("data-map-dismiss");
      closeModal($("#" + targetId));
    });

    root.on("click", ".maps-modal", function (event) {
      if (event.target === this) {
        closeModal($(this));
      }
    });

    $(document).on("keydown.mapsModal", function (event) {
      if (event.key === "Escape") {
        closeModal(root.find(".maps-modal:not([hidden])"));
      }
    });

    root.on("click", "[data-map-edit-location]", function () {
      var id = $(this).attr("data-map-edit-location");
      if (!id) {
        return;
      }
      fetchLocation(id).done(function (response) {
        if (response && response.location) {
          openLocationModal(response.location);
        }
      });
    });

    root.on("click", "#mapLocationDeleteBtn", function () {
      var id = $("#mapLocationId").val();
      if (!id) {
        closeModal($("#mapLocationModal"));
        return;
      }
      if (
        !window.confirm("Delete this location? This action cannot be undone.")
      ) {
        return;
      }
      apiRequest("delete_location", { id: id })
        .done(function (response) {
          if (response && Array.isArray(response.locations)) {
            setLocations(response.locations);
            refreshCategories();
            render();
            notify("Location deleted.", "success");
            closeModal($("#mapLocationModal"));
          }
        })
        .fail(handleAjaxError);
    });

    root.on("submit", "#mapLocationForm", function (event) {
      event.preventDefault();
      var payload = gatherLocationForm();
      apiRequest("save_location", payload)
        .done(function (response) {
          if (response && Array.isArray(response.locations)) {
            setLocations(response.locations);
            refreshCategories();
            render();
            closeModal($("#mapLocationModal"));
            notify("Location saved.", "success");
          }
        })
        .fail(handleAjaxError);
    });

    root.on("click", "#mapAddCategoryBtn", function () {
      openCategoryModal();
    });

    root.on("click", "[data-map-edit-category]", function () {
      var id = $(this).attr("data-map-edit-category");
      var category = findCategory(id);
      if (category && !category.is_virtual) {
        openCategoryModal(category);
      }
    });

    root.on("click", "[data-map-delete-category]", function () {
      var id = $(this).attr("data-map-delete-category");
      var category = findCategory(id);
      if (!category || category.is_virtual) {
        return;
      }
      if (!window.confirm('Delete the "' + category.name + '" category?')) {
        return;
      }
      apiRequest("delete_category", { id: id })
        .done(function (response) {
          if (response && Array.isArray(response.categories)) {
            updateCategories(response.categories);
            updateCategoryFilter();
            render();
            notify("Category deleted.", "success");
          }
        })
        .fail(handleAjaxError);
    });

    root.on("submit", "#mapCategoryForm", function (event) {
      event.preventDefault();
      var payload = gatherCategoryForm();
      apiRequest("save_category", payload)
        .done(function (response) {
          if (response && Array.isArray(response.categories)) {
            updateCategories(response.categories);
            updateCategoryFilter();
            render();
            closeModal($("#mapCategoryModal"));
            notify("Category saved.", "success");
          }
        })
        .fail(handleAjaxError);
    });
  }

  function normalizeLocationSummary(categories, location) {
    categories = Array.isArray(categories) ? categories : [];
    location = location || {};
    var lookup = {};
    categories.forEach(function (category) {
      if (!category || !category.id) {
        return;
      }
      lookup[category.id] = category;
    });
    var locationCategories = [];
    if (
      location &&
      Array.isArray(location.categories) &&
      location.categories.length
    ) {
      location.categories.forEach(function (category) {
        if (!category || !category.id) {
          return;
        }
        locationCategories.push({
          id: category.id,
          name: category.name || "Category",
          color: category.color || "#666666",
          icon: category.icon || "fa-location-dot",
        });
      });
    } else if (location && Array.isArray(location.category_ids)) {
      location.category_ids.forEach(function (id) {
        if (lookup[id]) {
          locationCategories.push({
            id: id,
            name: lookup[id].name || "Category",
            color: lookup[id].color || "#666666",
            icon: lookup[id].icon || "fa-location-dot",
          });
        }
      });
    }

    var address = {
      street: "",
      city: "",
      region: "",
      postal_code: "",
      country: "",
    };
    if (location.address && typeof location.address === "object") {
      address.street = location.address.street || "";
      address.city = location.address.city || "";
      address.region = location.address.region || "";
      address.postal_code = location.address.postal_code || "";
      address.country = location.address.country || "";
    }

    var coordinates = { lat: "", lng: "" };
    if (location.coordinates && typeof location.coordinates === "object") {
      if (
        typeof location.coordinates.lat !== "undefined" &&
        location.coordinates.lat !== null
      ) {
        coordinates.lat = location.coordinates.lat;
      }
      if (
        typeof location.coordinates.lng !== "undefined" &&
        location.coordinates.lng !== null
      ) {
        coordinates.lng = location.coordinates.lng;
      }
    }

    return {
      id: location.id,
      name: location.name || "Untitled location",
      slug: location.slug || "",
      status: location.status || "draft",
      city: address.city,
      region: address.region,
      address: address,
      coordinates: coordinates,
      description: location.description || "",
      updated_at: location.updated_at || location.created_at || "",
      categories: locationCategories,
    };
  }

  function computeCategoryCounts(locations) {
    var counts = {};
    if (!Array.isArray(locations)) {
      return counts;
    }
    locations.forEach(function (location) {
      var assigned = false;
      if (location && Array.isArray(location.category_ids)) {
        location.category_ids.forEach(function (id) {
          if (!id) {
            return;
          }
          assigned = true;
          counts[id] = (counts[id] || 0) + 1;
        });
      }
      if (!assigned) {
        counts.uncategorized = (counts.uncategorized || 0) + 1;
      }
    });
    return counts;
  }

  function normalizeCategory(category, count, isVirtual) {
    category = category || {};
    return {
      id: category.id || "",
      name: category.name || "Category",
      slug: category.slug || "",
      color: category.color || "#666666",
      icon: category.icon || "fa-location-dot",
      sort_order:
        typeof category.sort_order === "number" ? category.sort_order : 0,
      is_default: !!category.is_default,
      count: count || 0,
      is_virtual: !!isVirtual,
    };
  }

  function setLocations(locations) {
    var source = Array.isArray(locations) ? locations : [];
    state.locations = source.map(function (location) {
      return normalizeLocationSummary(state.categories, location);
    });
  }

  function render() {
    var filtered = filterLocations();
    updateViewMode();
    renderLocations(filtered);
    renderStats();
    renderCategorySidebar();
    updateCategoryChips();
    renderMap(filtered);
  }

  function renderLocations(filtered) {
    var container = root.find("#mapLocationsTable");
    var emptyState = root.find("#mapLocationsEmpty");
    var noResults = root.find("#mapLocationsNoResults");

    if (!Array.isArray(filtered)) {
      filtered = filterLocations();
    }

    container.empty();

    if (!state.locations.length) {
      emptyState.removeAttr("hidden");
      noResults.attr("hidden", "hidden");
      return;
    }
    emptyState.attr("hidden", "hidden");

    if (!filtered.length) {
      noResults.removeAttr("hidden");
      return;
    }
    noResults.attr("hidden", "hidden");

    filtered.forEach(function (location) {
      container.append(buildLocationRow(location));
    });
  }

  function filterLocations() {
    return state.locations.filter(function (location) {
      var matchesSearch = true;
      if (state.filters.search) {
        var haystackParts = [
          location.name,
          location.slug,
          location.city,
          location.region,
        ];
        if (location.address) {
          haystackParts.push(
            location.address.street,
            location.address.postal_code,
            location.address.country,
          );
        }
        var haystack = haystackParts.join(" ").toLowerCase();
        matchesSearch = haystack.indexOf(state.filters.search) !== -1;
      }
      if (!matchesSearch) {
        return false;
      }
      if (state.filters.category) {
        if (state.filters.category === "uncategorized") {
          if (location.categories.length) {
            return false;
          }
        } else {
          var hasCategory = location.categories.some(function (category) {
            return category.id === state.filters.category;
          });
          if (!hasCategory) {
            return false;
          }
        }
      }
      if (state.filters.status) {
        if (location.status !== state.filters.status) {
          return false;
        }
      }
      return true;
    });
  }

  function buildLocationRow(location) {
    var row = $('<div class="maps-table__row"></div>');
    var statusBadge = $('<span class="status-badge"></span>');
    applyStatusBadge(statusBadge, location.status);
    var cityRegion = $.trim(
      [location.city, location.region].filter(Boolean).join(", "),
    );
    var updated = location.updated_at
      ? formatRelativeTime(location.updated_at)
      : "—";
    var categories = $('<div class="maps-category-chips"></div>');
    if (location.categories.length) {
      location.categories.forEach(function (category) {
        var chip = $('<span class="maps-category-chip"></span>');
        chip.css("border-color", category.color || "#666666");
        chip.css("color", category.color || "#666666");
        chip.text(category.name);
        categories.append(chip);
      });
    } else {
      categories.append(
        '<span class="maps-category-chip maps-category-chip--muted">Uncategorized</span>',
      );
    }

    row.append(
      $(
        '<div class="maps-table__cell maps-table__cell--primary" data-label="Name"></div>',
      ).append(
        $('<div class="maps-location-name"></div>').text(location.name),
        $('<div class="maps-location-slug"></div>').text(
          location.slug ? "/" + location.slug : "—",
        ),
      ),
    );
    row.append(
      $('<div class="maps-table__cell" data-label="Status"></div>').append(
        statusBadge,
      ),
    );
    row.append(
      $('<div class="maps-table__cell" data-label="Location"></div>').text(
        cityRegion || "—",
      ),
    );
    row.append(
      $('<div class="maps-table__cell" data-label="Categories"></div>').append(
        categories,
      ),
    );
    row.append(
      $('<div class="maps-table__cell" data-label="Updated"></div>').text(
        updated,
      ),
    );
    var actions = $(
      '<div class="maps-table__cell maps-table__actions" data-label="Actions"></div>',
    );
    actions.append(
      '<button type="button" class="a11y-btn a11y-btn--icon maps-action-btn" data-map-edit-location="' +
        location.id +
        '"><i class="fas fa-pen" aria-hidden="true"></i><span class="sr-only">Edit location</span></button>',
    );
    row.append(actions);
    return row;
  }

  function updateViewMode() {
    var listPanel = root.find("#mapListPanel");
    var mapPanel = root.find("#mapMapPanel");
    var buttons = root.find("[data-map-view]");
    buttons.each(function () {
      var button = $(this);
      var view = button.attr("data-map-view") === "map" ? "map" : "list";
      var isActive = view === state.viewMode;
      button.toggleClass("is-active", isActive);
      button.attr("aria-selected", isActive ? "true" : "false");
      button.attr("aria-pressed", isActive ? "true" : "false");
      button.attr("tabindex", isActive ? "0" : "-1");
    });
    if (state.viewMode === "map") {
      mapPanel.removeAttr("hidden");
      listPanel.attr("hidden", "hidden");
      if (mapInstance) {
        setTimeout(function () {
          applyPendingMapView();
        }, 120);
      }
    } else {
      listPanel.removeAttr("hidden");
      mapPanel.attr("hidden", "hidden");
    }
  }

  function renderMap(filtered) {
    var mapWrapper = root.find("#mapMapWrapper");
    var emptyState = root.find("#mapMapNoData");
    var emptyTitle = root.find("#mapMapNoDataTitle");
    var emptyMessage = root.find("#mapMapNoDataMessage");
    var legend = root.find("#mapMapLegend");

    if (!Array.isArray(filtered)) {
      filtered = filterLocations();
    }

    legend.empty();
    legend.attr("hidden", "hidden");

    if (!state.locations.length) {
      mapWrapper.attr("hidden", "hidden");
      emptyState.removeAttr("hidden");
      emptyTitle.text("No locations yet");
      emptyMessage.text("Add a location to visualize it on the map.");
      clearMapMarkers();
      mapPendingBounds = null;
      return;
    }

    if (!filtered.length) {
      mapWrapper.attr("hidden", "hidden");
      emptyState.removeAttr("hidden");
      emptyTitle.text("No matches found");
      emptyMessage.text("Try adjusting the search term or filters.");
      clearMapMarkers();
      mapPendingBounds = null;
      return;
    }

    var mappable = filtered
      .map(function (location) {
        var coords = getLocationCoordinates(location);
        if (!coords) {
          return null;
        }
        return {
          location: location,
          coords: coords,
        };
      })
      .filter(Boolean);

    if (!mappable.length) {
      mapWrapper.attr("hidden", "hidden");
      emptyState.removeAttr("hidden");
      emptyTitle.text("No coordinates available");
      emptyMessage.text(
        "Add latitude and longitude to these locations to display them on the map.",
      );
      clearMapMarkers();
      mapPendingBounds = null;
      return;
    }

    emptyState.attr("hidden", "hidden");
    mapWrapper.removeAttr("hidden");
    renderMapLegend(
      mappable.map(function (entry) {
        return entry.location;
      }),
    );

    var shouldRenderMap = state.viewMode === "map" || mapInstance;
    if (!shouldRenderMap) {
      mapPendingBounds = buildPendingBounds(mappable);
      return;
    }

    ensureLeaflet()
      .done(function () {
        drawMap(mappable);
      })
      .fail(function () {
        mapWrapper.attr("hidden", "hidden");
        emptyState.removeAttr("hidden");
        emptyTitle.text("Map unavailable");
        emptyMessage.text(
          "Map assets could not be loaded. Please refresh the page and try again.",
        );
        clearMapMarkers();
      });
  }

  function renderStats() {
    var total = state.locations.length;
    var published = state.locations.filter(function (location) {
      return location.status === "published";
    }).length;
    var draft = total - published;
    root.find('[data-map-stat="total"]').text(total);
    root.find('[data-map-stat="published"]').text(published);
    root.find('[data-map-stat="draft"]').text(draft);
    root.find('[data-map-stat="categories"]').text(
      state.categories.filter(function (category) {
        return !category.is_virtual;
      }).length,
    );
  }

  function renderCategorySidebar() {
    var list = root.find("#mapCategoryList");
    list.empty();
    state.categories.forEach(function (category) {
      var item = $('<li class="maps-category-item"></li>').attr(
        "data-category-id",
        category.id,
      );
      var color = $('<span class="maps-category-color"></span>').css(
        "background-color",
        category.color,
      );
      var name = $('<span class="maps-category-name"></span>').text(
        category.name,
      );
      var count = $('<span class="maps-category-count"></span>')
        .attr("data-count", category.id)
        .text(category.count);
      item.append(color, name, count);
      if (!category.is_virtual) {
        var actions = $('<div class="maps-category-actions"></div>');
        actions.append(
          '<button type="button" class="a11y-btn a11y-btn--icon maps-action-btn" data-map-edit-category="' +
            category.id +
            '"><i class="fas fa-pen" aria-hidden="true"></i><span class="sr-only">Edit category</span></button>',
        );
        actions.append(
          '<button type="button" class="a11y-btn a11y-btn--icon maps-action-btn maps-action-btn--danger" data-map-delete-category="' +
            category.id +
            '"><i class="fas fa-trash" aria-hidden="true"></i><span class="sr-only">Delete category</span></button>',
        );
        item.append(actions);
      }
      list.append(item);
    });
  }

  function renderMapLegend(locations) {
    var legend = root.find("#mapMapLegend");
    if (!legend.length) {
      return;
    }
    legend.empty();
    if (!Array.isArray(locations) || !locations.length) {
      legend.attr("hidden", "hidden");
      return;
    }
    var groups = {};
    locations.forEach(function (location) {
      if (location.categories && location.categories.length) {
        location.categories.forEach(function (category) {
          if (!category) {
            return;
          }
          var key = category.id || "uncategorized";
          if (!groups[key]) {
            groups[key] = {
              id: key,
              name: category.name || "Category",
              color: sanitizeColor(category.color),
              count: 0,
            };
          }
          groups[key].count += 1;
        });
      } else {
        if (!groups.uncategorized) {
          groups.uncategorized = {
            id: "uncategorized",
            name: "Uncategorized",
            color: "#9CA3AF",
            count: 0,
          };
        }
        groups.uncategorized.count += 1;
      }
    });
    var items = Object.keys(groups).map(function (key) {
      return groups[key];
    });
    items.sort(function (a, b) {
      if (b.count === a.count) {
        return a.name.localeCompare(b.name);
      }
      return b.count - a.count;
    });
    legend.removeAttr("hidden");
    items.forEach(function (item) {
      var entry = $('<div class="maps-map-legend__item"></div>');
      var swatch = $('<span class="maps-map-legend__swatch"></span>').css(
        "background-color",
        item.color,
      );
      var text = $('<div class="maps-map-legend__text"></div>');
      text.append(
        $('<span class="maps-map-legend__name"></span>').text(item.name),
      );
      var countLabel =
        item.count === 1 ? "1 location" : item.count + " locations";
      text.append(
        $('<span class="maps-map-legend__count"></span>').text(countLabel),
      );
      entry.append(swatch, text);
      legend.append(entry);
    });
  }

  function updateCategoryChips() {
    var container = root.find("#mapLocationCategories");
    var selected = container
      .find('input[type="checkbox"]')
      .filter(function () {
        return this.checked;
      })
      .map(function () {
        return $(this).val();
      })
      .get();
    container.empty();
    state.categories.forEach(function (category) {
      if (category.is_virtual) {
        return;
      }
      var id = "mapCat_" + category.id;
      var wrapper = $('<label class="maps-chip"></label>').attr("for", id);
      var checkbox = $('<input type="checkbox" class="maps-chip__input">')
        .attr("id", id)
        .attr("value", category.id);
      checkbox.prop("checked", selected.indexOf(category.id) !== -1);
      wrapper.append(checkbox);
      var chip = $('<span class="maps-chip__label"></span>').text(
        category.name,
      );
      wrapper.append(chip);
      container.append(wrapper);
    });
  }

  function buildPendingBounds(mappable) {
    if (!Array.isArray(mappable) || !mappable.length) {
      return null;
    }
    var coords = mappable
      .map(function (entry) {
        return entry.coords;
      })
      .filter(function (pair) {
        if (!Array.isArray(pair) || pair.length < 2) {
          return false;
        }
        var lat = Number(pair[0]);
        var lng = Number(pair[1]);
        return Number.isFinite(lat) && Number.isFinite(lng);
      });
    if (!coords.length) {
      return null;
    }
    return {
      single: coords.length === 1,
      coords: coords[0],
      boundsCoords: coords,
    };
  }

  function applyPendingMapView() {
    if (
      !mapInstance ||
      typeof window.L === "undefined" ||
      typeof window.L.map !== "function"
    ) {
      return;
    }
    if (mapPendingBounds) {
      applyMapBounds(mapPendingBounds);
      mapPendingBounds = null;
    } else {
      mapInstance.invalidateSize();
    }
  }

  function applyMapBounds(pending) {
    if (
      !pending ||
      !mapInstance ||
      typeof window.L === "undefined" ||
      typeof window.L.latLng !== "function"
    ) {
      return;
    }
    if (pending.single && Array.isArray(pending.coords)) {
      var singleLat = Number(pending.coords[0]);
      var singleLng = Number(pending.coords[1]);
      if (Number.isFinite(singleLat) && Number.isFinite(singleLng)) {
        mapInstance.setView([singleLat, singleLng], 13, { animate: false });
      }
    } else if (pending.boundsCoords && pending.boundsCoords.length) {
      var points = [];
      pending.boundsCoords.forEach(function (coord) {
        if (!Array.isArray(coord) || coord.length < 2) {
          return;
        }
        var lat = Number(coord[0]);
        var lng = Number(coord[1]);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
          return;
        }
        points.push(window.L.latLng(lat, lng));
      });
      if (points.length) {
        var bounds = window.L.latLngBounds(points);
        mapInstance.fitBounds(bounds, { padding: [20, 20] });
      }
    }
    mapInstance.invalidateSize();
  }

  function ensureLeaflet() {
    if (window.L && typeof window.L.map === "function") {
      return $.Deferred().resolve(window.L).promise();
    }
    if (leafletDeferred) {
      return leafletDeferred.promise();
    }
    var deferred = $.Deferred();
    leafletDeferred = deferred;

    var css = document.querySelector("link[data-leaflet]");
    if (!css) {
      css = document.createElement("link");
      css.rel = "stylesheet";
      css.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
      css.integrity = "sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=";
      css.crossOrigin = "";
      css.setAttribute("data-leaflet", "true");
      document.head.appendChild(css);
    }

    var script = document.querySelector("script[data-leaflet]");
    if (!script) {
      script = document.createElement("script");
      script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
      script.integrity = "sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=";
      script.crossOrigin = "";
      script.async = true;
      script.defer = true;
      script.setAttribute("data-leaflet", "true");
    }

    var resolved = false;
    script.addEventListener(
      "load",
      function () {
        if (resolved) {
          return;
        }
        resolved = true;
        if (window.L && typeof window.L.map === "function") {
          deferred.resolve(window.L);
        } else {
          deferred.reject(new Error("Leaflet failed to initialize."));
        }
        leafletDeferred = null;
      },
      { once: true },
    );
    script.addEventListener(
      "error",
      function () {
        if (resolved) {
          return;
        }
        resolved = true;
        deferred.reject(new Error("Leaflet failed to load."));
        leafletDeferred = null;
      },
      { once: true },
    );

    if (!script.parentNode) {
      document.head.appendChild(script);
    }

    return deferred.promise();
  }

  function drawMap(mappable) {
    if (
      !Array.isArray(mappable) ||
      !mappable.length ||
      typeof window.L === "undefined" ||
      typeof window.L.map !== "function"
    ) {
      return;
    }
    var mapElement = document.getElementById("mapLocationsMap");
    if (!mapElement) {
      return;
    }
    if (!mapInstance) {
      if (!mapElement.hasAttribute("tabindex")) {
        mapElement.setAttribute("tabindex", "0");
      }
      mapInstance = window.L.map(mapElement, {
        zoomControl: true,
        attributionControl: true,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        keyboard: false,
        dragging: false,
      });
      window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution:
          '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
      }).addTo(mapInstance);

      function setInteractive(enabled) {
        if (!mapInstance) {
          return;
        }
        if (enabled) {
          mapInstance.scrollWheelZoom.enable();
          mapInstance.doubleClickZoom.enable();
          mapInstance.boxZoom.enable();
          mapInstance.keyboard.enable();
          mapInstance.dragging.enable();
          if (mapInstance.touchZoom) {
            mapInstance.touchZoom.enable();
          }
        } else {
          mapInstance.scrollWheelZoom.disable();
          mapInstance.doubleClickZoom.disable();
          mapInstance.boxZoom.disable();
          mapInstance.keyboard.disable();
          mapInstance.dragging.disable();
          if (mapInstance.touchZoom) {
            mapInstance.touchZoom.disable();
          }
        }
      }

      setInteractive(false);
      mapElement.addEventListener("focus", function () {
        setInteractive(true);
      });
      mapElement.addEventListener("blur", function () {
        setInteractive(false);
      });
    }

    clearMapMarkers();
    mappable.forEach(function (entry) {
      if (!Array.isArray(entry.coords) || entry.coords.length < 2) {
        return;
      }
      var lat = Number(entry.coords[0]);
      var lng = Number(entry.coords[1]);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        return;
      }
      var position = window.L.latLng(lat, lng);
      var marker = window.L.marker(position, {
        icon: window.L.divIcon({
          className: "maps-marker leaflet-div-icon",
          html: '<span class="maps-marker__dot"></span>',
          iconSize: [16, 16],
          iconAnchor: [8, 8],
          popupAnchor: [0, -8],
        }),
      }).addTo(mapInstance);
      marker.bindPopup(buildMapPopup(entry.location), { closeButton: true });
      marker.on("click", function () {
        if (mapActivePopup && typeof mapActivePopup.close === "function") {
          mapActivePopup.close();
        }
        marker.openPopup();
        mapActivePopup = marker.getPopup();
      });
      mapMarkers.push({ marker: marker, position: position });
    });
    mapPendingBounds = buildPendingBounds(mappable);
    if (isMapContainerVisible()) {
      applyPendingMapView();
    } else {
      setTimeout(function () {
        applyPendingMapView();
      }, 150);
    }
  }

  function isMapContainerVisible() {
    var container = root.find("#mapLocationsMap");
    if (!container.length) {
      return false;
    }
    return (
      container.is(":visible") &&
      container.width() > 0 &&
      container.height() > 0
    );
  }

  function clearMapMarkers() {
    if (!mapMarkers.length) {
      return;
    }
    mapMarkers.forEach(function (entry) {
      if (!entry || !entry.marker) {
        return;
      }
      if (mapInstance && typeof mapInstance.removeLayer === "function") {
        mapInstance.removeLayer(entry.marker);
      }
    });
    mapMarkers = [];
    if (mapActivePopup && typeof mapActivePopup.close === "function") {
      mapActivePopup.close();
    }
    mapActivePopup = null;
  }

  function buildMapPopup(location) {
    var name = escapeHtml(location.name || "Untitled location");
    var address = formatAddress(location.address);
    var status = location.status === "published" ? "published" : "draft";
    var html = '<div class="maps-popup">';
    html += '<div class="maps-popup__title">' + name + "</div>";
    if (address) {
      html +=
        '<div class="maps-popup__address">' + escapeHtml(address) + "</div>";
    }
    html +=
      '<div class="maps-popup__status maps-popup__status--' +
      status +
      '">' +
      capitalize(status) +
      "</div>";
    if (location.categories && location.categories.length) {
      html += '<div class="maps-popup__categories">';
      location.categories.forEach(function (category) {
        var color = sanitizeColor(category.color);
        html +=
          '<span class="maps-popup__category" style="border-color:' +
          color +
          ";color:" +
          color +
          ';">' +
          escapeHtml(category.name || "Category") +
          "</span>";
      });
      html += "</div>";
    }
    html += "</div>";
    return html;
  }

  function formatAddress(address) {
    if (!address || typeof address !== "object") {
      return "";
    }
    var parts = [];
    if (address.street) {
      parts.push(address.street);
    }
    var cityRegion = [address.city, address.region].filter(Boolean).join(", ");
    if (cityRegion) {
      parts.push(cityRegion);
    }
    var countryLine = [address.postal_code, address.country]
      .filter(Boolean)
      .join(" ")
      .trim();
    if (countryLine) {
      parts.push(countryLine);
    }
    return parts.join(", ");
  }

  function getLocationCoordinates(location) {
    if (!location || !location.coordinates) {
      return null;
    }
    var lat = parseFloat(location.coordinates.lat);
    var lng = parseFloat(location.coordinates.lng);
    if (!isFinite(lat) || !isFinite(lng)) {
      return null;
    }
    if (Math.abs(lat) > 90 || Math.abs(lng) > 180) {
      return null;
    }
    return [lat, lng];
  }

  function sanitizeColor(value) {
    if (typeof value !== "string") {
      return "#4B5563";
    }
    var color = value.trim();
    if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(color)) {
      return color;
    }
    return "#4B5563";
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

  function updateCategoryFilter() {
    var select = root.find("#mapCategoryFilter");
    var current = select.val();
    select.empty();
    select.append('<option value="">All categories</option>');
    state.categories.forEach(function (category) {
      select.append(
        '<option value="' + category.id + '">' + category.name + "</option>",
      );
    });
    select.val(current || "");
  }

  function openLocationModal(location) {
    var modal = $("#mapLocationModal");
    resetLocationForm();
    toggleLocationMetaFields(!!location);
    if (location) {
      $("#mapLocationId").val(location.id || "");
      $("#mapLocationName").val(location.name || "");
      $("#mapLocationSlug").val(location.slug || "");
      $("#mapLocationStatus").val(location.status || "draft");
      updateLocationModalStatusBadge(location.status || "draft");
      $("#mapLocationDescription").val(location.description || "");
      if (location.address) {
        $("#mapLocationStreet").val(location.address.street || "");
        $("#mapLocationCity").val(location.address.city || "");
        $("#mapLocationRegion").val(location.address.region || "");
        $("#mapLocationPostal").val(location.address.postal_code || "");
        $("#mapLocationCountry").val(location.address.country || "");
      }
      if (location.coordinates) {
        $("#mapLocationLat").val(location.coordinates.lat || "");
        $("#mapLocationLng").val(location.coordinates.lng || "");
      }
      if (location.contact) {
        $("#mapLocationPhone").val(location.contact.phone || "");
        $("#mapLocationEmail").val(location.contact.email || "");
        $("#mapLocationWebsite").val(location.contact.website || "");
      }
      if (Array.isArray(location.category_ids)) {
        location.category_ids.forEach(function (id) {
          $('#mapLocationCategories input[value="' + id + '"]').prop(
            "checked",
            true,
          );
        });
      }
      if (Array.isArray(location.tags)) {
        $("#mapLocationTags").val(location.tags.join(", "));
      } else {
        $("#mapLocationTags").val(location.tags || "");
      }
      if (Array.isArray(location.image_ids)) {
        $("#mapLocationImages").val(location.image_ids.join(", "));
      } else {
        $("#mapLocationImages").val(location.image_ids || "");
      }
      $("#mapLocationHours").val(location.hours || "");
      $("#mapLocationAccessibility").val(location.accessibility_notes || "");
      $("#mapLocationModalTitle").text("Edit location");
      $("#mapLocationDeleteBtn").removeAttr("hidden");
      geocodeLastSuccessfulQuery = buildAddressQuery();
    } else {
      $("#mapLocationModalTitle").text("Add location");
      $("#mapLocationDeleteBtn").attr("hidden", "hidden");
      updateLocationModalStatusBadge("draft");
      geocodeLastSuccessfulQuery = "";
    }
    coordinatesAutoFilled = false;
    coordinatesEditedManually = false;
    geocodePendingQuery = "";
    setGeocodeStatus(geocodeDefaultStatus);
    openModal(modal);
  }

  function resetLocationForm() {
    var form = $("#mapLocationForm")[0];
    if (form && typeof form.reset === "function") {
      form.reset();
    }
    $("#mapLocationCategories")
      .find('input[type="checkbox"]')
      .prop("checked", false);
    toggleLocationMetaFields(false);
    clearGeocodeState();
    coordinatesAutoFilled = false;
    coordinatesEditedManually = false;
    geocodeLastSuccessfulQuery = "";
    setGeocodeStatus(geocodeDefaultStatus);
  }

  function toggleLocationMetaFields(show) {
    root.find("[data-map-meta-field]").each(function () {
      if (show) {
        $(this).removeAttr("hidden");
      } else {
        $(this).attr("hidden", "hidden");
      }
    });
  }

  function scheduleGeocodeLookup() {
    clearGeocodeTimer();
    if (geocodeRequest && typeof geocodeRequest.abort === "function") {
      geocodeRequest.abort();
    }
    geocodeRequest = null;
    geocodePendingQuery = buildAddressQuery();
    if (geocodePendingQuery === "") {
      geocodePendingQuery = "";
      if (!coordinatesEditedManually || coordinatesAreEmpty()) {
        setGeocodeStatus(geocodeDefaultStatus);
      }
      return;
    }
    geocodeTimer = setTimeout(function () {
      requestGeocode(geocodePendingQuery);
    }, 600);
  }

  function requestGeocode(query) {
    if (!query || query !== buildAddressQuery()) {
      return;
    }
    if (geocodeRequest && typeof geocodeRequest.abort === "function") {
      geocodeRequest.abort();
    }
    if (
      query === geocodeLastSuccessfulQuery &&
      coordinatesAutoFilled &&
      !coordinatesAreEmpty()
    ) {
      return;
    }
    setGeocodeStatus("Searching for coordinates…", "loading");
    geocodeRequest = apiRequest("geocode_address", { query: query });
    geocodeRequest
      .done(function (response) {
        geocodeRequest = null;
        geocodePendingQuery = "";
        if (!response || !response.coordinates) {
          setGeocodeStatus(
            "Unable to determine coordinates for this address.",
            "error",
          );
          return;
        }
        var lat = parseFloat(response.coordinates.lat);
        var lng = parseFloat(response.coordinates.lng);
        if (!isFinite(lat) || !isFinite(lng)) {
          setGeocodeStatus(
            "Unable to determine coordinates for this address.",
            "error",
          );
          return;
        }
        geocodeLastSuccessfulQuery = query;
        if (canAutoFillCoordinates()) {
          $("#mapLocationLat").val(lat.toFixed(6));
          $("#mapLocationLng").val(lng.toFixed(6));
          coordinatesAutoFilled = true;
          coordinatesEditedManually = false;
          setGeocodeStatus(
            "Coordinates auto-filled from the address.",
            "success",
          );
        } else {
          setGeocodeStatus(
            "Address located. Update the coordinates if needed.",
            "success",
          );
        }
      })
      .fail(function (jqXHR, textStatus) {
        geocodeRequest = null;
        geocodePendingQuery = "";
        if (textStatus === "abort") {
          return;
        }
        var message = "Unable to determine coordinates for this address.";
        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) {
          message = jqXHR.responseJSON.error;
        }
        setGeocodeStatus(message, "error");
      });
  }

  function clearGeocodeState() {
    clearGeocodeTimer();
    if (geocodeRequest && typeof geocodeRequest.abort === "function") {
      geocodeRequest.abort();
    }
    geocodeRequest = null;
    geocodePendingQuery = "";
  }

  function clearGeocodeTimer() {
    if (geocodeTimer) {
      clearTimeout(geocodeTimer);
      geocodeTimer = null;
    }
  }

  function canAutoFillCoordinates() {
    if (
      coordinatesEditedManually &&
      !coordinatesAutoFilled &&
      !coordinatesAreEmpty()
    ) {
      return false;
    }
    return true;
  }

  function coordinatesAreEmpty() {
    var lat = ($("#mapLocationLat").val() || "").toString().trim();
    var lng = ($("#mapLocationLng").val() || "").toString().trim();
    return lat === "" && lng === "";
  }

  function buildAddressQuery() {
    var parts = [
      $("#mapLocationStreet").val(),
      $("#mapLocationCity").val(),
      $("#mapLocationRegion").val(),
      $("#mapLocationPostal").val(),
      $("#mapLocationCountry").val(),
    ];
    var filtered = [];
    parts.forEach(function (value) {
      var trimmed = (value || "").toString().trim();
      if (trimmed !== "") {
        filtered.push(trimmed);
      }
    });
    if (!filtered.length) {
      return "";
    }
    var query = filtered.join(", ");
    return query.length < 3 ? "" : query;
  }

  function setGeocodeStatus(message, state) {
    if (!geocodeStatusEl.length) {
      if (typeof message === "string" && message.trim() !== "") {
        geocodeDefaultStatus = message;
      }
      return;
    }
    var text =
      typeof message === "string" && message.trim() !== ""
        ? message
        : geocodeDefaultStatus;
    if (text === "") {
      text = "Latitude and longitude update automatically from the address.";
    }
    geocodeStatusEl
      .removeClass(
        "maps-form__hint--loading maps-form__hint--success maps-form__hint--error",
      )
      .text(text);
    if (state === "loading") {
      geocodeStatusEl.addClass("maps-form__hint--loading");
    } else if (state === "success") {
      geocodeStatusEl.addClass("maps-form__hint--success");
    } else if (state === "error") {
      geocodeStatusEl.addClass("maps-form__hint--error");
    }
  }

  function openCategoryModal(category) {
    var modal = $("#mapCategoryModal");
    var form = $("#mapCategoryForm")[0];
    if (form && typeof form.reset === "function") {
      form.reset();
    }
    if (category) {
      $("#mapCategoryId").val(category.id || "");
      $("#mapCategoryName").val(category.name || "");
      $("#mapCategorySlug").val(category.slug || "");
      $("#mapCategoryIcon").val(category.icon || "");
      $("#mapCategoryColor").val(category.color || "#2D70F5");
      $("#mapCategorySort").val(category.sort_order || 0);
      $("#mapCategoryModalTitle").text("Edit category");
    } else {
      $("#mapCategoryModalTitle").text("Add category");
    }
    openModal(modal);
  }

  function openModal(modal) {
    modal.removeAttr("hidden").addClass("maps-modal--visible");
    setTimeout(function () {
      modal
        .find("input, textarea, select")
        .filter(":visible:first")
        .trigger("focus");
    }, 150);
  }

  function closeModal(modal) {
    if (!modal || !modal.length) {
      return;
    }
    modal.removeClass("maps-modal--visible");
    modal.attr("hidden", "hidden");
  }

  function gatherLocationForm() {
    var categories = [];
    $('#mapLocationCategories input[type="checkbox"]').each(function () {
      if (this.checked) {
        categories.push($(this).val());
      }
    });
    return {
      id: $("#mapLocationId").val() || undefined,
      name: $("#mapLocationName").val(),
      slug: $("#mapLocationSlug").val(),
      status: $("#mapLocationStatus").val(),
      description: $("#mapLocationDescription").val(),
      address: {
        street: $("#mapLocationStreet").val(),
        city: $("#mapLocationCity").val(),
        region: $("#mapLocationRegion").val(),
        postal_code: $("#mapLocationPostal").val(),
        country: $("#mapLocationCountry").val(),
      },
      coordinates: {
        lat: $("#mapLocationLat").val(),
        lng: $("#mapLocationLng").val(),
      },
      contact: {
        phone: $("#mapLocationPhone").val(),
        email: $("#mapLocationEmail").val(),
        website: $("#mapLocationWebsite").val(),
      },
      category_ids: categories,
      tags: $("#mapLocationTags").val(),
      image_ids: $("#mapLocationImages").val(),
      hours: $("#mapLocationHours").val(),
      accessibility_notes: $("#mapLocationAccessibility").val(),
    };
  }

  function gatherCategoryForm() {
    return {
      id: $("#mapCategoryId").val() || undefined,
      name: $("#mapCategoryName").val(),
      slug: $("#mapCategorySlug").val(),
      icon: $("#mapCategoryIcon").val(),
      color: $("#mapCategoryColor").val(),
      sort_order: $("#mapCategorySort").val(),
    };
  }

  function fetchLocation(id) {
    return $.ajax({
      url: API_URL,
      data: { action: "get_location", id: id },
      method: "GET",
      dataType: "json",
    }).fail(handleAjaxError);
  }

  function refreshAll() {
    refreshLocations();
    refreshCategories();
  }

  function refreshLocations() {
    $.ajax({
      url: API_URL,
      data: { action: "list_locations" },
      method: "GET",
      dataType: "json",
    })
      .done(function (response) {
        if (response && Array.isArray(response.locations)) {
          setLocations(response.locations);
          render();
          notify("Locations refreshed.", "info");
        }
      })
      .fail(handleAjaxError);
  }

  function refreshCategories() {
    $.ajax({
      url: API_URL,
      data: { action: "list_categories" },
      method: "GET",
      dataType: "json",
    })
      .done(function (response) {
        if (response && Array.isArray(response.categories)) {
          updateCategories(response.categories);
          updateCategoryFilter();
          render();
        }
      })
      .fail(handleAjaxError);
  }

  function updateCategories(categories) {
    state.categories = [];
    categories.forEach(function (category) {
      state.categories.push(
        normalizeCategory(category, category.count || 0, !!category.is_virtual),
      );
    });
    var hasVirtual = state.categories.some(function (category) {
      return category.id === "uncategorized";
    });
    if (!hasVirtual) {
      state.categories.push(
        normalizeCategory(
          {
            id: "uncategorized",
            name: "Uncategorized",
            color: "#9CA3AF",
            icon: "fa-circle-exclamation",
            is_default: true,
          },
          0,
          true,
        ),
      );
    }
  }

  function apiRequest(action, payload) {
    return $.ajax({
      url: API_URL + "?action=" + encodeURIComponent(action),
      method: "POST",
      data: JSON.stringify(payload || {}),
      contentType: "application/json",
      dataType: "json",
    });
  }

  function findCategory(id) {
    return state.categories.find(function (category) {
      return category.id === id;
    });
  }

  function notify(message, type) {
    if (
      window.AdminNotifications &&
      typeof window.AdminNotifications.showToast === "function"
    ) {
      var toastType = type || "info";
      if (
        toastType === "success" &&
        window.AdminNotifications.showSuccessToast
      ) {
        window.AdminNotifications.showSuccessToast(message);
        return;
      }
      if (toastType === "error" && window.AdminNotifications.showErrorToast) {
        window.AdminNotifications.showErrorToast(message);
        return;
      }
      window.AdminNotifications.showToast(message, { type: toastType });
    }
  }

  function handleAjaxError(jqXHR) {
    var message = "An unexpected error occurred.";
    if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) {
      message = jqXHR.responseJSON.error;
    }
    notify(message, "error");
  }

  function formatRelativeTime(value) {
    if (!value) {
      return "—";
    }
    var date = new Date(value);
    if (isNaN(date.getTime())) {
      return value;
    }
    var diff = Date.now() - date.getTime();
    var minute = 60 * 1000;
    var hour = 60 * minute;
    var day = 24 * hour;
    if (diff < minute) {
      return "Just now";
    }
    if (diff < hour) {
      var minutes = Math.round(diff / minute);
      return minutes + " minute" + (minutes === 1 ? "" : "s") + " ago";
    }
    if (diff < day) {
      var hours = Math.round(diff / hour);
      return hours + " hour" + (hours === 1 ? "" : "s") + " ago";
    }
    var days = Math.round(diff / day);
    if (days <= 7) {
      return days + " day" + (days === 1 ? "" : "s") + " ago";
    }
    return date.toLocaleDateString();
  }

  function capitalize(value) {
    if (!value) {
      return "";
    }
    return value.charAt(0).toUpperCase() + value.slice(1);
  }
})(jQuery, window, document);
