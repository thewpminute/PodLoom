<?php
/**
 * Plugin Name:  PodLoom - Podcast Player for Transistor.fm & RSS Feeds
 * Plugin URI: https://thewpminute.com/podloom/
 * Description: Connect to your Transistor.fm account and embed podcast episodes using Gutenberg blocks or Elementor. Supports RSS feeds from any podcast platform.
 * Version: 2.16.0
 * Author: WP Minute
 * Author URI: https://thewpminute.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: podloom-podcast-player
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to:         6.8
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'PODLOOM_PLUGIN_VERSION', '2.16.0' );
define( 'PODLOOM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PODLOOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PODLOOM_PLUGIN_FILE', __FILE__ );

// Use minified assets unless SCRIPT_DEBUG is enabled.
define( 'PODLOOM_SCRIPT_SUFFIX', defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' );

/**
 * Plugin activation hook - sets default option values.
 *
 * Uses add_option() which only creates the option if it doesn't exist,
 * preserving any existing user settings.
 *
 * @since 2.12.1
 */
function podloom_activate() {
	// RSS display settings default to true (show all elements).
	$rss_display_defaults = array(
		'podloom_rss_display_artwork'       => true,
		'podloom_rss_display_title'         => true,
		'podloom_rss_display_date'          => true,
		'podloom_rss_display_duration'      => true,
		'podloom_rss_display_description'   => true,
		'podloom_rss_display_skip_buttons'  => true,
		'podloom_rss_display_funding'       => true,
		'podloom_rss_display_transcripts'   => true,
		'podloom_rss_display_people_hosts'  => true,
		'podloom_rss_display_people_guests' => true,
		'podloom_rss_display_chapters'      => true,
	);

	foreach ( $rss_display_defaults as $option_name => $default_value ) {
		add_option( $option_name, $default_value );
	}
}
register_activation_hook( __FILE__, 'podloom_activate' );

// Global flag to track if P2.0 content is used on this page
global $podloom_has_podcast20_content;
$podloom_has_podcast20_content = false;

// Include shared utilities.
require_once PODLOOM_PLUGIN_DIR . 'includes/cache.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/color-utils.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/utilities.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/class-podloom-image-cache.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/class-podloom-podcast20-parser.php';

// Include core modules.
require_once PODLOOM_PLUGIN_DIR . 'includes/utilities/class-podloom-typography.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/core/class-podloom-migration.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/core/class-podloom-assets.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/core/class-podloom-blocks.php';

// Include rendering modules.
require_once PODLOOM_PLUGIN_DIR . 'includes/rendering/class-podloom-p20-render.php';

// Include Transistor.fm integration.
require_once PODLOOM_PLUGIN_DIR . 'includes/transistor/class-podloom-transistor-api.php';

// Include RSS feed integration.
require_once PODLOOM_PLUGIN_DIR . 'includes/rss/class-podloom-rss.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss/class-podloom-rss-cron.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss/class-podloom-rss-ajax.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss/class-podloom-rss-storage.php';

// Create persistent storage table if needed (handles fresh installs and upgrades).
add_action(
	'admin_init',
	function () {
		$db_version = get_option( 'podloom_db_version', '0' );
		if ( version_compare( $db_version, Podloom_RSS_Storage::DB_VERSION, '<' ) ) {
			Podloom_RSS_Storage::create_table();
		}
	}
);

// Include Elementor integration (loads conditionally when Elementor is active).
require_once PODLOOM_PLUGIN_DIR . 'includes/elementor/class-podloom-elementor.php';

// Include subscribe buttons feature.
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe/class-podloom-subscribe-icons.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe/class-podloom-subscribe.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe/class-podloom-subscribe-render.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe-ajax-handlers.php';

// Include admin functions.
require_once PODLOOM_PLUGIN_DIR . 'admin/admin-functions.php';

// Migration, block registration, and asset enqueuing are now handled by:
// - Podloom_Migration (includes/core/class-podloom-migration.php)
// - Podloom_Blocks (includes/core/class-podloom-blocks.php)
// - Podloom_Assets (includes/core/class-podloom-assets.php)

/**
 * AJAX handler to proxy transcript requests (bypasses CORS)
 *
 * This endpoint is intentionally available to unauthenticated users (wp_ajax_nopriv)
 * because transcripts must load for all site visitors, not just logged-in users.
 *
 * Security measures in place:
 * - Rate limiting: 15 requests/minute per IP (configurable via filter)
 * - SSRF protection: WordPress core's reject_unsafe_urls blocks internal IPs
 * - URL validation: Only http/https URLs allowed
 * - Size limits: 2MB max transcript size (configurable via filter)
 *
 * Nonce verification is not used because:
 * 1. This is a read-only endpoint (no data modification)
 * 2. Anonymous users have no session to protect via CSRF
 * 3. Rate limiting prevents abuse
 *
 * @since 2.5.0
 */
