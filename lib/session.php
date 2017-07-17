<?php

/**
 * A class that interacts with wp-cli to setup WP.
 * I am relying on the server having a good internet connection at least with the first request.
 * The slowed operation is downloading WP package which is cached after the first request by wp-cli.
 */
class esg_session extends esg_singleton {
    private $project_sesion_key = 'qs_sess';

    /**
     * Starts php session of needed.
     * @see http://stackoverflow.com/questions/6249707/check-if-php-session-has-already-started
     */
    public function start() {
        if ( version_compare( phpversion(), '5.4.0', '<' ) && session_id() == '' ) {
            @session_start();
        } elseif ( function_exists( 'session_status' ) && session_status() == PHP_SESSION_NONE ) {
            @session_start();
        }
    }

    /**
     * 
     */
    public function get( $key ) {
        return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : null;
    }

    /**
     *
     */
    public function has( $key ) {
        $v = $this->get( $key );
        return ! is_null( $v );
    }
    
    /**
     *
     */
    public function set( $key, $val = null ) {
        $_SESSION[ $key ] = $val;
        return $this->get( $key );
    }

    /**
     *
     */
    public function delete( $key ) {
        unset( $_SESSION[ $key ] );
    }

    public function remove( $key ) {
        $this->delete( $key );
    }
}