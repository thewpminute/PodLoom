<?php
/**
 * Admin Functions
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include admin tab files
require_once PODLOOM_PLUGIN_DIR . 'admin/tabs/welcome.php';
require_once PODLOOM_PLUGIN_DIR . 'admin/tabs/general.php';
require_once PODLOOM_PLUGIN_DIR . 'admin/tabs/transistor.php';
require_once PODLOOM_PLUGIN_DIR . 'admin/tabs/typography.php';
require_once PODLOOM_PLUGIN_DIR . 'admin/tabs/rss.php';
require_once PODLOOM_PLUGIN_DIR . 'admin/tabs/subscribe.php';

/**
 * Enqueue admin scripts and styles
 */
function podloom_enqueue_admin_scripts( $hook ) {
	// Only load on our settings page
	if ( $hook !== 'settings_page_podloom-settings' ) {
		return;
	}

	// Enqueue admin styles
	wp_enqueue_style(
		'podloom-admin-styles',
		PODLOOM_PLUGIN_URL . 'admin/css/admin-styles' . PODLOOM_SCRIPT_SUFFIX . '.css',
		array(),
		PODLOOM_PLUGIN_VERSION
	);

	// Enqueue settings page general script
	wp_enqueue_script(
		'podloom-settings-page',
		PODLOOM_PLUGIN_URL . 'admin/js/settings-page' . PODLOOM_SCRIPT_SUFFIX . '.js',
		array( 'jquery' ),
		PODLOOM_PLUGIN_VERSION,
		true
	);

	// Enqueue typography manager (for RSS tab)
	wp_enqueue_script(
		'podloom-typography-manager',
		PODLOOM_PLUGIN_URL . 'admin/js/typography-manager' . PODLOOM_SCRIPT_SUFFIX . '.js',
		array(),
		PODLOOM_PLUGIN_VERSION,
		true
	);

	// Enqueue RSS manager (for RSS tab)
	wp_enqueue_script(
		'podloom-rss-manager',
		PODLOOM_PLUGIN_URL . 'admin/js/rss-manager' . PODLOOM_SCRIPT_SUFFIX . '.js',
		array( 'jquery', 'podloom-typography-manager' ),
		PODLOOM_PLUGIN_VERSION,
		true
	);

	// Enqueue subscribe admin script (for Subscribe tab)
	wp_enqueue_script(
		'podloom-subscribe-admin',
		PODLOOM_PLUGIN_URL . 'admin/js/subscribe-admin' . PODLOOM_SCRIPT_SUFFIX . '.js',
		array(),
		PODLOOM_PLUGIN_VERSION,
		true
	);

	// Localize scripts with translatable strings and data
	wp_localize_script(
		'podloom-rss-manager',
		'podloomData',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'podloom_nonce' ),
			'strings' => array(
				// Feed management
				'addNewFeed'           => __( 'Add New RSS Feed', 'podloom-podcast-player' ),
				'editFeedName'         => __( 'Edit Feed Name', 'podloom-podcast-player' ),
				'feedName'             => __( 'Feed Name', 'podloom-podcast-player' ),
				'feedUrl'              => __( 'Feed URL', 'podloom-podcast-player' ),
				'feedNamePlaceholder'  => __( 'e.g., My Podcast', 'podloom-podcast-player' ),
				'feedUrlPlaceholder'   => __( 'https://example.com/feed.xml', 'podloom-podcast-player' ),
				'enterFeedName'        => __( 'Please enter a feed name.', 'podloom-podcast-player' ),
				'fillAllFields'        => __( 'Please fill in all fields.', 'podloom-podcast-player' ),

				// Actions
				'save'                 => __( 'Save', 'podloom-podcast-player' ),
				'cancel'               => __( 'Cancel', 'podloom-podcast-player' ),
				'adding'               => __( 'Adding...', 'podloom-podcast-player' ),
				'addingFeedProgress'   => __( 'Adding feed... This may take up to 10 seconds.', 'podloom-podcast-player' ),
				'saving'               => __( 'Saving...', 'podloom-podcast-player' ),
				'refreshing'           => __( 'Refreshing...', 'podloom-podcast-player' ),
				'saveRssSettings'      => __( 'Save RSS Settings', 'podloom-podcast-player' ),

				// Messages
				'errorAddingFeed'      => __( 'Error adding feed.', 'podloom-podcast-player' ),
				'errorUpdatingFeed'    => __( 'Error updating feed name.', 'podloom-podcast-player' ),
				'errorRefreshingFeed'  => __( 'Error refreshing feed', 'podloom-podcast-player' ),
				'errorLoadingFeed'     => __( 'Error loading feed', 'podloom-podcast-player' ),
				'errorSavingSettings'  => __( 'Error saving settings.', 'podloom-podcast-player' ),
				'settingsSavedSuccess' => __( 'RSS settings saved successfully!', 'podloom-podcast-player' ),
				'unknownError'         => __( 'Unknown error', 'podloom-podcast-player' ),
				'deleteFeedConfirm'    => __( 'Are you sure you want to delete this RSS feed? This action cannot be undone.', 'podloom-podcast-player' ),
				'rssFeedXml'           => __( 'RSS Feed XML', 'podloom-podcast-player' ),

				// Feed refresh status messages
				'feedUpToDate'         => __( 'Feed is up to date', 'podloom-podcast-player' ),
				'feedRefreshed'        => __( 'Feed refreshed', 'podloom-podcast-player' ),
				'episodes'             => __( 'episodes', 'podloom-podcast-player' ),
				'usingCachedData'      => __( 'using cached data', 'podloom-podcast-player' ),

				// Subscribe links strings
				'saveLinks'            => __( 'Save Links', 'podloom-podcast-player' ),
				'syncing'              => __( 'Syncing...', 'podloom-podcast-player' ),
				'synced'               => __( 'Synced!', 'podloom-podcast-player' ),
				'saved'                => __( 'Saved!', 'podloom-podcast-player' ),
			),
		)
	);

	// Initialize scripts based on current tab - must use nonce-verified tab value
	$allowed_tabs = array( 'welcome', 'general', 'transistor', 'rss', 'subscribe' );
	$current_tab  = 'welcome';

	// Use nonce verification for tab determination to prevent CSRF
	if ( isset( $_GET['tab'] ) && isset( $_GET['_wpnonce'] ) ) {
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'podloom_switch_tab' ) ) {
			$requested_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			// Whitelist validation to prevent arbitrary values
			if ( in_array( $requested_tab, $allowed_tabs, true ) ) {
				$current_tab = $requested_tab;
			}
		}
	}
	$init_script = '';

	if ( $current_tab === 'rss' ) {
		$init_script = '
            if (window.podloomTypographyManager) {
                window.podloomTypographyManager.init();
            }
            if (window.podloomRssManager) {
                window.podloomRssManager.init();
            }
        ';
	}

	if ( $init_script ) {
		wp_add_inline_script( 'podloom-rss-manager', 'document.addEventListener("DOMContentLoaded", function() {' . $init_script . '});' );
	}
}
add_action( 'admin_enqueue_scripts', 'podloom_enqueue_admin_scripts' );

