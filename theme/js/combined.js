/* File: combined.js - merged from global.js, script.js */
/* File: global.js */
// File: global.js
(function () {
  var blogPostsPromise = null;
  var commerceDataPromise = null;
  var mapDataPromise = null;
  var leafletPromise = null;

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn, { once: true });
    } else {
      fn();
    }
  }

  function normalizeBasePath() {
    var base = typeof window.cmsBase === "string" ? window.cmsBase : "";
    base = base ? String(base).trim() : "";
    if (!base || base === "/") {
      return "";
    }
    if (base.charAt(0) !== "/") {
      base = "/" + base;
    }
    return base.replace(/\/+$/, "");
  }

  function fetchBlogPosts() {
    if (blogPostsPromise) {
      return blogPostsPromise;
    }
    var base = normalizeBasePath();
    var url = base + "/CMS/public_data.php?action=blog_posts";
    blogPostsPromise = fetch(url, {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to load blog posts");
        }
        return response.json();
      })
      .then(function (posts) {
        if (!Array.isArray(posts)) {
          return [];
        }
        return posts.filter(function (post) {
          return (
            post && String(post.status || "").toLowerCase() === "published"
          );
        });
      })
      .catch(function (error) {
        console.error("[SparkCMS] Blog list error:", error);
        blogPostsPromise = null;
        throw error;
      });
    return blogPostsPromise;
  }

  function fetchCommerceData() {
    if (commerceDataPromise) {
      return commerceDataPromise;
    }
    var base = normalizeBasePath();
    var url = base + "/CMS/public_data.php?action=commerce";
    commerceDataPromise = fetch(url, {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to load commerce data");
        }
        return response.json();
      })
      .catch(function (error) {
        console.error("[SparkCMS] Commerce grid error:", error);
        commerceDataPromise = null;
        throw error;
      });
    return commerceDataPromise;
  }

  function ensureLeaflet() {
    if (window.L && typeof window.L.map === "function") {
      return Promise.resolve(window.L);
    }
    if (leafletPromise) {
      return leafletPromise;
    }
    leafletPromise = new Promise(function (resolve, reject) {
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
        script.integrity =
          "sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=";
        script.crossOrigin = "";
        script.async = true;
        script.defer = true;
        script.setAttribute("data-leaflet", "true");
      }

      function cleanup(message) {
        leafletPromise = null;
        reject(new Error(message || "Leaflet failed to load"));
      }

      if (script.getAttribute("data-loaded") === "true") {
        if (window.L && typeof window.L.map === "function") {
          resolve(window.L);
        } else {
          cleanup("Leaflet failed to initialize");
        }
        return;
      }

      script.addEventListener(
        "load",
        function handleLoad() {
          script.setAttribute("data-loaded", "true");
          if (window.L && typeof window.L.map === "function") {
            resolve(window.L);
          } else {
            cleanup("Leaflet failed to initialize");
          }
        },
        { once: true },
      );

      script.addEventListener(
        "error",
        function () {
          cleanup("Leaflet network error");
        },
        { once: true },
      );

      if (!script.parentNode) {
        document.head.appendChild(script);
      }
    });
    return leafletPromise;
  }

  function sanitizeHexColor(value, fallback) {
    var color = typeof value === "string" ? value.trim() : "";
    if (!color) {
      return fallback;
    }
    if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(color)) {
      return fallback;
    }
    return color;
  }

  function normalizeIconClassList(icon) {
    var value = typeof icon === "string" ? icon.trim() : "";
    var parts = value ? value.split(/\s+/) : [];
    var hasStyle = parts.some(function (cls) {
      return (
        cls === "fa-solid" ||
        cls === "fa-regular" ||
        cls === "fa-light" ||
        cls === "fa-thin" ||
        cls === "fa-duotone" ||
        cls === "fa-brands"
      );
    });
    if (!hasStyle) {
      parts.unshift("fa-solid");
    }
    if (
      !parts.some(function (cls) {
        return (
          /^fa-[a-z0-9-]+$/i.test(cls) &&
          cls !== "fa-solid" &&
          cls !== "fa-regular" &&
          cls !== "fa-light" &&
          cls !== "fa-thin" &&
          cls !== "fa-duotone" &&
          cls !== "fa-brands"
        );
      })
    ) {
      parts.push("fa-location-dot");
    }
    return parts.join(" ");
  }

  function fetchMapData() {
    if (mapDataPromise) {
      return mapDataPromise;
    }
    var base = normalizeBasePath();
    var locationsUrl = base + "/CMS/public_data.php?action=map_locations";
    var categoriesUrl = base + "/CMS/public_data.php?action=map_categories";
    mapDataPromise = Promise.all([
      fetch(locationsUrl, {
        credentials: "same-origin",
        cache: "no-store",
      }).then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to load map locations");
        }
        return response.json();
      }),
      fetch(categoriesUrl, {
        credentials: "same-origin",
        cache: "no-store",
      }).then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to load map categories");
        }
        return response.json();
      }),
    ])
      .then(function (results) {
        var locations = Array.isArray(results[0]) ? results[0] : [];
        var categories = Array.isArray(results[1]) ? results[1] : [];
        var categoryLookup = {};
        var cleanedCategories = categories.reduce(function (list, category) {
          if (!category || typeof category !== "object") {
            return list;
          }
          var id = String(category.id || "").trim();
          if (!id) {
            return list;
          }
          var color = sanitizeHexColor(category.color, "#2D70F5");
          var iconClass = String(category.icon || "fa-location-dot").trim();
          var record = {
            id: id,
            name: category.name || "Category",
            slug: category.slug || "",
            color: color,
            icon: iconClass,
            normalizedId: normalizeCategory(id),
            normalizedSlug: normalizeCategory(category.slug),
            normalizedName: normalizeCategory(category.name),
          };
          categoryLookup[id] = record;
          list.push(record);
          return list;
        }, []);

        var cleanedLocations = [];
        locations.forEach(function (location) {
          if (!location || typeof location !== "object") {
            return;
          }
          var id = String(location.id || "").trim();
          if (!id) {
            return;
          }
          var status = normalizeCategory(location.status);
          if (status !== "published") {
            return;
          }
          var coordinates = location.coordinates || {};
          var lat = Number(coordinates.lat);
          var lng = Number(coordinates.lng);
          if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
          }
          var rawCategories = Array.isArray(location.category_ids)
            ? location.category_ids
            : [];
          var categoriesForLocation = [];
          var matchKeys = [];
          if (!rawCategories.length) {
            matchKeys.push("uncategorized");
          }
          rawCategories.forEach(function (categoryId) {
            var idString = String(categoryId || "").trim();
            if (!idString) {
              return;
            }
            var meta = categoryLookup[idString] || null;
            if (meta) {
              categoriesForLocation.push(meta);
              if (meta.normalizedId) {
                matchKeys.push(meta.normalizedId);
              }
              if (meta.normalizedSlug) {
                matchKeys.push(meta.normalizedSlug);
              }
              if (meta.normalizedName) {
                matchKeys.push(meta.normalizedName);
              }
            } else {
              matchKeys.push(normalizeCategory(idString));
            }
          });
          var address = location.address || {};
          var street = address.street || "";
          var city = address.city || "";
          var region = address.region || "";
          var postalCode = address.postal_code || "";
          var country = address.country || "";
          var cityRegion = [city, region]
            .filter(function (part) {
              return String(part).trim() !== "";
            })
            .join(", ");
          var addressParts = [street, cityRegion, postalCode, country].filter(
            function (part) {
              return String(part).trim() !== "";
            },
          );
          var fullAddress = addressParts.join(", ");
          var contact = location.contact || {};
          var locationRecord = {
            id: id,
            name: location.name || "Untitled location",
            slug: location.slug || "",
            description: stripHtml(location.description || ""),
            lat: lat,
            lng: lng,
            categories: categoriesForLocation,
            matchKeys: Array.from(
              new Set(
                matchKeys.filter(function (entry) {
                  return entry && entry.length;
                }),
              ),
            ),
            primaryCategory: categoriesForLocation.length
              ? categoriesForLocation[0]
              : null,
            address: {
              street: street,
              city: city,
              region: region,
              postalCode: postalCode,
              country: country,
            },
            cityRegion: cityRegion,
            fullAddress: fullAddress,
            contact: {
              phone: contact.phone || "",
              email: contact.email || "",
              website: contact.website || "",
            },
          };
          cleanedLocations.push(locationRecord);
        });

        return {
          locations: cleanedLocations,
          categories: cleanedCategories,
          categoryLookup: categoryLookup,
        };
      })
      .catch(function (error) {
        console.error("[SparkCMS] Map block error:", error);
        mapDataPromise = null;
        throw error;
      });
    return mapDataPromise;
  }

  function parseLimit(value, fallback) {
    var limit = parseInt(value, 10);
    if (!Number.isFinite(limit) || limit < 1) {
      var defaultLimit =
        Number.isFinite(fallback) && fallback > 0 ? fallback : 6;
      return defaultLimit;
    }
    return limit;
  }

  function normalizeCategory(value) {
    return (value || "").toString().toLowerCase().trim();
  }

  function parseCategoriesList(value) {
    if (value == null) {
      return [];
    }
    return String(value)
      .split(",")
      .map(function (entry) {
        return entry.toLowerCase().trim();
      })
      .filter(function (entry) {
        return entry.length > 0;
      });
  }

  function parseMapZoom(value, fallback) {
    var zoom = parseInt(value, 10);
    if (!Number.isFinite(zoom)) {
      zoom = Number.isFinite(fallback) ? fallback : 6;
    }
    if (!Number.isFinite(zoom)) {
      zoom = 6;
    }
    if (zoom < 2) {
      zoom = 2;
    }
    if (zoom > 18) {
      zoom = 18;
    }
    return zoom;
  }

  function locationMatchesTokens(location, tokens) {
    if (!tokens || !tokens.length) {
      return true;
    }
    if (!location || !Array.isArray(location.matchKeys)) {
      return false;
    }
    return tokens.some(function (token) {
      return location.matchKeys.indexOf(token) !== -1;
    });
  }

  function resolveDetailUrl(prefix, slug) {
    if (!slug) {
      return "#";
    }
    var detail = (prefix || "").toString().trim();
    if (!detail) {
      detail = "/blogs";
    }
    if (/^https?:\/\//i.test(detail)) {
      return detail.replace(/\/+$/, "") + "/" + slug;
    }
    var base = normalizeBasePath();
    detail = detail.replace(/\/+$/, "");
    detail = detail.replace(/^\/+/, "");
    var path = detail ? detail + "/" + slug : slug;
    var start = base;
    if (start && start.charAt(0) !== "/") {
      start = "/" + start;
    }
    if (!start) {
      return "/" + path;
    }
    return start.replace(/\/+$/, "") + "/" + path;
  }

  function formatDate(value) {
    if (!value) {
      return "";
    }
    var date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return "";
    }
    try {
      return date.toLocaleDateString(undefined, {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
    } catch (err) {
      return date.toISOString().slice(0, 10);
    }
  }

  function clearContainer(container) {
    while (container.firstChild) {
      container.removeChild(container.firstChild);
    }
  }

  function showError(container, message) {
    var itemsHost = container.querySelector("[data-blog-items]") || container;
    clearContainer(itemsHost);
    var error = document.createElement("div");
    error.className = "blog-item blog-item--error";
    error.textContent = message || "Unable to load blog posts.";
    itemsHost.appendChild(error);
    var emptyState = container.querySelector("[data-blog-empty]");
    if (emptyState) {
      emptyState.classList.add("d-none");
    }
  }

  function resolveBlogBackHref(base) {
    var value = typeof base === "string" ? base.trim() : "";
    if (!value) {
      return "";
    }
    if (/^https?:\/\//i.test(value)) {
      return value;
    }
    var normalizedBase = normalizeBasePath();
    var path = value.replace(/^\/+/, "").replace(/\/+$/, "");
    if (!path) {
      if (!normalizedBase) {
        return "/";
      }
      if (normalizedBase.charAt(0) !== "/") {
        normalizedBase = "/" + normalizedBase;
      }
      return normalizedBase;
    }
    if (!normalizedBase) {
      return "/" + path;
    }
    if (normalizedBase.charAt(0) !== "/") {
      normalizedBase = "/" + normalizedBase;
    }
    return normalizedBase.replace(/\/+$/, "") + "/" + path;
  }

  function resolveBlogSlug(container) {
    if (!(container instanceof HTMLElement)) {
      return "";
    }
    var explicit = container.dataset.slug || container.dataset.blogSlug;
    if (explicit && explicit.trim()) {
      return explicit.trim();
    }
    if (typeof window === "undefined" || !window.location) {
      return "";
    }
    var path = window.location.pathname || "";
    if (!path) {
      return "";
    }
    var base = normalizeBasePath();
    if (base && path.indexOf(base) === 0) {
      path = path.slice(base.length);
    }
    path = path.replace(/[?#].*$/, "");
    path = path.replace(/\/+$/, "");
    path = path.replace(/^\/+/, "");
    if (!path) {
      return "";
    }
    var segments = path.split("/");
    if (!segments.length) {
      return "";
    }
    var last = segments[segments.length - 1];
    if (!last && segments.length > 1) {
      last = segments[segments.length - 2];
    }
    if (!last || last === "blogs" || last === "blog") {
      return "";
    }
    try {
      return decodeURIComponent(last);
    } catch (err) {
      return last;
    }
  }

  function extractBlogTags(value) {
    if (!value && value !== 0) {
      return [];
    }
    if (Array.isArray(value)) {
      return value
        .map(function (tag) {
          return typeof tag === "string" ? tag.trim() : "";
        })
        .filter(function (tag) {
          return !!tag;
        });
    }
    var text = String(value).trim();
    if (!text) {
      return [];
    }
    return text
      .split(",")
      .map(function (tag) {
        return tag.trim();
      })
      .filter(function (tag) {
        return !!tag;
      });
  }

  function showBlogDetailEmpty(container, message) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    var loading = container.querySelector("[data-blog-detail-loading]");
    if (loading) {
      loading.classList.add("d-none");
    }
    var main = container.querySelector("[data-blog-detail-main]");
    if (main) {
      main.classList.add("d-none");
    }
    var empty = container.querySelector("[data-blog-detail-empty]");
    if (empty) {
      empty.textContent =
        message || container.dataset.empty || "This post could not be found.";
      empty.classList.remove("d-none");
    }
  }

  function updateBlogDetailBackLinks(container, href, label) {
    var backWrapper = container.querySelector(
      "[data-blog-detail-back-wrapper]",
    );
    var backLink = container.querySelector("[data-blog-detail-back]");
    var topLabel = container.querySelector("[data-blog-detail-back-label]");
    var backActions = container.querySelector("[data-blog-detail-actions]");
    var backButton = container.querySelector(
      "[data-blog-detail-back-secondary]",
    );
    var buttonLabel = container.querySelector(
      "[data-blog-detail-back-button-label]",
    );
    var showBackLink = parseBooleanOption(container.dataset.showBackLink, true);
    var showBackButton = parseBooleanOption(
      container.dataset.showBackButton,
      true,
    );

    if (topLabel) {
      topLabel.textContent = label;
    }
    if (buttonLabel) {
      buttonLabel.textContent = label;
    }
    if (backLink) {
      if (href) {
        backLink.href = href;
        backLink.setAttribute("aria-label", label);
      } else {
        backLink.removeAttribute("href");
        backLink.removeAttribute("aria-label");
      }
    }
    if (backButton) {
      if (href) {
        backButton.href = href;
      } else {
        backButton.removeAttribute("href");
      }
    }
    if (backWrapper) {
      if (showBackLink && href) {
        backWrapper.classList.remove("d-none");
      } else {
        backWrapper.classList.add("d-none");
      }
    }
    if (backActions) {
      if (showBackButton && href) {
        backActions.classList.remove("d-none");
      } else {
        backActions.classList.add("d-none");
      }
    }
  }

  function renderBlogDetail(container, post) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    var loading = container.querySelector("[data-blog-detail-loading]");
    if (loading) {
      loading.classList.add("d-none");
    }
    var empty = container.querySelector("[data-blog-detail-empty]");
    if (empty) {
      empty.classList.add("d-none");
    }
    var main = container.querySelector("[data-blog-detail-main]");
    if (main) {
      main.classList.remove("d-none");
    }

    var backLabel = container.dataset.backLabel || "Back to all posts";
    var backHref = resolveBlogBackHref(container.dataset.backBase);
    updateBlogDetailBackLinks(container, backHref, backLabel);

    var categoryEl = container.querySelector("[data-blog-detail-category]");
    var categoryValue =
      typeof post.category === "string" ? post.category.trim() : "";
    var showCategory = parseBooleanOption(container.dataset.showCategory, true);
    if (categoryEl) {
      if (showCategory && categoryValue) {
        categoryEl.textContent = categoryValue;
        categoryEl.classList.remove("d-none");
      } else {
        categoryEl.textContent = "";
        categoryEl.classList.add("d-none");
      }
    }

    var titleEl = container.querySelector("[data-blog-detail-title]");
    if (titleEl) {
      titleEl.textContent = post.title || "Untitled Post";
    }

    var authorEl = container.querySelector("[data-blog-detail-author]");
    var authorValue = typeof post.author === "string" ? post.author.trim() : "";
    var showAuthor = parseBooleanOption(container.dataset.showAuthor, true);
    if (authorEl) {
      if (showAuthor && authorValue) {
        authorEl.textContent = authorValue;
        authorEl.classList.remove("d-none");
      } else {
        authorEl.textContent = "";
        authorEl.classList.add("d-none");
      }
    }

    var dateEl = container.querySelector("[data-blog-detail-date]");
    var dateValue = formatDate(post.publishDate || post.createdAt || "");
    var showDate = parseBooleanOption(container.dataset.showDate, true);
    if (dateEl) {
      if (showDate && dateValue) {
        dateEl.textContent = dateValue;
        dateEl.classList.remove("d-none");
      } else {
        dateEl.textContent = "";
        dateEl.classList.add("d-none");
      }
    }

    var metaEl = container.querySelector("[data-blog-detail-meta]");
    if (metaEl) {
      if (
        (authorEl && !authorEl.classList.contains("d-none")) ||
        (dateEl && !dateEl.classList.contains("d-none"))
      ) {
        metaEl.classList.remove("d-none");
      } else {
        metaEl.classList.add("d-none");
      }
    }

    var contentEl = container.querySelector("[data-blog-detail-content]");
    if (contentEl) {
      var content = post.content || "";
      if (!content) {
        content = "<p>This post does not have any content yet.</p>";
      }
      contentEl.innerHTML = content;
    }

    var featuredWrapper = container.querySelector(
      "[data-blog-detail-featured]",
    );
    var imageEl = container.querySelector("[data-blog-detail-image]");
    var captionEl = container.querySelector("[data-blog-detail-image-caption]");
    var showFeatured = parseBooleanOption(container.dataset.showFeatured, true);
    var featuredSrc =
      post.featured_image ||
      post.featuredImage ||
      post.image ||
      post.featuredMedia ||
      "";
    var featuredAlt =
      post.featured_alt ||
      post.featuredAlt ||
      post.image_alt ||
      post.imageAlt ||
      post.title ||
      "Blog featured image";
    var featuredCaption =
      post.featured_caption ||
      post.featuredCaption ||
      post.image_caption ||
      post.imageCaption ||
      "";
    if (featuredWrapper) {
      if (showFeatured && featuredSrc) {
        if (imageEl) {
          imageEl.src = featuredSrc;
          imageEl.alt = featuredAlt || "Blog featured image";
        }
        if (captionEl) {
          if (featuredCaption) {
            captionEl.textContent = featuredCaption;
            captionEl.classList.remove("d-none");
          } else {
            captionEl.textContent = "";
            captionEl.classList.add("d-none");
          }
        }
        featuredWrapper.classList.remove("d-none");
      } else {
        if (imageEl) {
          imageEl.removeAttribute("src");
        }
        if (captionEl) {
          captionEl.textContent = "";
          captionEl.classList.add("d-none");
        }
        featuredWrapper.classList.add("d-none");
      }
    }

    var tagsWrapper = container.querySelector("[data-blog-detail-tags]");
    var tagsList = container.querySelector("[data-blog-detail-tags-list]");
    var showTags = parseBooleanOption(container.dataset.showTags, true);
    var tags = extractBlogTags(post.tags);
    if (tagsWrapper) {
      if (showTags && tags.length) {
        if (tagsList) {
          clearContainer(tagsList);
          tags.forEach(function (tag) {
            var badge = document.createElement("span");
            badge.className =
              "blog-post-detail__tag badge bg-light text-primary border me-2 mb-2";
            badge.textContent = "#" + tag;
            tagsList.appendChild(badge);
          });
        }
        tagsWrapper.classList.remove("d-none");
      } else {
        if (tagsList) {
          clearContainer(tagsList);
        }
        tagsWrapper.classList.add("d-none");
      }
    }
  }

  function hydrateBlogDetail(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    if (container.__blogDetailHydrating) {
      return;
    }
    container.__blogDetailHydrating = true;
    var loading = container.querySelector("[data-blog-detail-loading]");
    if (loading) {
      loading.textContent =
        container.dataset.loadingText || "Loading post details…";
      loading.classList.remove("d-none");
    }
    var main = container.querySelector("[data-blog-detail-main]");
    if (main) {
      main.classList.add("d-none");
    }
    var empty = container.querySelector("[data-blog-detail-empty]");
    if (empty) {
      empty.classList.add("d-none");
      empty.textContent =
        container.dataset.empty || "This post could not be found.";
    }

    var slug = resolveBlogSlug(container);
    if (!slug) {
      showBlogDetailEmpty(
        container,
        container.dataset.empty || "This post could not be found.",
      );
      container.__blogDetailHydrating = false;
      return;
    }

    fetchBlogPosts()
      .then(function (posts) {
        var normalizedSlug = String(slug).toLowerCase();
        var match = (Array.isArray(posts) ? posts : []).find(function (post) {
          if (!post || typeof post !== "object") {
            return false;
          }
          return String(post.slug || "").toLowerCase() === normalizedSlug;
        });
        if (!match) {
          showBlogDetailEmpty(
            container,
            container.dataset.empty || "This post could not be found.",
          );
          return;
        }
        renderBlogDetail(container, match);
      })
      .catch(function () {
        showBlogDetailEmpty(
          container,
          container.dataset.empty || "This post could not be found.",
        );
      })
      .finally(function () {
        container.__blogDetailHydrating = false;
      });
  }

  function showCommerceError(container, message) {
    var itemsHost =
      container.querySelector("[data-commerce-items]") || container;
    clearContainer(itemsHost);
    var error = document.createElement("div");
    error.className = "commerce-product-card commerce-product-card--error";
    error.textContent = message || "Unable to load products.";
    itemsHost.appendChild(error);
    var emptyState = container.querySelector("[data-commerce-empty]");
    if (emptyState) {
      emptyState.classList.add("d-none");
    }
  }

  function slugifyProduct(product) {
    if (!product || typeof product !== "object") {
      return "";
    }
    if (product.slug) {
      var existing = String(product.slug).trim();
      if (existing) {
        return existing;
      }
    }
    if (product.name) {
      var fromName = String(product.name)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
      if (fromName) {
        return fromName;
      }
    }
    if (product.sku) {
      return String(product.sku)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
    }
    return "";
  }

  function formatCurrencyValue(amount, currency) {
    var numeric = Number(amount);
    if (!Number.isFinite(numeric)) {
      return "";
    }
    var code = (currency || "").toString().trim().toUpperCase();
    if (!code) {
      code = "USD";
    }
    try {
      return new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: code,
      }).format(numeric);
    } catch (error) {
      var symbol = code === "USD" ? "$" : code + " ";
      return symbol + numeric.toFixed(2);
    }
  }

  function formatProductSummary(product) {
    if (!product || typeof product !== "object") {
      return "";
    }
    var parts = [];
    if (product.category) {
      parts.push(String(product.category));
    }
    if (Number.isFinite(Number(product.inventory))) {
      var inventory = Number(product.inventory);
      if (inventory > 0) {
        parts.push(inventory + " in stock");
      } else if (inventory === 0) {
        parts.push("Out of stock");
      }
    }
    if (product.status) {
      parts.push(String(product.status));
    }
    return parts.join(" • ");
  }

  function hydrate(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    if (container.dataset.blogRendered === "server") {
      return;
    }
    var itemsHost = container.querySelector("[data-blog-items]");
    if (!itemsHost) {
      itemsHost = container;
    }
    clearContainer(itemsHost);
    var placeholder = document.createElement("div");
    placeholder.className = "blog-item blog-item--placeholder";
    placeholder.textContent = "Loading latest posts…";
    itemsHost.appendChild(placeholder);
    var emptyState = container.querySelector("[data-blog-empty]");
    if (emptyState) {
      emptyState.classList.add("d-none");
    }
    var settings = container.dataset || {};
    var limit = parseLimit(settings.limit);
    var category = normalizeCategory(settings.category);
    var showExcerpt = String(settings.showExcerpt || "").toLowerCase();
    var showMeta = String(settings.showMeta || "").toLowerCase();
    var emptyMessage = settings.empty || "No posts available.";
    fetchBlogPosts()
      .then(function (posts) {
        var filtered = posts.slice();
        if (category) {
          filtered = filtered.filter(function (post) {
            return normalizeCategory(post.category) === category;
          });
        }
        filtered.sort(function (a, b) {
          var aDate = a.publishDate || a.createdAt || "";
          var bDate = b.publishDate || b.createdAt || "";
          var aTime = Date.parse(aDate);
          var bTime = Date.parse(bDate);
          if (Number.isNaN(aTime)) {
            aTime = 0;
          }
          if (Number.isNaN(bTime)) {
            bTime = 0;
          }
          return bTime - aTime;
        });
        if (limit && filtered.length > limit) {
          filtered = filtered.slice(0, limit);
        }
        clearContainer(itemsHost);
        if (!filtered.length) {
          if (emptyState) {
            emptyState.textContent = emptyMessage;
            emptyState.classList.remove("d-none");
          } else {
            var notice = document.createElement("div");
            notice.className = "blog-item blog-item--placeholder";
            notice.textContent = emptyMessage;
            itemsHost.appendChild(notice);
          }
          return;
        }
        if (emptyState) {
          emptyState.classList.add("d-none");
        }
        var excerptEnabled = showExcerpt !== "no" && showExcerpt !== "false";
        var metaEnabled = showMeta !== "no" && showMeta !== "false";
        filtered.forEach(function (post) {
          var article = document.createElement("article");
          article.className = "blog-item";

          var title = document.createElement("h3");
          title.className = "blog-title";
          var link = document.createElement("a");
          link.href = resolveDetailUrl(settings.base, post.slug);
          link.textContent = post.title || "Untitled Post";
          title.appendChild(link);
          article.appendChild(title);

          if (metaEnabled) {
            var parts = [];
            if (post.author) {
              parts.push(post.author);
            }
            var formattedDate = formatDate(post.publishDate || post.createdAt);
            if (formattedDate) {
              parts.push(formattedDate);
            }
            if (parts.length) {
              var meta = document.createElement("div");
              meta.className = "blog-meta";
              parts.forEach(function (value, index) {
                var span = document.createElement("span");
                span.textContent = value;
                meta.appendChild(span);
              });
              article.appendChild(meta);
            }
          }

          if (excerptEnabled && post.excerpt) {
            var excerpt = document.createElement("p");
            excerpt.className = "blog-excerpt";
            excerpt.textContent = post.excerpt;
            article.appendChild(excerpt);
          }

          var readMore = document.createElement("a");
          readMore.className = "blog-read-more";
          readMore.href = resolveDetailUrl(settings.base, post.slug);
          readMore.innerHTML =
            'Read more <span aria-hidden="true">&rarr;</span>';
          article.appendChild(readMore);

          itemsHost.appendChild(article);
        });
      })
      .catch(function () {
        showError(container, "Unable to load blog posts at this time.");
      });
  }

  function hydrateCommerceGrid(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    var itemsHost = container.querySelector("[data-commerce-items]");
    if (!itemsHost) {
      itemsHost = container;
    }
    clearContainer(itemsHost);
    var placeholder = document.createElement("article");
    placeholder.className =
      "commerce-product-card commerce-product-card--placeholder";
    var placeholderBody = document.createElement("div");
    placeholderBody.className = "commerce-product-body";
    var placeholderTitle = document.createElement("h3");
    placeholderTitle.className = "commerce-product-title";
    placeholderTitle.textContent = "Loading products…";
    placeholderBody.appendChild(placeholderTitle);
    var placeholderDescription = document.createElement("p");
    placeholderDescription.className = "commerce-product-description";
    placeholderDescription.textContent =
      "Latest catalogue items will appear here automatically.";
    placeholderBody.appendChild(placeholderDescription);
    placeholder.appendChild(placeholderBody);
    itemsHost.appendChild(placeholder);
    var emptyState = container.querySelector("[data-commerce-empty]");
    if (emptyState) {
      emptyState.classList.add("d-none");
    }
    var settings = container.dataset || {};
    var limit = parseLimit(settings.limit, 3);
    var categories = parseCategoriesList(settings.categories);
    var base = settings.base || "/store";
    var linkLabel = settings.linkText || "View product";
    var emptyMessage = settings.empty || "No products available right now.";
    fetchCommerceData()
      .then(function (data) {
        var catalog =
          data && Array.isArray(data.catalog) ? data.catalog.slice() : [];
        var currency = data && data.settings && data.settings.currency;
        var visible = catalog.filter(function (product) {
          if (!product || typeof product !== "object") {
            return false;
          }
          var visibility = String(product.visibility || "").toLowerCase();
          if (visibility && visibility !== "published") {
            return false;
          }
          var status = String(product.status || "").toLowerCase();
          if (status === "archived") {
            return false;
          }
          return true;
        });
        if (categories.length) {
          visible = visible.filter(function (product) {
            return (
              categories.indexOf(normalizeCategory(product.category)) !== -1
            );
          });
        }
        visible.sort(function (a, b) {
          var aTime = Date.parse(a && a.updated ? a.updated : "") || 0;
          var bTime = Date.parse(b && b.updated ? b.updated : "") || 0;
          return bTime - aTime;
        });
        if (limit && visible.length > limit) {
          visible = visible.slice(0, limit);
        }
        clearContainer(itemsHost);
        if (!visible.length) {
          if (emptyState) {
            emptyState.textContent = emptyMessage;
            emptyState.classList.remove("d-none");
          } else {
            var notice = document.createElement("div");
            notice.className =
              "commerce-product-card commerce-product-card--placeholder";
            notice.textContent = emptyMessage;
            itemsHost.appendChild(notice);
          }
          return;
        }
        if (emptyState) {
          emptyState.classList.add("d-none");
        }
        visible.forEach(function (product) {
          var card = document.createElement("article");
          card.className = "commerce-product-card";
          if (product.featured_image) {
            var figure = document.createElement("figure");
            figure.className = "commerce-product-media";
            var img = document.createElement("img");
            img.src = product.featured_image;
            img.alt = product.name || product.sku || "Product image";
            figure.appendChild(img);
            card.appendChild(figure);
          }
          var body = document.createElement("div");
          body.className = "commerce-product-body";
          var title = document.createElement("h3");
          title.className = "commerce-product-title";
          title.textContent = product.name || product.sku || "Product";
          body.appendChild(title);
          var priceText = formatCurrencyValue(product.price, currency);
          if (priceText) {
            var price = document.createElement("p");
            price.className = "commerce-product-price";
            price.textContent = priceText;
            body.appendChild(price);
          }
          var summary = formatProductSummary(product);
          if (summary) {
            var description = document.createElement("p");
            description.className = "commerce-product-description";
            description.textContent = summary;
            body.appendChild(description);
          }
          var detailSlug = slugifyProduct(product);
          var link = document.createElement("a");
          link.className = "commerce-product-link";
          link.href = resolveDetailUrl(
            base,
            detailSlug || String(product.sku || "").trim(),
          );
          link.textContent = linkLabel;
          body.appendChild(link);
          card.appendChild(body);
          itemsHost.appendChild(card);
        });
      })
      .catch(function () {
        showCommerceError(container, "Unable to load products at this time.");
      });
  }

  var eventsPromise = null;
  var eventCategoriesPromise = null;
  var htmlParser = null;

  function fetchEvents() {
    if (eventsPromise) {
      return eventsPromise;
    }
    var base = normalizeBasePath();
    var url = base + "/CMS/public_data.php?action=events";
    eventsPromise = fetch(url, {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to load events");
        }
        return response.json();
      })
      .then(function (events) {
        if (!Array.isArray(events)) {
          return [];
        }
        return events.filter(function (event) {
          if (!event || typeof event !== "object") {
            return false;
          }
          var status = String(event.status || "").toLowerCase();
          return !status || status === "published";
        });
      })
      .catch(function (error) {
        console.error("[SparkCMS] Events load error:", error);
        eventsPromise = null;
        throw error;
      });
    return eventsPromise;
  }

  function fetchEventCategories() {
    if (eventCategoriesPromise) {
      return eventCategoriesPromise;
    }
    var base = normalizeBasePath();
    var url = base + "/CMS/public_data.php?action=event_categories";
    eventCategoriesPromise = fetch(url, {
      credentials: "same-origin",
      cache: "no-store",
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Failed to load event categories");
        }
        return response.json();
      })
      .then(function (records) {
        if (!Array.isArray(records)) {
          return {};
        }
        return records.reduce(function (map, record) {
          if (!record || typeof record !== "object") {
            return map;
          }
          var id = record.id || record.slug || record.name;
          if (!id) {
            return map;
          }
          map[id] = {
            id: id,
            name: record.name || "",
            slug: record.slug || "",
            normalizedName: normalizeCategory(record.name),
            normalizedSlug: normalizeCategory(record.slug),
            normalizedId: normalizeCategory(id),
          };
          return map;
        }, {});
      })
      .catch(function (error) {
        console.error("[SparkCMS] Event categories load error:", error);
        eventCategoriesPromise = null;
        return {};
      });
    return eventCategoriesPromise;
  }

  function parseBooleanOption(value, defaultValue) {
    if (value == null) {
      return defaultValue;
    }
    var normalized = String(value).toLowerCase().trim();
    if (["no", "false", "0", "off", "hide"].indexOf(normalized) !== -1) {
      return false;
    }
    if (["yes", "true", "1", "show", "on"].indexOf(normalized) !== -1) {
      return true;
    }
    return defaultValue;
  }

  function normalizeEventsLayout(value) {
    var layout = String(value || "").toLowerCase();
    if (["list", "compact", "cards"].indexOf(layout) === -1) {
      return "cards";
    }
    return layout;
  }

  function parseIsoDate(value) {
    if (!value) {
      return null;
    }
    if (value instanceof Date) {
      return value;
    }
    var normalized = String(value).trim();
    if (!normalized) {
      return null;
    }
    var timestamp = Date.parse(normalized);
    if (!Number.isNaN(timestamp)) {
      return new Date(timestamp);
    }
    var fallback = normalized
      .replace(/-/g, "/")
      .replace(/T/, " ")
      .replace(/Z$/, " UTC");
    timestamp = Date.parse(fallback);
    if (!Number.isNaN(timestamp)) {
      return new Date(timestamp);
    }
    return null;
  }

  function eventMatchesCategoryFilter(event, filter, categoriesIndex) {
    if (!filter) {
      return true;
    }
    var ids = Array.isArray(event.categories) ? event.categories : [];
    if (!ids.length) {
      return false;
    }
    return ids.some(function (id) {
      var info = categoriesIndex[id];
      if (!info) {
        return normalizeCategory(id) === filter;
      }
      return (
        info.normalizedId === filter ||
        info.normalizedSlug === filter ||
        info.normalizedName === filter
      );
    });
  }

  function stripHtml(html) {
    if (!html) {
      return "";
    }
    if (!htmlParser) {
      htmlParser = document.createElement("div");
    }
    htmlParser.innerHTML = html;
    return htmlParser.textContent || htmlParser.innerText || "";
  }

  function escapeHtml(value) {
    if (value == null) {
      return "";
    }
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function truncateText(value, maxLength) {
    if (!value) {
      return "";
    }
    var text = String(value).trim();
    if (!maxLength || text.length <= maxLength) {
      return text;
    }
    return text.slice(0, maxLength - 1).trimEnd() + "…";
  }

  function formatEventMonth(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return "";
    }
    try {
      return date.toLocaleString(undefined, { month: "short" });
    } catch (error) {
      return (
        [
          "Jan",
          "Feb",
          "Mar",
          "Apr",
          "May",
          "Jun",
          "Jul",
          "Aug",
          "Sep",
          "Oct",
          "Nov",
          "Dec",
        ][date.getMonth()] || ""
      );
    }
  }

  function formatEventDay(date) {
    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
      return "";
    }
    return String(date.getDate()).padStart(2, "0");
  }

  function formatEventRange(start, end) {
    if (!(start instanceof Date) || Number.isNaN(start.getTime())) {
      return "";
    }
    var formatterOptions = { month: "short", day: "numeric", year: "numeric" };
    var timeFormatterOptions = { hour: "numeric", minute: "2-digit" };
    var dateLabel;
    try {
      dateLabel = start.toLocaleDateString(undefined, formatterOptions);
    } catch (error) {
      dateLabel =
        start.getFullYear() +
        "-" +
        String(start.getMonth() + 1).padStart(2, "0") +
        "-" +
        String(start.getDate()).padStart(2, "0");
    }
    if (!(end instanceof Date) || Number.isNaN(end.getTime())) {
      try {
        var timeLabel = start.toLocaleTimeString(
          undefined,
          timeFormatterOptions,
        );
        return timeLabel ? dateLabel + " • " + timeLabel : dateLabel;
      } catch (error) {
        return dateLabel;
      }
    }
    var sameDay = start.toDateString() === end.toDateString();
    try {
      if (sameDay) {
        var startTime = start.toLocaleTimeString(
          undefined,
          timeFormatterOptions,
        );
        var endTime = end.toLocaleTimeString(undefined, timeFormatterOptions);
        if (startTime && endTime) {
          return dateLabel + " • " + startTime + " – " + endTime;
        }
        if (startTime) {
          return dateLabel + " • " + startTime;
        }
        return dateLabel;
      }
      var endLabel = end.toLocaleDateString(undefined, formatterOptions);
      return dateLabel + " – " + endLabel;
    } catch (error) {
      var endStamp =
        end.getFullYear() +
        "-" +
        String(end.getMonth() + 1).padStart(2, "0") +
        "-" +
        String(end.getDate()).padStart(2, "0");
      return sameDay ? dateLabel : dateLabel + " – " + endStamp;
    }
  }

  function ticketIsCurrentlyAvailable(ticket, referenceDate) {
    if (!ticket || ticket.enabled === false) {
      return false;
    }
    var now =
      referenceDate instanceof Date && !Number.isNaN(referenceDate.getTime())
        ? referenceDate
        : new Date();
    var onSale = parseIsoDate(ticket.on_sale || ticket.onSale || "");
    if (onSale && onSale.getTime() > now.getTime()) {
      return false;
    }
    var offSale = parseIsoDate(ticket.off_sale || ticket.offSale || "");
    if (offSale && offSale.getTime() < now.getTime()) {
      return false;
    }
    return true;
  }

  function findLowestPrice(tickets) {
    if (!Array.isArray(tickets) || !tickets.length) {
      return null;
    }
    var now = new Date();
    var values = tickets
      .filter(function (ticket) {
        return (
          ticket &&
          Number.isFinite(Number(ticket.price)) &&
          ticketIsCurrentlyAvailable(ticket, now)
        );
      })
      .map(function (ticket) {
        return Number(ticket.price);
      });
    if (!values.length) {
      return null;
    }
    return Math.min.apply(Math, values);
  }

  function formatCurrency(amount) {
    if (!Number.isFinite(amount)) {
      return "";
    }
    try {
      return new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: "USD",
      }).format(amount);
    } catch (error) {
      return "$" + amount.toFixed(2);
    }
  }

  function createEventSlug(event) {
    if (!event || typeof event !== "object") {
      return "";
    }
    if (event.slug) {
      return String(event.slug).trim();
    }
    if (event.title) {
      var generated = String(event.title)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
      if (generated) {
        return generated;
      }
    }
    if (event.id) {
      return String(event.id).trim();
    }
    return "";
  }

  function resolveEventDetailUrl(prefix, event) {
    var base = (prefix || "").toString().trim();
    if (!base) {
      return "";
    }
    var slug = createEventSlug(event);
    if (/^https?:\/\//i.test(base)) {
      return base.replace(/\/+$/, "") + (slug ? "/" + slug : "");
    }
    var normalized = base.replace(/\/+$/, "").replace(/^\/+/, "");
    var path = normalized;
    if (slug) {
      path = normalized ? normalized + "/" + slug : slug;
    }
    var start = normalizeBasePath();
    if (start && start.charAt(0) !== "/") {
      start = "/" + start;
    }
    if (!start) {
      return "/" + path;
    }
    return start.replace(/\/+$/, "") + "/" + path;
  }

  function showEventsEmpty(container, message) {
    var empty = container.querySelector("[data-events-empty]");
    if (empty) {
      empty.textContent = message || empty.textContent || "";
      empty.classList.remove("d-none");
    }
  }

  function hideEventsEmpty(container) {
    var empty = container.querySelector("[data-events-empty]");
    if (empty) {
      empty.classList.add("d-none");
    }
  }

  function createEventsItem(event, options) {
    var item = document.createElement("article");
    item.className = "events-block__item";
    if (event.id) {
      item.setAttribute("data-events-id", String(event.id));
    }

    if (
      event.startDate instanceof Date &&
      !Number.isNaN(event.startDate.getTime())
    ) {
      var dateWrap = document.createElement("div");
      dateWrap.className = "events-block__date";
      var monthSpan = document.createElement("span");
      monthSpan.className = "events-block__date-month";
      monthSpan.textContent = formatEventMonth(event.startDate);
      dateWrap.appendChild(monthSpan);
      var daySpan = document.createElement("span");
      daySpan.className = "events-block__date-day";
      daySpan.textContent = formatEventDay(event.startDate);
      dateWrap.appendChild(daySpan);
      item.appendChild(dateWrap);
    }

    var linkUrl = resolveEventDetailUrl(options.detailBase, event.raw);

    var media = document.createElement("div");
    media.className = "events-block__media";
    var imageSource = event.raw.image || event.image;
    if (typeof imageSource === "string" && imageSource.trim() !== "") {
      var image = document.createElement("img");
      image.className = "events-block__image";
      image.loading = "lazy";
      image.src = imageSource;
      var altText =
        event.raw.imageAlt ||
        event.raw.image_alt ||
        event.imageAlt ||
        event.image_alt ||
        "";
      if (!altText) {
        if (event.raw.title) {
          altText = event.raw.title + " event image";
        } else {
          altText = "Event image";
        }
      }
      image.alt = altText;
      if (linkUrl) {
        var mediaLink = document.createElement("a");
        mediaLink.className = "events-block__media-link";
        mediaLink.href = linkUrl;
        mediaLink.appendChild(image);
        media.appendChild(mediaLink);
      } else {
        media.appendChild(image);
      }
      item.classList.add("events-block__item--has-image");
    } else {
      media.classList.add("events-block__media--fallback");
      media.setAttribute("aria-hidden", "true");
      var fallback = document.createElement("span");
      fallback.className = "events-block__media-fallback";
      fallback.textContent = "Event image coming soon";
      media.appendChild(fallback);
      item.classList.add("events-block__item--no-image");
    }
    item.appendChild(media);

    var body = document.createElement("div");
    body.className = "events-block__body";
    var title = document.createElement("h3");
    title.className = "events-block__title";
    if (linkUrl) {
      var link = document.createElement("a");
      link.className = "events-block__title-link";
      link.href = linkUrl;
      link.textContent = event.raw.title || "Untitled Event";
      title.appendChild(link);
    } else {
      title.textContent = event.raw.title || "Untitled Event";
    }
    body.appendChild(title);

    var meta = document.createElement("div");
    meta.className = "events-block__meta";

    if (event.startDate) {
      var metaLine = document.createElement("div");
      metaLine.className = "events-block__meta-line";
      var timeEl = document.createElement("time");
      timeEl.className = "events-block__time";
      timeEl.dateTime = event.startDate.toISOString();
      timeEl.textContent = formatEventRange(event.startDate, event.endDate);
      metaLine.appendChild(timeEl);
      meta.appendChild(metaLine);
    }

    if (options.showLocation && event.raw.location) {
      var locationLine = document.createElement("div");
      locationLine.className = "events-block__meta-line";
      var locationSpan = document.createElement("span");
      locationSpan.className = "events-block__location";
      locationSpan.textContent = event.raw.location;
      locationLine.appendChild(locationSpan);
      meta.appendChild(locationLine);
    }

    if (options.showCategories && event.categoryNames.length) {
      var categoriesLine = document.createElement("div");
      categoriesLine.className =
        "events-block__meta-line events-block__meta-line--badges";
      event.categoryNames.forEach(function (name) {
        var badge = document.createElement("span");
        badge.className = "events-block__badge";
        badge.textContent = name;
        categoriesLine.appendChild(badge);
      });
      meta.appendChild(categoriesLine);
    }

    if (options.showPrice && Number.isFinite(event.lowestPrice)) {
      var priceLine = document.createElement("div");
      priceLine.className = "events-block__meta-line";
      var priceSpan = document.createElement("span");
      priceSpan.className = "events-block__price";
      priceSpan.textContent = "From " + formatCurrency(event.lowestPrice);
      priceLine.appendChild(priceSpan);
      meta.appendChild(priceLine);
    }

    if (meta.childNodes.length) {
      body.appendChild(meta);
    }

    if (options.showDescription && event.description) {
      var description = document.createElement("p");
      description.className = "events-block__description";
      description.textContent = event.description;
      body.appendChild(description);
    }

    if (options.showButton && linkUrl) {
      var cta = document.createElement("a");
      cta.className = "events-block__cta";
      cta.href = linkUrl;
      cta.textContent = options.buttonLabel || "View event";
      body.appendChild(cta);
    }

    item.appendChild(body);
    return item;
  }

  function buildMapCategoryOptions(locations) {
    var usage = {};
    locations.forEach(function (location) {
      if (!location || typeof location !== "object") {
        return;
      }
      if (Array.isArray(location.categories) && location.categories.length) {
        location.categories.forEach(function (category) {
          if (!category || !category.id) {
            return;
          }
          var id = category.id;
          if (!usage[id]) {
            usage[id] = {
              id: id,
              name: category.name || "Category",
              color: sanitizeHexColor(category.color, "#2D70F5"),
              icon: category.icon,
              count: 0,
            };
          }
          usage[id].count += 1;
        });
      } else {
        if (!usage.uncategorized) {
          usage.uncategorized = {
            id: "uncategorized",
            name: "Uncategorized",
            color: "#9CA3AF",
            icon: "fa-location-dot",
            count: 0,
          };
        }
        usage.uncategorized.count += 1;
      }
    });
    return Object.keys(usage)
      .map(function (key) {
        return usage[key];
      })
      .sort(function (a, b) {
        return a.name.localeCompare(b.name);
      });
  }

  function createMapMarkerContent(category) {
    var color =
      category && category.color
        ? sanitizeHexColor(category.color, "#2D70F5")
        : "#2D70F5";
    var iconClasses = normalizeIconClassList(
      category && category.icon ? category.icon : "fa-location-dot",
    );
    var wrapper = document.createElement("div");
    wrapper.className = "map-block__marker-wrapper";
    wrapper.innerHTML =
      '<div class="map-block__marker" style="background:' +
      escapeHtml(color) +
      ';">' +
      '<span class="map-block__marker-icon"><i class="' +
      escapeHtml(iconClasses) +
      '" aria-hidden="true"></i></span>' +
      "</div>";
    return wrapper;
  }

  function normalizeExternalUrl(url) {
    var value = typeof url === "string" ? url.trim() : "";
    if (!value) {
      return "";
    }
    if (/^javascript:/i.test(value)) {
      return "";
    }
    if (/^(https?:)?\/\//i.test(value)) {
      if (!/^https?:\/\//i.test(value)) {
        return "https:" + value;
      }
      return value;
    }
    if (value.charAt(0) === "/") {
      return value;
    }
    return "https://" + value.replace(/^https?:\/\//i, "");
  }

  function buildTelHref(phone) {
    if (!phone) {
      return "";
    }
    var digits = String(phone).replace(/[^0-9+]/g, "");
    if (!digits) {
      return "";
    }
    return "tel:" + digits;
  }

  function buildMailHref(email) {
    if (!email) {
      return "";
    }
    var value = String(email).trim();
    if (!value || /\s/.test(value)) {
      return "";
    }
    return "mailto:" + value;
  }

  function buildMapPopupContent(location) {
    var html = '<div class="map-block__popup">';
    html +=
      '<h3 class="map-block__popup-title">' +
      escapeHtml(location.name) +
      "</h3>";
    if (Array.isArray(location.categories) && location.categories.length) {
      html += '<div class="map-block__popup-badges">';
      location.categories.forEach(function (category) {
        var color = sanitizeHexColor(category.color, "#2D70F5");
        html +=
          '<span class="map-block__popup-badge">' +
          '<span class="map-block__popup-badge-color" style="background:' +
          escapeHtml(color) +
          ';"></span>' +
          escapeHtml(category.name || "Category") +
          "</span>";
      });
      html += "</div>";
    }
    if (location.fullAddress) {
      html +=
        '<p class="map-block__popup-line"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>' +
        escapeHtml(location.fullAddress) +
        "</span></p>";
    }
    var phoneHref = buildTelHref(location.contact && location.contact.phone);
    if (phoneHref) {
      html +=
        '<p class="map-block__popup-line"><i class="fa-solid fa-phone" aria-hidden="true"></i>' +
        '<a href="' +
        escapeHtml(phoneHref) +
        '">' +
        escapeHtml(location.contact.phone) +
        "</a></p>";
    }
    var emailHref = buildMailHref(location.contact && location.contact.email);
    if (emailHref) {
      html +=
        '<p class="map-block__popup-line"><i class="fa-solid fa-envelope" aria-hidden="true"></i>' +
        '<a href="' +
        escapeHtml(emailHref) +
        '">' +
        escapeHtml(location.contact.email) +
        "</a></p>";
    }
    var websiteHref = normalizeExternalUrl(
      location.contact && location.contact.website,
    );
    if (websiteHref) {
      html +=
        '<p class="map-block__popup-line"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>' +
        '<a href="' +
        escapeHtml(websiteHref) +
        '" target="_blank" rel="noopener">Website</a></p>';
    }
    html += "</div>";
    return html;
  }

  function hydrateMapBlock(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    if (container.__sparkMapHydrated) {
      return;
    }
    var mapHost = container.querySelector("[data-map-container]");
    if (!mapHost) {
      return;
    }
    container.__sparkMapHydrated = true;

    var listHost = container.querySelector("[data-map-list]");
    var filtersHost = container.querySelector("[data-map-filters]");
    var loading = container.querySelector("[data-map-loading]");
    var emptyState = container.querySelector("[data-map-empty]");
    var heading = container.querySelector(".map-block__heading");
    var showList = parseBooleanOption(container.dataset.mapShowList, true);
    var limitTokens = parseCategoriesList(container.dataset.mapCategories);
    var defaultZoom = parseMapZoom(container.dataset.mapDefaultZoom, 6);
    var emptyMessage =
      container.dataset.mapEmpty || "No locations are available right now.";

    container.classList.toggle("map-block--no-list", !showList);
    if (listHost) {
      if (showList) {
        listHost.removeAttribute("hidden");
      } else {
        listHost.setAttribute("hidden", "hidden");
      }
    }
    mapHost.setAttribute("role", "region");
    if (!mapHost.hasAttribute("tabindex")) {
      mapHost.setAttribute("tabindex", "0");
    }
    if (heading) {
      mapHost.setAttribute("aria-label", heading.textContent.trim() + " map");
    } else {
      mapHost.setAttribute("aria-label", "Interactive map");
    }

    Promise.all([ensureLeaflet(), fetchMapData()])
      .then(function (results) {
        var L = results[0];
        var mapData = results[1];
        var locations = Array.isArray(mapData.locations)
          ? mapData.locations.filter(function (location) {
              return locationMatchesTokens(location, limitTokens);
            })
          : [];

        var map = L.map(mapHost, {
          zoomControl: true,
          attributionControl: true,
          scrollWheelZoom: false,
          doubleClickZoom: false,
          boxZoom: false,
          keyboard: false,
          dragging: false,
        });
        map.setView([20, 0], defaultZoom);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          maxZoom: 19,
          attribution:
            '&copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
        }).addTo(map);

        function setInteractive(enabled) {
          if (!map) {
            return;
          }
          if (enabled) {
            map.scrollWheelZoom.enable();
            map.doubleClickZoom.enable();
            map.boxZoom.enable();
            map.keyboard.enable();
            map.dragging.enable();
            if (map.touchZoom) {
              map.touchZoom.enable();
            }
          } else {
            map.scrollWheelZoom.disable();
            map.doubleClickZoom.disable();
            map.boxZoom.disable();
            map.keyboard.disable();
            map.dragging.disable();
            if (map.touchZoom) {
              map.touchZoom.disable();
            }
          }
        }

        setInteractive(false);
        mapHost.addEventListener("focus", function () {
          setInteractive(true);
        });
        mapHost.addEventListener("blur", function () {
          setInteractive(false);
        });

        var markerLayer = L.layerGroup().addTo(map);
        var markerEntries = {};
        var activeFilter = "__all";
        var activeLocationId = null;
        var activePopup = null;
        var maxFitZoom = Math.max(defaultZoom, 12);

        function updateEmptyState(isEmpty) {
          if (!emptyState) {
            return;
          }
          if (isEmpty) {
            emptyState.textContent = emptyMessage;
            emptyState.classList.remove("d-none");
          } else {
            emptyState.classList.add("d-none");
          }
        }

        function updateFilterButtons() {
          if (!filtersHost) {
            return;
          }
          var buttons = filtersHost.querySelectorAll("[data-map-filter]");
          buttons.forEach(function (button) {
            if (!(button instanceof HTMLElement)) {
              return;
            }
            var value = button.getAttribute("data-map-filter");
            button.setAttribute(
              "aria-pressed",
              value === activeFilter ? "true" : "false",
            );
          });
        }

        function clearMarkers() {
          markerLayer.clearLayers();
          markerEntries = {};
          if (activePopup) {
            map.closePopup(activePopup);
            activePopup = null;
          }
        }

        function createLeafletIcon(category) {
          var content = createMapMarkerContent(category);
          var html = "";
          if (content && typeof content.outerHTML === "string") {
            html = content.outerHTML;
          } else if (content && content.innerHTML) {
            html =
              '<div class="map-block__marker-wrapper">' +
              content.innerHTML +
              "</div>";
          } else {
            html =
              '<div class="map-block__marker-wrapper"><div class="map-block__marker"></div></div>';
          }
          return L.divIcon({
            className: "map-block__marker-container leaflet-div-icon",
            html: html,
            iconSize: [32, 44],
            iconAnchor: [16, 44],
            popupAnchor: [0, -44],
          });
        }

        function highlightLocation(id, openPopup) {
          activeLocationId = id;
          if (showList && listHost) {
            var items = listHost.querySelectorAll("[data-map-location]");
            items.forEach(function (node) {
              if (!(node instanceof HTMLElement)) {
                return;
              }
              var match = node.getAttribute("data-map-location") === id;
              node.classList.toggle("map-block__item--active", match);
            });
          }
          var entry = markerEntries[id];
          if (!entry) {
            if (openPopup && activePopup) {
              map.closePopup(activePopup);
              activePopup = null;
            }
            return;
          }
          var marker = entry.marker;
          if (
            openPopup !== false &&
            marker &&
            typeof marker.openPopup === "function"
          ) {
            marker.openPopup();
          }
          if (marker && typeof marker.getLatLng === "function") {
            var target = marker.getLatLng();
            map.panTo(target, { animate: true });
          }
        }

        function updateList(visibleLocations) {
          if (!listHost || !showList) {
            return;
          }
          clearContainer(listHost);
          listHost.setAttribute("role", "list");
          if (!visibleLocations.length) {
            var placeholder = document.createElement("article");
            placeholder.className =
              "map-block__item map-block__item--placeholder";
            placeholder.setAttribute("role", "listitem");
            var message = document.createElement("h3");
            message.className = "map-block__item-title";
            message.textContent = emptyMessage;
            placeholder.appendChild(message);
            listHost.appendChild(placeholder);
            return;
          }
          visibleLocations.forEach(function (location) {
            var item = document.createElement("article");
            item.className = "map-block__item";
            item.setAttribute("data-map-location", location.id);
            item.setAttribute("role", "listitem");
            item.setAttribute("tabindex", "0");
            var title = document.createElement("h3");
            title.className = "map-block__item-title";
            title.textContent = location.name;
            item.appendChild(title);

            if (location.categories && location.categories.length) {
              var badges = document.createElement("div");
              badges.className = "map-block__badges";
              location.categories.forEach(function (category) {
                var badge = document.createElement("span");
                badge.className = "map-block__badge";
                var color = sanitizeHexColor(category.color, "#2D70F5");
                var swatch = document.createElement("span");
                swatch.className = "map-block__badge-color";
                swatch.style.background = color;
                badge.appendChild(swatch);
                var label = document.createElement("span");
                label.className = "map-block__badge-label";
                label.textContent = category.name || "Category";
                badge.appendChild(label);
                badges.appendChild(badge);
              });
              item.appendChild(badges);
            }

            if (location.cityRegion) {
              var meta = document.createElement("div");
              meta.className = "map-block__item-meta";
              meta.innerHTML =
                '<i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>' +
                escapeHtml(location.cityRegion) +
                "</span>";
              item.appendChild(meta);
            }

            if (location.description) {
              var description = document.createElement("p");
              description.className = "map-block__item-description";
              description.textContent = location.description;
              item.appendChild(description);
            }

            var actions = document.createElement("div");
            actions.className = "map-block__item-actions";
            var phone = buildTelHref(
              location.contact && location.contact.phone,
            );
            if (phone) {
              var phoneLink = document.createElement("a");
              phoneLink.className = "map-block__item-action";
              phoneLink.href = phone;
              phoneLink.innerHTML =
                '<i class="fa-solid fa-phone" aria-hidden="true"></i><span>Call</span>';
              phoneLink.setAttribute("aria-label", "Call " + location.name);
              actions.appendChild(phoneLink);
            }
            var email = buildMailHref(
              location.contact && location.contact.email,
            );
            if (email) {
              var emailLink = document.createElement("a");
              emailLink.className = "map-block__item-action";
              emailLink.href = email;
              emailLink.innerHTML =
                '<i class="fa-solid fa-envelope" aria-hidden="true"></i><span>Email</span>';
              emailLink.setAttribute("aria-label", "Email " + location.name);
              actions.appendChild(emailLink);
            }
            var website = normalizeExternalUrl(
              location.contact && location.contact.website,
            );
            if (website) {
              var siteLink = document.createElement("a");
              siteLink.className = "map-block__item-action";
              siteLink.href = website;
              siteLink.target = "_blank";
              siteLink.rel = "noopener";
              siteLink.innerHTML =
                '<i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i><span>Website</span>';
              siteLink.setAttribute(
                "aria-label",
                "Open website for " + location.name,
              );
              actions.appendChild(siteLink);
            }
            if (actions.childNodes.length) {
              item.appendChild(actions);
            }

            item.addEventListener("click", function () {
              highlightLocation(location.id, true);
            });
            item.addEventListener("keydown", function (event) {
              if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                highlightLocation(location.id, true);
              }
            });

            listHost.appendChild(item);
          });
        }

        function updateMarkers(visibleLocations) {
          clearMarkers();
          if (!Array.isArray(visibleLocations) || !visibleLocations.length) {
            map.setView([20, 0], defaultZoom);
            return;
          }
          var bounds = L.latLngBounds();
          visibleLocations.forEach(function (location) {
            var position = L.latLng(location.lat, location.lng);
            var marker = L.marker(position, {
              icon: createLeafletIcon(location.primaryCategory),
            });
            marker.bindPopup(buildMapPopupContent(location), {
              maxWidth: 360,
              closeButton: true,
            });
            marker.on("click", function () {
              highlightLocation(location.id, true);
            });
            marker.on("popupopen", function (event) {
              activePopup = event.popup;
              highlightLocation(location.id, false);
            });
            marker.on("popupclose", function () {
              if (
                activePopup &&
                marker.getPopup &&
                marker.getPopup() === activePopup
              ) {
                activePopup = null;
              }
            });
            markerLayer.addLayer(marker);
            markerEntries[location.id] = { marker: marker, location: location };
            bounds.extend(position);
          });
          if (visibleLocations.length === 1) {
            map.setView(bounds.getCenter(), defaultZoom);
          } else {
            map.fitBounds(bounds, { padding: [40, 40] });
            if (map.getZoom() > maxFitZoom) {
              map.setZoom(maxFitZoom);
            }
          }
          map.invalidateSize();
        }

        function applyFilter(filterId) {
          activeFilter = filterId;
          updateFilterButtons();
          var filtered = locations.filter(function (location) {
            if (filterId === "__all") {
              return true;
            }
            if (filterId === "uncategorized") {
              return !location.categories || !location.categories.length;
            }
            return (
              location.categories &&
              location.categories.some(function (category) {
                return category && category.id === filterId;
              })
            );
          });
          updateEmptyState(!filtered.length);
          updateMarkers(filtered);
          updateList(filtered);
          if (filtered.length) {
            highlightLocation(filtered[0].id, false);
          }
        }

        function renderFilters(options) {
          if (!filtersHost) {
            return;
          }
          clearContainer(filtersHost);
          filtersHost.setAttribute("role", "toolbar");
          var allButton = document.createElement("button");
          allButton.type = "button";
          allButton.className = "map-block__filter";
          allButton.setAttribute("data-map-filter", "__all");
          allButton.setAttribute("aria-pressed", "true");
          allButton.textContent = "All locations";
          allButton.addEventListener("click", function () {
            applyFilter("__all");
          });
          filtersHost.appendChild(allButton);

          options.forEach(function (option) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "map-block__filter";
            button.setAttribute("data-map-filter", option.id);
            button.setAttribute("aria-pressed", "false");
            button.innerHTML =
              '<span class="map-block__filter-color" style="background:' +
              escapeHtml(option.color) +
              ';"></span>' +
              escapeHtml(option.name) +
              (option.count
                ? ' <span class="sr-only">(' +
                  option.count +
                  " locations)</span>"
                : "");
            button.addEventListener("click", function () {
              applyFilter(option.id);
            });
            filtersHost.appendChild(button);
          });
        }

        if (loading) {
          loading.remove();
        }

        if (!locations.length) {
          updateEmptyState(true);
          map.setView([20, 0], defaultZoom);
          return;
        }

        var filterOptions = buildMapCategoryOptions(locations);
        renderFilters(filterOptions);
        updateFilterButtons();
        updateMarkers(locations);
        updateList(locations);
        updateEmptyState(false);
        highlightLocation(locations[0].id, false);
        setTimeout(function () {
          map.invalidateSize();
        }, 120);
      })

      .catch(function (error) {
        console.error("[SparkCMS] Map block hydrate error:", error);
        if (loading) {
          loading.textContent = "Unable to load map data right now.";
        }
        if (emptyState) {
          emptyState.textContent = emptyMessage;
          emptyState.classList.remove("d-none");
        }
      });
  }

  function initMapBlocks() {
    var blocks = document.querySelectorAll("[data-map-block]");
    blocks.forEach(function (block) {
      hydrateMapBlock(block);
    });
  }

  function renderEventsBlock(container) {
    if (!(container instanceof HTMLElement)) {
      return;
    }
    var itemsHost = container.querySelector("[data-events-items]");
    if (!itemsHost) {
      return;
    }

    itemsHost.innerHTML = "";
    hideEventsEmpty(container);

    var loading = document.createElement("article");
    loading.className = "events-block__item events-block__item--placeholder";
    var loadingBody = document.createElement("div");
    loadingBody.className = "events-block__body";
    var loadingTitle = document.createElement("h3");
    loadingTitle.className = "events-block__title";
    loadingTitle.textContent = "Loading events…";
    loadingBody.appendChild(loadingTitle);
    loading.appendChild(loadingBody);
    itemsHost.appendChild(loading);

    var layout = normalizeEventsLayout(container.dataset.eventsLayout);
    container.dataset.eventsLayout = layout;
    container.classList.remove(
      "events-block--layout-cards",
      "events-block--layout-list",
      "events-block--layout-compact",
    );
    container.classList.add("events-block--layout-" + layout);

    var limit = parsePositiveInt(container.dataset.eventsLimit, 3);
    var categoryFilter = normalizeCategory(container.dataset.eventsCategory);
    var emptyMessage =
      container.dataset.eventsEmpty || "No upcoming events found.";
    var descriptionLength = parsePositiveInt(
      container.dataset.eventsDescriptionLength,
      160,
    );
    var options = {
      detailBase: container.dataset.eventsDetailBase || "",
      buttonLabel: container.dataset.eventsButtonLabel || "View event",
      showButton: parseBooleanOption(
        container.dataset.eventsShowButton,
        !!container.dataset.eventsDetailBase,
      ),
      showDescription: parseBooleanOption(
        container.dataset.eventsShowDescription,
        true,
      ),
      showLocation: parseBooleanOption(
        container.dataset.eventsShowLocation,
        true,
      ),
      showCategories: parseBooleanOption(
        container.dataset.eventsShowCategories,
        true,
      ),
      showPrice: parseBooleanOption(container.dataset.eventsShowPrice, true),
    };

    Promise.all([fetchEvents(), fetchEventCategories()])
      .then(function (results) {
        var records = Array.isArray(results[0]) ? results[0] : [];
        var categoriesIndex =
          results[1] && typeof results[1] === "object" ? results[1] : {};
        var now = new Date();
        var enriched = records
          .map(function (record) {
            var startDate = parseIsoDate(record.start);
            var endDate = parseIsoDate(record.end);
            var inFuture = false;
            if (startDate && !Number.isNaN(startDate.getTime())) {
              inFuture = startDate.getTime() >= now.getTime();
            }
            if (!inFuture && endDate && !Number.isNaN(endDate.getTime())) {
              inFuture = endDate.getTime() >= now.getTime();
            }
            var categoryNames = [];
            var ids = Array.isArray(record.categories) ? record.categories : [];
            ids.forEach(function (id) {
              var info = categoriesIndex[id];
              if (info && info.name) {
                categoryNames.push(info.name);
              } else if (id) {
                categoryNames.push(String(id));
              }
            });
            return {
              raw: record,
              id: record.id,
              startDate: startDate,
              endDate: endDate,
              upcoming: inFuture,
              description: truncateText(
                stripHtml(record.description),
                descriptionLength,
              ),
              categoryNames: categoryNames,
              lowestPrice: findLowestPrice(record.tickets),
            };
          })
          .filter(function (event) {
            if (!event.startDate) {
              return false;
            }
            if (!event.upcoming) {
              return false;
            }
            if (!categoryFilter) {
              return true;
            }
            return eventMatchesCategoryFilter(
              event.raw,
              categoryFilter,
              categoriesIndex,
            );
          });

        enriched.sort(function (a, b) {
          var aTime = a.startDate instanceof Date ? a.startDate.getTime() : 0;
          var bTime = b.startDate instanceof Date ? b.startDate.getTime() : 0;
          return aTime - bTime;
        });

        if (limit && enriched.length > limit) {
          enriched = enriched.slice(0, limit);
        }

        itemsHost.innerHTML = "";

        if (!enriched.length) {
          showEventsEmpty(container, emptyMessage);
          return;
        }

        hideEventsEmpty(container);
        enriched.forEach(function (event) {
          var node = createEventsItem(event, options);
          itemsHost.appendChild(node);
        });
      })
      .catch(function () {
        itemsHost.innerHTML = "";
        showEventsEmpty(container, "Unable to load events right now.");
      });
  }

  function initEventsBlocks() {
    var blocks = document.querySelectorAll("[data-events-block]");
    blocks.forEach(function (block) {
      renderEventsBlock(block);
    });
  }

  function initBlogDetailBlocks() {
    var blocks = document.querySelectorAll("[data-blog-detail]");
    blocks.forEach(function (block) {
      hydrateBlogDetail(block);
    });
  }

  function initCommerceGrids() {
    var grids = document.querySelectorAll("[data-commerce-grid]");
    grids.forEach(function (grid) {
      hydrateCommerceGrid(grid);
    });
  }

  function initBlogLists() {
    var lists = document.querySelectorAll("[data-blog-list]");
    lists.forEach(function (container) {
      hydrate(container);
    });
  }

  function observe() {
    if (typeof MutationObserver === "undefined") {
      return;
    }
    var observer = new MutationObserver(function (mutations) {
      var shouldRefreshCommerce = false;
      var shouldRefreshBlogs = false;
      var shouldRefreshEvents = false;
      var shouldRefreshMaps = false;
      var shouldRefreshBlogDetails = false;
      mutations.forEach(function (mutation) {
        if (mutation.type !== "childList") {
          return;
        }
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof HTMLElement)) {
            return;
          }
          if (
            node.matches("[data-commerce-grid]") ||
            node.querySelector("[data-commerce-grid]")
          ) {
            shouldRefreshCommerce = true;
          }
          if (
            node.matches("[data-blog-list]") ||
            node.querySelector("[data-blog-list]")
          ) {
            shouldRefreshBlogs = true;
          }
          if (
            node.matches("[data-blog-detail]") ||
            node.querySelector("[data-blog-detail]")
          ) {
            shouldRefreshBlogDetails = true;
          }
          if (
            node.matches("[data-events-block]") ||
            node.querySelector("[data-events-block]")
          ) {
            shouldRefreshEvents = true;
          }
          if (
            node.matches("[data-map-block]") ||
            node.querySelector("[data-map-block]")
          ) {
            shouldRefreshMaps = true;
          }
        });
      });
      if (shouldRefreshCommerce) {
        initCommerceGrids();
      }
      if (shouldRefreshBlogs) {
        initBlogLists();
      }
      if (shouldRefreshBlogDetails) {
        initBlogDetailBlocks();
      }
      if (shouldRefreshEvents) {
        initEventsBlocks();
      }
      if (shouldRefreshMaps) {
        initMapBlocks();
      }
    });
    observer.observe(document.body || document.documentElement, {
      childList: true,
      subtree: true,
    });
  }

  ready(function () {
    initBlogDetailBlocks();
    initCommerceGrids();
    initBlogLists();
    initEventsBlocks();
    initMapBlocks();
    observe();
  });

  window.SparkCMSCommerce = {
    refresh: initCommerceGrids,
  };

  window.SparkCMSBlogLists = {
    refresh: initBlogLists,
  };

  window.SparkCMSBlogDetails = {
    refresh: initBlogDetailBlocks,
  };

  window.SparkCMSEvents = {
    refresh: initEventsBlocks,
  };

  window.SparkCMSMaps = {
    refresh: initMapBlocks,
  };
})();

