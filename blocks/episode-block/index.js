/**
 * Transistor Episode Block with RSS Support
 */

const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, TextControl, Placeholder, Spinner, Button, RadioControl, ComboboxControl, __experimentalNumberControl: NumberControl } = wp.components;
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;
const { dispatch, select } = wp.data;

/**
 * Sanitize URL to prevent XSS attacks
 * Only allows http, https, and mailto protocols
 */
const sanitizeUrl = (url) => {
    if (!url) return '';

    // Trim whitespace
    url = url.trim();

    // Convert to lowercase for protocol check
    const urlLower = url.toLowerCase();

    // Allow only safe protocols
    if (urlLower.startsWith('http://') ||
        urlLower.startsWith('https://') ||
        urlLower.startsWith('mailto:')) {
        // Escape quotes and other special chars in the URL
        return url.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // For relative URLs (starting with / or #), allow them
    if (url.startsWith('/') || url.startsWith('#')) {
        return url.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Block dangerous protocols (javascript:, data:, etc.)
    return '';
};

/**
 * Clean and prepare HTML for WordPress blocks
 * Removes unwanted tags but keeps formatting (bold, italic, links, etc.)
 */
const cleanHtmlForBlocks = (html) => {
    if (!html) return '';

    // Create a temporary DOM element to parse HTML safely
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = html;

    // Function to recursively clean nodes
    const cleanNode = (node) => {
        // If it's a text node, return as-is
        if (node.nodeType === Node.TEXT_NODE) {
            return node.textContent;
        }

        // If it's not an element node, skip it
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return '';
        }

        const tagName = node.tagName.toLowerCase();
        let result = '';

        // Process child nodes
        const childContent = Array.from(node.childNodes)
            .map(child => cleanNode(child))
            .join('');

        // Handle different tags
        switch (tagName) {
            case 'p':
                result = childContent + '\n\n';
                break;
            case 'br':
                result = '\n';
                break;
            case 'strong':
            case 'b':
                result = '<strong>' + childContent + '</strong>';
                break;
            case 'em':
            case 'i':
                result = '<em>' + childContent + '</em>';
                break;
            case 'a':
                const href = node.getAttribute('href');
                const sanitizedHref = sanitizeUrl(href);
                if (sanitizedHref) {
                    result = '<a href="' + sanitizedHref + '">' + childContent + '</a>';
                } else {
                    result = childContent; // Invalid/dangerous URL, just use text
                }
                break;
            case 'h1':
            case 'h2':
            case 'h3':
            case 'h4':
            case 'h5':
            case 'h6':
                result = '\n\n' + childContent + '\n\n';
                break;
            case 'ul':
            case 'ol':
                result = childContent;
                break;
            case 'li':
                result = '- ' + childContent + '\n';
                break;
            default:
                // For any other tag, just keep the content
                result = childContent;
                break;
        }

        return result;
    };

    // Clean the HTML
    let cleaned = cleanNode(tempDiv);

    // Clean up excessive newlines
    cleaned = cleaned.replace(/\n{3,}/g, '\n\n');

    // Trim whitespace
    cleaned = cleaned.trim();

    return cleaned;
};

/**
 * Register the block
 */
registerBlockType('podloom/episode-player', {
    title: __('PodLoom Podcast Episode', 'podloom-podcast-player'),
    icon: 'microphone',
    category: 'media',
    attributes: {
        sourceType: {
            type: 'string',
            default: 'transistor' // 'transistor' or 'rss'
        },
        episodeId: {
            type: 'string',
            default: ''
        },
        episodeTitle: {
            type: 'string',
            default: ''
        },
        showId: {
            type: 'string',
            default: ''
        },
        showTitle: {
            type: 'string',
            default: ''
        },
        showSlug: {
            type: 'string',
            default: ''
        },
        rssFeedId: {
            type: 'string',
            default: ''
        },
        rssEpisodeData: {
            type: 'object',
            default: null
        },
        episodeDescription: {
            type: 'string',
            default: ''
        },
        embedHtml: {
            type: 'string',
            default: ''
        },
        theme: {
            type: 'string',
            default: 'light'
        },
        displayMode: {
            type: 'string',
            default: 'specific'
        },
        playlistHeight: {
            type: 'number',
            default: 390
        }
    },
    /**
     * Block Edit Component
     *
     * Handles the editor UI, data fetching, and state management for the block.
     *
     * @param {Object} props Block properties
     * @param {Object} props.attributes Block attributes
     * @param {Function} props.setAttributes Function to update attributes
     * @param {string} props.clientId Unique block ID
     */
    edit: function EditComponent({ attributes, setAttributes, clientId }) {
        const { sourceType, episodeId, episodeTitle, showId, showTitle, showSlug, rssFeedId, rssEpisodeData, episodeDescription, embedHtml, theme, displayMode, playlistHeight } = attributes;

        const [transistorShows, setTransistorShows] = useState([]);
        const [rssFeeds, setRssFeeds] = useState([]);
        const [episodes, setEpisodes] = useState([]);
        const [loading, setLoading] = useState(false);
        const [loadingShows, setLoadingShows] = useState(true);
        const [error, setError] = useState('');
        const [currentPage, setCurrentPage] = useState(1);
        const [hasMorePages, setHasMorePages] = useState(false);
        const [isLoadingMore, setIsLoadingMore] = useState(false);
        const [latestRssEpisode, setLatestRssEpisode] = useState(null);
        const [rssTypography, setRssTypography] = useState(null);
        const [renderedEpisodeHtml, setRenderedEpisodeHtml] = useState({});

        const blockProps = useBlockProps();

        // Load all initial data (shows, feeds, typography) in one request
        // This reduces server load by combining multiple API calls into a single AJAX request
        useEffect(() => {
            loadInitialData();
        }, []);

        // Lazy load RSS typography if user selects RSS but it wasn't loaded initially
        // This ensures we have the correct font settings when switching source types
        useEffect(() => {
            if (sourceType === 'rss' && !rssTypography) {
                loadRssTypography();
            }
        }, [sourceType]);

        // Load latest RSS episode when in latest mode
        // This fetches the most recent item from the feed to display in the editor preview
        useEffect(() => {
            if (sourceType === 'rss' && displayMode === 'latest' && rssFeedId) {
                loadLatestRssEpisode(rssFeedId);
            }
        }, [sourceType, displayMode, rssFeedId]);

        // Fetch rendered HTML for selected RSS episode
        useEffect(() => {
            if (sourceType === 'rss' && displayMode === 'specific' && rssEpisodeData) {
                fetchRenderedEpisodeHtml(rssEpisodeData);
            }
        }, [sourceType, displayMode, rssEpisodeData]);

        // Fetch rendered HTML for latest RSS episode
        useEffect(() => {
            if (sourceType === 'rss' && displayMode === 'latest' && latestRssEpisode) {
                fetchRenderedEpisodeHtml(latestRssEpisode);
            }
        }, [sourceType, displayMode, latestRssEpisode]);

        // Set default show if available and no show is selected
        useEffect(() => {
            if (transistorShows.length > 0 && !showId && !rssFeedId && podloomData.defaultShow) {
                const defaultShow = transistorShows.find(show => show.id === podloomData.defaultShow);
                if (defaultShow) {
                    setAttributes({
                        sourceType: 'transistor',
                        showId: defaultShow.id,
                        showTitle: defaultShow.attributes.title,
                        showSlug: defaultShow.attributes.slug
                    });
                }
            }
        }, [transistorShows, showId, rssFeedId]);

        // Validate selected RSS feed still exists
        useEffect(() => {
            if (sourceType === 'rss' && rssFeedId && rssFeeds.length > 0) {
                const feedExists = rssFeeds.find(feed => feed.id === rssFeedId);
                if (!feedExists) {
                    // Feed was deleted, clear selection
                    setAttributes({
                        rssFeedId: '',
                        showTitle: '',
                        episodeId: '',
                        episodeTitle: '',
                        episodeDescription: '',
                        embedHtml: '',
                        rssEpisodeData: null
                    });
                    setError(__('The selected RSS feed has been removed. Please select a different feed.', 'podloom-podcast-player'));
                }
            }
        }, [rssFeeds, rssFeedId, sourceType]);

        // Load episodes when source changes (only if no episode is selected)
        useEffect(() => {
            if (displayMode === 'specific') {
                if (sourceType === 'transistor' && showId && !episodeId) {
                    setCurrentPage(1);
                    setEpisodes([]);
                    loadTransistorEpisodes(showId, 1);
                } else if (sourceType === 'rss' && rssFeedId && !rssEpisodeData) {
                    setCurrentPage(1);
                    setEpisodes([]);
                    loadRssEpisodes(rssFeedId, 1);
                }
            }
        }, [showId, rssFeedId, sourceType, displayMode]);

        /**
         * Load all initial data in a single request (optimized)
         */
        const loadInitialData = async () => {
            setLoadingShows(true);
            setError('');

            try {
                const formData = new FormData();
                formData.append('action', 'podloom_get_block_init_data');
                formData.append('nonce', podloomData.nonce);

                const response = await fetch(podloomData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Set Transistor shows
                    if (result.data.podloom_shows && result.data.podloom_shows.data) {
                        setTransistorShows(result.data.podloom_shows.data);
                    }

                    // Set RSS feeds
                    if (result.data.rss_feeds) {
                        const feedsArray = Object.values(result.data.rss_feeds).filter(feed => feed.valid);
                        setRssFeeds(feedsArray);
                    }

                    // Set RSS typography (if available)
                    if (result.data.rss_typography) {
                        setRssTypography(result.data.rss_typography);
                    }
                } else {
                    setError(__('Error loading block data.', 'podloom-podcast-player'));
                }
            } catch (err) {
                setError(__('Error loading block data.', 'podloom-podcast-player'));
            } finally {
                setLoadingShows(false);
            }
        };

        /**
         * Load Transistor episodes
         */
        const loadTransistorEpisodes = async (selectedShowId, page = 1, isLoadMore = false) => {
            if (isLoadMore) {
                setIsLoadingMore(true);
            } else {
                setLoading(true);
            }
            setError('');

            try {
                // Use consistent page size for proper pagination
                const perPage = '20';

                const params = new URLSearchParams({
                    action: 'podloom_get_episodes',
                    nonce: podloomData.nonce,
                    show_id: selectedShowId,
                    page: page.toString(),
                    per_page: perPage
                });

                const response = await fetch(`${podloomData.ajaxUrl}?${params.toString()}`);
                const result = await response.json();

                if (result.success) {
                    const episodesData = result.data.data || [];
                    const meta = result.data.meta || {};

                    setEpisodes(prev => [...prev, ...episodesData]);
                    setHasMorePages(meta.currentPage < meta.totalPages);
                    setCurrentPage(meta.currentPage);
                } else {
                    setError(result.data.message || __('Failed to load episodes', 'podloom-podcast-player'));
                }
            } catch (err) {
                setError(__('Error loading episodes', 'podloom-podcast-player'));
            } finally {
                setLoading(false);
                setIsLoadingMore(false);
            }
        };

        /**
         * Load RSS episodes
         */
        const loadRssEpisodes = async (feedId, page = 1, isLoadMore = false) => {
            if (isLoadMore) {
                setIsLoadingMore(true);
            } else {
                setLoading(true);
            }
            setError('');

            try {
                // Use consistent page size for proper pagination
                const perPage = '20';

                const formData = new FormData();
                formData.append('action', 'podloom_get_rss_episodes');
                formData.append('nonce', podloomData.nonce);
                formData.append('feed_id', feedId);
                formData.append('page', page.toString());
                formData.append('per_page', perPage);

                const response = await fetch(podloomData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const episodesData = result.data.episodes || [];

                    // Format RSS episodes to match Transistor structure
                    const formattedEpisodes = episodesData.map(ep => ({
                        id: ep.id,
                        type: 'rss_episode',
                        attributes: {
                            title: ep.title,
                            status: 'published',
                            audio_url: ep.audio_url,
                            image: ep.image,
                            description: ep.description,
                            content: ep.content,
                            date: ep.date,
                            duration: ep.duration
                        }
                    }));

                    setEpisodes(prev => [...prev, ...formattedEpisodes]);
                    setHasMorePages(result.data.page < result.data.pages);
                    setCurrentPage(result.data.page);
                } else {
                    setError(result.data.message || __('Failed to load RSS episodes', 'podloom-podcast-player'));
                }
            } catch (err) {
                setError(__('Error loading RSS episodes', 'podloom-podcast-player'));
            } finally {
                setLoading(false);
                setIsLoadingMore(false);
            }
        };

        /**
         * Load more episodes
         */
        const loadMoreEpisodes = () => {
            const nextPage = currentPage + 1;
            if (sourceType === 'transistor') {
                loadTransistorEpisodes(showId, nextPage, true);
            } else {
                loadRssEpisodes(rssFeedId, nextPage, true);
            }
        };

        /**
         * Load RSS typography settings (lazy load if not already loaded)
         */
        const loadRssTypography = async () => {
            // Skip if already loaded
            if (rssTypography) return;

            try {
                const formData = new FormData();
                formData.append('action', 'podloom_get_rss_typography');
                formData.append('nonce', podloomData.nonce);

                const response = await fetch(podloomData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    setRssTypography(result.data);
                }
            } catch (err) {
                // Silently fail - typography will remain null and block will show loading state
            }
        };

        /**
         * Load latest RSS episode for preview
         */
        const loadLatestRssEpisode = async (feedId) => {
            try {
                const formData = new FormData();
                formData.append('action', 'podloom_get_rss_episodes');
                formData.append('nonce', podloomData.nonce);
                formData.append('feed_id', feedId);
                formData.append('page', '1');
                formData.append('per_page', '1');

                const response = await fetch(podloomData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success && result.data.episodes && result.data.episodes.length > 0) {
                    setLatestRssEpisode(result.data.episodes[0]);
                }
            } catch (err) {
                // Silently fail - episode will remain null and block will show loading state
            }
        };

        /**
         * Fetch fully rendered episode HTML from server (includes P2.0 tabs)
         */
        const fetchRenderedEpisodeHtml = async (episode) => {
            if (!episode) return;

            const episodeKey = episode.id || episode.title;

            // Check if already cached
            if (renderedEpisodeHtml[episodeKey]) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'podloom_render_rss_episode');
                formData.append('nonce', podloomData.nonce);
                formData.append('episode_data', JSON.stringify(episode));
                formData.append('feed_id', rssFeedId); // Pass feed ID for server-side fallback

                const response = await fetch(podloomData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success && result.data.html) {
                    setRenderedEpisodeHtml(prev => ({
                        ...prev,
                        [episodeKey]: result.data.html
                    }));
                } else {
                    console.warn('PodLoom: Failed to fetch rendered episode HTML', result);
                }
            } catch (err) {
                console.error('PodLoom: Error fetching rendered episode HTML', err);
                // Silently fail - will fall back to JavaScript rendering
            }
        };

        /**
         * Render RSS episode with typography (fallback for initial load)
         */
        const renderRssEpisode = (episode, typo) => {
            if (!episode || !typo) return null;

            // Get display settings (default to true if not available)
            const display = typo.display || {
                artwork: true,
                title: true,
                date: true,
                duration: true,
                description: true
            };

            const formatDate = (timestamp) => {
                const date = new Date(timestamp * 1000);
                return date.toLocaleDateString();
            };

            const formatDuration = (seconds) => {
                if (!seconds) return '';
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                if (hours > 0) {
                    return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                }
                return `${minutes}:${String(secs).padStart(2, '0')}`;
            };

            const wrapperChildren = [];

            // Artwork wrapper (if enabled and available)
            if (display.artwork && episode.image) {
                wrapperChildren.push(wp.element.createElement('div', {
                    key: 'artwork-wrapper',
                    className: 'rss-episode-artwork'
                }, [
                    wp.element.createElement('img', {
                        key: 'artwork-img',
                        src: episode.image,
                        alt: episode.title
                    })
                ]));
            }

            const contentChildren = [];

            // Title (if enabled and available)
            if (display.title && episode.title) {
                contentChildren.push(wp.element.createElement('h3', {
                    key: 'title',
                    className: 'rss-episode-title'
                }, episode.title));
            }

            // Meta information (date and duration)
            const metaChildren = [];
            if (display.date && episode.date) {
                metaChildren.push(wp.element.createElement('span', {
                    key: 'date',
                    className: 'rss-episode-date'
                }, formatDate(episode.date)));
            }
            if (display.duration && episode.duration) {
                metaChildren.push(wp.element.createElement('span', {
                    key: 'duration',
                    className: 'rss-episode-duration'
                }, formatDuration(episode.duration)));
            }
            if (metaChildren.length > 0) {
                contentChildren.push(wp.element.createElement('div', {
                    key: 'meta',
                    className: 'rss-episode-meta'
                }, metaChildren));
            }

            // Audio player (always shown if available)
            if (episode.audio_url) {
                // Add 'rss-audio-last' class if description is hidden to remove bottom margin
                const audioClass = (display.description && episode.description) ? 'rss-episode-audio' : 'rss-episode-audio rss-audio-last';

                // Create audio element with source tag (matching frontend structure)
                const audioChildren = [
                    wp.element.createElement('source', {
                        key: 'source',
                        src: episode.audio_url,
                        type: episode.audio_type || 'audio/mpeg'
                    }),
                    __('Your browser does not support the audio player.', 'podloom-podcast-player')
                ];

                contentChildren.push(wp.element.createElement('audio', {
                    key: 'audio',
                    className: audioClass,
                    controls: true,
                    preload: 'metadata'
                }, audioChildren));
            }

            // Description (if enabled and available)
            if (display.description && episode.description) {
                contentChildren.push(wp.element.createElement('div', {
                    key: 'description',
                    className: 'rss-episode-description',
                    dangerouslySetInnerHTML: { __html: episode.description }
                }));
            }

            // Content wrapper
            if (contentChildren.length > 0) {
                wrapperChildren.push(wp.element.createElement('div', {
                    key: 'content',
                    className: 'rss-episode-content'
                }, contentChildren));
            }

            // Wrapper with flexbox layout
            const wrapper = wp.element.createElement('div', {
                key: 'wrapper',
                className: 'rss-episode-wrapper'
            }, wrapperChildren);

            return wp.element.createElement('div', {
                className: 'wp-block-podloom-episode-player rss-episode-player'
            }, [wrapper]);
        };

        /**
         * Handle episode selection
         */
        const selectEpisode = (episode) => {
            if (sourceType === 'transistor') {
                const html = theme === 'dark' ? episode.attributes.embed_html_dark : episode.attributes.embed_html;
                setAttributes({
                    episodeId: episode.id,
                    episodeTitle: episode.attributes.title,
                    episodeDescription: episode.attributes.description || '',
                    embedHtml: html,
                    rssEpisodeData: null
                });
            } else {
                // For RSS, store episode data
                setAttributes({
                    episodeId: episode.id,
                    episodeTitle: episode.attributes.title,
                    embedHtml: '',
                    rssEpisodeData: {
                        title: episode.attributes.title,
                        audio_url: episode.attributes.audio_url,
                        image: episode.attributes.image,
                        description: episode.attributes.description,
                        content: episode.attributes.content,
                        date: episode.attributes.date,
                        duration: episode.attributes.duration,
                        podcast20: episode.attributes.podcast20 || null
                    }
                });
            }
        };

        /**
         * Handle source selection (Transistor show or RSS feed)
         */
        const handleSourceChange = (value) => {
            const [type, id] = value.split(':');

            if (type === 'transistor') {
                const selectedShow = transistorShows.find(show => show.id === id);
                setAttributes({
                    sourceType: 'transistor',
                    showId: id,
                    showTitle: selectedShow ? selectedShow.attributes.title : '',
                    showSlug: selectedShow ? selectedShow.attributes.slug : '',
                    rssFeedId: '',
                    episodeId: '',
                    episodeTitle: '',
                    episodeDescription: '',
                    embedHtml: '',
                    rssEpisodeData: null
                });
            } else if (type === 'rss') {
                const selectedFeed = rssFeeds.find(feed => feed.id === id);
                setAttributes({
                    sourceType: 'rss',
                    showId: '',
                    showTitle: '',
                    showSlug: '',
                    rssFeedId: id,
                    showTitle: selectedFeed ? selectedFeed.name : '',
                    episodeId: '',
                    episodeTitle: '',
                    episodeDescription: '',
                    embedHtml: '',
                    rssEpisodeData: null
                });
            }

            setEpisodes([]);
        };

        /**
         * Handle theme change
         */
        const handleThemeChange = (newTheme) => {
            setAttributes({ theme: newTheme });

            // If a Transistor episode is selected, update the embed HTML
            if (sourceType === 'transistor' && episodeId && episodes.length > 0) {
                const currentEpisode = episodes.find(ep => ep.id === episodeId);
                if (currentEpisode) {
                    const html = newTheme === 'dark'
                        ? currentEpisode.attributes.embed_html_dark
                        : currentEpisode.attributes.embed_html;
                    setAttributes({ embedHtml: html });
                }
            }
        };

        /**
         * Handle paste description
         * Cleans HTML and inserts it after the current block as paragraph blocks
         */
        const handlePasteDescription = () => {
            let description = '';

            // Get description based on source type
            if (sourceType === 'rss' && rssEpisodeData && rssEpisodeData.description) {
                description = rssEpisodeData.description;
            } else if (sourceType === 'transistor' && episodeDescription) {
                description = episodeDescription;
            }

            if (!description) {
                return;
            }

            // Clean HTML and keep formatting tags
            const cleanedHtml = cleanHtmlForBlocks(description);

            // Get block editor store
            const blockEditorStore = select('core/block-editor');

            // Get the block's index and parent (using clientId from props)
            const blockIndex = blockEditorStore.getBlockIndex(clientId);
            const rootClientId = blockEditorStore.getBlockRootClientId(clientId);

            // Split into paragraphs (separated by double line breaks)
            const paragraphs = cleanedHtml.split('\n\n').filter(p => p.trim() !== '');

            // Create multiple paragraph blocks with HTML content
            const blocks = paragraphs.map(paragraphText => {
                return wp.blocks.createBlock('core/paragraph', {
                    content: paragraphText.trim()
                });
            });

            // Insert all blocks after the current block
            dispatch('core/block-editor').insertBlocks(
                blocks,
                blockIndex + 1,
                rootClientId
            );
        };

        /**
         * Get current source value for select
         */
        const getCurrentSourceValue = () => {
            if (sourceType === 'transistor' && showId) {
                return `transistor:${showId}`;
            } else if (sourceType === 'rss' && rssFeedId) {
                return `rss:${rssFeedId}`;
            }
            return '';
        };

        /**
         * Build source options with labels
         */
        const buildSourceOptions = () => {
            const options = [
                { label: __('-- Select a source --', 'podloom-podcast-player'), value: '' }
            ];

            if (transistorShows.length > 0) {
                options.push({
                    label: __('━━ Transistor.fm ━━', 'podloom-podcast-player'),
                    value: '__podloom_header__',
                    disabled: true
                });
                transistorShows.forEach(show => {
                    options.push({
                        label: '  ' + show.attributes.title,
                        value: `transistor:${show.id}`
                    });
                });
            }

            if (rssFeeds.length > 0) {
                options.push({
                    label: __('━━ RSS Feeds ━━', 'podloom-podcast-player'),
                    value: '__rss_header__',
                    disabled: true
                });
                rssFeeds.forEach(feed => {
                    options.push({
                        label: '  ' + feed.name,
                        value: `rss:${feed.id}`
                    });
                });
            }

            return options;
        };

        /**
         * Render the block
         */
        if (loadingShows) {
            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('PodLoom Podcast Episode', 'podloom-podcast-player') },
                    wp.element.createElement(Spinner),
                    wp.element.createElement('p', null, __('Loading sources...', 'podloom-podcast-player'))
                )
            );
        }

        if (!podloomData.hasApiKey && transistorShows.length === 0 && rssFeeds.length === 0) {
            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('PodLoom Podcast Episode', 'podloom-podcast-player') },
                    wp.element.createElement('p', null, __('Please configure your Transistor API key or add RSS feeds in the settings.', 'podloom-podcast-player')),
                    wp.element.createElement(
                        Button,
                        {
                            variant: 'primary',
                            href: `${podloomData.ajaxUrl.replace('/wp-admin/admin-ajax.php', '')}/wp-admin/admin.php?page=podloom-settings`
                        },
                        __('Go to Settings', 'podloom-podcast-player')
                    )
                )
            );
        }

        // Check if source can support display modes
        const supportsLatestAndPlaylist = sourceType === 'transistor' && showSlug;
        const supportsLatest = (sourceType === 'transistor' && showSlug) || (sourceType === 'rss' && rssFeedId);

        return wp.element.createElement(
            wp.element.Fragment,
            null,
            wp.element.createElement(
                InspectorControls,
                null,
                wp.element.createElement(
                    PanelBody,
                    { title: __('Episode Settings', 'podloom-podcast-player'), initialOpen: true },
                    wp.element.createElement(SelectControl, {
                        label: __('Select Source', 'podloom-podcast-player'),
                        value: getCurrentSourceValue(),
                        options: buildSourceOptions(),
                        onChange: handleSourceChange,
                        help: __('Choose a Transistor show or RSS feed', 'podloom-podcast-player')
                    }),
                    (showId || rssFeedId) && displayMode === 'specific' && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', marginBottom: '16px' } },
                        loading && episodes.length === 0 ? wp.element.createElement(
                            'div',
                            { style: { marginTop: '8px' } },
                            wp.element.createElement(Spinner),
                            wp.element.createElement('p', null, __('Loading episodes...', 'podloom-podcast-player'))
                        ) : wp.element.createElement(ComboboxControl, {
                            label: __('Search and Select Episode', 'podloom-podcast-player'),
                            value: episodeId,
                            onChange: (selectedId) => {
                                if (selectedId === '__load_more__') {
                                    loadMoreEpisodes();
                                    return;
                                }
                                const episode = episodes.find(ep => ep.id === selectedId);
                                if (episode) {
                                    selectEpisode(episode);
                                } else {
                                    setAttributes({
                                        episodeId: '',
                                        episodeTitle: '',
                                        episodeDescription: '',
                                        embedHtml: '',
                                        rssEpisodeData: null
                                    });
                                }
                            },
                            options: [
                                ...episodes.map(episode => {
                                    const status = episode.attributes.status;
                                    const statusLabel = status === 'draft' ? ' (Draft)' : status === 'scheduled' ? ' (Scheduled)' : '';
                                    // Don't show source label since user already selected a specific source
                                    return {
                                        label: episode.attributes.title + statusLabel,
                                        value: episode.id
                                    };
                                }),
                                ...(hasMorePages ? [{
                                    label: isLoadingMore
                                        ? __('Loading more episodes...', 'podloom-podcast-player')
                                        : __('Load More Episodes...', 'podloom-podcast-player'),
                                    value: '__load_more__'
                                }] : [])
                            ],
                            help: episodes.length > 0
                                ? __('Type to search episodes, or scroll to browse', 'podloom-podcast-player')
                                : null
                        })
                    ),
                    (showId || rssFeedId) && wp.element.createElement(RadioControl, {
                        label: __('Display Mode', 'podloom-podcast-player'),
                        selected: displayMode,
                        options: [
                            { label: __('Specific Episode', 'podloom-podcast-player'), value: 'specific' },
                            ...(supportsLatest ? [
                                { label: __('Latest Episode', 'podloom-podcast-player'), value: 'latest' }
                            ] : []),
                            ...(supportsLatestAndPlaylist ? [
                                { label: __('Playlist', 'podloom-podcast-player'), value: 'playlist' }
                            ] : [])
                        ],
                        onChange: (value) => {
                            setAttributes({ displayMode: value });
                        },
                        help: displayMode === 'latest'
                            ? __('Will always show the most recent episode from this source', 'podloom-podcast-player')
                            : displayMode === 'playlist'
                                ? __('Displays a playlist of episodes. Episode count is controlled in your Transistor settings.', 'podloom-podcast-player')
                                : null
                    }),
                    displayMode === 'playlist' && NumberControl && wp.element.createElement(NumberControl, {
                        label: __('Playlist Height (px)', 'podloom-podcast-player'),
                        value: playlistHeight,
                        onChange: (value) => setAttributes({ playlistHeight: parseInt(value) || 390 }),
                        min: 200,
                        max: 1000,
                        step: 10,
                        help: __('Adjust the height of the playlist player (200-1000px)', 'podloom-podcast-player')
                    }),
                    sourceType === 'transistor' && wp.element.createElement(RadioControl, {
                        label: __('Player Theme', 'podloom-podcast-player'),
                        selected: theme,
                        options: [
                            { label: __('Light', 'podloom-podcast-player'), value: 'light' },
                            { label: __('Dark', 'podloom-podcast-player'), value: 'dark' }
                        ],
                        onChange: handleThemeChange
                    }),
                    displayMode === 'specific' && episodeId && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', padding: '12px', background: '#f0f0f0', borderRadius: '4px' } },
                        wp.element.createElement('strong', null, __('Selected Episode:', 'podloom-podcast-player')),
                        wp.element.createElement('p', { style: { margin: '8px 0 0 0', fontSize: '13px' } }, episodeTitle),
                        sourceType && wp.element.createElement('p', {
                            style: { margin: '4px 0 0 0', fontSize: '11px', color: '#666' }
                        }, __('Source: ', 'podloom-podcast-player') + (sourceType === 'transistor' ? 'Transistor.fm' : 'RSS Feed')),
                        wp.element.createElement(Button, {
                            variant: 'secondary',
                            onClick: handlePasteDescription,
                            style: { marginTop: '12px', width: '100%' }
                        }, __('Paste Description', 'podloom-podcast-player'))
                    ),
                    displayMode === 'latest' && (showId || rssFeedId) && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', padding: '12px', background: '#e7f5ff', borderRadius: '4px', border: '1px solid #1e88e5' } },
                        wp.element.createElement('strong', null, __('Latest Episode Mode', 'podloom-podcast-player')),
                        wp.element.createElement('p', { style: { margin: '8px 0 0 0', fontSize: '13px' } }, __('This block will always display the most recent episode from ', 'podloom-podcast-player') + showTitle)
                    ),
                    displayMode === 'playlist' && showId && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', padding: '12px', background: '#f3e5f5', borderRadius: '4px', border: '1px solid #9c27b0' } },
                        wp.element.createElement('strong', null, __('Playlist Mode', 'podloom-podcast-player')),
                        wp.element.createElement('p', { style: { margin: '8px 0 0 0', fontSize: '13px' } }, __('This block will display a playlist from ', 'podloom-podcast-player') + showTitle + __('. Episode count is controlled in your Transistor settings.', 'podloom-podcast-player'))
                    )
                )
            ),
            wp.element.createElement(
                'div',
                blockProps,
                // Transistor Latest Mode
                displayMode === 'latest' && sourceType === 'transistor' && showId && showSlug ? wp.element.createElement('div', {
                    dangerouslySetInnerHTML: {
                        __html: '<iframe width="100%" height="180" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/' + encodeURIComponent(showSlug) + '/' + (theme === 'dark' ? 'latest/dark' : 'latest') + '"></iframe>'
                    }
                }) :
                    // Transistor Playlist Mode
                    displayMode === 'playlist' && sourceType === 'transistor' && showId && showSlug ? wp.element.createElement('div', {
                        dangerouslySetInnerHTML: {
                            __html: '<iframe width="100%" height="' + parseInt(playlistHeight || 390) + '" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/' + encodeURIComponent(showSlug) + '/' + (theme === 'dark' ? 'playlist/dark' : 'playlist') + '"></iframe>'
                        }
                    }) :
                        // Specific Episode Mode - Transistor
                        displayMode === 'specific' && sourceType === 'transistor' && episodeId && embedHtml ? wp.element.createElement('div', {
                            dangerouslySetInnerHTML: { __html: embedHtml }
                        }) :
                            // Latest Episode Mode - RSS
                            displayMode === 'latest' && sourceType === 'rss' && rssFeedId ? (
                                latestRssEpisode && rssTypography ? (
                                    // Use server-rendered HTML if available (includes P2.0 tabs)
                                    renderedEpisodeHtml[latestRssEpisode.id || latestRssEpisode.title]
                                        ? wp.element.createElement('div', {
                                            dangerouslySetInnerHTML: { __html: renderedEpisodeHtml[latestRssEpisode.id || latestRssEpisode.title] }
                                        })
                                        : renderRssEpisode(latestRssEpisode, rssTypography)
                                ) : wp.element.createElement(
                                    'div',
                                    { style: { background: '#f9f9f9', border: '1px solid #ddd', borderRadius: '8px', padding: '20px', minHeight: '180px', display: 'flex', flexDirection: 'column', justifyContent: 'center', alignItems: 'center' } },
                                    wp.element.createElement('span', {
                                        className: 'dashicons dashicons-rss',
                                        style: { fontSize: '48px', color: '#f8981d', marginBottom: '10px' }
                                    }),
                                    wp.element.createElement('p', { style: { margin: '0', fontSize: '14px', color: '#666', textAlign: 'center', fontWeight: '600' } }, __('Loading Latest RSS Episode...', 'podloom-podcast-player'))
                                )
                            ) :
                                // Specific Episode Mode - RSS
                                displayMode === 'specific' && sourceType === 'rss' && episodeId && rssEpisodeData ? (
                                    rssTypography ? (
                                        // Use server-rendered HTML if available (includes P2.0 tabs)
                                        renderedEpisodeHtml[rssEpisodeData.id || rssEpisodeData.title]
                                            ? wp.element.createElement('div', {
                                                dangerouslySetInnerHTML: { __html: renderedEpisodeHtml[rssEpisodeData.id || rssEpisodeData.title] }
                                            })
                                            : renderRssEpisode(rssEpisodeData, rssTypography)
                                    ) : wp.element.createElement(
                                        'div',
                                        { style: { padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', borderRadius: '8px' } },
                                        wp.element.createElement('p', { style: { margin: '0', fontSize: '14px', color: '#666' } }, __('Loading episode...', 'podloom-podcast-player'))
                                    )
                                ) :
                                    // Placeholder
                                    wp.element.createElement(
                                        Placeholder,
                                        { icon: 'microphone', label: __('PodLoom Podcast Episode', 'podloom-podcast-player') },
                                        !showId && !rssFeedId ? wp.element.createElement('p', null, __('Please select a source from the sidebar.', 'podloom-podcast-player')) :
                                            displayMode === 'specific' && !episodeId ? wp.element.createElement('p', null, __('Please select an episode from the sidebar.', 'podloom-podcast-player')) :
                                                wp.element.createElement('p', null, __('Please configure the block settings.', 'podloom-podcast-player'))
                                    )
            )
        );
    },
    /**
     * Save Block Content
     *
     * Returns null because this is a dynamic block.
     * The content is rendered server-side via PHP in `podloom_render_block`.
     *
     * @return {null} Always returns null
     */
    save: function () {
        return null;
    }
});
