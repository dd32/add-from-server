<?php

class Plugin {
	function __construct() {

		// Handle activation gracefully with a block screen.
		if (
			isset( $_REQUEST['action'] ) &&
			(
					'activate' == $_REQUEST['action'] ||
					'error_scrape' == $_REQUEST['action']
			) &&
			isset( $_REQUEST['plugin'] ) &&
			PLUGIN == $_REQUEST['plugin']
		) {
			die( sprintf(
				__( 'This plugin requires WordPress %1$s or greater, and PHP %2$s or greater. You are currently running WordPress %3$s and PHP %4$s. Please contact your website host or server administrator for more information. The plugin has been deactivated.', 'add-from-server' ),
				MIN_WP,
				MIN_PHP,
				$GLOBALS['wp_version'],
				phpversion()
			) );
		}

		add_action( 'pre_current_active_plugins', array( $this, 'unsupported_plugins_notice' ) );
	}

	function unsupported_plugins_notice( $var ) {
		printf(
			'<div class="error"><p><strong>%s</strong>: %s</p></div>',
			__( 'Add From Server', 'add-from-server' ),
			sprintf(
				__( 'This plugin requires WordPress %1$s or greater, and PHP %2$s or greater. You are currently running WordPress %3$s and PHP %4$s. Please contact your website host or server administrator for more information. The plugin has been deactivated.', 'add-from-server' ),
				MIN_WP,
				MIN_PHP,
				$GLOBALS['wp_version'],
				phpversion()
			)
		);
	}

}
