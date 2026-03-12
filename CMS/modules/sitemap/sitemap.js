// File: modules/sitemap/sitemap.js
$(function () {
  const $section = $("#sitemap");
  if (!$section.length) {
    return;
  }

  const endpoint = $section.data("endpoint");
  const $regenerateButton = $("#sitemapRegenerate");
  const $statusMessage = $("#sitemapStatusMessage");
  const $entryCount = $("#sitemapEntryCount");
  const $lastGenerated = $("#sitemapLastGenerated");
  const $tableWrapper = $(".sitemap-table");
  const $tableBody = $("#sitemapTableBody");
  const $emptyMessage = $("#sitemapEmptyMessage");

  $("#pageTitle").text("Review sitemap");

  function setLoading(state) {
    if (!$regenerateButton.length) {
      return;
    }
    if (state) {
      $regenerateButton
        .prop("disabled", true)
        .attr("aria-disabled", "true")
        .addClass("is-loading");
      $statusMessage.text("Regenerating sitemap…");
    } else {
      $regenerateButton
        .prop("disabled", false)
        .removeAttr("aria-disabled")
        .removeClass("is-loading");
    }
  }

  function renderEntries(entries) {
    if (!$tableBody.length) {
      return;
    }

    if (!Array.isArray(entries) || entries.length === 0) {
      if ($tableBody.length) {
        $tableBody.empty();
      }
      if ($tableWrapper.length) {
        $tableWrapper.hide();
      }
      if ($emptyMessage.length) {
        $emptyMessage
          .text("No published pages are currently included in the sitemap.")
          .show();
      }
      return;
    }

    if ($emptyMessage.length) {
      $emptyMessage.hide();
    }

    if ($tableWrapper.length) {
      $tableWrapper.show();
    }

    $tableBody.empty();
    entries.forEach(function (entry) {
      const url = entry.url || "";
      const title = entry.title || "";
      const lastmod = entry.lastmodHuman || entry.lastmod || "";
      const $row = $("<tr>");
      $row.append($("<td>").text(title));
      $row.append(
        $("<td>").append(
          $("<a>", {
            href: url,
            text: url,
            target: "_blank",
            rel: "noopener",
          }),
        ),
      );
      $row.append(
        $("<td>").append(
          $("<span>", {
            class: "status-badge status-published",
            text: "Published",
            "aria-label": "Status: Published",
          }),
        ),
      );
      $row.append($("<td>").text(lastmod));
      $tableBody.append($row);
    });
  }

  if ($regenerateButton.length && endpoint) {
    $regenerateButton.on("click", function () {
      setLoading(true);
      $.ajax({
        url: endpoint,
        method: "POST",
        dataType: "json",
      })
        .done(function (response) {
          if (!response) {
            throw new Error("Empty response");
          }
          if (response.success) {
            $statusMessage.text(
              response.message || "Sitemap regenerated successfully.",
            );
            if (response.entryCount !== undefined) {
              $entryCount.text(response.entryCount.toLocaleString());
            }
            if (response.generatedAtFormatted) {
              $lastGenerated.text(response.generatedAtFormatted);
            }
            if (Array.isArray(response.entries)) {
              renderEntries(response.entries);
            }
          } else {
            const errorMessage =
              response.message || "Unable to regenerate sitemap.";
            $statusMessage.text(errorMessage);
          }
        })
        .fail(function () {
          $statusMessage.text("Unable to regenerate sitemap.");
        })
        .always(function () {
          setLoading(false);
        });
    });
  }
});
