<?php
/**
 * RSS Feed AJAX Handlers
 *
 * @package PodLoom
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Handler: Get RSS Feeds
 */
function podloom_ajax_get_rss_feeds() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	// Rate limiting: 30 requests per minute
	if ( ! Podloom_RSS::check_rate_limit( 'get_feeds', 30, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please try again later.' ), 429 );
		return;
	}

	$feeds = Podloom_RSS::get_feeds();
	wp_send_json_success( $feeds );
}
add_action( 'wp_ajax_podloom_get_rss_feeds', 'podloom_ajax_get_rss_feeds' );

/**
 * AJAX Handler: Add RSS Feed
 */
function podloom_ajax_add_rss_feed() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	// Rate limiting: 10 feed additions per minute per user
	if ( ! Podloom_RSS::check_rate_limit( 'add_feed', 10, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please wait before adding more feeds.' ), 429 );
		return;
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$url  = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

	if ( empty( $name ) || empty( $url ) ) {
		wp_send_json_error( array( 'message' => 'Name and URL are required' ) );
		return;
	}

	$result = Podloom_RSS::add_feed( $name, $url );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_podloom_add_rss_feed', 'podloom_ajax_add_rss_feed' );

/**
 * AJAX Handler: Update RSS Feed Name
 */
function podloom_ajax_update_rss_feed_name() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';
	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

	if ( empty( $feed_id ) || empty( $name ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID and name are required' ) );
		return;
	}

	$result = Podloom_RSS::update_feed_name( $feed_id, $name );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_podloom_update_rss_feed_name', 'podloom_ajax_update_rss_feed_name' );

/**
 * AJAX Handler: Delete RSS Feed
 */
function podloom_ajax_delete_rss_feed() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID is required' ) );
		return;
	}

	$result = Podloom_RSS::delete_feed( $feed_id );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_podloom_delete_rss_feed', 'podloom_ajax_delete_rss_feed' );

/**
 * AJAX Handler: Refresh RSS Feed
 */
function podloom_ajax_refresh_rss_feed() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	// Rate limiting: 30 refreshes per minute per user
	if ( ! Podloom_RSS::check_rate_limit( 'refresh_feed', 30, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please wait before refreshing feeds.' ), 429 );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID is required' ) );
		return;
	}

	$result = Podloom_RSS::refresh_feed( $feed_id );

	// Get updated feed data
	$feed = Podloom_RSS::get_feed( $feed_id );

	// Build response with status details
	$response = array(
		'message'      => $result['message'],
		'feed'         => $feed,
		'status_code'  => isset( $result['status_code'] ) ? $result['status_code'] : null,
		'not_modified' => isset( $result['not_modified'] ) ? $result['not_modified'] : false,
		'cache_kept'   => isset( $result['cache_kept'] ) ? $result['cache_kept'] : false,
	);

	if ( isset( $result['episode_count'] ) ) {
		$response['episode_count'] = $result['episode_count'];
	}

	if ( $result['success'] ) {
		wp_send_json_success( $response );
	} else {
		wp_send_json_error( $response );
	}
}
add_action( 'wp_ajax_podloom_refresh_rss_feed', 'podloom_ajax_refresh_rss_feed' );

/**
 * AJAX Handler: Get RSS Episodes
 * Supports both page/per_page and offset/limit pagination.
 */
function podloom_ajax_get_rss_episodes() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	// Rate limiting: 60 requests per minute (higher than feeds since episodes are paginated)
	if ( ! Podloom_RSS::check_rate_limit( 'get_episodes', 60, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please try again later.' ), 429 );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID is required' ) );
		return;
	}

	// Support both offset/limit and page/per_page parameters.
	// If offset is provided, use offset/limit mode for prefetch-friendly pagination.
	if ( isset( $_POST['offset'] ) ) {
		$offset = absint( wp_unslash( $_POST['offset'] ) );
		$limit  = isset( $_POST['limit'] ) ? max( 1, min( absint( wp_unslash( $_POST['limit'] ) ), 50 ) ) : 20; // Cap between 1-50.
		$page   = floor( $offset / $limit ) + 1;
	} else {
		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 20;
		$limit    = $per_page;
	}

	$result = Podloom_RSS::get_episodes( $feed_id, $page, $limit );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_podloom_get_rss_episodes', 'podloom_ajax_get_rss_episodes' );

