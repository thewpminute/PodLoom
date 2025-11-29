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
 * @param string $bg_color Background color in hex format
 * @return array Array of calculated colors
 */
function podloom_calculate_theme_colors( $bg_color ) {
	// Convert hex to RGB
	$bg_rgb = podloom_hex_to_rgb( $bg_color );

	// Calculate luminance to determine if background is dark or light
	$luminance = podloom_get_luminance( $bg_rgb );
	$is_dark   = $luminance < 0.5;

	// Generate colors based on background
	if ( $is_dark ) {
		// Dark theme colors
		return array(
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
		);
	} else {
		// Light theme colors
		return array(
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
		);
	}
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

	$r = min( 255, $rgb['r'] + ( 255 - $rgb['r'] ) * ( $percent / 100 ) );
	$g = min( 255, $rgb['g'] + ( 255 - $rgb['g'] ) * ( $percent / 100 ) );
	$b = min( 255, $rgb['b'] + ( 255 - $rgb['b'] ) * ( $percent / 100 ) );

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

	$r = max( 0, $rgb['r'] - ( $rgb['r'] * ( $percent / 100 ) ) );
	$g = max( 0, $rgb['g'] - ( $rgb['g'] * ( $percent / 100 ) ) );
	$b = max( 0, $rgb['b'] - ( $rgb['b'] * ( $percent / 100 ) ) );

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
