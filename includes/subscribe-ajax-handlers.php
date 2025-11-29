<?php
/**
 * Subscribe Links AJAX Handlers
 *
 * Handles AJAX requests for saving and syncing subscribe links.
 *
 * @package PodLoom
 * @since 2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save subscribe links for a podcast.
 */
function podloom_ajax_save_subscribe_links() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'podloom_nonce' ) ) {
		wp_send_json_error( __( 'Security check failed.', 'podloom-podcast-player' ) );
	}

	// Check capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'podloom-podcast-player' ) );
	}

	// Validate source ID.
	if ( ! isset( $_POST['source_id'] ) || empty( $_POST['source_id'] ) ) {
		wp_send_json_error( __( 'Invalid podcast.', 'podloom-podcast-player' ) );
	}

	$source_id = sanitize_text_field( wp_unslash( $_POST['source_id'] ) );

	// Parse links JSON.
	$links_json = isset( $_POST['links'] ) ? sanitize_text_field( wp_unslash( $_POST['links'] ) ) : '{}';
	$links      = json_decode( $links_json, true );

	if ( ! is_array( $links ) ) {
		$links = array();
	}

	// Save links.
	$result = Podloom_Subscribe::save_links( $source_id, $links );

	if ( $result ) {
		wp_send_json_success( array( 'message' => __( 'Links saved.', 'podloom-podcast-player' ) ) );
	} else {
		wp_send_json_error( __( 'Error saving links.', 'podloom-podcast-player' ) );
	}
}
add_action( 'wp_ajax_podloom_save_subscribe_links', 'podloom_ajax_save_subscribe_links' );

/**
 * Sync subscribe links from Transistor API.
 */
function podloom_ajax_sync_transistor_links() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'podloom_nonce' ) ) {
		wp_send_json_error( __( 'Security check failed.', 'podloom-podcast-player' ) );
	}

	// Check capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'podloom-podcast-player' ) );
	}

	// Validate show ID.
	if ( ! isset( $_POST['show_id'] ) || empty( $_POST['show_id'] ) ) {
		wp_send_json_error( __( 'Invalid show ID.', 'podloom-podcast-player' ) );
	}

	$show_id = sanitize_text_field( wp_unslash( $_POST['show_id'] ) );

	// Sync links from Transistor.
	$result = Podloom_Subscribe::sync_transistor_links( $show_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( array( 'links' => $result ) );
}
add_action( 'wp_ajax_podloom_sync_transistor_links', 'podloom_ajax_sync_transistor_links' );

/**
 * Get all podcasts for subscribe block dropdown.
 */
function podloom_ajax_get_subscribe_podcasts() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'podloom_nonce' ) ) {
		wp_send_json_error( __( 'Security check failed.', 'podloom-podcast-player' ) );
	}

	// Get all podcasts.
	$podcasts = Podloom_Subscribe::get_all_podcasts();

	// Simplify the data for the block.
	$simplified = array();
	foreach ( $podcasts as $podcast ) {
		$simplified[] = array(
			'source_id' => $podcast['source_id'],
			'name'      => $podcast['name'],
			'type'      => $podcast['type'],
		);
	}

	wp_send_json_success( array( 'podcasts' => $simplified ) );
}
add_action( 'wp_ajax_podloom_get_subscribe_podcasts', 'podloom_ajax_get_subscribe_podcasts' );

/**
 * Get subscribe preview data for a podcast.
 */
function podloom_ajax_get_subscribe_preview() {
	// Verify nonce.
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'podloom_nonce' ) ) {
		wp_send_json_error( __( 'Security check failed.', 'podloom-podcast-player' ) );
	}

	// Validate source ID.
	if ( ! isset( $_POST['source_id'] ) || empty( $_POST['source_id'] ) ) {
		wp_send_json_error( __( 'Invalid podcast.', 'podloom-podcast-player' ) );
	}

	$source_id = sanitize_text_field( wp_unslash( $_POST['source_id'] ) );

	// Get color mode from request (defaults to 'brand').
	$color_mode   = isset( $_POST['color_mode'] ) ? sanitize_key( $_POST['color_mode'] ) : 'brand';
	$custom_color = isset( $_POST['custom_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['custom_color'] ) ) : '';

	// Determine actual color mode for SVG generation.
	if ( 'custom' === $color_mode && ! empty( $custom_color ) ) {
		$svg_color_mode = $custom_color;
	} else {
		$svg_color_mode = $color_mode;
	}

	// Get preview data.
	$preview = Podloom_Subscribe_Render::get_preview_data( $source_id );

	// Add SVG data to preview with correct color mode.
	foreach ( $preview as &$item ) {
		$item['svg'] = Podloom_Subscribe_Icons::get_svg( $item['key'], $svg_color_mode );
	}

	wp_send_json_success( array( 'links' => $preview ) );
}
add_action( 'wp_ajax_podloom_get_subscribe_preview', 'podloom_ajax_get_subscribe_preview' );
