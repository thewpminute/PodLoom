<?php
/**
 * Block Registration
 *
 * Handles Gutenberg block registration and initialization.
 *
 * @package PodLoom
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Blocks
 *
 * Manages Gutenberg block registration.
 */
class Podloom_Blocks {

	/**
	 * Initialize block hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Register all Gutenberg blocks.
	 */
	public static function register_blocks() {
		self::register_data_store();
		self::register_episode_block();
		self::register_subscribe_block();
	}

	/**
	 * Register the shared data store.
	 */
	private static function register_data_store() {
		$store_asset_file = PODLOOM_PLUGIN_DIR . 'build/store/index.asset.php';
		$store_asset      = file_exists( $store_asset_file ) ? require $store_asset_file : array(
			'dependencies' => array( 'wp-data' ),
			'version'      => PODLOOM_PLUGIN_VERSION,
		);

		wp_register_script(
			'podloom-data-store',
			PODLOOM_PLUGIN_URL . 'build/store/index.js',
			$store_asset['dependencies'],
			$store_asset['version'],
			false
		);
	}

	/**
	 * Register the episode block.
	 */
	private static function register_episode_block() {
		// Register episode block from build folder.
		$episode_asset_file = PODLOOM_PLUGIN_DIR . 'build/episode-block/index.asset.php';
		$episode_asset      = file_exists( $episode_asset_file ) ? require $episode_asset_file : array(
			'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
			'version'      => PODLOOM_PLUGIN_VERSION,
		);

		// Add data store as a dependency.
		$episode_dependencies   = $episode_asset['dependencies'];
		$episode_dependencies[] = 'podloom-data-store';

		wp_register_script(
			'podloom-episode-block-editor',
			PODLOOM_PLUGIN_URL . 'build/episode-block/index.js',
			$episode_dependencies,
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
		// Note: editorScript is defined in block.json and references the
		// 'podloom-episode-block-editor' handle registered above.
		register_block_type(
			PODLOOM_PLUGIN_DIR . 'build/episode-block',
			array(
				'render_callback' => 'podloom_render_block',
			)
		);
	}

	/**
	 * Register the subscribe block.
	 */
	private static function register_subscribe_block() {
		// Register subscribe block from build folder.
		$subscribe_asset_file = PODLOOM_PLUGIN_DIR . 'build/subscribe-block/index.asset.php';
		$subscribe_asset      = file_exists( $subscribe_asset_file ) ? require $subscribe_asset_file : array(
			'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data' ),
			'version'      => PODLOOM_PLUGIN_VERSION,
		);

		// Add data store as a dependency.
		$subscribe_dependencies   = $subscribe_asset['dependencies'];
		$subscribe_dependencies[] = 'podloom-data-store';

		wp_register_script(
			'podloom-subscribe-block-editor',
			PODLOOM_PLUGIN_URL . 'build/subscribe-block/index.js',
			$subscribe_dependencies,
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
}

// Initialize blocks.
Podloom_Blocks::init();
