<?php
/**
 * RSS Feed Management Class
 *
 * @package PodLoom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Podloom_RSS
 * Handles RSS feed management, parsing, and caching
 */
class Podloom_RSS {

    /**
     * Check rate limit for AJAX operations
     *
     * @param string $action Action identifier (e.g., 'get_feeds', 'get_episodes')
     * @param int $max_requests Maximum requests allowed
     * @param int $time_window Time window in seconds
     * @return bool True if under limit, false if exceeded
     */
    public static function check_rate_limit($action, $max_requests = 30, $time_window = 60) {
        $rate_key = 'podloom_rss_rate_' . $action . '_' .
                    (is_user_logged_in() ? get_current_user_id() : md5($_SERVER['REMOTE_ADDR'] ?? ''));
        $rate_count = get_transient($rate_key);

        if ($rate_count && $rate_count >= $max_requests) {
            return false;
        }

        // Increment counter
        set_transient($rate_key, ($rate_count ?: 0) + 1, $time_window);
        return true;
    }

    /**
     * Maximum number of episodes to cache per feed
     */
    const MAX_EPISODES = 50;

    /**
     * Get all RSS feeds
     *
     * @return array Array of RSS feeds
     */
    public static function get_feeds() {
        $feeds = get_option('podloom_rss_feeds', array());
        return is_array($feeds) ? $feeds : array();
    }

    /**
     * Get a single RSS feed by ID
     *
     * @param string $feed_id Feed ID
     * @return array|null Feed data or null if not found
     */
    public static function get_feed($feed_id) {
        $feeds = self::get_feeds();
        return isset($feeds[$feed_id]) ? $feeds[$feed_id] : null;
    }

    /**
     * Add a new RSS feed
     *
     * @param string $name Feed name
     * @param string $url Feed URL
     * @param bool $async_refresh Whether to refresh feed asynchronously (default: true)
     * @return array Result with success status and message
     */
    public static function add_feed($name, $url, $async_refresh = true) {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return array(
                'success' => false,
                'message' => 'Invalid URL format'
            );
        }

        // Enhanced SSRF protection: Block localhost
        $parsed_url = wp_parse_url($url);
        if (!isset($parsed_url['host'])) {
            return array(
                'success' => false,
                'message' => 'Invalid URL - missing host'
            );
        }

        $host = $parsed_url['host'];
        $host_lower = strtolower($host);

        // Block obvious localhost references
        if (in_array($host_lower, array('localhost', '127.0.0.1', '::1', '0.0.0.0'), true)) {
            return array(
                'success' => false,
                'message' => 'Localhost URLs are not allowed'
            );
        }

        // Note: We previously performed a DNS resolution here to check for private IPs.
        // However, this caused false positives with some hosting providers and CDNs.
        // We now rely on WordPress's built-in 'reject_unsafe_urls' in wp_remote_get()
        // which is used inside self::validate_feed() below.

        // Warn about HTTP but allow it (many podcast feeds still use HTTP)
        // HTTPS is preferred but not required for compatibility
        if (isset($parsed_url['scheme']) && strtolower($parsed_url['scheme']) === 'http') {
            // Continue but could log a warning
        }

