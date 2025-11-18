<?php
/**
 * Temporary P2.0 Debug Page
 *
 * Add this to wp-admin menu temporarily to debug P2.0 parsing
 */

// Add to WordPress admin menu (with higher priority to ensure parent exists)
add_action('admin_menu', 'podloom_debug_p20_menu', 20);

function podloom_debug_p20_menu() {
    add_submenu_page(
        'options-general.php', // Parent is Settings menu
        'P2.0 Debug',
        'P2.0 Debug',
        'manage_options',
        'podloom-p20-debug',
        'podloom_debug_p20_page'
    );
}

function podloom_debug_p20_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1>Podcasting 2.0 Debug</h1>';

    // Get all RSS feeds
    $feeds = get_option('podloom_rss_feeds', []);

    if (empty($feeds)) {
        echo '<p>No RSS feeds found.</p>';
        echo '</div>';
        return;
    }

    foreach ($feeds as $feed_id => $feed_data) {
        echo '<h2>' . esc_html($feed_data['name']) . '</h2>';
        echo '<p><strong>URL:</strong> ' . esc_html($feed_data['url']) . '</p>';

        // Get cached episodes
        $episodes = get_transient('podloom_rss_episodes_' . $feed_id);

        if (empty($episodes)) {
            echo '<p style="color: red;">No cached episodes found. Try refreshing the feed.</p>';
            continue;
        }

        echo '<p><strong>Total Episodes Cached:</strong> ' . count($episodes) . '</p>';

        // Show first episode's P2.0 data
        $first_episode = $episodes[0];
        echo '<h3>First Episode: ' . esc_html($first_episode['title']) . '</h3>';

        if (isset($first_episode['podcast20'])) {
            echo '<h4>Podcasting 2.0 Data:</h4>';
            echo '<pre style="background: #f5f5f5; padding: 15px; overflow: auto; max-height: 500px;">';
            print_r($first_episode['podcast20']);
            echo '</pre>';
        } else {
            echo '<p style="color: red; font-weight: bold;">⚠️ No podcast20 data found in cached episode!</p>';
        }

        echo '<hr>';
    }

    echo '</div>';
}
