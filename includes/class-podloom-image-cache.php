<?php
/**
 * Image Cache Class
 *
 * Handles caching podcast cover art and chapter images in the WordPress media library.
 * Uses HTTP conditional requests (ETag/Last-Modified) to avoid re-downloading unchanged images.
 *
 * @package PodLoom
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Image_Cache
 */
class Podloom_Image_Cache {

	/**
	 * Option name for storing image mappings.
	 */
	const OPTION_NAME = 'podloom_cached_images';

	/**
	 * Maximum image file size (5MB).
	 */
	const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Allowed image mime types.
	 */
	const ALLOWED_TYPES = array(
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * Get all cached image mappings.
	 *
	 * @return array Array of URL => cache data mappings.
	 */
	public static function get_all_mappings() {
		$mappings = get_option( self::OPTION_NAME, array() );
		return is_array( $mappings ) ? $mappings : array();
	}

	/**
	 * Get cache data for a specific image URL.
	 *
	 * @param string $url External image URL.
	 * @return array|null Cache data or null if not cached.
	 */
	public static function get_mapping( $url ) {
		$mappings = self::get_all_mappings();
		$key      = self::get_cache_key( $url );
		return isset( $mappings[ $key ] ) ? $mappings[ $key ] : null;
	}

	/**
	 * Save cache mapping for an image URL.
	 *
	 * @param string $url           External image URL.
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $etag          ETag header value (optional).
	 * @param string $last_modified Last-Modified header value (optional).
	 * @param string $type          Image type: 'cover' or 'chapter'.
	 * @param string $feed_id       Associated feed ID (optional).
	 */
	public static function save_mapping( $url, $attachment_id, $etag = '', $last_modified = '', $type = 'cover', $feed_id = '' ) {
		$mappings = self::get_all_mappings();
		$key      = self::get_cache_key( $url );

		$mappings[ $key ] = array(
			'attachment_id'  => $attachment_id,
			'original_url'   => $url,
			'etag'           => $etag,
			'last_modified'  => $last_modified,
			'type'           => $type,
			'feed_id'        => $feed_id,
			'cached_at'      => time(),
		);

		update_option( self::OPTION_NAME, $mappings );
	}

	/**
	 * Delete cache mapping for an image URL.
	 *
	 * @param string $url            External image URL.
	 * @param bool   $delete_attachment Whether to also delete the attachment.
	 */
	public static function delete_mapping( $url, $delete_attachment = false ) {
		$mappings = self::get_all_mappings();
		$key      = self::get_cache_key( $url );

		if ( isset( $mappings[ $key ] ) ) {
			if ( $delete_attachment && ! empty( $mappings[ $key ]['attachment_id'] ) ) {
				wp_delete_attachment( $mappings[ $key ]['attachment_id'], true );
			}
			unset( $mappings[ $key ] );
			update_option( self::OPTION_NAME, $mappings );
		}
	}

	/**
	 * Generate a cache key from a URL.
	 *
	 * @param string $url Image URL.
	 * @return string Cache key (MD5 hash).
	 */
	public static function get_cache_key( $url ) {
		return md5( $url );
	}

	/**
	 * Get the local URL for a cached image, or cache it if not already cached.
	 *
	 * @param string $url     External image URL.
	 * @param string $type    Image type: 'cover' or 'chapter'.
	 * @param string $feed_id Associated feed ID (optional).
	 * @param bool   $force   Force re-download even if cached.
	 * @return string|false Local attachment URL or false on failure.
	 */
	public static function get_local_url( $url, $type = 'cover', $feed_id = '', $force = false ) {
		// Check if image caching is enabled.
		if ( ! self::is_enabled() ) {
			return $url; // Return original URL if caching disabled.
		}

		// Validate URL.
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		// Check existing cache.
		$cached = self::get_mapping( $url );

		if ( $cached && ! $force ) {
			// Verify attachment and physical file still exist.
			$attachment_url = wp_get_attachment_url( $cached['attachment_id'] );
			$file_path      = get_attached_file( $cached['attachment_id'] );

			if ( $attachment_url && $file_path && file_exists( $file_path ) ) {
				return $attachment_url;
			}
			// Attachment or file was deleted, remove stale mapping.
			self::delete_mapping( $url, true ); // Also delete orphaned attachment.
		}

		// Image not cached yet - queue for background caching and return original URL.
		// This prevents blocking page load while downloading images.
		self::queue_for_caching( $url, $type, $feed_id );

		return $url;
	}

	/**
	 * Queue an image for background caching.
	 *
	 * Stores image URLs in a transient queue to be processed via AJAX after page load.
	 *
	 * @param string $url     External image URL.
	 * @param string $type    Image type: 'cover' or 'chapter'.
	 * @param string $feed_id Associated feed ID.
	 */
	public static function queue_for_caching( $url, $type = 'cover', $feed_id = '' ) {
		$queue_key = 'podloom_image_cache_queue';
		$queue     = get_transient( $queue_key );

		if ( ! is_array( $queue ) ) {
			$queue = array();
		}

		// Use URL hash as key to avoid duplicates.
		$key = self::get_cache_key( $url );

		if ( ! isset( $queue[ $key ] ) ) {
			$queue[ $key ] = array(
				'url'     => $url,
				'type'    => $type,
				'feed_id' => $feed_id,
			);

			// Store queue for 5 minutes (enough time for page to load and trigger AJAX).
			set_transient( $queue_key, $queue, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Get queued images for background caching.
	 *
	 * @return array Array of queued images.
	 */
	public static function get_queue() {
		$queue = get_transient( 'podloom_image_cache_queue' );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Clear the image cache queue.
	 */
	public static function clear_queue() {
		delete_transient( 'podloom_image_cache_queue' );
	}

	/**
	 * Process a single image from the queue (called via AJAX).
	 *
	 * @param string $url     External image URL.
	 * @param string $type    Image type.
	 * @param string $feed_id Feed ID.
	 * @return array Result with success status.
	 */
	public static function process_queued_image( $url, $type = 'cover', $feed_id = '' ) {
		// Attempt to cache the image.
		$result = self::cache_image( $url, $type, $feed_id, null );

		if ( $result && ! empty( $result['attachment_id'] ) ) {
			// Verify the file was actually created.
			$file_path = get_attached_file( $result['attachment_id'] );
			if ( $file_path && file_exists( $file_path ) ) {
				return array(
					'success'   => true,
					'local_url' => wp_get_attachment_url( $result['attachment_id'] ),
				);
			}
			// File wasn't created, clean up.
			wp_delete_attachment( $result['attachment_id'], true );
			self::delete_mapping( $url );
		}

		return array(
			'success' => false,
			'error'   => 'Failed to cache image',
		);
	}

	/**
	 * Cache an image from an external URL.
	 *
	 * Uses HTTP conditional requests if we have cached ETag/Last-Modified values.
	 *
	 * @param string     $url     External image URL.
	 * @param string     $type    Image type: 'cover' or 'chapter'.
	 * @param string     $feed_id Associated feed ID.
	 * @param array|null $cached  Existing cache data (for conditional requests).
	 * @return array|false Result array with attachment_id, or false on failure.
	 */
	public static function cache_image( $url, $type = 'cover', $feed_id = '', $cached = null ) {
		// Build request headers.
		$request_headers = array(
			'User-Agent' => 'PodLoom WordPress Plugin',
		);

		// Add conditional headers if we have cached data.
		if ( $cached ) {
			if ( ! empty( $cached['etag'] ) ) {
				$request_headers['If-None-Match'] = $cached['etag'];
			}
			if ( ! empty( $cached['last_modified'] ) ) {
				$request_headers['If-Modified-Since'] = $cached['last_modified'];
			}
		}

		// Fetch the image.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'            => 30,
				'redirection'        => 3,
				'reject_unsafe_urls' => true,
				'headers'            => $request_headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Request failed - keep existing cache if available.
			if ( $cached && ! empty( $cached['attachment_id'] ) ) {
				return array(
					'attachment_id' => $cached['attachment_id'],
					'not_modified'  => false,
					'cache_kept'    => true,
				);
			}
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Handle 304 Not Modified - image hasn't changed.
		if ( 304 === $status_code && $cached ) {
			// Update last checked time but keep existing attachment.
			self::save_mapping(
				$url,
				$cached['attachment_id'],
				$cached['etag'],
				$cached['last_modified'],
				$type,
				$feed_id
			);

			return array(
				'attachment_id' => $cached['attachment_id'],
				'not_modified'  => true,
			);
		}

		// Handle non-200 responses.
		if ( $status_code < 200 || $status_code >= 300 ) {
			if ( $cached && ! empty( $cached['attachment_id'] ) ) {
				return array(
					'attachment_id' => $cached['attachment_id'],
					'not_modified'  => false,
					'cache_kept'    => true,
				);
			}
			return false;
		}

		// Validate content type.
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$content_type = strtolower( explode( ';', $content_type )[0] ); // Remove charset if present.

		if ( ! in_array( $content_type, self::ALLOWED_TYPES, true ) ) {
			return false;
		}

		// Get image data.
		$image_data = wp_remote_retrieve_body( $response );

		// Validate file size.
		if ( strlen( $image_data ) > self::MAX_FILE_SIZE ) {
			return false;
		}

		// Extract headers for future conditional requests.
		$response_etag          = wp_remote_retrieve_header( $response, 'etag' );
		$response_last_modified = wp_remote_retrieve_header( $response, 'last-modified' );

		// Generate filename from URL.
		$filename = self::generate_filename( $url, $content_type, $type );

		// Sideload to media library.
		$attachment_id = self::sideload_image( $image_data, $filename, $type );

		if ( ! $attachment_id ) {
			// Sideload failed - keep existing cache if available.
			if ( $cached && ! empty( $cached['attachment_id'] ) ) {
				return array(
					'attachment_id' => $cached['attachment_id'],
					'cache_kept'    => true,
				);
			}
			return false;
		}

		// Delete old attachment if we're replacing it.
		if ( $cached && ! empty( $cached['attachment_id'] ) && $cached['attachment_id'] !== $attachment_id ) {
			wp_delete_attachment( $cached['attachment_id'], true );
		}

		// Save the mapping.
		self::save_mapping(
			$url,
			$attachment_id,
			$response_etag,
			$response_last_modified,
			$type,
			$feed_id
		);

		return array(
			'attachment_id' => $attachment_id,
			'not_modified'  => false,
		);
	}

	/**
	 * Sideload image data to the WordPress media library.
	 *
	 * @param string $image_data Binary image data.
	 * @param string $filename   Filename to use.
	 * @param string $type       Image type for description.
	 * @return int|false Attachment ID or false on failure.
	 */
	private static function sideload_image( $image_data, $filename, $type ) {
		// Require media functions.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Create temporary file.
		$temp_file = wp_tempnam( $filename );
		if ( ! $temp_file ) {
			return false;
		}

		// Write image data to temp file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( file_put_contents( $temp_file, $image_data ) === false ) {
			wp_delete_file( $temp_file );
			return false;
		}

		// Prepare file array for wp_handle_sideload.
		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $temp_file,
		);

		// Handle the sideload.
		$result = wp_handle_sideload(
			$file_array,
			array(
				'test_form' => false,
				'test_type' => true,
			)
		);

		if ( isset( $result['error'] ) ) {
			wp_delete_file( $temp_file );
			return false;
		}

		// Create attachment.
		$attachment = array(
			'post_mime_type' => $result['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'meta_input'     => array(
				'_podloom_cached_image' => true,
				'_podloom_image_type'   => $type,
			),
		);

		$attachment_id = wp_insert_attachment( $attachment, $result['file'] );

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $result['file'] );
			return false;
		}

		// Generate attachment metadata.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $result['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return $attachment_id;
	}

	/**
	 * Generate a filename for the cached image.
	 *
	 * @param string $url          Original URL.
	 * @param string $content_type MIME type.
	 * @param string $type         Image type (cover/chapter).
	 * @return string Generated filename.
	 */
	private static function generate_filename( $url, $content_type, $type ) {
		// Map MIME type to extension.
		$extensions = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);

		$ext = isset( $extensions[ $content_type ] ) ? $extensions[ $content_type ] : 'jpg';

		// Try to get original filename from URL.
		$parsed   = wp_parse_url( $url );
		$path     = isset( $parsed['path'] ) ? $parsed['path'] : '';
		$basename = basename( $path );

		// If we got a reasonable filename, use it.
		if ( $basename && preg_match( '/\.(jpe?g|png|gif|webp)$/i', $basename ) ) {
			return 'podloom-' . $type . '-' . sanitize_file_name( $basename );
		}

		// Generate filename from URL hash.
		return 'podloom-' . $type . '-' . substr( md5( $url ), 0, 12 ) . '.' . $ext;
	}

