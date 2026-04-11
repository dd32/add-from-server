<?php
/**
 * File importer. Moves a validated server file into the media library.
 *
 * @package dd32\WordPress\AddFromServer
 */

declare( strict_types=1 );

namespace dd32\WordPress\AddFromServer;

use WP_Error;

/**
 * Imports a file from the server filesystem into the media library.
 *
 * Extracted into its own class so the copy/attach pipeline can be
 * covered with unit tests independently of the browse UI.
 */
class Importer {

	/**
	 * The filesystem helper used to validate paths.
	 */
	private Filesystem $filesystem;

	/**
	 * Constructor.
	 */
	public function __construct( Filesystem $filesystem ) {
		$this->filesystem = $filesystem;
	}

	/**
	 * Import a file (absolute path or relative to the root) into the media library.
	 *
	 * @param string $path Absolute or root-relative path to import.
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function import( string $path ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'afs_forbidden', __( 'You are not allowed to import files.', 'add-from-server' ) );
		}

		// Normalize to an absolute path inside the allowed root.
		if ( ! str_starts_with( $path, '/' ) || ! $this->filesystem->is_within_root( $path ) ) {
			$resolved = $this->filesystem->resolve( $path );
		} else {
			$resolved = $this->filesystem->resolve( $this->filesystem->relative( $path ) );
		}

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		if ( ! is_file( $resolved ) ) {
			return new WP_Error( 'afs_not_a_file', __( 'The requested path is not a file.', 'add-from-server' ) );
		}

		if ( ! is_readable( $resolved ) ) {
			return new WP_Error( 'afs_not_readable', __( 'File is not readable by the web server.', 'add-from-server' ) );
		}

		$file_type = wp_check_filetype( $resolved, null );
		$type      = $file_type['type'];
		$ext       = $file_type['ext'];
		if ( ( ! $type || ! $ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
			return new WP_Error(
				'afs_bad_filetype',
				__( 'Sorry, this file type is not permitted for security reasons.', 'add-from-server' )
			);
		}

		$time    = (int) ( @filemtime( $resolved ) ?: time() );
		$uploads = wp_upload_dir( gmdate( 'Y-m-d H:i:s', $time ) );
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'afs_upload_dir', (string) $uploads['error'] );
		}

		$uploads_base = Filesystem::normalize( $uploads['basedir'] );
		$normalized   = Filesystem::normalize( $resolved );

		if ( str_starts_with( $normalized, $uploads_base ) ) {
			$result = $this->adopt_existing_upload( $normalized, $uploads_base, $time );
		} else {
			$result = $this->copy_into_uploads( $normalized, $uploads );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		[ $new_file, $url, $time ] = $result;

		$title   = preg_replace( '!\.[^.]+$!', '', basename( $new_file ) );
		$content = '';
		$excerpt = '';

		if ( 0 === strpos( (string) $type, 'audio/' ) ) {
			[ $title, $content ] = $this->audio_metadata( $new_file, (string) $title );
		} elseif ( 0 === strpos( (string) $type, 'image/' ) ) {
			[ $title, $excerpt ] = $this->image_metadata( $new_file, (string) $title );
		}

		$attachment = array(
			'post_mime_type' => (string) $type,
			'guid'           => $url,
			'post_parent'    => 0,
			'post_title'     => (string) $title,
			'post_name'      => (string) $title,
			'post_content'   => $content,
			'post_excerpt'   => $excerpt,
			'post_date'      => current_time( 'mysql' ),
			'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $time ),
		);

		/**
		 * Filter the attachment data before it is inserted into the database.
		 *
		 * @param array  $attachment Attachment data.
		 * @param string $file       The original file path.
		 */
		$attachment = (array) apply_filters( 'add_from_server_attachment', $attachment, $resolved );

		$id = wp_insert_attachment( $attachment, $new_file, 0, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$metadata = wp_generate_attachment_metadata( $id, $new_file );
		wp_update_attachment_metadata( $id, $metadata );

		return $id;
	}

