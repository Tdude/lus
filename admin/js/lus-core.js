// lus-core.js
var LUS = LUS || {};
LUS.Handlers = LUS.Handlers || {};
LUS.UI = LUS.UI || {};
LUS.Utils = LUS.Utils || {};
LUS.Data = LUS.Data || {};
LUS.Strings =
  LUS.Strings || (typeof LUSStrings !== "undefined" ? LUSStrings : {});

// Set nonce from LUSStrings if available
LUS.nonce = LUS.Strings.nonce || null;

// Log to confirm nonce
console.log("Loaded nonce:", LUS.nonce);

// Configuration
LUS.Config = {
  ENDPOINTS: {
    AJAX: "/wp-admin/admin-ajax.php",
  },
  UI: {
    NOTIFICATION_TIMEOUT: 5000,
    MODAL_ANIMATION_DURATION: 300,
  },
};

console.log("AJAX endpoint:", LUS.Config.ENDPOINTS.AJAX);

// Data handling utilities
LUS.Data = {
  request: async function (action, data = {}) {
    const formData = new FormData();

    // If data is already FormData, merge it
    if (data instanceof FormData) {
      for (let [key, value] of data.entries()) {
        formData.append(key, value);
      }
    } else {
      // Convert plain object to FormData entries
      Object.entries(data).forEach(([key, value]) => {
        formData.append(key, value);
      });
    }

    // Log FormData content (Step 1)
    console.log("FormData content after initialization:");
    for (let [key, value] of formData.entries()) {
      console.log(`${key}: ${value}`);
    }

    // Add action and nonce
    formData.append(
      "action",
      action.startsWith("lus_") ? action : `lus_${action}`
    );
    formData.append("nonce", LUS.nonce);

    // Log FormData content (Step 2)
    console.log("FormData content after adding action and nonce:");
    for (let [key, value] of formData.entries()) {
      console.log(`${key}: ${value}`);
    }

    try {
      const response = await fetch(LUS.Config.ENDPOINTS.AJAX, {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      });

      const json = await response.json();
      if (!response.ok || !json.success) {
        throw new Error(json.data?.message || "Request failed");
      }
      return json.data;
    } catch (error) {
      console.error("AJAX Request Error:", error);
      throw error;
    }
  },

  // Utility to extract all data attributes from an element
  extractDataAttributes: function (element) {
    const dataset = element.dataset;
    const data = {};

    for (let key in dataset) {
      // Convert data-kebab-case to camelCase
      const camelKey = key.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
      data[camelKey] = dataset[key];
    }

    return data;
  },
};

