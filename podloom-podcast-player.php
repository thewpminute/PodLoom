<?php
/**
 * Plugin Name:  PodLoom - Podcast Player for Transistor.fm & RSS Feeds
 * Plugin URI: https://thewpminute.com/podloom/
 * Description: Connect to your Transistor.fm account and embed podcast episodes using Gutenberg blocks. Supports RSS feeds from any podcast platform.
 * Version: 2.1.1
 * Author: WP Minute
 * Author URI: https://thewpminute.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: podloom-podcast-player
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 3.2
 * WC tested up to:      10.2
 * Tested up to:         6.8
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PODLOOM_PLUGIN_VERSION', '2.1.1');
define('PODLOOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PODLOOM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Global flag to track if P2.0 content is used on this page
global $podloom_has_podcast20_content;
$podloom_has_podcast20_content = false;

// Include necessary files
require_once PODLOOM_PLUGIN_DIR . 'includes/api.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/class-podloom-rss.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss-cron.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss-ajax-handlers.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/class-podloom-podcast20-parser.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/cache.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/color-utils.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/utilities.php';
require_once PODLOOM_PLUGIN_DIR . 'admin/admin-functions.php';

// Debug page (only load when WP_DEBUG is enabled)
if (defined('WP_DEBUG') && WP_DEBUG && file_exists(PODLOOM_PLUGIN_DIR . 'debug-p20.php')) {
    require_once PODLOOM_PLUGIN_DIR . 'debug-p20.php';
}

/**
 * Run one-time migration from transistor_ to podloom_ options
 */
function podloom_run_migration() {
    // Check if migration has already run
    if (get_option('podloom_migration_complete')) {
        return;
    }

    // Map of old to new option names
    $options_map = array(
        'transistor_api_key' => 'podloom_api_key',
        'transistor_default_show' => 'podloom_default_show',
        'transistor_enable_cache' => 'podloom_enable_cache',
        'transistor_cache_duration' => 'podloom_cache_duration',
        'transistor_rss_feeds' => 'podloom_rss_feeds',
        'transistor_rss_enabled' => 'podloom_rss_enabled',
        'transistor_rss_description_limit' => 'podloom_rss_description_limit',
        'transistor_rss_minimal_styling' => 'podloom_rss_minimal_styling',
        'transistor_rss_background_color' => 'podloom_rss_background_color',
        'transistor_rss_display_artwork' => 'podloom_rss_display_artwork',
        'transistor_rss_display_title' => 'podloom_rss_display_title',
        'transistor_rss_display_date' => 'podloom_rss_display_date',
        'transistor_rss_display_duration' => 'podloom_rss_display_duration',
        'transistor_rss_display_description' => 'podloom_rss_display_description',
        'transistor_rss_title_font_family' => 'podloom_rss_title_font_family',
        'transistor_rss_title_font_size' => 'podloom_rss_title_font_size',
        'transistor_rss_title_line_height' => 'podloom_rss_title_line_height',
        'transistor_rss_title_color' => 'podloom_rss_title_color',
        'transistor_rss_title_font_weight' => 'podloom_rss_title_font_weight',
        'transistor_rss_date_font_family' => 'podloom_rss_date_font_family',
        'transistor_rss_date_font_size' => 'podloom_rss_date_font_size',
        'transistor_rss_date_line_height' => 'podloom_rss_date_line_height',
        'transistor_rss_date_color' => 'podloom_rss_date_color',
        'transistor_rss_date_font_weight' => 'podloom_rss_date_font_weight',
        'transistor_rss_duration_font_family' => 'podloom_rss_duration_font_family',
        'transistor_rss_duration_font_size' => 'podloom_rss_duration_font_size',
        'transistor_rss_duration_line_height' => 'podloom_rss_duration_line_height',
        'transistor_rss_duration_color' => 'podloom_rss_duration_color',
        'transistor_rss_duration_font_weight' => 'podloom_rss_duration_font_weight',
        'transistor_rss_description_font_family' => 'podloom_rss_description_font_family',
        'transistor_rss_description_font_size' => 'podloom_rss_description_font_size',
        'transistor_rss_description_line_height' => 'podloom_rss_description_line_height',
        'transistor_rss_description_color' => 'podloom_rss_description_color',
        'transistor_rss_description_font_weight' => 'podloom_rss_description_font_weight',
        'transistor_rss_typography_cache' => 'podloom_rss_typography_cache',
    );

    $migrated = 0;
    foreach ($options_map as $old_name => $new_name) {
        $old_value = get_option($old_name);
        if ($old_value !== false) {
            update_option($new_name, $old_value);
            $migrated++;
        }
    }

    // Migrate transients
    global $wpdb;
    $transients = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name, option_value
             FROM {$wpdb->options}
             WHERE option_name LIKE %s
             OR option_name LIKE %s",
            $wpdb->esc_like('_transient_transistor_') . '%',
            $wpdb->esc_like('_transient_timeout_transistor_') . '%'
        )
    );

    foreach ($transients as $transient) {
        $new_name = str_replace('transistor_', 'podloom_', $transient->option_name);
        update_option($new_name, $transient->option_value);
    }

    // Mark migration as complete
    update_option('podloom_migration_complete', true);

    // Log migration for debugging
    error_log("PodLoom: Migrated {$migrated} options from transistor_ to podloom_ prefix");
}
add_action('admin_init', 'podloom_run_migration');

/**
 * Initialize the plugin and register block
 */
