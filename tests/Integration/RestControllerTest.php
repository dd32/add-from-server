<?php
/**
 * Integration tests for the REST API controller.
 *
 * Runs against a real WordPress environment (wp-env + wp-phpunit).
 * These tests are skipped if the WordPress test framework is not
 * available in the current runtime, so the suite is safe to invoke
 * from plain PHPUnit without WP loaded.
 *
 * @package dd32\WordPress\AddFromServer\Tests\Integration
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer\Tests\Integration;

if ( ! class_exists( '\WP_UnitTestCase' ) ) {
	return;
}

/**
 * @coversDefaultClass \dd32\WordPress\AddFromServer\RestController
 */
final class RestControllerTest extends \WP_UnitTestCase {

	private int $admin_id;
	private int $subscriber_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		do_action( 'rest_api_init' );
	}

	public function test_browse_requires_upload_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$request  = new \WP_REST_Request( 'GET', '/add-from-server/v1/browse' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_browse_root_returns_listing_for_admin(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new \WP_REST_Request( 'GET', '/add-from-server/v1/browse' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'root', $data );
		$this->assertArrayHasKey( 'directories', $data );
		$this->assertArrayHasKey( 'files', $data );
	}

	public function test_browse_rejects_path_traversal(): void {
		wp_set_current_user( $this->admin_id );

		$request = new \WP_REST_Request( 'GET', '/add-from-server/v1/browse' );
		$request->set_param( 'path', '../../../../etc' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_import_requires_upload_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$request = new \WP_REST_Request( 'POST', '/add-from-server/v1/import' );
		$request->set_param( 'files', array( '/anything' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}
}
