// File: media.js
$(function () {
  let currentFolder = null;
  let currentImages = [];
  let currentPage = 1;
  let currentOffset = 0;
  let totalImagesCount = 0;
  let totalPages = 1;
  let cropper = null;
  let flipX = 1;
  let flipY = 1;
  let currentFolderMeta = "";
  let sortBy = "custom";
  let sortOrder = "asc";
  let viewType = "medium";
  let itemsPerPage = 12;
  let draggedMediaId = null;
  let draggedMediaFolder = null;
  const usageCache = {};

  const MAX_IMAGE_SIZE_BYTES = 15 * 1024 * 1024;
  const MAX_VIDEO_SIZE_BYTES = 30 * 1024 * 1024;
  const IMAGE_EXTENSIONS = [
    "jpg",
    "jpeg",
    "png",
    "gif",
    "webp",
    "bmp",
    "svg",
    "heic",
    "heif",
  ];
  const VIDEO_EXTENSIONS = [
    "mp4",
    "mov",
    "avi",
    "mkv",
    "webm",
    "wmv",
    "flv",
    "m4v",
  ];

  const reservedFolderNames = [
    ".",
    "..",
    "con",
    "prn",
    "aux",
    "nul",
    "com1",
    "com2",
    "com3",
    "com4",
    "com5",
    "com6",
    "com7",
    "com8",
    "com9",
    "lpt1",
    "lpt2",
    "lpt3",
    "lpt4",
    "lpt5",
    "lpt6",
    "lpt7",
    "lpt8",
    "lpt9",
  ];

  const defaultSelectFolderHeading = $("#selectFolderState h3").text();
  const defaultSelectFolderMessage = $("#selectFolderState p").text();
  const defaultEmptyFolderHeading = $("#emptyFolderState h3").text();
  const defaultEmptyFolderMessage = $("#emptyFolderState p").text();
  const $imageInfoSaveState = $("#imageInfoModal [data-save-state]");
  const $imageEditSaveState = $("#imageEditModal [data-save-state]");
  const saveStateLabels = {
    saving: "Saving…",
    saved: "Saved",
    unsaved: "Unsaved changes",
  };
  let imageInfoIsSaving = false;
  let imageInfoIsDirty = false;
  let imageEditIsSaving = false;
  let imageEditIsDirty = false;

  function setSaveState($element, state) {
    if (!$element || !$element.length) {
      return;
    }
    const nextState = saveStateLabels[state] ? state : "saved";
    $element.attr("data-state", nextState);
    $element.find("[data-save-state-text]").text(saveStateLabels[nextState]);
    if (nextState === "saving") {
      $element.attr("aria-busy", "true");
    } else {
      $element.removeAttr("aria-busy");
    }
  }

  function resetImageInfoSaveState() {
    imageInfoIsSaving = false;
    imageInfoIsDirty = false;
    setSaveState($imageInfoSaveState, "saved");
  }

  function markImageInfoDirty() {
    if (imageInfoIsSaving) {
      return;
    }
    imageInfoIsDirty = true;
    setSaveState($imageInfoSaveState, "unsaved");
  }

  function resetImageEditSaveState() {
    imageEditIsSaving = false;
    imageEditIsDirty = false;
    setSaveState($imageEditSaveState, "saved");
  }

  function markImageEditDirty() {
    if (imageEditIsSaving) {
      return;
    }
    imageEditIsDirty = true;
    setSaveState($imageEditSaveState, "unsaved");
  }

  function showRetryButton(containerSelector, handler) {
    const container = $(containerSelector);
    let btn = container.find("button.retry-button");
    if (!btn.length) {
      btn = $(
        '<button type="button" class="btn btn-secondary retry-button"><i class="fa-solid fa-rotate-right btn-icon" aria-hidden="true"></i><span class="btn-label">Retry</span></button>',
      );
      container.append(btn);
    }
    btn
      .off("click")
      .on("click", function (e) {
        e.preventDefault();
        handler();
      })
      .show();
  }

  function hideRetryButton(containerSelector) {
    $(containerSelector).find("button.retry-button").hide();
  }

  function disableUpload() {
    $("#uploadBtn")
      .prop("disabled", true)
      .addClass("is-disabled")
      .attr("aria-disabled", "true");
  }

  function enableUpload() {
    $("#uploadBtn")
      .prop("disabled", false)
      .removeClass("is-disabled")
      .removeAttr("aria-disabled");
  }

  function loadFolders() {
    $.getJSON("modules/media/list_media.php", function (res) {
      const list = $("#folderList").empty();
      const media = res.media || [];
      (res.folders || []).forEach((f) => {
        const name = typeof f === "string" ? f : f.name;
        const thumb = f.thumbnail ? f.thumbnail : null;
        const folderMedia = media.filter((m) => m.folder === name);
        const count = folderMedia.length;
        const totalBytes = folderMedia.reduce(
          (s, m) => s + parseInt(m.size || 0),
          0,
        );
        const lastMod = folderMedia.reduce(
          (m, i) => (i.modified_at && i.modified_at > m ? i.modified_at : m),
          0,
        );
        const meta =
          count +
          " files • " +
          formatFileSize(totalBytes) +
          " • Last edited " +
          (lastMod ? new Date(lastMod * 1000).toLocaleDateString() : "");
        const item = $(
          '<div class="folder-item" data-folder="' +
            name +
            '" aria-dropeffect="move"></div>',
        );
        if (thumb) {
          item.append('<img class="folder-thumb" src="' + thumb + '" alt="">');
        }
        item.append(
          '<div class="folder-info"><h3>' +
            name +
            '</h3><p class="folder-meta">' +
            meta +
            "</p></div>",
        );
        item.click(function () {
          selectFolder(name);
        });

        let enterCount = 0;
        item.on("dragenter", function (e) {
          if (!draggedMediaId) return;
          e.preventDefault();
          enterCount++;
          item.addClass("drop-target");
        });
        item.on("dragover", function (e) {
          if (!draggedMediaId) return;
          e.preventDefault();
          if (e.originalEvent && e.originalEvent.dataTransfer) {
            e.originalEvent.dataTransfer.dropEffect = "move";
          }
        });
        item.on("dragleave", function () {
          if (!draggedMediaId) return;
          enterCount = Math.max(0, enterCount - 1);
          if (enterCount === 0) {
            item.removeClass("drop-target");
          }
        });
        item.on("drop", function (e) {
          if (!draggedMediaId) return;
          e.preventDefault();
          enterCount = 0;
          item.removeClass("drop-target");
          const target = item.data("folder");
          if (target && target !== draggedMediaFolder) {
            moveMedia(draggedMediaId, target, draggedMediaFolder);
          }
          draggedMediaId = null;
          draggedMediaFolder = null;
        });
        if (typeof item.droppable === "function") {
          item.droppable({
            accept: "#imageGrid .image-card",
            tolerance: "pointer",
            hoverClass: "drop-target",
            drop: function (event, ui) {
              const dragged = ui.draggable || $(event.target);
              const mediaId = dragged.data("id");
              const sourceFolder = dragged.data("folder") || "";
              const target = $(this).data("folder");
              if (mediaId && target && target !== sourceFolder) {
                moveMedia(mediaId, target, sourceFolder);
              }
              $(this).removeClass("drop-target");
              dragged.removeClass("is-dragging");
              draggedMediaId = null;
              draggedMediaFolder = null;
            },
          });
        }
        list.append(item);
      });

      // Update stats
      $("#totalFolders").text((res.folders || []).length);
      $("#totalImages").text(media.length);
      const totalBytes = media.reduce(
        (sum, m) => sum + (parseInt(m.size) || 0),
        0,
      );
      $("#totalSize").text(formatFileSize(totalBytes));
      $("#mediaStorageSummary").text(formatFileSize(totalBytes) + " used");

      if (currentFolder) {
        $('.folder-item[data-folder="' + currentFolder + '"]').addClass(
          "active",
        );
      } else {
        $("#mediaHeroFolderName").text("No folder selected");
        $("#mediaHeroFolderInfo").text("Select a folder to see file details");
      }
      $("#selectFolderState h3").text(defaultSelectFolderHeading);
      $("#selectFolderState p").text(defaultSelectFolderMessage);
      hideRetryButton("#selectFolderState");
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error("Failed to load folders:", textStatus, errorThrown);
      currentFolder = null;
      currentImages = [];
      currentFolderMeta = "";
      $("#folderList").empty();
      $("#totalFolders").text("0");
      $("#totalImages").text("0");
      $("#totalSize").text("0");
      $("#mediaStorageSummary").text("0 used");
      $("#folderStats").text("");
      $("#galleryHeader").hide();
      disableUpload();
      renderImages();
      $("#mediaHeroFolderName").text("Unable to load folders");
      $("#mediaHeroFolderInfo").text(
        "Please try again or contact support if the issue continues.",
      );
      $("#selectFolderState h3").text("Unable to load folders");
      $("#selectFolderState p").text("Check your connection and try again.");
      showRetryButton("#selectFolderState", loadFolders);
      alertModal("We couldn't load your media folders. Please try again.");
    });
  }

  function selectFolder(name) {
    currentFolder = name;
    currentPage = 1;
    currentOffset = 0;
    totalPages = 1;
    totalImagesCount = 0;
    $(".folder-item").removeClass("active");
    $('.folder-item[data-folder="' + name + '"]').addClass("active");
    $("#selectedFolderName").text(name);
    $("#galleryHeader").show();
    $("#renameFolderBtn").show();
    $("#deleteFolderBtn").show();
    $("#mediaHeroFolderName").text(name);
    $("#mediaHeroFolderInfo").text("Loading folder details…");
    $("#uploadBtn")
      .prop("disabled", false)
      .removeClass("is-disabled")
      .removeAttr("aria-disabled");
    loadImages();
  }

  function loadImages() {
    if (!currentFolder) {
      currentImages = [];
      totalImagesCount = 0;
      totalPages = 1;
      currentOffset = 0;
      renderImages();
      return;
    }
    const limit = itemsPerPage > 0 ? itemsPerPage : 0;
    const offset = limit ? (currentPage - 1) * limit : 0;
    const params = { folder: currentFolder, sort: sortBy, order: sortOrder };
    if (limit) {
      params.limit = limit;
      params.offset = offset;
    }
    $.getJSON("modules/media/list_media.php", params, function (res) {
      currentImages = res.media || [];
      const parsedTotal = parseInt(res.total, 10);
      totalImagesCount = Number.isNaN(parsedTotal)
        ? currentImages.length
        : parsedTotal;
      const parsedBytes = parseInt(res.total_size, 10);
      const totalBytes = Number.isNaN(parsedBytes)
        ? currentImages.reduce((s, m) => s + parseInt(m.size || 0), 0)
        : parsedBytes;
      const parsedLastMod = parseInt(res.last_modified, 10);
      const lastMod = Number.isNaN(parsedLastMod)
        ? currentImages.reduce(
            (m, i) => (i.modified_at && i.modified_at > m ? i.modified_at : m),
            0,
          )
        : parsedLastMod;
      const lastEdited = lastMod
        ? "Last edited " + new Date(lastMod * 1000).toLocaleDateString()
        : "No edits yet";
      const limitPages = limit
        ? Math.max(1, Math.ceil(totalImagesCount / limit))
        : 1;
      if (limit && currentPage > limitPages && limitPages > 0) {
        currentPage = limitPages;
        loadImages();
        return;
      }
      totalPages = limitPages;
      currentOffset = offset;
      currentFolderMeta =
        totalImagesCount +
        " files • " +
        formatFileSize(totalBytes) +
        " • " +
        lastEdited;
      $("#folderStats").text(currentFolderMeta);
      $("#mediaHeroFolderInfo").text(currentFolderMeta);
      $("#emptyFolderState h3").text(defaultEmptyFolderHeading);
      $("#emptyFolderState p").text(defaultEmptyFolderMessage);
      hideRetryButton("#emptyFolderState");
      enableUpload();
      $("#renameFolderBtn").show();
      $("#deleteFolderBtn").show();
      renderImages();
    }).fail(function (jqXHR, textStatus, errorThrown) {
      console.error(
        "Failed to load images for folder",
        currentFolder,
        textStatus,
        errorThrown,
      );
      currentImages = [];
      currentFolderMeta = "";
      $("#folderStats").text("");
      $("#galleryHeader").show();
      $("#mediaHeroFolderInfo").text(
        "Unable to load files for this folder. Please try again.",
      );
      $("#emptyFolderState h3").text("Unable to load media");
      $("#emptyFolderState p").text("Check your connection and try again.");
      showRetryButton("#emptyFolderState", loadImages);
      disableUpload();
      renderImages();
      $("#mediaToolbar").hide();
      $("#renameFolderBtn").hide();
      $("#deleteFolderBtn").hide();
      alertModal(
        "We couldn't load the media in this folder. Please try again.",
      );
    });
  }

  function updateOrder() {
    const ids = $("#imageGrid .image-card")
      .map(function () {
        return $(this).data("id");
      })
      .get();
    $.post("modules/media/update_order.php", { order: JSON.stringify(ids) });
  }

  function moveMedia(id, targetFolder, sourceFolder) {
    if (!id || !targetFolder) return;
    $.ajax({
      url: "modules/media/move_media.php",
      method: "POST",
      data: { id: id, folder: targetFolder },
      dataType: "json",
    })
      .done(function (res) {
        const response = res || {};
        if (response.status === "success") {
          if (
            !currentFolder ||
            currentFolder === targetFolder ||
            currentFolder === sourceFolder
          ) {
            loadImages();
          }
          loadFolders();
        } else {
          alertModal(
            response.message || "Unable to move the file. Please try again.",
          );
        }
      })
      .fail(function () {
        alertModal("Unable to move the file. Please try again.");
      });
  }

  function getSortedImages() {
    let imgs = currentImages.slice();
    switch (sortBy) {
      case "name":
        imgs.sort((a, b) => a.name.localeCompare(b.name));
        break;
      case "date":
        imgs.sort(
          (a, b) =>
            (a.modified_at || a.uploaded_at || 0) -
            (b.modified_at || b.uploaded_at || 0),
        );
        break;
      case "type":
        imgs.sort((a, b) => (a.type || "").localeCompare(b.type || ""));
        break;
      case "size":
        imgs.sort((a, b) => (parseInt(a.size) || 0) - (parseInt(b.size) || 0));
        break;
      case "tags":
        imgs.sort((a, b) =>
          (a.tags || []).join(",").localeCompare((b.tags || []).join(",")),
        );
        break;
      case "dimensions":
        imgs.sort(
          (a, b) =>
            (a.width || 0) * (a.height || 0) - (b.width || 0) * (b.height || 0),
        );
        break;
      default:
        imgs.sort((a, b) => (a.order || 0) - (b.order || 0));
    }
    if (sortOrder === "desc") imgs.reverse();
    if (itemsPerPage > 0) {
      const startIndex = Math.max(
        0,
        (currentPage - 1) * itemsPerPage - currentOffset,
      );
      imgs = imgs.slice(startIndex, startIndex + itemsPerPage);
    }
    return imgs;
  }

  function renderPagination() {
    const pagination = $("#galleryPagination");
    if (!currentFolder || itemsPerPage <= 0 || totalPages <= 1) {
      pagination.hide().empty();
      return;
    }

    pagination.empty().show();

    const createButton = (label, page, disabled, active) => {
      const btn = $(
        '<button type="button" class="pagination-btn">' + label + "</button>",
      );
      if (disabled) {
        btn
          .prop("disabled", true)
          .attr("aria-disabled", "true")
          .addClass("is-disabled");
      } else {
        btn.attr("data-page", page);
      }
      if (active) {
        btn.addClass("is-active");
        btn.attr("aria-current", "page");
      }
      if (label === "Prev") {
        btn.attr("aria-label", "Previous page");
      } else if (label === "Next") {
        btn.attr("aria-label", "Next page");
      } else {
        btn.attr("aria-label", "Go to page " + label);
      }
      return btn;
    };

    pagination.append(
      createButton("Prev", currentPage - 1, currentPage === 1, false),
    );

    const maxButtons = 5;
    let start = Math.max(1, currentPage - Math.floor(maxButtons / 2));
    let end = start + maxButtons - 1;
    if (end > totalPages) {
      end = totalPages;
      start = Math.max(1, end - maxButtons + 1);
    }

    for (let page = start; page <= end; page++) {
      pagination.append(createButton(page, page, false, page === currentPage));
    }

    pagination.append(
      createButton("Next", currentPage + 1, currentPage === totalPages, false),
    );
  }

  function applyViewType() {
    const grid = $("#imageGrid");
    grid.removeClass(
      "view-extra-large view-large view-medium view-small view-details",
    );
    grid.addClass("view-" + viewType);
  }

  function renderImages() {
    const grid = $("#imageGrid");
    const toolbar = $("#mediaToolbar");
    if (!currentFolder) {
      toolbar.hide();
      $("#selectFolderState").show();
      grid.hide();
      $("#emptyFolderState").hide();
      $("#folderStats").text("");
      $("#renameFolderBtn").hide();
      $("#deleteFolderBtn").hide();
      $("#mediaHeroFolderName").text("No folder selected");
      $("#mediaHeroFolderInfo").text("Select a folder to see file details");
      $("#uploadBtn")
        .prop("disabled", true)
        .addClass("is-disabled")
        .attr("aria-disabled", "true");
      return;
    }
    toolbar.show();
    $("#selectFolderState").hide();
    const images = getSortedImages();
    grid.empty();
    if (images.length === 0) {
      $("#emptyFolderState").show();
      grid.hide();
    } else {
      $("#emptyFolderState").hide();
      grid.show();
      images.forEach((img) => {
        const isImage = img.type === "images";
        const src = img.thumbnail ? img.thumbnail : img.file;
        let preview = "";
        if (isImage) {
          preview = '<img src="' + src + '" alt="' + img.name + '" loading="lazy">';
        } else {
          const ext = getFileExtension(img.file);
          const icon = getFileIconMarkup(ext);
          preview = '<div class="file-icon">' + icon + "</div>";
        }
        const editButton = isImage
          ? '<button type="button" class="edit-btn action-icon-button has-tooltip" data-id="' +
            img.id +
            '" aria-label="Edit media" data-tooltip="Edit media"><i class="fa-solid fa-pen-to-square action-icon" aria-hidden="true"></i></button>'
          : "";
        const card = $(
          '<div class="image-card" data-id="' +
            img.id +
            '">\
                        <div class="image-preview">' +
            preview +
            '\
                            <div class="image-overlay">\
                                <div>\
                                    <button type="button" class="info-btn action-icon-button has-tooltip" data-id="' +
            img.id +
            '" aria-label="View details" data-tooltip="View details"><i class="fa-solid fa-circle-info action-icon" aria-hidden="true"></i></button>\
                                    ' +
            editButton +
            '\
                                    <button type="button" class="remove-btn action-icon-button has-tooltip" data-id="' +
            img.id +
            '" aria-label="Delete media" data-tooltip="Delete media"><i class="fa-solid fa-trash action-icon" aria-hidden="true"></i></button>\
                                </div>\
                            </div>\
                        </div>\
                        <div class="image-info">\
                            <h4>' +
            img.name +
            "</h4>\
                            <p>" +
            formatFileSize(img.size) +
            "</p>\
                        </div>\
                    </div>",
        );
        card.attr("draggable", true);
        card.data("folder", img.folder || "");
        grid.append(card);
      });
      if (sortBy === "custom" && totalPages <= 1) {
        grid.sortable({
          placeholder: "ui-sortable-placeholder",
          start: function (e, ui) {
            ui.placeholder.height(ui.item.height());
          },
          stop: function () {
            updateOrder();
          },
        });
      } else if (grid.hasClass("ui-sortable")) {
        grid.sortable("destroy");
      }
    }
    applyViewType();
    renderPagination();
  }

  function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";
    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
  }

  function getFileIconMarkup(ext) {
    const icons = {
      pdf: '<i class="fa-solid fa-file-pdf" aria-hidden="true"></i>',
      doc: '<i class="fa-solid fa-file-lines" aria-hidden="true"></i>',
      docx: '<i class="fa-solid fa-file-lines" aria-hidden="true"></i>',
      txt: '<i class="fa-solid fa-file-lines" aria-hidden="true"></i>',
      csv: '<i class="fa-solid fa-file-csv" aria-hidden="true"></i>',
      xlsx: '<i class="fa-solid fa-file-excel" aria-hidden="true"></i>',
      mp3: '<i class="fa-solid fa-file-audio" aria-hidden="true"></i>',
      mp4: '<i class="fa-solid fa-file-video" aria-hidden="true"></i>',
      m4v: '<i class="fa-solid fa-file-video" aria-hidden="true"></i>',
      webm: '<i class="fa-solid fa-file-video" aria-hidden="true"></i>',
      mov: '<i class="fa-solid fa-file-video" aria-hidden="true"></i>',
    };
    return icons[ext] || '<i class="fa-solid fa-file" aria-hidden="true"></i>';
  }

  function escapeHtml(str) {
    return $("<div>")
      .text(str == null ? "" : str)
      .html();
  }

  function resetUsageUI() {
    const list = $("#mediaUsageList");
    list.empty().hide();
    $("#mediaUsageLoading").show();
    $("#mediaUsageError").hide().text("");
    $("#mediaUsageEmpty").hide();
  }

  function showUsageError(message) {
    $("#mediaUsageLoading").hide();
    $("#mediaUsageList").hide().empty();
    $("#mediaUsageEmpty").hide();
    $("#mediaUsageError")
      .text(message || "Unable to load usage information.")
      .show();
  }

  function renderUsageList(usage) {
    const list = $("#mediaUsageList");
    $("#mediaUsageLoading").hide();
    $("#mediaUsageError").hide().text("");
    list.empty();
    if (!Array.isArray(usage) || !usage.length) {
      list.hide();
      $("#mediaUsageEmpty").show();
      return;
    }
    $("#mediaUsageEmpty").hide();
    usage.forEach((item) => {
      const type = escapeHtml(item && item.type ? item.type : "Content");
      const name = escapeHtml(item && item.name ? item.name : "");
      const details = item && item.details ? escapeHtml(item.details) : "";
      let html =
        "<strong>" + type + ":</strong> " + (name || "<em>Unnamed</em>");
      if (details) {
        html += '<div class="media-usage-detail">' + details + "</div>";
      }
      const li = $("<li></li>").html(html);
      list.append(li);
    });
    list.show();
  }

  function loadMediaUsage(id) {
    if (!id) {
      showUsageError("Missing media reference.");
      return;
    }
    if (usageCache[id]) {
      renderUsageList(usageCache[id]);
      return;
    }
    $.getJSON("modules/media/get_usage.php", { id: id })
      .done(function (res) {
        if (res && res.status === "success") {
          const usage = Array.isArray(res.usage) ? res.usage : [];
          usageCache[id] = usage;
          renderUsageList(usage);
        } else {
          const message =
            res && res.message
              ? res.message
              : "Unable to load usage information.";
          showUsageError(message);
        }
      })
      .fail(function (jqXHR) {
        let message = "Unable to load usage information.";
        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
          message = jqXHR.responseJSON.message;
        }
        showUsageError(message);
      });
  }

  function updateSizeEstimate() {
    if (!cropper) {
      $("#sizeEstimate").text("");
      return;
    }
    const canvas = cropper.getCroppedCanvas();
    const format = $("#saveFormat").val() || "jpeg";
    const mime = "image/" + (format === "jpg" ? "jpeg" : format);
    const quality = format === "jpeg" ? 0.9 : 1;
    canvas.toBlob(
      function (blob) {
        $("#sizeEstimate").text("Estimated: " + formatFileSize(blob.size));
      },
      mime,
      quality,
    );
  }

  function updateUploadProgress(percent) {
    const value = Math.max(0, Math.min(100, Math.round(percent || 0)));
    $("#uploadProgressFill").css("width", value + "%");
    $("#uploadProgressPercent").text(value + "%");
  }

  function startUploadUI() {
    updateUploadProgress(0);
    $("#uploadStatusMessage").text("Uploading files…");
    $("#uploadLoader").show();
  }

  function resetUploadUI() {
    $("#fileInput").val("");
    $("#uploadLoader").hide();
    updateUploadProgress(0);
    $("#uploadStatusMessage").text("");
  }

  function getFileExtension(name = "") {
    const parts = name.split(".");
    return parts.length > 1 ? parts.pop().toLowerCase() : "";
  }

  function getFileCategory(file) {
    const type = (file && file.type ? file.type : "").toLowerCase();
    if (type.startsWith("image/")) return "image";
    if (type.startsWith("video/")) return "video";
    const ext = getFileExtension(file && file.name ? file.name : "");
    if (IMAGE_EXTENSIONS.indexOf(ext) !== -1) return "image";
    if (VIDEO_EXTENSIONS.indexOf(ext) !== -1) return "video";
    return "other";
  }

  function validateUploadFiles(fileList) {
    const files = Array.isArray(fileList)
      ? fileList
      : Array.from(fileList || []);
    const oversized = { image: [], video: [] };
    files.forEach((file) => {
      const category = getFileCategory(file);
      if (category === "image" && file.size > MAX_IMAGE_SIZE_BYTES) {
        oversized.image.push(file.name || "Untitled image");
      } else if (category === "video" && file.size > MAX_VIDEO_SIZE_BYTES) {
        oversized.video.push(file.name || "Untitled video");
      }
    });

    const result = {
      error: "",
      warning: "",
      oversizedImages: oversized.image.slice(),
    };

    if (oversized.video.length) {
      const messages = [
        "Some files are too large to upload. Images will be optimized during upload so their final size is 15 MB or smaller. Videos must be 30 MB or smaller; please optimize oversized videos before trying to upload them again.",
        "Videos over 30 MB: " +
          oversized.video.join(", ") +
          ". Please optimize these videos before uploading again.",
      ];
      if (oversized.image.length) {
        messages.push("Images over 15 MB: " + oversized.image.join(", "));
      }
      result.error = messages.join("\n\n");
    } else if (oversized.image.length) {
      result.warning = "Images over 15 MB: " + oversized.image.join(", ");
    }

    return result;
  }

  function showImageOptimizationPrompt(imageNames) {
    const safeNames =
      Array.isArray(imageNames) && imageNames.length
        ? imageNames.map((name) => escapeHtml(name || "Untitled image"))
        : [];
    const listItems = safeNames.map((name) => "<li>" + name + "</li>").join("");
    const details = safeNames.length
      ? '<ul class="modal-list">' + listItems + "</ul>"
      : "";
    const bodyHtml = `
            <p>The following images are larger than 15 MB:</p>
            ${details}
            <p>Would you like to optimize them so the uploaded files are 15 MB or smaller, or keep the original file sizes?</p>
        `;
    return new Promise((resolve) => {
      const html = `
                <div class="modal-content">
                    <div class="modal-header"><h2>Large images detected</h2></div>
                    <div class="modal-body">${bodyHtml}</div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary cancel">Cancel</button>
                        <button class="btn btn-primary optimize">Optimize &amp; Upload</button>
                        <button class="btn btn-secondary upload-original">Upload Original</button>
                    </div>
                </div>`;
      const $modal = $('<div class="modal active"></div>')
        .append(html)
        .appendTo("body");
      const cleanup = (result) => {
        $modal.remove();
        resolve(result);
      };
      $modal.find(".cancel").on("click", () => cleanup("cancel"));
      $modal.find(".optimize").on("click", () => cleanup("optimize"));
      $modal.find(".upload-original").on("click", () => cleanup("original"));
      $modal.on("click", function (event) {
        if (event.target === this) {
          cleanup("cancel");
        }
      });
    });
  }

  function uploadFiles(fileList) {
    if (!currentFolder) return;
    const files = Array.isArray(fileList)
      ? fileList
      : Array.from(fileList || []);
    if (!files.length) return;
    const validationResult = validateUploadFiles(files);
    if (validationResult.error) {
      alertModal(validationResult.error);
      $("#fileInput").val("");
      return;
    }
    const proceedWithUpload = (optimizeImages = true) => {
      const fd = new FormData();
      files.forEach((f) => fd.append("files[]", f));
      fd.append("folder", currentFolder);
      fd.append("tags", "");
      fd.append("optimize_images", optimizeImages ? "1" : "0");
      startUploadUI();
      $.ajax({
        url: "modules/media/upload_media.php",
        method: "POST",
        data: fd,
        processData: false,
        contentType: false,
        dataType: "json",
        xhr: function () {
          const xhr = new window.XMLHttpRequest();
          xhr.upload.addEventListener("progress", function (e) {
            if (e.lengthComputable) {
              const percent = (e.loaded / e.total) * 100;
              updateUploadProgress(percent);
            }
          });
          return xhr;
        },
      })
        .done(function (res) {
          const response = res || {};
          if (response.status === "success") {
            updateUploadProgress(100);
            loadImages();
            loadFolders();
          }
          const errors = Array.isArray(response.errors) ? response.errors : [];
          if (errors.length) {
            alertModal(errors.join("\n"));
          } else if (response.status !== "success") {
            const message = response.message || "Error uploading files.";
            alertModal(message);
          }
        })
        .fail(function (jqXHR) {
          let message = "Error uploading files.";
          if (jqXHR.responseJSON) {
            const json = jqXHR.responseJSON;
            if (Array.isArray(json.errors) && json.errors.length) {
              message = json.errors.join("\n");
            } else if (json.message) {
              message = json.message;
            }
          } else if (jqXHR.responseText) {
            message = jqXHR.responseText;
          }
          alertModal(message);
        })
        .always(function () {
          resetUploadUI();
        });
    };

    if (
      Array.isArray(validationResult.oversizedImages) &&
      validationResult.oversizedImages.length
    ) {
      showImageOptimizationPrompt(validationResult.oversizedImages).then(
        (choice) => {
          if (choice === "cancel") {
            $("#fileInput").val("");
            return;
          }
          proceedWithUpload(choice !== "original");
        },
      );
      return;
    }

    proceedWithUpload(true);
  }

  function getCreateFolderMessageElement() {
    let $message = $("#createFolderModal .create-folder-message");
    if (!$message.length) {
      $message = $(
        '<p class="create-folder-message" role="status" aria-live="polite" style="display:none;margin-top:8px;"></p>',
      );
      $("#createFolderModal .modal-body").append($message);
    }
    return $message;
  }

  function showCreateFolderMessage(text, type = "error") {
    const $message = getCreateFolderMessageElement();
    $message
      .text(text)
      .attr("role", type === "error" ? "alert" : "status")
      .attr("aria-live", type === "error" ? "assertive" : "polite")
      .css({
        color: type === "error" ? "#c0392b" : "#1e8449",
        "font-weight": "600",
      })
      .show();
  }

  function clearCreateFolderMessage() {
    const $message = $("#createFolderModal .create-folder-message");
    if ($message.length) {
      $message.text("").hide();
    }
  }

  function createFolder() {
    const $input = $("#newFolderName");
    const name = $input.val().trim();
    $input.val(name);
    clearCreateFolderMessage();

    if (!name) {
      showCreateFolderMessage("Please enter a folder name.", "error");
      $input.focus();
      return;
    }

    const lowerName = name.toLowerCase();
    if (reservedFolderNames.includes(lowerName)) {
      showCreateFolderMessage(
        "That folder name is reserved. Please choose another.",
        "error",
      );
      $input.focus();
      return;
    }

    if (/[\\/]/.test(name)) {
      showCreateFolderMessage("Folder names cannot contain slashes.", "error");
      $input.focus();
      return;
    }

    $("#confirmCreateBtn").prop("disabled", true);

    $.ajax({
      url: "modules/media/create_folder.php",
      method: "POST",
      data: { folder: name },
      dataType: "json",
    })
      .done(function (res) {
        if (res && res.status === "success") {
          showCreateFolderMessage(
            res.message || "Folder created successfully.",
            "success",
          );
          $input.val("");
          loadFolders();
          setTimeout(function () {
            clearCreateFolderMessage();
            closeModal("createFolderModal");
          }, 1000);
        } else {
          showCreateFolderMessage(
            (res && res.message) || "Error creating folder.",
            "error",
          );
          $input.focus();
        }
      })
      .fail(function () {
        showCreateFolderMessage("Error creating folder.", "error");
        $input.focus();
      })
      .always(function () {
        $("#confirmCreateBtn").prop("disabled", false);
      });
  }

  function getRenameFolderMessageElement() {
    return $("#renameFolderMessage");
  }

  function showRenameFolderMessage(text, type = "error") {
    const $message = getRenameFolderMessageElement();
    if (!$message.length) return;
    $message
      .text(text)
      .attr("role", type === "error" ? "alert" : "status")
      .attr("aria-live", type === "error" ? "assertive" : "polite")
      .css({
        color: type === "error" ? "#c0392b" : "#1e8449",
        "font-weight": "600",
      })
      .show();
  }

  function clearRenameFolderMessage() {
    const $message = getRenameFolderMessageElement();
    if ($message.length) {
      $message.text("").hide().removeAttr("role").removeAttr("aria-live");
    }
  }

  function renameFolder() {
    if (!currentFolder) return;
    const previousFolder = currentFolder;
    const $modal = $("#renameFolderModal");
    const $input = $("#renameFolderName");
    const $confirm = $("#confirmRenameFolderBtn");
    const $cancel = $("#cancelRenameFolderBtn");
    const restoreSelection = () => {
      currentFolder = previousFolder;
      $("#selectedFolderName").text(previousFolder);
      $("#mediaHeroFolderName").text(previousFolder);
      $(".folder-item").removeClass("active");
      $('.folder-item[data-folder="' + previousFolder + '"]').addClass(
        "active",
      );
    };
    clearRenameFolderMessage();
    $input.val(previousFolder);
    openModal("renameFolderModal");
    $input.focus().select();

    function cleanup() {
      $modal.off("click.renameFolder");
      $input.off("keypress.renameFolder");
      $input.off("input.renameFolder");
      $confirm.off("click.renameFolder").prop("disabled", false);
      $cancel.off("click.renameFolder");
    }

    function closeRenameModal() {
      cleanup();
      closeModal("renameFolderModal");
      clearRenameFolderMessage();
    }

    function cancelRename() {
      closeRenameModal();
      restoreSelection();
    }

    function attemptRename() {
      clearRenameFolderMessage();
      const value = $input.val();
      const trimmedName = value.trim();
      $input.val(trimmedName);

      if (!trimmedName) {
        showRenameFolderMessage("Folder name cannot be empty.", "error");
        $input.focus();
        return;
      }

      if (/[\\/]/.test(trimmedName)) {
        showRenameFolderMessage(
          "Folder names cannot contain slashes.",
          "error",
        );
        $input.focus();
        return;
      }

      const lowerName = trimmedName.toLowerCase();
      if (reservedFolderNames.includes(lowerName)) {
        showRenameFolderMessage(
          "That folder name is reserved. Please choose another.",
          "error",
        );
        $input.focus();
        return;
      }

      if (trimmedName === previousFolder) {
        showRenameFolderMessage("Folder name is unchanged.", "error");
        $input.focus();
        return;
      }

      $confirm.prop("disabled", true);

      $.post(
        "modules/media/rename_folder.php",
        { old: previousFolder, new: trimmedName },
        function (res) {
          if (res.status === "success") {
            currentFolder = trimmedName;
            $("#selectedFolderName").text(trimmedName);
            $("#mediaHeroFolderName").text(trimmedName);
            loadImages();
            loadFolders();
            closeRenameModal();
          } else {
            const message =
              res && res.message ? res.message : "Error renaming folder";
            showRenameFolderMessage(message, "error");
            restoreSelection();
          }
        },
        "json",
      )
        .fail(function (xhr) {
          const message =
            xhr.responseJSON && xhr.responseJSON.message
              ? xhr.responseJSON.message
              : "Error renaming folder";
          showRenameFolderMessage(message, "error");
          restoreSelection();
        })
        .always(function () {
          $confirm.prop("disabled", false);
        });
    }

    $confirm.off("click.renameFolder").on("click.renameFolder", function (e) {
      e.preventDefault();
      attemptRename();
    });

    $cancel.off("click.renameFolder").on("click.renameFolder", function (e) {
      e.preventDefault();
      cancelRename();
    });

    $modal.off("click.renameFolder").on("click.renameFolder", function (e) {
      if (e.target === this) {
        cancelRename();
      }
    });

    $input
      .off("keypress.renameFolder")
      .on("keypress.renameFolder", function (e) {
        if (e.which === 13) {
          e.preventDefault();
          attemptRename();
        }
      });

    $input.off("input.renameFolder").on("input.renameFolder", function () {
      clearRenameFolderMessage();
    });
  }

  function deleteFolder() {
    if (!currentFolder) return;
    confirmModal("Delete this folder and all its files?").then((ok) => {
      if (!ok) return;
      $.post(
        "modules/media/delete_folder.php",
        { folder: currentFolder },
        function (res) {
          if (res.status === "success") {
            currentFolder = null;
            currentPage = 1;
            currentOffset = 0;
            totalPages = 1;
            totalImagesCount = 0;
            $("#galleryHeader").hide();
            loadFolders();
            loadImages();
          } else {
            alertModal(res.message || "Error deleting folder");
          }
        },
        "json",
      );
    });
  }

  function renameImageDirect(id, newName) {
    if (!newName) return;
    return $.post(
      "modules/media/rename_media.php",
      { id: id, name: newName },
      function (res) {
        if (res.status === "success") {
          delete usageCache[id];
          loadImages();
          loadFolders();
        } else {
          alertModal(res.message || "Error renaming file");
        }
      },
      "json",
    );
  }

  function renameImage(id, name) {
    promptModal("Enter new file name", name || "").then((newName) => {
      if (!newName || newName === name) return;
      renameImageDirect(id, newName);
    });
  }

  function resetInfoPreview() {
    const $image = $("#infoImage");
    const $video = $("#infoVideo");
    const $audio = $("#infoAudio");
    const $iframe = $("#infoDocumentFrame");
    const $document = $("#infoDocument");

    $image.hide().attr("src", "");
    $video.hide().removeAttr("src");
    $video.each(function () {
      this.pause();
      this.load();
    });
    $audio.hide().removeAttr("src");
    $audio.each(function () {
      this.pause();
      this.load();
    });
    $iframe.hide().attr("src", "");
    $document.hide();
  }

  function showDocumentPreview(img, ext) {
    const $doc = $("#infoDocument");
    const $icon = $("#infoDocumentIcon");
    const $name = $("#infoDocumentName");
    const $link = $("#infoDocumentLink");
    $icon.html(getFileIconMarkup(ext));
    $name.text(img.name || "Untitled file");
    $link.attr("href", img.file || "#");
    $doc.show();
  }

  function showImageInfo(id) {
    const img = currentImages.find((i) => i.id === id);
    if (!img) return;
    const ext = getFileExtension(img.file || img.name || "");
    const fileUrl = img.file || "";
    const isImage = img.type === "images";
    resetInfoPreview();
    if (isImage) {
      $("#infoImage")
        .attr("src", img.thumbnail ? img.thumbnail : img.file)
        .show();
    } else if (img.type === "videos") {
      const $video = $("#infoVideo");
      $video.attr("src", fileUrl);
      if (img.thumbnail) {
        $video.attr("poster", img.thumbnail);
      } else {
        $video.removeAttr("poster");
      }
      $video.show();
      $video.each(function () {
        this.load();
      });
    } else if (img.type === "audio") {
      const $audio = $("#infoAudio");
      $audio.attr("src", fileUrl).show();
      $audio.each(function () {
        this.load();
      });
    } else {
      if (ext === "pdf" && fileUrl) {
        $("#infoDocumentFrame").attr("src", fileUrl).show();
      } else {
        showDocumentPreview(img, ext);
      }
    }
    $("#edit-name").val(img.name);
    $("#edit-fileName").val(img.name);
    $("#infoType").text(img.type || "");
    $("#infoFile").text(img.name || "");
    $("#infoSize").text(formatFileSize(parseInt(img.size) || 0));
    $("#infoDimensions").text(
      isImage ? (img.width || "?") + " x " + (img.height || "?") : "—",
    );
    $("#infoExt").text(ext || "—");
    const d = img.modified_at
      ? new Date(img.modified_at * 1000)
      : new Date(img.uploaded_at * 1000);
    $("#infoDate").text(d.toLocaleString());
    $("#infoFolder").text(img.folder || "");
    if (isImage) {
      $("#imageEditorBtn").show();
    } else {
      $("#imageEditorBtn").hide();
    }
    resetUsageUI();
    $("#imageInfoModal").data("id", id);
    resetImageInfoSaveState();
    openModal("imageInfoModal");
    loadMediaUsage(id);
  }

  function deleteImage(id) {
    confirmModal("Delete this image?").then((ok) => {
      if (!ok) return;
      $.post("modules/media/delete_media.php", { id: id }, function () {
        delete usageCache[id];
        loadImages();
        loadFolders();
      });
    });
  }

  function openEditor(id) {
    const img = currentImages.find((i) => i.id === id);
    if (!img) return;
    $("#imageEditModal").data("id", id);
    resetImageEditSaveState();
    openModal("imageEditModal");
    const el = document.getElementById("editImage");
    el.src = img.file;
    if (cropper) cropper.destroy();
    cropper = new Cropper(el, { viewMode: 1 });
    flipX = 1;
    flipY = 1;
    $("#scaleSlider").val(1);
    $("#crop-preset").val("NaN");
    cropper.setAspectRatio(NaN);
    cropper.zoomTo(1);
    updateSizeEstimate();
  }

  function saveEditedImage() {
    if (!cropper) return;
    const id = $("#imageEditModal").data("id");
    const canvas = cropper.getCroppedCanvas();
    const format = $("#saveFormat").val() || "jpeg";
    const mime = "image/" + (format === "jpg" ? "jpeg" : format);
    const quality = format === "jpeg" ? 0.9 : 1;
    const dataUrl = canvas.toDataURL(mime, quality);
    confirmModal(
      "Create a new version? Click Cancel to overwrite the original.",
    ).then((newVer) => {
      imageEditIsSaving = true;
      setSaveState($imageEditSaveState, "saving");
      $.post(
        "modules/media/crop_media.php",
        { id: id, image: dataUrl, new_version: newVer ? 1 : 0, format: format },
        function () {
          imageEditIsDirty = false;
          setSaveState($imageEditSaveState, "saved");
          closeModal("imageEditModal");
          if (cropper) {
            cropper.destroy();
            cropper = null;
          }
          loadImages();
          loadFolders();
        },
        "json",
      )
        .fail(function () {
          imageEditIsDirty = true;
          setSaveState($imageEditSaveState, "unsaved");
        })
        .always(function () {
          imageEditIsSaving = false;
        });
    });
  }

  $("#uploadBtn").click(function () {
    $("#fileInput").click();
  });
  $("#fileInput").change(function () {
    uploadFiles(this.files);
  });

  let dragCounter = 0;
  function isFileDrag(event) {
    const dt =
      event && event.originalEvent ? event.originalEvent.dataTransfer : null;
    if (!dt || !dt.types) return false;
    if (typeof dt.types.contains === "function") {
      return dt.types.contains("Files");
    }
    try {
      return Array.from(dt.types).indexOf("Files") !== -1;
    } catch (e) {
      return false;
    }
  }

  const dropZone = $("#dropZone");
  $("#galleryContent")
    .on("dragenter", function (e) {
      if (!currentFolder || !isFileDrag(e)) return;
      e.preventDefault();
      dragCounter++;
      dropZone.addClass("dragging").css("display", "flex");
    })
    .on("dragover", function (e) {
      if (!currentFolder || !isFileDrag(e)) return;
      e.preventDefault();
    })
    .on("dragleave", function (e) {
      if (!currentFolder || !isFileDrag(e)) return;
      dragCounter--;
      if (dragCounter <= 0) {
        dragCounter = 0;
        dropZone.removeClass("dragging").css("display", "none");
      }
    })
    .on("drop", function (e) {
      if (!currentFolder || !isFileDrag(e)) return;
      e.preventDefault();
      dragCounter = 0;
      dropZone.removeClass("dragging").css("display", "none");
      const files = e.originalEvent.dataTransfer.files;
      if (files && files.length) {
        uploadFiles(files);
      }
    });
  dropZone.click(function () {
    $("#fileInput").click();
  });

  $("#renameFolderBtn").click(renameFolder);
  $("#deleteFolderBtn").click(deleteFolder);
  $("#createFolderBtn").click(function () {
    clearCreateFolderMessage();
    openModal("createFolderModal");
    $("#newFolderName").focus();
  });
  $("#mediaCreateFolderCta").click(function () {
    $("#createFolderBtn").trigger("click");
  });
  $("#mediaUploadCta").click(function () {
    $("#uploadBtn").trigger("click");
  });
  $("#cancelBtn").click(function () {
    closeModal("createFolderModal");
    $("#newFolderName").val("");
    clearCreateFolderMessage();
  });
  $("#confirmCreateBtn").click(createFolder);
  $("#newFolderName").keypress(function (e) {
    if (e.which === 13) createFolder();
  });

  $("#imageGrid").on("dragstart", ".image-card", function (e) {
    draggedMediaId = $(this).data("id");
    draggedMediaFolder = $(this).data("folder") || "";
    if ($("#imageGrid").data("ui-sortable")) {
      $("#imageGrid").sortable("disable");
    }
    if (e.originalEvent && e.originalEvent.dataTransfer) {
      e.originalEvent.dataTransfer.setData("text/plain", draggedMediaId);
      e.originalEvent.dataTransfer.effectAllowed = "move";
    }
    $(this).addClass("is-dragging");
  });

  $("#imageGrid").on("dragend", ".image-card", function () {
    if ($("#imageGrid").data("ui-sortable")) {
      $("#imageGrid").sortable("enable");
    }
    draggedMediaId = null;
    draggedMediaFolder = null;
    $(".folder-item").removeClass("drop-target");
    $(this).removeClass("is-dragging");
  });

  $("#imageGrid").on("sortstart", function (e, ui) {
    const item = ui.item || $(ui.helper);
    draggedMediaId = item.data("id");
    draggedMediaFolder = item.data("folder") || "";
  });

  $("#imageGrid").on("sortstop", function () {
    draggedMediaId = null;
    draggedMediaFolder = null;
    $(".folder-item").removeClass("drop-target");
  });

  $("#imageGrid").on("click", ".remove-btn", function (e) {
    e.stopPropagation();
    deleteImage($(this).data("id"));
  });
  $("#imageGrid").on("click", ".info-btn", function (e) {
    e.stopPropagation();
    showImageInfo($(this).data("id"));
  });
  $("#imageGrid").on("click", ".edit-btn", function (e) {
    e.stopPropagation();
    openEditor($(this).data("id"));
  });
  $("#imageGrid").on("click", ".image-card", function (e) {
    if (
      $(e.target).closest(".remove-btn").length ||
      $(e.target).closest(".info-btn").length ||
      $(e.target).closest(".edit-btn").length
    )
      return;
    showImageInfo($(this).data("id"));
  });

  $("#deleteBtn").click(function () {
    const id = $("#imageInfoModal").data("id");
    deleteImage(id);
    closeModal("imageInfoModal");
  });
  $("#imageEditorBtn").click(function () {
    const id = $("#imageInfoModal").data("id");
    if (!id) {
      return;
    }
    closeModal("imageInfoModal");
    openEditor(id);
  });
  $("#imageInfoModal").on(
    "input change",
    "#edit-name, #edit-fileName, #renamePhysicalCheckbox",
    function () {
      markImageInfoDirty();
    },
  );

  $("#saveEditBtn").click(function () {
    const id = $("#imageInfoModal").data("id");
    const newName = $("#edit-fileName").val().trim();
    const current = currentImages.find((i) => i.id === id) || {};
    imageInfoIsSaving = true;
    setSaveState($imageInfoSaveState, "saving");
    if (newName && newName !== current.name) {
      const request = renameImageDirect(id, newName);
      if (request && typeof request.always === "function") {
        request
          .done(function () {
            imageInfoIsDirty = false;
            setSaveState($imageInfoSaveState, "saved");
          })
          .fail(function () {
            imageInfoIsDirty = true;
            setSaveState($imageInfoSaveState, "unsaved");
          })
          .always(function () {
            imageInfoIsSaving = false;
            closeModal("imageInfoModal");
          });
        return;
      }
    }
    imageInfoIsDirty = false;
    imageInfoIsSaving = false;
    setSaveState($imageInfoSaveState, "saved");
    closeModal("imageInfoModal");
  });

  $("#imageEditCancel").click(function () {
    closeModal("imageEditModal");
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
  });
  $("#imageEditSave").click(saveEditedImage);
  $("#flipHorizontal").click(function () {
    if (!cropper) return;
    flipX = flipX * -1;
    cropper.scaleX(flipX);
    markImageEditDirty();
  });
  $("#flipVertical").click(function () {
    if (!cropper) return;
    flipY = flipY * -1;
    cropper.scaleY(flipY);
    markImageEditDirty();
  });
  $("#scaleSlider").on("input", function () {
    const val = parseFloat(this.value);
    if (cropper) {
      cropper.zoomTo(val);
      updateSizeEstimate();
    }
    markImageEditDirty();
  });
  $("#crop-preset").change(function () {
    if (!cropper) return;
    const ratio = parseFloat(this.value);
    cropper.setAspectRatio(isNaN(ratio) ? NaN : ratio);
    updateSizeEstimate();
    markImageEditDirty();
  });
  $("#saveFormat").change(function () {
    updateSizeEstimate();
    markImageEditDirty();
  });

  $("#sort-by").change(function () {
    sortBy = this.value;
    currentPage = 1;
    loadImages();
  });
  $("#sort-order").change(function () {
    sortOrder = this.value;
    currentPage = 1;
    loadImages();
  });
  $("#view-type").change(function () {
    viewType = this.value;
    applyViewType();
  });
  $("#items-per-page").change(function () {
    itemsPerPage = parseInt(this.value, 10);
    currentPage = 1;
    loadImages();
  });

  $("#galleryPagination").on(
    "click",
    ".pagination-btn[data-page]",
    function () {
      const page = parseInt($(this).data("page"), 10);
      if (
        !isNaN(page) &&
        page >= 1 &&
        page <= totalPages &&
        page !== currentPage
      ) {
        currentPage = page;
        loadImages();
      }
    },
  );

  $(window).click(function (e) {
    if (e.target.id === "createFolderModal") {
      closeModal("createFolderModal");
      $("#newFolderName").val("");
      clearCreateFolderMessage();
    }
    if (e.target.id === "imageInfoModal") {
      closeModal("imageInfoModal");
    }
    if (e.target.id === "imageEditModal") {
      closeModal("imageEditModal");
      if (cropper) {
        cropper.destroy();
        cropper = null;
      }
    }
  });

  // ── Feature 10: Responsive images – show/hide Copy HTML button ──────────────
  // Mirror the imageEditorBtn show/hide pattern: after the info modal is
  // populated by showImageInfo(), toggle our button based on media type.
  $("#imageGrid").on("click", ".info-btn, .image-card", function () {
    setTimeout(function () {
      var id = $("#imageInfoModal").data("id");
      if (!id) return;
      var img = currentImages.find(function (i) { return i.id === id; });
      $("#copyImageHtmlBtn").toggle(!!(img && img.type === "images"));
    }, 20);
  });

  // ── Feature 10: Copy HTML button – build srcset markup and copy to clipboard ─
  $("#copyImageHtmlBtn").on("click", function () {
    var id = $("#imageInfoModal").data("id");
    if (!id) return;
    var img = currentImages.find(function (i) { return i.id === id; });
    if (!img || img.type !== "images") return;

    var altText = (img.name || "").replace(/"/g, "&quot;");
    var html;

    var sizes = img.sizes && typeof img.sizes === "object" ? img.sizes : {};
    var sizeKeys = Object.keys(sizes)
      .map(Number)
      .filter(function (w) { return w > 0; })
      .sort(function (a, b) { return a - b; });

    if (sizeKeys.length > 0) {
      // Build srcset from responsive variants + original
      var srcsetParts = sizeKeys.map(function (w) {
        return sizes[w] + " " + w + "w";
      });
      // Append the full-size original
      var origWidth = img.width ? parseInt(img.width) : null;
      if (origWidth && origWidth > 0) {
        srcsetParts.push(img.file + " " + origWidth + "w");
      } else {
        srcsetParts.push(img.file + " 1200w");
      }
      html =
        '<img src="' + img.file + '"' +
        ' srcset="' + srcsetParts.join(", ") + '"' +
        ' sizes="(max-width: 480px) 100vw, (max-width: 800px) 100vw, 1200px"' +
        ' loading="lazy"' +
        ' alt="' + altText + '">';
    } else {
      // No responsive variants – at minimum add loading="lazy"
      html = '<img src="' + img.file + '" loading="lazy" alt="' + altText + '">';
    }

    var $btn = $("#copyImageHtmlBtn");
    var $label = $btn.find(".btn-label");
    var origLabel = $label.text();

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(html)
        .then(function () {
          $label.text("Copied!");
          setTimeout(function () { $label.text(origLabel); }, 2000);
        })
        .catch(function () {
          alertModal("Could not copy automatically. Here is your HTML:\n\n" + html);
        });
    } else {
      alertModal("Copy to clipboard not supported. Here is your HTML:\n\n" + html);
    }
  });

  loadFolders();
});
