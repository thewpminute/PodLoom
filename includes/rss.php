<?php
/**
 * RSS Feed Management
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
     * Maximum number of episodes to cache per feed
     */
    const MAX_EPISODES = 50;

    /**
     * Rate limit check for AJAX endpoints
     *
     * @param string $action Action name for rate limiting
     * @param int $limit Maximum requests allowed
     * @param int $window Time window in seconds
     * @return bool True if within limit, false if exceeded
     */
    public static function check_rate_limit($action, $limit = 60, $window = 60) {
        $user_id = get_current_user_id();
        $rate_key = "podloom_rate_{$action}_{$user_id}";
        $rate_count = get_transient($rate_key);

        if ($rate_count === false) {
            // First request in this window
            set_transient($rate_key, 1, $window);
            return true;
        }

        if ($rate_count >= $limit) {
            return false;
        }

        // Increment counter
        set_transient($rate_key, $rate_count + 1, $window);
        return true;
    }

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

        // Basic SSRF protection: Block localhost and common private IPs
        $parsed_url = wp_parse_url($url);
        if (!isset($parsed_url['host'])) {
            return array(
                'success' => false,
                'message' => 'Invalid URL - missing host'
            );
        }

        // Block obvious localhost references
        $host_lower = strtolower($parsed_url['host']);
        if (in_array($host_lower, array('localhost', '127.0.0.1', '::1', '0.0.0.0'), true)) {
            return array(
                'success' => false,
                'message' => 'Localhost URLs are not allowed'
            );
        }

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

        // Delete cached episodes
        delete_transient('podloom_rss_episodes_' . $feed_id);

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

        // Fetch feed
        $response = wp_remote_get($feed_data['url'], array(
            'timeout' => 15,
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

        // Configure SimplePie caching for better performance
        $cache_location = WP_CONTENT_DIR . '/cache/simplepie';
        if (!file_exists($cache_location)) {
            wp_mkdir_p($cache_location);
        }
        $feed->set_cache_location($cache_location);
        $feed->set_cache_duration(3600); // 1 hour cache

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

        foreach ($items as $item) {
            $enclosure = $item->get_enclosure();

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
                'image' => $item->get_thumbnail() ? $item->get_thumbnail()['url'] : ($feed->get_image_url() ?: '')
            );

            $episodes[] = $episode;
        }

        // Cache episodes
        $cache_duration = get_option('podloom_cache_duration', 21600);
        set_transient('podloom_rss_episodes_' . $feed_id, $episodes, $cache_duration);

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
     * @return array Episodes array with pagination info
     */
    public static function get_episodes($feed_id, $page = 1, $per_page = 20) {
        // Check if caching is enabled
        $enable_cache = get_option('podloom_enable_cache', true);

        if ($enable_cache) {
            $episodes = get_transient('podloom_rss_episodes_' . $feed_id);
        } else {
            $episodes = false;
        }

        // If no cache, refresh feed
        if ($episodes === false) {
            $result = self::refresh_feed($feed_id);
            if (!$result['success']) {
                return array(
                    'episodes' => array(),
                    'total' => 0,
                    'page' => $page,
                    'pages' => 0
                );
            }
            $episodes = get_transient('podloom_rss_episodes_' . $feed_id);
        }

        // Apply character limit to descriptions if set
        $char_limit = get_option('podloom_rss_description_limit', 0);
        if ($char_limit > 0) {
            foreach ($episodes as &$episode) {
                if (!empty($episode['description'])) {
                    // Strip HTML tags to count actual text characters
                    $text_only = wp_strip_all_tags($episode['description']);
                    if (mb_strlen($text_only) > $char_limit) {
                        // Truncate to limit and add ellipsis
                        $truncated = mb_substr($text_only, 0, $char_limit);
                        // Try to break at last space to avoid cutting words
                        $last_space = mb_strrpos($truncated, ' ');
                        if ($last_space !== false && $last_space > $char_limit * 0.8) {
                            $truncated = mb_substr($truncated, 0, $last_space);
                        }
                        $episode['description'] = esc_html($truncated) . '…';
                    }
                }
            }
        }

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

        $body = wp_remote_retrieve_body($response);

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

/**
 * WordPress cron hook to refresh RSS feed in background
 */
function podloom_cron_refresh_rss_feed($feed_id) {
    Podloom_RSS::refresh_feed($feed_id);
}
add_action('podloom_refresh_rss_feed', 'podloom_cron_refresh_rss_feed');

/**
 * AJAX Handler: Get RSS Feeds
 */
function podloom_ajax_get_rss_feeds() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    $feeds = Podloom_RSS::get_feeds();
    wp_send_json_success($feeds);
}
add_action('wp_ajax_podloom_get_rss_feeds', 'podloom_ajax_get_rss_feeds');

/**
 * AJAX Handler: Add RSS Feed
 */
function podloom_ajax_add_rss_feed() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    // Rate limiting: 10 feed additions per minute per user
    if (!Podloom_RSS::check_rate_limit('add_feed', 10, 60)) {
        wp_send_json_error(array('message' => 'Rate limit exceeded. Please wait before adding more feeds.'), 429);
        return;
    }

    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';

    if (empty($name) || empty($url)) {
        wp_send_json_error(array('message' => 'Name and URL are required'));
        return;
    }

    $result = Podloom_RSS::add_feed($name, $url);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_podloom_add_rss_feed', 'podloom_ajax_add_rss_feed');

/**
 * AJAX Handler: Update RSS Feed Name
 */
function podloom_ajax_update_rss_feed_name() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    $feed_id = isset($_POST['feed_id']) ? sanitize_text_field(wp_unslash($_POST['feed_id'])) : '';
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

    if (empty($feed_id) || empty($name)) {
        wp_send_json_error(array('message' => 'Feed ID and name are required'));
        return;
    }

    $result = Podloom_RSS::update_feed_name($feed_id, $name);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_podloom_update_rss_feed_name', 'podloom_ajax_update_rss_feed_name');

/**
 * AJAX Handler: Delete RSS Feed
 */
function podloom_ajax_delete_rss_feed() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    $feed_id = isset($_POST['feed_id']) ? sanitize_text_field(wp_unslash($_POST['feed_id'])) : '';

    if (empty($feed_id)) {
        wp_send_json_error(array('message' => 'Feed ID is required'));
        return;
    }

    $result = Podloom_RSS::delete_feed($feed_id);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_podloom_delete_rss_feed', 'podloom_ajax_delete_rss_feed');

/**
 * AJAX Handler: Refresh RSS Feed
 */
function podloom_ajax_refresh_rss_feed() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    // Rate limiting: 30 refreshes per minute per user
    if (!Podloom_RSS::check_rate_limit('refresh_feed', 30, 60)) {
        wp_send_json_error(array('message' => 'Rate limit exceeded. Please wait before refreshing feeds.'), 429);
        return;
    }

    $feed_id = isset($_POST['feed_id']) ? sanitize_text_field(wp_unslash($_POST['feed_id'])) : '';

    if (empty($feed_id)) {
        wp_send_json_error(array('message' => 'Feed ID is required'));
        return;
    }

    $result = Podloom_RSS::refresh_feed($feed_id);

    // Get updated feed data
    $feed = Podloom_RSS::get_feed($feed_id);

    if ($result['success']) {
        wp_send_json_success(array(
            'message' => $result['message'],
            'feed' => $feed
        ));
    } else {
        wp_send_json_error(array(
            'message' => $result['message'],
            'feed' => $feed
        ));
    }
}
add_action('wp_ajax_podloom_refresh_rss_feed', 'podloom_ajax_refresh_rss_feed');

