<?php
/*
Plugin Name: Add From Server
Version: 1.3.2
Plugin URI: http://dd32.id.au/wordpress-plugins/add-from-server/
Description: Plugin to allow the Media Manager to add files from the webservers filesystem. <strong>Note:</strong> All files are copied to the uploads directory.
Author: Dion Hulse
Author URI: http://dd32.id.au/
*/

register_activation_hook(__FILE__, 'frmsvr_activated');
function frmsvr_activated(){
	global $wp_version;
	if( ! version_compare( $wp_version, '2.5-alpha', '>=') ){
		if( function_exists('deactivate_plugins') )
			deactivate_plugins(__FILE__);
		wp_die(__('<h1>Add From Server</h1> Sorry, This plugin requires WordPress 2.5+', 'add-from-server'));
	}
	if( ! get_option('frmsvr_last_folder') )
		add_option('frmsvr_last_folder', ABSPATH . '/wp-content/');
}

register_deactivation_hook(__FILE__, 'frmsvr_deactivated');
function frmsvr_deactivated(){
	delete_option('frmsvr_last_folder');
}

add_action('init', 'frmsvr_init');
function frmsvr_init(){
    load_plugin_textdomain('add-from-server', PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)) . '/langs/');
}

add_filter('media_upload_tabs', 'frmsvr_tabs');
function frmsvr_tabs($tabs){
	if( current_user_can( 'unfiltered_upload' ) )
		$tabs['server'] = __('Add From Server', 'add-from-server');
	return $tabs;
}

add_action('media_upload_server', 'frmsvr_file');
function frmsvr_file(){
	if( ! current_user_can( 'unfiltered_upload' ) )
		return;
	add_action('admin_head', 'media_admin_css');
	wp_enqueue_script('admin-forms');
	return wp_iframe('frmsvr_mainform');
}

function frmsvr_get_cwd(){
	$subpath = '';
	if( isset($_REQUEST['directory']) )
		$subpath = $_REQUEST['directory'];
	else
		$subpath = get_option('frmsvr_last_folder');

	$path = ABSPATH;
	if( $subpath && !empty($subpath) )
		$path = path_join($path, realpath($subpath));

	$path = str_replace('\\','/',$path);
	$path = trailingslashit($path);
	
	update_option('frmsvr_last_folder', $path);
	
	return $path;
}
function frmsvr_list_files($dest = 'return'){
	$cwd = frmsvr_get_cwd();
	$dir = dir($cwd);
	
	$list = $dirs = $files = array();
	
	while( false !== ($file = $dir->read())){
		if( $file == '.' || $file == '..' )
			continue;
		if( is_dir($cwd . '/' . $file) )
			$dirs[] = array('name' => $file, 'dir' => true, 'file' => false );
		else 
			$files[] = array('name' => $file, 'dir' => false, 'file' => true );
	}
	$list = array_merge($dirs, $files); //Organises it into dirs first, then files.
	
	$content = frmsrv_walk_files($list);
	if( $dest == 'return' )
		return $content;
	elseif( $dest == 'display' )
		echo $content;
}

function frmsrv_walk_files($files = array()){
	$post_id = intval($_REQUEST['post_id']);
	$base = frmsvr_get_cwd();
	$folderurl = get_option('siteurl') . '/wp-admin/media-upload.php?tab=server&post_id=' . $post_id . '&directory=';
	
	$return = "<form action='$folderurl$base' method='POST'><table>";
	$return .= "<thead><tr>
					<th>" . __('Import', 'add-from-server') . '</th>
					<th>' . __('Filename', 'add-from-server') . '</th>
				</tr></thead>';
	$parent = realpath($base . '/..');

	$return .= '<tbody>';

	$return .= "<tr>
					<td>&nbsp;</td>
					<td><strong><a href='$folderurl$parent'>" . __('Parent Folder', 'add-from-server') . "</a></strong></td>
				</tr>";
	foreach($files as $file){
		$filename = $file['name'];
		if( $file['file'] ){
			//File
			$sanname = str_replace('.', '', $filename);
			$return .= "<tr>
							<td><input type='checkbox' id='file-$sanname' name='files[$base$filename]' /></td>
							<td><a href='#' onclick='jQuery(\"#file-$sanname\").attr(\"checked\",\"checked\"); return false;'>$filename</a></td>
						</tr>";
		} else {
			//Dir
			//<input type='checkbox' name='files[$base$filename]' />
			$return .= "<tr>
							<td>&nbsp;</td>
							<td><strong><a href='$folderurl$base$filename'>$filename</a></strong></td>
						</tr>";
		}
	}
	$return .= '<tr>
					<th colspan="2" style="text-align: left;"><a href="javascript:checkAll(\'#filesystem-list\');">' . __('Toggle All', 'add-from-server') . '</a></th>
				</tr>';
	$return .= '</tbody>';
	$return .= '</table>';

	//Let the plugin work with the "Post Uploads" plugin of mine :)	
	if( function_exists('pu_checkbox') ){
		$ret = pu_checkbox(false);
		if( $ret )
			$ret .= sprintf('(<em>%s</em>)', __('Note: Will not take effect if selected file is within an upload folder at present', 'add-from-server'));
		$return .= '<p>' . $ret . '</p>';
	}
	
	//Offer to not assoc. with this post.
	$return .= '<p><input type="checkbox" name="no-gallery" />' . __('Do not add selected files to current post Gallery', 'add-from-server') . '</p>';

	$return .= '
			<input type="submit" name="submit" value=" ' . __('Import selected files', 'add-from-server') . '" />
			</form>';
	
	return $return;
}

