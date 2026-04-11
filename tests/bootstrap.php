<?php
/**
 * PHPUnit bootstrap.
 *
 * Unit tests run in isolation without loading WordPress; they stub
 * the small surface area of WP functions they need. Integration
 * tests (if present) load the full WP test suite when WP_TESTS_DIR
 * is set.
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

define( 'ADD_FROM_SERVER_TESTS_DIR', __DIR__ );
define( 'ADD_FROM_SERVER_ROOT_DIR', dirname( __DIR__ ) );

// Composer autoload (provides src/ and tests/ classes via PSR-4).
$autoload = ADD_FROM_SERVER_ROOT_DIR . '/vendor/autoload.php';
if ( is_file( $autoload ) ) {
	require_once $autoload;
} else {
	// Fallback: manually register a PSR-4 autoloader so unit tests
	// can run without composer install (useful for sandboxes).
	spl_autoload_register(
		static function ( string $class ): void {
			$map = array(
				'dd32\\WordPress\\AddFromServer\\Tests\\' => ADD_FROM_SERVER_ROOT_DIR . '/tests/',
				'dd32\\WordPress\\AddFromServer\\'        => ADD_FROM_SERVER_ROOT_DIR . '/src/',
			);
			foreach ( $map as $prefix => $dir ) {
				if ( 0 === strpos( $class, $prefix ) ) {
					$relative = substr( $class, strlen( $prefix ) );
					$path     = $dir . str_replace( '\\', '/', $relative ) . '.php';
					if ( is_file( $path ) ) {
						require_once $path;
					}
					return;
				}
			}
		}
	);
}

// Integration suite bootstraps WordPress if available.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( $wp_tests_dir && is_file( $wp_tests_dir . '/includes/functions.php' ) ) {
	require_once $wp_tests_dir . '/includes/functions.php';

	tests_add_filter(
		'muplugins_loaded',
		static function (): void {
			require ADD_FROM_SERVER_ROOT_DIR . '/add-from-server.php';
		}
	);

	require $wp_tests_dir . '/includes/bootstrap.php';
} else {
	// Provide minimal WP stubs for unit tests.
	require_once ADD_FROM_SERVER_TESTS_DIR . '/stubs.php';
}
