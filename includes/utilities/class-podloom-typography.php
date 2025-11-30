<?php
/**
 * Typography Utilities
 *
 * Handles typography styles and caching for the RSS player.
 *
 * @package PodLoom
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Typography
 *
 * Manages typography settings and CSS generation.
 */
class Podloom_Typography {

	/**
	 * Get typography styles for RSS elements (with caching).
	 *
	 * @return array Typography styles array.
	 */
	public static function get_styles() {
		// Try to get from cache first (uses object cache if available).
		$cached_styles = podloom_cache_get( 'rss_typography_cache' );
		if ( $cached_styles !== false ) {
			return $cached_styles;
		}

		$elements = array( 'title', 'date', 'duration', 'description' );
		$styles   = array();

		// Default values.
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

		// Load all options at once to avoid N+1 query problem (20+ queries reduced to 1).
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

		// Cache the styles for 1 hour (uses object cache if available).
		podloom_cache_set( 'rss_typography_cache', $styles, 'podloom', HOUR_IN_SECONDS );

		return $styles;
	}

	/**
	 * Clear typography cache (called when settings are saved).
	 */
	public static function clear_cache() {
		// Clear typography cache.
		podloom_cache_delete( 'rss_typography_cache' );

		// Increment render cache version to invalidate all rendered episode HTML in editor.
		// This forces editor to re-render episodes with new typography/display settings
		// WITHOUT clearing heavy episode metadata cache (podcasts, episodes, etc.).
		self::increment_render_cache_version();
	}

	/**
	 * Atomically increment render cache version to avoid race conditions.
	 * Uses direct database query to ensure atomic increment even with concurrent requests.
	 */
	public static function increment_render_cache_version() {
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

		// If option doesn't exist yet, create it.
		if ( $updated === 0 ) {
			add_option( 'podloom_render_cache_version', 1, '', false ); // no autoload.
		}

		// Clear object cache to ensure fresh reads.
		wp_cache_delete( 'podloom_render_cache_version', 'options' );
	}
}

/**
 * Get typography styles for RSS elements (with caching).
 *
 * Wrapper function for backwards compatibility.
 *
 * @return array Typography styles array.
 */
function podloom_get_rss_typography_styles() {
	return Podloom_Typography::get_styles();
}

/**
 * Clear typography cache (called when settings are saved).
 *
 * Wrapper function for backwards compatibility.
 */
function podloom_clear_typography_cache() {
	Podloom_Typography::clear_cache();
}

/**
 * Atomically increment render cache version.
 *
 * Wrapper function for backwards compatibility.
 */
function podloom_increment_render_cache_version() {
	Podloom_Typography::increment_render_cache_version();
}
