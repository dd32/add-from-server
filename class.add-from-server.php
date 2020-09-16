<?php
namespace dd32\WordPress\AddFromServer;

const COOKIE = 'frmsvr_path';

class Plugin {

	public static function instance() {
		static $instance = false;
		$class           = static::class;

		return $instance ?: ( $instance = new $class );
	}

	protected function __construct() {
		add_action( 'init', [ $this, 'init' ] );
	}

	function init() {
		if (
			current_user_can( 'upload_files' ) &&
			current_user_can( 'unfiltered_html' )
		) {
			add_action( 'admin_init', [ $this, 'admin_init' ] );
			add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		}
	}

	function admin_init() {
		// Register JS & CSS
		wp_register_style( 'add-from-server', plugins_url( '/add-from-server.css', __FILE__ ), array(), VERSION );

		// Enqueue JS & CSS
		add_action( 'load-media_page_add-from-server', [ $this, 'add_styles' ] );
		add_action( 'media_upload_server', [ $this, 'add_styles' ] );

		add_filter( 'plugin_action_links_' . PLUGIN, [ $this, 'add_configure_link' ] );

		// Add actions/filters
		add_filter( 'media_upload_tabs', [ $this, 'tabs' ] );
		add_action( 'media_upload_server', [ $this, 'tab_handler' ] );

		// Handle the path selection early.
		$this->path_selection_cookie();
	}

	function admin_menu() {
		add_media_page(
			__( 'Add From Server', 'add-from-server' ),
			__( 'Add From Server', 'add-from-server' ),
			'upload_files',
			'add-from-server',
			[ $this, 'menu_page' ]
		);

		add_options_page( __( 'Add From Server', 'add-from-server' ), __( 'Add From Server', 'add-from-server' ), 'manage_options', 'add-from-server-settings', [ $this, 'options_page' ] );
	}

	function add_configure_link( $_links ) {
		$links = array();
		if ( current_user_can( 'upload_files' ) ) {
			$links[] = '<a href="' . admin_url( 'upload.php?page=add-from-server' ) . '">' . __( 'Import Files', 'add-from-server' ) . '</a>';
		}
		if ( current_user_can( 'manage_options' ) ) {
			$links[] = '<a href="' . admin_url( 'options-general.php?page=add-from-server-settings' ) . '">' . __( 'Options', 'add-from-server' ) . '</a>';
		}

		return array_merge( $links, $_links );
	}

	// Add a tab to the media uploader:
	function tabs( $tabs ) {
		$tabs['server'] = __( 'Add From Server', 'add-from-server' );
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

		$body_id = 'media-upload';
		iframe_header( __( 'Add From Server', 'add-from-server' ) );

		$this->handle_imports();

		$this->main_content();

		iframe_footer();
	}

	function menu_page() {
		// Handle any imports:
		$this->handle_imports();

		echo '<div class="wrap">';
		echo '<h1>' . __( 'Add From Server', 'add-from-server' ) . '</h1>';

		$this->main_content();

		echo '</div>';
	}

	function options_page() {
		include __DIR__ . '/class.add-from-server-settings.php';
		$settings = new Settings();
		$settings->render();
	}

	function get_root() {
		// Lock users to either
		// a) The 'ADD_FROM_SERVER' constant.
		// b) Their home directory.
		// c) The parent directory of the current install or wp-content directory.

		if ( defined( 'ADD_FROM_SERVER' ) ) {
			$root = ADD_FROM_SERVER;
		} elseif ( str_starts_with( __FILE__, '/home/' ) ) {
			$root = implode( '/', array_slice( explode( '/', __FILE__ ), 0, 3 ) );
		} else {
			if ( str_starts_with( WP_CONTENT_DIR, ABSPATH ) ) {
				$root = dirname( ABSPATH );
			} else {
				$root = dirname( WP_CONTENT_DIR );
			}
		}

		// Precautions. The user is using the folder placeholder code. Abort for lower-privledge users.
		if (
			str_contains( get_option( 'frmsvr_root', '%' ), '%' )
			&&
			! defined( 'ADD_FROM_SERVER' )
			&&
			! current_user_can( 'unfiltered_html' )
		) {
			$root = false;
		}

		return $root;
	}

