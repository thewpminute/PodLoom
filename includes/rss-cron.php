<?php
/**
 * RSS Feed Cron Jobs
 *
 * @package PodLoom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress cron hook to refresh RSS feed in background
 *
 * @param string $feed_id Feed ID to refresh
 */
function podloom_cron_refresh_rss_feed($feed_id) {
    Podloom_RSS::refresh_feed($feed_id);
}
add_action('podloom_refresh_rss_feed', 'podloom_cron_refresh_rss_feed');
