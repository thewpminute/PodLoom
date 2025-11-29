<?php
/**
 * RSS Feed Management Class
 *
 * @package PodLoom
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_RSS
 * Handles RSS feed management, parsing, and caching
 */
class Podloom_RSS {

	/**
	 * Check rate limit for AJAX operations
	 *
	 * @param string $action Action identifier (e.g., 'get_feeds', 'get_episodes')
	 * @param int    $max_requests Maximum requests allowed
	 * @param int    $time_window Time window in seconds
	 * @return bool True if under limit, false if exceeded
	 */
	public static function check_rate_limit( $action, $max_requests = 30, $time_window = 60 ) {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rate_key    = 'podloom_rss_rate_' . $action . '_' .
					( is_user_logged_in() ? get_current_user_id() : md5( $remote_addr ) );
		$rate_count  = get_transient( $rate_key );

		if ( $rate_count && $rate_count >= $max_requests ) {
			return false;
		}

		// Increment counter
		set_transient( $rate_key, ( $rate_count ? $rate_count : 0 ) + 1, $time_window );
		return true;
	}

	/**
	 * Maximum number of episodes to cache per feed
	 */
	const MAX_EPISODES = 50;

	/**
	 * Get all RSS feeds
	 *
	 * @return array Array of RSS feeds
	 */
	public static function get_feeds() {
		$feeds = get_option( 'podloom_rss_feeds', array() );
		return is_array( $feeds ) ? $feeds : array();
	}

	/**
	 * Get a single RSS feed by ID
	 *
	 * @param string $feed_id Feed ID
	 * @return array|null Feed data or null if not found
	 */
	public static function get_feed( $feed_id ) {
		$feeds = self::get_feeds();
		return isset( $feeds[ $feed_id ] ) ? $feeds[ $feed_id ] : null;
	}