/**
 * AJAX Handler: Get RSS Episodes (Public/Frontend)
 *
 * Public endpoint for frontend playlist prefetching.
 * Returns episode data without requiring login.
 */
function podloom_ajax_get_rss_episodes_public() {
	// Verify nonce - uses playlist nonce for frontend.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'podloom_playlist_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
		return;
	}

	// Rate limiting: 60 requests per minute per IP.
	if ( ! Podloom_RSS::check_rate_limit( 'get_episodes_public', 60, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please try again later.' ), 429 );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID is required' ) );
		return;
	}

	// Support offset/limit for prefetching.
	$offset = isset( $_POST['offset'] ) ? absint( wp_unslash( $_POST['offset'] ) ) : 0;
	$limit  = isset( $_POST['limit'] ) ? max( 1, min( absint( wp_unslash( $_POST['limit'] ) ), 50 ) ) : 20; // Cap between 1-50.
	$page   = floor( $offset / $limit ) + 1;

	// Get episodes (disallow remote fetch to prevent blocking).
	$result = Podloom_RSS::get_episodes( $feed_id, $page, $limit, false );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_podloom_get_rss_episodes_public', 'podloom_ajax_get_rss_episodes_public' );
add_action( 'wp_ajax_nopriv_podloom_get_rss_episodes_public', 'podloom_ajax_get_rss_episodes_public' );

/**
 * AJAX Handler: Get Raw RSS Feed
 */
function podloom_ajax_get_raw_rss_feed() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID is required' ) );
		return;
	}

	$result = Podloom_RSS::get_raw_feed( $feed_id );

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_podloom_get_raw_rss_feed', 'podloom_ajax_get_raw_rss_feed' );

/**
 * AJAX Handler: Save All RSS Settings (Bulk)
 */
function podloom_ajax_save_all_rss_settings() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	$settings_json = isset( $_POST['settings'] ) ? sanitize_text_field( wp_unslash( $_POST['settings'] ) ) : '';
	$settings      = json_decode( $settings_json, true );

	if ( ! is_array( $settings ) ) {
		wp_send_json_error( array( 'message' => 'Invalid settings data' ) );
		return;
	}

	// List of boolean options
	$boolean_options = array(
		'podloom_rss_enabled',
		'podloom_rss_display_artwork',
		'podloom_rss_display_title',
		'podloom_rss_display_date',
		'podloom_rss_display_duration',
		'podloom_rss_display_description',
		'podloom_rss_display_skip_buttons',
		'podloom_rss_display_funding',
		'podloom_rss_display_transcripts',
		'podloom_rss_display_people_hosts',
		'podloom_rss_display_people_guests',
		'podloom_rss_display_chapters',
		'podloom_rss_minimal_styling',
	);

	$saved_count = 0;
	foreach ( $settings as $option_name => $option_value ) {
		// Validate option name - must start with podloom_rss_
		if ( strpos( $option_name, 'podloom_rss_' ) !== 0 ) {
			continue;
		}

		// Convert to boolean if it's a boolean option
		if ( in_array( $option_name, $boolean_options, true ) ) {
			$option_value = ( $option_value === '1' );
		} else {
			// Sanitize text field
			$option_value = sanitize_text_field( $option_value );

			// For color values, sanitize as hex color
			if ( strpos( $option_name, '_color' ) !== false ) {
				$option_value = sanitize_hex_color( $option_value );
			}
		}

		update_option( $option_name, $option_value );
		++$saved_count;
	}

	// Clear typography cache when settings are saved
	podloom_clear_typography_cache();

	wp_send_json_success(
		array(
			'message' => 'All settings saved successfully',
			'count'   => $saved_count,
		)
	);
}
add_action( 'wp_ajax_podloom_save_all_rss_settings', 'podloom_ajax_save_all_rss_settings' );

/**
 * AJAX Handler: Get RSS Typography Settings
 */
