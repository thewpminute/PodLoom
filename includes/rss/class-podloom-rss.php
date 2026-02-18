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
	 * Default maximum number of episodes to cache per feed (0 = unlimited).
	 * Can be overridden via the podloom_max_episodes option in settings.
	 */
	const DEFAULT_MAX_EPISODES = 0;

	/**
	 * Get maximum episodes to cache per feed.
	 *
	 * @return int Maximum episode count (0 = unlimited).
	 */
	public static function get_max_episodes() {
		$max = (int) get_option( 'podloom_max_episodes', self::DEFAULT_MAX_EPISODES );

		/**
		 * Filter the maximum number of episodes to cache per feed.
		 *
		 * @param int $max Maximum episodes (0 = unlimited).
		 */
		return apply_filters( 'podloom_max_episodes', $max );
	}

	/**
	 * Sanitize episode HTML fields (description and content) at ingest/output time.
	 *
	 * Delegates to podloom_sanitize_rss_description_html() when available (loaded
	 * from includes/utilities.php). Falls back to wp_kses_post() so this method
	 * is safe to call even if utilities.php is not yet loaded.
	 *
	 * @param string $html Raw HTML string.
	 * @return string Sanitized HTML.
	 */
	private static function sanitize_episode_html( $html ) {
		if ( function_exists( 'podloom_sanitize_rss_description_html' ) ) {
			return podloom_sanitize_rss_description_html( $html );
		}
		return wp_kses_post( $html );
	}

	/**
	 * Re-sanitize description and content fields on every episode returned to callers.
	 *
	 * Provides defense-in-depth for episodes that were cached before this security
	 * patch was applied and may still contain un-sanitized HTML.
	 *
	 * @param array $episode Single episode array.
	 * @return array Episode with sanitized description and content fields.
	 */
	private static function sanitize_episode_fields_for_output( $episode ) {
		if ( ! empty( $episode['description'] ) ) {
			$episode['description'] = self::sanitize_episode_html( $episode['description'] );
		}
		if ( ! empty( $episode['content'] ) ) {
			$episode['content'] = self::sanitize_episode_html( $episode['content'] );
		}
		return $episode;
	}

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

		// Generate cryptographically secure unique ID (lowercase to avoid any case issues)
		$feed_id = 'rss_' . strtolower( wp_generate_password( 32, false ) );

		// Get feeds
		$feeds = self::get_feeds();

		// Create the new feed data
		$new_feed = array(
			'id'           => $feed_id,
			'name'         => sanitize_text_field( $name ),
			'url'          => esc_url_raw( $url ),
			'created'      => time(),
			'last_checked' => null,
			'valid'        => false, // Will be set to true after successful refresh
		);

		// Add new feed
		$feeds[ $feed_id ] = $new_feed;

		// Save feeds
		update_option( 'podloom_rss_feeds', $feeds );

		// Clear WordPress object cache to ensure get_option returns fresh data
		// This is critical because refresh_feed_with_data() calls get_feeds() internally
		wp_cache_delete( 'podloom_rss_feeds', 'options' );

		// Auto-enable RSS feeds if this is the first feed being added
		$rss_enabled = get_option( 'podloom_rss_enabled', false );
		if ( ! $rss_enabled ) {
			update_option( 'podloom_rss_enabled', true );
		}

		// Refresh feed to cache episodes - pass feed data directly to avoid lookup issues
		$refresh_result = self::refresh_feed_with_data( $feed_id, $new_feed, true );

		// Update the feed with refresh results
		$feeds = get_option( 'podloom_rss_feeds', array() );

		// For subsequent refreshes, use async if requested
		if ( $async_refresh ) {
			// Schedule periodic background refresh for this feed
			wp_schedule_single_event( time() + 3600, 'podloom_refresh_rss_feed', array( $feed_id ) );
		}

		return array(
			'success' => ! empty( $refresh_result['success'] ),
			'message' => ! empty( $refresh_result['success'] ) ? 'Feed added successfully' : ( $refresh_result['message'] ?? 'Feed validation failed' ),
			'feed'    => $feeds[ $feed_id ] ?? $new_feed,
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

		return self::refresh_feed_with_data( $feed_id, $feed_data, $force );
	}

	/**
	 * Refresh a feed with provided feed data (internal use)
	 *
	 * This method accepts feed data directly to avoid lookup issues when
	 * the feed was just added and may not be available via get_feed() yet.
	 *
	 * @param string $feed_id   Feed ID.
	 * @param array  $feed_data Feed data array with 'url' key required.
	 * @param bool   $force     Force full refresh, bypassing conditional headers. Default false.
	 * @return array Result with success status, message, and status code.
	 */
	public static function refresh_feed_with_data( $feed_id, $feed_data, $force = false ) {
		if ( empty( $feed_data['url'] ) ) {
			return array(
				'success' => false,
				'message' => 'Feed URL not provided',
			);
		}

		// Check retry count and apply exponential backoff for failing feeds.
		$retry_count = isset( $feed_data['retry_count'] ) ? (int) $feed_data['retry_count'] : 0;
		$max_retries = 3;

		if ( $retry_count >= $max_retries && ! $force ) {
			$last_retry   = isset( $feed_data['last_retry'] ) ? (int) $feed_data['last_retry'] : 0;
			$backoff_time = min( pow( 2, $retry_count ) * HOUR_IN_SECONDS, DAY_IN_SECONDS );

			if ( ( time() - $last_retry ) < $backoff_time ) {
				// Still in backoff period - return cached data if available.
				$cached = podloom_cache_get( 'rss_episodes_' . $feed_id );
				if ( $cached ) {
					return array(
						'success'     => true,
						'message'     => 'Using cached data during backoff period',
						'status_code' => 0,
						'episodes'    => $cached,
						'from_cache'  => true,
						'in_backoff'  => true,
					);
				}
			}
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
			// Mark feed as having issues, but DO NOT delete existing cache.
			// This preserves last-known-good data when the feed is temporarily unavailable.
			$feeds                             = self::get_feeds();
			$feeds[ $feed_id ]['valid']        = false;
			$feeds[ $feed_id ]['last_checked'] = time();
			$feeds[ $feed_id ]['retry_count']  = $retry_count + 1;
			$feeds[ $feed_id ]['last_retry']   = time();
			$feeds[ $feed_id ]['last_error']   = $response->get_error_message();
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
			$feeds[ $feed_id ]['retry_count']  = $retry_count + 1;
			$feeds[ $feed_id ]['last_retry']   = time();
			$feeds[ $feed_id ]['last_error']   = 'HTTP ' . $status_code;
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

		// Get episodes up to the configured maximum (0 = unlimited)
		$max_episodes = self::get_max_episodes();
		$items        = $max_episodes > 0 ? $feed->get_items( 0, $max_episodes ) : $feed->get_items();
		$episodes = array();

		// Initialize P2.0 parser
		$p20_parser = new Podloom_Podcast20_Parser();

		// Parse channel-level P2.0 data (applies to all episodes)
		$channel_p20_data = $p20_parser->parse_from_simplepie_channel( $feed );

		// Get the podcast cover image (channel-level).
		// Note: Local caching is handled at render time, not here. This keeps cached data canonical (remote URLs).
		$podcast_cover_url = $feed->get_image_url() ? $feed->get_image_url() : '';

		foreach ( $items as $item ) {
			$enclosure = $item->get_enclosure();

			// Parse item-level Podcasting 2.0 data
			$item_p20_data = $p20_parser->parse_from_simplepie( $item );

			// Merge channel and item data (item takes precedence)
			$p20_data = $p20_parser->merge_data( $item_p20_data, $channel_p20_data );

			// Get episode image (item thumbnail or fallback to podcast cover).
			// Note: Local caching is handled at render time, not here.
			$episode_image_url = '';
			$thumbnail         = $item->get_thumbnail();
			if ( $thumbnail && ! empty( $thumbnail['url'] ) ) {
				$episode_image_url = $thumbnail['url'];
			} else {
				// Use podcast cover as fallback.
				$episode_image_url = $podcast_cover_url;
			}

			// Note: Chapter images are cached lazily when the episode is rendered,
			// not during feed refresh. This avoids downloading images for episodes
			// that may never be embedded. See podloom_cache_chapter_images().

			$episode = array(
				'id'          => md5( $item->get_permalink() ),
				'title'       => $item->get_title(),
				'description' => self::sanitize_episode_html( (string) $item->get_description() ),
				'content'     => self::sanitize_episode_html( (string) $item->get_content() ),
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
		$default_duration = (int) get_option( 'podloom_cache_duration', 21600 ); // Default: 6 hours
		$dynamic_duration = self::calculate_cache_duration( $episodes );
		$cache_duration   = min( $default_duration, $dynamic_duration ); // Use shorter of the two

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
		 * @param array  $episodes       Parsed episodes array (for analysis).
		 */
		$cache_duration = apply_filters( 'podloom_cache_duration', $cache_duration, $feed_id, $feed, $episodes );

		podloom_cache_set( 'rss_episodes_' . $feed_id, $episodes, 'podloom', $cache_duration );

		// Store to persistent storage as fallback for cache misses.
		if ( class_exists( 'Podloom_RSS_Storage' ) ) {
			Podloom_RSS_Storage::store_episodes( $feed_id, $episodes );
		}

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
		$feeds                               = self::get_feeds();
		$feeds[ $feed_id ]['valid']          = true;
		$feeds[ $feed_id ]['last_checked']   = time();
		$feeds[ $feed_id ]['episode_count']  = count( $episodes );
		$feeds[ $feed_id ]['cache_duration'] = $cache_duration; // Store for per-feed cron scheduling
		$feeds[ $feed_id ]['retry_count']    = 0; // Reset retry count on success.
		$feeds[ $feed_id ]['last_error']     = null; // Clear last error on success.

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
			'episodes'      => $episodes, // Return parsed episodes for direct use if cache fails
		);
	}

	/**
	 * Calculate optimal cache duration based on podcast release patterns.
	 *
	 * Analyzes recent episode dates to determine how frequently a podcast releases
	 * and adjusts cache duration accordingly:
	 * - Inactive podcasts (>90 days): 7 day cache
	 * - Active podcasts near release: 30 min to 6 hour cache
	 * - Active podcasts not near release: 1 day cache
	 *
	 * @since 2.16.0
	 * @param array $episodes Array of episode data with 'date' keys.
	 * @return int Cache duration in seconds.
	 */
	private static function calculate_cache_duration( $episodes ) {
		// Default durations.
		$inactive_duration = 7 * DAY_IN_SECONDS;    // 7 days for inactive podcasts.
		$active_duration   = DAY_IN_SECONDS;        // 1 day default for active podcasts.
		$near_duration     = 6 * HOUR_IN_SECONDS;   // 6 hours when near expected release.
		$close_duration    = HOUR_IN_SECONDS;       // 1 hour when very close.
		$imminent_duration = 30 * MINUTE_IN_SECONDS; // 30 min when release is imminent.

		if ( empty( $episodes ) || count( $episodes ) < 2 ) {
			return $active_duration;
		}

		// Sort episodes by date (newest first).
		$sorted = $episodes;
		usort(
			$sorted,
			function ( $a, $b ) {
				return strtotime( $b['date'] ?? 0 ) - strtotime( $a['date'] ?? 0 );
			}
		);

		// Get episodes from last 6 months for analysis.
		$six_months_ago   = strtotime( '-6 months' );
		$recent_episodes  = array_filter(
			$sorted,
			function ( $ep ) use ( $six_months_ago ) {
				return strtotime( $ep['date'] ?? 0 ) > $six_months_ago;
			}
		);

		// Check if podcast is active (episode within last 90 days).
		$latest_date     = strtotime( $sorted[0]['date'] ?? 0 );
		$days_since_last = ( time() - $latest_date ) / DAY_IN_SECONDS;

		if ( $days_since_last > 90 ) {
			// Inactive podcast - check weekly.
			return $inactive_duration;
		}

		// Calculate average release interval from recent episodes.
		if ( count( $recent_episodes ) < 2 ) {
			return $active_duration;
		}

		$intervals       = array();
		$recent_episodes = array_values( $recent_episodes );

		for ( $i = 0; $i < count( $recent_episodes ) - 1; $i++ ) {
			$date1 = strtotime( $recent_episodes[ $i ]['date'] ?? 0 );
			$date2 = strtotime( $recent_episodes[ $i + 1 ]['date'] ?? 0 );
			if ( $date1 && $date2 ) {
				$intervals[] = ( $date1 - $date2 ) / DAY_IN_SECONDS;
			}
		}

		if ( empty( $intervals ) ) {
			return $active_duration;
		}

		// Remove outliers (more than 2x standard deviation).
		$avg     = array_sum( $intervals ) / count( $intervals );
		$std_dev = sqrt(
			array_sum(
				array_map(
					function ( $x ) use ( $avg ) {
						return pow( $x - $avg, 2 );
					},
					$intervals
				)
			) / count( $intervals )
		);

		$filtered = array_filter(
			$intervals,
			function ( $x ) use ( $avg, $std_dev ) {
				return abs( $x - $avg ) <= 2 * $std_dev;
			}
		);

		$release_cycle = ! empty( $filtered ) ? array_sum( $filtered ) / count( $filtered ) : $avg;

		// Calculate days until expected next release.
		$days_until_next = $release_cycle - $days_since_last;

		// Return appropriate cache duration based on proximity to expected release.
		if ( $days_until_next <= 1 ) {
			return $imminent_duration; // 30 minutes.
		} elseif ( $days_until_next <= 2 ) {
			return $close_duration; // 1 hour.
		} elseif ( $days_until_next <= 7 ) {
			return $near_duration; // 6 hours.
		}

		return $active_duration; // 1 day.
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
		// Clamp pagination integers to safe ranges.
		$page     = max( 1, (int) $page );
		$per_page = max( 1, (int) $per_page );

		// Check if caching is enabled
		$enable_cache = get_option( 'podloom_enable_cache', true );

		if ( $enable_cache ) {
			$episodes = podloom_cache_get( 'rss_episodes_' . $feed_id );
		} else {
			$episodes = false;
		}

		// If no cache, try persistent storage as fallback.
		if ( $episodes === false && class_exists( 'Podloom_RSS_Storage' ) ) {
			$max_episodes = self::get_max_episodes();
			$episodes     = Podloom_RSS_Storage::get_episodes( $feed_id, $max_episodes );

			// If we got episodes from storage, re-cache them.
			if ( ! empty( $episodes ) && $enable_cache ) {
				$cache_duration = (int) get_option( 'podloom_cache_duration', 21600 );
				podloom_cache_set( 'rss_episodes_' . $feed_id, $episodes, 'podloom', $cache_duration );
			}
		}

		// If still no episodes, handle based on context
		if ( empty( $episodes ) ) {
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

			// Try cache first, but use fresh data from refresh_feed() as fallback
			$episodes = podloom_cache_get( 'rss_episodes_' . $feed_id );

			// Cache retrieval failed - use the episodes returned directly from refresh_feed()
			if ( $episodes === false && ! empty( $result['episodes'] ) ) {
				$episodes = $result['episodes'];
			}
		}

		// Final safety check - ensure $episodes is an array before counting
		if ( ! is_array( $episodes ) ) {
			return array(
				'episodes' => array(),
				'total'    => 0,
				'page'     => $page,
				'pages'    => 0,
				'error'    => 'invalid_cache_data',
			);
		}

		// Note: Character limit for descriptions is applied at render time (not here)
		// to preserve HTML formatting and avoid redundant processing on every retrieval.
		// See podloom_truncate_html() in podloom-podcast-player.php for HTML-aware truncation.

		// Defense-in-depth: re-sanitize HTML fields on episodes that may have been cached
		// before this security patch was applied.
		$episodes = array_map( array( __CLASS__, 'sanitize_episode_fields_for_output' ), $episodes );

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
			'offset'   => $offset,
			'has_more' => ( $offset + count( $paginated ) ) < $total,
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
