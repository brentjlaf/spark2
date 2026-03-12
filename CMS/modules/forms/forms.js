// File: forms.js
$(function () {
  function escapeHtml(str) {
    return $("<div>").text(str).html();
  }

  let currentField = null;
  let currentFormId = null;
  let currentFormName = "";
  let currentSubmissions = [];
  let lastSubmissionTrigger = null;
  let lastSubmissionsTrigger = null;
  let confirmationPreviewTrigger = null;
  let formsCache = [];

  const $drawer = $("#formBuilderDrawer");
  const $form = $("#formBuilderForm");
  const $formId = $("#formId");
  const $formName = $("#formName");
  const $formTitle = $("#formBuilderTitle");
  const $formPreview = $("#formPreview");
  const $formSaveState = $("#formBuilderDrawer [data-save-state]");
  const $cancelFormEdit = $("#cancelFormEdit");
  const $closeFormBuilder = $("#closeFormBuilder");
  const $formsGrid = $("#formsLibrary");
  const $formsEmptyState = $("#formsLibraryEmptyState");
  const $emptyCta = $("#formsEmptyCta");
  const $exportForm = $("#exportSubmissionsForm");
  const $exportFormId = $("#exportFormId");
  const $exportButton = $("#exportSubmissionsBtn");
  const $confirmationEnabled = $("#confirmationEmailEnabled");
  const $confirmationDetails = $("#confirmationEmailDetails");
  const $confirmationField = $("#confirmationEmailField");
  const $confirmationFieldHint = $("#confirmationEmailFieldHint");
  const $confirmationSubject = $("#confirmationEmailSubject");
  const $confirmationTitle = $("#confirmationEmailTitle");
  const $confirmationDescription = $("#confirmationEmailDescription");
  const $confirmationFromName = $("#confirmationEmailFromName");
  const $confirmationFromEmail = $("#confirmationEmailFromEmail");
  const $confirmationPreviewButton = $("#previewConfirmationEmail");
  const $confirmationPreviewModal = $("#confirmationEmailPreviewModal");
  const $confirmationPreviewClose = $("#closeConfirmationPreview");
  const $confirmationPreviewSubject = $("#confirmationPreviewSubject");
  const $confirmationPreviewSender = $("#confirmationPreviewSender");
  const $confirmationPreviewFrame = $("#confirmationEmailPreviewFrame");
  const confirmationDefaults = {
    subject: ($form.attr("data-default-subject") || "").trim(),
    title: ($form.attr("data-default-title") || "").trim(),
    fromName: ($form.attr("data-default-from-name") || "").trim(),
    fromEmail: ($form.attr("data-default-from-email") || "").trim(),
  };
  const formSaveLabels = {
    saving: "Saving…",
    saved: "Saved",
    unsaved: "Unsaved changes",
  };
  let formIsSaving = false;
  let formIsDirty = false;
  let formIsReady = false;

  function setFormSaveState(state) {
    if (!$formSaveState.length) {
      return;
    }
    const nextState = formSaveLabels[state] ? state : "saved";
    $formSaveState.attr("data-state", nextState);
    $formSaveState
      .find("[data-save-state-text]")
      .text(formSaveLabels[nextState]);
    if (nextState === "saving") {
      $formSaveState.attr("aria-busy", "true");
    } else {
      $formSaveState.removeAttr("aria-busy");
    }
  }

  function resetFormSaveState() {
    formIsSaving = false;
    formIsDirty = false;
    formIsReady = true;
    setFormSaveState("saved");
  }

  function markFormDirty() {
    if (!formIsReady || formIsSaving) {
      return;
    }
    formIsDirty = true;
    setFormSaveState("unsaved");
  }

  function markFieldPending($field, pending) {
    if (!$field || !$field.length) {
      return;
    }
    if (pending) {
      $field.attr("data-pending-config", "true").addClass("field-pending");
    } else {
      $field.removeAttr("data-pending-config").removeClass("field-pending");
    }
  }

  function markFieldConfigured($field) {
    if (!$field || !$field.length) {
      return;
    }
    if ($field.attr("data-pending-config") === "true") {
      markFieldPending($field, false);
    }
  }

  const FIELD_TYPE_LABELS = {
    text: "Text input",
    email: "Email",
    password: "Password",
    number: "Number",
    date: "Date",
    textarea: "Textarea",
    select: "Select",
    checkbox: "Checkbox",
    radio: "Radio",
    file: "File upload",
    recaptcha: "reCAPTCHA",
    submit: "Submit button",
  };

  const FIELD_DEFAULT_LABELS = {
    text: "Text input",
    email: "Email address",
    password: "Password",
    number: "Number",
    date: "Date",
    textarea: "Message",
    select: "Select an option",
    checkbox: "Checkbox",
    radio: "Radio choice",
    file: "File upload",
    recaptcha: "reCAPTCHA verification",
    submit: "Submit",
  };

  const FORM_TEMPLATES = {
    contact: {
      label: "Contact form",
      name: "Contact form",
      fields: [
        { type: "text", label: "Full name", name: "full_name", required: true },
        {
          type: "email",
          label: "Email address",
          name: "email",
          required: true,
        },
        { type: "text", label: "Company", name: "company" },
        {
          type: "textarea",
          label: "How can we help?",
          name: "message",
          required: true,
        },
        { type: "submit", label: "Send message", name: "submit" },
      ],
    },
    newsletter: {
      label: "Newsletter signup",
      name: "Newsletter signup",
      fields: [
        { type: "text", label: "First name", name: "first_name" },
        {
          type: "email",
          label: "Email address",
          name: "email",
          required: true,
        },
        {
          type: "checkbox",
          label: "Email preferences",
          name: "topics",
          options: "Product updates, Events, Blog highlights",
        },
        { type: "submit", label: "Subscribe", name: "submit" },
      ],
    },
    rsvp: {
      label: "Event RSVP",
      name: "Event RSVP",
      fields: [
        { type: "text", label: "Full name", name: "full_name", required: true },
        {
          type: "email",
          label: "Email address",
          name: "email",
          required: true,
        },
        {
          type: "select",
          label: "Attendance",
          name: "attendance",
          required: true,
          options: "Attending, Not attending, Maybe",
        },
        { type: "number", label: "Guests", name: "guest_count" },
        { type: "textarea", label: "Dietary needs", name: "dietary_needs" },
        { type: "submit", label: "RSVP", name: "submit" },
      ],
    },
  };

  function slugifyName(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  function ensureUniqueName(base, $input) {
    const existing = new Set();
    $("#formPreview .field-name").each(function () {
      if ($input && this === $input[0]) return;
      const val = $(this).val().trim();
      if (val) {
        existing.add(val);
      }
    });
    let candidate = base || "field";
    if (!existing.has(candidate)) {
      return candidate;
    }
    let counter = 2;
    let suffixless = candidate.replace(/_\d+$/, "");
    if (!suffixless) {
      suffixless = "field";
    }
    candidate = suffixless + "_" + counter;
    while (existing.has(candidate)) {
      counter++;
      candidate = suffixless + "_" + counter;
    }
    return candidate;
  }

  function generateAutoName(label, $input) {
    const base = slugifyName(label);
    return ensureUniqueName(base || "field", $input);
  }

  function setManualNameFlag($input, manual) {
    if (!$input || !$input.length) return;
    $input.data("manual", manual === true);
    if (manual) {
      $input.attr("data-manual", "true");
    } else {
      $input.removeAttr("data-manual");
    }
  }

  function isManualName($input) {
    if (!$input || !$input.length) return false;
    return $input.data("manual") === true;
  }

  function showBuilderAlert(message, tone) {
    const $alert = $("#formBuilderAlert");
    if (!$alert.length) return;
    const typeClass =
      tone === "success" ? "form-alert--success" : "form-alert--error";
    $alert
      .removeClass("form-alert--error form-alert--success")
      .addClass(typeClass)
      .text(message)
      .show();
  }

  function hideBuilderAlert() {
    const $alert = $("#formBuilderAlert");
    if (!$alert.length) return;
    $alert.removeClass("form-alert--error form-alert--success").hide().text("");
  }

  function formatStatValue(value) {
    if (value === null || typeof value === "undefined") {
      return "—";
    }
    if (typeof value === "number" && Number.isNaN(value)) {
      return "—";
    }
    if (typeof value === "string") {
      const trimmed = value.trim();
      return trimmed === "" ? "—" : trimmed;
    }
    return value;
  }

  function applyFormStats(stats) {
    if (Object.prototype.hasOwnProperty.call(stats, "totalForms")) {
      $("#formsStatForms").text(formatStatValue(stats.totalForms));
    }
    if (Object.prototype.hasOwnProperty.call(stats, "activeForms")) {
      $("#formsStatActive").text(formatStatValue(stats.activeForms));
    }
    if (Object.prototype.hasOwnProperty.call(stats, "totalSubmissions")) {
      $("#formsStatSubmissions").text(formatStatValue(stats.totalSubmissions));
    }
    if (Object.prototype.hasOwnProperty.call(stats, "recentSubmissions")) {
      $("#formsStatRecent").text(formatStatValue(stats.recentSubmissions));
    }
    if (Object.prototype.hasOwnProperty.call(stats, "lastSubmission")) {
      $("#formsLastSubmission").text(formatStatValue(stats.lastSubmission));
    }
  }

  function bootstrapStatsFromDataset() {
    const dashboard = $(".forms-dashboard");
    if (!dashboard.length) {
      return;
    }
    applyFormStats({
      totalForms: Number(dashboard.data("total-forms")),
      activeForms: Number(dashboard.data("active-forms")),
      totalSubmissions: Number(dashboard.data("total-submissions")),
      recentSubmissions: Number(dashboard.data("recent-submissions")),
      lastSubmission: dashboard.data("last-submission"),
    });
  }

  function isValidEmail(value) {
    if (typeof value !== "string") {
      return false;
    }
    const trimmed = value.trim();
    if (trimmed === "") {
      return false;
    }
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed);
  }

  function getAvailableEmailFieldNames() {
    const names = [];
    $("#formPreview > li").each(function () {
      const $li = $(this);
      const type = String($li.data("type") || "").toLowerCase();
      if (type !== "email") {
        return;
      }
      const nameVal = ($li.find(".field-name").val() || "").trim();
      if (!nameVal) {
        return;
      }
      const labelVal = ($li.find(".field-label").val() || "").trim();
      names.push({
        name: nameVal,
        label: labelVal || nameVal,
      });
    });
    return names;
  }

  function refreshConfirmationEmailFieldOptions(selectedName) {
    const currentSelection =
      typeof selectedName === "string"
        ? selectedName
        : $confirmationField.val() || "";
    const emailFields = getAvailableEmailFieldNames();

    $confirmationField.empty();

    if (!emailFields.length) {
      $confirmationField.append(
        '<option value="">Add an email field to enable confirmation emails</option>',
      );
      $confirmationField.prop("disabled", true);
      $confirmationFieldHint.text(
        "Add an email field to your form to send confirmation emails.",
      );
      return;
    }

    $confirmationField.prop("disabled", false);
    $confirmationField.append(
      '<option value="">Select an email field</option>',
    );

    let hasSelection = false;
    emailFields.forEach(function (item) {
      const option = $("<option></option>")
        .attr("value", item.name)
        .text(item.label + " (" + item.name + ")");
      if (item.name === currentSelection) {
        option.prop("selected", true);
        hasSelection = true;
      }
      $confirmationField.append(option);
    });

    if (currentSelection && !hasSelection) {
      const missingOption = $("<option></option>")
        .attr("value", currentSelection)
        .text(currentSelection + " (saved field missing)")
        .prop("selected", true);
      $confirmationField.append(missingOption);
    }

    $confirmationFieldHint.text(
      "Choose which form field holds the visitor's email address.",
    );
  }

  function toggleConfirmationEmailDetails(visible) {
    if (!$confirmationDetails.length) {
      return;
    }
    if (visible) {
      $confirmationDetails.removeAttr("hidden");
    } else {
      $confirmationDetails.attr("hidden", true);
    }
  }

  function resetConfirmationEmailConfig() {
    $confirmationEnabled.prop("checked", false);
    $confirmationSubject.val(confirmationDefaults.subject);
    $confirmationTitle.val(confirmationDefaults.title);
    $confirmationDescription.val("");
    $confirmationFromName.val(confirmationDefaults.fromName);
    $confirmationFromEmail.val(confirmationDefaults.fromEmail);
    refreshConfirmationEmailFieldOptions("");
    toggleConfirmationEmailDetails(false);
  }

  function getConfirmationEmailConfig() {
    return {
      enabled: $confirmationEnabled.is(":checked"),
      email_field: ($confirmationField.val() || "").trim(),
      subject: ($confirmationSubject.val() || "").trim(),
      title: ($confirmationTitle.val() || "").trim(),
      description: ($confirmationDescription.val() || "").trim(),
      from_name: ($confirmationFromName.val() || "").trim(),
      from_email: ($confirmationFromEmail.val() || "").trim(),
    };
  }

  function applyConfirmationEmailConfig(config) {
    const payload = config && typeof config === "object" ? config : {};
    const selectedField =
      typeof payload.email_field === "string" ? payload.email_field : "";
    refreshConfirmationEmailFieldOptions(selectedField);

    const enabled = payload.enabled === true;
    $confirmationEnabled.prop("checked", enabled);

    $confirmationSubject.val(
      typeof payload.subject === "string" && payload.subject.trim() !== ""
        ? payload.subject
        : confirmationDefaults.subject,
    );
    $confirmationTitle.val(
      typeof payload.title === "string" && payload.title.trim() !== ""
        ? payload.title
        : confirmationDefaults.title,
    );
    $confirmationDescription.val(
      typeof payload.description === "string" ? payload.description : "",
    );
    $confirmationFromName.val(
      typeof payload.from_name === "string" && payload.from_name.trim() !== ""
        ? payload.from_name
        : confirmationDefaults.fromName,
    );
    $confirmationFromEmail.val(
      typeof payload.from_email === "string" && payload.from_email.trim() !== ""
        ? payload.from_email
        : confirmationDefaults.fromEmail,
    );

    if (selectedField) {
      $confirmationField.val(selectedField);
    }
    toggleConfirmationEmailDetails(enabled);
  }

  function setConfirmationPreviewLoading(isLoading) {
    if (!$confirmationPreviewButton.length) {
      return;
    }
    $confirmationPreviewButton.prop("disabled", isLoading);
    if (isLoading) {
      $confirmationPreviewButton.attr("aria-busy", "true");
    } else {
      $confirmationPreviewButton.removeAttr("aria-busy");
    }
  }

  function writeConfirmationPreview(html) {
    if (!$confirmationPreviewFrame.length) {
      return;
    }
    const iframe = $confirmationPreviewFrame[0];
    if ("srcdoc" in iframe) {
      iframe.setAttribute("srcdoc", html);
    } else if (iframe.contentDocument) {
      const doc = iframe.contentDocument;
      doc.open();
      doc.write(html);
      doc.close();
    }
  }

  function clearConfirmationPreview() {
    writeConfirmationPreview(
      '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;background-color:#f8fafc;"></body></html>',
    );
  }

  function formatPreviewSender(name, email) {
    const trimmedName = (name || "").trim();
    const trimmedEmail = (email || "").trim();
    if (trimmedName && trimmedEmail) {
      if (trimmedName.toLowerCase() === trimmedEmail.toLowerCase()) {
        return trimmedEmail;
      }
      return trimmedName + " <" + trimmedEmail + ">";
    }
    return trimmedName || trimmedEmail || "—";
  }

  function openConfirmationPreview(trigger) {
    if (!$confirmationPreviewModal.length) {
      return;
    }
    confirmationPreviewTrigger = trigger ? $(trigger) : null;
    $confirmationPreviewModal.addClass("active").attr("aria-hidden", "false");
    setTimeout(function () {
      $confirmationPreviewClose.trigger("focus");
    }, 0);
  }

  function closeConfirmationPreview() {
    if (
      !$confirmationPreviewModal.length ||
      !$confirmationPreviewModal.hasClass("active")
    ) {
      return;
    }
    $confirmationPreviewModal.removeClass("active").attr("aria-hidden", "true");
    $confirmationPreviewSubject.text("—");
    $confirmationPreviewSender.text("—");
    clearConfirmationPreview();
    if (confirmationPreviewTrigger && confirmationPreviewTrigger.length) {
      confirmationPreviewTrigger.trigger("focus");
    }
    confirmationPreviewTrigger = null;
  }

  function fetchPerFormStats() {
    const deferred = $.Deferred();
    $.getJSON("modules/forms/list_submissions.php", { summary: "per_form" })
      .done(function (data) {
        if (Array.isArray(data)) {
          deferred.resolve(data);
          return;
        }
        if (data && typeof data === "object") {
          const entries = Object.keys(data)
            .map(function (key) {
              const value = data[key];
              if (value && typeof value === "object") {
                const formId = Number(key);
                const payload = Object.assign({}, value);
                if (!Number.isNaN(formId)) {
                  payload.form_id = formId;
                }
                return payload;
              }
              return null;
            })
            .filter(Boolean);
          deferred.resolve(entries);
          return;
        }
        deferred.resolve([]);
      })
      .fail(function () {
        deferred.resolve([]);
      });
    return deferred.promise();
  }

  function setExportAvailability(formId) {
    if (!$exportForm.length || !$exportFormId.length || !$exportButton.length) {
      return;
    }
    if (formId) {
      $exportFormId.val(formId);
      $exportButton.prop("disabled", false).removeAttr("aria-disabled");
    } else {
      $exportFormId.val("");
      $exportButton.prop("disabled", true).attr("aria-disabled", "true");
    }
  }

  function getFormCardName($card) {
    if (!$card || !$card.length) {
      return "";
    }
    const stored = $card.data("formName");
    if (typeof stored === "string" && stored.trim() !== "") {
      return stored;
    }
    const title = $card.find(".forms-card__title").first().text();
    return title ? title.trim() : "";
  }

  function refreshSubmissionStats() {
    $.getJSON("modules/forms/list_submissions.php", function (data) {
      const submissions = Array.isArray(data) ? data : [];
      const activeFormIds = new Set();
      const now = Date.now();
      const THIRTY_DAYS = 30 * 24 * 60 * 60 * 1000;
      let latest = null;
      let recent = 0;

      submissions.forEach(function (entry) {
        if (entry && typeof entry === "object") {
          if (entry.form_id) {
            activeFormIds.add(entry.form_id);
          }
          const raw = entry.submitted_at || entry.created_at || entry.timestamp;
          let timestamp = null;
          if (typeof raw === "number") {
            timestamp = raw < 1e12 ? raw * 1000 : raw;
          } else if (typeof raw === "string") {
            const parsed = Date.parse(raw);
            if (!Number.isNaN(parsed)) {
              timestamp = parsed;
            }
          }
          if (timestamp !== null) {
            if (!latest || timestamp > latest) {
              latest = timestamp;
            }
            if (now - timestamp <= THIRTY_DAYS) {
              recent++;
            }
          }
        }
      });

      applyFormStats({
        totalForms: formsCache.length,
        activeForms: activeFormIds.size,
        totalSubmissions: submissions.length,
        recentSubmissions: recent,
        lastSubmission: latest
          ? formatSubmissionDate(latest)
          : "No submissions yet",
      });
    }).fail(function () {
      applyFormStats({
        totalForms: formsCache.length,
        activeForms: "—",
        totalSubmissions: "—",
        recentSubmissions: "—",
        lastSubmission: "Unavailable",
      });
    });
  }

  function resetSubmissionsCard(message) {
    currentFormId = null;
    currentFormName = "";
    currentSubmissions = [];
    setExportAvailability(null);
    closeSubmissionModal();
    const text = message || "Select a form to view submissions";
    const placeholder = /[.!?]$/.test(text) ? text : text + ".";
    $formsGrid.find(".forms-library-item").removeClass("is-selected");
    $("#selectedFormName").text(text);
    $("#formSubmissionsCount").text("—");
    $("#formSubmissionsList")
      .attr("data-state", "empty")
      .attr("aria-busy", "false")
      .html(
        '<div class="forms-submissions-empty">' +
          escapeHtml(placeholder) +
          "</div>",
      );
  }

  function parseSubmissionDate(value) {
    if (!value) {
      return null;
    }
    if (value instanceof Date) {
      return Number.isNaN(value.getTime()) ? null : value;
    }
    const numeric = Number(value);
    if (!Number.isNaN(numeric) && numeric > 0) {
      const timestamp = numeric < 1e12 ? numeric * 1000 : numeric;
      const date = new Date(timestamp);
      return Number.isNaN(date.getTime()) ? null : date;
    }
    if (typeof value === "string") {
      const parsed = Date.parse(value);
      if (!Number.isNaN(parsed)) {
        const date = new Date(parsed);
        return Number.isNaN(date.getTime()) ? null : date;
      }
    }
    return null;
  }

  function formatRelativeSubmissionTime(value) {
    const date = parseSubmissionDate(value);
    if (!date) {
      return null;
    }
    const now = Date.now();
    const diff = now - date.getTime();
    const absDiff = Math.abs(diff);

    if (absDiff < 45 * 1000) {
      return diff >= 0 ? "just now" : "in moments";
    }

    const makeLabel = function (amount, unit) {
      const safeAmount = Math.max(1, amount);
      if (diff >= 0) {
        return safeAmount + unit + " ago";
      }
      return "in " + safeAmount + unit;
    };

    if (absDiff < 90 * 1000) {
      return makeLabel(1, "m");
    }

    const minutes = Math.round(absDiff / 60000);
    if (minutes < 60) {
      return makeLabel(minutes, "m");
    }

    const hours = Math.round(minutes / 60);
    if (hours < 48) {
      return makeLabel(hours, "h");
    }

    const days = Math.round(hours / 24);
    if (days < 30) {
      return makeLabel(days, "d");
    }

    const months = Math.round(days / 30);
    if (months < 18) {
      return makeLabel(months, "mo");
    }

    const years = Math.max(1, Math.round(days / 365));
    return makeLabel(years, "y");
  }

  function formatSubmissionDate(value) {
    if (!value) return "Unknown";

    function formatDate(date) {
      if (Number.isNaN(date.getTime())) {
        return null;
      }
      try {
        return date.toLocaleString(undefined, {
          year: "numeric",
          month: "short",
          day: "numeric",
          hour: "numeric",
          minute: "2-digit",
        });
      } catch (err) {
        return date.toString();
      }
    }

    const date = parseSubmissionDate(value);
    if (date) {
      const formatted = formatDate(date);
      if (formatted) {
        return formatted;
      }
    }

    return String(value);
  }

  function normalizeSubmissionValue(value) {
    if (value === null || typeof value === "undefined") return "";
    if (Array.isArray(value)) {
      return value.map((v) => String(v)).join(", ");
    }
    if (typeof value === "object") {
      try {
        return JSON.stringify(value);
      } catch (err) {
        return "[object]";
      }
    }
    return String(value);
  }

  function normalizeSubmissionRecord(submission) {
    const submittedAt = formatSubmissionDate(
      submission &&
        (submission.submitted_at ||
          submission.created_at ||
          submission.timestamp),
    );
    const fields =
      submission && submission.data && typeof submission.data === "object"
        ? submission.data
        : {};
    const meta =
      submission && submission.meta && typeof submission.meta === "object"
        ? Object.assign({}, submission.meta)
        : {};
    if (submission && submission.source && !meta.source) {
      meta.source = submission.source;
    }
    if (submission && submission.id && !meta.id) {
      meta.id = submission.id;
    }
    const fieldEntries = Object.keys(fields)
      .sort((a, b) => a.localeCompare(b))
      .map(function (key) {
        return { key: key, value: normalizeSubmissionValue(fields[key]) };
      });
    const metaEntries = Object.keys(meta)
      .sort((a, b) => a.localeCompare(b))
      .map(function (key) {
        return { key: key, value: normalizeSubmissionValue(meta[key]) };
      });
    const identifierEntry = metaEntries.find(function (entry) {
      return entry.key && entry.key.toLowerCase() === "id";
    });
    const sourceEntry = metaEntries.find(function (entry) {
      return entry.key && entry.key.toLowerCase() === "source";
    });
    return {
      submittedAt: submittedAt,
      fieldEntries: fieldEntries,
      metaEntries: metaEntries,
      identifier: identifierEntry
        ? identifierEntry.value
        : submission && submission.id
          ? normalizeSubmissionValue(submission.id)
          : "",
      source: sourceEntry
        ? sourceEntry.value
        : submission && submission.source
          ? normalizeSubmissionValue(submission.source)
          : "",
      raw: submission || {},
    };
  }

  function loadFormSubmissions(formId, formName, options) {
    options = options || {};
    const shouldOpenModal = options.openModal === true;
    currentFormId = formId;
    currentFormName = formName;
    currentSubmissions = [];
    setExportAvailability(formId);
    $formsGrid.find(".forms-library-item").removeClass("is-selected");
    $formsGrid
      .find(".forms-library-item")
      .filter(function () {
        return $(this).data("id") == formId;
      })
      .addClass("is-selected");
    $("#selectedFormName").text(formName);
    $("#formSubmissionsCount").text("—");
    const $list = $("#formSubmissionsList");
    $list
      .attr("data-state", "loading")
      .attr("aria-busy", "true")
      .html(
        '<div class="forms-submissions-empty">Loading submissions...</div>',
      );
    if (shouldOpenModal) {
      openSubmissionsModal(options.trigger);
    }
    $.getJSON(
      "modules/forms/list_submissions.php",
      { form_id: formId },
      function (data) {
        const submissions = Array.isArray(data) ? data : [];
        currentSubmissions = submissions.map(normalizeSubmissionRecord);
        if (!currentSubmissions.length) {
          $("#formSubmissionsCount").text("0 submissions");
          $list
            .attr("data-state", "empty")
            .attr("aria-busy", "false")
            .html(
              '<div class="forms-submissions-empty">No submissions yet.</div>',
            );
          return;
        }
        const countLabel =
          currentSubmissions.length === 1
            ? "1 submission"
            : currentSubmissions.length + " submissions";
        $("#formSubmissionsCount").text(countLabel);
        $list.attr("data-state", "ready").attr("aria-busy", "false").empty();
        currentSubmissions.forEach(function (record, index) {
          const $card = $(
            '<article class="forms-submission-card" role="listitem"></article>',
          );
          const $header = $(
            '<div class="forms-submission-card__header"></div>',
          );
          $header.append(
            '<span class="forms-submission-card__timestamp">' +
              escapeHtml(record.submittedAt || "Unknown") +
              "</span>",
          );
          const $badges = $(
            '<div class="forms-submission-card__badges"></div>',
          );
          const identifier =
            record.identifier && String(record.identifier).trim();
          const source = record.source && String(record.source).trim();
          if (identifier) {
            $badges.append(
              '<span class="forms-submission-card__badge forms-submission-card__badge--id">ID ' +
                escapeHtml(identifier) +
                "</span>",
            );
          }
          if (source) {
            $badges.append(
              '<span class="forms-submission-card__badge">' +
                escapeHtml(source) +
                "</span>",
            );
          }
          if (!$badges.children().length) {
            $badges.append(
              '<span class="forms-submission-card__badge forms-submission-card__badge--muted">Submission ' +
                escapeHtml(String(index + 1)) +
                "</span>",
            );
          }
          $header.append($badges);
          $card.append($header);

          const $preview = $(
            '<div class="forms-submission-card__preview"></div>',
          );
          const previewFields = record.fieldEntries.slice(0, 3);
          if (previewFields.length) {
            previewFields.forEach(function (entry) {
              const $field = $(
                '<div class="forms-submission-card__field"></div>',
              );
              $field.append(
                '<span class="forms-submission-card__field-label">' +
                  escapeHtml(entry.key) +
                  "</span>",
              );
              $field.append(
                '<span class="forms-submission-card__field-value">' +
                  escapeHtml(entry.value) +
                  "</span>",
              );
              $preview.append($field);
            });
            if (record.fieldEntries.length > 3) {
              const remaining = record.fieldEntries.length - 3;
              $preview.append(
                '<span class="forms-submission-card__more">+' +
                  escapeHtml(String(remaining)) +
                  " more field" +
                  (remaining === 1 ? "" : "s") +
                  "</span>",
              );
            }
          } else {
            $preview.append(
              '<div class="forms-submission-card__empty">No submission data provided.</div>',
            );
          }
          $card.append($preview);

          const $actions = $(
            '<div class="forms-submission-card__actions"></div>',
          );
          const $button = $(
            '<button type="button" class="a11y-btn a11y-btn--ghost forms-submission-view"><i class="fa-solid fa-eye" aria-hidden="true"></i><span>View details</span></button>',
          );
          $button.on("click", function () {
            openSubmissionModal(index, this);
          });
          $actions.append($button);
          $card.append($actions);

          $list.append($card);
        });
      },
    ).fail(function () {
      $("#formSubmissionsCount").text("—");
      $list
        .attr("data-state", "error")
        .attr("aria-busy", "false")
        .html(
          '<div class="forms-submissions-empty">Unable to load submissions.</div>',
        );
    });
  }

  function buildModalDescription(record) {
    const segments = [];
    if (currentFormName) {
      segments.push("Form: " + currentFormName);
    }
    if (record && record.submittedAt && record.submittedAt !== "Unknown") {
      segments.push("Submitted " + record.submittedAt);
    }
    return segments.join(" • ");
  }

  function openSubmissionModal(index, trigger) {
    if (!currentSubmissions[index]) {
      return;
    }
    const record = currentSubmissions[index];
    lastSubmissionTrigger = trigger ? $(trigger) : null;
    const $modal = $("#submissionDetailModal");
    const $body = $("#submissionModalBody").empty();
    const identifier = record.identifier && String(record.identifier).trim();
    $("#submissionModalTitle").text(
      identifier ? "Submission ID " + identifier : "Submission details",
    );
    $("#submissionModalDescription").text(buildModalDescription(record));

    if (record.fieldEntries.length) {
      const $fieldsSection = $(
        '<section class="forms-submission-modal__section"></section>',
      );
      $fieldsSection.append(
        '<h3 class="forms-submission-modal__section-title">Submitted fields</h3>',
      );
      const $fieldList = $('<dl class="forms-submission-modal__details"></dl>');
      record.fieldEntries.forEach(function (entry) {
        const $item = $('<div class="forms-submission-modal__detail"></div>');
        $item.append("<dt>" + escapeHtml(entry.key) + "</dt>");
        $item.append("<dd>" + escapeHtml(entry.value) + "</dd>");
        $fieldList.append($item);
      });
      $fieldsSection.append($fieldList);
      $body.append($fieldsSection);
    }

    if (record.metaEntries.length) {
      const $metaSection = $(
        '<section class="forms-submission-modal__section"></section>',
      );
      $metaSection.append(
        '<h3 class="forms-submission-modal__section-title">Metadata</h3>',
      );
      const $metaList = $('<dl class="forms-submission-modal__details"></dl>');
      record.metaEntries.forEach(function (entry) {
        const $item = $('<div class="forms-submission-modal__detail"></div>');
        $item.append("<dt>" + escapeHtml(entry.key) + "</dt>");
        $item.append("<dd>" + escapeHtml(entry.value) + "</dd>");
        $metaList.append($item);
      });
      $metaSection.append($metaList);
      $body.append($metaSection);
    }

    if (!$body.children().length) {
      $body.append(
        '<div class="forms-submission-modal__empty">No additional information available for this submission.</div>',
      );
    }

    $modal.addClass("active").attr("aria-hidden", "false");
    setTimeout(function () {
      $("#submissionModalClose").trigger("focus");
    }, 0);
  }

  function openSubmissionsModal(trigger) {
    const $modal = $("#formSubmissionsModal");
    if (!$modal.length) {
      return;
    }
    lastSubmissionsTrigger = trigger ? $(trigger) : null;
    $modal.addClass("active").attr("aria-hidden", "false");
    setTimeout(function () {
      $("#closeSubmissionsModal").trigger("focus");
    }, 0);
  }

  function closeSubmissionsModal() {
    const $modal = $("#formSubmissionsModal");
    if (!$modal.hasClass("active")) {
      return;
    }
    $modal.removeClass("active").attr("aria-hidden", "true");
    if (lastSubmissionsTrigger && lastSubmissionsTrigger.length) {
      lastSubmissionsTrigger.trigger("focus");
    }
    lastSubmissionsTrigger = null;
  }

  function closeSubmissionModal() {
    const $modal = $("#submissionDetailModal");
    if (!$modal.hasClass("active")) {
      return;
    }
    $modal.removeClass("active").attr("aria-hidden", "true");
    $("#submissionModalTitle").text("Submission details");
    $("#submissionModalDescription").text("");
    $("#submissionModalBody")
      .empty()
      .append(
        '<div class="forms-submission-modal__empty">Select a form submission to view the collected data.</div>',
      );
    if (lastSubmissionTrigger && lastSubmissionTrigger.length) {
      lastSubmissionTrigger.trigger("focus");
    }
    lastSubmissionTrigger = null;
  }

  function updatePreview($li) {
    const type = $li.data("type");
    const labelValue = ($li.find(".field-label").val() || "").trim();
    const nameValue = ($li.find(".field-name").val() || "").trim();
    const hasRequiredToggle = $li.find(".field-required").length > 0;
    const required =
      hasRequiredToggle && $li.find(".field-required").is(":checked")
        ? " required"
        : "";
    const options = ($li.find(".field-options-input").val() || "").trim();
    const labelHtml = labelValue
      ? escapeHtml(labelValue)
      : '<span class="field-placeholder">Label</span>';
    const nameAttr = nameValue ? ' name="' + escapeHtml(nameValue) + '"' : "";
    let html = "";
    switch (type) {
      case "textarea":
        html =
          '<div class="form-group"><label>' +
          labelHtml +
          "</label><textarea" +
          nameAttr +
          required +
          "></textarea></div>";
        break;
      case "select":
        const opts = options
          .split(",")
          .map((o) => o.trim())
          .filter(Boolean);
        html =
          '<div class="form-group"><label>' +
          labelHtml +
          "</label><select" +
          nameAttr +
          required +
          ">" +
          opts.map((o) => "<option>" + escapeHtml(o) + "</option>").join("") +
          "</select></div>";
        break;
      case "checkbox":
      case "radio":
        const optList = options
          .split(",")
          .map((o) => o.trim())
          .filter(Boolean);
        if (optList.length) {
          html =
            '<div class="form-group"><label>' + labelHtml + "</label><div>";
          optList.forEach((o) => {
            const optionHtml = escapeHtml(o);
            const valueAttr = ' value="' + optionHtml + '"';
            html +=
              '<label><input type="' +
              type +
              '"' +
              nameAttr +
              valueAttr +
              "> " +
              optionHtml +
              "</label> ";
          });
          html += "</div></div>";
        } else {
          html =
            '<div class="form-group"><label><input type="' +
            type +
            '"' +
            nameAttr +
            required +
            "> " +
            labelHtml +
            "</label></div>";
        }
        break;
      case "recaptcha":
        html =
          '<div class="form-group"><label>' +
          labelHtml +
          '</label><div class="field-recaptcha-placeholder"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> reCAPTCHA widget will render here.</div></div>';
        break;
      case "submit":
        html =
          '<div class="form-group"><button type="submit">' +
          (labelValue ? escapeHtml(labelValue) : "Submit") +
          "</button></div>";
        break;
      default:
        const inputType = type === "date" ? "date" : type;
        html =
          '<div class="form-group"><label>' +
          labelHtml +
          '</label><input type="' +
          inputType +
          '"' +
          nameAttr +
          required +
          "></div>";
        break;
    }
    $li.find(".field-preview").html(html);
  }

  function selectField($li) {
    if (currentField && currentField[0] === ($li && $li[0])) return;
    if (currentField) {
      currentField.append($("#fieldSettings .field-body").hide());
      currentField.removeClass("selected");
    }
    currentField = $li;
    $("#fieldSettings").empty();
    if ($li) {
      currentField.addClass("selected");
      $("#fieldSettings").append(currentField.find(".field-body").show());
    } else {
      $("#fieldSettings").html(
        '<div class="field-settings-empty"><h4>Field settings</h4><p>Select a field in the preview to customize labels, names, and validation.</p></div>',
      );
    }
  }

  function focusFormBuilder() {
    if (!$formName.length) {
      return;
    }
    try {
      $formName[0].focus({ preventScroll: true });
    } catch (err) {
      $formName[0].focus();
    }
  }

  function clearFormBuilder() {
    if ($form.length && $form[0]) {
      $form[0].reset();
    }
    $formId.val("");
    $formPreview.empty();
    selectField(null);
    hideBuilderAlert();
    resetConfirmationEmailConfig();
  }

  function canReplaceBuilder() {
    if (!$("#formPreview > li").length) {
      return $.Deferred().resolve(true).promise();
    }
    if (typeof confirmModal === "function") {
      return confirmModal("Replace the current fields with a template?");
    }
    const deferred = $.Deferred();
    deferred.resolve(
      window.confirm("Replace the current fields with a template?"),
    );
    return deferred.promise();
  }

  function applyTemplate(templateKey) {
    const template = FORM_TEMPLATES[templateKey];
    if (!template) {
      return;
    }
    canReplaceBuilder().then(function (ok) {
      if (!ok) {
        return;
      }
      $formPreview.empty();
      selectField(null);
      (template.fields || []).forEach(function (field) {
        addField(field.type, field, { suppressSelect: true });
      });
      if (!$formName.val()) {
        $formName.val(template.name);
      }
      const firstField = $("#formPreview > li").first();
      if (firstField.length) {
        selectField(firstField);
      }
      markFormDirty();
    });
  }

  function openFormBuilder(title) {
    if (typeof title === "string" && title) {
      $formTitle.text(title);
    }
    hideBuilderAlert();
    $drawer.attr("hidden", false).addClass("is-visible");
    resetFormSaveState();
    setTimeout(focusFormBuilder, 80);
  }

  function closeFormBuilder() {
    $drawer.attr("hidden", true).removeClass("is-visible");
    formIsReady = false;
  }

  function dismissFormBuilder() {
    closeFormBuilder();
    clearFormBuilder();
  }

  function loadForms() {
    $.getJSON("modules/forms/list_forms.php", function (data) {
      const forms = Array.isArray(data) ? data : [];
      formsCache = forms;
      applyFormStats({ totalForms: forms.length });
      if ($formsGrid.length) {
        $formsGrid.attr("aria-busy", "true").empty().removeAttr("hidden");
      }
      if ($formsEmptyState.length) {
        $formsEmptyState.attr("hidden", true);
      }

      if (!forms.length) {
        if ($formsGrid.length) {
          $formsGrid.attr("aria-busy", "false").attr("hidden", true).empty();
        }
        if ($formsEmptyState.length) {
          $formsEmptyState.removeAttr("hidden");
        }
        refreshSubmissionStats();
        resetSubmissionsCard("Create a form to start collecting submissions");
        return;
      }

      fetchPerFormStats().done(function (perFormStats) {
        const statsIndex = {};
        perFormStats.forEach(function (entry) {
          if (!entry || typeof entry !== "object") {
            return;
          }
          const id = Number(entry.form_id);
          if (Number.isNaN(id) || id <= 0) {
            return;
          }
          const count = Number(entry.submission_count);
          const submissionCount = Number.isNaN(count) || count < 0 ? 0 : count;
          statsIndex[id] = {
            submissionCount: submissionCount,
            lastSubmission: entry.last_submission || null,
          };
        });

        forms.forEach(function (f) {
          const fieldCount = Array.isArray(f.fields) ? f.fields.length : 0;
          const fieldLabel =
            fieldCount === 1 ? "1 field" : fieldCount + " fields";
          const formName =
            typeof f.name === "string" && f.name.trim() !== ""
              ? f.name
              : "Untitled form";
          const $card = $(
            '<article class="a11y-page-card forms-card forms-library-item" role="listitem" tabindex="0"></article>',
          );
          $card.attr("data-id", f.id);
          $card.data("formName", formName);

          const $header = $('<div class="forms-card__header"></div>');
          const $heading = $('<div class="forms-card__heading"></div>');
          const $title = $('<h4 class="forms-card__title"></h4>').text(
            formName,
          );
          const $badges = $('<div class="forms-card__badges"></div>');

          const $fieldBadge = $(
            '<span class="forms-card__badge forms-card__badge--fields" aria-label="' +
              escapeHtml(fieldLabel) +
              '"></span>',
          );
          $fieldBadge.append(
            '<i class="fas fa-layer-group" aria-hidden="true"></i>',
          );
          $fieldBadge.append("<span>" + escapeHtml(fieldLabel) + "</span>");
          $badges.append($fieldBadge);

          const stats = statsIndex[Number(f.id)] || {
            submissionCount: 0,
            lastSubmission: null,
          };
          const submissionCount = stats.submissionCount;
          const submissionLabel =
            submissionCount === 1
              ? "1 submission"
              : submissionCount + " submissions";
          const $submissionsBadge = $(
            '<span class="forms-card__badge forms-card__badge--submissions" aria-label="' +
              escapeHtml(submissionLabel) +
              '"></span>',
          );
          $submissionsBadge.append(
            '<i class="fa-solid fa-inbox" aria-hidden="true"></i>',
          );
          $submissionsBadge.append(
            "<span>" + escapeHtml(submissionLabel) + "</span>",
          );
          if (submissionCount === 0) {
            $submissionsBadge.addClass("forms-card__badge--muted");
          }
          $badges.append($submissionsBadge);

          const lastRaw = stats.lastSubmission;
          let lastBadgeText = "No submissions yet";
          let lastBadgeTitle = "No submissions yet";
          if (lastRaw) {
            const relative = formatRelativeSubmissionTime(lastRaw);
            const absolute = formatSubmissionDate(lastRaw);
            if (relative) {
              lastBadgeText = "Last submission " + relative;
              lastBadgeTitle = "Last submission " + (absolute || relative);
            } else {
              lastBadgeText = "Last submission " + String(lastRaw);
              lastBadgeTitle = lastBadgeText;
            }
          }
          const $lastBadge = $(
            '<span class="forms-card__badge forms-card__badge--last-submission" aria-label="' +
              escapeHtml(lastBadgeTitle) +
              '"></span>',
          );
          $lastBadge.append(
            '<i class="fa-solid fa-clock" aria-hidden="true"></i>',
          );
          $lastBadge.append("<span>" + escapeHtml(lastBadgeText) + "</span>");
          $lastBadge.attr("title", lastBadgeTitle);
          if (!lastRaw) {
            $lastBadge.addClass("forms-card__badge--muted");
          }
          $badges.append($lastBadge);

          $heading.append($title).append($badges);

          const $actions = $(
            '<div class="forms-card__actions" role="group" aria-label="Form actions"></div>',
          );
          const $viewBtn = $(
            '<button type="button" class="a11y-btn a11y-btn--ghost forms-card__action" data-action="view-submissions"></button>',
          );
          $viewBtn.append('<i class="fa-solid fa-eye" aria-hidden="true"></i>');
          $viewBtn.append("<span>View submissions</span>");
          const $editBtn = $(
            '<button type="button" class="a11y-btn a11y-btn--secondary forms-card__action" data-action="edit-form"></button>',
          );
          $editBtn.append(
            '<i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>',
          );
          $editBtn.append("<span>Edit</span>");
          const $deleteBtn = $(
            '<button type="button" class="a11y-btn a11y-btn--ghost forms-card__action forms-card__action--danger" data-action="delete-form"></button>',
          );
          $deleteBtn.append(
            '<i class="fa-solid fa-trash" aria-hidden="true"></i>',
          );
          $deleteBtn.append("<span>Delete</span>");
          $actions.append($viewBtn, $editBtn, $deleteBtn);

          $header.append($heading).append($actions);
          $card.append($header);

          if (f.id) {
            const $meta = $('<div class="forms-card__meta"></div>');
            const $metaItem = $('<span class="forms-card__meta-item"></span>');
            $metaItem.append(
              '<i class="fas fa-hashtag" aria-hidden="true"></i>',
            );
            $metaItem.append(
              "<span>Form ID " + escapeHtml(String(f.id)) + "</span>",
            );
            $meta.append($metaItem);
            $card.append($meta);
          }

          if ($formsGrid.length) {
            $formsGrid.append($card);
          }
        });

        refreshSubmissionStats();

        if ($formsGrid.length) {
          $formsGrid.attr("aria-busy", "false");
        }

        const $cards = $formsGrid.length
          ? $formsGrid.find(".forms-library-item")
          : $();
        if (currentFormId) {
          const $activeCard = $cards
            .filter(function () {
              return $(this).data("id") == currentFormId;
            })
            .first();
          if ($activeCard.length) {
            loadFormSubmissions(
              $activeCard.data("id"),
              getFormCardName($activeCard),
            );
          } else {
            resetSubmissionsCard();
            const $fallback = $cards.first();
            if ($fallback.length) {
              loadFormSubmissions(
                $fallback.data("id"),
                getFormCardName($fallback),
              );
            }
          }
        } else {
          const $firstCard = $cards.first();
          if ($firstCard.length) {
            loadFormSubmissions(
              $firstCard.data("id"),
              getFormCardName($firstCard),
            );
          }
        }
      });
    });
  }

  function addField(type, field, options) {
    field = field || {};
    options = options || {};
    const suppressSelect = options.suppressSelect === true;
    const isNew = options.isNew === true;
    const typeLabel =
      FIELD_TYPE_LABELS[type] ||
      (type ? type.charAt(0).toUpperCase() + type.slice(1) : "Field");
    const $li = $('<li class="field-item" data-type="' + type + '"></li>');
    const $bar = $('<div class="field-bar"></div>');
    $bar.append(
      '<span class="drag-handle action-icon-button has-tooltip" role="button" tabindex="0" aria-label="Move field" data-tooltip="Drag to reorder"><i class="fa-solid fa-up-down-left-right action-icon" aria-hidden="true"></i></span>',
    );
    $bar.append(
      '<span class="field-type">' + escapeHtml(typeLabel) + "</span>",
    );
    $bar.append(
      '<button type="button" class="btn btn-danger btn-sm removeField" aria-label="Remove ' +
        escapeHtml(typeLabel) +
        '"><i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i><span class="btn-label">Remove</span></button>',
    );
    const preview = $('<div class="field-preview"></div>');
    const body = $('<div class="field-body"></div>');

    const labelGroup = $('<div class="form-group"></div>');
    labelGroup.append('<label class="form-label">Label</label>');
    const labelInput = $(
      '<input type="text" class="form-input field-label" placeholder="Field label">',
    );
    labelGroup.append(labelInput);
    body.append(labelGroup);

    const nameGroup = $('<div class="form-group"></div>');
    nameGroup.append('<label class="form-label">Name</label>');
    const nameInput = $(
      '<input type="text" class="form-input field-name" placeholder="e.g. email_address">',
    );
    nameGroup.append(nameInput);
    nameGroup.append(
      '<p class="field-help">Used in submissions and embeds. Letters, numbers, underscores, and hyphens work best.</p>',
    );
    body.append(nameGroup);

    let requiredInput = null;
    if (type !== "submit" && type !== "recaptcha") {
      const requiredGroup = $('<div class="form-group"></div>');
      requiredGroup.append(
        '<label><input type="checkbox" class="field-required"> Required</label>',
      );
      body.append(requiredGroup);
      requiredInput = requiredGroup.find(".field-required");
    }

    let optionsInput = null;
    if (["select", "radio", "checkbox"].includes(type)) {
      const optionsGroup = $('<div class="form-group field-options"></div>');
      optionsGroup.append('<label class="form-label">Options</label>');
      optionsInput = $(
        '<input type="text" class="form-input field-options-input" placeholder="Option 1, Option 2">',
      );
      optionsGroup.append(optionsInput);
      optionsGroup.append(
        '<p class="field-help">Separate each choice with a comma.</p>',
      );
      const suggestions = [
        { label: "Yes / No", value: "Yes, No" },
        {
          label: "Agree / Disagree",
          value: "Strongly agree, Agree, Neutral, Disagree, Strongly disagree",
        },
        { label: "Sizes", value: "Small, Medium, Large, Extra large" },
        { label: "Ratings", value: "1, 2, 3, 4, 5" },
      ];
      const $suggestions = $(
        '<div class="field-options-suggestions" role="list"></div>',
      );
      suggestions.forEach(function (suggestion) {
        const $chip = $(
          '<button type="button" class="field-option-chip" role="listitem"></button>',
        );
        $chip.text(suggestion.label);
        $chip.on("click", function () {
          optionsInput.val(suggestion.value);
          updatePreview($li);
          markFormDirty();
          markFieldConfigured($li);
        });
        $suggestions.append($chip);
      });
      optionsGroup.append($suggestions);
      body.append(optionsGroup);
    }

    $li.append($bar).append(preview).append(body.hide());

    const initialLabel =
      typeof field.label === "string" && field.label !== ""
        ? field.label
        : isNew
          ? FIELD_DEFAULT_LABELS[type] || ""
          : field.label || "";
    if (initialLabel) {
      labelInput.val(initialLabel);
    }

    if (type === "recaptcha") {
      const recaptchaName = field.name ? String(field.name) : "recaptcha_token";
      nameInput.val(recaptchaName);
      nameInput.prop("readonly", true).attr("aria-readonly", "true");
      setManualNameFlag(nameInput, true);
      nameGroup.append(
        '<p class="field-help">Name is fixed for reCAPTCHA validation tokens.</p>',
      );
    } else if (field.name) {
      nameInput.val(String(field.name));
      setManualNameFlag(nameInput, true);
    } else {
      const autoName = generateAutoName(
        labelInput.val() || typeLabel,
        nameInput,
      );
      nameInput.val(autoName);
      setManualNameFlag(nameInput, false);
    }

    if (requiredInput && field.required) {
      requiredInput.prop("checked", true);
    }
    if (optionsInput) {
      if (Array.isArray(field.options)) {
        optionsInput.val(field.options.join(", "));
      } else if (typeof field.options === "string") {
        optionsInput.val(field.options);
      }
    }

    labelInput
      .on("input", function () {
        if (!isManualName(nameInput)) {
          const autoName = generateAutoName(
            $(this).val() || typeLabel,
            nameInput,
          );
          nameInput.val(autoName);
          setManualNameFlag(nameInput, false);
        }
        updatePreview($li);
        refreshConfirmationEmailFieldOptions($confirmationField.val());
        markFieldConfigured($li);
      })
      .on("blur", function () {
        markFieldConfigured($li);
      });

    labelInput.on("blur", function () {
      if (!isManualName(nameInput)) {
        const autoName = generateAutoName(
          $(this).val() || typeLabel,
          nameInput,
        );
        nameInput.val(autoName);
        setManualNameFlag(nameInput, false);
      }
      updatePreview($li);
      refreshConfirmationEmailFieldOptions($confirmationField.val());
    });

    nameInput.on("input", function () {
      const value = $(this).val();
      if (value.trim() === "") {
        setManualNameFlag(nameInput, false);
      } else {
        setManualNameFlag(nameInput, true);
      }
      updatePreview($li);
      refreshConfirmationEmailFieldOptions($confirmationField.val());
    });

    nameInput.on("blur", function () {
      if ($(this).val().trim() === "") {
        const autoName = generateAutoName(
          labelInput.val() || typeLabel,
          nameInput,
        );
        $(this).val(autoName);
        setManualNameFlag(nameInput, false);
        updatePreview($li);
        refreshConfirmationEmailFieldOptions($confirmationField.val());
      }
      markFieldConfigured($li);
    });

    body.on("input change", "input, textarea", function () {
      updatePreview($li);
      markFieldConfigured($li);
    });

    $li.on("click", function () {
      selectField($li);
    });

    updatePreview($li);
    $formPreview.append($li);
    if (!suppressSelect) {
      selectField($li);
    }
    if (isNew) {
      markFormDirty();
      markFieldPending($li, true);
    } else {
      markFieldPending($li, false);
    }
    hideBuilderAlert();
    refreshConfirmationEmailFieldOptions($confirmationField.val());
  }

  $("#fieldPalette").on("click", ".palette-item", function (e) {
    e.preventDefault();
    const type = $(this).data("type");
    if (type) {
      addField(type, {}, { isNew: true });
    }
  });

  $("#fieldPalette").on("keydown", ".palette-item", function (e) {
    if (e.key === "Enter" || e.key === " " || e.key === "Spacebar") {
      e.preventDefault();
      const type = $(this).data("type");
      if (type) {
        addField(type, {}, { isNew: true });
      }
    }
  });

  $(".forms-template-btn").on("click", function () {
    const templateKey = $(this).data("template");
    if (templateKey) {
      applyTemplate(templateKey);
    }
  });

  $(".palette-item").draggable({ helper: "clone", revert: "invalid" });

  $formPreview
    .sortable({
      placeholder: "ui-sortable-placeholder",
      stop: function () {
        refreshConfirmationEmailFieldOptions($confirmationField.val());
      },
    })
    .droppable({
      accept: ".palette-item",
      drop: function (e, ui) {
        const type = ui.draggable.data("type");
        if (type) {
          addField(type, {}, { isNew: true });
        }
      },
    });

  $confirmationEnabled.on("change", function () {
    const enabled = $(this).is(":checked");
    if (enabled) {
      if (($confirmationSubject.val() || "").trim() === "") {
        $confirmationSubject.val(confirmationDefaults.subject);
      }
      if (($confirmationTitle.val() || "").trim() === "") {
        $confirmationTitle.val(confirmationDefaults.title);
      }
      if (($confirmationFromName.val() || "").trim() === "") {
        $confirmationFromName.val(confirmationDefaults.fromName);
      }
      if (($confirmationFromEmail.val() || "").trim() === "") {
        $confirmationFromEmail.val(confirmationDefaults.fromEmail);
      }
      refreshConfirmationEmailFieldOptions($confirmationField.val());
    }
    toggleConfirmationEmailDetails(enabled);
  });

  $("#newFormBtn").on("click", function () {
    clearFormBuilder();
    openFormBuilder("Add form");
  });

  if ($emptyCta.length) {
    $emptyCta.on("click", function () {
      $("#newFormBtn").trigger("click");
    });
  }

  $cancelFormEdit.on("click", function () {
    dismissFormBuilder();
  });

  $closeFormBuilder.on("click", function () {
    if (!$drawer.hasClass("is-visible")) {
      return;
    }
    dismissFormBuilder();
  });

  $drawer.on("click", function (event) {
    if (event.target === this) {
      dismissFormBuilder();
    }
  });

  $(document)
    .off("keydown.formsBuilder")
    .on("keydown.formsBuilder", function (event) {
      if (event.key === "Escape" && $drawer.hasClass("is-visible")) {
        event.preventDefault();
        dismissFormBuilder();
      }
    });

  $formsGrid.on("click", '[data-action="view-submissions"]', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const $card = $(this).closest(".forms-library-item");
    const id = $card.data("id");
    if (id) {
      loadFormSubmissions(id, getFormCardName($card), {
        openModal: true,
        trigger: this,
      });
    }
  });

  $formsGrid.on("click", ".forms-library-item", function (event) {
    if ($(event.target).closest("button").length) {
      return;
    }
    const $card = $(this);
    const id = $card.data("id");
    if (id) {
      loadFormSubmissions(id, getFormCardName($card), {
        openModal: true,
        trigger: this,
      });
    }
  });

  $formsGrid.on("keydown", ".forms-library-item", function (event) {
    if (
      event.key === "Enter" ||
      event.key === " " ||
      event.key === "Spacebar"
    ) {
      event.preventDefault();
      $(this).trigger("click");
    }
  });

  $formsGrid.on("click", '[data-action="edit-form"]', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const $card = $(this).closest(".forms-library-item");
    const id = $card.data("id");
    if (!id) {
      return;
    }
    $.getJSON("modules/forms/list_forms.php", function (forms) {
      const list = Array.isArray(forms) ? forms : [];
      formsCache = list;
      const formData = list.find(function (item) {
        return item && item.id == id;
      });
      if (!formData) {
        return;
      }
      clearFormBuilder();
      $formId.val(formData.id);
      $formName.val(formData.name);
      (formData.fields || []).forEach(function (fd) {
        addField(fd.type, fd, { suppressSelect: true });
      });
      applyConfirmationEmailConfig(formData.confirmation_email || {});
      const firstField = $("#formPreview > li").first();
      if (firstField.length) {
        selectField(firstField);
      } else {
        selectField(null);
      }
      openFormBuilder("Edit form");
    });
  });

  $formsGrid.on("click", '[data-action="delete-form"]', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const $card = $(this).closest(".forms-library-item");
    const id = $card.data("id");
    if (!id) {
      return;
    }
    confirmModal("Delete this form?").then(function (ok) {
      if (ok) {
        $.post("modules/forms/delete_form.php", { id: id }, loadForms);
      }
    });
  });

  $form.on("input change", "input, textarea, select", function () {
    markFormDirty();
  });

  $("#formBuilderForm").on("submit", function (e) {
    e.preventDefault();
    hideBuilderAlert();
    selectField(null);

    const formName = ($("#formName").val() || "").trim();
    if (!formName) {
      showBuilderAlert("Give your form a name before saving.");
      $("#formName").focus();
      return;
    }

    const confirmationConfig = getConfirmationEmailConfig();
    const $items = $("#formPreview > li");
    if (!$items.length) {
      showBuilderAlert("Add at least one field before saving.");
      return;
    }

    let missingLabels = 0;
    let missingNames = 0;
    let pendingSetup = 0;
    const nameCounts = {};
    $items.removeClass("field-error");

    $items.each(function () {
      const $li = $(this);
      const labelVal = ($li.find(".field-label").val() || "").trim();
      const nameVal = ($li.find(".field-name").val() || "").trim();
      if ($li.attr("data-pending-config") === "true") {
        pendingSetup++;
        $li.addClass("field-error");
      }
      if (!labelVal) {
        missingLabels++;
        $li.addClass("field-error");
      }
      if (!nameVal) {
        missingNames++;
        $li.addClass("field-error");
      } else {
        nameCounts[nameVal] = (nameCounts[nameVal] || 0) + 1;
      }
    });

    const duplicateNames = Object.keys(nameCounts).filter(function (name) {
      return nameCounts[name] > 1;
    });

    if (duplicateNames.length) {
      $items.each(function () {
        const $li = $(this);
        const nameVal = ($li.find(".field-name").val() || "").trim();
        if (duplicateNames.includes(nameVal)) {
          $li.addClass("field-error");
        }
      });
    }

    const errors = [];
    if (pendingSetup) {
      errors.push(
        pendingSetup === 1
          ? "Finish configuring the new field you just added before saving."
          : "Finish configuring the new fields you just added before saving.",
      );
    }
    if (missingLabels) {
      errors.push(
        missingLabels === 1
          ? "One field is missing a label."
          : missingLabels + " fields are missing labels.",
      );
    }
    if (missingNames) {
      errors.push(
        missingNames === 1
          ? "One field needs a field name."
          : missingNames + " fields need field names.",
      );
    }
    if (duplicateNames.length) {
      errors.push(
        "Field names must be unique. Duplicate names: " +
          duplicateNames.join(", ") +
          ".",
      );
    }

    if (confirmationConfig.enabled) {
      const availableNames = getAvailableEmailFieldNames().map(function (item) {
        return item.name;
      });
      if (!confirmationConfig.email_field) {
        errors.push(
          "Select which email field should receive the confirmation email.",
        );
      } else if (!availableNames.includes(confirmationConfig.email_field)) {
        errors.push(
          "Confirmation email must point to an email field in the form.",
        );
      }
      if (!confirmationConfig.from_email) {
        errors.push("Provide a From email address for the confirmation email.");
      } else if (!isValidEmail(confirmationConfig.from_email)) {
        errors.push(
          "Enter a valid From email address for the confirmation email.",
        );
      }
      if (!confirmationConfig.subject) {
        errors.push("Add a subject line for the confirmation email.");
      }
    }

    if (errors.length) {
      showBuilderAlert(errors.join(" "));
      const $firstError = $("#formPreview > li.field-error").first();
      if ($firstError.length) {
        selectField($firstError);
        try {
          $firstError[0].scrollIntoView({
            behavior: "smooth",
            block: "center",
          });
        } catch (err) {
          // ignore scroll errors
        }
      }
      return;
    }

    const fields = [];
    $items.each(function () {
      const $li = $(this);
      const type = $li.data("type");
      const labelVal = ($li.find(".field-label").val() || "").trim();
      const nameVal = ($li.find(".field-name").val() || "").trim();
      const fieldData = {
        type: type,
        label: labelVal,
        name: nameVal,
      };
      if (type !== "submit") {
        fieldData.required = $li.find(".field-required").is(":checked");
      }
      if (["select", "radio", "checkbox"].includes(type)) {
        const rawOptions = ($li.find(".field-options-input").val() || "")
          .split(",")
          .map(function (opt) {
            return opt.trim();
          })
          .filter(Boolean);
        fieldData.options = rawOptions.join(", ");
      }
      fields.push(fieldData);
    });

    const payload = {
      id: $formId.val(),
      name: formName,
      fields: JSON.stringify(fields),
      confirmation_email: JSON.stringify(confirmationConfig),
    };

    formIsSaving = true;
    setFormSaveState("saving");

    $.post("modules/forms/save_form.php", payload)
      .done(function () {
        formIsDirty = false;
        setFormSaveState("saved");
        dismissFormBuilder();
        loadForms();
      })
      .fail(function () {
        showBuilderAlert("Unable to save the form. Please try again.");
        formIsDirty = true;
        setFormSaveState("unsaved");
      })
      .always(function () {
        formIsSaving = false;
      });
  });

  $formPreview.on("click", ".removeField", function (e) {
    e.stopPropagation();
    const li = $(this).closest("li");
    if (currentField && currentField[0] === li[0]) selectField(null);
    li.remove();
    markFormDirty();
    if (!$("#formPreview > li").length) {
      selectField(null);
    }
    hideBuilderAlert();
    refreshConfirmationEmailFieldOptions($confirmationField.val());
  });

  $("#fieldSettings").on(
    "input change",
    ".field-body input, .field-body textarea",
    function () {
      if (currentField) updatePreview(currentField);
    },
  );

  $("#submissionModalClose").on("click", function () {
    closeSubmissionModal();
  });

  $("#submissionDetailModal").on("click", function (event) {
    if (event.target === this) {
      closeSubmissionModal();
    }
  });

  $(document).on("keydown.formsSubmissionModal", function (event) {
    if (event.key === "Escape") {
      closeSubmissionModal();
    }
  });

  $("#closeSubmissionsModal").on("click", function () {
    closeSubmissionsModal();
  });

  $("#formSubmissionsModal").on("click", function (event) {
    if (event.target === this) {
      closeSubmissionsModal();
    }
  });

  $(document).on("keydown.formsSubmissionsModal", function (event) {
    if (event.key === "Escape") {
      closeSubmissionsModal();
    }
  });

  if ($confirmationPreviewButton.length) {
    $confirmationPreviewButton.on("click", function () {
      const trigger = this;
      const config = getConfirmationEmailConfig();
      setConfirmationPreviewLoading(true);
      $.ajax({
        url: "modules/forms/preview_confirmation_email.php",
        method: "POST",
        dataType: "json",
        data: {
          subject: config.subject,
          title: config.title,
          description: config.description,
          from_name: config.from_name,
          from_email: config.from_email,
        },
      })
        .done(function (response) {
          if (
            !response ||
            response.success !== true ||
            typeof response.html !== "string"
          ) {
            const errorMessage =
              response && response.error
                ? response.error
                : "Unable to generate the confirmation email preview.";
            showBuilderAlert(errorMessage);
            return;
          }
          hideBuilderAlert();
          $confirmationPreviewSubject.text(response.subject || "—");
          $confirmationPreviewSender.text(
            formatPreviewSender(response.from_name, response.from_email),
          );
          writeConfirmationPreview(response.html);
          openConfirmationPreview(trigger);
        })
        .fail(function (jqXHR) {
          let message = "Unable to generate the confirmation email preview.";
          if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) {
            message = jqXHR.responseJSON.error;
          }
          showBuilderAlert(message);
        })
        .always(function () {
          setConfirmationPreviewLoading(false);
        });
    });
  }

  $confirmationPreviewClose.on("click", function () {
    closeConfirmationPreview();
  });

  $confirmationPreviewModal.on("click", function (event) {
    if (event.target === this) {
      closeConfirmationPreview();
    }
  });

  $(document).on("keydown.formsConfirmationPreview", function (event) {
    if (event.key === "Escape") {
      closeConfirmationPreview();
    }
  });

  clearConfirmationPreview();

  resetConfirmationEmailConfig();
  bootstrapStatsFromDataset();
  resetSubmissionsCard();
  loadForms();
});
