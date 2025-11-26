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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the RSS Feeds tab
 */
function podloom_render_rss_tab( $all_options ) {
	$rss_enabled              = $all_options['podloom_rss_enabled'] ?? false;
	$rss_display_artwork      = $all_options['podloom_rss_display_artwork'] ?? true;
	$rss_display_title        = $all_options['podloom_rss_display_title'] ?? true;
	$rss_display_date         = $all_options['podloom_rss_display_date'] ?? true;
	$rss_display_duration     = $all_options['podloom_rss_display_duration'] ?? true;
	$rss_display_description  = $all_options['podloom_rss_display_description'] ?? true;
	$rss_display_skip_buttons = $all_options['podloom_rss_display_skip_buttons'] ?? true;
	?>
	<div id="rss-feeds-settings">
		<table class="form-table">
			<tr>
				<th scope="row">
					<?php esc_html_e( 'Enable RSS Feeds', 'podloom-podcast-player' ); ?>
				</th>
				<td>
					<label for="podloom_rss_enabled">
						<input
							type="checkbox"
							id="podloom_rss_enabled"
							name="podloom_rss_enabled"
							value="1"
							<?php checked( $rss_enabled, true ); ?>
						/>
						<span class="dashicons dashicons-rss" style="color: #f8981d;"></span>
						<strong><?php esc_html_e( 'Enable RSS Feeds', 'podloom-podcast-player' ); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e( 'Add podcast RSS feeds as an alternative or supplement to Transistor.fm.', 'podloom-podcast-player' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div id="rss-feeds-container" style="<?php echo $rss_enabled ? '' : 'display: none;'; ?>">
			<button type="button" id="add-new-rss-feed" class="button button-primary">
				<span class="dashicons dashicons-plus-alt" style="line-height: 1.4;"></span>
				<?php esc_html_e( 'Add New RSS Feed', 'podloom-podcast-player' ); ?>
			</button>

			<h3><?php esc_html_e( 'Your RSS Feeds', 'podloom-podcast-player' ); ?></h3>
			<div id="rss-feeds-list">
				<?php
				// Render feeds server-side for instant display
				$feeds = Podloom_RSS::get_feeds();
				if ( empty( $feeds ) ) {
					echo '<p class="description">' . esc_html__( 'No RSS feeds added yet. Click "Add New RSS Feed" to get started.', 'podloom-podcast-player' ) . '</p>';
				} else {
					echo '<table class="wp-list-table widefat fixed striped">';
					echo '<thead><tr>';
					echo '<th>' . esc_html__( 'Feed Name', 'podloom-podcast-player' ) . '</th>';
					echo '<th>' . esc_html__( 'Feed URL', 'podloom-podcast-player' ) . '</th>';
					echo '<th>' . esc_html__( 'Status', 'podloom-podcast-player' ) . '</th>';
					echo '<th>' . esc_html__( 'Last Checked', 'podloom-podcast-player' ) . '</th>';
					echo '<th>' . esc_html__( 'Actions', 'podloom-podcast-player' ) . '</th>';
					echo '</tr></thead><tbody>';

					foreach ( $feeds as $feed ) {
						$status_class = $feed['valid'] ? 'valid' : 'invalid';
						$status_text  = $feed['valid'] ? __( 'Valid', 'podloom-podcast-player' ) : __( 'Invalid', 'podloom-podcast-player' );
						$last_checked = isset( $feed['last_checked'] ) && $feed['last_checked']
							? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $feed['last_checked'] )
							: __( 'Never', 'podloom-podcast-player' );

						echo '<tr>';
						echo '<td><strong>' . esc_html( $feed['name'] ) . '</strong></td>';
						echo '<td><a href="' . esc_url( $feed['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $feed['url'] ) . '</a></td>';
						echo '<td><span class="rss-feed-status ' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span></td>';
						echo '<td>' . esc_html( $last_checked ) . '</td>';
						echo '<td>';
						echo '<button type="button" class="button button-small edit-feed" data-feed-id="' . esc_attr( $feed['id'] ) . '">' . esc_html__( 'Edit', 'podloom-podcast-player' ) . '</button> ';
						echo '<button type="button" class="button button-small refresh-feed" data-feed-id="' . esc_attr( $feed['id'] ) . '">' . esc_html__( 'Refresh', 'podloom-podcast-player' ) . '</button> ';
						echo '<button type="button" class="button button-small button-link-delete delete-feed" data-feed-id="' . esc_attr( $feed['id'] ) . '">' . esc_html__( 'Delete', 'podloom-podcast-player' ) . '</button> ';
						echo '<button type="button" class="button button-small view-feed-xml" data-feed-id="' . esc_attr( $feed['id'] ) . '">' . esc_html__( 'View Feed', 'podloom-podcast-player' ) . '</button>';
						echo '</td>';
						echo '</tr>';
					}

					echo '</tbody></table>';
				}
				?>
			</div>

			<hr>

			<!-- Player Elements Card -->
			<div class="podloom-settings-card">
				<h3 class="podloom-card-title"><?php esc_html_e( 'Player Elements', 'podloom-podcast-player' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Control which elements appear in the RSS episode player.', 'podloom-podcast-player' ); ?>
				</p>

				<div class="podloom-checkbox-grid">
					<label>
						<input type="checkbox" id="podloom_rss_display_artwork" name="podloom_rss_display_artwork" value="1" <?php checked( $rss_display_artwork, true ); ?> />
						<?php esc_html_e( 'Artwork', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_title" name="podloom_rss_display_title" value="1" <?php checked( $rss_display_title, true ); ?> />
						<?php esc_html_e( 'Episode Title', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_date" name="podloom_rss_display_date" value="1" <?php checked( $rss_display_date, true ); ?> />
						<?php esc_html_e( 'Date', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_duration" name="podloom_rss_display_duration" value="1" <?php checked( $rss_display_duration, true ); ?> />
						<?php esc_html_e( 'Duration', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_description" name="podloom_rss_display_description" value="1" <?php checked( $rss_display_description, true ); ?> />
						<?php esc_html_e( 'Description', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_skip_buttons" name="podloom_rss_display_skip_buttons" value="1" <?php checked( $rss_display_skip_buttons, true ); ?> />
						<?php esc_html_e( 'Skip Buttons', 'podloom-podcast-player' ); ?>
					</label>
				</div>

				<div class="podloom-inline-inputs">
					<div class="podloom-input-group">
						<label for="podloom_rss_description_limit"><?php esc_html_e( 'Description Limit', 'podloom-podcast-player' ); ?></label>
						<input type="number" id="podloom_rss_description_limit" name="podloom_rss_description_limit" value="<?php echo esc_attr( $all_options['podloom_rss_description_limit'] ?? '0' ); ?>" min="0" step="1" placeholder="0 = none" />
						<span class="podloom-input-hint"><?php esc_html_e( 'Truncate long descriptions. 0 = no limit.', 'podloom-podcast-player' ); ?></span>
					</div>
					<div class="podloom-input-group">
						<label for="podloom_rss_player_height"><?php esc_html_e( 'Max Height (px)', 'podloom-podcast-player' ); ?></label>
						<input type="number" id="podloom_rss_player_height" name="podloom_rss_player_height" value="<?php echo esc_attr( $all_options['podloom_rss_player_height'] ?? '600' ); ?>" min="200" step="10" />
						<span class="podloom-input-hint"><?php esc_html_e( 'Adds scrollbar if content exceeds this height.', 'podloom-podcast-player' ); ?></span>
					</div>
				</div>

				<div class="podloom-toggle-row">
					<input type="checkbox" id="podloom_rss_minimal_styling" name="podloom_rss_minimal_styling" value="1" <?php checked( $all_options['podloom_rss_minimal_styling'] ?? false, true ); ?> />
					<div class="podloom-toggle-info">
						<span class="podloom-toggle-label"><?php esc_html_e( 'Minimal Styling Mode', 'podloom-podcast-player' ); ?></span>
						<span class="podloom-toggle-description"><?php esc_html_e( 'Use your theme\'s styles instead of plugin typography.', 'podloom-podcast-player' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Podcasting 2.0 Features Card -->
			<div class="podloom-settings-card">
				<h3 class="podloom-card-title"><?php esc_html_e( 'Podcasting 2.0 Features', 'podloom-podcast-player' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Enable support for Podcasting 2.0 namespace tags.', 'podloom-podcast-player' ); ?>
				</p>

				<div class="podloom-checkbox-grid two-col">
					<label>
						<input type="checkbox" id="podloom_rss_display_funding" name="podloom_rss_display_funding" value="1" <?php checked( $all_options['podloom_rss_display_funding'] ?? true, true ); ?> />
						<?php esc_html_e( 'Funding / Value for Value', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_transcripts" name="podloom_rss_display_transcripts" value="1" <?php checked( $all_options['podloom_rss_display_transcripts'] ?? true, true ); ?> />
						<?php esc_html_e( 'Transcripts', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_people_hosts" name="podloom_rss_display_people_hosts" value="1" <?php checked( $all_options['podloom_rss_display_people_hosts'] ?? true, true ); ?> />
						<?php esc_html_e( 'People (Hosts)', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_people_guests" name="podloom_rss_display_people_guests" value="1" <?php checked( $all_options['podloom_rss_display_people_guests'] ?? true, true ); ?> />
						<?php esc_html_e( 'People (Guests)', 'podloom-podcast-player' ); ?>
					</label>
					<label>
						<input type="checkbox" id="podloom_rss_display_chapters" name="podloom_rss_display_chapters" value="1" <?php checked( $all_options['podloom_rss_display_chapters'] ?? true, true ); ?> />
						<?php esc_html_e( 'Chapters', 'podloom-podcast-player' ); ?>
					</label>
				</div>
			</div>

			<hr>

			<div id="color-palette-section" style="<?php echo ( $all_options['podloom_rss_minimal_styling'] ?? false ) ? 'display: none;' : ''; ?>">
				<h3><?php esc_html_e( 'Player Colors', 'podloom-podcast-player' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'Customize the colors of the RSS player. The player will automatically generate a color palette based on your background color.', 'podloom-podcast-player' ); ?>
				</p>

				<!-- Quick Color Palettes -->
				<div class="podloom-palettes-wrapper" style="margin-bottom: 30px;">
				<h4><?php esc_html_e( 'Quick Color Palettes', 'podloom-podcast-player' ); ?></h4>
				<div class="podloom-palettes-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
					<?php
					$palettes = array(
						'classic-dark'   => array(
							'name'   => __( 'Classic Dark', 'podloom-podcast-player' ),
							'bg'     => '#1a1a1a',
							'title'  => '#ffffff',
							'text'   => '#cccccc',
							'accent' => '#f8981d',
						),
						'light-minimal'  => array(
							'name'   => __( 'Light Minimal', 'podloom-podcast-player' ),
							'bg'     => '#ffffff',
							'title'  => '#333333',
							'text'   => '#666666',
							'accent' => '#2271b1',
						),
						'midnight-blue'  => array(
							'name'   => __( 'Midnight Blue', 'podloom-podcast-player' ),
							'bg'     => '#0f172a',
							'title'  => '#e2e8f0',
							'text'   => '#94a3b8',
							'accent' => '#38bdf8',
						),
						'forest-green'   => array(
							'name'   => __( 'Forest Green', 'podloom-podcast-player' ),
							'bg'     => '#064e3b',
							'title'  => '#ecfdf5',
							'text'   => '#a7f3d0',
							'accent' => '#34d399',
						),
						'warm-amber'     => array(
							'name'   => __( 'Warm Amber', 'podloom-podcast-player' ),
							'bg'     => '#78350f',
							'title'  => '#fffbeb',
							'text'   => '#fde68a',
							'accent' => '#fbbf24',
						),
						'sunset-vibes'   => array(
							'name'   => __( 'Sunset Vibes', 'podloom-podcast-player' ),
							'bg'     => '#4c1d1d',
							'title'  => '#ffedd5',
							'text'   => '#fdba74',
							'accent' => '#f97316',
						),
						'deep-ocean'     => array(
							'name'   => __( 'Deep Ocean', 'podloom-podcast-player' ),
							'bg'     => '#0c4a6e',
							'title'  => '#e0f2fe',
							'text'   => '#bae6fd',
							'accent' => '#0ea5e9',
						),
						'berry-smoothie' => array(
							'name'   => __( 'Berry Smoothie', 'podloom-podcast-player' ),
							'bg'     => '#4a044e',
							'title'  => '#fdf4ff',
							'text'   => '#f0abfc',
							'accent' => '#d946ef',
						),
						'slate-gray'     => array(
							'name'   => __( 'Slate Gray', 'podloom-podcast-player' ),
							'bg'     => '#334155',
							'title'  => '#f8fafc',
							'text'   => '#cbd5e1',
							'accent' => '#94a3b8',
						),
					);

					foreach ( $palettes as $id => $palette ) :
						?>
						<button type="button" class="podloom-palette-btn"
							data-palette="<?php echo esc_attr( $id ); ?>"
							data-bg="<?php echo esc_attr( $palette['bg'] ); ?>"
							data-title="<?php echo esc_attr( $palette['title'] ); ?>"
							data-text="<?php echo esc_attr( $palette['text'] ); ?>"
							data-accent="<?php echo esc_attr( $palette['accent'] ); ?>"
						>
							<span class="palette-check dashicons dashicons-yes"></span>
							<div class="palette-preview" style="background-color: <?php echo esc_attr( $palette['bg'] ); ?>;">
								<div style="width: 60%; height: 8px; background-color: <?php echo esc_attr( $palette['title'] ); ?>; border-radius: 4px; margin-bottom: 4px;"></div>
								<div style="width: 40%; height: 6px; background-color: <?php echo esc_attr( $palette['text'] ); ?>; border-radius: 3px; position: absolute; bottom: 15px; left: 20%;"></div>
								<div style="width: 20px; height: 20px; background-color: <?php echo esc_attr( $palette['accent'] ); ?>; border-radius: 50%; position: absolute; bottom: -10px; right: 10px; border: 2px solid #fff;"></div>
							</div>
							<div class="palette-name">
								<?php echo esc_html( $palette['name'] ); ?>
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
					value="<?php echo esc_attr( $all_options['podloom_rss_background_color'] ?? '#f9f9f9' ); ?>"
				/>
				<input
					type="hidden"
					id="podloom_rss_accent_color"
					name="podloom_rss_accent_color"
					value="<?php echo esc_attr( $all_options['podloom_rss_accent_color'] ?? '' ); ?>"
				/>

				<hr>
			</div><!-- #color-palette-section -->

			<div id="typography-section-wrapper">
				<?php podloom_render_typography_settings( $all_options ); ?>
			</div>

			<button type="button" id="save-rss-settings" class="button button-primary" style="margin-top: 20px;">
				<?php esc_html_e( 'Save RSS Settings', 'podloom-podcast-player' ); ?>
			</button>
		</div>
	</div>
	<?php
}