function frmsvr_handle_import($files) {
	if( empty($files) )
		return;
	$gallery = isset($_POST['no-gallery']) && $_POST['no-gallery'] ? false : true;

	foreach($files as $file){
		$file = realpath($file);
		$id = frmsvr_handle_file($file, $gallery);
		if( $id ){
			echo "<script type='text/javascript'>jQuery('#attachments-count').text(1 * jQuery('#attachments-count').text() + 1);</script>";
			echo '<div class="updated"><p>' . sprintf(__('<em>%s</em> has been added to Media library', 'add-from-server'), $file) . '</p></div>';
		}
	}
}

function frmsvr_handle_file($file, $gallery = true){

	$post_id = $gallery && isset($_REQUEST['post_id']) ? $_REQUEST['post_id'] : 0; //If the post id is set and we're adding to a gallery.
	
	$wp_filetype = wp_check_filetype( $file, null );

	extract( $wp_filetype );

	if ( !$ext )
		$ext = ltrim(strrchr($file['name'], '.'), '.');

	// A writable uploads dir will pass this test. Again, there's no point overriding this one.
	if ( ! ( ( $uploads = wp_upload_dir( gmdate('Y-m-d H:i:s', filemtime($file)) ) ) && false === $uploads['error'] ) )
		wp_die( $uploads['error'] );


	$uploads_folder = defined('UPLOADS') ? UPLOADS : (trim(get_option('upload_path')) === '' ? 'wp-content/uploads' : get_option('upload_path'));

	//Is the file allready in the uploads folder?
	if( preg_match('|^' . str_replace('\\','\\\\',realpath(ABSPATH . $uploads_folder)) . '(.*)|i', $file, $mat) ) {
		//First line of business.. Check that file isnt allready in the media library!.

		$filename = basename($file);
		$new_file = $file;
		if ( !$url = get_option('upload_url_path') )
			$url = trailingslashit(get_option('siteurl'));
		
		$url .= rtrim($uploads_folder,'/') . '/' . ltrim(str_replace('\\', '/', $mat[1]),'/');;
		
		global $wpdb;
		$results = $wpdb->get_col( $wpdb->prepare("SELECT ID FROM `{$wpdb->posts}` WHERE `guid` = '%s' AND `post_type` = 'attachment'", $url) );
		if( count($results) > 0 )
			return $results[0]; //Kill function off at this point.. It exists in the media library allready.
	} else {	
		$filename = wp_unique_filename( $uploads['path'], basename($file), $unique_filename_callback );

		// copy the file to the uploads dir
		$new_file = $uploads['path'] . '/' . $filename;
		if ( false === @ copy( $file, $new_file ) )
			return $upload_error_handler( $file, sprintf( __('The uploaded file could not be moved to %s.' ), $uploads['path'] ) );
	
		// Set correct file permissions
		$stat = stat( dirname( $new_file ));
		$perms = $stat['mode'] & 0000666;
		@ chmod( $new_file, $perms );
		// Compute the URL
		$url = $uploads['url'] . '/' . $filename;
	}
	
	$title = preg_replace('/\.[^.]+$/', '', basename($file));
	$content = '';

	// use image exif/iptc data for title and caption defaults if possible
	if ( $image_meta = @wp_read_image_metadata($file) ) {
		if ( trim($image_meta['title']) )
			$title = $image_meta['title'];
		if ( trim($image_meta['caption']) )
			$content = $image_meta['caption'];
	}

	// Construct the attachment array
	$attachment = array(
		'post_mime_type' => $type,
		'guid' => $url,
		'post_parent' => $post_id,
		'post_title' => $title,
		'post_name' => $title,
		'post_content' => $content,
	);

	// Save the data
	$id = wp_insert_attachment($attachment, $new_file, $post_parent);
	if ( !is_wp_error($id) ) {
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $new_file ) );
	}

	return $id;

}

function frmsvr_mainform(){
	media_upload_header();
	$post_id = intval($_REQUEST['post_id']);
	$form_action_url = get_option('siteurl') . '/wp-admin/media-upload.php?tab=server&post_id=' . $post_id;
	
	$cwd = frmsvr_get_cwd();
	
	if( ! empty($_POST) )
		frmsvr_handle_import(array_keys((array)$_POST['files']));
	if( isset($_GET['upload-file']) )
		frmsvr_handle_import( array($_GET['upload-file']) );
	
?>
<h3><?php _e('Add From Server', 'add-from-server'); ?></h3>

<p><?php printf(__('Once you have selected files to be imported, Head over to the <a href="%s">Media Library tab</a> to add them to your post.', 'add-from-server'), 'media-upload.php?type=image&tab=library&post_id=' . $post_id ); ?></p>

<p><strong><?php _e('Current Directory', 'add-from-server'); ?>: </strong><span id="cwd"><?php echo $cwd; ?></span></p>
<div id="filesystem-list">
<?php frmsvr_list_files('display') ?>
</div>
<?php
}

?>