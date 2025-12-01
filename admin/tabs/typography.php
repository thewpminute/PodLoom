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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render typography settings section for RSS tab
 */
function podloom_render_typography_settings( $all_options ) {
	$elements = array( 'title', 'date', 'duration', 'description' );

	// Default values matching PHP backend defaults
	$defaults = array(
		'title'       => array(
			'font_size'   => '24px',
			'line_height' => '1.3',
			'color'       => '#000000',
			'font_weight' => '600',
		),
		'date'        => array(
			'font_size'   => '14px',
			'line_height' => '1.5',
			'color'       => '#666666',
			'font_weight' => 'normal',
		),
		'duration'    => array(
			'font_size'   => '14px',
			'line_height' => '1.5',
			'color'       => '#666666',
			'font_weight' => 'normal',
		),
		'description' => array(
			'font_size'   => '16px',
			'line_height' => '1.6',
			'color'       => '#333333',
			'font_weight' => 'normal',
		),
	);

	$typo = array();
	foreach ( $elements as $element ) {
		$typo[ $element ] = array(
			'font_family' => $all_options[ "podloom_rss_{$element}_font_family" ] ?? 'inherit',
			'font_size'   => $all_options[ "podloom_rss_{$element}_font_size" ] ?? $defaults[ $element ]['font_size'],
			'line_height' => $all_options[ "podloom_rss_{$element}_line_height" ] ?? $defaults[ $element ]['line_height'],
			'color'       => $all_options[ "podloom_rss_{$element}_color" ] ?? $defaults[ $element ]['color'],
			'font_weight' => $all_options[ "podloom_rss_{$element}_font_weight" ] ?? $defaults[ $element ]['font_weight'],
		);
	}

	// Funding button defaults
	$funding_defaults = array(
		'font_family'      => 'inherit',
		'font_size'        => '13px',
		'background_color' => '#2271b1',
		'text_color'       => '#ffffff',
		'border_radius'    => '4px',
	);

	$funding = array(
		'font_family'      => $all_options['podloom_rss_funding_font_family'] ?? $funding_defaults['font_family'],
		'font_size'        => $all_options['podloom_rss_funding_font_size'] ?? $funding_defaults['font_size'],
		'background_color' => $all_options['podloom_rss_funding_background_color'] ?? $funding_defaults['background_color'],
		'text_color'       => $all_options['podloom_rss_funding_text_color'] ?? $funding_defaults['text_color'],
		'border_radius'    => $all_options['podloom_rss_funding_border_radius'] ?? $funding_defaults['border_radius'],
	);

	// Player border defaults
	$border_defaults = array(
		'color'  => '#dddddd',
		'width'  => '1px',
		'style'  => 'solid',
		'radius' => '8px',
	);

	$border = array(
		'color'  => $all_options['podloom_rss_border_color'] ?? $border_defaults['color'],
		'width'  => $all_options['podloom_rss_border_width'] ?? $border_defaults['width'],
		'style'  => $all_options['podloom_rss_border_style'] ?? $border_defaults['style'],
		'radius' => $all_options['podloom_rss_border_radius'] ?? $border_defaults['radius'],
	);
	?>

	<div class="podloom-accordion-container">
		<div class="podloom-accordion-header" id="typography-accordion-toggle">
			<span class="dashicons dashicons-admin-customizer" style="color: #2271b1; margin-right: 8px;"></span>
			<strong><?php esc_html_e( 'Advanced Typography & Player Style Settings', 'podloom-podcast-player' ); ?></strong>
			<span class="description" style="margin-left: 10px;">
				<?php esc_html_e( 'Click to customize fonts, sizes, and colors', 'podloom-podcast-player' ); ?>
			</span>
			<span class="dashicons dashicons-arrow-down-alt2 podloom-accordion-arrow"></span>
		</div>
		<div class="podloom-accordion-content" id="typography-accordion-content" style="display: none;">
			<!-- Minimal Styling Mode Toggle -->
			<div class="podloom-toggle-row" id="minimal-styling-toggle" style="border-top: none; margin-top: 0; padding: 20px 0;">
				<input type="checkbox" id="podloom_rss_minimal_styling" name="podloom_rss_minimal_styling" value="1" <?php checked( $all_options['podloom_rss_minimal_styling'] ?? false, true ); ?> />
				<div class="podloom-toggle-info">
					<span class="podloom-toggle-label"><?php esc_html_e( 'Minimal Styling Mode', 'podloom-podcast-player' ); ?></span>
					<span class="podloom-toggle-description"><?php esc_html_e( 'Disable plugin styling and use your theme\'s CSS instead. Shows available CSS classes below.', 'podloom-podcast-player' ); ?></span>
				</div>
			</div>

			<!-- CSS Classes Notice (shown when minimal styling is enabled) -->
			<div id="minimal-styling-notice" class="notice notice-info inline" style="margin: 0 0 20px 0; <?php echo ( $all_options['podloom_rss_minimal_styling'] ?? false ) ? '' : 'display: none;'; ?>">
				<p><strong><?php esc_html_e( 'Minimal Styling Mode is enabled.', 'podloom-podcast-player' ); ?></strong> <?php esc_html_e( 'Typography and color settings are disabled. Add your own CSS using the following classes:', 'podloom-podcast-player' ); ?></p>
				<p><strong><?php esc_html_e( 'Episode Elements:', 'podloom-podcast-player' ); ?></strong> <code>.rss-episode-player</code>, <code>.rss-episode-title</code>, <code>.rss-episode-date</code>, <code>.rss-episode-duration</code>, <code>.rss-episode-description</code>, <code>.rss-episode-artwork</code>, <code>.rss-episode-audio</code></p>
				<p><strong><?php esc_html_e( 'Podcasting 2.0 Elements:', 'podloom-podcast-player' ); ?></strong> <code>.podcast20-tabs</code>, <code>.podcast20-tab-button</code>, <code>.podcast20-tab-panel</code>, <code>.podcast20-funding-button</code>, <code>.podcast20-transcripts</code>, <code>.transcript-format-button</code>, <code>.transcript-viewer</code>, <code>.podcast20-people</code>, <code>.podcast20-person</code>, <code>.podcast20-person-name</code>, <code>.podcast20-chapters-list</code>, <code>.chapter-item</code>, <code>.chapter-title</code>, <code>.chapter-timestamp</code></p>
			</div>

			<div class="typography-settings-container" style="<?php echo ( $all_options['podloom_rss_minimal_styling'] ?? false ) ? 'display: none;' : ''; ?>">
			<div class="typography-controls">
				<!-- Background Color Section -->
				<div class="typography-element-section">
					<h4><?php esc_html_e( 'Block Background Color', 'podloom-podcast-player' ); ?></h4>
					<table class="form-table">
						<tr>
							<th><label><?php esc_html_e( 'Background', 'podloom-podcast-player' ); ?></label></th>
							<td>
								<input type="color" id="rss_background_color" value="<?php echo esc_attr( $all_options['podloom_rss_background_color'] ?? '#f9f9f9' ); ?>" class="typo-control color-picker" data-element="background" data-property="background-color">
								<p class="description"><?php esc_html_e( 'Choose a background color for the entire RSS episode block.', 'podloom-podcast-player' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<?php
				foreach ( $elements as $element ) :
					$label = ucfirst( $element );
					?>
				<div class="typography-element-section" id="<?php echo esc_attr( $element ); ?>_typography_section" data-element="<?php echo esc_attr( $element ); ?>">
					<h4><?php echo esc_html( $label ); ?> <?php esc_html_e( 'Typography', 'podloom-podcast-player' ); ?></h4>

				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Font Family', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<select id="<?php echo esc_attr( $element ); ?>_font_family" class="regular-text typo-control" data-element="<?php echo esc_attr( $element ); ?>" data-property="font-family">
								<option value="inherit" <?php selected( $typo[ $element ]['font_family'], 'inherit' ); ?>>Inherit</option>
								<option value="Arial, sans-serif" <?php selected( $typo[ $element ]['font_family'], 'Arial, sans-serif' ); ?>>Arial</option>
								<option value="Helvetica, sans-serif" <?php selected( $typo[ $element ]['font_family'], 'Helvetica, sans-serif' ); ?>>Helvetica</option>
								<option value="'Times New Roman', serif" <?php selected( $typo[ $element ]['font_family'], "'Times New Roman', serif" ); ?>>Times New Roman</option>
								<option value="Georgia, serif" <?php selected( $typo[ $element ]['font_family'], 'Georgia, serif' ); ?>>Georgia</option>
								<option value="'Courier New', monospace" <?php selected( $typo[ $element ]['font_family'], "'Courier New', monospace" ); ?>>Courier New</option>
								<option value="Verdana, sans-serif" <?php selected( $typo[ $element ]['font_family'], 'Verdana, sans-serif' ); ?>>Verdana</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Font Size', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<input type="hidden" id="<?php echo esc_attr( $element ); ?>_font_size" value="<?php echo esc_attr( $typo[ $element ]['font_size'] ); ?>">
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="range" id="<?php echo esc_attr( $element ); ?>_font_size_range" min="8" max="72" step="1" value="16" class="typo-range" data-element="<?php echo esc_attr( $element ); ?>" style="flex: 1;">
								<input type="number" id="<?php echo esc_attr( $element ); ?>_font_size_value" min="0" max="200" step="0.1" value="16" class="small-text typo-control typo-size-value" data-element="<?php echo esc_attr( $element ); ?>" data-property="font-size" style="width: 70px;">
								<select id="<?php echo esc_attr( $element ); ?>_font_size_unit" class="typo-control typo-size-unit" data-element="<?php echo esc_attr( $element ); ?>" data-property="font-size" style="width: 70px;">
									<option value="px">px</option>
									<option value="em">em</option>
									<option value="rem">rem</option>
									<option value="%">%</option>
								</select>
							</div>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Line Height', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<input type="hidden" id="<?php echo esc_attr( $element ); ?>_line_height" value="<?php echo esc_attr( $typo[ $element ]['line_height'] ); ?>">
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="range" id="<?php echo esc_attr( $element ); ?>_line_height_range" min="0.5" max="3" step="0.1" value="1.5" class="typo-range" data-element="<?php echo esc_attr( $element ); ?>" style="flex: 1;">
								<input type="number" id="<?php echo esc_attr( $element ); ?>_line_height_value" min="0" max="10" step="0.1" value="1.5" class="small-text typo-control typo-lineheight-value" data-element="<?php echo esc_attr( $element ); ?>" data-property="line-height" style="width: 70px;">
								<span style="width: 70px; text-align: center; color: #666;">(unitless)</span>
							</div>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Text Color', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<input type="color" id="<?php echo esc_attr( $element ); ?>_color" value="<?php echo esc_attr( $typo[ $element ]['color'] ); ?>" class="typo-control color-picker" data-element="<?php echo esc_attr( $element ); ?>" data-property="color">
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Font Weight', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<select id="<?php echo esc_attr( $element ); ?>_font_weight" class="regular-text typo-control" data-element="<?php echo esc_attr( $element ); ?>" data-property="font-weight">
								<option value="normal" <?php selected( $typo[ $element ]['font_weight'], 'normal' ); ?>>Normal</option>
								<option value="bold" <?php selected( $typo[ $element ]['font_weight'], 'bold' ); ?>>Bold</option>
								<option value="600" <?php selected( $typo[ $element ]['font_weight'], '600' ); ?>>Semi-Bold (600)</option>
								<option value="300" <?php selected( $typo[ $element ]['font_weight'], '300' ); ?>>Light (300)</option>
							</select>
						</td>
					</tr>
				</table>
			</div>
			<?php endforeach; ?>

			<!-- Player Border Settings -->
			<div class="typography-element-section" id="border_settings_section">
				<h4><?php esc_html_e( 'Player Border', 'podloom-podcast-player' ); ?></h4>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Border Color', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<input type="color" id="podloom_rss_border_color" name="podloom_rss_border_color" value="<?php echo esc_attr( $border['color'] ); ?>" class="typo-control color-picker">
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Border Width', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="range" id="podloom_rss_border_width_range" min="0" max="10" step="1" value="<?php echo esc_attr( (int) $border['width'] ); ?>" style="flex: 1;">
								<input type="number" id="podloom_rss_border_width_value" name="podloom_rss_border_width" min="0" max="20" step="1" value="<?php echo esc_attr( (int) $border['width'] ); ?>" class="small-text" style="width: 70px;">
								<span style="width: 30px;">px</span>
							</div>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Border Style', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<select id="podloom_rss_border_style" name="podloom_rss_border_style" class="regular-text">
								<option value="solid" <?php selected( $border['style'], 'solid' ); ?>><?php esc_html_e( 'Solid', 'podloom-podcast-player' ); ?></option>
								<option value="dashed" <?php selected( $border['style'], 'dashed' ); ?>><?php esc_html_e( 'Dashed', 'podloom-podcast-player' ); ?></option>
								<option value="dotted" <?php selected( $border['style'], 'dotted' ); ?>><?php esc_html_e( 'Dotted', 'podloom-podcast-player' ); ?></option>
								<option value="double" <?php selected( $border['style'], 'double' ); ?>><?php esc_html_e( 'Double', 'podloom-podcast-player' ); ?></option>
								<option value="none" <?php selected( $border['style'], 'none' ); ?>><?php esc_html_e( 'None', 'podloom-podcast-player' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Border Radius', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="range" id="podloom_rss_border_radius_range" min="0" max="30" step="1" value="<?php echo esc_attr( (int) $border['radius'] ); ?>" style="flex: 1;">
								<input type="number" id="podloom_rss_border_radius_value" name="podloom_rss_border_radius" min="0" max="50" step="1" value="<?php echo esc_attr( (int) $border['radius'] ); ?>" class="small-text" style="width: 70px;">
								<span style="width: 30px;">px</span>
							</div>
						</td>
					</tr>
				</table>
			</div>

			<!-- Funding Button Settings -->
			<div class="typography-element-section" id="funding_settings_section">
				<h4><?php esc_html_e( 'Funding Button', 'podloom-podcast-player' ); ?></h4>
				<table class="form-table">
					<tr>
						<th><label><?php esc_html_e( 'Font Family', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<select id="podloom_rss_funding_font_family" name="podloom_rss_funding_font_family" class="regular-text">
								<option value="inherit" <?php selected( $funding['font_family'], 'inherit' ); ?>><?php esc_html_e( 'Inherit', 'podloom-podcast-player' ); ?></option>
								<option value="Arial, sans-serif" <?php selected( $funding['font_family'], 'Arial, sans-serif' ); ?>>Arial</option>
								<option value="Helvetica, sans-serif" <?php selected( $funding['font_family'], 'Helvetica, sans-serif' ); ?>>Helvetica</option>
								<option value="'Times New Roman', serif" <?php selected( $funding['font_family'], "'Times New Roman', serif" ); ?>>Times New Roman</option>
								<option value="Georgia, serif" <?php selected( $funding['font_family'], 'Georgia, serif' ); ?>>Georgia</option>
								<option value="'Courier New', monospace" <?php selected( $funding['font_family'], "'Courier New', monospace" ); ?>>Courier New</option>
								<option value="Verdana, sans-serif" <?php selected( $funding['font_family'], 'Verdana, sans-serif' ); ?>>Verdana</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Font Size', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="range" id="podloom_rss_funding_font_size_range" min="10" max="24" step="1" value="<?php echo esc_attr( (int) $funding['font_size'] ); ?>" style="flex: 1;">
								<input type="number" id="podloom_rss_funding_font_size_value" name="podloom_rss_funding_font_size" min="8" max="32" step="1" value="<?php echo esc_attr( (int) $funding['font_size'] ); ?>" class="small-text" style="width: 70px;">
								<span style="width: 30px;">px</span>
							</div>
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Background Color', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<input type="color" id="podloom_rss_funding_background_color" name="podloom_rss_funding_background_color" value="<?php echo esc_attr( $funding['background_color'] ); ?>" class="typo-control color-picker">
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Text Color', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<input type="color" id="podloom_rss_funding_text_color" name="podloom_rss_funding_text_color" value="<?php echo esc_attr( $funding['text_color'] ); ?>" class="typo-control color-picker">
						</td>
					</tr>
					<tr>
						<th><label><?php esc_html_e( 'Border Radius', 'podloom-podcast-player' ); ?></label></th>
						<td>
							<div style="display: flex; align-items: center; gap: 10px;">
								<input type="range" id="podloom_rss_funding_border_radius_range" min="0" max="30" step="1" value="<?php echo esc_attr( (int) $funding['border_radius'] ); ?>" style="flex: 1;">
								<input type="number" id="podloom_rss_funding_border_radius_value" name="podloom_rss_funding_border_radius" min="0" max="50" step="1" value="<?php echo esc_attr( (int) $funding['border_radius'] ); ?>" class="small-text" style="width: 70px;">
								<span style="width: 30px;">px</span>
							</div>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<div class="typography-preview">
			<h4><?php esc_html_e( 'Live Preview', 'podloom-podcast-player' ); ?></h4>
			<div id="rss-episode-preview" class="rss-episode-player" style="background: #f9f9f9; border: <?php echo esc_attr( $border['width'] ); ?>px <?php echo esc_attr( $border['style'] ); ?> <?php echo esc_attr( $border['color'] ); ?>; border-radius: <?php echo esc_attr( (int) $border['radius'] ); ?>px; padding: 20px;">
				<div class="rss-episode-wrapper" style="display: flex; gap: 20px; align-items: flex-start;">
					<div class="rss-episode-artwork-column" style="flex-shrink: 0; width: 200px;">
						<div id="preview-artwork" class="rss-episode-artwork">
							<img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Crect fill='%23f0f0f0' width='300' height='300'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' font-family='Arial' font-size='18' fill='%23666'%3EPodcast Artwork%3C/text%3E%3C/svg%3E" alt="<?php esc_attr_e( 'Podcast Artwork', 'podloom-podcast-player' ); ?>" style="width: 100%; height: auto; border-radius: 4px; display: block;">
						</div>
						<div class="rss-funding-desktop" style="margin-top: 12px;">
							<a href="#" id="preview-funding-button" class="podcast20-funding-button" style="display: inline-flex; align-items: center; gap: 6px; width: 100%; justify-content: center; box-sizing: border-box; text-decoration: none; font-weight: 500; font-family: <?php echo esc_attr( $funding['font_family'] ); ?>; font-size: <?php echo esc_attr( (int) $funding['font_size'] ); ?>px; background: <?php echo esc_attr( $funding['background_color'] ); ?>; color: <?php echo esc_attr( $funding['text_color'] ); ?>; padding: 8px 16px; border-radius: <?php echo esc_attr( (int) $funding['border_radius'] ); ?>px;" onclick="return false;">
								<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
									<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
									<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
								</svg>
								<span><?php esc_html_e( 'Support the Show', 'podloom-podcast-player' ); ?></span>
							</a>
						</div>
					</div>
					<div class="rss-episode-content" style="flex: 1; min-width: 0;">
						<h3 id="preview-title" class="rss-episode-title" style="margin: 0 0 10px 0;">Sample Episode Title</h3>
						<div id="preview-meta" class="rss-episode-meta" style="display: flex; gap: 15px; margin-bottom: 15px;">
							<span id="preview-date" class="rss-episode-date">January 1, 2024</span>
							<span id="preview-duration" class="rss-episode-duration">45:30</span>
						</div>
						<!-- Custom Audio Player Preview -->
						<div class="podloom-player-container" style="margin-bottom: 15px;">
							<audio class="podloom-audio-element" preload="metadata">
								<source src="data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=" type="audio/wav">
							</audio>
							<div class="podloom-player-main">
								<button type="button" class="podloom-play-toggle" aria-label="<?php esc_attr_e( 'Play', 'podloom-podcast-player' ); ?>">
									<svg class="podloom-icon-play" width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
										<circle cx="24" cy="24" r="24" fill="currentColor"/>
										<path d="M32 24L18 33V15L32 24Z"/>
									</svg>
									<svg class="podloom-icon-pause" width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
										<circle cx="24" cy="24" r="24" fill="currentColor"/>
										<rect x="17" y="14" width="5" height="20" rx="1"/>
										<rect x="26" y="14" width="5" height="20" rx="1"/>
									</svg>
								</button>
								<div class="podloom-player-content">
									<div class="podloom-timeline-container">
										<div class="podloom-timeline-progress" style="width: 35%;"></div>
										<input type="range" class="podloom-timeline-slider" min="0" max="100" value="35" step="0.1" aria-label="<?php esc_attr_e( 'Seek', 'podloom-podcast-player' ); ?>">
									</div>
									<div class="podloom-controls-row">
										<div class="podloom-secondary-controls">
											<button type="button" class="podloom-control-btn podloom-skip-btn" data-skip="-10" aria-label="<?php esc_attr_e( 'Rewind 10 seconds', 'podloom-podcast-player' ); ?>">
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
													<path d="M3 3v5h5"/>
												</svg>
												<span class="podloom-skip-label">10</span>
											</button>
											<button type="button" class="podloom-speed-btn" aria-label="<?php esc_attr_e( 'Playback speed', 'podloom-podcast-player' ); ?>">1x</button>
											<button type="button" class="podloom-control-btn podloom-skip-btn" data-skip="30" aria-label="<?php esc_attr_e( 'Forward 30 seconds', 'podloom-podcast-player' ); ?>">
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M21 12a9 9 0 1 1-9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/>
													<path d="M21 3v5h-5"/>
												</svg>
												<span class="podloom-skip-label">30</span>
											</button>
										</div>
										<div class="podloom-time-display">
											<span class="podloom-current-time">15:54</span>
											<span class="podloom-time-separator">/</span>
											<span class="podloom-duration">45:30</span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div id="preview-description" class="rss-episode-description">
							<p>This is a sample episode description. It gives listeners an overview of what the episode is about and what they can expect to learn.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
		</div><!-- .podloom-accordion-content -->
	</div><!-- .podloom-accordion-container -->
	</div><!-- #typography-section-wrapper -->
	<?php
}
