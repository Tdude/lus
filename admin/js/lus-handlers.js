// File: lus-handlers.php
LUS.Handlers = {
  Generic: {
    init() {
      this.bindEvents();
    },

    bindEvents() {
      // Bind generic actions based on class naming convention
      jQuery(document).on("click", "[class^='lus-edit-']", (e) => {
        e.preventDefault();
        const $button = jQuery(e.currentTarget);
        const action = $button.attr("class").match(/lus-edit-([a-z\-]+)/)[1];
        this.handleEdit(action, $button.data());
      });

      jQuery(document).on("click", "[class^='lus-delete-']", (e) => {
        e.preventDefault();
        const $button = jQuery(e.currentTarget);
        const action = $button.attr("class").match(/lus-delete-([a-z\-]+)/)[1];
        this.handleDelete(action, $button.data("id"), $button);
      });

      jQuery(document).on("submit", "[id^='lus-form-']", (e) => {
        e.preventDefault();
        const $form = jQuery(e.currentTarget);
        const action = $form.attr("id").match(/lus-form-([a-z\-]+)/)[1];
        this.handleSubmit(action, $form);
      });

      jQuery(document).on("click", "[id^='lus-cancel-edit-']", (e) => {
        const action = jQuery(e.currentTarget)
          .attr("id")
          .match(/lus-cancel-edit-([a-z\-]+)/)[1];
        this.resetForm(action);
      });
    },

    handleEdit(action, data) {
      const prefix = `lus-${action}`;
      jQuery(`#${prefix}_id`).val(data.id);
      jQuery(`#${prefix}_text`).val(data.text);
      // Add more fields as needed

      jQuery(`#lus-form-title-${action}`).text(
        LUS.Strings[`edit${this.capitalize(action)}`]
      );
      jQuery(`#lus-cancel-edit-${action}`).show();
      jQuery(`#submit-${action}`).val(
        LUS.Strings[`update${this.capitalize(action)}`]
      );
      LUS.UI.scrollTo(`#lus-form-${action}`);
    },

    handleDelete(action, id, $button) {
      if (!confirm(LUS.Strings[`confirmDelete${this.capitalize(action)}`])) {
        return;
      }

      LUS.UI.LoadingState.show($button, LUS.Strings.deleting, {
        spinnerPosition: "replace",
      });

      LUS.Data.request(`delete_${action}`, { id })
        .then((response) => {
          const $row = $button.closest("tr");
          $row.fadeOut(400, () => {
            $row.remove();
            LUS.UI.Notices.show("success", response.message);
            if (jQuery(`.lus-${action}s-list tbody tr`).length === 0) {
              location.reload();
            }
          });
        })
        .catch((error) => {
          LUS.UI.LoadingState.error($button, error.message);
        });
    },

    handleSubmit(action, $form) {
      const $submitButton = $form.find(`#submit-${action}`);
      const isEdit = jQuery(`#lus-${action}_id`).val() !== "";

      LUS.UI.LoadingState.show($submitButton, LUS.Strings.saving);

      const formData = new FormData($form[0]);
      formData.append(
        "action",
        isEdit ? `lus_update_${action}` : `lus_create_${action}`
      );
      formData.append("nonce", LUS.nonce);

      fetch(LUS.ajaxurl, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
        .then((response) => response.json())
        .then((response) => {
          if (!response.success) throw new Error(response.data.message);
          LUS.UI.LoadingState.success($submitButton, response.data.message);
          if (!isEdit) this.resetForm(action);
          setTimeout(() => location.reload(), 1000);
        })
        .catch((error) => {
          LUS.UI.LoadingState.error($submitButton, error.message);
        });
    },

    resetForm(action) {
      const prefix = `lus-${action}`;
      const $form = jQuery(`#lus-form-${action}`);

      $form[0].reset();
      jQuery(`#${prefix}_id`).val("");

      jQuery(`#lus-form-title-${action}`).text(
        LUS.Strings[`addNew${this.capitalize(action)}`]
      );
      jQuery(`#lus-cancel-edit-${action}`).hide();
      jQuery(`#submit-${action}`).val(
        LUS.Strings[`save${this.capitalize(action)}`]
      );

      jQuery(".notice").fadeOut(400, function () {
        jQuery(this).remove();
      });
    },

    capitalize(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    },
  },

  // Handlers...
  Charts: {
    init: () => {
      console.log("Charts handler initialized");
      // Add specific initialization logic here
    },
  },
  Passages: {
    init: () => {
      console.log("Passages handler initialized");
      // Add specific initialization logic here
    },
  },
  Questions: {
    init: () => {
      console.log("Questions handler initialized");
      // Add specific initialization logic here
    },
  },
  Recordings: {
    init: () => {
      console.log("Recordings handler initialized");
      // Add specific initialization logic here
    },
  },
  Results: {
    init: () => {
      console.log("Results handler initialized");
      // Add specific initialization logic here
    },
  },

  Initializer: {
    initializers: {
      // Include the generic handler
      generic: {
        condition: () =>
          document.querySelector(
            "[class^='lus-edit-'], [class^='lus-delete-'], [id^='lus-form-'], [id^='lus-cancel-edit-']"
          ),
        action: () => {
          try {
            LUS.Handlers.Generic.init();
          } catch (error) {
            console.error("Failed to initialize Generic handler:", error);
          }
        },
      },
      questions: {
        condition: () => document.querySelector("#lus-question-form"),
        action: async () => {
          try {
            // Dynamically import and initialize module
            const { init } = await import("./lus-questions.js");
            init();
          } catch (error) {
            console.error("Failed to initialize Questions handler:", error);
          }
        },
      },
      recordings: {
        condition: () => document.querySelector("#lus-recordings-form"),
        action: async () => {
          try {
            // Dynamically import and initialize
            const { init } = await import("./lus-recordings.js");
            init();
          } catch (error) {
            console.error("Failed to initialize Recordings handler:", error);
          }
        },
      },
      passages: {
        condition: () => document.querySelector("#lus-passage-form"),
        action: () => {
          try {
            LUS.Handlers.Passages.init();
          } catch (error) {
            console.error("Failed to initialize Passages handler:", error);
          }
        },
      },
      instructions: {
        condition: () =>
          document.querySelector("#toggle-instructions") &&
          document.querySelector("#instructions-content"),
        action: () => {
          try {
            LUS.UI.toggleInstructions(
              "#toggle-instructions",
              "#instructions-content"
            );
          } catch (error) {
            console.error("Failed to initialize Instructions handler:", error);
          }
        },
      },
    },

    runByKey(keys) {
      keys.forEach((key) => {
        const initializer = this.initializers[key];
        if (initializer && initializer.condition()) {
          try {
            initializer.action();
          } catch (error) {
            console.error(`Error running initializer for ${key}:`, error);
          }
        }
      });
    },

    runAll() {
      Object.keys(this.initializers).forEach((key) => {
        const initializer = this.initializers[key];
        if (initializer.condition()) {
          try {
            initializer.action();
          } catch (error) {
            console.error(`Error running initializer for ${key}:`, error);
          }
        }
      });
    },
  },
};

// Auto-run on DOMContentLoaded
document.addEventListener("DOMContentLoaded", () => {
  LUS.Handlers.Initializer.runByKey(["generic", "passages", "instructions"]);
  // Example: Run all initializers
  // LUS.Handlers.Initializer.runAll();
});