function podloom_init() {
    // Register the block editor script
    wp_register_script(
        'podloom-block-editor',
        PODLOOM_PLUGIN_URL . 'blocks/episode-block/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        filemtime(PODLOOM_PLUGIN_DIR . 'blocks/episode-block/index.js'),
        false // Block editor scripts must load in header
    );

    // Pass data to JavaScript
    wp_localize_script('podloom-block-editor', 'podloomData', [
        'defaultShow' => get_option('podloom_default_show', ''),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('podloom_nonce'),
        'hasApiKey' => !empty(get_option('podloom_api_key', ''))
    ]);

    // Register the block type
    register_block_type('podloom/episode-player', [
        'api_version' => 2,
        'editor_script' => 'podloom-block-editor',
        'title' => __('PodLoom Podcast Episode', 'podloom-podcast-player'),
        'description' => __('Embed a Transistor.fm podcast episode player', 'podloom-podcast-player'),
        'category' => 'media',
        'icon' => 'microphone',
        'keywords' => ['podcast', 'audio', 'transistor', 'episode'],
        'supports' => [
            'html' => false
        ],
        'attributes' => [
            'sourceType' => [
                'type' => 'string',
                'default' => 'transistor'
            ],
            'episodeId' => [
                'type' => 'string',
                'default' => ''
            ],
            'episodeTitle' => [
                'type' => 'string',
                'default' => ''
            ],
            'showId' => [
                'type' => 'string',
                'default' => ''
            ],
            'showTitle' => [
                'type' => 'string',
                'default' => ''
            ],
            'showSlug' => [
                'type' => 'string',
                'default' => ''
            ],
            'rssFeedId' => [
                'type' => 'string',
                'default' => ''
            ],
            'rssEpisodeData' => [
                'type' => 'object',
                'default' => null
            ],
            'episodeDescription' => [
                'type' => 'string',
                'default' => ''
            ],
            'embedHtml' => [
                'type' => 'string',
                'default' => ''
            ],
            'theme' => [
                'type' => 'string',
                'default' => 'light'
            ],
            'displayMode' => [
                'type' => 'string',
                'default' => 'specific'
            ],
            'playlistHeight' => [
                'type' => 'number',
                'default' => 390
            ]
        ],
        'render_callback' => 'podloom_render_block'
    ]);
}
add_action('init', 'podloom_init');

/**
 * AJAX handler to proxy transcript requests (bypasses CORS)
 * Security: Rate limited, domain whitelisted, SSRF protected
 */
function podloom_fetch_transcript() {
    // Rate limiting: 10 requests per minute per IP/user
    $rate_key = 'podloom_transcript_rate_' . (is_user_logged_in() ? get_current_user_id() : md5($_SERVER['REMOTE_ADDR']));
    $rate_count = get_transient($rate_key);

    if ($rate_count && $rate_count >= 10) {
        wp_send_json_error(['message' => 'Rate limit exceeded. Please wait a minute.'], 429);
    }

    // Verify the URL parameter exists
    if (!isset($_GET['url'])) {
        wp_send_json_error(['message' => 'No URL provided'], 400);
    }

    $url = esc_url_raw($_GET['url']);

    // Validate that it's a reasonable URL
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        wp_send_json_error(['message' => 'Invalid URL'], 400);
    }

    // Domain validation: Block obviously malicious domains
    // Use blacklist approach instead of whitelist to support all transcript hosting services
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) {
        wp_send_json_error(['message' => 'Invalid URL format'], 400);
    }

    // Block localhost and local network references
    $blocked_hosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254', 'metadata.google.internal'];
    if (in_array(strtolower($host), $blocked_hosts, true)) {
        wp_send_json_error(['message' => 'Domain not allowed: ' . esc_html($host)], 403);
    }

    // Require HTTPS for transcripts (security best practice)
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if ($scheme !== 'https') {
        wp_send_json_error(['message' => 'Only HTTPS URLs are allowed for transcripts'], 403);
    }

    // SSRF Protection: Prevent internal network access
    $ip = gethostbyname($host);

    // Block private and reserved IP ranges
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // Block private ranges (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
        // Block localhost (127.x.x.x)
        // Block link-local (169.254.x.x)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            wp_send_json_error(['message' => 'Cannot access internal network addresses'], 403);
        }
    }

    // Fetch the transcript using WordPress HTTP API
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'sslverify' => true,
        'redirection' => 2, // Limit redirects
        'user-agent' => 'PodLoom/' . PODLOOM_PLUGIN_VERSION . '; ' . get_bloginfo('url')
    ]);

    // Check for errors
    if (is_wp_error($response)) {
        wp_send_json_error([
            'message' => 'Failed to fetch transcript',
            'error' => $response->get_error_message()
        ], 500);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    if ($status_code !== 200) {
        wp_send_json_error([
            'message' => 'Server returned error',
            'status' => $status_code
        ], $status_code);
    }

    // Content-Type validation: Only allow transcript formats
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $allowed_types = ['text/plain', 'text/html', 'application/json', 'text/vtt', 'application/x-subrip', 'text/srt'];

    $type_allowed = false;
    foreach ($allowed_types as $allowed_type) {
        if (stripos($content_type, $allowed_type) !== false) {
            $type_allowed = true;
            break;
        }
    }

    if (!$type_allowed && !empty($content_type)) {
        wp_send_json_error(['message' => 'Invalid content type: ' . esc_html($content_type)], 415);
    }

    // Update rate limit counter
    set_transient($rate_key, ($rate_count ?: 0) + 1, MINUTE_IN_SECONDS);

    // Return the transcript content
    wp_send_json_success([
        'content' => $body,
        'content_type' => $content_type
    ]);
}
add_action('wp_ajax_podloom_fetch_transcript', 'podloom_fetch_transcript');
add_action('wp_ajax_nopriv_podloom_fetch_transcript', 'podloom_fetch_transcript');

/**
 * Generate dynamic typography CSS for RSS player
 */
