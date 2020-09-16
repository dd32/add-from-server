<?php
namespace dd32\WordPress\AddFromServer;

class Settings {
	function render() {
		echo '<div class="wrap">';
		echo '<h1>' . __( 'Add From Server', 'add-from-server' ) . '</h1>';
		echo '<form method="post" action="options.php">';

		settings_fields( 'add_from_server' );

		if (
			str_contains( get_option( 'frmsvr_root', '%' ), '%' )
			&&
			! defined( 'ADD_FROM_SERVER' )
		) {
			printf(
				'<div class="notice error"><p>%s</p></div>',
				'You previously used the "Root Directory" option with a placeholder, such as "%username% or "%role%".<br>' .
				'Unfortunately this feature is no longer supported. As a result, Add From Server has been disabled for users who have restricted upload privledges.<br>' .
				'To make this warning go away, empty the "frmsvr_root" option on <a href="options.php">options.php</a>.'
			);
		}

		?>
		<?php
		submit_button( __( 'Save Changes', 'add-from-server' ), 'primary', 'submit' );
		echo '</form>';
		Plugin::instance()->language_notice( ( get_locale() !== 'en_US' ) );
		echo '</div>';
	}
}