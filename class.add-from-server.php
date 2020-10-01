<?php
namespace dd32\WordPress\AddFromServer;
use WP_Error;

const COOKIE = 'frmsvr_path';

class Plugin {

	public static function instance() {
		static $instance = false;
		$class           = static::class;

		return $instance ?: ( $instance = new $class );
	}

	protected function __construct() {
		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
	}

	function admin_init() {
		// Register JS & CSS
		wp_register_script( 'add-from-server', plugins_url( '/add-from-server.js', __FILE__ ), array( 'jquery' ), VERSION );
		wp_register_style( 'add-from-server', plugins_url( '/add-from-server.css', __FILE__ ), array(), VERSION );

		add_filter( 'plugin_action_links_' . PLUGIN, [ $this, 'add_upload_link' ] );

		// Handle the path selection early.
		$this->path_selection_cookie();
	}

	function admin_menu() {
		$page_slug = add_media_page(
			__( 'Add From Server', 'add-from-server' ),
			__( 'Add From Server', 'add-from-server' ),
			'upload_files',
			'add-from-server',
			[ $this, 'menu_page' ]
		);
		add_action( 'load-' . $page_slug, function() {
			wp_enqueue_style( 'add-from-server' );
			wp_enqueue_script( 'add-from-server' );
		} );
	}

	function add_upload_link( $links ) {
		if ( current_user_can( 'upload_files' ) ) {
			array_unshift( $links, '<a href="' . admin_url( 'upload.php?page=add-from-server' ) . '">' . __( 'Import Files', 'add-from-server' ) . '</a>' );
		}

		return $links;
	}

	function menu_page() {
		// Handle any imports
		$this->handle_imports();

		echo '<div class="wrap">';
		echo '<h1>' . __( 'Add From Server', 'add-from-server' ) . '</h1>';

		$this->outdated_options_notice();
		$this->main_content();
		$this->language_notice();

		echo '</div>';
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
		if ( isset( $_REQUEST['path'] ) && current_user_can( 'upload_files' ) ) {
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

		if ( !empty( $_POST['files'] ) ) {

			check_admin_referer( 'afs_import' );

			$files = wp_unslash( $_POST['files'] );

			$root = $this->get_root();
			if ( ! $root ) {
				return false;
			}

			flush();
			wp_ob_end_flush_all();

			foreach ( (array)$files as $file ) {
				$filename = trailingslashit( $root ) . ltrim( $file, '/' );

				if ( $filename !== realpath( $filename ) ) {
					continue;
				}

				$id = $this->handle_import_file( $filename );

				if ( is_wp_error( $id ) ) {
					echo '<div class="updated error"><p>' . sprintf( __( '<em>%s</em> was <strong>not</strong> imported due to an error: %s', 'add-from-server' ), esc_html( basename( $file ) ), $id->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="updated"><p>' . sprintf( __( '<em>%s</em> has been added to Media library', 'add-from-server' ), esc_html( basename( $file ) ) ) . '</p></div>';
				}

				flush();
				wp_ob_end_flush_all();
			}
		}
	}

	// Handle an individual file import.
	function handle_import_file( $file ) {
		set_time_limit( 0 );

		$file = wp_normalize_path( $file );

		// Initially, Base it on the -current- time.
		$time = time();

		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( ! ( ( $uploads = wp_upload_dir( $time ) ) && false === $uploads['error'] ) ) {
			return new WP_Error( 'upload_error', $uploads['error'] );
		}

		$wp_filetype = wp_check_filetype( $file, null );
		$type = $wp_filetype['type'];
		$ext  = $wp_filetype['ext'];
		if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
			return new WP_Error( 'wrong_file_type', __( 'Sorry, this file type is not permitted for security reasons.', 'add-from-server' ) );
		}

		// Is the file already in the uploads folder?
		if ( preg_match( '|^' . preg_quote( wp_normalize_path( $uploads['basedir'] ), '|' ) . '(.*)$|i', $file, $mat ) ) {

			$filename = basename( $file );
			$new_file = $file;

			$url = $uploads['baseurl'] . $mat[1];

			$attachment = get_posts( array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => ltrim( $mat[1], '/' ) ) );
			if ( !empty($attachment) ) {
				return new WP_Error( 'file_exists', __( 'Sorry, that file already exists in the WordPress media library.', 'add-from-server' ) );
			}

			$time = filemtime( $file ) ?: time();

			// Ok, Its in the uploads folder, But NOT in WordPress's media library.
			if ( preg_match( '|^/?(?P<Ym>(?P<year>\d{4})/(?P<month>\d{2}))|', dirname( $mat[1] ), $datemat ) ) {
				// The file date and the folder it's in are mismatched. Set it to the date of the folder.
				if ( date( 'Y/m', $time ) !== $datemat['Ym'] ) {
					$time = mktime( 0, 0, 0, $datemat['month'], 1, $datemat['year'] );
				}
			}

			// A new time has been found! Get the new uploads folder:
			// A writable uploads dir will pass this test. Again, there's no point overriding this one.
			if ( !(($uploads = wp_upload_dir( $time )) && false === $uploads['error']) ) {
				return new WP_Error( 'upload_error', $uploads['error'] );
			}
			$url = $uploads['baseurl'] . $mat[1];
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

		// Construct the attachment array
		$attachment = [
			'post_mime_type' => $type,
			'guid'           => $url,
			'post_parent'    => 0,
			'post_title'     => $title,
			'post_name'      => $title,
			'post_content'   => $content,
			'post_excerpt'   => $excerpt,
			'post_date'      => gmdate( 'Y-m-d H:i:s', $time ),
			'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $time ),
		];

		$attachment = apply_filters( 'afs-import_details', $attachment, $file, 0, 'current' );

		// Save the data
		$id = wp_insert_attachment( $attachment, $new_file, 0 );
		if ( !is_wp_error( $id ) ) {
			$data = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $data );
		}

		return $id;
	}

