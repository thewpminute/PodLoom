<?php
/**
 * Plugin Name:  PodLoom - Podcast Player for Transistor.fm & RSS Feeds
 * Plugin URI: https://thewpminute.com/podloom/
 * Description: Connect to your Transistor.fm account and embed podcast episodes using Gutenberg blocks or Elementor. Supports RSS feeds from any podcast platform.
 * Version: 2.11.1
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
define( 'PODLOOM_PLUGIN_VERSION', '2.11.1' );
define( 'PODLOOM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PODLOOM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PODLOOM_PLUGIN_FILE', __FILE__ );

// Use minified assets unless SCRIPT_DEBUG is enabled.
define( 'PODLOOM_SCRIPT_SUFFIX', defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min' );

// Global flag to track if P2.0 content is used on this page
global $podloom_has_podcast20_content;
$podloom_has_podcast20_content = false;

// Include shared utilities.
require_once PODLOOM_PLUGIN_DIR . 'includes/cache.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/color-utils.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/utilities.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/class-podloom-image-cache.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/class-podloom-podcast20-parser.php';

// Include Transistor.fm integration.
require_once PODLOOM_PLUGIN_DIR . 'includes/transistor/class-podloom-transistor-api.php';

// Include RSS feed integration.
require_once PODLOOM_PLUGIN_DIR . 'includes/rss/class-podloom-rss.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss/class-podloom-rss-cron.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss/class-podloom-rss-ajax.php';

// Include Elementor integration (loads conditionally when Elementor is active).
require_once PODLOOM_PLUGIN_DIR . 'includes/elementor/class-podloom-elementor.php';

// Include subscribe buttons feature.
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe/class-podloom-subscribe-icons.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe/class-podloom-subscribe.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe/class-podloom-subscribe-render.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/subscribe-ajax-handlers.php';

// Include admin functions.
require_once PODLOOM_PLUGIN_DIR . 'admin/admin-functions.php';

/**
 * Run one-time migration from transistor_ to podloom_ options
 */
function podloom_run_migration() {
	// Check if migration has already run
	if ( get_option( 'podloom_migration_complete' ) ) {
		return;
	}

	// Map of old to new option names
	$options_map = array(
		'transistor_api_key'                     => 'podloom_api_key',
		'transistor_default_show'                => 'podloom_default_show',
		'transistor_enable_cache'                => 'podloom_enable_cache',
		'transistor_cache_duration'              => 'podloom_cache_duration',
		'transistor_rss_feeds'                   => 'podloom_rss_feeds',
		'transistor_rss_enabled'                 => 'podloom_rss_enabled',
		'transistor_rss_description_limit'       => 'podloom_rss_description_limit',
		'transistor_rss_minimal_styling'         => 'podloom_rss_minimal_styling',
		'transistor_rss_background_color'        => 'podloom_rss_background_color',
		'transistor_rss_display_artwork'         => 'podloom_rss_display_artwork',
		'transistor_rss_display_title'           => 'podloom_rss_display_title',
		'transistor_rss_display_date'            => 'podloom_rss_display_date',
		'transistor_rss_display_duration'        => 'podloom_rss_display_duration',
		'transistor_rss_display_description'     => 'podloom_rss_display_description',
		'transistor_rss_title_font_family'       => 'podloom_rss_title_font_family',
		'transistor_rss_title_font_size'         => 'podloom_rss_title_font_size',
		'transistor_rss_title_line_height'       => 'podloom_rss_title_line_height',
		'transistor_rss_title_color'             => 'podloom_rss_title_color',
		'transistor_rss_title_font_weight'       => 'podloom_rss_title_font_weight',
		'transistor_rss_date_font_family'        => 'podloom_rss_date_font_family',
		'transistor_rss_date_font_size'          => 'podloom_rss_date_font_size',
		'transistor_rss_date_line_height'        => 'podloom_rss_date_line_height',
		'transistor_rss_date_color'              => 'podloom_rss_date_color',
		'transistor_rss_date_font_weight'        => 'podloom_rss_date_font_weight',
		'transistor_rss_duration_font_family'    => 'podloom_rss_duration_font_family',
		'transistor_rss_duration_font_size'      => 'podloom_rss_duration_font_size',
		'transistor_rss_duration_line_height'    => 'podloom_rss_duration_line_height',
		'transistor_rss_duration_color'          => 'podloom_rss_duration_color',
		'transistor_rss_duration_font_weight'    => 'podloom_rss_duration_font_weight',
		'transistor_rss_description_font_family' => 'podloom_rss_description_font_family',
		'transistor_rss_description_font_size'   => 'podloom_rss_description_font_size',
		'transistor_rss_description_line_height' => 'podloom_rss_description_line_height',
		'transistor_rss_description_color'       => 'podloom_rss_description_color',
		'transistor_rss_description_font_weight' => 'podloom_rss_description_font_weight',
		'transistor_rss_typography_cache'        => 'podloom_rss_typography_cache',
	);

	$migrated = 0;
	foreach ( $options_map as $old_name => $new_name ) {
		$old_value = get_option( $old_name );
		if ( $old_value !== false ) {
			update_option( $new_name, $old_value );
			++$migrated;
		}
	}

	// Migrate transients - direct query required to find all legacy transients.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration, caching not applicable.
	$transients = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE %s
             OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_transistor_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_transistor_' ) . '%'
		)
	);

	foreach ( $transients as $transient ) {
		$new_name = str_replace( 'transistor_', 'podloom_', $transient->option_name );
		update_option( $new_name, $transient->option_value );
	}

	// Mark migration as complete
	update_option( 'podloom_migration_complete', true );
}
add_action( 'admin_init', 'podloom_run_migration' );

/**
 * Initialize the plugin and register blocks
 */
