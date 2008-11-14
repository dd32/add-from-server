<?php
/*
Plugin Name: Add From Server
Version: 2.0-alpha
Plugin URI: http://dd32.id.au/wordpress-plugins/add-from-server/
Description: Plugin to allow the Media Manager to add files from the webservers filesystem. <strong>Note:</strong> All files are copied to the uploads directory.
Author: Dion Hulse
Author URI: http://dd32.id.au/
*/

class add_from_server {
	
	var $dd32_requires = 1;
	var $basename = '';
	var $folder = '';
	
	function add_from_server() {
		//Set the directory of the plugin:
		$this->basename = plugin_basename(__FILE__);
		$this->folder = dirname($this->basename);

		//Set the version of the DD32 library this plugin requires.
		$GLOBALS['dd32_version'] = isset($GLOBALS['dd32_version']) ? max($GLOBALS['dd32_version'], $this->dd32_requires) : $this->dd32_requires;
		add_action('init', array(&$this, 'load_dd32'), 20);

		//Register general hooks.
		add_action('admin_init', array(&$this, 'admin_init'));
		register_activation_hook(__FILE__, array(&$this, 'activate'));
		register_deactivation_hook(__FILE__, array(&$this, 'deactivate'));
	}
	
	function load_dd32() {
		//Load common library
		include 'inc/class.dd32.php';
	}
	
	function admin_init() {
		//Load any translation files needed:
		load_plugin_textdomain('add-from-server', '', $this->folder . '/langs/');

		//Register our JS & CSS
		wp_register_script('add-from-server', plugins_url( $this->folder . '/add-from-server.js' ), array('jquery'), 1);
		wp_register_style ('add-from-server', plugins_url( $this->folder . '/add-from-server.css' ));

		//Load common library
		include 'inc/class.dd32.php';

		DD32::add_configure($this->basename, __('Add From Server', 'add-from-server'), admin_url('media-upload.php?tab=server&TB_iframe=true'), array('class' => 'thickbox'));
		DD32::add_changelog($this->basename, 'http://svn.wp-plugins.org/add-from-server/trunk/readme.txt');

		//Add actions/filters
		add_filter('media_upload_tabs', array(&$this, 'tabs'));
		add_action('media_upload_server', array(&$this, 'tab_handler'));
	}

	function activate(){
		global $wp_version;
		if( ! version_compare( $wp_version, '2.7-alpha', '>=') ) {
			if( function_exists('deactivate_plugins') )
				deactivate_plugins(__FILE__);
			wp_die(__('<h1>Add From Server</h1> Sorry, This plugin requires WordPress 2.7+', 'add-from-server'));
		}
	}
	
	function deactivate(){
		delete_option('frmsvr_last_folder');
	}

	//Add a tab to the media uploader:
	function tabs($tabs) {
		if( current_user_can( 'unfiltered_upload' ) )
			$tabs['server'] = __('Add From Server', 'add-from-server');
		return $tabs;
	}

