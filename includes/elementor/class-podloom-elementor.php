<?php
/**
 * Elementor Integration for PodLoom
 *
 * Handles registration of Elementor widgets and assets.
 *
 * @package PodLoom
 * @since 2.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Elementor
 *
 * Main class for Elementor integration.
 */
class Podloom_Elementor {

	/**
	 * Minimum Elementor Version
	 *
	 * @var string Minimum Elementor version required.
	 */
	const MINIMUM_ELEMENTOR_VERSION = '3.0.0';

	/**
	 * Instance
	 *
	 * @var Podloom_Elementor The single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Get Instance
	 *
	 * Ensures only one instance of the class is loaded.
	 *
	 * @return Podloom_Elementor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Check if Elementor is installed and activated.
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Check for minimum Elementor version.
		if ( ! version_compare( ELEMENTOR_VERSION, self::MINIMUM_ELEMENTOR_VERSION, '>=' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_elementor_version' ) );
			return;
		}

		// Register widgets.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );

		// Register editor scripts.
		add_action( 'elementor/editor/before_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );

		// Register preview styles (for the iframe preview).
		add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_preview_styles' ) );

		// Register preview scripts (for the iframe preview).
		add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_preview_scripts' ) );

		// Register widget category.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_category' ) );
	}

	/**
	 * Admin notice for minimum Elementor version
	 */
	public function admin_notice_minimum_elementor_version() {
		if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$message = sprintf(
			/* translators: 1: Plugin name 2: Elementor 3: Required Elementor version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'podloom-podcast-player' ),
			'<strong>' . esc_html__( 'PodLoom Elementor Widget', 'podloom-podcast-player' ) . '</strong>',
			'<strong>' . esc_html__( 'Elementor', 'podloom-podcast-player' ) . '</strong>',
			self::MINIMUM_ELEMENTOR_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', wp_kses_post( $message ) );
	}

	/**
	 * Register widget category
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_widget_category( $elements_manager ) {
		$elements_manager->add_category(
			'podloom',
			array(
				'title' => esc_html__( 'PodLoom', 'podloom-podcast-player' ),
				'icon'  => 'eicon-play',
			)
		);
	}

	/**
	 * Register widgets
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		// Include widget files.
		require_once PODLOOM_PLUGIN_DIR . 'includes/elementor/widgets/class-podloom-elementor-widget.php';

		// Register widgets.
		$widgets_manager->register( new Podloom_Elementor_Widget() );
	}

	/**
	 * Enqueue editor scripts
	 *
	 * Scripts loaded only in the Elementor editor.
	 */
	public function enqueue_editor_scripts() {
		// Get shows and feeds for the editor.
		$shows_data = $this->get_sources_data();

		wp_enqueue_script(
			'podloom-elementor-editor',
			PODLOOM_PLUGIN_URL . 'includes/elementor/assets/js/elementor-editor.js',
			array( 'elementor-editor', 'jquery' ),
			PODLOOM_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'podloom-elementor-editor',
			'podloomElementor',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'podloom_nonce' ),
				'sources'          => $shows_data['sources'],
				'hasApiKey'        => ! empty( get_option( 'podloom_api_key' ) ),
				'defaultShow'      => get_option( 'podloom_default_show', '' ),
				'strings'          => array(
					'selectSource'       => esc_html__( '-- Select a source --', 'podloom-podcast-player' ),
					'transistorHeader'   => esc_html__( 'Transistor.fm', 'podloom-podcast-player' ),
					'rssHeader'          => esc_html__( 'RSS Feeds', 'podloom-podcast-player' ),
					'selectEpisode'      => esc_html__( '-- Select an episode --', 'podloom-podcast-player' ),
					'loadingEpisodes'    => esc_html__( 'Loading episodes...', 'podloom-podcast-player' ),
					'loadMore'           => esc_html__( 'Load More Episodes...', 'podloom-podcast-player' ),
					'noEpisodes'         => esc_html__( 'No episodes found', 'podloom-podcast-player' ),
					'errorLoading'       => esc_html__( 'Error loading episodes', 'podloom-podcast-player' ),
					'latestEpisodeMode'  => esc_html__( 'Will display the latest episode', 'podloom-podcast-player' ),
					'playlistMode'       => esc_html__( 'Will display a playlist', 'podloom-podcast-player' ),
					'configureSettings'  => esc_html__( 'Please configure your Transistor API key or add RSS feeds in PodLoom settings.', 'podloom-podcast-player' ),
				),
			)
		);
	}

	/**
	 * Get sources data for the editor
	 *
	 * @return array Sources data including Transistor shows and RSS feeds.
	 */
	private function get_sources_data() {
		$sources = array();

		// Get Transistor shows.
		$api_key = get_option( 'podloom_api_key' );
		if ( ! empty( $api_key ) ) {
			$shows = podloom_get_shows();
			if ( ! empty( $shows['data'] ) ) {
				foreach ( $shows['data'] as $show ) {
					$sources[] = array(
						'type'  => 'transistor',
						'id'    => $show['id'],
						'name'  => $show['attributes']['title'],
						'slug'  => $show['attributes']['slug'],
						'value' => 'transistor:' . $show['id'],
					);
				}
			}
		}

		// Get RSS feeds.
		$rss_feeds = Podloom_RSS::get_feeds();
		if ( ! empty( $rss_feeds ) ) {
			foreach ( $rss_feeds as $feed_id => $feed ) {
				if ( ! empty( $feed['valid'] ) ) {
					$sources[] = array(
						'type'  => 'rss',
						'id'    => $feed_id,
						'name'  => $feed['name'],
						'value' => 'rss:' . $feed_id,
					);
				}
			}
		}

		return array(
			'sources' => $sources,
		);
	}

	/**
	 * Enqueue preview styles
	 *
	 * Styles loaded in the Elementor preview iframe.
	 */
	public function enqueue_preview_styles() {
		// Base RSS player styles.
		wp_enqueue_style(
			'podloom-rss-player',
			PODLOOM_PLUGIN_URL . 'assets/css/rss-player.css',
			array(),
			PODLOOM_PLUGIN_VERSION
		);

		// Add dynamic CSS.
		$custom_css = podloom_get_rss_dynamic_css();
		if ( $custom_css ) {
			wp_add_inline_style( 'podloom-rss-player', $custom_css );
		}

		// Podcasting 2.0 styles (for tabs, chapters, transcripts, etc.).
		wp_enqueue_style(
			'podloom-podcast20',
			PODLOOM_PLUGIN_URL . 'assets/css/podcast20-styles.css',
			array( 'podloom-rss-player' ),
			PODLOOM_PLUGIN_VERSION
		);
	}

	/**
	 * Enqueue preview scripts
	 *
	 * Scripts loaded in the Elementor preview iframe.
	 */
	public function enqueue_preview_scripts() {
		// Podcasting 2.0 JavaScript (for tab switching, chapter navigation, transcript loading).
		wp_enqueue_script(
			'podloom-podcast20-player',
			PODLOOM_PLUGIN_URL . 'assets/js/podcast20-player.js',
			array(),
			PODLOOM_PLUGIN_VERSION,
			true
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
				)
			);
		}
	}
}

// Initialize Elementor integration.
add_action( 'plugins_loaded', array( 'Podloom_Elementor', 'get_instance' ) );
