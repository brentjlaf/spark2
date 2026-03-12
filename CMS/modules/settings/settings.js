// File: settings.js
$(function () {
  const $form = $("#settingsForm");
  const $dashboard = $("#settingsDashboard");
  const $lastSaved = $("#settingsLastSaved");
  const $saveButtons = $("[data-save-settings]");
  const $logoPreview = $("#logoPreview");
  const $faviconPreview = $("#faviconPreview");
  const $ogPreview = $("#ogImagePreview");
  const $tabs = $(".settings-tab");
  const $tabPanels = $(".settings-tab-panel");
  const $logoFileLabel = $("#logoFileName");
  const $faviconFileLabel = $("#faviconFileName");
  const $ogImageFileLabel = $("#ogImageFileName");
  const $clearLogo = $("#clearLogo");
  const $clearFavicon = $("#clearFavicon");
  const $clearOgImage = $("#clearOgImage");
  const $socialPreviewImage = $("#socialPreviewImage");
  const $socialPreviewFallback = $("#socialPreviewImageFallback");
  const $socialPreviewTitle = $("#socialPreviewTitle");
  const $socialPreviewDescription = $("#socialPreviewDescription");
  const $socialPreviewDomain = $("#socialPreviewDomain");
  const $stripeEnabled = $("#stripeEnabled");
  const $stripeMode = $("#stripeMode");
  const $stripePublishableKey = $("#stripePublishableKey");
  const $stripeSecretKey = $("#stripeSecretKey");
  const $stripeWebhookSecret = $("#stripeWebhookSecret");
  const $stripeConfigFields = $("#stripeConfigFields");

  const integrationValidators = [
    {
      selector: "#googleAnalytics",
      pattern: /^(G-[A-Z0-9]{8,12}|UA-\d{4,10}-\d{1,4})$/i,
      message:
        "Enter a valid Google Analytics ID (e.g., G-XXXXXXXXXX or UA-XXXXXXXX-X).",
    },
    {
      selector: "#googleSearchConsole",
      pattern: /^(google-site-verification=)?[A-Za-z0-9_-]{10,100}$/,
      message:
        "Enter a valid Google Search Console verification code (e.g., google-site-verification=XXXXX).",
    },
    {
      selector: "#facebookPixel",
      pattern: /^\d{15,16}$/,
      message: "Enter a valid Facebook Pixel ID using 15-16 digits.",
    },
    {
      selector: "#stripePublishableKey",
      pattern: /^pk_(test|live)_[0-9a-zA-Z]{16,}$/,
      message: "Enter a valid Stripe publishable key (e.g., pk_test_XXXX).",
      requiredWhen: () => $stripeEnabled.is(":checked"),
      requiredMessage:
        "Stripe publishable key is required when Stripe is enabled.",
    },
    {
      selector: "#stripeSecretKey",
      pattern: /^sk_(test|live)_[0-9a-zA-Z]{16,}$/,
      message: "Enter a valid Stripe secret key (e.g., sk_test_XXXX).",
      requiredWhen: () => $stripeEnabled.is(":checked"),
      requiredMessage: "Stripe secret key is required when Stripe is enabled.",
    },
    {
      selector: "#stripeWebhookSecret",
      pattern: /^whsec_[0-9a-zA-Z]{8,}$/,
      message:
        "Enter a valid Stripe webhook signing secret (e.g., whsec_XXXX).",
    },
  ];

  function activateTab($tab, options = {}) {
    if (!$tab || !$tab.length) {
      return;
    }

    const targetId = $tab.attr("data-tab-target");
    if (!targetId) {
      return;
    }

    const shouldFocus = Boolean(options.focus);

    $tabs.each(function () {
      const $button = $(this);
      const isActive = $button.is($tab);
      $button
        .attr("aria-selected", isActive ? "true" : "false")
        .attr("tabindex", isActive ? "0" : "-1")
        .toggleClass("is-active", isActive);
    });

    $tabPanels.each(function () {
      const $panel = $(this);
      if ($panel.attr("id") === targetId) {
        $panel.removeAttr("hidden");
      } else {
        $panel.attr("hidden", true);
      }
    });

    if (shouldFocus) {
      $tab.trigger("focus");
    }
  }

  function focusAdjacentTab(currentIndex, direction) {
    if (!$tabs.length) {
      return;
    }
    const total = $tabs.length;
    let nextIndex = (currentIndex + direction + total) % total;
    const $next = $tabs.eq(nextIndex);
    activateTab($next, { focus: true });
  }

  function extractFileName(value) {
    if (!value) {
      return "";
    }
    if (/^data:/i.test(value)) {
      return "Uploaded image";
    }
    const cleaned = value.split("?")[0];
    const parts = cleaned.split(/[\\/]/);
    const candidate = parts.pop();
    return candidate || value;
  }

  function getDefaultFileLabel($label) {
    return $label && $label.data("default")
      ? String($label.data("default"))
      : "No file selected";
  }

  function getRemovalFileLabel($label) {
    return $label && $label.data("remove")
      ? String($label.data("remove"))
      : "Marked for removal";
  }

  function refreshFileLabel($checkbox, $label) {
    if (!$label || !$label.length) {
      return;
    }
    if ($checkbox && $checkbox.is(":checked")) {
      $label.text(getRemovalFileLabel($label));
      return;
    }
    const fileName = $checkbox ? $checkbox.data("fileName") || "" : "";
    const fileSource = $checkbox
      ? $checkbox.data("fileSource") || "stored"
      : "stored";
    if (fileName) {
      const prefix = fileSource === "selected" ? "Selected" : "Current";
      $label.text(`${prefix}: ${fileName}`);
    } else {
      $label.text(getDefaultFileLabel($label));
    }
  }

  function clearFieldError($field) {
    if (!$field || !$field.length) {
      return;
    }
    $field.removeClass("is-invalid").removeAttr("aria-invalid");
    $field.closest(".form-group").find(".form-error").remove();
  }

  function showFieldError($field, message) {
    if (!$field || !$field.length) {
      return;
    }
    clearFieldError($field);
    $field.addClass("is-invalid").attr("aria-invalid", "true");
    const $group = $field.closest(".form-group");
    const $error = $('<div class="form-error" role="alert"></div>').text(
      message,
    );
    const $help = $group.find(".form-help").last();
    if ($help.length) {
      $error.insertAfter($help);
    } else {
      $group.append($error);
    }
  }

  function clearIntegrationErrors() {
    integrationValidators.forEach(({ selector }) => {
      clearFieldError($(selector));
    });
  }

  function sanitizeToastMessage(value, fallback = "") {
    const raw = value === null || value === undefined ? "" : String(value);
    const container = document.createElement("div");
    container.innerHTML = raw;
    const text = (container.textContent || container.innerText || "").trim();
    if (text) {
      return text;
    }
    return fallback || "";
  }

  function notifySuccess(message) {
    const text = sanitizeToastMessage(message);
    if (!text) {
      return;
    }
    if (
      window.AdminNotifications &&
      typeof window.AdminNotifications.showSuccessToast === "function"
    ) {
      window.AdminNotifications.showSuccessToast(text);
    } else {
      alertModal(text);
    }
  }

  function notifyError(message) {
    const text = sanitizeToastMessage(message);
    if (!text) {
      return;
    }
    if (
      window.AdminNotifications &&
      typeof window.AdminNotifications.showErrorToast === "function"
    ) {
      window.AdminNotifications.showErrorToast(text);
    } else {
      alertModal(text);
    }
  }

  function validateIntegrations() {
    let firstInvalidField = null;
    let hasError = false;

    integrationValidators.forEach((validator) => {
      const { selector, pattern, message, requiredWhen, requiredMessage } =
        validator;
      const $field = $(selector);
      if (!$field.length) {
        return;
      }

      const value = ($field.val() || "").trim();
      const isRequired =
        typeof requiredWhen === "function" ? Boolean(requiredWhen()) : false;

      if (!value) {
        clearFieldError($field);
        if (isRequired) {
          const errorMessage =
            requiredMessage || message || "This field is required.";
          showFieldError($field, errorMessage);
          if (!firstInvalidField) {
            firstInvalidField = $field;
          }
          hasError = true;
        }
        return;
      }

      if (pattern && !pattern.test(value)) {
        showFieldError($field, message || "Enter a valid value.");
        if (!firstInvalidField) {
          firstInvalidField = $field;
        }
        hasError = true;
      } else {
        clearFieldError($field);
      }
    });

    if (hasError) {
      notifyError(
        "Please fix the highlighted integration fields before saving.",
      );
      if (firstInvalidField) {
        firstInvalidField.focus();
      }
    }

    return !hasError;
  }

  function formatTimestamp(value) {
    if (!value) {
      return "Not saved yet";
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
      return value;
    }
    return date.toLocaleString(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    });
  }

  function updateHeroMeta(timestamp) {
    $dashboard.attr("data-last-saved", timestamp || "");
    $lastSaved.text(formatTimestamp(timestamp));
  }

  function updateOverview() {
    const siteName = $("#site_name").val().trim();
    $("#settingsOverviewName").text(siteName || "Not set");

    const socialSelectors = [
      "#facebookLink",
      "#twitterLink",
      "#instagramLink",
      "#linkedinLink",
      "#youtubeLink",
      "#tiktokLink",
      "#pinterestLink",
      "#snapchatLink",
      "#redditLink",
      "#threadsLink",
      "#mastodonLink",
      "#githubLink",
      "#dribbbleLink",
      "#twitchLink",
      "#whatsappLink",
    ];
    const socialCount = socialSelectors.reduce((count, selector) => {
      return count + ($(selector).val().trim() ? 1 : 0);
    }, 0);
    $("#settingsOverviewSocials").text(socialCount);

    const trackingFields = [
      "#googleAnalytics",
      "#googleSearchConsole",
      "#facebookPixel",
    ];
    const trackingCount = trackingFields.reduce((count, selector) => {
      return count + ($(selector).val().trim() ? 1 : 0);
    }, 0);
    $("#settingsOverviewTracking").text(trackingCount);

    const sitemapOn = $("#generateSitemap").is(":checked");
    const indexingOn = $("#allowIndexing").is(":checked");
    const visibility = indexingOn ? "Public" : "Restricted";
    const details = [];
    details.push(sitemapOn ? "Sitemap on" : "Sitemap off");
    details.push(indexingOn ? "Indexing allowed" : "Indexing blocked");
    $("#settingsOverviewVisibility")
      .text(visibility)
      .attr("title", details.join(" • "));

    const stripeEnabled = $stripeEnabled.is(":checked");
    const stripeMode =
      ($stripeMode.val() || "test") === "live" ? "Live" : "Test";
    const paymentsText = stripeEnabled
      ? `Stripe (${stripeMode})`
      : "Stripe off";
    const paymentsTitle = stripeEnabled
      ? `Stripe ${stripeMode.toLowerCase()} mode enabled`
      : "Stripe disabled";
    $("#settingsOverviewPayments")
      .text(paymentsText)
      .attr("title", paymentsTitle);
  }

  function setSocialPreviewImage(src) {
    if (src) {
      $socialPreviewImage.attr("src", src).removeAttr("hidden");
      $socialPreviewFallback.attr("hidden", true);
    } else {
      $socialPreviewImage.attr("src", "").attr("hidden", true);
      $socialPreviewFallback.removeAttr("hidden");
    }
  }

  function togglePreview($img, src) {
    if (src) {
      $img.attr("src", src).removeAttr("hidden");
    } else {
      $img.attr("src", "").attr("hidden", true);
    }
    if ($img.is($ogPreview)) {
      setSocialPreviewImage(src);
    }
  }

  function setPreviewState(
    $preview,
    $checkbox,
    src,
    fileName = "",
    source = "stored",
  ) {
    const hasSrc = Boolean(src);
    $checkbox.data("previewSrc", hasSrc ? src : "");
    $checkbox.data("fileName", hasSrc ? fileName : "");
    $checkbox.data("fileSource", hasSrc ? source : "");
    $checkbox.prop("checked", false).prop("disabled", !hasSrc);
    togglePreview($preview, src);
  }

  function updateStripeConfigState() {
    if (!$stripeConfigFields.length) {
      return;
    }
    const enabled = $stripeEnabled.is(":checked");
    $stripeConfigFields
      .toggleClass("is-disabled", !enabled)
      .attr("aria-disabled", enabled ? "false" : "true");
    if (enabled) {
      $stripeConfigFields.removeAttr("aria-hidden");
    } else {
      $stripeConfigFields.attr("aria-hidden", "true");
    }
  }

  function bindClearToggle($checkbox, $preview, $label) {
    $checkbox.on("change", function () {
      if (this.checked) {
        togglePreview($preview, "");
      } else {
        const stored = $checkbox.data("previewSrc") || "";
        togglePreview($preview, stored);
      }
      if ($label) {
        refreshFileLabel($checkbox, $label);
      }
    });
  }

  bindClearToggle($clearLogo, $logoPreview, $logoFileLabel);
  bindClearToggle($clearFavicon, $faviconPreview, $faviconFileLabel);
  bindClearToggle($clearOgImage, $ogPreview, $ogImageFileLabel);

  updateStripeConfigState();
  $stripeEnabled.on("change", function () {
    updateStripeConfigState();
    updateOverview();
  });

  function getDefaultOgTitle(settings) {
    const siteName = (settings.site_name || "").trim() || "SparkCMS";
    const tagline = (settings.tagline || "").trim();
    return tagline ? `${siteName} | ${tagline}` : siteName;
  }

  function getDefaultOgDescription(settings) {
    const siteName = (settings.site_name || "").trim() || "SparkCMS";
    return `Stay up to date with the latest updates from ${siteName}.`;
  }

  function resolveOgTitle() {
    const ogTitleInput = $("#ogTitle").val().trim();
    if (ogTitleInput) {
      return ogTitleInput;
    }
    return getDefaultOgTitle({
      site_name: $("#site_name").val(),
      tagline: $("#tagline").val(),
    });
  }

  function resolveOgDescription() {
    const ogDescriptionInput = $("#ogDescription").val().trim();
    if (ogDescriptionInput) {
      return ogDescriptionInput;
    }
    return getDefaultOgDescription({
      site_name: $("#site_name").val(),
    });
  }

  function updateSocialPreviewText() {
    $socialPreviewTitle.text(resolveOgTitle());
    $socialPreviewDescription.text(resolveOgDescription());
  }

  function loadSettings() {
    $.getJSON("modules/settings/list_settings.php", function (data) {
      data = data || {};
      $("#site_name").val(data.site_name || "");
      $("#tagline").val(data.tagline || "");
      $("#admin_email").val(data.admin_email || "");

      setPreviewState(
        $logoPreview,
        $clearLogo,
        data.logo || "",
        extractFileName(data.logo),
        "stored",
      );
      setPreviewState(
        $faviconPreview,
        $clearFavicon,
        data.favicon || "",
        extractFileName(data.favicon),
        "stored",
      );

      $("#timezone").val(data.timezone || "America/Denver");
      $("#googleAnalytics").val(data.googleAnalytics || "");
      $("#googleSearchConsole").val(data.googleSearchConsole || "");
      $("#facebookPixel").val(data.facebookPixel || "");

      $("#generateSitemap").prop("checked", data.generateSitemap !== false);
      $("#allowIndexing").prop("checked", data.allowIndexing !== false);

      const social = data.social || {};
      $("#facebookLink").val(social.facebook || "");
      $("#twitterLink").val(social.twitter || "");
      $("#instagramLink").val(social.instagram || "");
      $("#linkedinLink").val(social.linkedin || "");
      $("#youtubeLink").val(social.youtube || "");
      $("#tiktokLink").val(social.tiktok || "");
      $("#pinterestLink").val(social.pinterest || "");
      $("#snapchatLink").val(social.snapchat || "");
      $("#redditLink").val(social.reddit || "");
      $("#threadsLink").val(social.threads || "");
      $("#mastodonLink").val(social.mastodon || "");
      $("#githubLink").val(social.github || "");
      $("#dribbbleLink").val(social.dribbble || "");
      $("#twitchLink").val(social.twitch || "");
      $("#whatsappLink").val(social.whatsapp || "");

      const openGraph = data.open_graph || {};
      const ogTitleValue = (openGraph.title || "").trim();
      const ogDescriptionValue = (openGraph.description || "").trim();
      $("#ogTitle").val(ogTitleValue || getDefaultOgTitle(data));
      $("#ogDescription").val(
        ogDescriptionValue || getDefaultOgDescription(data),
      );
      setPreviewState(
        $ogPreview,
        $clearOgImage,
        openGraph.image || "",
        extractFileName(openGraph.image),
        "stored",
      );

      const payments = data.payments || {};
      const stripe = payments.stripe || {};
      $stripeEnabled.prop("checked", stripe.enabled === true);
      $stripeMode.val(stripe.mode === "live" ? "live" : "test");
      $stripePublishableKey.val(stripe.publishable_key || "");
      $stripeSecretKey.val(stripe.secret_key || "");
      $stripeWebhookSecret.val(stripe.webhook_secret || "");

      $("#logoFile").val("");
      $("#faviconFile").val("");
      $("#ogImageFile").val("");

      refreshFileLabel($clearLogo, $logoFileLabel);
      refreshFileLabel($clearFavicon, $faviconFileLabel);
      refreshFileLabel($clearOgImage, $ogImageFileLabel);

      const hostname =
        window.location && window.location.hostname
          ? window.location.hostname
          : "yourdomain.com";
      $socialPreviewDomain.text(hostname);
      updateSocialPreviewText();

      clearIntegrationErrors();
      updateStripeConfigState();
      updateHeroMeta(data.last_updated || "");
      updateOverview();
    });
  }

  $("#logoFile").on("change", function () {
    const file = this.files && this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        setPreviewState(
          $logoPreview,
          $clearLogo,
          e.target.result,
          file.name,
          "selected",
        );
        refreshFileLabel($clearLogo, $logoFileLabel);
        updateOverview();
      };
      reader.readAsDataURL(file);
    } else {
      refreshFileLabel($clearLogo, $logoFileLabel);
    }
  });

  $("#faviconFile").on("change", function () {
    const file = this.files && this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        setPreviewState(
          $faviconPreview,
          $clearFavicon,
          e.target.result,
          file.name,
          "selected",
        );
        refreshFileLabel($clearFavicon, $faviconFileLabel);
      };
      reader.readAsDataURL(file);
    } else {
      refreshFileLabel($clearFavicon, $faviconFileLabel);
    }
  });

  $("#ogImageFile").on("change", function () {
    const file = this.files && this.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = function (e) {
        setPreviewState(
          $ogPreview,
          $clearOgImage,
          e.target.result,
          file.name,
          "selected",
        );
        refreshFileLabel($clearOgImage, $ogImageFileLabel);
      };
      reader.readAsDataURL(file);
    } else {
      refreshFileLabel($clearOgImage, $ogImageFileLabel);
    }
  });

  $(".settings-file-trigger").on("click", function (event) {
    event.preventDefault();
    const targetId = $(this).attr("data-input-target");
    if (!targetId) {
      return;
    }
    const $targetInput = $(`#${targetId}`);
    if ($targetInput.length) {
      $targetInput.trigger("click");
    }
  });

  if ($tabs.length) {
    const $initialTab = $tabs.filter('[aria-selected="true"]').first();
    activateTab($initialTab.length ? $initialTab : $tabs.first());

    $tabs.on("click", function () {
      activateTab($(this));
    });

    $tabs.on("keydown", function (event) {
      const key = event.key;
      const index = $tabs.index(this);
      if (key === "ArrowRight" || key === "ArrowDown") {
        event.preventDefault();
        focusAdjacentTab(index, 1);
      } else if (key === "ArrowLeft" || key === "ArrowUp") {
        event.preventDefault();
        focusAdjacentTab(index, -1);
      } else if (key === "Home") {
        event.preventDefault();
        activateTab($tabs.first(), { focus: true });
      } else if (key === "End") {
        event.preventDefault();
        activateTab($tabs.last(), { focus: true });
      }
    });
  }

  $form.on("input change", "input, textarea, select", function () {
    clearFieldError($(this));
    updateOverview();
  });

  $form.on(
    "input change",
    "#ogTitle, #ogDescription, #site_name, #tagline",
    function () {
      updateSocialPreviewText();
    },
  );

  $form.on("submit", function (e) {
    e.preventDefault();
    if (!validateIntegrations()) {
      return;
    }

    const fd = new FormData(this);
    $.ajax({
      url: "modules/settings/save_settings.php",
      method: "POST",
      data: fd,
      processData: false,
      contentType: false,
      dataType: "json",
      beforeSend: function () {
        $saveButtons.addClass("is-loading").prop("disabled", true);
      },
      complete: function () {
        $saveButtons.removeClass("is-loading").prop("disabled", false);
      },
      success: function (response) {
        if (response && response.status === "ok") {
          notifySuccess("Settings saved successfully.");
          if (response.last_updated) {
            updateHeroMeta(response.last_updated);
          }
          loadSettings();
        } else {
          const message =
            response && response.message
              ? response.message
              : "Unable to save settings";
          notifyError(message);
        }
      },
      error: function (xhr) {
        let message = "Unable to save settings";
        if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
          message = xhr.responseJSON.message;
        } else if (xhr && xhr.responseText) {
          try {
            const parsed = JSON.parse(xhr.responseText);
            if (parsed && parsed.message) {
              message = parsed.message;
            }
          } catch (e) {
            // ignore JSON parse errors
          }
        }
        notifyError(message);
      },
    });
  });

  loadSettings();
});

