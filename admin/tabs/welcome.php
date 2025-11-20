<?php
/**
 * Welcome Tab
 *
 * Displays welcome message, getting started guide, and video tutorial
 *
 * @package PodLoom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Welcome tab
 */
function podloom_render_welcome_tab() {
    ?>
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
    <?php
}
