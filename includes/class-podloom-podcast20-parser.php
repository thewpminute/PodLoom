<?php
/**
 * Podcasting 2.0 Namespace Parser
 *
 * Parses Podcasting 2.0 namespace tags from RSS feeds and Transistor API responses.
 * Supports: podcast:funding, podcast:transcript, podcast:person, podcast:chapters
 *
 * @link https://github.com/Podcastindex-org/podcast-namespace/blob/main/docs/1.0.md
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Podloom_Podcast20_Parser {

    /**
     * Podcast namespace URIs (multiple valid namespace URLs)
     * Supports all known variations used in production feeds
     */
    const PODCAST_NAMESPACES = [
        'https://podcastindex.org/namespace/1.0',                                          // Official canonical
        'https://github.com/Podcastindex-org/podcast-namespace/blob/main/docs/1.0.md',   // Used in official example
        'https://github.com/Podcastindex-org/podcast-namespace',                          // Shortened version
        'http://podcastindex.org/namespace/1.0',                                           // HTTP (non-SSL) version
    ];

    /**
     * Primary podcast namespace URI (for backwards compatibility)
     */
    const PODCAST_NAMESPACE = 'https://podcastindex.org/namespace/1.0';

    /**
     * Helper method to get tags from SimplePie trying multiple namespaces
     *
     * @param SimplePie_Item|SimplePie $source SimplePie item or feed
     * @param string $tag_name Tag name to search for
     * @param bool $is_channel Whether this is a channel-level tag
     * @return array|null Tags data or null
     */
    private function get_podcast_tags($source, $tag_name, $is_channel = false) {
        foreach (self::PODCAST_NAMESPACES as $namespace) {
            $tags = $is_channel
                ? $source->get_channel_tags($namespace, $tag_name)
                : $source->get_item_tags($namespace, $tag_name);

            if (!empty($tags)) {
                return $tags;
            }
        }
        return null;
    }

    /**
     * Parse all Podcasting 2.0 tags from RSS feed item
     *
     * @param SimpleXMLElement $item RSS feed item
     * @return array Parsed P2.0 data
     */
    public function parse_from_rss($item) {
        if (!$item instanceof SimpleXMLElement) {
            return $this->get_empty_structure();
        }

        // Register podcast namespace
        $namespaces = $item->getNamespaces(true);
        $podcast_ns = null;

        // Find podcast namespace (could be registered with different prefix)
        foreach ($namespaces as $prefix => $uri) {
            if (strpos($uri, 'podcastindex.org') !== false || $prefix === 'podcast') {
                $podcast_ns = $prefix;
                break;
            }
        }

        if (!$podcast_ns) {
            return $this->get_empty_structure();
        }

        return [
            'funding' => $this->parse_funding_tag($item, $podcast_ns),
            'transcripts' => $this->parse_transcript_tag($item, $podcast_ns),
            'people' => $this->parse_person_tag($item, $podcast_ns),
            'chapters' => $this->parse_chapters_tag($item, $podcast_ns),
        ];
    }

    /**
     * Parse all Podcasting 2.0 tags from SimplePie channel
     *
     * @param SimplePie $feed SimplePie feed object
     * @return array Parsed P2.0 data
     */
    public function parse_from_simplepie_channel($feed) {
        if (!$feed) {
            return $this->get_empty_structure();
        }

        // Get channel-level tags using get_channel_tags()
        $funding_data = $this->get_podcast_tags($feed, 'funding', true);

        $funding = null;
        if (!empty($funding_data)) {
            $url = isset($funding_data[0]['attribs']['']['url']) ? $funding_data[0]['attribs']['']['url'] : '';
            $text = isset($funding_data[0]['data']) ? trim($funding_data[0]['data']) : '';

            if (!empty($url)) {
                $funding = [
                    'url' => esc_url_raw($url),
                    'text' => !empty($text) ? sanitize_text_field($text) : __('Support this podcast', 'podloom-podcast-player'),
                ];
            }
        }

        // Parse channel-level people (typically hosts)
        $person_data = $this->get_podcast_tags($feed, 'person', true);
        $people = [];

        if (!empty($person_data)) {
            foreach ($person_data as $person) {
                $name = isset($person['data']) ? trim($person['data']) : '';
                $role = isset($person['attribs']['']['role']) ? $person['attribs']['']['role'] : 'host';
                $group = isset($person['attribs']['']['group']) ? $person['attribs']['']['group'] : '';
                $img = isset($person['attribs']['']['img']) ? $person['attribs']['']['img'] : '';
                $href = isset($person['attribs']['']['href']) ? $person['attribs']['']['href'] : '';

                if (empty($name)) {
                    continue;
                }

                $people[] = [
                    'name' => sanitize_text_field($name),
                    'role' => sanitize_text_field($role),
                    'group' => sanitize_text_field($group),
                    'img' => !empty($img) ? esc_url_raw($img) : null,
                    'href' => !empty($href) ? esc_url_raw($href) : null,
                ];
            }

            // Sort by role priority
            usort($people, function($a, $b) {
                $priority = ['host' => 1, 'co-host' => 2, 'guest' => 3];
                $a_priority = $priority[strtolower($a['role'])] ?? 999;
                $b_priority = $priority[strtolower($b['role'])] ?? 999;
                return $a_priority - $b_priority;
            });
        }

        return [
            'funding' => $funding,
            'transcripts' => [], // Channel-level transcripts are rare
            'people' => $people, // Channel-level hosts
            'chapters' => null, // Chapters are episode-specific
        ];
    }

    /**
     * Parse all Podcasting 2.0 tags from SimplePie item
     *
     * @param SimplePie_Item $item SimplePie item object
     * @return array Parsed P2.0 data
     */
    public function parse_from_simplepie($item) {
        if (!$item) {
            return $this->get_empty_structure();
        }

        return [
            'funding' => $this->parse_funding_from_simplepie($item),
            'transcripts' => $this->parse_transcripts_from_simplepie($item),
            'people' => $this->parse_people_from_simplepie($item),
            'chapters' => $this->parse_chapters_from_simplepie($item),
        ];
    }

    /**
     * Parse Transistor API episode data
     *
     * @param array $episode_data Transistor API episode data
     * @return array Parsed P2.0 data
     */
    public function parse_from_transistor($episode_data) {
        return [
            'funding' => null, // Not available in Transistor API
            'transcripts' => $this->parse_transistor_transcripts($episode_data),
            'people' => null, // Not available in Transistor API
            'chapters' => null, // Not available in Transistor API
        ];
    }

    /**
     * Parse podcast:funding tag
     *
     * Format: <podcast:funding url="https://example.com/donate">Support the show</podcast:funding>
     *
     * @param SimpleXMLElement $item RSS feed item
     * @param string $ns Namespace prefix
     * @return array|null Funding data or null
     */
    private function parse_funding_tag($item, $ns) {
        $funding = $item->children($ns, true)->funding;

        if (!$funding) {
            return null;
        }

        $url = (string) $funding['url'];
        $text = trim((string) $funding);

        if (empty($url)) {
            return null;
        }

        return [
            'url' => esc_url_raw($url),
            'text' => !empty($text) ? sanitize_text_field($text) : __('Support this podcast', 'podloom-podcast-player'),
        ];
    }

    /**
     * Parse podcast:transcript tags (can be multiple)
     *
     * Format: <podcast:transcript url="https://example.com/transcript.vtt" type="text/vtt" />
     *
     * @param SimpleXMLElement $item RSS feed item
     * @param string $ns Namespace prefix
     * @return array Array of transcript objects
     */
    private function parse_transcript_tag($item, $ns) {
        $transcripts = [];
        $transcript_elements = $item->children($ns, true)->transcript;

        if (!$transcript_elements) {
            return $transcripts;
        }

        foreach ($transcript_elements as $transcript) {
            $url = (string) $transcript['url'];
            $type = (string) $transcript['type'];
            $language = (string) $transcript['language'];
            $rel = (string) $transcript['rel'];

            if (empty($url)) {
                continue;
            }

            $transcripts[] = [
                'url' => esc_url_raw($url),
                'type' => sanitize_text_field($type ?: 'text/plain'),
                'language' => sanitize_text_field($language ?: 'en'),
                'rel' => sanitize_text_field($rel ?: 'captions'),
                'label' => $this->get_transcript_label($type),
            ];
        }

        return $transcripts;
    }

    /**
     * Parse podcast:person tags (can be multiple)
     *
     * Format: <podcast:person role="host" img="https://example.com/person.jpg" href="https://example.com">Jane Doe</podcast:person>
     *
     * @param SimpleXMLElement $item RSS feed item
     * @param string $ns Namespace prefix
     * @return array Array of person objects
     */
    private function parse_person_tag($item, $ns) {
        $people = [];
        $person_elements = $item->children($ns, true)->person;

        if (!$person_elements) {
            return $people;
        }

        foreach ($person_elements as $person) {
            $name = trim((string) $person);
            $role = (string) $person['role'];
            $group = (string) $person['group'];
            $img = (string) $person['img'];
            $href = (string) $person['href'];

            if (empty($name)) {
                continue;
            }

            $people[] = [
                'name' => sanitize_text_field($name),
                'role' => sanitize_text_field($role ?: 'guest'),
                'group' => sanitize_text_field($group),
                'img' => !empty($img) ? esc_url_raw($img) : null,
                'href' => !empty($href) ? esc_url_raw($href) : null,
            ];
        }

        // Sort by role priority (host, co-host, guest, etc.)
        usort($people, function($a, $b) {
            $priority = ['host' => 1, 'co-host' => 2, 'guest' => 3];
            $a_priority = $priority[strtolower($a['role'])] ?? 999;
            $b_priority = $priority[strtolower($b['role'])] ?? 999;
            return $a_priority - $b_priority;
        });

        return $people;
    }

    /**
     * Parse transcripts from Transistor API response
     *
     * @param array $episode_data Transistor episode data
     * @return array Array of transcript objects
     */
    private function parse_transistor_transcripts($episode_data) {
        $transcripts = [];

        // Main transcript URL
        if (!empty($episode_data['attributes']['transcript_url'])) {
            $transcripts[] = [
                'url' => esc_url_raw($episode_data['attributes']['transcript_url']),
                'type' => 'text/html',
                'language' => 'en',
                'rel' => 'captions',
                'label' => 'HTML',
            ];
        }

        // AI transcription formats array
        if (!empty($episode_data['attributes']['transcripts']) && is_array($episode_data['attributes']['transcripts'])) {
            foreach ($episode_data['attributes']['transcripts'] as $transcript_url) {
                $type = $this->detect_transcript_format($transcript_url);
                $transcripts[] = [
                    'url' => esc_url_raw($transcript_url),
                    'type' => $type,
                    'language' => 'en',
                    'rel' => 'captions',
                    'label' => $this->get_transcript_label($type),
                ];
            }
        }

        return $transcripts;
    }

    /**
     * Detect transcript format from URL
     *
     * @param string $url Transcript URL
     * @return string MIME type
     */
    private function detect_transcript_format($url) {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        $formats = [
            'vtt' => 'text/vtt',
            'srt' => 'application/x-subrip',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'html' => 'text/html',
        ];

        return $formats[$extension] ?? 'text/plain';
    }

    /**
     * Get human-readable label for transcript type
     *
     * @param string $type MIME type
     * @return string Label
     */
    private function get_transcript_label($type) {
        $labels = [
            'text/vtt' => 'VTT',
            'application/x-subrip' => 'SRT',
            'application/json' => 'JSON',
            'text/plain' => 'TXT',
            'text/html' => 'HTML',
        ];

        return $labels[$type] ?? strtoupper(pathinfo($type, PATHINFO_EXTENSION));
    }

    /**
     * Get empty P2.0 data structure
     *
     * @return array Empty structure
     */
    public function get_empty_structure() {
        return [
            'funding' => null,
            'transcripts' => [],
            'people' => [],
            'people_channel' => [], // Hosts from channel level
            'people_episode' => [], // Guests from episode level
            'chapters' => null,
        ];
    }

    /**
     * Merge P2.0 data from multiple sources (item-level + channel-level)
     *
     * @param array $item_data Item-level data (episode-specific)
     * @param array $channel_data Channel-level data (podcast-wide)
     * @return array Merged data
     */
    public function merge_data($item_data, $channel_data) {
        $channel_people = $channel_data['people'] ?? [];
        $episode_people = $item_data['people'] ?? [];

        // Merge and sort all people (channel + episode)
        $all_people = array_merge($channel_people, $episode_people);
        if (!empty($all_people)) {
            usort($all_people, function($a, $b) {
                $priority = ['host' => 1, 'co-host' => 2, 'guest' => 3];
                $a_priority = $priority[strtolower($a['role'])] ?? 999;
                $b_priority = $priority[strtolower($b['role'])] ?? 999;
                return $a_priority - $b_priority;
            });
        }

        return [
            'funding' => $channel_data['funding'] ?? $item_data['funding'] ?? null, // Prefer channel-level funding
            'transcripts' => array_merge(
                $item_data['transcripts'] ?? [],
                $channel_data['transcripts'] ?? []
            ),
            'people' => $all_people, // Merged list (for "show both" option)
            'people_channel' => $channel_people, // Channel-level hosts
            'people_episode' => $episode_people, // Episode-level guests
            'chapters' => $item_data['chapters'] ?? $channel_data['chapters'] ?? null,
        ];
    }

    /**
     * Parse podcast:funding from SimplePie item
     *
     * @param SimplePie_Item $item SimplePie item
     * @return array|null Funding data or null
     */
    private function parse_funding_from_simplepie($item) {
        $funding_data = $this->get_podcast_tags($item, 'funding');

        if (empty($funding_data)) {
            return null;
        }

        $url = isset($funding_data[0]['attribs']['']['url']) ? $funding_data[0]['attribs']['']['url'] : '';
        $text = isset($funding_data[0]['data']) ? trim($funding_data[0]['data']) : '';

        if (empty($url)) {
            return null;
        }

        return [
            'url' => esc_url_raw($url),
            'text' => !empty($text) ? sanitize_text_field($text) : __('Support this podcast', 'podloom-podcast-player'),
        ];
    }

    /**
     * Parse podcast:transcript from SimplePie item
     *
     * @param SimplePie_Item $item SimplePie item
     * @return array Array of transcript objects
     */
    private function parse_transcripts_from_simplepie($item) {
        $transcript_data = $this->get_podcast_tags($item, 'transcript');
        $transcripts = [];

        if (empty($transcript_data)) {
            return $transcripts;
        }

        foreach ($transcript_data as $transcript) {
            $url = isset($transcript['attribs']['']['url']) ? $transcript['attribs']['']['url'] : '';
            $type = isset($transcript['attribs']['']['type']) ? $transcript['attribs']['']['type'] : 'text/plain';
            $language = isset($transcript['attribs']['']['language']) ? $transcript['attribs']['']['language'] : 'en';
            $rel = isset($transcript['attribs']['']['rel']) ? $transcript['attribs']['']['rel'] : 'captions';

            if (empty($url)) {
                continue;
            }

            $transcripts[] = [
                'url' => esc_url_raw($url),
                'type' => sanitize_text_field($type),
                'language' => sanitize_text_field($language),
                'rel' => sanitize_text_field($rel),
                'label' => $this->get_transcript_label($type),
            ];
        }

        return $transcripts;
    }

    /**
     * Parse podcast:person from SimplePie item
     *
     * @param SimplePie_Item $item SimplePie item
     * @return array Array of person objects
     */
    private function parse_people_from_simplepie($item) {
        $person_data = $this->get_podcast_tags($item, 'person');
        $people = [];

        if (empty($person_data)) {
            return $people;
        }

        foreach ($person_data as $person) {
            $name = isset($person['data']) ? trim($person['data']) : '';
            $role = isset($person['attribs']['']['role']) ? $person['attribs']['']['role'] : 'guest';
            $group = isset($person['attribs']['']['group']) ? $person['attribs']['']['group'] : '';
            $img = isset($person['attribs']['']['img']) ? $person['attribs']['']['img'] : '';
            $href = isset($person['attribs']['']['href']) ? $person['attribs']['']['href'] : '';

            if (empty($name)) {
                continue;
            }

            $people[] = [
                'name' => sanitize_text_field($name),
                'role' => sanitize_text_field($role),
                'group' => sanitize_text_field($group),
                'img' => !empty($img) ? esc_url_raw($img) : null,
                'href' => !empty($href) ? esc_url_raw($href) : null,
            ];
        }

        // Sort by role priority (host, co-host, guest, etc.)
        usort($people, function($a, $b) {
            $priority = ['host' => 1, 'co-host' => 2, 'guest' => 3];
            $a_priority = $priority[strtolower($a['role'])] ?? 999;
            $b_priority = $priority[strtolower($b['role'])] ?? 999;
            return $a_priority - $b_priority;
        });

        return $people;
    }

    /**
     * Parse podcast:chapters from SimplePie item
     *
     * @param SimplePie_Item $item SimplePie item
     * @return array|null Chapters data or null
     */
    private function parse_chapters_from_simplepie($item) {
        $chapters_data = $this->get_podcast_tags($item, 'chapters');

        if (empty($chapters_data)) {
            return null;
        }

        $url = isset($chapters_data[0]['attribs']['']['url']) ? $chapters_data[0]['attribs']['']['url'] : '';
        $type = isset($chapters_data[0]['attribs']['']['type']) ? $chapters_data[0]['attribs']['']['type'] : 'application/json+chapters';

        if (empty($url)) {
            return null;
        }

        $chapter_structure = [
            'url' => esc_url_raw($url),
            'type' => sanitize_text_field($type),
            'chapters' => []
        ];

        // Fetch and parse the chapters JSON
        if (strpos($type, 'json') !== false || $type === 'application/json+chapters') {
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'sslverify' => true
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $json = json_decode($body, true);

                if (!empty($json['chapters']) && is_array($json['chapters'])) {
                    foreach ($json['chapters'] as $chapter) {
                        $chapter_item = [
                            'startTime' => floatval($chapter['startTime'] ?? 0),
                            'title' => sanitize_text_field($chapter['title'] ?? '')
                        ];

                        if (!empty($chapter['url'])) {
                            $chapter_item['url'] = esc_url_raw($chapter['url']);
                        }

                        if (!empty($chapter['img'])) {
                            $chapter_item['img'] = esc_url_raw($chapter['img']);
                        }

                        $chapter_structure['chapters'][] = $chapter_item;
                    }
                }
            }
        }

        return $chapter_structure;
    }
}
