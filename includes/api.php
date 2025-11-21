<?php
/**
 * Transistor API Wrapper
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Podloom_API {

    private $api_key;
    private $base_url = 'https://api.transistor.fm/v1';

    /**
     * Constructor
     */
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('podloom_api_key', '');
    }

    /**
     * Make API request
     */
    private function request($endpoint, $method = 'GET', $params = []) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Transistor API key is not set.', 'podloom-podcast-player'));
        }

        $url = $this->base_url . $endpoint;

        // Add query parameters for GET requests
        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = [
            'method' => $method,
            'headers' => [
                'x-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($method !== 'GET' && !empty($params)) {
            $args['body'] = json_encode($params);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('api_error', sprintf(
                /* translators: 1: HTTP response code, 2: error message */
                __('Transistor API error (code %1$d): %2$s', 'podloom-podcast-player'),
                $code,
                $body
            ));
        }

        return json_decode($body, true);
    }

    /**
     * Get cache key for request
     */
    private function get_cache_key($endpoint, $params = []) {
        // Add a salt to the cache key to allow for instant invalidation
        // This is crucial for persistent object caches where direct DB deletion fails
        $salt = get_option('podloom_cache_salt', '');
        return 'podloom_cache_' . md5($endpoint . wp_json_encode($params) . $salt);
    }

    /**
     * Check if caching is enabled
     */
    private function is_cache_enabled() {
        return get_option('podloom_enable_cache', true);
    }

    /**
     * Get cache duration in seconds
     */
    private function get_cache_duration() {
        return get_option('podloom_cache_duration', 21600);
    }

    /**
     * Make cached API request
     */
    private function cached_request($endpoint, $method = 'GET', $params = []) {
        // Check if caching is enabled
        if (!$this->is_cache_enabled()) {
            return $this->request($endpoint, $method, $params);
        }

        // Generate cache key
        $cache_key = $this->get_cache_key($endpoint, $params);

        // Try to get from cache
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        // Make the API request
        $result = $this->request($endpoint, $method, $params);

        // Cache the result if successful
        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, $this->get_cache_duration());
        }

        return $result;
    }

    /**
     * Get all shows
     */
    public function get_shows($page = 1, $per_page = 100) {
        return $this->cached_request('/shows', 'GET', [
            'pagination' => [
                'page' => $page,
                'per' => $per_page
            ]
        ]);
    }

    /**
     * Get episodes for a show
     */
    public function get_episodes($show_id = null, $search = '', $status = 'published', $page = 1, $per_page = 100) {
        $params = [
            'pagination' => [
                'page' => $page,
                'per' => $per_page
            ]
        ];

        if (!empty($show_id)) {
            $params['show_id'] = $show_id;
        }

        if (!empty($search)) {
            $params['q'] = $search;
        }

        if (!empty($status)) {
            $params['status'] = $status;
        }

        return $this->cached_request('/episodes', 'GET', $params);
    }

    /**
     * Get a single episode
     */
    public function get_episode($episode_id) {
        // Sanitize episode ID to prevent injection
        $episode_id = sanitize_text_field($episode_id);
        return $this->request("/episodes/{$episode_id}");
    }
}

/**
 * AJAX handler to get shows
 */
function podloom_ajax_get_shows() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $api = new Podloom_API();
    $shows = $api->get_shows();

    if (is_wp_error($shows)) {
        wp_send_json_error(['message' => $shows->get_error_message()]);
    }

    wp_send_json_success($shows);
}
add_action('wp_ajax_podloom_get_shows', 'podloom_ajax_get_shows');

/**
 * AJAX handler to get initial block data (combines shows, feeds, typography)
 * Reduces 3 separate requests to 1 on block mount
 */
function podloom_ajax_get_block_init_data() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $data = array();

    // Get Transistor shows (cached)
    $api = new Podloom_API();
    $shows = $api->get_shows();
    $data['podloom_shows'] = is_wp_error($shows) ? array() : $shows;

    // Get RSS feeds
    $data['rss_feeds'] = Podloom_RSS::get_feeds();

    // Get RSS typography (only if RSS is enabled)
    $rss_enabled = get_option('podloom_rss_enabled', false);
    if ($rss_enabled && !empty($data['rss_feeds'])) {
        $typo = podloom_get_rss_typography_styles();
        $typo['background_color'] = get_option('podloom_rss_background_color', '#f9f9f9');
        $typo['display'] = array(
            'artwork' => get_option('podloom_rss_display_artwork', true),
            'title' => get_option('podloom_rss_display_title', true),
            'date' => get_option('podloom_rss_display_date', true),
            'duration' => get_option('podloom_rss_display_duration', true),
            'description' => get_option('podloom_rss_display_description', true)
        );
        $data['rss_typography'] = $typo;
    } else {
        $data['rss_typography'] = null;
    }

    wp_send_json_success($data);
}
add_action('wp_ajax_podloom_get_block_init_data', 'podloom_ajax_get_block_init_data');

