<?php
/**
 * Safe filesystem navigation and validation.
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer;

use WP_Error;

/**
 * Filesystem helper that confines all browsing/imports to a safe root.
 *
 * This class is deliberately decoupled from WordPress globals so it
 * can be unit tested. WordPress-specific helpers may be injected.
 */
class Filesystem {

	/**
	 * Absolute, normalized root directory.
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param string $root Absolute path to the allowed root directory.
	 */
	public function __construct( string $root ) {
		$normalized = self::normalize( $root );
		$real       = realpath( $normalized );
		$this->root = rtrim( $real ?: $normalized, '/' );
		if ( '' === $this->root ) {
			$this->root = '/';
		}
	}

	/**
	 * Determine the default root directory for the site.
	 *
	 * Precedence:
	 *   1. `ADD_FROM_SERVER` constant.
	 *   2. `add_from_server_root` filter.
	 *   3. Parent of ABSPATH (when wp-content lives inside) or parent of wp-content.
	 */
	public static function default_root(): string {
		if ( defined( 'ADD_FROM_SERVER' ) ) {
			return (string) constant( 'ADD_FROM_SERVER' );
		}

		if ( defined( 'WP_CONTENT_DIR' ) && defined( 'ABSPATH' ) ) {
			$root = str_starts_with( WP_CONTENT_DIR, ABSPATH )
				? dirname( ABSPATH )
				: dirname( WP_CONTENT_DIR );
		} else {
			$root = dirname( __DIR__, 2 );
		}

		/**
		 * Filter the root directory used for file browsing.
		 *
		 * @param string $root Absolute root directory path.
		 */
		return (string) apply_filters( 'add_from_server_root', $root );
	}

	/**
	 * Get the normalized root path.
	 */
	public function root(): string {
		return $this->root;
	}

	/**
	 * Normalize a filesystem path (forward slashes, no duplicates).
	 */
	public static function normalize( string $path ): string {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '#/+#', '/', $path );
		return (string) $path;
	}

	/**
	 * Resolve a user-supplied relative path to an absolute path inside the root.
	 *
	 * Returns a WP_Error if the path cannot be resolved or escapes the root.
	 *
	 * @param string $relative A user-supplied path fragment.
	 * @return string|WP_Error Absolute path on success, WP_Error on failure.
	 */
	public function resolve( string $relative ) {
		$relative = self::normalize( $relative );
		// Strip any leading slashes/dots; we always anchor from the root.
		$relative = ltrim( $relative, '/' );

		$candidate = '' === $relative
			? $this->root
			: $this->root . '/' . $relative;

		// Use realpath to resolve symlinks, ., .. and get the canonical path.
		$real = realpath( $candidate );

		if ( false === $real ) {
			return new WP_Error(
				'afs_not_found',
				__( 'The requested path does not exist or is not readable.', 'add-from-server' )
			);
		}

		if ( ! $this->is_within_root( $real ) ) {
			return new WP_Error(
				'afs_outside_root',
				__( 'The requested path is outside the allowed root directory.', 'add-from-server' )
			);
		}

		return $real;
	}

	/**
	 * Check whether an absolute path falls within the root.
	 *
	 * Uses segment-aware comparison to avoid the `/foo` vs `/foobar` collision.
	 */
	public function is_within_root( string $absolute ): bool {
		$absolute = self::normalize( $absolute );
		$absolute = rtrim( $absolute, '/' );
		$root     = rtrim( $this->root, '/' );

		if ( '' === $root || '/' === $root ) {
			return str_starts_with( $absolute, '/' );
		}

		if ( $absolute === $root ) {
			return true;
		}

		return str_starts_with( $absolute, $root . '/' );
	}

	/**
	 * Compute the path relative to the root (with leading slash).
	 */
	public function relative( string $absolute ): string {
		$absolute = self::normalize( rtrim( $absolute, '/' ) );
		$root     = rtrim( $this->root, '/' );

		if ( $absolute === $root ) {
			return '/';
		}

		if ( ! $this->is_within_root( $absolute ) ) {
			return '';
		}

		$relative = substr( $absolute, strlen( $root ) );
		return '' === $relative ? '/' : $relative;
	}

	/**
	 * List the contents of a directory.
	 *
	 * @param string $directory Absolute path inside the root.
	 * @return array{directories: array<int, array{name: string, path: string}>, files: array<int, array{name: string, path: string, readable: bool, size: int, mime: string|false}>}|WP_Error
	 */
	public function list_directory( string $directory ) {
		if ( ! $this->is_within_root( $directory ) ) {
			return new WP_Error( 'afs_outside_root', __( 'Access denied.', 'add-from-server' ) );
		}

		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return new WP_Error( 'afs_not_readable', __( 'Directory is not readable.', 'add-from-server' ) );
		}

		$entries = @scandir( $directory );
		if ( false === $entries ) {
			return new WP_Error( 'afs_scandir_failed', __( 'Unable to list directory.', 'add-from-server' ) );
		}

		$directories = array();
		$files       = array();

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$full = $directory . '/' . $entry;

			if ( is_dir( $full ) ) {
				$directories[] = array(
					'name' => $entry,
					'path' => $this->relative( $full ),
				);
			} elseif ( is_file( $full ) ) {
				$mime    = function_exists( 'wp_check_filetype' ) ? wp_check_filetype( $full )['type'] : false;
				$files[] = array(
					'name'     => $entry,
					'path'     => $this->relative( $full ),
					'readable' => is_readable( $full ),
					'size'     => (int) @filesize( $full ),
					'mime'     => $mime,
				);
			}
		}

		$sort = static function ( array $a, array $b ): int {
			return strcasecmp( $a['name'], $b['name'] );
		};

		usort( $directories, $sort );
		usort( $files, $sort );

		return array(
			'directories' => $directories,
			'files'       => $files,
		);
	}
}
