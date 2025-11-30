<?php
/**
 * Migration Handler
 *
 * Handles one-time migration from transistor_ to podloom_ options.
 *
 * @package PodLoom
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Migration
 *
 * Handles database migrations when upgrading from older versions.
 */
class Podloom_Migration {

	/**
	 * Initialize migration hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'run' ) );
	}

	/**
	 * Run one-time migration from transistor_ to podloom_ options.
	 */
	public static function run() {
		// Check if migration has already run.
		if ( get_option( 'podloom_migration_complete' ) ) {
			return;
		}

		// Map of old to new option names.
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

		// Mark migration as complete.
		update_option( 'podloom_migration_complete', true );
	}
}

// Initialize migration.
Podloom_Migration::init();