        // Validate feed (this will fetch it once)
        $validation = self::validate_feed($url);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'message' => $validation['message']
            );
        }

        // Generate cryptographically secure unique ID
        $feed_id = 'rss_' . wp_generate_password(32, false);

        // Get feeds
        $feeds = self::get_feeds();

        // Add new feed - initially marked as unchecked
        $feeds[$feed_id] = array(
            'id' => $feed_id,
            'name' => sanitize_text_field($name),
            'url' => esc_url_raw($url),
            'created' => current_time('timestamp'),
            'last_checked' => null,
            'valid' => false // Mark as unvalidated until first refresh completes
        );

        // Save feeds
        update_option('podloom_rss_feeds', $feeds);

        // Auto-enable RSS feeds if this is the first feed being added
        $rss_enabled = get_option('podloom_rss_enabled', false);
        if (!$rss_enabled) {
            update_option('podloom_rss_enabled', true);
        }

        // Always do initial refresh synchronously to ensure feed is validated before use
        // This prevents race condition where invalid feeds could be saved
        $refresh_result = self::refresh_feed($feed_id);

        // Update validation status based on refresh result
        $feeds = self::get_feeds();
        if (isset($feeds[$feed_id])) {
            $feeds[$feed_id]['valid'] = !empty($refresh_result);
            update_option('podloom_rss_feeds', $feeds);
        }

        // For subsequent refreshes, use async if requested
        if ($async_refresh) {
            // Schedule periodic background refresh for this feed
            wp_schedule_single_event(time() + 3600, 'podloom_refresh_rss_feed', array($feed_id));
        }

        return array(
            'success' => true,
            'message' => 'Feed added successfully',
            'feed' => $feeds[$feed_id]
        );
    }

    /**
     * Update a feed name
     *
     * @param string $feed_id Feed ID
     * @param string $name New name
     * @return array Result with success status and message
     */
    public static function update_feed_name($feed_id, $name) {
        $feeds = self::get_feeds();

        if (!isset($feeds[$feed_id])) {
            return array(
                'success' => false,
                'message' => 'Feed not found'
            );
        }

        $feeds[$feed_id]['name'] = sanitize_text_field($name);
        update_option('podloom_rss_feeds', $feeds);

        return array(
            'success' => true,
            'message' => 'Feed name updated',
            'feed' => $feeds[$feed_id]
        );
    }

    /**
     * Delete a feed
     *
     * @param string $feed_id Feed ID
     * @return array Result with success status and message
     */
    public static function delete_feed($feed_id) {
        $feeds = self::get_feeds();

        if (!isset($feeds[$feed_id])) {
            return array(
                'success' => false,
                'message' => 'Feed not found'
            );
        }

        // Delete cached episodes (handles both object cache and transients)
        podloom_cache_delete('rss_episodes_' . $feed_id);

        // Remove feed
        unset($feeds[$feed_id]);
        update_option('podloom_rss_feeds', $feeds);

        return array(
            'success' => true,
            'message' => 'Feed deleted successfully'
        );
    }

    /**
     * Validate an RSS feed URL
     *
     * @param string $url Feed URL
     * @return array Validation result with valid status and message
     */
    public static function validate_feed($url) {
        // Fetch the feed
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'User-Agent' => 'PodLoom WordPress Plugin'
            )
        ));

        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'message' => 'Could not fetch feed: ' . $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');

        // Check if content is XML
        if (strpos($content_type, 'xml') === false && strpos($body, '<?xml') !== 0) {
            return array(
                'valid' => false,
                'message' => 'URL does not return valid XML'
            );
        }

        // Try to parse with SimplePie
        require_once(ABSPATH . WPINC . '/class-simplepie.php');

        $feed = new SimplePie();
        $feed->set_raw_data($body);
        $feed->init();

        if ($feed->error()) {
            return array(
                'valid' => false,
                'message' => 'Invalid RSS feed: ' . $feed->error()
            );
        }

        return array(
            'valid' => true,
            'message' => 'Valid RSS feed'
        );
    }

    /**
     * Refresh a feed (fetch and cache episodes)
     *
     * @param string $feed_id Feed ID
     * @return array Result with success status and message
     */
    public static function refresh_feed($feed_id) {
        $feed_data = self::get_feed($feed_id);

        if (!$feed_data) {
            return array(
                'success' => false,
                'message' => 'Feed not found'
            );
        }

        // Clear existing cache before refresh (handles both object cache and transients)
        podloom_cache_delete('rss_episodes_' . $feed_id);

        // Clear SimplePie cache for this feed
        $cache_location = WP_CONTENT_DIR . '/cache/simplepie';
        if (file_exists($cache_location)) {
            $cache_file = $cache_location . '/' . md5($feed_data['url']) . '.spc';
            if (file_exists($cache_file)) {
                @unlink($cache_file);
            }
        }

        // Fetch feed with SSRF protection
        $response = wp_remote_get($feed_data['url'], array(
            'timeout' => 15,
            'redirection' => 3, // Limit redirects to prevent redirect chains
            'reject_unsafe_urls' => true, // WordPress built-in SSRF protection
            'headers' => array(
                'User-Agent' => 'PodLoom WordPress Plugin'
            )
        ));

        if (is_wp_error($response)) {
            // Mark feed as invalid
            $feeds = self::get_feeds();
            $feeds[$feed_id]['valid'] = false;
            $feeds[$feed_id]['last_checked'] = current_time('timestamp');
            update_option('podloom_rss_feeds', $feeds);

            return array(
                'success' => false,
                'message' => 'Could not fetch feed: ' . $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body($response);

        // Parse with SimplePie
        require_once(ABSPATH . WPINC . '/class-simplepie.php');

        $feed = new SimplePie();

        // Disable SimplePie caching during manual refresh to ensure fresh data
        $feed->enable_cache(false);

        $feed->set_raw_data($body);
        $feed->init();

        if ($feed->error()) {
            // Mark feed as invalid
            $feeds = self::get_feeds();
            $feeds[$feed_id]['valid'] = false;
            $feeds[$feed_id]['last_checked'] = current_time('timestamp');
            update_option('podloom_rss_feeds', $feeds);

            return array(
                'success' => false,
                'message' => 'Invalid RSS feed: ' . $feed->error()
            );
        }

        // Get up to MAX_EPISODES most recent episodes
        $items = $feed->get_items(0, self::MAX_EPISODES);
        $episodes = array();

        // Initialize P2.0 parser
        $p20_parser = new Podloom_Podcast20_Parser();

        // Parse channel-level P2.0 data (applies to all episodes)
        $channel_p20_data = $p20_parser->parse_from_simplepie_channel($feed);

        foreach ($items as $item) {
            $enclosure = $item->get_enclosure();

            // Parse item-level Podcasting 2.0 data
            $item_p20_data = $p20_parser->parse_from_simplepie($item);

            // Merge channel and item data (item takes precedence)
            $p20_data = $p20_parser->merge_data($item_p20_data, $channel_p20_data);

            $episode = array(
                'id' => md5($item->get_permalink()),
                'title' => $item->get_title(),
                'description' => $item->get_description(),
                'content' => $item->get_content(),
                'link' => $item->get_permalink(),
                'date' => $item->get_date('U'),
                'author' => $item->get_author() ? $item->get_author()->get_name() : '',
                'audio_url' => $enclosure ? $enclosure->get_link() : '',
                'audio_type' => $enclosure ? $enclosure->get_type() : '',
                'duration' => $enclosure ? $enclosure->get_duration() : '',
                'image' => $item->get_thumbnail() ? $item->get_thumbnail()['url'] : ($feed->get_image_url() ?: ''),
                'podcast20' => $p20_data, // Add P2.0 data
            );

            $episodes[] = $episode;
        }

        // Cache episodes with configurable duration (uses object cache if available)
        // Uses the general cache duration setting from Settings → General
        $cache_duration = get_option('podloom_cache_duration', 21600); // Default: 6 hours
        podloom_cache_set('rss_episodes_' . $feed_id, $episodes, 'podloom', $cache_duration);

        // Increment render cache version to invalidate all rendered episode HTML
        // This ensures editor shows updated content after feed refresh
        // Note: This invalidates ALL episodes (not just this feed), but feed refreshes
        // are rare enough that this is acceptable for simplicity
        // Uses atomic increment function to avoid race conditions
        podloom_increment_render_cache_version();

        // Update feed metadata
        $feeds = self::get_feeds();
        $feeds[$feed_id]['valid'] = true;
        $feeds[$feed_id]['last_checked'] = current_time('timestamp');
        $feeds[$feed_id]['episode_count'] = count($episodes);
        update_option('podloom_rss_feeds', $feeds);

        return array(
            'success' => true,
            'message' => 'Feed refreshed successfully',
            'episode_count' => count($episodes)
        );
    }

    /**
     * Get cached episodes for a feed
     *
     * @param string $feed_id Feed ID
     * @param int $page Page number (default: 1)
     * @param int $per_page Items per page (default: 20)
     * @param bool $allow_remote_fetch Whether to allow fetching from remote URL if cache is missing (default: true)
     * @return array Episodes array with pagination info
     */
    public static function get_episodes($feed_id, $page = 1, $per_page = 20, $allow_remote_fetch = true) {
        // Check if caching is enabled
        $enable_cache = get_option('podloom_enable_cache', true);

        if ($enable_cache) {
            $episodes = podloom_cache_get('rss_episodes_' . $feed_id);
        } else {
            $episodes = false;
        }

        // If no cache, refresh feed
        if ($episodes === false) {
            // If remote fetch is disabled (e.g. in editor), return empty result immediately
            if (!$allow_remote_fetch) {
                return array(
                    'episodes' => array(),
                    'total' => 0,
                    'page' => $page,
                    'pages' => 0,
                    'error' => 'cache_miss' // Signal that data is missing but fetch was skipped
                );
            }

            $result = self::refresh_feed($feed_id);
            if (!$result['success']) {
                return array(
                    'episodes' => array(),
                    'total' => 0,
                    'page' => $page,
                    'pages' => 0
                );
            }
            $episodes = podloom_cache_get('rss_episodes_' . $feed_id);
        }

        // Note: Character limit for descriptions is applied at render time (not here)
        // to preserve HTML formatting and avoid redundant processing on every retrieval.
        // See podloom_truncate_html() in podloom-podcast-player.php for HTML-aware truncation.

        // Paginate episodes
        $total = count($episodes);
        $pages = ceil($total / $per_page);
        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($episodes, $offset, $per_page);

        return array(
            'episodes' => $paginated,
            'total' => $total,
            'page' => $page,
            'pages' => $pages
        );
    }

    /**
     * Get raw feed XML for preview
     *
     * @param string $feed_id Feed ID
     * @return array Result with XML content
     */
    public static function get_raw_feed($feed_id) {
        $feed_data = self::get_feed($feed_id);

        if (!$feed_data) {
            return array(
                'success' => false,
                'message' => 'Feed not found'
            );
        }

        $response = wp_remote_get($feed_data['url'], array(
            'timeout' => 15,
            'redirection' => 3, // Limit redirects to prevent redirect chains
            'reject_unsafe_urls' => true, // WordPress built-in SSRF protection
            'limit_response_size' => 5 * MB_IN_BYTES, // Limit to 5MB to prevent memory exhaustion
            'headers' => array(
                'User-Agent' => 'PodLoom WordPress Plugin'
            )
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Could not fetch feed: ' . $response->get_error_message()
            );
        }

        // Additional check: ensure response size is within limits
        $body = wp_remote_retrieve_body($response);
        if (strlen($body) > 5 * MB_IN_BYTES) {
            return array(
                'success' => false,
                'message' => 'Feed too large (maximum 5MB)'
            );
        }

        return array(
            'success' => true,
            'xml' => $body
        );
    }

    /**
     * Get the latest episode from a feed
     *
     * @param string $feed_id Feed ID
     * @return array|null Latest episode data or null if not found
     */
    public static function get_latest_episode($feed_id) {
        $episodes_data = self::get_episodes($feed_id, 1, 1);

        if (!empty($episodes_data['episodes'])) {
            return $episodes_data['episodes'][0];
        }

        return null;
    }

    /**
     * Clear all RSS feed caches with batching to prevent table locks
     */
    public static function clear_all_caches() {
        global $wpdb;

        // Use proper LIKE pattern preparation to prevent SQL injection
        $pattern1 = $wpdb->esc_like('_transient_podloom_rss_episodes_') . '%';
        $pattern2 = $wpdb->esc_like('_transient_timeout_podloom_rss_episodes_') . '%';

        // Delete in batches of 100 to prevent long table locks
        $batch_size = 100;
        $deleted_count = 0;

        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting cache entries, caching not applicable for DELETE operations
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options}
                     WHERE (option_name LIKE %s OR option_name LIKE %s)
                     LIMIT %d",
                    $pattern1,
                    $pattern2,
                    $batch_size
                )
            );

            $deleted_count += $deleted;

            // Small delay between batches to prevent overwhelming the database
            if ($deleted >= $batch_size) {
                usleep(50000); // 50ms delay
            }
        } while ($deleted >= $batch_size);

        wp_cache_flush();

        return $deleted_count;
    }
}