	protected function get_default_dir() {
		$root = $this->get_root();

		if ( str_starts_with( WP_CONTENT_DIR, $root ) ) {
			return WP_CONTENT_DIR;
		}

		return $root;
	}

	// Create the content for the page
	function main_content() {

		$url = admin_url( 'upload.php?page=add-from-server' );

		$root = $this->get_root();
		if ( ! $root ) {
			return; // Intervention required.
		}

		$cwd = $this->get_default_dir();
		if ( ! empty( $_COOKIE[ COOKIE ] ) ) {
			$cwd = realpath( trailingslashit( $root ) . wp_unslash( $_COOKIE[ COOKIE ] ) );
		}

		// Validate it.
		if ( ! str_starts_with( $cwd, $root ) ) {
			$cwd = $root;
		}

		$cwd_relative = substr( $cwd, strlen( $root ) );

		// Make a list of the directories the user can enter.
		$dirparts = [];
		$dirparts[] = '<a href="' . esc_url( add_query_arg( 'path', rawurlencode( '/' ), $url ) ) . '">' . esc_html( trailingslashit( $root ) ) . '</a> ';

		$dir_path = '';
		foreach ( array_filter( explode( '/', $cwd_relative ) ) as $dir ) {
			$dir_path .= '/' . $dir;
			$dirparts[] = '<a href="' . esc_url( add_query_arg( 'path', rawurlencode( $dir_path ), $url ) ) . '">' . esc_html( $dir ?: basename( $root ) ) . '/</a> ';
		}

		$dirparts = implode( '', $dirparts );

		// Sort alphabetically correctly..
		$sort_by_text = function( $a, $b ) {
			return strtolower( $a['text'] ) <=> strtolower( $b['text'] );
		};

		// Get a list of files to show.
		$nodes = glob( rtrim( $cwd, '/' ) . '/*' ) ?: [];

		$directories = array_flip( array_filter( $nodes, function( $node ) {
			return is_dir( $node );
		} ) );

		$get_import_root = function( $path ) use ( &$get_import_root ) {
			if ( ! is_readable( $path ) ) {
				return false;
			}

			$files = glob( $path . '/*' );
			if ( ! $files ) {
				return false;
			}

			$has_files = false;
			foreach ( $files as $i => $file ) {
				if ( is_file( $file ) ) {
					$has_files = true;
					break;
				} else {
					if ( $get_import_root( $file ) ) {
						$has_files = true;
						break;
					} else {
						unset( $files[ $i ] );
					}
				}
			}
			if ( ! $has_files ) {
				return false;
			}

			// Rekey the array incase anything was removed.
			$files = array_values( $files );

			if ( 1 === count( $files ) && is_dir( $files[0] ) ) {
				return $get_import_root( $files[0] );
			}

			return $path;
		};

		$get_root_relative_path = function( $path ) use( $root ) {
			$root_offset = strlen( $root );
			if ( '/' !== $root ) {
				$root_offset += 1;
			}

			return substr( $path, $root_offset );
		};

		array_walk( $directories, function( &$data, $path ) use( $root, $cwd_relative, $get_import_root, $get_root_relative_path ) {
			$import_root = $get_import_root( $path );
			if ( ! $import_root ) {
				// Unreadable, etc.
				$data = false;
				return;
			}

			$data = [
				'text' => substr(
						$get_root_relative_path( $import_root ),
						strlen( $cwd_relative )
					) . '/',
				'path' => $get_root_relative_path( $import_root )
			];

			$data['text'] = ltrim( $data['text'], '/' );
		} );

		$directories = array_filter( $directories );

		// Sort the directories case insensitively.
		uasort( $directories, $sort_by_text );

		// Prefix the parent directory.
		if ( str_starts_with( dirname( $cwd ), $root ) && dirname( $cwd ) != $cwd ) {
			$directories = array_merge(
				[
					dirname( $cwd ) => [
						'text' => __( 'Parent Folder', 'add-from-server' ),
						'path' => $get_root_relative_path( dirname( $cwd ) ) ?: '/',
					]
				],
				$directories
			);
		}

		$files = array_flip( array_filter( $nodes, function( $node ) {
			return is_file( $node );
		} ) );
		array_walk( $files, function( &$data, $path ) use( $root, $get_root_relative_path ) {
			$importable = ( false !== wp_check_filetype( $path )['type'] || current_user_can( 'unfiltered_upload' ) );
			$readable   = is_readable( $path );

			$data = [
				'text'       => basename( $path ),
				'file'       => $get_root_relative_path( $path ),
				'importable' => $importable,
				'readable'   => $readable,
				'error'      => (
					! $importable ? 'doesnt-meet-guidelines' : (
						! $readable ? 'unreadable' : false
					)
				),
			];
		} );

		// Sort case insensitively.
		uasort( $files, $sort_by_text );

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
						if ( ! $dir['path'] ) {
							continue;
						}

						printf(
							'<tr>
								<td>&nbsp;</td>
								<td><a href="%s">%s</a></td>
							</tr>',
							esc_url( add_query_arg( 'path', rawurlencode( $dir['path'] ), $url ) ),
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
							$file['error'] ?: '', // 1
							$error_str, // 2
							$file_id++, // 3
							$file['file'], // 4
							disabled( false, $file['readable'] && $file['importable'], false ), // 5
							esc_html( $file['text'] ) // 6
						);
					}

