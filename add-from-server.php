<?php
/*
Plugin Name: Add From Server
Version: 2.4
Plugin URI: http://dd32.id.au/wordpress-plugins/add-from-server/
Description: Plugin to allow the Media Manager to add files from the webservers filesystem. <strong>Note:</strong> All files are copied to the uploads directory.
Author: Dion Hulse
Author URI: http://dd32.id.au/
*/

$GLOBALS['add-from-server'] = new add_from_server();
class add_from_server {

	var $version = '2.4';
	
	var $meets_guidelines = array(); // Internal use only.
	
	function get_root() {
		if ( defined('AFS_ROOT') )
			return untrailingslashit(AFS_ROOT);
		return untrailingslashit(str_replace('\\', '/', ABSPATH));
	}
	function user_allowed() {
		return current_user_can('upload_files');
	}
	
	function add_from_server() {
		//Register general hooks.
		add_action('admin_init', array(&$this, 'admin_init'));
		add_action('admin_menu', array(&$this, 'admin_menu'));
	}
	
	function admin_init() {
		//Load any translation files needed:
		load_plugin_textdomain('add-from-server', '', dirname(plugin_basename(__FILE__)) . '/langs/');

		//Register our JS & CSS
		wp_register_style ('add-from-server', plugins_url( '/add-from-server.css', __FILE__ ), array(), $this->version);

		//Enqueue JS & CSS
		add_action('load-media_page_add-from-server', array(&$this, 'add_styles') );
		add_action('media_upload_server', array(&$this, 'add_styles') );

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'add_configure_link'));

		if ( $this->user_allowed() ) {
			//Add actions/filters
			add_filter('media_upload_tabs', array(&$this, 'tabs'));
			add_action('media_upload_server', array(&$this, 'tab_handler'));
		}
	}

	function admin_menu() {
		if ( $this->user_allowed() )
			add_media_page( __('Add From Server', 'add-from-server'), __('Add From Server', 'add-from-server'), 'upload_files', 'add-from-server', array(&$this, 'menu_page') );
	}

	function add_configure_link($_links) {
		$links = array();
		if ( $this->user_allowed() )
			$links[] = '<a href="' . admin_url('upload.php?page=add-from-server') . '">' . __('Add Files', 'add-from-server') . '</a>';
		if ( current_user_can('manage_options') )
			$links[] = '<a href="' . admin_url('options.php?page=add-from-server') . '">' . __('Options', 'add-from-server') . '</a>';

		return array_merge($links, $_links);
	}

	//Add a tab to the media uploader:
	function tabs($tabs) {
		if ( $this->user_allowed() )
			$tabs['server'] = __('Add From Server', 'add-from-server');
		return $tabs;
	}
	
	function add_styles() {
		//Enqueue support files.
		if ( 'media_upload_server' == current_filter() )
			wp_enqueue_style('media');
		wp_enqueue_style('add-from-server');
	}

	//Handle the actual page:
	function tab_handler(){
		if ( ! $this->user_allowed() )
			return;

		//Set the body ID
		$GLOBALS['body_id'] = 'media-upload';

		//Do an IFrame header
		iframe_header( __('Add From Server', 'add-from-server') );

		//Add the Media buttons	
		media_upload_header();

		//Handle any imports:
		$this->handle_imports();

		//Do the content
		$this->main_content();

		//Do a footer
		iframe_footer();
	}
	
	function menu_page() {
		if ( ! $this->user_allowed() )
			return;

		//Handle any imports:
		$this->handle_imports();

		//Do the content
		$this->main_content();

	}

	//Handle the imports
	function handle_imports() {

		if ( !empty($_POST['files']) && !empty($_POST['cwd']) ) {

			$files = array_map('stripslashes', $_POST['files']);

			$cwd = trailingslashit(stripslashes($_POST['cwd']));
			$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;

			$import_to_gallery = isset($_POST['gallery']) && 'on' == $_POST['gallery'];
			if ( ! $import_to_gallery && !isset($_REQUEST['cwd']) )
				$import_to_gallery = true; // cwd should always be set, if it's not, and neither is gallery, this must be the first page load.

			if ( ! $import_to_gallery )
				$post_id = 0;

			foreach ( (array)$files as $file ) {
				$filename = $cwd . $file;
				$id = $this->handle_import_file($filename, $post_id);
				if ( is_wp_error($id) ) {
					echo '<div class="updated error"><p>' . sprintf(__('<em>%s</em> was <strong>not</strong> imported due to an error: %s', 'add-from-server'), $file, $id->get_error_message() ) . '</p></div>';
				} else {
					//increment the gallery count
					if ( $import_to_gallery )
						echo "<script type='text/javascript'>jQuery('#attachments-count').text(1 * jQuery('#attachments-count').text() + 1);</script>";
					echo '<div class="updated"><p>' . sprintf(__('<em>%s</em> has been added to Media library', 'add-from-server'), $file) . '</p></div>';
				}
			}
		}
	}

	//Handle an individual file import.
	function handle_import_file($file, $post_id = 0) {
		set_time_limit(120);
		$time = current_time('mysql');
		if ( $post_id && ($post = get_post($post_id)) ) {
			if ( substr( $post->post_date, 0, 4 ) > 0 )
				$time = $post->post_date;
		}

		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( ! ( ( $uploads = wp_upload_dir($time) ) && false === $uploads['error'] ) )
			return new WP_Error( 'upload_error', $uploads['error']);

		$wp_filetype = wp_check_filetype( $file, null );

		extract( $wp_filetype );
		
		if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) )
			return new WP_Error('wrong_file_type', __( 'File type does not meet security guidelines. Try another.' ) ); //A WP-core string..

		//Is the file allready in the uploads folder?
		if ( preg_match('|^' . preg_quote(str_replace('\\', '/', $uploads['basedir'])) . '(.*)$|i', $file, $mat) ) {

			$filename = basename($file);
			$new_file = $file;

			$url = $uploads['baseurl'] . $mat[1];

			$attachment = get_posts(array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => $uploads['subdir'] . '/' . $filename ));
			if ( !empty($attachment) )
				return new WP_Error('file_exists', __( 'Sorry, That file already exists in the WordPress media library.' ) );

			//Ok, Its in the uploads folder, But NOT in WordPress's media library.
			$time = @filemtime($file);
			if ( preg_match("|(\d+)/(\d+)|", $mat[1], $datemat) ) { //So lets set the date of the import to the date folder its in, IF its in a date folder.
				$hour = $min = $sec = 0;
				$day = 1;
				$year = $datemat[1];
				$month = $datemat[2];

				// If the files datetime is set, and it's in the same region of upload directory, set the minute details to that too, else, override it.
				if ( $time && date('Y-m', $time) == "$year-$month" )
					list($hour, $min, $sec, $day) = explode(';', date('H;i;s;j', $time) );

				$time = mktime($hour, $min, $sec, $month, $day, $year);
			}

			if ( $time ) {
				$post_date = date( 'Y-m-d H:i:s', $time);
				$post_date_gmt = gmdate( 'Y-m-d H:i:s', $time);
			}
		} else {
			$filename = wp_unique_filename( $uploads['path'], basename($file));

			// copy the file to the uploads dir
			$new_file = $uploads['path'] . '/' . $filename;
			if ( false === @copy( $file, $new_file ) )
				wp_die(sprintf( __('The selected file could not be copied to %s.', 'add-from-server'), $uploads['path']));

			// Set correct file permissions
			$stat = stat( dirname( $new_file ));
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );
			// Compute the URL
			$url = $uploads['url'] . '/' . $filename;
		}

		// Compute the URL
		$url = $uploads['url'] . '/' . rawurlencode($filename);

		//Apply upload filters
		$return = apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ) );
		$new_file = $return['file'];
		$url = $return['url'];
		$type = $return['type'];

		$title = preg_replace('!\.[^.]+$!', '', basename($file));
		$content = '';

		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata($new_file) ) {
			if ( '' != trim($image_meta['title']) )
				$title = trim($image_meta['title']);
			if ( '' != trim($image_meta['caption']) )
				$content = trim($image_meta['caption']);
		}

		if ( empty($post_date) )
			$post_date = current_time('mysql');
		if ( empty($post_date_gmt) )
			$post_date_gmt = current_time('mysql', 1);

		// Construct the attachment array
		$attachment = array(
			'post_mime_type' => $type,
			'guid' => $url,
			'post_parent' => $post_id,
			'post_title' => $title,
			'post_name' => $title,
			'post_content' => $content,
			'post_date' => $post_date,
			'post_date_gmt' => $post_date_gmt
		);

		// Save the data
		$id = wp_insert_attachment($attachment, $new_file, $post_id);
		if ( !is_wp_error($id) ) {
			$data = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $data );
		}

		return $id;
	}

	//Create the content for the page
	function main_content() {
		global $pagenow;
		$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
		$import_to_gallery = isset($_POST['gallery']) && 'on' == $_POST['gallery'];
		if ( ! $import_to_gallery && !isset($_REQUEST['cwd']) )
			$import_to_gallery = true; // cwd should always be set, if it's not, and neither is gallery, this must be the first page load.
		$import_date = isset($_REQUEST['import-date']) ? $_REQUEST['import-date'] : 'current';

		if ( 'upload.php' == $pagenow )
			$url = admin_url('upload.php?page=add-from-server');
		else
			$url = admin_url('media-upload.php?tab=server');

		if ( $post_id )
			$url = add_query_arg('post_id', $post_id, $url);

		$cwd = trailingslashit(get_option('frmsvr_last_folder', WP_CONTENT_DIR));

		if ( isset($_REQUEST['directory']) ) 
			$cwd .= stripslashes(urldecode($_REQUEST['directory']));

		if ( isset($_REQUEST['adirectory']) )
			$cwd = stripslashes(urldecode($_REQUEST['adirectory']));

		$cwd = preg_replace('![^/]+/\.\./!', '', $cwd);
		$cwd = preg_replace('!//!', '/', $cwd);

		if ( ! is_readable($cwd) && is_readable( $this->get_root() . '/' . ltrim($cwd, '/') ) )
			$cwd = $this->get_root() . '/' . ltrim($cwd, '/');

		if ( ! is_readable($cwd) && get_option('frmsvr_last_folder') )
			$cwd = get_option('frmsvr_last_folder');

		if ( ! is_readable($cwd) )
			$cwd = WP_CONTENT_DIR;

		if ( strpos($cwd, $this->get_root()) === false )
			$cwd = $this->get_root();

		$cwd = str_replace('\\', '/', $cwd);

		$cwd = untrailingslashit($cwd);

		update_option('frmsvr_last_folder', $cwd);

		$files = $this->find_files($cwd, array('levels' => 1));

		$parts = explode('/', ltrim(str_replace($this->get_root(), '', $cwd), '/'));
		$dir = $cwd;
		$dirparts = '';
		for ( $i = count($parts) - 1; $i >= 0; $i-- ) {
			$piece = $parts[$i];
			$adir = implode('/', array_slice($parts, 0, $i+1));
			$durl = esc_url(add_query_arg(array('adirectory' => $adir), $url));
			$dirparts = '<a href="' . $durl . '">' . DIRECTORY_SEPARATOR . $piece . '</a>' . $dirparts;
			$dir = dirname($dir);
		}
		unset($dir, $piece, $adir, $durl);

		?>
		<div class="frmsvr_wrap">
		<p><?php printf(__('<strong>Current Directory:</strong> <span id="cwd">%s</span>', 'add-from-server'), $dirparts) ?></p>
		<?php 
			$quickjumps = array();
			$quickjumps[] = array( __('WordPress Root', 'add-from-server'), ABSPATH );
			if ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] )
				$quickjumps[] = array( __('Uploads Folder', 'add-from-server'), $uploads['path']);
			$quickjumps[] = array( __('Content Folder', 'add-from-server'), WP_CONTENT_DIR );

			$quickjumps = apply_filters('frmsvr_quickjumps', $quickjumps);

			if ( ! empty($quickjumps) ) {
				$pieces = array();
				foreach( $quickjumps as $jump ) {
					list( $text, $adir ) = $jump;
					$adir = str_replace('\\', '/', $adir);
					if ( strpos($adir, $this->get_root()) === false )
						continue;
					$adir = str_replace($this->get_root(), '', $adir);
					$durl = add_query_arg(array('adirectory' => addslashes($adir)), $url);
					$pieces[] = "<a href='$durl'>$text</a>";
				}
				if ( ! empty($pieces) ) {
					echo '<p>';
					printf( __('<strong>Quick Jump:</strong> %s', 'add-from-server'), implode(' | ', $pieces) );
					echo '</p>';
				}
			}
		 ?>
		<form method="post" action="<?php echo $url ?>">
         <?php if ( 'media-upload.php' == $GLOBALS['pagenow'] && $post_id > 0 ) : ?>
		<p><?php printf(__('Once you have selected files to be imported, Head over to the <a href="%s">Media Library tab</a> to add them to your post.', 'add-from-server'), esc_url(admin_url('media-upload.php?type=image&tab=library&post_id=' . $post_id)) ); ?></p>
        <?php endif; ?>
		<table class="widefat">
		<thead>
			<tr>
				<th class="check-column"><input type='checkbox' /></th>
				<th><?php _e('File', 'add-from-server'); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>&nbsp;</td>
				<?php /*  <td class='check-column'><input type='checkbox' id='file-<?php echo $sanname; ?>' name='files[]' value='<?php echo esc_attr($file) ?>' /></td> */ ?>
				<td><a href="<?php echo add_query_arg(array('directory' => '../'), $url) ?>" title="<?php echo esc_attr(dirname($cwd)) ?>"><?php _e('Parent Folder', 'add-from-server') ?></a></td>
			</tr>
		<?php
			$directories = array();
			foreach( (array)$files as $key => $file ) {
				if ( '/' == substr($file, -1) ) {
					$directories[] = $file;
					unset($files[$key]);
				}
			}
			
			sort($directories);
			sort($files);
			
			foreach( (array)$directories as $file  ) :
				$filename = preg_replace('!^' . preg_quote($cwd) . '!', '', $file);
				$filename = ltrim($filename, '/');
				$folder_url = add_query_arg(array('directory' => $filename, 'import-date' => $import_date, 'gallery' => $import_to_gallery ), $url);
		?>
			<tr>
				<td>&nbsp;</td>
				<?php /* <td class='check-column'><input type='checkbox' id='file-<?php echo $sanname; ?>' name='files[]' value='<?php echo esc_attr($file) ?>' /></td> */ ?>
				<td><a href="<?php echo $folder_url ?>"><?php echo $filename ?></a></td>
			</tr>
		<?php
			endforeach;
			$names = array();
			$rejected_files = array();
			$unfiltered_upload = current_user_can( 'unfiltered_upload' );
			if ( ! $unfiltered_upload ) {
				foreach ( (array)$files as $key => $file ) {
					$wp_filetype = wp_check_filetype( $file );
					if ( false === $wp_filetype['type'] ) {
						$rejected_files[] = $file;
						unset($files[$key]);
					}
				}
			}
			
			foreach ( array( 'meets_guidelines' => $files, 'doesnt_meets_guidelines' => $rejected_files) as $key => $_files ) :
			$file_meets_guidelines = $unfiltered_upload || ($key == 'meets_guidelines');
			foreach ( $_files as $file  ) :
				$classes = array();

				if ( ! $file_meets_guidelines )
					$classes[] = 'doesnt-meet-guidelines';

				if ( preg_match('/\.(.+)$/i', $file, $ext_match) ) 
					$classes[] = 'filetype-' . $ext_match[1];

				$filename = preg_replace('!^' . preg_quote($cwd) . '!', '', $file);
				$filename = ltrim($filename, '/');
				$sanname = preg_replace('![^a-zA-Z0-9]!', '', $filename);

				$i = 0;
				while ( in_array($sanname, $names) )
					$sanname = preg_replace('![^a-zA-Z0-9]!', '', $filename) . '-' . ++$i;
				$names[] = $sanname;
		?>
			<tr class="<?php echo esc_attr(implode(' ', $classes)); ?>" title="<?php if ( ! $file_meets_guidelines ) { _e('This filetype does not meet the Security guidelines, Please see the FAQ.', 'add-from-server'); } ?>">
				<th class='check-column'><input type='checkbox' id='file-<?php echo $sanname; ?>' name='files[]' value='<?php echo esc_attr($filename) ?>' <?php disabled(!$file_meets_guidelines); ?> /></th>
				<td><label for='file-<?php echo $sanname; ?>'><?php echo $filename ?></label></td>
			</tr>
			<?php endforeach; endforeach;?>
		</tbody>
		<tfoot>
			<tr>
				<th class="check-column"><input type='checkbox' /></th>
				<th><?php _e('File', 'add-from-server'); ?></th>
			</tr>
		</tfoot>
		</table>

		<fieldset>
			<legend>Import Options</legend>
	
		<?php if ( $post_id != 0 ) : ?>
			<input type="checkbox" name="gallery" id="gallery-import" <?php checked( $import_to_gallery ); ?> /> <label for="gallery-import"><?php _e('Attach imported files to this post', 'add-from-server')?></label>
			<br class="clear" />
		<?php endif; ?>
			<?php _e('Set the imported date to the', 'add-from-server'); ?>
			<input type="radio" name="import-date" id="import-time-currenttime" value="current" <?php checked('current', $import_date); ?> /> <label for="import-time-currenttime"><?php _e('Current Time', 'add-from-server'); ?></label>
			<input type="radio" name="import-date" id="import-time-filetime" value="file" <?php checked('file', $import_date); ?> /> <label for="import-time-filetime"><?php _e('File Time', 'add-from-server'); ?></label>
			<input type="radio" name="import-date" id="import-time-posttime" value="post" <?php checked('post', $import_date); ?> /> <label for="import-time-posttime"><?php _e('Post Time', 'add-from-server'); ?></label>
		</fieldset>
		<br class="clear" />
		<input type="hidden" name="cwd" value="<?php echo esc_attr( $cwd ); ?>" />
		<input type="submit" class="button savebutton" name="import" value="<?php echo esc_attr( __('Import', 'add-from-server') ); ?>" /> 

		</form>
		</div>
	<?php
	}

	//HELPERS	
	function find_files( $folder, $args = array() ) {
	
		$folder = untrailingslashit($folder);
	
		$defaults = array( 'pattern' => '', 'levels' => 100, 'relative' => '' );
		$r = wp_parse_args($args, $defaults);
	
		extract($r, EXTR_SKIP);
		
		//Now for recursive calls, clear relative, we'll handle it, and decrease the levels.
		unset($r['relative']);
		--$r['levels'];
	
		if ( ! $levels )
			return array();
		
		if ( ! is_readable($folder) )
			return false;
	
		$files = array();
		if ( $dir = @opendir( $folder ) ) {
			while ( ( $file = readdir($dir) ) !== false ) {
				if ( in_array($file, array('.', '..') ) )
					continue;
				if ( is_dir( $folder . '/' . $file ) ) {
					$files2 = $this->find_files( $folder . '/' . $file, $r );
					if( $files2 )
						$files = array_merge($files, $files2 );
					else if ( empty($pattern) || preg_match('|^' . str_replace('\*', '\w+', preg_quote($pattern)) . '$|i', $file) )
						$files[] = $folder . '/' . $file . '/';
				} else {
					if ( empty($pattern) || preg_match('|^' . str_replace('\*', '\w+', preg_quote($pattern)) . '$|i', $file) )
						$files[] = $folder . '/' . $file;
				}
			}
		}
		@closedir( $dir );
	
		if ( ! empty($relative) ) {
			$relative = trailingslashit($relative);
			foreach ( $files as $key => $file )
				$files[$key] = preg_replace('!^' . preg_quote($relative) . '!', '', $file);
		}
	
		return $files;
	}
}//end class

?>