function podloom_ajax_get_rss_typography() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	// Get typography styles
	$typo = podloom_get_rss_typography_styles();

	// Add background color
	$typo['background_color'] = get_option( 'podloom_rss_background_color', '#f9f9f9' );

	// Add minimal styling mode
	$typo['minimal_styling'] = get_option( 'podloom_rss_minimal_styling', false );

	// Add display settings
	$typo['display'] = array(
		'artwork'      => get_option( 'podloom_rss_display_artwork', true ),
		'title'        => get_option( 'podloom_rss_display_title', true ),
		'date'         => get_option( 'podloom_rss_display_date', true ),
		'duration'     => get_option( 'podloom_rss_display_duration', true ),
		'description'  => get_option( 'podloom_rss_display_description', true ),
		'skip_buttons' => get_option( 'podloom_rss_display_skip_buttons', true ),
	);

	wp_send_json_success( $typo );
}
add_action( 'wp_ajax_podloom_get_rss_typography', 'podloom_ajax_get_rss_typography' );

/**
 * AJAX Handler: Render RSS Episode HTML (for block editor preview)
 * Returns fully rendered episode HTML including P2.0 tabs
 */
function podloom_ajax_render_rss_episode() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	// Get episode data from request - JSON string that will be decoded and fields sanitized below.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded, then individual fields sanitized.
	$episode_data_raw = isset( $_POST['episode_data'] ) ? wp_unslash( $_POST['episode_data'] ) : '';
	$feed_id          = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';

	// Decode JSON
	$episode_data = json_decode( $episode_data_raw, true );

	// Validate JSON structure
	if ( ! is_array( $episode_data ) || empty( $episode_data ) ) {
		wp_send_json_error( array( 'message' => 'Invalid episode data' ) );
		return;
	}

	// Sanitize critical fields that will be rendered to prevent XSS
	// Title is rendered as text, so sanitize
	if ( isset( $episode_data['title'] ) ) {
		$episode_data['title'] = sanitize_text_field( $episode_data['title'] );
	}

	// ID should be alphanumeric
	if ( isset( $episode_data['id'] ) ) {
		$episode_data['id'] = sanitize_text_field( $episode_data['id'] );
	}

	// URLs should be validated
	if ( isset( $episode_data['audio_url'] ) ) {
		$episode_data['audio_url'] = esc_url_raw( $episode_data['audio_url'] );
	}
	if ( isset( $episode_data['image'] ) ) {
		$episode_data['image'] = esc_url_raw( $episode_data['image'] );
	}

	// Date should be sanitized
	if ( isset( $episode_data['date'] ) ) {
		$episode_data['date'] = sanitize_text_field( $episode_data['date'] );
	}

	// Duration should be numeric/text
	if ( isset( $episode_data['duration'] ) ) {
		$episode_data['duration'] = sanitize_text_field( $episode_data['duration'] );
	}

	// Audio type should be sanitized
	if ( isset( $episode_data['audio_type'] ) ) {
		$episode_data['audio_type'] = sanitize_text_field( $episode_data['audio_type'] );
	}

	// Description contains HTML - will be sanitized by render function using wp_kses_post()
	// Podcast20 data will be validated by render function

	// Generate cache key based on episode ID, audio URL, and render cache version
	// Version changes when typography/display settings change, invalidating old cache
	$episode_id     = $episode_data['id'] ?? '';
	$audio_url      = $episode_data['audio_url'] ?? '';
	$render_version = get_option( 'podloom_render_cache_version', 0 );
	$cache_key      = 'rendered_episode_' . md5( $episode_id . $audio_url . $render_version );

	// Try to get from cache first (6 hours cache - matches other caches)
	$cached_html = podloom_cache_get( $cache_key, 'podloom_editor' );
	if ( $cached_html !== false ) {
		wp_send_json_success(
			array(
				'html'   => $cached_html,
				'cached' => true,
			)
		);
		return;
	}

	// Build attributes array matching the block's renderRssEpisode format
	$attributes = array(
		'rssEpisodeData' => $episode_data,
		'rssFeedId'      => $feed_id,
	);

	// Use the same rendering function as front-end
	$html = podloom_render_rss_episode( $attributes );

	if ( empty( $html ) ) {
		wp_send_json_error( array( 'message' => 'Failed to render episode HTML' ) );
		return;
	}

	// Cache the rendered HTML for 6 hours (21600 seconds - matches episode data cache)
	podloom_cache_set( $cache_key, $html, 'podloom_editor', 21600 );

	wp_send_json_success(
		array(
			'html'   => $html,
			'cached' => false,
		)
	);
}
add_action( 'wp_ajax_podloom_render_rss_episode', 'podloom_ajax_render_rss_episode' );

