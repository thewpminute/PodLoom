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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the General Settings tab
 */
function podloom_render_general_tab( $all_options, $shows ) {
	$api_key        = $all_options['podloom_api_key'] ?? '';
	$default_show   = $all_options['podloom_default_show'] ?? '';
	$enable_cache   = $all_options['podloom_enable_cache'] ?? true;
	$cache_duration = $all_options['podloom_cache_duration'] ?? 21600;
	?>
	<form method="post" action="">
		<?php wp_nonce_field( 'podloom_settings_save', 'podloom_settings_nonce' ); ?>
		<input type="hidden" name="podloom_api_key" value="<?php echo esc_attr( $api_key ); ?>" />

		<?php if ( ! empty( $shows ) ) : ?>
		<!-- Default Show Card -->
		<div class="podloom-settings-card">
			<h3 class="podloom-card-title"><?php esc_html_e( 'Default Show', 'podloom-podcast-player' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Select the default show to use in the episode block.', 'podloom-podcast-player' ); ?>
			</p>
			<select id="podloom_default_show" name="podloom_default_show" class="regular-text">
				<option value="">
					<?php esc_html_e( '-- Select a default show --', 'podloom-podcast-player' ); ?>
				</option>
				<?php foreach ( $shows as $show ) : ?>
					<option value="<?php echo esc_attr( $show['id'] ); ?>" <?php selected( $default_show, $show['id'] ); ?>>
						<?php echo esc_html( $show['attributes']['title'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php else : ?>
			<input type="hidden" name="podloom_default_show" value="<?php echo esc_attr( $default_show ); ?>" />
		<?php endif; ?>

		<!-- Cache Settings Card -->
		<div class="podloom-settings-card">
			<h3 class="podloom-card-title"><?php esc_html_e( 'Cache Settings', 'podloom-podcast-player' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Caching improves performance and reduces API usage.', 'podloom-podcast-player' ); ?>
			</p>

			<div class="podloom-toggle-row" style="border-top: none; margin-top: 0; padding-top: 0;">
				<input type="checkbox" id="podloom_enable_cache" name="podloom_enable_cache" value="1" <?php checked( $enable_cache, true ); ?> />
				<div class="podloom-toggle-info">
					<span class="podloom-toggle-label"><?php esc_html_e( 'Enable Caching', 'podloom-podcast-player' ); ?></span>
					<span class="podloom-toggle-description"><?php esc_html_e( 'Cache API responses to reduce API calls. Recommended.', 'podloom-podcast-player' ); ?></span>
				</div>
			</div>

			<div class="podloom-inline-inputs" style="margin-top: 16px; padding-top: 16px;">
				<div class="podloom-input-group">
					<label for="podloom_cache_duration"><?php esc_html_e( 'Cache Duration', 'podloom-podcast-player' ); ?></label>
					<select id="podloom_cache_duration" name="podloom_cache_duration" style="width: 180px;">
						<option value="1800" <?php selected( $cache_duration, 1800 ); ?>><?php esc_html_e( '30 minutes', 'podloom-podcast-player' ); ?></option>
						<option value="3600" <?php selected( $cache_duration, 3600 ); ?>><?php esc_html_e( '1 hour', 'podloom-podcast-player' ); ?></option>
						<option value="7200" <?php selected( $cache_duration, 7200 ); ?>><?php esc_html_e( '2 hours', 'podloom-podcast-player' ); ?></option>
						<option value="21600" <?php selected( $cache_duration, 21600 ); ?>><?php esc_html_e( '6 hours (recommended)', 'podloom-podcast-player' ); ?></option>
						<option value="43200" <?php selected( $cache_duration, 43200 ); ?>><?php esc_html_e( '12 hours', 'podloom-podcast-player' ); ?></option>
						<option value="86400" <?php selected( $cache_duration, 86400 ); ?>><?php esc_html_e( '24 hours', 'podloom-podcast-player' ); ?></option>
					</select>
				</div>
			</div>
		</div>

		<?php submit_button( esc_html__( 'Save Settings', 'podloom-podcast-player' ), 'primary', 'podloom_settings_submit' ); ?>
	</form>

	<!-- Cache Management Card -->
	<div class="podloom-settings-card">
		<h3 class="podloom-card-title"><?php esc_html_e( 'Cache Management', 'podloom-podcast-player' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Clear the cached API data to force fresh data from Transistor and RSS feeds.', 'podloom-podcast-player' ); ?>
		</p>
		<form method="post" action="" style="margin-top: 12px;">
			<?php wp_nonce_field( 'podloom_clear_cache', 'podloom_clear_cache_nonce' ); ?>
			<button type="submit" name="podloom_clear_cache" class="button button-secondary">
				<?php esc_html_e( 'Clear Cache', 'podloom-podcast-player' ); ?>
			</button>
		</form>
	</div>

	<hr style="margin-top: 40px; border: none; border-top: 2px solid #dc3232;">

	<!-- Danger Zone Section -->
	<div class="danger-zone-container">
		<div class="danger-zone-header" id="danger-zone-toggle">
			<span class="dashicons dashicons-warning" style="color: #dc3232;"></span>
			<strong style="color: #dc3232;"><?php esc_html_e( 'Danger Zone!', 'podloom-podcast-player' ); ?></strong>
			<span class="description" style="margin-left: 10px;">
				<?php esc_html_e( 'Click to expand destructive actions', 'podloom-podcast-player' ); ?>
			</span>
			<span class="dashicons dashicons-arrow-down-alt2 danger-zone-arrow" style="float: right;"></span>
		</div>

		<div class="danger-zone-content" id="danger-zone-content" style="display: none;">
			<div class="danger-zone-warning">
				<p><strong><?php esc_html_e( '⚠️ WARNING: This action cannot be undone!', 'podloom-podcast-player' ); ?></strong></p>
				<p><?php esc_html_e( 'Resetting the plugin will permanently delete:', 'podloom-podcast-player' ); ?></p>
				<ul style="margin-left: 20px; list-style-type: disc;">
					<li><?php esc_html_e( 'Your Transistor API key', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'All RSS feeds and settings', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'Default show setting', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'Cache settings and all cached data', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'All other PodLoom plugin settings', 'podloom-podcast-player' ); ?></li>
				</ul>
				<p><?php esc_html_e( 'This will NOT affect your posts or existing episode blocks - they will simply need to be reconfigured after you re-enter your API key.', 'podloom-podcast-player' ); ?></p>
				<p><strong><?php esc_html_e( 'To confirm, type RESET in the field below and click the button.', 'podloom-podcast-player' ); ?></strong></p>
			</div>

			<form method="post" action="" onsubmit="return confirmReset();">
				<?php wp_nonce_field( 'podloom_reset_plugin', 'podloom_reset_plugin_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="reset_confirmation">
								<?php esc_html_e( 'Type RESET to confirm', 'podloom-podcast-player' ); ?>
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
								<?php esc_html_e( 'This field is case-sensitive. You must type exactly: RESET', 'podloom-podcast-player' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php
				submit_button(
					esc_html__( 'Delete All Plugin Data', 'podloom-podcast-player' ),
					'delete',
					'podloom_reset_plugin',
					true,
					array( 'style' => 'background-color: #a83232; border-color: #a83232; color: #fff;' )
				);
				?>
			</form>
		</div>
	</div>
	<?php
}
