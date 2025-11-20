<?php
/**
 * Transistor API Tab
 *
 * Displays Transistor API key configuration, connection status,
 * and list of available shows
 *
 * @package PodLoom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Transistor API tab
 */
function podloom_render_transistor_tab($api_key, $default_show, $enable_cache, $cache_duration, $connection_status, $shows) {
    ?>
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
    <?php
}