/**
 * AJAX Handler: Render RSS Playlist HTML (for block editor preview)
 * Returns fully rendered playlist HTML including episodes tab and player
 */
function podloom_ajax_render_rss_playlist() {
	check_ajax_referer( 'podloom_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		return;
	}

	$feed_id       = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';
	$max_episodes  = isset( $_POST['max_episodes'] ) ? absint( $_POST['max_episodes'] ) : 25;
	$playlist_order = isset( $_POST['playlist_order'] ) ? sanitize_text_field( wp_unslash( $_POST['playlist_order'] ) ) : 'episodic';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Missing feed ID' ) );
		return;
	}

	// Validate feed exists.
	$feeds = get_option( 'podloom_rss_feeds', array() );
	if ( ! isset( $feeds[ $feed_id ] ) ) {
		wp_send_json_error( array( 'message' => 'Feed not found' ) );
		return;
	}

	// Generate cache key.
	$cache_key = 'playlist_editor_' . $feed_id . '_' . $max_episodes . '_' . $playlist_order;

	// Check cache first.
	$cached_html = podloom_cache_get( $cache_key, 'podloom_editor' );
	if ( false !== $cached_html ) {
		wp_send_json_success(
			array(
				'html'   => $cached_html,
				'cached' => true,
			)
		);
		return;
	}

	// Build attributes array for the render function.
	$attributes = array(
		'playlistMaxEpisodes' => $max_episodes,
		'playlistOrder'       => $playlist_order,
	);

	// Render the playlist HTML.
	$html = podloom_render_rss_playlist( $feed_id, $max_episodes, $attributes );

	if ( empty( $html ) ) {
		wp_send_json_error( array( 'message' => 'Failed to render playlist HTML' ) );
		return;
	}

	// Cache the rendered HTML for 6 hours.
	podloom_cache_set( $cache_key, $html, 'podloom_editor', 21600 );

	wp_send_json_success(
		array(
			'html'   => $html,
			'cached' => false,
		)
	);
}
add_action( 'wp_ajax_podloom_render_rss_playlist', 'podloom_ajax_render_rss_playlist' );

/**
 * AJAX Handler: Process Image Cache Queue
 *
 * Processes queued images for background caching.
 * Called via AJAX after page load to avoid blocking rendering.
 */
