<?php
/**
 * Admin Functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the settings page
 */
function transistor_render_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Get current tab
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

    // Success/error messages
    $success_message = '';
    $error_message = '';

    // Handle clear cache request
    if (isset($_POST['transistor_clear_cache'])) {
        check_admin_referer('transistor_clear_cache', 'transistor_clear_cache_nonce');
        transistor_clear_all_cache();
        $success_message = __('Cache cleared successfully!', 'podloom');
    }

    // Handle plugin reset request
    if (isset($_POST['transistor_reset_plugin'])) {
        check_admin_referer('transistor_reset_plugin', 'transistor_reset_plugin_nonce');

        $reset_confirmation = isset($_POST['reset_confirmation']) ? sanitize_text_field($_POST['reset_confirmation']) : '';

        if ($reset_confirmation === 'RESET') {
            // Delete all plugin options and cache
            transistor_delete_all_plugin_data();
            $success_message = __('All PodLoom settings and cache have been deleted successfully. The plugin has been reset to default state.', 'podloom');

            // Refresh variables since we just deleted everything
            $api_key = '';
            $default_show = '';
            $enable_cache = true;
            $cache_duration = 21600;
            $shows = [];
            $connection_status = '';
        } else {
            $error_message = __('Reset failed: You must type RESET in the confirmation field.', 'podloom');
        }
    }

    // Handle form submission
    if (isset($_POST['transistor_settings_submit'])) {
        check_admin_referer('transistor_settings_save', 'transistor_settings_nonce');

        $api_key = isset($_POST['transistor_api_key']) ? sanitize_text_field($_POST['transistor_api_key']) : '';
        $default_show = isset($_POST['transistor_default_show']) ? sanitize_text_field($_POST['transistor_default_show']) : '';

        // Handle checkbox - when checked it's '1', when unchecked the field is not submitted
        $enable_cache = isset($_POST['transistor_enable_cache']) && $_POST['transistor_enable_cache'] == '1';
        $cache_duration = isset($_POST['transistor_cache_duration']) ? absint($_POST['transistor_cache_duration']) : 21600;

        update_option('transistor_api_key', $api_key);
        update_option('transistor_default_show', $default_show);
        update_option('transistor_enable_cache', $enable_cache);
        update_option('transistor_cache_duration', $cache_duration);

        // Clear cache when settings change
        transistor_clear_all_cache();

        // Test if API connection is working
        $success_message = __('Settings saved successfully!', 'podloom');
        if (!empty($api_key)) {
            $test_api = new Transistor_API($api_key);
            $test_result = $test_api->get_shows();
            if (!is_wp_error($test_result)) {
                $success_message .= ' ' . __('Successfully connected to Transistor API!', 'podloom');
            }
        }
    }

    // Get current settings
    $api_key = get_option('transistor_api_key', '');
    $default_show = get_option('transistor_default_show', '');
    $enable_cache = get_option('transistor_enable_cache', true);
    $cache_duration = get_option('transistor_cache_duration', 21600);

    // Test connection and get shows if API key is set
    $shows = [];
    $connection_status = '';
    if (!empty($api_key)) {
        $api = new Transistor_API($api_key);
        $shows_result = $api->get_shows();

        if (is_wp_error($shows_result)) {
            $connection_status = '<div class="notice notice-warning"><p>' .
                                esc_html__('There is an error connecting to the Transistor API: ', 'podloom') .
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
            <a href="?page=transistor-api-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('General Settings', 'podloom'); ?>
            </a>
            <a href="?page=transistor-api-settings&tab=transistor" class="nav-tab <?php echo $current_tab === 'transistor' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Transistor API', 'podloom'); ?>
            </a>
        </h2>

        <?php if ($current_tab === 'general'): ?>
            <!-- General Settings Tab -->
            <form method="post" action="">
                <?php wp_nonce_field('transistor_settings_save', 'transistor_settings_nonce'); ?>
                <input type="hidden" name="transistor_api_key" value="<?php echo esc_attr($api_key); ?>" />

                <table class="form-table">
                    <?php if (!empty($shows)): ?>
                    <tr>
                        <th scope="row">
                            <label for="transistor_default_show">
                                <?php esc_html_e('Default Show', 'podloom'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="transistor_default_show" name="transistor_default_show" class="regular-text">
                                <option value="">
                                    <?php esc_html_e('-- Select a default show --', 'podloom'); ?>
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
                                <?php esc_html_e('Select the default show to use in the episode block. Users can override this when adding a block.', 'podloom'); ?>
                            </p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <!-- Hidden field to preserve default_show when no shows available -->
                        <input type="hidden" name="transistor_default_show" value="<?php echo esc_attr($default_show); ?>" />
                    <?php endif; ?>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Enable Caching', 'podloom'); ?>
                        </th>
                        <td>
                            <label for="transistor_enable_cache">
                                <input
                                    type="checkbox"
                                    id="transistor_enable_cache"
                                    name="transistor_enable_cache"
                                    value="1"
                                    <?php checked($enable_cache, true); ?>
                                />
                                <?php esc_html_e('Cache API responses to reduce API calls', 'podloom'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Caching improves performance and reduces API usage. Recommended to keep enabled.', 'podloom'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="transistor_cache_duration">
                                <?php esc_html_e('Cache Duration', 'podloom'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="transistor_cache_duration" name="transistor_cache_duration" class="regular-text">
                                <option value="1800" <?php selected($cache_duration, 1800); ?>>
                                    <?php esc_html_e('30 minutes', 'podloom'); ?>
                                </option>
                                <option value="3600" <?php selected($cache_duration, 3600); ?>>
                                    <?php esc_html_e('1 hour', 'podloom'); ?>
                                </option>
                                <option value="7200" <?php selected($cache_duration, 7200); ?>>
                                    <?php esc_html_e('2 hours', 'podloom'); ?>
                                </option>
                                <option value="21600" <?php selected($cache_duration, 21600); ?>>
                                    <?php esc_html_e('6 hours (recommended)', 'podloom'); ?>
                                </option>
                                <option value="43200" <?php selected($cache_duration, 43200); ?>>
                                    <?php esc_html_e('12 hours', 'podloom'); ?>
                                </option>
                                <option value="86400" <?php selected($cache_duration, 86400); ?>>
                                    <?php esc_html_e('24 hours', 'podloom'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('How long to cache API responses before fetching fresh data.', 'podloom'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'podloom'), 'primary', 'transistor_settings_submit'); ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Cache Management', 'podloom'); ?></h2>
            <p><?php esc_html_e('Clear the cached API data to force fresh data from Transistor.', 'podloom'); ?></p>
            <form method="post" action="">
                <?php wp_nonce_field('transistor_clear_cache', 'transistor_clear_cache_nonce'); ?>
                <?php submit_button(__('Clear Cache', 'podloom'), 'secondary', 'transistor_clear_cache'); ?>
            </form>

            <hr style="margin-top: 40px; border: none; border-top: 2px solid #dc3232;">

            <!-- Danger Zone Section -->
            <div class="danger-zone-container">
                <div class="danger-zone-header" id="danger-zone-toggle">
                    <span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
                    <strong style="color: #dc3232;"><?php esc_html_e('Danger Zone!', 'podloom'); ?></strong>
                    <span class="description" style="margin-left: 10px;">
                        <?php esc_html_e('Click to expand destructive actions', 'podloom'); ?>
                    </span>
                    <span class="dashicons dashicons-arrow-down-alt2 danger-zone-arrow" style="float: right;"></span>
                </div>

                <div class="danger-zone-content" id="danger-zone-content" style="display: none;">
                    <div class="danger-zone-warning">
                        <p><strong><?php esc_html_e('⚠️ WARNING: This action cannot be undone!', 'podloom'); ?></strong></p>
                        <p><?php esc_html_e('Resetting the plugin will permanently delete:', 'podloom'); ?></p>
                        <ul style="margin-left: 20px; list-style-type: disc;">
                            <li><?php esc_html_e('Your Transistor API key', 'podloom'); ?></li>
                            <li><?php esc_html_e('Default show setting', 'podloom'); ?></li>
                            <li><?php esc_html_e('Cache settings and all cached data', 'podloom'); ?></li>
                            <li><?php esc_html_e('All other PodLoom plugin settings', 'podloom'); ?></li>
                        </ul>
                        <p><?php esc_html_e('This will NOT affect your posts or existing episode blocks - they will simply need to be reconfigured after you re-enter your API key.', 'podloom'); ?></p>
                        <p><strong><?php esc_html_e('To confirm, type RESET in the field below and click the button.', 'podloom'); ?></strong></p>
                    </div>

                    <form method="post" action="" onsubmit="return confirmReset();">
                        <?php wp_nonce_field('transistor_reset_plugin', 'transistor_reset_plugin_nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="reset_confirmation">
                                        <?php esc_html_e('Type RESET to confirm', 'podloom'); ?>
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
                                        <?php esc_html_e('This field is case-sensitive. You must type exactly: RESET', 'podloom'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(
                            __('Delete All Plugin Data', 'podloom'),
                            'delete',
                            'transistor_reset_plugin',
                            true,
                            array('style' => 'background-color: #a83232; border-color: #a83232; color: #fff;')
                        ); ?>
                    </form>
                </div>
            </div>

        <?php elseif ($current_tab === 'transistor'): ?>
            <!-- Transistor API Tab -->
            <?php echo $connection_status; ?>

            <form method="post" action="">
                <?php wp_nonce_field('transistor_settings_save', 'transistor_settings_nonce'); ?>
                <input type="hidden" name="transistor_default_show" value="<?php echo esc_attr($default_show); ?>" />
                <input type="hidden" name="transistor_enable_cache" value="<?php echo $enable_cache ? '1' : '0'; ?>" />
                <input type="hidden" name="transistor_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" />

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="transistor_api_key">
                                <?php esc_html_e('API Key', 'podloom'); ?>
                            </label>
                        </th>
                        <td>
                            <div style="position: relative; display: inline-block;">
                                <input
                                    type="password"
                                    id="transistor_api_key"
                                    name="transistor_api_key"
                                    value="<?php echo esc_attr($api_key); ?>"
                                    class="regular-text"
                                    style="padding-right: 40px;"
                                />
                                <button
                                    type="button"
                                    id="toggle_api_key_visibility"
                                    class="button"
                                    style="position: absolute; right: 1px; top: 1px; height: calc(100% - 2px); padding: 0 8px; border-left: 1px solid #8c8f94;"
                                    aria-label="<?php esc_attr_e('Toggle API key visibility', 'podloom'); ?>"
                                >
                                    <span class="dashicons dashicons-visibility" style="line-height: 1.4;"></span>
                                </button>
                            </div>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: URL to Transistor dashboard */
                                    esc_html__('Enter your Transistor API key. You can find this in your %s.', 'podloom'),
                                    '<a href="https://dashboard.transistor.fm/account" target="_blank">' .
                                    esc_html__('Transistor Dashboard', 'podloom') .
                                    '</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Settings', 'podloom'), 'primary', 'transistor_settings_submit'); ?>
            </form>

            <?php if (!empty($shows)): ?>
            <hr>
            <h2><?php esc_html_e('Your Shows', 'podloom'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Show Title', 'podloom'); ?></th>
                        <th><?php esc_html_e('Show ID', 'podloom'); ?></th>
                        <th><?php esc_html_e('Website', 'podloom'); ?></th>
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
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // API Key Visibility Toggle
            const toggleButton = document.getElementById('toggle_api_key_visibility');
            if (toggleButton) {
                const apiKeyInput = document.getElementById('transistor_api_key');
                const icon = toggleButton.querySelector('.dashicons');

                toggleButton.addEventListener('click', function() {
                    if (apiKeyInput.type === 'password') {
                        apiKeyInput.type = 'text';
                        icon.classList.remove('dashicons-visibility');
                        icon.classList.add('dashicons-hidden');
                    } else {
                        apiKeyInput.type = 'password';
                        icon.classList.remove('dashicons-hidden');
                        icon.classList.add('dashicons-visibility');
                    }
                });
            }

            // Danger Zone Toggle
            const dangerZoneToggle = document.getElementById('danger-zone-toggle');
            if (dangerZoneToggle) {
                const dangerZoneContent = document.getElementById('danger-zone-content');
                const arrow = dangerZoneToggle.querySelector('.danger-zone-arrow');

                dangerZoneToggle.addEventListener('click', function() {
                    if (dangerZoneContent.style.display === 'none') {
                        dangerZoneContent.style.display = 'block';
                        arrow.classList.add('rotated');
                    } else {
                        dangerZoneContent.style.display = 'none';
                        arrow.classList.remove('rotated');
                    }
                });
            }
        });

        // Confirm Reset Function
        function confirmReset() {
            const confirmationInput = document.getElementById('reset_confirmation');
            const confirmationValue = confirmationInput ? confirmationInput.value : '';

            if (confirmationValue !== 'RESET') {
                alert('You must type RESET (in uppercase) to confirm this action.');
                return false;
            }

            return confirm(
                'Are you absolutely sure you want to delete all PodLoom settings and cache?\n\n' +
                'This action cannot be undone!\n\n' +
                'Click OK to proceed with deletion, or Cancel to abort.'
            );
        }
    </script>
    <?php
}