function podloom_fetch_transcript() {
	// Rate limiting: 15 requests/minute per IP (filterable for advanced users)
	$rate_limit  = apply_filters( 'podloom_transcript_rate_limit', 15 ); // requests per minute
	$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$rate_key    = 'podloom_transcript_rate_' . ( is_user_logged_in() ? get_current_user_id() : md5( $remote_addr ) );
	$rate_count  = get_transient( $rate_key );

	if ( $rate_limit > 0 && $rate_count && $rate_count >= $rate_limit ) {
		wp_send_json_error( array( 'message' => 'Rate limit exceeded. Please wait a minute.' ), 429 );
	}

	// Verify the URL parameter exists.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public CORS proxy endpoint, rate-limited.
	if ( ! isset( $_GET['url'] ) ) {
		wp_send_json_error( array( 'message' => 'No URL provided' ), 400 );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public CORS proxy endpoint, rate-limited.
	$url = sanitize_text_field( wp_unslash( $_GET['url'] ) );

	// Basic URL validation
	if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		wp_send_json_error( array( 'message' => 'Invalid URL' ), 400 );
	}

	// Allow users to add custom validation via filter
	$validation_error = apply_filters( 'podloom_transcript_validate_url', null, $url );
	if ( $validation_error ) {
		wp_send_json_error( array( 'message' => $validation_error ), 403 );
	}

	// Fetch the transcript using WordPress HTTP API
	// WordPress's 'reject_unsafe_urls' handles SSRF protection (blocks private IPs, localhost, etc.)
	$response = wp_remote_get(
		$url,
		apply_filters(
			'podloom_transcript_request_args',
			array(
				'timeout'            => 15,
				'sslverify'          => true,
				'redirection'        => 2,
				'reject_unsafe_urls' => true,
				'user-agent'         => 'PodLoom/' . PODLOOM_PLUGIN_VERSION . '; ' . get_bloginfo( 'url' ),
			),
			$url
		)
	);

	// Check for errors
	if ( is_wp_error( $response ) ) {
		wp_send_json_error(
			array(
				'message' => 'Failed to fetch transcript',
				'error'   => $response->get_error_message(),
			),
			500
		);
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = wp_remote_retrieve_body( $response );

	if ( $status_code !== 200 ) {
		wp_send_json_error(
			array(
				'message' => 'Server returned error',
				'status'  => $status_code,
			),
			$status_code
		);
	}

	// Check transcript size limit to prevent memory exhaustion
	// Default: 2MB (configurable via filter for sites with larger transcripts)
	$max_size  = apply_filters( 'podloom_transcript_max_size', 2 * 1024 * 1024 ); // 2MB default
	$body_size = strlen( $body );

	if ( $max_size > 0 && $body_size > $max_size ) {
		wp_send_json_error(
			array(
				'message' => 'Transcript too large',
				'size'    => size_format( $body_size ),
				'limit'   => size_format( $max_size ),
			),
			413
		); // 413 Payload Too Large
	}

	// Optional content-type validation (disabled by default to support all servers)
	// Users can enable strict validation via filter if needed for their environment
	$strict_content_type = apply_filters( 'podloom_transcript_strict_content_type', false );
	if ( $strict_content_type ) {
		$content_type  = wp_remote_retrieve_header( $response, 'content-type' );
		$allowed_types = apply_filters(
			'podloom_transcript_allowed_content_types',
			array(
				'text/plain',
				'text/html',
				'application/json',
				'text/vtt',
				'application/x-subrip',
				'text/srt',
				'application/srt',
			)
		);

		$type_allowed = false;
		foreach ( $allowed_types as $allowed_type ) {
			if ( stripos( $content_type, $allowed_type ) !== false ) {
				$type_allowed = true;
				break;
			}
		}

		if ( ! $type_allowed && ! empty( $content_type ) ) {
			wp_send_json_error( array( 'message' => 'Invalid content type: ' . esc_html( $content_type ) ), 415 );
		}
	}

	// Update rate limit counter
	if ( $rate_limit > 0 ) {
		set_transient( $rate_key, ( $rate_count ? $rate_count : 0 ) + 1, MINUTE_IN_SECONDS );
	}

	// Return the transcript content
	wp_send_json_success(
		array(
			'content'      => $body,
			'content_type' => $content_type,
		)
	);
}
add_action( 'wp_ajax_podloom_fetch_transcript', 'podloom_fetch_transcript' );
add_action( 'wp_ajax_nopriv_podloom_fetch_transcript', 'podloom_fetch_transcript' );


/**
 * Render callback for the block (frontend display)
 */
function podloom_render_block( $attributes ) {
	// Get source type
	$source_type = isset( $attributes['sourceType'] ) ? $attributes['sourceType'] : 'transistor';

	// Validate display mode
	$display_mode = isset( $attributes['displayMode'] ) && in_array( $attributes['displayMode'], array( 'specific', 'latest', 'playlist' ), true )
		? $attributes['displayMode']
		: 'specific';

	// Handle RSS episodes
	if ( $source_type === 'rss' ) {
		$feed_id = isset( $attributes['rssFeedId'] ) ? $attributes['rssFeedId'] : '';
		if ( empty( $feed_id ) ) {
			return '';
		}

		// Verify feed still exists
		$feed = Podloom_RSS::get_feed( $feed_id );
		if ( ! $feed ) {
			// Feed was deleted - show user-friendly message
			return '<div class="wp-block-podloom-episode-player" style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">' .
					'<p style="margin: 0; color: #856404;"><strong>' . esc_html__( 'RSS Feed Not Found', 'podloom-podcast-player' ) . '</strong></p>' .
					'<p style="margin: 5px 0 0 0; color: #856404; font-size: 14px;">' . esc_html__( 'The RSS feed used by this block has been removed. Please select a different feed or remove this block.', 'podloom-podcast-player' ) . '</p>' .
					'</div>';
		}

		if ( $display_mode === 'playlist' ) {
			// Get max episodes from attributes (default 25)
			$max_episodes = isset( $attributes['playlistMaxEpisodes'] ) ? absint( $attributes['playlistMaxEpisodes'] ) : 25;
			$max_episodes = max( 5, min( 100, $max_episodes ) ); // Clamp between 5-100

			return podloom_render_rss_playlist( $feed_id, $max_episodes, $attributes );
		} elseif ( $display_mode === 'latest' ) {
			$latest_episode = Podloom_RSS::get_latest_episode( $feed_id );
			if ( ! $latest_episode ) {
				// Cache is cold - a background refresh has been scheduled
				// Show a minimal placeholder that doesn't break the page layout
				return '<div class="wp-block-podloom-episode-player podloom-loading" style="padding: 20px; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; text-align: center;">' .
						'<p style="margin: 0; color: #6c757d;">' . esc_html__( 'Loading podcast episode...', 'podloom-podcast-player' ) . '</p>' .
						'</div>';
			}

			// Create temporary attributes with the latest episode data
			$rss_attributes                   = $attributes;
			$rss_attributes['rssEpisodeData'] = $latest_episode;

			return podloom_render_rss_episode( $rss_attributes );
		} else {
			return podloom_render_rss_episode( $attributes );
		}
	}

	// Handle "latest episode" mode (Transistor only)
	if ( $display_mode === 'latest' ) {
		if ( empty( $attributes['showSlug'] ) ) {
			return '';
		}

		// Validate and sanitize
		$show_slug  = sanitize_title( $attributes['showSlug'] );
		$theme      = isset( $attributes['theme'] ) && $attributes['theme'] === 'dark' ? 'dark' : 'light';
		$theme_path = $theme === 'dark' ? 'latest/dark' : 'latest';

		// Get show title for accessible iframe title
		$show_title = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : '';

		// Construct iframe for latest episode
		$iframe = sprintf(
			'<iframe width="100%%" height="180" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/%s/%s" title="%s"></iframe>',
			esc_attr( $show_slug ),
			esc_attr( $theme_path ),
			esc_attr( $show_title ? $show_title . ' - ' . __( 'Latest Episode Player', 'podloom-podcast-player' ) : __( 'Latest Episode Player', 'podloom-podcast-player' ) )
		);

		return '<div class="wp-block-podloom-episode-player">' . $iframe . '</div>';
	}

	// Handle "playlist" mode (Transistor only)
	if ( $display_mode === 'playlist' ) {
		if ( empty( $attributes['showSlug'] ) ) {
			return '';
		}

		// Validate and sanitize
		$show_slug  = sanitize_title( $attributes['showSlug'] );
		$theme      = isset( $attributes['theme'] ) && $attributes['theme'] === 'dark' ? 'dark' : 'light';
		$theme_path = $theme === 'dark' ? 'playlist/dark' : 'playlist';

		// Validate and sanitize playlist height (min: 200, max: 1000, default: 390)
		$playlist_height = isset( $attributes['playlistHeight'] ) ? absint( $attributes['playlistHeight'] ) : 390;
		if ( $playlist_height < 200 ) {
			$playlist_height = 200;
		} elseif ( $playlist_height > 1000 ) {
			$playlist_height = 1000;
		}

		// Get show title for accessible iframe title
		$show_title = isset( $attributes['showTitle'] ) ? $attributes['showTitle'] : '';

		// Construct iframe for playlist
		$iframe = sprintf(
			'<iframe width="100%%" height="%d" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/%s/%s" title="%s"></iframe>',
			$playlist_height,
			esc_attr( $show_slug ),
			esc_attr( $theme_path ),
			esc_attr( $show_title ? $show_title . ' - ' . __( 'Podcast Playlist Player', 'podloom-podcast-player' ) : __( 'Podcast Playlist Player', 'podloom-podcast-player' ) )
		);

		return '<div class="wp-block-podloom-episode-player">' . $iframe . '</div>';
	}

	// Handle specific episode mode (Transistor)
	if ( empty( $attributes['embedHtml'] ) ) {
		return '';
	}

	// Only allow iframe tags with specific attributes for security
	$allowed_html = array(
		'iframe' => array(
			'width'       => true,
			'height'      => true,
			'frameborder' => true,
			'scrolling'   => true,
			'seamless'    => true,
			'src'         => true,
			'title'       => true,
			'loading'     => true,
		),
	);

	$safe_embed = wp_kses( $attributes['embedHtml'], $allowed_html );

	return '<div class="wp-block-podloom-episode-player">' . $safe_embed . '</div>';
}

/**
 * Render callback for the subscribe buttons block (frontend display)
 *
 * @param array $attributes Block attributes.
 * @return string HTML output.
 */
function podloom_render_subscribe_block( $attributes ) {
	$source_id = isset( $attributes['source'] ) ? sanitize_text_field( $attributes['source'] ) : '';

	if ( empty( $source_id ) ) {
		return '';
	}

	// Sanitize attributes before passing to render.
	$sanitized_attributes = array(
		'source'          => $source_id,
		'iconSize'        => isset( $attributes['iconSize'] ) ? absint( $attributes['iconSize'] ) : 32,
		'colorMode'       => isset( $attributes['colorMode'] ) ? sanitize_key( $attributes['colorMode'] ) : 'brand',
		'layout'          => isset( $attributes['layout'] ) ? sanitize_key( $attributes['layout'] ) : 'horizontal',
		'showLabels'      => isset( $attributes['showLabels'] ) ? (bool) $attributes['showLabels'] : false,
		'customColor'     => isset( $attributes['customColor'] ) ? sanitize_hex_color( $attributes['customColor'] ) : '',
		'iconGap'         => isset( $attributes['iconGap'] ) ? absint( $attributes['iconGap'] ) : 12,
		'labelFontSize'   => isset( $attributes['labelFontSize'] ) ? absint( $attributes['labelFontSize'] ) : 14,
		'labelFontFamily' => isset( $attributes['labelFontFamily'] ) ? sanitize_text_field( $attributes['labelFontFamily'] ) : 'inherit',
	);

	// Enqueue frontend styles.
	wp_enqueue_style( 'podloom-subscribe-buttons' );

	return Podloom_Subscribe_Render::render_block( $sanitized_attributes );
}


/**
 * Render RSS episode player
 *
 * When minimal styling mode is disabled (default):
 * - Outputs semantic HTML with inline CSS styles
 * - Applies typography settings from plugin options
 *
 * When minimal styling mode is enabled:
 * - Outputs only semantic HTML with classes
 * - No inline styles or typography settings applied
 * - Users can apply their own CSS using these classes:
 *   .wp-block-podloom-episode-player.rss-episode-player (container)
 *   .rss-episode-artwork (artwork wrapper)
 *   .rss-episode-content (content wrapper)
 *   .rss-episode-title (title heading)
 *   .rss-episode-meta (date/duration container)
 *   .rss-episode-date (date span)
 *   .rss-episode-duration (duration span)
 *   .rss-episode-audio (audio element)
 *   .rss-episode-audio.rss-audio-last (audio when last element)
 *   .rss-episode-description (description div)
 */

/**
 * Render custom audio player HTML
 *
 * Creates a custom player UI with play/pause, timeline, skip buttons, speed control, and time display.
 * The native audio element is hidden and controlled via JavaScript.
 *
 * @param string $audio_url   URL of the audio file.
 * @param string $audio_type  MIME type of the audio file.
 * @param string $extra_class Additional classes for the audio element.
 * @param bool   $is_playlist Whether this is a playlist player (adds playlist-specific classes).
 * @param array  $colors      Theme colors array from podloom_calculate_theme_colors().
 * @return string HTML output.
 */
function podloom_render_custom_player( $audio_url, $audio_type = 'audio/mpeg', $extra_class = '', $is_playlist = false, $colors = array() ) {
	$audio_class = 'podloom-audio-element' . ( $is_playlist ? ' podloom-playlist-audio' : '' ) . ( $extra_class ? ' ' . $extra_class : '' );

	// Build CSS custom properties from colors array
	// Note: Colors come from podloom_calculate_theme_colors() which uses sanitize_hex_color()
	// and our own color manipulation functions, so values are safe CSS color values.
	$css_vars = array();
	$color_keys = array(
		'player_btn',
		'player_btn_bg',
		'player_btn_icon',
		'player_timeline',
		'player_progress',
		'player_control',
		'player_control_hover_bg',
		'player_time',
		'player_speed_bg',
		'player_speed_border',
		'player_speed_hover_bg',
		'player_speed_hover_border',
		'player_speed_active_bg',
		'player_text',
	);

	foreach ( $color_keys as $key ) {
		if ( ! empty( $colors[ $key ] ) ) {
			// Convert underscores to hyphens for CSS variable names
			$css_var_name = '--podloom-' . str_replace( '_', '-', $key );
			$css_vars[]   = $css_var_name . ': ' . $colors[ $key ];
		}
	}

	$container_style = ! empty( $css_vars ) ? esc_attr( implode( '; ', $css_vars ) ) : '';

	$output = '<div class="podloom-player-container"' . ( $container_style ? ' style="' . $container_style . '"' : '' ) . '>';

	// Hidden audio element (the engine)
	$output .= sprintf(
		'<audio class="%s" preload="metadata"><source src="%s" type="%s">%s</audio>',
		esc_attr( $audio_class ),
		esc_url( $audio_url ),
		esc_attr( $audio_type ),
		esc_html__( 'Your browser does not support the audio player.', 'podloom-podcast-player' )
	);

	$output .= '<div class="podloom-player-main">';

	// Play/Pause button
	// Circle uses currentColor (set via CSS), icon fill is controlled by CSS variable
	$output .= sprintf(
		'<button type="button" class="podloom-play-toggle" aria-label="%s" data-play-label="%s" data-pause-label="%s">
			<svg class="podloom-icon-play" width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<circle cx="24" cy="24" r="24" fill="currentColor"/>
				<path d="M32 24L18 33V15L32 24Z"/>
			</svg>
			<svg class="podloom-icon-pause" width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<circle cx="24" cy="24" r="24" fill="currentColor"/>
				<rect x="17" y="14" width="5" height="20" rx="1"/>
				<rect x="26" y="14" width="5" height="20" rx="1"/>
			</svg>
		</button>',
		esc_attr__( 'Play', 'podloom-podcast-player' ),
		esc_attr__( 'Play', 'podloom-podcast-player' ),
		esc_attr__( 'Pause', 'podloom-podcast-player' )
	);

	$output .= '<div class="podloom-player-content">';

	// Timeline
	$output .= '<div class="podloom-timeline-container">';
	$output .= '<div class="podloom-timeline-progress"></div>';
	$output .= sprintf(
		'<input type="range" class="podloom-timeline-slider" min="0" max="100" value="0" step="0.1" aria-label="%s">',
		esc_attr__( 'Seek', 'podloom-podcast-player' )
	);
	$output .= '</div>';

	// Controls row
	$output .= '<div class="podloom-controls-row">';

	// Secondary controls (skip buttons + speed)
	$output .= '<div class="podloom-secondary-controls">';

	// Skip back 10s
	$output .= sprintf(
		'<button type="button" class="podloom-control-btn podloom-skip-btn" data-skip="-10" aria-label="%s">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
				<path d="M3 3v5h5"/>
			</svg>
			<span class="podloom-skip-label">10</span>
		</button>',
		esc_attr__( 'Rewind 10 seconds', 'podloom-podcast-player' )
	);

	// Speed toggle
	$output .= sprintf(
		'<button type="button" class="podloom-speed-btn" aria-label="%s">1x</button>',
		esc_attr__( 'Playback speed', 'podloom-podcast-player' )
	);

	// Skip forward 30s
	$output .= sprintf(
		'<button type="button" class="podloom-control-btn podloom-skip-btn" data-skip="30" aria-label="%s">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
				<path d="M21 3v5h-5"/>
			</svg>
			<span class="podloom-skip-label">30</span>
		</button>',
		esc_attr__( 'Forward 30 seconds', 'podloom-podcast-player' )
	);

	$output .= '</div>'; // .podloom-secondary-controls

	// Time display
	$output .= '<div class="podloom-time-display">';
	$output .= '<span class="podloom-current-time">0:00</span>';
	$output .= '<span class="podloom-time-separator">/</span>';
	$output .= '<span class="podloom-duration">0:00</span>';
	$output .= '</div>';

	$output .= '</div>'; // .podloom-controls-row
	$output .= '</div>'; // .podloom-player-content
	$output .= '</div>'; // .podloom-player-main
	$output .= '</div>'; // .podloom-player-container

	return $output;
}

/**
 * Render RSS playlist player
 *
 * Displays a player with the first episode and an Episodes tab listing all episodes.
 * Clicking an episode updates the player and tabs dynamically via JavaScript.
 *
 * @param string $feed_id      RSS feed ID.
 * @param int    $max_episodes Maximum number of episodes to display.
 * @param array  $attributes   Block attributes.
 * @return string HTML output.
 */
function podloom_render_rss_playlist( $feed_id, $max_episodes, $attributes ) {
	// Pagination configuration.
	$initial_load = 20; // Episodes to render on page load.
	$load_step    = 20; // Episodes to load per AJAX request.

	// Fetch episodes from the feed.
	$episodes_data = Podloom_RSS::get_episodes( $feed_id, 1, $max_episodes, true );

	if ( empty( $episodes_data['episodes'] ) ) {
		return '<div class="wp-block-podloom-episode-player podloom-loading" style="padding: 20px; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; text-align: center;">' .
				'<p style="margin: 0; color: #6c757d;">' . esc_html__( 'Loading podcast episodes...', 'podloom-podcast-player' ) . '</p>' .
				'</div>';
	}

	$all_episodes   = $episodes_data['episodes'];
	$total_episodes = count( $all_episodes );

	// Sort episodes based on playlistOrder attribute.
	// 'episodic' = newest first (default RSS order), 'serial' = oldest first (reversed).
	$playlist_order = isset( $attributes['playlistOrder'] ) ? $attributes['playlistOrder'] : 'episodic';
	if ( 'serial' === $playlist_order && $total_episodes > 1 ) {
		$all_episodes = array_reverse( $all_episodes );
	}

	// Get only initial batch for rendering.
	$initial_episodes = array_slice( $all_episodes, 0, $initial_load );

	// Use first episode as the current/active episode.
	$current_episode = $initial_episodes[0];

	// Set global flag to indicate P2.0 content is used.
	global $podloom_has_podcast20_content;
	$podloom_has_podcast20_content = true;

	// Get display settings.
	$show_artwork      = get_option( 'podloom_rss_display_artwork', true );
	$show_title        = get_option( 'podloom_rss_display_title', true );
	$show_date         = get_option( 'podloom_rss_display_date', true );
	$show_duration     = get_option( 'podloom_rss_display_duration', true );
	$show_description  = get_option( 'podloom_rss_display_description', true );
	$show_skip_buttons = get_option( 'podloom_rss_display_skip_buttons', true );

	// Get background color for color calculations.
	$bg_color = get_option( 'podloom_rss_background_color', '#f9f9f9' );
	$colors   = podloom_calculate_theme_colors( $bg_color );

	// Generate unique player ID for this playlist instance.
	$player_id = 'podloom-playlist-' . wp_unique_id();

	// Build data attributes for JavaScript pagination.
	$data_attrs = sprintf(
		'data-feed-id="%s" data-total="%d" data-loaded="%d" data-step="%d" data-order="%s"',
		esc_attr( $feed_id ),
		$total_episodes,
		count( $initial_episodes ),
		$load_step,
		esc_attr( $playlist_order )
	);

	// Start building output.
	$output = '<div id="' . esc_attr( $player_id ) . '" class="wp-block-podloom-episode-player rss-episode-player rss-playlist-player" ' . $data_attrs . '>';

	// Get funding button HTML (if available)
	$funding_button = '';
	if ( ! empty( $current_episode['podcast20'] ) ) {
		$funding_button = podloom_get_funding_button( $current_episode['podcast20'] );
	}

	// Mobile funding button
	if ( ! empty( $funding_button ) ) {
		$output .= '<div class="rss-funding-mobile">' . $funding_button . '</div>';
	}

	// Episode wrapper with data attributes for JS
	$output .= '<div class="rss-episode-wrapper">';

	// Artwork column
	if ( $show_artwork && ! empty( $current_episode['image'] ) ) {
		// Get responsive image with srcset if caching enabled, otherwise simple img.
		$artwork_img = Podloom_Image_Cache::get_responsive_img(
			$current_episode['image'],
			$current_episode['title'],
			'podloom-playlist-artwork',
			'medium_large', // Use medium_large (768px) as base - suitable for episode artwork.
			'(max-width: 480px) 70px, (max-width: 768px) 100px, 200px'
		);

		$output .= '<div class="rss-episode-artwork-column">';
		$output .= '<div class="rss-episode-artwork">' . $artwork_img . '</div>';

		// Desktop funding button
		if ( ! empty( $funding_button ) ) {
			$output .= '<div class="rss-funding-desktop">' . $funding_button . '</div>';
		}

		$output .= '</div>'; // .rss-episode-artwork-column
	}

	// Content container
	$output .= '<div class="rss-episode-content">';

	// Episode title
	if ( $show_title && ! empty( $current_episode['title'] ) ) {
		$output .= sprintf(
			'<h3 class="rss-episode-title podloom-playlist-title">%s</h3>',
			esc_html( $current_episode['title'] )
		);
	}

	// Episode meta
	if ( ( $show_date && ! empty( $current_episode['date'] ) ) || ( $show_duration && ! empty( $current_episode['duration'] ) ) ) {
		$output .= '<div class="rss-episode-meta">';

		if ( $show_date && ! empty( $current_episode['date'] ) ) {
			$date    = date_i18n( get_option( 'date_format' ), $current_episode['date'] );
			$output .= sprintf( '<span class="rss-episode-date podloom-playlist-date">%s</span>', esc_html( $date ) );
		}

		if ( $show_duration && ! empty( $current_episode['duration'] ) ) {
			$duration = podloom_format_duration( $current_episode['duration'] );
			if ( $duration ) {
				$output .= sprintf( '<span class="rss-episode-duration podloom-playlist-duration">%s</span>', esc_html( $duration ) );
			}
		}

		$output .= '</div>';
	}

	// Custom audio player
	if ( ! empty( $current_episode['audio_url'] ) ) {
		$output .= podloom_render_custom_player(
			$current_episode['audio_url'],
			! empty( $current_episode['audio_type'] ) ? $current_episode['audio_type'] : 'audio/mpeg',
			'rss-episode-audio',
			true, // is_playlist
			$colors
		);
	}

	$output .= '</div>'; // .rss-episode-content
	$output .= '</div>'; // .rss-episode-wrapper

	// Render tabs with Episodes tab first (pass only initial episodes and total count).
	$output .= podloom_render_playlist_tabs( $initial_episodes, $current_episode, $show_description, $colors, $total_episodes );

	// Store initial episode data as JSON for JavaScript (only initially loaded episodes).
	$episodes_json = podloom_prepare_episodes_json( $initial_episodes, $feed_id );
	$output       .= '<script type="application/json" class="podloom-playlist-data">' . wp_json_encode( $episodes_json ) . '</script>';

	$output .= '</div>'; // .wp-block-podloom-episode-player

	return $output;
}

/**
 * Prepare episodes array for JSON output.
 *
 * @param array  $episodes Episodes array.
 * @param string $feed_id  Feed ID for image caching.
 * @return array Sanitized episodes for JSON.
 */
function podloom_prepare_episodes_json( $episodes, $feed_id ) {
	$allowed_html = array(
		'p'          => array(),
		'br'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'u'          => array(),
		'a'          => array(
			'href'   => array(),
			'title'  => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'blockquote' => array(),
		'code'       => array(),
		'pre'        => array(),
	);

	$output = array();
	foreach ( $episodes as $index => $ep ) {
		// Sanitize description and content to prevent XSS when JavaScript updates the DOM.
		$sanitized_description = ! empty( $ep['description'] ) ? wp_kses( $ep['description'], $allowed_html ) : '';
		$sanitized_content     = ! empty( $ep['content'] ) ? wp_kses( $ep['content'], $allowed_html ) : '';

		// Get local URL if image caching is enabled (returns original URL as fallback).
		$ep_image_url = ! empty( $ep['image'] ) ? Podloom_Image_Cache::get_local_url( $ep['image'], 'cover', $feed_id ) : '';

		$output[] = array(
			'id'          => $ep['id'] ?? $index,
			'title'       => sanitize_text_field( $ep['title'] ?? '' ),
			'audio_url'   => esc_url_raw( $ep['audio_url'] ?? '' ),
			'image'       => esc_url_raw( $ep_image_url ),
			'date'        => $ep['date'] ?? 0,
			'duration'    => $ep['duration'] ?? 0,
			'description' => $sanitized_description,
			'content'     => $sanitized_content,
			'podcast20'   => $ep['podcast20'] ?? null,
		);
	}

	return $output;
}

function podloom_render_rss_episode( $attributes ) {
	// Check if we have RSS episode data
	if ( empty( $attributes['rssEpisodeData'] ) ) {
		return '';
	}

	$episode = $attributes['rssEpisodeData'];
	$feed_id = $attributes['rssFeedId'] ?? '';

	/**
	 * Filter episode data before rendering the player.
	 *
	 * Allows modification of episode data (title, description, audio URL, etc.)
	 * before the player HTML is generated.
	 *
	 * @since 2.5.0
	 * @param array  $episode   Episode data array.
	 * @param string $feed_id   RSS feed ID.
	 * @param array  $attributes Block attributes.
	 */
	$episode = apply_filters( 'podloom_episode_data', $episode, $feed_id, $attributes );

	// Server-side fallback: If podcast20 data is missing (old block), fetch it from cache
	if ( empty( $episode['podcast20'] ) && ! empty( $attributes['rssFeedId'] ) ) {
		$feed_id = $attributes['rssFeedId'];

		// Check if we are in the editor (REST API request)
		// We want to avoid synchronous remote fetches in the editor to prevent slow loading
		$is_editor    = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$allow_remote = ! $is_editor;

		// Get all episodes from the feed cache
		$episodes_data = Podloom_RSS::get_episodes( $feed_id, 1, 100, $allow_remote ); // Get first 100 episodes

		// If we are in editor and got a cache miss error, show a placeholder
		if ( $is_editor && isset( $episodes_data['error'] ) && $episodes_data['error'] === 'cache_miss' ) {
			return '<div class="wp-block-podloom-episode-player" style="padding: 20px; background: #f8f9fa; border: 1px dashed #ccc; text-align: center; color: #666;">' .
					'<p style="margin: 0;"><strong>' . esc_html__( 'Preview Unavailable', 'podloom-podcast-player' ) . '</strong></p>' .
					'<p style="margin: 5px 0 0 0; font-size: 13px;">' . esc_html__( 'Feed data is not currently cached. Please refresh the feed in the settings or view the page on the frontend.', 'podloom-podcast-player' ) . '</p>' .
					'</div>';
		}

		if ( ! empty( $episodes_data['episodes'] ) ) {
			// Try to match the episode by audio_url (most reliable) or title
			foreach ( $episodes_data['episodes'] as $cached_episode ) {
				$match = false;

				// Match by audio URL (most reliable)
				if ( ! empty( $episode['audio_url'] ) && ! empty( $cached_episode['audio_url'] ) ) {
					if ( $episode['audio_url'] === $cached_episode['audio_url'] ) {
						$match = true;
					}
				}

				// Fallback: match by title and date
				if ( ! $match && ! empty( $episode['title'] ) && ! empty( $cached_episode['title'] ) ) {
					if ( $episode['title'] === $cached_episode['title'] ) {
						$match = true;
					}
				}

				if ( $match && ! empty( $cached_episode['podcast20'] ) ) {
					// Merge fresh podcast20 data from cache
					$episode['podcast20'] = $cached_episode['podcast20'];
					break;
				}
			}
		}
	}

	// Character limit is now applied later when preparing description for tabs
	// This preserves HTML formatting while limiting character count

	// Get display settings
	$show_artwork      = get_option( 'podloom_rss_display_artwork', true );
	$show_title        = get_option( 'podloom_rss_display_title', true );
	$show_date         = get_option( 'podloom_rss_display_date', true );
	$show_duration     = get_option( 'podloom_rss_display_duration', true );
	$show_description  = get_option( 'podloom_rss_display_description', true );
	$show_skip_buttons = get_option( 'podloom_rss_display_skip_buttons', true );

	// Get typography styles
	$typo = podloom_get_rss_typography_styles();

	// Get background color and calculate theme colors
	$bg_color = get_option( 'podloom_rss_background_color', '#f9f9f9' );
	$colors   = podloom_calculate_theme_colors( $bg_color );

	// Start building the output
	$output = '<div class="wp-block-podloom-episode-player rss-episode-player">';

	// Get funding button HTML (if available) - we'll place it in different locations for mobile vs desktop
	$funding_button = '';
	if ( ! empty( $episode['podcast20'] ) ) {
		$funding_button = podloom_get_funding_button( $episode['podcast20'] );
	}

	// Mobile funding button (full width, shown only on mobile via CSS)
	if ( ! empty( $funding_button ) ) {
		$output .= '<div class="rss-funding-mobile">';
		$output .= $funding_button;
		$output .= '</div>';
	}

	// Add a wrapper for flexbox layout
	$output .= '<div class="rss-episode-wrapper">';

	// Episode artwork column (includes artwork + funding button on desktop/tablet)
	if ( $show_artwork && ! empty( $episode['image'] ) ) {
		// Get responsive image with srcset if caching enabled, otherwise simple img.
		$artwork_img = Podloom_Image_Cache::get_responsive_img(
			$episode['image'],
			$episode['title'],
			'',
			'medium_large', // Use medium_large (768px) as base - suitable for episode artwork.
			'(max-width: 480px) 70px, (max-width: 768px) 100px, 200px'
		);

		$output .= '<div class="rss-episode-artwork-column">';
		$output .= '<div class="rss-episode-artwork">' . $artwork_img . '</div>';

		// Desktop/tablet funding button (below artwork, hidden on mobile via CSS)
		if ( ! empty( $funding_button ) ) {
			$output .= '<div class="rss-funding-desktop">';
			$output .= $funding_button;
			$output .= '</div>';
		}

		$output .= '</div>'; // .rss-episode-artwork-column
	}

	// Episode content container
	$output .= '<div class="rss-episode-content">';

	// Episode title
	if ( $show_title && ! empty( $episode['title'] ) ) {
		$output .= sprintf(
			'<h3 class="rss-episode-title">%s</h3>',
			esc_html( $episode['title'] )
		);
	}

	// Episode meta (date and duration)
	if ( ( $show_date && ! empty( $episode['date'] ) ) || ( $show_duration && ! empty( $episode['duration'] ) ) ) {
		$output .= '<div class="rss-episode-meta">';

		if ( $show_date && ! empty( $episode['date'] ) ) {
			$date    = date_i18n( get_option( 'date_format' ), $episode['date'] );
			$output .= sprintf(
				'<span class="rss-episode-date">%s</span>',
				esc_html( $date )
			);
		}

		if ( $show_duration && ! empty( $episode['duration'] ) ) {
			$duration = podloom_format_duration( $episode['duration'] );
			if ( $duration ) {
				$output .= sprintf(
					'<span class="rss-episode-duration">%s</span>',
					esc_html( $duration )
				);
			}
		}

		$output .= '</div>';
	}

	// Custom audio player
	if ( ! empty( $episode['audio_url'] ) ) {
		// Add class if description is hidden to remove bottom margin
		$has_description = ! empty( $episode['content'] ) || ! empty( $episode['description'] );
		$extra_class     = ( $show_description && $has_description ) ? 'rss-episode-audio' : 'rss-episode-audio rss-audio-last';

		$output .= podloom_render_custom_player(
			$episode['audio_url'],
			! empty( $episode['audio_type'] ) ? $episode['audio_type'] : 'audio/mpeg',
			$extra_class,
			false, // not a playlist
			$colors
		);
	}

	$output .= '</div>'; // .rss-episode-content
	$output .= '</div>'; // .rss-episode-wrapper

	// Prepare description for tabs (if enabled)
	$description_html = '';
	// Prefer 'content' over 'description' as SimplePie's get_description() truncates by default
	$description_source = ! empty( $episode['content'] ) ? $episode['content'] : ( $episode['description'] ?? '' );
	if ( $show_description && ! empty( $description_source ) ) {
		// Use restrictive HTML sanitization to prevent XSS from untrusted RSS feeds
		$allowed_html     = array(
			'p'          => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			'a'          => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
		);
		$description_html = wp_kses( $description_source, $allowed_html );

		// Additional security: validate href attributes to prevent javascript: and data: URLs
		if ( ! empty( $description_html ) && strpos( $description_html, '<a ' ) !== false ) {
			$dom = new DOMDocument();
			libxml_use_internal_errors( true );
			@$dom->loadHTML( '<?xml encoding="UTF-8">' . $description_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			libxml_clear_errors();

			$links = $dom->getElementsByTagName( 'a' );
			foreach ( $links as $link ) {
				$href = $link->getAttribute( 'href' );
				if ( $href ) {
					$href_lower = strtolower( trim( $href ) );
					// Remove dangerous URL schemes
					if ( strpos( $href_lower, 'javascript:' ) === 0 ||
						strpos( $href_lower, 'data:' ) === 0 ||
						strpos( $href_lower, 'vbscript:' ) === 0 ) {
						$link->removeAttribute( 'href' );
					}
				}
				// Ensure external links have proper rel attribute for security
				if ( $link->hasAttribute( 'href' ) && $link->getAttribute( 'target' ) === '_blank' ) {
					$link->setAttribute( 'rel', 'noopener noreferrer' );
				}
			}

			$description_html = '';
			foreach ( $dom->childNodes as $child ) {
				$description_html .= $dom->saveHTML( $child );
			}
		}

		// Apply character limit if set (while preserving HTML)
		$char_limit = get_option( 'podloom_rss_description_limit', 0 );
		if ( $char_limit > 0 ) {
			$description_html = podloom_truncate_html( $description_html, $char_limit );
		}
	}

	// Render Podcasting 2.0 tabs (after player, outside content wrapper)
	// Pass description to be included as first tab if enabled
	if ( ! empty( $episode['podcast20'] ) || ! empty( $description_html ) ) {
		// Set global flag to indicate P2.0 content is used
		global $podloom_has_podcast20_content;
		$podloom_has_podcast20_content = true;

		$output .= podloom_render_podcast20_tabs(
			$episode['podcast20'] ?? array(),
			$description_html,
			$show_description
		);
	}

	$output .= '</div>'; // .wp-block-podloom-episode-player

	/**
	 * Filter the final player HTML output.
	 *
	 * Allows modification of the complete player HTML before it's rendered.
	 * Useful for adding wrapper elements, custom attributes, or post-processing.
	 *
	 * @since 2.5.0
	 * @param string $output     The complete player HTML.
	 * @param array  $episode    Episode data array.
	 * @param string $feed_id    RSS feed ID.
	 * @param array  $attributes Block attributes.
	 */
	$output = apply_filters( 'podloom_player_html', $output, $episode, $feed_id, $attributes );

	// Styles are now enqueued via wp_add_inline_style() in podloom_enqueue_rss_styles()
	return $output;
}

/**
 * Add admin menu
 */
function podloom_add_admin_menu() {
	add_options_page(
		__( 'PodLoom Settings', 'podloom-podcast-player' ),
		__( 'PodLoom Settings', 'podloom-podcast-player' ),
		'manage_options',
		'podloom-settings',
		'podloom_render_settings_page'
	);
}
add_action( 'admin_menu', 'podloom_add_admin_menu' );

/**
 * Add settings link on plugin page
 */
function podloom_add_plugin_action_links( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=podloom-settings' ) . '">' . __( 'Settings', 'podloom-podcast-player' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'podloom_add_plugin_action_links' );

/**
 * Register plugin settings
 */
function podloom_register_settings() {
	register_setting(
		'podloom_settings',
		'podloom_api_key',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_default_show',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_enable_cache',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_cache_duration',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 21600,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_enabled',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_feeds',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'podloom_sanitize_rss_feeds',
			'default'           => array(),
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_artwork',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_title',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_date',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_duration',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_description',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_skip_buttons',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	// Podcasting 2.0 element display settings
	register_setting(
		'podloom_settings',
		'podloom_rss_display_funding',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_transcripts',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_people_hosts',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_people_guests',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	register_setting(
		'podloom_settings',
		'podloom_rss_display_chapters',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		)
	);

	// Typography settings for RSS title
	register_setting(
		'podloom_settings',
		'podloom_rss_title_font_family',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'inherit',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_title_font_size',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '24px',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_title_line_height',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1.3',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_title_color',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#000000',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_title_font_weight',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '600',
		)
	);

	// Typography settings for RSS date
	register_setting(
		'podloom_settings',
		'podloom_rss_date_font_family',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'inherit',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_date_font_size',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '14px',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_date_line_height',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1.5',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_date_color',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#666666',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_date_font_weight',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'normal',
		)
	);

	// Typography settings for RSS duration
	register_setting(
		'podloom_settings',
		'podloom_rss_duration_font_family',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'inherit',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_duration_font_size',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '14px',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_duration_line_height',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1.5',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_duration_color',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#666666',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_duration_font_weight',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'normal',
		)
	);

	// Typography settings for RSS description
	register_setting(
		'podloom_settings',
		'podloom_rss_description_font_family',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'inherit',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_description_font_size',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '16px',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_description_line_height',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1.6',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_description_color',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#333333',
		)
	);
	register_setting(
		'podloom_settings',
		'podloom_rss_description_font_weight',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'normal',
		)
	);

	// Background color for RSS block
	register_setting(
		'podloom_settings',
		'podloom_rss_background_color',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#f9f9f9',
		)
	);

	// Accent color for RSS block (optional override)
	register_setting(
		'podloom_settings',
		'podloom_rss_accent_color',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '',
		)
	);

	// Minimal styling mode
	register_setting(
		'podloom_settings',
		'podloom_rss_minimal_styling',
		array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		)
	);

	// Description character limit
	register_setting(
		'podloom_settings',
		'podloom_rss_description_limit',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		)
	);

	// Player height
	register_setting(
		'podloom_settings',
		'podloom_rss_player_height',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 600,
		)
	);

	// RSS Cache Duration removed - now uses podloom_cache_duration from General settings
}
add_action( 'admin_init', 'podloom_register_settings' );

