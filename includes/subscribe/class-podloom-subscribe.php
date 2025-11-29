<?php
/**
 * Subscribe Links Data Management Class
 *
 * Handles storage and retrieval of podcast subscribe links.
 *
 * @package PodLoom
 * @since 2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Subscribe
 *
 * Manages subscribe links data for podcasts.
 */
class Podloom_Subscribe {

	/**
	 * Option name for storing subscribe links.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'podloom_subscribe_links';

	/**
	 * Get all stored subscribe links.
	 *
	 * @return array All subscribe links keyed by source ID.
	 */
	public static function get_all_links() {
		$links = get_option( self::OPTION_NAME, array() );
		return is_array( $links ) ? $links : array();
	}

	/**
	 * Get subscribe links for a specific podcast.
	 *
	 * @param string $source_id Source identifier (e.g., 'transistor:123' or 'rss:abc').
	 * @return array Subscribe links for the podcast.
	 */
	public static function get_links( $source_id ) {
		$all_links = self::get_all_links();
		return isset( $all_links[ $source_id ] ) ? $all_links[ $source_id ] : array();
	}

	/**
	 * Save subscribe links for a specific podcast.
	 *
	 * @param string $source_id Source identifier.
	 * @param array  $links     Subscribe links (platform => url).
	 * @return bool True on success.
	 */
	public static function save_links( $source_id, $links ) {
		$all_links = self::get_all_links();

		// Sanitize links.
		$sanitized_links = array();
		foreach ( $links as $platform => $url ) {
			$platform = sanitize_key( $platform );
			$url      = trim( $url );
			if ( ! empty( $url ) ) {
				$sanitized_links[ $platform ] = esc_url_raw( $url );
			}
		}

		$all_links[ $source_id ] = $sanitized_links;

		return update_option( self::OPTION_NAME, $all_links );
	}

	/**
	 * Delete subscribe links for a specific podcast.
	 *
	 * @param string $source_id Source identifier.
	 * @return bool True on success.
	 */
	public static function delete_links( $source_id ) {
		$all_links = self::get_all_links();

		if ( isset( $all_links[ $source_id ] ) ) {
			unset( $all_links[ $source_id ] );
			return update_option( self::OPTION_NAME, $all_links );
		}

		return true;
	}

	/**
	 * Get all configured podcasts (Transistor shows + RSS feeds).
	 *
	 * @return array List of podcasts with source IDs and names.
	 */
	public static function get_all_podcasts() {
		$podcasts = array();

		// Get Transistor shows.
		$api_key = get_option( 'podloom_api_key', '' );
		if ( ! empty( $api_key ) ) {
			$api   = new Podloom_API( $api_key );
			$shows = $api->get_shows();

			if ( ! empty( $shows['data'] ) && is_array( $shows['data'] ) ) {
				foreach ( $shows['data'] as $show ) {
					if ( isset( $show['id'], $show['attributes']['title'] ) ) {
						$podcasts[] = array(
							'source_id'   => 'transistor:' . $show['id'],
							'name'        => $show['attributes']['title'],
							'type'        => 'transistor',
							'transistor'  => $show,
						);
					}
				}
			}
		}

		// Get RSS feeds.
		$rss_feeds = Podloom_RSS::get_feeds();
		if ( ! empty( $rss_feeds ) && is_array( $rss_feeds ) ) {
			foreach ( $rss_feeds as $feed_id => $feed ) {
				if ( ! empty( $feed['valid'] ) && isset( $feed['name'] ) ) {
					$podcasts[] = array(
						'source_id' => 'rss:' . $feed_id,
						'name'      => $feed['name'],
						'type'      => 'rss',
						'feed'      => $feed,
					);
				}
			}
		}

		return $podcasts;
	}

	/**
	 * Sync subscribe links from Transistor API for a show.
	 *
	 * @param string $show_id Transistor show ID.
	 * @return array|WP_Error Synced links or error.
	 */
	public static function sync_transistor_links( $show_id ) {
		$api_key = get_option( 'podloom_api_key', '' );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'Transistor API key not configured.', 'podloom-podcast-player' ) );
		}

		$api  = new Podloom_API( $api_key );
		$show = $api->get_show( $show_id );

		if ( empty( $show['data']['attributes'] ) ) {
			return new WP_Error( 'show_not_found', __( 'Show not found.', 'podloom-podcast-player' ) );
		}

		$attributes = $show['data']['attributes'];
		$links      = array();

		// Map Transistor API fields to platform keys.
		$transistor_platforms = Podloom_Subscribe_Icons::get_transistor_platforms();

		foreach ( $transistor_platforms as $platform_key => $platform ) {
			$transistor_key = $platform['transistor_key'];
			if ( ! empty( $attributes[ $transistor_key ] ) ) {
				$links[ $platform_key ] = $attributes[ $transistor_key ];
			}
		}

		// Save the synced links.
		$source_id = 'transistor:' . $show_id;
		self::save_links( $source_id, $links );

		return $links;
	}

	/**
	 * Get non-empty links for a podcast (for rendering).
	 *
	 * @param string $source_id Source identifier.
	 * @return array Links with URLs only (non-empty).
	 */
	public static function get_active_links( $source_id ) {
		$links        = self::get_links( $source_id );
		$active_links = array();

		foreach ( $links as $platform => $url ) {
			if ( ! empty( $url ) ) {
				$active_links[ $platform ] = $url;
			}
		}

		return $active_links;
	}
}
