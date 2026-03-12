/* File: modules/events/events.js */
(function () {
  const root = document.querySelector(".events-dashboard");
  if (!root) {
    return;
  }

  const endpoint = root.dataset.eventsEndpoint || "";
  const initialPayload = (() => {
    try {
      return JSON.parse(root.dataset.eventsInitial || "{}");
    } catch (error) {
      return {};
    }
  })();

  const selectors = {
    stats: {
      events: root.querySelector('[data-events-stat="events"]'),
      tickets: root.querySelector('[data-events-stat="tickets"]'),
      revenue: root.querySelector('[data-events-stat="revenue"]'),
    },
    upcoming: {
      list: root.querySelector("[data-events-upcoming-list]"),
      viewButtons: Array.from(
        root.querySelectorAll("[data-events-upcoming-view]"),
      ),
      panels: Array.from(root.querySelectorAll("[data-events-upcoming-panel]")),
      calendar: {
        container: root.querySelector("[data-events-calendar]"),
        label: root.querySelector("[data-events-calendar-label]"),
        grid: root.querySelector("[data-events-calendar-grid]"),
        prev: root.querySelector('[data-events-calendar-nav="prev"]'),
        next: root.querySelector('[data-events-calendar-nav="next"]'),
        empty: root.querySelector("[data-events-calendar-empty]"),
      },
    },
    tableBody: root.querySelector("[data-events-table]"),
    filters: {
      status: root.querySelector('[data-events-filter="status"]'),
      search: root.querySelector('[data-events-filter="search"]'),
    },
    orders: {
      body: root.querySelector("[data-events-orders]"),
      filterEvent: root.querySelector('[data-events-orders-filter="event"]'),
      filterStatus: root.querySelector('[data-events-orders-filter="status"]'),
      exportBtn: root.querySelector("[data-events-export]"),
    },
    reports: {
      tableBody: root.querySelector("[data-events-reports-table]"),
      metrics: {
        container: root.querySelector("[data-events-report-metrics]"),
        revenue: root.querySelector('[data-events-report-metric="revenue"]'),
        averageOrder: root.querySelector(
          '[data-events-report-metric="average_order"]',
        ),
        refunds: root.querySelector('[data-events-report-metric="refunds"]'),
      },
      insights: root.querySelector("[data-events-insights]"),
      downloads: root.querySelector("[data-events-downloads]"),
      filters: {
        timeframe: root.querySelector(
          '[data-events-reports-filter="timeframe"]',
        ),
        status: root.querySelector('[data-events-reports-filter="status"]'),
      },
    },
    tabs: {
      root: root.querySelector("[data-events-tabs]"),
      buttons: Array.from(root.querySelectorAll("[data-events-tab]")),
    },
    orderEditor: {
      modal: document.querySelector('[data-events-modal="order"]'),
      form: document.querySelector('[data-events-form="order"]'),
      title: document.querySelector("[data-events-order-title]"),
      event: document.querySelector("[data-events-order-event]"),
      status: document.querySelector("[data-events-order-status]"),
      lines: document.querySelector("[data-events-order-lines]"),
      summary: document.querySelector("[data-events-order-summary]"),
      totals: {
        subtotal: document.querySelector('[data-order-total="subtotal"]'),
        refunds: document.querySelector('[data-order-total="refunds"]'),
        net: document.querySelector('[data-order-total="net"]'),
      },
      breakdown: document.querySelector("[data-events-order-breakdown]"),
      addSelect: document.querySelector("[data-events-order-add-select]"),
      addButton: document.querySelector("[data-events-order-add]"),
    },
    modal: document.querySelector('[data-events-modal="event"]'),
    confirmModal: document.querySelector('[data-events-modal="confirm"]'),
    categoriesModal: document.querySelector('[data-events-modal="categories"]'),
    mediaModal: document.querySelector('[data-events-modal="media"]'),
    mediaGrid: document.querySelector("[data-events-media-grid]"),
    mediaSearch: document.querySelector("[data-events-media-search]"),
    categoriesForm: document.querySelector('[data-events-form="category"]'),
    categoriesList: document.querySelector("[data-events-categories-list]"),
    categoriesFormTitle: document.querySelector(
      "[data-events-category-form-title]",
    ),
    categoriesSubmit: document.querySelector("[data-events-category-submit]"),
    categoriesReset: document.querySelector("[data-events-category-reset]"),
    toast: document.querySelector("[data-events-toast]"),
    formBuilder: {
      modal: document.querySelector('[data-events-modal="form-builder"]'),
      form: document.querySelector('[data-events-form="form-builder"]'),
      fields: document.querySelector("[data-events-builder-fields]"),
      empty: document.querySelector("[data-events-builder-empty]"),
      addButton: document.querySelector("[data-events-builder-add]"),
      alert: document.querySelector("[data-events-builder-alert]"),
      submit: document.querySelector("[data-events-builder-submit]"),
    },
  };
  const eventSaveState = {
    element: selectors.modal?.querySelector("[data-save-state]") || null,
    isSaving: false,
    isDirty: false,
    isReady: false,
  };
  const eventSaveLabels = {
    saving: "Saving…",
    saved: "Saved",
    unsaved: "Unsaved changes",
  };
  const STATUS_BADGE_MAP = {
    draft: { label: "Draft", className: "status-draft" },
    published: { label: "Published", className: "status-published" },
    scheduled: { label: "Scheduled", className: "status-scheduled" },
    ended: { label: "Archived", className: "status-archived" },
    pending: { label: "Pending", className: "status-pending" },
    paid: { label: "Paid", className: "status-paid" },
    refunded: { label: "Refunded", className: "status-refunded" },
  };
  const STATUS_BADGE_CLASSES = Object.values(STATUS_BADGE_MAP)
    .map((entry) => entry.className)
    .join(" ");

  function getStatusBadgeInfo(status) {
    const key = String(status || "").toLowerCase();
    return (
      STATUS_BADGE_MAP[key] || { label: "Draft", className: "status-draft" }
    );
  }

  function applyStatusBadge(element, status) {
    if (!element) {
      return;
    }
    const info = getStatusBadgeInfo(status);
    element.classList.remove(...STATUS_BADGE_CLASSES.split(" "));
    element.classList.add(info.className);
    element.textContent = info.label;
    element.setAttribute("aria-label", `Status: ${info.label}`);
  }

  function setEventSaveState(state) {
    if (!eventSaveState.element) {
      return;
    }
    const nextState = eventSaveLabels[state] ? state : "saved";
    eventSaveState.element.dataset.state = nextState;
    const label = eventSaveState.element.querySelector(
      "[data-save-state-text]",
    );
    if (label) {
      label.textContent = eventSaveLabels[nextState];
    }
    if (nextState === "saving") {
      eventSaveState.element.setAttribute("aria-busy", "true");
    } else {
      eventSaveState.element.removeAttribute("aria-busy");
    }
  }

  function resetEventSaveState() {
    eventSaveState.isSaving = false;
    eventSaveState.isDirty = false;
    eventSaveState.isReady = true;
    setEventSaveState("saved");
  }

  function markEventDirty() {
    if (!eventSaveState.isReady || eventSaveState.isSaving) {
      return;
    }
    eventSaveState.isDirty = true;
    setEventSaveState("unsaved");
  }

  const state = {
    events: new Map(),
    eventRows: [],
    orders: [],
    salesSummary: [],
    reportRows: [],
    categories: [],
    upcoming: [],
    forms: [],
    upcomingView: "list",
    calendar: {
      currentMonth: null,
    },
    filters: {
      status: "",
      search: "",
    },
    ordersFilter: {
      event: "",
      status: "",
    },
    reportsFilter: {
      timeframe: "all",
      status: "",
    },
    confirm: null,
    categoryEditing: null,
    media: {
      items: [],
      loaded: false,
      loading: false,
      currentSetter: null,
    },
    orderEditor: {
      detail: null,
    },
    formBuilder: {
      targetSelect: null,
    },
  };

  if (Array.isArray(initialPayload.forms)) {
    state.forms = initialPayload.forms
      .map((form) => normalizeFormOption(form))
      .filter((form) => form !== null);
  }
  if (Array.isArray(initialPayload.events)) {
    const normalizedEvents = initialPayload.events
      .map((event) => {
        storeEvent(event);
        return normalizeEventRecord(event);
      })
      .filter((event) => event !== null);
    if (normalizedEvents.length > 0) {
      state.eventRows = normalizedEvents;
    }
  }
  if (Array.isArray(initialPayload.categories)) {
    state.categories = sortCategories(initialPayload.categories);
  }
  if (initialPayload.sales && typeof initialPayload.sales === "object") {
    state.salesSummary = Object.entries(initialPayload.sales)
      .map(([eventId, metrics]) => {
        const eventRecord = state.events.get(String(eventId));
        return normalizeReportRow({
          event_id: eventId,
          title: eventRecord?.title || "Event",
          status: eventRecord?.status || "draft",
          capacity: eventRecord?.capacity ?? 0,
          ...metrics,
        });
      })
      .filter((report) => report !== null);
  }
  if (Array.isArray(initialPayload.orders)) {
    state.orders = initialPayload.orders
      .map((order) => normalizeOrderRow(order))
      .filter((order) => order !== null);
  }

  if (Array.isArray(initialPayload.upcoming)) {
    state.upcoming = initialPayload.upcoming
      .map((item) => normalizeUpcomingItem(item))
      .filter((item) => item !== null);
  }

  initializeTabs();
  initializeUpcoming();
  renderUpcoming(state.upcoming);

  function initializeTabs() {
    const tabButtons = selectors.tabs.buttons;
    const tabPanels = Array.from(root.querySelectorAll("[data-events-panel]"));

    if (
      !selectors.tabs.root ||
      tabButtons.length === 0 ||
      tabPanels.length === 0
    ) {
      return;
    }

    selectors.tabs.root.setAttribute("role", "tablist");
    selectors.tabs.root.setAttribute("aria-orientation", "horizontal");

    let activeId =
      tabButtons.find((button) => button.classList.contains("is-active"))
        ?.dataset.eventsTab || tabButtons[0].dataset.eventsTab;

    function activate(tabId, options = {}) {
      const tab = tabButtons.find(
        (button) => button.dataset.eventsTab === tabId,
      );
      const panel = tabPanels.find(
        (section) => section.dataset.eventsPanel === tabId,
      );

      if (!tab || !panel) {
        return;
      }

      activeId = tabId;

      tabButtons.forEach((button) => {
        const isActive = button === tab;
        button.classList.toggle("is-active", isActive);
        button.setAttribute("aria-selected", isActive ? "true" : "false");
        button.setAttribute("tabindex", isActive ? "0" : "-1");
      });

      tabPanels.forEach((section) => {
        const isActive = section === panel;
        section.classList.toggle("is-active", isActive);
        section.hidden = !isActive;
      });

      if (options.focus) {
        tab.focus();
      }
    }

    selectors.tabs.activate = activate;

    function focusByIndex(index) {
      const normalizedIndex = (index + tabButtons.length) % tabButtons.length;
      const target = tabButtons[normalizedIndex];
      if (target) {
        activate(target.dataset.eventsTab, { focus: true });
      }
    }

    selectors.tabs.root.addEventListener("keydown", (event) => {
      const target =
        event.target instanceof HTMLElement
          ? event.target.closest("[data-events-tab]")
          : null;
      if (!target) {
        return;
      }

      const currentIndex = tabButtons.findIndex((button) => button === target);
      if (currentIndex === -1) {
        return;
      }

      switch (event.key) {
        case "ArrowRight":
        case "ArrowDown":
          event.preventDefault();
          focusByIndex(currentIndex + 1);
          break;
        case "ArrowLeft":
        case "ArrowUp":
          event.preventDefault();
          focusByIndex(currentIndex - 1);
          break;
        case "Home":
          event.preventDefault();
          focusByIndex(0);
          break;
        case "End":
          event.preventDefault();
          focusByIndex(tabButtons.length - 1);
          break;
        default:
          break;
      }
    });

    tabButtons.forEach((button) => {
      if (!button.dataset.eventsTab) {
        return;
      }

      button.addEventListener("click", () => {
        activate(button.dataset.eventsTab, { focus: true });
      });

      button.addEventListener("keydown", (event) => {
        if (
          event.key === "Enter" ||
          event.key === " " ||
          event.key === "Spacebar"
        ) {
          event.preventDefault();
          activate(button.dataset.eventsTab, { focus: true });
        }
      });
    });

    root.classList.add("events-dashboard--tabs-ready");
    tabPanels.forEach((section) => {
      section.hidden = !section.classList.contains("is-active");
    });
    activate(activeId);
  }

  function initializeUpcoming() {
    const upcomingSelectors = selectors.upcoming;
    if (!upcomingSelectors) {
      return;
    }

    const { viewButtons, panels, calendar } = upcomingSelectors;

    if (
      Array.isArray(viewButtons) &&
      viewButtons.length > 0 &&
      Array.isArray(panels) &&
      panels.length > 0
    ) {
      setUpcomingView(state.upcomingView);

      viewButtons.forEach((button) => {
        button.addEventListener("click", () => {
          const targetView = button.dataset.eventsUpcomingView || "list";
          if (state.upcomingView !== targetView) {
            setUpcomingView(targetView);
          }
        });

        button.addEventListener("keydown", (event) => {
          if (
            event.key === "Enter" ||
            event.key === " " ||
            event.key === "Spacebar"
          ) {
            event.preventDefault();
            const targetView = button.dataset.eventsUpcomingView || "list";
            if (state.upcomingView !== targetView) {
              setUpcomingView(targetView);
            }
          }
        });
      });
    }

    if (calendar?.prev) {
      calendar.prev.addEventListener("click", () => {
        changeCalendarMonth(-1);
      });
    }

    if (calendar?.next) {
      calendar.next.addEventListener("click", () => {
        changeCalendarMonth(1);
      });
    }
  }

  function setUpcomingView(view) {
    const upcomingSelectors = selectors.upcoming;
    if (!upcomingSelectors) {
      state.upcomingView = view;
      return;
    }

    const { viewButtons = [], panels = [] } = upcomingSelectors;
    state.upcomingView = view;

    viewButtons.forEach((button) => {
      const isActive = (button.dataset.eventsUpcomingView || "list") === view;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
    });

    panels.forEach((panel) => {
      const isActive = (panel.dataset.eventsUpcomingPanel || "list") === view;
      panel.classList.toggle("is-active", isActive);
      panel.hidden = !isActive;
    });

    if (view === "calendar") {
      renderUpcomingCalendar();
    }
  }

  function changeCalendarMonth(offset) {
    const todayMonth = startOfMonth(new Date());
    const currentMonth = state.calendar.currentMonth
      ? startOfMonth(state.calendar.currentMonth)
      : todayMonth;
    let target = new Date(
      currentMonth.getFullYear(),
      currentMonth.getMonth() + offset,
      1,
    );
    const latestMonth = getLatestEventMonth();

    if (offset < 0 && target < todayMonth) {
      target = todayMonth;
    }

    if (offset > 0 && latestMonth && target > latestMonth) {
      target = startOfMonth(latestMonth);
    }

    if (offset > 0 && !latestMonth) {
      target = todayMonth;
    }

    state.calendar.currentMonth = startOfMonth(target);
    renderUpcomingCalendar();
  }

  function formatDate(value) {
    if (!value) {
      return "Date TBD";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "Date TBD";
    }
    return new Intl.DateTimeFormat("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }).format(date);
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
      minimumFractionDigits: 2,
    }).format(Number(value || 0));
  }

  const percentageFormatter = new Intl.NumberFormat("en-US", {
    style: "percent",
    maximumFractionDigits: 1,
  });

  function formatPercentage(value) {
    const number = Number(value);
    if (!Number.isFinite(number) || number <= 0) {
      return "0%";
    }
    return percentageFormatter.format(Math.max(0, Math.min(1, number)));
  }

  function getInputDateValue(value) {
    if (!value) {
      return "";
    }
    if (
      typeof value === "string" &&
      value.length >= 16 &&
      value.includes("T")
    ) {
      return value.slice(0, 16);
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
  }

  function withSchedule(event) {
    if (!event || typeof event !== "object") {
      return { status: "draft", schedule_note: "", schedule_type: null };
    }
    const result = { ...event };
    const now = Date.now();
    const publishRaw = result.publish_at ?? "";
    const unpublishRaw = result.unpublish_at ?? "";
    const publishTime = publishRaw ? Date.parse(publishRaw) : Number.NaN;
    const unpublishTime = unpublishRaw ? Date.parse(unpublishRaw) : Number.NaN;

    let status = String(result.status || "draft").toLowerCase();

    if (!Number.isNaN(unpublishTime) && unpublishTime <= now) {
      status = "ended";
    } else if (
      !Number.isNaN(publishTime) &&
      publishTime <= now &&
      status !== "ended"
    ) {
      status = "published";
    } else if (
      !Number.isNaN(publishTime) &&
      publishTime > now &&
      status === "published"
    ) {
      status = "draft";
    }

    const upcoming = [];
    if (status !== "ended" && !Number.isNaN(publishTime) && publishTime > now) {
      upcoming.push({ type: "publish", time: publishTime });
    }
    if (
      status !== "ended" &&
      !Number.isNaN(unpublishTime) &&
      unpublishTime > now
    ) {
      upcoming.push({ type: "unpublish", time: unpublishTime });
    }

    upcoming.sort((a, b) => a.time - b.time);

    let scheduleType = null;
    let scheduleNote = "";
    if (upcoming.length > 0) {
      const nextChange = upcoming[0];
      scheduleType = nextChange.type;
      const label = formatDate(new Date(nextChange.time));
      scheduleNote =
        nextChange.type === "publish" ? `Publishes ${label}` : `Ends ${label}`;
    }

    result.status = status;
    result.schedule_type = scheduleType;
    result.schedule_note = scheduleNote;

    return result;
  }

  function getEventStatusFromForm(form) {
    if (!form) {
      return "draft";
    }
    const status =
      form.querySelector('[name="status"]:checked')?.value || "draft";
    const publishAt = form.querySelector('[name="publish_at"]')?.value || "";
    const unpublishAt =
      form.querySelector('[name="unpublish_at"]')?.value || "";
    const derived = withSchedule({
      status,
      publish_at: publishAt,
      unpublish_at: unpublishAt,
    });
    return derived.status || status;
  }

  function updateEventModalStatusBadge(form) {
    const badge = selectors.modal?.querySelector("[data-events-status-badge]");
    if (!badge) {
      return;
    }
    applyStatusBadge(badge, getEventStatusFromForm(form));
  }

  function normalizeFormOption(form) {
    if (!form || typeof form !== "object") {
      return null;
    }
    const id = String(form.id ?? "").trim();
    const name = String(form.name ?? "").trim();
    if (id === "" || name === "") {
      return null;
    }
    return { id, name };
  }

  function getFormName(formId) {
    const id = String(formId ?? "").trim();
    if (id === "") {
      return "";
    }
    const match = state.forms.find((form) => form.id === id);
    return match ? match.name : "";
  }

  function normalizeReportRow(report) {
    if (!report || typeof report !== "object") {
      return null;
    }
    const eventId = String(report.event_id ?? "").trim();
    const title = report.title ?? "Event";
    const ticketsSold = Number(report.tickets_sold ?? 0) || 0;
    const revenue = Number(report.revenue ?? 0) || 0;
    const refunded = Number(report.refunded ?? 0) || 0;
    const capacity = Number(report.capacity ?? 0) || 0;
    const netRevenue =
      Number(report.net_revenue ?? report.netRevenue ?? revenue - refunded) ||
      0;
    const averageOrder =
      Number(report.average_order ?? report.averageOrder ?? 0) || 0;
    const orders = Number(report.orders ?? 0) || 0;
    const paidOrders =
      Number(report.paid_orders ?? report.paidOrders ?? 0) || 0;
    const pendingOrders =
      Number(report.pending_orders ?? report.pendingOrders ?? 0) || 0;
    let sellThrough = Number(
      report.sell_through_rate ?? report.sellThroughRate ?? Number.NaN,
    );
    if (!Number.isFinite(sellThrough)) {
      sellThrough = capacity > 0 ? ticketsSold / capacity : 0;
    }
    sellThrough = Math.max(0, Math.min(1, sellThrough));
    const status = String(report.status ?? "draft").toLowerCase();
    return {
      event_id: eventId,
      title,
      tickets_sold: ticketsSold,
      revenue,
      refunded,
      net_revenue: netRevenue,
      average_order: averageOrder,
      orders,
      paid_orders: paidOrders,
      pending_orders: pendingOrders,
      capacity,
      sell_through_rate: sellThrough,
      status,
    };
  }

  function normalizeEventRecord(event) {
    if (!event || typeof event !== "object") {
      return null;
    }
    const id = String(event.id ?? "").trim();
    if (id === "") {
      return null;
    }
    const ticketsSold = Number(event.tickets_sold ?? 0) || 0;
    const capacity = Number(event.capacity ?? 0) || 0;
    const revenue = Number(event.revenue ?? 0) || 0;
    const refunded = Number(event.refunded ?? 0) || 0;
    const netRevenue =
      Number(event.net_revenue ?? event.netRevenue ?? revenue - refunded) || 0;
    let sellThrough = Number(
      event.sell_through_rate ?? event.sellThroughRate ?? Number.NaN,
    );
    if (!Number.isFinite(sellThrough)) {
      sellThrough = capacity > 0 ? ticketsSold / capacity : 0;
    }
    sellThrough = Math.max(0, Math.min(1, sellThrough));
    const formId = String(event.form_id ?? "").trim();
    const base = {
      ...event,
      id,
      title: event.title ?? "Untitled Event",
      location: event.location ?? "",
      start: event.start ?? "",
      end: event.end ?? "",
      image: event.image ?? "",
      status: String(event.status ?? "draft").toLowerCase(),
      publish_at: event.publish_at ?? "",
      unpublish_at: event.unpublish_at ?? "",
      categories: Array.isArray(event.categories) ? event.categories : [],
      tickets_sold: ticketsSold,
      revenue,
      net_revenue: netRevenue,
      average_order:
        Number(event.average_order ?? event.averageOrder ?? 0) || 0,
      orders: Number(event.orders ?? 0) || 0,
      paid_orders: Number(event.paid_orders ?? event.paidOrders ?? 0) || 0,
      pending_orders:
        Number(event.pending_orders ?? event.pendingOrders ?? 0) || 0,
      capacity,
      sell_through_rate: sellThrough,
      form_id: formId,
      form_name: getFormName(formId),
    };
    return withSchedule(base);
  }

  function storeEvent(event) {
    if (!event || typeof event !== "object" || !event.id) {
      return;
    }
    const id = String(event.id);
    const existing = state.events.get(id) || {};
    const merged = {
      ...existing,
      ...event,
    };
    merged.id = id;
    if (!Array.isArray(merged.categories)) {
      merged.categories = Array.isArray(event.categories)
        ? event.categories
        : Array.isArray(existing.categories)
          ? existing.categories
          : [];
    }
    if (!Array.isArray(merged.tickets)) {
      merged.tickets = Array.isArray(event.tickets)
        ? event.tickets
        : Array.isArray(existing.tickets)
          ? existing.tickets
          : [];
    }
    merged.publish_at = merged.publish_at ?? "";
    merged.unpublish_at = merged.unpublish_at ?? "";
    merged.status = String(merged.status ?? "draft").toLowerCase();
    merged.form_id = String(merged.form_id ?? "").trim();
    merged.form_name = getFormName(merged.form_id);
    state.events.set(id, withSchedule(merged));
  }

  function normalizeUpcomingItem(item) {
    if (!item || typeof item !== "object") {
      return null;
    }

    return {
      id: String(item.id ?? ""),
      title: item.title ?? "Untitled event",
      start: item.start ?? "",
      end: item.end ?? "",
      tickets_sold: Number.parseInt(item.tickets_sold ?? 0, 10) || 0,
      revenue: Number(item.revenue ?? 0) || 0,
    };
  }

  function parseDateValue(value) {
    if (!value) {
      return null;
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return null;
    }
    return date;
  }

  function startOfMonth(date) {
    const reference = date instanceof Date ? date : new Date(date);
    if (Number.isNaN(reference.getTime())) {
      const fallback = new Date();
      return new Date(fallback.getFullYear(), fallback.getMonth(), 1);
    }
    return new Date(reference.getFullYear(), reference.getMonth(), 1);
  }

  function formatMonthLabel(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return "";
    }
    return new Intl.DateTimeFormat("en-US", {
      month: "long",
      year: "numeric",
    }).format(date);
  }

  function formatTimeLabel(value) {
    const date = parseDateValue(value);
    if (!date) {
      return "";
    }
    return new Intl.DateTimeFormat("en-US", {
      hour: "numeric",
      minute: "2-digit",
    }).format(date);
  }

  function formatDateTimeLocal(value) {
    if (!value) {
      return "";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");
    return `${year}-${month}-${day}T${hours}:${minutes}`;
  }

  function formatDateKey(date) {
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${date.getFullYear()}-${month}-${day}`;
  }

  function startOfDay(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return null;
    }
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function endOfMonth(date) {
    const reference = date instanceof Date ? date : new Date(date);
    if (Number.isNaN(reference.getTime())) {
      return null;
    }
    return new Date(
      reference.getFullYear(),
      reference.getMonth() + 1,
      0,
      23,
      59,
      59,
      999,
    );
  }

  function compareUpcomingByStart(a, b) {
    const dateA = parseDateValue(a?.start);
    const dateB = parseDateValue(b?.start);
    if (!dateA && !dateB) {
      return 0;
    }
    if (!dateA) {
      return 1;
    }
    if (!dateB) {
      return -1;
    }
    return dateA.getTime() - dateB.getTime();
  }

  function eventOccursInMonth(event, monthDate) {
    if (!(monthDate instanceof Date) || Number.isNaN(monthDate.getTime())) {
      return false;
    }

    const monthStart = startOfMonth(monthDate);
    const monthEnd = endOfMonth(monthDate);
    if (!monthStart || !monthEnd) {
      return false;
    }

    const startDate = parseDateValue(event?.start);
    const normalizedStart = startOfDay(startDate);
    if (!normalizedStart) {
      return false;
    }

    const endDate = parseDateValue(event?.end);
    let normalizedEnd = startOfDay(endDate);
    if (!normalizedEnd || normalizedEnd.getTime() < normalizedStart.getTime()) {
      normalizedEnd = normalizedStart;
    }

    return (
      normalizedStart.getTime() <= monthEnd.getTime() &&
      normalizedEnd.getTime() >= monthStart.getTime()
    );
  }

  function getEarliestEventMonth(list = state.upcoming) {
    let earliest = null;
    list.forEach((item) => {
      const candidates = [parseDateValue(item.start), parseDateValue(item.end)];
      candidates.forEach((candidate) => {
        if (!candidate) {
          return;
        }
        const month = startOfMonth(candidate);
        if (!earliest || month.getTime() < earliest.getTime()) {
          earliest = month;
        }
      });
    });
    return earliest;
  }

  function getLatestEventMonth(list = state.upcoming) {
    let latest = null;
    list.forEach((item) => {
      const candidates = [parseDateValue(item.start), parseDateValue(item.end)];
      candidates.forEach((candidate) => {
        if (!candidate) {
          return;
        }
        const month = startOfMonth(candidate);
        if (!latest || month.getTime() > latest.getTime()) {
          latest = month;
        }
      });
    });
    return latest;
  }

  function isSameMonth(dateA, dateB) {
    if (!(dateA instanceof Date) || !(dateB instanceof Date)) {
      return false;
    }
    return (
      dateA.getFullYear() === dateB.getFullYear() &&
      dateA.getMonth() === dateB.getMonth()
    );
  }

  function toLocalDateTimeInput(value) {
    if (!value) {
      return "";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60000);
    return local.toISOString().slice(0, 16);
  }

  function fromLocalDateTimeInput(value) {
    if (!value) {
      return "";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    return new Date(
      date.getTime() - date.getTimezoneOffset() * 60000,
    ).toISOString();
  }

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (character) => {
      switch (character) {
        case "&":
          return "&amp;";
        case "<":
          return "&lt;";
        case ">":
          return "&gt;";
        case '"':
          return "&quot;";
        case "'":
          return "&#39;";
        default:
          return character;
      }
    });
  }

  function escapeAttribute(value) {
    return escapeHtml(value);
  }

  function slugifyFieldName(value) {
    return String(value || "")
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "")
      .slice(0, 60);
  }

  function generateUniqueFieldName(base, currentInput) {
    const sanitizedBase = slugifyFieldName(base) || "field";
    const container = selectors.formBuilder.fields;
    if (!container) {
      return sanitizedBase;
    }
    const existing = new Set();
    container
      .querySelectorAll("[data-events-builder-name]")
      .forEach((input) => {
        if (input === currentInput) {
          return;
        }
        const value = String(input.value || "").trim();
        if (value !== "") {
          existing.add(value);
        }
      });
    if (!existing.has(sanitizedBase)) {
      return sanitizedBase;
    }
    let index = 2;
    let candidate = `${sanitizedBase}_${index}`;
    while (existing.has(candidate)) {
      index += 1;
      candidate = `${sanitizedBase}_${index}`;
    }
    return candidate;
  }

  function shouldShowBuilderOptions(type) {
    const normalized = String(type || "").toLowerCase();
    return ["select", "radio", "checkbox"].includes(normalized);
  }

  function showBuilderAlert(message, tone = "error") {
    const alert = selectors.formBuilder.alert;
    if (!alert) {
      return;
    }
    alert.textContent = message;
    alert.classList.remove(
      "events-builder-alert--error",
      "events-builder-alert--success",
    );
    alert.classList.add(
      tone === "success"
        ? "events-builder-alert--success"
        : "events-builder-alert--error",
    );
    alert.hidden = false;
  }

  function hideBuilderAlert() {
    const alert = selectors.formBuilder.alert;
    if (!alert) {
      return;
    }
    alert.classList.remove(
      "events-builder-alert--error",
      "events-builder-alert--success",
    );
    alert.hidden = true;
    alert.textContent = "";
  }

  function updateBuilderEmptyState() {
    const container = selectors.formBuilder.fields;
    const emptyState = selectors.formBuilder.empty;
    if (!container || !emptyState) {
      return;
    }
    const hasFields = container.querySelector("[data-events-builder-field]");
    emptyState.hidden = Boolean(hasFields);
  }

  function setBuilderSaving(isSaving) {
    const submit = selectors.formBuilder.submit;
    if (submit) {
      if (isSaving) {
        if (!submit.dataset.originalLabel) {
          submit.dataset.originalLabel = submit.textContent || "Save form";
        }
        submit.textContent = "Saving…";
        submit.disabled = true;
      } else {
        submit.disabled = false;
        submit.textContent = submit.dataset.originalLabel || "Save form";
      }
    }
    const addButton = selectors.formBuilder.addButton;
    if (addButton) {
      addButton.disabled = Boolean(isSaving);
    }
  }

  function resetFormBuilder() {
    const builder = selectors.formBuilder;
    if (!builder.form || !builder.fields) {
      return;
    }
    builder.form.reset();
    builder.fields.innerHTML = "";
    hideBuilderAlert();
    setBuilderSaving(false);
    addBuilderField();
    updateBuilderEmptyState();
  }

  function addBuilderField(field = {}) {
    const container = selectors.formBuilder.fields;
    if (!container) {
      return;
    }
    hideBuilderAlert();
    const wrapper = document.createElement("div");
    wrapper.className = "events-builder-field";
    wrapper.dataset.eventsBuilderField = "";
    wrapper.innerHTML = `
            <div class="events-builder-field-row">
                <label>
                    <span>Field label</span>
                    <input type="text" data-events-builder-label required>
                </label>
                <label>
                    <span>Field name</span>
                    <input type="text" data-events-builder-name>
                </label>
                <label>
                    <span>Field type</span>
                    <select data-events-builder-type>
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="number">Number</option>
                        <option value="textarea">Paragraph</option>
                        <option value="select">Dropdown</option>
                        <option value="radio">Multiple choice</option>
                        <option value="checkbox">Checkbox</option>
                    </select>
                </label>
                <label class="events-builder-inline">
                    <input type="checkbox" data-events-builder-required>
                    <span>Required</span>
                </label>
            </div>
            <button type="button" class="events-builder-remove" data-events-builder-remove>
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                <span class="sr-only">Remove field</span>
            </button>
            <label class="events-builder-options" data-events-builder-options hidden>
                <span>Options (one per line)</span>
                <textarea data-events-builder-options-input rows="3" placeholder="Add each option on a new line"></textarea>
            </label>
        `;
    container.appendChild(wrapper);
    const labelInput = wrapper.querySelector("[data-events-builder-label]");
    const nameInput = wrapper.querySelector("[data-events-builder-name]");
    const typeSelect = wrapper.querySelector("[data-events-builder-type]");
    const requiredInput = wrapper.querySelector(
      "[data-events-builder-required]",
    );
    const optionsWrapper = wrapper.querySelector(
      "[data-events-builder-options]",
    );
    const optionsInput = wrapper.querySelector(
      "[data-events-builder-options-input]",
    );
    const removeBtn = wrapper.querySelector("[data-events-builder-remove]");
    const initialType = String(field.type || "text").toLowerCase();
    if (labelInput) {
      labelInput.value = field.label || "";
    }
    if (typeSelect) {
      typeSelect.value = initialType;
      if (typeSelect.value !== initialType) {
        typeSelect.value = "text";
      }
    }
    if (requiredInput) {
      requiredInput.checked = Boolean(field.required);
    }
    if (optionsInput && typeof field.options === "string") {
      optionsInput.value = field.options;
    }
    const applyGeneratedName = () => {
      if (!nameInput || nameInput.dataset.manual === "true") {
        return;
      }
      const base = slugifyFieldName(labelInput?.value || "");
      const unique = generateUniqueFieldName(base || "field", nameInput);
      nameInput.value = unique;
    };
    if (nameInput) {
      if (field.name) {
        const sanitized = generateUniqueFieldName(field.name, nameInput);
        nameInput.value = sanitized;
        nameInput.dataset.manual = "true";
      } else {
        applyGeneratedName();
      }
      nameInput.addEventListener("input", () => {
        if (!nameInput) {
          return;
        }
        const sanitized = slugifyFieldName(nameInput.value);
        if (sanitized === "") {
          nameInput.value = "";
          delete nameInput.dataset.manual;
          applyGeneratedName();
          return;
        }
        const unique = generateUniqueFieldName(sanitized, nameInput);
        nameInput.value = unique;
        nameInput.dataset.manual = "true";
      });
    }
    if (labelInput) {
      labelInput.addEventListener("input", () => {
        if (nameInput?.dataset.manual === "true") {
          return;
        }
        applyGeneratedName();
      });
    }
    if (typeSelect && optionsWrapper) {
      const updateOptionsVisibility = () => {
        optionsWrapper.hidden = !shouldShowBuilderOptions(typeSelect.value);
      };
      typeSelect.addEventListener("change", updateOptionsVisibility);
      updateOptionsVisibility();
    }
    if (removeBtn) {
      removeBtn.addEventListener("click", () => {
        wrapper.remove();
        updateBuilderEmptyState();
        hideBuilderAlert();
      });
    }
    if (labelInput && !field.label) {
      labelInput.focus();
    }
    updateBuilderEmptyState();
  }

  function gatherBuilderFields() {
    const container = selectors.formBuilder.fields;
    if (!container) {
      return [];
    }
    const fields = [];
    const fieldElements = container.querySelectorAll(
      "[data-events-builder-field]",
    );
    fieldElements.forEach((fieldElement) => {
      const labelInput = fieldElement.querySelector(
        "[data-events-builder-label]",
      );
      const nameInput = fieldElement.querySelector(
        "[data-events-builder-name]",
      );
      const typeSelect = fieldElement.querySelector(
        "[data-events-builder-type]",
      );
      const optionsWrapper = fieldElement.querySelector(
        "[data-events-builder-options]",
      );
      const optionsInput = fieldElement.querySelector(
        "[data-events-builder-options-input]",
      );
      const requiredInput = fieldElement.querySelector(
        "[data-events-builder-required]",
      );
      const label = String(labelInput?.value || "").trim();
      const name = slugifyFieldName(nameInput?.value || "");
      if (label === "" || name === "") {
        throw {
          type: "validation",
          message: "Each field needs a label and field name.",
          element: labelInput || nameInput,
        };
      }
      const type = String(typeSelect?.value || "text").toLowerCase();
      const fieldData = { type, label, name };
      if (requiredInput) {
        fieldData.required = requiredInput.checked;
      }
      if (optionsWrapper && !optionsWrapper.hidden && optionsInput) {
        const options = optionsInput.value
          .split(/\r?\n/)
          .map((line) => line.trim())
          .filter(Boolean)
          .join(", ");
        if (options) {
          fieldData.options = options;
        }
      }
      fields.push(fieldData);
    });
    return fields;
  }

  function handleFormBuilderSubmit(event) {
    event.preventDefault();
    const builder = selectors.formBuilder;
    if (!builder.form) {
      return;
    }
    hideBuilderAlert();
    const nameInput = builder.form.querySelector(
      "[data-events-builder-form-name]",
    );
    const formName = String(nameInput?.value || "").trim();
    if (formName === "") {
      showBuilderAlert("Form name is required.");
      nameInput?.focus();
      return;
    }
    const container = builder.fields;
    if (!container || !container.querySelector("[data-events-builder-field]")) {
      showBuilderAlert(
        "Add at least one field to build your registration form.",
      );
      return;
    }
    let fields;
    try {
      fields = gatherBuilderFields();
    } catch (error) {
      if (error && error.type === "validation") {
        showBuilderAlert(error.message);
        if (error.element && typeof error.element.focus === "function") {
          error.element.focus();
        }
      } else {
        showBuilderAlert(
          "Unable to process the form fields. Please review and try again.",
        );
      }
      return;
    }
    if (fields.length === 0) {
      showBuilderAlert(
        "Add at least one field to build your registration form.",
      );
      return;
    }
    const payload = new FormData();
    const id = builder.form.querySelector('[name="id"]')?.value || "";
    if (id) {
      payload.append("id", id);
    }
    payload.append("name", formName);
    payload.append("fields", JSON.stringify(fields));
    payload.append("confirmation_email", JSON.stringify({ enabled: false }));
    setBuilderSaving(true);
    fetch("modules/forms/save_form.php", {
      method: "POST",
      body: payload,
      credentials: "same-origin",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Request failed");
        }
        return response.text();
      })
      .then((text) => {
        if (
          typeof text !== "string" ||
          text.toLowerCase().indexOf("ok") === -1
        ) {
          throw new Error("Unexpected response");
        }
        const targetSelect = state.formBuilder.targetSelect;
        closeModal(selectors.formBuilder.modal);
        state.formBuilder.targetSelect = targetSelect;
        showToast("Form saved successfully.");
        return refreshForms({
          target: targetSelect,
          selectName: formName,
        }).catch(() => {
          showToast("Form saved but the list could not be refreshed.", "error");
        });
      })
      .catch(() => {
        showBuilderAlert("Unable to save the form. Please try again.");
      })
      .finally(() => {
        setBuilderSaving(false);
        state.formBuilder.targetSelect = null;
      });
  }

  function openFormBuilder(trigger) {
    const builder = selectors.formBuilder;
    if (!builder.modal) {
      return;
    }
    const selectFromTrigger = trigger
      ? trigger
          .closest(".events-form-card")
          ?.querySelector("[data-events-event-form]")
      : null;
    state.formBuilder.targetSelect =
      selectFromTrigger ||
      selectors.modal?.querySelector("[data-events-event-form]") ||
      null;
    resetFormBuilder();
    openModal(builder.modal);
    builder.form?.querySelector("[data-events-builder-form-name]")?.focus();
  }

  function initFormBuilder() {
    const builder = selectors.formBuilder;
    if (!builder.modal || !builder.form || !builder.fields) {
      return;
    }
    if (builder.addButton) {
      builder.addButton.addEventListener("click", (event) => {
        event.preventDefault();
        addBuilderField();
      });
    }
    builder.form.addEventListener("submit", handleFormBuilderSubmit);
    builder.form.addEventListener("input", () => {
      hideBuilderAlert();
    });
  }

  function refreshForms(preferences = {}) {
    return fetch("modules/forms/list_forms.php", { credentials: "same-origin" })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Request failed");
        }
        return response.json();
      })
      .then((forms) => {
        const normalized = Array.isArray(forms)
          ? forms
              .map((form) => normalizeFormOption(form))
              .filter((form) => form !== null)
          : [];
        state.forms = normalized;
        const targetSelect =
          preferences.target ||
          state.formBuilder.targetSelect ||
          selectors.modal?.querySelector("[data-events-event-form]") ||
          null;
        let selectedId = "";
        if (preferences.selectId) {
          selectedId = preferences.selectId;
        } else if (preferences.selectName) {
          const matchByName = normalized.find(
            (form) => form.name === preferences.selectName,
          );
          selectedId = matchByName ? matchByName.id : "";
        } else if (targetSelect) {
          selectedId = targetSelect.value;
        }
        if (targetSelect) {
          renderEventFormOptions(targetSelect, selectedId);
          if (preferences.selectName) {
            const match = normalized.find(
              (form) => form.name === preferences.selectName,
            );
            if (match) {
              targetSelect.value = match.id;
            }
          }
        }
        state.events.forEach((event, id) => {
          if (event && typeof event === "object") {
            event.form_name = getFormName(event.form_id);
            state.events.set(id, event);
          }
        });
        state.eventRows = state.eventRows.map((row) => {
          if (!row || typeof row !== "object") {
            return row;
          }
          return { ...row, form_name: getFormName(row.form_id) };
        });
        renderEventsTable();
        return normalized;
      });
  }

  function sortCategories(list) {
    if (!Array.isArray(list)) {
      return [];
    }
    return list
      .slice()
      .filter((item) => item && item.id && item.name)
      .sort((a, b) =>
        String(a.name || "").localeCompare(String(b.name || ""), undefined, {
          sensitivity: "base",
        }),
      );
  }

  function normalizeOrderRow(order) {
    if (!order || typeof order !== "object") {
      return null;
    }
    const id = String(order.id || "").trim();
    if (id === "") {
      return null;
    }
    const eventId = String(order.event_id || "").trim();
    const event = eventId ? state.events.get(eventId) : null;
    const ticketLookup = (() => {
      if (!event || !Array.isArray(event.tickets)) {
        return new Map();
      }
      return new Map(
        event.tickets
          .filter((ticket) => ticket && ticket.id)
          .map((ticket) => [String(ticket.id), ticket]),
      );
    })();

    let lineItems = [];
    if (Array.isArray(order.line_items) && order.line_items.length > 0) {
      lineItems = order.line_items;
    } else if (Array.isArray(order.tickets)) {
      lineItems = order.tickets.map((ticket) => ({
        ticket_id: ticket.ticket_id,
        quantity: ticket.quantity,
        price: ticket.price,
      }));
    }

    const normalizedLines = [];
    let ticketsTotal = 0;
    let amountTotal = 0;

    lineItems.forEach((item) => {
      if (!item || typeof item !== "object") {
        return;
      }
      const ticketId = String(item.ticket_id || "").trim();
      if (ticketId === "") {
        return;
      }
      const ticketInfo = ticketLookup.get(ticketId) || {};
      const name = item.name || ticketInfo.name || "Ticket";
      const price = Math.max(
        0,
        Number.parseFloat(item.price ?? ticketInfo.price ?? 0),
      );
      const quantity = Math.max(0, Number.parseInt(item.quantity ?? 0, 10));
      const subtotal = price * quantity;
      ticketsTotal += quantity;
      amountTotal += subtotal;
      normalizedLines.push({
        ticket_id: ticketId,
        name,
        price,
        quantity,
        subtotal,
      });
    });

    const status = String(order.status || "paid").toLowerCase();
    const orderedAt = order.ordered_at || "";
    const fallbackAmount = Number(order.amount || 0) || 0;
    const computedAmount = Number.isFinite(amountTotal) ? amountTotal : 0;

    return {
      id,
      event_id: eventId,
      event: event
        ? event.title || "Untitled event"
        : String(order.event || ""),
      buyer_name: order.buyer_name || "",
      buyer_email: order.buyer_email ? String(order.buyer_email) : "",
      buyer_phone: order.buyer_phone ? String(order.buyer_phone) : "",
      tickets: ticketsTotal,
      amount: computedAmount || fallbackAmount,
      status,
      ordered_at: orderedAt,
      line_items: normalizedLines,
    };
  }

  function getCategoryOptionsContainer() {
    return (
      selectors.modal?.querySelector("[data-events-category-options]") || null
    );
  }

  function getSelectedCategoryIds() {
    const container = getCategoryOptionsContainer();
    if (!container) {
      return [];
    }
    return Array.from(
      container.querySelectorAll('input[name="categories[]"]:checked'),
    ).map((input) => input.value);
  }

  function renderCategoryOptions(selectedIds = []) {
    const container = getCategoryOptionsContainer();
    if (!container) {
      return;
    }
    const selectedSet = new Set(
      Array.isArray(selectedIds) ? selectedIds.map((id) => String(id)) : [],
    );
    container.innerHTML = "";
    if (!Array.isArray(state.categories) || state.categories.length === 0) {
      const empty = document.createElement("p");
      empty.className = "events-category-empty";
      empty.textContent = "No categories yet. Manage categories to create one.";
      container.appendChild(empty);
      return;
    }
    state.categories.forEach((category) => {
      const label = document.createElement("label");
      label.className = "events-category-item";
      label.innerHTML = `
                <input type="checkbox" name="categories[]" value="${category.id}">
                <span>${category.name}</span>
            `;
      const input = label.querySelector("input");
      if (input && selectedSet.has(String(category.id))) {
        input.checked = true;
      }
      container.appendChild(label);
    });
  }

  function renderCategoryList() {
    const list = selectors.categoriesList;
    if (!list) {
      return;
    }
    list.innerHTML = "";
    if (!Array.isArray(state.categories) || state.categories.length === 0) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 4;
      cell.className = "events-empty";
      cell.textContent = "No categories yet. Create one above.";
      row.appendChild(cell);
      list.appendChild(row);
      return;
    }
    state.categories.forEach((category) => {
      const row = document.createElement("tr");
      const updatedLabel = category.updated_at
        ? formatDate(category.updated_at)
        : "—";
      row.innerHTML = `
                <td>${category.name}</td>
                <td>${category.slug || ""}</td>
                <td>${updatedLabel}</td>
                <td class="events-table-actions">
                    <button type="button" class="events-action" data-events-category-edit data-id="${category.id}">
                        <i class="fa-solid fa-pen"></i><span class="sr-only">Edit</span>
                    </button>
                    <button type="button" class="events-action danger" data-events-category-delete data-id="${category.id}">
                        <i class="fa-solid fa-trash"></i><span class="sr-only">Delete</span>
                    </button>
                </td>
            `;
      list.appendChild(row);
    });
  }

  function updateCategoryFormMode() {
    if (selectors.categoriesFormTitle) {
      selectors.categoriesFormTitle.textContent = state.categoryEditing
        ? "Edit category"
        : "Create category";
    }
    if (selectors.categoriesSubmit) {
      selectors.categoriesSubmit.textContent = state.categoryEditing
        ? "Update category"
        : "Save category";
    }
  }

  function resetCategoryForm() {
    if (selectors.categoriesForm) {
      selectors.categoriesForm.reset();
    }
    state.categoryEditing = null;
    updateCategoryFormMode();
  }

  function fillCategoryForm(category) {
    if (!selectors.categoriesForm) {
      return;
    }
    selectors.categoriesForm.querySelector('[name="id"]').value =
      category?.id || "";
    selectors.categoriesForm.querySelector('[name="name"]').value =
      category?.name || "";
    selectors.categoriesForm.querySelector('[name="slug"]').value =
      category?.slug || "";
    state.categoryEditing = category?.id || null;
    updateCategoryFormMode();
  }

  function openCategoriesModal(categoryId = null) {
    if (!selectors.categoriesModal) {
      return;
    }
    renderCategoryList();
    resetCategoryForm();
    if (categoryId) {
      const category = state.categories.find(
        (item) => String(item.id) === String(categoryId),
      );
      if (category) {
        fillCategoryForm(category);
      }
    }
    openModal(selectors.categoriesModal);
  }

  function closeCategoryModal() {
    if (!selectors.categoriesModal) {
      return;
    }
    resetCategoryForm();
    closeModal(selectors.categoriesModal);
  }

  function filterMediaItems(term = "") {
    const normalized = term.trim().toLowerCase();
    return state.media.items.filter((item) => {
      if (!item || (item.type ?? "") !== "images") {
        return false;
      }
      if (!normalized) {
        return true;
      }
      const name = String(item.name || "").toLowerCase();
      const file = String(item.file || "").toLowerCase();
      let tags = "";
      if (Array.isArray(item.tags)) {
        tags = item.tags.join(" ").toLowerCase();
      } else if (typeof item.tags === "string") {
        tags = item.tags.toLowerCase();
      }
      return (
        name.includes(normalized) ||
        file.includes(normalized) ||
        tags.includes(normalized)
      );
    });
  }

  function renderMediaLibrary({
    status = "idle",
    items = [],
    search = "",
  } = {}) {
    const grid = selectors.mediaGrid;
    if (!grid) {
      return;
    }
    grid.setAttribute("aria-busy", status === "loading" ? "true" : "false");
    if (status === "loading") {
      grid.innerHTML = '<p class="events-media-status">Loading media…</p>';
      return;
    }
    if (status === "error") {
      grid.innerHTML =
        '<p class="events-media-status events-media-status--error">Unable to load the media library. Please try again.</p>';
      return;
    }
    const list = Array.isArray(items) ? items.slice() : [];
    if (list.length === 0) {
      if (search) {
        grid.innerHTML = `<p class="events-media-status">No images match &ldquo;${escapeHtml(search)}&rdquo;. Try a different keyword.</p>`;
      } else {
        grid.innerHTML =
          '<p class="events-media-status">No images found in the media library. Upload images in the Media module.</p>';
      }
      return;
    }
    list.sort((a, b) => {
      const aName = String(a.name || a.file || "").toLowerCase();
      const bName = String(b.name || b.file || "").toLowerCase();
      return aName.localeCompare(bName, undefined, { sensitivity: "base" });
    });
    grid.innerHTML = list
      .map((item) => {
        const file = escapeAttribute(item.file || "");
        const name = escapeHtml(item.name || item.file || "Media item");
        const thumbSource = escapeAttribute(item.thumbnail || item.file || "");
        return `
                    <button type="button" class="events-media-item" data-events-media-item data-file="${file}" role="option">
                        <span class="events-media-thumb"><img src="${thumbSource}" alt="${name}"></span>
                        <span class="events-media-name">${name}</span>
                    </button>
                `;
      })
      .join("");
  }

  function loadMediaLibrary() {
    if (state.media.loading) {
      return;
    }
    state.media.loading = true;
    renderMediaLibrary({ status: "loading" });
    fetch("modules/media/list_media.php?sort=name&order=asc")
      .then((response) => {
        if (!response.ok) {
          throw new Error("Unable to load media");
        }
        return response.json();
      })
      .then((data) => {
        state.media.items = Array.isArray(data?.media) ? data.media : [];
        state.media.loaded = true;
        state.media.loading = false;
        const term = selectors.mediaSearch?.value || "";
        renderMediaLibrary({ items: filterMediaItems(term), search: term });
      })
      .catch(() => {
        state.media.loading = false;
        renderMediaLibrary({ status: "error" });
      });
  }

  function openMediaPicker() {
    if (!selectors.mediaModal) {
      return;
    }
    openModal(selectors.mediaModal);
    if (selectors.mediaSearch) {
      selectors.mediaSearch.value = "";
    }
    if (state.media.loaded) {
      renderMediaLibrary({ items: filterMediaItems(""), search: "" });
    } else {
      renderMediaLibrary({ status: "loading" });
      loadMediaLibrary();
    }
    setTimeout(() => {
      selectors.mediaSearch?.focus();
    }, 120);
  }

  function initMediaPicker() {
    if (!selectors.mediaModal) {
      return;
    }
    if (selectors.mediaSearch) {
      selectors.mediaSearch.addEventListener("input", () => {
        if (!state.media.loaded) {
          return;
        }
        const term = selectors.mediaSearch.value || "";
        renderMediaLibrary({ items: filterMediaItems(term), search: term });
      });
    }
    selectors.mediaModal.addEventListener("click", (event) => {
      const item = event.target.closest("[data-events-media-item]");
      if (!item) {
        return;
      }
      event.preventDefault();
      const file = item.dataset.file || "";
      if (file && typeof state.media.currentSetter === "function") {
        state.media.currentSetter(file);
      }
      state.media.currentSetter = null;
      closeModal(selectors.mediaModal);
    });
  }

  function initImagePicker(form) {
    if (!form) {
      return null;
    }
    const picker = form.querySelector("[data-events-image-picker]");
    if (!picker) {
      return null;
    }
    const input = picker.querySelector('input[name="image"]');
    const preview = picker.querySelector("[data-events-image-preview]");
    const chooseBtn = picker.querySelector("[data-events-image-open]");
    const clearBtn = picker.querySelector("[data-events-image-clear]");
    if (!input || !preview || !chooseBtn || !clearBtn) {
      return null;
    }

    function setValue(value) {
      const normalized = typeof value === "string" ? value.trim() : "";
      input.value = normalized;
      if (normalized) {
        preview.innerHTML = `<img src="${escapeAttribute(normalized)}" alt="Event featured image preview">`;
        preview.classList.add("has-image");
        clearBtn.hidden = false;
      } else {
        preview.innerHTML =
          '<span class="events-image-placeholder">No image selected yet.</span>';
        preview.classList.remove("has-image");
        clearBtn.hidden = true;
      }
    }

    chooseBtn.addEventListener("click", (event) => {
      event.preventDefault();
      state.media.currentSetter = setValue;
      openMediaPicker();
    });

    clearBtn.addEventListener("click", (event) => {
      event.preventDefault();
      setValue("");
    });

    input.addEventListener("change", () => {
      setValue(input.value);
    });

    form.addEventListener("reset", () => {
      setTimeout(() => setValue(""), 0);
    });

    setValue(input.value);

    return { setValue };
  }

  function normalizeToastMessage(value) {
    if (value === null || value === undefined) {
      return "";
    }
    const container = document.createElement("div");
    container.innerHTML = String(value);
    const text = (container.textContent || container.innerText || "").trim();
    return text;
  }

  function showToast(message, type = "success") {
    if (!selectors.toast) {
      return;
    }
    const text = normalizeToastMessage(message);
    if (!text) {
      return;
    }
    selectors.toast.dataset.type = type;
    selectors.toast.querySelector("[data-events-toast-message]").textContent =
      text;
    selectors.toast.hidden = false;
    selectors.toast.classList.add("is-visible");
    setTimeout(() => {
      selectors.toast.classList.remove("is-visible");
      selectors.toast.hidden = true;
    }, 2400);
  }

  function buildQuery(params) {
    const query = new URLSearchParams();
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null && String(value) !== "") {
        query.append(key, value);
      }
    });
    return query.toString();
  }

  function fetchJSON(action, options = {}) {
    const method = options.method || "GET";
    const headers = options.headers || {};
    let url = `${endpoint}?action=${encodeURIComponent(action)}`;
    const fetchOptions = { method, headers: { ...headers } };
    if (method === "GET" && options.params) {
      const query = buildQuery(options.params);
      if (query) {
        url += `&${query}`;
      }
    }
    if (method !== "GET" && options.body) {
      fetchOptions.body = JSON.stringify(options.body);
      fetchOptions.headers["Content-Type"] = "application/json";
    }
    return fetch(url, fetchOptions).then((response) => {
      if (!response.ok) {
        throw new Error("Request failed");
      }
      return response.json();
    });
  }

  function renderStats(stats) {
    if (selectors.stats.events) {
      selectors.stats.events.textContent =
        stats.total_events ?? state.eventRows.length;
    }
    if (selectors.stats.tickets) {
      selectors.stats.tickets.textContent = stats.total_tickets_sold ?? 0;
    }
    if (selectors.stats.revenue) {
      const revenue = stats.total_revenue ?? 0;
      selectors.stats.revenue.textContent = formatCurrency(revenue);
    }
  }

  function renderUpcoming(list) {
    const normalized = Array.isArray(list)
      ? list
          .map((item) => normalizeUpcomingItem(item))
          .filter((item) => item !== null)
      : [];

    state.upcoming = normalized;
    const todayMonth = startOfMonth(new Date());
    const earliestMonth = getEarliestEventMonth(normalized);
    const latestMonth = getLatestEventMonth(normalized);

    if (!state.calendar.currentMonth) {
      state.calendar.currentMonth = earliestMonth
        ? startOfMonth(earliestMonth)
        : todayMonth;
    } else {
      state.calendar.currentMonth = startOfMonth(state.calendar.currentMonth);
      if (state.calendar.currentMonth.getTime() < todayMonth.getTime()) {
        state.calendar.currentMonth = todayMonth;
      }
      if (
        latestMonth &&
        state.calendar.currentMonth.getTime() > latestMonth.getTime()
      ) {
        state.calendar.currentMonth = startOfMonth(latestMonth);
      }
    }

    if (normalized.length > 0) {
      const monthHasEvent = normalized.some((item) =>
        eventOccursInMonth(item, state.calendar.currentMonth),
      );
      if (!monthHasEvent && earliestMonth) {
        state.calendar.currentMonth = startOfMonth(earliestMonth);
      }
    } else {
      state.calendar.currentMonth = todayMonth;
    }

    renderUpcomingList(normalized);
    renderUpcomingCalendar();
  }

  function renderUpcomingList(list) {
    const listElement = selectors.upcoming?.list;
    if (!listElement) {
      return;
    }

    listElement.innerHTML = "";

    if (!Array.isArray(list) || list.length === 0) {
      const li = document.createElement("li");
      li.className = "events-empty";
      li.textContent =
        "No upcoming events scheduled. Create one to get started.";
      listElement.appendChild(li);
      return;
    }

    list.forEach((item) => {
      const li = document.createElement("li");
      li.className = "events-upcoming-item";
      li.dataset.eventId = item.id || "";

      const primary = document.createElement("div");
      primary.className = "events-upcoming-primary";

      const title = document.createElement("span");
      title.className = "events-upcoming-title";
      title.textContent = item.title || "Untitled event";
      primary.appendChild(title);

      const date = document.createElement("span");
      date.className = "events-upcoming-date";
      date.textContent = formatDate(item.start);
      primary.appendChild(date);

      const meta = document.createElement("div");
      meta.className = "events-upcoming-meta";

      const tickets = document.createElement("span");
      tickets.className = "events-upcoming-stat";
      tickets.dataset.label = "Tickets sold";
      tickets.textContent = String(item.tickets_sold ?? 0);
      meta.appendChild(tickets);

      const revenue = document.createElement("span");
      revenue.className = "events-upcoming-stat";
      revenue.dataset.label = "Revenue";
      revenue.textContent = formatCurrency(item.revenue ?? 0);
      meta.appendChild(revenue);

      li.appendChild(primary);
      li.appendChild(meta);
      listElement.appendChild(li);
    });
  }

  function renderUpcomingCalendar() {
    const calendarSelectors = selectors.upcoming?.calendar;
    if (!calendarSelectors || !calendarSelectors.grid) {
      return;
    }

    const todayMonth = startOfMonth(new Date());
    const monthDate = state.calendar.currentMonth
      ? startOfMonth(state.calendar.currentMonth)
      : todayMonth;
    state.calendar.currentMonth = monthDate;

    if (calendarSelectors.label) {
      calendarSelectors.label.textContent = formatMonthLabel(monthDate);
    }

    if (calendarSelectors.empty) {
      calendarSelectors.empty.hidden = state.upcoming.length > 0;
    }

    const eventsByDay = new Map();
    state.upcoming.forEach((event) => {
      const startDate = parseDateValue(event.start);
      const startDay = startOfDay(startDate);
      if (!startDay) {
        return;
      }

      const endDate = parseDateValue(event.end);
      let endDay = startOfDay(endDate);
      if (!endDay || endDay.getTime() < startDay.getTime()) {
        endDay = startDay;
      }

      for (
        let current = new Date(startDay.getTime());
        current.getTime() <= endDay.getTime();
        current.setDate(current.getDate() + 1)
      ) {
        const key = formatDateKey(current);
        const occurrence = {
          ...event,
          occurrenceDate: new Date(current.getTime()),
          isFirstDay: current.getTime() === startDay.getTime(),
        };
        if (eventsByDay.has(key)) {
          eventsByDay.get(key).push(occurrence);
        } else {
          eventsByDay.set(key, [occurrence]);
        }
      }
    });

    const firstOfMonth = new Date(
      monthDate.getFullYear(),
      monthDate.getMonth(),
      1,
    );
    const startDay = firstOfMonth.getDay();
    const daysInMonth = new Date(
      monthDate.getFullYear(),
      monthDate.getMonth() + 1,
      0,
    ).getDate();
    const totalCells = Math.ceil((startDay + daysInMonth) / 7) * 7;

    calendarSelectors.grid.innerHTML = "";

    for (let index = 0; index < totalCells; index += 1) {
      const dayNumber = index - startDay + 1;
      const cell = document.createElement("div");
      cell.className = "events-calendar-day";

      if (dayNumber < 1 || dayNumber > daysInMonth) {
        cell.classList.add("is-muted");
        calendarSelectors.grid.appendChild(cell);
        continue;
      }

      const cellDate = new Date(
        monthDate.getFullYear(),
        monthDate.getMonth(),
        dayNumber,
      );
      const key = formatDateKey(cellDate);
      cell.dataset.date = key;

      const dateLabel = document.createElement("div");
      dateLabel.className = "events-calendar-date";
      dateLabel.textContent = String(dayNumber);
      cell.appendChild(dateLabel);

      const dayEvents = eventsByDay.has(key)
        ? eventsByDay.get(key).slice().sort(compareUpcomingByStart)
        : [];

      if (dayEvents.length > 0) {
        cell.classList.add("has-events");
        const listElement = document.createElement("ul");
        listElement.className = "events-calendar-events";

        dayEvents.forEach((event) => {
          const item = document.createElement("li");
          item.className = "events-calendar-event";
          item.dataset.eventId = event.id || "";

          const timeLabel = event.isFirstDay
            ? formatTimeLabel(event.start)
            : "";
          if (timeLabel) {
            const timeSpan = document.createElement("span");
            timeSpan.className = "events-calendar-event-time";
            timeSpan.textContent = timeLabel;
            item.appendChild(timeSpan);
          }

          const titleSpan = document.createElement("span");
          titleSpan.className = "events-calendar-event-title";
          titleSpan.textContent = event.title || "Untitled event";
          item.appendChild(titleSpan);

          listElement.appendChild(item);
        });

        cell.appendChild(listElement);
      }

      calendarSelectors.grid.appendChild(cell);
    }

    if (calendarSelectors.prev) {
      const disablePrev = monthDate.getTime() <= todayMonth.getTime();
      calendarSelectors.prev.disabled = disablePrev;
    }

    if (calendarSelectors.next) {
      const latestMonth = getLatestEventMonth();
      if (latestMonth) {
        calendarSelectors.next.disabled =
          monthDate.getTime() >= latestMonth.getTime();
      } else {
        calendarSelectors.next.disabled = true;
      }
    }
  }

  function createStatusBadge(status) {
    const span = document.createElement("span");
    span.className = "status-badge";
    applyStatusBadge(span, status);
    return span;
  }

  function getCategoryNames(categoryIds) {
    if (!Array.isArray(categoryIds) || categoryIds.length === 0) {
      return [];
    }
    return categoryIds
      .map((categoryId) => {
        const category = state.categories.find(
          (item) => String(item.id) === String(categoryId),
        );
        return category && category.name ? category.name : null;
      })
      .filter((name) => Boolean(name));
  }

  function createEventRow(row) {
    const tr = document.createElement("tr");
    tr.dataset.eventId = row.id;
    const startLabel = formatDate(row.start);
    const endLabel = row.end ? formatDate(row.end) : "";
    const categoryNames = getCategoryNames(row.categories);
    const categoriesMarkup = categoryNames.length
      ? `<div class="events-table-categories">${categoryNames
          .map(
            (name) =>
              `<span class="events-table-category">${escapeHtml(name)}</span>`,
          )
          .join("")}</div>`
      : "";
    const titleMarkup = `<div class="events-table-title">${escapeHtml(row.title || "Untitled Event")}</div>`;
    const locationSub = row.location
      ? `<div class="events-table-sub">${escapeHtml(row.location)}</div>`
      : "";
    const locationCell = row.location ? escapeHtml(row.location) : "TBA";
    const ticketsLabel =
      row.capacity > 0
        ? `${row.tickets_sold ?? 0} / ${row.capacity}`
        : `${row.tickets_sold ?? 0}`;
    const formCell = row.form_name
      ? `<div class="events-table-title">${escapeHtml(row.form_name)}</div>`
      : '<div class="events-table-sub">No form selected</div>';
    tr.innerHTML = `
            <td>
                ${titleMarkup}
                ${categoriesMarkup}
                ${locationSub}
            </td>
            <td>
                <div>${startLabel}</div>
                ${endLabel ? `<div class="events-table-sub">Ends ${endLabel}</div>` : ""}
            </td>
            <td>${locationCell}</td>
            <td>${formCell}</td>
            <td>${ticketsLabel}</td>
            <td>${formatCurrency(row.revenue ?? 0)}</td>
            <td data-status></td>
            <td class="events-table-actions">
                <button type="button" class="events-action" data-events-action="edit" data-id="${row.id}">
                    <i class="fa-solid fa-pen"></i><span class="sr-only">Edit</span>
                </button>
                <button type="button" class="events-action" data-events-action="duplicate" data-id="${row.id}">
                    <i class="fa-solid fa-copy"></i><span class="sr-only">Duplicate</span>
                </button>
                <button type="button" class="events-action" data-events-action="sales" data-id="${row.id}">
                    <i class="fa-solid fa-chart-column"></i><span class="sr-only">View sales</span>
                </button>
                <button type="button" class="events-action danger" data-events-action="delete" data-id="${row.id}">
                    <i class="fa-solid fa-trash"></i><span class="sr-only">Delete</span>
                </button>
            </td>
        `;
    const badgeCell = tr.querySelector("[data-status]");
    const badge = createStatusBadge(row.status);
    if (row.schedule_note) {
      badge.title = row.schedule_note;
      badgeCell.title = row.schedule_note;
    }
    badgeCell.appendChild(badge);
    if (row.schedule_note) {
      const note = document.createElement("div");
      note.className = "events-table-sub events-status-note";
      note.textContent = row.schedule_note;
      badgeCell.appendChild(note);
    }
    return tr;
  }

  function applyEventFilters(rows) {
    const term = state.filters.search.trim().toLowerCase();
    const filtered = [];
    rows.forEach((row) => {
      const derived = withSchedule(row);
      const matchesStatus =
        !state.filters.status || derived.status === state.filters.status;
      const matchesSearch =
        !term ||
        `${derived.title} ${derived.location}`.toLowerCase().includes(term);
      if (matchesStatus && matchesSearch) {
        filtered.push(derived);
      }
    });
    return filtered;
  }

  function renderEventsTable() {
    if (!selectors.tableBody) {
      return;
    }
    selectors.tableBody.innerHTML = "";
    const filtered = applyEventFilters(state.eventRows);
    if (filtered.length === 0) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 8;
      cell.className = "events-empty";
      cell.textContent = "No events match the current filters.";
      row.appendChild(cell);
      selectors.tableBody.appendChild(row);
      return;
    }
    filtered.forEach((row) => {
      selectors.tableBody.appendChild(createEventRow(row));
    });
  }

  function populateEventSelect(select) {
    if (!select) {
      return;
    }
    const current = select.value;
    select.innerHTML = "";
    const defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.textContent = "All events";
    select.appendChild(defaultOption);
    Array.from(state.events.values())
      .sort((a, b) => {
        const aTime = a.start ? new Date(a.start).getTime() : 0;
        const bTime = b.start ? new Date(b.start).getTime() : 0;
        return aTime - bTime;
      })
      .forEach((event) => {
        const option = document.createElement("option");
        option.value = event.id;
        option.textContent = event.title || "Untitled event";
        select.appendChild(option);
      });
    if (current && select.querySelector(`option[value="${current}"]`)) {
      select.value = current;
    }
  }

  function renderOrdersTable() {
    if (!selectors.orders.body) {
      return;
    }
    selectors.orders.body.innerHTML = "";
    if (!Array.isArray(state.orders) || state.orders.length === 0) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 8;
      cell.className = "events-empty";
      cell.textContent = "No orders found for the selected filters.";
      row.appendChild(cell);
      selectors.orders.body.appendChild(row);
      return;
    }
    state.orders.forEach((order) => {
      const tr = document.createElement("tr");
      const totalTickets =
        typeof order.tickets === "number"
          ? order.tickets
          : Array.isArray(order.line_items)
            ? order.line_items.reduce(
                (sum, item) => sum + (item.quantity || 0),
                0,
              )
            : 0;
      const contactLines = [];
      if (order.buyer_email) {
        contactLines.push(
          `<div class="events-table-sub">${escapeHtml(order.buyer_email)}</div>`,
        );
      }
      if (order.buyer_phone) {
        contactLines.push(
          `<div class="events-table-sub">${escapeHtml(order.buyer_phone)}</div>`,
        );
      }
      tr.innerHTML = `
                <td>${escapeHtml(order.id || "")}</td>
                <td>
                    <div class="events-table-title">${escapeHtml(order.event || "Event")}</div>
                    ${order.event_id ? `<div class="events-table-sub">#${escapeHtml(order.event_id)}</div>` : ""}
                </td>
                <td>
                    <div class="events-table-title">${escapeHtml(order.buyer_name || "")}</div>
                    ${contactLines.join("")}
                </td>
                <td>${totalTickets}</td>
                <td>${formatCurrency(order.amount ?? 0)}</td>
                <td data-status></td>
                <td>${formatDate(order.ordered_at)}</td>
                <td class="is-actions">
                    <button type="button" class="events-order-manage" data-events-order-manage data-id="${escapeAttribute(order.id)}">
                        Manage
                    </button>
                </td>
            `;
      const statusCell = tr.querySelector("[data-status]");
      if (statusCell) {
        statusCell.appendChild(createStatusBadge(order.status));
      }
      selectors.orders.body.appendChild(tr);
    });
  }

  function renderReportsTable(rows = state.reportRows) {
    const table = selectors.reports.tableBody;
    if (!table) {
      return;
    }
    table.innerHTML = "";
    if (!Array.isArray(rows) || rows.length === 0) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 7;
      cell.className = "events-empty";
      cell.textContent = "No report data available yet.";
      row.appendChild(cell);
      table.appendChild(row);
      return;
    }
    rows.forEach((report) => {
      const row = document.createElement("tr");
      const ticketsLabel =
        report.capacity > 0
          ? `${report.tickets_sold ?? 0} / ${report.capacity}`
          : `${report.tickets_sold ?? 0}`;
      const sellThroughText = formatPercentage(report.sell_through_rate ?? 0);
      const revenueText = formatCurrency(report.revenue ?? 0);
      const netRevenueText = formatCurrency(report.net_revenue ?? 0);
      const avgOrderText = formatCurrency(report.average_order ?? 0);
      const refundsMeta =
        Number(report.refunded ?? 0) > 0
          ? `<div class="events-table-sub">Refunded ${formatCurrency(report.refunded ?? 0)}</div>`
          : "";
      const ordersMeta =
        report.orders > 0
          ? `<div class="events-table-sub">${report.orders} total · ${report.paid_orders || 0} paid${report.pending_orders ? ` · ${report.pending_orders} pending` : ""}</div>`
          : '<div class="events-table-sub">No orders yet</div>';
      row.innerHTML = `
                <td>
                    <div class="events-table-title">${escapeHtml(report.title || "Untitled event")}</div>
                </td>
                <td>${ticketsLabel}</td>
                <td>${sellThroughText}</td>
                <td>
                    <div class="events-table-title">${revenueText}</div>
                </td>
                <td>
                    <div class="events-table-title">${netRevenueText}</div>
                    ${refundsMeta}
                </td>
                <td>
                    <div class="events-table-title">${avgOrderText}</div>
                    ${ordersMeta}
                </td>
                <td data-status></td>
            `;
      const statusCell = row.querySelector("[data-status]");
      if (statusCell) {
        statusCell.appendChild(createStatusBadge(report.status));
      }
      table.appendChild(row);
    });
  }

  const REPORT_TIMEFRAME_LABELS = {
    all: "All time",
    30: "Last 30 days",
    90: "Last 90 days",
    365: "Last 12 months",
  };

  function getReportTimeframeLabel(value = state.reportsFilter.timeframe) {
    const key = String(value || "all");
    return REPORT_TIMEFRAME_LABELS[key] || REPORT_TIMEFRAME_LABELS.all;
  }

  function getReportTimeframeCutoff(value = state.reportsFilter.timeframe) {
    const key = String(value || "all");
    if (key === "all") {
      return null;
    }
    const days = Number.parseInt(key, 10);
    if (!Number.isFinite(days) || days <= 0) {
      return null;
    }
    const now = new Date();
    return new Date(now.getTime() - days * 24 * 60 * 60 * 1000);
  }

  function getEventCapacityValue(event, fallback = 0) {
    if (event && typeof event === "object") {
      const direct = Number(event.capacity);
      if (Number.isFinite(direct) && direct > 0) {
        return direct;
      }
      if (Array.isArray(event.tickets)) {
        const total = event.tickets.reduce((sum, ticket) => {
          if (!ticket || typeof ticket !== "object") {
            return sum;
          }
          if (ticket.enabled === false) {
            return sum;
          }
          const quantity = Number(ticket.quantity ?? ticket.capacity ?? 0);
          if (!Number.isFinite(quantity) || quantity <= 0) {
            return sum;
          }
          return sum + quantity;
        }, 0);
        if (total > 0) {
          return total;
        }
      }
    }
    const fallbackValue = Number(fallback);
    return Number.isFinite(fallbackValue) && fallbackValue > 0
      ? fallbackValue
      : 0;
  }

  function buildSummaryMap() {
    const entries = Array.isArray(state.salesSummary) ? state.salesSummary : [];
    return new Map(entries.map((item) => [String(item.event_id || ""), item]));
  }

  function filterOrdersForReports() {
    const orders = Array.isArray(state.orders) ? state.orders : [];
    const cutoff = getReportTimeframeCutoff();
    const statusFilter = String(state.reportsFilter.status || "").toLowerCase();
    const summaryMap = buildSummaryMap();
    return orders.filter((order) => {
      if (!order || typeof order !== "object") {
        return false;
      }
      const eventId = String(order.event_id || "").trim();
      if (eventId === "") {
        return false;
      }
      if (cutoff) {
        const orderedAt = order.ordered_at ? new Date(order.ordered_at) : null;
        if (
          orderedAt instanceof Date &&
          !Number.isNaN(orderedAt.getTime()) &&
          orderedAt < cutoff
        ) {
          return false;
        }
      }
      if (statusFilter) {
        const event = state.events.get(eventId);
        const summary = summaryMap.get(eventId);
        const eventStatus = String(
          event?.status || summary?.status || "draft",
        ).toLowerCase();
        if (eventStatus !== statusFilter) {
          return false;
        }
      }
      return true;
    });
  }

  function buildReportRows(filteredOrders) {
    const summaryMap = buildSummaryMap();
    const statusFilter = String(state.reportsFilter.status || "").toLowerCase();
    const rows = new Map();

    function ensureRow(eventId, options = {}) {
      const id = String(eventId || "").trim();
      if (id === "") {
        return null;
      }
      if (!rows.has(id)) {
        const event = state.events.get(id);
        const summary = summaryMap.get(id);
        const baseStatus = String(
          options.status || event?.status || summary?.status || "draft",
        ).toLowerCase();
        rows.set(id, {
          event_id: id,
          title: options.title || event?.title || summary?.title || "Event",
          tickets_sold: 0,
          revenue: 0,
          refunded: 0,
          net_revenue: 0,
          average_order: 0,
          orders: 0,
          paid_orders: 0,
          pending_orders: 0,
          capacity: getEventCapacityValue(event, summary?.capacity ?? 0),
          sell_through_rate: 0,
          status: baseStatus,
          _paid_amount: 0,
        });
      } else if (options.title) {
        const existing = rows.get(id);
        if (!existing.title || existing.title === "Event") {
          existing.title = options.title;
        }
      }
      return rows.get(id) || null;
    }

    state.events.forEach((event) => {
      const eventStatus = String(event.status || "draft").toLowerCase();
      if (statusFilter && eventStatus !== statusFilter) {
        return;
      }
      ensureRow(event.id, { title: event.title, status: eventStatus });
    });

    state.salesSummary.forEach((summary) => {
      if (!summary || typeof summary !== "object") {
        return;
      }
      const id = String(summary.event_id || "").trim();
      if (id === "") {
        return;
      }
      const summaryStatus = String(summary.status || "draft").toLowerCase();
      if (statusFilter && summaryStatus !== statusFilter) {
        return;
      }
      ensureRow(id, { title: summary.title, status: summaryStatus });
    });

    filteredOrders.forEach((order) => {
      if (!order || typeof order !== "object") {
        return;
      }
      const eventId = String(order.event_id || "").trim();
      if (eventId === "") {
        return;
      }
      const event = state.events.get(eventId);
      const summary = summaryMap.get(eventId);
      const status = String(
        event?.status || summary?.status || "draft",
      ).toLowerCase();
      if (statusFilter && status !== statusFilter) {
        return;
      }
      const row = ensureRow(eventId, {
        title: order.event || event?.title || summary?.title || "Event",
        status,
      });
      if (!row) {
        return;
      }
      const amount = Number(order.amount || 0) || 0;
      const tickets = Number(order.tickets || 0) || 0;
      const orderStatus = String(order.status || "paid").toLowerCase();
      if (orderStatus === "refunded") {
        row.refunded += amount;
      } else {
        row.revenue += amount;
        row.tickets_sold += tickets;
        row.orders += 1;
        if (orderStatus === "pending") {
          row.pending_orders += 1;
        }
        if (orderStatus === "paid") {
          row.paid_orders += 1;
          row._paid_amount += amount;
        }
      }
    });

    const results = Array.from(rows.values()).map((row) => {
      const event = state.events.get(row.event_id);
      const summary = summaryMap.get(row.event_id);
      const capacity = getEventCapacityValue(
        event,
        summary?.capacity ?? row.capacity ?? 0,
      );
      const ticketsSold = Math.max(0, Number(row.tickets_sold || 0));
      row.capacity = capacity;
      row.sell_through_rate =
        capacity > 0 ? Math.min(1, ticketsSold / capacity) : 0;
      const revenue = Number(row.revenue || 0) || 0;
      const refunded = Number(row.refunded || 0) || 0;
      row.net_revenue = revenue > refunded ? revenue - refunded : 0;
      const paidOrders = Number(row.paid_orders || 0) || 0;
      row.average_order = paidOrders > 0 ? row._paid_amount / paidOrders : 0;
      delete row._paid_amount;
      return row;
    });

    results.sort((a, b) => {
      const revenueDiff = (Number(b.revenue) || 0) - (Number(a.revenue) || 0);
      if (Math.abs(revenueDiff) > 0.01) {
        return revenueDiff;
      }
      return String(a.title || "").localeCompare(
        String(b.title || ""),
        undefined,
        { sensitivity: "base" },
      );
    });

    return results;
  }

  function collectOrderLines() {
    const container = selectors.orderEditor.lines;
    if (!container) {
      return [];
    }
    const rows = Array.from(container.querySelectorAll("[data-order-line]"));
    return rows.map((row) => {
      updateLineTotal(row);
      const priceInput = row.querySelector("[data-order-line-price]");
      const quantityInput = row.querySelector("[data-order-line-quantity]");
      let price = Number.parseFloat(priceInput?.value ?? "0");
      if (!Number.isFinite(price) || price < 0) {
        price = 0;
      }
      let quantity = Number.parseInt(quantityInput?.value ?? "0", 10);
      if (!Number.isFinite(quantity) || quantity < 0) {
        quantity = 0;
      }
      return {
        ticket_id: row.dataset.ticketId || "",
        name: row.dataset.ticketName || "Ticket",
        price,
        quantity,
        subtotal: price * quantity,
      };
    });
  }

  function updateLineTotal(row) {
    if (!row) {
      return;
    }
    const priceInput = row.querySelector("[data-order-line-price]");
    const quantityInput = row.querySelector("[data-order-line-quantity]");
    let price = Number.parseFloat(priceInput?.value ?? "0");
    if (!Number.isFinite(price) || price < 0) {
      price = 0;
    }
    let quantity = Number.parseInt(quantityInput?.value ?? "0", 10);
    if (!Number.isFinite(quantity) || quantity < 0) {
      quantity = 0;
    }
    if (priceInput) {
      priceInput.value = price.toFixed(2);
    }
    if (quantityInput) {
      quantityInput.value = String(quantity);
    }
    const total = price * quantity;
    const totalEl = row.querySelector("[data-order-line-total]");
    if (totalEl) {
      totalEl.textContent = formatCurrency(total);
    }
  }

  function createOrderLine(line) {
    const row = document.createElement("div");
    row.className = "events-order-line";
    row.dataset.orderLine = "true";
    row.dataset.ticketId = line.ticket_id || "";
    row.dataset.ticketName = line.name || "Ticket";
    row.innerHTML = `
            <div class="events-order-line-header">
                <div>
                    <div class="events-order-line-name">${escapeHtml(line.name || "Ticket")}</div>
                    <div class="events-order-line-meta">ID ${escapeHtml(line.ticket_id || "")}</div>
                </div>
                <button type="button" class="events-order-line-remove" data-order-line-remove>&times;<span class="sr-only">Remove ticket</span></button>
            </div>
            <div class="events-order-line-grid">
                <label class="events-order-line-field">
                    <span>Price</span>
                    <input type="number" min="0" step="0.01" value="${Number(line.price || 0).toFixed(2)}" data-order-line-price>
                </label>
                <label class="events-order-line-field">
                    <span>Quantity</span>
                    <input type="number" min="0" step="1" value="${Math.max(0, Number.parseInt(line.quantity ?? 0, 10) || 0)}" data-order-line-quantity>
                </label>
                <div class="events-order-line-total" data-order-line-total>${formatCurrency((line.price || 0) * (line.quantity || 0))}</div>
            </div>
        `;
    return row;
  }

  function renderOrderLines(lines) {
    const container = selectors.orderEditor.lines;
    if (!container) {
      return;
    }
    container.innerHTML = "";
    if (!Array.isArray(lines) || lines.length === 0) {
      container.innerHTML =
        '<p class="events-order-empty">No tickets on this order yet.</p>';
      return;
    }
    lines.forEach((line) => {
      const row = createOrderLine(line);
      container.appendChild(row);
      updateLineTotal(row);
    });
  }

  function updateOrderAddOptions(detail = state.orderEditor.detail) {
    const select = selectors.orderEditor.addSelect;
    if (!select) {
      return;
    }
    select.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "Choose ticket type";
    select.appendChild(placeholder);
    const available = detail?.available_tickets;
    if (!Array.isArray(available) || available.length === 0) {
      select.disabled = true;
      if (selectors.orderEditor.addButton) {
        selectors.orderEditor.addButton.disabled = true;
      }
      return;
    }
    const used = new Set();
    if (selectors.orderEditor.lines) {
      selectors.orderEditor.lines
        .querySelectorAll("[data-order-line]")
        .forEach((row) => {
          used.add(row.dataset.ticketId || "");
        });
    }
    available.forEach((ticket) => {
      const ticketId = String(ticket.ticket_id || "").trim();
      if (ticketId === "" || used.has(ticketId)) {
        return;
      }
      const option = document.createElement("option");
      option.value = ticketId;
      option.textContent = `${ticket.name || "Ticket"} — ${formatCurrency(ticket.price || 0)}`;
      select.appendChild(option);
    });
    select.disabled = select.options.length <= 1;
    if (selectors.orderEditor.addButton) {
      selectors.orderEditor.addButton.disabled = select.disabled;
    }
    if (!select.disabled) {
      select.value = "";
    }
  }

  function updateOrderSummary() {
    const totals = selectors.orderEditor.totals;
    const statusValue = selectors.orderEditor.status?.value || "paid";
    const lines = collectOrderLines();
    const subtotal = lines.reduce(
      (sum, line) => sum + line.price * line.quantity,
      0,
    );
    const refunds = statusValue === "refunded" ? subtotal : 0;
    const net = subtotal - refunds;
    if (totals.subtotal) {
      totals.subtotal.textContent = formatCurrency(subtotal);
    }
    if (totals.refunds) {
      totals.refunds.textContent = formatCurrency(refunds);
    }
    if (totals.net) {
      totals.net.textContent = formatCurrency(net);
    }
    const breakdown = selectors.orderEditor.breakdown;
    if (breakdown) {
      breakdown.innerHTML = "";
      if (lines.length === 0) {
        const empty = document.createElement("p");
        empty.className = "events-order-empty";
        empty.textContent = "Ticket breakdown will appear here.";
        breakdown.appendChild(empty);
      } else {
        const list = document.createElement("ul");
        list.className = "events-order-breakdown-list";
        lines.forEach((line) => {
          const item = document.createElement("li");
          item.className = "events-order-breakdown-item";
          item.innerHTML = `
                        <span class="events-order-breakdown-name">${escapeHtml(line.name || "Ticket")}</span>
                        <span class="events-order-breakdown-meta">${line.quantity} × ${formatCurrency(line.price)} = ${formatCurrency(line.price * line.quantity)}</span>
                    `;
          list.appendChild(item);
        });
        breakdown.appendChild(list);
      }
    }
    return { subtotal, refunds, net, lines };
  }

  function fillOrderEditor(detail) {
    const form = selectors.orderEditor.form;
    if (!form || !detail) {
      return;
    }
    state.orderEditor.detail = {
      ...detail,
      available_tickets: Array.isArray(detail.available_tickets)
        ? detail.available_tickets
        : [],
    };
    const idInput = form.querySelector('[name="id"]');
    if (idInput) {
      idInput.value = detail.id || "";
    }
    const eventIdInput = form.querySelector('[name="event_id"]');
    if (eventIdInput) {
      eventIdInput.value = detail.event_id || "";
    }
    const buyerInput = form.querySelector('[name="buyer_name"]');
    if (buyerInput) {
      buyerInput.value = detail.buyer_name || "";
    }
    const buyerEmailInput = form.querySelector('[name="buyer_email"]');
    if (buyerEmailInput) {
      buyerEmailInput.value = detail.buyer_email || "";
    }
    const buyerPhoneInput = form.querySelector('[name="buyer_phone"]');
    if (buyerPhoneInput) {
      buyerPhoneInput.value = detail.buyer_phone || "";
    }
    const orderedAtInput = form.querySelector('[name="ordered_at"]');
    if (orderedAtInput) {
      orderedAtInput.value = toLocalDateTimeInput(detail.ordered_at);
    }
    if (selectors.orderEditor.status) {
      selectors.orderEditor.status.value = detail.status || "paid";
    }
    if (selectors.orderEditor.title) {
      selectors.orderEditor.title.textContent = detail.id
        ? `Order ${detail.id}`
        : "Order";
    }
    if (selectors.orderEditor.event) {
      selectors.orderEditor.event.textContent = detail.event?.title || "Event";
    }
    renderOrderLines(Array.isArray(detail.line_items) ? detail.line_items : []);
    updateOrderAddOptions(state.orderEditor.detail);
    updateOrderSummary();
  }

  function resetOrderEditor() {
    state.orderEditor.detail = null;
    if (selectors.orderEditor.form) {
      selectors.orderEditor.form.reset();
      const idInput = selectors.orderEditor.form.querySelector('[name="id"]');
      if (idInput) {
        idInput.value = "";
      }
      const eventIdInput =
        selectors.orderEditor.form.querySelector('[name="event_id"]');
      if (eventIdInput) {
        eventIdInput.value = "";
      }
    }
    if (selectors.orderEditor.lines) {
      selectors.orderEditor.lines.innerHTML =
        '<p class="events-order-empty">No tickets on this order yet.</p>';
    }
    if (selectors.orderEditor.breakdown) {
      selectors.orderEditor.breakdown.innerHTML =
        '<p class="events-order-empty">Ticket breakdown will appear here.</p>';
    }
    if (selectors.orderEditor.totals) {
      if (selectors.orderEditor.totals.subtotal) {
        selectors.orderEditor.totals.subtotal.textContent = formatCurrency(0);
      }
      if (selectors.orderEditor.totals.refunds) {
        selectors.orderEditor.totals.refunds.textContent = formatCurrency(0);
      }
      if (selectors.orderEditor.totals.net) {
        selectors.orderEditor.totals.net.textContent = formatCurrency(0);
      }
    }
    if (selectors.orderEditor.addSelect) {
      selectors.orderEditor.addSelect.innerHTML = "";
      selectors.orderEditor.addSelect.disabled = true;
    }
    if (selectors.orderEditor.addButton) {
      selectors.orderEditor.addButton.disabled = true;
    }
    if (selectors.orderEditor.title) {
      selectors.orderEditor.title.textContent = "Order";
    }
    if (selectors.orderEditor.event) {
      selectors.orderEditor.event.textContent = "";
    }
  }

  function addOrderLine() {
    const detail = state.orderEditor.detail;
    if (!detail) {
      return;
    }
    const select = selectors.orderEditor.addSelect;
    if (!select) {
      return;
    }
    let ticketId = select.value;
    if (!ticketId && select.options.length > 1) {
      ticketId = select.options[1].value;
      select.value = ticketId;
    }
    if (!ticketId) {
      showToast("No additional ticket types available.", "error");
      return;
    }
    const ticket = detail.available_tickets.find(
      (item) => String(item.ticket_id) === ticketId,
    );
    if (!ticket) {
      showToast("Ticket type not found.", "error");
      return;
    }
    const container = selectors.orderEditor.lines;
    if (!container) {
      return;
    }
    const existing = container.querySelector(
      `[data-order-line][data-ticket-id="${ticketId}"]`,
    );
    if (existing) {
      const quantityInput = existing.querySelector(
        "[data-order-line-quantity]",
      );
      if (quantityInput) {
        quantityInput.value = String(
          Number.parseInt(quantityInput.value || "0", 10) + 1,
        );
        updateLineTotal(existing);
        updateOrderSummary();
        updateOrderAddOptions(detail);
        select.value = "";
      }
      return;
    }
    if (container.querySelector(".events-order-empty")) {
      container.innerHTML = "";
    }
    const row = createOrderLine({
      ticket_id: ticket.ticket_id,
      name: ticket.name,
      price: ticket.price,
      quantity: 1,
    });
    container.appendChild(row);
    updateLineTotal(row);
    updateOrderSummary();
    updateOrderAddOptions(detail);
    select.value = "";
  }

  function openOrderModal(orderId) {
    const modal = selectors.orderEditor.modal;
    if (!modal || !orderId) {
      return;
    }
    fetchJSON("get_order", { params: { id: orderId } })
      .then((response) => {
        if (!response || !response.order) {
          throw new Error("Order not found");
        }
        fillOrderEditor(response.order);
        openModal(modal);
      })
      .catch(() => {
        showToast("Unable to load order.", "error");
      });
  }

  function serializeOrderForm() {
    const form = selectors.orderEditor.form;
    if (!form) {
      return null;
    }
    updateOrderSummary();
    const formData = new FormData(form);
    const id = String(formData.get("id") || "").trim();
    if (id === "") {
      showToast("Missing order information.", "error");
      return null;
    }
    const buyerName = String(formData.get("buyer_name") || "").trim();
    if (buyerName === "") {
      showToast("Buyer name is required.", "error");
      return null;
    }
    const buyerEmail = String(formData.get("buyer_email") || "").trim();
    const buyerPhone = String(formData.get("buyer_phone") || "").trim();
    const status = String(formData.get("status") || "paid").toLowerCase();
    const orderedAtRaw = String(formData.get("ordered_at") || "").trim();
    const orderedAt = orderedAtRaw ? fromLocalDateTimeInput(orderedAtRaw) : "";
    const lines = collectOrderLines().filter(
      (line) => line.ticket_id && line.quantity > 0,
    );
    const tickets = lines.map((line) => {
      const priceValue = Number.isFinite(line.price) ? line.price : 0;
      return {
        ticket_id: line.ticket_id,
        quantity: line.quantity,
        price: Number(priceValue.toFixed(2)),
      };
    });

    return {
      id,
      event_id: String(formData.get("event_id") || "").trim(),
      buyer_name: buyerName,
      buyer_email: buyerEmail,
      buyer_phone: buyerPhone,
      status,
      ordered_at,
      tickets,
    };
  }

  function computeRevenueMetrics(rows = state.reportRows, orders = []) {
    const totalRevenue = rows.reduce(
      (sum, report) => sum + (Number(report.revenue) || 0),
      0,
    );
    const refunds = rows.reduce(
      (sum, report) => sum + (Number(report.refunded) || 0),
      0,
    );
    const totalOrders = Array.isArray(orders) ? orders.length : 0;
    let paidTotal = 0;
    let paidCount = 0;
    let refundOrders = 0;
    (Array.isArray(orders) ? orders : []).forEach((order) => {
      if (!order || typeof order !== "object") {
        return;
      }
      const amount = Number(order.amount || 0) || 0;
      if (order.status === "refunded") {
        refundOrders += 1;
      } else if (order.status === "paid") {
        paidTotal += amount;
        paidCount += 1;
      }
    });
    const averageOrder = paidCount > 0 ? paidTotal / paidCount : 0;
    return {
      totalRevenue,
      netRevenue: totalRevenue > refunds ? totalRevenue - refunds : 0,
      totalOrders,
      averageOrder,
      paidOrdersCount: paidCount,
      refundsTotal: refunds,
      refundOrders,
    };
  }

  function renderReportMetrics(metrics) {
    const metricEls = selectors.reports.metrics;
    if (!metricEls) {
      return;
    }
    const timeframeLabel = getReportTimeframeLabel();
    const isAllTime = state.reportsFilter.timeframe === "all";
    const revenueEl = metricEls.revenue;
    if (revenueEl) {
      const value = revenueEl.querySelector("[data-value]");
      const meta = revenueEl.querySelector("[data-meta]");
      if (value) {
        value.textContent = formatCurrency(metrics.totalRevenue);
      }
      if (meta) {
        const netText = formatCurrency(metrics.netRevenue);
        const ordersText =
          metrics.totalOrders === 1
            ? "1 order"
            : `${metrics.totalOrders} orders`;
        meta.textContent = `${ordersText} · Net ${netText}`;
        if (!isAllTime) {
          meta.textContent += ` (${timeframeLabel})`;
        }
      }
    }
    const averageEl = metricEls.averageOrder;
    if (averageEl) {
      const value = averageEl.querySelector("[data-value]");
      const meta = averageEl.querySelector("[data-meta]");
      if (value) {
        value.textContent = formatCurrency(metrics.averageOrder);
      }
      if (meta) {
        let text =
          metrics.paidOrdersCount > 0
            ? metrics.paidOrdersCount === 1
              ? "1 paid order"
              : `${metrics.paidOrdersCount} paid orders`
            : "No paid orders yet.";
        if (!isAllTime) {
          text = text.replace(/\.$/, "");
          text += ` (${timeframeLabel})`;
        }
        meta.textContent = text;
      }
    }
    const refundsEl = metricEls.refunds;
    if (refundsEl) {
      const value = refundsEl.querySelector("[data-value]");
      const meta = refundsEl.querySelector("[data-meta]");
      if (value) {
        value.textContent = formatCurrency(metrics.refundsTotal);
      }
      if (meta) {
        let text = metrics.refundOrders
          ? `${metrics.refundOrders} refunded ${metrics.refundOrders === 1 ? "order" : "orders"}.`
          : "No refunds issued.";
        if (!isAllTime) {
          text = text.replace(/\.$/, "");
          text += ` (${timeframeLabel})`;
        }
        meta.textContent = text;
      }
    }
  }

  function computeInsights(rows = state.reportRows, orders = []) {
    const topEvent =
      [...rows]
        .filter((item) => (Number(item.revenue) || 0) > 0)
        .sort(
          (a, b) => (Number(b.revenue) || 0) - (Number(a.revenue) || 0),
        )[0] || null;
    const ticketTotals = new Map();
    (Array.isArray(orders) ? orders : []).forEach((order) => {
      if (!order || typeof order !== "object") {
        return;
      }
      if (order.status === "refunded") {
        return;
      }
      (order.line_items || []).forEach((line) => {
        if (!line || !line.ticket_id) {
          return;
        }
        const key = String(line.ticket_id);
        const quantity = Number(line.quantity || 0);
        if (!ticketTotals.has(key)) {
          ticketTotals.set(key, {
            id: key,
            name: line.name || key,
            quantity: 0,
          });
        }
        ticketTotals.get(key).quantity += quantity;
      });
    });
    const topTicket =
      [...ticketTotals.values()]
        .filter((item) => item.quantity > 0)
        .sort((a, b) => b.quantity - a.quantity)[0] || null;
    const buyerTotals = new Map();
    (Array.isArray(orders) ? orders : []).forEach((order) => {
      if (!order || typeof order !== "object") {
        return;
      }
      if (order.status === "refunded") {
        return;
      }
      const buyer = order.buyer_name || "Unknown buyer";
      const amount = Number(order.amount || 0);
      buyerTotals.set(buyer, (buyerTotals.get(buyer) || 0) + amount);
    });
    const topBuyerEntry = [...buyerTotals.entries()]
      .filter(([, amount]) => amount > 0)
      .sort((a, b) => b[1] - a[1])[0];
    const topBuyer = topBuyerEntry
      ? { name: topBuyerEntry[0], amount: topBuyerEntry[1] }
      : null;
    return { topEvent, topTicket, topBuyer };
  }

  function renderInsights(insights) {
    const container = selectors.reports.insights;
    if (!container) {
      return;
    }
    const timeframeLabel = getReportTimeframeLabel();
    const includeTimeframe = state.reportsFilter.timeframe !== "all";
    container.querySelectorAll("[data-insight]").forEach((card) => {
      const type = card.dataset.insight;
      const valueEl = card.querySelector("[data-insight-value]");
      const metaEl = card.querySelector("[data-insight-meta]");
      let valueText = "—";
      let metaText = "No data available yet.";
      switch (type) {
        case "top-event": {
          const topEvent = insights.topEvent;
          if (topEvent && (Number(topEvent.revenue) || 0) > 0) {
            valueText = topEvent.title || "Event";
            metaText = `${formatCurrency(Number(topEvent.revenue) || 0)} total revenue`;
          } else {
            metaText = "No revenue recorded yet.";
          }
          break;
        }
        case "top-ticket": {
          const topTicket = insights.topTicket;
          if (topTicket && topTicket.quantity > 0) {
            valueText = topTicket.name || "Ticket";
            metaText = `${topTicket.quantity} tickets sold`;
          } else {
            metaText = "No ticket sales yet.";
          }
          break;
        }
        case "top-buyer": {
          const topBuyer = insights.topBuyer;
          if (topBuyer && (Number(topBuyer.amount) || 0) > 0) {
            valueText = topBuyer.name || "Customer";
            metaText = `${formatCurrency(Number(topBuyer.amount) || 0)} in purchases`;
          } else {
            metaText = "No paid customers yet.";
          }
          break;
        }
        default:
          break;
      }
      if (valueEl) {
        valueEl.textContent = valueText;
      }
      if (metaEl) {
        if (includeTimeframe) {
          metaText = metaText.replace(/\.$/, "");
          metaText += ` (${timeframeLabel})`;
        }
        metaEl.textContent = metaText;
      }
    });
  }

  function updateReportsView() {
    const filteredOrders = filterOrdersForReports();
    const rows = buildReportRows(filteredOrders);
    state.reportRows = rows;
    renderReportsTable(rows);
    renderReportMetrics(computeRevenueMetrics(rows, filteredOrders));
    renderInsights(computeInsights(rows, filteredOrders));
  }

  function downloadReport(type) {
    const timeframeSuffix = {
      all: "all-time",
      30: "last-30-days",
      90: "last-90-days",
      365: "last-12-months",
    };
    const timeframeKey = String(state.reportsFilter.timeframe || "all");
    const timeframeSegment =
      timeframeSuffix[timeframeKey] || timeframeSuffix.all;
    const statusSegment = state.reportsFilter.status
      ? `status-${state.reportsFilter.status}`
      : "all-statuses";
    const metadata = [["Timeframe", getReportTimeframeLabel()]];
    if (state.reportsFilter.status) {
      metadata.push(["Event status", state.reportsFilter.status]);
    }
    metadata.push([]);
    const header = [
      "Event",
      "Tickets Sold",
      "Capacity",
      "Sell Through",
      "Revenue",
      "Refunded",
      "Net Revenue",
      "Average Order",
      "Status",
    ];
    const rows = Array.isArray(state.reportRows) ? state.reportRows : [];
    const data = rows.map((item) => [
      item.title || "Event",
      Number(item.tickets_sold ?? 0),
      Number(item.capacity ?? 0),
      `${((Number(item.sell_through_rate) || 0) * 100).toFixed(2)}%`,
      Number(item.revenue ?? 0).toFixed(2),
      Number(item.refunded ?? 0).toFixed(2),
      Number(item.net_revenue ?? 0).toFixed(2),
      Number(item.average_order ?? 0).toFixed(2),
      item.status || "",
    ]);
    const csvRows = [...metadata, header, ...data];
    const csv = csvRows
      .map((row) => {
        if (!Array.isArray(row) || row.length === 0) {
          return "";
        }
        return row
          .map((value) => `"${String(value ?? "").replace(/"/g, '""')}"`)
          .join(",");
      })
      .join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    const filename = `events-${type}-report-${timeframeSegment}-${statusSegment}.csv`;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  }

  function handleOrderForm() {
    const form = selectors.orderEditor.form;
    if (!form) {
      return;
    }
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const payload = serializeOrderForm();
      if (!payload) {
        return;
      }
      fetchJSON("save_order", { method: "POST", body: payload })
        .then(() => {
          showToast("Order updated.");
          closeModal(selectors.orderEditor.modal);
          refreshAll();
        })
        .catch(() => {
          showToast("Unable to save order.", "error");
        });
    });
    if (selectors.orderEditor.lines) {
      selectors.orderEditor.lines.addEventListener("input", (event) => {
        const row = event.target.closest("[data-order-line]");
        if (!row) {
          return;
        }
        if (
          event.target.matches(
            "[data-order-line-price], [data-order-line-quantity]",
          )
        ) {
          updateLineTotal(row);
          updateOrderSummary();
          updateOrderAddOptions();
        }
      });
      selectors.orderEditor.lines.addEventListener("click", (event) => {
        const removeBtn = event.target.closest("[data-order-line-remove]");
        if (!removeBtn) {
          return;
        }
        event.preventDefault();
        const row = removeBtn.closest("[data-order-line]");
        if (row) {
          row.remove();
        }
        const container = selectors.orderEditor.lines;
        if (container && !container.querySelector("[data-order-line]")) {
          container.innerHTML =
            '<p class="events-order-empty">No tickets on this order yet.</p>';
        }
        updateOrderSummary();
        updateOrderAddOptions();
      });
    }
    if (selectors.orderEditor.status) {
      selectors.orderEditor.status.addEventListener("change", () => {
        updateOrderSummary();
      });
    }
    if (selectors.orderEditor.addButton) {
      selectors.orderEditor.addButton.addEventListener("click", () => {
        addOrderLine();
      });
    }
  }

  function openModal(modal) {
    if (!modal) {
      return;
    }
    modal.classList.add("is-open");
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }
    modal.classList.remove("is-open");
    if (modal === selectors.mediaModal) {
      state.media.currentSetter = null;
    }
    if (modal === selectors.orderEditor.modal) {
      resetOrderEditor();
      return;
    }
    if (modal?.dataset?.eventsModal === "form-builder") {
      resetFormBuilder();
      return;
    }
    const form = modal.querySelector("form");
    if (form) {
      form.reset();
      const editor = form.querySelector("[data-events-editor]");
      if (editor) {
        editor.innerHTML = "";
      }
      const tickets = form.querySelector("[data-events-tickets]");
      if (tickets) {
        tickets.innerHTML =
          '<div class="events-ticket-empty">No ticket types yet. Add one to begin selling.</div>';
      }
    }
  }

  function bindModalDismissals() {
    document.querySelectorAll("[data-events-close]").forEach((button) => {
      button.addEventListener("click", () => {
        const backdrop = button.closest(".events-modal-backdrop");
        if (!backdrop) {
          return;
        }
        if (backdrop.dataset?.eventsModal === "form-builder") {
          state.formBuilder.targetSelect = null;
        }
        if (backdrop === selectors.categoriesModal) {
          closeCategoryModal();
        } else {
          closeModal(backdrop);
        }
      });
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeModal(selectors.modal);
        closeModal(selectors.confirmModal);
        closeModal(selectors.mediaModal);
        closeModal(selectors.orderEditor.modal);
        state.formBuilder.targetSelect = null;
        closeModal(selectors.formBuilder.modal);
        closeCategoryModal();
      }
    });
  }

  function fillEventForm(eventData) {
    const modal = selectors.modal;
    if (!modal) {
      return;
    }
    eventSaveState.isReady = false;
    const form = modal.querySelector('[data-events-form="event"]');
    form.reset();
    form.querySelector('[name="id"]').value = eventData?.id || "";
    form.querySelector('[name="title"]').value = eventData?.title || "";
    form.querySelector('[name="location"]').value = eventData?.location || "";
    const formSelect = form.querySelector("[data-events-event-form]");
    renderEventFormOptions(formSelect, eventData?.form_id || "");
    if (!form.__imagePicker) {
      form.__imagePicker = initImagePicker(form);
    }
    if (
      form.__imagePicker &&
      typeof form.__imagePicker.setValue === "function"
    ) {
      form.__imagePicker.setValue(eventData?.image || "");
    } else {
      const imageInput = form.querySelector('[name="image"]');
      if (imageInput) {
        imageInput.value = eventData?.image || "";
      }
    }
    const startInput = form.querySelector('[name="start"]');
    if (startInput) {
      startInput.value = getInputDateValue(eventData?.start);
    }
    const endInput = form.querySelector('[name="end"]');
    if (endInput) {
      endInput.value = getInputDateValue(eventData?.end);
    }
    const statusValue = eventData?.status || "draft";
    const statusInput = form.querySelector(
      `[name="status"][value="${statusValue}"]`,
    );
    if (statusInput) {
      statusInput.checked = true;
    }
    const publishInput = form.querySelector('[name="publish_at"]');
    if (publishInput) {
      publishInput.value = getInputDateValue(eventData?.publish_at);
    }
    const unpublishInput = form.querySelector('[name="unpublish_at"]');
    if (unpublishInput) {
      unpublishInput.value = getInputDateValue(eventData?.unpublish_at);
    }
    const editor = form.querySelector("[data-events-editor]");
    const target = form.querySelector("[data-events-editor-target]");
    if (editor && target) {
      editor.innerHTML = eventData?.description || "";
      target.value = eventData?.description || "";
    }
    renderCategoryOptions(
      Array.isArray(eventData?.categories) ? eventData.categories : [],
    );
    const ticketContainer = form.querySelector("[data-events-tickets]");
    ticketContainer.innerHTML = "";
    const tickets = Array.isArray(eventData?.tickets) ? eventData.tickets : [];
    if (tickets.length === 0) {
      ticketContainer.innerHTML =
        '<div class="events-ticket-empty">No ticket types yet. Add one to begin selling.</div>';
    } else {
      tickets.forEach((ticket) => addTicketRow(ticketContainer, ticket));
    }
    updateEventModalStatusBadge(form);
    resetEventSaveState();
  }

  function renderEventFormOptions(select, selectedId = "") {
    if (!select) {
      return;
    }
    const hasForms = Array.isArray(state.forms) && state.forms.length > 0;
    select.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = hasForms
      ? "No form selected"
      : "No forms available";
    select.appendChild(placeholder);
    if (!hasForms) {
      select.disabled = true;
      select.value = "";
      return;
    }
    select.disabled = false;
    state.forms.forEach((formOption) => {
      const option = document.createElement("option");
      option.value = formOption.id;
      option.textContent = formOption.name;
      select.appendChild(option);
    });
    if (selectedId) {
      select.value = String(selectedId);
      if (select.value !== String(selectedId)) {
        select.value = "";
      }
    } else {
      select.value = "";
    }
  }

  function addTicketRow(container, ticket = {}) {
    if (!container) {
      return;
    }
    const row = document.createElement("div");
    row.className = "events-ticket-row";
    const onSaleValue = formatDateTimeLocal(
      ticket.on_sale || ticket.onSale || "",
    );
    const offSaleValue = formatDateTimeLocal(
      ticket.off_sale || ticket.offSale || "",
    );
    row.innerHTML = `
            <input type="hidden" data-ticket-field="id" value="${ticket.id || ""}">
            <label>
                <span>Name</span>
                <input type="text" data-ticket-field="name" value="${ticket.name || ""}" required>
            </label>
            <label>
                <span>Price</span>
                <input type="number" min="0" step="0.01" data-ticket-field="price" value="${ticket.price ?? 0}" required>
            </label>
            <label>
                <span>Quantity</span>
                <input type="number" min="0" step="1" data-ticket-field="quantity" value="${ticket.quantity ?? 0}" required>
            </label>
            <label>
                <span>On-sale starts (optional)</span>
                <input type="datetime-local" data-ticket-field="on_sale" value="${escapeAttribute(onSaleValue)}">
            </label>
            <label>
                <span>Off-sale ends (optional)</span>
                <input type="datetime-local" data-ticket-field="off_sale" value="${escapeAttribute(offSaleValue)}">
            </label>
            <label class="events-ticket-toggle">
                <input type="checkbox" data-ticket-field="enabled" ${ticket.enabled === false ? "" : "checked"}>
                <span>Enabled</span>
            </label>
            <button type="button" class="events-action danger" data-ticket-remove>
                <i class="fa-solid fa-times"></i><span class="sr-only">Remove ticket</span>
            </button>
        `;
    const empty = container.querySelector(".events-ticket-empty");
    if (empty) {
      empty.remove();
    }
    container.appendChild(row);
  }

  function gatherTickets(container) {
    const rows = Array.from(container.querySelectorAll(".events-ticket-row"));
    return rows
      .map((row) => {
        const onSaleInput = row.querySelector('[data-ticket-field="on_sale"]');
        const offSaleInput = row.querySelector(
          '[data-ticket-field="off_sale"]',
        );
        return {
          id: row.querySelector('[data-ticket-field="id"]').value || undefined,
          name: row.querySelector('[data-ticket-field="name"]').value.trim(),
          price: parseFloat(
            row.querySelector('[data-ticket-field="price"]').value || "0",
          ),
          quantity: parseInt(
            row.querySelector('[data-ticket-field="quantity"]').value || "0",
            10,
          ),
          enabled: row.querySelector('[data-ticket-field="enabled"]').checked,
          on_sale: onSaleInput ? onSaleInput.value.trim() : "",
          off_sale: offSaleInput ? offSaleInput.value.trim() : "",
        };
      })
      .filter((ticket) => ticket.name !== "");
  }

  function serializeForm(form) {
    const formData = new FormData(form);
    const payload = {};
    formData.forEach((value, key) => {
      if (key.endsWith("[]")) {
        const base = key.slice(0, -2);
        if (!Array.isArray(payload[base])) {
          payload[base] = [];
        }
        payload[base].push(value);
      } else {
        payload[key] = value;
      }
    });
    if (typeof payload.image === "string") {
      payload.image = payload.image.trim();
    }
    if (typeof payload.buyer_email === "string") {
      payload.buyer_email = payload.buyer_email.trim();
    }
    if (typeof payload.buyer_phone === "string") {
      payload.buyer_phone = payload.buyer_phone.trim();
    }
    if (typeof payload.publish_at === "string") {
      payload.publish_at = payload.publish_at.trim();
    }
    if (typeof payload.unpublish_at === "string") {
      payload.unpublish_at = payload.unpublish_at.trim();
    }
    if (typeof payload.form_id === "string") {
      payload.form_id = payload.form_id.trim();
    }
    return payload;
  }

  function setupEditor(form) {
    const toolbar = form.querySelector(".events-editor-toolbar");
    const editor = form.querySelector("[data-events-editor]");
    const target = form.querySelector("[data-events-editor-target]");
    if (!toolbar || !editor || !target) {
      return;
    }
    toolbar.addEventListener("click", (event) => {
      const command = event.target.closest("[data-editor-command]")?.dataset
        .editorCommand;
      if (!command) {
        return;
      }
      event.preventDefault();
      document.execCommand(command, false, null);
      target.value = editor.innerHTML;
      markEventDirty();
    });
    editor.addEventListener("input", () => {
      target.value = editor.innerHTML;
      markEventDirty();
    });
  }

  function handleEventForm() {
    const modal = selectors.modal;
    if (!modal) {
      return;
    }
    const form = modal.querySelector('[data-events-form="event"]');
    setupEditor(form);
    if (!form.__imagePicker) {
      form.__imagePicker = initImagePicker(form);
    }
    form.querySelectorAll('[name="status"]').forEach((input) => {
      input.addEventListener("change", () => updateEventModalStatusBadge(form));
    });
    const scheduleInputs = form.querySelectorAll(
      '[name="publish_at"], [name="unpublish_at"]',
    );
    scheduleInputs.forEach((input) => {
      input.addEventListener("change", () => updateEventModalStatusBadge(form));
      input.addEventListener("input", () => updateEventModalStatusBadge(form));
    });
    form.addEventListener("input", markEventDirty);
    form.addEventListener("change", markEventDirty);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const payload = serializeForm(form);
      payload.tickets = gatherTickets(
        form.querySelector("[data-events-tickets]"),
      );
      eventSaveState.isSaving = true;
      setEventSaveState("saving");
      return fetchJSON("save_event", { method: "POST", body: payload })
        .then((response) => {
          if (response?.event?.id) {
            storeEvent(response.event);
          }
          eventSaveState.isDirty = false;
          setEventSaveState("saved");
          closeModal(selectors.modal);
          showToast("Event saved successfully.");
          refreshAll();
        })
        .catch(() => {
          showToast("Unable to save event.", "error");
          eventSaveState.isDirty = true;
          setEventSaveState("unsaved");
        })
        .finally(() => {
          eventSaveState.isSaving = false;
        });
    });
    modal.addEventListener("click", (event) => {
      if (event.target.matches("[data-ticket-remove]")) {
        const row = event.target.closest(".events-ticket-row");
        if (row) {
          row.remove();
        }
        const container = modal.querySelector("[data-events-tickets]");
        if (container && container.children.length === 0) {
          container.innerHTML =
            '<div class="events-ticket-empty">No ticket types yet. Add one to begin selling.</div>';
        }
      }
    });
    const addTicketBtn = modal.querySelector("[data-events-ticket-add]");
    addTicketBtn.addEventListener("click", () => {
      addTicketRow(modal.querySelector("[data-events-tickets]"));
    });
  }

  function openEventModal(eventId) {
    const modal = selectors.modal;
    if (!modal) {
      return;
    }
    const title = modal.querySelector(".events-modal-title");
    title.textContent = eventId ? "Edit event" : "Create event";
    if (eventId) {
      fetchJSON("get_event", { params: { id: eventId } })
        .then((response) => {
          const event = response.event ? withSchedule(response.event) : {};
          if (event.id) {
            storeEvent(event);
          }
          fillEventForm(event);
          openModal(modal.closest(".events-modal-backdrop"));
        })
        .catch(() => {
          showToast("Unable to load event.", "error");
        });
    } else {
      fillEventForm({});
      openModal(modal.closest(".events-modal-backdrop"));
    }
  }

  function openConfirm(message, onConfirm) {
    const modal = selectors.confirmModal;
    if (!modal) {
      return;
    }
    modal.querySelector("[data-events-confirm-message]").textContent = message;
    state.confirm = onConfirm;
    openModal(modal);
  }

  function attachConfirmHandler() {
    if (!selectors.confirmModal) {
      return;
    }
    selectors.confirmModal
      .querySelector("[data-events-confirm]")
      .addEventListener("click", () => {
        if (typeof state.confirm === "function") {
          state.confirm();
        }
        state.confirm = null;
        closeModal(selectors.confirmModal);
      });
  }

  function refreshOrders() {
    return fetchJSON("list_orders", { params: state.ordersFilter })
      .then((response) => {
        const orders = Array.isArray(response.orders) ? response.orders : [];
        state.orders = orders
          .map((order) => normalizeOrderRow(order))
          .filter((order) => order !== null);
        renderOrdersTable();
        updateReportsView();
      })
      .catch(() => {
        showToast("Unable to load orders.", "error");
      });
  }

  function refreshEvents() {
    return fetchJSON("list_events")
      .then((response) => {
        const rows = Array.isArray(response.events) ? response.events : [];
        state.eventRows = rows
          .map((row) => normalizeEventRecord(row))
          .filter((row) => row !== null);
        rows.forEach((row) => {
          storeEvent(row);
        });
        renderEventsTable();
        populateEventSelect(selectors.orders.filterEvent);
        updateReportsView();
      })
      .catch(() => {
        showToast("Unable to load events.", "error");
      });
  }

  function refreshOverview() {
    return fetchJSON("overview")
      .then((response) => {
        renderStats({
          total_events: response.stats?.total_events,
          total_tickets_sold: response.stats?.total_tickets_sold,
          total_revenue: response.stats?.total_revenue,
        });
        const upcoming = Array.isArray(response.upcoming)
          ? response.upcoming
          : [];
        renderUpcoming(upcoming);
      })
      .catch(() => {
        showToast("Unable to refresh overview.", "error");
      });
  }

  function refreshReportsSummary() {
    return fetchJSON("reports_summary")
      .then((response) => {
        const reports = Array.isArray(response.reports) ? response.reports : [];
        state.salesSummary = reports
          .map((report) => normalizeReportRow(report))
          .filter((report) => report !== null);
        updateReportsView();
      })
      .catch(() => {
        showToast("Unable to load reports data.", "error");
      });
  }

  function refreshCategories() {
    return fetchJSON("list_categories")
      .then((response) => {
        if (Array.isArray(response.categories)) {
          state.categories = sortCategories(response.categories);
          renderCategoryList();
          renderCategoryOptions(getSelectedCategoryIds());
          renderEventsTable();
        }
      })
      .catch(() => {
        showToast("Unable to load categories.", "error");
      });
  }

  function refreshAll() {
    refreshEvents();
    refreshOrders();
    refreshOverview();
    refreshReportsSummary();
    refreshCategories();
  }

  function attachEventListeners() {
    if (selectors.filters.status) {
      selectors.filters.status.addEventListener("change", (event) => {
        state.filters.status = event.target.value;
        renderEventsTable();
      });
    }
    if (selectors.filters.search) {
      selectors.filters.search.addEventListener("input", (event) => {
        state.filters.search = event.target.value;
        renderEventsTable();
      });
    }
    if (selectors.reports.filters?.timeframe) {
      selectors.reports.filters.timeframe.addEventListener(
        "change",
        (event) => {
          const value = event.target.value || "all";
          state.reportsFilter.timeframe = value;
          updateReportsView();
        },
      );
    }
    if (selectors.reports.filters?.status) {
      selectors.reports.filters.status.addEventListener("change", (event) => {
        state.reportsFilter.status = event.target.value || "";
        updateReportsView();
      });
    }
    root.addEventListener("click", (event) => {
      const action = event.target.closest("[data-events-action]");
      if (!action) {
        return;
      }
      const id = action.dataset.id;
      switch (action.dataset.eventsAction) {
        case "edit":
          openEventModal(id);
          break;
        case "duplicate":
          fetchJSON("duplicate_event", { method: "POST", body: { id } })
            .then((response) => {
              const event = response?.event;
              if (!event?.id) {
                throw new Error("Invalid response");
              }
              storeEvent(event);
              showToast("Event duplicated.");
              refreshAll();
              openEventModal(event.id);
            })
            .catch(() => {
              showToast("Unable to duplicate event.", "error");
            });
          break;
        case "sales":
          selectors.tabs.activate?.("orders");
          document
            .getElementById("eventsOrdersTitle")
            ?.scrollIntoView({ behavior: "smooth" });
          if (selectors.orders.filterEvent) {
            selectors.orders.filterEvent.value = id;
            state.ordersFilter.event = id;
            refreshOrders();
          }
          break;
        case "end":
          openConfirm(
            "End this event? It will move to the ended state.",
            () => {
              fetchJSON("end_event", { method: "POST", body: { id } })
                .then(() => {
                  showToast("Event ended.");
                  refreshAll();
                })
                .catch(() => {
                  showToast("Unable to end event.", "error");
                });
            },
          );
          break;
        case "delete":
          openConfirm("Delete this event? This cannot be undone.", () => {
            fetchJSON("delete_event", { method: "POST", body: { id } })
              .then(() => {
                state.events.delete(id);
                showToast("Event deleted.");
                refreshAll();
              })
              .catch(() => {
                showToast("Unable to delete event.", "error");
              });
          });
          break;
        default:
          break;
      }
    });
    root.addEventListener("click", (event) => {
      const manage = event.target.closest("[data-events-order-manage]");
      if (!manage) {
        return;
      }
      const id = manage.dataset.id;
      if (id) {
        openOrderModal(id);
      }
    });
    if (selectors.orders.filterEvent) {
      selectors.orders.filterEvent.addEventListener("change", (event) => {
        state.ordersFilter.event = event.target.value;
        refreshOrders();
      });
    }
    if (selectors.orders.filterStatus) {
      selectors.orders.filterStatus.addEventListener("change", (event) => {
        state.ordersFilter.status = event.target.value;
        refreshOrders();
      });
    }
    if (selectors.orders.exportBtn) {
      selectors.orders.exportBtn.addEventListener("click", () => {
        window.open(`${endpoint}?action=export_orders`, "_blank");
      });
    }
    const reportButtons = root.querySelectorAll(
      "[data-events-report-download]",
    );
    reportButtons.forEach((button) => {
      button.addEventListener("click", () => {
        const type = button.dataset.eventsReportDownload || "summary";
        downloadReport(type);
      });
    });
  }

  function handleCategoryForm() {
    if (selectors.categoriesForm) {
      selectors.categoriesForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const formData = new FormData(selectors.categoriesForm);
        const payload = {
          id: formData.get("id") || undefined,
          name: String(formData.get("name") || "").trim(),
          slug: String(formData.get("slug") || "").trim(),
        };
        if (!payload.name) {
          showToast("Category name is required.", "error");
          return;
        }
        if (!payload.id) {
          delete payload.id;
        }
        if (!payload.slug) {
          delete payload.slug;
        }
        const isUpdate = Boolean(state.categoryEditing);
        fetchJSON("save_category", { method: "POST", body: payload })
          .then((response) => {
            if (Array.isArray(response.categories)) {
              state.categories = sortCategories(response.categories);
            }
            renderCategoryList();
            renderCategoryOptions(getSelectedCategoryIds());
            renderEventsTable();
            showToast(isUpdate ? "Category updated." : "Category created.");
            resetCategoryForm();
          })
          .catch(() => {
            showToast("Unable to save category.", "error");
          });
      });
    }
    if (selectors.categoriesReset) {
      selectors.categoriesReset.addEventListener("click", (event) => {
        event.preventDefault();
        resetCategoryForm();
      });
    }
    if (selectors.categoriesModal) {
      selectors.categoriesModal.addEventListener("click", (event) => {
        const editBtn = event.target.closest("[data-events-category-edit]");
        if (editBtn) {
          const category = state.categories.find(
            (item) => String(item.id) === String(editBtn.dataset.id),
          );
          if (category) {
            fillCategoryForm(category);
            selectors.categoriesForm?.querySelector('[name="name"]').focus();
          }
          return;
        }
        const deleteBtn = event.target.closest("[data-events-category-delete]");
        if (deleteBtn) {
          const id = deleteBtn.dataset.id;
          openConfirm(
            "Delete this category? It will be removed from any events.",
            () => {
              fetchJSON("delete_category", { method: "POST", body: { id } })
                .then((response) => {
                  if (Array.isArray(response.categories)) {
                    state.categories = sortCategories(response.categories);
                  } else {
                    state.categories = [];
                  }
                  state.events.forEach((event) => {
                    if (Array.isArray(event.categories)) {
                      event.categories = event.categories.filter(
                        (categoryId) => String(categoryId) !== String(id),
                      );
                    }
                  });
                  renderCategoryList();
                  renderCategoryOptions(getSelectedCategoryIds());
                  renderEventsTable();
                  showToast("Category deleted.");
                  resetCategoryForm();
                })
                .catch(() => {
                  showToast("Unable to delete category.", "error");
                });
            },
          );
        }
      });
    }
  }

  function init() {
    bindModalDismissals();
    attachConfirmHandler();
    handleEventForm();
    handleOrderForm();
    handleCategoryForm();
    initFormBuilder();
    initMediaPicker();
    attachEventListeners();
    populateEventSelect(selectors.orders.filterEvent);
    renderEventsTable();
    renderOrdersTable();
    updateReportsView();
    renderCategoryOptions();
    renderCategoryList();
    refreshAll();
  }

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-events-open]");
    if (!trigger) {
      return;
    }
    const type = trigger.dataset.eventsOpen;
    if (type === "event") {
      openEventModal(null);
    } else if (type === "categories") {
      openCategoriesModal();
    } else if (type === "form-builder") {
      openFormBuilder(trigger);
    }
  });

  init();
})();
