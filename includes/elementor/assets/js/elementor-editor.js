/**
 * PodLoom Elementor Editor Script
 *
 * Handles dynamic episode loading and control updates in the Elementor editor.
 *
 * @package PodLoom
 * @since 2.8.0
 */

(function ($) {
    'use strict';

    // Store loaded episodes per source to avoid redundant API calls.
    const episodesCache = {};

    // Store current pagination state per source.
    const paginationState = {};

    /**
     * Initialize PodLoom Elementor integration
     */
    function init() {
        // Listen for Elementor panel open.
        elementor.hooks.addAction('panel/open_editor/widget/podloom-episode', function (panel, model, view) {
            setupWidgetControls(panel, model, view);
        });
    }

    /**
     * Setup widget controls and event listeners
     */
    function setupWidgetControls(panel, model, view) {
        const $panel = panel.$el;

        // Wait for controls to be rendered.
        setTimeout(function () {
            // Get current settings.
            const settings = model.get('settings');
            const source = settings.get('source');

            // Setup source change listener.
            setupSourceChangeListener(panel, model, view);

            // Setup display mode change listener.
            setupDisplayModeListener(panel, model, view);

            // Setup theme change listener.
            setupThemeChangeListener(panel, model, view);

            // If source is already selected, load episodes.
            if (source && settings.get('display_mode') === 'specific') {
                loadEpisodesForSource(source, panel, model, view);
            }

            // Update show slug if needed.
            if (source && source.startsWith('transistor:')) {
                updateShowSlug(source, model);
            }

            // Update display mode options based on source.
            updateDisplayModeOptions(source, panel);
        }, 100);
    }

    /**
     * Update display mode dropdown options based on source type
     */
    function updateDisplayModeOptions(source, panel) {
        const $displayModeControl = panel.$el.find('[data-setting="display_mode"]');
        if (!$displayModeControl.length) {
            return;
        }

        const $playlistOption = $displayModeControl.find('option[value="playlist"]');

        if (source && source.startsWith('rss:')) {
            // Hide playlist option for RSS sources.
            $playlistOption.hide();

            // If playlist was selected, reset to specific.
            if ($displayModeControl.val() === 'playlist') {
                $displayModeControl.val('specific').trigger('change');
            }
        } else {
            // Show playlist option for Transistor sources.
            $playlistOption.show();
        }
    }

    /**
     * Setup listener for source control changes
     */
    function setupSourceChangeListener(panel, model, view) {
        const settings = model.get('settings');

        settings.on('change:source', function (settingsModel, newSource) {
            // Clear episode selection when source changes.
            settingsModel.set('episode_id', '');
            settingsModel.set('episode_data', '');
            settingsModel.set('embed_html', '');

            if (newSource) {
                // Update show slug for Transistor sources.
                if (newSource.startsWith('transistor:')) {
                    updateShowSlug(newSource, model);
                } else {
                    settingsModel.set('show_slug', '');
                    // Reset to specific mode if playlist was selected for RSS.
                    if (settingsModel.get('display_mode') === 'playlist') {
                        settingsModel.set('display_mode', 'specific');
                    }
                }

                // Update display mode options based on source type.
                updateDisplayModeOptions(newSource, panel);

                // Load episodes if in specific mode.
                if (settingsModel.get('display_mode') === 'specific') {
                    loadEpisodesForSource(newSource, panel, model, view);
                }
            }

            // Trigger preview refresh.
            view.render();
        });
    }

    /**
     * Setup listener for display mode changes
     */
    function setupDisplayModeListener(panel, model, view) {
        const settings = model.get('settings');

        settings.on('change:display_mode', function (settingsModel, newMode) {
            const source = settingsModel.get('source');

            if (newMode === 'specific' && source) {
                loadEpisodesForSource(source, panel, model, view);
            }

            // Trigger preview refresh.
            view.render();
        });
    }

    /**
     * Setup listener for theme changes (Transistor only)
     */
    function setupThemeChangeListener(panel, model, view) {
        const settings = model.get('settings');

        settings.on('change:theme', function (settingsModel, newTheme) {
            const source = settingsModel.get('source');
            const episodeId = settingsModel.get('episode_id');

            // Update embed HTML if Transistor episode is selected.
            if (source && source.startsWith('transistor:') && episodeId) {
                updateEmbedHtmlForTheme(source, episodeId, newTheme, model);
            }

            // Trigger preview refresh.
            view.render();
        });
    }

    /**
     * Update show slug from source
     */
    function updateShowSlug(source, model) {
        const sourceParts = source.split(':');
        if (sourceParts[0] !== 'transistor') {
            return;
        }

        const showId = sourceParts[1];
        const sources = podloomElementor.sources || [];

        // Find the show in sources.
        for (let i = 0; i < sources.length; i++) {
            if (sources[i].type === 'transistor' && sources[i].id === showId) {
                model.get('settings').set('show_slug', sources[i].slug || '');
                break;
            }
        }
    }

    /**
     * Load episodes for the selected source
     */
    function loadEpisodesForSource(source, panel, model, view, page = 1, append = false) {
        if (!source) {
            return;
        }

        const sourceParts = source.split(':');
        const sourceType = sourceParts[0];
        const sourceId = sourceParts[1];

        // Check cache first (only for first page).
        const cacheKey = source;
        if (page === 1 && !append && episodesCache[cacheKey]) {
            updateEpisodeControl(episodesCache[cacheKey], panel, model, paginationState[cacheKey]);
            return;
        }

        // Show loading state.
        updateEpisodeControlLoading(panel);

        // Determine AJAX action based on source type.
        const ajaxAction = sourceType === 'transistor' ? 'podloom_get_episodes' : 'podloom_get_rss_episodes';
        const ajaxData = {
            action: ajaxAction,
            nonce: podloomElementor.nonce,
            page: page,
            per_page: 20
        };

        if (sourceType === 'transistor') {
            ajaxData.show_id = sourceId;
        } else {
            ajaxData.feed_id = sourceId;
        }

        $.ajax({
            url: podloomElementor.ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                if (response.success) {
                    let episodes = [];
                    let hasMore = false;
                    let currentPage = page;

                    if (sourceType === 'transistor') {
                        episodes = response.data.data || [];
                        const meta = response.data.meta || {};
                        hasMore = meta.currentPage < meta.totalPages;
                        currentPage = meta.currentPage || page;
                    } else {
                        const rawEpisodes = response.data.episodes || [];
                        // Format RSS episodes to match structure.
                        episodes = rawEpisodes.map(function (ep) {
                            return {
                                id: ep.id,
                                type: 'rss_episode',
                                attributes: {
                                    title: ep.title,
                                    audio_url: ep.audio_url,
                                    image: ep.image,
                                    description: ep.description,
                                    date: ep.date,
                                    duration: ep.duration,
                                    podcast20: ep.podcast20 || null
                                }
                            };
                        });
                        hasMore = response.data.page < response.data.pages;
                        currentPage = response.data.page || page;
                    }

                    // Cache or append results.
                    if (append && episodesCache[cacheKey]) {
                        episodesCache[cacheKey] = episodesCache[cacheKey].concat(episodes);
                    } else {
                        episodesCache[cacheKey] = episodes;
                    }

                    // Store pagination state.
                    paginationState[cacheKey] = {
                        hasMore: hasMore,
                        currentPage: currentPage,
                        sourceType: sourceType
                    };

                    updateEpisodeControl(episodesCache[cacheKey], panel, model, paginationState[cacheKey]);
                } else {
                    updateEpisodeControlError(panel, response.data?.message || podloomElementor.strings.errorLoading);
                }
            },
            error: function () {
                updateEpisodeControlError(panel, podloomElementor.strings.errorLoading);
            }
        });
    }

    /**
     * Get the Select2 control element
     * Elementor's SELECT2 uses a wrapper structure.
     */
    function getSelect2Control(panel) {
        return panel.$el.find('[data-setting="episode_id"]');
    }

    /**
     * Refresh Select2 after updating options
     * Destroys and reinitializes Select2 to reflect new options.
     */
    function refreshSelect2($select) {
        if (!$select.length) {
            return;
        }

        // Check if Select2 is initialized.
        if ($select.hasClass('select2-hidden-accessible')) {
            // Destroy existing Select2 instance.
            $select.select2('destroy');
        }

        // Reinitialize Select2 with Elementor's default settings.
        $select.select2({
            allowClear: true,
            placeholder: podloomElementor.strings.selectEpisode,
            width: '100%',
            dropdownParent: $select.closest('.elementor-control-content')
        });
    }

    /**
     * Update episode control with loading state
     */
    function updateEpisodeControlLoading(panel) {
        const $episodeControl = getSelect2Control(panel);
        if ($episodeControl.length) {
            $episodeControl.empty().append(
                $('<option>', { value: '', text: podloomElementor.strings.loadingEpisodes })
            );
            $episodeControl.prop('disabled', true);
            refreshSelect2($episodeControl);
        }
    }

    /**
     * Update episode control with error state
     */
    function updateEpisodeControlError(panel, message) {
        const $episodeControl = getSelect2Control(panel);
        if ($episodeControl.length) {
            $episodeControl.empty().append(
                $('<option>', { value: '', text: message })
            );
            $episodeControl.prop('disabled', false);
            refreshSelect2($episodeControl);
        }
    }

    /**
     * Update episode control with episodes list
     */
    function updateEpisodeControl(episodes, panel, model, pagination) {
        const $episodeControl = getSelect2Control(panel);
        if (!$episodeControl.length) {
            return;
        }

        const settings = model.get('settings');
        const currentEpisodeId = settings.get('episode_id');

        // Clear existing options.
        $episodeControl.empty();

        // Add default option.
        $episodeControl.append(
            $('<option>', { value: '', text: podloomElementor.strings.selectEpisode })
        );

        if (episodes.length === 0) {
            $episodeControl.empty().append(
                $('<option>', { value: '', text: podloomElementor.strings.noEpisodes })
            );
        } else {
            episodes.forEach(function (episode) {
                const title = episode.attributes.title || 'Untitled';
                const $option = $('<option>', {
                    value: episode.id,
                    text: title
                });
                if (episode.id === currentEpisodeId) {
                    $option.prop('selected', true);
                }
                $episodeControl.append($option);
            });

            // Add "Load More" option if there are more pages.
            if (pagination && pagination.hasMore) {
                $episodeControl.append(
                    $('<option>', {
                        value: '__load_more__',
                        text: podloomElementor.strings.loadMore,
                        css: { fontStyle: 'italic', color: '#666' }
                    })
                );
            }
        }

        $episodeControl.prop('disabled', false);

        // Refresh Select2 to show new options.
        refreshSelect2($episodeControl);

        // Remove existing handlers and setup new one.
        $episodeControl.off('select2:select.podloom change.podloom');

        // Listen for Select2 selection event.
        $episodeControl.on('select2:select.podloom', function (e) {
            const selectedId = e.params.data.id;

            if (selectedId === '__load_more__') {
                // Reset selection and load more.
                $episodeControl.val(currentEpisodeId || '').trigger('change.select2');
                const source = settings.get('source');
                const nextPage = (pagination?.currentPage || 1) + 1;
                loadEpisodesForSource(source, panel, model, null, nextPage, true);
                return;
            }

            // Find selected episode.
            const selectedEpisode = episodes.find(function (ep) {
                return ep.id === selectedId;
            });

            if (selectedEpisode) {
                handleEpisodeSelection(selectedEpisode, model);
            } else {
                // Clear episode data.
                settings.set('episode_id', '');
                settings.set('episode_data', '');
                settings.set('embed_html', '');
            }
        });

        // Also listen for native change as fallback.
        $episodeControl.on('change.podloom', function () {
            const selectedId = $(this).val();

            // Skip if handled by select2:select.
            if (!selectedId) {
                settings.set('episode_id', '');
                settings.set('episode_data', '');
                settings.set('embed_html', '');
            }
        });
    }

    /**
     * Handle episode selection
     */
    function handleEpisodeSelection(episode, model) {
        const settings = model.get('settings');
        const source = settings.get('source');
        const sourceParts = source.split(':');
        const sourceType = sourceParts[0];
        const theme = settings.get('theme') || 'light';

        settings.set('episode_id', episode.id);

        if (sourceType === 'transistor') {
            // Set embed HTML.
            const embedHtml = theme === 'dark'
                ? (episode.attributes.embed_html_dark || episode.attributes.embed_html)
                : episode.attributes.embed_html;
            settings.set('embed_html', embedHtml || '');
            settings.set('episode_data', '');
        } else {
            // Set episode data for RSS.
            const episodeData = {
                id: episode.id,
                title: episode.attributes.title,
                audio_url: episode.attributes.audio_url,
                image: episode.attributes.image,
                description: episode.attributes.description,
                date: episode.attributes.date,
                duration: episode.attributes.duration,
                podcast20: episode.attributes.podcast20 || null
            };
            settings.set('episode_data', JSON.stringify(episodeData));
            settings.set('embed_html', '');

        }
    }

    /**
     * Update embed HTML when theme changes
     */
    function updateEmbedHtmlForTheme(source, episodeId, theme, model) {
        const cacheKey = source;
        const episodes = episodesCache[cacheKey];

        if (!episodes) {
            return;
        }

        const episode = episodes.find(function (ep) {
            return ep.id === episodeId;
        });

        if (episode) {
            const embedHtml = theme === 'dark'
                ? (episode.attributes.embed_html_dark || episode.attributes.embed_html)
                : episode.attributes.embed_html;
            model.get('settings').set('embed_html', embedHtml || '');
        }
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when Elementor editor is ready.
    $(window).on('elementor:init', init);

})(jQuery);
