<?php
/**
 * RSS Feed Cron Jobs
 *
 * @package PodLoom
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress cron hook to refresh RSS feed in background
 *
 * @param string $feed_id Feed ID to refresh
 */
function podloom_cron_refresh_rss_feed( $feed_id ) {
	Podloom_RSS::refresh_feed( $feed_id );
}
add_action( 'podloom_refresh_rss_feed', 'podloom_cron_refresh_rss_feed' );

/**
 * Refresh all RSS feeds in background
 *
 * This runs on a recurring schedule to keep caches warm,
 * preventing slow page loads from synchronous feed fetches.
 */
function podloom_cron_refresh_all_feeds() {
	$feeds = Podloom_RSS::get_feeds();

	if ( empty( $feeds ) ) {
		return;
	}

	foreach ( $feeds as $feed_id => $feed_data ) {
		// Only refresh valid feeds
		if ( ! empty( $feed_data['valid'] ) ) {
			Podloom_RSS::refresh_feed( $feed_id );
		}
	}
}
add_action( 'podloom_refresh_all_feeds', 'podloom_cron_refresh_all_feeds' );

/**
 * Schedule the recurring feed refresh cron job
 *
 * Runs at 2/3 of the cache duration to ensure feeds are refreshed
 * before the cache expires.
 */
function podloom_schedule_feed_refresh() {
	if ( ! wp_next_scheduled( 'podloom_refresh_all_feeds' ) ) {
		// Get cache duration and schedule refresh at 2/3 of that interval
		$cache_duration    = get_option( 'podloom_cache_duration', 21600 ); // Default: 6 hours
		$refresh_interval  = max( 1800, intval( $cache_duration * 2 / 3 ) ); // Minimum 30 minutes

		wp_schedule_event( time() + $refresh_interval, 'podloom_feed_refresh', 'podloom_refresh_all_feeds' );
	}
}
add_action( 'wp', 'podloom_schedule_feed_refresh' );

/**
 * Register custom cron schedule based on cache duration
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified schedules.
 */
function podloom_cron_schedules( $schedules ) {
	$cache_duration   = get_option( 'podloom_cache_duration', 21600 ); // Default: 6 hours
	$refresh_interval = max( 1800, intval( $cache_duration * 2 / 3 ) ); // Minimum 30 minutes

	$schedules['podloom_feed_refresh'] = array(
		'interval' => $refresh_interval,
		'display'  => sprintf(
			/* translators: %d: number of hours */
			__( 'PodLoom Feed Refresh (every %d hours)', 'podloom-podcast-player' ),
			round( $refresh_interval / 3600, 1 )
		),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'podloom_cron_schedules' );

/**
 * Clear scheduled cron on plugin deactivation
 */
function podloom_clear_feed_refresh_cron() {
	$timestamp = wp_next_scheduled( 'podloom_refresh_all_feeds' );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, 'podloom_refresh_all_feeds' );
	}
}
register_deactivation_hook( PODLOOM_PLUGIN_FILE, 'podloom_clear_feed_refresh_cron' );