	/**
	 * Check if image caching is enabled.
	 *
	 * @return bool True if enabled.
	 */
	public static function is_enabled() {
		return (bool) get_option( 'podloom_cache_images', false );
	}

	/**
	 * Delete all cached images for a specific feed.
	 *
	 * @param string $feed_id Feed ID.
	 * @return int Number of images deleted.
	 */
	public static function delete_feed_images( $feed_id ) {
		$mappings = self::get_all_mappings();
		$deleted  = 0;

		foreach ( $mappings as $key => $data ) {
			if ( isset( $data['feed_id'] ) && $data['feed_id'] === $feed_id ) {
				if ( ! empty( $data['attachment_id'] ) ) {
					wp_delete_attachment( $data['attachment_id'], true );
				}
				unset( $mappings[ $key ] );
				++$deleted;
			}
		}

		update_option( self::OPTION_NAME, $mappings );
		return $deleted;
	}

	/**
	 * Delete all cached images.
	 *
	 * @return int Number of images deleted.
	 */
	public static function delete_all_images() {
		$mappings = self::get_all_mappings();
		$deleted  = 0;

		foreach ( $mappings as $data ) {
			if ( ! empty( $data['attachment_id'] ) ) {
				wp_delete_attachment( $data['attachment_id'], true );
				++$deleted;
			}
		}

		delete_option( self::OPTION_NAME );
		return $deleted;
	}

