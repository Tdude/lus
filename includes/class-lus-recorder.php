<?php
class LUS_Recorder {
    private static $instance = null;

    public function __construct() {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = $this;
        // ...existing code...
    }

    // ...existing code...
}