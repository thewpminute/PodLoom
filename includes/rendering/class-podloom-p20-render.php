<?php
/**
 * Podcasting 2.0 Rendering
 *
 * Handles rendering of Podcasting 2.0 elements (chapters, transcripts, people, funding).
 *
 * @package PodLoom
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Podloom_P20_Render
 *
 * Renders Podcasting 2.0 elements.
 */
class Podloom_P20_Render {

	/**
	 * Render Podcasting 2.0 elements as tabbed interface.
	 *
	 * @param array  $p20_data         Podcasting 2.0 data from parser.
	 * @param string $description_html Sanitized episode description HTML.
	 * @param bool   $show_description Whether to show description tab.
	 * @return string HTML output.
	 */
	public static function render_tabs( $p20_data, $description_html = '', $show_description = true ) {
		// Get display settings.
		$display_transcripts   = get_option( 'podloom_rss_display_transcripts', true );
		$display_people_hosts  = get_option( 'podloom_rss_display_people_hosts', true );
		$display_people_guests = get_option( 'podloom_rss_display_people_guests', true );
		$display_chapters      = get_option( 'podloom_rss_display_chapters', true );

		// Build tabs array: [id, label, content].
		$tabs = array();

		// Tab 0: Description (if enabled and has content).
		if ( $show_description && ! empty( $description_html ) ) {
			$tabs[] = array(
				'id'      => 'description',
				'label'   => __( 'Description', 'podloom-podcast-player' ),
				'content' => '<div class="rss-episode-description">' . $description_html . '</div>',
			);
		}

		// Ensure p20_data is an array.
		if ( ! is_array( $p20_data ) ) {
			$p20_data = array();
		}

		// Tab: Credits (People).
		$people_to_show = array();
		if ( $display_people_hosts && ! empty( $p20_data['people_channel'] ) ) {
			$people_to_show = array_merge( $people_to_show, $p20_data['people_channel'] );
		}
		if ( $display_people_guests && ! empty( $p20_data['people_episode'] ) ) {
			$people_to_show = array_merge( $people_to_show, $p20_data['people_episode'] );
		}
		// Deduplicate by name (case-insensitive), keeping the first occurrence.
		$seen_names     = array();
		$people_to_show = array_filter(
			$people_to_show,
			function ( $person ) use ( &$seen_names ) {
				$name_key = strtolower( trim( $person['name'] ) );
				if ( isset( $seen_names[ $name_key ] ) ) {
					return false;
				}
				$seen_names[ $name_key ] = true;
				return true;
			}
		);
		if ( ! empty( $people_to_show ) ) {
			usort(
				$people_to_show,
				function ( $a, $b ) {
					$priority   = array(
						'host'    => 1,
						'co-host' => 2,
						'guest'   => 3,
					);
					$a_priority = $priority[ strtolower( $a['role'] ) ] ?? 999;
					$b_priority = $priority[ strtolower( $b['role'] ) ] ?? 999;
					return $a_priority - $b_priority;
				}
			);
			$tabs[] = array(
				'id'      => 'credits',
				'label'   => __( 'Credits', 'podloom-podcast-player' ),
				'content' => self::render_people( $people_to_show ),
			);
		}

		// Tab: Chapters.
		if ( $display_chapters && ! empty( $p20_data['chapters'] ) ) {
			$tabs[] = array(
				'id'      => 'chapters',
				'label'   => __( 'Chapters', 'podloom-podcast-player' ),
				'content' => self::render_chapters( $p20_data['chapters'] ),
			);
		}

		// Tab: Transcripts.
		if ( $display_transcripts && ! empty( $p20_data['transcripts'] ) ) {
			$tabs[] = array(
				'id'      => 'transcripts',
				'label'   => __( 'Transcripts', 'podloom-podcast-player' ),
				'content' => self::render_transcripts( $p20_data['transcripts'] ),
			);
		}

		// If no tabs, return empty.
		if ( empty( $tabs ) ) {
			return '';
		}

		// Build tab navigation.
		$output  = '<div class="podcast20-tabs">';
		$output .= '<div class="podcast20-tab-nav" role="tablist">';

		foreach ( $tabs as $index => $tab ) {
			$is_active = ( $index === 0 ) ? 'active' : '';
			$output   .= sprintf(
				'<button class="podcast20-tab-button %s" data-tab="%s" role="tab" aria-selected="%s" aria-controls="tab-panel-%s">%s</button>',
				$is_active,
				esc_attr( $tab['id'] ),
				$is_active ? 'true' : 'false',
				esc_attr( $tab['id'] ),
				esc_html( $tab['label'] )
			);
		}

		$output .= '</div>'; // .podcast20-tab-nav

		// Build tab panels.
		foreach ( $tabs as $index => $tab ) {
			$is_active = ( $index === 0 ) ? 'active' : '';
			$output   .= sprintf(
				'<div class="podcast20-tab-panel %s" id="tab-panel-%s" role="tabpanel" aria-labelledby="%s">%s</div>',
				$is_active,
				esc_attr( $tab['id'] ),
				esc_attr( $tab['id'] ),
				$tab['content']
			);
		}

		$output .= '</div>'; // .podcast20-tabs

		return $output;
	}

