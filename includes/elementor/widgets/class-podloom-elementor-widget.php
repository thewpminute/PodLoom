<?php
/**
 * PodLoom Elementor Episode Widget
 *
 * Elementor widget for embedding podcast episodes from Transistor.fm or RSS feeds.
 *
 * @package PodLoom
 * @since 2.8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_Elementor_Widget
 *
 * Elementor widget for podcast episodes.
 */
class Podloom_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Cached source options.
	 *
	 * @var array|null
	 */
	private $cached_source_options = null;

	/**
	 * Cached Transistor conditions.
	 *
	 * @var array|null
	 */
	private $cached_transistor_conditions = null;

	/**
	 * Cached shows data.
	 *
	 * @var array|null
	 */
	private $cached_shows = null;

	/**
	 * Get widget name
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'podloom-episode';
	}

	/**
	 * Get widget title
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return esc_html__( 'PodLoom Episode', 'podloom-podcast-player' );
	}

	/**
	 * Get widget icon
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-play';
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
		return array( 'podcast', 'episode', 'audio', 'player', 'transistor', 'rss', 'podloom' );
	}

	/**
	 * Get script depends
	 *
	 * @return array Script dependencies.
	 */
	public function get_script_depends() {
		return array( 'podloom-podcast20-player' );
	}

	/**
	 * Get style depends
	 *
	 * @return array Style dependencies.
	 */
	public function get_style_depends() {
		return array( 'podloom-rss-player', 'podloom-podcast20' );
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
	 * Get Transistor shows with caching.
	 *
	 * @return array Shows data.
	 */
	private function get_cached_shows() {
		if ( null !== $this->cached_shows ) {
			return $this->cached_shows;
		}

		$api_key = get_option( 'podloom_api_key' );
		if ( empty( $api_key ) ) {
			$this->cached_shows = array();
			return $this->cached_shows;
		}

		$shows = podloom_get_shows();
		$this->cached_shows = ! empty( $shows['data'] ) ? $shows['data'] : array();
		return $this->cached_shows;
	}

	/**
	 * Get source options for the select control
	 *
	 * @return array Source options.
	 */
	private function get_source_options() {
		if ( null !== $this->cached_source_options ) {
			return $this->cached_source_options;
		}

		$options = array(
			'' => esc_html__( '-- Select a source --', 'podloom-podcast-player' ),
		);

		// Get Transistor shows (cached).
		$shows = $this->get_cached_shows();
		foreach ( $shows as $show ) {
			if ( isset( $show['id'], $show['attributes']['title'] ) ) {
				$options[ 'transistor:' . $show['id'] ] = '[Transistor] ' . $show['attributes']['title'];
			}
		}

		// Get RSS feeds.
		$rss_feeds = Podloom_RSS::get_feeds();
		if ( ! empty( $rss_feeds ) && is_array( $rss_feeds ) ) {
			foreach ( $rss_feeds as $feed_id => $feed ) {
				if ( ! empty( $feed['valid'] ) && isset( $feed['name'] ) ) {
					$options[ 'rss:' . $feed_id ] = '[RSS] ' . $feed['name'];
				}
			}
		}

		$this->cached_source_options = $options;
		return $options;
	}

	/**
	 * Register widget controls
	 */
	protected function register_controls() {
		// Content Section - Source Selection.
		$this->start_controls_section(
			'section_source',
			array(
				'label' => esc_html__( 'Source', 'podloom-podcast-player' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'source',
			array(
				'label'       => esc_html__( 'Podcast Source', 'podloom-podcast-player' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->get_source_options(),
				'description' => esc_html__( 'Select a Transistor show or RSS feed.', 'podloom-podcast-player' ),
			)
		);

		$this->add_control(
			'display_mode',
			array(
				'label'     => esc_html__( 'Display Mode', 'podloom-podcast-player' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'specific',
				'options'   => array(
					'specific' => esc_html__( 'Specific Episode', 'podloom-podcast-player' ),
					'latest'   => esc_html__( 'Latest Episode', 'podloom-podcast-player' ),
					'playlist' => esc_html__( 'Playlist', 'podloom-podcast-player' ),
				),
				'condition' => array(
					'source!' => '',
				),
			)
		);

		// Episode selector - populated via JavaScript with search capability.
		$this->add_control(
			'episode_id',
			array(
				'label'       => esc_html__( 'Episode', 'podloom-podcast-player' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'default'     => '',
				'options'     => array(
					'' => esc_html__( '-- Select an episode --', 'podloom-podcast-player' ),
				),
				'label_block' => true,
				'description' => esc_html__( 'Type to search or scroll to browse episodes.', 'podloom-podcast-player' ),
				'condition'   => array(
					'source!'      => '',
					'display_mode' => 'specific',
				),
			)
		);

		// Hidden field to store episode data (for RSS).
		$this->add_control(
			'episode_data',
			array(
				'label'   => '',
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => '',
			)
		);

		// Hidden field to store embed HTML (for Transistor).
		$this->add_control(
			'embed_html',
			array(
				'label'   => '',
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => '',
			)
		);

		// Hidden field to store show slug (for Transistor latest/playlist).
		$this->add_control(
			'show_slug',
			array(
				'label'   => '',
				'type'    => \Elementor\Controls_Manager::HIDDEN,
				'default' => '',
			)
		);

		// Theme (Transistor only).
		$this->add_control(
			'theme',
			array(
				'label'       => esc_html__( 'Player Theme', 'podloom-podcast-player' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'light',
				'options'     => array(
					'light' => esc_html__( 'Light', 'podloom-podcast-player' ),
					'dark'  => esc_html__( 'Dark', 'podloom-podcast-player' ),
				),
				'separator'   => 'before',
				'condition'   => array(
					'source' => $this->get_transistor_source_conditions(),
				),
			)
		);

		// Playlist height (Transistor only).
		$this->add_control(
			'playlist_height',
			array(
				'label'       => esc_html__( 'Playlist Height', 'podloom-podcast-player' ),
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => array( 'px' ),
				'range'       => array(
					'px' => array(
						'min'  => 200,
						'max'  => 1000,
						'step' => 10,
					),
				),
				'default'     => array(
					'unit' => 'px',
					'size' => 390,
				),
				'condition'   => array(
					'display_mode' => 'playlist',
					'source'       => $this->get_transistor_source_conditions(),
				),
			)
		);

		// Max episodes (RSS playlist only).
		$this->add_control(
			'playlist_max_episodes',
			array(
				'label'       => esc_html__( 'Max Episodes', 'podloom-podcast-player' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 5,
				'step'        => 5,
				'default'     => 25,
				'description' => esc_html__( 'Maximum number of episodes to display in the playlist.', 'podloom-podcast-player' ),
				'condition'   => array(
					'display_mode' => 'playlist',
					'source!'      => $this->get_transistor_source_conditions(),
				),
			)
		);

		// Playlist order (RSS playlist only).
		$this->add_control(
			'playlist_order',
			array(
				'label'       => esc_html__( 'Episode Order', 'podloom-podcast-player' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'episodic',
				'options'     => array(
					'episodic' => esc_html__( 'Episodic (newest first)', 'podloom-podcast-player' ),
					'serial'   => esc_html__( 'Serial (oldest first)', 'podloom-podcast-player' ),
				),
				'description' => esc_html__( 'Episodic for talk shows, Serial for narrative podcasts.', 'podloom-podcast-player' ),
				'condition'   => array(
					'display_mode' => 'playlist',
					'source!'      => $this->get_transistor_source_conditions(),
				),
			)
		);

		// Latest mode info notice.
		$this->add_control(
			'latest_info',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => '<div style="padding: 10px; background: rgba(128, 128, 128, 0.1); border-radius: 4px; border-left: 3px solid #888;">' .
									'<strong style="color: inherit;">' . esc_html__( 'Latest Episode Mode', 'podloom-podcast-player' ) . '</strong><br>' .
									'<span style="color: inherit; opacity: 0.8;">' . esc_html__( 'This widget will always display the most recent episode from the selected source.', 'podloom-podcast-player' ) . '</span>' .
									'</div>',
				'separator'       => 'before',
				'condition'       => array(
					'source!'      => '',
					'display_mode' => 'latest',
				),
			)
		);

		// Playlist mode info notice.
		$this->add_control(
			'playlist_info',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => '<div style="padding: 10px; background: rgba(128, 128, 128, 0.1); border-radius: 4px; border-left: 3px solid #888;">' .
									'<strong style="color: inherit;">' . esc_html__( 'Playlist Mode', 'podloom-podcast-player' ) . '</strong><br>' .
									'<span style="color: inherit; opacity: 0.8;">' . esc_html__( 'This widget displays a playlist of episodes with an Episodes tab. Click any episode to play it.', 'podloom-podcast-player' ) . '</span>' .
									'</div>',
				'separator'       => 'before',
				'condition'       => array(
					'source!'      => '',
					'display_mode' => 'playlist',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Get Transistor source condition values
	 *
	 * Returns array of source values that are Transistor shows.
	 *
	 * @return array Transistor source values.
	 */
	private function get_transistor_source_conditions() {
		if ( null !== $this->cached_transistor_conditions ) {
			return $this->cached_transistor_conditions;
		}

		$conditions = array();
		$shows      = $this->get_cached_shows();

		foreach ( $shows as $show ) {
			if ( isset( $show['id'] ) ) {
				$conditions[] = 'transistor:' . $show['id'];
			}
		}

		$this->cached_transistor_conditions = $conditions;
		return $conditions;
	}

	/**
	 * Render widget output on the frontend
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		// Check if source is selected.
		if ( empty( $settings['source'] ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				$this->render_editor_placeholder(
					esc_html__( 'Select a podcast source from the widget settings.', 'podloom-podcast-player' ),
					'#f5f5f5',
					'#ddd',
					'#999'
				);
			}
			return;
		}

		// Parse source.
		$source_parts = explode( ':', $settings['source'], 2 );
		if ( count( $source_parts ) !== 2 ) {
			return;
		}

		$source_type  = sanitize_key( $source_parts[0] );
		$source_id    = sanitize_text_field( $source_parts[1] );
		$display_mode = isset( $settings['display_mode'] ) ? sanitize_key( $settings['display_mode'] ) : 'specific';

		// Validate source type and display mode.
		if ( ! in_array( $source_type, array( 'transistor', 'rss' ), true ) ) {
			return;
		}
		if ( ! in_array( $display_mode, array( 'specific', 'latest', 'playlist' ), true ) ) {
			$display_mode = 'specific';
		}

		// Show placeholder in Elementor editor instead of rendering the full player.
		if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$this->render_editor_preview_placeholder( $settings, $source_type, $display_mode );
			return;
		}

		// Build attributes array to pass to the existing render function.
		$attributes = array(
			'sourceType'  => $source_type,
			'displayMode' => $display_mode,
			'theme'       => isset( $settings['theme'] ) && 'dark' === $settings['theme'] ? 'dark' : 'light',
		);

		if ( 'transistor' === $source_type ) {
			$attributes['showId']   = $source_id;
			$attributes['showSlug'] = ! empty( $settings['show_slug'] ) ? sanitize_title( $settings['show_slug'] ) : $this->get_show_slug( $source_id );

			if ( 'specific' === $display_mode ) {
				// Sanitize embed HTML - only allow iframe tags from Transistor.
				$embed_html = '';
				if ( ! empty( $settings['embed_html'] ) ) {
					$allowed_html = array(
						'iframe' => array(
							'width'       => true,
							'height'      => true,
							'frameborder' => true,
							'scrolling'   => true,
							'seamless'    => true,
							'src'         => true,
							'title'       => true,
							'loading'     => true,
						),
					);
					$embed_html = wp_kses( $settings['embed_html'], $allowed_html );

					// Verify it's a Transistor URL.
					if ( ! empty( $embed_html ) && strpos( $embed_html, 'share.transistor.fm' ) === false ) {
						$embed_html = '';
					}
				}
				$attributes['embedHtml'] = $embed_html;
				$attributes['episodeId'] = ! empty( $settings['episode_id'] ) ? sanitize_text_field( $settings['episode_id'] ) : '';
			} elseif ( 'playlist' === $display_mode ) {
				$attributes['playlistHeight'] = isset( $settings['playlist_height']['size'] ) ? absint( $settings['playlist_height']['size'] ) : 390;
			}
		} elseif ( 'rss' === $source_type ) {
			$attributes['rssFeedId'] = $source_id;

			if ( 'specific' === $display_mode && ! empty( $settings['episode_data'] ) ) {
				$episode_data = json_decode( $settings['episode_data'], true );

				// Validate JSON decode was successful and data is an array.
				if ( is_array( $episode_data ) ) {
					// Sanitize episode data fields.
					$sanitized_episode = array(
						'id'          => isset( $episode_data['id'] ) ? sanitize_text_field( $episode_data['id'] ) : '',
						'title'       => isset( $episode_data['title'] ) ? sanitize_text_field( $episode_data['title'] ) : '',
						'audio_url'   => isset( $episode_data['audio_url'] ) ? esc_url_raw( $episode_data['audio_url'] ) : '',
						'image'       => isset( $episode_data['image'] ) ? esc_url_raw( $episode_data['image'] ) : '',
						'description' => isset( $episode_data['description'] ) ? wp_kses_post( $episode_data['description'] ) : '',
						'date'        => isset( $episode_data['date'] ) ? sanitize_text_field( $episode_data['date'] ) : '',
						'duration'    => isset( $episode_data['duration'] ) ? sanitize_text_field( $episode_data['duration'] ) : '',
						'podcast20'   => isset( $episode_data['podcast20'] ) && is_array( $episode_data['podcast20'] ) ? $episode_data['podcast20'] : null,
					);

					$attributes['rssEpisodeData'] = $sanitized_episode;
					$attributes['episodeId']      = ! empty( $settings['episode_id'] ) ? sanitize_text_field( $settings['episode_id'] ) : '';
				}
			} elseif ( 'playlist' === $display_mode ) {
				// RSS playlist mode - pass max episodes and order.
				$max_episodes = isset( $settings['playlist_max_episodes'] ) ? absint( $settings['playlist_max_episodes'] ) : 25;
				$max_episodes = max( 5, $max_episodes );
				$attributes['playlistMaxEpisodes'] = $max_episodes;

				$playlist_order = isset( $settings['playlist_order'] ) ? sanitize_text_field( $settings['playlist_order'] ) : 'episodic';
				$attributes['playlistOrder'] = in_array( $playlist_order, array( 'episodic', 'serial' ), true ) ? $playlist_order : 'episodic';
			}
		}

		// Use the existing block render function.
		$output = podloom_render_block( $attributes );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in podloom_render_block
		echo $output;
	}

	/**
	 * Render a placeholder for the Elementor editor preview.
	 *
	 * @param array  $settings     Widget settings.
	 * @param string $source_type  Source type (transistor or rss).
	 * @param string $display_mode Display mode (specific, latest, playlist).
	 */
	private function render_editor_preview_placeholder( $settings, $source_type, $display_mode ) {
		// Determine the description based on settings.
		$source_label = 'transistor' === $source_type ? 'Transistor' : 'RSS';

		if ( 'specific' === $display_mode ) {
			if ( empty( $settings['episode_id'] ) ) {
				$description = esc_html__( 'Select an episode from the widget settings.', 'podloom-podcast-player' );
				$status      = 'warning';
			} else {
				$description = sprintf(
					/* translators: %s: source type (Transistor or RSS) */
					esc_html__( '%s episode player will appear here when you preview or publish.', 'podloom-podcast-player' ),
					$source_label
				);
				$status = 'ready';
			}
		} elseif ( 'latest' === $display_mode ) {
			$description = sprintf(
				/* translators: %s: source type (Transistor or RSS) */
				esc_html__( 'Latest %s episode will appear here when you preview or publish.', 'podloom-podcast-player' ),
				$source_label
			);
			$status = 'ready';
		} else { // playlist.
			$description = sprintf(
				/* translators: %s: source type (Transistor or RSS) */
				esc_html__( '%s playlist player will appear here when you preview or publish.', 'podloom-podcast-player' ),
				$source_label
			);
			$status = 'ready';
		}

		// Set colors based on status.
		if ( 'warning' === $status ) {
			$bg_color     = '#fff3cd';
			$border_color = '#ffc107';
			$text_color   = '#856404';
		} else {
			$bg_color     = '#e7f3ff';
			$border_color = '#2271b1';
			$text_color   = '#2271b1';
		}

		$this->render_editor_placeholder( $description, $bg_color, $border_color, $text_color );
	}

	/**
	 * Render a styled placeholder for the Elementor editor.
	 *
	 * @param string $message      Message to display.
	 * @param string $bg_color     Background color.
	 * @param string $border_color Border color.
	 * @param string $text_color   Text/icon color.
	 */
	private function render_editor_placeholder( $message, $bg_color, $border_color, $text_color ) {
		printf(
			'<div class="podloom-elementor-placeholder" style="padding: 40px; background: %s; border: 2px dashed %s; border-radius: 8px; text-align: center;">
				<span class="dashicons dashicons-microphone" style="font-size: 48px; color: %s; display: block; margin-bottom: 10px;"></span>
				<p style="margin: 0; color: %s; font-size: 14px;">%s</p>
			</div>',
			esc_attr( $bg_color ),
			esc_attr( $border_color ),
			esc_attr( $text_color ),
			esc_attr( $text_color ),
			esc_html( $message )
		);
	}

	/**
	 * Get show slug from show ID
	 *
	 * @param string $show_id Show ID.
	 * @return string Show slug.
	 */
	private function get_show_slug( $show_id ) {
		$shows = $this->get_cached_shows();
		foreach ( $shows as $show ) {
			if ( isset( $show['id'] ) && $show['id'] === $show_id && isset( $show['attributes']['slug'] ) ) {
				return $show['attributes']['slug'];
			}
		}
		return '';
	}

}