	//Handle the actual page:
	function tab_handler(){
		if( ! current_user_can( 'unfiltered_upload' ) )
			return;

		//Enqueue support files.
		wp_enqueue_style('media');
		wp_enqueue_style('add-from-server');
		wp_enqueue_script('admin-forms');
		wp_enqueue_script('add-from-server');

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

	//Handle the imports
	function handle_imports() {

		if ( isset($_POST['files']) && !empty($_POST['cwd']) ) {

			$files = array_map('stripslashes', $_POST['files']);

			$cwd = trailingslashit(stripslashes($_POST['cwd']));
			$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
			$import_to_gallery = !( isset($_POST['no-gallery']) && 'on' == $_POST['no-gallery'] );
			if ( ! $import_to_gallery )
				$post_id = 0;

			foreach ( (array)$files as $file ) {
				$filename = $cwd . $file;
				$id = $this->handle_import_file($filename, $post_id);
				if ( is_wp_error($id) ) {
					echo '<div class="updated error"><p>' . sprintf(__('<em>%s</em> was <strong>not</strong> imported due to an error: %s', 'add-from-server'), $file, $id) . '</p></div>';
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
	function handle_import_file($file, $post_id) {
	
		$time = current_time('mysql', true);
		if ( $post = get_post($post_id) )
			if ( '0000-00-00 00:00:00' != $post->post_date_gmt )
				$time = $post->post_date_gmt;

		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( ! ( ( $uploads = wp_upload_dir($time) ) && false === $uploads['error'] ) )
			return new WP_Error($uploads['error']);

		$wp_filetype = wp_check_filetype( $file, null );

		extract( $wp_filetype );

		//Is the file allready in the uploads folder?
		if( preg_match('|^' . preg_quote(str_replace('\\', '/', $uploads['basedir'])) . '(.*)$|i', $file, $mat) ) {

			$filename = basename($file);
			$new_file = $file;

			$url = $uploads['baseurl'] . $mat[1];

			$attachment = get_posts(array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => $uploads['subdir'] . '/' . $filename ));
			if ( !empty($attachment) )
				return $attachments[0]->ID;

			//Ok, Its in the uploads folder, But NOT in WordPress's media library.
			if ( preg_match("|(\d+)/(\d+)|", $mat[1], $datemat) ) //So lets set the date of the import to the date folder its in, IF its in a date folder.
				$time = mktime(0,0,0,$datemat[2], 1, $datemat[1]);
			else //Else, set the date based on the date of the files time.
				$time = filemtime($file);

			$post_date = date( 'Y-m-d H:i:s', $time);
			$post_date_gmt = gmdate( 'Y-m-d H:i:s', $time);
		} else {	
			$filename = wp_unique_filename( $uploads['path'], basename($file));

			// copy the file to the uploads dir
			$new_file = $uploads['path'] . '/' . $filename;
			if ( false === @copy( $file, $new_file ) )
				wp_die(sprintf( __('The selected file could not be moved to %s.', 'add-from-server'), $uploads['path']));

			// Set correct file permissions
			$stat = stat( dirname( $new_file ));
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );
			// Compute the URL
			$url = $uploads['url'] . '/' . $filename;
		}

		// Compute the URL
		$url = $uploads['url'] . '/' . $filename;

		$title = preg_replace('/\.[^.]+$/', '', basename($new_file));
		$content = '';

		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata($new_file) ) {
			if ( trim($image_meta['title']) )
				$title = $image_meta['title'];
			if ( trim($image_meta['caption']) )
				$content = $image_meta['caption'];
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
		$id = wp_insert_attachment($attachment, $new_file, $post_parent);
		if ( !is_wp_error($id) ) {
			$data = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $data );
		}
//var_dump($id, $data, $new_file);
		return $id;
	}

	//Create the content for the page
	function main_content() {

		$post_id = isset($_REQUEST['post_id']) ? intval($_REQUEST['post_id']) : 0;
		$import_to_gallery = !( isset($_POST['no-gallery']) && 'on' == $_POST['no-gallery'] );
		
		$url = admin_url('media-upload.php?tab=server&post_id=' . $post_id);

		$cwd = trailingslashit(get_option('frmsvr_last_folder', str_replace('\\', '/', WP_CONTENT_DIR)));

		if ( isset($_REQUEST['directory']) ) 
			$cwd .= stripslashes($_REQUEST['directory']);

		if ( isset($_REQUEST['adirectory']) )
			$cwd = stripslashes($_REQUEST['adirectory']);

		$cwd = preg_replace('![^/]+/\.\./!', '', $cwd);
		$cwd = preg_replace('!//!', '/', $cwd);

		if ( ! is_readable($cwd) && get_option('frmsvr_last_folder') )
			$cwd = get_option('frmsvr_last_folder');

		if ( ! is_readable($cwd) )
			$cwd = str_replace('\\', '/', WP_CONTENT_DIR);

		$cwd = untrailingslashit($cwd);

		update_option('frmsvr_last_folder', $cwd);

		$files = DD32::find_files($cwd, array('levels' => 1));

		$parts = explode('/', rtrim($cwd, '/'));
		$dir = $cwd;
		$dirparts = '';
		for ( $i = count($parts) - 1; $i >= 0; $i-- ) {
			$piece = $parts[$i];
			$adir = implode('/', array_slice($parts, 0, $i+1));
			$durl = clean_url(add_query_arg(array('adirectory' => $adir), $url));
			$dirparts = "<a href='$durl'>$piece</a>/ $dirparts";
			$dir = dirname($dir);
		}
		unset($dir, $piece, $adir, $durl);

		?>
		<div class="frmsvr_wrap">
		<p><?php printf(__('<strong>Current Directory:</strong> <span id="cwd">%s</span>', 'add-from-server'), $dirparts) ?></p>
		<form method="post" action="<?php echo $url ?>">
		<p><?php printf(__('Once you have selected files to be imported, Head over to the <a href="%s">Media Library tab</a> to add them to your post.', 'add-from-server'), clean_url(admin_url('media-upload.php?type=image&tab=library&post_id=' . $post_id)) ); ?></p>
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
				<!-- <td class='check-column'><input type='checkbox' id='file-<?php echo $sanname; ?>' name='files[]' value='<?php echo attribute_escape($file) ?>' /></td> -->
				<td><a href="<?php echo add_query_arg(array('directory' => '../'), $url) ?>" title="<?php echo attribute_escape(dirname($cwd)) ?>"><?php _e('Parent Folder', 'add-from-server') ?></a></td>
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
				$folder_url = add_query_arg(array('directory' => $filename), $url);
		?>
			<tr>
				<td>&nbsp;</td>
				<!-- <td class='check-column'><input type='checkbox' id='file-<?php echo $sanname; ?>' name='files[]' value='<?php echo attribute_escape($file) ?>' /></td> -->
				<td><a href="<?php echo $folder_url ?>"><?php echo $filename ?></a></td>
			</tr>
		<?php
			endforeach;
			foreach( (array)$files as $file  ) :
				$filename = preg_replace('!^' . preg_quote($cwd) . '!', '', $file);
				$filename = ltrim($filename, '/');
				$sanname = preg_replace('![^a-zA-Z0-9]!', '', $filename);
		?>
			<tr>
				<td class='check-column'><input type='checkbox' id='file-<?php echo $sanname; ?>' name='files[]' value='<?php echo attribute_escape($filename) ?>' /></td>
				<td><label for='file-<?php echo $sanname; ?>'><?php echo $filename ?></label></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<th class="check-column"><input type='checkbox' /></th>
				<th><?php _e('File', 'add-from-server'); ?></th>
			</tr>
		</tfoot>
		</table>
		<p class="ml-submit">
		<input type="hidden" name="cwd" value="<?php echo attribute_escape( $cwd ); ?>" />
		<?php if ( $post_id != 0 ) : ?>
		<input type="checkbox" name="no-gallery" id="no-gallery" <?php if ( !$import_to_gallery ) echo 'checked="checked"' ?> /> <label for="no-gallery"><?php _e('Do not add selected files to current post Gallery', 'add-from-server')?></label><br />
		<?php endif; ?>
		<input type="submit" class="button savebutton" name="import" value="<?php echo attribute_escape( __('Import', 'add-from-server') ); ?>" /> 
		</p>
		</form>
		</div>
	<?php
	}
}//end class

add_action('init', create_function('', '$GLOBALS["add-from-server"] = new add_from_server();'), 5);

?>
