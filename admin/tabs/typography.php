<?php
/**
 * Typography Settings Tab
 *
 * Displays advanced typography controls for RSS episode players,
 * including font family, size, line height, color, and weight settings
 * with live preview
 *
 * @package PodLoom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render typography settings section for RSS tab
 */
function podloom_render_typography_settings($all_options) {
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

    <div class="postbox closed">
        <button type="button" class="handlediv" aria-expanded="false">
            <span class="screen-reader-text"><?php esc_html_e('Toggle panel: Advanced Typography Settings', 'podloom-podcast-player'); ?></span>
            <span class="toggle-indicator" aria-hidden="true"></span>
        </button>
        <h2 class="hndle">
            <span><?php esc_html_e('Advanced Typography Settings', 'podloom-podcast-player'); ?></span>
        </h2>
        <div class="inside">
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
        </div><!-- .inside -->
    </div><!-- .postbox -->
    </div><!-- #typography-section-wrapper -->
    <?php
}