/**
 * Sanitize RSS feeds array
 *
 * @param mixed $feeds Raw feeds data
 * @return array Sanitized feeds array
 */
function podloom_sanitize_rss_feeds( $feeds ) {
	if ( ! is_array( $feeds ) ) {
		return array();
	}

	$sanitized = array();
	foreach ( $feeds as $feed_id => $feed ) {
		// Validate feed ID format (must start with rss_)
		if ( ! is_string( $feed_id ) || strpos( $feed_id, 'rss_' ) !== 0 ) {
			continue;
		}

		// Sanitize feed ID
		$safe_id = sanitize_key( $feed_id );

		// Ensure feed is an array with required fields
		if ( ! is_array( $feed ) ) {
			continue;
		}

		$sanitized[ $safe_id ] = array(
			'id'            => $safe_id,
			'name'          => isset( $feed['name'] ) ? sanitize_text_field( $feed['name'] ) : '',
			'url'           => isset( $feed['url'] ) ? esc_url_raw( $feed['url'] ) : '',
			'created'       => isset( $feed['created'] ) ? absint( $feed['created'] ) : time(),
			'last_checked'  => isset( $feed['last_checked'] ) ? absint( $feed['last_checked'] ) : null,
			'valid'         => isset( $feed['valid'] ) ? (bool) $feed['valid'] : false,
			'episode_count' => isset( $feed['episode_count'] ) ? absint( $feed['episode_count'] ) : 0,
		);
	}

	return $sanitized;
}


