<?php
/**
 * Color Utility Functions
 *
 * Functions for color manipulation, theme-aware color calculation,
 * and hex/RGB conversions.
 *
 * @package PodLoom
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calculate theme-aware colors for tabs and P2.0 elements
 *
 * Uses static caching to avoid recalculating colors for the same background
 * color within a single request.
 *
 * @param string $bg_color Background color in hex format
 * @return array Array of calculated colors
 */
function podloom_calculate_theme_colors( $bg_color ) {
	static $cache = array();

	// Validate and sanitize input color.
	$bg_color = sanitize_hex_color( $bg_color );
	if ( empty( $bg_color ) ) {
		$bg_color = '#f9f9f9'; // Default fallback.
	}

	// Return cached result if available
	if ( isset( $cache[ $bg_color ] ) ) {
		return $cache[ $bg_color ];
	}

	// Convert hex to RGB
	$bg_rgb = podloom_hex_to_rgb( $bg_color );

	// Calculate luminance to determine if background is dark or light
	$luminance = podloom_get_luminance( $bg_rgb );
	$is_dark   = $luminance < 0.5;

	// Generate colors based on background
	if ( $is_dark ) {
		// Dark theme colors
		$colors = array(
			// Tab colors
			'tab_text'          => podloom_lighten_color( $bg_color, 50 ),
			'tab_text_hover'    => podloom_lighten_color( $bg_color, 70 ),
			'tab_bg_hover'      => podloom_lighten_color( $bg_color, 12 ),
			'tab_active_text'   => podloom_lighten_color( $bg_color, 90 ),
			'tab_active_bg'     => podloom_lighten_color( $bg_color, 25 ),
			'tab_border'        => podloom_lighten_color( $bg_color, 20 ),
			// Keep tab_active for backwards compatibility
			'tab_active'        => podloom_lighten_color( $bg_color, 90 ),
			// Content area colors
			'content_bg'        => podloom_lighten_color( $bg_color, 10 ),
			'content_border'    => podloom_lighten_color( $bg_color, 20 ),
			'content_bg_active' => podloom_lighten_color( $bg_color, 20 ),
			// Accent colors (for buttons, timestamps)
			'accent'            => podloom_lighten_color( $bg_color, 60 ),
			'accent_hover'      => podloom_lighten_color( $bg_color, 70 ),
			'accent_text'       => '#000000',
			// Card colors (for person cards, chapter items, transcript viewer)
			'card_bg'           => podloom_lighten_color( $bg_color, 8 ),
			'card_bg_hover'     => podloom_lighten_color( $bg_color, 12 ),
			'card_border'       => podloom_lighten_color( $bg_color, 15 ),
			// Text colors
			'text_primary'      => podloom_lighten_color( $bg_color, 85 ),
			'text_secondary'    => podloom_lighten_color( $bg_color, 60 ),
			'text_muted'        => podloom_lighten_color( $bg_color, 45 ),
			// Button colors (secondary style for transcript buttons)
			'button_bg'         => podloom_lighten_color( $bg_color, 15 ),
			'button_bg_hover'   => podloom_lighten_color( $bg_color, 20 ),
			'button_border'     => podloom_lighten_color( $bg_color, 30 ),
			'button_text'       => podloom_lighten_color( $bg_color, 70 ),
			// Avatar/placeholder colors
			'avatar_bg'         => podloom_lighten_color( $bg_color, 20 ),
			'avatar_text'       => podloom_lighten_color( $bg_color, 60 ),
			// Warning/error colors (softer on dark backgrounds)
			'warning_bg'        => podloom_lighten_color( $bg_color, 12 ),
			'warning_border'    => podloom_lighten_color( $bg_color, 25 ),
			'warning_text'      => podloom_lighten_color( $bg_color, 70 ),
			// Audio player colors
			'player_btn_bg'           => podloom_lighten_color( $bg_color, 60 ),
			'player_btn_icon'         => podloom_lighten_color( $bg_color, 95 ),
			'player_btn'              => podloom_lighten_color( $bg_color, 60 ), // Legacy, same as btn_bg
			'player_timeline'         => podloom_lighten_color( $bg_color, 25 ),
			'player_progress'         => podloom_lighten_color( $bg_color, 60 ),
			'player_control'          => podloom_lighten_color( $bg_color, 50 ),
			'player_control_hover_bg' => 'rgba(255, 255, 255, 0.1)',
			'player_time'             => podloom_lighten_color( $bg_color, 45 ),
			'player_speed_bg'         => podloom_lighten_color( $bg_color, 15 ),
			'player_speed_border'     => podloom_lighten_color( $bg_color, 30 ),
			'player_speed_hover_bg'   => podloom_lighten_color( $bg_color, 20 ),
			'player_speed_hover_border' => podloom_lighten_color( $bg_color, 40 ),
			'player_speed_active_bg'  => podloom_lighten_color( $bg_color, 25 ),
			'player_text'             => podloom_lighten_color( $bg_color, 70 ),
		);
	} else {
		// Light theme colors
		$colors = array(
			// Tab colors
			'tab_text'          => podloom_darken_color( $bg_color, 40 ),
			'tab_text_hover'    => podloom_darken_color( $bg_color, 60 ),
			'tab_bg_hover'      => podloom_darken_color( $bg_color, 5 ),
			'tab_active_text'   => podloom_darken_color( $bg_color, 80 ),
			'tab_active_bg'     => podloom_darken_color( $bg_color, 12 ),
			'tab_border'        => podloom_darken_color( $bg_color, 10 ),
			// Keep tab_active for backwards compatibility
			'tab_active'        => podloom_darken_color( $bg_color, 80 ),
			// Content area colors
			'content_bg'        => podloom_darken_color( $bg_color, 3 ),
			'content_border'    => podloom_darken_color( $bg_color, 10 ),
			'content_bg_active' => podloom_darken_color( $bg_color, 8 ),
			// Accent colors (for buttons, timestamps)
			'accent'            => podloom_darken_color( $bg_color, 60 ),
			'accent_hover'      => podloom_darken_color( $bg_color, 70 ),
			'accent_text'       => '#ffffff',
			// Card colors (for person cards, chapter items, transcript viewer)
			'card_bg'           => '#ffffff',
			'card_bg_hover'     => podloom_darken_color( $bg_color, 2 ),
			'card_border'       => podloom_darken_color( $bg_color, 8 ),
			// Text colors
			'text_primary'      => podloom_darken_color( $bg_color, 80 ),
			'text_secondary'    => podloom_darken_color( $bg_color, 60 ),
			'text_muted'        => podloom_darken_color( $bg_color, 40 ),
			// Button colors (secondary style for transcript buttons)
			'button_bg'         => '#ffffff',
			'button_bg_hover'   => podloom_darken_color( $bg_color, 3 ),
			'button_border'     => podloom_darken_color( $bg_color, 20 ),
			'button_text'       => podloom_darken_color( $bg_color, 60 ),
			// Avatar/placeholder colors
			'avatar_bg'         => podloom_darken_color( $bg_color, 10 ),
			'avatar_text'       => podloom_darken_color( $bg_color, 50 ),
			// Warning/error colors
			'warning_bg'        => '#fff3cd',
			'warning_border'    => '#ffc107',
			'warning_text'      => '#856404',
			// Audio player colors
			'player_btn_bg'           => podloom_darken_color( $bg_color, 75 ),
			'player_btn_icon'         => '#ffffff',
			'player_btn'              => podloom_darken_color( $bg_color, 75 ), // Legacy, same as btn_bg
			'player_timeline'         => podloom_darken_color( $bg_color, 10 ),
			'player_progress'         => podloom_darken_color( $bg_color, 75 ),
			'player_control'          => podloom_darken_color( $bg_color, 50 ),
			'player_control_hover_bg' => 'rgba(0, 0, 0, 0.05)',
			'player_time'             => podloom_darken_color( $bg_color, 40 ),
			'player_speed_bg'         => '#ffffff',
			'player_speed_border'     => podloom_darken_color( $bg_color, 20 ),
			'player_speed_hover_bg'   => podloom_darken_color( $bg_color, 2 ),
			'player_speed_hover_border' => podloom_darken_color( $bg_color, 35 ),
			'player_speed_active_bg'  => podloom_darken_color( $bg_color, 5 ),
			'player_text'             => podloom_darken_color( $bg_color, 70 ),
		);
	}

	// Cache and return
	$cache[ $bg_color ] = $colors;
	return $colors;
}

