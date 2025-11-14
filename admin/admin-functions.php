<?php
/**
 * Admin Functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue admin scripts and styles
 */
function podloom_enqueue_admin_scripts($hook) {
    // Only load on our settings page
    if ($hook !== 'settings_page_podloom-settings') {
        return;
    }

    // Enqueue settings page general script
    wp_enqueue_script(
        'podloom-settings-page',
        PODLOOM_PLUGIN_URL . 'admin/js/settings-page.js',
        ['jquery'],
        PODLOOM_PLUGIN_VERSION,
        true
    );

    // Enqueue typography manager (for RSS tab)
    wp_enqueue_script(
        'podloom-typography-manager',
        PODLOOM_PLUGIN_URL . 'admin/js/typography-manager.js',
        [],
        PODLOOM_PLUGIN_VERSION,
        true
    );

    // Enqueue RSS manager (for RSS tab)
    wp_enqueue_script(
        'podloom-rss-manager',
        PODLOOM_PLUGIN_URL . 'admin/js/rss-manager.js',
        ['jquery', 'podloom-typography-manager'],
        PODLOOM_PLUGIN_VERSION,
        true
    );

    // Localize scripts with translatable strings and data
    wp_localize_script('podloom-rss-manager', 'podloomData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('podloom_nonce'),
        'strings' => [
            // Feed management
            'addNewFeed' => __('Add New RSS Feed', 'podloom-podcast-player'),
            'editFeedName' => __('Edit Feed Name', 'podloom-podcast-player'),
            'feedName' => __('Feed Name', 'podloom-podcast-player'),
            'feedUrl' => __('Feed URL', 'podloom-podcast-player'),
            'feedNamePlaceholder' => __('e.g., My Podcast', 'podloom-podcast-player'),
            'feedUrlPlaceholder' => __('https://example.com/feed.xml', 'podloom-podcast-player'),
            'enterFeedName' => __('Please enter a feed name.', 'podloom-podcast-player'),
            'fillAllFields' => __('Please fill in all fields.', 'podloom-podcast-player'),

            // Actions
            'save' => __('Save', 'podloom-podcast-player'),
            'cancel' => __('Cancel', 'podloom-podcast-player'),
            'adding' => __('Adding...', 'podloom-podcast-player'),
            'saving' => __('Saving...', 'podloom-podcast-player'),
            'refreshing' => __('Refreshing...', 'podloom-podcast-player'),
            'saveRssSettings' => __('Save RSS Settings', 'podloom-podcast-player'),

            // Messages
            'errorAddingFeed' => __('Error adding feed.', 'podloom-podcast-player'),
            'errorUpdatingFeed' => __('Error updating feed name.', 'podloom-podcast-player'),
            'errorLoadingFeed' => __('Error loading feed', 'podloom-podcast-player'),
            'errorSavingSettings' => __('Error saving settings.', 'podloom-podcast-player'),
            'settingsSavedSuccess' => __('RSS settings saved successfully!', 'podloom-podcast-player'),
            'unknownError' => __('Unknown error', 'podloom-podcast-player'),
            'deleteFeedConfirm' => __('Are you sure you want to delete this RSS feed? This action cannot be undone.', 'podloom-podcast-player'),
            'rssFeedXml' => __('RSS Feed XML', 'podloom-podcast-player')
        ]
    ]);

    // Initialize scripts based on current tab
    $allowed_tabs = array('welcome', 'general', 'transistor', 'rss');
    $current_tab = 'welcome';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading tab for display only, using whitelist validation
    if (isset($_GET['tab'])) {
        $requested_tab = sanitize_text_field(wp_unslash($_GET['tab']));
        // Whitelist validation to prevent arbitrary values
        if (in_array($requested_tab, $allowed_tabs, true)) {
            $current_tab = $requested_tab;
        }
    }
    $init_script = '';

    if ($current_tab === 'rss') {
        $init_script = '
            if (window.podloomTypographyManager) {
                window.podloomTypographyManager.init();
            }
            if (window.podloomRssManager) {
                window.podloomRssManager.init();
            }
        ';
    }

    if ($init_script) {
        wp_add_inline_script('podloom-rss-manager', 'document.addEventListener("DOMContentLoaded", function() {' . $init_script . '});');
    }
}
add_action('admin_enqueue_scripts', 'podloom_enqueue_admin_scripts');

/**
 * Handle plugin reset request before any output
 * This needs to run early to allow redirects
 */
function podloom_handle_plugin_reset() {
    // Only run on our settings page
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking page parameter for routing only, actual form processing has nonce verification
    if (!isset($_GET['page']) || sanitize_text_field(wp_unslash($_GET['page'])) !== 'podloom-settings') {
        return;
    }

    // Handle plugin reset request
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
    if (isset($_POST['podloom_reset_plugin'])) {
        check_admin_referer('podloom_reset_plugin', 'podloom_reset_plugin_nonce');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'podloom-podcast-player'));
        }

        $reset_confirmation = isset($_POST['reset_confirmation']) ? sanitize_text_field(wp_unslash($_POST['reset_confirmation'])) : '';

        if ($reset_confirmation === 'RESET') {
            // Delete all plugin options and cache
            podloom_delete_all_plugin_data();

            // Redirect to settings page to reload with clean state
            $redirect_url = add_query_arg(
                array(
                    'page' => 'podloom-settings',
                    'reset' => 'success'
                ),
                admin_url('options-general.php')
            );

            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}
add_action('admin_init', 'podloom_handle_plugin_reset');

/**
 * Render the settings page
 */