function podloom_init() {
	// Register episode block from build folder.
	$episode_asset_file = PODLOOM_PLUGIN_DIR . 'build/episode-block/index.asset.php';
	$episode_asset      = file_exists( $episode_asset_file ) ? require $episode_asset_file : array(
		'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
		'version'      => PODLOOM_PLUGIN_VERSION,
	);

	wp_register_script(
		'podloom-episode-block-editor',
		PODLOOM_PLUGIN_URL . 'build/episode-block/index.js',
		$episode_asset['dependencies'],
		$episode_asset['version'],
		false
	);

	// Pass data to episode block.
	wp_localize_script(
		'podloom-episode-block-editor',
		'podloomData',
		array(
			'defaultShow' => get_option( 'podloom_default_show', '' ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'podloom_nonce' ),
			'hasApiKey'   => ! empty( get_option( 'podloom_api_key', '' ) ),
		)
	);

	// Register the episode block type.
	register_block_type(
		PODLOOM_PLUGIN_DIR . 'build/episode-block',
		array(
			'editor_script'   => 'podloom-episode-block-editor',
			'render_callback' => 'podloom_render_block',
		)
	);

	// Register subscribe block from build folder.
	$subscribe_asset_file = PODLOOM_PLUGIN_DIR . 'build/subscribe-block/index.asset.php';
	$subscribe_asset      = file_exists( $subscribe_asset_file ) ? require $subscribe_asset_file : array(
		'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		'version'      => PODLOOM_PLUGIN_VERSION,
	);

	wp_register_script(
		'podloom-subscribe-block-editor',
		PODLOOM_PLUGIN_URL . 'build/subscribe-block/index.js',
		$subscribe_asset['dependencies'],
		$subscribe_asset['version'],
		false
	);

	// Pass data to subscribe block.
	wp_localize_script(
		'podloom-subscribe-block-editor',
		'podloomData',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'podloom_nonce' ),
		)
	);

	// Register the subscribe block type.
	register_block_type(
		PODLOOM_PLUGIN_DIR . 'build/subscribe-block',
		array(
			'editor_script'   => 'podloom-subscribe-block-editor',
			'render_callback' => 'podloom_render_subscribe_block',
		)
	);
}
add_action( 'init', 'podloom_init' );

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
 * Generate dynamic typography CSS for RSS player
 */
