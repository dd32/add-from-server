<?php
/**
 * Plugin Name:       Add From Server
 * Plugin URI:        https://dd32.id.au/wordpress-plugins/add-from-server/
 * Description:       Import files from the web server's filesystem into the WordPress Media Library, with block editor integration.
 * Version:           4.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Dion Hulse
 * Author URI:        https://dd32.id.au/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       add-from-server
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer;

defined( 'ABSPATH' ) || exit;

const VERSION = '4.0.0';
const MIN_WP  = '6.9';
const MIN_PHP = '7.4';

define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );
define( __NAMESPACE__ . '\PLUGIN_DIR', __DIR__ );
define( __NAMESPACE__ . '\PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Guard against old WP / PHP versions.
if (
	version_compare( $GLOBALS['wp_version'] ?? '0', MIN_WP, '<' )
	|| version_compare( PHP_VERSION, MIN_PHP, '<' )
) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong>: %s</p></div>',
				esc_html__( 'Add From Server', 'add-from-server' ),
				esc_html(
					sprintf(
					/* translators: 1: required WP version, 2: required PHP version, 3: current WP version, 4: current PHP version */
						__( 'This plugin requires WordPress %1$s or greater and PHP %2$s or greater. You are running WordPress %3$s and PHP %4$s.', 'add-from-server' ),
						MIN_WP,
						MIN_PHP,
						$GLOBALS['wp_version'] ?? 'unknown',
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

// PSR-4 style autoloader for src/.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = PLUGIN_DIR . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_file( $path ) ) {
			require_once $path;
		}
	}
);

// Bootstrap the plugin.
Plugin::instance()->init();
