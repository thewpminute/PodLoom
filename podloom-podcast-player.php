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

// Include necessary files
require_once PODLOOM_PLUGIN_DIR . 'includes/api.php';
require_once PODLOOM_PLUGIN_DIR . 'includes/rss.php';
require_once PODLOOM_PLUGIN_DIR . 'admin/admin-functions.php';

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
        "SELECT option_name, option_value
         FROM $wpdb->options
         WHERE option_name LIKE '_transient_transistor_%'
         OR option_name LIKE '_transient_timeout_transistor_%'"
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
 * Generate dynamic typography CSS for RSS player
 */
function podloom_get_rss_dynamic_css() {
    $minimal_styling = get_option('podloom_rss_minimal_styling', false);
    if ($minimal_styling) {
        return '';
    }

    $typo = podloom_get_rss_typography_styles();
    $bg_color = get_option('podloom_rss_background_color', '#f9f9f9');

    return sprintf('
        .wp-block-podloom-episode-player.rss-episode-player {
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
 * Enqueue frontend styles for RSS player
 */
function podloom_enqueue_rss_styles() {
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
    // Try to get from cache first
    $cached_styles = get_transient('podloom_rss_typography_cache');
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

    foreach ($elements as $element) {
        $styles[$element] = [
            'font-family' => get_option("podloom_rss_{$element}_font_family", 'inherit'),
            'font-size' => get_option("podloom_rss_{$element}_font_size", $defaults[$element]['font_size']),
            'line-height' => get_option("podloom_rss_{$element}_line_height", $defaults[$element]['line_height']),
            'color' => get_option("podloom_rss_{$element}_color", $defaults[$element]['color']),
            'font-weight' => get_option("podloom_rss_{$element}_font_weight", $defaults[$element]['font_weight'])
        ];
    }

    // Cache the styles for 1 hour
    set_transient('podloom_rss_typography_cache', $styles, HOUR_IN_SECONDS);

    return $styles;
}

/**
 * Clear typography cache (called when settings are saved)
 */
function podloom_clear_typography_cache() {
    delete_transient('podloom_rss_typography_cache');
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

    // Episode description (character limit already applied in backend)
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
        $output .= sprintf(
            '<div class="rss-episode-description">%s</div>',
            wp_kses($episode['description'], $allowed_html)
        );
    }

    $output .= '</div>'; // .rss-episode-content
    $output .= '</div>'; // .rss-episode-wrapper
    $output .= '</div>'; // .wp-block-podloom-episode-player

    // Styles are now enqueued via wp_add_inline_style() in podloom_enqueue_rss_styles()
    return $output;
}

/**
 * Format duration from seconds to readable format
 */
function podloom_format_duration($seconds) {
    if (empty($seconds) || !is_numeric($seconds)) {
        return '';
    }

    $seconds = intval($seconds);
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    } else {
        return sprintf('%d:%02d', $minutes, $secs);
    }
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
