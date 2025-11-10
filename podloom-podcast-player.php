<?php
/**
 * Plugin Name: PodLoom Podcast Player for Transistor.fm
 * Plugin URI: https://thewpminute.com/podloom/
 * Description: Connect to your Transistor.fm account and embed podcast episodes using Gutenberg blocks.
 * Version: 1.1.0
 * Author: WP Minute
 * Author URI: https://thewpminute.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: podloom
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TRANSISTOR_PLUGIN_VERSION', '1.1.0');
define('TRANSISTOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRANSISTOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once TRANSISTOR_PLUGIN_DIR . 'includes/api.php';
require_once TRANSISTOR_PLUGIN_DIR . 'admin/admin-functions.php';

/**
 * Initialize the plugin and register block
 */
function transistor_init() {
    // Register the block editor script
    wp_register_script(
        'transistor-block-editor',
        TRANSISTOR_PLUGIN_URL . 'blocks/episode-block/index.js',
        ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
        filemtime(TRANSISTOR_PLUGIN_DIR . 'blocks/episode-block/index.js')
    );

    // Pass data to JavaScript
    wp_localize_script('transistor-block-editor', 'transistorData', [
        'defaultShow' => get_option('transistor_default_show', ''),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('transistor_nonce'),
        'hasApiKey' => !empty(get_option('transistor_api_key', ''))
    ]);

    // Register the block type
    register_block_type('podloom/episode-player', [
        'api_version' => 2,
        'editor_script' => 'transistor-block-editor',
        'title' => __('Podcast Episode', 'podloom'),
        'description' => __('Embed a Transistor.fm podcast episode player', 'podloom'),
        'category' => 'media',
        'icon' => 'microphone',
        'keywords' => ['podcast', 'audio', 'transistor', 'episode'],
        'supports' => [
            'html' => false
        ],
        'attributes' => [
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
        'render_callback' => 'transistor_render_block'
    ]);
}
add_action('init', 'transistor_init');

/**
 * Render callback for the block (frontend display)
 */
function transistor_render_block($attributes) {
    // Validate display mode
    $display_mode = isset($attributes['displayMode']) && in_array($attributes['displayMode'], ['specific', 'latest', 'playlist'], true)
        ? $attributes['displayMode']
        : 'specific';

    // Handle "latest episode" mode
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

        return '<div class="wp-block-transistor-episode-player">' . $iframe . '</div>';
    }

    // Handle "playlist" mode
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

        return '<div class="wp-block-transistor-episode-player">' . $iframe . '</div>';
    }

    // Handle specific episode mode
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

    return '<div class="wp-block-transistor-episode-player">' . $safe_embed . '</div>';
}

/**
 * Add admin menu
 */
function transistor_add_admin_menu() {
    add_options_page(
        __('PodLoom Settings', 'podloom'),
        __('PodLoom Settings', 'podloom'),
        'manage_options',
        'transistor-api-settings',
        'transistor_render_settings_page'
    );
}
add_action('admin_menu', 'transistor_add_admin_menu');

/**
 * Add settings link on plugin page
 */
function transistor_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=transistor-api-settings') . '">' . __('Settings', 'podloom') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'transistor_add_plugin_action_links');

/**
 * Register plugin settings
 */
function transistor_register_settings() {
    register_setting('transistor_settings', 'transistor_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);

    register_setting('transistor_settings', 'transistor_default_show', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);

    register_setting('transistor_settings', 'transistor_enable_cache', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true
    ]);

    register_setting('transistor_settings', 'transistor_cache_duration', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 21600
    ]);
}
add_action('admin_init', 'transistor_register_settings');
