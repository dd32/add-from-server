<?php
/*
 * Plugin Name: Add From Server
 * Version: 3.3.3
 * Plugin URI: https://dd32.id.au/wordpress-plugins/add-from-server/
 * Description: Plugin to allow the Media Manager to add files from the webservers filesystem. <strong>Note:</strong> All files are copied to the uploads directory.
 * Author: Dion Hulse
 * Author URI: https://dd32.id.au/
 * Text Domain: add-from-server
 */

if ( !is_admin() ) {
	return;
}

define( 'ADD_FROM_SERVER_WP_REQUIREMENT', '4.5' );
define( 'ADD_FROM_SERVER_PHP_REQUIREMENT', '5.4' );

// Old versions of WordPress or PHP
if ( version_compare( $GLOBALS['wp_version'], ADD_FROM_SERVER_WP_REQUIREMENT, '<' ) || version_compare( phpversion(), ADD_FROM_SERVER_PHP_REQUIREMENT, '<' ) ) {
	include dirname( __FILE__ ) . '/old-versions.php';
} else {
	include __DIR__ . '/class.add-from-server.php';
}

$add_from_server = new Add_From_Server( plugin_basename( __FILE__ ) );
