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
 *
 * Each feed is checked against its own cache duration (set dynamically
 * based on release patterns) rather than using a single global duration.
 */
function podloom_cron_refresh_all_feeds() {
	$feeds = Podloom_RSS::get_feeds();

	if ( empty( $feeds ) ) {
		return;
	}

	$default_duration = (int) get_option( 'podloom_cache_duration', 21600 );

	foreach ( $feeds as $feed_id => $feed_data ) {
		// Only refresh valid feeds.
		if ( empty( $feed_data['valid'] ) ) {
			continue;
		}

		// Get per-feed cache duration (set by dynamic calculation).
		$feed_duration = isset( $feed_data['cache_duration'] )
			? (int) $feed_data['cache_duration']
			: $default_duration;

		// Check if this feed needs refresh based on its own duration.
		$last_checked      = isset( $feed_data['last_checked'] ) ? (int) $feed_data['last_checked'] : 0;
		$refresh_threshold = $feed_duration * 2 / 3; // Refresh at 2/3 of cache duration.

		if ( ( time() - $last_checked ) >= $refresh_threshold ) {
			Podloom_RSS::refresh_feed( $feed_id );
		}
	}
}
add_action( 'podloom_refresh_all_feeds', 'podloom_cron_refresh_all_feeds' );

/**
 * Schedule the recurring feed refresh cron job
 *
 * Runs at 2/3 of the shortest feed cache duration to ensure
 * all feeds are checked before their cache expires.
 */
function podloom_schedule_feed_refresh() {
	if ( ! wp_next_scheduled( 'podloom_refresh_all_feeds' ) ) {
		// Find the shortest cache duration among all feeds.
		$feeds            = Podloom_RSS::get_feeds();
		$default_duration = (int) get_option( 'podloom_cache_duration', 21600 );
		$min_duration     = $default_duration;

		foreach ( $feeds as $feed_data ) {
			if ( ! empty( $feed_data['cache_duration'] ) ) {
				$min_duration = min( $min_duration, (int) $feed_data['cache_duration'] );
			}
		}

		// Schedule at 2/3 of the shortest duration (minimum 30 minutes).
		$refresh_interval = max( 1800, intval( $min_duration * 2 / 3 ) );

		wp_schedule_event( time() + $refresh_interval, 'podloom_feed_refresh', 'podloom_refresh_all_feeds' );
	}
}
add_action( 'wp', 'podloom_schedule_feed_refresh' );

/**
 * Register custom cron schedule based on shortest feed cache duration
 *
 * @param array $schedules Existing cron schedules.
 * @return array Modified schedules.
 */
function podloom_cron_schedules( $schedules ) {
	// Find the shortest cache duration among all feeds.
	$feeds            = Podloom_RSS::get_feeds();
	$default_duration = (int) get_option( 'podloom_cache_duration', 21600 );
	$min_duration     = $default_duration;

	foreach ( $feeds as $feed_data ) {
		if ( ! empty( $feed_data['cache_duration'] ) ) {
			$min_duration = min( $min_duration, (int) $feed_data['cache_duration'] );
		}
	}

	$refresh_interval = max( 1800, intval( $min_duration * 2 / 3 ) ); // Minimum 30 minutes.

	$schedules['podloom_feed_refresh'] = array(
		'interval' => $refresh_interval,
		'display'  => sprintf(
			/* translators: %s: formatted time interval */
			__( 'PodLoom Feed Refresh (every %s)', 'podloom-podcast-player' ),
			$refresh_interval >= 3600
				? round( $refresh_interval / 3600, 1 ) . ' hours'
				: round( $refresh_interval / 60 ) . ' minutes'
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

/**
 * Cron handler for syncing subscribe links in background.
 *
 * @param array $show_ids Array of Transistor show IDs to sync.
 */
function podloom_cron_sync_subscribe_links( $show_ids ) {
	if ( empty( $show_ids ) || ! is_array( $show_ids ) || ! class_exists( 'Podloom_Subscribe' ) ) {
		return;
	}

	foreach ( $show_ids as $show_id ) {
		if ( ! empty( $show_id ) ) {
			Podloom_Subscribe::sync_transistor_links( $show_id );
		}
	}
}
add_action( 'podloom_sync_subscribe_links', 'podloom_cron_sync_subscribe_links' );
