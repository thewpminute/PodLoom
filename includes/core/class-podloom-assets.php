<?php
/**
 * Asset Management
 *
 * Handles enqueuing of frontend and editor scripts and styles.
 *
 * @package PodLoom
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Assets
 *
 * Manages all asset enqueuing for the plugin.
 */
class Podloom_Assets {

	/**
	 * Initialize asset hooks.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_styles' ) );
		add_action( 'wp_footer', array( __CLASS__, 'enqueue_podcast20_assets' ), 5 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_styles' ) );
	}

	/**
	 * Enqueue frontend styles for RSS player (base styles always loaded).
	 */
	public static function enqueue_frontend_styles() {
		// Always enqueue base player styles.
		wp_enqueue_style(
			'podloom-rss-player',
			PODLOOM_PLUGIN_URL . 'assets/css/rss-player' . PODLOOM_SCRIPT_SUFFIX . '.css',
			array(),
			PODLOOM_PLUGIN_VERSION
		);

		$custom_css = self::get_dynamic_css();
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

	/**
	 * Conditionally enqueue Podcasting 2.0 assets in footer.
	 * Only loads if P2.0 content was rendered on the page.
	 */
	public static function enqueue_podcast20_assets() {
		global $podloom_has_podcast20_content;

		// Only load P2.0 assets if content exists.
		if ( ! $podloom_has_podcast20_content ) {
			echo '<!-- PodLoom: No P2.0 content detected, assets not loaded -->';
			return;
		}

		// Enqueue Podcasting 2.0 styles.
		wp_enqueue_style(
			'podloom-podcast20',
			PODLOOM_PLUGIN_URL . 'assets/css/podcast20-styles' . PODLOOM_SCRIPT_SUFFIX . '.css',
			array(),
			PODLOOM_PLUGIN_VERSION
		);

		// Enqueue Podcasting 2.0 chapter navigation script.
		wp_enqueue_script(
			'podloom-podcast20-player',
			PODLOOM_PLUGIN_URL . 'assets/js/podcast20-player' . PODLOOM_SCRIPT_SUFFIX . '.js',
			array(),
			PODLOOM_PLUGIN_VERSION,
			true // Load in footer.
		);

		// Pass AJAX URL to the script for transcript proxy.
		wp_localize_script(
			'podloom-podcast20-player',
			'podloomTranscript',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);

		// Pass AJAX URL for background image caching (only if enabled).
		if ( Podloom_Image_Cache::is_enabled() ) {
			wp_localize_script(
				'podloom-podcast20-player',
				'podloomImageCache',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'podloom_image_cache_nonce' ),
				)
			);
		}

		// Pass AJAX URL for playlist P2.0 data fetching.
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

