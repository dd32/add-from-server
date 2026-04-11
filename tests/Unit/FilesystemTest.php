<?php
/**
 * Unit tests for the Filesystem class.
 *
 * @package dd32\WordPress\AddFromServer\Tests\Unit
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer\Tests\Unit;

use dd32\WordPress\AddFromServer\Filesystem;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * @covers \dd32\WordPress\AddFromServer\Filesystem
 */
final class FilesystemTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();

		$base = sys_get_temp_dir() . '/afs-test-' . bin2hex( random_bytes( 4 ) );
		mkdir( $base . '/inside/sub', 0777, true );
		mkdir( $base . '/inside/other', 0777, true );
		mkdir( $base . '/outside', 0777, true );

		file_put_contents( $base . '/inside/a.txt', 'a' );
		file_put_contents( $base . '/inside/sub/b.png', 'b' );
		file_put_contents( $base . '/inside/other/ignored.xyz', 'x' );
		file_put_contents( $base . '/outside/secret.txt', 'secret' );

		$this->root = $base . '/inside';
	}

	protected function tearDown(): void {
		$this->rrmdir( dirname( $this->root ) );
		parent::tearDown();
	}

	private function rrmdir( string $dir ): void {
		if ( is_link( $dir ) ) {
			unlink( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_link( $path ) ) {
				unlink( $path );
			} elseif ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	public function test_normalize_collapses_slashes(): void {
		$this->assertSame( '/foo/bar', Filesystem::normalize( '//foo///bar' ) );
		$this->assertSame( '/foo/bar', Filesystem::normalize( '\\foo\\bar' ) );
	}

	public function test_root_is_normalized_and_canonical(): void {
		$fs = new Filesystem( $this->root . '/./' );
		$this->assertSame( rtrim( realpath( $this->root ), '/' ), $fs->root() );
	}

	public function test_resolve_returns_absolute_path_inside_root(): void {
		$fs       = new Filesystem( $this->root );
		$resolved = $fs->resolve( '/sub' );

		$this->assertIsString( $resolved );
		$this->assertSame( realpath( $this->root . '/sub' ), $resolved );
	}

	public function test_resolve_rejects_parent_traversal(): void {
		$fs       = new Filesystem( $this->root );
		$resolved = $fs->resolve( '../outside' );

		$this->assertInstanceOf( WP_Error::class, $resolved );
		$this->assertSame( 'afs_outside_root', $resolved->get_error_code() );
	}

	public function test_resolve_rejects_absolute_path_outside_root(): void {
		$fs       = new Filesystem( $this->root );
		$resolved = $fs->resolve( dirname( $this->root ) . '/outside' );

		$this->assertInstanceOf( WP_Error::class, $resolved );
	}

	public function test_resolve_rejects_symlink_escaping_root(): void {
		$link = $this->root . '/escape';
		if ( ! @symlink( dirname( $this->root ) . '/outside', $link ) ) {
			$this->markTestSkipped( 'Cannot create symlinks on this platform.' );
		}

		$fs       = new Filesystem( $this->root );
		$resolved = $fs->resolve( '/escape' );

		$this->assertInstanceOf( WP_Error::class, $resolved );
		$this->assertSame( 'afs_outside_root', $resolved->get_error_code() );
	}

	public function test_resolve_returns_error_for_missing_path(): void {
		$fs       = new Filesystem( $this->root );
		$resolved = $fs->resolve( '/nope' );

		$this->assertInstanceOf( WP_Error::class, $resolved );
		$this->assertSame( 'afs_not_found', $resolved->get_error_code() );
	}

	public function test_is_within_root_handles_segment_boundaries(): void {
		$fs = new Filesystem( '/var/www' );
		$this->assertTrue( $fs->is_within_root( '/var/www/site' ) );
		$this->assertTrue( $fs->is_within_root( '/var/www' ) );
		$this->assertFalse( $fs->is_within_root( '/var/wwwsomething' ) );
		$this->assertFalse( $fs->is_within_root( '/etc' ) );
	}

	public function test_relative_returns_slash_for_root(): void {
		$fs = new Filesystem( $this->root );
		$this->assertSame( '/', $fs->relative( $this->root ) );
	}

	public function test_relative_returns_leading_slash_path(): void {
		$fs       = new Filesystem( $this->root );
		$relative = $fs->relative( $this->root . '/sub' );
		$this->assertSame( '/sub', $relative );
	}

	public function test_list_directory_returns_sorted_entries(): void {
		$fs      = new Filesystem( $this->root );
		$listing = $fs->list_directory( $this->root );

		$this->assertIsArray( $listing );
		$this->assertCount( 2, $listing['directories'] );
		$this->assertCount( 1, $listing['files'] );
		$this->assertSame( 'other', $listing['directories'][0]['name'] );
		$this->assertSame( 'sub', $listing['directories'][1]['name'] );
		$this->assertSame( 'a.txt', $listing['files'][0]['name'] );
	}

	public function test_list_directory_rejects_outside_root(): void {
		$fs      = new Filesystem( $this->root );
		$listing = $fs->list_directory( dirname( $this->root ) . '/outside' );

		$this->assertInstanceOf( WP_Error::class, $listing );
		$this->assertSame( 'afs_outside_root', $listing->get_error_code() );
	}
}
