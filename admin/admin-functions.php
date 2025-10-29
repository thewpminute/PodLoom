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

    // Handle clear cache request
    if (isset($_POST['transistor_clear_cache'])) {
        check_admin_referer('transistor_clear_cache', 'transistor_clear_cache_nonce');
        transistor_clear_all_cache();
        echo '<div class="notice notice-success is-dismissible"><p>' .
             esc_html__('Cache cleared successfully!', 'podloom') .
             '</p></div>';
    }

    // Handle form submission
    if (isset($_POST['transistor_settings_submit'])) {
        check_admin_referer('transistor_settings_save', 'transistor_settings_nonce');

        $api_key = sanitize_text_field($_POST['transistor_api_key']);
        $default_show = sanitize_text_field($_POST['transistor_default_show']);
        $enable_cache = isset($_POST['transistor_enable_cache']) ? true : false;
        $cache_duration = absint($_POST['transistor_cache_duration']);

        update_option('transistor_api_key', $api_key);
        update_option('transistor_default_show', $default_show);
        update_option('transistor_enable_cache', $enable_cache);
        update_option('transistor_cache_duration', $cache_duration);

        // Clear cache when settings change
        transistor_clear_all_cache();

        echo '<div class="notice notice-success is-dismissible"><p>' .
             esc_html__('Settings saved successfully!', 'podloom') .
             '</p></div>';
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
            $connection_status = '<div class="notice notice-error"><p>' .
                                esc_html__('Error connecting to Transistor API: ', 'podloom') .
                                esc_html($shows_result->get_error_message()) .
                                '</p></div>';
        } else {
            $connection_status = '<div class="notice notice-success"><p>' .
                                esc_html__('Successfully connected to Transistor API!', 'podloom') .
                                '</p></div>';
            $shows = isset($shows_result['data']) ? $shows_result['data'] : [];
        }
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php echo $connection_status; ?>

        <form method="post" action="">
            <?php wp_nonce_field('transistor_settings_save', 'transistor_settings_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="transistor_api_key">
                            <?php esc_html_e('API Key', 'podloom'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="transistor_api_key"
                            name="transistor_api_key"
                            value="<?php echo esc_attr($api_key); ?>"
                            class="regular-text"
                        />
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
    </div>

    <style>
        .wrap {
            max-width: 1200px;
        }
        .form-table th {
            width: 200px;
        }
    </style>
    <?php
}
