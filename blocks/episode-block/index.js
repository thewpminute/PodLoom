/**
 * Transistor Episode Block with RSS Support
 */

const { registerBlockType } = wp.blocks;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { PanelBody, SelectControl, TextControl, Placeholder, Spinner, Button, RadioControl, ComboboxControl, __experimentalNumberControl: NumberControl } = wp.components;
const { useState, useEffect } = wp.element;
const { __ } = wp.i18n;

/**
 * Register the block
 */
registerBlockType('podloom/episode-player', {
    title: __('PodLoom Podcast Episode', 'podloom'),
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
    edit: function EditComponent({ attributes, setAttributes }) {
        const { sourceType, episodeId, episodeTitle, showId, showTitle, showSlug, rssFeedId, rssEpisodeData, embedHtml, theme, displayMode, playlistHeight } = attributes;

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

        const blockProps = useBlockProps();

        // Load all initial data in one request
        useEffect(() => {
            loadInitialData();
        }, []);

        // Lazy load RSS typography if user selects RSS but it wasn't loaded initially
        useEffect(() => {
            if (sourceType === 'rss' && !rssTypography) {
                loadRssTypography();
            }
        }, [sourceType]);

        // Load latest RSS episode when in latest mode
        useEffect(() => {
            if (sourceType === 'rss' && displayMode === 'latest' && rssFeedId) {
                loadLatestRssEpisode(rssFeedId);
            }
        }, [sourceType, displayMode, rssFeedId]);

        // Set default show if available and no show is selected
        useEffect(() => {
            if (transistorShows.length > 0 && !showId && !rssFeedId && transistorData.defaultShow) {
                const defaultShow = transistorShows.find(show => show.id === transistorData.defaultShow);
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
                        embedHtml: '',
                        rssEpisodeData: null
                    });
                    setError(__('The selected RSS feed has been removed. Please select a different feed.', 'podloom'));
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
                formData.append('action', 'transistor_get_block_init_data');
                formData.append('nonce', transistorData.nonce);

                const response = await fetch(transistorData.ajaxUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Set Transistor shows
                    if (result.data.transistor_shows && result.data.transistor_shows.data) {
                        setTransistorShows(result.data.transistor_shows.data);
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
                    setError(__('Error loading block data.', 'podloom'));
                }
            } catch (err) {
                setError(__('Error loading block data.', 'podloom'));
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
                    action: 'transistor_get_episodes',
                    nonce: transistorData.nonce,
                    show_id: selectedShowId,
                    page: page.toString(),
                    per_page: perPage
                });

                const response = await fetch(`${transistorData.ajaxUrl}?${params.toString()}`);
                const result = await response.json();

                if (result.success) {
                    const episodesData = result.data.data || [];
                    const meta = result.data.meta || {};

                    setEpisodes(prev => [...prev, ...episodesData]);
                    setHasMorePages(meta.currentPage < meta.totalPages);
                    setCurrentPage(meta.currentPage);
                } else {
                    setError(result.data.message || __('Failed to load episodes', 'podloom'));
                }
            } catch (err) {
                setError(__('Error loading episodes', 'podloom'));
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
                formData.append('action', 'transistor_get_rss_episodes');
                formData.append('nonce', transistorData.nonce);
                formData.append('feed_id', feedId);
                formData.append('page', page.toString());
                formData.append('per_page', perPage);

                const response = await fetch(transistorData.ajaxUrl, {
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
                    setError(result.data.message || __('Failed to load RSS episodes', 'podloom'));
                }
            } catch (err) {
                setError(__('Error loading RSS episodes', 'podloom'));
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
                formData.append('action', 'transistor_get_rss_typography');
                formData.append('nonce', transistorData.nonce);

                const response = await fetch(transistorData.ajaxUrl, {
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
                formData.append('action', 'transistor_get_rss_episodes');
                formData.append('nonce', transistorData.nonce);
                formData.append('feed_id', feedId);
                formData.append('page', '1');
                formData.append('per_page', '1');

                const response = await fetch(transistorData.ajaxUrl, {
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
         * Render RSS episode with typography
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

            // Check if minimal styling mode is enabled
            const minimalStyling = typo.minimal_styling || false;

            const styles = {
                container: minimalStyling ? {} : {
                    background: typo.background_color || '#f9f9f9',
                    border: '1px solid #ddd',
                    borderRadius: '8px',
                    padding: '20px'
                },
                wrapper: {
                    display: 'flex',
                    gap: '20px',
                    alignItems: 'flex-start'
                },
                artwork: {
                    flexShrink: 0,
                    width: '200px'
                },
                artworkImage: {
                    width: '100%',
                    height: 'auto',
                    borderRadius: '4px',
                    display: 'block'
                },
                content: {
                    flex: 1,
                    minWidth: 0
                },
                title: minimalStyling ? { margin: '0 0 10px 0' } : {
                    margin: '0 0 10px 0',
                    fontFamily: typo.title.font_family || 'inherit',
                    fontSize: typo.title.font_size || '24px',
                    lineHeight: typo.title.line_height || '1.3',
                    color: typo.title.color || '#000000',
                    fontWeight: typo.title.font_weight || '600'
                },
                meta: {
                    display: 'flex',
                    gap: '15px',
                    marginBottom: '15px'
                },
                date: minimalStyling ? {} : {
                    fontFamily: typo.date.font_family || 'inherit',
                    fontSize: typo.date.font_size || '14px',
                    lineHeight: typo.date.line_height || '1.5',
                    color: typo.date.color || '#666666',
                    fontWeight: typo.date.font_weight || 'normal'
                },
                duration: minimalStyling ? {} : {
                    fontFamily: typo.duration.font_family || 'inherit',
                    fontSize: typo.duration.font_size || '14px',
                    lineHeight: typo.duration.line_height || '1.5',
                    color: typo.duration.color || '#666666',
                    fontWeight: typo.duration.font_weight || 'normal'
                },
                description: minimalStyling ? {} : {
                    fontFamily: typo.description.font_family || 'inherit',
                    fontSize: typo.description.font_size || '16px',
                    lineHeight: typo.description.line_height || '1.6',
                    color: typo.description.color || '#333333',
                    fontWeight: typo.description.font_weight || 'normal'
                },
                audio: {
                    width: '100%',
                    marginBottom: '15px'
                }
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
                    className: 'rss-episode-artwork',
                    style: styles.artwork
                }, [
                    wp.element.createElement('img', {
                        key: 'artwork-img',
                        src: episode.image,
                        alt: episode.title,
                        style: styles.artworkImage
                    })
                ]));
            }

            const contentChildren = [];

            // Title (if enabled and available)
            if (display.title && episode.title) {
                contentChildren.push(wp.element.createElement('h3', {
                    key: 'title',
                    className: 'rss-episode-title',
                    style: styles.title
                }, episode.title));
            }

            // Meta information (date and duration)
            const metaChildren = [];
            if (display.date && episode.date) {
                metaChildren.push(wp.element.createElement('span', {
                    key: 'date',
                    className: 'rss-episode-date',
                    style: styles.date
                }, formatDate(episode.date)));
            }
            if (display.duration && episode.duration) {
                metaChildren.push(wp.element.createElement('span', {
                    key: 'duration',
                    className: 'rss-episode-duration',
                    style: styles.duration
                }, formatDuration(episode.duration)));
            }
            if (metaChildren.length > 0) {
                contentChildren.push(wp.element.createElement('div', {
                    key: 'meta',
                    className: 'rss-episode-meta',
                    style: styles.meta
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
                    __('Your browser does not support the audio player.', 'podloom')
                ];

                contentChildren.push(wp.element.createElement('audio', {
                    key: 'audio',
                    className: audioClass,
                    controls: true,
                    preload: 'metadata',
                    style: styles.audio
                }, audioChildren));
            }

            // Description (if enabled and available)
            if (display.description && episode.description) {
                contentChildren.push(wp.element.createElement('div', {
                    key: 'description',
                    className: 'rss-episode-description',
                    style: styles.description,
                    dangerouslySetInnerHTML: { __html: episode.description }
                }));
            }

            // Content wrapper
            if (contentChildren.length > 0) {
                wrapperChildren.push(wp.element.createElement('div', {
                    key: 'content',
                    className: 'rss-episode-content',
                    style: styles.content
                }, contentChildren));
            }

            // Wrapper with flexbox layout
            const wrapper = wp.element.createElement('div', {
                key: 'wrapper',
                className: 'rss-episode-wrapper',
                style: styles.wrapper
            }, wrapperChildren);

            return wp.element.createElement('div', {
                className: 'wp-block-transistor-episode-player rss-episode-player',
                style: styles.container
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
                        duration: episode.attributes.duration
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
                { label: __('-- Select a source --', 'podloom'), value: '' }
            ];

            if (transistorShows.length > 0) {
                options.push({
                    label: __('━━ Transistor.fm ━━', 'podloom'),
                    value: '__transistor_header__',
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
                    label: __('━━ RSS Feeds ━━', 'podloom'),
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
                    { icon: 'microphone', label: __('PodLoom Podcast Episode', 'podloom') },
                    wp.element.createElement(Spinner),
                    wp.element.createElement('p', null, __('Loading sources...', 'podloom'))
                )
            );
        }

        if (!transistorData.hasApiKey && transistorShows.length === 0 && rssFeeds.length === 0) {
            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('PodLoom Podcast Episode', 'podloom') },
                    wp.element.createElement('p', null, __('Please configure your Transistor API key or add RSS feeds in the settings.', 'podloom')),
                    wp.element.createElement(
                        Button,
                        {
                            variant: 'primary',
                            href: `${transistorData.ajaxUrl.replace('/wp-admin/admin-ajax.php', '')}/wp-admin/admin.php?page=transistor-api-settings`
                        },
                        __('Go to Settings', 'podloom')
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
                    { title: __('Episode Settings', 'podloom'), initialOpen: true },
                    wp.element.createElement(SelectControl, {
                        label: __('Select Source', 'podloom'),
                        value: getCurrentSourceValue(),
                        options: buildSourceOptions(),
                        onChange: handleSourceChange,
                        help: __('Choose a Transistor show or RSS feed', 'podloom')
                    }),
                    (showId || rssFeedId) && displayMode === 'specific' && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', marginBottom: '16px' } },
                        loading && episodes.length === 0 ? wp.element.createElement(
                            'div',
                            { style: { marginTop: '8px' } },
                            wp.element.createElement(Spinner),
                            wp.element.createElement('p', null, __('Loading episodes...', 'podloom'))
                        ) : wp.element.createElement(ComboboxControl, {
                            label: __('Search and Select Episode', 'podloom'),
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
                                        ? __('Loading more episodes...', 'podloom')
                                        : __('Load More Episodes...', 'podloom'),
                                    value: '__load_more__'
                                }] : [])
                            ],
                            help: episodes.length > 0
                                ? __('Type to search episodes, or scroll to browse', 'podloom')
                                : null
                        })
                    ),
                    (showId || rssFeedId) && wp.element.createElement(RadioControl, {
                        label: __('Display Mode', 'podloom'),
                        selected: displayMode,
                        options: [
                            { label: __('Specific Episode', 'podloom'), value: 'specific' },
                            ...(supportsLatest ? [
                                { label: __('Latest Episode', 'podloom'), value: 'latest' }
                            ] : []),
                            ...(supportsLatestAndPlaylist ? [
                                { label: __('Playlist', 'podloom'), value: 'playlist' }
                            ] : [])
                        ],
                        onChange: (value) => {
                            setAttributes({ displayMode: value });
                        },
                        help: displayMode === 'latest'
                            ? __('Will always show the most recent episode from this source', 'podloom')
                            : displayMode === 'playlist'
                            ? __('Displays a playlist of episodes. Episode count is controlled in your Transistor settings.', 'podloom')
                            : null
                    }),
                    displayMode === 'playlist' && NumberControl && wp.element.createElement(NumberControl, {
                        label: __('Playlist Height (px)', 'podloom'),
                        value: playlistHeight,
                        onChange: (value) => setAttributes({ playlistHeight: parseInt(value) || 390 }),
                        min: 200,
                        max: 1000,
                        step: 10,
                        help: __('Adjust the height of the playlist player (200-1000px)', 'podloom')
                    }),
                    sourceType === 'transistor' && wp.element.createElement(RadioControl, {
                        label: __('Player Theme', 'podloom'),
                        selected: theme,
                        options: [
                            { label: __('Light', 'podloom'), value: 'light' },
                            { label: __('Dark', 'podloom'), value: 'dark' }
                        ],
                        onChange: handleThemeChange
                    }),
                    displayMode === 'specific' && episodeId && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', padding: '12px', background: '#f0f0f0', borderRadius: '4px' } },
                        wp.element.createElement('strong', null, __('Selected Episode:', 'podloom')),
                        wp.element.createElement('p', { style: { margin: '8px 0 0 0', fontSize: '13px' } }, episodeTitle),
                        sourceType && wp.element.createElement('p', {
                            style: { margin: '4px 0 0 0', fontSize: '11px', color: '#666' }
                        }, __('Source: ', 'podloom') + (sourceType === 'transistor' ? 'Transistor.fm' : 'RSS Feed'))
                    ),
                    displayMode === 'latest' && (showId || rssFeedId) && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', padding: '12px', background: '#e7f5ff', borderRadius: '4px', border: '1px solid #1e88e5' } },
                        wp.element.createElement('strong', null, __('Latest Episode Mode', 'podloom')),
                        wp.element.createElement('p', { style: { margin: '8px 0 0 0', fontSize: '13px' } }, __('This block will always display the most recent episode from ', 'podloom') + showTitle)
                    ),
                    displayMode === 'playlist' && showId && wp.element.createElement(
                        'div',
                        { style: { marginTop: '16px', padding: '12px', background: '#f3e5f5', borderRadius: '4px', border: '1px solid #9c27b0' } },
                        wp.element.createElement('strong', null, __('Playlist Mode', 'podloom')),
                        wp.element.createElement('p', { style: { margin: '8px 0 0 0', fontSize: '13px' } }, __('This block will display a playlist from ', 'podloom') + showTitle + __('. Episode count is controlled in your Transistor settings.', 'podloom'))
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
                    latestRssEpisode && rssTypography
                        ? renderRssEpisode(latestRssEpisode, rssTypography)
                        : wp.element.createElement(
                            'div',
                            { style: { background: '#f9f9f9', border: '1px solid #ddd', borderRadius: '8px', padding: '20px', minHeight: '180px', display: 'flex', flexDirection: 'column', justifyContent: 'center', alignItems: 'center' } },
                            wp.element.createElement('span', {
                                className: 'dashicons dashicons-rss',
                                style: { fontSize: '48px', color: '#f8981d', marginBottom: '10px' }
                            }),
                            wp.element.createElement('p', { style: { margin: '0', fontSize: '14px', color: '#666', textAlign: 'center', fontWeight: '600' } }, __('Loading Latest RSS Episode...', 'podloom'))
                        )
                ) :
                // Specific Episode Mode - RSS
                displayMode === 'specific' && sourceType === 'rss' && episodeId && rssEpisodeData ? (
                    rssTypography
                        ? renderRssEpisode(rssEpisodeData, rssTypography)
                        : wp.element.createElement(
                            'div',
                            { style: { padding: '20px', background: '#f9f9f9', border: '1px solid #ddd', borderRadius: '8px' } },
                            wp.element.createElement('p', { style: { margin: '0', fontSize: '14px', color: '#666' } }, __('Loading episode...', 'podloom'))
                        )
                ) :
                // Placeholder
                wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('PodLoom Podcast Episode', 'podloom') },
                    !showId && !rssFeedId ? wp.element.createElement('p', null, __('Please select a source from the sidebar.', 'podloom')) :
                    displayMode === 'specific' && !episodeId ? wp.element.createElement('p', null, __('Please select an episode from the sidebar.', 'podloom')) :
                    wp.element.createElement('p', null, __('Please configure the block settings.', 'podloom'))
                )
            )
        );
    },
    save: function() {
        return null;
    }
});