// Base Handler Class
LUS.BaseHandler = {
  init() {
    this.bindCommonEvents();
    if (typeof this.bindSpecificEvents === "function") {
      this.bindSpecificEvents();
    }
  },

  bindCommonEvents() {
    const $container = this.container ? $(this.container) : $(document);
    const itemType = this.itemType;

    // Edit button handler
    $container.on("click", `.lus-edit-${itemType}`, (e) => {
      e.preventDefault();
      const $button = $(e.currentTarget);
      this.handleEdit(LUS.Data.extractDataAttributes($button[0]));
    });

    // Delete button handler
    $container.on("click", `.lus-delete-${itemType}`, (e) => {
      e.preventDefault();
      const $button = $(e.currentTarget);
      this.handleDelete($button.data("id"), $button);
    });

    // Cancel edit handler
    $container.on("click", "#lus-cancel-edit", () => this.resetForm());

    // Form submission handler
    $container.on("submit", `#lus-${itemType}-form`, (e) => {
      e.preventDefault();
      this.handleSubmit(e);
    });
  },

  handleEdit(itemData) {
    LUS.UI.LoadingState.show("#lus-form-title", LUS.Strings.loading);

    // For items requiring additional data loading
    if (this.shouldFetchData) {
      LUS.Data.request(`get_${this.itemType}`, {
        [`${this.itemType}_id`]: itemData.id,
      })
        .then((data) => this.populateForm(data))
        .catch((error) => {
          LUS.UI.Notices.show("error", error.message);
          LUS.UI.LoadingState.hide("#lus-form-title");
        });
    } else {
      this.populateForm(itemData);
      LUS.UI.LoadingState.hide("#lus-form-title");
    }
  },

  populateForm(data) {
    // Populate all form fields based on data attributes
    const formFields = this.getFormFields();
    formFields.forEach((field) => {
      const $field = $(`#${field}`);
      if ($field.length) {
        if (typeof tinyMCE !== "undefined" && tinyMCE.get(field)) {
          tinyMCE.get(field).setContent(data[field] || "");
        } else {
          $field.val(data[field] || "");
        }
      }
    });

    this.updateFormState("edit");
    LUS.UI.scrollTo(`#lus-${this.itemType}-form`);
  },

  handleDelete(itemId, $button) {
    const confirmMessage = this.getDeleteConfirmMessage(itemId);
    if (!confirm(confirmMessage)) {
      return;
    }

    LUS.UI.LoadingState.show($button, LUS.Strings.deleting);

    LUS.Data.request(`delete_${this.itemType}`, {
      [`${this.itemType}_id`]: itemId,
    })
      .then((response) => {
        const $row = $button.closest("tr");
        $row.fadeOut(400, () => {
          $row.remove();
          LUS.UI.Notices.show("success", response.message);

          if (!$(this.listSelector + " tbody tr").length) {
            location.reload();
          }
        });
      })
      .catch((error) => LUS.UI.LoadingState.error($button, error.message));
  },

  handleSubmit(e) {
    const $form = $(e.currentTarget);
    const $submitButton = $form.find('input[type="submit"]');
    const isEdit = $(`#${this.itemType}_id`).val() !== "";

    LUS.UI.LoadingState.show($submitButton, LUS.Strings.saving);

    const formData = new FormData($form[0]);

    // Special handling for TinyMCE if present
    if (typeof tinyMCE !== "undefined") {
      this.getFormFields().forEach((field) => {
        const editor = tinyMCE.get(field);
        if (editor) {
          formData.set(field, editor.getContent());
        }
      });
    }

    LUS.Data.request(
      isEdit ? `update_${this.itemType}` : `create_${this.itemType}`,
      formData
    )
      .then((response) => {
        LUS.UI.LoadingState.success($submitButton, response.message);
        if (!isEdit) this.resetForm();
        setTimeout(() => location.reload(), 1000);
      })
      .catch((error) =>
        LUS.UI.LoadingState.error($submitButton, error.message)
      );
  },

  resetForm() {
    const $form = $(`#lus-${this.itemType}-form`);
    $form[0].reset();
    $(`#${this.itemType}_id`).val("");

    // Reset TinyMCE editors if any
    if (typeof tinyMCE !== "undefined") {
      this.getFormFields().forEach((field) => {
        const editor = tinyMCE.get(field);
        if (editor) {
          editor.setContent("");
        }
      });
    }

    this.updateFormState("create");
    $(".notice").fadeOut(400, function () {
      $(this).remove();
    });
  },

  updateFormState(mode) {
    const isEdit = mode === "edit";
    $("#lus-form-title").text(
      LUS.Strings[isEdit ? `edit${this.itemType}` : `addNew${this.itemType}`]
    );
    $("#lus-cancel-edit").toggle(isEdit);
    $('input[type="submit"]').val(
      LUS.Strings[isEdit ? `update${this.itemType}` : `save${this.itemType}`]
    );
  },

  getDeleteConfirmMessage(itemId) {
    const message = LUS.Strings[`confirmDelete${this.itemType}`];
    return message.replace("%s", itemId);
  },
};

// Specific Handlers
LUS.Handlers.Questions = Object.create(LUS.BaseHandler);
Object.assign(LUS.Handlers.Questions, {
  itemType: "question",
  container: "#lus-questions-container",
  formSelector: "#lus-question-form",
  listSelector: ".lus-questions-list",
  shouldFetchData: false,

  getFormFields() {
    return ["question_text", "correct_answer", "weight"];
  },
});

LUS.Handlers.Passages = Object.create(LUS.BaseHandler);
Object.assign(LUS.Handlers.Passages, {
  itemType: "passage",
  container: "#lus-passages-container",
  formSelector: "#lus-passage-form",
  listSelector: ".lus-passages-list",
  shouldFetchData: true,

  getFormFields() {
    return ["title", "content", "time_limit", "difficulty_level"];
  },

  bindSpecificEvents() {
    this.setupWysiwygEditor();
  },

  setupWysiwygEditor() {
    if (typeof tinyMCE !== "undefined") {
      tinyMCE.on("AddEditor", (e) => {
        e.editor.on("change", () => e.editor.save());
      });
    }
  },
});

// Initialize handlers on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  // Initialize all available handlers
  Object.keys(LUS.Handlers).forEach((handler) => {
    if (
      typeof LUS.Handlers[handler].init === "function" &&
      document.querySelector(LUS.Handlers[handler].container)
    ) {
      LUS.Handlers[handler].init();
    }
  });
});
