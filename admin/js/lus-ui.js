// lus-ui.js
LUS.UI = {
  LoadingState: {
    defaults: {
      spinnerClass: "lus-spinner",
      loadingClass: "is-loading",
      successClass: "is-success",
      errorClass: "is-error",
      originalContentAttr: "data-original-content",
      spinnerPosition: "start",
      disabled: true,
      duration: {
        success: 2000,
        error: 3000,
      },
    },

    /**
     * Show loading state on an element
     * @param {Element|jQuery|string} element
     * @param {string} loadingText
     * @param {Object} options
     */
    show(element, loadingText = "", options = {}) {
      const $el = jQuery(element);
      if (!$el.length) return;

      const settings = { ...this.defaults, ...options };
      const originalContent = $el.html();
      $el.attr(settings.originalContentAttr, originalContent);

      const spinner = jQuery("<span>", {
        class: settings.spinnerClass,
      });

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

      $el
        .html(newContent)
        .addClass(settings.loadingClass)
        .prop("disabled", settings.disabled)
        .data("lusLoadingSettings", settings);
    },

    /**
     * Reset element to original state
     * @param {Element|jQuery|string} element
     */
    hide(element) {
      const $el = jQuery(element);
      if (!$el.length) return;

      const settings = $el.data("lusLoadingSettings");
      if (!settings) return;

      const originalContent = $el.attr(settings.originalContentAttr);

      $el
        .html(originalContent)
        .removeClass(settings.loadingClass)
        .removeAttr(settings.originalContentAttr)
        .removeData("lusLoadingSettings")
        .prop("disabled", false);

      ["successClass", "errorClass"].forEach((className) => {
        if ($el.hasClass(settings[className])) {
          $el.removeClass(settings[className]);
        }
      });
    },

    /**
     * Show success state
     * @param {Element|jQuery|string} element
     * @param {string} message
     * @param {Object} options
     */
    success(element, message, options = {}) {
      const $el = jQuery(element);
      if (!$el.length) return;

      const settings = {
        ...this.defaults,
        icon: "✓",
        ...options,
      };

      $el
        .html(
          `<span class="lus-success-icon">${settings.icon}</span> ${message}`
        )
        .addClass(settings.successClass);

      if (settings.duration.success) {
        setTimeout(() => this.hide(element), settings.duration.success);
      }
    },

    /**
     * Show error state
     * @param {Element|jQuery|string} element
     * @param {string} message
     * @param {Object} options
     */
    error(element, message, options = {}) {
      const $el = jQuery(element);
      if (!$el.length) return;

      const settings = {
        ...this.defaults,
        icon: "⚠",
        ...options,
      };

      $el
        .html(`<span class="lus-error-icon">${settings.icon}</span> ${message}`)
        .addClass(settings.errorClass);

      if (settings.duration.error) {
        setTimeout(() => this.hide(element), settings.duration.error);
      }
    },
  },

  Notices: {
    /**
     * Show notification message
     * @param {string} type success|error|warning|info
     * @param {string} message
     * @param {Object} options
     */
    show(type, message, options = {}) {
      const settings = {
        container: ".wrap:first",
        duration: LUS.Config.UI.NOTIFICATION_TIMEOUT,
        ...options,
      };

      const $notice = jQuery("<div>", {
        class: `notice notice-${type} is-dismissible`,
        html: jQuery("<p>", { text: message }),
      });

      // Add dismiss button if WordPress's dismissible notice JS isn't available
      if (!window.wp || !wp.notices) {
        const $dismissButton = jQuery("<button>", {
          type: "button",
          class: "notice-dismiss",
          html: '<span class="screen-reader-text">Dismiss this notice.</span>',
        }).on("click", function () {
          jQuery(this)
            .closest(".notice")
            .fadeOut(200, function () {
              jQuery(this).remove();
            });
        });

        $notice.append($dismissButton);
      }

      jQuery(settings.container).prepend($notice);

      if (settings.duration) {
        setTimeout(() => {
          $notice.fadeOut(200, function () {
            jQuery(this).remove();
          });
        }, settings.duration);
      }
    },
  },

  Modal: {
    show(content, options = {}) {
      const settings = {
        title: "",
        width: "auto",
        height: "auto",
        buttons: [],
        close: true,
        ...options,
      };

      const $modal = jQuery("<div>", {
        class: "lus-modal",
        html: [
          jQuery("<div>", {
            class: "lus-modal-content",
            css: {
              width: settings.width,
              height: settings.height,
            },
            html: [
              settings.title &&
                jQuery("<div>", {
                  class: "lus-modal-header",
                  html: [
                    jQuery("<h3>", { text: settings.title }),
                    settings.close &&
                      jQuery("<button>", {
                        class: "lus-modal-close",
                        html: "×",
                        on: { click: () => this.hide() },
                      }),
                  ],
                }),
              jQuery("<div>", {
                class: "lus-modal-body",
                html: content,
              }),
              settings.buttons.length > 0 &&
                jQuery("<div>", {
                  class: "lus-modal-footer",
                  html: settings.buttons.map((btn) =>
                    jQuery("<button>", {
                      text: btn.text,
                      class: `button ${btn.class || ""}`,
                      on: { click: btn.click },
                    })
                  ),
                }),
            ],
          }),
        ],
      }).appendTo("body");

      // Add click outside to close
      if (settings.close) {
        $modal.on("click", (e) => {
          if (e.target === $modal[0]) this.hide();
        });
      }

      // Add escape key to close
      if (settings.close) {
        jQuery(document).on("keydown.lusModal", (e) => {
          if (e.key === "Escape") this.hide();
        });
      }

      setTimeout(() => $modal.addClass("show"), 10);
      return $modal;
    },

    hide() {
      const $modal = jQuery(".lus-modal");
      if (!$modal.length) return;

      $modal.removeClass("show");
      setTimeout(() => {
        $modal.remove();
        jQuery(document).off("keydown.lusModal");
      }, LUS.Config.UI.MODAL_ANIMATION_DURATION);
    },
  },

  /**
   * Scroll to element with smooth animation
   * @param {string|Element|jQuery} target
   * @param {Object} options
   */
  scrollTo(target, options = {}) {
    const settings = {
      offset: 50,
      duration: 500,
      ...options,
    };

    const $target = jQuery(target);
    if (!$target.length) return;

    const targetOffset = $target.offset().top - settings.offset;
    jQuery("html, body").animate(
      {
        scrollTop: targetOffset,
      },
      settings.duration
    );
  },

  /**
   * Toggle instructions panel visibility
   * @param {string|Element|jQuery} toggleButton
   * @param {string|Element|jQuery} contentPanel
   */
  toggleInstructions(toggleButton, contentPanel) {
    const $toggleButton = jQuery(toggleButton);
    const $contentPanel = jQuery(contentPanel);

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