	/**
	 * Add a new RSS feed
	 *
	 * @param string $name Feed name
	 * @param string $url Feed URL
	 * @param bool   $async_refresh Whether to refresh feed asynchronously (default: true)
	 * @return array Result with success status and message
	 */
	public static function add_feed( $name, $url, $async_refresh = true ) {
		// Validate URL format
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid URL format',
			);
		}

		// Enhanced SSRF protection: Block localhost
		$parsed_url = wp_parse_url( $url );
		if ( ! isset( $parsed_url['host'] ) ) {
			return array(
				'success' => false,
				'message' => 'Invalid URL - missing host',
			);
		}

		$host       = $parsed_url['host'];
		$host_lower = strtolower( $host );

		// Block obvious localhost references
		if ( in_array( $host_lower, array( 'localhost', '127.0.0.1', '::1', '0.0.0.0' ), true ) ) {
			return array(
				'success' => false,
				'message' => 'Localhost URLs are not allowed',
			);
		}

		// Note: We previously performed a DNS resolution here to check for private IPs.
		// However, this caused false positives with some hosting providers and CDNs.
		// We now rely on WordPress's built-in 'reject_unsafe_urls' in wp_remote_get()
		// which is used inside self::validate_feed() below.

		// Note: HTTP feeds are allowed for compatibility (many podcast feeds still use HTTP)
		// HTTPS is preferred but not required.

		// Validate feed (this will fetch it once)
		$validation = self::validate_feed( $url );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'message' => $validation['message'],
			);
		}

		// Generate cryptographically secure unique ID
		$feed_id = 'rss_' . wp_generate_password( 32, false );

		// Get feeds
		$feeds = self::get_feeds();

		// Add new feed - initially marked as unchecked
		$feeds[ $feed_id ] = array(
			'id'           => $feed_id,
			'name'         => sanitize_text_field( $name ),
			'url'          => esc_url_raw( $url ),
			'created'      => time(),
			'last_checked' => null,
			'valid'        => false, // Mark as unvalidated until first refresh completes
		);

		// Save feeds
		update_option( 'podloom_rss_feeds', $feeds );

		// Auto-enable RSS feeds if this is the first feed being added
		$rss_enabled = get_option( 'podloom_rss_enabled', false );
		if ( ! $rss_enabled ) {
			update_option( 'podloom_rss_enabled', true );
		}

		// Always do initial refresh synchronously to ensure feed is validated before use
		// This prevents race condition where invalid feeds could be saved
		$refresh_result = self::refresh_feed( $feed_id, true ); // Force full refresh for new feeds

		// Clear option cache to ensure we get the freshly updated feed data
		wp_cache_delete( 'podloom_rss_feeds', 'options' );

		// Get the updated feed data (refresh_feed already sets valid status)
		$feeds = self::get_feeds();

		// For subsequent refreshes, use async if requested
		if ( $async_refresh ) {
			// Schedule periodic background refresh for this feed
			wp_schedule_single_event( time() + 3600, 'podloom_refresh_rss_feed', array( $feed_id ) );
		}

		return array(
			'success' => ! empty( $refresh_result['success'] ),
			'message' => ! empty( $refresh_result['success'] ) ? 'Feed added successfully' : ( $refresh_result['message'] ?? 'Feed validation failed' ),
			'feed'    => $feeds[ $feed_id ] ?? null,
		);
	}

	/**
	 * Update a feed name
	 *
	 * @param string $feed_id Feed ID
	 * @param string $name New name
	 * @return array Result with success status and message
	 */
	public static function update_feed_name( $feed_id, $name ) {
		$feeds = self::get_feeds();

		if ( ! isset( $feeds[ $feed_id ] ) ) {
			return array(
				'success' => false,
				'message' => 'Feed not found',
			);
		}

		$feeds[ $feed_id ]['name'] = sanitize_text_field( $name );
		update_option( 'podloom_rss_feeds', $feeds );

		return array(
			'success' => true,
			'message' => 'Feed name updated',
			'feed'    => $feeds[ $feed_id ],
		);
	}

	/**
	 * Delete a feed
	 *
	 * @param string $feed_id Feed ID
	 * @return array Result with success status and message
	 */
	public static function delete_feed( $feed_id ) {
		$feeds = self::get_feeds();

		if ( ! isset( $feeds[ $feed_id ] ) ) {
			return array(
				'success' => false,
				'message' => 'Feed not found',
			);
		}

		// Delete cached episodes (handles both object cache and transients)
		podloom_cache_delete( 'rss_episodes_' . $feed_id );

		// Remove feed
		unset( $feeds[ $feed_id ] );
		update_option( 'podloom_rss_feeds', $feeds );

		return array(
			'success' => true,
			'message' => 'Feed deleted successfully',
		);
	}

	/**
	 * Validate an RSS feed URL
	 *
	 * @param string $url Feed URL
	 * @return array Validation result with valid status and message
	 */
	public static function validate_feed( $url ) {
		// Fetch the feed with SSRF protection.
		$response = wp_remote_get(
			$url,
			array(
				'timeout'            => 15,
				'reject_unsafe_urls' => true,
				'headers'            => array(
					'User-Agent' => 'PodLoom WordPress Plugin',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'valid'   => false,
				'message' => 'Could not fetch feed: ' . $response->get_error_message(),
			);
		}

		$body         = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Check if content is XML
		if ( strpos( $content_type, 'xml' ) === false && strpos( $body, '<?xml' ) !== 0 ) {
			return array(
				'valid'   => false,
				'message' => 'URL does not return valid XML',
			);
		}

		// Try to parse with SimplePie
		require_once ABSPATH . WPINC . '/class-simplepie.php';

		$feed = new SimplePie();
		$feed->set_raw_data( $body );
		$feed->init();

		if ( $feed->error() ) {
			return array(
				'valid'   => false,
				'message' => 'Invalid RSS feed: ' . $feed->error(),
			);
		}

		return array(
			'valid'   => true,
			'message' => 'Valid RSS feed',
		);
	}

	/**
	 * Refresh a feed (fetch and cache episodes)
	 *
	 * Uses HTTP conditional requests (ETag/Last-Modified) to avoid re-downloading
	 * unchanged feeds. This makes it safe to use shorter cache durations without
	 * hammering remote podcast hosts.
	 *
	 * @param string $feed_id Feed ID.
	 * @param bool   $force   Force full refresh, bypassing conditional headers. Default false.
	 * @return array Result with success status, message, and status code.
	 */
	public static function refresh_feed( $feed_id, $force = false ) {
		$feed_data = self::get_feed( $feed_id );

		if ( ! $feed_data ) {
			return array(
				'success' => false,
				'message' => 'Feed not found',
			);
		}

		// Clear SimplePie cache for this feed (separate from our episode cache)
		$cache_location = WP_CONTENT_DIR . '/cache/simplepie';
		if ( file_exists( $cache_location ) ) {
			$cache_file = $cache_location . '/' . md5( $feed_data['url'] ) . '.spc';
			if ( file_exists( $cache_file ) ) {
				wp_delete_file( $cache_file );
			}
		}

		// Build request headers
		$request_headers = array(
			'User-Agent' => 'PodLoom WordPress Plugin',
		);

		// Add conditional headers if not forcing and we have cached values
		if ( ! $force ) {
			if ( ! empty( $feed_data['etag'] ) ) {
				$request_headers['If-None-Match'] = $feed_data['etag'];
			}
			if ( ! empty( $feed_data['last_modified'] ) ) {
				$request_headers['If-Modified-Since'] = $feed_data['last_modified'];
			}
		}

		// Fetch feed with SSRF protection
		$response = wp_remote_get(
			$feed_data['url'],
			array(
				'timeout'            => 15,
				'redirection'        => 3, // Limit redirects to prevent redirect chains
				'reject_unsafe_urls' => true, // WordPress built-in SSRF protection
				'headers'            => $request_headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			// Mark feed as having issues, but DO NOT delete existing cache
			// This preserves last-known-good data when the feed is temporarily unavailable
			$feeds                             = self::get_feeds();
			$feeds[ $feed_id ]['valid']        = false;
			$feeds[ $feed_id ]['last_checked'] = time();
			update_option( 'podloom_rss_feeds', $feeds );

			return array(
				'success'     => false,
				'message'     => 'Could not fetch feed: ' . $response->get_error_message(),
				'status_code' => 0,
				'cache_kept'  => true,
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Handle 304 Not Modified - feed hasn't changed
		if ( 304 === $status_code ) {
			$feeds                             = self::get_feeds();
			$feeds[ $feed_id ]['valid']        = true;
			$feeds[ $feed_id ]['last_checked'] = time();
			update_option( 'podloom_rss_feeds', $feeds );

			return array(
				'success'     => true,
				'message'     => 'Feed is up to date',
				'status_code' => 304,
				'not_modified'=> true,
			);
		}

		// Handle non-200 responses (but not 304 which we handled above)
		if ( $status_code < 200 || $status_code >= 300 ) {
			$feeds                             = self::get_feeds();
			$feeds[ $feed_id ]['valid']        = false;
			$feeds[ $feed_id ]['last_checked'] = time();
			update_option( 'podloom_rss_feeds', $feeds );

			return array(
				'success'     => false,
				'message'     => 'Feed returned HTTP ' . $status_code,
				'status_code' => $status_code,
				'cache_kept'  => true,
			);
		}

		$body = wp_remote_retrieve_body( $response );

		// Parse with SimplePie
		require_once ABSPATH . WPINC . '/class-simplepie.php';

		$feed = new SimplePie();

		// Disable SimplePie caching during manual refresh to ensure fresh data
		$feed->enable_cache( false );

		$feed->set_raw_data( $body );
		$feed->init();

		if ( $feed->error() ) {
			// Mark feed as invalid but DO NOT delete existing cache
			// This preserves last-known-good data when feed XML is temporarily broken
			$feeds                             = self::get_feeds();
			$feeds[ $feed_id ]['valid']        = false;
			$feeds[ $feed_id ]['last_checked'] = time();
			update_option( 'podloom_rss_feeds', $feeds );

			return array(
				'success'     => false,
				'message'     => 'Invalid RSS feed: ' . $feed->error(),
				'status_code' => $status_code,
				'cache_kept'  => true,
			);
		}

		// Get up to MAX_EPISODES most recent episodes
		$items    = $feed->get_items( 0, self::MAX_EPISODES );
		$episodes = array();

		// Initialize P2.0 parser
		$p20_parser = new Podloom_Podcast20_Parser();

		// Parse channel-level P2.0 data (applies to all episodes)
		$channel_p20_data = $p20_parser->parse_from_simplepie_channel( $feed );

		// Get the podcast cover image (channel-level) and cache it if image caching is enabled.
		$podcast_cover_url = $feed->get_image_url() ? $feed->get_image_url() : '';
		if ( ! empty( $podcast_cover_url ) && Podloom_Image_Cache::is_enabled() ) {
			$podcast_cover_url = Podloom_Image_Cache::get_local_url( $podcast_cover_url, 'cover', $feed_id );
		}

		foreach ( $items as $item ) {
			$enclosure = $item->get_enclosure();

			// Parse item-level Podcasting 2.0 data
			$item_p20_data = $p20_parser->parse_from_simplepie( $item );

			// Merge channel and item data (item takes precedence)
			$p20_data = $p20_parser->merge_data( $item_p20_data, $channel_p20_data );

			// Get episode image (item thumbnail or fallback to podcast cover).
			$episode_image_url = '';
			$thumbnail         = $item->get_thumbnail();
			if ( $thumbnail && ! empty( $thumbnail['url'] ) ) {
				$episode_image_url = $thumbnail['url'];
				// Cache episode-specific artwork if different from podcast cover.
				if ( Podloom_Image_Cache::is_enabled() ) {
					$episode_image_url = Podloom_Image_Cache::get_local_url( $episode_image_url, 'cover', $feed_id );
				}
			} else {
				// Use podcast cover (already cached above).
				$episode_image_url = $podcast_cover_url;
			}

			// Note: Chapter images are cached lazily when the episode is rendered,
			// not during feed refresh. This avoids downloading images for episodes
			// that may never be embedded. See podloom_cache_chapter_images().

			$episode = array(
				'id'          => md5( $item->get_permalink() ),
				'title'       => $item->get_title(),
				'description' => $item->get_description(),
				'content'     => $item->get_content(),
				'link'        => $item->get_permalink(),
				'date'        => $item->get_date( 'U' ),
				'author'      => $item->get_author() ? $item->get_author()->get_name() : '',
				'audio_url'   => $enclosure ? $enclosure->get_link() : '',
				'audio_type'  => $enclosure ? $enclosure->get_type() : '',
				'duration'    => $enclosure ? $enclosure->get_duration() : '',
				'image'       => $episode_image_url,
				'podcast20'   => $p20_data,
			);

			$episodes[] = $episode;
		}

		/*
		 * Cache episodes with configurable duration (uses object cache if available).
		 *
		 * This duration controls how often PodLoom checks for new episodes.
		 * Since we now use HTTP conditional requests (ETag/Last-Modified), it's safe
		 * to use shorter durations (e.g., 1-2 hours) without overloading podcast hosts:
		 * - If the feed hasn't changed, the server returns 304 Not Modified (~200 bytes)
		 * - Full feed content is only downloaded when there are actual changes
		 *
		 * @see Settings → General → Cache Duration
		 */
		$cache_duration = get_option( 'podloom_cache_duration', 21600 ); // Default: 6 hours

		/**
		 * Filter the cache duration for a specific RSS feed.
		 *
		 * Allows per-feed cache duration overrides. Useful for feeds that update
		 * more frequently (e.g., news podcasts) or less frequently (e.g., archived shows).
		 *
		 * @since 2.5.0
		 * @param int    $cache_duration Cache duration in seconds.
		 * @param string $feed_id        RSS feed ID.
		 * @param array  $feed           Feed configuration array.
		 */
		$cache_duration = apply_filters( 'podloom_cache_duration', $cache_duration, $feed_id, $feed );

		podloom_cache_set( 'rss_episodes_' . $feed_id, $episodes, 'podloom', $cache_duration );

		// Increment render cache version to invalidate all rendered episode HTML
		// This ensures editor shows updated content after feed refresh
		// Note: This invalidates ALL episodes (not just this feed), but feed refreshes
		// are rare enough that this is acceptable for simplicity
		// Uses atomic increment function to avoid race conditions
		podloom_increment_render_cache_version();

		// Extract ETag and Last-Modified headers for future conditional requests
		$response_etag          = wp_remote_retrieve_header( $response, 'etag' );
		$response_last_modified = wp_remote_retrieve_header( $response, 'last-modified' );

		// Update feed metadata including conditional request headers
		$feeds                              = self::get_feeds();
		$feeds[ $feed_id ]['valid']         = true;
		$feeds[ $feed_id ]['last_checked']  = time();
		$feeds[ $feed_id ]['episode_count'] = count( $episodes );

		// Store headers for conditional requests (may be empty if server doesn't support them)
		if ( ! empty( $response_etag ) ) {
			$feeds[ $feed_id ]['etag'] = $response_etag;
		}
		if ( ! empty( $response_last_modified ) ) {
			$feeds[ $feed_id ]['last_modified'] = $response_last_modified;
		}

		update_option( 'podloom_rss_feeds', $feeds );

		return array(
			'success'       => true,
			'message'       => 'Feed refreshed successfully',
			'status_code'   => 200,
			'episode_count' => count( $episodes ),
		);
	}

	/**
	 * Get cached episodes for a feed
	 *
	 * @param string $feed_id Feed ID
	 * @param int    $page Page number (default: 1)
	 * @param int    $per_page Items per page (default: 20)
	 * @param bool   $allow_remote_fetch Whether to allow fetching from remote URL if cache is missing (default: true)
	 * @return array Episodes array with pagination info
	 */
	public static function get_episodes( $feed_id, $page = 1, $per_page = 20, $allow_remote_fetch = true ) {
		// Check if caching is enabled
		$enable_cache = get_option( 'podloom_enable_cache', true );

		if ( $enable_cache ) {
			$episodes = podloom_cache_get( 'rss_episodes_' . $feed_id );
		} else {
			$episodes = false;
		}

		// If no cache, handle based on context
		if ( $episodes === false ) {
			// If remote fetch is disabled (e.g. in editor), return empty result immediately
			if ( ! $allow_remote_fetch ) {
				return array(
					'episodes' => array(),
					'total'    => 0,
					'page'     => $page,
					'pages'    => 0,
					'error'    => 'cache_miss', // Signal that data is missing but fetch was skipped
				);
			}

			// On frontend, never block page load with synchronous fetch
			// Schedule background refresh and return empty - cron will populate cache
			if ( ! is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				// Schedule immediate background refresh if not already scheduled
				if ( ! wp_next_scheduled( 'podloom_refresh_rss_feed', array( $feed_id ) ) ) {
					wp_schedule_single_event( time(), 'podloom_refresh_rss_feed', array( $feed_id ) );
				}

				return array(
					'episodes' => array(),
					'total'    => 0,
					'page'     => $page,
					'pages'    => 0,
					'error'    => 'cache_miss',
				);
			}

			// In admin/AJAX contexts, allow synchronous fetch for immediate feedback
			$result = self::refresh_feed( $feed_id );
			if ( ! $result['success'] ) {
				return array(
					'episodes' => array(),
					'total'    => 0,
					'page'     => $page,
					'pages'    => 0,
					'error'    => 'fetch_failed',
				);
			}
			$episodes = podloom_cache_get( 'rss_episodes_' . $feed_id );
		}

		// Note: Character limit for descriptions is applied at render time (not here)
		// to preserve HTML formatting and avoid redundant processing on every retrieval.
		// See podloom_truncate_html() in podloom-podcast-player.php for HTML-aware truncation.

		// Paginate episodes
		$total     = count( $episodes );
		$pages     = ceil( $total / $per_page );
		$offset    = ( $page - 1 ) * $per_page;
		$paginated = array_slice( $episodes, $offset, $per_page );

		return array(
			'episodes' => $paginated,
			'total'    => $total,
			'page'     => $page,
			'pages'    => $pages,
		);
	}

	/**
	 * Get raw feed XML for preview
	 *
	 * @param string $feed_id Feed ID
	 * @return array Result with XML content
	 */
	public static function get_raw_feed( $feed_id ) {
		$feed_data = self::get_feed( $feed_id );

		if ( ! $feed_data ) {
			return array(
				'success' => false,
				'message' => 'Feed not found',
			);
		}

		$response = wp_remote_get(
			$feed_data['url'],
			array(
				'timeout'             => 15,
				'redirection'         => 3, // Limit redirects to prevent redirect chains
				'reject_unsafe_urls'  => true, // WordPress built-in SSRF protection
				'limit_response_size' => 5 * MB_IN_BYTES, // Limit to 5MB to prevent memory exhaustion
				'headers'             => array(
					'User-Agent' => 'PodLoom WordPress Plugin',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'Could not fetch feed: ' . $response->get_error_message(),
			);
		}

		// Additional check: ensure response size is within limits
		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > 5 * MB_IN_BYTES ) {
			return array(
				'success' => false,
				'message' => 'Feed too large (maximum 5MB)',
			);
		}

		return array(
			'success' => true,
			'xml'     => $body,
		);
	}

	/**
	 * Get the latest episode from a feed
	 *
	 * @param string $feed_id Feed ID
	 * @return array|null Latest episode data or null if not found
	 */
	public static function get_latest_episode( $feed_id ) {
		$episodes_data = self::get_episodes( $feed_id, 1, 1 );

		if ( ! empty( $episodes_data['episodes'] ) ) {
			return $episodes_data['episodes'][0];
		}

		return null;
	}

	/**
	 * Clear all RSS feed caches with batching to prevent table locks
	 */
	public static function clear_all_caches() {
		global $wpdb;

		// Use proper LIKE pattern preparation to prevent SQL injection
		$pattern1 = $wpdb->esc_like( '_transient_podloom_rss_episodes_' ) . '%';
		$pattern2 = $wpdb->esc_like( '_transient_timeout_podloom_rss_episodes_' ) . '%';

		// Delete in batches of 100 to prevent long table locks
		$batch_size    = 100;
		$deleted_count = 0;

		do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting cache entries, caching not applicable for DELETE operations
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options}
                     WHERE (option_name LIKE %s OR option_name LIKE %s)
                     LIMIT %d",
					$pattern1,
					$pattern2,
					$batch_size
				)
			);

			$deleted_count += $deleted;

			// Small delay between batches to prevent overwhelming the database
			if ( $deleted >= $batch_size ) {
				usleep( 50000 ); // 50ms delay
			}
		} while ( $deleted >= $batch_size );

		// Clear PodLoom object cache groups if using external object cache
		if ( wp_using_ext_object_cache() ) {
			if ( function_exists( 'wp_cache_flush_group' ) ) {
				wp_cache_flush_group( 'podloom' );
			}
		}

		return $deleted_count;
	}
}