/**
 * AJAX Handler: Get RSS Episodes
 */
function podloom_ajax_get_rss_episodes() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    $feed_id = isset($_POST['feed_id']) ? sanitize_text_field(wp_unslash($_POST['feed_id'])) : '';
    $page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
    $per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20;

    if (empty($feed_id)) {
        wp_send_json_error(array('message' => 'Feed ID is required'));
        return;
    }

    $result = Podloom_RSS::get_episodes($feed_id, $page, $per_page);

    wp_send_json_success($result);
}
add_action('wp_ajax_podloom_get_rss_episodes', 'podloom_ajax_get_rss_episodes');

/**
 * AJAX Handler: Get Raw RSS Feed
 */
function podloom_ajax_get_raw_rss_feed() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    $feed_id = isset($_POST['feed_id']) ? sanitize_text_field(wp_unslash($_POST['feed_id'])) : '';

    if (empty($feed_id)) {
        wp_send_json_error(array('message' => 'Feed ID is required'));
        return;
    }

    $result = Podloom_RSS::get_raw_feed($feed_id);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}
add_action('wp_ajax_podloom_get_raw_rss_feed', 'podloom_ajax_get_raw_rss_feed');

/**
 * AJAX Handler: Save All RSS Settings (Bulk)
 */
function podloom_ajax_save_all_rss_settings() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    $settings_json = isset($_POST['settings']) ? sanitize_text_field(wp_unslash($_POST['settings'])) : '';
    $settings = json_decode($settings_json, true);

    if (!is_array($settings)) {
        wp_send_json_error(array('message' => 'Invalid settings data'));
        return;
    }

    // List of boolean options
    $boolean_options = array(
        'podloom_rss_enabled',
        'podloom_rss_display_artwork',
        'podloom_rss_display_title',
        'podloom_rss_display_date',
        'podloom_rss_display_duration',
        'podloom_rss_display_description',
        'podloom_rss_minimal_styling'
    );

    $saved_count = 0;
    foreach ($settings as $option_name => $option_value) {
        // Validate option name - must start with podloom_rss_
        if (strpos($option_name, 'podloom_rss_') !== 0) {
            continue;
        }

        // Convert to boolean if it's a boolean option
        if (in_array($option_name, $boolean_options)) {
            $option_value = ($option_value === '1');
        } else {
            // Sanitize text field
            $option_value = sanitize_text_field($option_value);

            // For color values, sanitize as hex color
            if (strpos($option_name, '_color') !== false) {
                $option_value = sanitize_hex_color($option_value);
            }
        }

        update_option($option_name, $option_value);
        $saved_count++;
    }

    // Clear typography cache when settings are saved
    podloom_clear_typography_cache();

    wp_send_json_success(array(
        'message' => 'All settings saved successfully',
        'count' => $saved_count
    ));
}
add_action('wp_ajax_podloom_save_all_rss_settings', 'podloom_ajax_save_all_rss_settings');

/**
 * AJAX Handler: Get RSS Typography Settings
 */
function podloom_ajax_get_rss_typography() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
        return;
    }

    // Get typography styles
    $typo = podloom_get_rss_typography_styles();

    // Add background color
    $typo['background_color'] = get_option('podloom_rss_background_color', '#f9f9f9');

    // Add minimal styling mode
    $typo['minimal_styling'] = get_option('podloom_rss_minimal_styling', false);

    // Add display settings
    $typo['display'] = array(
        'artwork' => get_option('podloom_rss_display_artwork', true),
        'title' => get_option('podloom_rss_display_title', true),
        'date' => get_option('podloom_rss_display_date', true),
        'duration' => get_option('podloom_rss_display_duration', true),
        'description' => get_option('podloom_rss_display_description', true)
    );

    wp_send_json_success($typo);
}
add_action('wp_ajax_podloom_get_rss_typography', 'podloom_ajax_get_rss_typography');
