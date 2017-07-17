<?php
/**
 * Allows sub classes to have a convenient get_instance method.
 */
class esg_singleton {
    /**
     * Singleton pattern i.e. we have only one instance of this obj
     *
     * @staticvar type $instance
     * @return \cls
     */
    public static function get_instance() {
        static $instance = null;

        // This will make the calling class to be instantiated.
        // no need each sub class to define this method.
        if (is_null($instance)) {
            $instance = new static();
            //$instance->init();
        }

        return $instance;
    }
    
    public function init() {
        
    }
}