/**
 * AJAX handler to get episodes
 */
function podloom_ajax_get_episodes() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $show_id = isset($_GET['show_id']) ? sanitize_text_field(wp_unslash($_GET['show_id'])) : null;
    $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
    $page = isset($_GET['page']) ? absint(wp_unslash($_GET['page'])) : 1;
    $per_page = isset($_GET['per_page']) ? absint(wp_unslash($_GET['per_page'])) : 100;

    // Validate page number is positive
    if ($page < 1) {
        $page = 1;
    }

    // Validate per_page is within reasonable limits
    if ($per_page < 1 || $per_page > 100) {
        $per_page = 100;
    }

    $api = new Podloom_API();
    // Fetch all episode statuses (published, draft, scheduled)
    $episodes = $api->get_episodes($show_id, $search, '', $page, $per_page);

    if (is_wp_error($episodes)) {
        wp_send_json_error(['message' => $episodes->get_error_message()]);
    }

    wp_send_json_success($episodes);
}
add_action('wp_ajax_podloom_get_episodes', 'podloom_ajax_get_episodes');

/**
 * AJAX handler to get a single episode
 */
function podloom_ajax_get_episode() {
    check_ajax_referer('podloom_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $episode_id = isset($_GET['episode_id']) ? sanitize_text_field(wp_unslash($_GET['episode_id'])) : '';

    // Validate episode ID is not empty and contains only valid characters
    if (empty($episode_id)) {
        wp_send_json_error(['message' => 'Episode ID is required']);
    }

    $api = new Podloom_API();
    $episode = $api->get_episode($episode_id);

    if (is_wp_error($episode)) {
        wp_send_json_error(['message' => $episode->get_error_message()]);
    }

    wp_send_json_success($episode);
}
add_action('wp_ajax_podloom_get_episode', 'podloom_ajax_get_episode');

/**
 * Clear all Transistor cache
 */
function podloom_clear_all_cache() {
    global $wpdb;

    // Delete all transients that start with 'podloom_cache_'
    // Use proper LIKE pattern preparation to prevent SQL injection
    $pattern1 = $wpdb->esc_like('_transient_podloom_cache_') . '%';
    $pattern2 = $wpdb->esc_like('_transient_timeout_podloom_cache_') . '%';

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

    // Clear object cache if enabled
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }

    // Also clear editor cache
    podloom_clear_editor_cache();

    // Rotate the cache salt to instantly invalidate all existing cache keys
    // This ensures that even if the DB deletion above fails (e.g. due to object caching),
    // the old data will no longer be accessible because the keys have changed.
    update_option('podloom_cache_salt', time());

    return $deleted_count;
}

/**
 * Clear editor rendered episode cache
 * These are transients with the 'podloom_editor_' prefix
 */
function podloom_clear_editor_cache() {
    global $wpdb;

    // Delete all editor cache transients
    $pattern1 = $wpdb->esc_like('_transient_podloom_editor_') . '%';
    $pattern2 = $wpdb->esc_like('_transient_timeout_podloom_editor_') . '%';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $pattern1,
            $pattern2
        )
    );

    // Clear object cache if enabled
    if (function_exists('wp_cache_flush_group') && wp_using_ext_object_cache()) {
        wp_cache_flush_group('podloom_editor');
    }
}

/**
 * Delete all PodLoom plugin data from the database
 * This includes all options and cached data
 */
function podloom_delete_all_plugin_data() {
    // Delete all plugin options
    delete_option('podloom_api_key');
    delete_option('podloom_default_show');
    delete_option('podloom_enable_cache');
    delete_option('podloom_cache_duration');

    // Delete RSS options
    delete_option('podloom_rss_enabled');
    delete_option('podloom_rss_feeds');
    delete_option('podloom_rss_display_artwork');
    delete_option('podloom_rss_display_title');
    delete_option('podloom_rss_display_date');
    delete_option('podloom_rss_display_duration');
    delete_option('podloom_rss_display_description');

    // Delete Podcasting 2.0 display options
    delete_option('podloom_rss_display_funding');
    delete_option('podloom_rss_display_transcripts');
    delete_option('podloom_rss_display_people_hosts');
    delete_option('podloom_rss_display_people_guests');
    delete_option('podloom_rss_display_chapters');

    // Delete RSS typography options
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

    // Delete cache version option
    delete_option('podloom_render_cache_version');

    // Clear all cached data
    podloom_clear_all_cache();

    // Clear RSS cached data
    if (class_exists('Podloom_RSS')) {
        Podloom_RSS::clear_all_caches();
    }

    // Clear editor rendered episode cache
    podloom_clear_editor_cache();
}
