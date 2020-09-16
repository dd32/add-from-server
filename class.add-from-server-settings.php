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

		$uac = get_option( 'frmsvr_uac', 'allusers' );

		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'User Access Control', 'add-from-server' ); ?></th>

				<td>
					<fieldset>
						<legend class="screen-reader-text">
							<span><?php _e( 'User Access Control', 'add-from-server' ); ?></span></legend>
						<label for="frmsvr_uac-allusers">
							<input name="frmsvr_uac" type="radio" id="frmsvr_uac-allusers"
								   value="allusers" <?php checked( $uac, 'allusers' ); ?> />
							<?php _e( 'All users with the ability to upload files', 'add-from-server' ); ?>
						</label>
						<br/>
						<label for="frmsvr_uac-role">
							<input name="frmsvr_uac" type="radio" id="frmsvr_uac-role"
								   value="role" <?php checked( $uac, 'role' ); ?> />
							<?php _e( 'Any user with the ability to upload files in the following roles', 'add-from-server' ); ?>
						</label>
						<?php
						$current_roles = (array)get_option( 'frmsvr_uac_role', array() );
						foreach ( get_editable_roles() as $role => $details ) {
							if ( !isset($details['capabilities']['upload_files']) || !$details['capabilities']['upload_files'] )
								continue;
							?>
							<label for="frmsvr_uac-role-<?php echo esc_attr( $role ); ?>">
								<input type="checkbox" name="frmsvr_uac_role[]"
									   id="frmsvr_uac-role-<?php echo esc_attr( $role ); ?>"
									   value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $current_roles ) ); ?> />
								<?php echo translate_user_role( $details['name'] ); ?>
							</label>
						<?php
						}
						?>
						<br/>
						<label for="frmsvr_uac-listusers">
							<input name="frmsvr_uac" type="radio" id="frmsvr_uac-listusers"
								   value="listusers" <?php checked( $uac, 'listusers' ); ?> />
							<?php _e( 'Any users with the ability to upload files listed below', 'add-from-server' ); ?>
						</label>
						<br/>
						<textarea rows="5" cols="20" name="frmsvr_uac_users"
								  class="large-text code"><?php echo esc_textarea( get_option( 'frmsvr_uac_users', 'admin' ) ); ?></textarea>
						<br/>
						<small><em><?php _e( "List the user login's one per line", 'add-from-server' ); ?></em></small>
					</fieldset>
				</td>
			</tr>
		</table>
		<?php
		submit_button( __( 'Save Changes', 'add-from-server' ), 'primary', 'submit' );
		echo '</form>';
		Plugin::instance()->language_notice( ( get_locale() !== 'en_US' ) );
		echo '</div>';
	}
}