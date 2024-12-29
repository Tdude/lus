/**
 * File: admin/js/lus-ui.js
 */

LUS.UI = {
  LoadingState: {
    /**
     * Show loading state on an element
     */
    show(element, loadingText = "", options = {}) {
      const $el = $(element);
      if (!$el.length) return;

      const defaults = {
        spinnerClass: "lus-spinner",
        loadingClass: "is-loading",
        originalContentAttr: "data-original-content",
        spinnerPosition: "start", // 'start', 'end', or 'replace'
        disabled: true,
      };

      const settings = { ...defaults, ...options };

      // Store original content
      const originalContent = $el.html();
      $el.attr(settings.originalContentAttr, originalContent);

      // Create spinner
      const spinner = $("<span>", {
        class: settings.spinnerClass,
      });

      // Set loading content
      let newContent;
      switch (settings.spinnerPosition) {
        case "end":
          newContent = `${loadingText} ${spinner[0].outerHTML}`;
          break;
        case "replace":
          newContent = spinner[0].outerHTML;
          break;
        default: // 'start'
          newContent = `${spinner[0].outerHTML} ${loadingText}`;
      }

      $el.html(newContent).addClass(settings.loadingClass);

      if (settings.disabled) {
        $el.prop("disabled", true);
      }

      // Store settings for reset
      $el.data("lusLoadingSettings", settings);
    },

    /**
     * Reset element to original state
     */
    hide(element) {
      const $el = $(element);
      if (!$el.length) return;

      const settings = $el.data("lusLoadingSettings");
      if (!settings) return;

      const originalContent = $el.attr(settings.originalContentAttr);

      $el
        .html(originalContent)
        .removeClass(settings.loadingClass)
        .removeAttr(settings.originalContentAttr)
        .removeData("lusLoadingSettings");

      if (settings.disabled) {
        $el.prop("disabled", false);
      }
    },

    /**
     * Show success state
     */
    success(element, message, options = {}) {
      const $el = $(element);
      if (!$el.length) return;

      const defaults = {
        successClass: "is-success",
        duration: 2000,
        icon: "✓",
      };

      const settings = { ...defaults, ...options };

      $el
        .html(
          `<span class="lus-success-icon">${settings.icon}</span> ${message}`
        )
        .addClass(settings.successClass);

      if (settings.duration) {
        setTimeout(() => this.hide(element), settings.duration);
      }
    },

    /**
     * Show error state
     */
    error(element, message, options = {}) {
      const $el = $(element);
      if (!$el.length) return;

      const defaults = {
        errorClass: "is-error",
        duration: 3000,
        icon: "⚠",
      };

      const settings = { ...defaults, ...options };

      $el
        .html(`<span class="lus-error-icon">${settings.icon}</span> ${message}`)
        .addClass(settings.errorClass);

      if (settings.duration) {
        setTimeout(() => this.hide(element), settings.duration);
      }
    },
  },

  /**
   * Toggle instructions panel visibility
   * @param {string|Element} toggleButton Selector or element for the toggle button
   * @param {string|Element} contentPanel Selector or element for the content panel
   */
  toggleInstructions(toggleButton, contentPanel) {
    const $toggleButton = $(toggleButton);
    const $contentPanel = $(contentPanel);

    if ($toggleButton.length && $contentPanel.length) {
      const isVisible = localStorage.getItem("instructionsVisible") === "true";
      if (isVisible) $contentPanel.addClass("show");

      $toggleButton.on("click", function () {
        $contentPanel.toggleClass("show");
        localStorage.setItem(
          "instructionsVisible",
          $contentPanel.hasClass("show")
        );
      });
    }
  },
};