	function path_selection_cookie() {
		if ( isset( $_REQUEST['path'] ) ) {
			$_COOKIE[ COOKIE ] = $_REQUEST['path'];

			$parts = parse_url( admin_url(), PHP_URL_HOST );

			setcookie(
				COOKIE,
				wp_unslash( $_COOKIE[ COOKIE ] ),
				time() + 30 * DAY_IN_SECONDS,
				parse_url( admin_url(), PHP_URL_PATH ),
				parse_url( admin_url(), PHP_URL_HOST ),
				'https' === parse_url( admin_url(), PHP_URL_SCHEME ),
				true
			);
		}
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

	protected function get_default_dir() {
		$root = $this->get_root();

		if ( false !== str_starts_with( WP_CONTENT_DIR, $root ) ) {
			return WP_CONTENT_DIR;
		}

		return $root;
	}

	// Create the content for the page
	function main_content() {
		global $pagenow;

		$post_id = isset($_REQUEST['post_id']) ? intval( $_REQUEST['post_id'] ) : 0;
		$import_to_gallery = isset($_POST['gallery']) && 'on' == $_POST['gallery'];
		if ( !$import_to_gallery && !isset($_REQUEST['cwd']) ) {
			$import_to_gallery = true;
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

		$root = $this->get_root();
		$cwd  = $this->get_default_dir();
		if ( ! empty( $_COOKIE[ COOKIE ] ) ) {
			$cwd = realpath( trailingslashit( $root ) . wp_unslash( $_COOKIE[ COOKIE ] ) );
		}

		// Validate it.
		if ( ! str_starts_with( $cwd, $root ) ) {
			$cwd = $root;
		}

		$cwd_relative = substr( $cwd, strlen( $root ) );

		// Make a list of the directories the user can enter.
		$dirparts = [
			'<a href="' . esc_url( add_query_arg( 'path', '/', $url ) ) . '">' . esc_html( basename( $root ) ) . '</a>'
		];

		$dir_path = '';
		foreach ( explode( '/', $cwd_relative ) as $dir ) {
			$dir_path .= '/' . $dir;
			$dirparts[] = '<a href="' . esc_url( add_query_arg( 'path', rawurlencode( $dir_path ), $url ) ) . '">' . esc_html( $dir ) . '/</a> ';
		}

		$dirparts = implode( '', $dirparts );

		// Get a list of files to show.
		$nodes = glob( rtrim( $cwd, '/' ) . '/*' ) ?: [];

		$directories = array_flip( array_filter( $nodes, function( $node ) {
			return is_dir( $node );
		} ) );
		array_walk( $directories, function( &$data, $path ) use( $root ) {
			$data = [
				'text' => basename( $path ) . '/',
				'path' => substr( $path, strlen( $root ) + 1 )
			];
		} );
		// Prefix the parent directory.
		if ( str_starts_with( dirname( $cwd ), $root ) ) {
			$directories = array_merge(
				[
					dirname( $cwd ) => [
						'text' => __( 'Parent Folder', 'add-from-server' ),
						'path' => substr( dirname( $cwd ), strlen( $root ) + 1 ) ?: '/',
					]
				],
				$directories
			);
		}

		$files = array_flip( array_filter( $nodes, function( $node ) {
			return is_file( $node );
		} ) );
		array_walk( $files, function( &$data, $path ) use( $root ) {
			$importable = ( false !== wp_check_filetype( $path )['type'] || current_user_can( 'unfiltered_upload' ) );
			$readable   = is_readable( $path );

			$data = [
				'text'       => basename( $path ),
				'file'       => substr( $path, strlen( $root ) + 1 ),
				'importable' => $importable,
				'readable'   => $readable,
				'error'      => (
					! $importable ? 'doesnt-meet-guidelines' : (
						! $readable ? 'unreadable' : false
					)
				),
			];
		} );

		// Importable files first.
		uasort( $files, function( $a, $b ) {
			return $a['error'] <=> $b['error'];
		} );

		?>
		<div class="frmsvr_wrap">
			<form method="post" action="<?php echo esc_url( $url ); ?>">
				<p><?php
					printf(
						__( '<strong>Current Directory:</strong> %s', 'add-from-server' ),
						'<span id="cwd">' . $dirparts . '</span>'
					);
				?></p>

				<table class="widefat">
					<thead>
					<tr>
						<td class="check-column"><input type='checkbox'/></td>
						<td><?php _e( 'File', 'add-from-server' ); ?></td>
					</tr>
					</thead>
					<tbody>
					<?php

					foreach ( $directories as $dir ) {
						printf(
							'<tr>
								<td>&nbsp;</td>
								<td><a href="%s">%s</a></td>
							</tr>',
							esc_url( add_query_arg( 'path', $dir['path'], $url ) ),
							esc_html( $dir['text'] )
						);
					}

					$file_id = 0;
					foreach ( $files as $file ) {
						$error_str = '';
						if ( 'doesnt-meet-guidelines' === $file['error'] ) {
							$error_str = __( 'Sorry, this file type is not permitted for security reasons. Please see the FAQ.', 'add-from-server' );
						} else if ( 'unreadable' === $file['error'] ) {
							$error_str = __( 'Sorry, but this file is unreadable by your Webserver. Perhaps check your File Permissions?', 'add-from-server' );
						}

						printf(
							'<tr class="%1$s" title="%2$s">
								<th class="check-column">
									<input type="checkbox" id="file-%3$d" name="files[]" value="%4$s" %5$s />
								</th>
								<td><label for="file-%3$d">%6$s</label></td>
							</tr>',
							$file['error'] ?: '', $error_str, // 1, 2
							$file_id++, // 3
							$file['file'], // 4
							disabled( false, $file['readable'] && $file['importable'], false ), // 5
							esc_html( $file['text'] ) // 6
						);
					}
					?>

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
				<?php submit_button( __( 'Import', 'add-from-server' ), 'primary', 'import', false ); ?>
			</form>
			<?php $this->language_notice(); ?>
		</div>
	<?php
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
