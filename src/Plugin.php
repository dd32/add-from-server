<?php
/**
 * Main plugin bootstrapper.
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer;

/**
 * Main plugin class. Wires together all components.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 */
	private static ?Plugin $instance = null;

	/**
	 * Admin page controller.
	 */
	private Admin $admin;

	/**
	 * REST API controller.
	 */
	private RestController $rest;

	/**
	 * Block editor integration.
	 */
	private BlockEditor $block_editor;

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor; wire up subsystems.
	 */
	private function __construct() {
		$this->admin        = new Admin();
		$this->rest         = new RestController();
		$this->block_editor = new BlockEditor();
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			$this->admin->register();
		}

		$this->rest->register();
		$this->block_editor->register();

		// Upgrade routine: remove the old frmsvr_root option from prior versions.
		add_action( 'admin_init', array( $this, 'maybe_run_upgrade' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'add-from-server',
			false,
			dirname( PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Remove legacy options from previous versions.
	 *
	 * Since 4.0: the `frmsvr_root` option is fully removed. The root
	 * directory is now exclusively controlled via the `ADD_FROM_SERVER`
	 * constant or the `add_from_server_root` filter.
	 */
	public function maybe_run_upgrade(): void {
		$stored_version = get_option( 'add_from_server_version' );
		if ( VERSION === $stored_version ) {
			return;
		}

		delete_option( 'frmsvr_root' );
		update_option( 'add_from_server_version', VERSION );
	}
}
