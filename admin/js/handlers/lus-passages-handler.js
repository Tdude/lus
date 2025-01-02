/**
 * File: admin/js/handlers/lus-passages-handler.js
 * Handles all passage management interactions
 */

LUS.Handlers.Passages = {
  init() {
    this.bindEvents();
    this.setupWysiwygEditor();
  },

  bindEvents() {
    // Edit passage
    jQuery(".lus-edit-passage").on("click", (e) => {
      e.preventDefault();
      const $button = jQuery(e.currentTarget);
      this.handleEdit($button.data());
    });

    // Delete passage
    jQuery(".lus-delete-passage").on("click", (e) => {
      e.preventDefault();
      const jQuerybutton = jQuery(e.currentTarget);
      this.handleDelete($button.data("id"), $button.data("title"), $button);
    });

    // Cancel edit
    jQuery("#lus-cancel-edit").on("click", () => {
      this.resetForm();
    });

    // Form submission
    jQuery("#lus-passage-form").on("submit", (e) => {
      e.preventDefault();
      this.handleSubmit(e);
    });
  },

  setupWysiwygEditor() {
    if (typeof tinyMCE !== "undefined") {
      tinyMCE.on("AddEditor", (e) => {
        e.editor.on("change", () => {
          e.editor.save(); // Update textarea content
        });
      });
    }
  },

  handleEdit(passageData) {
    // Show text strings from strings-lus-admin
    console.log(LUS.Strings.loading); // Outputs: Laddar...
    console.log(LUS.Strings.passages.title); // Outputs: Texter
    LUS.UI.LoadingState.show("#lus-form-title", LUS.Strings.loading);

    LUS.Data.request("get_passage", {
      passage_id: passageData.id,
    })
      .then((passage) => {
        // Populate form
        jQuery("#passage_id").val(passage.id);
        jQuery("#title").val(passage.title);
        jQuery("#time_limit").val(passage.time_limit);
        jQuery("#difficulty_level").val(passage.difficulty_level);

        // Update TinyMCE if available
        if (typeof tinyMCE !== "undefined" && tinyMCE.get("content")) {
          tinyMCE.get("content").setContent(passage.content);
        } else {
          jQuery("#content").val(passage.content);
        }

        // Update form state
        jQuery("#lus-form-title").text(LUS.Strings.editPassage);
        jQuery("#lus-cancel-edit").show();
        jQuery("#submit").val(LUS.Strings.updatePassage);

        // Scroll to form
        LUS.UI.scrollTo("#lus-passage-form");

        LUS.UI.LoadingState.hide("#lus-form-title");
      })
      .catch((error) => {
        LUS.UI.Notices.show("error", error.message);
        LUS.UI.LoadingState.hide("#lus-form-title");
      });
  },

  handleDelete(passageId, passageTitle, $button) {
    if (
      !confirm(LUS_Strings.confirmDeletePassage.replace("%s", passageTitle))
    ) {
      return;
    }

    // Show loading state
    LUS.UI.LoadingState.show($button, LUS_Strings.deleting);

    // Make AJAX request
    fetch(ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams({
        action: "delete_passage",
        passage_id: passageId,
        nonce: lusStrings.nonce,
      }),
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.success) {
          throw new Error(response.data.message);
        }
        // Remove the row from the table
        const $row = $button.closest("tr");
        $row.fadeOut(400, function () {
          $row.remove();
          LUS.UI.LoadingState.success($button, response.data.message);

          // Reload if no rows left
          if (jQuery(".lus-passages-list tbody tr").length === 0) {
            location.reload();
          }
        });
      })
      .catch((error) => {
        LUS.UI.LoadingState.error($button, error.message);
      });
  },

  handleSubmit(e) {
    const $form = jQuery(e.currentTarget);
    const $submitButton = $form.find("#submit");
    const isEdit = jQuery("#passage_id").val() !== "";

    // Show loading state
    LUS.UI.LoadingState.show($submitButton, LUS.Strings.saving);

    // Get form data including editor content
    const formData = new FormData($form[0]);
    if (typeof tinyMCE !== "undefined" && tinyMCE.get("content")) {
      formData.set("content", tinyMCE.get("content").getContent());
    }

    // Add action based on whether we're editing or creating
    formData.append(
      "action",
      isEdit ? "lus_update_passage" : "lus_create_passage"
    );
    formData.append("nonce", LUS.nonce);

    // Make AJAX request
    fetch(LUS.ajaxurl, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.success) {
          throw new Error(response.data.message);
        }

        LUS.UI.LoadingState.success($submitButton, response.data.message);

        // Reset form if this was a new passage
        if (!isEdit) {
          this.resetForm();
        }

        // Refresh page after short delay to show updated list
        setTimeout(() => location.reload(), 1000);
      })
      .catch((error) => {
        LUS.UI.LoadingState.error($submitButton, error.message);
      });
  },

  resetForm() {
    const jQueryform = $("#lus-passage-form");

    // Reset hidden fields and regular inputs
    $form[0].reset();
    jQuery("#passage_id").val("");

    // Reset TinyMCE if available
    if (typeof tinyMCE !== "undefined" && tinyMCE.get("content")) {
      tinyMCE.get("content").setContent("");
    }

    // Reset form state
    jQuery("#lus-form-title").text(LUS.Strings.addNewPassage);
    jQuery("#lus-cancel-edit").hide();
    jQuery("#submit").val(LUS.Strings.savePassage);

    // Clear any notices
    jQuery(".notice").fadeOut(400, function () {
      jQuery(this).remove();
    });
  },
};

// Initialize
document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector("#lus-passage-form")) {
    LUS.Handlers.Passages.init();
  }
});
