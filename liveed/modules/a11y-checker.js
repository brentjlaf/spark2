// File: a11y-checker.js
(function ($) {
  /**
   * Floating trigger button that lets editors launch a full accessibility scan
   * without leaving the canvas. The button lives off screen until it is
   * animated into view by the toggle handler or keyboard shortcut.
   */
  const checkerBtn = $(
    '<div class="accessibility-checker" role="button" aria-label="Check Accessibility">Check Accessibility</div>'
  );
  $('body').append(checkerBtn);

  /**
   * Slide the floating trigger button in and out of view. We keep the button in
   * the DOM at all times but animate its `right` position so screen readers can
   * still reach it when visible.
   */
  function toggleAccessibilityChecker(){
    if (checkerBtn.is(':visible')){
      checkerBtn.animate({ right: '-150px' }, 500, function(){ checkerBtn.hide(); });
    } else {
      checkerBtn.show().animate({ right: '20px' }, 500);
    }
  }

  // Wire up an editor friendly keyboard shortcut (mouse down + "a") so power
  // users can quickly launch the checker without hunting for the button.
  $(document).on('mousedown', function(e){
    if(e.button === 0){
      $(document).on('keydown.accessibilityCheck', function(ev){
        if(ev.key.toLowerCase() === 'a'){ toggleAccessibilityChecker(); }
      });
    }
  });
  $(document).on('mouseup', (e) => {
    if (e.button === 0) {
      $(document).off('keydown.accessibilityCheck');
    }
  });

  /**
   * Visually decorate an element with an accessibility warning. We add a CSS
   * class, set an aria-label for screen reader context and inject a helper span
   * that contains the human readable error copy.
   */
  function addError($el, message, cls = 'highlight-issue') {
    $el.addClass(cls).attr('aria-label', message);
    $('<span>')
      .addClass(`error-message ${cls}`.trim())
      .text(message)
      .insertAfter($el);
  }

  /**
   * Remove any previous highlighting artifacts so each scan presents a clean
   * state before populating new results.
   */
  function clearHighlights($root) {
    $root
      .find('.highlight-issue, .highlight-htag')
      .removeClass('highlight-issue highlight-htag')
      .removeAttr('aria-label');
    $root.find('.accessibility-bubble').remove();
  }

  /**
   * Convenience wrapper that applies the same error message to a collection of
   * matched elements. Used for simple one-off attribute checks.
   */
  function checkIssues($elements, issue) {
    addError($elements, issue);
  }

  /**
   * Ensure the document uses a valid heading hierarchy. Each heading receives a
   * bubble showing the tag level and any skips in the outline are flagged.
   */
  function checkHeadings($root) {
    if ($root.find('h1').length === 0) {
      $('<h1 class="error-message">Missing h1 tag</h1>').prependTo($root);
    }
    const headingLevels = { H1: 1, H2: 2, H3: 3, H4: 4, H5: 5, H6: 6 };
    let last = 0;
    $root.find('h1, h2, h3, h4, h5, h6').each(function () {
      const $t = $(this);
      const cur = headingLevels[$t.prop('tagName')];
      const tag = $t.prop('tagName');
      $t.addClass('highlight-htag');
      if (cur > last + 1) {
        addError(
          $t,
          'Heading is out of order. Ensure proper heading hierarchy.',
          'highlight-htag'
        );
        $t.append('<span class="accessibility-bubble red">' + tag + '</span>');
      } else {
        $t.append('<span class="accessibility-bubble green">' + tag + '</span>');
      }
      last = cur;
    });
  }

  /**
   * Warn about links that have meaningful destinations but unhelpful or missing
   * text content.
   */
  function checkLinks($root){
    $root.find('a').each(function () {
      const txt = $(this).text().trim();
      const href = $(this).attr('href');
      if (
        href &&
        href !== '#' &&
        href !== '/' &&
        !href.includes('#') &&
        !href.includes('javascript:')
      ) {
        if (!txt || txt === 'click here') {
          addError(
            $(this),
            'Non-descriptive link text. Use descriptive text that provides context for the link.'
          );
        }
      }
    });
  }

  /**
   * Convert hex or rgba CSS color strings into RGB arrays so we can perform
   * contrast calculations.
   */
  function parseColor(color) {
    let match;
    if ((match = color.match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i))) {
      return [
        parseInt(match[1], 16),
        parseInt(match[2], 16),
        parseInt(match[3], 16),
      ];
    } else if (
      (match = color.match(
        /^rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+(?:\.\d+)?))?\)$/
      ))
    ) {
      return [parseInt(match[1]), parseInt(match[2]), parseInt(match[3])];
    }
    return null;
  }
  /**
   * Helper that normalizes a single color channel to linear lightness space as
   * required by the WCAG luminance formula.
   */
  function adjustColor(val) {
    val = val / 255;
    return val <= 0.03928 ? val / 12.92 : Math.pow((val + 0.055) / 1.055, 2.4);
  }
  /**
   * Calculate relative luminance for any supported color string.
   */
  function getRelativeLuminance(color) {
    const rgb = parseColor(color);
    if (!rgb) return 1;
    const r = adjustColor(rgb[0]);
    const g = adjustColor(rgb[1]);
    const b = adjustColor(rgb[2]);
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  }
  /**
   * Return the WCAG contrast ratio between two color values.
   */
  function calculateContrastRatio(c1, c2) {
    const l1 = getRelativeLuminance(c1);
    const l2 = getRelativeLuminance(c2);
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  /**
   * Flag any element where the text and background colors fall below 4.5:1,
   * the standard contrast ratio for normal sized text.
   */
  function checkColorContrast($root) {
    $root.find('*').each(function () {
      const bg = $(this).css('background-color');
      const col = $(this).css('color');
      if (bg && col) {
        const ratio = calculateContrastRatio(bg, col);
        if (ratio < 4.5) {
          addError(
            $(this),
            'Insufficient color contrast. Ensure text is readable against the background color.'
          );
        }
      }
    });
  }

  /**
   * Require form controls to have matching `<label>` elements referenced via
   * the `for` attribute.
   */
  function checkFormLabels($root){
    $root.find('input, select, textarea').each(function () {
      const label = $('label[for="' + $(this).attr('id') + '"]');
      if (!label.length) {
        addError(
          $(this),
          'Missing associated label. Each form control must have a corresponding label.'
        );
      }
    });
  }

  /**
   * Warn when the base document language is missing since that impacts screen
   * reader pronunciation.
   */
  function checkLangAttribute() {
    if (!$('html').attr('lang')) {
      addError(
        $('html'),
        'Missing language attribute on HTML element. Use the "lang" attribute to specify the language of the page content.'
      );
    }
  }

  /**
   * Encourage authors to replace non-semantic containers with semantic
   * structural elements when appropriate.
   */
  function checkSemanticHTML($root){
    $root.find('*').each(function () {
      const tag = $(this).prop('tagName').toLowerCase();
      if (
        ['div', 'span'].includes(tag) &&
        !['header', 'nav', 'main', 'footer'].includes(tag)
      ) {
        addError(
          $(this),
          'Use of non-semantic HTML. Consider using semantic elements like <header>, <nav>, <main>, <footer>, etc.'
        );
      }
    });
  }

  /**
   * Remind editors to include <track> captions for audio and video content.
   */
  function checkMediaAccessibility($root){
    $root.find('video, audio').each(function () {
      if (!$(this).find('track').length) {
        addError(
          $(this),
          'Missing captions or transcripts for multimedia content. Ensure accessibility for users with visual or hearing impairments.'
        );
      }
    });
  }

  /**
   * Verify that text can scale to 200% without breaking layout by temporarily
   * scaling a hidden probe element.
   */
  function checkTextResizing() {
    $('body').append(
      '<div class="text-resize-test" style="font-size:100%;position:absolute;left:-9999px;">Text resizing test</div>'
    );
    const originalSize = $('.text-resize-test').css('font-size');
    $('.text-resize-test').css('font-size', '200%');
    if ($('.text-resize-test').css('font-size') !== originalSize) {
      addError(
        $('.text-resize-test'),
        'Text resizing issue. Ensure text can be resized up to 200% without loss of content or functionality.'
      );
    }
    $('.text-resize-test').remove();
  }

  /**
   * Ensure the viewport meta tag exists and supports responsive scaling.
   */
  function checkResponsiveDesign() {
    if (!$('meta[name="viewport"]').length) {
      $('head').append(
        '<meta name="viewport" content="width=device-width, initial-scale=1">'
      );
    }
    const viewportMeta = $('meta[name="viewport"]').attr('content');
    if (!viewportMeta.includes('width=device-width')) {
      addError(
        $('meta[name="viewport"]'),
        'Missing viewport settings. Ensure content is accessible on different screen sizes and orientations.'
      );
    }
  }

  let keyboardBound = false;
  /**
   * Monitor keyboard focus so we can remind editors to provide tabbable
   * experiences. The handler is only bound once per session.
   */
  function checkKeyboardNavigation($root) {
    if (keyboardBound) return;
    $root.on('keydown.a11y', '*', function (e) {
      if (e.which === 9) {
        addError(
          $(this),
          'Keyboard navigation issue. Ensure all interactive elements are accessible via keyboard navigation.'
        );
      }
    });
    keyboardBound = true;
  }

  /**
   * Highlight whichever nodes currently have focus to expose potential focus
   * traps or invisible focus states.
   */
  function checkFocusManagement() {
    $(':focus').each(function () {
      addError(
        $(this),
        'Focus management issue. Ensure focus is managed correctly and visually noticeable.'
      );
    });
  }

  /**
   * Make sure notification widgets announce themselves appropriately by having
   * an ARIA role.
   */
  function checkNotifications($root) {
    $root.find('.alert, .notification').each(function () {
      if (!$(this).attr('role')) {
        $(this).attr('role', 'alert');
        addError(
          $(this),
          'Missing role attribute for notifications. Ensure alerts and notifications are accessible and announced by screen readers.'
        );
      }
    });
  }

  let dynamicObserver;
  /**
   * Observe DOM mutations to encourage authors to announce dynamic updates.
   * Only one observer is created so repeated scans do not spawn duplicates.
   */
  function checkDynamicContentUpdates() {
    if (dynamicObserver) return;
    dynamicObserver = new MutationObserver((mutations) => {
      mutations.forEach((m) => {
        if (m.addedNodes.length > 0) {
          $(m.addedNodes).each(function () {
            addError(
              $(this),
              'Dynamic content update. Ensure updates to the content are announced to screen reader users.'
            );
          });
        }
      });
    });
    dynamicObserver.observe(document.body, { childList: true, subtree: true });
  }

  /**
   * Inject a skip link at the top of the body to support quick keyboard
   * navigation to the main content area.
   */
  function addSkipLink() {
    if (!$('a.skip-main').length) {
      $('<a class="skip-main" href="#main-content">Skip to main content</a>').prependTo('body');
    }
  }

  /**
   * Master orchestration routine that performs every accessibility heuristic on
   * the builder canvas, starting from a clean slate.
   */
  function runChecks() {
    const $root = $('#canvas');
    clearHighlights($root);
    $('.error-message').remove();
    checkIssues($root.find('img:not([alt])'),'Missing alt attribute');
    checkIssues($root.find('img[alt=""]'),'Empty alt attribute');
    checkIssues($root.find(':button:not([aria-label]), :input:not([aria-label])'),'Missing aria-label attribute');
    checkIssues($root.find('input:not([id])'),'Missing id attribute');
    checkHeadings($root);
    checkLinks($root);
    checkColorContrast($root);
    checkFormLabels($root);
    checkLangAttribute();
    checkSemanticHTML($root);
    checkMediaAccessibility($root);
    checkTextResizing();
    checkResponsiveDesign();
    checkKeyboardNavigation($root);
    checkFocusManagement();
    checkNotifications($root);
    checkDynamicContentUpdates();
    addSkipLink();
  }

  // Expose the checker so other scripts (and manual console use) can invoke it.
  window.runAccessibilityCheck = runChecks;

  // Trigger the scan from both the floating button and any inline action
  // buttons that opt in via the `a11y-check-btn` class.
  checkerBtn.on('click', runChecks);
  $('.a11y-check-btn').on('click', runChecks);
})(jQuery);
