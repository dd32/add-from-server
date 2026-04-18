<?php
/**
 * PHPUnit bootstrap.
 *
 * All tests run inside a real WordPress environment (via wp-env's
 * tests-cli container). The WP test suite is located via the
 * WP_TESTS_DIR env var or WP_PHPUNIT__DIR (provided by wp-phpunit).
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	$wp_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}
if ( ! $wp_tests_dir && is_dir( '/wordpress-phpunit' ) ) {
	$wp_tests_dir = '/wordpress-phpunit';
}
if ( ! $wp_tests_dir || ! is_file( $wp_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"Unable to locate the WordPress test suite. Set WP_TESTS_DIR, or run the\n" .
		"suite via `npm run test:php` inside wp-env.\n"
	);
	exit( 1 );
}

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/add-from-server.php';
	}
);

require $wp_tests_dir . '/includes/bootstrap.php';