	/**
	 * Adopt a file that is already inside wp-content/uploads.
	 *
	 * @return array{0: string, 1: string, 2: int}|WP_Error
	 */
	private function adopt_existing_upload( string $file, string $uploads_base, int $time ) {
		$relative = substr( $file, strlen( $uploads_base ) );

		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'meta_key'       => '_wp_attached_file',
				'meta_value'     => ltrim( $relative, '/' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			return new WP_Error(
				'afs_already_exists',
				__( 'Sorry, that file already exists in the WordPress media library.', 'add-from-server' )
			);
		}

		// Match dated folder (YYYY/MM) if present.
		if ( preg_match( '|^/?(?P<year>\d{4})/(?P<month>\d{2})|', ltrim( $relative, '/' ), $matches ) ) {
			if ( gmdate( 'Y/m', $time ) !== $matches['year'] . '/' . $matches['month'] ) {
				$time = (int) mktime( 0, 0, 0, (int) $matches['month'], 1, (int) $matches['year'] );
			}
		}

		$uploads = wp_upload_dir( gmdate( 'Y-m-d H:i:s', $time ) );
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'afs_upload_dir', (string) $uploads['error'] );
		}

		return array( $file, $uploads['baseurl'] . $relative, $time );
	}

	/**
	 * Copy a file from outside uploads into the uploads directory.
	 *
	 * @param string               $file    Source file absolute path.
	 * @param array<string, mixed> $uploads Result of {@see wp_upload_dir()}.
	 * @return array{0: string, 1: string, 2: int}|WP_Error
	 */
	private function copy_into_uploads( string $file, array $uploads ) {
		$filename = wp_unique_filename( $uploads['path'], basename( $file ) );
		$new_file = $uploads['path'] . '/' . $filename;

		if ( ! copy( $file, $new_file ) ) {
			return new WP_Error(
				'afs_copy_failed',
				sprintf(
					/* translators: %s: target directory */
					__( 'The selected file could not be copied to %s.', 'add-from-server' ),
					$uploads['path']
				)
			);
		}

		$stat  = @stat( dirname( $new_file ) );
		$perms = is_array( $stat ) ? ( $stat['mode'] & 0000666 ) : 0644;
		@chmod( $new_file, $perms );

		return array( $new_file, $uploads['url'] . '/' . $filename, (int) ( @filemtime( $file ) ?: time() ) );
	}

	/**
	 * Extract audio metadata from the file to produce title/content.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function audio_metadata( string $file, string $fallback_title ): array {
		if ( ! function_exists( 'wp_read_audio_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		$meta = wp_read_audio_metadata( $file );
		if ( ! $meta ) {
			return array( $fallback_title, '' );
		}

		$title   = ! empty( $meta['title'] ) ? (string) $meta['title'] : $fallback_title;
		$content = '';

		if ( ! empty( $meta['album'] ) && ! empty( $meta['artist'] ) ) {
			/* translators: 1: track, 2: album, 3: artist */
			$content = sprintf( __( '"%1$s" from %2$s by %3$s.', 'add-from-server' ), $title, $meta['album'], $meta['artist'] );
		} elseif ( ! empty( $meta['album'] ) ) {
			/* translators: 1: track, 2: album */
			$content = sprintf( __( '"%1$s" from %2$s.', 'add-from-server' ), $title, $meta['album'] );
		} elseif ( ! empty( $meta['artist'] ) ) {
			/* translators: 1: track, 2: artist */
			$content = sprintf( __( '"%1$s" by %2$s.', 'add-from-server' ), $title, $meta['artist'] );
		}

		return array( $title, $content );
	}

	/**
	 * Extract image metadata (EXIF/IPTC) for title and caption.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function image_metadata( string $file, string $fallback_title ): array {
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$meta = @wp_read_image_metadata( $file );
		if ( ! is_array( $meta ) ) {
			return array( $fallback_title, '' );
		}

		$title = $fallback_title;
		if ( ! empty( $meta['title'] ) && trim( (string) $meta['title'] ) && ! is_numeric( sanitize_title( (string) $meta['title'] ) ) ) {
			$title = (string) $meta['title'];
		}

		$excerpt = '';
		if ( ! empty( $meta['caption'] ) && trim( (string) $meta['caption'] ) ) {
			$excerpt = (string) $meta['caption'];
		}

		return array( $title, $excerpt );
	}
}
