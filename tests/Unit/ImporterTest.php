<?php
/**
 * Unit tests for Importer capability + validation guards.
 *
 * These tests cover the pre-flight checks that don't require a full
 * WordPress environment. The happy-path import tests live in the
 * integration suite which boots wp-tests via wp-env.
 *
 * @package dd32\WordPress\AddFromServer\Tests\Unit
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer\Tests\Unit;

use dd32\WordPress\AddFromServer\Filesystem;
use dd32\WordPress\AddFromServer\Importer;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \dd32\WordPress\AddFromServer\Importer
 */
final class ImporterTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();

		$base = sys_get_temp_dir() . '/afs-imp-' . bin2hex( random_bytes( 4 ) );
		mkdir( $base . '/inside', 0777, true );
		mkdir( $base . '/outside', 0777, true );
		file_put_contents( $base . '/inside/photo.jpg', 'jpg' );
		file_put_contents( $base . '/inside/weird.xyz', 'unknown' );
		file_put_contents( $base . '/outside/secret.jpg', 'nope' );

		$this->root = $base . '/inside';

		// current_user_can is stubbed globally in tests/stubs.php; we
		// control what capabilities it reports via $GLOBALS.
		$GLOBALS['afs_test_caps'] = array( 'upload_files' );
	}

	protected function tearDown(): void {
		$this->rrmdir( dirname( $this->root ) );
		unset( $GLOBALS['afs_test_caps'] );
		parent::tearDown();
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}

	public function test_import_requires_upload_capability(): void {
		$GLOBALS['afs_test_caps'] = array();

		$importer = new Importer( new Filesystem( $this->root ) );
		$result   = $importer->import( '/photo.jpg' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'afs_forbidden', $result->get_error_code() );
	}

	public function test_import_rejects_paths_outside_root(): void {
		$importer = new Importer( new Filesystem( $this->root ) );
		$result   = $importer->import( '../outside/secret.jpg' );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_import_rejects_missing_file(): void {
		$importer = new Importer( new Filesystem( $this->root ) );
		$result   = $importer->import( '/does-not-exist.jpg' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'afs_not_found', $result->get_error_code() );
	}

	public function test_import_rejects_unknown_file_type(): void {
		$importer = new Importer( new Filesystem( $this->root ) );
		$result   = $importer->import( '/weird.xyz' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'afs_bad_filetype', $result->get_error_code() );
	}
}
