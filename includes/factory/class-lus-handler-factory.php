<?php
/**
 * File: class-handler-factory.php
 * Handler Factory class
 *
 * @package    LUS
 * @subpackage LUS/includes/factory
 */

abstract class LUS_Handler_Factory {
    /**
     * Create a passage handler
     *
     * @return LUS_Passage_Handler
     */
    abstract public function createPassageHandler(): LUS_Passage_Handler;

    /**
     * Create a question handler
     *
     * @return LUS_Question_Handler
     */
    abstract public function createQuestionHandler(): LUS_Question_Handler;

    /**
     * Create a recording handler
     *
     * @return LUS_Recording_Handler
     */
    abstract public function createRecordingHandler(): LUS_Recording_Handler;

    /**
     * Create an assignment handler
     *
     * @return LUS_Assignment_Handler
     */
    abstract public function createAssignmentHandler(): LUS_Assignment_Handler;
}

/**
 * Concrete implementation of handler factory
 */
class LUS_Default_Handler_Factory extends LUS_Handler_Factory {
    /**
     * Database instance
     * @var LUS_Database
     */
    private $db;

    /**
     * Events instance
     * @var LUS_Events
     */
    private $events;

    /**
     * Constructor
     *
     * @param LUS_Database $db     Database instance
     * @param LUS_Events   $events Events instance
     */
    public function __construct(LUS_Database $db, LUS_Events $events) {
        $this->db = $db;
        $this->events = $events;
    }

    /**
     * Create a passage handler
     *
     * @return LUS_Passage_Handler
     */
    public function createPassageHandler(): LUS_Passage_Handler {
        return new LUS_Passage_Handler($this->db, $this->events);
    }

    /**
     * Create a question handler
     *
     * @return LUS_Question_Handler
     */
    public function createQuestionHandler(): LUS_Question_Handler {
        return new LUS_Question_Handler($this->db, $this->events);
    }

    /**
     * Create a recording handler
     *
     * @return LUS_Recording_Handler
     */
    public function createRecordingHandler(): LUS_Recording_Handler {
        return new LUS_Recording_Handler($this->db, $this->events);
    }

    /**
     * Create an assignment handler
     *
     * @return LUS_Assignment_Handler
     */
    public function createAssignmentHandler(): LUS_Assignment_Handler {
        return new LUS_Assignment_Handler($this->db, $this->events);
    }
}

/**
 * Base handler class that all specific handlers extend
 */
abstract class LUS_Base_Handler {
    /**
     * Database instance
     * @var LUS_Database
     */
}