	/**
	 * Get responsive image attributes (srcset and sizes) for a cached image.
	 *
	 * Returns srcset and sizes attributes for use in <img> tags to serve
	 * appropriately sized images based on viewport/container size.
	 *
	 * @param string $url     External image URL.
	 * @param string $size    WordPress image size (default 'large').
	 * @param string $sizes   Custom sizes attribute (optional).
	 * @return array|false Array with 'src', 'srcset', 'sizes' or false if not cached.
	 */
	public static function get_responsive_attrs( $url, $size = 'large', $sizes = '' ) {
		// Check if image caching is enabled.
		if ( ! self::is_enabled() ) {
			return false;
		}

		// Get cache mapping.
		$cached = self::get_mapping( $url );
		if ( ! $cached || empty( $cached['attachment_id'] ) ) {
			return false;
		}

		$attachment_id = $cached['attachment_id'];

		// Get the main image URL.
		$src = wp_get_attachment_image_url( $attachment_id, $size );
		if ( ! $src ) {
			return false;
		}

		// Get srcset.
		$srcset = wp_get_attachment_image_srcset( $attachment_id, $size );

		// Get sizes if not provided.
		if ( empty( $sizes ) ) {
			$sizes = wp_get_attachment_image_sizes( $attachment_id, $size );
		}

		return array(
			'src'    => $src,
			'srcset' => $srcset ? $srcset : '',
			'sizes'  => $sizes ? $sizes : '',
		);
	}