function podloom_get_rss_dynamic_css() {
    $minimal_styling = get_option('podloom_rss_minimal_styling', false);
    if ($minimal_styling) {
        return '';
    }

    $typo = podloom_get_rss_typography_styles();
    $bg_color = get_option('podloom_rss_background_color', '#f9f9f9');
    $player_height = get_option('podloom_rss_player_height', 600);

    // Calculate theme-aware colors for tabs and P2.0 elements
    $theme_colors = podloom_calculate_theme_colors($bg_color);

    return sprintf('
        .wp-block-podloom-episode-player.rss-episode-player {
            background: %s;
            max-height: %dpx;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .wp-block-podloom-episode-player.rss-episode-player .rss-episode-wrapper {
            flex-shrink: 0;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tabs {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-panel {
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }
        @media (max-width: 640px) {
            .wp-block-podloom-episode-player.rss-episode-player {
                max-height: %dpx;
            }
            .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-nav {
                border-right-color: %s;
            }
            .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-button.active {
                border-left-color: %s;
            }
        }
        /* Tab colors based on theme */
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-button {
            color: %s;
            border-bottom-color: transparent;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-button:hover {
            color: %s;
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-button.active {
            color: %s;
            border-bottom-color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-tab-nav {
            border-bottom-color: %s;
        }
        /* P2.0 content area colors based on theme */
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-people,
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-chapters-list {
            background: %s;
            border-color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .podcast20-person-role,
        .wp-block-podloom-episode-player.rss-episode-player .chapters-heading {
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-timestamp {
            background: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-timestamp:hover {
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .chapter-item.active {
            background: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-timestamp {
            background: %s;
            color: %s;
        }
        .wp-block-podloom-episode-player.rss-episode-player .transcript-timestamp:hover {
            background: %s;
        }
        .rss-episode-title {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }
        .rss-episode-date {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }
        .rss-episode-duration {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }
        .rss-episode-description {
            font-family: %s;
            font-size: %s;
            line-height: %s;
            color: %s;
            font-weight: %s;
        }',
        esc_attr($bg_color),
        absint($player_height),
        absint($player_height + 100), // Mobile height - add 100px for stacked layout
        // Mobile tab border colors
        esc_attr($theme_colors['tab_border']),
        esc_attr($theme_colors['tab_active']),
        // Tab colors
        esc_attr($theme_colors['tab_text']),
        esc_attr($theme_colors['tab_text_hover']),
        esc_attr($theme_colors['tab_bg_hover']),
        esc_attr($theme_colors['tab_active']),
        esc_attr($theme_colors['tab_active']),
        esc_attr($theme_colors['tab_border']),
        // P2.0 content colors
        esc_attr($theme_colors['content_bg']),
        esc_attr($theme_colors['content_border']),
        esc_attr($theme_colors['accent']),
        esc_attr($theme_colors['accent']),
        esc_attr($theme_colors['accent_text']),
        esc_attr($theme_colors['accent_hover']),
        esc_attr($theme_colors['content_bg_active']),
        esc_attr($theme_colors['accent']),
        esc_attr($theme_colors['accent_text']),
        esc_attr($theme_colors['accent_hover']),
        // Typography
        esc_attr($typo['title']['font-family']),
        esc_attr($typo['title']['font-size']),
        esc_attr($typo['title']['line-height']),
        esc_attr($typo['title']['color']),
        esc_attr($typo['title']['font-weight']),
        esc_attr($typo['date']['font-family']),
        esc_attr($typo['date']['font-size']),
        esc_attr($typo['date']['line-height']),
        esc_attr($typo['date']['color']),
        esc_attr($typo['date']['font-weight']),
        esc_attr($typo['duration']['font-family']),
        esc_attr($typo['duration']['font-size']),
        esc_attr($typo['duration']['line-height']),
        esc_attr($typo['duration']['color']),
        esc_attr($typo['duration']['font-weight']),
        esc_attr($typo['description']['font-family']),
        esc_attr($typo['description']['font-size']),
        esc_attr($typo['description']['line-height']),
        esc_attr($typo['description']['color']),
        esc_attr($typo['description']['font-weight'])
    );
}

/**
 * Enqueue frontend styles for RSS player (base styles always loaded)
 */
function podloom_enqueue_rss_styles() {
    // Always enqueue base player styles
    wp_enqueue_style(
        'podloom-rss-player',
        PODLOOM_PLUGIN_URL . 'assets/css/rss-player.css',
        [],
        PODLOOM_PLUGIN_VERSION
    );

    $custom_css = podloom_get_rss_dynamic_css();
    if ($custom_css) {
        wp_add_inline_style('podloom-rss-player', $custom_css);
    }
}
add_action('wp_enqueue_scripts', 'podloom_enqueue_rss_styles');

/**
 * Conditionally enqueue Podcasting 2.0 assets in footer
 * Only loads if P2.0 content was rendered on the page
 */
function podloom_enqueue_podcast20_assets() {
    global $podloom_has_podcast20_content;

    // Only load P2.0 assets if content exists
    if (!$podloom_has_podcast20_content) {
        echo '<!-- PodLoom: No P2.0 content detected, assets not loaded -->';
        return;
    }

    // Enqueue Podcasting 2.0 styles
    wp_enqueue_style(
        'podloom-podcast20',
        PODLOOM_PLUGIN_URL . 'assets/css/podcast20-styles.css',
        [],
        PODLOOM_PLUGIN_VERSION
    );

    // Enqueue Podcasting 2.0 chapter navigation script
    wp_enqueue_script(
        'podloom-podcast20-chapters',
        PODLOOM_PLUGIN_URL . 'assets/js/podcast20-chapters.js',
        [],
        PODLOOM_PLUGIN_VERSION,
        true // Load in footer
    );

    // Pass AJAX URL to the script for transcript proxy
    wp_localize_script('podloom-podcast20-chapters', 'podloomTranscript', [
        'ajaxUrl' => admin_url('admin-ajax.php')
    ]);

    echo '<!-- PodLoom: P2.0 assets loaded (~11KB CSS + ~22KB JS) -->';
}
add_action('wp_footer', 'podloom_enqueue_podcast20_assets', 5);

/**
 * Enqueue editor styles for RSS player (uses same CSS as frontend)
 */
function podloom_enqueue_editor_styles() {
    wp_enqueue_style(
        'podloom-rss-player-editor',
        PODLOOM_PLUGIN_URL . 'assets/css/rss-player.css',
        [],
        PODLOOM_PLUGIN_VERSION
    );

    $custom_css = podloom_get_rss_dynamic_css();
    if ($custom_css) {
        wp_add_inline_style('podloom-rss-player-editor', $custom_css);
    }
}
add_action('enqueue_block_editor_assets', 'podloom_enqueue_editor_styles');

/**
 * Render callback for the block (frontend display)
 */
function podloom_render_block($attributes) {
    // Get source type
    $source_type = isset($attributes['sourceType']) ? $attributes['sourceType'] : 'transistor';

    // Validate display mode
    $display_mode = isset($attributes['displayMode']) && in_array($attributes['displayMode'], ['specific', 'latest', 'playlist'], true)
        ? $attributes['displayMode']
        : 'specific';

    // Handle RSS episodes
    if ($source_type === 'rss') {
        $feed_id = isset($attributes['rssFeedId']) ? $attributes['rssFeedId'] : '';
        if (empty($feed_id)) {
            return '';
        }

        // Verify feed still exists
        $feed = Podloom_RSS::get_feed($feed_id);
        if (!$feed) {
            // Feed was deleted - show user-friendly message
            return '<div class="wp-block-podloom-episode-player" style="padding: 20px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">' .
                   '<p style="margin: 0; color: #856404;"><strong>' . esc_html__('RSS Feed Not Found', 'podloom-podcast-player') . '</strong></p>' .
                   '<p style="margin: 5px 0 0 0; color: #856404; font-size: 14px;">' . esc_html__('The RSS feed used by this block has been removed. Please select a different feed or remove this block.', 'podloom-podcast-player') . '</p>' .
                   '</div>';
        }

        if ($display_mode === 'latest') {
            $latest_episode = Podloom_RSS::get_latest_episode($feed_id);
            if (!$latest_episode) {
                return '';
            }

            // Create temporary attributes with the latest episode data
            $rss_attributes = $attributes;
            $rss_attributes['rssEpisodeData'] = $latest_episode;

            return podloom_render_rss_episode($rss_attributes);
        } else {
            return podloom_render_rss_episode($attributes);
        }
    }

    // Handle "latest episode" mode (Transistor only)
    if ($display_mode === 'latest') {
        if (empty($attributes['showSlug'])) {
            return '';
        }

        // Validate and sanitize
        $show_slug = sanitize_title($attributes['showSlug']);
        $theme = isset($attributes['theme']) && $attributes['theme'] === 'dark' ? 'dark' : 'light';
        $theme_path = $theme === 'dark' ? 'latest/dark' : 'latest';

        // Construct iframe for latest episode
        $iframe = sprintf(
            '<iframe width="100%%" height="180" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/%s/%s"></iframe>',
            esc_attr($show_slug),
            esc_attr($theme_path)
        );

        return '<div class="wp-block-podloom-episode-player">' . $iframe . '</div>';
    }

    // Handle "playlist" mode (Transistor only)
    if ($display_mode === 'playlist') {
        if (empty($attributes['showSlug'])) {
            return '';
        }

        // Validate and sanitize
        $show_slug = sanitize_title($attributes['showSlug']);
        $theme = isset($attributes['theme']) && $attributes['theme'] === 'dark' ? 'dark' : 'light';
        $theme_path = $theme === 'dark' ? 'playlist/dark' : 'playlist';

        // Validate and sanitize playlist height (min: 200, max: 1000, default: 390)
        $playlist_height = isset($attributes['playlistHeight']) ? absint($attributes['playlistHeight']) : 390;
        if ($playlist_height < 200) {
            $playlist_height = 200;
        } elseif ($playlist_height > 1000) {
            $playlist_height = 1000;
        }

        // Construct iframe for playlist
        $iframe = sprintf(
            '<iframe width="100%%" height="%d" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/%s/%s"></iframe>',
            $playlist_height,
            esc_attr($show_slug),
            esc_attr($theme_path)
        );

        return '<div class="wp-block-podloom-episode-player">' . $iframe . '</div>';
    }

    // Handle specific episode mode (Transistor)
    if (empty($attributes['embedHtml'])) {
        return '';
    }

    // Only allow iframe tags with specific attributes for security
    $allowed_html = [
        'iframe' => [
            'width' => true,
            'height' => true,
            'frameborder' => true,
            'scrolling' => true,
            'seamless' => true,
            'src' => true,
            'title' => true,
            'loading' => true,
        ]
    ];

    $safe_embed = wp_kses($attributes['embedHtml'], $allowed_html);

    return '<div class="wp-block-podloom-episode-player">' . $safe_embed . '</div>';
}

/**
 * Get typography styles for RSS elements (with caching)
 */
function podloom_get_rss_typography_styles() {
    // Try to get from cache first (uses object cache if available)
    $cached_styles = podloom_cache_get('rss_typography_cache');
    if ($cached_styles !== false) {
        return $cached_styles;
    }

    $elements = ['title', 'date', 'duration', 'description'];
    $styles = [];

    // Default values
    $defaults = [
        'title' => [
            'font_size' => '24px',
            'line_height' => '1.3',
            'color' => '#000000',
            'font_weight' => '600'
        ],
        'date' => [
            'font_size' => '14px',
            'line_height' => '1.5',
            'color' => '#666666',
            'font_weight' => 'normal'
        ],
        'duration' => [
            'font_size' => '14px',
            'line_height' => '1.5',
            'color' => '#666666',
            'font_weight' => 'normal'
        ],
        'description' => [
            'font_size' => '16px',
            'line_height' => '1.6',
            'color' => '#333333',
            'font_weight' => 'normal'
        ]
    ];

    // Load all options at once to avoid N+1 query problem (20+ queries reduced to 1)
    $all_options = wp_load_alloptions();

    foreach ($elements as $element) {
        $styles[$element] = [
            'font-family' => $all_options["podloom_rss_{$element}_font_family"] ?? 'inherit',
            'font-size' => $all_options["podloom_rss_{$element}_font_size"] ?? $defaults[$element]['font_size'],
            'line-height' => $all_options["podloom_rss_{$element}_line_height"] ?? $defaults[$element]['line_height'],
            'color' => $all_options["podloom_rss_{$element}_color"] ?? $defaults[$element]['color'],
            'font-weight' => $all_options["podloom_rss_{$element}_font_weight"] ?? $defaults[$element]['font_weight']
        ];
    }

    // Cache the styles for 1 hour (uses object cache if available)
    podloom_cache_set('rss_typography_cache', $styles, 'podloom', HOUR_IN_SECONDS);

    return $styles;
}

/**
 * Clear typography cache (called when settings are saved)
 */
function podloom_clear_typography_cache() {
    podloom_cache_delete('rss_typography_cache');
}

/**
 * Render RSS episode player
 *
 * When minimal styling mode is disabled (default):
 * - Outputs semantic HTML with inline CSS styles
 * - Applies typography settings from plugin options
 *
 * When minimal styling mode is enabled:
 * - Outputs only semantic HTML with classes
 * - No inline styles or typography settings applied
 * - Users can apply their own CSS using these classes:
 *   .wp-block-podloom-episode-player.rss-episode-player (container)
 *   .rss-episode-artwork (artwork wrapper)
 *   .rss-episode-content (content wrapper)
 *   .rss-episode-title (title heading)
 *   .rss-episode-meta (date/duration container)
 *   .rss-episode-date (date span)
 *   .rss-episode-duration (duration span)
 *   .rss-episode-audio (audio element)
 *   .rss-episode-audio.rss-audio-last (audio when last element)
 *   .rss-episode-description (description div)
 */
function podloom_render_rss_episode($attributes) {
    // Check if we have RSS episode data
    if (empty($attributes['rssEpisodeData'])) {
        return '';
    }

    $episode = $attributes['rssEpisodeData'];

    // Server-side fallback: If podcast20 data is missing (old block), fetch it from cache
    if (empty($episode['podcast20']) && !empty($attributes['rssFeedId'])) {
        $feed_id = $attributes['rssFeedId'];

        // Get all episodes from the feed cache
        $episodes_data = Podloom_RSS::get_episodes($feed_id, 1, 100); // Get first 100 episodes

        if (!empty($episodes_data['episodes'])) {
            // Try to match the episode by audio_url (most reliable) or title
            foreach ($episodes_data['episodes'] as $cached_episode) {
                $match = false;

                // Match by audio URL (most reliable)
                if (!empty($episode['audio_url']) && !empty($cached_episode['audio_url'])) {
                    if ($episode['audio_url'] === $cached_episode['audio_url']) {
                        $match = true;
                    }
                }

                // Fallback: match by title and date
                if (!$match && !empty($episode['title']) && !empty($cached_episode['title'])) {
                    if ($episode['title'] === $cached_episode['title']) {
                        $match = true;
                    }
                }

                if ($match && !empty($cached_episode['podcast20'])) {
                    // Merge fresh podcast20 data from cache
                    $episode['podcast20'] = $cached_episode['podcast20'];
                    break;
                }
            }
        }
    }

    // Character limit is now applied later when preparing description for tabs
    // This preserves HTML formatting while limiting character count

    // Get display settings
    $show_artwork = get_option('podloom_rss_display_artwork', true);
    $show_title = get_option('podloom_rss_display_title', true);
    $show_date = get_option('podloom_rss_display_date', true);
    $show_duration = get_option('podloom_rss_display_duration', true);
    $show_description = get_option('podloom_rss_display_description', true);

    // Get typography styles
    $typo = podloom_get_rss_typography_styles();

    // Get background color
    $bg_color = get_option('podloom_rss_background_color', '#f9f9f9');

    // Start building the output
    $output = '<div class="wp-block-podloom-episode-player rss-episode-player">';

    // Add a wrapper for flexbox layout
    $output .= '<div class="rss-episode-wrapper">';

    // Episode artwork
    if ($show_artwork && !empty($episode['image'])) {
        $output .= sprintf(
            '<div class="rss-episode-artwork"><img src="%s" alt="%s" /></div>',
            esc_url($episode['image']),
            esc_attr($episode['title'])
        );
    }

    // Episode content container
    $output .= '<div class="rss-episode-content">';

    // Episode header with title and funding button
    $output .= '<div class="rss-episode-header">';

    // Title and meta in left section
    $output .= '<div class="rss-episode-header-content">';

    // Episode title
    if ($show_title && !empty($episode['title'])) {
        $output .= sprintf(
            '<h3 class="rss-episode-title">%s</h3>',
            esc_html($episode['title'])
        );
    }

    // Episode meta (date and duration)
    if (($show_date && !empty($episode['date'])) || ($show_duration && !empty($episode['duration']))) {
        $output .= '<div class="rss-episode-meta">';

        if ($show_date && !empty($episode['date'])) {
            $date = date_i18n(get_option('date_format'), $episode['date']);
            $output .= sprintf(
                '<span class="rss-episode-date">%s</span>',
                esc_html($date)
            );
        }

        if ($show_duration && !empty($episode['duration'])) {
            $duration = podloom_format_duration($episode['duration']);
            if ($duration) {
                $output .= sprintf(
                    '<span class="rss-episode-duration">%s</span>',
                    esc_html($duration)
                );
            }
        }

        $output .= '</div>';
    }

    $output .= '</div>'; // .rss-episode-header-content

    // Funding button in top-right
    if (!empty($episode['podcast20'])) {
        $funding_button = podloom_get_funding_button($episode['podcast20']);
        if (!empty($funding_button)) {
            $output .= '<div class="rss-episode-header-actions">';
            $output .= $funding_button;
            $output .= '</div>';
        }
    }

    $output .= '</div>'; // .rss-episode-header

    // Audio player (always shown)
    if (!empty($episode['audio_url'])) {
        // Add class if description is hidden to remove bottom margin
        $audio_class = ($show_description && !empty($episode['description'])) ? 'rss-episode-audio' : 'rss-episode-audio rss-audio-last';
        $output .= sprintf(
            '<audio class="%s" controls preload="metadata"><source src="%s" type="%s">%s</audio>',
            esc_attr($audio_class),
            esc_url($episode['audio_url']),
            esc_attr(!empty($episode['audio_type']) ? $episode['audio_type'] : 'audio/mpeg'),
            esc_html__('Your browser does not support the audio player.', 'podloom-podcast-player')
        );
    }

    $output .= '</div>'; // .rss-episode-content
    $output .= '</div>'; // .rss-episode-wrapper

    // Debug: Show all episode keys
    $output .= '<!-- PodLoom Episode Keys: ' . implode(', ', array_keys($episode)) . ' -->';

    // Prepare description for tabs (if enabled)
    $description_html = '';
    if ($show_description && !empty($episode['description'])) {
        // Use restrictive HTML sanitization to prevent XSS from untrusted RSS feeds
        $allowed_html = array(
            'p' => array(),
            'br' => array(),
            'strong' => array(),
            'b' => array(),
            'em' => array(),
            'i' => array(),
            'u' => array(),
            'a' => array(
                'href' => array(),
                'title' => array(),
                'target' => array(),
                'rel' => array()
            ),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'blockquote' => array(),
            'code' => array(),
            'pre' => array()
        );
        $description_html = wp_kses($episode['description'], $allowed_html);

        // Additional security: validate href attributes to prevent javascript: and data: URLs
        if (!empty($description_html) && strpos($description_html, '<a ') !== false) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $description_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $links = $dom->getElementsByTagName('a');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                if ($href) {
                    $href_lower = strtolower(trim($href));
                    // Remove dangerous URL schemes
                    if (strpos($href_lower, 'javascript:') === 0 ||
                        strpos($href_lower, 'data:') === 0 ||
                        strpos($href_lower, 'vbscript:') === 0) {
                        $link->removeAttribute('href');
                    }
                }
                // Ensure external links have proper rel attribute for security
                if ($link->hasAttribute('href') && $link->getAttribute('target') === '_blank') {
                    $link->setAttribute('rel', 'noopener noreferrer');
                }
            }

            $description_html = '';
            foreach ($dom->childNodes as $child) {
                $description_html .= $dom->saveHTML($child);
            }
        }

        // Apply character limit if set (while preserving HTML)
        $char_limit = get_option('podloom_rss_description_limit', 0);
        if ($char_limit > 0) {
            $description_html = podloom_truncate_html($description_html, $char_limit);
        }
    }

    // Render Podcasting 2.0 tabs (after player, outside content wrapper)
    // Pass description to be included as first tab if enabled
    if (!empty($episode['podcast20']) || !empty($description_html)) {
        // Set global flag to indicate P2.0 content is used
        global $podloom_has_podcast20_content;
        $podloom_has_podcast20_content = true;

        $output .= podloom_render_podcast20_tabs(
            $episode['podcast20'] ?? [],
            $description_html,
            $show_description
        );
        $output .= '<!-- PodLoom: Rendered P2.0 tabs -->';
    } else {
        $output .= '<!-- PodLoom Debug: No podcast20 data or description found for this episode -->';
    }

    // Debug output
    if (!empty($episode['podcast20'])) {
        $output .= '<!-- PodLoom P2.0 Debug: ' . print_r($episode['podcast20'], true) . ' -->';
    }

    $output .= '</div>'; // .wp-block-podloom-episode-player

    // Styles are now enqueued via wp_add_inline_style() in podloom_enqueue_rss_styles()
    return $output;
}

/**
 * Add admin menu
 */
function podloom_add_admin_menu() {
    add_options_page(
        __('PodLoom Settings', 'podloom-podcast-player'),
        __('PodLoom Settings', 'podloom-podcast-player'),
        'manage_options',
        'podloom-settings',
        'podloom_render_settings_page'
    );
}
add_action('admin_menu', 'podloom_add_admin_menu');

/**
 * Add settings link on plugin page
 */
function podloom_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=podloom-settings') . '">' . __('Settings', 'podloom-podcast-player') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'podloom_add_plugin_action_links');

/**
 * Register plugin settings
 */
function podloom_register_settings() {
    register_setting('podloom_settings', 'podloom_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);

    register_setting('podloom_settings', 'podloom_default_show', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);

    register_setting('podloom_settings', 'podloom_enable_cache', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_cache_duration', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 21600
    ]);

    register_setting('podloom_settings', 'podloom_rss_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false
    ]);

    register_setting('podloom_settings', 'podloom_rss_feeds', [
        'type' => 'array',
        'sanitize_callback' => 'podloom_sanitize_rss_feeds',
        'default' => []
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_artwork', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_title', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_date', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_duration', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_description', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    // Podcasting 2.0 element display settings
    register_setting('podloom_settings', 'podloom_rss_display_funding', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_transcripts', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_people_hosts', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_people_guests', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('podloom_settings', 'podloom_rss_display_chapters', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    // Typography settings for RSS title
    register_setting('podloom_settings', 'podloom_rss_title_font_family', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'inherit'
    ]);
    register_setting('podloom_settings', 'podloom_rss_title_font_size', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '24px'
    ]);
    register_setting('podloom_settings', 'podloom_rss_title_line_height', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1.3'
    ]);
    register_setting('podloom_settings', 'podloom_rss_title_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#000000'
    ]);
    register_setting('podloom_settings', 'podloom_rss_title_font_weight', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '600'
    ]);

    // Typography settings for RSS date
    register_setting('podloom_settings', 'podloom_rss_date_font_family', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'inherit'
    ]);
    register_setting('podloom_settings', 'podloom_rss_date_font_size', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '14px'
    ]);
    register_setting('podloom_settings', 'podloom_rss_date_line_height', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1.5'
    ]);
    register_setting('podloom_settings', 'podloom_rss_date_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#666666'
    ]);
    register_setting('podloom_settings', 'podloom_rss_date_font_weight', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'normal'
    ]);

    // Typography settings for RSS duration
    register_setting('podloom_settings', 'podloom_rss_duration_font_family', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'inherit'
    ]);
    register_setting('podloom_settings', 'podloom_rss_duration_font_size', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '14px'
    ]);
    register_setting('podloom_settings', 'podloom_rss_duration_line_height', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1.5'
    ]);
    register_setting('podloom_settings', 'podloom_rss_duration_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#666666'
    ]);
    register_setting('podloom_settings', 'podloom_rss_duration_font_weight', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'normal'
    ]);

    // Typography settings for RSS description
    register_setting('podloom_settings', 'podloom_rss_description_font_family', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'inherit'
    ]);
    register_setting('podloom_settings', 'podloom_rss_description_font_size', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '16px'
    ]);
    register_setting('podloom_settings', 'podloom_rss_description_line_height', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => '1.6'
    ]);
    register_setting('podloom_settings', 'podloom_rss_description_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#333333'
    ]);
    register_setting('podloom_settings', 'podloom_rss_description_font_weight', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'normal'
    ]);

    // Background color for RSS block
    register_setting('podloom_settings', 'podloom_rss_background_color', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_hex_color',
        'default' => '#f9f9f9'
    ]);

    // Minimal styling mode
    register_setting('podloom_settings', 'podloom_rss_minimal_styling', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false
    ]);

    // Description character limit
    register_setting('podloom_settings', 'podloom_rss_description_limit', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0
    ]);

    // Player height
    register_setting('podloom_settings', 'podloom_rss_player_height', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 600
    ]);

    register_setting('podloom_settings', 'podloom_rss_cache_duration', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 21600 // 6 hours in seconds
    ]);
}
add_action('admin_init', 'podloom_register_settings');

