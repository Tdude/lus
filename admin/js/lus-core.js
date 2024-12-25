/**
 * let ThisBe: var declarations are hoisted to the top of their scope and are initialized with undefined.
 * This means var LUS will not throw an error when LUS is accessed before its declaration.
 */
var LUS = LUS || {};
LUS.Handlers = LUS.Handlers || {};
LUS.UI = LUS.UI || {};

// admin/js/config/lus-config.js
// I will rather have them here.

const LUS_Config = {
  // API endpoints
  ENDPOINTS: {
    SAVE_PASSAGE: "lus_admin_passage_save",
    DELETE_RECORDING: "lus_admin_recording_delete",
  },

  // Status codes
  STATUS: {
    PENDING: "pending",
    ASSESSED: "assessed",
  },

  // UI settings
  UI: {
    NOTIFICATION_TIMEOUT: 5000,
    MODAL_ANIMATION_DURATION: 300,
  },
};