function podloom_ajax_process_image_cache() {
	// Nonce verification: prevents CSRF abuse of this endpoint.
	// The nonce is generated server-side and passed to JavaScript via wp_localize_script().
	$nonce = isset( $_REQUEST['podloom_image_cache_nonce'] )
		? sanitize_text_field( wp_unslash( $_REQUEST['podloom_image_cache_nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, 'podloom_image_cache_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
		return;
	}

	// Rate limiting: 20 requests per minute per IP.
	if ( ! Podloom_RSS::check_rate_limit( 'image_cache', 20, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded' ), 429 );
		return;
	}

	// Check if image caching is enabled.
	if ( ! Podloom_Image_Cache::is_enabled() ) {
		wp_send_json_error( array( 'message' => 'Image caching is disabled' ) );
		return;
	}

	// Get the queue.
	$queue = Podloom_Image_Cache::get_queue();

	if ( empty( $queue ) ) {
		wp_send_json_success( array( 'processed' => 0 ) );
		return;
	}

	// Process up to 5 images per request to avoid timeout.
	$processed = 0;
	$max_per_request = 5;
	$results = array();

	foreach ( $queue as $key => $item ) {
		if ( $processed >= $max_per_request ) {
			break;
		}

		// Validate queue item structure before processing.
		if ( ! is_array( $item ) || empty( $item['url'] ) || empty( $item['type'] ) ) {
			unset( $queue[ $key ] );
			continue;
		}

		$result = Podloom_Image_Cache::process_queued_image(
			$item['url'],
			$item['type'],
			$item['feed_id'] ?? ''
		);

		$results[ $key ] = $result;

		// Remove from queue regardless of success/failure.
		unset( $queue[ $key ] );
		++$processed;
	}

	// Update the queue (remove processed items).
	if ( empty( $queue ) ) {
		Podloom_Image_Cache::clear_queue();
	} else {
		set_transient( 'podloom_image_cache_queue', $queue, 5 * MINUTE_IN_SECONDS );
	}

	wp_send_json_success(
		array(
			'processed' => $processed,
			'remaining' => count( $queue ),
			'results'   => $results,
		)
	);
}
add_action( 'wp_ajax_podloom_process_image_cache', 'podloom_ajax_process_image_cache' );
add_action( 'wp_ajax_nopriv_podloom_process_image_cache', 'podloom_ajax_process_image_cache' );

/**
 * AJAX Handler: Get Episode Podcast 2.0 Data
 *
 * Returns P2.0 data (chapters, transcripts, people) for a specific episode.
 * Used by playlist player when switching episodes to update tab content.
 * Available to both logged-in and logged-out users since it's frontend functionality.
 */
function podloom_ajax_get_episode_p20() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'podloom_playlist_nonce' ) ) {
		wp_send_json_error( array( 'message' => 'Invalid security token' ), 403 );
		return;
	}

	// Rate limiting: 60 requests per minute (similar to get_episodes).
	if ( ! Podloom_RSS::check_rate_limit( 'get_episode_p20', 60, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please try again later.' ), 429 );
		return;
	}

	$feed_id    = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';
	$episode_id = isset( $_POST['episode_id'] ) ? sanitize_text_field( wp_unslash( $_POST['episode_id'] ) ) : '';

	if ( empty( $feed_id ) || empty( $episode_id ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID and Episode ID are required' ) );
		return;
	}

	// Get all episodes for this feed (with P2.0 data).
	$result = Podloom_RSS::get_episodes( $feed_id, 1, 100, true );

	if ( empty( $result['episodes'] ) ) {
		wp_send_json_error( array( 'message' => 'No episodes found' ) );
		return;
	}

	// Find the specific episode by ID.
	$episode = null;
	foreach ( $result['episodes'] as $ep ) {
		if ( isset( $ep['id'] ) && $ep['id'] === $episode_id ) {
			$episode = $ep;
			break;
		}
	}

	if ( ! $episode ) {
		wp_send_json_error( array( 'message' => 'Episode not found' ) );
		return;
	}

	// Return P2.0 data.
	$p20_data = isset( $episode['podcast20'] ) ? $episode['podcast20'] : array();

	wp_send_json_success( $p20_data );
}
add_action( 'wp_ajax_podloom_get_episode_p20', 'podloom_ajax_get_episode_p20' );
add_action( 'wp_ajax_nopriv_podloom_get_episode_p20', 'podloom_ajax_get_episode_p20' );

/**
 * AJAX Handler: Get Paginated Playlist Episodes
 *
 * Public endpoint for loading more episodes in the playlist player.
 * No nonce required - this is a read-only endpoint for public visitors.
 *
 * @return void Outputs JSON response.
 */
function podloom_ajax_playlist_episodes() {
	// Rate limiting: 60 requests per minute per IP.
	if ( ! Podloom_RSS::check_rate_limit( 'playlist_episodes', 60, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded' ), 429 );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';
	$offset  = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
	$limit   = isset( $_POST['limit'] ) ? min( 50, absint( $_POST['limit'] ) ) : 20;
	$order   = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'episodic';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Missing feed ID' ) );
		return;
	}

	// Get all episodes from cache.
	$all_episodes = podloom_cache_get( 'rss_episodes_' . $feed_id );

	if ( $all_episodes === false ) {
		// Try to refresh the feed.
		$result = Podloom_RSS::refresh_feed( $feed_id );
		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => 'Feed not available' ) );
			return;
		}
		$all_episodes = podloom_cache_get( 'rss_episodes_' . $feed_id );
	}

	if ( ! is_array( $all_episodes ) ) {
		wp_send_json_success(
			array(
				'episodes' => array(),
				'total'    => 0,
				'has_more' => false,
			)
		);
		return;
	}

	// Apply order.
	if ( 'serial' === $order ) {
		$all_episodes = array_reverse( $all_episodes );
	}

	$total = count( $all_episodes );

	// Paginate.
	$episodes = array_slice( $all_episodes, $offset, $limit );

	// Sanitize output.
	$episodes = podloom_sanitize_episodes_for_output( $episodes, $feed_id );

	wp_send_json_success(
		array(
			'episodes' => $episodes,
			'total'    => $total,
			'offset'   => $offset,
			'has_more' => ( $offset + $limit ) < $total,
		)
	);
}
add_action( 'wp_ajax_podloom_playlist_episodes', 'podloom_ajax_playlist_episodes' );
add_action( 'wp_ajax_nopriv_podloom_playlist_episodes', 'podloom_ajax_playlist_episodes' );

