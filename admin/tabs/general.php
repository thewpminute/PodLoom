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
	$cache_images   = $all_options['podloom_cache_images'] ?? false;
	$max_episodes   = $all_options['podloom_max_episodes'] ?? 50;

	// Get RSS feeds
	$rss_feeds = Podloom_RSS::get_feeds();

	// Get image cache stats if enabled.
	$image_stats = Podloom_Image_Cache::get_stats();

	// Check if we have any shows or feeds to display
	$has_options = ! empty( $shows ) || ! empty( $rss_feeds );
	?>
	<form method="post" action="">
		<?php wp_nonce_field( 'podloom_settings_save', 'podloom_settings_nonce' ); ?>
		<input type="hidden" name="podloom_settings_tab" value="general" />
		<input type="hidden" name="podloom_api_key" value="<?php echo esc_attr( $api_key ); ?>" />

		<?php if ( $has_options ) : ?>
		<!-- Default Show Card -->
		<div class="podloom-settings-card">
			<h3 class="podloom-card-title"><?php esc_html_e( 'Default Show', 'podloom-podcast-player' ); ?></h3>
			<p class="description" id="default_show_desc">
				<?php esc_html_e( 'Select the default show to use in the episode block.', 'podloom-podcast-player' ); ?>
			</p>
			<label for="podloom_default_show" class="screen-reader-text"><?php esc_html_e( 'Default Show', 'podloom-podcast-player' ); ?></label>
			<select id="podloom_default_show" name="podloom_default_show" class="regular-text" aria-describedby="default_show_desc">
				<option value="">
					<?php esc_html_e( '-- Select a default show --', 'podloom-podcast-player' ); ?>
				</option>
				<?php if ( ! empty( $shows ) ) : ?>
					<optgroup label="<?php esc_attr_e( 'Transistor Shows', 'podloom-podcast-player' ); ?>">
						<?php foreach ( $shows as $show ) : ?>
							<option value="<?php echo esc_attr( $show['id'] ); ?>" <?php selected( $default_show, $show['id'] ); ?>>
								<?php echo esc_html( $show['attributes']['title'] ); ?>
							</option>
						<?php endforeach; ?>
					</optgroup>
				<?php endif; ?>
				<?php if ( ! empty( $rss_feeds ) ) : ?>
					<optgroup label="<?php esc_attr_e( 'RSS Feeds', 'podloom-podcast-player' ); ?>">
						<?php foreach ( $rss_feeds as $feed_id => $feed ) : ?>
							<option value="<?php echo esc_attr( $feed_id ); ?>" <?php selected( $default_show, $feed_id ); ?>>
								<?php echo esc_html( $feed['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</optgroup>
				<?php endif; ?>
			</select>
		</div>
		<?php else : ?>
			<input type="hidden" name="podloom_default_show" value="<?php echo esc_attr( $default_show ); ?>" />
		<?php endif; ?>

		<!-- Cache Settings Card -->
		<div class="podloom-settings-card">
			<h3 class="podloom-card-title"><?php esc_html_e( 'Cache Settings', 'podloom-podcast-player' ); ?></h3>
			<p class="description" id="cache_settings_desc">
				<?php esc_html_e( 'Controls how often PodLoom checks for new episodes. Uses conditional HTTP requests (ETag/Last-Modified) so shorter durations are safe — feeds are only re-downloaded when content has changed.', 'podloom-podcast-player' ); ?>
			</p>

			<div class="podloom-toggle-row" style="border-top: none; margin-top: 0; padding-top: 0;">
				<input type="checkbox" id="podloom_enable_cache" name="podloom_enable_cache" value="1" <?php checked( $enable_cache, true ); ?> aria-describedby="enable_cache_desc" />
				<div class="podloom-toggle-info">
					<label for="podloom_enable_cache" class="podloom-toggle-label"><?php esc_html_e( 'Enable Caching', 'podloom-podcast-player' ); ?></label>
					<span class="podloom-toggle-description" id="enable_cache_desc"><?php esc_html_e( 'Cache API responses to reduce API calls. Recommended.', 'podloom-podcast-player' ); ?></span>
				</div>
			</div>

			<div class="podloom-inline-inputs" style="margin-top: 16px; padding-top: 16px;">
				<div class="podloom-input-group">
					<label for="podloom_cache_duration"><?php esc_html_e( 'Cache Duration', 'podloom-podcast-player' ); ?></label>
					<select id="podloom_cache_duration" name="podloom_cache_duration" style="width: 180px;" aria-describedby="cache_settings_desc">
						<option value="1800" <?php selected( $cache_duration, 1800 ); ?>><?php esc_html_e( '30 minutes', 'podloom-podcast-player' ); ?></option>
						<option value="3600" <?php selected( $cache_duration, 3600 ); ?>><?php esc_html_e( '1 hour', 'podloom-podcast-player' ); ?></option>
						<option value="7200" <?php selected( $cache_duration, 7200 ); ?>><?php esc_html_e( '2 hours', 'podloom-podcast-player' ); ?></option>
						<option value="21600" <?php selected( $cache_duration, 21600 ); ?>><?php esc_html_e( '6 hours (recommended)', 'podloom-podcast-player' ); ?></option>
						<option value="43200" <?php selected( $cache_duration, 43200 ); ?>><?php esc_html_e( '12 hours', 'podloom-podcast-player' ); ?></option>
						<option value="86400" <?php selected( $cache_duration, 86400 ); ?>><?php esc_html_e( '24 hours', 'podloom-podcast-player' ); ?></option>
					</select>
				</div>
				<div class="podloom-input-group">
					<label for="podloom_max_episodes"><?php esc_html_e( 'Max Episodes', 'podloom-podcast-player' ); ?></label>
					<input type="number" id="podloom_max_episodes" name="podloom_max_episodes" value="<?php echo esc_attr( $max_episodes ); ?>" min="1" style="width: 80px;" aria-describedby="max_episodes_desc" />
					<p class="description" id="max_episodes_desc" style="margin-top: 4px;">
						<?php esc_html_e( 'Maximum episodes to parse from RSS feeds. Higher values increase memory usage.', 'podloom-podcast-player' ); ?>
					</p>
				</div>
			</div>

			<div class="podloom-toggle-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd;">
				<input type="checkbox" id="podloom_cache_images" name="podloom_cache_images" value="1" <?php checked( $cache_images, true ); ?> aria-describedby="cache_images_desc" />
				<div class="podloom-toggle-info">
					<label for="podloom_cache_images" class="podloom-toggle-label"><?php esc_html_e( 'Cache Images Locally', 'podloom-podcast-player' ); ?></label>
					<span class="podloom-toggle-description" id="cache_images_desc"><?php esc_html_e( 'Store podcast cover art in your media library for faster loading.', 'podloom-podcast-player' ); ?></span>
				</div>
			</div>
			<?php if ( $image_stats['total_count'] > 0 ) : ?>
			<div class="podloom-image-cache-stats" style="margin-top: 12px; padding: 12px; background: #f0f0f1; border-radius: 4px; font-size: 13px;">
				<strong><?php esc_html_e( 'Cached Images:', 'podloom-podcast-player' ); ?></strong>
				<?php
				printf(
					/* translators: 1: cover count, 2: total size */
					esc_html__( '%1$d cover image(s) — %2$s total', 'podloom-podcast-player' ),
					$image_stats['cover_count'],
					size_format( $image_stats['total_size'] )
				);
				?>
			</div>
			<?php endif; ?>
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
		<button type="button" class="danger-zone-header" id="danger-zone-toggle" aria-expanded="false" aria-controls="danger-zone-content">
			<span class="dashicons dashicons-warning" style="color: #dc3232;" aria-hidden="true"></span>
			<strong style="color: #dc3232;"><?php esc_html_e( 'Danger Zone!', 'podloom-podcast-player' ); ?></strong>
			<span class="description" style="margin-left: 10px;">
				<?php esc_html_e( 'Click to expand destructive actions', 'podloom-podcast-player' ); ?>
			</span>
			<span class="dashicons dashicons-arrow-down-alt2 danger-zone-arrow" style="float: right;" aria-hidden="true"></span>
		</button>

		<div class="danger-zone-content" id="danger-zone-content" style="display: none;" aria-hidden="true">

			<?php if ( $image_stats['total_count'] > 0 ) : ?>
			<!-- Delete Cached Images -->
			<div class="podloom-danger-item" style="padding: 20px; background: #fff8f8; border: 1px solid #f5c6c6; border-radius: 4px; margin-bottom: 20px;">
				<h4 style="margin-top: 0; color: #a83232;">
					<span class="dashicons dashicons-images-alt2" style="margin-right: 5px;"></span>
					<?php esc_html_e( 'Delete Cached Images', 'podloom-podcast-player' ); ?>
				</h4>
				<p class="description">
					<?php
					printf(
						/* translators: 1: total image count, 2: total size */
						esc_html__( 'Remove all %1$d cached cover image(s) (%2$s) from the media library. The player will fall back to loading images from the original URLs.', 'podloom-podcast-player' ),
						$image_stats['total_count'],
						size_format( $image_stats['total_size'] )
					);
					?>
				</p>
				<form method="post" action="" style="margin-top: 12px;">
					<?php wp_nonce_field( 'podloom_delete_cached_images', 'podloom_delete_cached_images_nonce' ); ?>
					<button type="submit" name="podloom_delete_cached_images" class="button" style="color: #a83232; border-color: #a83232;" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete all cached images? This cannot be undone.', 'podloom-podcast-player' ) ); ?>');">
						<?php esc_html_e( 'Delete All Cached Images', 'podloom-podcast-player' ); ?>
					</button>
				</form>
			</div>
			<?php endif; ?>

			<!-- Full Reset -->
			<div class="danger-zone-warning">
				<p><strong><?php esc_html_e( '⚠️ WARNING: This action cannot be undone!', 'podloom-podcast-player' ); ?></strong></p>
				<p><?php esc_html_e( 'Resetting the plugin will permanently delete:', 'podloom-podcast-player' ); ?></p>
				<ul style="margin-left: 20px; list-style-type: disc;">
					<li><?php esc_html_e( 'Your Transistor API key', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'All RSS feeds and settings', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'Default show setting', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'Cache settings and all cached data', 'podloom-podcast-player' ); ?></li>
					<li><?php esc_html_e( 'All cached images from the media library', 'podloom-podcast-player' ); ?></li>
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
								aria-describedby="reset_confirmation_desc"
							/>
							<p class="description" id="reset_confirmation_desc">
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