	/**
	 * Get a complete <img> tag with responsive attributes.
	 *
	 * @param string $url     External image URL.
	 * @param string $alt     Alt text.
	 * @param string $class   CSS class(es).
	 * @param string $size    WordPress image size.
	 * @param string $sizes   Custom sizes attribute.
	 * @return string HTML img tag.
	 */
	public static function get_responsive_img( $url, $alt = '', $class = '', $size = 'large', $sizes = '' ) {
		$attrs = self::get_responsive_attrs( $url, $size, $sizes );

		if ( $attrs && ! empty( $attrs['srcset'] ) ) {
			// Use responsive attributes.
			$html = sprintf(
				'<img src="%s" srcset="%s" sizes="%s" alt="%s" loading="lazy"',
				esc_url( $attrs['src'] ),
				esc_attr( $attrs['srcset'] ),
				esc_attr( $attrs['sizes'] ),
				esc_attr( $alt )
			);
		} else {
			// Fallback to simple image.
			$local_url = self::get_local_url( $url );
			$html      = sprintf(
				'<img src="%s" alt="%s" loading="lazy"',
				esc_url( $local_url ? $local_url : $url ),
				esc_attr( $alt )
			);
		}

		if ( ! empty( $class ) ) {
			$html .= sprintf( ' class="%s"', esc_attr( $class ) );
		}

		$html .= ' />';

		return $html;
	}

	/**
	 * Get statistics about cached images.
	 *
	 * @return array Statistics array.
	 */
	public static function get_stats() {
		$mappings    = self::get_all_mappings();
		$total_size  = 0;
		$cover_count = 0;

		foreach ( $mappings as $data ) {
			if ( ! empty( $data['attachment_id'] ) ) {
				$file = get_attached_file( $data['attachment_id'] );
				if ( $file && file_exists( $file ) ) {
					$total_size += filesize( $file );
				}
				++$cover_count;
			}
		}

		return array(
			'total_count' => count( $mappings ),
			'cover_count' => $cover_count,
			'total_size'  => $total_size,
		);
	}
}