function podloom_get_rss_dynamic_css() {
	$minimal_styling = get_option( 'podloom_rss_minimal_styling', false );
	$player_height   = get_option( 'podloom_rss_player_height', 600 );

	// In minimal styling mode, only output structural CSS (height, flex layout)
	if ( $minimal_styling ) {
		return sprintf(
			'
            .wp-block-podloom-episode-player.rss-episode-player {
                max-height: %dpx;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .wp-block-podloom-episode-player.rss-episode-player .rss-episode-wrapper {
                flex-shrink: 0;
            }
            .wp-block-podloom-episode-player.rss-episode-player .podcast20-tabs {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                min-height: 0;
            }
            .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-panel {
                overflow-y: auto;
                flex: 1;
                min-height: 0;
            }
            @media (max-width: 640px) {
                .wp-block-podloom-episode-player.rss-episode-player {
                    max-height: %dpx;
                }
            }
        ',
			absint( $player_height ),
			absint( $player_height ) + 100 // Slightly taller on mobile
		);
	}

	$typo     = podloom_get_rss_typography_styles();
	$bg_color = get_option( 'podloom_rss_background_color', '#f9f9f9' );

	// Get border settings
	$border_color  = get_option( 'podloom_rss_border_color', '#dddddd' );
	$border_width  = get_option( 'podloom_rss_border_width', '1px' );
	$border_style  = get_option( 'podloom_rss_border_style', 'solid' );
	$border_radius = get_option( 'podloom_rss_border_radius', '8px' );

	// Get funding button settings
	$funding_font_family      = get_option( 'podloom_rss_funding_font_family', 'inherit' );
	$funding_font_size        = get_option( 'podloom_rss_funding_font_size', '13px' );
	$funding_background_color = get_option( 'podloom_rss_funding_background_color', '#2271b1' );
	$funding_text_color       = get_option( 'podloom_rss_funding_text_color', '#ffffff' );
	$funding_border_radius    = get_option( 'podloom_rss_funding_border_radius', '4px' );

	// Calculate theme-aware colors for tabs and P2.0 elements
	$theme_colors = podloom_calculate_theme_colors( $bg_color );

	// Override accent if saved
	$saved_accent = get_option( 'podloom_rss_accent_color' );
	if ( ! empty( $saved_accent ) ) {
		$theme_colors['accent'] = $saved_accent;
		// Recalculate dependent colors if needed, or just use accent
		// For now, we'll trust the palette's accent is good
	}

	return sprintf(
		'
        .wp-block-podloom-episode-player.rss-episode-player {
            background: %s;
            border: %s %s %s;
            border-radius: %s;
            max-height: %dpx;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        /* Funding button styles */
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-funding-button {
            font-family: %s;
            font-size: %s;
            background: %s;
            color: %s;
            border-radius: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-funding-button:hover {
            background: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .rss-episode-wrapper {
            flex-shrink: 0;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tabs {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-panel {
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }
        @media (max-width: 640px) {
            .wp-block-podloom-episode-player.rss-episode-player {
                max-height: %dpx;
            }
            .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-nav {
                background: %s;
            }
        }
        /* Tab colors based on theme */
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-button {
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-button:hover {
            color: %s;
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-button.active {
            color: %s;
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-nav {
            border-bottom-color: %s;
        }
        /* P2.0 content area colors based on theme */
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-people,
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-chapters-list {
            background: %s;
            border-color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-person-role,
        .wp-block-podloom-episode-player.rss-episode-player .chapters-heading {
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-timestamp {
            background: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-timestamp:hover {
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-item.active {
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-timestamp {
            background: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-timestamp:hover {
            background: %s;
        }
        /* P2.0 Headings */
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-heading {
            color: %s;
        }
        /* Person cards */
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-person {
            background: %s;
            border-color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-person-name {
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-person-avatar {
            background: %s;
            color: %s;
        }
        /* Chapter items */
        .wp-block-podloom-episode-player.rss-episode-player .chapter-item {
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-item:hover {
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-title {
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-img-placeholder {
            background: %s;
        }
        /* Transcript viewer */
        .wp-block-podloom-episode-player.rss-episode-player .transcript-viewer {
            background: %s;
            border-color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-content {
            color: %s;
        }
        /* Transcript format buttons */
        .wp-block-podloom-episode-player.rss-episode-player .transcript-format-button {
            background: %s;
            border-color: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-format-button:hover {
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-format-button.active {
            background: %s;
            color: %s;
            border-color: %s;
        }
        /* Transcript close button */
        .wp-block-podloom-episode-player.rss-episode-player .transcript-close {
            background: %s;
            border-color: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-close:hover {
            background: %s;
        }
        /* Transcript error/loading */
        .wp-block-podloom-episode-player.rss-episode-player .transcript-error {
            background: %s;
            border-color: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-loading {
            color: %s;
        }
        /* External links */
        .wp-block-podloom-episode-player.rss-episode-player .transcript-external-link,
        .wp-block-podloom-episode-player.rss-episode-player .chapter-external-link {
            color: %s;
        }
        /* Skip buttons */
        .wp-block-podloom-episode-player.rss-episode-player .podloom-skip-btn {
            color: %s;
            border-color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podloom-skip-btn:hover {
            background: %s;
        }
        .rss-episode-title {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }
        .rss-episode-date {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }
        .rss-episode-duration {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }
        .rss-episode-description {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }
',
		esc_attr( $bg_color ),
		// Border styles
		esc_attr( $border_width ),
		esc_attr( $border_style ),
		esc_attr( $border_color ),
		esc_attr( $border_radius ),
		absint( $player_height ),
		// Funding button styles
		esc_attr( $funding_font_family ),
		esc_attr( $funding_font_size ),
		esc_attr( $funding_background_color ),
		esc_attr( $funding_text_color ),
		esc_attr( $funding_border_radius ),
		// Funding button hover (darken background slightly)
		esc_attr( podloom_adjust_color_brightness( $funding_background_color, -15 ) ),
		esc_attr( $funding_text_color ),
		absint( $player_height + 100 ), // Mobile height - add 100px for stacked layout
		// Mobile tab nav background
		esc_attr( $theme_colors['content_bg'] ),
		// Tab colors
		esc_attr( $theme_colors['tab_text'] ),
		esc_attr( $theme_colors['tab_text_hover'] ),
		esc_attr( $theme_colors['tab_bg_hover'] ),
		esc_attr( $theme_colors['tab_active_text'] ),
		esc_attr( $theme_colors['tab_active_bg'] ),
		esc_attr( $theme_colors['tab_border'] ),
		// P2.0 content colors
		esc_attr( $theme_colors['content_bg'] ),
		esc_attr( $theme_colors['content_border'] ),
		esc_attr( $theme_colors['accent'] ),
		esc_attr( $theme_colors['accent'] ),
		esc_attr( $theme_colors['accent_text'] ),
		esc_attr( $theme_colors['accent_hover'] ),
		esc_attr( $theme_colors['content_bg_active'] ),
		esc_attr( $theme_colors['accent'] ),
		esc_attr( $theme_colors['accent_text'] ),
		esc_attr( $theme_colors['accent_hover'] ),
		// P2.0 headings
		esc_attr( $theme_colors['text_primary'] ),
		// Person cards
		esc_attr( $theme_colors['card_bg'] ),
		esc_attr( $theme_colors['card_border'] ),
		esc_attr( $theme_colors['text_primary'] ),
		esc_attr( $theme_colors['avatar_bg'] ),
		esc_attr( $theme_colors['avatar_text'] ),
		// Chapter items
		esc_attr( $theme_colors['card_bg'] ),
		esc_attr( $theme_colors['card_bg_hover'] ),
		esc_attr( $theme_colors['text_primary'] ),
		esc_attr( $theme_colors['content_bg'] ),
		// Transcript viewer
		esc_attr( $theme_colors['card_bg'] ),
		esc_attr( $theme_colors['card_border'] ),
		esc_attr( $theme_colors['text_primary'] ),
		// Transcript format buttons
		esc_attr( $theme_colors['button_bg'] ),
		esc_attr( $theme_colors['button_border'] ),
		esc_attr( $theme_colors['button_text'] ),
		esc_attr( $theme_colors['button_bg_hover'] ),
		// Transcript format button active
		esc_attr( $theme_colors['accent'] ),
		esc_attr( $theme_colors['accent_text'] ),
		esc_attr( $theme_colors['accent'] ),
		// Transcript close button
		esc_attr( $theme_colors['button_bg'] ),
		esc_attr( $theme_colors['button_border'] ),
		esc_attr( $theme_colors['text_secondary'] ),
		esc_attr( $theme_colors['button_bg_hover'] ),
		// Transcript error/loading
		esc_attr( $theme_colors['warning_bg'] ),
		esc_attr( $theme_colors['warning_border'] ),
		esc_attr( $theme_colors['warning_text'] ),
		esc_attr( $theme_colors['text_muted'] ),
		// External links
		esc_attr( $theme_colors['text_secondary'] ),
		// Skip buttons
		esc_attr( $theme_colors['text_secondary'] ),
		esc_attr( $theme_colors['button_border'] ),
		esc_attr( $theme_colors['tab_bg_hover'] ),
		// Typography
		esc_attr( $typo['title']['font-family'] ),
		esc_attr( $typo['title']['font-size'] ),
		esc_attr( $typo['title']['line-height'] ),
		esc_attr( $typo['title']['color'] ),
		esc_attr( $typo['title']['font-weight'] ),
		esc_attr( $typo['date']['font-family'] ),
		esc_attr( $typo['date']['font-size'] ),
		esc_attr( $typo['date']['line-height'] ),
		esc_attr( $typo['date']['color'] ),
		esc_attr( $typo['date']['font-weight'] ),
		esc_attr( $typo['duration']['font-family'] ),
		esc_attr( $typo['duration']['font-size'] ),
		esc_attr( $typo['duration']['line-height'] ),
		esc_attr( $typo['duration']['color'] ),
		esc_attr( $typo['duration']['font-weight'] ),
		esc_attr( $typo['description']['font-family'] ),
		esc_attr( $typo['description']['font-size'] ),
		esc_attr( $typo['description']['line-height'] ),
		esc_attr( $typo['description']['color'] ),
		esc_attr( $typo['description']['font-weight'] )
	);
}

/**
 * Enqueue frontend styles for RSS player (base styles always loaded)
 */
function podloom_enqueue_rss_styles() {
	// Always enqueue base player styles
	wp_enqueue_style(
		'podloom-rss-player',
		PODLOOM_PLUGIN_URL . 'assets/css/rss-player' . PODLOOM_SCRIPT_SUFFIX . '.css',
		array(),
		PODLOOM_PLUGIN_VERSION
	);

	$custom_css = podloom_get_rss_dynamic_css();
	if ( $custom_css ) {
		wp_add_inline_style( 'podloom-rss-player', $custom_css );
	}

	// Register subscribe buttons styles (enqueued on-demand by blocks/widgets).
	wp_register_style(
		'podloom-subscribe-buttons',
		PODLOOM_PLUGIN_URL . 'assets/css/subscribe-buttons' . PODLOOM_SCRIPT_SUFFIX . '.css',
		array(),
		PODLOOM_PLUGIN_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'podloom_enqueue_rss_styles' );

/**
 * Conditionally enqueue Podcasting 2.0 assets in footer
 * Only loads if P2.0 content was rendered on the page
 */
function podloom_enqueue_podcast20_assets() {
	global $podloom_has_podcast20_content;

	// Only load P2.0 assets if content exists
	if ( ! $podloom_has_podcast20_content ) {
		echo '<!-- PodLoom: No P2.0 content detected, assets not loaded -->';
		return;
	}

	// Enqueue Podcasting 2.0 styles
	wp_enqueue_style(
		'podloom-podcast20',
		PODLOOM_PLUGIN_URL . 'assets/css/podcast20-styles' . PODLOOM_SCRIPT_SUFFIX . '.css',
		array(),
		PODLOOM_PLUGIN_VERSION
	);

	// Enqueue Podcasting 2.0 chapter navigation script
	wp_enqueue_script(
		'podloom-podcast20-player',
		PODLOOM_PLUGIN_URL . 'assets/js/podcast20-player' . PODLOOM_SCRIPT_SUFFIX . '.js',
		array(),
		PODLOOM_PLUGIN_VERSION,
		true // Load in footer
	);

	// Pass AJAX URL to the script for transcript proxy
	wp_localize_script(
		'podloom-podcast20-player',
		'podloomTranscript',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		)
	);

	// Pass AJAX URL for background image caching (only if enabled)
	if ( Podloom_Image_Cache::is_enabled() ) {
		wp_localize_script(
			'podloom-podcast20-player',
			'podloomImageCache',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	// Pass AJAX URL for playlist P2.0 data fetching
	wp_localize_script(
		'podloom-podcast20-player',
		'podloomPlaylist',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'podloom_playlist_nonce' ),
		)
	);

	echo '<!-- PodLoom: P2.0 assets loaded -->';
}
add_action( 'wp_footer', 'podloom_enqueue_podcast20_assets', 5 );

/**
 * Enqueue editor styles and scripts for RSS player (uses same as frontend)
 */
function podloom_enqueue_editor_styles() {
	// Base RSS player styles
	wp_enqueue_style(
		'podloom-rss-player-editor',
		PODLOOM_PLUGIN_URL . 'assets/css/rss-player' . PODLOOM_SCRIPT_SUFFIX . '.css',
		array(),
		PODLOOM_PLUGIN_VERSION
	);

	// Podcasting 2.0 styles (for tabs, chapters, transcripts, etc.)
	wp_enqueue_style(
		'podloom-podcast20-editor',
		PODLOOM_PLUGIN_URL . 'assets/css/podcast20-styles' . PODLOOM_SCRIPT_SUFFIX . '.css',
		array( 'podloom-rss-player-editor' ),
		PODLOOM_PLUGIN_VERSION
	);

	// Podcasting 2.0 JavaScript (for tab switching, chapter navigation, transcript loading)
	wp_enqueue_script(
		'podloom-podcast20-player-editor',
		PODLOOM_PLUGIN_URL . 'assets/js/podcast20-player' . PODLOOM_SCRIPT_SUFFIX . '.js',
		array(),
		PODLOOM_PLUGIN_VERSION,
		true
	);

	// Pass AJAX URL to the script for transcript proxy
	wp_localize_script(
		'podloom-podcast20-player-editor',
		'podloomTranscript',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		)
	);

	// Pass AJAX URL for background image caching (only if enabled)
	if ( Podloom_Image_Cache::is_enabled() ) {
		wp_localize_script(
			'podloom-podcast20-player-editor',
			'podloomImageCache',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	// Add dynamic CSS based on user settings (colors, fonts, etc.)
	$custom_css = podloom_get_rss_dynamic_css();
	if ( $custom_css ) {
		wp_add_inline_style( 'podloom-rss-player-editor', $custom_css );
	}
}
add_action( 'enqueue_block_editor_assets', 'podloom_enqueue_editor_styles' );

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

		// Construct iframe for latest episode
		$iframe = sprintf(
			'<iframe width="100%%" height="180" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/%s/%s"></iframe>',
			esc_attr( $show_slug ),
			esc_attr( $theme_path )
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

		// Construct iframe for playlist
		$iframe = sprintf(
			'<iframe width="100%%" height="%d" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/%s/%s"></iframe>',
			$playlist_height,
			esc_attr( $show_slug ),
			esc_attr( $theme_path )
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
 * Get typography styles for RSS elements (with caching)
 */
function podloom_get_rss_typography_styles() {
	// Try to get from cache first (uses object cache if available)
	$cached_styles = podloom_cache_get( 'rss_typography_cache' );
	if ( $cached_styles !== false ) {
		return $cached_styles;
	}

	$elements = array( 'title', 'date', 'duration', 'description' );
	$styles   = array();

	// Default values
	$defaults = array(
		'title'       => array(
			'font_size'   => '24px',
			'line_height' => '1.3',
			'color'       => '#000000',
			'font_weight' => '600',
		),
		'date'        => array(
			'font_size'   => '14px',
			'line_height' => '1.5',
			'color'       => '#666666',
			'font_weight' => 'normal',
		),
		'duration'    => array(
			'font_size'   => '14px',
			'line_height' => '1.5',
			'color'       => '#666666',
			'font_weight' => 'normal',
		),
		'description' => array(
			'font_size'   => '16px',
			'line_height' => '1.6',
			'color'       => '#333333',
			'font_weight' => 'normal',
		),
	);

	// Load all options at once to avoid N+1 query problem (20+ queries reduced to 1)
	$all_options = wp_load_alloptions();

	foreach ( $elements as $element ) {
		$styles[ $element ] = array(
			'font-family' => $all_options[ "podloom_rss_{$element}_font_family" ] ?? 'inherit',
			'font-size'   => $all_options[ "podloom_rss_{$element}_font_size" ] ?? $defaults[ $element ]['font_size'],
			'line-height' => $all_options[ "podloom_rss_{$element}_line_height" ] ?? $defaults[ $element ]['line_height'],
			'color'       => $all_options[ "podloom_rss_{$element}_color" ] ?? $defaults[ $element ]['color'],
			'font-weight' => $all_options[ "podloom_rss_{$element}_font_weight" ] ?? $defaults[ $element ]['font_weight'],
		);
	}

	// Cache the styles for 1 hour (uses object cache if available)
	podloom_cache_set( 'rss_typography_cache', $styles, 'podloom', HOUR_IN_SECONDS );

	return $styles;
}

/**
 * Clear typography cache (called when settings are saved)
 */
function podloom_clear_typography_cache() {
	// Clear typography cache
	podloom_cache_delete( 'rss_typography_cache' );

	// Increment render cache version to invalidate all rendered episode HTML in editor
	// This forces editor to re-render episodes with new typography/display settings
	// WITHOUT clearing heavy episode metadata cache (podcasts, episodes, etc.)
	podloom_increment_render_cache_version();
}

/**
 * Atomically increment render cache version to avoid race conditions
 * Uses direct database query to ensure atomic increment even with concurrent requests
 */
function podloom_increment_render_cache_version() {
	global $wpdb;

	// Try to increment existing option atomically.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic increment required for concurrency.
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->options}
             SET option_value = option_value + 1
             WHERE option_name = %s",
			'podloom_render_cache_version'
		)
	);

	// If option doesn't exist yet, create it
	if ( $updated === 0 ) {
		add_option( 'podloom_render_cache_version', 1, '', false ); // no autoload
	}

	// Clear object cache to ensure fresh reads
	wp_cache_delete( 'podloom_render_cache_version', 'options' );
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
	// Fetch episodes from the feed
	$episodes_data = Podloom_RSS::get_episodes( $feed_id, 1, $max_episodes, true );

	if ( empty( $episodes_data['episodes'] ) ) {
		return '<div class="wp-block-podloom-episode-player podloom-loading" style="padding: 20px; background: #f8f9fa; border: 1px dashed #dee2e6; border-radius: 4px; text-align: center;">' .
				'<p style="margin: 0; color: #6c757d;">' . esc_html__( 'Loading podcast episodes...', 'podloom-podcast-player' ) . '</p>' .
				'</div>';
	}

	$episodes = $episodes_data['episodes'];

	// Sort episodes based on playlistOrder attribute
	// 'episodic' = newest first (default RSS order), 'serial' = oldest first (reversed)
	$playlist_order = isset( $attributes['playlistOrder'] ) ? $attributes['playlistOrder'] : 'episodic';
	if ( 'serial' === $playlist_order && count( $episodes ) > 1 ) {
		$episodes = array_reverse( $episodes );
	}

	// Use first episode as the current/active episode
	$current_episode = $episodes[0];

	// Set global flag to indicate P2.0 content is used
	global $podloom_has_podcast20_content;
	$podloom_has_podcast20_content = true;

	// Get display settings
	$show_artwork      = get_option( 'podloom_rss_display_artwork', true );
	$show_title        = get_option( 'podloom_rss_display_title', true );
	$show_date         = get_option( 'podloom_rss_display_date', true );
	$show_duration     = get_option( 'podloom_rss_display_duration', true );
	$show_description  = get_option( 'podloom_rss_display_description', true );
	$show_skip_buttons = get_option( 'podloom_rss_display_skip_buttons', true );

	// Get background color for color calculations
	$bg_color = get_option( 'podloom_rss_background_color', '#f9f9f9' );
	$colors   = podloom_calculate_theme_colors( $bg_color );

	// Generate unique player ID for this playlist instance
	$player_id = 'podloom-playlist-' . wp_unique_id();

	// Start building output
	$output = '<div id="' . esc_attr( $player_id ) . '" class="wp-block-podloom-episode-player rss-episode-player rss-playlist-player" data-feed-id="' . esc_attr( $feed_id ) . '">';

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
		$output .= '<div class="rss-episode-artwork-column">';
		$output .= sprintf(
			'<div class="rss-episode-artwork"><img src="%s" alt="%s" class="podloom-playlist-artwork" /></div>',
			esc_url( $current_episode['image'] ),
			esc_attr( $current_episode['title'] )
		);

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

	// Audio player
	if ( ! empty( $current_episode['audio_url'] ) ) {
		$output .= sprintf(
			'<audio class="rss-episode-audio podloom-playlist-audio" controls preload="metadata"><source src="%s" type="%s">%s</audio>',
			esc_url( $current_episode['audio_url'] ),
			esc_attr( ! empty( $current_episode['audio_type'] ) ? $current_episode['audio_type'] : 'audio/mpeg' ),
			esc_html__( 'Your browser does not support the audio player.', 'podloom-podcast-player' )
		);

		// Skip buttons
		if ( $show_skip_buttons ) {
			$output .= '<div class="podloom-skip-buttons">';
			$output .= sprintf(
				'<button type="button" class="podloom-skip-btn" data-skip="-10" aria-label="%s">
					<svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
						<polygon points="10,0 10,12 1,6"/>
					</svg>
					<span>10s</span>
				</button>',
				esc_attr__( 'Skip back 10 seconds', 'podloom-podcast-player' )
			);
			$output .= sprintf(
				'<button type="button" class="podloom-skip-btn" data-skip="30" aria-label="%s">
					<svg width="16" height="12" viewBox="0 0 16 12" fill="currentColor">
						<polygon points="0,0 0,12 7,6"/>
						<polygon points="7,0 7,12 14,6"/>
					</svg>
					<span>30s</span>
				</button>',
				esc_attr__( 'Skip forward 30 seconds', 'podloom-podcast-player' )
			);
			$output .= '</div>';
		}
	}

	$output .= '</div>'; // .rss-episode-content
	$output .= '</div>'; // .rss-episode-wrapper

	// Render tabs with Episodes tab first
	$output .= podloom_render_playlist_tabs( $episodes, $current_episode, $show_description, $colors );

	// Store all episode data as JSON for JavaScript
	// Define allowed HTML tags for description/content sanitization (same as in podloom_render_playlist_tabs).
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
	$episodes_json = array();
	foreach ( $episodes as $index => $ep ) {
		// Sanitize description and content to prevent XSS when JavaScript updates the DOM.
		$sanitized_description = ! empty( $ep['description'] ) ? wp_kses( $ep['description'], $allowed_html ) : '';
		$sanitized_content     = ! empty( $ep['content'] ) ? wp_kses( $ep['content'], $allowed_html ) : '';

		$episodes_json[] = array(
			'id'          => $ep['id'] ?? $index,
			'title'       => sanitize_text_field( $ep['title'] ?? '' ),
			'audio_url'   => esc_url_raw( $ep['audio_url'] ?? '' ),
			'image'       => esc_url_raw( $ep['image'] ?? '' ),
			'date'        => $ep['date'] ?? 0,
			'duration'    => $ep['duration'] ?? 0,
			'description' => $sanitized_description,
			'content'     => $sanitized_content,
			'podcast20'   => $ep['podcast20'] ?? null,
		);
	}

	$output .= '<script type="application/json" class="podloom-playlist-data">' . wp_json_encode( $episodes_json ) . '</script>';

	$output .= '</div>'; // .wp-block-podloom-episode-player

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

	// Get background color
	$bg_color = get_option( 'podloom_rss_background_color', '#f9f9f9' );

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
		$output .= '<div class="rss-episode-artwork-column">';
		$output .= sprintf(
			'<div class="rss-episode-artwork"><img src="%s" alt="%s" /></div>',
			esc_url( $episode['image'] ),
			esc_attr( $episode['title'] )
		);

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

	// Audio player (always shown) - uses native HTML5 audio controls
	if ( ! empty( $episode['audio_url'] ) ) {
		// Add class if description is hidden to remove bottom margin
		// Check for either content or description
		$has_description = ! empty( $episode['content'] ) || ! empty( $episode['description'] );
		$audio_class     = ( $show_description && $has_description ) ? 'rss-episode-audio' : 'rss-episode-audio rss-audio-last';

		$output .= sprintf(
			'<audio class="%s" controls preload="metadata"><source src="%s" type="%s">%s</audio>',
			esc_attr( $audio_class ),
			esc_url( $episode['audio_url'] ),
			esc_attr( ! empty( $episode['audio_type'] ) ? $episode['audio_type'] : 'audio/mpeg' ),
			esc_html__( 'Your browser does not support the audio player.', 'podloom-podcast-player' )
		);

		// Skip buttons for audio navigation
		if ( $show_skip_buttons ) {
			$output .= '<div class="podloom-skip-buttons">';
			$output .= sprintf(
				'<button type="button" class="podloom-skip-btn" data-skip="-10" aria-label="%s">
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                        <polygon points="10,0 10,12 1,6"/>
                    </svg>
                    <span>10s</span>
                </button>',
				esc_attr__( 'Skip back 10 seconds', 'podloom-podcast-player' )
			);
			$output .= sprintf(
				'<button type="button" class="podloom-skip-btn" data-skip="30" aria-label="%s">
                    <svg width="16" height="12" viewBox="0 0 16 12" fill="currentColor">
                        <polygon points="0,0 0,12 7,6"/>
                        <polygon points="7,0 7,12 14,6"/>
                    </svg>
                    <span>30s</span>
                </button>',
				esc_attr__( 'Skip forward 30 seconds', 'podloom-podcast-player' )
			);
			$output .= '</div>';
		}
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
 * Get funding button HTML (for top-right positioning)
 *
 * @param array $p20_data Podcasting 2.0 data from parser
 * @return string HTML output
 */
function podloom_get_funding_button( $p20_data ) {
	$display_funding = get_option( 'podloom_rss_display_funding', true );

	if ( $display_funding && ! empty( $p20_data['funding'] ) ) {
		return podloom_render_funding( $p20_data['funding'] );
	}

	return '';
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
 * @param array $episodes        Array of all episodes.
 * @param array $current_episode Currently playing episode data.
 * @param bool  $show_description Whether to show description tab.
 * @param array $colors          Theme colors from color calculation.
 * @return string HTML output.
 */
function podloom_render_playlist_tabs( $episodes, $current_episode, $show_description, $colors ) {
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
		'content' => podloom_render_episodes_list( $episodes, $current_episode, $colors ),
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
 * @param array $episodes        Array of all episodes.
 * @param array $current_episode Currently playing episode.
 * @param array $colors          Theme colors.
 * @return string HTML output.
 */
function podloom_render_episodes_list( $episodes, $current_episode, $colors ) {
	$output = '<div class="podloom-episodes-list">';

	foreach ( $episodes as $index => $episode ) {
		$is_current = ( $episode['audio_url'] === $current_episode['audio_url'] );
		$current_class = $is_current ? ' podloom-episode-current' : '';

		// Format date
		$date_formatted = '';
		if ( ! empty( $episode['date'] ) ) {
			$date_formatted = date_i18n( get_option( 'date_format' ), $episode['date'] );
		}

		// Format duration
		$duration_formatted = '';
		if ( ! empty( $episode['duration'] ) ) {
			$duration_formatted = podloom_format_duration( $episode['duration'] );
		}

		$output .= '<div class="podloom-episode-item' . $current_class . '" data-episode-index="' . esc_attr( $index ) . '">';

		// Episode thumbnail
		if ( ! empty( $episode['image'] ) ) {
			$output .= '<div class="podloom-episode-thumb">';
			$output .= '<img src="' . esc_url( $episode['image'] ) . '" alt="' . esc_attr( $episode['title'] ) . '" loading="lazy" />';
			// Play/Now Playing indicator overlay
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
			// Placeholder thumbnail with play button
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

		// Episode info
		$output .= '<div class="podloom-episode-info">';
		$output .= '<div class="podloom-episode-title-row">';
		$output .= '<span class="podloom-episode-item-title">' . esc_html( $episode['title'] ) . '</span>';
		$output .= '</div>';

		// Meta row (date and duration)
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
	}

	$output .= '</div>'; // .podloom-episodes-list

	return $output;
}

/**
 * Render Podcasting 2.0 elements as tabbed interface
 *
 * @param array  $p20_data Podcasting 2.0 data from parser
 * @param string $description_html Sanitized episode description HTML
 * @param bool   $show_description Whether to show description tab
 * @return string HTML output
 */
function podloom_render_podcast20_tabs( $p20_data, $description_html = '', $show_description = true ) {
	// Get display settings
	$display_transcripts   = get_option( 'podloom_rss_display_transcripts', true );
	$display_people_hosts  = get_option( 'podloom_rss_display_people_hosts', true );
	$display_people_guests = get_option( 'podloom_rss_display_people_guests', true );
	$display_chapters      = get_option( 'podloom_rss_display_chapters', true );

	// Build tabs array: [id, label, content]
	$tabs = array();

	// Tab 0: Description (if enabled and has content)
	if ( $show_description && ! empty( $description_html ) ) {
		$tabs[] = array(
			'id'      => 'description',
			'label'   => __( 'Description', 'podloom-podcast-player' ),
			'content' => '<div class="rss-episode-description">' . $description_html . '</div>',
		);
	}

	// Ensure p20_data is an array
	if ( ! is_array( $p20_data ) ) {
		$p20_data = array();
	}

	// Tab: Credits (People)
	$people_to_show = array();
	if ( $display_people_hosts && ! empty( $p20_data['people_channel'] ) ) {
		$people_to_show = array_merge( $people_to_show, $p20_data['people_channel'] );
	}
	if ( $display_people_guests && ! empty( $p20_data['people_episode'] ) ) {
		$people_to_show = array_merge( $people_to_show, $p20_data['people_episode'] );
	}
	// Deduplicate by name (case-insensitive), keeping the first occurrence (channel-level takes priority)
	$seen_names     = array();
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
				$priority   = array(
					'host'    => 1,
					'co-host' => 2,
					'guest'   => 3,
				);
				$a_priority = $priority[ strtolower( $a['role'] ) ] ?? 999;
				$b_priority = $priority[ strtolower( $b['role'] ) ] ?? 999;
				return $a_priority - $b_priority;
			}
		);
		$tabs[] = array(
			'id'      => 'credits',
			'label'   => __( 'Credits', 'podloom-podcast-player' ),
			'content' => podloom_render_people( $people_to_show ),
		);
	}

	// Tab: Chapters
	if ( $display_chapters && ! empty( $p20_data['chapters'] ) ) {
		$tabs[] = array(
			'id'      => 'chapters',
			'label'   => __( 'Chapters', 'podloom-podcast-player' ),
			'content' => podloom_render_chapters( $p20_data['chapters'] ),
		);
	}

	// Tab: Transcripts
	if ( $display_transcripts && ! empty( $p20_data['transcripts'] ) ) {
		$tabs[] = array(
			'id'      => 'transcripts',
			'label'   => __( 'Transcripts', 'podloom-podcast-player' ),
			'content' => podloom_render_transcripts( $p20_data['transcripts'] ),
		);
	}

	// If no tabs, return empty
	if ( empty( $tabs ) ) {
		return '';
	}

	// Build tab navigation
	$output  = '<div class="podcast20-tabs">';
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
			'<div class="podcast20-tab-panel %s" id="tab-panel-%s" role="tabpanel" aria-labelledby="%s">%s</div>',
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
 * Render Podcasting 2.0 elements (legacy function for backwards compatibility)
 *
 * @param array $p20_data Podcasting 2.0 data from parser
 * @return string HTML output
 */
function podloom_render_podcast20_elements( $p20_data ) {
	return podloom_render_podcast20_tabs( $p20_data );
}

/**
 * Render podcast:funding tag
 *
 * @param array $funding Funding data
 * @return string HTML output
 */
function podloom_render_funding( $funding ) {
	if ( empty( $funding['url'] ) ) {
		return '';
	}

	return sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-funding-button">
            <svg class="podcast20-icon" width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
            </svg>
            <span>%s</span>
        </a>',
		esc_url( $funding['url'] ),
		esc_html( $funding['text'] )
	);
}

/**
 * Render podcast:transcript tags
 *
 * @param array $transcripts Array of transcript objects
 * @return string HTML output
 */
function podloom_render_transcripts( $transcripts ) {
	if ( empty( $transcripts ) || ! is_array( $transcripts ) ) {
		return '';
	}

	// Check if any .txt transcripts exist and add potential HTML versions
	$has_html = false;
	foreach ( $transcripts as $transcript ) {
		if ( ( $transcript['type'] ?? '' ) === 'text/html' ) {
			$has_html = true;
			break;
		}
	}

	// If no HTML transcript exists, check for .txt files and generate HTML alternatives
	if ( ! $has_html ) {
		$additional_transcripts = array();
		foreach ( $transcripts as $transcript ) {
			$url  = $transcript['url'] ?? '';
			$type = $transcript['type'] ?? '';

			// If this is a text/plain or .txt file, try HTML version
			if ( ( $type === 'text/plain' || strpos( $url, '.txt' ) !== false ) && ! empty( $url ) ) {
				// Generate potential HTML URL by replacing .txt with .html
				$html_url = preg_replace( '/\.txt$/i', '.html', $url );

				// Only add if it's actually different (i.e., URL ended with .txt)
				if ( $html_url !== $url ) {
					$additional_transcripts[] = array(
						'url'      => $html_url,
						'type'     => 'text/html',
						'label'    => $transcript['label'] ?? '',
						'language' => $transcript['language'] ?? '',
					);
				}
			}
		}

		// Add potential HTML transcripts to the array
		if ( ! empty( $additional_transcripts ) ) {
			$transcripts = array_merge( $additional_transcripts, $transcripts );
		}
	}

	// Sort transcripts by format preference: HTML > SRT > VTT > JSON > text/plain > other
	$format_priority = array(
		'text/html'            => 1,
		'application/x-subrip' => 2,
		'text/srt'             => 2,
		'text/vtt'             => 3,
		'application/json'     => 4,
		'text/plain'           => 5,
	);

	usort(
		$transcripts,
		function ( $a, $b ) use ( $format_priority ) {
			$a_priority = $format_priority[ $a['type'] ?? '' ] ?? 999;
			$b_priority = $format_priority[ $b['type'] ?? '' ] ?? 999;
			return $a_priority - $b_priority;
		}
	);

	// Use the first (highest priority) transcript for display
	$primary_transcript = $transcripts[0];

	if ( empty( $primary_transcript['url'] ) ) {
		return '';
	}

	$output = '<div class="podcast20-transcripts">';

	// Transcript format button - include all transcripts as fallbacks
	$output .= '<div class="transcript-formats">';
	$output .= sprintf(
		'<button class="transcript-format-button" data-url="%s" data-type="%s" data-transcripts="%s">
            <svg class="podcast20-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M14 4.5V14a2 2 0 01-2 2H4a2 2 0 01-2-2V2a2 2 0 012-2h5.5L14 4.5zm-3 0A1.5 1.5 0 019.5 3V1H4a1 1 0 00-1 1v12a1 1 0 001 1h8a1 1 0 001-1V4.5h-2z"/>
                <path d="M3 9.5h10v1H3v-1zm0 2h10v1H3v-1z"/>
            </svg>
            <span>%s</span>
        </button>',
		esc_url( $primary_transcript['url'] ),
		esc_attr( $primary_transcript['type'] ?? 'text/plain' ),
		esc_attr( wp_json_encode( $transcripts ) ),
		esc_html__( 'Click for Transcript', 'podloom-podcast-player' )
	);

	// Fallback link for no-JS - use same external link icon as chapters
	$output .= sprintf(
		' <a href="%s" target="_blank" rel="noopener noreferrer" class="transcript-external-link" title="%s">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                <path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>
                <path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>
            </svg>
        </a>',
		esc_url( $primary_transcript['url'] ),
		esc_attr__( 'Open transcript in new tab', 'podloom-podcast-player' )
	);

	$output .= '</div>'; // .transcript-formats

	// Transcript viewer (hidden by default)
	$output .= '<div class="transcript-viewer" style="display:none;">';
	$output .= '<div class="transcript-content"></div>';
	$output .= '<button class="transcript-close">' . esc_html__( 'Close', 'podloom-podcast-player' ) . '</button>';
	$output .= '</div>'; // .transcript-viewer

	$output .= '</div>'; // .podcast20-transcripts

	return $output;
}

/**
 * Render podcast:person tags
 *
 * @param array $people Array of person objects
 * @return string HTML output
 */
function podloom_render_people( $people ) {
	if ( empty( $people ) || ! is_array( $people ) ) {
		return '';
	}

	$output  = '<div class="podcast20-people">';
	$output .= '<h4 class="podcast20-heading">' . esc_html__( 'Credits', 'podloom-podcast-player' ) . '</h4>';
	$output .= '<div class="podcast20-people-list">';

	foreach ( $people as $person ) {
		if ( empty( $person['name'] ) ) {
			continue;
		}

		$output .= '<div class="podcast20-person">';

		// Person image
		if ( ! empty( $person['img'] ) ) {
			$output .= sprintf(
				'<img src="%s" alt="%s" class="podcast20-person-img">',
				esc_url( $person['img'] ),
				esc_attr( $person['name'] )
			);
		} else {
			// Default avatar icon
			$output .= '<div class="podcast20-person-avatar">
                <svg width="40" height="40" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M11 6a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path d="M2 0a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V2a2 2 0 00-2-2H2zm12 1a1 1 0 011 1v12a1 1 0 01-1 1v-1c0-1-1-4-6-4s-6 3-6 4v1a1 1 0 01-1-1V2a1 1 0 011-1h12z"/>
                </svg>
            </div>';
		}

		$output .= '<div class="podcast20-person-info">';

		// Role
		if ( ! empty( $person['role'] ) ) {
			$output .= sprintf(
				'<span class="podcast20-person-role">%s</span>',
				esc_html( ucfirst( $person['role'] ) )
			);
		}

		// Name (linked or plain text)
		if ( ! empty( $person['href'] ) ) {
			$output .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-person-name">%s</a>',
				esc_url( $person['href'] ),
				esc_html( $person['name'] )
			);
		} else {
			$output .= sprintf(
				'<span class="podcast20-person-name">%s</span>',
				esc_html( $person['name'] )
			);
		}

		$output .= '</div>'; // .podcast20-person-info
		$output .= '</div>'; // .podcast20-person
	}

	$output .= '</div></div>'; // .podcast20-people-list and .podcast20-people

	return $output;
}

/**
 * Render podcast:chapters tag
 *
 * @param array $chapters Chapters data
 * @return string HTML output
 */
function podloom_render_chapters( $chapters ) {
	if ( empty( $chapters ) ) {
		return '';
	}

	// If no chapters array is available, show link to chapters JSON
	if ( empty( $chapters['chapters'] ) || ! is_array( $chapters['chapters'] ) ) {
		if ( ! empty( $chapters['url'] ) ) {
			return sprintf(
				'<div class="podcast20-chapters">
                    <a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-chapters-link">
                        <svg class="podcast20-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M1 2.5A1.5 1.5 0 012.5 1h3A1.5 1.5 0 017 2.5v3A1.5 1.5 0 015.5 7h-3A1.5 1.5 0 011 5.5v-3zm8 0A1.5 1.5 0 0110.5 1h3A1.5 1.5 0 0115 2.5v3A1.5 1.5 0 0113.5 7h-3A1.5 1.5 0 019 5.5v-3zm-8 8A1.5 1.5 0 012.5 9h3A1.5 1.5 0 017 10.5v3A1.5 1.5 0 15.5 15h-3A1.5 1.5 0 011 13.5v-3zm8 0A1.5 1.5 0 0110.5 9h3a1.5 1.5 0 011.5 1.5v3a1.5 1.5 0 01-1.5 1.5h-3A1.5 1.5 0 019 13.5v-3z"/>
                        </svg>
                        <span>%s</span>
                    </a>
                </div>',
				esc_url( $chapters['url'] ),
				esc_html__( 'View Chapters', 'podloom-podcast-player' )
			);
		}
		return '';
	}

	// Render full chapter list
	$output  = '<div class="podcast20-chapters-list">';
	$output .= '<h4 class="chapters-heading">' . esc_html__( 'Chapters', 'podloom-podcast-player' ) . '</h4>';

	foreach ( $chapters['chapters'] as $chapter ) {
		$start_time     = $chapter['startTime'];
		$formatted_time = podloom_format_timestamp( $start_time );
		$title          = $chapter['title'];

		$output .= '<div class="chapter-item" data-start-time="' . esc_attr( $start_time ) . '">';

		// Chapter image
		if ( ! empty( $chapter['img'] ) ) {
			$output .= sprintf(
				'<img src="%s" alt="%s" class="chapter-img" loading="lazy" />',
				esc_url( $chapter['img'] ),
				esc_attr( $title )
			);
		} else {
			// Placeholder if no image
			$output .= '<div class="chapter-img-placeholder"></div>';
		}

		// Chapter info
		$output .= '<div class="chapter-info">';
		$output .= '<button class="chapter-timestamp" data-start-time="' . esc_attr( $start_time ) . '">';
		$output .= esc_html( $formatted_time );
		$output .= '</button>';

		// Chapter title (always a span, never a link)
		$output .= '<span class="chapter-title">' . esc_html( $title );

		// If chapter has a URL, add external link icon
		if ( ! empty( $chapter['url'] ) ) {
			$output .= sprintf(
				' <a href="%s" target="_blank" rel="noopener noreferrer" class="chapter-external-link" title="%s">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-left: 4px;">
                        <path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>
                        <path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>
                    </svg>
                </a>',
				esc_url( $chapter['url'] ),
				esc_attr__( 'Open chapter link', 'podloom-podcast-player' )
			);
		}

		$output .= '</span>';

		$output .= '</div>'; // .chapter-info
		$output .= '</div>'; // .chapter-item
	}

	$output .= '</div>'; // .podcast20-chapters-list

	return $output;
}
