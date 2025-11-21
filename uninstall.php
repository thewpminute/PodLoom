<?php
/**
 * Uninstall PodLoom Plugin
 *
 * Fired when the plugin is uninstalled.
 *
 * @package PodLoom
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete all plugin options
 */
function podloom_uninstall_delete_options() {
    // Core plugin options
    delete_option('podloom_api_key');
    delete_option('podloom_default_show');
    delete_option('podloom_enable_cache');
    delete_option('podloom_cache_duration');

    // RSS options
    delete_option('podloom_rss_cache_duration'); // Legacy option (now uses podloom_cache_duration)
    delete_option('podloom_rss_enabled');
    delete_option('podloom_rss_feeds');
    delete_option('podloom_rss_display_artwork');
    delete_option('podloom_rss_display_title');
    delete_option('podloom_rss_display_date');
    delete_option('podloom_rss_display_duration');
    delete_option('podloom_rss_display_description');

    // Podcasting 2.0 display options
    delete_option('podloom_rss_display_funding');
    delete_option('podloom_rss_display_transcripts');
    delete_option('podloom_rss_display_people_hosts');
    delete_option('podloom_rss_display_people_guests');
    delete_option('podloom_rss_display_chapters');

    // RSS typography options
    $elements = ['title', 'date', 'duration', 'description'];
    $properties = ['font_family', 'font_size', 'line_height', 'color', 'font_weight'];

    foreach ($elements as $element) {
        foreach ($properties as $property) {
            delete_option("podloom_rss_{$element}_{$property}");
        }
    }

    delete_option('podloom_rss_background_color');
    delete_option('podloom_rss_description_limit');
    delete_option('podloom_rss_minimal_styling');
    delete_option('podloom_rss_player_height');

    // Cache version option
    delete_option('podloom_render_cache_version');
}

/**
 * Delete all cached data (transients)
 */
function podloom_uninstall_delete_transients() {
    global $wpdb;

    // Delete all Transistor API cache transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting transients during uninstall, caching not applicable
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_podloom_cache_') . '%',
            $wpdb->esc_like('_transient_timeout_podloom_cache_') . '%'
        )
    );

    // Delete all RSS cache transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting transients during uninstall, caching not applicable
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_podloom_rss_episodes_') . '%',
            $wpdb->esc_like('_transient_timeout_podloom_rss_episodes_') . '%'
        )
    );

    // Delete all editor rendered episode cache transients
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting transients during uninstall, caching not applicable
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_podloom_editor_') . '%',
            $wpdb->esc_like('_transient_timeout_podloom_editor_') . '%'
        )
    );
}

/**
 * Delete scheduled cron events
 */
function podloom_uninstall_delete_cron() {
    // Clear any scheduled RSS feed refresh events
    $crons = _get_cron_array();

    if (is_array($crons)) {
        foreach ($crons as $timestamp => $cron) {
            if (isset($cron['podloom_refresh_rss_feed'])) {
                foreach ($cron['podloom_refresh_rss_feed'] as $key => $event) {
                    wp_unschedule_event($timestamp, 'podloom_refresh_rss_feed', $event['args']);
                }
            }
        }
    }
}

// Run uninstall procedures
podloom_uninstall_delete_options();
podloom_uninstall_delete_transients();
podloom_uninstall_delete_cron();

// Clear any remaining caches
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}