	/**
	 * Render podcast:funding tag.
	 *
	 * @param array $funding Funding data.
	 * @return string HTML output.
	 */
	public static function render_funding( $funding ) {
		if ( empty( $funding['url'] ) ) {
			return '';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-funding-button">
				<svg class="podcast20-icon" width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
					<path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
					<path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
				</svg>
				<span>%s</span>
			</a>',
			esc_url( $funding['url'] ),
			esc_html( $funding['text'] )
		);
	}

	/**
	 * Render podcast:transcript tags.
	 *
	 * @param array $transcripts Array of transcript objects.
	 * @return string HTML output.
	 */
	public static function render_transcripts( $transcripts ) {
		if ( empty( $transcripts ) || ! is_array( $transcripts ) ) {
			return '';
		}

		// Check if any .txt transcripts exist and add potential HTML versions.
		$has_html = false;
		foreach ( $transcripts as $transcript ) {
			if ( ( $transcript['type'] ?? '' ) === 'text/html' ) {
				$has_html = true;
				break;
			}
		}

		// If no HTML transcript exists, check for .txt files and generate HTML alternatives.
		if ( ! $has_html ) {
			$additional_transcripts = array();
			foreach ( $transcripts as $transcript ) {
				$url  = $transcript['url'] ?? '';
				$type = $transcript['type'] ?? '';

				// If this is a text/plain or .txt file, try HTML version.
				if ( ( $type === 'text/plain' || strpos( $url, '.txt' ) !== false ) && ! empty( $url ) ) {
					// Generate potential HTML URL by replacing .txt with .html.
					$html_url = preg_replace( '/\.txt$/i', '.html', $url );

					// Only add if it's actually different.
					if ( $html_url !== $url ) {
						$additional_transcripts[] = array(
							'url'      => $html_url,
							'type'     => 'text/html',
							'label'    => $transcript['label'] ?? '',
							'language' => $transcript['language'] ?? '',
						);
					}
				}
			}

			// Add potential HTML transcripts to the array.
			if ( ! empty( $additional_transcripts ) ) {
				$transcripts = array_merge( $additional_transcripts, $transcripts );
			}
		}

		// Sort transcripts by format preference: HTML > SRT > VTT > JSON > text/plain > other.
		$format_priority = array(
			'text/html'            => 1,
			'application/x-subrip' => 2,
			'text/srt'             => 2,
			'text/vtt'             => 3,
			'application/json'     => 4,
			'text/plain'           => 5,
		);

		usort(
			$transcripts,
			function ( $a, $b ) use ( $format_priority ) {
				$a_priority = $format_priority[ $a['type'] ?? '' ] ?? 999;
				$b_priority = $format_priority[ $b['type'] ?? '' ] ?? 999;
				return $a_priority - $b_priority;
			}
		);

		// Use the first (highest priority) transcript for display.
		$primary_transcript = $transcripts[0];

		if ( empty( $primary_transcript['url'] ) ) {
			return '';
		}

		$output = '<div class="podcast20-transcripts">';

		// Transcript format button - include all transcripts as fallbacks.
		$output .= '<div class="transcript-formats">';
		$output .= sprintf(
			'<button class="transcript-format-button" data-url="%s" data-type="%s" data-transcripts="%s">
				<svg class="podcast20-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
					<path d="M14 4.5V14a2 2 0 01-2 2H4a2 2 0 01-2-2V2a2 2 0 012-2h5.5L14 4.5zm-3 0A1.5 1.5 0 019.5 3V1H4a1 1 0 00-1 1v12a1 1 0 001 1h8a1 1 0 001-1V4.5h-2z"/>
					<path d="M3 9.5h10v1H3v-1zm0 2h10v1H3v-1z"/>
				</svg>
				<span>%s</span>
			</button>',
			esc_url( $primary_transcript['url'] ),
			esc_attr( $primary_transcript['type'] ?? 'text/plain' ),
			esc_attr( wp_json_encode( $transcripts ) ),
			esc_html__( 'Click for Transcript', 'podloom-podcast-player' )
		);

		// Fallback link for no-JS.
		$output .= sprintf(
			' <a href="%s" target="_blank" rel="noopener noreferrer" class="transcript-external-link" title="%s">
				<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
					<path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>
					<path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>
				</svg>
			</a>',
			esc_url( $primary_transcript['url'] ),
			esc_attr__( 'Open transcript in new tab', 'podloom-podcast-player' )
		);

		$output .= '</div>'; // .transcript-formats

		// Transcript viewer (hidden by default).
		$output .= '<div class="transcript-viewer" style="display:none;">';
		$output .= '<div class="transcript-content"></div>';
		$output .= '<button class="transcript-close">' . esc_html__( 'Close', 'podloom-podcast-player' ) . '</button>';
		$output .= '</div>'; // .transcript-viewer

		$output .= '</div>'; // .podcast20-transcripts

		return $output;
	}

