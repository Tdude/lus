<?php
/**
 * File: includes/class-lus-events.php
 * Events System class
 *
 * @package    LUS
 * @subpackage LUS/includes
 */

class LUS_Events {
    /**
     * Event listeners
     * @var array
     */
    private static $listeners = [];

    /**
     * One-time event listeners
     * @var array
     */
    private static $oneTimeListeners = [];

    /**
     * Add an event listener
     *
     * @param string   $event    Event name
     * @param callable $callback Callback function
     * @param int      $priority Priority (lower numbers = higher priority)
     */
    public static function on(string $event, callable $callback, int $priority = 10): void {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        if (!isset(self::$listeners[$event][$priority])) {
            self::$listeners[$event][$priority] = [];
        }

        self::$listeners[$event][$priority][] = $callback;
    }

    /**
     * Add a one-time event listener
     *
     * @param string   $event    Event name
     * @param callable $callback Callback function
     * @param int      $priority Priority (lower numbers = higher priority)
     */
    public static function once(string $event, callable $callback, int $priority = 10): void {
        if (!isset(self::$oneTimeListeners[$event])) {
            self::$oneTimeListeners[$event] = [];
        }

        if (!isset(self::$oneTimeListeners[$event][$priority])) {
            self::$oneTimeListeners[$event][$priority] = [];
        }

        self::$oneTimeListeners[$event][$priority][] = $callback;
    }

    /**
     * Remove an event listener
     *
     * @param string   $event    Event name
     * @param callable $callback Callback function to remove
     * @return bool Whether the listener was removed
     */
    public static function off(string $event, callable $callback): bool {
        $removed = false;

        // Check regular listeners
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $priority => $callbacks) {
                $key = array_search($callback, $callbacks, true);
                if ($key !== false) {
                    unset(self::$listeners[$event][$priority][$key]);
                    $removed = true;
                }
            }
        }

        // Check one-time listeners
        if (isset(self::$oneTimeListeners[$event])) {
            foreach (self::$oneTimeListeners[$event] as $priority => $callbacks) {
                $key = array_search($callback, $callbacks, true);
                if ($key !== false) {
                    unset(self::$oneTimeListeners[$event][$priority][$key]);
                    $removed = true;
                }
            }
        }

        return $removed;
    }

    /**
     * Emit an event
     *
     * @param string $event Event name
     * @param mixed  $data  Event data
     * @return array Results from all listeners
     */
    public static function emit(string $event, $data = null): array {
        $results = [];

        // Get all listeners for this event
        $allListeners = [];

        // Regular listeners
        if (isset(self::$listeners[$event])) {
            $allListeners[] = self::$listeners[$event];
        }

        // One-time listeners
        if (isset(self::$oneTimeListeners[$event])) {
            $allListeners[] = self::$oneTimeListeners[$event];
            // Clear one-time listeners after use
            unset(self::$oneTimeListeners[$event]);
        }

        // Sort by priority and execute
        foreach ($allListeners as $listeners) {
            ksort($listeners); // Sort by priority
            foreach ($listeners as $callbacks) {
                foreach ($callbacks as $callback) {
                    try {
                        $results[] = $callback($data);
                    } catch (Exception $e) {
                        // Log error but continue executing other listeners
                        error_log("Error in event listener for $event: " . $e->getMessage());
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Get count of listeners for an event
     *
     * @param string $event Event name
     * @return int Number of listeners
     */
    public static function listenerCount(string $event): int {
        $count = 0;

        // Count regular listeners
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $callbacks) {
                $count += count($callbacks);
            }
        }

        // Count one-time listeners
        if (isset(self::$oneTimeListeners[$event])) {
            foreach (self::$oneTimeListeners[$event] as $callbacks) {
                $count += count($callbacks);
            }
        }

        return $count;
    }

    /**
     * Remove all listeners for an event
     *
     * @param string|null $event Event name (null to remove all listeners)
     */
    public static function removeAllListeners(?string $event = null): void {
        if ($event === null) {
            self::$listeners = [];
            self::$oneTimeListeners = [];
            return;
        }

        unset(self::$listeners[$event]);
        unset(self::$oneTimeListeners[$event]);
    }

    /**
     * Get all registered events
     *
     * @return array List of event names
     */
    public static function getEvents(): array {
        return array_unique(
            array_merge(
                array_keys(self::$listeners),
                array_keys(self::$oneTimeListeners)
            )
        );
    }
}