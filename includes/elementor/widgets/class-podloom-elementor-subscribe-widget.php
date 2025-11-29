<?php
/**
 * PodLoom Elementor Subscribe Widget
 *
 * Elementor widget for displaying podcast subscribe buttons.
 *
 * @package PodLoom
 * @since 2.10.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Elementor_Subscribe_Widget
 *
 * Elementor widget for subscribe buttons.
 */
class Podloom_Elementor_Subscribe_Widget extends \Elementor\Widget_Base {

	/**
	 * Cached podcast options.
	 *
	 * @var array|null
	 */
	private $cached_podcast_options = null;

	/**
	 * Get widget name
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'podloom-subscribe';
	}

	/**
	 * Get widget title
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'PodLoom Subscribe Buttons', 'podloom-podcast-player' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-share';
	}

	/**
	 * Get widget categories
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return array( 'podloom', 'general' );
	}

	/**
	 * Get widget keywords
	 *
	 * @return array Widget keywords.
	 */
	public function get_keywords() {
		return array( 'podcast', 'subscribe', 'buttons', 'apple', 'spotify', 'podloom' );
	}

	/**
	 * Get style depends
	 *
	 * @return array Style dependencies.
	 */
	public function get_style_depends() {
		return array( 'podloom-subscribe-buttons' );
	}

	/**
	 * Get custom help URL
	 *
	 * @return string Help URL.
	 */
	public function get_custom_help_url() {
		return 'https://thewpminute.com/podloom/';
	}

	/**
	 * Get podcast options for the select control
	 *
	 * @return array Podcast options.
	 */
	private function get_podcast_options() {
		if ( null !== $this->cached_podcast_options ) {
			return $this->cached_podcast_options;
		}

		$options = array(
			'' => esc_html__( '-- Select a podcast --', 'podloom-podcast-player' ),
		);

		// Get all podcasts.
		$podcasts = Podloom_Subscribe::get_all_podcasts();

		foreach ( $podcasts as $podcast ) {
			$prefix = 'transistor' === $podcast['type'] ? '[Transistor] ' : '[RSS] ';
			$options[ $podcast['source_id'] ] = $prefix . $podcast['name'];
		}

		$this->cached_podcast_options = $options;
		return $options;
	}

	/**
	 * Register widget controls
	 */
	protected function register_controls() {
		// Content Section - Podcast Selection.
		$this->start_controls_section(
			'section_source',
			array(
				'label' => esc_html__( 'Podcast', 'podloom-podcast-player' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'source',
			array(
				'label'       => esc_html__( 'Select Podcast', 'podloom-podcast-player' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->get_podcast_options(),
				'description' => esc_html__( 'Choose which podcast to display subscribe buttons for.', 'podloom-podcast-player' ),
			)
		);

		$this->end_controls_section();

		// Style Section - Display Options.
		$this->start_controls_section(
			'section_style',
			array(
				'label' => esc_html__( 'Display Options', 'podloom-podcast-player' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'icon_size',
			array(
				'label'   => esc_html__( 'Icon Size', 'podloom-podcast-player' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'medium',
				'options' => array(
					'small'  => esc_html__( 'Small (24px)', 'podloom-podcast-player' ),
					'medium' => esc_html__( 'Medium (32px)', 'podloom-podcast-player' ),
					'large'  => esc_html__( 'Large (48px)', 'podloom-podcast-player' ),
				),
			)
		);

		$this->add_control(
			'color_mode',
			array(
				'label'   => esc_html__( 'Color Mode', 'podloom-podcast-player' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'brand',
				'options' => array(
					'brand'  => esc_html__( 'Brand Colors', 'podloom-podcast-player' ),
					'mono'   => esc_html__( 'Monochrome', 'podloom-podcast-player' ),
					'custom' => esc_html__( 'Custom Color', 'podloom-podcast-player' ),
				),
			)
		);

		$this->add_control(
			'custom_color',
			array(
				'label'     => esc_html__( 'Custom Color', 'podloom-podcast-player' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#000000',
				'condition' => array(
					'color_mode' => 'custom',
				),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Layout', 'podloom-podcast-player' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'horizontal',
				'options' => array(
					'horizontal' => esc_html__( 'Horizontal', 'podloom-podcast-player' ),
					'vertical'   => esc_html__( 'Vertical', 'podloom-podcast-player' ),
					'grid'       => esc_html__( 'Grid', 'podloom-podcast-player' ),
				),
			)
		);

		$this->add_control(
			'show_labels',
			array(
				'label'        => esc_html__( 'Show Labels', 'podloom-podcast-player' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Yes', 'podloom-podcast-player' ),
				'label_off'    => esc_html__( 'No', 'podloom-podcast-player' ),
				'return_value' => 'yes',
				'default'      => '',
				'description'  => esc_html__( 'Display platform names next to icons.', 'podloom-podcast-player' ),
			)
		);

		$this->add_responsive_control(
			'alignment',
			array(
				'label'     => esc_html__( 'Alignment', 'podloom-podcast-player' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => esc_html__( 'Left', 'podloom-podcast-player' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => esc_html__( 'Center', 'podloom-podcast-player' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Right', 'podloom-podcast-player' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'default'   => 'flex-start',
				'selectors' => array(
					'{{WRAPPER}} .podloom-subscribe-buttons' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget output on the frontend.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$source_id = ! empty( $settings['source'] ) ? sanitize_text_field( $settings['source'] ) : '';

		if ( empty( $source_id ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="podloom-elementor-placeholder">';
				echo '<p>' . esc_html__( 'Select a podcast to display subscribe buttons.', 'podloom-podcast-player' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		$options = array(
			'icon_size'    => ! empty( $settings['icon_size'] ) ? sanitize_key( $settings['icon_size'] ) : 'medium',
			'color_mode'   => ! empty( $settings['color_mode'] ) ? sanitize_key( $settings['color_mode'] ) : 'brand',
			'layout'       => ! empty( $settings['layout'] ) ? sanitize_key( $settings['layout'] ) : 'horizontal',
			'show_labels'  => 'yes' === $settings['show_labels'],
			'custom_color' => ! empty( $settings['custom_color'] ) ? sanitize_hex_color( $settings['custom_color'] ) : '',
		);

		$output = Podloom_Subscribe_Render::render( $source_id, $options );

		if ( empty( $output ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="podloom-elementor-placeholder">';
				echo '<p>' . esc_html__( 'No subscribe links configured for this podcast.', 'podloom-podcast-player' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		echo '<div class="podloom-subscribe-widget">' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is already escaped in render method.
	}

	/**
	 * Render widget output in the editor.
	 *
	 * Note: Subscribe buttons require server-side rendering because the SVG icons
	 * and link data are stored in PHP. The content_template only shows a placeholder
	 * message when no podcast is selected.
	 */
	protected function content_template() {
		?>
		<#
		if ( ! settings.source ) {
			#>
			<div class="podloom-elementor-placeholder">
				<p><?php esc_html_e( 'Select a podcast to display subscribe buttons.', 'podloom-podcast-player' ); ?></p>
			</div>
			<#
		} else {
			#>
			<div class="podloom-elementor-placeholder podloom-elementor-placeholder--loading">
				<p><?php esc_html_e( 'Loading subscribe buttons...', 'podloom-podcast-player' ); ?></p>
			</div>
			<#
		}
		#>
		<?php
	}
}
