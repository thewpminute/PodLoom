<?php
/**
 * Utility Helper Functions
 *
 * General purpose utility functions for HTML manipulation,
 * duration formatting, and other common tasks.
 *
 * @package PodLoom
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Truncate HTML content while preserving tags and structure
 *
 * @param string $html The HTML content to truncate
 * @param int    $limit Character limit for text content
 * @return string Truncated HTML
 */
function podloom_truncate_html( $html, $limit ) {
	if ( empty( $html ) || $limit <= 0 ) {
		return $html;
	}

	// Strip tags to count actual text length
	$text_only = wp_strip_all_tags( $html );

	// If text is within limit, return as-is
	if ( mb_strlen( $text_only ) <= $limit ) {
		return $html;
	}

	// Need to truncate - use DOMDocument for proper HTML truncation
	$dom = new DOMDocument();
	// Suppress warnings for malformed HTML
	@$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body ) {
		// Fallback: simple text truncation
		$truncated  = mb_substr( $text_only, 0, $limit );
		$last_space = mb_strrpos( $truncated, ' ' );
		if ( $last_space !== false && $last_space > $limit * 0.8 ) {
			$truncated = mb_substr( $truncated, 0, $last_space );
		}
		return '<p>' . esc_html( $truncated ) . '…</p>';
	}

	// Walk through nodes and truncate at character limit
	$char_count = 0;
	$result     = podloom_truncate_html_node( $body, $limit, $char_count );

	// Get HTML output
	$output = '';
	foreach ( $result->childNodes as $child ) {
		$output .= $dom->saveHTML( $child );
	}

	// Add ellipsis if we actually truncated
	if ( $char_count >= $limit ) {
		$output = rtrim( $output ) . '…';
	}

	return $output;
}

/**
 * Recursively truncate HTML nodes
 *
 * @param DOMNode $node The node to process
 * @param int     $limit Character limit
 * @param int     &$char_count Current character count (passed by reference)
 * @return DOMNode Modified node
 */
function podloom_truncate_html_node( $node, $limit, &$char_count ) {
	if ( $char_count >= $limit ) {
		return $node;
	}

	foreach ( $node->childNodes as $child ) {
		if ( $char_count >= $limit ) {
			$child->parentNode->removeChild( $child );
			continue;
		}

		if ( $child->nodeType === XML_TEXT_NODE ) {
			$text        = $child->nodeValue;
			$text_length = mb_strlen( $text );

			if ( $char_count + $text_length > $limit ) {
				// Truncate this text node
				$remaining = $limit - $char_count;
				$truncated = mb_substr( $text, 0, $remaining );

				// Try to break at last space
				$last_space = mb_strrpos( $truncated, ' ' );
				if ( $last_space !== false && $last_space > $remaining * 0.8 ) {
					$truncated = mb_substr( $truncated, 0, $last_space );
				}

				$child->nodeValue = $truncated;
				$char_count       = $limit; // We're done
			} else {
				$char_count += $text_length;
			}
		} elseif ( $child->nodeType === XML_ELEMENT_NODE ) {
			podloom_truncate_html_node( $child, $limit, $char_count );
		}
	}

	return $node;
}

/**
 * Format duration from seconds to readable format (H:MM:SS or M:SS)
 *
 * @param int $seconds Duration in seconds
 * @return string Formatted duration string
 */
function podloom_format_duration( $seconds ) {
	if ( empty( $seconds ) || ! is_numeric( $seconds ) ) {
		return '';
	}

	$seconds = intval( $seconds );
	$hours   = floor( $seconds / 3600 );
	$minutes = floor( ( $seconds % 3600 ) / 60 );
	$secs    = $seconds % 60;

	if ( $hours > 0 ) {
		return sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
	} else {
		return sprintf( '%d:%02d', $minutes, $secs );
	}
}

/**
 * Get allowed HTML tags for RSS episode descriptions.
 *
 * Returns a single canonical allowlist used by all description rendering paths.
 * Site owners can extend it via the 'podloom_rss_description_allowed_html' filter.
 *
 * @return array wp_kses-compatible allowlist array.
 */
function podloom_get_rss_description_allowed_html() {
	$allowed_html = array(
		'p'          => array(),
		'br'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'u'          => array(),
		'a'          => array(
			'href'   => array(),
			'title'  => array(),
			'target' => array(),
			'rel'    => array(),
		),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'blockquote' => array(),
		'code'       => array(),
		'pre'        => array(),
	);

	/**
	 * Filter the allowed HTML tags for RSS episode descriptions.
	 *
	 * @param array $allowed_html wp_kses-compatible allowlist.
	 */
	return apply_filters( 'podloom_rss_description_allowed_html', $allowed_html );
}

/**
 * Sanitize RSS episode description HTML.
 *
 * Runs HTML through wp_kses() with the canonical allowlist, then does a second
 * DOM pass to strip dangerous href schemes (javascript:, data:, vbscript:) and
 * add rel="noopener noreferrer" to target="_blank" links.
 *
 * @param string $html Raw HTML from an RSS feed.
 * @return string Sanitized HTML safe for output.
 */
function podloom_sanitize_rss_description_html( $html ) {
	if ( empty( $html ) ) {
		return '';
	}

	$allowed_html = podloom_get_rss_description_allowed_html();
	$sanitized    = wp_kses( $html, $allowed_html );

	// Second pass: fix link security via DOMDocument (only when links are present).
	if ( ! empty( $sanitized ) && strpos( $sanitized, '<a ' ) !== false && class_exists( 'DOMDocument' ) ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		@$dom->loadHTML( '<?xml encoding="UTF-8">' . $sanitized, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$links = $dom->getElementsByTagName( 'a' );
		foreach ( $links as $link ) {
			$href = $link->getAttribute( 'href' );
			if ( $href ) {
				$href_lower = strtolower( trim( $href ) );
				// Remove dangerous URL schemes entirely.
				if ( strpos( $href_lower, 'javascript:' ) === 0 ||
					strpos( $href_lower, 'data:' ) === 0 ||
					strpos( $href_lower, 'vbscript:' ) === 0 ) {
					$link->removeAttribute( 'href' );
				}
			}
			// Ensure external links have a safe rel attribute.
			if ( $link->hasAttribute( 'href' ) && $link->getAttribute( 'target' ) === '_blank' ) {
				$link->setAttribute( 'rel', 'noopener noreferrer' );
			}
		}

		$sanitized = '';
		foreach ( $dom->childNodes as $child ) {
			$sanitized .= $dom->saveHTML( $child );
		}
	}

	return $sanitized;
}

/**
 * Format timestamp for P2.0 elements (chapters, transcripts)
 *
 * @param float $seconds Timestamp in seconds
 * @return string Formatted timestamp string
 */
function podloom_format_timestamp( $seconds ) {
	$hours   = floor( $seconds / 3600 );
	$minutes = floor( ( $seconds % 3600 ) / 60 );
	$secs    = floor( $seconds % 60 );

	if ( $hours > 0 ) {
		return sprintf( '%d:%02d:%02d', $hours, $minutes, $secs );
	} else {
		return sprintf( '%d:%02d', $minutes, $secs );
	}
}
