// admin/js/lus-handlers.js
/**
 * Why this?
 * Performance: By deferring the loading of certain modules, the application remains lightweight until the specific functionality is required.
 * Robustness: The use of try...catch ensures initialization failures in one handler wont affect others.
 * Scalability: Adding more handlers remains straightforward, and their actions can use either static methods or dynamic module imports as needed.
 * Enjoy!
 */

LUS.Handlers = {
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
  Passages: {
    init: () => {
      console.log("Passages handler initialized");
      // Add specific initialization logic here
    },
  },
};

// Main initialization logic
LUS.Handlers.Initializer = {
  /**
   * Define the conditions and actions for initializing handlers
   */
  initializers: {
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

  /**
   * Run specific initializers by key
   * @param {Array} keys - Keys of the initializers to run
   */
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

  /**
   * Run all available initializers
   */
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
};

// Auto-run on DOMContentLoaded
document.addEventListener("DOMContentLoaded", () => {
  // Example: Run specific initializers
  LUS.Handlers.Initializer.runByKey(["passages", "instructions"]);

  // Example: Run all initializers
  // LUS.Handlers.Initializer.runAll();
});