/**
 * AJAX Handler: Search Episodes
 *
 * Public endpoint for searching episodes in the playlist player.
 * No nonce required - this is a read-only endpoint for public visitors.
 *
 * @return void Outputs JSON response.
 */
function podloom_ajax_search_episodes() {
	// Rate limiting: 30 requests per minute (more restrictive for search).
	if ( ! Podloom_RSS::check_rate_limit( 'search_episodes', 30, 60 ) ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded' ), 429 );
		return;
	}

	$feed_id = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';
	$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$limit   = isset( $_POST['limit'] ) ? min( 50, absint( $_POST['limit'] ) ) : 20;
	$order   = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : 'episodic';

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Missing feed ID' ) );
		return;
	}

	// Require minimum search term length.
	if ( strlen( $search ) < 2 ) {
		wp_send_json_error( array( 'message' => 'Search term too short' ) );
		return;
	}

	// Get all episodes from cache.
	$all_episodes = podloom_cache_get( 'rss_episodes_' . $feed_id );

	if ( ! is_array( $all_episodes ) || empty( $all_episodes ) ) {
		wp_send_json_success(
			array(
				'episodes' => array(),
				'total'    => 0,
			)
		);
		return;
	}

	// Apply order first.
	if ( 'serial' === $order ) {
		$all_episodes = array_reverse( $all_episodes );
	}

	// Search (case-insensitive, title only for speed).
	$search_lower = strtolower( $search );
	$results      = array();

	foreach ( $all_episodes as $episode ) {
		$title = strtolower( $episode['title'] ?? '' );

		if ( strpos( $title, $search_lower ) !== false ) {
			$results[] = $episode;

			// Limit results.
			if ( count( $results ) >= $limit ) {
				break;
			}
		}
	}

	// Sanitize output.
	$results = podloom_sanitize_episodes_for_output( $results, $feed_id );

	wp_send_json_success(
		array(
			'episodes'    => $results,
			'total'       => count( $results ),
			'search_term' => $search,
		)
	);
}
add_action( 'wp_ajax_podloom_search_episodes', 'podloom_ajax_search_episodes' );
add_action( 'wp_ajax_nopriv_podloom_search_episodes', 'podloom_ajax_search_episodes' );

/**
 * Sanitize episodes for JSON output.
 *
 * @param array  $episodes Episodes array.
 * @param string $feed_id  Feed ID for image caching.
 * @return array Sanitized episodes.
 */
function podloom_sanitize_episodes_for_output( $episodes, $feed_id ) {
	$allowed_html = array(
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'a'      => array(
			'href'   => array(),
			'target' => array(),
		),
	);

	$output = array();
	foreach ( $episodes as $index => $ep ) {
		// Get local image URL if caching enabled.
		$image_url = '';
		if ( ! empty( $ep['image'] ) ) {
			$image_url = Podloom_Image_Cache::get_local_url( $ep['image'], 'cover', $feed_id );
		}

		$output[] = array(
			'id'          => $ep['id'] ?? $index,
			'title'       => sanitize_text_field( $ep['title'] ?? '' ),
			'audio_url'   => esc_url_raw( $ep['audio_url'] ?? '' ),
			'image'       => esc_url_raw( $image_url ),
			'date'        => $ep['date'] ?? 0,
			'duration'    => $ep['duration'] ?? 0,
			'description' => wp_kses( $ep['description'] ?? '', $allowed_html ),
			'podcast20'   => $ep['podcast20'] ?? null,
		);
	}

	return $output;
}