/**
 * Sanitize RSS feeds array
 */
function podloom_sanitize_rss_feeds($feeds) {
    if (!is_array($feeds)) {
        return [];
    }
    return $feeds;
}

/**
 * Get funding button HTML (for top-right positioning)
 *
 * @param array $p20_data Podcasting 2.0 data from parser
 * @return string HTML output
 */
function podloom_get_funding_button($p20_data) {
    $display_funding = get_option('podloom_rss_display_funding', true);

    if ($display_funding && !empty($p20_data['funding'])) {
        return podloom_render_funding($p20_data['funding']);
    }

    return '';
}

/**
 * Render Podcasting 2.0 elements as tabbed interface
 *
 * @param array $p20_data Podcasting 2.0 data from parser
 * @param string $description_html Sanitized episode description HTML
 * @param bool $show_description Whether to show description tab
 * @return string HTML output
 */
function podloom_render_podcast20_tabs($p20_data, $description_html = '', $show_description = true) {
    // Get display settings
    $display_transcripts = get_option('podloom_rss_display_transcripts', true);
    $display_people_hosts = get_option('podloom_rss_display_people_hosts', true);
    $display_people_guests = get_option('podloom_rss_display_people_guests', true);
    $display_chapters = get_option('podloom_rss_display_chapters', true);

    // Build tabs array: [id, label, content]
    $tabs = [];

    // Tab 0: Description (if enabled and has content)
    if ($show_description && !empty($description_html)) {
        $tabs[] = [
            'id' => 'description',
            'label' => __('Description', 'podloom-podcast-player'),
            'content' => '<div class="rss-episode-description">' . $description_html . '</div>'
        ];
    }

    // Ensure p20_data is an array
    if (!is_array($p20_data)) {
        $p20_data = [];
    }

    // Tab: Credits (People)
    $people_to_show = [];
    if ($display_people_hosts && !empty($p20_data['people_channel'])) {
        $people_to_show = array_merge($people_to_show, $p20_data['people_channel']);
    }
    if ($display_people_guests && !empty($p20_data['people_episode'])) {
        $people_to_show = array_merge($people_to_show, $p20_data['people_episode']);
    }
    if (!empty($people_to_show)) {
        usort($people_to_show, function($a, $b) {
            $priority = ['host' => 1, 'co-host' => 2, 'guest' => 3];
            $a_priority = $priority[strtolower($a['role'])] ?? 999;
            $b_priority = $priority[strtolower($b['role'])] ?? 999;
            return $a_priority - $b_priority;
        });
        $tabs[] = [
            'id' => 'credits',
            'label' => __('Credits', 'podloom-podcast-player'),
            'content' => podloom_render_people($people_to_show)
        ];
    }

    // Tab: Chapters
    if ($display_chapters && !empty($p20_data['chapters'])) {
        $tabs[] = [
            'id' => 'chapters',
            'label' => __('Chapters', 'podloom-podcast-player'),
            'content' => podloom_render_chapters($p20_data['chapters'])
        ];
    }

    // Tab: Transcripts
    if ($display_transcripts && !empty($p20_data['transcripts'])) {
        $tabs[] = [
            'id' => 'transcripts',
            'label' => __('Transcripts', 'podloom-podcast-player'),
            'content' => podloom_render_transcripts($p20_data['transcripts'])
        ];
    }

    // If no tabs, return empty
    if (empty($tabs)) {
        return '';
    }

    // Build tab navigation
    $output = '<div class="podcast20-tabs">';
    $output .= '<div class="podcast20-tab-nav" role="tablist">';

    foreach ($tabs as $index => $tab) {
        $is_active = ($index === 0) ? 'active' : '';
        $output .= sprintf(
            '<button class="podcast20-tab-button %s" data-tab="%s" role="tab" aria-selected="%s" aria-controls="tab-panel-%s">%s</button>',
            $is_active,
            esc_attr($tab['id']),
            $is_active ? 'true' : 'false',
            esc_attr($tab['id']),
            esc_html($tab['label'])
        );
    }

    $output .= '</div>'; // .podcast20-tab-nav

    // Build tab panels
    foreach ($tabs as $index => $tab) {
        $is_active = ($index === 0) ? 'active' : '';
        $output .= sprintf(
            '<div class="podcast20-tab-panel %s" id="tab-panel-%s" role="tabpanel" aria-labelledby="%s">%s</div>',
            $is_active,
            esc_attr($tab['id']),
            esc_attr($tab['id']),
            $tab['content']
        );
    }

    $output .= '</div>'; // .podcast20-tabs

    return $output;
}

