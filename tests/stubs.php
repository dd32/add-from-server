<?php
/**
 * Minimal WordPress stubs for unit tests that run without WP.
 *
 * Only the functions the Filesystem and related classes need to run.
 *
 * @package dd32\WordPress\AddFromServer\Tests
 *
 * phpcs:disable PSR1.Files.SideEffects
 * phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 * phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
 * phpcs:disable PEAR.Files.IncludingFile
 * phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
 */

declare( strict_types=1 );

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private array $data;

		public function __construct( string $code = '', string $message = '', array $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( string $text, string $domain = '' ): void {
		echo $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	function str_starts_with( string $haystack, string $needle ): bool {
		return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		return in_array( $cap, $GLOBALS['afs_test_caps'] ?? array(), true );
	}
}

if ( ! function_exists( 'wp_check_filetype' ) ) {
	function wp_check_filetype( string $file, $mimes = null ): array {
		$ext   = pathinfo( $file, PATHINFO_EXTENSION );
		$map   = array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'txt'  => 'text/plain',
		);
		$lower = strtolower( $ext );
		return array(
			'ext'  => isset( $map[ $lower ] ) ? $lower : false,
			'type' => $map[ $lower ] ?? false,
		);
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/afs-abspath/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}