/**
 * Convert hex color to RGB array
 *
 * @param string $hex Hex color code
 * @return array RGB values
 */
function podloom_hex_to_rgb( $hex ) {
	$hex = ltrim( $hex, '#' );
	return array(
		'r' => hexdec( substr( $hex, 0, 2 ) ),
		'g' => hexdec( substr( $hex, 2, 2 ) ),
		'b' => hexdec( substr( $hex, 4, 2 ) ),
	);
}

/**
 * Calculate relative luminance of a color
 *
 * @param array $rgb RGB color array
 * @return float Luminance value (0-1)
 */
function podloom_get_luminance( $rgb ) {
	// Validate input array.
	if ( ! is_array( $rgb ) || ! isset( $rgb['r'], $rgb['g'], $rgb['b'] ) ) {
		return 0.5; // Neutral luminance fallback.
	}

	$r = $rgb['r'] / 255;
	$g = $rgb['g'] / 255;
	$b = $rgb['b'] / 255;

	return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

/**
 * Lighten a hex color by percentage
 *
 * @param string $hex Hex color code
 * @param int    $percent Percentage to lighten (0-100)
 * @return string Lightened hex color
 */
function podloom_lighten_color( $hex, $percent ) {
	$rgb = podloom_hex_to_rgb( $hex );

	$r = (int) min( 255, $rgb['r'] + ( 255 - $rgb['r'] ) * ( $percent / 100 ) );
	$g = (int) min( 255, $rgb['g'] + ( 255 - $rgb['g'] ) * ( $percent / 100 ) );
	$b = (int) min( 255, $rgb['b'] + ( 255 - $rgb['b'] ) * ( $percent / 100 ) );

	return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

/**
 * Darken a hex color by percentage
 *
 * @param string $hex Hex color code
 * @param int    $percent Percentage to darken (0-100)
 * @return string Darkened hex color
 */
function podloom_darken_color( $hex, $percent ) {
	$rgb = podloom_hex_to_rgb( $hex );

	$r = (int) max( 0, $rgb['r'] - ( $rgb['r'] * ( $percent / 100 ) ) );
	$g = (int) max( 0, $rgb['g'] - ( $rgb['g'] * ( $percent / 100 ) ) );
	$b = (int) max( 0, $rgb['b'] - ( $rgb['b'] * ( $percent / 100 ) ) );

	return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

/**
 * Adjust color brightness (positive to lighten, negative to darken)
 *
 * @param string $hex Hex color code
 * @param int    $amount Amount to adjust (-100 to 100)
 * @return string Adjusted hex color
 */
function podloom_adjust_color_brightness( $hex, $amount ) {
	if ( $amount >= 0 ) {
		return podloom_lighten_color( $hex, $amount );
	} else {
		return podloom_darken_color( $hex, abs( $amount ) );
	}
}
