// admin/js/lus-recording-handler.js
LUS.Handlers.Recordings = {
  init() {
    this.bindEvents();
    this.initBulkActions();
    $("#lus-recordings-form").on("submit", this.handleFormSubmit.bind(this));
  },

  bindEvents() {
    // Handle bulk select
    $("#cb-select-all-1").on("change", function () {
      $('input[name="recording_ids[]"]').prop(
        "checked",
        $(this).prop("checked")
      );
    });

    // Handle individual recording selection
    $('input[name="recording_ids[]"]').on(
      "change",
      function () {
        this.updateBulkSelectState();
      }.bind(this)
    );

    // Handle bulk assign button
    $("#bulk-assign").on(
      "click",
      function (e) {
        e.preventDefault();
        this.handleBulkAssign();
      }.bind(this)
    );

    // Initialize delete buttons
    $(".delete-recording").on(
      "click",
      function (e) {
        e.preventDefault();
        const recordingId = $(this).data("recording-id");
        this.handleDelete(recordingId, $(this));
      }.bind(this)
    );
  },

  initBulkActions() {
    // Initialize tooltip for bulk actions
    if (typeof tippy !== "undefined") {
      tippy("#bulk-assign", {
        content: LUS.Strings.selectPassageFirst,
        trigger: "manual",
      });
    }
  },

  updateBulkSelectState() {
    const totalCheckboxes = $('input[name="recording_ids[]"]').length;
    const checkedCheckboxes = $('input[name="recording_ids[]"]:checked').length;

    $("#cb-select-all-1").prop({
      checked: totalCheckboxes === checkedCheckboxes,
      indeterminate:
        checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes,
    });
  },

  handleBulkAssign() {
    const selectedPassage = $("#bulk-passage-id").val();

    if (!selectedPassage) {
      if (typeof tippy !== "undefined") {
        const tooltip = document.getElementById("bulk-assign")._tippy;
        tooltip.show();
        setTimeout(() => tooltip.hide(), 2000);
      } else {
        alert(LUS.Strings.selectPassageFirst);
      }
      return;
    }

    $('input[name="recording_ids[]"]:checked').each(function () {
      const recordingId = $(this).val();
      $(`select[name="recording_passages[${recordingId}]"]`).val(
        selectedPassage
      );
    });

    LUS.UI.Notices.show("info", LUS.Strings.clickSaveToConfirm);
  },

  handleDelete(recordingId, $button) {
    if (!confirm(LUS.Strings.confirmDelete)) {
      return;
    }

    $button.prop("disabled", true);

    LUS.Data.request("delete_recording", {
      recording_id: recordingId,
    })
      .then((response) => {
        const $row = $button.closest("tr");
        $row.fadeOut(
          400,
          function () {
            $row.remove();
            this.updateBulkSelectState();

            // Check if no recordings left
            if ($("tbody tr").length === 0) {
              location.reload();
            }
          }.bind(this)
        );
      })
      .catch((error) => {
        console.error("Delete error:", error);
        alert(error.message || LUS.Strings.deleteError);
        $button.prop("disabled", false);
      });
  },

  validateForm() {
    let hasAssignments = false;
    $(".recording-passage-select").each(function () {
      if ($(this).val()) {
        hasAssignments = true;
        return false; // Break loop
      }
    });

    if (!hasAssignments) {
      LUS.UI.Notices.show("error", LUS.Strings.noAssignmentsSelected);
      return false;
    }

    return true;
  },

  handleFormSubmit(e) {
    e.preventDefault();

    if (!this.validateForm()) {
      return;
    }

    const $submitButton = $('#lus-recordings-form button[type="submit"]');

    // Show loading state
    LUS.UI.LoadingState.show($submitButton, LUS.Strings.saving);

    // Get assignments
    const assignments = {};
    $(".recording-passage-select").each(function () {
      const passageId = $(this).val();
      if (passageId) {
        const recordingId = $(this)
          .attr("name")
          .match(/\[(\d+)\]/)[1];
        assignments[recordingId] = passageId;
      }
    });

    // Make AJAX request
    LUS.Data.request("bulk_assign_recordings", {
      assignments: assignments,
      nonce: LUS.nonce,
    })
      .then((response) => {
        // Show success state
        LUS.UI.LoadingState.success($submitButton, response.message);

        // Update UI
        Object.entries(assignments).forEach(([recordingId, passageId]) => {
          $(`tr[data-recording-id="${recordingId}"]`).fadeOut(400, function () {
            $(this).remove();

            if ($("tbody tr").length === 0) {
              location.reload();
            }
          });
        });
      })
      .catch((error) => {
        // Show error state
        LUS.UI.LoadingState.error(
          $submitButton,
          error.message || LUS.Strings.savingError,
          { duration: 4000 }
        );
      });
  },
};
/**
   * Usage examples on handleFormSubmit:
  // Simple loading button
  LUS.UI.LoadingState.show('#save-button', 'Sparar...');

  // Custom spinner position
  LUS.UI.LoadingState.show('#delete-button', 'Raderar', {
      spinnerPosition: 'end'
  });

  // Just spinner, no text
  LUS.UI.LoadingState.show('#refresh-button', '', {
      spinnerPosition: 'replace'
  });

  // Success state
  LUS.UI.LoadingState.success('#save-button', 'Sparat!');

  // Error with custom duration
  LUS.UI.LoadingState.error('#save-button', 'Kunde inte spara', {
      duration: 5000
  });
  */

// Add to main initialization
document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector("#lus-recordings-form")) {
    LUS.Handlers.Recordings.init();
  }
});
