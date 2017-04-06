<?php

class Add_From_Server {

	var $version = '3.3.3';
	var $basename = '';

	function __construct( $plugin ) {
		$this->basename = $plugin;
		// Register general hooks.
		add_action( 'init', array( $this, 'load_translations' ) ); // must run before admin_menu
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function load_translations() {
		// Load any translation files needed:
		load_plugin_textdomain( 'add-from-server' );
	}

	function admin_init() {
		// Register our JS & CSS
		wp_register_style( 'add-from-server', plugins_url( '/add-from-server.css', __FILE__ ), array(), $this->version );

		// Enqueue JS & CSS
		add_action( 'load-media_page_add-from-server', array( $this, 'add_styles' ) );
		add_action( 'media_upload_server', array( $this, 'add_styles' ) );

		add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'add_configure_link' ) );

		if ( $this->user_allowed() ) {
			// Add actions/filters
			add_filter( 'media_upload_tabs', array( $this, 'tabs' ) );
			add_action( 'media_upload_server', array( $this, 'tab_handler' ) );
		}

		// Register our settings:
		register_setting( 'add_from_server', 'frmsvr_root', array( $this, 'sanitize_option_root' ) );
		// register_setting('add-from-server', 'frmsvr_last_folder');
		register_setting( 'add_from_server', 'frmsvr_uac' );
		register_setting( 'add_from_server', 'frmsvr_uac_users' );
		register_setting( 'add_from_server', 'frmsvr_uac_role' );

	}

	function admin_menu() {
		if ( $this->user_allowed() ) {
			add_media_page( __( 'Add From Server', 'add-from-server' ), __( 'Add From Server', 'add-from-server' ), 'read', 'add-from-server', array( $this, 'menu_page' ) );
		}
		add_options_page( __( 'Add From Server', 'add-from-server' ), __( 'Add From Server', 'add-from-server' ), 'manage_options', 'add-from-server-settings', array( $this, 'options_page' ) );
	}

	function add_configure_link( $_links ) {
		$links = array();
		if ( $this->user_allowed() ) {
			$links[] = '<a href="' . admin_url( 'upload.php?page=add-from-server' ) . '">' . __( 'Import Files', 'add-from-server' ) . '</a>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			$links[] = '<a href="' . admin_url( 'options-general.php?page=add-from-server-settings' ) . '">' . __( 'Options', 'add-from-server' ) . '</a>';
		}

		return array_merge( $links, $_links );
	}

	// Add a tab to the media uploader:
	function tabs( $tabs ) {
		if ( $this->user_allowed() ) {
			$tabs['server'] = __( 'Add From Server', 'add-from-server' );
		}
		return $tabs;
	}

	function add_styles() {
		// Enqueue support files.
		if ( 'media_upload_server' == current_filter() ) {
			wp_enqueue_style( 'media' );
		}
		wp_enqueue_style( 'add-from-server' );
	}

	// Handle the actual page:
	function tab_handler() {
		global $body_id;
		if ( !$this->user_allowed() ) {
			return;
		}

		$body_id = 'media-upload';
		iframe_header( __( 'Add From Server', 'add-from-server' ) );
		$this->handle_imports();
		$this->main_content();
		iframe_footer();
	}

	function menu_page() {
		if ( !$this->user_allowed() ) {
			return;
		}

		// Handle any imports:
		$this->handle_imports();

		echo '<div class="wrap">';
		echo '<h1>' . __( 'Add From Server', 'add-from-server' ) . '</h1>';
		$this->main_content();
		echo '</div>';
	}

	function options_page() {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}

		include __DIR__ . '/class.add-from-server-settings.php';
		$settings = new Add_From_Server_Settings( $this );
		$settings->render();
	}

	function get_root( $context = 'use' ) {
		static $static_root = null;
		if ( $static_root )
			return $static_root;

		$root = get_option( 'frmsvr_root', false );
		if ( strpos( $root, '%' ) !== false && 'raw' != $context ) {
			$user = wp_get_current_user();

			$root = str_replace( '%username%', $user->user_login, $root );
			$root = str_replace( '%role%', $user->roles[0], $root );
		}
		if ( ! $root ) {
			if ( '/' == substr( __FILE__, 0, 1 ) ) {
				$root = '/';
			} elseif ( preg_match( '!^[a-zA-Z]:!', __FILE__, $root_win_match ) ) {
				$root = $root_win_match[1];
			}
		}

		if ( strlen( $root ) > 1 ) {
			$root = untrailingslashit( $root );
		}

		return $root;
	}

	function user_allowed() {
		if ( !current_user_can( 'upload_files' ) ) {
			return false;
		}

		switch ( get_option( 'frmsvr_uac', 'allusers' ) ) {
			default:
			case 'allusers':
				return true;

			case 'role':
				$user = wp_get_current_user();
				$roles = $user->roles;
				$allowed_roles = get_option( 'frmsvr_uac_role', array() );
				foreach ( $roles as $r ) {
					if ( in_array( $r, $allowed_roles ) )
						return true;
				}
				return false;

			case 'listusers':
				$user = wp_get_current_user();
				$allowed_users = explode( "\n", get_option( 'frmsvr_uac_users', '' ) );
				$allowed_users = array_map( 'trim', $allowed_users );
				$allowed_users = array_filter( $allowed_users );
				return in_array( $user->user_login, $allowed_users );
		}
		return false;
	}

	function sanitize_option_root($input) {
		$_input = $input;
		if ( 'specific' == $input ) {
			$input = wp_unslash( $_POST['frmsvr_root-specified'] );
		}

		if ( !$this->validate_option_root( $input ) ) {
			$input = get_option( 'frmsvr_root' );
		}

		// WP < 4.4 Compat: ucfirt
		$input = ucfirst( wp_normalize_path( $input ) );

		return $input;
	}

	function validate_option_root($o) {
		if ( strpos( $o, '%' ) !== false ) {
			// Ensure only valid placeholders are used:
			if ( preg_match_all( '!%(.*?)%!', $o, $placeholders ) ) {
				$valid_ph = array( 'username', 'role' );
				foreach ( $placeholders[1] as $ph ) {
					if ( !in_array( $ph, $valid_ph ) ) {
						add_settings_error( 'general', 'update_failed', sprintf( __( 'The placeholder %s is not valid in the root path.', 'add-from-server' ), '%' . $ph . '%' ), 'error' );
						return false;
					}
				}
				return true;
			}
		}
		if ( !is_dir( $o ) || !is_readable( $o ) ) {
			add_settings_error( 'general', 'update_failed', __( 'The root path specified could not be read.', 'add-from-server' ), 'error' );
			return false;
		}
		return true;
	}

	// Handle the imports
	function handle_imports() {

		if ( !empty($_POST['files']) && !empty($_POST['cwd']) ) {

			check_admin_referer( 'afs_import' );

			$files = wp_unslash( $_POST['files'] );

			$cwd = trailingslashit( wp_unslash( $_POST['cwd'] ) );
			if ( false === strpos( $cwd, $this->get_root() ) ) {
				return;
			}

			$post_id = isset($_REQUEST['post_id']) ? absint( $_REQUEST['post_id'] ) : 0;
			$import_date = isset($_REQUEST['import-date']) ? $_REQUEST['import-date'] : 'current';

			$import_to_gallery = isset($_POST['gallery']) && 'on' == $_POST['gallery'];
			if ( !$import_to_gallery && !isset($_REQUEST['cwd']) ) {
				$import_to_gallery = true; // cwd should always be set, if it's not, and neither is gallery, this must be the first page load.
			}

			if ( !$import_to_gallery ) {
				$post_id = 0;
			}

			flush();
			wp_ob_end_flush_all();

			foreach ( (array)$files as $file ) {
				$filename = $cwd . $file;
				$id = $this->handle_import_file( $filename, $post_id, $import_date );
				if ( is_wp_error( $id ) ) {
					echo '<div class="updated error"><p>' . sprintf( __( '<em>%s</em> was <strong>not</strong> imported due to an error: %s', 'add-from-server' ), esc_html( $file ), $id->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="updated"><p>' . sprintf( __( '<em>%s</em> has been added to Media library', 'add-from-server' ), esc_html( $file ) ) . '</p></div>';
				}
				flush();
				wp_ob_end_flush_all();
			}
		}
	}

	// Handle an individual file import.
	function handle_import_file( $file, $post_id = 0, $import_date = 'current' ) {
		set_time_limit( 0 );

		// Initially, Base it on the -current- time.
		$time = current_time( 'mysql', 1 );
		// Next, If it's post to base the upload off:
		if ( 'post' == $import_date && $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post && substr( $post->post_date_gmt, 0, 4 ) > 0 ) {
				$time = $post->post_date_gmt;
			}
		} elseif ( 'file' == $import_date ) {
			$time = gmdate( 'Y-m-d H:i:s', @filemtime( $file ) );
		}

		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( !(($uploads = wp_upload_dir( $time )) && false === $uploads['error']) ) {
			return new WP_Error( 'upload_error', $uploads['error'] );
		}

		$wp_filetype = wp_check_filetype( $file, null );

		extract( $wp_filetype );

		if ( (!$type || !$ext) && !current_user_can( 'unfiltered_upload' ) ) {
			return new WP_Error( 'wrong_file_type', __( 'Sorry, this file type is not permitted for security reasons.', 'add-from-server' ) );
		}

		// Is the file allready in the uploads folder?
		// WP < 4.4 Compat: ucfirt
		if ( preg_match( '|^' . preg_quote( ucfirst( wp_normalize_path( $uploads['basedir'] ) ), '|' ) . '(.*)$|i', $file, $mat ) ) {

			$filename = basename( $file );
			$new_file = $file;

			$url = $uploads['baseurl'] . $mat[1];

			$attachment = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => ltrim( $mat[1], '/' ) ) );
			if ( !empty($attachment) ) {
				return new WP_Error( 'file_exists', __( 'Sorry, That file already exists in the WordPress media library.', 'add-from-server' ) );
			}

			// Ok, Its in the uploads folder, But NOT in WordPress's media library.
			if ( 'file' == $import_date ) {
				$time = @filemtime( $file );
				if ( preg_match( "|(\d+)/(\d+)|", $mat[1], $datemat ) ) { // So lets set the date of the import to the date folder its in, IF its in a date folder.
					$hour = $min = $sec = 0;
					$day = 1;
					$year = $datemat[1];
					$month = $datemat[2];

					// If the files datetime is set, and it's in the same region of upload directory, set the minute details to that too, else, override it.
					if ( $time && date( 'Y-m', $time ) == "$year-$month" ) {
						list($hour, $min, $sec, $day) = explode( ';', date( 'H;i;s;j', $time ) );
					}

					$time = mktime( $hour, $min, $sec, $month, $day, $year );
				}
				$time = gmdate( 'Y-m-d H:i:s', $time );

				// A new time has been found! Get the new uploads folder:
				// A writable uploads dir will pass this test. Again, there's no point overriding this one.
				if ( !(($uploads = wp_upload_dir( $time )) && false === $uploads['error']) ) {
					return new WP_Error( 'upload_error', $uploads['error'] );
				}
				$url = $uploads['baseurl'] . $mat[1];
			}
		} else {
			$filename = wp_unique_filename( $uploads['path'], basename( $file ) );

			// copy the file to the uploads dir
			$new_file = $uploads['path'] . '/' . $filename;
			if ( false === @copy( $file, $new_file ) )
				return new WP_Error( 'upload_error', sprintf( __( 'The selected file could not be copied to %s.', 'add-from-server' ), $uploads['path'] ) );

			// Set correct file permissions
			$stat = stat( dirname( $new_file ) );
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );
			// Compute the URL
			$url = $uploads['url'] . '/' . $filename;

			if ( 'file' == $import_date ) {
				$time = gmdate( 'Y-m-d H:i:s', @filemtime( $file ) );
			}
		}

		// Apply upload filters
		$return = apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ) );
		$new_file = $return['file'];
		$url = $return['url'];
		$type = $return['type'];

		$title = preg_replace( '!\.[^.]+$!', '', basename( $file ) );
		$content = $excerpt = '';

		if ( preg_match( '#^audio#', $type ) ) {
			$meta = wp_read_audio_metadata( $new_file );
	
			if ( ! empty( $meta['title'] ) ) {
				$title = $meta['title'];
			}
	
			if ( ! empty( $title ) ) {
	
				if ( ! empty( $meta['album'] ) && ! empty( $meta['artist'] ) ) {
					/* translators: 1: audio track title, 2: album title, 3: artist name */
					$content .= sprintf( __( '"%1$s" from %2$s by %3$s.', 'add-from-server' ), $title, $meta['album'], $meta['artist'] );
				} elseif ( ! empty( $meta['album'] ) ) {
					/* translators: 1: audio track title, 2: album title */
					$content .= sprintf( __( '"%1$s" from %2$s.', 'add-from-server' ), $title, $meta['album'] );
				} elseif ( ! empty( $meta['artist'] ) ) {
					/* translators: 1: audio track title, 2: artist name */
					$content .= sprintf( __( '"%1$s" by %2$s.', 'add-from-server' ), $title, $meta['artist'] );
				} else {
					$content .= sprintf( __( '"%s".', 'add-from-server' ), $title );
				}
	
			} elseif ( ! empty( $meta['album'] ) ) {
	
				if ( ! empty( $meta['artist'] ) ) {
					/* translators: 1: audio album title, 2: artist name */
					$content .= sprintf( __( '%1$s by %2$s.', 'add-from-server' ), $meta['album'], $meta['artist'] );
				} else {
					$content .= $meta['album'] . '.';
				}
	
			} elseif ( ! empty( $meta['artist'] ) ) {
	
				$content .= $meta['artist'] . '.';
	
			}
	
			if ( ! empty( $meta['year'] ) )
				$content .= ' ' . sprintf( __( 'Released: %d.' ), $meta['year'] );
	
			if ( ! empty( $meta['track_number'] ) ) {
				$track_number = explode( '/', $meta['track_number'] );
				if ( isset( $track_number[1] ) )
					$content .= ' ' . sprintf( __( 'Track %1$s of %2$s.', 'add-from-server' ), number_format_i18n( $track_number[0] ), number_format_i18n( $track_number[1] ) );
				else
					$content .= ' ' . sprintf( __( 'Track %1$s.', 'add-from-server' ), number_format_i18n( $track_number[0] ) );
			}
	
			if ( ! empty( $meta['genre'] ) )
				$content .= ' ' . sprintf( __( 'Genre: %s.', 'add-from-server' ), $meta['genre'] );
	
		// Use image exif/iptc data for title and caption defaults if possible.
		} elseif ( 0 === strpos( $type, 'image/' ) && $image_meta = @wp_read_image_metadata( $new_file ) ) {
			if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
				$title = $image_meta['title'];
			}
	
			if ( trim( $image_meta['caption'] ) ) {
				$excerpt = $image_meta['caption'];
			}
		}

		if ( $time ) {
			$post_date_gmt = $time;
			$post_date = $time;
		} else {
			$post_date = current_time( 'mysql' );
			$post_date_gmt = current_time( 'mysql', 1 );
		}

		// Construct the attachment array
		$attachment = array(
			'post_mime_type' => $type,
			'guid' => $url,
			'post_parent' => $post_id,
			'post_title' => $title,
			'post_name' => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_date' => $post_date,
			'post_date_gmt' => $post_date_gmt
		);

		$attachment = apply_filters( 'afs-import_details', $attachment, $file, $post_id, $import_date );

		// WP < 4.4 Compat: ucfirt
		$new_file = str_replace( ucfirst( wp_normalize_path( $uploads['basedir'] ) ), $uploads['basedir'], $new_file );

		// Save the data
		$id = wp_insert_attachment( $attachment, $new_file, $post_id );
		if ( !is_wp_error( $id ) ) {
			$data = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $data );
		}
		// update_post_meta( $id, '_wp_attached_file', $uploads['subdir'] . '/' . $filename );

		return $id;
	}

	// Create the content for the page
	function main_content() {
		global $pagenow;
		$post_id = isset($_REQUEST['post_id']) ? intval( $_REQUEST['post_id'] ) : 0;
		$import_to_gallery = isset($_POST['gallery']) && 'on' == $_POST['gallery'];
		if ( !$import_to_gallery && !isset($_REQUEST['cwd']) ) {
			$import_to_gallery = true; // cwd should always be set, if it's not, and neither is gallery, this must be the first page load.
		}
		$import_date = isset($_REQUEST['import-date']) ? $_REQUEST['import-date'] : 'current';

		if ( 'upload.php' == $pagenow ) {
			$url = admin_url( 'upload.php?page=add-from-server' );
		} else {
			$url = admin_url( 'media-upload.php?tab=server' );
		}

		if ( $post_id ) {
			$url = add_query_arg( 'post_id', $post_id, $url );
		}

		$cwd = trailingslashit( get_option( 'frmsvr_last_folder' ) ?: WP_CONTENT_DIR );

		if ( isset($_REQUEST['directory']) ) {
			$cwd .= stripslashes( urldecode( $_REQUEST['directory'] ) );
		}

		if ( isset($_REQUEST['adirectory']) && empty($_REQUEST['adirectory']) ) {
			$_REQUEST['adirectory'] = '/'; // For good measure.
		}

		if ( isset($_REQUEST['adirectory']) ) {
			$cwd = stripslashes( urldecode( $_REQUEST['adirectory'] ) );
		}

		$cwd = preg_replace( '![^/]*/\.\./!', '', $cwd );
		$cwd = preg_replace( '!//!', '/', $cwd );

		if ( !is_readable( $cwd ) && is_readable( $this->get_root() . '/' . ltrim( $cwd, '/' ) ) ) {
			$cwd = $this->get_root() . '/' . ltrim( $cwd, '/' );
		}

		if ( !is_readable( $cwd ) && get_option( 'frmsvr_last_folder' ) ) {
			$cwd = get_option( 'frmsvr_last_folder' );
		}

		if ( !is_readable( $cwd ) ) {
			$cwd = WP_CONTENT_DIR;
		}

		if ( strpos( $cwd, $this->get_root() ) === false ) {
			$cwd = $this->get_root();
		}

		// WP < 4.4 Compat: ucfirt
		$cwd = ucfirst( wp_normalize_path( $cwd ) );

		if ( strlen( $cwd ) > 1 ) {
			$cwd = untrailingslashit( $cwd );
		}

		if ( !is_readable( $cwd ) ) {
			echo '<div class="error"><p>' . __( '<strong>Error:</strong> This users root directory is not readable. Please have your site administrator correct the <em>Add From Server</em> root directory settings.', 'add-from-server' ) . '</p></div>';
			return;
		}

		update_option( 'frmsvr_last_folder', $cwd );

		$files = $this->find_files( $cwd );

		$parts = explode( '/', ltrim( str_replace( $this->get_root(), '/', $cwd ), '/' ) );
		if ( $parts[0] != '' ) {
			$parts = array_merge( (array)'', $parts );
		}

		// array_walk() + eAccelerator + anonymous function = bad news
		foreach ( $parts as $index => &$item ) {
			$this_path = implode( '/', array_slice( $parts, 0, $index + 1 ) );
			$this_path = ltrim( $this_path, '/' ) ?: '/';
			$item_url = add_query_arg( array( 'adirectory' => $this_path ), $url );

			if ( $index == count( $parts ) - 1 ) {
				$item = esc_html( $item ) . '/';
			} else {
				$item = sprintf( '<a href="%s">%s/</a>', esc_url( $item_url ), esc_html( $item ) );
			}
		}

		$dirparts = implode( '', $parts );

		?>
		<div class="frmsvr_wrap">
			<form method="post" action="<?php echo esc_url( $url ); ?>">
				<p><?php printf( __( '<strong>Current Directory:</strong> <span id="cwd">%s</span>', 'add-from-server' ), $dirparts ) ?></p>
				<?php $this->display_quick_jumps( $url ); ?>
				<?php if ( 'media-upload.php' == $GLOBALS['pagenow'] && $post_id > 0 ) : ?>
					<p><?php _e( 'Once you have Imported your files, head over to <strong>Insert Media</strong> to add them to your post.', 'add-from-server' ); ?></p>
				<?php endif; ?>
				<table class="widefat">
					<thead>
					<tr>
						<td class="check-column"><input type='checkbox'/></td>
						<td><?php _e( 'File', 'add-from-server' ); ?></td>
					</tr>
					</thead>
					<tbody>
					<?php
					$parent = dirname( $cwd );
					if ( $parent != $cwd && (strpos( $parent, $this->get_root() ) === 0) && is_readable( $parent ) ) :
						$parent = preg_replace( '!^' . preg_quote( $this->get_root(), '!' ) . '!i', '', $parent );
						?>
						<tr>
							<td>&nbsp;</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'adirectory' => rawurlencode( $parent ) ), $url ) ); ?>"
								   title="<?php echo esc_attr( dirname( $cwd ) ) ?>"><?php _e( 'Parent Folder', 'add-from-server' ); ?></a>
							</td>
						</tr>
					<?php endif; ?>
					<?php
					$directories = array();
					foreach ( (array)$files as $key => $file ) {
						if ( is_dir( $file ) ) {
							$directories[] = $file;
							unset($files[$key]);
						}
					}

					sort( $directories );
					sort( $files );

					foreach ( (array)$directories as $file ) :
						$filename = preg_replace( '!^' . preg_quote( $cwd ) . '!i', '', $file );
						$filename = ltrim( $filename, '/' );
						$folder_url = add_query_arg( array( 'directory' => rawurlencode( $filename ), 'import-date' => $import_date, 'gallery' => $import_to_gallery ), $url );
						?>
						<tr>
							<td>&nbsp;</td>
							<td>
								<a href="<?php echo esc_url( $folder_url ); ?>"><?php echo esc_html( rtrim( $filename, '/' ) . '/' ); ?></a>
							</td>
						</tr>
					<?php
					endforeach;
					$names = $rejected_files = $unreadable_files = array();
					$unfiltered_upload = current_user_can( 'unfiltered_upload' );
					foreach ( (array)$files as $key => $file ) {
						if ( !$unfiltered_upload ) {
							$wp_filetype = wp_check_filetype( $file );
							if ( false === $wp_filetype['type'] ) {
								$rejected_files[] = $file;
								unset($files[$key]);
								continue;
							}
						}
						if ( !is_readable( $file ) ) {
							$unreadable_files[] = $file;
							unset($files[$key]);
							continue;
						}
					}

					foreach ( array( 'meets_guidelines' => $files, 'unreadable' => $unreadable_files, 'doesnt_meets_guidelines' => $rejected_files ) as $key => $_files ) :
						$file_meets_guidelines = $unfiltered_upload || ('meets_guidelines' == $key);
						$unreadable = 'unreadable' == $key;
						foreach ( $_files as $file_index => $file ) :
							$classes = array();

							if ( !$file_meets_guidelines ) {
								$classes[] = 'doesnt-meet-guidelines';
							}
							if ( $unreadable ) {
								$classes[] = 'unreadable';
							}

							$filename = preg_replace( '!^' . preg_quote( $cwd, '!' ) . '!', '', $file );
							$filename = ltrim( $filename, '/' );

							?>
							<tr class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" title="<?php if ( !$file_meets_guidelines ) {
									esc_attr_e( 'Sorry, this file type is not permitted for security reasons. Please see the FAQ.', 'add-from-server' );
								} elseif ( $unreadable ) {
									esc_attr_e( 'Sorry, but this file is unreadable by your Webserver. Perhaps check your File Permissions?', 'add-from-server' );
								} ?>">
								<th class='check-column'>
									<input type='checkbox' id='file-<?php echo (int)$file_index; ?>' name='files[]' value='<?php echo esc_attr( $filename ); ?>' <?php disabled( !$file_meets_guidelines || $unreadable ); ?> />
								</th>
								<td>
									<label for='file-<?php echo (int)$file_index; ?>'><?php echo esc_html( $filename ); ?></label>
								</td>
							</tr>
						<?php endforeach; endforeach; ?>
					</tbody>
					<tfoot>
					<tr>
						<td class="check-column"><input type='checkbox'/></td>
						<td><?php _e( 'File', 'add-from-server' ); ?></td>
					</tr>
					</tfoot>
				</table>

				<fieldset>
					<legend><?php _e( 'Import Options', 'add-from-server' ); ?></legend>

					<?php if ( $post_id ) : ?>
						<input type="checkbox" name="gallery" id="gallery-import" <?php checked( $import_to_gallery ); ?> /><label for="gallery-import"><?php _e( 'Attach imported files to this post', 'add-from-server' ) ?></label>
						<br class="clear"/>
					<?php endif; ?>
					<?php _e( 'Set the imported date to the', 'add-from-server' ); ?>
					<input type="radio" name="import-date" id="import-time-currenttime" value="current" <?php checked( 'current', $import_date ); ?> /> <label for="import-time-currenttime"><?php _e( 'Current Time', 'add-from-server' ); ?></label>
					<input type="radio" name="import-date" id="import-time-filetime" value="file" <?php checked( 'file', $import_date ); ?> /> <label for="import-time-filetime"><?php _e( 'File Time', 'add-from-server' ); ?></label>
					<?php if ( $post_id ) : ?>
						<input type="radio" name="import-date" id="import-time-posttime" value="post" <?php checked( 'post', $import_date ); ?> /> <label for="import-time-posttime"><?php _e( 'Post Time', 'add-from-server' ); ?></label>
					<?php endif; ?>
				</fieldset>
				<br class="clear"/>
				<?php wp_nonce_field( 'afs_import' ); ?>
				<input type="hidden" name="cwd" value="<?php echo esc_attr( $cwd ); ?>"/>
				<?php submit_button( __( 'Import', 'add-from-server' ), 'primary', 'import', false ); ?>
			</form>
			<?php $this->language_notice(); ?>
		</div>
	<?php
	}

	function display_quick_jumps( $url ) {
		$quickjumps = array();
		$quickjumps[] = array(	
			__( 'WordPress Root', 'add-from-server' ),
			ucfirst( wp_normalize_path( ABSPATH ) ) // WP < 4.4 Compat: ucfirt
		);
		if ( ($uploads = wp_upload_dir()) && false === $uploads['error'] ) {
			$quickjumps[] = array(
				__( 'Uploads Folder', 'add-from-server' ),
				ucfirst( wp_normalize_path( $uploads['path'] ) ) // WP < 4.4 Compat: ucfirt
			);
		}
		$quickjumps[] = array(
			__( 'Content Folder', 'add-from-server' ),
			ucfirst( wp_normalize_path( WP_CONTENT_DIR ) ) // WP < 4.4 Compat: ucfirt
		);

		$quickjumps = apply_filters( 'frmsvr_quickjumps', $quickjumps );

		if ( empty( $quickjumps ) ) {
			return;
		}

		$pieces = array();
		foreach ( $quickjumps as $jump ) {
			list( $text, $adir ) = $jump;
			$adir = ucfirst( wp_normalize_path( $adir ) ); // WP < 4.4 Compat: ucfirt

			// Validate it's within the locked directory
			if ( strpos( $adir, $this->get_root() ) === false )
				continue;

			$adir = preg_replace( '!^' . preg_quote( $this->get_root(), '!' ) . '!i', '', $adir );
			if ( strlen( $adir ) > 1 ) {
				$adir = ltrim( $adir, '/' );
			}

			$durl = add_query_arg( array( 'adirectory' => rawurlencode( $adir ) ), $url );
			$pieces[] = sprintf( '<a href="%s">%s</a>', esc_url( $durl ), esc_html( $text ) );
		}

		if ( !empty( $pieces ) ) {
			printf( '<p>' .  __( '<strong>Quick Jump:</strong> %s', 'add-from-server' ) . '<p>', implode( ' | ', $pieces ) );
		}
	}

	function find_files( $folder ) {
		if ( !is_readable( $folder ) ) {
			return array();
		}

		return glob( rtrim( $folder, '/' ) . '/*' );
	}

	function language_notice( $force = false ) {
		$message_english = 'Hi there!
I notice you use WordPress in a Language other than English (US), Did you know you can translate WordPress Plugins into your native language as well?
If you\'d like to help out with translating this plugin into %1$s you can head over to <a href="%2$s">translate.WordPress.org</a> and suggest translations for any languages which you know.
Thanks! Dion.';
		/* translators: %1$s = The Locale (de_DE, en_US, fr_FR, he_IL, etc). %2$s = The translate.wordpress.org link to the plugin overview */
		$message = __( 'Hi there!
I notice you use WordPress in a Language other than English (US), Did you know you can translate WordPress Plugins into your native language as well?
If you\'d like to help out with translating this plugin into %1$s you can head over to <a href="%2$s">translate.WordPress.org</a> and suggest translations for any languages which you know.
Thanks! Dion.', 'add-from-server' );

		$locale = get_locale();
		if ( function_exists( 'get_user_locale' ) ) {
			$locale = get_user_locale();
		}

		// Don't display the message for English (Any) or what we'll assume to be fully translated localised builds.
		if ( 'en_' === substr( $locale, 0, 3 ) || ( $message != $message_english && ! $force  ) ) {
			return false;
		}

		$translate_url = 'https://translate.wordpress.org/projects/wp-plugins/add-from-server/stable';

		echo '<div class="notice notice-info"><p>' . sprintf( nl2br( $message ), get_locale(), $translate_url ) . '</p></div>';
	}

}