/**
 * Render playlist tabs with Episodes tab first
 *
 * Creates a tabbed interface for the playlist player with:
 * - Episodes tab (first) - list of all episodes with play buttons
 * - Description tab - current episode description
 * - Credits tab - hosts/guests
 * - Chapters tab - chapter markers
 * - Transcripts tab - episode transcript
 *
 * @param array    $episodes        Array of episodes to display (initial batch).
 * @param array    $current_episode Currently playing episode data.
 * @param bool     $show_description Whether to show description tab.
 * @param array    $colors          Theme colors from color calculation.
 * @param int|null $total_episodes  Total number of episodes (for pagination). Defaults to count of $episodes.
 * @return string HTML output.
 */
function podloom_render_playlist_tabs( $episodes, $current_episode, $show_description, $colors, $total_episodes = null ) {
	// Get display settings
	$display_transcripts   = get_option( 'podloom_rss_display_transcripts', true );
	$display_people_hosts  = get_option( 'podloom_rss_display_people_hosts', true );
	$display_people_guests = get_option( 'podloom_rss_display_people_guests', true );
	$display_chapters      = get_option( 'podloom_rss_display_chapters', true );

	$p20_data = $current_episode['podcast20'] ?? array();
	if ( ! is_array( $p20_data ) ) {
		$p20_data = array();
	}

	// Build tabs array
	$tabs = array();

	// Tab 0: Episodes (always first for playlist)
	$tabs[] = array(
		'id'      => 'episodes',
		'label'   => __( 'Episodes', 'podloom-podcast-player' ),
		'content' => podloom_render_episodes_list( $episodes, $current_episode, $colors, $total_episodes ),
	);

	// Tab 1: Description
	$description_html = '';
	$description_source = ! empty( $current_episode['content'] ) ? $current_episode['content'] : ( $current_episode['description'] ?? '' );
	if ( $show_description && ! empty( $description_source ) ) {
		$allowed_html = array(
			'p'          => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			'a'          => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
		);
		$description_html = wp_kses( $description_source, $allowed_html );

		// Apply character limit if set
		$char_limit = get_option( 'podloom_rss_description_limit', 0 );
		if ( $char_limit > 0 ) {
			$description_html = podloom_truncate_html( $description_html, $char_limit );
		}

		$tabs[] = array(
			'id'      => 'description',
			'label'   => __( 'Description', 'podloom-podcast-player' ),
			'content' => '<div class="rss-episode-description podloom-playlist-description">' . $description_html . '</div>',
		);
	}

	// Tab: Credits (People) - Always show for playlist so JS can update it
	if ( $display_people_hosts || $display_people_guests ) {
		$people_to_show = array();
		if ( $display_people_hosts && ! empty( $p20_data['people_channel'] ) ) {
			$people_to_show = array_merge( $people_to_show, $p20_data['people_channel'] );
		}
		if ( $display_people_guests && ! empty( $p20_data['people_episode'] ) ) {
			$people_to_show = array_merge( $people_to_show, $p20_data['people_episode'] );
		}
		// Deduplicate by name
		$seen_names = array();
		$people_to_show = array_filter(
			$people_to_show,
			function ( $person ) use ( &$seen_names ) {
				$name_key = strtolower( trim( $person['name'] ) );
				if ( isset( $seen_names[ $name_key ] ) ) {
					return false;
				}
				$seen_names[ $name_key ] = true;
				return true;
			}
		);
		if ( ! empty( $people_to_show ) ) {
			usort(
				$people_to_show,
				function ( $a, $b ) {
					$priority = array( 'host' => 1, 'co-host' => 2, 'guest' => 3 );
					$a_priority = $priority[ strtolower( $a['role'] ) ] ?? 999;
					$b_priority = $priority[ strtolower( $b['role'] ) ] ?? 999;
					return $a_priority - $b_priority;
				}
			);
			$credits_content = podloom_render_people( $people_to_show );
		} else {
			$credits_content = '<p class="no-content">' . esc_html__( 'No credits available for this episode.', 'podloom-podcast-player' ) . '</p>';
		}
		$tabs[] = array(
			'id'      => 'credits',
			'label'   => __( 'Credits', 'podloom-podcast-player' ),
			'content' => '<div class="podloom-playlist-credits">' . $credits_content . '</div>',
		);
	}

	// Tab: Chapters - Always show for playlist so JS can update it
	if ( $display_chapters ) {
		if ( ! empty( $p20_data['chapters'] ) ) {
			$chapters_content = podloom_render_chapters( $p20_data['chapters'] );
		} else {
			$chapters_content = '<p class="no-content">' . esc_html__( 'No chapters available for this episode.', 'podloom-podcast-player' ) . '</p>';
		}
		$tabs[] = array(
			'id'      => 'chapters',
			'label'   => __( 'Chapters', 'podloom-podcast-player' ),
			'content' => '<div class="podloom-playlist-chapters">' . $chapters_content . '</div>',
		);
	}

	// Tab: Transcripts - Always show for playlist so JS can update it
	if ( $display_transcripts ) {
		if ( ! empty( $p20_data['transcripts'] ) ) {
			$transcripts_content = podloom_render_transcripts( $p20_data['transcripts'] );
		} else {
			$transcripts_content = '<p class="no-content">' . esc_html__( 'No transcript available for this episode.', 'podloom-podcast-player' ) . '</p>';
		}
		$tabs[] = array(
			'id'      => 'transcripts',
			'label'   => __( 'Transcripts', 'podloom-podcast-player' ),
			'content' => '<div class="podloom-playlist-transcripts">' . $transcripts_content . '</div>',
		);
	}

	// If only Episodes tab, still show it
	if ( empty( $tabs ) ) {
		return '';
	}

	// Build tab navigation
	$output  = '<div class="podcast20-tabs podloom-playlist-tabs">';
	$output .= '<div class="podcast20-tab-nav" role="tablist">';

	foreach ( $tabs as $index => $tab ) {
		$is_active = ( $index === 0 ) ? 'active' : '';
		$output   .= sprintf(
			'<button class="podcast20-tab-button %s" data-tab="%s" role="tab" aria-selected="%s" aria-controls="tab-panel-%s">%s</button>',
			$is_active,
			esc_attr( $tab['id'] ),
			$is_active ? 'true' : 'false',
			esc_attr( $tab['id'] ),
			esc_html( $tab['label'] )
		);
	}

	$output .= '</div>'; // .podcast20-tab-nav

	// Build tab panels
	foreach ( $tabs as $index => $tab ) {
		$is_active = ( $index === 0 ) ? 'active' : '';
		$output   .= sprintf(
			'<div class="podcast20-tab-panel %s" id="tab-panel-%s" role="tabpanel" data-tab-id="%s">%s</div>',
			$is_active,
			esc_attr( $tab['id'] ),
			esc_attr( $tab['id'] ),
			$tab['content']
		);
	}

	$output .= '</div>'; // .podcast20-tabs

	return $output;
}

