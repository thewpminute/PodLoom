<?php
/**
 * Transistor API Wrapper
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Transistor_API {

    private $api_key;
    private $base_url = 'https://api.transistor.fm/v1';

    /**
     * Constructor
     */
    public function __construct($api_key = null) {
        $this->api_key = $api_key ?: get_option('transistor_api_key', '');
    }

    /**
     * Make API request
     */
    private function request($endpoint, $method = 'GET', $params = []) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Transistor API key is not set.', 'podloom'));
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
                __('Transistor API error (code %d): %s', 'podloom'),
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
        return 'transistor_cache_' . md5($endpoint . wp_json_encode($params));
    }

    /**
     * Check if caching is enabled
     */
    private function is_cache_enabled() {
        return get_option('transistor_enable_cache', true);
    }

    /**
     * Get cache duration in seconds
     */
    private function get_cache_duration() {
        return get_option('transistor_cache_duration', 21600);
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

    /**
     * Test API connection
     */
    public function test_connection() {
        $result = $this->get_shows(1, 1);
        return !is_wp_error($result);
    }
}

/**
 * AJAX handler to get shows
 */
function transistor_ajax_get_shows() {
    check_ajax_referer('transistor_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $api = new Transistor_API();
    $shows = $api->get_shows();

    if (is_wp_error($shows)) {
        wp_send_json_error(['message' => $shows->get_error_message()]);
    }

    wp_send_json_success($shows);
}
add_action('wp_ajax_transistor_get_shows', 'transistor_ajax_get_shows');

/**
 * AJAX handler to get episodes
 */
function transistor_ajax_get_episodes() {
    check_ajax_referer('transistor_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $show_id = isset($_GET['show_id']) ? sanitize_text_field($_GET['show_id']) : null;
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
    $per_page = isset($_GET['per_page']) ? absint($_GET['per_page']) : 100;

    // Validate page number is positive
    if ($page < 1) {
        $page = 1;
    }

    // Validate per_page is within reasonable limits
    if ($per_page < 1 || $per_page > 100) {
        $per_page = 100;
    }

    $api = new Transistor_API();
    // Fetch all episode statuses (published, draft, scheduled)
    $episodes = $api->get_episodes($show_id, $search, '', $page, $per_page);

    if (is_wp_error($episodes)) {
        wp_send_json_error(['message' => $episodes->get_error_message()]);
    }

    wp_send_json_success($episodes);
}
add_action('wp_ajax_transistor_get_episodes', 'transistor_ajax_get_episodes');

/**
 * AJAX handler to get a single episode
 */
function transistor_ajax_get_episode() {
    check_ajax_referer('transistor_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $episode_id = isset($_GET['episode_id']) ? sanitize_text_field($_GET['episode_id']) : '';

    // Validate episode ID is not empty and contains only valid characters
    if (empty($episode_id)) {
        wp_send_json_error(['message' => 'Episode ID is required']);
    }

    $api = new Transistor_API();
    $episode = $api->get_episode($episode_id);

    if (is_wp_error($episode)) {
        wp_send_json_error(['message' => $episode->get_error_message()]);
    }

    wp_send_json_success($episode);
}
add_action('wp_ajax_transistor_get_episode', 'transistor_ajax_get_episode');

/**
 * Clear all Transistor cache
 */
function transistor_clear_all_cache() {
    global $wpdb;

    // Delete all transients that start with 'transistor_cache_'
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_transistor_cache_') . '%',
            $wpdb->esc_like('_transient_timeout_transistor_cache_') . '%'
        )
    );

    // Clear object cache if enabled
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Delete all PodLoom plugin data from the database
 * This includes all options and cached data
 */
function transistor_delete_all_plugin_data() {
    // Delete all plugin options
    delete_option('transistor_api_key');
    delete_option('transistor_default_show');
    delete_option('transistor_enable_cache');
    delete_option('transistor_cache_duration');

    // Clear all cached data
    transistor_clear_all_cache();
}