function podloom_render_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get current tab with nonce verification and whitelist validation
    $allowed_tabs = array('welcome', 'general', 'transistor', 'rss');
    $current_tab = 'welcome';

    if (isset($_GET['tab'])) {
        // Require nonce verification for tab switching
        if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'podloom_switch_tab')) {
            $requested_tab = sanitize_text_field(wp_unslash($_GET['tab']));
            // Whitelist validation to prevent arbitrary values
            if (in_array($requested_tab, $allowed_tabs, true)) {
                $current_tab = $requested_tab;
            }
        }
        // If tab parameter exists but nonce is invalid/missing, stay on default tab
    }

    // Success/error messages
    $success_message = '';
    $error_message = '';

    // Handle clear cache request
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
    if (isset($_POST['podloom_clear_cache'])) {
        check_admin_referer('podloom_clear_cache', 'podloom_clear_cache_nonce');
        podloom_clear_all_cache();
        $success_message = esc_html__('Cache cleared successfully!', 'podloom-podcast-player');
    }

    // Handle plugin reset validation error (successful reset is handled in admin_init hook)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
    if (isset($_POST['podloom_reset_plugin'])) {
        check_admin_referer('podloom_reset_plugin', 'podloom_reset_plugin_nonce');

        $reset_confirmation = isset($_POST['reset_confirmation']) ? sanitize_text_field(wp_unslash($_POST['reset_confirmation'])) : '';

        if ($reset_confirmation !== 'RESET') {
            $error_message = esc_html__('Reset failed: You must type RESET in the confirmation field.', 'podloom-podcast-player');
        }
    }

    // Check for reset success message from redirect
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading success parameter from redirect, no form data being processed
    if (isset($_GET['reset']) && sanitize_text_field(wp_unslash($_GET['reset'])) === 'success') {
        $success_message = esc_html__('All PodLoom settings and cache have been deleted successfully. The plugin has been reset to default state.', 'podloom-podcast-player');
    }

    // Handle form submission
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
    if (isset($_POST['podloom_settings_submit'])) {
        check_admin_referer('podloom_settings_save', 'podloom_settings_nonce');

        $api_key = isset($_POST['podloom_api_key']) ? sanitize_text_field(wp_unslash($_POST['podloom_api_key'])) : '';
        $default_show = isset($_POST['podloom_default_show']) ? sanitize_text_field(wp_unslash($_POST['podloom_default_show'])) : '';

        // Handle checkbox - when checked it's '1', when unchecked the field is not submitted
        $enable_cache = isset($_POST['podloom_enable_cache']) && sanitize_text_field(wp_unslash($_POST['podloom_enable_cache'])) == '1';
        $cache_duration = isset($_POST['podloom_cache_duration']) ? absint(wp_unslash($_POST['podloom_cache_duration'])) : 21600;

        update_option('podloom_api_key', $api_key);
        update_option('podloom_default_show', $default_show);
        update_option('podloom_enable_cache', $enable_cache);
        update_option('podloom_cache_duration', $cache_duration);

        // Clear cache when settings change
        podloom_clear_all_cache();

        // Test if API connection is working
        $success_message = esc_html__('Settings saved successfully!', 'podloom-podcast-player');
        if (!empty($api_key)) {
            $test_api = new Transistor_API($api_key);
            $test_result = $test_api->get_shows();
            if (!is_wp_error($test_result)) {
                $success_message .= ' ' . __('Successfully connected to Transistor API!', 'podloom-podcast-player');
            }
        }
    }

    // Get current settings (optimized - single query for all autoload options)
    $all_options = wp_load_alloptions();
    $api_key = $all_options['podloom_api_key'] ?? '';
    $default_show = $all_options['podloom_default_show'] ?? '';
    $enable_cache = $all_options['podloom_enable_cache'] ?? true;
    $cache_duration = $all_options['podloom_cache_duration'] ?? 21600;

    // Test connection and get shows if API key is set
    $shows = [];
    $connection_status = '';
    if (!empty($api_key)) {
        $api = new Transistor_API($api_key);
        $shows_result = $api->get_shows();

        if (is_wp_error($shows_result)) {
            $connection_status = '<div class="notice notice-warning"><p>' .
                                esc_html__('There is an error connecting to the Transistor API: ', 'podloom-podcast-player') .
                                esc_html($shows_result->get_error_message()) .
                                '</p></div>';
        } else {
            // Connection successful - only show shows, no success message
            $shows = isset($shows_result['data']) ? $shows_result['data'] : [];
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php if (!empty($success_message)): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($success_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html($error_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(wp_nonce_url('?page=podloom-settings&tab=welcome', 'podloom_switch_tab')); ?>" class="nav-tab <?php echo $current_tab === 'welcome' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Welcome', 'podloom-podcast-player'); ?>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url('?page=podloom-settings&tab=transistor', 'podloom_switch_tab')); ?>" class="nav-tab <?php echo $current_tab === 'transistor' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Transistor API', 'podloom-podcast-player'); ?>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url('?page=podloom-settings&tab=rss', 'podloom_switch_tab')); ?>" class="nav-tab <?php echo $current_tab === 'rss' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('RSS Feeds', 'podloom-podcast-player'); ?>
            </a>
            <a href="<?php echo esc_url(wp_nonce_url('?page=podloom-settings&tab=general', 'podloom_switch_tab')); ?>" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('General Settings', 'podloom-podcast-player'); ?>
            </a>
        </h2>

        <?php if ($current_tab === 'welcome'): ?>
            <!-- Welcome Tab -->
            <div class="welcome-container" style="margin-top: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;">
                    <!-- Left Column: Content -->
                    <div>
                        <h2><?php esc_html_e('Welcome to PodLoom Podcast Player!', 'podloom-podcast-player'); ?></h2>
                        <p><?php esc_html_e('Thank you for installing PodLoom! This plugin makes it easy to embed and manage podcast episodes from Transistor.fm and RSS feeds directly in your WordPress site.', 'podloom-podcast-player'); ?></p>

                        <hr>

                        <h3><span class="dashicons dashicons-admin-plugins" style="color: #2271b1;"></span> <?php esc_html_e('What PodLoom Can Do', 'podloom-podcast-player'); ?></h3>
                        <ul style="line-height: 1.8;">
                            <li><strong><?php esc_html_e('Embed Episodes:', 'podloom-podcast-player'); ?></strong> <?php esc_html_e('Add individual podcast episodes to any post or page using Gutenberg blocks', 'podloom-podcast-player'); ?></li>
                            <li><strong><?php esc_html_e('Display Playlists:', 'podloom-podcast-player'); ?></strong> <?php esc_html_e('Showcase multiple episodes or your entire show', 'podloom-podcast-player'); ?></li>
                            <li><strong><?php esc_html_e('Multiple Sources:', 'podloom-podcast-player'); ?></strong> <?php esc_html_e('Support for both Transistor.fm and standard RSS feeds', 'podloom-podcast-player'); ?></li>
                            <li><strong><?php esc_html_e('Customizable Players:', 'podloom-podcast-player'); ?></strong> <?php esc_html_e('Control which elements appear (artwork, title, date, duration, description)', 'podloom-podcast-player'); ?></li>
                            <li><strong><?php esc_html_e('Performance Optimized:', 'podloom-podcast-player'); ?></strong> <?php esc_html_e('Built-in caching to reduce API calls and improve load times', 'podloom-podcast-player'); ?></li>
                        </ul>

                        <hr>

                        <h3><span class="dashicons dashicons-admin-settings" style="color: #2271b1;"></span> <?php esc_html_e('Getting Started', 'podloom-podcast-player'); ?></h3>

                        <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('Transistor API', 'podloom-podcast-player'); ?></h4>
                            <p><?php esc_html_e('Connect to your Transistor.fm account to access your hosted podcasts. You\'ll need your API key from your Transistor dashboard. This gives you access to the official Transistor player with all its features.', 'podloom-podcast-player'); ?></p>
                            <p><a href="<?php echo esc_url(wp_nonce_url('?page=podloom-settings&tab=transistor', 'podloom_switch_tab')); ?>" class="button button-primary"><?php esc_html_e('Configure Transistor API', 'podloom-podcast-player'); ?></a></p>
                        </div>

                        <div style="background: #fef7f0; border-left: 4px solid #f8981d; padding: 15px; margin: 15px 0;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('RSS Feeds', 'podloom-podcast-player'); ?></h4>
                            <p><?php esc_html_e('Add podcasts from any RSS feed, whether hosted on Transistor, Libsyn, Buzzsprout, or any other platform. Perfect for featuring guest appearances or podcasts from other creators. Customize the player appearance with typography and styling options.', 'podloom-podcast-player'); ?></p>
                            <p><a href="<?php echo esc_url(wp_nonce_url('?page=podloom-settings&tab=rss', 'podloom_switch_tab')); ?>" class="button button-primary"><?php esc_html_e('Add RSS Feeds', 'podloom-podcast-player'); ?></a></p>
                        </div>

                        <div style="background: #f0f0f1; border-left: 4px solid #646970; padding: 15px; margin: 15px 0;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('General Settings', 'podloom-podcast-player'); ?></h4>
                            <p><?php esc_html_e('Configure your default show, manage caching settings, and control global plugin options. Fine-tune performance and set defaults that work across your entire site.', 'podloom-podcast-player'); ?></p>
                            <p><a href="<?php echo esc_url(wp_nonce_url('?page=podloom-settings&tab=general', 'podloom_switch_tab')); ?>" class="button"><?php esc_html_e('View General Settings', 'podloom-podcast-player'); ?></a></p>
                        </div>

                        <hr>

                        <div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-top: 20px;">
                            <h3 style="margin-top: 0;"><?php esc_html_e('Need Help?', 'podloom-podcast-player'); ?></h3>
                            <p><?php esc_html_e('Watch the video tutorial on the right to see PodLoom in action, or visit the plugin documentation for detailed guides and troubleshooting tips.', 'podloom-podcast-player'); ?></p>
                        </div>
                    </div>

                    <!-- Right Column: Video -->
                    <div>
                        <div style="position: sticky; top: 32px;">
                            <h3><?php esc_html_e('Video Walkthrough', 'podloom-podcast-player'); ?></h3>
                            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                <iframe
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"
                                    src="https://www.youtube.com/embed/2vkY-cVMnBg"
                                    title="<?php esc_attr_e('PodLoom Plugin Tutorial', 'podloom-podcast-player'); ?>"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen>
                                </iframe>
                            </div>
                            <p style="margin-top: 15px; color: #666; font-size: 14px;">
                                <?php esc_html_e('Learn how to set up and use PodLoom to embed podcast episodes on your WordPress site.', 'podloom-podcast-player'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($current_tab === 'general'): ?>
            <!-- General Settings Tab -->
            <form method="post" action="">
                <?php wp_nonce_field('podloom_settings_save', 'podloom_settings_nonce'); ?>
                <input type="hidden" name="podloom_api_key" value="<?php echo esc_attr($api_key); ?>" />

                <table class="form-table">
                    <?php if (!empty($shows)): ?>
                    <tr>
                        <th scope="row">
                            <label for="podloom_default_show">
                                <?php esc_html_e('Default Show', 'podloom-podcast-player'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="podloom_default_show" name="podloom_default_show" class="regular-text">
                                <option value="">
                                    <?php esc_html_e('-- Select a default show --', 'podloom-podcast-player'); ?>
                                </option>
                                <?php foreach ($shows as $show): ?>
                                    <option
                                        value="<?php echo esc_attr($show['id']); ?>"
                                        <?php selected($default_show, $show['id']); ?>
                                    >
                                        <?php echo esc_html($show['attributes']['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select the default show to use in the episode block. Users can override this when adding a block.', 'podloom-podcast-player'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <!-- Hidden field to preserve default_show when no shows available -->
                        <input type="hidden" name="podloom_default_show" value="<?php echo esc_attr($default_show); ?>" />
                    <?php endif; ?>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Enable Caching', 'podloom-podcast-player'); ?>
                        </th>
                        <td>
                            <label for="podloom_enable_cache">
                                <input
                                    type="checkbox"
                                    id="podloom_enable_cache"
                                    name="podloom_enable_cache"
                                    value="1"
                                    <?php checked($enable_cache, true); ?>
                                />
                                <?php esc_html_e('Cache API responses to reduce API calls', 'podloom-podcast-player'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Caching improves performance and reduces API usage. Recommended to keep enabled.', 'podloom-podcast-player'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="podloom_cache_duration">
                                <?php esc_html_e('Cache Duration', 'podloom-podcast-player'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="podloom_cache_duration" name="podloom_cache_duration" class="regular-text">
                                <option value="1800" <?php selected($cache_duration, 1800); ?>>
                                    <?php esc_html_e('30 minutes', 'podloom-podcast-player'); ?>
                                </option>
                                <option value="3600" <?php selected($cache_duration, 3600); ?>>
                                    <?php esc_html_e('1 hour', 'podloom-podcast-player'); ?>
                                </option>
                                <option value="7200" <?php selected($cache_duration, 7200); ?>>
                                    <?php esc_html_e('2 hours', 'podloom-podcast-player'); ?>
                                </option>
                                <option value="21600" <?php selected($cache_duration, 21600); ?>>
                                    <?php esc_html_e('6 hours (recommended)', 'podloom-podcast-player'); ?>
                                </option>
                                <option value="43200" <?php selected($cache_duration, 43200); ?>>
                                    <?php esc_html_e('12 hours', 'podloom-podcast-player'); ?>
                                </option>
                                <option value="86400" <?php selected($cache_duration, 86400); ?>>
                                    <?php esc_html_e('24 hours', 'podloom-podcast-player'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('How long to cache API responses before fetching fresh data.', 'podloom-podcast-player'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(esc_html__('Save Settings', 'podloom-podcast-player'), 'primary', 'podloom_settings_submit'); ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Cache Management', 'podloom-podcast-player'); ?></h2>
            <p><?php esc_html_e('Clear the cached API data to force fresh data from Transistor.', 'podloom-podcast-player'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('podloom_clear_cache', 'podloom_clear_cache_nonce'); ?>
                <?php submit_button(esc_html__('Clear Cache', 'podloom-podcast-player'), 'secondary', 'podloom_clear_cache'); ?>
            </form>

            <hr style="margin-top: 40px; border: none; border-top: 2px solid #dc3232;">

            <!-- Danger Zone Section -->
            <div class="danger-zone-container">
                <div class="danger-zone-header" id="danger-zone-toggle">
                    <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                    <strong style="color: #dc3232;"><?php esc_html_e('Danger Zone!', 'podloom-podcast-player'); ?></strong>
                    <span class="description" style="margin-left: 10px;">
                        <?php esc_html_e('Click to expand destructive actions', 'podloom-podcast-player'); ?>
                    </span>
                    <span class="dashicons dashicons-arrow-down-alt2 danger-zone-arrow" style="float: right;"></span>
                </div>

                <div class="danger-zone-content" id="danger-zone-content" style="display: none;">
                    <div class="danger-zone-warning">
                        <p><strong><?php esc_html_e('⚠️ WARNING: This action cannot be undone!', 'podloom-podcast-player'); ?></strong></p>
                        <p><?php esc_html_e('Resetting the plugin will permanently delete:', 'podloom-podcast-player'); ?></p>
                        <ul style="margin-left: 20px; list-style-type: disc;">
                            <li><?php esc_html_e('Your Transistor API key', 'podloom-podcast-player'); ?></li>
                            <li><?php esc_html_e('All RSS feeds and settings', 'podloom-podcast-player'); ?></li>
                            <li><?php esc_html_e('Default show setting', 'podloom-podcast-player'); ?></li>
                            <li><?php esc_html_e('Cache settings and all cached data', 'podloom-podcast-player'); ?></li>
                            <li><?php esc_html_e('All other PodLoom plugin settings', 'podloom-podcast-player'); ?></li>
                        </ul>
                        <p><?php esc_html_e('This will NOT affect your posts or existing episode blocks - they will simply need to be reconfigured after you re-enter your API key.', 'podloom-podcast-player'); ?></p>
                        <p><strong><?php esc_html_e('To confirm, type RESET in the field below and click the button.', 'podloom-podcast-player'); ?></strong></p>
                    </div>

                    <form method="post" action="" onsubmit="return confirmReset();">
                        <?php wp_nonce_field('podloom_reset_plugin', 'podloom_reset_plugin_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="reset_confirmation">
                                        <?php esc_html_e('Type RESET to confirm', 'podloom-podcast-player'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input
                                        type="text"
                                        id="reset_confirmation"
                                        name="reset_confirmation"
                                        value=""
                                        class="regular-text"
                                        placeholder="RESET"
                                        autocomplete="off"
                                    />
                                    <p class="description">
                                        <?php esc_html_e('This field is case-sensitive. You must type exactly: RESET', 'podloom-podcast-player'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(
                            esc_html__('Delete All Plugin Data', 'podloom-podcast-player'),
                            'delete',
                            'podloom_reset_plugin',
                            true,
                            array('style' => 'background-color: #a83232; border-color: #a83232; color: #fff;')
                        ); ?>
                    </form>
                </div>
            </div>

        <?php elseif ($current_tab === 'transistor'): ?>
            <!-- Transistor API Tab -->
            <?php echo wp_kses_post($connection_status); ?>

            <form method="post" action="">
                <?php wp_nonce_field('podloom_settings_save', 'podloom_settings_nonce'); ?>
                <input type="hidden" name="podloom_default_show" value="<?php echo esc_attr($default_show); ?>" />
                <input type="hidden" name="podloom_enable_cache" value="<?php echo $enable_cache ? '1' : '0'; ?>" />
                <input type="hidden" name="podloom_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="podloom_api_key">
                                <?php esc_html_e('API Key', 'podloom-podcast-player'); ?>
                            </label>
                        </th>
                        <td>
                            <div style="position: relative; display: inline-block;">
                                <input
                                    type="password"
                                    id="podloom_api_key"
                                    name="podloom_api_key"
                                    value="<?php echo esc_attr($api_key); ?>"
                                    class="regular-text"
                                    style="padding-right: 40px;"
                                />
                                <button
                                    type="button"
                                    id="toggle_api_key_visibility"
                                    class="button"
                                    style="position: absolute; right: 1px; top: 1px; height: calc(100% - 2px); padding: 0 8px; border-left: 1px solid #8c8f94;"
                                    aria-label="<?php esc_attr_e('Toggle API key visibility', 'podloom-podcast-player'); ?>"
                                >
                                    <span class="dashicons dashicons-visibility" style="line-height: 1.4;"></span>
                                </button>
                            </div>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: URL to Transistor dashboard */
                                    esc_html__('Enter your Transistor API key. You can find this in your %s.', 'podloom-podcast-player'),
                                    '<a href="https://dashboard.transistor.fm/account" target="_blank">' .
                                    esc_html__('Transistor Dashboard', 'podloom-podcast-player') .
                                    '</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(esc_html__('Save Settings', 'podloom-podcast-player'), 'primary', 'podloom_settings_submit'); ?>
            </form>

            <?php if (!empty($shows)): ?>
            <hr>
            <h2><?php esc_html_e('Your Shows', 'podloom-podcast-player'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Show Title', 'podloom-podcast-player'); ?></th>
                        <th><?php esc_html_e('Show ID', 'podloom-podcast-player'); ?></th>
                        <th><?php esc_html_e('Website', 'podloom-podcast-player'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shows as $show): ?>
                    <tr>
                        <td><strong><?php echo esc_html($show['attributes']['title']); ?></strong></td>
                        <td><?php echo esc_html($show['id']); ?></td>
                        <td>
                            <?php if (!empty($show['attributes']['website'])): ?>
                                <a href="<?php echo esc_url($show['attributes']['website']); ?>" target="_blank">
                                    <?php echo esc_html($show['attributes']['website']); ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

        <?php elseif ($current_tab === 'rss'): ?>
            <!-- RSS Feeds Tab -->
            <?php
            // Optimized - reuse already loaded options
            $rss_enabled = $all_options['podloom_rss_enabled'] ?? false;
            $rss_display_artwork = $all_options['podloom_rss_display_artwork'] ?? true;
            $rss_display_title = $all_options['podloom_rss_display_title'] ?? true;
            $rss_display_date = $all_options['podloom_rss_display_date'] ?? true;
            $rss_display_duration = $all_options['podloom_rss_display_duration'] ?? true;
            $rss_display_description = $all_options['podloom_rss_display_description'] ?? true;
            ?>

            <div id="rss-feeds-settings">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Enable RSS Feeds', 'podloom-podcast-player'); ?>
                        </th>
                        <td>
                            <label for="podloom_rss_enabled">
                                <input
                                    type="checkbox"
                                    id="podloom_rss_enabled"
                                    name="podloom_rss_enabled"
                                    value="1"
                                    <?php checked($rss_enabled, true); ?>
                                />
                                <span class="dashicons dashicons-rss" style="color: #f8981d;"></span>
                                <strong><?php esc_html_e('Enable RSS Feeds', 'podloom-podcast-player'); ?></strong>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Add podcast RSS feeds as an alternative or supplement to Transistor.fm.', 'podloom-podcast-player'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <div id="rss-feeds-container" style="<?php echo $rss_enabled ? '' : 'display: none;'; ?>">
                    <button type="button" id="add-new-rss-feed" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt" style="line-height: 1.4;"></span>
                        <?php esc_html_e('Add New RSS Feed', 'podloom-podcast-player'); ?>
                    </button>

                    <h3><?php esc_html_e('Your RSS Feeds', 'podloom-podcast-player'); ?></h3>
                    <div id="rss-feeds-list">
                        <?php
                        // Render feeds server-side for instant display
                        $feeds = Transistor_RSS::get_feeds();
                        if (empty($feeds)) {
                            echo '<p class="description">' . esc_html__('No RSS feeds added yet. Click "Add New RSS Feed" to get started.', 'podloom-podcast-player') . '</p>';
                        } else {
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead><tr>';
                            echo '<th>' . esc_html__('Feed Name', 'podloom-podcast-player') . '</th>';
                            echo '<th>' . esc_html__('Feed URL', 'podloom-podcast-player') . '</th>';
                            echo '<th>' . esc_html__('Status', 'podloom-podcast-player') . '</th>';
                            echo '<th>' . esc_html__('Last Checked', 'podloom-podcast-player') . '</th>';
                            echo '<th>' . esc_html__('Actions', 'podloom-podcast-player') . '</th>';
                            echo '</tr></thead><tbody>';

                            foreach ($feeds as $feed) {
                                $status_class = $feed['valid'] ? 'valid' : 'invalid';
                                $status_text = $feed['valid'] ? __('Valid', 'podloom-podcast-player') : __('Invalid', 'podloom-podcast-player');
                                $last_checked = isset($feed['last_checked']) && $feed['last_checked']
                                    ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $feed['last_checked'])
                                    : __('Never', 'podloom-podcast-player');

                                echo '<tr>';
                                echo '<td><strong>' . esc_html($feed['name']) . '</strong></td>';
                                echo '<td><a href="' . esc_url($feed['url']) . '" target="_blank" rel="noopener">' . esc_html($feed['url']) . '</a></td>';
                                echo '<td><span class="rss-feed-status ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
                                echo '<td>' . esc_html($last_checked) . '</td>';
                                echo '<td>';
                                echo '<button type="button" class="button button-small edit-feed" data-feed-id="' . esc_attr($feed['id']) . '">' . esc_html__('Edit', 'podloom-podcast-player') . '</button> ';
                                echo '<button type="button" class="button button-small refresh-feed" data-feed-id="' . esc_attr($feed['id']) . '">' . esc_html__('Refresh', 'podloom-podcast-player') . '</button> ';
                                echo '<button type="button" class="button button-small button-link-delete delete-feed" data-feed-id="' . esc_attr($feed['id']) . '">' . esc_html__('Delete', 'podloom-podcast-player') . '</button> ';
                                echo '<button type="button" class="button button-small view-feed-xml" data-feed-id="' . esc_attr($feed['id']) . '">' . esc_html__('View Feed', 'podloom-podcast-player') . '</button>';
                                echo '</td>';
                                echo '</tr>';
                            }

                            echo '</tbody></table>';
                        }
                        ?>
                    </div>

                    <hr>

                    <h3><?php esc_html_e('RSS Player Display Settings', 'podloom-podcast-player'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Control which elements appear in the RSS episode player by default. These settings can be overridden per-block in the editor.', 'podloom-podcast-player'); ?>
                        <br>
                        <?php esc_html_e('RSS episodes use the default WordPress audio player with customizable episode information display.', 'podloom-podcast-player'); ?>
                    </p>

                    <h4><?php esc_html_e('Player Elements', 'podloom-podcast-player'); ?></h4>
                    <table class="form-table">
                        <tr>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        id="podloom_rss_display_artwork"
                                        name="podloom_rss_display_artwork"
                                        value="1"
                                        <?php checked($rss_display_artwork, true); ?>
                                    />
                                    <?php esc_html_e('Show Episode Artwork', 'podloom-podcast-player'); ?>
                                </label>
                                <br><br>
                                <label>
                                    <input
                                        type="checkbox"
                                        id="podloom_rss_display_title"
                                        name="podloom_rss_display_title"
                                        value="1"
                                        <?php checked($rss_display_title, true); ?>
                                    />
                                    <?php esc_html_e('Show Episode Title', 'podloom-podcast-player'); ?>
                                </label>
                                <br><br>
                                <label>
                                    <input
                                        type="checkbox"
                                        id="podloom_rss_display_date"
                                        name="podloom_rss_display_date"
                                        value="1"
                                        <?php checked($rss_display_date, true); ?>
                                    />
                                    <?php esc_html_e('Show Publication Date', 'podloom-podcast-player'); ?>
                                </label>
                                <br><br>
                                <label>
                                    <input
                                        type="checkbox"
                                        id="podloom_rss_display_duration"
                                        name="podloom_rss_display_duration"
                                        value="1"
                                        <?php checked($rss_display_duration, true); ?>
                                    />
                                    <?php esc_html_e('Show Episode Duration', 'podloom-podcast-player'); ?>
                                </label>
                                <br><br>
                                <label>
                                    <input
                                        type="checkbox"
                                        id="podloom_rss_display_description"
                                        name="podloom_rss_display_description"
                                        value="1"
                                        <?php checked($rss_display_description, true); ?>
                                    />
                                    <?php esc_html_e('Show Episode Description', 'podloom-podcast-player'); ?>
                                </label>
                                <br><br>
                                <p class="description">
                                    <?php esc_html_e('Note: Audio player is always shown.', 'podloom-podcast-player'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="podloom_rss_description_limit">
                                    <?php esc_html_e('Description Character Limit', 'podloom-podcast-player'); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="number"
                                    id="podloom_rss_description_limit"
                                    name="podloom_rss_description_limit"
                                    value="<?php echo esc_attr($all_options['podloom_rss_description_limit'] ?? 0); ?>"
                                    min="0"
                                    step="1"
                                    class="small-text"
                                />
                                <p class="description">
                                    <?php esc_html_e('Maximum number of characters to display in episode descriptions. Set to 0 for unlimited (show full description).', 'podloom-podcast-player'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Styling Mode', 'podloom-podcast-player'); ?></th>
                            <td>
                                <label>
                                    <input
                                        type="checkbox"
                                        id="podloom_rss_minimal_styling"
                                        name="podloom_rss_minimal_styling"
                                        value="1"
                                        <?php checked($all_options['podloom_rss_minimal_styling'] ?? false, true); ?>
                                    />
                                    <?php esc_html_e('Enable Minimal Styling Mode', 'podloom-podcast-player'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, the plugin will output only semantic HTML with classes, allowing you to apply your own custom CSS. All plugin typography and styling settings will be disabled.', 'podloom-podcast-player'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <h3><?php esc_html_e('Typography Settings', 'podloom-podcast-player'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Customize the appearance of RSS episode text elements.', 'podloom-podcast-player'); ?>
                    </p>

                    <?php
                    // Get current typography settings (optimized - reuse loaded options)
                    $elements = ['title', 'date', 'duration', 'description'];

                    // Default values matching PHP backend defaults
                    $defaults = [
                        'title' => ['font_size' => '24px', 'line_height' => '1.3', 'color' => '#000000', 'font_weight' => '600'],
                        'date' => ['font_size' => '14px', 'line_height' => '1.5', 'color' => '#666666', 'font_weight' => 'normal'],
                        'duration' => ['font_size' => '14px', 'line_height' => '1.5', 'color' => '#666666', 'font_weight' => 'normal'],
                        'description' => ['font_size' => '16px', 'line_height' => '1.6', 'color' => '#333333', 'font_weight' => 'normal']
                    ];

                    $typo = [];
                    foreach ($elements as $element) {
                        $typo[$element] = [
                            'font_family' => $all_options["podloom_rss_{$element}_font_family"] ?? 'inherit',
                            'font_size' => $all_options["podloom_rss_{$element}_font_size"] ?? $defaults[$element]['font_size'],
                            'line_height' => $all_options["podloom_rss_{$element}_line_height"] ?? $defaults[$element]['line_height'],
                            'color' => $all_options["podloom_rss_{$element}_color"] ?? $defaults[$element]['color'],
                            'font_weight' => $all_options["podloom_rss_{$element}_font_weight"] ?? $defaults[$element]['font_weight']
                        ];
                    }
                    ?>

                    <div class="typography-settings-container">
                        <div class="typography-controls">
                            <!-- Background Color Section -->
                            <div class="typography-element-section">
                                <h4><?php esc_html_e('Block Background Color', 'podloom-podcast-player'); ?></h4>
                                <table class="form-table">
                                    <tr>
                                        <th><label><?php esc_html_e('Background', 'podloom-podcast-player'); ?></label></th>
                                        <td>
                                            <input type="color" id="rss_background_color" value="<?php echo esc_attr($all_options['podloom_rss_background_color'] ?? '#f9f9f9'); ?>" class="typo-control color-picker" data-element="background" data-property="background-color">
                                            <p class="description"><?php esc_html_e('Choose a background color for the entire RSS episode block.', 'podloom-podcast-player'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <?php foreach ($elements as $element):
                                $label = ucfirst($element);
                            ?>
                            <div class="typography-element-section" id="<?php echo esc_attr($element); ?>_typography_section" data-element="<?php echo esc_attr($element); ?>">
                                <h4><?php echo esc_html($label); ?> <?php esc_html_e('Typography', 'podloom-podcast-player'); ?></h4>

                                <table class="form-table">
                                    <tr>
                                        <th><label><?php esc_html_e('Font Family', 'podloom-podcast-player'); ?></label></th>
                                        <td>
                                            <select id="<?php echo esc_attr($element); ?>_font_family" class="regular-text typo-control" data-element="<?php echo esc_attr($element); ?>" data-property="font-family">
                                                <option value="inherit" <?php selected($typo[$element]['font_family'], 'inherit'); ?>>Inherit</option>
                                                <option value="Arial, sans-serif" <?php selected($typo[$element]['font_family'], 'Arial, sans-serif'); ?>>Arial</option>
                                                <option value="Helvetica, sans-serif" <?php selected($typo[$element]['font_family'], 'Helvetica, sans-serif'); ?>>Helvetica</option>
                                                <option value="'Times New Roman', serif" <?php selected($typo[$element]['font_family'], "'Times New Roman', serif"); ?>>Times New Roman</option>
                                                <option value="Georgia, serif" <?php selected($typo[$element]['font_family'], 'Georgia, serif'); ?>>Georgia</option>
                                                <option value="'Courier New', monospace" <?php selected($typo[$element]['font_family'], "'Courier New', monospace"); ?>>Courier New</option>
                                                <option value="Verdana, sans-serif" <?php selected($typo[$element]['font_family'], 'Verdana, sans-serif'); ?>>Verdana</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php esc_html_e('Font Size', 'podloom-podcast-player'); ?></label></th>
                                        <td>
                                            <input type="hidden" id="<?php echo esc_attr($element); ?>_font_size" value="<?php echo esc_attr($typo[$element]['font_size']); ?>">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <input type="range" id="<?php echo esc_attr($element); ?>_font_size_range" min="8" max="72" step="1" value="16" class="typo-range" data-element="<?php echo esc_attr($element); ?>" style="flex: 1;">
                                                <input type="number" id="<?php echo esc_attr($element); ?>_font_size_value" min="0" max="200" step="0.1" value="16" class="small-text typo-control typo-size-value" data-element="<?php echo esc_attr($element); ?>" data-property="font-size" style="width: 70px;">
                                                <select id="<?php echo esc_attr($element); ?>_font_size_unit" class="typo-control typo-size-unit" data-element="<?php echo esc_attr($element); ?>" data-property="font-size" style="width: 70px;">
                                                    <option value="px">px</option>
                                                    <option value="em">em</option>
                                                    <option value="rem">rem</option>
                                                    <option value="%">%</option>
                                                </select>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php esc_html_e('Line Height', 'podloom-podcast-player'); ?></label></th>
                                        <td>
                                            <input type="hidden" id="<?php echo esc_attr($element); ?>_line_height" value="<?php echo esc_attr($typo[$element]['line_height']); ?>">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <input type="range" id="<?php echo esc_attr($element); ?>_line_height_range" min="0.5" max="3" step="0.1" value="1.5" class="typo-range" data-element="<?php echo esc_attr($element); ?>" style="flex: 1;">
                                                <input type="number" id="<?php echo esc_attr($element); ?>_line_height_value" min="0" max="10" step="0.1" value="1.5" class="small-text typo-control typo-lineheight-value" data-element="<?php echo esc_attr($element); ?>" data-property="line-height" style="width: 70px;">
                                                <span style="width: 70px; text-align: center; color: #666;">(unitless)</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php esc_html_e('Text Color', 'podloom-podcast-player'); ?></label></th>
                                        <td>
                                            <input type="color" id="<?php echo esc_attr($element); ?>_color" value="<?php echo esc_attr($typo[$element]['color']); ?>" class="typo-control color-picker" data-element="<?php echo esc_attr($element); ?>" data-property="color">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><label><?php esc_html_e('Font Weight', 'podloom-podcast-player'); ?></label></th>
                                        <td>
                                            <select id="<?php echo esc_attr($element); ?>_font_weight" class="regular-text typo-control" data-element="<?php echo esc_attr($element); ?>" data-property="font-weight">
                                                <option value="normal" <?php selected($typo[$element]['font_weight'], 'normal'); ?>>Normal</option>
                                                <option value="bold" <?php selected($typo[$element]['font_weight'], 'bold'); ?>>Bold</option>
                                                <option value="600" <?php selected($typo[$element]['font_weight'], '600'); ?>>Semi-Bold (600)</option>
                                                <option value="300" <?php selected($typo[$element]['font_weight'], '300'); ?>>Light (300)</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="typography-preview">
                            <h4><?php esc_html_e('Live Preview', 'podloom-podcast-player'); ?></h4>
                            <div id="rss-episode-preview" class="rss-episode-player" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                                <div class="rss-episode-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
                                    <div id="preview-artwork" class="rss-episode-artwork" style="flex-shrink: 0; width: 200px;">
                                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Crect fill='%23f0f0f0' width='300' height='300'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='Arial' font-size='18' fill='%23666'%3EPodcast Artwork%3C/text%3E%3C/svg%3E" alt="<?php esc_attr_e('Podcast Artwork', 'podloom-podcast-player'); ?>" style="width: 100%; height: auto; border-radius: 4px; display: block;">
                                    </div>
                                    <div class="rss-episode-content" style="flex: 1; min-width: 0;">
                                        <h3 id="preview-title" class="rss-episode-title" style="margin: 0 0 10px 0;">Sample Episode Title</h3>
                                        <div id="preview-meta" class="rss-episode-meta" style="display: flex; gap: 15px; margin-bottom: 15px;">
                                            <span id="preview-date" class="rss-episode-date">January 1, 2024</span>
                                            <span id="preview-duration" class="rss-episode-duration">45:30</span>
                                        </div>
                                        <audio class="rss-episode-audio" controls style="width: 100%; margin-bottom: 15px;">
                                            <source src="data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=" type="audio/wav">
                                        </audio>
                                        <div id="preview-description" class="rss-episode-description">
                                            <p>This is a sample episode description. It gives listeners an overview of what the episode is about and what they can expect to learn.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" id="save-rss-settings" class="button button-primary" style="margin-top: 20px;">
                    <?php esc_html_e('Save RSS Settings', 'podloom-podcast-player'); ?>
                </button>
            </div>

        <?php endif; // End tab conditional ?>
    </div>

    <style>
        .wrap {
            max-width: 1200px;
        }
        .form-table th {
            width: 200px;
        }
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }

        /* Danger Zone Styles */
        .danger-zone-container {
            border: 2px solid #dc3232;
            border-radius: 4px;
            margin-top: 20px;
            background: #fff;
        }
        .danger-zone-header {
            padding: 15px 20px;
            cursor: pointer;
            background: #fff;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .danger-zone-header:hover {
            background: #fef7f7;
        }
        .danger-zone-arrow {
            transition: transform 0.3s;
            color: #dc3232;
        }
        .danger-zone-arrow.rotated {
            transform: rotate(180deg);
        }
        .danger-zone-content {
            padding: 0 20px 20px 20px;
            border-top: 1px solid #dc3232;
            margin-top: 0;
        }
        .danger-zone-warning {
            background: #fef7f7;
            border-left: 4px solid #dc3232;
            padding: 15px;
            margin: 20px 0;
        }
        .danger-zone-warning p:first-child {
            margin-top: 0;
        }
        .danger-zone-warning p:last-child {
            margin-bottom: 0;
        }

        /* RSS Feeds Styles */
        #rss-feeds-list {
            margin-top: 20px;
        }
        .rss-feed-item {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }
        .rss-feed-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .rss-feed-header .dashicons-rss {
            font-size: 24px;
            color: #f8981d;
        }
        .rss-feed-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            flex-grow: 1;
        }
        .rss-feed-title-editable {
            font-size: 16px;
            padding: 4px 8px;
            border: 1px solid #8c8f94;
            border-radius: 3px;
            min-width: 200px;
        }
        .rss-feed-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .rss-feed-status.valid {
            background: #00a32a;
            color: #fff;
        }
        .rss-feed-status.invalid {
            background: #dc3232;
            color: #fff;
        }
        .rss-feed-url {
            color: #2271b1;
            margin: 5px 0;
            word-break: break-all;
        }
        .rss-feed-meta {
            color: #646970;
            font-size: 13px;
            margin: 5px 0;
        }
        .rss-feed-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .rss-feed-actions .button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .rss-feed-actions .dashicons {
            font-size: 16px;
            line-height: 1.4;
        }

        /* Modal Styles */
        #rss-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100000;
            display: none;
        }
        #rss-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 100001;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            display: none;
            flex-direction: column;
        }
        #rss-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #rss-modal-header h2 {
            margin: 0;
        }
        #rss-modal-close {
            cursor: pointer;
            font-size: 24px;
            color: #666;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        #rss-modal-close:hover {
            color: #000;
        }
        #rss-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex-grow: 1;
        }
        #rss-modal-body label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        #rss-modal-body input[type="text"],
        #rss-modal-body input[type="url"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #8c8f94;
            border-radius: 3px;
        }
        #rss-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        #rss-modal-footer .button {
            margin-left: 10px;
        }

        /* XML Viewer Styles */
        #xml-viewer-modal {
            max-width: 900px;
            width: 90%;
        }
        #xml-viewer-content {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 60vh;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Notice Styles */
        .rss-notice {
            padding: 12px;
            margin: 15px 0;
            border-left: 4px solid;
            border-radius: 3px;
        }
        .rss-notice.success {
            background: #f0f6fc;
            border-color: #00a32a;
            color: #003d0d;
        }
        .rss-notice.error {
            background: #fef7f7;
            border-color: #dc3232;
            color: #3c0008;
        }
        .rss-notice.info {
            background: #f0f6fc;
            border-color: #2271b1;
            color: #003d5c;
        }

        /* Typography Settings Styles */
        .typography-settings-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 20px;
        }
        .typography-controls {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .typography-preview {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            position: sticky;
            top: 32px;
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }
        .typography-element-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .typography-element-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .typography-element-section h4 {
            margin: 0 0 15px 0;
            color: #2271b1;
        }
        .typography-element-section .form-table {
            margin-top: 0;
        }
        .typography-element-section .form-table th {
            width: 150px;
        }
        .color-picker {
            width: 80px;
            height: 40px;
            border: 1px solid #8c8f94;
            border-radius: 3px;
            cursor: pointer;
        }
        @media screen and (max-width: 1280px) {
            .typography-settings-container {
                grid-template-columns: 1fr;
            }
            .typography-preview {
                position: relative;
                top: auto;
                max-height: none;
            }
        }
        @media screen and (max-width: 768px) {
            .rss-episode-wrapper {
                gap: 15px !important;
            }
            .rss-episode-artwork {
                width: 100px !important;
            }
        }
    </style>

    <?php
}