/**
 * Render Podcasting 2.0 elements (legacy function for backwards compatibility)
 *
 * @param array $p20_data Podcasting 2.0 data from parser
 * @return string HTML output
 */
function podloom_render_podcast20_elements($p20_data) {
    return podloom_render_podcast20_tabs($p20_data);
}

/**
 * Render podcast:funding tag
 *
 * @param array $funding Funding data
 * @return string HTML output
 */
function podloom_render_funding($funding) {
    if (empty($funding['url'])) {
        return '';
    }

    return sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-funding-button">
            <svg class="podcast20-icon" width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
            </svg>
            <span>%s</span>
        </a>',
        esc_url($funding['url']),
        esc_html($funding['text'])
    );
}

/**
 * Render podcast:transcript tags
 *
 * @param array $transcripts Array of transcript objects
 * @return string HTML output
 */
function podloom_render_transcripts($transcripts) {
    if (empty($transcripts) || !is_array($transcripts)) {
        return '';
    }

    // Check if any .txt transcripts exist and add potential HTML versions
    $has_html = false;
    foreach ($transcripts as $transcript) {
        if (($transcript['type'] ?? '') === 'text/html') {
            $has_html = true;
            break;
        }
    }

    // If no HTML transcript exists, check for .txt files and generate HTML alternatives
    if (!$has_html) {
        $additional_transcripts = [];
        foreach ($transcripts as $transcript) {
            $url = $transcript['url'] ?? '';
            $type = $transcript['type'] ?? '';

            // If this is a text/plain or .txt file, try HTML version
            if (($type === 'text/plain' || strpos($url, '.txt') !== false) && !empty($url)) {
                // Generate potential HTML URL by replacing .txt with .html
                $html_url = preg_replace('/\.txt$/i', '.html', $url);

                // Only add if it's actually different (i.e., URL ended with .txt)
                if ($html_url !== $url) {
                    $additional_transcripts[] = [
                        'url' => $html_url,
                        'type' => 'text/html',
                        'label' => $transcript['label'] ?? '',
                        'language' => $transcript['language'] ?? ''
                    ];
                }
            }
        }

        // Add potential HTML transcripts to the array
        if (!empty($additional_transcripts)) {
            $transcripts = array_merge($additional_transcripts, $transcripts);
        }
    }

    // Sort transcripts by format preference: HTML > SRT > VTT > JSON > text/plain > other
    $format_priority = [
        'text/html' => 1,
        'application/x-subrip' => 2,
        'text/srt' => 2,
        'text/vtt' => 3,
        'application/json' => 4,
        'text/plain' => 5
    ];

    usort($transcripts, function($a, $b) use ($format_priority) {
        $a_priority = $format_priority[$a['type'] ?? ''] ?? 999;
        $b_priority = $format_priority[$b['type'] ?? ''] ?? 999;
        return $a_priority - $b_priority;
    });

    // Use the first (highest priority) transcript for display
    $primary_transcript = $transcripts[0];

    if (empty($primary_transcript['url'])) {
        return '';
    }

    $output = '<div class="podcast20-transcripts">';

    // Transcript format button - include all transcripts as fallbacks
    $output .= '<div class="transcript-formats">';
    $output .= sprintf(
        '<button class="transcript-format-button" data-url="%s" data-type="%s" data-transcripts="%s">
            <svg class="podcast20-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M14 4.5V14a2 2 0 01-2 2H4a2 2 0 01-2-2V2a2 2 0 012-2h5.5L14 4.5zm-3 0A1.5 1.5 0 019.5 3V1H4a1 1 0 00-1 1v12a1 1 0 001 1h8a1 1 0 001-1V4.5h-2z"/>
                <path d="M3 9.5h10v1H3v-1zm0 2h10v1H3v-1z"/>
            </svg>
            <span>%s</span>
        </button>',
        esc_url($primary_transcript['url']),
        esc_attr($primary_transcript['type'] ?? 'text/plain'),
        esc_attr(wp_json_encode($transcripts)),
        esc_html__('Click for Transcript', 'podloom-podcast-player')
    );

    // Fallback link for no-JS - use same external link icon as chapters
    $output .= sprintf(
        ' <a href="%s" target="_blank" rel="noopener noreferrer" class="transcript-external-link" title="%s">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                <path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>
                <path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>
            </svg>
        </a>',
        esc_url($primary_transcript['url']),
        esc_attr__('Open transcript in new tab', 'podloom-podcast-player')
    );

    $output .= '</div>'; // .transcript-formats

    // Transcript viewer (hidden by default)
    $output .= '<div class="transcript-viewer" style="display:none;">';
    $output .= '<div class="transcript-content"></div>';
    $output .= '<button class="transcript-close">' . esc_html__('Close', 'podloom-podcast-player') . '</button>';
    $output .= '</div>'; // .transcript-viewer

    $output .= '</div>'; // .podcast20-transcripts

    return $output;
}

