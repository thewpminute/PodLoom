<?php
/**
 * Persistent RSS Episode Storage
 *
 * Provides database-backed storage for RSS episodes that survives
 * cache clears and server restarts. This serves as a fallback when
 * transient/object cache is empty.
 *
 * @package PodLoom
 * @since   2.16.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persistent storage class for RSS episodes.
 */
class Podloom_RSS_Storage {

	/**
	 * Database table name (without prefix).
	 */
	const TABLE_NAME = 'podloom_episodes';

	/**
	 * Database version for schema migrations.
	 */
	const DB_VERSION = '1.0';

	/**
	 * Create the episodes table on plugin activation.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			feed_id varchar(64) NOT NULL,
			episode_id varchar(64) NOT NULL,
			title text NOT NULL,
			description longtext,
			audio_url text NOT NULL,
			audio_type varchar(100),
			duration varchar(20),
			episode_date datetime,
			image_url text,
			link text,
			podcast20_data longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY feed_episode (feed_id, episode_id),
			KEY feed_id (feed_id),
			KEY episode_date (episode_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'podloom_db_version', self::DB_VERSION );
	}

	/**
	 * Check if the table exists.
	 *
	 * @return bool True if table exists.
	 */
	public static function table_exists() {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
	}

	/**
	 * Store episodes for a feed.
	 *
	 * Uses INSERT ... ON DUPLICATE KEY UPDATE for efficient upserts.
	 *
	 * @param string $feed_id  Feed ID.
	 * @param array  $episodes Array of episode data.
	 * @return int Number of episodes stored.
	 */
	public static function store_episodes( $feed_id, $episodes ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$count      = 0;

		foreach ( $episodes as $episode ) {
			// Generate a unique episode ID from the audio URL.
			$episode_id = md5( $episode['audio_url'] ?? '' );

			// Parse date to MySQL format.
			$episode_date = null;
			if ( ! empty( $episode['date'] ) ) {
				$timestamp = strtotime( $episode['date'] );
				if ( $timestamp ) {
					$episode_date = gmdate( 'Y-m-d H:i:s', $timestamp );
				}
			}

			// Prepare podcast20 data as JSON.
			$podcast20_json = wp_json_encode( $episode['podcast20'] ?? array() );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->replace(
				$table_name,
				array(
					'feed_id'        => $feed_id,
					'episode_id'     => $episode_id,
					'title'          => $episode['title'] ?? '',
					'description'    => $episode['description'] ?? '',
					'audio_url'      => $episode['audio_url'] ?? '',
					'audio_type'     => $episode['audio_type'] ?? '',
					'duration'       => $episode['duration'] ?? '',
					'episode_date'   => $episode_date,
					'image_url'      => $episode['image'] ?? '',
					'link'           => $episode['link'] ?? '',
					'podcast20_data' => $podcast20_json,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false !== $result ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get episodes for a feed from persistent storage.
	 *
	 * @param string $feed_id Feed ID.
	 * @param int    $limit   Maximum episodes to retrieve. Default 100.
	 * @param int    $offset  Offset for pagination. Default 0.
	 * @return array Array of episode data, or empty array if none found.
	 */
	public static function get_episodes( $feed_id, $limit = 100, $offset = 0 ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array();
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `$table_name` WHERE feed_id = %s ORDER BY episode_date DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$feed_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return array();
		}

		// Convert database format back to episode array format.
		return array_map(
			function ( $row ) {
				return array(
					'title'       => $row['title'],
					'description' => $row['description'],
					'audio_url'   => $row['audio_url'],
					'audio_type'  => $row['audio_type'],
					'duration'    => $row['duration'],
					'date'        => $row['episode_date'],
					'image'       => $row['image_url'],
					'link'        => $row['link'],
					'podcast20'   => json_decode( $row['podcast20_data'], true ) ?: array(),
				);
			},
			$results
		);
	}

	/**
	 * Get episode count for a feed.
	 *
	 * @param string $feed_id Feed ID.
	 * @return int Number of episodes.
	 */
	public static function get_episode_count( $feed_id ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return 0;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `$table_name` WHERE feed_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$feed_id
			)
		);
	}

	/**
	 * Delete all episodes for a feed.
	 *
	 * @param string $feed_id Feed ID.
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public static function delete_feed_episodes( $feed_id ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->delete(
			$table_name,
			array( 'feed_id' => $feed_id ),
			array( '%s' )
		);
	}

	/**
	 * Delete all episodes from the table.
	 *
	 * @return int|false Number of rows deleted, or false on error.
	 */
	public static function delete_all_episodes() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return false;
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query( "TRUNCATE TABLE `$table_name`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Drop the table on plugin uninstall.
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `$table_name`" );

		delete_option( 'podloom_db_version' );
	}

	/**
	 * Get storage statistics.
	 *
	 * @return array Stats including total count and size.
	 */
	public static function get_stats() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array(
				'total_episodes' => 0,
				'total_feeds'    => 0,
				'table_size'     => 0,
			);
		}

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_episodes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$table_name`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_feeds = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT feed_id) FROM `$table_name`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Get table size in bytes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_status = $wpdb->get_row(
			$wpdb->prepare(
				'SHOW TABLE STATUS LIKE %s',
				$wpdb->prefix . self::TABLE_NAME
			)
		);

		$table_size = 0;
		if ( $table_status ) {
			$table_size = ( $table_status->Data_length ?? 0 ) + ( $table_status->Index_length ?? 0 );
		}

		return array(
			'total_episodes' => $total_episodes,
			'total_feeds'    => $total_feeds,
			'table_size'     => $table_size,
		);
	}
}
