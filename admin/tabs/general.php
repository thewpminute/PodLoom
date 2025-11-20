<?php
/**
 * General Settings Tab
 *
 * Displays default show selection, cache settings, cache management,
 * and plugin reset functionality
 *
 * @package PodLoom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the General Settings tab
 */
function podloom_render_general_tab($all_options, $shows) {
    $api_key = $all_options['podloom_api_key'] ?? '';
    $default_show = $all_options['podloom_default_show'] ?? '';
    $enable_cache = $all_options['podloom_enable_cache'] ?? true;
    $cache_duration = $all_options['podloom_cache_duration'] ?? 21600;
    ?>
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
    <?php
}