/**
 * Render podcast:person tags
 *
 * @param array $people Array of person objects
 * @return string HTML output
 */
function podloom_render_people($people) {
    if (empty($people) || !is_array($people)) {
        return '';
    }

    $output = '<div class="podcast20-people">';
    $output .= '<h4 class="podcast20-heading">' . esc_html__('Credits', 'podloom-podcast-player') . '</h4>';
    $output .= '<div class="podcast20-people-list">';

    foreach ($people as $person) {
        if (empty($person['name'])) {
            continue;
        }

        $output .= '<div class="podcast20-person">';

        // Person image
        if (!empty($person['img'])) {
            $output .= sprintf(
                '<img src="%s" alt="%s" class="podcast20-person-img">',
                esc_url($person['img']),
                esc_attr($person['name'])
            );
        } else {
            // Default avatar icon
            $output .= '<div class="podcast20-person-avatar">
                <svg width="40" height="40" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M11 6a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path d="M2 0a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V2a2 2 0 00-2-2H2zm12 1a1 1 0 011 1v12a1 1 0 01-1 1v-1c0-1-1-4-6-4s-6 3-6 4v1a1 1 0 01-1-1V2a1 1 0 011-1h12z"/>
                </svg>
            </div>';
        }

        $output .= '<div class="podcast20-person-info">';

        // Role
        if (!empty($person['role'])) {
            $output .= sprintf(
                '<span class="podcast20-person-role">%s</span>',
                esc_html(ucfirst($person['role']))
            );
        }

        // Name (linked or plain text)
        if (!empty($person['href'])) {
            $output .= sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-person-name">%s</a>',
                esc_url($person['href']),
                esc_html($person['name'])
            );
        } else {
            $output .= sprintf(
                '<span class="podcast20-person-name">%s</span>',
                esc_html($person['name'])
            );
        }

        $output .= '</div>'; // .podcast20-person-info
        $output .= '</div>'; // .podcast20-person
    }

    $output .= '</div></div>'; // .podcast20-people-list and .podcast20-people

    return $output;
}