	/**
	 * Render podcast:person tags.
	 *
	 * @param array $people Array of person objects.
	 * @return string HTML output.
	 */
	public static function render_people( $people ) {
		if ( empty( $people ) || ! is_array( $people ) ) {
			return '';
		}

		$output  = '<div class="podcast20-people">';
		$output .= '<h4 class="podcast20-heading">' . esc_html__( 'Credits', 'podloom-podcast-player' ) . '</h4>';
		$output .= '<div class="podcast20-people-list">';

		foreach ( $people as $person ) {
			if ( empty( $person['name'] ) ) {
				continue;
			}

			$output .= '<div class="podcast20-person">';

			// Person image.
			if ( ! empty( $person['img'] ) ) {
				$output .= sprintf(
					'<img src="%s" alt="%s" class="podcast20-person-img">',
					esc_url( $person['img'] ),
					esc_attr( $person['name'] )
				);
			} else {
				// Default avatar icon.
				$output .= '<div class="podcast20-person-avatar">
					<svg width="40" height="40" viewBox="0 0 16 16" fill="currentColor">
						<path d="M11 6a3 3 0 11-6 0 3 3 0 016 0z"/>
						<path d="M2 0a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V2a2 2 0 00-2-2H2zm12 1a1 1 0 011 1v12a1 1 0 01-1 1v-1c0-1-1-4-6-4s-6 3-6 4v1a1 1 0 01-1-1V2a1 1 0 011-1h12z"/>
					</svg>
				</div>';
			}

			$output .= '<div class="podcast20-person-info">';

			// Role.
			if ( ! empty( $person['role'] ) ) {
				$output .= sprintf(
					'<span class="podcast20-person-role">%s</span>',
					esc_html( ucfirst( $person['role'] ) )
				);
			}

			// Name (linked or plain text).
			if ( ! empty( $person['href'] ) ) {
				$output .= sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-person-name">%s</a>',
					esc_url( $person['href'] ),
					esc_html( $person['name'] )
				);
			} else {
				$output .= sprintf(
					'<span class="podcast20-person-name">%s</span>',
					esc_html( $person['name'] )
				);
			}

			$output .= '</div>'; // .podcast20-person-info
			$output .= '</div>'; // .podcast20-person
		}

		$output .= '</div></div>'; // .podcast20-people-list and .podcast20-people

