<?php
/*
  Plugin Name: Event Stream Gallery
  Plugin URI: http://eventstreamgallery.com
  Description: Cool gallery plugin for your events.
  Version: 1.0.9
  Author: Orbisius & Site123.ca
  Author URI: http://site123.ca
  Text Domain: esg
  Domain Path: /lang
 */

define( 'ESG_CORE_BASE_PLUGIN', __FILE__ );
define( 'ESG_CORE_BASE_DIR', dirname( __FILE__ ) );
define( 'ESG_CORE_DATA_DIR', ESG_CORE_BASE_DIR . '/data' );
define( 'ESG_CORE_SHARE_DIR', ESG_CORE_BASE_DIR . '/share' );

$libs = glob( ESG_CORE_BASE_DIR . '/lib/*.php' );
$mods = glob( ESG_CORE_BASE_DIR . '/modules/*.php' );

$libs = array_merge( (array) $libs, (array) $mods );
$libs = array_unique($libs);
$libs = array_filter($libs); // sometimes the casting to array produces 1-2 elements to be false ?!?

foreach ( $libs as $lib_file ) {
    require_once( $lib_file );
}

// https://wordpress.stackexchange.com/questions/25910/uninstall-activate-deactivate-a-plugin-typical-features-how-to/25979#25979
register_activation_hook( ESG_CORE_BASE_PLUGIN, [ esg_module_admin::get_instance(), 'on_plugin_activate' ] );
register_deactivation_hook( ESG_CORE_BASE_PLUGIN, [ esg_module_admin::get_instance(), 'on_plugin_deactivate' ] );
register_uninstall_hook( ESG_CORE_BASE_PLUGIN, [ esg_module_admin::get_instance(), 'on_plugin_uninstall' ] );

add_action( 'after_switch_theme', [ esg_module_admin::get_instance(), 'on_plugin_activate' ] );

class esg_exception extends Exception {}
class esg_core_exception extends Exception {}