/**
 * Render podcast:chapters tag
 *
 * @param array $chapters Chapters data
 * @return string HTML output
 */
function podloom_render_chapters($chapters) {
    if (empty($chapters)) {
        return '';
    }

    // If no chapters array is available, show link to chapters JSON
    if (empty($chapters['chapters']) || !is_array($chapters['chapters'])) {
        if (!empty($chapters['url'])) {
            return sprintf(
                '<div class="podcast20-chapters">
                    <a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-chapters-link">
                        <svg class="podcast20-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M1 2.5A1.5 1.5 0 012.5 1h3A1.5 1.5 0 017 2.5v3A1.5 1.5 0 015.5 7h-3A1.5 1.5 0 011 5.5v-3zm8 0A1.5 1.5 0 0110.5 1h3A1.5 1.5 0 0115 2.5v3A1.5 1.5 0 0113.5 7h-3A1.5 1.5 0 019 5.5v-3zm-8 8A1.5 1.5 0 012.5 9h3A1.5 1.5 0 017 10.5v3A1.5 1.5 0 15.5 15h-3A1.5 1.5 0 011 13.5v-3zm8 0A1.5 1.5 0 0110.5 9h3a1.5 1.5 0 011.5 1.5v3a1.5 1.5 0 01-1.5 1.5h-3A1.5 1.5 0 019 13.5v-3z"/>
                        </svg>
                        <span>%s</span>
                    </a>
                </div>',
                esc_url($chapters['url']),
                esc_html__('View Chapters', 'podloom-podcast-player')
            );
        }
        return '';
    }

    // Render full chapter list
    $output = '<div class="podcast20-chapters-list">';
    $output .= '<h4 class="chapters-heading">' . esc_html__('Chapters', 'podloom-podcast-player') . '</h4>';

    foreach ($chapters['chapters'] as $chapter) {
        $start_time = $chapter['startTime'];
        $formatted_time = podloom_format_timestamp($start_time);
        $title = $chapter['title'];

        $output .= '<div class="chapter-item" data-start-time="' . esc_attr($start_time) . '">';

        // Chapter image
        if (!empty($chapter['img'])) {
            $output .= sprintf(
                '<img src="%s" alt="%s" class="chapter-img" loading="lazy" />',
                esc_url($chapter['img']),
                esc_attr($title)
            );
        } else {
            // Placeholder if no image
            $output .= '<div class="chapter-img-placeholder"></div>';
        }

        // Chapter info
        $output .= '<div class="chapter-info">';
        $output .= '<button class="chapter-timestamp" data-start-time="' . esc_attr($start_time) . '">';
        $output .= esc_html($formatted_time);
        $output .= '</button>';

        // Chapter title (always a span, never a link)
        $output .= '<span class="chapter-title">' . esc_html($title);

        // If chapter has a URL, add external link icon
        if (!empty($chapter['url'])) {
            $output .= sprintf(
                ' <a href="%s" target="_blank" rel="noopener noreferrer" class="chapter-external-link" title="%s">
                    <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-left: 4px;">
                        <path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>
                        <path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>
                    </svg>
                </a>',
                esc_url($chapter['url']),
                esc_attr__('Open chapter link', 'podloom-podcast-player')
            );
        }

        $output .= '</span>';

        $output .= '</div>'; // .chapter-info
        $output .= '</div>'; // .chapter-item
    }

    $output .= '</div>'; // .podcast20-chapters-list

    return $output;
}
