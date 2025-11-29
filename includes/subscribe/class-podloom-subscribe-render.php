<?php
/**
 * Subscribe Buttons Render Class
 *
 * Handles rendering of subscribe buttons on the frontend.
 *
 * @package PodLoom
 * @since 2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Subscribe_Render
 *
 * Renders subscribe buttons with various display options.
 */
class Podloom_Subscribe_Render {

	/**
	 * Render subscribe buttons for a podcast.
	 *
	 * @param string $source_id Source identifier (e.g., 'transistor:123' or 'rss:abc').
	 * @param array  $options   Display options.
	 * @return string HTML output.
	 */
	public static function render( $source_id, $options = array() ) {
		$defaults = array(
			'icon_size'         => 32,           // Icon size in pixels.
			'color_mode'        => 'brand',      // brand, mono, or hex color.
			'layout'            => 'horizontal', // horizontal, vertical, grid.
			'show_labels'       => false,
			'custom_color'      => '',
			'icon_gap'          => 12,           // Gap between icons in pixels.
			'label_font_size'   => 14,           // Label font size in pixels.
			'label_font_family' => 'inherit',    // Label font family.
		);

		$options = wp_parse_args( $options, $defaults );

		// Get active links for this podcast.
		$links = Podloom_Subscribe::get_active_links( $source_id );

		if ( empty( $links ) ) {
			return '';
		}

		// Determine color mode.
		$color_mode = $options['color_mode'];
		if ( 'custom' === $color_mode && ! empty( $options['custom_color'] ) ) {
			$color_mode = sanitize_hex_color( $options['custom_color'] );
		}

		// Build inline styles for container.
		$container_styles = array(
			'display: flex',
			'flex-wrap: wrap',
			'gap: ' . intval( $options['icon_gap'] ) . 'px',
			'align-items: center',
		);

		if ( 'vertical' === $options['layout'] ) {
			$container_styles[] = 'flex-direction: column';
			$container_styles[] = 'justify-content: center';
		} elseif ( 'grid' === $options['layout'] ) {
			$container_styles[] = 'justify-content: center';
		} else {
			$container_styles[] = 'justify-content: flex-start';
		}

		// Build CSS classes.
		$container_classes = array(
			'podloom-subscribe-buttons',
			'podloom-subscribe-buttons--' . esc_attr( $options['layout'] ),
		);

		if ( $options['show_labels'] ) {
			$container_classes[] = 'podloom-subscribe-buttons--with-labels';
		}

		$output = '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '" style="' . esc_attr( implode( '; ', $container_styles ) ) . '">';

		$platforms = Podloom_Subscribe_Icons::get_platforms();

		// Icon styles.
		$icon_size   = intval( $options['icon_size'] );
		$icon_styles = 'width: ' . $icon_size . 'px; height: ' . $icon_size . 'px; display: flex; align-items: center; justify-content: center;';

		// Label styles.
		$label_styles = '';
		if ( $options['show_labels'] ) {
			$label_styles = 'font-size: ' . intval( $options['label_font_size'] ) . 'px;';
			if ( ! empty( $options['label_font_family'] ) && 'inherit' !== $options['label_font_family'] ) {
				$label_styles .= ' font-family: ' . esc_attr( $options['label_font_family'] ) . ';';
			}
		}

		foreach ( $links as $platform_key => $url ) {
			if ( empty( $url ) || ! isset( $platforms[ $platform_key ] ) ) {
				continue;
			}

			$platform = $platforms[ $platform_key ];
			$svg      = Podloom_Subscribe_Icons::get_svg( $platform_key, $color_mode );

			if ( empty( $svg ) ) {
				continue;
			}

			$button_classes = array(
				'podloom-subscribe-btn',
				'podloom-subscribe-btn--' . esc_attr( $platform_key ),
			);

			$output .= sprintf(
				'<a href="%s" class="%s" target="_blank" rel="noopener noreferrer" title="%s" style="display: flex; align-items: center; gap: 8px; text-decoration: none; color: inherit;">',
				esc_url( $url ),
				esc_attr( implode( ' ', $button_classes ) ),
				esc_attr( sprintf( __( 'Subscribe on %s', 'podloom-podcast-player' ), $platform['name'] ) )
			);

			$output .= '<span class="podloom-subscribe-btn__icon" style="' . esc_attr( $icon_styles ) . '">' . $svg . '</span>';

			if ( $options['show_labels'] ) {
				$output .= '<span class="podloom-subscribe-btn__label" style="' . esc_attr( $label_styles ) . '">' . esc_html( $platform['name'] ) . '</span>';
			}

			$output .= '</a>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render subscribe buttons block (Gutenberg callback).
	 *
	 * @param array $attributes Block attributes.
	 * @return string HTML output.
	 */
	public static function render_block( $attributes ) {
		$source_id = isset( $attributes['source'] ) ? sanitize_text_field( $attributes['source'] ) : '';

		if ( empty( $source_id ) ) {
			// Check if we're in the editor.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div class="podloom-subscribe-placeholder">' .
					   '<p>' . esc_html__( 'Select a podcast to display subscribe buttons.', 'podloom-podcast-player' ) . '</p>' .
					   '</div>';
			}
			return '';
		}

		$options = array(
			'icon_size'         => isset( $attributes['iconSize'] ) ? intval( $attributes['iconSize'] ) : 32,
			'color_mode'        => isset( $attributes['colorMode'] ) ? sanitize_key( $attributes['colorMode'] ) : 'brand',
			'layout'            => isset( $attributes['layout'] ) ? sanitize_key( $attributes['layout'] ) : 'horizontal',
			'show_labels'       => isset( $attributes['showLabels'] ) ? (bool) $attributes['showLabels'] : false,
			'custom_color'      => isset( $attributes['customColor'] ) ? sanitize_hex_color( $attributes['customColor'] ) : '',
			'icon_gap'          => isset( $attributes['iconGap'] ) ? intval( $attributes['iconGap'] ) : 12,
			'label_font_size'   => isset( $attributes['labelFontSize'] ) ? intval( $attributes['labelFontSize'] ) : 14,
			'label_font_family' => isset( $attributes['labelFontFamily'] ) ? sanitize_text_field( $attributes['labelFontFamily'] ) : 'inherit',
		);

		$output = self::render( $source_id, $options );

		if ( empty( $output ) ) {
			// Check if we're in the editor.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div class="podloom-subscribe-placeholder">' .
					   '<p>' . esc_html__( 'No subscribe links configured for this podcast.', 'podloom-podcast-player' ) . '</p>' .
					   '</div>';
			}
			return '';
		}

		// Enqueue frontend styles.
		wp_enqueue_style( 'podloom-subscribe-buttons' );

		return '<div class="wp-block-podloom-subscribe-buttons">' . $output . '</div>';
	}

	/**
	 * Get preview data for the block editor.
	 *
	 * @param string $source_id Source identifier.
	 * @return array Preview data.
	 */
	public static function get_preview_data( $source_id ) {
		$links     = Podloom_Subscribe::get_active_links( $source_id );
		$platforms = Podloom_Subscribe_Icons::get_platforms();

		$preview = array();
		foreach ( $links as $platform_key => $url ) {
			if ( isset( $platforms[ $platform_key ] ) ) {
				$preview[] = array(
					'key'   => $platform_key,
					'name'  => $platforms[ $platform_key ]['name'],
					'color' => $platforms[ $platform_key ]['color'],
					'url'   => $url,
				);
			}
		}

		return $preview;
	}
}
