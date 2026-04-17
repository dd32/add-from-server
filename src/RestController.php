<?php
/**
 * REST API controller.
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Exposes secure REST API endpoints for browsing and importing files.
 */
class RestController {

	public const NAMESPACE = 'add-from-server/v1';

	/**
	 * Register REST routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Define the routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/browse',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'browse' ),
				'args'                => array(
					'path' => array(
						'type'              => 'string',
						'required'          => false,
						'default'           => '/',
						'sanitize_callback' => array( $this, 'sanitize_path' ),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'permission_check' ),
				'callback'            => array( $this, 'import' ),
				'args'                => array(
					'files' => array(
						'type'              => 'array',
						'required'          => true,
						'items'             => array(
							'type' => 'string',
						),
						'sanitize_callback' => array( $this, 'sanitize_file_list' ),
					),
				),
			)
		);
	}

	/**
	 * Permission check: require upload capability.
	 *
	 * @return true|WP_Error
	 */
	public function permission_check() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'afs_forbidden',
				__( 'Sorry, you are not allowed to import files.', 'add-from-server' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Sanitize a user-supplied path argument.
	 */
	public function sanitize_path( $value ): string {
		$value = (string) $value;
		// Strip nulls and control characters.
		$value = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value ) ?? '';
		return $value;
	}

	/**
	 * Sanitize the file list for the /import endpoint.
	 *
	 * @param array<int, mixed> $value Raw file list.
	 * @return array<int, string>
	 */
	public function sanitize_file_list( $value ): array {
		return array_values(
			array_filter(
				array_map(
					[ $this, 'sanitize_path' ],
					(array) $value
				)
			)
		);
	}

	/**
	 * GET /browse?path=...
	 */
	public function browse( WP_REST_Request $request ): WP_REST_Response {
		$filesystem = new Filesystem( Filesystem::default_root() );
		$resolved   = $filesystem->resolve( (string) $request->get_param( 'path' ) );

		if ( is_wp_error( $resolved ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $resolved->get_error_code(),
					'message' => $resolved->get_error_message(),
				),
				400
			);
		}

		$listing = $filesystem->list_directory( $resolved );
		if ( is_wp_error( $listing ) ) {
			return new WP_REST_Response(
				array(
					'code'    => $listing->get_error_code(),
					'message' => $listing->get_error_message(),
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'root'        => $filesystem->root(),
				'path'        => $filesystem->relative( $resolved ),
				'directories' => $listing['directories'],
				'files'       => $listing['files'],
			)
		);
	}

	/**
	 * POST /import
	 */
	public function import( WP_REST_Request $request ): WP_REST_Response {
		$filesystem = new Filesystem( Filesystem::default_root() );
		$importer   = new Importer( $filesystem );

		$files = (array) $request->get_param( 'files' );
		$files = array_values( array_filter( array_map( 'strval', $files ) ) );

		$results = array();
		foreach ( $files as $file ) {
			$result = $importer->import( $file );
			if ( is_wp_error( $result ) ) {
				$results[] = array(
					'file'    => $file,
					'success' => false,
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				);
			} else {
				$results[] = array(
					'file'          => $file,
					'success'       => true,
					'attachment_id' => (int) $result,
				);
			}
		}

		$all_ok = ! in_array( false, array_column( $results, 'success' ), true );

		return new WP_REST_Response(
			array(
				'success' => $all_ok,
				'results' => $results,
			),
			$all_ok ? 200 : 207
		);
	}
}
