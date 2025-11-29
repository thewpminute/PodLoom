<?php
/**
 * Subscribe Links Admin Tab
 *
 * Manages subscribe links for all configured podcasts.
 *
 * @package PodLoom
 * @since 2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the Subscribe Links admin tab.
 *
 * @param array $all_options All plugin options.
 */
function podloom_render_subscribe_tab( $all_options ) {
	// Get all podcasts.
	$podcasts  = Podloom_Subscribe::get_all_podcasts();
	$platforms = Podloom_Subscribe_Icons::get_platforms();

	?>
	<div class="podloom-settings-section">
		<h2><?php esc_html_e( 'Subscribe Links', 'podloom-podcast-player' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Configure subscribe links for each podcast. These links will be displayed when you use the Subscribe Buttons block or widget.', 'podloom-podcast-player' ); ?>
		</p>

		<?php if ( empty( $podcasts ) ) : ?>
			<div class="podloom-notice podloom-notice-warning">
				<p><?php esc_html_e( 'No podcasts configured. Add a Transistor API key or RSS feed first.', 'podloom-podcast-player' ); ?></p>
			</div>
		<?php else : ?>
			<div id="podloom-subscribe-podcasts" class="podloom-subscribe-podcasts">
				<?php foreach ( $podcasts as $podcast ) : ?>
					<?php
					$source_id = $podcast['source_id'];
					$links     = Podloom_Subscribe::get_links( $source_id );
					$is_transistor = 'transistor' === $podcast['type'];
					?>
					<div class="podloom-subscribe-podcast" data-source-id="<?php echo esc_attr( $source_id ); ?>">
						<div class="podloom-subscribe-podcast__header">
							<button type="button" class="podloom-subscribe-podcast__toggle" aria-expanded="false">
								<span class="podloom-subscribe-podcast__icon dashicons dashicons-arrow-right-alt2"></span>
								<span class="podloom-subscribe-podcast__title">
									<?php echo esc_html( $podcast['name'] ); ?>
								</span>
								<span class="podloom-subscribe-podcast__type">
									<?php echo $is_transistor ? esc_html__( 'Transistor', 'podloom-podcast-player' ) : esc_html__( 'RSS', 'podloom-podcast-player' ); ?>
								</span>
								<span class="podloom-subscribe-podcast__count">
									<?php
									$link_count = count( array_filter( $links ) );
									printf(
										/* translators: %d: number of links configured */
										esc_html( _n( '%d link', '%d links', $link_count, 'podloom-podcast-player' ) ),
										$link_count
									);
									?>
								</span>
							</button>
						</div>
						<div class="podloom-subscribe-podcast__content" style="display: none;">
							<?php if ( $is_transistor ) : ?>
								<div class="podloom-subscribe-podcast__actions">
									<button type="button" class="button podloom-sync-transistor" data-show-id="<?php echo esc_attr( str_replace( 'transistor:', '', $source_id ) ); ?>">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Sync from Transistor', 'podloom-podcast-player' ); ?>
									</button>
									<span class="podloom-sync-status"></span>
								</div>
							<?php endif; ?>

							<div class="podloom-subscribe-links">
								<?php foreach ( $platforms as $platform_key => $platform ) : ?>
									<?php $value = isset( $links[ $platform_key ] ) ? $links[ $platform_key ] : ''; ?>
									<div class="podloom-subscribe-link">
										<label for="<?php echo esc_attr( $source_id . '_' . $platform_key ); ?>">
											<span class="podloom-subscribe-link__icon" style="color: <?php echo esc_attr( $platform['color'] ); ?>;">
												<?php echo Podloom_Subscribe_Icons::get_svg( $platform_key, 'brand' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
											</span>
											<span class="podloom-subscribe-link__name"><?php echo esc_html( $platform['name'] ); ?></span>
										</label>
										<input
											type="url"
											id="<?php echo esc_attr( $source_id . '_' . $platform_key ); ?>"
											class="podloom-subscribe-link__input"
											data-platform="<?php echo esc_attr( $platform_key ); ?>"
											value="<?php echo esc_url( $value ); ?>"
											placeholder="<?php echo esc_attr( sprintf( __( 'Enter %s URL', 'podloom-podcast-player' ), $platform['name'] ) ); ?>"
										/>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="podloom-subscribe-podcast__footer">
								<button type="button" class="button button-primary podloom-save-subscribe-links">
									<?php esc_html_e( 'Save Links', 'podloom-podcast-player' ); ?>
								</button>
								<span class="podloom-save-status"></span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<style>
		.podloom-subscribe-podcasts {
			margin-top: 20px;
		}
		.podloom-subscribe-podcast {
			background: #fff;
			border: 1px solid #c3c4c7;
			margin-bottom: 10px;
			border-radius: 4px;
		}
		.podloom-subscribe-podcast__header {
			padding: 0;
		}
		.podloom-subscribe-podcast__toggle {
			display: flex;
			align-items: center;
			width: 100%;
			padding: 12px 15px;
			background: none;
			border: none;
			cursor: pointer;
			text-align: left;
			font-size: 14px;
		}
		.podloom-subscribe-podcast__toggle:hover {
			background: #f6f7f7;
		}
		.podloom-subscribe-podcast__icon {
			transition: transform 0.2s;
			margin-right: 8px;
		}
		.podloom-subscribe-podcast__toggle[aria-expanded="true"] .podloom-subscribe-podcast__icon {
			transform: rotate(90deg);
		}
		.podloom-subscribe-podcast__title {
			font-weight: 600;
			flex: 1;
		}
		.podloom-subscribe-podcast__type {
			background: #f0f0f1;
			padding: 2px 8px;
			border-radius: 3px;
			font-size: 12px;
			margin-right: 10px;
		}
		.podloom-subscribe-podcast__count {
			color: #646970;
			font-size: 12px;
		}
		.podloom-subscribe-podcast__content {
			padding: 15px;
			border-top: 1px solid #c3c4c7;
			background: #f6f7f7;
		}
		.podloom-subscribe-podcast__actions {
			margin-bottom: 15px;
			display: flex;
			align-items: center;
			gap: 10px;
		}
		.podloom-subscribe-podcast__actions .dashicons {
			margin-right: 4px;
		}
		.podloom-sync-status,
		.podloom-save-status {
			font-size: 13px;
		}
		.podloom-sync-status.success,
		.podloom-save-status.success {
			color: #00a32a;
		}
		.podloom-sync-status.error,
		.podloom-save-status.error {
			color: #d63638;
		}
		.podloom-subscribe-links {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
			gap: 10px;
		}
		.podloom-subscribe-link {
			display: flex;
			align-items: center;
			gap: 10px;
			background: #fff;
			padding: 8px 12px;
			border: 1px solid #dcdcde;
			border-radius: 4px;
		}
		.podloom-subscribe-link label {
			display: flex;
			align-items: center;
			gap: 8px;
			min-width: 150px;
			cursor: pointer;
		}
		.podloom-subscribe-link__icon {
			width: 20px;
			height: 20px;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.podloom-subscribe-link__icon svg {
			width: 20px;
			height: 20px;
		}
		.podloom-subscribe-link__name {
			font-size: 13px;
			font-weight: 500;
		}
		.podloom-subscribe-link__input {
			flex: 1;
			min-width: 0;
		}
		.podloom-subscribe-podcast__footer {
			margin-top: 15px;
			display: flex;
			align-items: center;
			gap: 10px;
		}
	</style>
	<?php
}
