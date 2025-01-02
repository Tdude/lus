/**
 * File: admin/js/handlers/lus-questions-handler.js
 */

LUS.Handlers.Questions = {
  init() {
    this.bindEvents();
  },

  bindEvents() {
    // Edit question
    jQuery(".lus-edit-question").on("click", (e) => {
      e.preventDefault();
      const $button = jQuery(e.currentTarget);
      this.handleEdit($button.data());
    });

    // Delete question
    jQuery(".lus-delete-question").on("click", (e) => {
      e.preventDefault();
      const $button = jQuery(e.currentTarget);
      this.handleDelete($button.data("id"), $button);
    });

    // Cancel edit
    jQuery("#lus-cancel-edit").on("click", () => {
      this.resetForm();
    });

    // Form submission
    jQuery("#lus-question-form").on("submit", (e) => {
      e.preventDefault();
      this.handleSubmit(e);
    });
  },

  handleEdit(questionData) {
    // Update form with question data
    jQuery("#question_id").val(questionData.id);
    jQuery("#question_text").val(questionData.question);
    jQuery("#correct_answer").val(questionData.answer);
    jQuery("#weight").val(questionData.weight);

    console.log(LUS.Strings.loading); // Outputs: "Laddar..."
    console.log(LUS.Strings.passages.title); // Outputs: "Texter"
    // Update form state
    // The form's id is lus-question-form
    jQuery("#lus-form-title").text(LUS.Strings.editQuestion);
    jQuery("#lus-cancel-edit").show();
    jQuery("#submit").val(LUS.Strings.updateQuestion);

    // Scroll to form
    LUS.UI.scrollTo("#lus-question-form");
  },

  handleDelete(questionId, $button) {
    if (!confirm(LUS.Strings.confirmDeleteQuestion)) {
      return;
    }

    LUS.UI.LoadingState.show($button, LUS.Strings.deleting, {
      spinnerPosition: "replace",
    });

    LUS.Data.request("delete_question", {
      question_id: questionId,
    })
      .then((response) => {
        const $row = $button.closest("tr");
        $row.fadeOut(400, function () {
          $row.remove();
          LUS.UI.Notices.show("success", response.message);

          // Check if no questions left
          if (jQuery(".lus-questions-list tbody tr").length === 0) {
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
    const isEdit = jQuery("#question_id").val() !== "";

    // Show loading state
    LUS.UI.LoadingState.show($submitButton, LUS.Strings.saving);

    const formData = new FormData($form[0]);
    formData.append(
      "action",
      isEdit ? "lus_update_question" : "lus_create_question"
    );
    formData.append("nonce", LUS.nonce);

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

        // Reset form if this was a new question
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
    const $form = jQuery("#lus-question-form");

    // Reset form and hidden fields
    $form[0].reset();
    jQuery("#question_id").val("");

    // Reset form state
    jQuery("#lus-form-title").text(LUS.Strings.addNewQuestion);
    jQuery("#lus-cancel-edit").hide();
    jQuery("#submit").val(LUS.Strings.saveQuestion);

    // Clear notices
    jQuery(".notice").fadeOut(400, function () {
      jQuery(this).remove();
    });
  },
};

// Initialize when document is ready
document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector("#lus-question-form")) {
    LUS.Handlers.Questions.init();
  }
});
