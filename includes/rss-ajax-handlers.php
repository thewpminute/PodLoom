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

	if ( $result['success'] ) {
		wp_send_json_success(
			array(
				'message' => $result['message'],
				'feed'    => $feed,
			)
		);
	} else {
		wp_send_json_error(
			array(
				'message' => $result['message'],
				'feed'    => $feed,
			)
		);
	}
}
add_action( 'wp_ajax_podloom_refresh_rss_feed', 'podloom_ajax_refresh_rss_feed' );

/**
 * AJAX Handler: Get RSS Episodes
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

	$feed_id  = isset( $_POST['feed_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feed_id'] ) ) : '';
	$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
	$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 20;

	if ( empty( $feed_id ) ) {
		wp_send_json_error( array( 'message' => 'Feed ID is required' ) );
		return;
	}

	$result = Podloom_RSS::get_episodes( $feed_id, $page, $per_page );

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_podloom_get_rss_episodes', 'podloom_ajax_get_rss_episodes' );

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
