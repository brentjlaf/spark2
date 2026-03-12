// File: search.js
(function (window, $) {
  const STORAGE_KEY = "sparkcms.search.history";
  const MAX_HISTORY = 15;
  const MAX_SUGGESTIONS = 8;

  function parseJSON(value) {
    if (!value) {
      return [];
    }
    try {
      return JSON.parse(value);
    } catch (error) {
      return [];
    }
  }

  function levenshtein(a, b) {
    const matrix = [];
    const lenA = a.length;
    const lenB = b.length;
    for (let i = 0; i <= lenB; i += 1) {
      matrix[i] = [i];
    }
    for (let j = 0; j <= lenA; j += 1) {
      matrix[0][j] = j;
    }
    for (let i = 1; i <= lenB; i += 1) {
      for (let j = 1; j <= lenA; j += 1) {
        if (b.charAt(i - 1) === a.charAt(j - 1)) {
          matrix[i][j] = matrix[i - 1][j - 1];
        } else {
          matrix[i][j] = Math.min(
            matrix[i - 1][j - 1] + 1,
            matrix[i][j - 1] + 1,
            matrix[i - 1][j] + 1,
          );
        }
      }
    }
    return matrix[lenB][lenA];
  }

  function normaliseTerm(term) {
    return (term || "").toString().trim();
  }

  function lower(term) {
    return normaliseTerm(term).toLowerCase();
  }

  function scoreMatch(value, query) {
    const target = lower(value);
    if (!query) {
      return 0;
    }
    if (target === query) {
      return -6;
    }
    if (target.startsWith(query)) {
      return -4;
    }
    if (target.indexOf(query) !== -1) {
      return -2;
    }
    const distance = levenshtein(
      query,
      target.slice(0, Math.max(query.length, 3)),
    );
    return Math.max(0, distance);
  }

  const SparkSearch = {
    $input: null,
    $container: null,
    $panel: null,
    submitCallback: null,
    activeIndex: -1,
    currentSuggestions: [],
    history: [],
    suggestionPool: [],

    mount({ input, container, history = [], suggestions = [], onSubmit } = {}) {
      if (!input || !input.length) {
        return;
      }

      this.$input = input;
      this.$container =
        container && container.length
          ? container
          : input.closest(".search-box");
      this.submitCallback = typeof onSubmit === "function" ? onSubmit : null;

      this.ensurePanel();
      this.mergeHistory(history);
      this.mergeHistory(this.loadLocalHistory());
      this.mergeSuggestions(suggestions);
      this.registerEvents();
    },

    ensurePanel() {
      if (this.$panel && this.$panel.length) {
        return;
      }
      this.$panel = $(
        '<div class="search-suggestions" role="listbox" aria-label="Search suggestions"></div>',
      );
      if (this.$container && this.$container.length) {
        this.$container.append(this.$panel);
      }
    },

    registerEvents() {
      if (!this.$input) {
        return;
      }

      this.$input.off(".sparkSearch");
      this.$input.on("input.sparkSearch", (event) => {
        const value = normaliseTerm(event.target.value);
        this.showSuggestions(value);
      });
      this.$input.on("keydown.sparkSearch", (event) =>
        this.handleKeydown(event),
      );
      this.$input.on("focus.sparkSearch", () => {
        const value = normaliseTerm(this.$input.val());
        this.showSuggestions(value);
      });
      this.$input.on("blur.sparkSearch", () => {
        window.setTimeout(() => this.hideSuggestions(), 120);
      });
    },

    mergeHistory(history) {
      if (!Array.isArray(history)) {
        return;
      }
      const existing = new Map(
        this.history.map((item) => [lower(item.term), item]),
      );
      history.forEach((item) => {
        if (!item) {
          return;
        }
        const term = normaliseTerm(item.term || item.value || item);
        if (!term) {
          return;
        }
        const key = lower(term);
        const count = typeof item.count === "number" ? item.count : 1;
        const last = typeof item.last === "number" ? item.last : Date.now();
        if (existing.has(key)) {
          const record = existing.get(key);
          record.count = Math.max(record.count, count);
          record.last = Math.max(record.last, last);
          record.term = term;
        } else {
          existing.set(key, {
            term,
            count,
            last,
          });
        }
      });
      const merged = Array.from(existing.values());
      merged.sort((a, b) => {
        if (b.count === a.count) {
          return b.last - a.last;
        }
        return b.count - a.count;
      });
      this.history = merged.slice(0, MAX_HISTORY);
    },

    mergeSuggestions(suggestions) {
      if (!Array.isArray(suggestions)) {
        return;
      }
      const seen = new Set(
        this.suggestionPool.map(
          (item) => `${lower(item.value)}|${lower(item.type || "")}`,
        ),
      );
      suggestions.forEach((item) => {
        if (!item || !item.value) {
          return;
        }
        const key = `${lower(item.value)}|${lower(item.type || "")}`;
        if (seen.has(key)) {
          return;
        }
        seen.add(key);
        this.suggestionPool.push({
          value: item.value,
          label: item.label || item.value,
          type: item.type || "",
        });
      });
    },

    loadLocalHistory() {
      try {
        if (!window.localStorage) {
          return [];
        }
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
          return [];
        }
        const parsed = parseJSON(raw);
        return parsed.map((term) => ({
          term,
          count: 1,
          last: Date.now(),
        }));
      } catch (error) {
        return [];
      }
    },

    persistLocalHistory() {
      try {
        if (!window.localStorage) {
          return;
        }
        const terms = this.history
          .map((item) => item.term)
          .slice(0, MAX_HISTORY);
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(terms));
      } catch (error) {
        // Ignore storage errors (e.g., private mode).
      }
    },

    showSuggestions(term) {
      const query = lower(term);
      const suggestions = [];
      const used = new Set();
      const usedTerms = new Set();

      this.history.forEach((item) => {
        if (!item.term) {
          return;
        }
        const valueLower = lower(item.term);
        if (query && valueLower.indexOf(query) === -1) {
          const distance = levenshtein(
            query,
            valueLower.slice(0, Math.max(query.length, 3)),
          );
          if (distance > Math.max(2, Math.round(query.length * 0.4))) {
            return;
          }
        }
        const key = valueLower;
        if (used.has(key)) {
          return;
        }
        used.add(key);
        usedTerms.add(valueLower);
        suggestions.push({
          value: item.term,
          label: `${item.term} · Recent`,
          type: "history",
          count: item.count,
          score: scoreMatch(item.term, query),
        });
      });

      this.suggestionPool.forEach((item) => {
        if (suggestions.length >= MAX_SUGGESTIONS) {
          return;
        }
        const candidate = lower(item.value);
        if (usedTerms.has(candidate)) {
          return;
        }
        if (query) {
          if (candidate.indexOf(query) === -1) {
            const distance = levenshtein(
              query,
              candidate.slice(0, Math.max(query.length, 3)),
            );
            if (distance > Math.max(2, Math.round(query.length * 0.4))) {
              return;
            }
          }
        }
        const key = `${candidate}|${lower(item.type || "")}`;
        if (used.has(key)) {
          return;
        }
        used.add(key);
        suggestions.push({
          value: item.value,
          label: item.label || item.value,
          type: item.type || "",
          score: scoreMatch(item.value, query),
        });
      });

      suggestions.sort((a, b) => {
        if (a.score === b.score) {
          if (a.type === "history" && b.type === "history") {
            return (b.count || 0) - (a.count || 0);
          }
          if (a.type === "history") {
            return -1;
          }
          if (b.type === "history") {
            return 1;
          }
          return a.value.localeCompare(b.value);
        }
        return a.score - b.score;
      });

      this.currentSuggestions = suggestions.slice(0, MAX_SUGGESTIONS);
      this.renderSuggestions();
    },

    renderSuggestions() {
      if (!this.$panel) {
        return;
      }
      this.$panel.empty();
      if (!this.currentSuggestions.length) {
        this.$panel.removeClass("is-active");
        this.activeIndex = -1;
        return;
      }

      const fragment = document.createDocumentFragment();
      this.currentSuggestions.forEach((item, index) => {
        const option = document.createElement("button");
        option.type = "button";
        option.className = "search-suggestion";
        option.setAttribute("role", "option");
        option.setAttribute("data-index", index.toString());
        option.innerHTML = `<span class="search-suggestion__value">${$("<div>").text(item.value).html()}</span>`;
        if (item.type) {
          const meta = document.createElement("span");
          meta.className = "search-suggestion__meta";
          meta.textContent = item.type;
          option.appendChild(meta);
        }
        option.addEventListener("mousedown", (event) => {
          event.preventDefault();
          this.selectSuggestion(index);
        });
        fragment.appendChild(option);
      });
      this.$panel.append(fragment);
      this.$panel.addClass("is-active");
    },

    hideSuggestions() {
      if (this.$panel) {
        this.$panel.removeClass("is-active");
      }
      this.activeIndex = -1;
    },

    handleKeydown(event) {
      if (!this.currentSuggestions.length) {
        if (event.key === "Enter" && this.$input && this.$input.val()) {
          event.preventDefault();
          this.submit(this.$input.val());
        }
        return;
      }
      if (event.key === "ArrowDown") {
        event.preventDefault();
        this.activeIndex =
          (this.activeIndex + 1) % this.currentSuggestions.length;
        this.highlightActive();
      } else if (event.key === "ArrowUp") {
        event.preventDefault();
        this.activeIndex =
          this.activeIndex <= 0
            ? this.currentSuggestions.length - 1
            : this.activeIndex - 1;
        this.highlightActive();
      } else if (event.key === "Enter") {
        event.preventDefault();
        if (this.activeIndex >= 0) {
          this.selectSuggestion(this.activeIndex);
        } else if (this.$input && this.$input.val()) {
          this.submit(this.$input.val());
        }
      } else if (event.key === "Escape") {
        this.hideSuggestions();
      }
    },

    highlightActive() {
      if (!this.$panel) {
        return;
      }
      this.$panel.find(".search-suggestion").removeClass("is-active");
      if (this.activeIndex < 0) {
        return;
      }
      const $target = this.$panel.find(
        `.search-suggestion[data-index="${this.activeIndex}"]`,
      );
      $target.addClass("is-active");
    },

    selectSuggestion(index) {
      const item = this.currentSuggestions[index];
      if (!item) {
        return;
      }
      if (this.$input) {
        this.$input.val(item.value);
      }
      this.hideSuggestions();
      this.submit(item.value);
    },

    submit(query, extra = {}) {
      const term = normaliseTerm(query);
      if (!term) {
        return;
      }
      this.addToHistory(term);
      this.hideSuggestions();
      if (this.submitCallback) {
        this.submitCallback(term, extra);
      }
    },

    addToHistory(term) {
      const key = lower(term);
      const existing = this.history.find((item) => lower(item.term) === key);
      if (existing) {
        existing.count += 1;
        existing.last = Date.now();
        existing.term = term;
      } else {
        this.history.unshift({ term, count: 1, last: Date.now() });
      }
      this.history.sort((a, b) => {
        if (b.count === a.count) {
          return b.last - a.last;
        }
        return b.count - a.count;
      });
      this.history = this.history.slice(0, MAX_HISTORY);
      this.persistLocalHistory();
    },

    bootstrapFromModule($module) {
      if (!$module || !$module.length) {
        return;
      }
      const historyAttr = $module.attr("data-history");
      const suggestionAttr = $module.attr("data-suggestions");
      const selectedAttr = $module.attr("data-selected-types");
      const query = $module.attr("data-query") || "";

      this.mergeHistory(parseJSON(historyAttr));
      this.mergeSuggestions(parseJSON(suggestionAttr));
      this.persistLocalHistory();

      if (this.$input) {
        this.$input.val(query);
      }

      const selectedTypes = parseJSON(selectedAttr);
      this.applyTypeFilters(Array.isArray(selectedTypes) ? selectedTypes : []);
      this.updateSummary(query);
      if (query) {
        $("#pageTitle").text("Search: " + query);
      } else {
        $("#pageTitle").text("Search");
      }
    },

    applyTypeFilters(types, allowEmpty = false) {
      const normalized = Array.isArray(types)
        ? types.map((type) => lower(type))
        : [];
      const showAll = !allowEmpty && normalized.length === 0;
      const $rows = $(".search-table tbody tr[data-type]");
      const counts = { Page: 0, Post: 0, Media: 0 };
      let visible = 0;

      $rows.each(function () {
        const $row = $(this);
        const type = $row.data("type") || "";
        const typeKey = lower(type);
        const shouldShow = showAll || normalized.indexOf(typeKey) !== -1;
        $row.toggle(shouldShow);
        if (shouldShow) {
          visible += 1;
          if (counts[type] !== undefined) {
            counts[type] += 1;
          }
        }
      });

      $('.search-empty-row[data-filter-empty="true"]').toggle(
        visible === 0 && $rows.length > 0,
      );

      $("#searchCountPages").text((counts.Page || 0).toLocaleString());
      $("#searchCountPosts").text((counts.Post || 0).toLocaleString());
      $("#searchCountMedia").text((counts.Media || 0).toLocaleString());

      const query = $("#search").attr("data-query") || "";
      this.updateSummary(query, visible);
    },

    updateSummary(query, visibleCount) {
      const $meta = $(".search-results-card__meta");
      if (!$meta.length) {
        return;
      }
      let visible =
        typeof visibleCount === "number"
          ? visibleCount
          : $(".search-table tbody tr[data-type]:visible").length;
      const summary =
        visible === 1
          ? "Showing 1 result"
          : `Showing ${visible.toLocaleString()} results`;
      const suffix = query ? ` for “${query}”` : "";
      $meta.text(summary + suffix);
    },
  };

  $(document).on("click", "[data-search-term]", function (event) {
    event.preventDefault();
    const term = normaliseTerm($(this).data("search-term"));
    if (!term || !window.SparkSearch) {
      return;
    }
    window.SparkSearch.submit(term);
  });

  $(document).on(
    "change",
    '.search-filters input[type="checkbox"]',
    function () {
      const types = $('.search-filters input[type="checkbox"]:checked')
        .map(function () {
          return $(this).val();
        })
        .get();
      if (window.SparkSearch) {
        window.SparkSearch.applyTypeFilters(types, true);
      }
    },
  );

  window.SparkSearch = Object.assign(window.SparkSearch || {}, SparkSearch);

  $(function () {
    const $module = $("#search");
    if ($module.length && window.SparkSearch) {
      window.SparkSearch.bootstrapFromModule($module);
      const query = $module.data("query");
      if (query) {
        $("#pageTitle").text("Search: " + query);
      } else {
        $("#pageTitle").text("Search");
      }
    }
  });
})(window, jQuery);
