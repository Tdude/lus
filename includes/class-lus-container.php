<?php
/**
 * File. class-lus-container.php
 * Service Container class
 *
 * @package    LUS
 * @subpackage LUS/includes
 */
 class LUS_Container {
     /**
      * Stored service definitions
      * @var array
      */
     private static $services = [];

     /**
      * Stored singleton instances
      * @var array
      */
     private static $instances = [];

     /**
      * Register a service
      *
      * @param string   $key      Service identifier
      * @param callable $callback Service factory callback
      * @param bool     $shared   Whether to return the same instance each time
      */
     public static function register(string $key, callable $callback, bool $shared = false): void {
         self::$services[$key] = [
             'callback' => $callback,
             'shared' => $shared
         ];
     }

     /**
      * Get a service instance
      *
      * @param string $key Service identifier
      * @return mixed Service instance
      * @throws Exception When service is not found
      */
     public static function get(string $key) {
         if (!isset(self::$services[$key])) {
             throw new Exception("Service not found: $key");
         }

         $service = self::$services[$key];

         // Return cached instance for shared services
         if ($service['shared'] && isset(self::$instances[$key])) {
             return self::$instances[$key];
         }

         // Create new instance
         $instance = $service['callback']();

         // Cache instance if shared
         if ($service['shared']) {
             self::$instances[$key] = $instance;
         }

         return $instance;
     }

     /**
      * Check if a service exists
      *
      * @param string $key Service identifier
      * @return bool Whether the service exists
      */
     public static function has(string $key): bool {
         return isset(self::$services[$key]);
     }

     /**
      * Unregister a service
      *
      * @param string $key Service identifier
      */
     public static function unregister(string $key): void {
         unset(self::$services[$key]);
         unset(self::$instances[$key]);
     }

     /**
      * Reset all services
      */
     public static function reset(): void {
         self::$services = [];
         self::$instances = [];
     }

     /**
      * Register common services
      */
     public static function registerCommonServices(): void {
         // Database
         self::register('database', function() {
             return new LUS_Database();
         }, true);

         // Assessment Handler
         self::register('assessment_handler', function() {
             return new LUS_Assessment_Handler(self::get('database'));
         }, true);

         // Statistics
         self::register('statistics', function() {
             return new LUS_Statistics(self::get('database'));
         }, true);

         // Export Handler
         self::register('export_handler', function() {
             return new LUS_Export_Handler(self::get('database'));
         }, false);

         // Evaluators
         self::register('evaluator.manual', function() {
             return new LUS_Manual_Evaluator();
         }, true);

         self::register('evaluator.levenshtein', function() {
             return new LUS_Levenshtein_Strategy();
         }, true);

         // Event Manager
         self::register('events', function() {
             return new LUS_Events();
         }, true);
     }

     /**
      * Create a new instance with dependencies
      *
      * @param string $className Class name to instantiate
      * @param array  $params    Constructor parameters
      * @return object New instance
      * @throws ReflectionException
      */
     public static function make(string $className, array $params = []): object {
         $reflection = new ReflectionClass($className);

         if (!$reflection->isInstantiable()) {
             throw new Exception("Class $className is not instantiable");
         }

         $constructor = $reflection->getConstructor();

         if (null === $constructor) {
             return new $className;
         }

         $dependencies = [];

         foreach ($constructor->getParameters() as $param) {
             $name = $param->getName();
             $type = $param->getType();

             // Use provided parameter if available
             if (isset($params[$name])) {
                 $dependencies[] = $params[$name];
                 continue;
             }

             // Try to resolve type-hinted service
             if ($type && !$type->isBuiltin()) {
                 $serviceName = strtolower($type->getName());
                 if (self::has($serviceName)) {
                     $dependencies[] = self::get($serviceName);
                     continue;
                 }
             }

             // Use default value if available
             if ($param->isDefaultValueAvailable()) {
                 $dependencies[] = $param->getDefaultValue();
                 continue;
             }

             throw new Exception("Unable to resolve dependency: $name");
         }

         return $reflection->newInstanceArgs($dependencies);
     }
 }