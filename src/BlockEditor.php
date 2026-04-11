<?php
/**
 * Block editor integration.
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer;

/**
 * Registers a block editor sidebar plugin that exposes the Add From Server
 * picker from inside the post editor.
 */
class BlockEditor {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the editor-side script that registers the sidebar plugin.
	 */
	public function enqueue_assets(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		wp_enqueue_style(
			'add-from-server-editor',
			plugins_url( 'assets/editor.css', PLUGIN_FILE ),
			array( 'wp-components' ),
			VERSION
		);

		wp_enqueue_script(
			'add-from-server-editor',
			plugins_url( 'assets/editor.js', PLUGIN_FILE ),
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-api-fetch',
				'wp-data',
				'wp-i18n',
				'wp-icons',
			),
			VERSION,
			true
		);

		wp_set_script_translations( 'add-from-server-editor', 'add-from-server' );

		wp_localize_script(
			'add-from-server-editor',
			'addFromServerSettings',
			array(
				'root'      => Filesystem::default_root(),
				'restRoot'  => esc_url_raw( rest_url( RestController::NAMESPACE . '/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}
}