		return $output;
	}

	/**
	 * Render podcast:chapters tag.
	 *
	 * @param array $chapters Chapters data.
	 * @return string HTML output.
	 */
	public static function render_chapters( $chapters ) {
		if ( empty( $chapters ) ) {
			return '';
		}

		// If no chapters array is available, show link to chapters JSON.
		if ( empty( $chapters['chapters'] ) || ! is_array( $chapters['chapters'] ) ) {
			if ( ! empty( $chapters['url'] ) ) {
				return sprintf(
					'<div class="podcast20-chapters">
						<a href="%s" target="_blank" rel="noopener noreferrer" class="podcast20-chapters-link">
							<svg class="podcast20-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
								<path d="M1 2.5A1.5 1.5 0 012.5 1h3A1.5 1.5 0 017 2.5v3A1.5 1.5 0 015.5 7h-3A1.5 1.5 0 011 5.5v-3zm8 0A1.5 1.5 0 0110.5 1h3A1.5 1.5 0 0115 2.5v3A1.5 1.5 0 0113.5 7h-3A1.5 1.5 0 019 5.5v-3zm-8 8A1.5 1.5 0 012.5 9h3A1.5 1.5 0 017 10.5v3A1.5 1.5 0 015.5 15h-3A1.5 1.5 0 011 13.5v-3zm8 0A1.5 1.5 0 0110.5 9h3a1.5 1.5 0 011.5 1.5v3a1.5 1.5 0 01-1.5 1.5h-3A1.5 1.5 0 019 13.5v-3z"/>
							</svg>
							<span>%s</span>
						</a>
					</div>',
					esc_url( $chapters['url'] ),
					esc_html__( 'View Chapters', 'podloom-podcast-player' )
				);
			}
			return '';
		}

		// Render full chapter list.
		$output  = '<div class="podcast20-chapters-list">';
		$output .= '<h4 class="chapters-heading">' . esc_html__( 'Chapters', 'podloom-podcast-player' ) . '</h4>';

		foreach ( $chapters['chapters'] as $chapter ) {
			$start_time     = $chapter['startTime'];
			$formatted_time = podloom_format_timestamp( $start_time );
			$title          = $chapter['title'];

			$output .= '<div class="chapter-item" data-start-time="' . esc_attr( $start_time ) . '">';

			// Chapter image.
			if ( ! empty( $chapter['img'] ) ) {
				$output .= sprintf(
					'<img src="%s" alt="%s" class="chapter-img" loading="lazy" />',
					esc_url( $chapter['img'] ),
					esc_attr( $title )
				);
			} else {
				// Placeholder if no image.
				$output .= '<div class="chapter-img-placeholder"></div>';
			}

			// Chapter info.
			$output .= '<div class="chapter-info">';
			$output .= '<button class="chapter-timestamp" data-start-time="' . esc_attr( $start_time ) . '">';
			$output .= esc_html( $formatted_time );
			$output .= '</button>';

			// Chapter title (always a span, never a link).
			$output .= '<span class="chapter-title">' . esc_html( $title );

			// If chapter has a URL, add external link icon.
			if ( ! empty( $chapter['url'] ) ) {
				$output .= sprintf(
					' <a href="%s" target="_blank" rel="noopener noreferrer" class="chapter-external-link" title="%s">
						<svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" style="display: inline-block; vertical-align: middle; margin-left: 4px;">
							<path d="M6.354 5.5H4a3 3 0 000 6h3a3 3 0 002.83-4H9c-.086 0-.17.01-.25.031A2 2 0 017 10.5H4a2 2 0 110-4h1.535c.218-.376.495-.714.82-1z"/>
							<path d="M9 5.5a3 3 0 00-2.83 4h1.098A2 2 0 019 6.5h3a2 2 0 110 4h-1.535a4.02 4.02 0 01-.82 1H12a3 3 0 100-6H9z"/>
						</svg>
					</a>',
					esc_url( $chapter['url'] ),
					esc_attr__( 'Open chapter link', 'podloom-podcast-player' )
				);
			}

			$output .= '</span>';

			$output .= '</div>'; // .chapter-info
			$output .= '</div>'; // .chapter-item
		}

		$output .= '</div>'; // .podcast20-chapters-list

		return $output;
	}

	/**
	 * Get funding button HTML (for top-right positioning).
	 *
	 * @param array $p20_data Podcasting 2.0 data from parser.
	 * @return string HTML output.
	 */
	public static function get_funding_button( $p20_data ) {
		$display_funding = get_option( 'podloom_rss_display_funding', true );

		if ( $display_funding && ! empty( $p20_data['funding'] ) ) {
			return self::render_funding( $p20_data['funding'] );
		}

		return '';
	}
}

/**
 * Render Podcasting 2.0 elements as tabbed interface.
 *
 * Wrapper function for backwards compatibility.
 *
 * @param array  $p20_data         Podcasting 2.0 data from parser.
 * @param string $description_html Sanitized episode description HTML.
 * @param bool   $show_description Whether to show description tab.
 * @return string HTML output.
 */
function podloom_render_podcast20_tabs( $p20_data, $description_html = '', $show_description = true ) {
	return Podloom_P20_Render::render_tabs( $p20_data, $description_html, $show_description );
}

/**
 * Render Podcasting 2.0 elements (legacy function for backwards compatibility).
 *
 * @param array $p20_data Podcasting 2.0 data from parser.
 * @return string HTML output.
 */
function podloom_render_podcast20_elements( $p20_data ) {
	return Podloom_P20_Render::render_tabs( $p20_data );
}

/**
 * Render podcast:funding tag.
 *
 * @param array $funding Funding data.
 * @return string HTML output.
 */
function podloom_render_funding( $funding ) {
	return Podloom_P20_Render::render_funding( $funding );
}

/**
 * Render podcast:transcript tags.
 *
 * @param array $transcripts Array of transcript objects.
 * @return string HTML output.
 */
function podloom_render_transcripts( $transcripts ) {
	return Podloom_P20_Render::render_transcripts( $transcripts );
}

/**
 * Render podcast:person tags.
 *
 * @param array $people Array of person objects.
 * @return string HTML output.
 */
function podloom_render_people( $people ) {
	return Podloom_P20_Render::render_people( $people );
}

/**
 * Render podcast:chapters tag.
 *
 * @param array $chapters Chapters data.
 * @return string HTML output.
 */
function podloom_render_chapters( $chapters ) {
	return Podloom_P20_Render::render_chapters( $chapters );
}

/**
 * Get funding button HTML (for top-right positioning).
 *
 * @param array $p20_data Podcasting 2.0 data from parser.
 * @return string HTML output.
 */
function podloom_get_funding_button( $p20_data ) {
	return Podloom_P20_Render::get_funding_button( $p20_data );
}