/**
 * Render episodes list for playlist tab
 *
 * @param array    $episodes        Array of episodes to display (initial batch).
 * @param array    $current_episode Currently playing episode.
 * @param array    $colors          Theme colors.
 * @param int|null $total_episodes  Total number of episodes (for pagination).
 * @return string HTML output.
 */
function podloom_render_episodes_list( $episodes, $current_episode, $colors, $total_episodes = null ) {
	$loaded_count = count( $episodes );
	$total        = $total_episodes ?? $loaded_count;
	$has_more     = $total > $loaded_count;

	$output = '';

	// Search box (show if more than 10 episodes total).
	if ( $total > 10 ) {
		$output .= '<div class="podloom-episodes-search">';
		$output .= '<div class="podloom-search-input-wrapper">';
		$output .= '<svg class="podloom-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>';
		$output .= '<input type="search" class="podloom-episodes-search-input" placeholder="' . esc_attr__( 'Search episodes...', 'podloom-podcast-player' ) . '" aria-label="' . esc_attr__( 'Search episodes', 'podloom-podcast-player' ) . '" />';
		$output .= '<button type="button" class="podloom-episodes-search-clear" aria-label="' . esc_attr__( 'Clear search', 'podloom-podcast-player' ) . '">';
		$output .= '<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>';
		$output .= '</button>';
		$output .= '</div>';
		$output .= '<div class="podloom-search-status" role="status" aria-live="polite"></div>';
		$output .= '</div>';
	}

	// Episodes list container.
	$output .= '<div class="podloom-episodes-list" data-loaded="' . esc_attr( $loaded_count ) . '">';

	foreach ( $episodes as $index => $episode ) {
		$output .= podloom_render_single_episode_item( $episode, $index, $current_episode );
	}

	$output .= '</div>'; // .podloom-episodes-list

	// Load More button.
	if ( $has_more ) {
		$remaining = $total - $loaded_count;
		$output   .= '<div class="podloom-episodes-load-more-wrapper">';
		$output   .= '<button type="button" class="podloom-episodes-load-more" data-remaining="' . esc_attr( $remaining ) . '">';
		$output   .= '<span class="podloom-load-more-text">' . sprintf(
			/* translators: %d: number of remaining episodes */
			esc_html__( 'Load More (%d remaining)', 'podloom-podcast-player' ),
			$remaining
		) . '</span>';
		$output .= '<span class="podloom-load-more-loading" style="display: none;">' . esc_html__( 'Loading...', 'podloom-podcast-player' ) . '</span>';
		$output .= '</button>';
		$output .= '</div>';
	}

	// No results message (hidden by default).
	$output .= '<div class="podloom-episodes-no-results" style="display: none;" role="status">';
	$output .= esc_html__( 'No episodes found', 'podloom-podcast-player' );
	$output .= '</div>';

	return $output;
}