/* File: script.js */
// File: script.js
(function () {
  var formCache = {};
  var formRequests = {};

  function basePath() {
    var base = typeof window.cmsBase === "string" ? window.cmsBase : "";
    base = base.trim();
    if (!base) return "";
    if (base.charAt(0) !== "/") {
      base = "/" + base;
    }
    return base.replace(/\/$/, "");
  }

  function fetchFormDefinition(id) {
    var key = String(id);
    if (formCache[key]) return Promise.resolve(formCache[key]);
    if (formRequests[key]) return formRequests[key];
    var prefix = basePath();
    var url = (prefix || "") + "/forms/get.php?id=" + encodeURIComponent(id);
    formRequests[key] = fetch(url, { credentials: "same-origin" })
      .then(function (response) {
        if (!response.ok) throw new Error("Failed to load form");
        return response.json();
      })
      .then(function (data) {
        formCache[key] = data;
        return data;
      })
      .catch(function (err) {
        delete formRequests[key];
        throw err;
      });
    return formRequests[key];
  }

  function escapeSelector(value) {
    if (window.CSS && typeof window.CSS.escape === "function") {
      return window.CSS.escape(value);
    }
    return value.replace(/([\.\#\[\]:,])/g, "\\$1");
  }

  function prepareContainer(container) {
    if (!container) return;
    if (!container.dataset.successMessage) {
      var successTemplate = container.querySelector(
        "template[data-success-template]",
      );
      if (successTemplate) {
        container.dataset.successMessage = successTemplate.textContent.trim();
        successTemplate.remove();
      } else {
        container.dataset.successMessage = "Thank you!";
      }
    }
    if (!container.dataset.placeholderMessage) {
      var placeholderEl = container.querySelector(".spark-form-placeholder");
      if (placeholderEl) {
        container.dataset.placeholderMessage = placeholderEl.textContent.trim();
      } else if (container.dataset.placeholder) {
        container.dataset.placeholderMessage = container.dataset.placeholder;
      } else {
        container.dataset.placeholderMessage = "Select a form to display.";
      }
    }
  }

  function showPlaceholder(container, message) {
    prepareContainer(container);
    container.innerHTML = "";
    var placeholder = document.createElement("div");
    placeholder.className = "spark-form-placeholder text-muted";
    placeholder.textContent =
      message || container.dataset.placeholderMessage || "";
    container.appendChild(placeholder);
    container.removeAttribute("data-rendered-form-id");
  }

  function showLoading(container) {
    prepareContainer(container);
    container.innerHTML = "";
    var loading = document.createElement("div");
    loading.className = "spark-form-loading text-muted";
    loading.textContent = "Loading form…";
    container.appendChild(loading);
  }

  function showError(container, message) {
    prepareContainer(container);
    container.innerHTML = "";
    var error = document.createElement("div");
    error.className = "spark-form-error text-danger";
    error.textContent =
      message ||
      "This form is currently unavailable. Please refresh the page or try again later.";
    container.appendChild(error);
    container.removeAttribute("data-rendered-form-id");
  }

  function buildField(field, index) {
    var type = (field.type || "text").toLowerCase();
    var name = field.name || "field_" + index;
    var labelText = field.label || name;
    var required = !!field.required;
    var options = Array.isArray(field.options) ? field.options : [];
    var fieldId = "spark-form-" + name + "-" + index;
    var wrapper = document.createElement("div");
    wrapper.className = "mb-3 spark-form-field";
    wrapper.setAttribute("data-field-name", name);

    if (type === "submit") {
      var submitBtn = document.createElement("button");
      submitBtn.type = "submit";
      submitBtn.className = "btn btn-primary";
      submitBtn.textContent = labelText || "Submit";
      wrapper.classList.add("spark-form-actions");
      wrapper.appendChild(submitBtn);
      return wrapper;
    }

    if (type === "recaptcha") {
      var recaptchaLabel = document.createElement("span");
      recaptchaLabel.className = "form-label d-block";
      recaptchaLabel.textContent = labelText || "reCAPTCHA";
      var recaptchaPlaceholder = document.createElement("div");
      recaptchaPlaceholder.className =
        "spark-recaptcha-placeholder alert alert-secondary mb-0";
      recaptchaPlaceholder.textContent =
        "reCAPTCHA widget will render here during publishing.";
      wrapper.appendChild(recaptchaLabel);
      wrapper.appendChild(recaptchaPlaceholder);
      return wrapper;
    }

    function addDescribedBy(input, describedById) {
      if (!input || !describedById) return;
      var existing = input.getAttribute("aria-describedby");
      if (!existing) {
        input.setAttribute("aria-describedby", describedById);
        return;
      }
      var parts = existing.split(/\s+/);
      if (parts.indexOf(describedById) === -1) {
        input.setAttribute("aria-describedby", existing + " " + describedById);
      }
    }

    function appendFeedback(target, feedbackId, blockDisplay) {
      var feedback = document.createElement("div");
      feedback.className =
        "invalid-feedback spark-form-error-text" +
        (blockDisplay ? " d-block" : "");
      feedback.id = feedbackId;
      target.appendChild(feedback);
      return feedback;
    }

    if (type === "checkbox" && !options.length) {
      var checkboxWrap = document.createElement("div");
      checkboxWrap.className = "form-check";
      var checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.className = "form-check-input";
      checkbox.id = fieldId;
      checkbox.name = name;
      checkbox.value = "1";
      if (required) checkbox.required = true;
      var checkboxLabel = document.createElement("label");
      checkboxLabel.className = "form-check-label";
      checkboxLabel.setAttribute("for", fieldId);
      checkboxLabel.textContent = labelText + (required ? " *" : "");
      checkboxWrap.appendChild(checkbox);
      checkboxWrap.appendChild(checkboxLabel);
      wrapper.appendChild(checkboxWrap);
      var checkboxFeedback = appendFeedback(wrapper, fieldId + "-error", true);
      addDescribedBy(checkbox, checkboxFeedback.id);
      return wrapper;
    }

    if ((type === "checkbox" && options.length) || type === "radio") {
      var groupLabel = document.createElement("span");
      groupLabel.className = "form-label d-block";
      groupLabel.textContent = labelText + (required ? " *" : "");
      wrapper.appendChild(groupLabel);
      var choices = document.createElement("div");
      choices.className = "spark-choice-group";
      options.forEach(function (option, optionIndex) {
        var checkWrap = document.createElement("div");
        checkWrap.className = "form-check";
        var input = document.createElement("input");
        input.type = type;
        input.className = "form-check-input";
        var optionName = type === "checkbox" ? name + "[]" : name;
        input.name = optionName;
        var optionId = fieldId + "-" + optionIndex;
        input.id = optionId;
        input.value = option;
        if (required) {
          if (type === "radio") {
            input.required = true;
          } else if (type === "checkbox" && optionIndex === 0) {
            input.required = true;
          }
        }
        var optLabel = document.createElement("label");
        optLabel.className = "form-check-label";
        optLabel.setAttribute("for", optionId);
        optLabel.textContent = option;
        checkWrap.appendChild(input);
        checkWrap.appendChild(optLabel);
        choices.appendChild(checkWrap);
      });
      wrapper.appendChild(choices);
      var groupFeedback = appendFeedback(wrapper, fieldId + "-error", true);
      choices.querySelectorAll("input").forEach(function (input) {
        addDescribedBy(input, groupFeedback.id);
      });
      return wrapper;
    }

    var label = document.createElement("label");
    label.className = "form-label";
    label.setAttribute("for", fieldId);
    label.textContent = labelText + (required ? " *" : "");
    wrapper.appendChild(label);

    if (type === "textarea") {
      var textarea = document.createElement("textarea");
      textarea.className = "form-control";
      textarea.id = fieldId;
      textarea.name = name;
      textarea.rows = 4;
      if (required) textarea.required = true;
      wrapper.appendChild(textarea);
      var textareaFeedback = appendFeedback(wrapper, fieldId + "-error");
      addDescribedBy(textarea, textareaFeedback.id);
      return wrapper;
    }

    if (type === "select") {
      var select = document.createElement("select");
      select.className = "form-select";
      select.id = fieldId;
      select.name = name;
      if (required) select.required = true;
      var placeholderOption = document.createElement("option");
      placeholderOption.value = "";
      placeholderOption.textContent = "Please select";
      select.appendChild(placeholderOption);
      options.forEach(function (option) {
        var opt = document.createElement("option");
        opt.value = option;
        opt.textContent = option;
        select.appendChild(opt);
      });
      wrapper.appendChild(select);
      var selectFeedback = appendFeedback(wrapper, fieldId + "-error");
      addDescribedBy(select, selectFeedback.id);
      return wrapper;
    }

    var input = document.createElement("input");
    input.id = fieldId;
    input.name = name;
    if (required) input.required = true;
    switch (type) {
      case "date":
        input.type = "date";
        break;
      case "number":
        input.type = "number";
        break;
      case "password":
        input.type = "password";
        break;
      case "file":
        input.type = "file";
        break;
      case "email":
        input.type = "email";
        break;
      default:
        input.type = "text";
        break;
    }
    input.className = input.type === "file" ? "form-control" : "form-control";
    wrapper.appendChild(input);
    var inputFeedback = appendFeedback(wrapper, fieldId + "-error");
    addDescribedBy(input, inputFeedback.id);
    return wrapper;
  }

  function clearFieldErrors(formEl) {
    formEl.querySelectorAll(".spark-form-field").forEach(function (wrapper) {
      wrapper.classList.remove("has-error");
      wrapper.querySelectorAll(".is-invalid").forEach(function (el) {
        el.classList.remove("is-invalid");
        el.removeAttribute("aria-invalid");
      });
      var feedback = wrapper.querySelector(".invalid-feedback");
      if (feedback) {
        feedback.textContent = "";
        feedback.style.display = "";
      }
    });
  }

  function applyFieldErrors(formEl, errors) {
    if (!Array.isArray(errors)) return;
    errors.forEach(function (error) {
      if (!error || !error.field) return;
      var selector = '[data-field-name="' + escapeSelector(error.field) + '"]';
      var wrapper = formEl.querySelector(selector);
      if (!wrapper) return;
      wrapper.classList.add("has-error");
      var inputs = wrapper.querySelectorAll("input, textarea, select");
      inputs.forEach(function (input) {
        input.classList.add("is-invalid");
        input.setAttribute("aria-invalid", "true");
      });
      var feedback = wrapper.querySelector(".invalid-feedback");
      if (feedback) {
        feedback.textContent = error.message || "Please correct this field.";
        feedback.style.display = "block";
      }
    });
  }

  function attachSubmitHandler(formEl, statusEl, successMessage) {
    if (!formEl) return;
    formEl.addEventListener("submit", function (event) {
      event.preventDefault();
      if (formEl.dataset.submitting === "true") return;
      clearFieldErrors(formEl);
      if (statusEl) {
        statusEl.textContent = "Submitting…";
        statusEl.classList.remove("text-success", "text-danger");
      }
      formEl.dataset.submitting = "true";
      var submitButtons = formEl.querySelectorAll(
        'button[type="submit"], input[type="submit"]',
      );
      submitButtons.forEach(function (btn) {
        btn.disabled = true;
      });
      var prefix = basePath();
      var url = (prefix || "") + "/forms/submit.php";
      var formData = new FormData(formEl);
      fetch(url, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.data || result.data.success === false) {
            var errors =
              result.data && result.data.errors ? result.data.errors : null;
            applyFieldErrors(formEl, errors);
            if (statusEl) {
              statusEl.textContent =
                (result.data && result.data.message) ||
                "We could not submit the form. Check the highlighted fields and try again.";
              statusEl.classList.add("text-danger");
            }
            return;
          }
          formEl.reset();
          if (statusEl) {
            statusEl.textContent = successMessage || "Thank you!";
            statusEl.classList.add("text-success");
          }
        })
        .catch(function () {
          if (statusEl) {
            statusEl.textContent =
              "We could not submit the form. Please try again in a moment.";
            statusEl.classList.add("text-danger");
          }
        })
        .finally(function () {
          formEl.dataset.submitting = "false";
          submitButtons.forEach(function (btn) {
            btn.disabled = false;
          });
        });
    });
  }

  function renderSparkForm(container, form) {
    prepareContainer(container);
    container.innerHTML = "";
    if (!form || !Array.isArray(form.fields) || !form.fields.length) {
      showError(container, "This form has no fields yet.");
      return;
    }
    var formEl = document.createElement("form");
    formEl.className = "spark-form needs-validation";
    formEl.setAttribute("novalidate", "novalidate");
    formEl.setAttribute("enctype", "multipart/form-data");
    formEl.dataset.formId = form.id;

    var hiddenId = document.createElement("input");
    hiddenId.type = "hidden";
    hiddenId.name = "form_id";
    hiddenId.value = form.id;
    formEl.appendChild(hiddenId);

    form.fields.forEach(function (field, index) {
      formEl.appendChild(buildField(field, index));
    });

    var status = document.createElement("div");
    status.className = "spark-form-status mt-3";
    status.setAttribute("role", "status");
    status.setAttribute("aria-live", "polite");

    container.appendChild(formEl);
    container.appendChild(status);

    var successMessage = container.dataset.successMessage || "Thank you!";
    attachSubmitHandler(formEl, status, successMessage);
  }

  function initializeSparkForms() {
    var containers = document.querySelectorAll(
      ".spark-form-embed[data-form-id]",
    );
    containers.forEach(function (container) {
      if (!container) return;
      prepareContainer(container);
      var rawId = container.getAttribute("data-form-id") || "";
      var formId = parseInt(rawId, 10);
      if (!formId) {
        showPlaceholder(
          container,
          container.dataset.placeholderMessage || "Select a form to display.",
        );
        return;
      }
      var renderedId = parseInt(
        container.getAttribute("data-rendered-form-id") || "0",
        10,
      );
      if (renderedId === formId && container.querySelector("form.spark-form")) {
        return;
      }
      showLoading(container);
      var token = Date.now().toString(36) + Math.random().toString(36).slice(2);
      container.setAttribute("data-render-token", token);
      fetchFormDefinition(formId)
        .then(function (form) {
          if (container.getAttribute("data-render-token") !== token) return;
          renderSparkForm(container, form);
          container.setAttribute("data-rendered-form-id", String(formId));
          container.removeAttribute("data-render-token");
        })
        .catch(function () {
          if (container.getAttribute("data-render-token") !== token) return;
          showError(
            container,
            "We could not load this form. Please refresh the page or try again later.",
          );
          container.removeAttribute("data-render-token");
        });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    var toggle = document.querySelector(".nav-toggle");
    var nav = document.getElementById("main-nav");
    if (toggle && nav) {
      toggle.addEventListener("click", function () {
        nav.classList.toggle("active");
      });
    }

    var accordions = document.querySelectorAll(".accordion");
    accordions.forEach(function (acc) {
      var btn = acc.querySelector(".accordion-button");
      var panel = acc.querySelector(".accordion-panel");
      if (!btn || !panel) return;

      if (acc.classList.contains("open")) {
        btn.setAttribute("aria-expanded", "true");
        panel.style.display = "block";
      } else {
        btn.setAttribute("aria-expanded", "false");
        panel.style.display = "none";
      }

      btn.addEventListener("click", function () {
        if (acc.classList.contains("open")) {
          acc.classList.remove("open");
          btn.setAttribute("aria-expanded", "false");
          panel.style.display = "none";
        } else {
          acc.classList.add("open");
          btn.setAttribute("aria-expanded", "true");
          panel.style.display = "block";
        }
      });
    });

    initializeSparkForms();
    document.addEventListener("canvasUpdated", initializeSparkForms);

    if (window.MutationObserver) {
      var observer = new MutationObserver(function (mutations) {
        var needsRefresh = false;
        mutations.forEach(function (mutation) {
          if (
            mutation.type === "attributes" &&
            mutation.attributeName === "data-form-id"
          ) {
            var target = mutation.target;
            if (
              target &&
              target.classList &&
              target.classList.contains("spark-form-embed")
            ) {
              needsRefresh = true;
            }
          }
          if (mutation.type === "childList") {
            mutation.addedNodes.forEach(function (node) {
              if (node.nodeType !== 1) return;
              if (
                node.classList &&
                node.classList.contains("spark-form-embed")
              ) {
                needsRefresh = true;
              } else if (
                node.querySelector &&
                node.querySelector(".spark-form-embed")
              ) {
                needsRefresh = true;
              }
            });
          }
        });
        if (needsRefresh) {
          initializeSparkForms();
        }
      });
      observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["data-form-id"],
      });
    }
  });
})();