/**
 * Handle plugin reset request before any output
 * This needs to run early to allow redirects
 */
function podloom_handle_plugin_reset() {
	// Only run on our settings page
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking page parameter for routing only, actual form processing has nonce verification
	if ( ! isset( $_GET['page'] ) || sanitize_text_field( wp_unslash( $_GET['page'] ) ) !== 'podloom-settings' ) {
		return;
	}

	// Handle plugin reset request
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
	if ( isset( $_POST['podloom_reset_plugin'] ) ) {
		check_admin_referer( 'podloom_reset_plugin', 'podloom_reset_plugin_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'podloom-podcast-player' ) );
		}

		$reset_confirmation = isset( $_POST['reset_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_confirmation'] ) ) : '';

		if ( $reset_confirmation === 'RESET' ) {
			// Delete all plugin options and cache
			podloom_delete_all_plugin_data();

			// Redirect to settings page to reload with clean state
			$redirect_url = add_query_arg(
				array(
					'page'  => 'podloom-settings',
					'reset' => 'success',
				),
				admin_url( 'options-general.php' )
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}
}
add_action( 'admin_init', 'podloom_handle_plugin_reset' );

/**
 * Render the main settings page
 *
 * Handles tab navigation, form submissions, and displays the appropriate settings template.
 *
 * @since 1.0.0
 * @return void
 */
function podloom_render_settings_page() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Get current tab with nonce verification and whitelist validation
	$allowed_tabs = array( 'welcome', 'general', 'transistor', 'rss', 'subscribe' );
	$current_tab  = 'welcome';

	if ( isset( $_GET['tab'] ) ) {
		// Require nonce verification for tab switching
		if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'podloom_switch_tab' ) ) {
			$requested_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
			// Whitelist validation to prevent arbitrary values
			if ( in_array( $requested_tab, $allowed_tabs, true ) ) {
				$current_tab = $requested_tab;
			}
		}
		// If tab parameter exists but nonce is invalid/missing, stay on default tab
	}

	// Success/error messages
	$success_message = '';
	$error_message   = '';

	// Handle clear cache request
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
	if ( isset( $_POST['podloom_clear_cache'] ) ) {
		check_admin_referer( 'podloom_clear_cache', 'podloom_clear_cache_nonce' );

		// Clear Transistor API cache
		podloom_clear_all_cache();

		// Clear RSS episode cache and force refresh all feeds (bypassing ETag/Last-Modified)
		Podloom_RSS::clear_all_caches();

		// Force refresh all RSS feeds to get fresh data
		$feeds           = Podloom_RSS::get_feeds();
		$refreshed_count = 0;
		foreach ( $feeds as $feed_id => $feed_data ) {
			// Use force=true to bypass conditional headers and fetch fresh content
			$result = Podloom_RSS::refresh_feed( $feed_id, true );
			if ( ! empty( $result['success'] ) ) {
				++$refreshed_count;
			}
		}

		$success_message = sprintf(
			/* translators: %d: number of feeds refreshed */
			esc_html__( 'Cache cleared and %d RSS feed(s) refreshed!', 'podloom-podcast-player' ),
			$refreshed_count
		);
	}

	// Handle delete cached images request
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
	if ( isset( $_POST['podloom_delete_cached_images'] ) ) {
		check_admin_referer( 'podloom_delete_cached_images', 'podloom_delete_cached_images_nonce' );

		$deleted_count   = Podloom_Image_Cache::delete_all_images();
		$success_message = sprintf(
			/* translators: %d: number of images deleted */
			esc_html__( '%d cached image(s) deleted from the media library.', 'podloom-podcast-player' ),
			$deleted_count
		);
	}

	// Handle plugin reset validation error (successful reset is handled in admin_init hook)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
	if ( isset( $_POST['podloom_reset_plugin'] ) ) {
		check_admin_referer( 'podloom_reset_plugin', 'podloom_reset_plugin_nonce' );

		$reset_confirmation = isset( $_POST['reset_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['reset_confirmation'] ) ) : '';

		if ( $reset_confirmation !== 'RESET' ) {
			$error_message = esc_html__( 'Reset failed: You must type RESET in the confirmation field.', 'podloom-podcast-player' );
		}
	}

	// Check for reset success message from redirect
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading success parameter from redirect, no form data being processed
	if ( isset( $_GET['reset'] ) && sanitize_text_field( wp_unslash( $_GET['reset'] ) ) === 'success' ) {
		$success_message = esc_html__( 'All PodLoom settings and cache have been deleted successfully. The plugin has been reset to default state.', 'podloom-podcast-player' );
	}

	// Handle form submission
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified on next line
	if ( isset( $_POST['podloom_settings_submit'] ) ) {
		check_admin_referer( 'podloom_settings_save', 'podloom_settings_nonce' );

		// Determine which tab submitted the form
		$settings_tab = isset( $_POST['podloom_settings_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['podloom_settings_tab'] ) ) : '';

		$api_key      = isset( $_POST['podloom_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['podloom_api_key'] ) ) : '';
		$default_show = isset( $_POST['podloom_default_show'] ) ? sanitize_text_field( wp_unslash( $_POST['podloom_default_show'] ) ) : '';

		// Handle checkbox - when checked it's '1', when unchecked the field is not submitted
		$enable_cache   = isset( $_POST['podloom_enable_cache'] ) && sanitize_text_field( wp_unslash( $_POST['podloom_enable_cache'] ) ) === '1';
		$cache_duration = isset( $_POST['podloom_cache_duration'] ) ? absint( wp_unslash( $_POST['podloom_cache_duration'] ) ) : 21600;
		$cache_images   = isset( $_POST['podloom_cache_images'] ) && sanitize_text_field( wp_unslash( $_POST['podloom_cache_images'] ) ) === '1';

		update_option( 'podloom_api_key', $api_key );
		update_option( 'podloom_default_show', $default_show );
		update_option( 'podloom_enable_cache', $enable_cache );
		update_option( 'podloom_cache_duration', $cache_duration );
		update_option( 'podloom_cache_images', $cache_images );

		// Note: RSS settings are saved via AJAX from the RSS tab (see podloom_ajax_save_all_rss_settings).
		// We intentionally do NOT save RSS settings here to avoid overwriting them when saving from other tabs.

		// Clear cache when settings change
		podloom_clear_all_cache();

		// Test if API connection is working
		$success_message = esc_html__( 'Settings saved successfully!', 'podloom-podcast-player' );
		if ( ! empty( $api_key ) ) {
			$test_api    = new Podloom_API( $api_key );
			$test_result = $test_api->get_shows();
			if ( ! is_wp_error( $test_result ) ) {
				$success_message .= ' ' . __( 'Successfully connected to Transistor API!', 'podloom-podcast-player' );

				// Schedule background sync of subscribe links to avoid blocking form submission.
				if ( ! empty( $test_result['data'] ) && class_exists( 'Podloom_Subscribe' ) ) {
					$show_ids = array();
					foreach ( $test_result['data'] as $show ) {
						if ( ! empty( $show['id'] ) ) {
							$show_ids[] = $show['id'];
						}
					}
					if ( ! empty( $show_ids ) ) {
						// Schedule immediate background job for subscribe link sync.
						if ( ! wp_next_scheduled( 'podloom_sync_subscribe_links', array( $show_ids ) ) ) {
							wp_schedule_single_event( time(), 'podloom_sync_subscribe_links', array( $show_ids ) );
						}
						$success_message .= ' ' . esc_html__( 'Subscribe links will sync in the background.', 'podloom-podcast-player' );
					}
				}
			}
		}
	}

	// Get current settings (optimized - single query for all autoload options)
	$all_options    = wp_load_alloptions();
	$api_key        = $all_options['podloom_api_key'] ?? '';
	$default_show   = $all_options['podloom_default_show'] ?? '';
	$enable_cache   = $all_options['podloom_enable_cache'] ?? true;
	$cache_duration = $all_options['podloom_cache_duration'] ?? 21600;

	// Test connection and get shows if API key is set
	$shows             = array();
	$connection_status = '';
	if ( ! empty( $api_key ) ) {
		$api          = new Podloom_API( $api_key );
		$shows_result = $api->get_shows();

		if ( is_wp_error( $shows_result ) ) {
			$connection_status = '<div class="notice notice-warning"><p>' .
								esc_html__( 'There is an error connecting to the Transistor API: ', 'podloom-podcast-player' ) .
								esc_html( $shows_result->get_error_message() ) .
								'</p></div>';
		} else {
			// Connection successful - only show shows, no success message
			$shows = isset( $shows_result['data'] ) ? $shows_result['data'] : array();
		}
	}

	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<?php if ( ! empty( $success_message ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html( $success_message ); ?></p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $error_message ) ) : ?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( $error_message ); ?></p>
			</div>
		<?php endif; ?>

		<!-- Tab Navigation -->
		<h2 class="nav-tab-wrapper">
			<a href="<?php echo esc_url( wp_nonce_url( '?page=podloom-settings&tab=welcome', 'podloom_switch_tab' ) ); ?>" class="nav-tab <?php echo $current_tab === 'welcome' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Welcome', 'podloom-podcast-player' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( '?page=podloom-settings&tab=transistor', 'podloom_switch_tab' ) ); ?>" class="nav-tab <?php echo $current_tab === 'transistor' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Transistor API', 'podloom-podcast-player' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( '?page=podloom-settings&tab=rss', 'podloom_switch_tab' ) ); ?>" class="nav-tab <?php echo $current_tab === 'rss' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'RSS Feeds', 'podloom-podcast-player' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( '?page=podloom-settings&tab=subscribe', 'podloom_switch_tab' ) ); ?>" class="nav-tab <?php echo $current_tab === 'subscribe' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Subscribe Links', 'podloom-podcast-player' ); ?>
			</a>
			<a href="<?php echo esc_url( wp_nonce_url( '?page=podloom-settings&tab=general', 'podloom_switch_tab' ) ); ?>" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'General Settings', 'podloom-podcast-player' ); ?>
			</a>
		</h2>

		<?php if ( $current_tab === 'welcome' ) : ?>
			<?php podloom_render_welcome_tab(); ?>

		<?php elseif ( $current_tab === 'general' ) : ?>
			<?php podloom_render_general_tab( $all_options, $shows ); ?>


		<?php elseif ( $current_tab === 'transistor' ) : ?>
			<?php podloom_render_transistor_tab( $api_key, $default_show, $enable_cache, $cache_duration, $connection_status, $shows ); ?>


		<?php elseif ( $current_tab === 'rss' ) : ?>
			<?php podloom_render_rss_tab( $all_options ); ?>


		<?php elseif ( $current_tab === 'subscribe' ) : ?>
			<?php podloom_render_subscribe_tab( $all_options ); ?>


		<?php endif; // End tab conditional ?>
	</div>
	<?php
}