// ── Feature 12: API Key management ───────────────────────────────────────────
// Self-contained IIFE so it doesn't pollute the settings closure scope.
(function () {
  "use strict";

  // ── Helpers ───────────────────────────────────────────────────────────────
  function esc(str) {
    return String(str == null ? "" : str).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  function fmtDate(ts) {
    if (!ts) return "Never";
    return new Date(ts * 1000).toLocaleString();
  }

  // ── State ─────────────────────────────────────────────────────────────────
  var keysLoaded = false;

  // ── Load keys when the API Keys tab is first activated ────────────────────
  $(document).on("click", '[data-tab-target="settings-tab-apikeys"]', function () {
    if (!keysLoaded) {
      loadApiKeys();
    }
  });

  function loadApiKeys() {
    $("#apiKeysLoading").show();
    $("#apiKeysTable, #apiKeysEmpty, #apiKeysError").hide();
    $.getJSON("modules/settings/list_api_keys.php")
      .done(function (res) {
        $("#apiKeysLoading").hide();
        renderApiKeys(res.keys || []);
        keysLoaded = true;
      })
      .fail(function () {
        $("#apiKeysLoading").hide();
        $("#apiKeysError").text("Failed to load API keys. Please refresh and try again.").show();
      });
  }

  function renderApiKeys(keys) {
    var $tbody = $("#apiKeysBody");
    $tbody.empty();
    if (!keys.length) {
      $("#apiKeysTable").hide();
      $("#apiKeysEmpty").show();
      return;
    }
    keys.forEach(function (k) {
      var perms = (k.permissions || []).join(", ") || "none";
      var enabledBadge = k.enabled
        ? '<span class="api-key-badge api-key-badge--on">Enabled</span>'
        : '<span class="api-key-badge api-key-badge--off">Disabled</span>';
      var toggleLabel = k.enabled ? "Disable" : "Enable";
      var $row = $("<tr>").attr("data-key-id", k.id).html(
        "<td>" + esc(k.name) + "</td>" +
        '<td><code class="api-key-hint">' + esc(k.key_hint) + "</code></td>" +
        "<td>" + esc(perms) + "</td>" +
        "<td>" + fmtDate(k.last_used_at) + "</td>" +
        "<td>" + enabledBadge + "</td>" +
        '<td class="api-keys-row-actions">' +
          '<button type="button" class="btn btn-sm btn-secondary api-key-toggle-btn"' +
            ' data-key-id="' + esc(k.id) + '"' +
            ' data-enabled="' + (k.enabled ? "1" : "0") + '">' +
            toggleLabel +
          "</button>" +
          '<button type="button" class="btn btn-sm btn-danger api-key-revoke-btn"' +
            ' data-key-id="' + esc(k.id) + '"' +
            ' data-key-name="' + esc(k.name) + '">' +
            "Revoke" +
          "</button>" +
        "</td>"
      );
      $tbody.append($row);
    });
    $("#apiKeysTable").show();
    $("#apiKeysEmpty").hide();
  }

  // ── Open "Create" modal ───────────────────────────────────────────────────
  $("#createApiKeyBtn").on("click", function () {
    $("#apiKeyName").val("");
    $("#apiKeyPermRead").prop("checked", true);
    $("#apiKeyPermWrite, #apiKeyPermDelete").prop("checked", false);
    $("#apiKeyModalError").hide().text("");
    openModal("apiKeyModal");
    setTimeout(function () { $("#apiKeyName").focus(); }, 100);
  });

  $("#apiKeyModalCancel").on("click", function () {
    closeModal("apiKeyModal");
  });

  // ── Save new key ──────────────────────────────────────────────────────────
  $("#apiKeyModalSave").on("click", function () {
    var name = $("#apiKeyName").val().trim();
    if (!name) {
      $("#apiKeyModalError").text("Please enter a name for this API key.").show();
      $("#apiKeyName").focus();
      return;
    }
    var permissions = [];
    if ($("#apiKeyPermRead").is(":checked"))   permissions.push("read");
    if ($("#apiKeyPermWrite").is(":checked"))  permissions.push("write");
    if ($("#apiKeyPermDelete").is(":checked")) permissions.push("delete");

    var $btn = $("#apiKeyModalSave");
    $btn.prop("disabled", true).text("Creating…");

    $.ajax({
      url: "modules/settings/save_api_key.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({ name: name, permissions: permissions }),
      dataType: "json",
    })
      .done(function (res) {
        closeModal("apiKeyModal");
        if (res && res.key && res.key.key) {
          $("#apiKeyRevealValue").text(res.key.key);
          openModal("apiKeyRevealModal");
        }
        keysLoaded = false;
        loadApiKeys();
      })
      .fail(function (jqXHR) {
        var msg = "Failed to create API key.";
        if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
          msg = jqXHR.responseJSON.error;
        }
        $("#apiKeyModalError").text(msg).show();
      })
      .always(function () {
        $btn.prop("disabled", false).text("Create Key");
      });
  });

  // ── Reveal modal: copy key ────────────────────────────────────────────────
  $("#apiKeyRevealCopy").on("click", function () {
    var val = $("#apiKeyRevealValue").text();
    var $btn = $("#apiKeyRevealCopy");
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(val).then(function () {
        $btn.find(".btn-label").text("Copied!");
        setTimeout(function () { $btn.find(".btn-label").text("Copy"); }, 2000);
      });
    } else {
      // Fallback: select the code element text
      var range = document.createRange();
      range.selectNodeContents(document.getElementById("apiKeyRevealValue"));
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    }
  });

  $("#apiKeyRevealClose").on("click", function () {
    closeModal("apiKeyRevealModal");
  });

  // ── Toggle enabled / disabled ─────────────────────────────────────────────
  $(document).on("click", ".api-key-toggle-btn", function () {
    var $btn      = $(this);
    var id        = $btn.data("key-id");
    var isEnabled = $btn.data("enabled") === 1 || $btn.data("enabled") === "1";
    $btn.prop("disabled", true);
    $.ajax({
      url: "modules/settings/save_api_key.php",
      method: "POST",
      contentType: "application/json",
      data: JSON.stringify({ id: id, enabled: !isEnabled }),
      dataType: "json",
    })
      .done(function () {
        keysLoaded = false;
        loadApiKeys();
      })
      .fail(function () {
        alertModal("Failed to update the API key. Please try again.");
        $btn.prop("disabled", false);
      });
  });

  // ── Revoke (permanently delete) ───────────────────────────────────────────
  $(document).on("click", ".api-key-revoke-btn", function () {
    var id   = $(this).data("key-id");
    var name = $(this).data("key-name") || "this key";
    confirmModal(
      'Permanently revoke the API key "' + name + '"?\n\n' +
      "Any apps using it will immediately lose access. This cannot be undone."
    ).then(function (confirmed) {
      if (!confirmed) return;
      $.ajax({
        url: "modules/settings/delete_api_key.php",
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify({ id: id }),
        dataType: "json",
      })
        .done(function () {
          keysLoaded = false;
          loadApiKeys();
        })
        .fail(function () {
          alertModal("Failed to revoke the API key. Please try again.");
        });
    });
  });
})();
