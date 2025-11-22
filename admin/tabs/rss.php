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
                        <?php esc_html_e('Player Type', 'podloom-podcast-player'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label style="margin-right: 20px;">
                                <input
                                    type="radio"
                                    name="podloom_rss_player_type"
                                    value="native"
                                    <?php checked($all_options['podloom_rss_player_type'] ?? 'native', 'native'); ?>
                                />
                                <?php esc_html_e('Native HTML5 Player', 'podloom-podcast-player'); ?>
                            </label>
                            <label>
                                <input
                                    type="radio"
                                    name="podloom_rss_player_type"
                                    value="plyr"
                                    <?php checked($all_options['podloom_rss_player_type'] ?? 'native', 'plyr'); ?>
                                />
                                <?php esc_html_e('PodLoom Player', 'podloom-podcast-player'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Choose between the standard browser audio player or the enhanced PodLoom player (features speed control, volume slider, and theme matching).', 'podloom-podcast-player'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>

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
                                <?php esc_html_e('Display Artwork', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('Display Episode Title', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('Display Date', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('Display Duration', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('Display Description', 'podloom-podcast-player'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Advanced Display', 'podloom-podcast-player'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label for="podloom_rss_description_limit">
                                <?php esc_html_e('Description Character Limit', 'podloom-podcast-player'); ?>
                                <br>
                                <input
                                    type="number"
                                    id="podloom_rss_description_limit"
                                    name="podloom_rss_description_limit"
                                    value="<?php echo esc_attr($all_options['podloom_rss_description_limit'] ?? '0'); ?>"
                                    min="0"
                                    step="1"
                                    class="small-text"
                                />
                                <p class="description">
                                    <?php esc_html_e('Limit the number of characters in the description. Set to 0 for no limit.', 'podloom-podcast-player'); ?>
                                </p>
                            </label>
                            <br><br>
                            <label for="podloom_rss_player_height">
                                <?php esc_html_e('Player Max Height (px)', 'podloom-podcast-player'); ?>
                                <br>
                                <input
                                    type="number"
                                    id="podloom_rss_player_height"
                                    name="podloom_rss_player_height"
                                    value="<?php echo esc_attr($all_options['podloom_rss_player_height'] ?? '600'); ?>"
                                    min="200"
                                    step="10"
                                    class="small-text"
                                />
                            </label>
                            <br><br>
                            <label for="podloom_rss_minimal_styling">
                                <input
                                    type="checkbox"
                                    id="podloom_rss_minimal_styling"
                                    name="podloom_rss_minimal_styling"
                                    value="1"
                                    <?php checked($all_options['podloom_rss_minimal_styling'] ?? false, true); ?>
                                />
                                <?php esc_html_e('Minimal Styling Mode', 'podloom-podcast-player'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Disable advanced typography settings and use your theme\'s styles instead.', 'podloom-podcast-player'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <hr>

            <h3><?php esc_html_e('Podcasting 2.0 Features', 'podloom-podcast-player'); ?></h3>
            <p class="description">
                <?php esc_html_e('Enable or disable support for specific Podcasting 2.0 namespace tags.', 'podloom-podcast-player'); ?>
            </p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <?php esc_html_e('Supported Features', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('Funding / Value for Value', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('Transcripts', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('People (Hosts)', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('People (Guests)', 'podloom-podcast-player'); ?>
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
                                <?php esc_html_e('Chapters', 'podloom-podcast-player'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <hr>

            <h3><?php esc_html_e('Player Colors', 'podloom-podcast-player'); ?></h3>
            <p class="description">
                <?php esc_html_e('Customize the colors of the RSS player. The player will automatically generate a color palette based on your background color.', 'podloom-podcast-player'); ?>
            </p>

            <!-- Quick Color Palettes -->
            <div class="podloom-palettes-wrapper" style="margin-bottom: 30px;">
                <h4><?php esc_html_e('Quick Color Palettes', 'podloom-podcast-player'); ?></h4>
                <div class="podloom-palettes-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                    <?php
                    $palettes = [
                        'classic-dark' => [
                            'name' => __('Classic Dark', 'podloom-podcast-player'),
                            'bg' => '#1a1a1a',
                            'title' => '#ffffff',
                            'text' => '#cccccc',
                            'accent' => '#f8981d'
                        ],
                        'light-minimal' => [
                            'name' => __('Light Minimal', 'podloom-podcast-player'),
                            'bg' => '#ffffff',
                            'title' => '#333333',
                            'text' => '#666666',
                            'accent' => '#2271b1'
                        ],
                        'midnight-blue' => [
                            'name' => __('Midnight Blue', 'podloom-podcast-player'),
                            'bg' => '#0f172a',
                            'title' => '#e2e8f0',
                            'text' => '#94a3b8',
                            'accent' => '#38bdf8'
                        ],
                        'forest-green' => [
                            'name' => __('Forest Green', 'podloom-podcast-player'),
                            'bg' => '#064e3b',
                            'title' => '#ecfdf5',
                            'text' => '#a7f3d0',
                            'accent' => '#34d399'
                        ],
                        'warm-amber' => [
                            'name' => __('Warm Amber', 'podloom-podcast-player'),
                            'bg' => '#78350f',
                            'title' => '#fffbeb',
                            'text' => '#fde68a',
                            'accent' => '#fbbf24'
                        ],
                        'sunset-vibes' => [
                            'name' => __('Sunset Vibes', 'podloom-podcast-player'),
                            'bg' => '#4c1d1d',
                            'title' => '#ffedd5',
                            'text' => '#fdba74',
                            'accent' => '#f97316'
                        ],
                        'deep-ocean' => [
                            'name' => __('Deep Ocean', 'podloom-podcast-player'),
                            'bg' => '#0c4a6e',
                            'title' => '#e0f2fe',
                            'text' => '#bae6fd',
                            'accent' => '#0ea5e9'
                        ],
                        'berry-smoothie' => [
                            'name' => __('Berry Smoothie', 'podloom-podcast-player'),
                            'bg' => '#4a044e',
                            'title' => '#fdf4ff',
                            'text' => '#f0abfc',
                            'accent' => '#d946ef'
                        ],
                        'slate-gray' => [
                            'name' => __('Slate Gray', 'podloom-podcast-player'),
                            'bg' => '#334155',
                            'title' => '#f8fafc',
                            'text' => '#cbd5e1',
                            'accent' => '#94a3b8'
                        ]
                    ];

                    foreach ($palettes as $id => $palette) :
                    ?>
                        <button type="button" class="podloom-palette-btn"
                            data-palette="<?php echo esc_attr($id); ?>"
                            data-bg="<?php echo esc_attr($palette['bg']); ?>"
                            data-title="<?php echo esc_attr($palette['title']); ?>"
                            data-text="<?php echo esc_attr($palette['text']); ?>"
                            data-accent="<?php echo esc_attr($palette['accent']); ?>"
                            style="
                                display: flex;
                                flex-direction: column;
                                border: 1px solid #ddd;
                                border-radius: 6px;
                                overflow: hidden;
                                cursor: pointer;
                                padding: 0;
                                background: transparent;
                                transition: transform 0.2s, box-shadow 0.2s;
                            "
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 6px rgba(0,0,0,0.1)';"
                            onmouseout="this.style.transform='none'; this.style.boxShadow='none';"
                        >
                            <div class="palette-preview" style="height: 60px; width: 100%; background-color: <?php echo esc_attr($palette['bg']); ?>; position: relative; display: flex; align-items: center; justify-content: center;">
                                <div style="width: 60%; height: 8px; background-color: <?php echo esc_attr($palette['title']); ?>; border-radius: 4px; margin-bottom: 4px;"></div>
                                <div style="width: 40%; height: 6px; background-color: <?php echo esc_attr($palette['text']); ?>; border-radius: 3px; position: absolute; bottom: 15px; left: 20%;"></div>
                                <div style="width: 20px; height: 20px; background-color: <?php echo esc_attr($palette['accent']); ?>; border-radius: 50%; position: absolute; bottom: -10px; right: 10px; border: 2px solid #fff;"></div>
                            </div>
                            <div class="palette-name" style="padding: 8px; font-size: 12px; font-weight: 500; color: #444; width: 100%; text-align: center; background: #fff;">
                                <?php echo esc_html($palette['name']); ?>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Hidden inputs for colors (managed via palettes) -->
            <input
                type="hidden"
                id="podloom_rss_background_color"
                name="podloom_rss_background_color"
                value="<?php echo esc_attr($all_options['podloom_rss_background_color'] ?? '#f9f9f9'); ?>"
            />
            <input
                type="hidden"
                id="podloom_rss_accent_color"
                name="podloom_rss_accent_color"
                value="<?php echo esc_attr($all_options['podloom_rss_accent_color'] ?? ''); ?>"
            />

            <hr>

            <div id="typography-section-wrapper">
                <?php podloom_render_typography_settings($all_options); ?>
            </div>

            <button type="button" id="save-rss-settings" class="button button-primary" style="margin-top: 20px;">
                <?php esc_html_e('Save RSS Settings', 'podloom-podcast-player'); ?>
            </button>
        </div>
    </div>
    <?php
}