					// If we have any files that are error flagged, add the hidden row.
					if ( array_filter( array_column( $files, 'error' ) ) ) {
						printf(
							'<tr class="hidden-toggle">
								<td>&nbsp;</td>
								<td><a href="#">%1$s</a></td>
							</tr>',
							__( 'Show hidden files', 'add-from-server' )
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

				<br class="clear"/>
				<?php wp_nonce_field( 'afs_import' ); ?>
				<?php submit_button( __( 'Import', 'add-from-server' ), 'primary', 'import', false ); ?>
			</form>
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

	function outdated_options_notice() {
		$old_root = get_option( 'frmsvr_root', '' );

		if (
			$old_root
			&&
			str_contains( $old_root, '%' )
			&&
			! defined( 'ADD_FROM_SERVER' )
		) {
			printf(
				'<div class="notice error"><p>%s</p></div>',
				'You previously used the "Root Directory" option with a placeholder, such as "%username% or "%role%".<br>' .
				'Unfortunately this feature is no longer supported. As a result, Add From Server has been disabled for users who have restricted upload privledges.<br>' .
				'To make this warning go away, empty the "frmsvr_root" option on <a href="options.php#frmsvr_root">options.php</a>.'
			);
		}

		if ( $old_root && ! str_starts_with( $old_root, $this->get_root() ) ) {
			printf(
				'<div class="notice error"><p>%s</p></div>',
				'Warning: Root Directory changed. You previously used <code>' . esc_html( $old_root ) . '</code> as your "Root Directory", ' .
				'this has been changed to <code>' . esc_html( $this->get_root() ) . '</code>.<br>' .
				'To restore your previous settings, add the following line to your <code>wp-config.php</code> file:<br>' .
				'<code>define( "ADD_FROM_SERVER", "' . $old_root . '" );</code><br>' .
				'To make this warning go away, empty the "frmsvr_root" option on <a href="options.php#frmsvr_root">options.php</a>.'
			);
		}
	}

}
