/**
 * File: admin/js/lus-core.js
 * let ThisBe: var declarations are hoisted to the top of their scope and are initialized with undefined.
 * This means var LUS will not throw an error when LUS is accessed before its declaration.
 */
var LUS = LUS || {};
LUS.Handlers = LUS.Handlers || {};
LUS.UI = LUS.UI || {};

// admin/js/config/lus-config.js
// I will rather have them here.

const LUS_Config = {
  ENDPOINTS: {
    SAVE_PASSAGE: LUS_Constants.AJAX_SAVE_PASSAGE,
    DELETE_RECORDING: LUS_Constants.AJAX_DELETE_RECORDING,
  },
  STATUS: {
    PENDING: LUS_Constants.STATUS_PENDING,
    ASSESSED: LUS_Constants.STATUS_ASSESSED,
  },

  // UI settings
  UI: {
    NOTIFICATION_TIMEOUT: 5000,
    MODAL_ANIMATION_DURATION: 300,
  },
};