	/**
	 * Enqueue editor styles and scripts for RSS player (uses same as frontend).
	 */
	public static function enqueue_editor_styles() {
		// Base RSS player styles.
		wp_enqueue_style(
			'podloom-rss-player-editor',
			PODLOOM_PLUGIN_URL . 'assets/css/rss-player' . PODLOOM_SCRIPT_SUFFIX . '.css',
			array(),
			PODLOOM_PLUGIN_VERSION
		);

		// Podcasting 2.0 styles (for tabs, chapters, transcripts, etc.).
		wp_enqueue_style(
			'podloom-podcast20-editor',
			PODLOOM_PLUGIN_URL . 'assets/css/podcast20-styles' . PODLOOM_SCRIPT_SUFFIX . '.css',
			array( 'podloom-rss-player-editor' ),
			PODLOOM_PLUGIN_VERSION
		);

		// Podcasting 2.0 JavaScript (for tab switching, chapter navigation, transcript loading).
		wp_enqueue_script(
			'podloom-podcast20-player-editor',
			PODLOOM_PLUGIN_URL . 'assets/js/podcast20-player' . PODLOOM_SCRIPT_SUFFIX . '.js',
			array(),
			PODLOOM_PLUGIN_VERSION,
			true
		);

		// Pass AJAX URL to the script for transcript proxy.
		wp_localize_script(
			'podloom-podcast20-player-editor',
			'podloomTranscript',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);

		// Pass AJAX URL for background image caching (only if enabled).
		if ( Podloom_Image_Cache::is_enabled() ) {
			wp_localize_script(
				'podloom-podcast20-player-editor',
				'podloomImageCache',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'podloom_image_cache_nonce' ),
				)
			);
		}

		// Add dynamic CSS based on user settings (colors, fonts, etc.).
		$custom_css = self::get_dynamic_css();
		if ( $custom_css ) {
			wp_add_inline_style( 'podloom-rss-player-editor', $custom_css );
		}
	}

	/**
	 * Generate dynamic typography CSS for RSS player.
	 *
	 * @return string CSS output.
	 */
	public static function get_dynamic_css() {
		$minimal_styling = get_option( 'podloom_rss_minimal_styling', false );
		$player_height   = get_option( 'podloom_rss_player_height', 600 );

		// In minimal styling mode, only output structural CSS (height, flex layout).
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
				absint( $player_height ) + 100 // Slightly taller on mobile.
			);
		}

		$typo     = podloom_get_rss_typography_styles();
		$bg_color = get_option( 'podloom_rss_background_color', '#f9f9f9' );

		// Get border settings.
		$border_color  = get_option( 'podloom_rss_border_color', '#dddddd' );
		$border_width  = get_option( 'podloom_rss_border_width', '1px' );
		$border_style  = get_option( 'podloom_rss_border_style', 'solid' );
		$border_radius = get_option( 'podloom_rss_border_radius', '8px' );

		// Get funding button settings.
		$funding_font_family      = get_option( 'podloom_rss_funding_font_family', 'inherit' );
		$funding_font_size        = get_option( 'podloom_rss_funding_font_size', '13px' );
		$funding_background_color = get_option( 'podloom_rss_funding_background_color', '#2271b1' );
		$funding_text_color       = get_option( 'podloom_rss_funding_text_color', '#ffffff' );
		$funding_border_radius    = get_option( 'podloom_rss_funding_border_radius', '4px' );

		// Calculate theme-aware colors for tabs and P2.0 elements.
		$theme_colors = podloom_calculate_theme_colors( $bg_color );

		// Override accent if saved.
		$saved_accent = get_option( 'podloom_rss_accent_color' );
		if ( ! empty( $saved_accent ) ) {
			$theme_colors['accent'] = $saved_accent;
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
			// Border styles.
			esc_attr( $border_width ),
			esc_attr( $border_style ),
			esc_attr( $border_color ),
			esc_attr( $border_radius ),
			absint( $player_height ),
			// Funding button styles.
			esc_attr( $funding_font_family ),
			esc_attr( $funding_font_size ),
			esc_attr( $funding_background_color ),
			esc_attr( $funding_text_color ),
			esc_attr( $funding_border_radius ),
			// Funding button hover (darken background slightly).
			esc_attr( podloom_adjust_color_brightness( $funding_background_color, -15 ) ),
			esc_attr( $funding_text_color ),
			absint( $player_height + 100 ), // Mobile height - add 100px for stacked layout.
			// Mobile tab nav background.
			esc_attr( $theme_colors['content_bg'] ),
			// Tab colors.
			esc_attr( $theme_colors['tab_text'] ),
			esc_attr( $theme_colors['tab_text_hover'] ),
			esc_attr( $theme_colors['tab_bg_hover'] ),
			esc_attr( $theme_colors['tab_active_text'] ),
			esc_attr( $theme_colors['tab_active_bg'] ),
			esc_attr( $theme_colors['tab_border'] ),
			// P2.0 content colors.
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
			// P2.0 headings.
			esc_attr( $theme_colors['text_primary'] ),
			// Person cards.
			esc_attr( $theme_colors['card_bg'] ),
			esc_attr( $theme_colors['card_border'] ),
			esc_attr( $theme_colors['text_primary'] ),
			esc_attr( $theme_colors['avatar_bg'] ),
			esc_attr( $theme_colors['avatar_text'] ),
			// Chapter items.
			esc_attr( $theme_colors['card_bg'] ),
			esc_attr( $theme_colors['card_bg_hover'] ),
			esc_attr( $theme_colors['text_primary'] ),
			esc_attr( $theme_colors['content_bg'] ),
			// Transcript viewer.
			esc_attr( $theme_colors['card_bg'] ),
			esc_attr( $theme_colors['card_border'] ),
			esc_attr( $theme_colors['text_primary'] ),
			// Transcript format buttons.
			esc_attr( $theme_colors['button_bg'] ),
			esc_attr( $theme_colors['button_border'] ),
			esc_attr( $theme_colors['button_text'] ),
			esc_attr( $theme_colors['button_bg_hover'] ),
			// Transcript format button active.
			esc_attr( $theme_colors['accent'] ),
			esc_attr( $theme_colors['accent_text'] ),
			esc_attr( $theme_colors['accent'] ),
			// Transcript close button.
			esc_attr( $theme_colors['button_bg'] ),
			esc_attr( $theme_colors['button_border'] ),
			esc_attr( $theme_colors['text_secondary'] ),
			esc_attr( $theme_colors['button_bg_hover'] ),
			// Transcript error/loading.
			esc_attr( $theme_colors['warning_bg'] ),
			esc_attr( $theme_colors['warning_border'] ),
			esc_attr( $theme_colors['warning_text'] ),
			esc_attr( $theme_colors['text_muted'] ),
			// External links.
			esc_attr( $theme_colors['text_secondary'] ),
			// Skip buttons.
			esc_attr( $theme_colors['text_secondary'] ),
			esc_attr( $theme_colors['button_border'] ),
			esc_attr( $theme_colors['tab_bg_hover'] ),
			// Typography.
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
}

// Initialize assets.
Podloom_Assets::init();

/**
 * Get dynamic CSS for RSS player.
 *
 * Wrapper function for backwards compatibility.
 *
 * @return string CSS output.
 */
function podloom_get_rss_dynamic_css() {
	return Podloom_Assets::get_dynamic_css();
}
