/**
 * Transistor Episode Block
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
    title: __('Podcast Episode', 'podloom'),
    icon: 'microphone',
    category: 'media',
    attributes: {
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
        const { episodeId, episodeTitle, showId, showTitle, showSlug, embedHtml, theme, displayMode, playlistHeight } = attributes;

        const [shows, setShows] = useState([]);
        const [episodes, setEpisodes] = useState([]);
        const [loading, setLoading] = useState(false);
        const [loadingShows, setLoadingShows] = useState(true);
        const [error, setError] = useState('');
        const [currentPage, setCurrentPage] = useState(1);
        const [hasMorePages, setHasMorePages] = useState(false);
        const [isLoadingMore, setIsLoadingMore] = useState(false);

        const blockProps = useBlockProps();

        // Load shows on mount
        useEffect(() => {
            loadShows();
        }, []);

        // Set default show if available and no show is selected
        useEffect(() => {
            if (shows.length > 0 && !showId && transistorData.defaultShow) {
                const defaultShow = shows.find(show => show.id === transistorData.defaultShow);
                if (defaultShow) {
                    setAttributes({
                        showId: defaultShow.id,
                        showTitle: defaultShow.attributes.title,
                        showSlug: defaultShow.attributes.slug
                    });
                }
            }
        }, [shows, showId]);

        // Load episodes when show changes
        useEffect(() => {
            if (showId && displayMode === 'specific') {
                setCurrentPage(1);
                setEpisodes([]);
                loadEpisodes(showId, 1);
            }
        }, [showId, displayMode]);

        /**
         * Load shows from API
         */
        const loadShows = async () => {
            setLoadingShows(true);
            setError('');

            try {
                const response = await fetch(
                    `${transistorData.ajaxUrl}?action=transistor_get_shows&nonce=${transistorData.nonce}`
                );

                const result = await response.json();

                if (result.success) {
                    setShows(result.data.data || []);
                } else {
                    setError(result.data.message || __('Failed to load shows', 'podloom'));
                }
            } catch (err) {
                setError(__('Error loading shows. Please check your API key.', 'podloom'));
            } finally {
                setLoadingShows(false);
            }
        };

        /**
         * Load episodes from API (20 per page, accumulated)
         */
        const loadEpisodes = async (selectedShowId, page = 1, isLoadMore = false) => {
            if (isLoadMore) {
                setIsLoadingMore(true);
            } else {
                setLoading(true);
            }
            setError('');

            try {
                const params = new URLSearchParams({
                    action: 'transistor_get_episodes',
                    nonce: transistorData.nonce,
                    show_id: selectedShowId,
                    page: page.toString(),
                    per_page: '20'
                });

                const response = await fetch(`${transistorData.ajaxUrl}?${params.toString()}`);
                const result = await response.json();

                if (result.success) {
                    const episodesData = result.data.data || [];
                    const meta = result.data.meta || {};

                    // Always accumulate episodes
                    setEpisodes(prev => [...prev, ...episodesData]);

                    // Check if there are more pages
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
         * Load more episodes
         */
        const loadMoreEpisodes = () => {
            const nextPage = currentPage + 1;
            loadEpisodes(showId, nextPage, true);
        };

        /**
         * Handle episode selection
         */
        const selectEpisode = (episode) => {
            const html = theme === 'dark' ? episode.attributes.embed_html_dark : episode.attributes.embed_html;

            setAttributes({
                episodeId: episode.id,
                episodeTitle: episode.attributes.title,
                embedHtml: html
            });
        };

        /**
         * Handle show change
         */
        const handleShowChange = (newShowId) => {
            const selectedShow = shows.find(show => show.id === newShowId);
            setAttributes({
                showId: newShowId,
                showTitle: selectedShow ? selectedShow.attributes.title : '',
                showSlug: selectedShow ? selectedShow.attributes.slug : '',
                episodeId: '',
                episodeTitle: '',
                embedHtml: ''
            });
            setEpisodes([]);
        };

        /**
         * Handle theme change
         */
        const handleThemeChange = (newTheme) => {
            setAttributes({ theme: newTheme });

            // If an episode is already selected, update the embed HTML
            if (episodeId && episodes.length > 0) {
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
         * Render the block
         */
        if (loadingShows) {
            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('Podcast Episode', 'podloom') },
                    wp.element.createElement(Spinner),
                    wp.element.createElement('p', null, __('Loading shows...', 'podloom'))
                )
            );
        }

        if (!transistorData.hasApiKey) {
            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('Podcast Episode', 'podloom') },
                    wp.element.createElement('p', null, __('Please configure your Transistor API key in the settings.', 'podloom')),
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

        if (shows.length === 0) {
            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('Podcast Episode', 'podloom') },
                    wp.element.createElement('p', null, __('No shows found. Please check your API key.', 'podloom'))
                )
            );
        }

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
                        label: __('Select Show', 'podloom'),
                        value: showId,
                        options: [
                            { label: __('-- Select a show --', 'podloom'), value: '' },
                            ...shows.map(show => ({
                                label: show.attributes.title,
                                value: show.id
                            }))
                        ],
                        onChange: handleShowChange
                    }),
                    showId && displayMode === 'specific' && wp.element.createElement(
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
                                    setAttributes({ episodeId: '', episodeTitle: '', embedHtml: '' });
                                }
                            },
                            options: [
                                ...episodes.map(episode => {
                                    const status = episode.attributes.status;
                                    const statusLabel = status === 'draft' ? ' (Draft)' : status === 'scheduled' ? ' (Scheduled)' : '';
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
                    showId && wp.element.createElement(RadioControl, {
                        label: __('Display Mode', 'podloom'),
                        selected: displayMode,
                        options: [
                            { label: __('Specific Episode', 'podloom'), value: 'specific' },
                            { label: __('Latest Episode', 'podloom'), value: 'latest' },
                            { label: __('Playlist', 'podloom'), value: 'playlist' }
                        ],
                        onChange: (value) => setAttributes({ displayMode: value }),
                        help: displayMode === 'latest'
                            ? __('Will always show the most recent episode from this show', 'podloom')
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
                    wp.element.createElement(RadioControl, {
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
                        wp.element.createElement('p', { style: { margin: '8px 0 0 0', fontSize: '13px' } }, episodeTitle)
                    ),
                    displayMode === 'latest' && showId && wp.element.createElement(
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
                displayMode === 'latest' && showId && showSlug ? wp.element.createElement('div', {
                    dangerouslySetInnerHTML: {
                        __html: '<iframe width="100%" height="180" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/' + encodeURIComponent(showSlug) + '/' + (theme === 'dark' ? 'latest/dark' : 'latest') + '"></iframe>'
                    }
                }) : displayMode === 'playlist' && showId && showSlug ? wp.element.createElement('div', {
                    dangerouslySetInnerHTML: {
                        __html: '<iframe width="100%" height="' + parseInt(playlistHeight || 390) + '" frameborder="no" scrolling="no" seamless src="https://share.transistor.fm/e/' + encodeURIComponent(showSlug) + '/' + (theme === 'dark' ? 'playlist/dark' : 'playlist') + '"></iframe>'
                    }
                }) : displayMode === 'specific' && episodeId && embedHtml ? wp.element.createElement('div', {
                    dangerouslySetInnerHTML: { __html: embedHtml }
                }) : wp.element.createElement(
                    Placeholder,
                    { icon: 'microphone', label: __('Podcast Episode', 'podloom') },
                    !showId ? wp.element.createElement('p', null, __('Please select a show from the sidebar.', 'podloom')) :
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
