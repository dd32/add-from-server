<?php
/**
 * Admin page.
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer;

use WP_Error;

/**
 * Renders the admin page and enqueues its assets.
 *
 * The heavy lifting now happens client-side: the admin page is a thin
 * container that loads a JavaScript app talking to the REST API.
 */
class Admin {

	private const PAGE_SLUG = 'add-from-server';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'plugin_action_links_' . PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Add the Media → Add From Server submenu.
	 */
	public function register_menu(): void {
		$hook = add_media_page(
			__( 'Add From Server', 'add-from-server' ),
			__( 'Add From Server', 'add-from-server' ),
			'upload_files',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		add_action( "load-{$hook}", array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add a "Import Files" link to the plugins screen entry.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function add_action_links( array $links ): array {
		if ( current_user_can( 'upload_files' ) ) {
			$url  = admin_url( 'upload.php?page=' . self::PAGE_SLUG );
			$link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $url ),
				esc_html__( 'Import Files', 'add-from-server' )
			);
			array_unshift( $links, $link );
		}
		return $links;
	}

	/**
	 * Enqueue the admin page assets.
	 */
	public function enqueue_assets(): void {
		wp_enqueue_style(
			'add-from-server',
			plugins_url( 'assets/admin.css', PLUGIN_FILE ),
			array( 'wp-components' ),
			VERSION
		);

		wp_enqueue_script(
			'add-from-server',
			plugins_url( 'assets/admin.js', PLUGIN_FILE ),
			array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			VERSION,
			true
		);

		wp_set_script_translations( 'add-from-server', 'add-from-server' );

		wp_localize_script(
			'add-from-server',
			'addFromServerSettings',
			array(
				'root'      => Filesystem::default_root(),
				'restRoot'  => esc_url_raw( rest_url( RestController::NAMESPACE . '/' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render the admin page container.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'add-from-server' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Add From Server', 'add-from-server' ); ?></h1>
			<div id="add-from-server-app"></div>
			<noscript>
				<div class="notice notice-error">
					<p><?php echo esc_html__( 'This page requires JavaScript to be enabled.', 'add-from-server' ); ?></p>
				</div>
			</noscript>
		</div>
		<?php
	}
}