/**
 * Render a single episode item for the episodes list.
 *
 * @param array $episode         Episode data.
 * @param int   $index           Episode index in the list.
 * @param array $current_episode Currently playing episode.
 * @return string HTML output.
 */
function podloom_render_single_episode_item( $episode, $index, $current_episode ) {
	$is_current    = ( $episode['audio_url'] === $current_episode['audio_url'] );
	$current_class = $is_current ? ' podloom-episode-current' : '';
	$search_term   = strtolower( sanitize_text_field( $episode['title'] ?? '' ) );

	// Format date.
	$date_formatted = '';
	if ( ! empty( $episode['date'] ) ) {
		$date_formatted = date_i18n( get_option( 'date_format' ), $episode['date'] );
	}

	// Format duration.
	$duration_formatted = '';
	if ( ! empty( $episode['duration'] ) ) {
		$duration_formatted = podloom_format_duration( $episode['duration'] );
	}

	$output = '<div class="podloom-episode-item' . $current_class . '" data-episode-index="' . esc_attr( $index ) . '" data-search-term="' . esc_attr( $search_term ) . '">';

	// Episode thumbnail.
	if ( ! empty( $episode['image'] ) ) {
		$output .= '<div class="podloom-episode-thumb">';
		$output .= '<img src="' . esc_url( $episode['image'] ) . '" alt="' . esc_attr( $episode['title'] ) . '" loading="lazy" />';
		// Play/Now Playing indicator overlay.
		$output .= '<div class="podloom-episode-play-overlay">';
		if ( $is_current ) {
			$output .= '<span class="podloom-now-playing-icon" title="' . esc_attr__( 'Now Playing', 'podloom-podcast-player' ) . '">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
					<rect x="4" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite"/></rect>
					<rect x="10" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="8;16;8" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="8;4;8" dur="0.8s" repeatCount="indefinite"/></rect>
					<rect x="16" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite" begin="0.2s"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite" begin="0.2s"/></rect>
				</svg>
			</span>';
		} else {
			$output .= '<span class="podloom-play-icon" title="' . esc_attr__( 'Play', 'podloom-podcast-player' ) . '">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
					<polygon points="5,3 19,12 5,21"/>
				</svg>
			</span>';
		}
		$output .= '</div>'; // .podloom-episode-play-overlay
		$output .= '</div>'; // .podloom-episode-thumb
	} else {
		// Placeholder thumbnail with play button.
		$output .= '<div class="podloom-episode-thumb podloom-episode-thumb-placeholder">';
		$output .= '<div class="podloom-episode-play-overlay">';
		if ( $is_current ) {
			$output .= '<span class="podloom-now-playing-icon" title="' . esc_attr__( 'Now Playing', 'podloom-podcast-player' ) . '">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
					<rect x="4" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite"/></rect>
					<rect x="10" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="8;16;8" dur="0.8s" repeatCount="indefinite"/><animate attributeName="y" values="8;4;8" dur="0.8s" repeatCount="indefinite"/></rect>
					<rect x="16" y="4" width="4" height="16" rx="1"><animate attributeName="height" values="16;8;16" dur="0.8s" repeatCount="indefinite" begin="0.2s"/><animate attributeName="y" values="4;8;4" dur="0.8s" repeatCount="indefinite" begin="0.2s"/></rect>
				</svg>
			</span>';
		} else {
			$output .= '<span class="podloom-play-icon" title="' . esc_attr__( 'Play', 'podloom-podcast-player' ) . '">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
					<polygon points="5,3 19,12 5,21"/>
				</svg>
			</span>';
		}
		$output .= '</div>'; // .podloom-episode-play-overlay
		$output .= '</div>'; // .podloom-episode-thumb
	}

	// Episode info.
	$output .= '<div class="podloom-episode-info">';
	$output .= '<div class="podloom-episode-title-row">';
	$output .= '<span class="podloom-episode-item-title">' . esc_html( $episode['title'] ) . '</span>';
	$output .= '</div>';

	// Meta row (date and duration).
	if ( $date_formatted || $duration_formatted ) {
		$output .= '<div class="podloom-episode-meta-row">';
		if ( $date_formatted ) {
			$output .= '<span class="podloom-episode-item-date">' . esc_html( $date_formatted ) . '</span>';
		}
		if ( $duration_formatted ) {
			$output .= '<span class="podloom-episode-item-duration">' . esc_html( $duration_formatted ) . '</span>';
		}
		$output .= '</div>';
	}
	$output .= '</div>'; // .podloom-episode-info

	$output .= '</div>'; // .podloom-episode-item

	return $output;
}

