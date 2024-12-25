/**
 * File: admin/js/handlers/lus-questions-handler.js
 */

LUS.Handlers.Questions = {
  init() {
    this.bindEvents();
  },

  bindEvents() {
    // Edit question
    $(".lus-edit-question").on("click", (e) => {
      e.preventDefault();
      const $button = $(e.currentTarget);
      this.handleEdit($button.data());
    });

    // Delete question
    $(".lus-delete-question").on("click", (e) => {
      e.preventDefault();
      const $button = $(e.currentTarget);
      this.handleDelete($button.data("id"), $button);
    });

    // Cancel edit
    $("#lus-cancel-edit").on("click", () => {
      this.resetForm();
    });

    // Form submission
    $("#lus-question-form").on("submit", (e) => {
      e.preventDefault();
      this.handleSubmit(e);
    });
  },

  handleEdit(questionData) {
    // Update form with question data
    $("#question_id").val(questionData.id);
    $("#question_text").val(questionData.question);
    $("#correct_answer").val(questionData.answer);
    $("#weight").val(questionData.weight);

    // Update form state
    $("#lus-form-title").text(LUS.Strings.editQuestion);
    $("#lus-cancel-edit").show();
    $("#submit").val(LUS.Strings.updateQuestion);

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
          if ($(".lus-questions-list tbody tr").length === 0) {
            location.reload();
          }
        });
      })
      .catch((error) => {
        LUS.UI.LoadingState.error($button, error.message);
      });
  },

  handleSubmit(e) {
    const $form = $(e.currentTarget);
    const $submitButton = $form.find("#submit");
    const isEdit = $("#question_id").val() !== "";

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
    const $form = $("#lus-question-form");

    // Reset form and hidden fields
    $form[0].reset();
    $("#question_id").val("");

    // Reset form state
    $("#lus-form-title").text(LUS.Strings.addNewQuestion);
    $("#lus-cancel-edit").hide();
    $("#submit").val(LUS.Strings.saveQuestion);

    // Clear notices
    $(".notice").fadeOut(400, function () {
      $(this).remove();
    });
  },
};

// Initialize when document is ready
document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector("#lus-question-form")) {
    LUS.Handlers.Questions.init();
  }
});
