<?php
/**
 * RSS Feeds Tab
 *
 * Displays RSS feed management, player display settings,
 * Podcasting 2.0 element toggles, color palettes, and typography settings
 *
 * @package PodLoom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the RSS Feeds tab
 */
function podloom_render_rss_tab($all_options) {
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
                $feeds = Podloom_RSS::get_feeds();
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
                <?php esc_html_e('Control which elements appear in the RSS episode player by default. These settings can be overridden per-block in the editor. RSS episodes use the default WordPress audio player with customizable episode information display.', 'podloom-podcast-player'); ?>
            </p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Player Elements', 'podloom-podcast-player'); ?>
                    </th>
                    <td>
                        <fieldset>
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
                            <br>
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
                            <br>
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
                            <br>
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
                            <br>
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
                            <p class="description">
                                <?php esc_html_e('Audio player is always shown.', 'podloom-podcast-player'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Podcasting 2.0 Elements', 'podloom-podcast-player'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input
                                    type="checkbox"
                                    id="podloom_rss_display_funding"
                                    name="podloom_rss_display_funding"
                                    value="1"
                                    <?php checked($all_options['podloom_rss_display_funding'] ?? true, true); ?>
                                />
                                <?php esc_html_e('Show Funding Links', 'podloom-podcast-player'); ?>
                            </label>
                            <br>
                            <label>
                                <input
                                    type="checkbox"
                                    id="podloom_rss_display_transcripts"
                                    name="podloom_rss_display_transcripts"
                                    value="1"
                                    <?php checked($all_options['podloom_rss_display_transcripts'] ?? true, true); ?>
                                />
                                <?php esc_html_e('Show Transcripts', 'podloom-podcast-player'); ?>
                            </label>
                            <br>
                            <label>
                                <input
                                    type="checkbox"
                                    id="podloom_rss_display_people_hosts"
                                    name="podloom_rss_display_people_hosts"
                                    value="1"
                                    <?php checked($all_options['podloom_rss_display_people_hosts'] ?? true, true); ?>
                                />
                                <?php esc_html_e('Show Podcast Hosts (from channel)', 'podloom-podcast-player'); ?>
                            </label>
                            <br>
                            <label>
                                <input
                                    type="checkbox"
                                    id="podloom_rss_display_people_guests"
                                    name="podloom_rss_display_people_guests"
                                    value="1"
                                    <?php checked($all_options['podloom_rss_display_people_guests'] ?? true, true); ?>
                                />
                                <?php esc_html_e('Show Episode Guests (from episode)', 'podloom-podcast-player'); ?>
                            </label>
                            <br>
                            <label>
                                <input
                                    type="checkbox"
                                    id="podloom_rss_display_chapters"
                                    name="podloom_rss_display_chapters"
                                    value="1"
                                    <?php checked($all_options['podloom_rss_display_chapters'] ?? true, true); ?>
                                />
                                <?php esc_html_e('Show Chapters', 'podloom-podcast-player'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Display Podcasting 2.0 namespace elements when available in the RSS feed. Note: Podcast hosts are typically defined at the channel level (apply to all episodes), while episode guests are defined per episode.', 'podloom-podcast-player'); ?>
                            </p>
                        </fieldset>
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
                    <th scope="row">
                        <label for="podloom_rss_player_height">
                            <?php esc_html_e('Player Height', 'podloom-podcast-player'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="podloom_rss_player_height"
                            name="podloom_rss_player_height"
                            value="<?php echo esc_attr($all_options['podloom_rss_player_height'] ?? 600); ?>"
                            min="200"
                            max="1000"
                            step="10"
                            class="small-text"
                        /> px
                        <p class="description">
                            <?php esc_html_e('Maximum height of the player in pixels. Tab content that exceeds this height will be scrollable. Default: 600px.', 'podloom-podcast-player'); ?>
                        </p>
                    </td>
                </tr>
                <!-- RSS Cache Duration setting removed - now uses General Settings → Cache Duration -->
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

            <div id="typography-section-wrapper">
                <div id="color-palette-section" style="<?php echo ($all_options['podloom_rss_minimal_styling'] ?? false) ? 'display: none;' : ''; ?>">
            <?php
            $palettes = [
                [
                    'id' => 'custom',
                    'name' => __('Custom (Use Individual Colors)', 'podloom-podcast-player'),
                    'colors' => [
                        'background' => '#f9f9f9',
                        'title' => '#1a1a1a',
                        'date' => '#666666',
                        'duration' => '#666666',
                        'description' => '#333333'
                    ]
                ],
                [
                    'id' => 'classic-dark',
                    'name' => __('Classic Dark', 'podloom-podcast-player'),
                    'colors' => [
                        'background' => '#1a1a1a',
                        'title' => '#ffffff',
                        'date' => '#a0a0a0',
                        'duration' => '#a0a0a0',
                        'description' => '#d4d4d4'
                    ]
                ],
                [
                    'id' => 'light-clean',
                    'name' => __('Light & Clean', 'podloom-podcast-player'),
                    'colors' => [
                        'background' => '#ffffff',
                        'title' => '#1a1a1a',
                        'date' => '#757575',
                        'duration' => '#757575',
                        'description' => '#424242'
                    ]
                ],
                [
                    'id' => 'warm-sunset',
                    'name' => __('Warm Sunset', 'podloom-podcast-player'),
                    'colors' => [
                        'background' => '#fff5eb',
                        'title' => '#d84315',
                        'date' => '#8d6e63',
                        'duration' => '#8d6e63',
                        'description' => '#4e342e'
                    ]
                ],
                [
                    'id' => 'cool-ocean',
                    'name' => __('Cool Ocean', 'podloom-podcast-player'),
                    'colors' => [
                        'background' => '#e3f2fd',
                        'title' => '#0d47a1',
                        'date' => '#546e7a',
                        'duration' => '#546e7a',
                        'description' => '#263238'
                    ]
                ],
                [
                    'id' => 'modern-purple',
                    'name' => __('Modern Purple', 'podloom-podcast-player'),
                    'colors' => [
                        'background' => '#f3e5f5',
                        'title' => '#6a1b9a',
                        'date' => '#7e57c2',
                        'duration' => '#7e57c2',
                        'description' => '#4a148c'
                    ]
                ],
                [
                    'id' => 'earthy-green',
                    'name' => __('Earthy Green', 'podloom-podcast-player'),
                    'colors' => [
                        'background' => '#f1f8e9',
                        'title' => '#33691e',
                        'date' => '#689f38',
                        'duration' => '#689f38',
                        'description' => '#1b5e20'
                    ]
                ]
            ];
            ?>

            <h3><?php esc_html_e('Quick Color Palettes', 'podloom-podcast-player'); ?></h3>
            <p class="description" style="margin-bottom: 15px;">
                <?php esc_html_e('Choose a pre-designed color palette. For advanced typography customization, expand the "Advanced Typography Settings" section below.', 'podloom-podcast-player'); ?>
            </p>

            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <div>
                    <select id="color-palette-select" class="regular-text">
                        <?php foreach ($palettes as $palette): ?>
                            <option value="<?php echo esc_attr($palette['id']); ?>" <?php selected($palette['id'], 'light-clean'); ?>>
                                <?php echo esc_html($palette['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="palette-swatches" style="display: flex; gap: 6px; align-items: center;">
                    <!-- Swatches will be populated by JavaScript -->
                </div>
            </div>

            <div id="palette-live-preview" style="border: 1px solid #ddd; border-radius: 4px; padding: 15px; background: #fff; max-width: 400px; margin-bottom: 20px;">
                <div id="preview-title" style="font-weight: 600; font-size: 16px; margin-bottom: 8px;">Episode Title</div>
                <div style="display: flex; gap: 12px; margin-bottom: 10px;">
                    <span id="preview-date" style="font-size: 12px;">Jan 1, 2024</span>
                    <span id="preview-duration" style="font-size: 12px;">45:30</span>
                </div>
                <div style="background: rgba(0,0,0,0.1); height: 30px; border-radius: 3px; margin-bottom: 10px;"></div>
                <div id="preview-description" style="font-size: 13px; line-height: 1.5;">
                    This is a sample episode description that shows how your text will look with the selected color palette.
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const palettes = <?php echo json_encode($palettes); ?>;
                const paletteSelect = document.getElementById('color-palette-select');
                const swatchesContainer = document.getElementById('palette-swatches');
                const livePreview = document.getElementById('palette-live-preview');

                function updatePalette(paletteId) {
                    const palette = palettes.find(p => p.id === paletteId);
                    if (!palette) return;

                    // Update swatches
                    swatchesContainer.innerHTML = Object.values(palette.colors).map(color =>
                        `<div style="width: 24px; height: 24px; border-radius: 3px; background: ${color}; border: 1px solid #ddd;" title="${color}"></div>`
                    ).join('');

                    // Update live preview
                    livePreview.style.background = palette.colors.background;
                    document.getElementById('preview-title').style.color = palette.colors.title;
                    document.getElementById('preview-date').style.color = palette.colors.date;
                    document.getElementById('preview-duration').style.color = palette.colors.duration;
                    document.getElementById('preview-description').style.color = palette.colors.description;

                    // Apply colors to form inputs (if not custom)
                    if (paletteId !== 'custom') {
                        document.getElementById('rss_background_color').value = palette.colors.background;
                        document.getElementById('title_color').value = palette.colors.title;
                        document.getElementById('date_color').value = palette.colors.date;
                        document.getElementById('duration_color').value = palette.colors.duration;
                        document.getElementById('description_color').value = palette.colors.description;

                        // Trigger preview update
                        if (window.podloomTypographyManager) {
                            window.podloomTypographyManager.updatePreview();
                        }
                    }
                }

                paletteSelect.addEventListener('change', function() {
                    updatePalette(this.value);
                });

                // Initialize with first palette
                updatePalette(paletteSelect.value);
            });
            </script>
            </div>

            <?php podloom_render_typography_settings($all_options); ?>
        </div>

        <button type="button" id="save-rss-settings" class="button button-primary" style="margin-top: 20px;">
            <?php esc_html_e('Save RSS Settings', 'podloom-podcast-player'); ?>
        </button>
    </div>
    <?php
}
