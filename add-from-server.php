<?php
/*
Plugin Name: Add From Server
Version: 1.1
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
		wp_die('<h1>Add From Server</h1> Sorry, This plugin requires WordPress 2.5+');
	}
		
	add_option('frmsvr_last_folder', ABSPATH . '/wp-content/');
}

register_deactivation_hook(__FILE__, 'frmsvr_deactivated');
function frmsvr_deactivated(){
	delete_option('frmsvr_last_folder');
}

add_filter('media_upload_tabs', 'frmsvr_tabs');
function frmsvr_tabs($tabs){
	if( current_user_can( 'unfiltered_upload' ) )
		$tabs['server'] = __('Add From Server');
	return $tabs;
}

add_action('media_upload_server', 'frmsvr_file');
function frmsvr_file(){
	if( ! current_user_can( 'unfiltered_upload' ) )
		return;
	add_action('admin_head', 'media_admin_css');
	return wp_iframe('frmsvr_mainform');
}

function frmsvr_get_cwd(){
	$subpath = '';
	if( isset($_REQUEST['directory']) )
		$subpath = $_REQUEST['directory'];
	else
		$subpath = get_option('frmsvr_last_folder');

	$path = path_join(ABSPATH, realpath($subpath));
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
	$post_id = $_REQUEST['post_id'];
	$base = frmsvr_get_cwd();
	$folderurl = get_option('siteurl') . "/wp-admin/media-upload.php?tab=server&post_id=$post_id&directory=";
	
	$return = "<form action='$folderurl$base' method='POST'><table>";
	$return .= "<tr>
					<th>Import</th>
					<th>Filename</th>
				</tr>";
	$parent = realpath($base . '/..');

	$return .= "<tr>
					<td>&nbsp;</td>
					<td><strong><a href='$folderurl$parent'>Parent Folder</a></strong></td>
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
	$return .= '</table>';
	
	$return .= '
			<input type="submit" name="submit" value="Import selected files" />
			</form>';
	
	return $return;
}

function frmsvr_handle_import($files) {
	if( empty($files) )
		return;
	foreach($files as $file){
		$file = realpath($file);
		$id = frmsvr_handle_file($file);
		if( $id ){
			echo "<div class='updated'><p><em>$file</em> has been added to Media library</p></div>";
		}
	}
}
function frmsvr_handle_file($file){
	$post_id = $_REQUEST['post_id'];
	
	$wp_filetype = wp_check_filetype( $file, null );

	extract( $wp_filetype );

	if ( !$ext )
		$ext = ltrim(strrchr($file['name'], '.'), '.');

	// A writable uploads dir will pass this test. Again, there's no point overriding this one.
	if ( ! ( ( $uploads = wp_upload_dir() ) && false === $uploads['error'] ) )
		wp_die( $uploads['error'] );

	$filename = wp_unique_filename( $uploads['path'], $file, $unique_filename_callback );

	// Move the file to the uploads dir
	$new_file = $uploads['path'] . '/' . $filename;
	if ( false === @ copy( $file, $new_file ) )
		wp_die( printf( __('The uploaded file could not be copied to %s.' ), $uploads['path'] ));

	// Set correct file permissions
	$stat = stat( dirname( $new_file ));
	$perms = $stat['mode'] & 0000666;
	@ chmod( $new_file, $perms );

	// Compute the URL
	$url = $uploads['url'] . '/' . $filename;
	
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
	$form_action_url = get_option('siteurl') . "/wp-admin/media-upload.php?tab=server&post_id=$post_id";
	
	$cwd = frmsvr_get_cwd();
	
	if( ! empty($_POST) )
		frmsvr_handle_import(array_keys((array)$_POST['files']));
	if( isset($_GET['upload-file']) )
		frmsvr_handle_import( array($_GET['upload-file']) );
	
?>
<h3><?php _e('Add From Server'); ?></h3>

<p><?php printf(__('Once you have selected files to be imported, Head over to the <a href="%s">Media Library tab</a> to add them to your post.'), 'media-upload.php?type=image&tab=library&post_id=' . $post_id ); ?></p>

<p><strong><?php _e('Current Directory'); ?>: </strong><span id="cwd"><?php echo $cwd; ?></span></p>
<div id="filesystem-list">
<?php frmsvr_list_files('display') ?>
</div>
<?php
}

?>